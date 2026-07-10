<?php
/**
 * Widget dashboard WordPress — riepilogo negozio e richieste clienti
 *
 * Due widget nella dashboard nativa di wp-admin (non nella pagina Analytics
 * di WooCommerce): vendite/ordini con mini-grafico a 7 giorni, e stato delle
 * richieste clienti (ticket, resi, RMA, preventivi) con attività recente.
 *
 * I conteggi su ticket/resi/preventivi richiedono una scansione ordini/utenti
 * (stessi dati sono meta, non un CPT interrogabile) — stesso costo già
 * accettato da shopforge_admin_support_page(). Qui però il risultato è
 * cache-ato in un transient per non ricalcolarlo ad ogni caricamento
 * della dashboard.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// AGGREGAZIONE DATI — vendite
// =============================================================================

function shopforge_get_dashboard_sales_stats(): array {
	$cached = get_transient( 'shopforge_dash_sales' );
	if ( false !== $cached ) return $cached;

	$paid_statuses = [ 'processing', 'completed', 'spedito' ];
	$tz            = wp_timezone();
	$today_start   = new DateTime( 'today', $tz );
	$week_start    = ( clone $today_start )->modify( '-6 days' );
	$month_start   = new DateTime( 'first day of this month', $tz );

	$orders = wc_get_orders( [
		'limit'        => -1,
		'status'       => $paid_statuses,
		'date_created' => '>=' . min( $week_start->getTimestamp(), $month_start->getTimestamp() ),
		'return'       => 'objects',
	] );

	$stats = [
		'orders_today'  => 0,
		'orders_week'   => 0,
		'orders_month'  => 0,
		'revenue_week'  => 0.0,
		'revenue_month' => 0.0,
		'daily_orders'  => array_fill( 0, 7, 0 ), // indice 0 = 6 giorni fa, indice 6 = oggi
	];

	foreach ( $orders as $order ) {
		$created_dt = $order->get_date_created(); // WC_DateTime, già confrontabile con DateTime
		if ( ! $created_dt ) continue;
		$created_dt->setTimezone( $tz );

		$total = (float) $order->get_total();

		if ( $created_dt >= $month_start ) {
			$stats['orders_month']++;
			$stats['revenue_month'] += $total;
		}
		if ( $created_dt >= $week_start ) {
			$stats['orders_week']++;
			$stats['revenue_week'] += $total;

			$day_index = 6 - (int) $today_start->diff( $created_dt )->days;
			if ( $day_index >= 0 && $day_index <= 6 ) {
				$stats['daily_orders'][ $day_index ]++;
			}
		}
		if ( $created_dt >= $today_start ) {
			$stats['orders_today']++;
		}
	}

	set_transient( 'shopforge_dash_sales', $stats, 10 * MINUTE_IN_SECONDS );
	return $stats;
}


// =============================================================================
// AGGREGAZIONE DATI — richieste clienti
// =============================================================================

/**
 * Conteggi + attività recente per il widget. Costruita sopra
 * shopforge_get_all_customer_requests() (definita in
 * shopforge-admin-messages.php, stessa fonte usata dalla pagina "Messaggi")
 * così i due punti di vista non scansionano ordini/utenti due volte.
 */
function shopforge_get_dashboard_requests_summary(): array {
	$cached = get_transient( 'shopforge_dash_requests' );
	if ( false !== $cached ) return $cached;

	$summary = [
		'open_tickets'   => 0,
		'open_returns'   => 0,
		'open_rma'       => 0,
		'pending_quotes' => 0,
		'recent'         => [],
	];

	if ( function_exists( 'shopforge_get_all_customer_requests' ) ) {
		$all = shopforge_get_all_customer_requests();

		foreach ( $all as $item ) {
			if ( ! $item['is_open'] ) continue;
			switch ( $item['type'] ) {
				case 'ticket': $summary['open_tickets']++; break;
				case 'return': $summary['open_returns']++; break;
				case 'rma':    $summary['open_rma']++; break;
				case 'quote':  $summary['pending_quotes']++; break;
			}
		}

		// $all è già ordinato per data decrescente
		foreach ( array_slice( $all, 0, 6 ) as $item ) {
			$summary['recent'][] = [
				'type' => $item['type'],
				'date' => $item['date'],
				'text' => $item['ref'] . ( $item['text'] ? ' — ' . $item['text'] : '' ),
				'url'  => $item['url'],
			];
		}
	}

	set_transient( 'shopforge_dash_requests', $summary, 10 * MINUTE_IN_SECONDS );
	return $summary;
}

/** Invalida tutte le cache condivise — richiamata sugli eventi che cambiano i numeri. */
function shopforge_dashboard_flush_cache(): void {
	delete_transient( 'shopforge_dash_sales' );
	delete_transient( 'shopforge_dash_requests' );
	delete_transient( 'shopforge_all_requests' );
}
add_action( 'woocommerce_order_status_changed', 'shopforge_dashboard_flush_cache' );
add_action( 'shopforge_ticket_submitted', 'shopforge_dashboard_flush_cache' );
add_action( 'shopforge_rma_status_changed', 'shopforge_dashboard_flush_cache' );
add_action( 'shopforge_rma_submitted', 'shopforge_dashboard_flush_cache' );


// =============================================================================
// REGISTRAZIONE WIDGET
// =============================================================================

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) return;
	if ( ! class_exists( 'WooCommerce' ) ) return;

	wp_add_dashboard_widget( 'shopforge_dash_sales', __( 'ShopForge — Sales', 'shopforge' ), 'shopforge_render_dashboard_sales_widget' );
	wp_add_dashboard_widget( 'shopforge_dash_requests', __( 'ShopForge — Customer Requests', 'shopforge' ), 'shopforge_render_dashboard_requests_widget' );
} );

function shopforge_render_dashboard_sales_widget(): void {
	$s = shopforge_get_dashboard_sales_stats();
	$max_day = max( 1, max( $s['daily_orders'] ) );
	?>
	<div class="shopforge-dash-stats">
		<div class="shopforge-dash-stat">
			<span class="shopforge-dash-stat__value"><?php echo esc_html( number_format_i18n( $s['orders_today'] ) ); ?></span>
			<span class="shopforge-dash-stat__label"><?php esc_html_e( 'Orders today', 'shopforge' ); ?></span>
		</div>
		<div class="shopforge-dash-stat">
			<span class="shopforge-dash-stat__value"><?php echo esc_html( number_format_i18n( $s['orders_week'] ) ); ?></span>
			<span class="shopforge-dash-stat__label"><?php esc_html_e( 'Orders (7 days)', 'shopforge' ); ?></span>
		</div>
		<div class="shopforge-dash-stat">
			<span class="shopforge-dash-stat__value"><?php echo wp_kses_post( wc_price( $s['revenue_week'] ) ); ?></span>
			<span class="shopforge-dash-stat__label"><?php esc_html_e( 'Revenue (7 days)', 'shopforge' ); ?></span>
		</div>
		<div class="shopforge-dash-stat">
			<span class="shopforge-dash-stat__value"><?php echo wp_kses_post( wc_price( $s['revenue_month'] ) ); ?></span>
			<span class="shopforge-dash-stat__label"><?php esc_html_e( 'Revenue (this month)', 'shopforge' ); ?></span>
		</div>
	</div>

	<div class="shopforge-dash-chart" role="img" aria-label="<?php esc_attr_e( 'Orders per day, last 7 days', 'shopforge' ); ?>">
		<?php foreach ( $s['daily_orders'] as $i => $count ) :
			$day_label = date_i18n( 'D', strtotime( "-" . ( 6 - $i ) . " days" ) );
			$height    = max( 4, round( $count / $max_day * 60 ) );
		?>
		<div class="shopforge-dash-bar">
			<span class="shopforge-dash-bar__count"><?php echo esc_html( $count ); ?></span>
			<span class="shopforge-dash-bar__fill" style="height:<?php echo esc_attr( $height ); ?>px"></span>
			<span class="shopforge-dash-bar__day"><?php echo esc_html( $day_label ); ?></span>
		</div>
		<?php endforeach; ?>
	</div>

	<p class="shopforge-dash-footer">
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>"><?php esc_html_e( 'View all orders', 'shopforge' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ) ); ?>"><?php esc_html_e( 'Full analytics', 'shopforge' ); ?></a>
	</p>
	<?php
	shopforge_dashboard_widget_styles();
}

function shopforge_render_dashboard_requests_widget(): void {
	$s = shopforge_get_dashboard_requests_summary();

	// I ticket sono una funzionalità core (order-tracker), sempre disponibile;
	// resi, RMA e preventivi sono moduli opzionali — mostra il relativo badge
	// solo se il modulo è attivo, altrimenti il link porta a una pagina/CPT
	// non registrato (es. edit.php?post_type=shopforge_rma_request va in
	// errore se il modulo RMA è disattivato).
	$badges = [
		[ 'count' => $s['open_tickets'], 'label' => __( 'Open tickets', 'shopforge' ), 'url' => admin_url( 'admin.php?page=shopforge-support&tab=tickets' ) ],
	];
	if ( function_exists( 'shopforge_is_module_active' ) && shopforge_is_module_active( 'returns' ) ) {
		$badges[] = [ 'count' => $s['open_returns'], 'label' => __( 'Open withdrawals', 'shopforge' ), 'url' => admin_url( 'admin.php?page=shopforge-support&tab=returns' ) ];
	}
	if ( function_exists( 'shopforge_is_module_active' ) && shopforge_is_module_active( 'rma' ) ) {
		$badges[] = [ 'count' => $s['open_rma'], 'label' => __( 'Open RMA', 'shopforge' ), 'url' => admin_url( 'edit.php?post_type=shopforge_rma_request' ) ];
	}
	if ( function_exists( 'shopforge_is_module_active' ) && shopforge_is_module_active( 'quotes' ) ) {
		$badges[] = [ 'count' => $s['pending_quotes'], 'label' => __( 'Pending quotes', 'shopforge' ), 'url' => admin_url( 'admin.php?page=shopforge-quotes' ) ];
	}
	$type_labels = [
		'ticket' => __( 'Ticket', 'shopforge' ),
		'return' => __( 'Withdrawal', 'shopforge' ),
		'rma'    => __( 'RMA', 'shopforge' ),
		'quote'  => __( 'Quote', 'shopforge' ),
	];
	?>
	<div class="shopforge-dash-badges">
		<?php foreach ( $badges as $b ) : ?>
		<a href="<?php echo esc_url( $b['url'] ); ?>" class="shopforge-dash-badge <?php echo $b['count'] ? 'has-open' : ''; ?>">
			<span class="shopforge-dash-badge__count"><?php echo esc_html( $b['count'] ); ?></span>
			<span class="shopforge-dash-badge__label"><?php echo esc_html( $b['label'] ); ?></span>
		</a>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $s['recent'] ) ) : ?>
	<ul class="shopforge-dash-recent">
		<?php foreach ( $s['recent'] as $item ) :
			$type_label = $type_labels[ $item['type'] ] ?? '';
			$date       = $item['date'] ? date_i18n( get_option( 'date_format' ), strtotime( $item['date'] ) ) : '';
		?>
		<li>
			<a href="<?php echo esc_url( $item['url'] ); ?>">
				<span class="shopforge-dash-recent__type"><?php echo esc_html( $type_label ); ?></span>
				<span class="shopforge-dash-recent__text"><?php echo esc_html( wp_trim_words( $item['text'], 8 ) ); ?></span>
				<span class="shopforge-dash-recent__date"><?php echo esc_html( $date ); ?></span>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else : ?>
	<p class="shopforge-dash-empty"><?php esc_html_e( 'No customer requests yet.', 'shopforge' ); ?></p>
	<?php endif; ?>
	<?php
	shopforge_dashboard_widget_styles();
}

/** CSS inline, emesso una sola volta per pagina anche se entrambi i widget sono presenti. */
function shopforge_dashboard_widget_styles(): void {
	static $printed = false;
	if ( $printed ) return;
	$printed = true;
	?>
	<style>
	.shopforge-dash-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px; }
	.shopforge-dash-stat { background: #f6f7f7; border-radius: 6px; padding: 10px 12px; }
	.shopforge-dash-stat__value { display: block; font-size: 18px; font-weight: 700; color: #1d2327; }
	.shopforge-dash-stat__label { display: block; font-size: 11px; color: #646970; margin-top: 2px; }

	.shopforge-dash-chart { display: flex; align-items: flex-end; gap: 8px; height: 100px; padding: 0 4px; margin-bottom: 8px; }
	.shopforge-dash-bar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
	.shopforge-dash-bar__count { font-size: 11px; color: #646970; margin-bottom: 2px; }
	.shopforge-dash-bar__fill { width: 100%; max-width: 24px; background: #2271b1; border-radius: 3px 3px 0 0; }
	.shopforge-dash-bar__day { font-size: 10px; color: #8c8f94; margin-top: 4px; text-transform: uppercase; }

	.shopforge-dash-footer { display: flex; gap: 16px; font-size: 12px; margin: 8px 0 0; }

	.shopforge-dash-badges { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 14px; }
	.shopforge-dash-badge {
		display: flex; align-items: center; gap: 8px;
		padding: 8px 10px; border-radius: 6px;
		background: #f6f7f7; text-decoration: none; color: #1d2327;
	}
	.shopforge-dash-badge.has-open { background: #FEF3C7; }
	.shopforge-dash-badge__count { font-size: 16px; font-weight: 700; }
	.shopforge-dash-badge.has-open .shopforge-dash-badge__count { color: #92400E; }
	.shopforge-dash-badge__label { font-size: 11px; color: #646970; }

	.shopforge-dash-recent { margin: 0; padding: 0; list-style: none; border-top: 1px solid #f0f0f1; }
	.shopforge-dash-recent li { border-bottom: 1px solid #f0f0f1; }
	.shopforge-dash-recent a {
		display: flex; align-items: center; gap: 8px;
		padding: 8px 2px; text-decoration: none; color: #1d2327;
	}
	.shopforge-dash-recent__type {
		flex-shrink: 0; font-size: 10px; font-weight: 700; text-transform: uppercase;
		letter-spacing: .04em; color: #2271b1; background: #f0f6fc;
		padding: 2px 6px; border-radius: 3px;
	}
	.shopforge-dash-recent__text { flex: 1; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.shopforge-dash-recent__date { flex-shrink: 0; font-size: 11px; color: #8c8f94; }

	.shopforge-dash-empty { color: #646970; font-size: 12px; }
	</style>
	<?php
}
