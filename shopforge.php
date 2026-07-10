<?php
/**
 * Plugin Name:  ShopForge
 * Plugin URI:   https://www.andreaem.it
 * Description:  Modular WooCommerce plugin — account area, tracking, wishlist, returns, quotes, notifications and UX improvements.
 * Version:      1.12.1
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

// Traduzioni: plugin non ospitato su WordPress.org, quindi il caricamento
// automatico non si applica — va richiamato esplicitamente.
add_action( 'init', function () {
    load_plugin_textdomain( 'shopforge', false, dirname( plugin_basename( SHOPFORGE_FILE ) ) . '/languages' );
} );

// License server endpoint
define( 'SHOPFORGE_LICENSE_SERVER', 'https://licenses.andreaem.it/api.php?action=validate' );

// FontAwesome Free in bundle (assets/vendor/fontawesome) — nessun kit
// esterno: i kit sono legati all'account/dominio e non redistribuibili.
function shopforge_enqueue_fontawesome(): void {
    if ( wp_style_is( 'shopforge-fontawesome', 'enqueued' ) ) {
        return;
    }
    wp_enqueue_style(
        'shopforge-fontawesome',
        SHOPFORGE_URL . 'assets/vendor/fontawesome/css/all.min.css',
        [],
        '6.7.2'
    );
}

// All'attivazione gli endpoint non sono ancora registrati: segna un flag,
// il flush avviene su init dopo add_rewrite_endpoint (inc/shopforge-modules.php).
register_activation_hook( __FILE__, function () {
    update_option( 'shopforge_flush_rewrite', 1 );
} );

// Compatibilità WooCommerce HPOS
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
} );

// Licensing (controlla prima di caricare il plugin)
require_once SHOPFORGE_DIR . 'inc/shopforge-license.php';
require_once SHOPFORGE_DIR . 'inc/shopforge-modules-check.php';

// Pagina admin unificata (menu + router Licenza/Moduli/Shortcode): non
// dipende da WooCommerce, così la tab Licenza resta raggiungibile anche se
// WooCommerce non è (ancora) attivo — le altre tab lo segnalano a runtime.
require_once SHOPFORGE_DIR . 'inc/shopforge-admin-page.php';

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
    require_once SHOPFORGE_DIR . 'inc/shopforge-product-info.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-thankyou.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-order-statuses.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-order-tracker.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-order-alert.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-admin-messages.php';
    require_once SHOPFORGE_DIR . 'inc/shopforge-admin-dashboard.php';

    // Email WooCommerce: registra il filtro woocommerce_email_classes.
    // Le classi (che estendono WC_Email) vengono incluse DENTRO il filtro,
    // dove WC_Email è garantita disponibile.
    require_once SHOPFORGE_DIR . 'inc/shopforge-emails.php';

    // Sistema moduli: registro, loader, menu dinamico
    require_once SHOPFORGE_DIR . 'inc/shopforge-modules.php';
    shopforge_load_modules();

    // Settings page admin (tab Moduli, usata dal router in shopforge-admin-page.php)
    require_once SHOPFORGE_DIR . 'inc/shopforge-settings.php';
} );

// Override template: cerca nella cartella del plugin prima del fallback WooCommerce.
//
// Condizioni di esclusione (feature disattivate):
//   - myaccount/dashboard.php  → non ovverridato se la feature 'dashboard' è OFF
//   - myaccount/navigation.php → non ovverridato se 'styles-account' è OFF
//     (la nav custom aggiunge icone FA e classi CSS che dipendono da quello stile)
//   - cart/*                   → non ovverridato se 'styles-shop' è OFF
add_filter( 'woocommerce_locate_template', function ( $template, $template_name, $template_path ) {
    // Se il sistema moduli non è ancora caricato, usa sempre il default WC (fail-safe)
    if ( ! function_exists( 'shopforge_is_module_active' ) ) {
        return $template;
    }

    $styles_shop_on    = shopforge_is_module_active( 'styles-shop' );
    $styles_account_on = shopforge_is_module_active( 'styles-account' );
    $dashboard_on      = shopforge_is_module_active( 'dashboard' );

    // Cart/shop templates: usano HTML custom con classi shopforge-*.
    // Senza CSS rompono il layout → serviti SOLO se 'styles-shop' è attivo.
    if ( str_starts_with( $template_name, 'cart/' ) && ! $styles_shop_on ) {
        return $template;
    }

    // Dashboard: usa WC default se la feature 'dashboard' è disattivata
    if ( 'myaccount/dashboard.php' === $template_name && ! $dashboard_on ) {
        return $template;
    }

    // Navigazione: usa WC default se 'styles-account' è disattivata
    if ( 'myaccount/navigation.php' === $template_name && ! $styles_account_on ) {
        return $template;
    }

    $plugin_template = SHOPFORGE_DIR . 'woocommerce/' . $template_name;
    if ( file_exists( $plugin_template ) ) {
        return $plugin_template;
    }
    return $template;
}, 10, 3 );
