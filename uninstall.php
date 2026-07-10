<?php
/**
 * ShopForge uninstall cleanup.
 *
 * Removes plugin options and custom post type content when the plugin
 * is deleted from the WordPress admin. Order/user meta created by
 * WooCommerce itself is intentionally left untouched.
 *
 * @package ShopForge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$shopforge_options = [
	'shopforge_17track_key',
	'shopforge_colors',
	'shopforge_contact_url',
	'shopforge_flush_rewrite',
	'shopforge_license_data',
	'shopforge_license_key',
	'shopforge_loyalty_earn_rate',
	'shopforge_loyalty_min_redeem',
	'shopforge_loyalty_point_value',
	'shopforge_modules_enabled',
	'shopforge_receipt_next_number',
	'shopforge_receipt_settings',
	'shopforge_receipt_template',
	'shopforge_return_window_days',
	'shopforge_rma_excluded_categories',
	'shopforge_rma_max_requests_per_day',
	'shopforge_rma_notification_email',
	'shopforge_rma_return_period_days',
	'shopforge_rma_slug_migrated',
	'shopforge_rma_warranty_months',
	'shopforge_theme',
	'shopforge_theme_overrides',
];

foreach ( $shopforge_options as $shopforge_option ) {
	delete_option( $shopforge_option );
}

delete_transient( 'shopforge_dash_sales' );
delete_transient( 'shopforge_dash_requests' );

// RMA requests custom post type content. Cleans up both the current slug
// and the pre-1.12.6 one, in case a site is uninstalled before the
// one-time migration in shopforge-mod-rma.php ever got to run (e.g. the
// module was disabled or the license was invalid).
foreach ( [ 'shopforge_rma', 'shopforge_rma_request' ] as $shopforge_rma_post_type ) {
	$shopforge_rma_ids = get_posts( [
		'post_type'      => $shopforge_rma_post_type,
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	foreach ( $shopforge_rma_ids as $shopforge_rma_id ) {
		wp_delete_post( $shopforge_rma_id, true );
	}
}
