<?php
/**
 * Modulo RMA — Vista stampabile di una singola richiesta.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	if ( empty( $_GET['page'] ) || 'shopforge-rma-print' !== $_GET['page'] ) return;

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'shopforge' ) );
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'shopforge_rma_print_request' ) ) {
		wp_die( esc_html__( 'Invalid or expired link.', 'shopforge' ) );
	}

	$request_id = absint( $_GET['request_id'] ?? 0 );
	$request    = get_post( $request_id );
	if ( ! $request || 'shopforge_rma' !== $request->post_type ) {
		wp_die( esc_html__( 'Request not found.', 'shopforge' ) );
	}

	$user_id     = (int) get_post_meta( $request_id, '_shopforge_rma_user_id', true );
	$order_id    = (int) get_post_meta( $request_id, '_shopforge_rma_order_id', true );
	$product_id  = (int) get_post_meta( $request_id, '_shopforge_rma_product_id', true );
	$tipo        = get_post_meta( $request_id, '_shopforge_rma_tipo_richiesta', true );
	$stato       = get_post_meta( $request_id, '_shopforge_rma_stato', true ) ?: 'aperta';
	$descrizione = get_post_meta( $request_id, '_shopforge_rma_descrizione_problema', true );
	$rimedio     = get_post_meta( $request_id, '_shopforge_rma_rimedio_scelto', true );
	$motivo      = get_post_meta( $request_id, '_shopforge_rma_motivo', true );
	$quantita    = get_post_meta( $request_id, '_shopforge_rma_quantita', true ) ?: 1;
	$tr_corriere = get_post_meta( $request_id, '_shopforge_rma_tracking_corriere', true );
	$tr_numero   = get_post_meta( $request_id, '_shopforge_rma_tracking_numero', true );
	$messages    = get_post_meta( $request_id, '_shopforge_rma_messages', true ) ?: [];

	$user    = $user_id ? get_userdata( $user_id ) : null;
	$order   = $order_id ? wc_get_order( $order_id ) : null;
	$product = $product_id ? wc_get_product( $product_id ) : null;

	$remedy_options = shopforge_rma_get_remedy_options( $tipo );
	$motivo_options = shopforge_rma_get_motivo_options();

	nocache_headers();
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<title><?php echo esc_html( $request->post_title ); ?></title>
		<style>
			body { font-family: Arial, sans-serif; padding: 30px; color: #222; }
			h1 { font-size: 20px; }
			table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
			td, th { padding: 6px 10px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
			th { width: 220px; }
			.shopforge-rma-print-message { border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 10px; }
			.shopforge-rma-print-actions { margin-bottom: 20px; }
			@media print { .shopforge-rma-print-actions { display: none; } }
		</style>
	</head>
	<body>
		<div class="shopforge-rma-print-actions"><button onclick="window.print()">Stampa</button></div>
		<h1><?php echo esc_html( $request->post_title ); ?></h1>
		<table>
			<tr><th>Stato</th><td><?php echo esc_html( shopforge_rma_get_status_label( $stato ) ); ?></td></tr>
			<tr><th>Cliente</th><td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : '—'; ?></td></tr>
			<tr><th>Ordine</th><td><?php echo $order ? esc_html( '#' . $order->get_order_number() ) : '—'; ?></td></tr>
			<tr><th>Prodotto</th><td><?php echo $product ? esc_html( $product->get_name() ) : '—'; ?></td></tr>
			<tr><th>Quantità</th><td><?php echo esc_html( $quantita ); ?></td></tr>
			<?php if ( $motivo ) : ?><tr><th>Motivazione</th><td><?php echo esc_html( $motivo_options[ $motivo ] ?? $motivo ); ?></td></tr><?php endif; ?>
			<?php if ( $rimedio ) : ?><tr><th>Rimedio scelto</th><td><?php echo esc_html( $remedy_options[ $rimedio ] ?? $rimedio ); ?></td></tr><?php endif; ?>
			<?php if ( $descrizione ) : ?><tr><th>Descrizione problema</th><td><?php echo wp_kses_post( nl2br( esc_html( $descrizione ) ) ); ?></td></tr><?php endif; ?>
			<?php if ( $tr_corriere || $tr_numero ) : ?><tr><th>Tracking spedizione</th><td><?php echo esc_html( trim( $tr_corriere . ' ' . $tr_numero ) ); ?></td></tr><?php endif; ?>
		</table>

		<h2>Conversazione</h2>
		<?php if ( ! $messages ) : ?>
			<p><?php esc_html_e( 'No messages.', 'shopforge' ); ?></p>
		<?php else : ?>
			<?php foreach ( $messages as $message ) :
				$is_admin = ! empty( $message['is_admin'] );
				$author   = shopforge_rma_get_message_author_label( $is_admin, $message['user_id'] ?? 0 );
			?>
			<div class="shopforge-rma-print-message">
				<strong><?php echo esc_html( $author ); ?></strong>
				<span> — <?php echo esc_html( $message['date'] ?? '' ); ?></span>
				<p><?php echo wp_kses_post( nl2br( esc_html( $message['message'] ?? '' ) ) ); ?></p>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</body>
	</html>
	<?php
	exit;
} );
