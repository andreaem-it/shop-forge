<?php
/**
 * Pagina admin unificata "Messaggi" — vista aggregata su ticket assistenza,
 * resi/recesso, RMA e preventivi.
 *
 * Non è una migrazione dati: ogni tipologia resta salvata dov'è oggi (meta
 * ordine per ticket/resi, CPT per RMA, meta utente per preventivi) e si
 * gestisce ancora dalla sua schermata originale (metabox ordine, CPT edit,
 * pagina preventivi) — qui c'è solo una vista unica con filtri per non dover
 * saltare tra quattro pagine diverse per capire cosa è aperto.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// AGGREGAZIONE — lista completa, condivisa con i widget dashboard
// =============================================================================

/**
 * Tutte le richieste cliente (ticket, resi, RMA, preventivi) in un formato
 * comune. Cache-ata come i widget dashboard (stesso transient, stessa
 * invalidazione) così le due viste non duplicano la scansione ordini/utenti.
 */
function shopforge_get_all_customer_requests(): array {
	$cached = get_transient( 'shopforge_all_requests' );
	if ( false !== $cached ) return $cached;

	$items      = [];
	$has_module = function_exists( 'shopforge_is_module_active' );

	if ( $has_module ) {
		$orders = wc_get_orders( [ 'limit' => -1, 'return' => 'objects' ] );

		foreach ( $orders as $order ) {
			$customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$email    = $order->get_billing_email();

			foreach ( $order->get_meta( '_shopforge_tickets' ) ?: [] as $t ) {
				$status = $t['status'] ?? 'open';
				$items[] = [
					'type'         => 'ticket',
					'type_label'   => __( 'Ticket', 'shopforge' ),
					'type_icon'    => '📩',
					'status'       => $status,
					'status_label' => 'open' === $status ? __( 'Open', 'shopforge' ) : __( 'Closed', 'shopforge' ),
					'is_open'      => 'open' === $status,
					'customer'     => $customer,
					'email'        => $email,
					/* translators: %s: order number */
					'ref'          => sprintf( __( 'Order #%s', 'shopforge' ), $order->get_order_number() ),
					'text'         => $t['subject'] ?? '',
					'date'         => $t['date'] ?? '',
					'url'          => $order->get_edit_order_url() . '#shopforge-tickets',
				];
			}

			if ( shopforge_is_module_active( 'returns' ) ) {
				foreach ( $order->get_meta( '_shopforge_returns' ) ?: [] as $r ) {
					$status  = $r['status'] ?? 'pending';
					$is_open = ! in_array( $status, [ 'refunded', 'rejected' ], true );
					$items[] = [
						'type'         => 'return',
						'type_label'   => __( 'Withdrawal', 'shopforge' ),
						'type_icon'    => '↩️',
						'status'       => $status,
						'status_label' => shopforge_return_get_status_label_safe( $status ),
						'is_open'      => $is_open,
						'customer'     => $customer,
						'email'        => $email,
						'ref'          => $r['ref'] ?? '',
						'text'         => $r['reason'] ?? '',
						'date'         => $r['date'] ?? '',
						'url'          => $order->get_edit_order_url() . '#shopforge-returns',
					];
				}
			}
		}
	}

	if ( $has_module && shopforge_is_module_active( 'rma' ) && function_exists( 'shopforge_rma_get_open_statuses' ) ) {
		$requests = get_posts( [
			'post_type'      => 'shopforge_rma_request',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		] );
		$open_statuses = shopforge_rma_get_open_statuses();

		foreach ( $requests as $request ) {
			$status     = get_post_meta( $request->ID, '_shopforge_rma_stato', true ) ?: 'aperta';
			$user_id    = (int) get_post_meta( $request->ID, '_shopforge_rma_user_id', true );
			$order_id   = (int) get_post_meta( $request->ID, '_shopforge_rma_order_id', true );
			$user       = $user_id ? get_userdata( $user_id ) : null;
			$order      = $order_id ? wc_get_order( $order_id ) : null;

			$items[] = [
				'type'         => 'rma',
				'type_label'   => __( 'RMA', 'shopforge' ),
				'type_icon'    => '🛠️',
				'status'       => $status,
				'status_label' => function_exists( 'shopforge_rma_get_status_label' ) ? shopforge_rma_get_status_label( $status ) : $status,
				'is_open'      => in_array( $status, $open_statuses, true ),
				'customer'     => $user ? $user->display_name : '',
				'email'        => $user ? $user->user_email : '',
				/* translators: %d: RMA request ID */
				'ref'          => sprintf( __( 'RMA #%d', 'shopforge' ), $request->ID ),
				'text'         => get_post_meta( $request->ID, '_shopforge_rma_descrizione_problema', true ) ?: $request->post_title,
				'date'         => $request->post_date,
				'url'          => admin_url( 'post.php?post=' . $request->ID . '&action=edit' ),
			];
		}
	}

	if ( $has_module && shopforge_is_module_active( 'quotes' ) ) {
		$users = get_users( [ 'meta_key' => '_shopforge_quotes', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
		foreach ( $users as $user ) {
			foreach ( get_user_meta( $user->ID, '_shopforge_quotes', true ) ?: [] as $q ) {
				$status = $q['status'] ?? 'pending';
				$items[] = [
					'type'         => 'quote',
					'type_label'   => __( 'Quote', 'shopforge' ),
					'type_icon'    => '📄',
					'status'       => $status,
					'status_label' => shopforge_quote_get_status_label_safe( $status ),
					'is_open'      => in_array( $status, [ 'pending', 'sent' ], true ),
					'customer'     => $user->display_name,
					'email'        => $user->user_email,
					'ref'          => $q['ref'] ?? '',
					'text'         => $q['notes'] ?? '',
					'date'         => $q['date'] ?? '',
					'url'          => admin_url( 'admin.php?page=shopforge-quotes' ),
				];
			}
		}
	}

	usort( $items, fn( $a, $b ) => strtotime( $b['date'] ?: '1970-01-01' ) - strtotime( $a['date'] ?: '1970-01-01' ) );

	set_transient( 'shopforge_all_requests', $items, 10 * MINUTE_IN_SECONDS );
	return $items;
}

/** shopforge-mod-returns.php non espone una funzione label pubblica: piccola mappa locale. */
function shopforge_return_get_status_label_safe( string $status ): string {
	$labels = [
		'pending'    => __( 'Received', 'shopforge' ),
		'processing' => __( 'Processing', 'shopforge' ),
		'approved'   => __( 'Approved', 'shopforge' ),
		'refunded'   => __( 'Refunded', 'shopforge' ),
		'rejected'   => __( 'Rejected', 'shopforge' ),
	];
	return $labels[ $status ] ?? $status;
}

/** shopforge-mod-quotes.php non espone una funzione label pubblica: piccola mappa locale. */
function shopforge_quote_get_status_label_safe( string $status ): string {
	$labels = [
		'pending'  => __( 'Pending', 'shopforge' ),
		'sent'     => __( 'Sent', 'shopforge' ),
		'accepted' => __( 'Accepted', 'shopforge' ),
		'declined' => __( 'Declined', 'shopforge' ),
		'expired'  => __( 'Expired', 'shopforge' ),
	];
	return $labels[ $status ] ?? $status;
}


// =============================================================================
// MENU + PAGINA
// =============================================================================

add_action( 'admin_menu', function () {
	if ( ! class_exists( 'WooCommerce' ) ) return;
	add_menu_page(
		__( 'Messages', 'shopforge' ),
		'💬 ' . __( 'Messages', 'shopforge' ),
		'edit_shop_orders',
		'shopforge-messages',
		'shopforge_render_messages_page',
		'dashicons-email-alt2',
		56.5
	);
}, 20 );

function shopforge_render_messages_page(): void {
	$all = shopforge_get_all_customer_requests();

	$type_filter   = sanitize_key( $_GET['type'] ?? 'all' );
	$status_filter = sanitize_key( $_GET['status'] ?? 'open' );
	$search        = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$per_page      = 30;

	$counts = [ 'all' => count( $all ) ];
	foreach ( $all as $item ) {
		$counts[ $item['type'] ] = ( $counts[ $item['type'] ] ?? 0 ) + 1;
	}

	$filtered = array_filter( $all, function ( $item ) use ( $type_filter, $status_filter, $search ) {
		if ( 'all' !== $type_filter && $item['type'] !== $type_filter ) return false;
		if ( 'open' === $status_filter && ! $item['is_open'] ) return false;
		if ( 'closed' === $status_filter && $item['is_open'] ) return false;
		if ( $search ) {
			$haystack = $item['customer'] . ' ' . $item['email'] . ' ' . $item['ref'] . ' ' . $item['text'];
			if ( false === stripos( $haystack, $search ) ) return false;
		}
		return true;
	} );

	$total       = count( $filtered );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$page_items  = array_slice( array_values( $filtered ), ( $paged - 1 ) * $per_page, $per_page );

	$types = [
		'all'    => __( 'All', 'shopforge' ),
		'ticket' => __( 'Tickets', 'shopforge' ),
		'return' => __( 'Withdrawals', 'shopforge' ),
		'rma'    => __( 'RMA', 'shopforge' ),
		'quote'  => __( 'Quotes', 'shopforge' ),
	];
	$base_url = admin_url( 'admin.php?page=shopforge-messages' );
	?>
	<div class="wrap shopforge-messages">
		<h1><?php esc_html_e( 'Messages', 'shopforge' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'A single overview of every customer request — support tickets, withdrawal requests, RMA and quotes. Each row links to where it is actually managed.', 'shopforge' ); ?>
		</p>

		<ul class="subsubsub">
			<?php foreach ( $types as $key => $label ) :
				$count = 'all' === $key ? $counts['all'] : ( $counts[ $key ] ?? 0 );
				$url   = add_query_arg( [ 'type' => $key, 'status' => $status_filter, 's' => $search ], $base_url );
			?>
			<li>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo $type_filter === $key ? 'current' : ''; ?>">
					<?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( $count ); ?>)</span>
				</a> |
			</li>
			<?php endforeach; ?>
		</ul>

		<form method="get" class="shopforge-messages__filters">
			<input type="hidden" name="page" value="shopforge-messages">
			<input type="hidden" name="type" value="<?php echo esc_attr( $type_filter ); ?>">
			<select name="status">
				<option value="open" <?php selected( $status_filter, 'open' ); ?>><?php esc_html_e( 'Open only', 'shopforge' ); ?></option>
				<option value="closed" <?php selected( $status_filter, 'closed' ); ?>><?php esc_html_e( 'Closed only', 'shopforge' ); ?></option>
				<option value="any" <?php selected( $status_filter, 'any' ); ?>><?php esc_html_e( 'All statuses', 'shopforge' ); ?></option>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search customer, order, text…', 'shopforge' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'shopforge' ); ?></button>
			<?php if ( $search || 'open' !== $status_filter || 'all' !== $type_filter ) : ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button-link"><?php esc_html_e( 'Reset', 'shopforge' ); ?></a>
			<?php endif; ?>
		</form>

		<?php if ( empty( $page_items ) ) : ?>
		<p><?php esc_html_e( 'No requests match this filter.', 'shopforge' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:90px"><?php esc_html_e( 'Type', 'shopforge' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'shopforge' ); ?></th>
					<th><?php esc_html_e( 'Reference', 'shopforge' ); ?></th>
					<th><?php esc_html_e( 'Message', 'shopforge' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Status', 'shopforge' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page_items as $item ) : ?>
				<tr class="<?php echo $item['is_open'] ? 'shopforge-msg-row--open' : ''; ?>">
					<td><?php echo esc_html( $item['type_icon'] ); ?> <?php echo esc_html( $item['type_label'] ); ?></td>
					<td>
						<?php echo esc_html( $item['customer'] ?: '—' ); ?>
						<?php if ( $item['email'] ) : ?><br><small><?php echo esc_html( $item['email'] ); ?></small><?php endif; ?>
					</td>
					<td><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['ref'] ?: '—' ); ?></a></td>
					<td><?php echo esc_html( wp_trim_words( $item['text'], 12 ) ); ?></td>
					<td>
						<span class="shopforge-msg-status <?php echo $item['is_open'] ? 'is-open' : 'is-closed'; ?>">
							<?php echo esc_html( $item['status_label'] ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $item['date'] ? date_i18n( get_option( 'date_format' ), strtotime( $item['date'] ) ) : '—' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post( paginate_links( [
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => '‹',
					'next_text' => '›',
				] ) );
				?>
			</div>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>

	<style>
	.shopforge-messages__filters { margin: 12px 0 16px; display: flex; gap: 8px; align-items: center; }
	.shopforge-messages__filters input[type="search"] { min-width: 260px; }
	.shopforge-msg-row--open { background: #FFFBEB; }
	.shopforge-msg-status {
		display: inline-block; padding: 2px 10px; border-radius: 999px;
		font-size: 11px; font-weight: 700;
	}
	.shopforge-msg-status.is-open   { background: #FEF3C7; color: #92400E; }
	.shopforge-msg-status.is-closed { background: #F3F4F6; color: #6B7280; }
	</style>
	<?php
}
