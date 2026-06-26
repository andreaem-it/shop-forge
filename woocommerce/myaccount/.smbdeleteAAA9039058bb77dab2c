<?php
/**
 * My Account dashboard - Andrea Emili override
 *
 * Sovrascrive: woocommerce/templates/myaccount/dashboard.php
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'shopforge_render_account_dashboard' ) ) {
    shopforge_render_account_dashboard();
    return;
}

// Fallback WooCommerce standard
printf(
    '<p>' . esc_html__( 'Hello %1$s, from your account dashboard you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.', 'woocommerce' ) . '</p>',
    '<strong>' . esc_html( wp_get_current_user()->display_name ) . '</strong>'
);

do_action( 'woocommerce_account_dashboard' );
