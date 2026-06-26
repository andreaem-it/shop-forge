<?php
/**
 * Plugin Name:  ShopForge
 * Plugin URI:   https://www.andreaem.it
 * Description:  Plugin modulare per WooCommerce — account area, tracking, wishlist, resi, preventivi, notifiche e UX improvements.
 * Version:      1.5.5
 * Author:       Andrea Emili
 * Author URI:   https://www.andreaem.it
 * Text Domain:  shopforge
 * Domain Path:  /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// Legge la versione direttamente dall'header del file — unica fonte di verità
$_shopforge_data = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
define( 'SHOPFORGE_VERSION', $_shopforge_data['Version'] ?? '0.0.0' );
unset( $_shopforge_data );

define( 'SHOPFORGE_FILE', __FILE__ );
define( 'SHOPFORGE_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SHOPFORGE_URL',  plugin_dir_url( __FILE__ ) );

// URL kit FontAwesome — unico punto di configurazione
define( 'SHOPFORGE_FA_KIT_URL', 'https://kit.fontawesome.com/051de31815.js' );

// Compatibilità WooCommerce HPOS
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
} );

// Carica solo se WooCommerce è attivo
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>ShopForge</strong> richiede WooCommerce attivo.</p></div>';
        } );
        return;
    }

    // Core (sempre caricati)
    require_once SHOPFORGE_DIR . 'inc/shopforge-account.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-product.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-thankyou.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-order-statuses.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-order-tracker.php';

    // Email WooCommerce: registra il filtro woocommerce_email_classes.
    // Le classi (che estendono WC_Email) vengono incluse DENTRO il filtro,
    // dove WC_Email è garantita disponibile.
    require_once SHOPFORGE_DIR . 'inc/shopforge-emails.php';

    // Sistema moduli: registro, loader, menu dinamico
    require_once SHOPFORGE_DIR . 'inc/shopforge-modules.php';
    shopforge_load_modules();

    // Settings page admin
    require_once SHOPFORGE_DIR . 'inc/shopforge-settings.php';
} );

// Override template: cerca nella cartella del plugin prima del fallback WooCommerce.
//
// Condizioni di esclusione (feature disattivate):
//   - myaccount/dashboard.php  → non ovverridato se la feature 'dashboard' è OFF
//   - myaccount/navigation.php → non ovverridato se la feature 'styles' è OFF
//     (la nav custom aggiunge icone FA e classi CSS che dipendono dagli stili)
add_filter( 'woocommerce_locate_template', function ( $template, $template_name, $template_path ) {
    // Se il sistema moduli non è ancora caricato, usa sempre il default WC (fail-safe)
    if ( ! function_exists( 'shopforge_is_module_active' ) ) {
        return $template;
    }

    $styles_on    = shopforge_is_module_active( 'styles' );
    $dashboard_on = shopforge_is_module_active( 'dashboard' );

    // Cart/shop templates: usano HTML custom con classi shopforge-*.
    // Senza CSS rompono il layout → serviti SOLO se 'styles' è attivo.
    if ( str_starts_with( $template_name, 'cart/' ) && ! $styles_on ) {
        return $template;
    }

    // Dashboard: usa WC default se la feature 'dashboard' è disattivata
    if ( 'myaccount/dashboard.php' === $template_name && ! $dashboard_on ) {
        return $template;
    }

    // Navigazione: usa WC default se la feature 'styles' è disattivata
    if ( 'myaccount/navigation.php' === $template_name && ! $styles_on ) {
        return $template;
    }

    $plugin_template = SHOPFORGE_DIR . 'woocommerce/' . $template_name;
    if ( file_exists( $plugin_template ) ) {
        return $plugin_template;
    }
    return $template;
}, 10, 3 );
