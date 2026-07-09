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
	'shopforge_return_window_days',
	'shopforge_rma_excluded_categories',
	'shopforge_rma_max_requests_per_day',
	'shopforge_rma_notification_email',
	'shopforge_rma_return_period_days',
	'shopforge_rma_warranty_months',
	'shopforge_theme',
	'shopforge_theme_overrides',
];

foreach ( $shopforge_options as $shopforge_option ) {
	delete_option( $shopforge_option );
}

delete_transient( 'shopforge_dash_sales' );
delete_transient( 'shopforge_dash_requests' );

// RMA requests custom post type content.
$shopforge_rma_ids = get_posts( [
	'post_type'      => 'shopforge_rma_request',
	'post_status'    => 'any',
	'numberposts'    => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
] );

foreach ( $shopforge_rma_ids as $shopforge_rma_id ) {
	wp_delete_post( $shopforge_rma_id, true );
}
