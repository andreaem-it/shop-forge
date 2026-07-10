<?php
/**
 * Modulo RMA — Export CSV richieste (rispetta i filtri della lista admin).
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	if ( empty( $_GET['shopforge_rma_export_csv'] ) || empty( $_GET['post_type'] ) || 'shopforge_rma' !== $_GET['post_type'] ) return;

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'shopforge' ) );
	}

	$args = [ 'post_type' => 'shopforge_rma', 'posts_per_page' => -1, 'post_status' => 'publish' ];

	$meta_query = [];
	if ( ! empty( $_GET['shopforge_rma_stato'] ) )     $meta_query[] = [ 'key' => '_shopforge_rma_stato', 'value' => sanitize_text_field( $_GET['shopforge_rma_stato'] ) ];
	if ( ! empty( $_GET['shopforge_rma_order'] ) )     $meta_query[] = [ 'key' => '_shopforge_rma_order_id', 'value' => absint( $_GET['shopforge_rma_order'] ) ];
	if ( ! empty( $_GET['shopforge_rma_user'] ) )      $meta_query[] = [ 'key' => '_shopforge_rma_user_id', 'value' => absint( $_GET['shopforge_rma_user'] ) ];
	if ( ! empty( $_GET['shopforge_rma_assegnato'] ) ) $meta_query[] = [ 'key' => '_shopforge_rma_assigned_to', 'value' => absint( $_GET['shopforge_rma_assegnato'] ) ];
	if ( $meta_query ) $args['meta_query'] = $meta_query;
	if ( ! empty( $_GET['s'] ) ) $args['s'] = sanitize_text_field( $_GET['s'] );

	$requests = get_posts( $args );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=richieste-rma-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, [ 'ID', __( 'Type', 'shopforge' ), __( 'Customer', 'shopforge' ), __( 'Email', 'shopforge' ), __( 'Order', 'shopforge' ), __( 'Product', 'shopforge' ), __( 'Quantity', 'shopforge' ), __( 'Status', 'shopforge' ), __( 'Assigned to', 'shopforge' ), __( 'Created', 'shopforge' ), __( 'Reason', 'shopforge' ), __( 'Remedy', 'shopforge' ) ] );

	foreach ( $requests as $request ) {
		$user_id       = (int) get_post_meta( $request->ID, '_shopforge_rma_user_id', true );
		$user          = $user_id ? get_userdata( $user_id ) : null;
		$order_id      = (int) get_post_meta( $request->ID, '_shopforge_rma_order_id', true );
		$order         = $order_id ? wc_get_order( $order_id ) : null;
		$product_id    = (int) get_post_meta( $request->ID, '_shopforge_rma_product_id', true );
		$product       = $product_id ? wc_get_product( $product_id ) : null;
		$assigned_to   = (int) get_post_meta( $request->ID, '_shopforge_rma_assigned_to', true );
		$assigned_user = $assigned_to ? get_userdata( $assigned_to ) : null;
		$tipo          = get_post_meta( $request->ID, '_shopforge_rma_tipo_richiesta', true );
		$stato         = get_post_meta( $request->ID, '_shopforge_rma_stato', true ) ?: 'aperta';

		fputcsv( $output, [
			$request->ID,
			'reso' === $tipo ? 'Reso' : 'Assistenza',
			$user ? $user->display_name : '',
			$user ? $user->user_email : '',
			$order ? $order->get_order_number() : '',
			$product ? $product->get_name() : '',
			get_post_meta( $request->ID, '_shopforge_rma_quantita', true ) ?: 1,
			shopforge_rma_get_status_label( $stato ),
			$assigned_user ? $assigned_user->display_name : '',
			get_post_meta( $request->ID, '_shopforge_rma_data_creazione', true ),
			get_post_meta( $request->ID, '_shopforge_rma_motivo', true ),
			get_post_meta( $request->ID, '_shopforge_rma_rimedio_scelto', true ),
		] );
	}

	fclose( $output );
	exit;
} );
