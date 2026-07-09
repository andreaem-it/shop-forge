<?php
/**
 * Alert richieste aperte — pagina dettaglio ordine (wp-admin)
 *
 * Aggrega lo stato di ticket assistenza, resi/recesso e richieste RMA per
 * l'ordine corrente e mostra un avviso in cima alla pagina se ce ne sono di
 * aperte, con link diretto a ciascuna sezione. Ogni controllo è difensivo
 * (function_exists) perché i moduli returns/rma sono disattivabili.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID dell'ordine nella pagina admin corrente, sia HPOS che post classico.
 */
function shopforge_get_admin_screen_order_id(): int {
	if ( isset( $_GET['page'], $_GET['id'] ) && 'wc-orders' === $_GET['page'] ) {
		return absint( $_GET['id'] );
	}
	if ( isset( $_GET['post'] ) ) {
		return absint( $_GET['post'] );
	}
	return 0;
}

/**
 * Riepilogo richieste aperte per un ordine: conteggi + link diretto alla
 * sezione (metabox in pagina per ticket/resi, lista filtrata per RMA).
 */
function shopforge_get_order_open_requests_summary( WC_Order $order ): array {
	$summary = [];

	if ( function_exists( 'shopforge_is_module_active' ) ) {
		$tickets = $order->get_meta( '_shopforge_tickets' ) ?: [];
		$open_tickets = count( array_filter( $tickets, fn( $t ) => 'open' === ( $t['status'] ?? 'open' ) ) );
		if ( $open_tickets ) {
			$summary['tickets'] = [
				/* translators: %d: number of open support tickets */
				'label' => sprintf( _n( '%d open support ticket', '%d open support tickets', $open_tickets, 'shopforge' ), $open_tickets ),
				'url'   => '#shopforge-tickets',
			];
		}

		if ( shopforge_is_module_active( 'returns' ) ) {
			$returns = $order->get_meta( '_shopforge_returns' ) ?: [];
			$open_returns = count( array_filter( $returns, fn( $r ) => ! in_array( $r['status'] ?? 'pending', [ 'refunded', 'rejected' ], true ) ) );
			if ( $open_returns ) {
				$summary['returns'] = [
					/* translators: %d: number of open withdrawal requests */
					'label' => sprintf( _n( '%d open withdrawal request', '%d open withdrawal requests', $open_returns, 'shopforge' ), $open_returns ),
					'url'   => '#shopforge-returns',
				];
			}
		}

		if ( shopforge_is_module_active( 'rma' ) && function_exists( 'shopforge_rma_get_open_statuses' ) ) {
			$open_rma = get_posts( [
				'post_type'      => 'shopforge_rma_request',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'AND',
					[ 'key' => '_shopforge_rma_order_id', 'value' => $order->get_id() ],
					[ 'key' => '_shopforge_rma_stato', 'value' => shopforge_rma_get_open_statuses(), 'compare' => 'IN' ],
				],
			] );
			if ( $open_rma ) {
				$summary['rma'] = [
					/* translators: %d: number of open RMA requests */
					'label' => sprintf( _n( '%d open product support request', '%d open product support requests', count( $open_rma ), 'shopforge' ), count( $open_rma ) ),
					'url'   => admin_url( 'edit.php?post_type=shopforge_rma_request&shopforge_rma_order=' . $order->get_id() ),
				];
			}
		}
	}

	return $summary;
}

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) return;

	$order_id = shopforge_get_admin_screen_order_id();
	if ( ! $order_id ) return;

	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	$summary = shopforge_get_order_open_requests_summary( $order );
	if ( empty( $summary ) ) return;
	?>
	<div class="notice notice-warning shopforge-order-alert">
		<p>
			<strong><?php esc_html_e( 'This order has open customer requests:', 'shopforge' ); ?></strong>
			<?php foreach ( $summary as $item ) : ?>
			<a href="<?php echo esc_url( $item['url'] ); ?>" class="shopforge-order-alert__item"><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</p>
	</div>
	<style>
		.shopforge-order-alert p { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 14px; }
		.shopforge-order-alert__item {
			display: inline-flex; align-items: center;
			padding: 2px 10px; border-radius: 999px;
			background: #FEF3C7; color: #92400E !important;
			font-size: 12px; font-weight: 700; text-decoration: none;
		}
		.shopforge-order-alert__item:hover { background: #FDE68A; }
	</style>
	<?php
} );
