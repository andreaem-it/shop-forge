<?php
/**
 * Modulo: Notifiche
 *
 * Centro notifiche alimentato da eventi reali del plugin:
 *  - Cambio stato ordine (WooCommerce)
 *  - Ticket di assistenza aperto
 *  - Cambio stato reso
 *  - Preventivo ricevuto / risposto
 *
 * Le notifiche sono salvate in user meta `_shopforge_notifications`
 * come array di oggetti. Ogni notifica ha un campo `read` (bool).
 * Il badge sul menu mostra il numero di non lette.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// HELPER: scrivi notifica
// =============================================================================

/**
 * Aggiunge una notifica all'utente.
 *
 * @param int    $user_id  ID utente.
 * @param string $type     Tipo evento (order_status, ticket, return_status, quote_received, quote_replied).
 * @param array  $data     Dati della notifica: 'text' (string), 'url' (string, optional).
 */
function shopforge_add_notification( int $user_id, string $type, array $data ): void {
	if ( ! $user_id ) return;

	$icons = [
		'order_status'   => 'fa-solid fa-box',
		'ticket'         => 'fa-solid fa-headset',
		'return_status'  => 'fa-solid fa-rotate-left',
		'rma_status'     => 'fa-solid fa-screwdriver-wrench',
		'quote_received' => 'fa-solid fa-file-invoice',
		'quote_replied'  => 'fa-solid fa-file-invoice',
		'back_in_stock'  => 'fa-solid fa-heart',
		'loyalty_earned' => 'fa-solid fa-star',
		'loyalty_redeemed' => 'fa-solid fa-star',
	];

	$notifications   = get_user_meta( $user_id, '_shopforge_notifications', true ) ?: [];
	$notifications[] = [
		'id'   => uniqid( 'ntf_' ),
		'type' => $type,
		'icon' => $icons[ $type ] ?? 'fa-solid fa-bell',
		'text' => sanitize_text_field( $data['text'] ?? '' ),
		'url'  => esc_url_raw( $data['url'] ?? '' ),
		'date' => current_time( 'mysql' ),
		'read' => false,
	];

	// Mantieni solo le ultime 50 notifiche
	if ( count( $notifications ) > 50 ) {
		$notifications = array_slice( $notifications, -50 );
	}

	update_user_meta( $user_id, '_shopforge_notifications', $notifications );
}

// Hook su `shopforge_notification` (usato dai moduli preventivi, resi, ticket)
add_action( 'shopforge_notification', 'shopforge_add_notification', 10, 3 );


// =============================================================================
// HOOK EVENTI REALI
// =============================================================================

// 1. Cambio stato ordine WooCommerce
add_action( 'woocommerce_order_status_changed', function ( int $order_id, string $old_status, string $new_status ) {
	$order   = wc_get_order( $order_id );
	if ( ! $order ) return;
	$user_id = (int) $order->get_customer_id();
	if ( ! $user_id ) return;

	$labels = [
		'pending'    => __( 'Awaiting payment', 'shopforge' ),
		'processing' => __( 'Payment confirmed — preparing', 'shopforge' ),
		'on-hold'    => __( 'Awaiting verification', 'shopforge' ),
		'completed'  => __( 'Delivered', 'shopforge' ),
		'cancelled'  => __( 'Cancelled', 'shopforge' ),
		'refunded'   => __( 'Refunded', 'shopforge' ),
		'failed'     => __( 'Payment failed', 'shopforge' ),
	];

	// Non notificare tutti i cambi — solo quelli rilevanti per il cliente
	$notifiable = [ 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];
	if ( ! in_array( $new_status, $notifiable, true ) ) return;

	$label = $labels[ $new_status ] ?? $new_status;

	shopforge_add_notification( $user_id, 'order_status', [
		/* translators: 1: order number, 2: status label */
		'text' => sprintf( __( 'Order #%1$s — %2$s', 'shopforge' ), $order->get_order_number(), $label ),
		'url'  => $order->get_view_order_url(),
	] );
}, 10, 3 );

// 2. Ticket di assistenza aperto (da shopforge-order-tracker.php)
add_action( 'shopforge_ticket_submitted', function ( int $user_id, int $order_id, string $ref ) {
	shopforge_add_notification( $user_id, 'ticket', [
		/* translators: 1: ticket reference, 2: order ID */
		'text' => sprintf( __( 'Support request %1$s sent for order #%2$d', 'shopforge' ), $ref, $order_id ),
		'url'  => wc_get_account_endpoint_url( 'orders' ),
	] );
}, 10, 3 );

// 3. Cambio stato reso (da shopforge-mod-returns.php via AJAX admin)
// Il modulo resi chiama `shopforge_notification` → gestito già da add_action('shopforge_notification')


// =============================================================================
// HELPER: conta non lette
// =============================================================================

function shopforge_unread_count( int $user_id ): int {
	$notifications = get_user_meta( $user_id, '_shopforge_notifications', true ) ?: [];
	return count( array_filter( $notifications, fn( $n ) => ! $n['read'] ) );
}


// =============================================================================
// BADGE nel menu account
//
// Il badge NON viene più concatenato all'etichetta del menu: il template
// di navigazione (woocommerce/myaccount/navigation.php) passa ogni label
// attraverso esc_html(), quindi qualunque <span> incluso qui finiva
// mostrato come testo letterale invece che come HTML. shopforge_unread_count()
// viene invece richiamata direttamente dal template per quella voce.
// =============================================================================


// =============================================================================
// ENDPOINT — Contenuto pagina "Notifiche" nell'account
// =============================================================================

add_action( 'woocommerce_account_shopforge-notices_endpoint', function () {
	$user_id       = get_current_user_id();
	$notifications = get_user_meta( $user_id, '_shopforge_notifications', true ) ?: [];
	$notifications = array_reverse( $notifications ); // più recenti prima
	$unread        = shopforge_unread_count( $user_id );
	$nonce         = wp_create_nonce( 'shopforge_notif_' . $user_id );

	shopforge_account_section_header(
		__( 'Notifications', 'shopforge' ),
		'fa-solid fa-bell',
		$unread > 0
			/* translators: %d: number of unread notifications */
			? sprintf( _n( '%d unread', '%d unread', $unread, 'shopforge' ), $unread )
			: __( 'All read', 'shopforge' )
	);

	if ( empty( $notifications ) ) {
		shopforge_account_empty_state(
			'fa-solid fa-bell',
			__( 'No notifications', 'shopforge' ),
			__( 'You will receive updates about orders, shipments, support tickets and quotes.', 'shopforge' )
		);
		return;
	}
	?>

	<?php if ( $unread > 0 ) : ?>
	<div style="margin-bottom:16px;text-align:right">
		<button type="button" class="shopforge-btn shopforge-btn--ghost" id="shopforge-notif-read-all"
		        data-nonce="<?php echo esc_attr( $nonce ); ?>"
		        data-user="<?php echo esc_attr( $user_id ); ?>">
			<i class="fa-solid fa-check-double"></i> <?php esc_html_e( 'Mark all as read', 'shopforge' ); ?>
		</button>
	</div>
	<?php endif; ?>

	<div class="shopforge-notif-list" id="shopforge-notif-list">
		<?php foreach ( $notifications as $n ) :
			$date = date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $n['date'] ) );
		?>
		<div class="shopforge-notif-row <?php echo ! $n['read'] ? 'shopforge-notif-row--unread' : ''; ?>"
		     data-id="<?php echo esc_attr( $n['id'] ); ?>">
			<div class="shopforge-notif-row__icon">
				<i class="<?php echo esc_attr( $n['icon'] ); ?>" aria-hidden="true"></i>
			</div>
			<div class="shopforge-notif-row__body">
				<?php if ( $n['url'] ) : ?>
				<a href="<?php echo esc_url( $n['url'] ); ?>" class="shopforge-notif-row__text"
				   data-notif-id="<?php echo esc_attr( $n['id'] ); ?>">
					<?php echo esc_html( $n['text'] ); ?>
				</a>
				<?php else : ?>
				<span class="shopforge-notif-row__text"><?php echo esc_html( $n['text'] ); ?></span>
				<?php endif; ?>
				<span class="shopforge-notif-row__date"><?php echo esc_html( $date ); ?></span>
			</div>
			<?php if ( ! $n['read'] ) : ?>
			<span class="shopforge-notif-dot" aria-hidden="true"></span>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<script>
	(function () {
		'use strict';
		var ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
		var nonce   = '<?php echo esc_js( $nonce ); ?>';
		var userId  = '<?php echo esc_js( $user_id ); ?>';

		// Segna come letta al click sul link
		document.querySelectorAll('[data-notif-id]').forEach(function (el) {
			el.addEventListener('click', function () {
				fetch(ajaxUrl, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: 'action=shopforge_mark_notif_read&nonce=' + nonce + '&notif_id=' + this.dataset.notifId + '&user_id=' + userId
				});
			});
		});

		// Segna tutte come lette
		document.getElementById('shopforge-notif-read-all')?.addEventListener('click', function () {
			var btn = this;
			btn.disabled = true;
			fetch(ajaxUrl, {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: 'action=shopforge_mark_all_notif_read&nonce=' + nonce + '&user_id=' + userId
			}).then(function (r) { return r.json(); }).then(function (d) {
				if (d.success) {
					document.querySelectorAll('.shopforge-notif-row--unread').forEach(function (row) {
						row.classList.remove('shopforge-notif-row--unread');
						var dot = row.querySelector('.shopforge-notif-dot');
						if (dot) dot.remove();
					});
					btn.style.display = 'none';
				}
			});
		});
	})();
	</script>
	<?php
} );


// =============================================================================
// AJAX — Segna notifica come letta
// =============================================================================

add_action( 'wp_ajax_shopforge_mark_notif_read', function () {
	$user_id  = absint( $_POST['user_id'] ?? 0 );
	$notif_id = sanitize_text_field( $_POST['notif_id'] ?? '' );
	if ( ! $user_id || ! $notif_id ) wp_send_json_error();
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_notif_' . $user_id ) ) wp_send_json_error();
	if ( get_current_user_id() !== $user_id ) wp_send_json_error();

	$notifications = get_user_meta( $user_id, '_shopforge_notifications', true ) ?: [];
	foreach ( $notifications as &$n ) {
		if ( $n['id'] === $notif_id ) { $n['read'] = true; break; }
	}
	update_user_meta( $user_id, '_shopforge_notifications', $notifications );
	wp_send_json_success();
} );

add_action( 'wp_ajax_shopforge_mark_all_notif_read', function () {
	$user_id = absint( $_POST['user_id'] ?? 0 );
	if ( ! $user_id ) wp_send_json_error();
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_notif_' . $user_id ) ) wp_send_json_error();
	if ( get_current_user_id() !== $user_id ) wp_send_json_error();

	$notifications = get_user_meta( $user_id, '_shopforge_notifications', true ) ?: [];
	foreach ( $notifications as &$n ) { $n['read'] = true; }
	update_user_meta( $user_id, '_shopforge_notifications', $notifications );
	wp_send_json_success();
} );


// =============================================================================
// CSS
// =============================================================================

add_action( 'wp_head', function () {
	if ( ! is_account_page() ) return;
	?>
	<style id="shopforge-notif-css">

	/* ---- Badge menu ---- */
	.shopforge-notif-badge {
		display: inline-flex; align-items: center; justify-content: center;
		min-width: 18px; height: 18px; padding: 0 5px;
		background: #E11D48; color: #fff;
		font-size: 10px; font-weight: 800; border-radius: 999px;
		margin-left: 6px; vertical-align: middle; line-height: 1;
	}

	/* ---- Lista notifiche ---- */
	.shopforge-notif-list { display: flex; flex-direction: column; gap: 2px; }
	.shopforge-notif-row {
		display: flex; align-items: flex-start; gap: 14px;
		padding: 14px 16px; border-radius: var(--shopforge-radius);
		background: #fff; border: 1px solid var(--shopforge-border-soft);
		position: relative; transition: background .15s;
	}
	.shopforge-notif-row:hover { background: var(--shopforge-bg-soft); }
	.shopforge-notif-row--unread { background: #FAFFF4; border-color: #BBF7D0; }
	.shopforge-notif-row--unread:hover { background: #F0FDF4; }

	.shopforge-notif-row__icon {
		width: 36px; height: 36px; flex-shrink: 0;
		background: var(--shopforge-bg-soft); border-radius: 8px;
		display: flex; align-items: center; justify-content: center;
		color: var(--shopforge-primary); font-size: 14px;
	}
	.shopforge-notif-row--unread .shopforge-notif-row__icon {
		background: #DCFCE7; color: #16A34A;
	}
	.shopforge-notif-row__body { flex: 1; min-width: 0; }
	.shopforge-notif-row__text {
		display: block; font-size: 13px; color: var(--shopforge-text-main);
		font-weight: 500; margin-bottom: 3px; text-decoration: none;
		line-height: 1.4;
	}
	a.shopforge-notif-row__text:hover { color: var(--shopforge-primary); }
	.shopforge-notif-row--unread .shopforge-notif-row__text { font-weight: 700; }
	.shopforge-notif-row__date { font-size: 11px; color: var(--shopforge-text-muted); }

	.shopforge-notif-dot {
		width: 8px; height: 8px; flex-shrink: 0;
		background: #E11D48; border-radius: 50%;
		margin-top: 4px;
	}
	</style>
	<?php
} );
