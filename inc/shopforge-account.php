<?php
/**
 * ShopForge — logica principale
 *
 * Hook WooCommerce, endpoint custom, The7 full-width,
 * FontAwesome, dashboard personalizzata.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// ICONE: mappa endpoint → classe FontAwesome
// =========================================================================

function shopforge_nav_icons(): array {
    return [
        // WooCommerce nativi
        'dashboard'               => 'fa-solid fa-house',
        'orders'                  => 'fa-solid fa-bag-shopping',
        'downloads'               => 'fa-solid fa-download',
        'edit-address'            => 'fa-solid fa-location-dot',
        'edit-account'            => 'fa-solid fa-user',
        'payment-methods'         => 'fa-solid fa-credit-card',
        'subscriptions'           => 'fa-solid fa-rotate',
        'customer-logout'         => 'fa-solid fa-right-from-bracket',

        // Moduli Andrea Emili
        'shopforge-quotes'            => 'fa-solid fa-file-invoice',
        'shopforge-wishlist'          => 'fa-solid fa-heart',
        'shopforge-products'          => 'fa-solid fa-box',
        'shopforge-returns'           => 'fa-solid fa-rotate-left',
        'shopforge-rma'               => 'fa-solid fa-screwdriver-wrench',
        'shopforge-notices'           => 'fa-solid fa-bell',
        'shopforge-loyalty'           => 'fa-solid fa-star',

        // Plugin italiani / YITH / WooCommerce estensioni (endpoint comuni)
        'resi-assistenza'         => 'fa-solid fa-headset',
        'le-mie-richieste'        => 'fa-solid fa-ticket',
        'resi'                    => 'fa-solid fa-rotate-left',
        'reso'                    => 'fa-solid fa-rotate-left',
        'assistenza'              => 'fa-solid fa-headset',
        'supporto'                => 'fa-solid fa-headset',
        'richieste'               => 'fa-solid fa-ticket',
        'ticket'                  => 'fa-solid fa-ticket',
        'tickets'                 => 'fa-solid fa-ticket',
        'wishlist'                => 'fa-solid fa-heart',
        'yith-wishlist'           => 'fa-solid fa-heart',
        'yith-points'             => 'fa-solid fa-star',
        'wc-returns'              => 'fa-solid fa-rotate-left',
        'wc-rma'                  => 'fa-solid fa-rotate-left',
        'reviews'                 => 'fa-solid fa-star',
        'punti'                   => 'fa-solid fa-star',
        'loyalty'                 => 'fa-solid fa-star',
        'fatture'                 => 'fa-solid fa-file-lines',
        'invoices'                => 'fa-solid fa-file-lines',
        'documenti'               => 'fa-solid fa-file-lines',
        'preventivi'              => 'fa-solid fa-file-invoice',
    ];
}

/**
 * Icona fallback per endpoint non mappati in shopforge_nav_icons().
 * Restituisce una classe FA generica.
 */
function shopforge_nav_icon_fallback(): string {
    return 'fa-solid fa-chevron-right';
}


// =========================================================================
// THE7: forza full-width senza sidebar sulla pagina account
// =========================================================================

add_action( 'wp', function () {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }

    add_filter( 'presscore_is_sidebar_enabled', '__return_false', 99 );
    add_filter( 'dt_the7_sidebar',              '__return_false', 99 );

    add_filter( 'body_class', function ( $classes ) {
        $remove   = [ 'right-sidebar', 'left-sidebar', 'sidebar-right', 'sidebar-left', 'has-sidebar' ];
        $classes  = array_diff( $classes, $remove );
        $classes[] = 'dt-no-sidebar';
        $classes[] = 'full-width-content';
        $classes[] = 'shopforge-account-page';
        return array_unique( $classes );
    }, 99 );
}, 5 );


// =========================================================================
// CSS VARIABLES — iniettate su pagine account se 'styles-colors' è attivo
// (priority 5, prima di wp_print_styles a priority 8).
//
// Se 'styles-colors' è attivo, shopforge-woo-account.css (priority 8) le
// ridefinisce con gli stessi valori di default → nessun conflitto.
// Se 'styles-colors' è OFF, sparisce anche questo fallback: le modali di
// assistenza/recesso tornano allo stile di default del tema (kill switch
// totale, per design).
// =========================================================================

add_action( 'wp_head', function () {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    if ( ! function_exists( 'shopforge_is_module_active' ) || ! shopforge_is_module_active( 'styles-colors' ) ) return;
    ?>
    <style id="shopforge-css-vars">
    :root {
        --shopforge-primary:       #006FEF;
        --shopforge-primary-hover: #168BFF;
        --shopforge-text-main:     #07172F;
        --shopforge-text-muted:    #64748B;
        --shopforge-border:        #E2E8F0;
        --shopforge-border-soft:   #EEF2F7;
        --shopforge-bg-soft:       #F8FAFC;
        --shopforge-success:       #16A34A;
        --shopforge-warning:       #F59E0B;
        --shopforge-danger:        #DC2626;
        --shopforge-radius:        8px;
        --shopforge-shadow:        0 16px 40px rgba(15,23,42,.06);
    }
    </style>
    <?php
}, 5 );


// =========================================================================
// ASSETS: FontAwesome Kit + CSS
// =========================================================================

/**
 * Registra e carica i CSS del plugin.
 * Usa tre approcci in cascata per coprire WordPress standard, Elementor e Cart Block.
 *
 * CSS negozio → 'styles-shop'. CSS account + FontAwesome → 'styles-account'.
 * Se il rispettivo toggle è disattivato il plugin non inietta nulla in quell'area.
 */
function shopforge_enqueue_shop_css() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    // Fail-safe: se il sistema moduli non è caricato, non iniettare nulla
    if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
    // Carica solo se la feature "Stili catalogo/negozio" è attiva
    if ( ! shopforge_is_module_active( 'styles-shop' ) ) return;

    if ( ! wp_style_is( 'shopforge-woo-shop', 'enqueued' ) && ! wp_style_is( 'shopforge-woo-shop', 'done' ) ) {
        wp_enqueue_style( 'shopforge-woo-shop', SHOPFORGE_URL . 'assets/css/shopforge-woo-shop.css', [], SHOPFORGE_VERSION );
    }
}

function shopforge_enqueue_account_css() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    if ( ! function_exists( 'shopforge_is_module_active' ) || ! shopforge_is_module_active( 'styles-account' ) ) return;

    // FontAwesome: caricato solo se lo stile account è attivo (unico consumer lato frontend)
    shopforge_enqueue_fontawesome();

    wp_enqueue_style( 'shopforge-woo-account', SHOPFORGE_URL . 'assets/css/shopforge-woo-account.css', [], SHOPFORGE_VERSION );
}

// 1. Hook WordPress standard
add_action( 'wp_enqueue_scripts', 'shopforge_enqueue_account_css', 20 );
add_action( 'wp_enqueue_scripts', 'shopforge_enqueue_shop_css', 20 );

// 2. Hook Elementor — si attiva quando Elementor gestisce il rendering della pagina
add_action( 'elementor/frontend/after_enqueue_styles', 'shopforge_enqueue_shop_css' );

// 3. Fallback wp_head — stampa il tag direttamente se nessuno dei metodi precedenti ha funzionato
//    Solo se il sistema moduli è caricato E la feature 'styles-shop' è attiva.
add_action( 'wp_head', function () {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
    if ( ! shopforge_is_module_active( 'styles-shop' ) ) return;
    if ( wp_style_is( 'shopforge-woo-shop', 'done' ) || wp_style_is( 'shopforge-woo-shop', 'enqueued' ) ) return;

    $url = esc_url( SHOPFORGE_URL . 'assets/css/shopforge-woo-shop.css?v=' . SHOPFORGE_VERSION );
    echo '<link rel="stylesheet" id="shopforge-woo-shop-fallback-css" href="' . $url . '" media="all">' . "\n";
}, 999 );

// 4. Fix larghezza uniforme — iniettato a priorità 999 (DOPO il CSS di Elementor)
//    così le CSS custom properties sovrascrivono quelle di Elementor.
add_action( 'wp_head', function () {
    if ( ! is_account_page() ) return;
    // Il width-fix è un layout override dell'area account: solo se 'styles-account' è attivo
    if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
    if ( ! shopforge_is_module_active( 'styles-account' ) ) return;
    ?>
    <style id="shopforge-account-width-fix">
    /* Forza il widget Elementor a occupare tutta la larghezza disponibile
       su tutte le pagine account, non solo la bacheca. */
    .woocommerce-account .e-con-inner > *:has(.woocommerce-MyAccount-navigation) {
        --flex-grow: 1;
        --flex-shrink: 1;
        --flex-basis: 0%;
        min-width: 0;
    }
    .woocommerce-account .e-con-inner > *:has(.woocommerce-MyAccount-navigation) > .elementor-widget-container {
        width: 100%;
    }
    .woocommerce-account .woocommerce {
        width: 100%;
    }
    </style>
    <?php
}, 999 );

// 5. JS fallback width-fix — solo se 'styles-account' è attivo
add_action( 'wp_footer', function () {
    if ( ! is_account_page() ) return;
    if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
    if ( ! shopforge_is_module_active( 'styles-account' ) ) return;
    ?>
    <script id="shopforge-width-fix-js">
    (function() {
        function shopforgeFixWidth() {
            var widget = document.querySelector(
                '.woocommerce-account .e-con-inner > *:has(.woocommerce-MyAccount-navigation)'
            );
            if ( ! widget ) return;
            widget.style.setProperty('flex', '1 1 0%', 'important');
            widget.style.setProperty('min-width', '0', 'important');
            var wc = widget.querySelector('.elementor-widget-container');
            if ( wc ) wc.style.width = '100%';
        }
        if ( document.readyState === 'loading' ) {
            document.addEventListener('DOMContentLoaded', shopforgeFixWidth);
        } else {
            shopforgeFixWidth();
        }
    })();
    </script>
    <?php
}, 999 );


// =========================================================================
// MENU LATERALE ACCOUNT
// Ordine voci native WC — i moduli custom vengono aggiunti da shopforge-modules.php
// =========================================================================

add_filter( 'woocommerce_account_menu_items', function ( $items ) {
    $logout = [];
    if ( isset( $items['customer-logout'] ) ) {
        $logout['customer-logout'] = $items['customer-logout'];
        unset( $items['customer-logout'] );
    }

    $wc_native = [ 'dashboard', 'orders', 'downloads', 'edit-address', 'edit-account', 'payment-methods' ];
    $menu = [];
    foreach ( $wc_native as $key ) {
        if ( isset( $items[ $key ] ) ) {
            $menu[ $key ] = $items[ $key ];
        }
    }

    // I moduli custom vengono inseriti da shopforge-modules.php a priority 20
    // Qui restituiamo solo le voci native + logout (il merge avviene dopo)
    return array_merge( $menu, $logout );
}, 10 );


// =========================================================================
// RIORDINA RAPIDO — aggiunge azione "Riordina" nella lista ordini
// =========================================================================

add_filter( 'woocommerce_my_account_my_orders_actions', function ( array $actions, WC_Order $order ) {
    // Mostra "Riordina" solo se l'ordine è completato o annullato
    if ( in_array( $order->get_status(), [ 'completed', 'cancelled' ], true ) ) {
        $cart_url = wc_get_cart_url();
        $actions['reorder'] = [
            'url'  => add_query_arg( [
                'order_again' => $order->get_id(),
                '_wpnonce'    => wp_create_nonce( 'woocommerce-order_again' ),
            ], $cart_url ),
            'name' => __( 'Reorder', 'shopforge' ), // esc_html-safe; l'icona viene iniettata dal filtro sotto
        ];
    }
    return $actions;
}, 10, 2 );

// Inietta l'icona FA nel pulsante Riordina (WC applica esc_html sul 'name',
// quindi l'HTML va aggiunto qui dove viene costruito il tag <a>).
add_filter( 'woocommerce_my_account_my_orders_actions_button_html', function ( string $html, array $action, string $key ): string {
    if ( 'reorder' === $key ) {
        $html = '<a href="' . esc_url( $action['url'] ) . '" class="button reorder">'
              . '<i class="fa-solid fa-rotate-right" aria-hidden="true"></i> ' . esc_html__( 'Reorder', 'shopforge' )
              . '</a>';
    }
    return $html;
}, 10, 3 );


// =========================================================================
// COLONNE TABELLA ORDINI
// Condizionale alla feature 'styles-account' — senza CSS custom le colonne WC native
// sono più adatte perché vengono stilizzate dal tema.
// =========================================================================

add_filter( 'woocommerce_account_orders_columns', function ( $columns ) {
    if ( function_exists( 'shopforge_is_module_active' ) && ! shopforge_is_module_active( 'styles-account' ) ) {
        return $columns; // usa le colonne WooCommerce predefinite
    }
    return [
        'order-number'  => __( 'Order', 'shopforge' ),
        'order-date'    => __( 'Date', 'shopforge' ),
        'order-status'  => __( 'Status', 'shopforge' ),
        'order-total'   => __( 'Total', 'shopforge' ),
        'order-actions' => __( 'Actions', 'shopforge' ),
    ];
}, 20 );


// =========================================================================
// STATUS BADGE nella tabella ordini
// WooCommerce 7+ emette testo puro — questo hook lo wrappa in mark.order-status
// Condizionale a 'styles-account': senza CSS il badge non è stilizzato.
// =========================================================================

add_action( 'woocommerce_my_account_my_orders_column_order-status', function ( $order ) {
    if ( function_exists( 'shopforge_is_module_active' ) && ! shopforge_is_module_active( 'styles-account' ) ) {
        // Fallback: output WC nativo
        echo esc_html( wc_get_order_status_name( $order->get_status() ) );
        return;
    }
    $status = $order->get_status();
    $label  = wc_get_order_status_name( $status );
    printf(
        '<mark class="order-status status-%s"><span>%s</span></mark>',
        esc_attr( sanitize_html_class( $status ) ),
        esc_html( $label )
    );
} );


// =========================================================================
// HELPER: componenti UI riutilizzabili dai moduli
// =========================================================================

/**
 * Header di sezione con icona e titolo.
 */
function shopforge_account_section_header( string $title, string $icon = '', string $meta = '' ): void {
    ?>
    <div class="shopforge-section-header">
        <?php if ( $icon ) : ?>
        <span class="shopforge-section-header__icon">
            <i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
        </span>
        <?php endif; ?>
        <div>
            <h2 class="shopforge-section-header__title"><?php echo esc_html( $title ); ?></h2>
            <?php if ( $meta ) : ?>
            <p class="shopforge-section-header__meta"><?php echo esc_html( $meta ); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Stato vuoto generico (icona centrata + titolo + testo).
 */
function shopforge_account_empty_state( string $icon, string $title, string $text = '' ): void {
    ?>
    <div class="shopforge-empty-state">
        <i class="<?php echo esc_attr( $icon ); ?> shopforge-empty-state__icon" aria-hidden="true"></i>
        <p class="shopforge-empty-state__title"><?php echo esc_html( $title ); ?></p>
        <?php if ( $text ) : ?>
        <p class="shopforge-empty-state__text"><?php echo esc_html( $text ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * @deprecated Usa shopforge_account_section_header() + shopforge_account_empty_state()
 */
function shopforge_account_placeholder_section(
    string $title,
    string $description,
    string $empty_label,
    string $empty_hint = ''
): void {
    shopforge_account_section_header( $title );
    shopforge_account_empty_state( 'fa-solid fa-inbox', $empty_label, $empty_hint );
}


// =========================================================================
// DASHBOARD CUSTOM
// =========================================================================

function shopforge_render_account_dashboard(): void {
    if ( ! function_exists( 'wc_get_orders' ) || ! is_user_logged_in() ) {
        return;
    }

    $user_id      = get_current_user_id();
    $user         = wp_get_current_user();
    $display_name = $user->display_name ?: $user->user_login;

    $recent_orders = wc_get_orders( [
        'customer_id' => $user_id,
        'limit'       => 5,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'return'      => 'objects',
    ] );

    $total_orders = wc_get_orders( [
        'customer_id' => $user_id,
        'limit'       => -1,
        'return'      => 'ids',
    ] );

    $processing_orders = wc_get_orders( [
        'customer_id' => $user_id,
        'status'      => [ 'processing', 'on-hold' ],
        'limit'       => -1,
        'return'      => 'ids',
    ] );

    $completed_orders = wc_get_orders( [
        'customer_id' => $user_id,
        'status'      => [ 'completed' ],
        'limit'       => -1,
        'return'      => 'ids',
    ] );

    $billing_address  = wc_get_account_formatted_address( 'billing' );
    $shipping_address = wc_get_account_formatted_address( 'shipping' );

    $stats = [
        [
            'icon'      => 'fa-solid fa-bag-shopping',
            'label'     => __( 'Total orders', 'shopforge' ),
            'value'     => count( $total_orders ),
            'link_text' => __( 'View orders', 'shopforge' ),
            'url'       => wc_get_account_endpoint_url( 'orders' ),
        ],
        [
            'icon'      => 'fa-solid fa-clock',
            'label'     => __( 'Processing', 'shopforge' ),
            'value'     => count( $processing_orders ),
            'link_text' => __( 'Check status', 'shopforge' ),
            'url'       => wc_get_account_endpoint_url( 'orders' ),
        ],
        [
            'icon'      => 'fa-solid fa-truck',
            'label'     => __( 'Delivered', 'shopforge' ),
            'value'     => count( $completed_orders ),
            'link_text' => __( 'Purchase history', 'shopforge' ),
            'url'       => wc_get_account_endpoint_url( 'orders' ),
        ],
    ];

    // Card preventivi solo se il modulo è attivo (altrimenti il link è un 404)
    if ( function_exists( 'shopforge_is_module_active' ) && shopforge_is_module_active( 'quotes' ) ) {
        $quotes  = get_user_meta( $user_id, '_shopforge_quotes', true ) ?: [];
        $stats[] = [
            'icon'      => 'fa-solid fa-file-invoice',
            'label'     => __( 'Quotes', 'shopforge' ),
            'value'     => count( $quotes ),
            'link_text' => __( 'Go to quotes', 'shopforge' ),
            'url'       => wc_get_account_endpoint_url( 'shopforge-quotes' ),
        ];
    }

    // Punteggio loyalty solo se il modulo è attivo (altrimenti il link è un 404)
    $loyalty_balance = null;
    if ( function_exists( 'shopforge_is_module_active' ) && shopforge_is_module_active( 'loyalty' )
         && function_exists( 'shopforge_loyalty_get_balance' ) ) {
        $loyalty_balance = shopforge_loyalty_get_balance( $user_id );
    }
    ?>
    <div class="shopforge-account-dashboard">

        <!-- Benvenuto -->
        <div class="shopforge-account-welcome">
            <div>
                <span class="shopforge-account-eyebrow"><?php esc_html_e( 'Customer area', 'shopforge' ); ?></span>
                <h2><?php
                    /* translators: %s: customer display name */
                    printf( esc_html__( 'Hi %s', 'shopforge' ), esc_html( $display_name ) );
                ?></h2>
                <p><?php esc_html_e( 'From here you can manage orders, addresses, personal data, payment methods, quotes and sales requests.', 'shopforge' ); ?></p>
            </div>
            <div class="shopforge-account-header-actions">
                <?php if ( null !== $loyalty_balance ) : ?>
                <a class="shopforge-loyalty-badge" href="<?php echo esc_url( wc_get_account_endpoint_url( 'shopforge-loyalty' ) ); ?>">
                    <span class="shopforge-loyalty-badge__row">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        <strong><?php echo esc_html( $loyalty_balance ); ?></strong>
                    </span>
                    <span class="shopforge-loyalty-badge__label"><?php esc_html_e( 'Loyalty points', 'shopforge' ); ?></span>
                </a>
                <?php endif; ?>
                <a class="shopforge-account-logout" href="<?php echo esc_url( wc_logout_url() ); ?>">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <?php esc_html_e( 'Log out', 'shopforge' ); ?>
                </a>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="shopforge-account-stats">
            <?php foreach ( $stats as $stat ) : ?>
                <a class="shopforge-stat-card" href="<?php echo esc_url( $stat['url'] ); ?>">
                    <span class="shopforge-stat-icon">
                        <i class="<?php echo esc_attr( $stat['icon'] ); ?>" aria-hidden="true"></i>
                    </span>
                    <span class="shopforge-stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
                    <strong><?php echo esc_html( $stat['value'] ); ?></strong>
                    <em><?php echo esc_html( $stat['link_text'] ); ?></em>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Widget tracking ordine in transito -->
        <?php shopforge_dashboard_tracking_widget( $user_id ); ?>

        <!-- Griglia ordini + indirizzi -->
        <div class="shopforge-account-grid">

            <section class="shopforge-account-card shopforge-orders-card">
                <div class="shopforge-card-header">
                    <div>
                        <h3><?php esc_html_e( 'My recent orders', 'shopforge' ); ?></h3>
                        <p><?php
                            /* translators: %s: site name */
                            printf( esc_html__( 'Your latest purchases on %s.', 'shopforge' ), esc_html( get_bloginfo( 'name' ) ) );
                        ?></p>
                    </div>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'View all', 'shopforge' ); ?></a>
                </div>

                <?php if ( ! empty( $recent_orders ) ) : ?>
                    <div class="shopforge-orders-table-wrap">
                        <table class="shopforge-orders-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
                                    <th><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'shopforge' ); ?></th>
                                    <th><?php esc_html_e( 'Total', 'shopforge' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'shopforge' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_orders as $order ) :
                                    $status      = $order->get_status();
                                    $status_name = wc_get_order_status_name( $status );
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
                                        <td><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'd/m/Y' ) ); ?></td>
                                        <td>
                                            <span class="shopforge-status shopforge-status-<?php echo esc_attr( $status ); ?>">
                                                <?php echo esc_html( $status_name ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                                        <td>
                                            <a class="button view shopforge-orders-table__view-btn" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
                                                <?php esc_html_e( 'View', 'shopforge' ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="shopforge-empty-state">
                        <strong><?php esc_html_e( 'No orders placed yet.', 'shopforge' ); ?></strong>
                        <span><?php esc_html_e( 'When you make a purchase, you will find it here.', 'shopforge' ); ?></span>
                    </div>
                <?php endif; ?>
            </section>

            <section class="shopforge-account-card shopforge-address-card">
                <div class="shopforge-card-header">
                    <div>
                        <h3><?php esc_html_e( 'Saved addresses', 'shopforge' ); ?></h3>
                        <p><?php esc_html_e( 'Billing and shipping.', 'shopforge' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>"><?php esc_html_e( 'Edit', 'shopforge' ); ?></a>
                </div>

                <div class="shopforge-address-block">
                    <h4><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> <?php esc_html_e( 'Billing', 'shopforge' ); ?></h4>
                    <?php if ( $billing_address ) : ?>
                        <address><?php echo wp_kses_post( $billing_address ); ?></address>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No address saved.', 'shopforge' ); ?></p>
                    <?php endif; ?>
                </div>

                <div class="shopforge-address-block">
                    <h4><i class="fa-solid fa-truck" aria-hidden="true"></i> <?php esc_html_e( 'Shipping', 'shopforge' ); ?></h4>
                    <?php if ( $shipping_address ) : ?>
                        <address><?php echo wp_kses_post( $shipping_address ); ?></address>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No address saved.', 'shopforge' ); ?></p>
                    <?php endif; ?>
                </div>

                <a class="shopforge-outline-button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">
                    <?php esc_html_e( 'Manage addresses', 'shopforge' ); ?>
                </a>
            </section>

        </div>

    </div>
    <?php
}


// =========================================================================
// WIDGET TRACKING IN DASHBOARD
// Mostra l'ultimo ordine con numero tracking e stato 17track
// =========================================================================

function shopforge_dashboard_tracking_widget( int $user_id ): void {
    // Trova l'ordine più recente con numero tracking
    $orders = wc_get_orders( [
        'customer_id' => $user_id,
        'status'      => [ 'processing', 'on-hold', 'wc-spedito', 'completed' ],
        'limit'       => 10,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'return'      => 'objects',
    ] );

    $tracked_order  = null;
    $tracking_data  = null;
    $tracking_number = null;

    foreach ( $orders as $order ) {
        $tn = $order->get_meta( '_shopforge_tracking_number' );
        if ( ! $tn ) continue;

        $cached = get_transient( 'shopforge_track_' . md5( $tn ) );
        // Mostra solo ordini non ancora consegnati (status < 40)
        if ( $cached && ( $cached['status_code'] ?? 0 ) >= 40 ) continue;

        $tracked_order   = $order;
        $tracking_number = $tn;
        $tracking_data   = $cached;
        break;
    }

    if ( ! $tracked_order ) return;

    $status_labels = [
        0  => [ 'label' => __( 'Awaiting pickup', 'shopforge' ),        'icon' => 'fa-solid fa-hourglass-start',  'color' => '#6B7280' ],
        10 => [ 'label' => __( 'Tracking not found', 'shopforge' ),     'icon' => 'fa-solid fa-question-circle', 'color' => '#6B7280' ],
        20 => [ 'label' => __( 'Picked up by carrier', 'shopforge' ),   'icon' => 'fa-solid fa-box',            'color' => '#2563EB' ],
        30 => [ 'label' => __( 'In transit', 'shopforge' ),             'icon' => 'fa-solid fa-truck-fast',     'color' => '#7C3AED' ],
        35 => [ 'label' => __( 'Delivery attempt failed', 'shopforge' ), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => '#D97706' ],
        40 => [ 'label' => __( 'Delivered', 'shopforge' ),              'icon' => 'fa-solid fa-circle-check',   'color' => '#16A34A' ],
    ];

    $status_code  = $tracking_data['status_code'] ?? 0;
    $st           = $status_labels[ $status_code ] ?? $status_labels[0];
    $last_event   = ! empty( $tracking_data['events'] ) ? $tracking_data['events'][0] : null;
    $order_url    = $tracked_order->get_view_order_url();
    ?>
    <div class="shopforge-tracking-widget">
        <div class="shopforge-tracking-widget__head">
            <span class="shopforge-tracking-widget__label">
                <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                <?php esc_html_e( 'Shipment in progress', 'shopforge' ); ?>
            </span>
            <a href="<?php echo esc_url( $order_url ); ?>" class="shopforge-tracking-widget__link">
                <?php
                /* translators: %s: order number */
                printf( esc_html__( 'Order #%s', 'shopforge' ), esc_html( $tracked_order->get_order_number() ) );
                ?> →
            </a>
        </div>
        <div class="shopforge-tracking-widget__body">
            <div class="shopforge-tracking-widget__status" style="color:<?php echo esc_attr( $st['color'] ); ?>">
                <i class="<?php echo esc_attr( $st['icon'] ); ?>" aria-hidden="true"></i>
                <?php echo esc_html( $st['label'] ); ?>
            </div>
            <?php if ( $last_event ) : ?>
            <p class="shopforge-tracking-widget__event">
                <?php echo esc_html( $last_event['description'] ?? '' ); ?>
                <?php if ( $last_event['location'] ?? '' ) : ?>
                — <em><?php echo esc_html( $last_event['location'] ); ?></em>
                <?php endif; ?>
            </p>
            <p class="shopforge-tracking-widget__date">
                <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', $last_event['timestamp'] ?? time() ) ); ?>
            </p>
            <?php elseif ( $tracking_number ) : ?>
            <p class="shopforge-tracking-widget__event">
                <?php esc_html_e( 'Tracking number:', 'shopforge' ); ?> <strong><?php echo esc_html( $tracking_number ); ?></strong>
                — <?php esc_html_e( 'Waiting for carrier updates.', 'shopforge' ); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
