<?php
/**
 * Andrea Emili — Stato ordine personalizzato: Spedito
 *
 * Aggiunge lo stato "Spedito" (wc-spedito) tra "In lavorazione"
 * e "Completato". Compatibile con WooCommerce HPOS.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// -------------------------------------------------------------------------
// 1. Registra il post status (necessario per ordini legacy CPT)
// -------------------------------------------------------------------------

add_action( 'init', function () {
	register_post_status( 'wc-spedito', [
		'label'                     => _x( 'Spedito', 'Order status', 'woocommerce' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop(
			'Spedito <span class="count">(%s)</span>',
			'Spediti <span class="count">(%s)</span>'
		),
	] );
} );


// -------------------------------------------------------------------------
// 2. Aggiunge "Spedito" alla lista stati WooCommerce
//    — inserito dopo "In lavorazione" (wc-processing)
// -------------------------------------------------------------------------

add_filter( 'wc_order_statuses', function ( $statuses ) {
	$result = [];

	foreach ( $statuses as $key => $label ) {
		$result[ $key ] = $label;

		if ( 'wc-processing' === $key ) {
			$result['wc-spedito'] = _x( 'Spedito', 'Order status', 'woocommerce' );
		}
	}

	return $result;
} );


// -------------------------------------------------------------------------
// 3. "Spedito" è considerato "pagato"
// -------------------------------------------------------------------------

add_filter( 'woocommerce_order_is_paid_statuses', function ( $statuses ) {
	$statuses[] = 'spedito';
	return $statuses;
} );


// -------------------------------------------------------------------------
// 4. Mantiene lo stock già scalato
// -------------------------------------------------------------------------

add_filter( 'woocommerce_valid_order_statuses_for_stock_reduce', function ( $statuses ) {
	$statuses[] = 'spedito';
	return array_unique( $statuses );
} );


// -------------------------------------------------------------------------
// 5. Badge colorato in WP Admin + frontend My Account
// -------------------------------------------------------------------------

add_action( 'admin_head', function () {
	?>
	<style>
		.order-status.status-spedito,
		mark.spedito,
		.wc-order-status-spedito {
			background: #dbeeff !important;
			color: #1565C0 !important;
		}
	</style>
	<?php
} );

add_action( 'wp_head', function () {
	if ( ! is_account_page() ) {
		return;
	}
	?>
	<style>
		.shopforge-status-spedito,
		.woocommerce-orders-table__cell-order-status span.spedito,
		.woocommerce-MyAccount-orders mark.spedito,
		.woocommerce-account mark.order-status.status-spedito {
			background: #dbeeff !important;
			color: #1565C0 !important;
		}
	</style>
	<?php
} );


// -------------------------------------------------------------------------
// 6. Azioni bulk in WP Admin
// -------------------------------------------------------------------------

add_filter( 'bulk_actions-edit-shop_order', function ( $actions ) {
	$actions['mark_spedito'] = 'Cambia stato in: Spedito';
	return $actions;
} );

add_filter( 'bulk_actions-woocommerce_page_wc-orders', function ( $actions ) {
	$actions['mark_spedito'] = 'Cambia stato in: Spedito';
	return $actions;
} );
