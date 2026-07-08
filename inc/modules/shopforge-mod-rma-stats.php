<?php
/**
 * Modulo RMA — Statistiche di base.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page( 'shopforge-rma', 'Statistiche', 'Statistiche', 'manage_woocommerce', 'shopforge-rma-stats', 'shopforge_rma_stats_page_render' );
} );

function shopforge_rma_stats_resolution_days( int $request_id, string $stato ): ?float {
	$created = get_post_meta( $request_id, '_shopforge_rma_data_creazione', true );
	$history = get_post_meta( $request_id, '_shopforge_rma_status_history', true ) ?: [];

	$closed_date = '';
	foreach ( array_reverse( $history ) as $entry ) {
		if ( ( $entry['to'] ?? '' ) === $stato ) {
			$closed_date = $entry['date'];
			break;
		}
	}
	if ( ! $created || ! $closed_date ) return null;

	$diff = strtotime( $closed_date ) - strtotime( $created );
	return $diff >= 0 ? $diff / DAY_IN_SECONDS : null;
}

function shopforge_rma_stats_page_render(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) return;

	$final_statuses = [ 'chiusa', 'rimborsata', 'sostituita', 'rifiutata', 'annullata' ];
	$requests = get_posts( [ 'post_type' => 'shopforge_rma_request', 'posts_per_page' => -1, 'post_status' => 'publish' ] );

	$status_counts = $product_counts = $motivo_counts = [];
	$resolution_days = [];

	foreach ( $requests as $request ) {
		$stato = get_post_meta( $request->ID, '_shopforge_rma_stato', true ) ?: 'aperta';
		$status_counts[ $stato ] = ( $status_counts[ $stato ] ?? 0 ) + 1;

		$product_id = get_post_meta( $request->ID, '_shopforge_rma_product_id', true );
		$tipo       = get_post_meta( $request->ID, '_shopforge_rma_tipo_richiesta', true );
		if ( $product_id ) {
			$product_counts[ $product_id ] ??= [ 'reso' => 0, 'assistenza' => 0 ];
			$product_counts[ $product_id ][ 'reso' === $tipo ? 'reso' : 'assistenza' ]++;
		}

		$motivo = get_post_meta( $request->ID, '_shopforge_rma_motivo', true );
		if ( $motivo ) $motivo_counts[ $motivo ] = ( $motivo_counts[ $motivo ] ?? 0 ) + 1;

		if ( in_array( $stato, $final_statuses, true ) ) {
			$days = shopforge_rma_stats_resolution_days( $request->ID, $stato );
			if ( null !== $days ) $resolution_days[] = $days;
		}
	}

	uasort( $product_counts, fn( $a, $b ) => ( $b['reso'] + $b['assistenza'] ) - ( $a['reso'] + $a['assistenza'] ) );
	$top_products = array_slice( $product_counts, 0, 10, true );
	arsort( $motivo_counts );
	$avg_resolution = $resolution_days ? array_sum( $resolution_days ) / count( $resolution_days ) : null;

	$statuses       = shopforge_rma_get_statuses();
	$motivo_options = shopforge_rma_get_motivo_options();
	?>
	<div class="wrap">
		<h1>Statistiche Assistenza Prodotti</h1>

		<h2><?php esc_html_e( 'Requests by status', 'shopforge' ); ?></h2>
		<table class="widefat striped" style="max-width:500px">
			<thead><tr><th>Stato</th><th>N. richieste</th></tr></thead>
			<tbody>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( $status_counts[ $key ] ?? 0 ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Average resolution time', 'shopforge' ); ?></h2>
		<p><?php echo null !== $avg_resolution ? esc_html( number_format_i18n( $avg_resolution, 1 ) . ' giorni (calcolato su ' . count( $resolution_days ) . ' richieste risolte)' ) : 'Nessun dato disponibile ancora.'; ?></p>

		<h2><?php esc_html_e( 'Most requested products', 'shopforge' ); ?></h2>
		<table class="widefat striped" style="max-width:700px">
			<thead><tr><th>Prodotto</th><th>Resi</th><th>Assistenza</th></tr></thead>
			<tbody>
			<?php if ( ! $top_products ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No data available.', 'shopforge' ); ?></td></tr>
			<?php else : foreach ( $top_products as $product_id => $counts ) : $product = wc_get_product( $product_id ); ?>
				<tr>
					<td><?php echo $product ? esc_html( $product->get_name() ) : 'Prodotto eliminato (#' . absint( $product_id ) . ')'; ?></td>
					<td><?php echo esc_html( $counts['reso'] ); ?></td>
					<td><?php echo esc_html( $counts['assistenza'] ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Most frequent reasons (support)', 'shopforge' ); ?></h2>
		<table class="widefat striped" style="max-width:500px">
			<thead><tr><th>Motivo</th><th>N. richieste</th></tr></thead>
			<tbody>
			<?php if ( ! $motivo_counts ) : ?>
				<tr><td colspan="2"><?php esc_html_e( 'No data available.', 'shopforge' ); ?></td></tr>
			<?php else : foreach ( $motivo_counts as $motivo_key => $count ) : ?>
				<tr><td><?php echo esc_html( $motivo_options[ $motivo_key ] ?? $motivo_key ); ?></td><td><?php echo esc_html( $count ); ?></td></tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
