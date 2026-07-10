<?php
/**
 * My Account navigation — Andrea Emili override con icone FontAwesome
 *
 * Ogni voce mostra un'icona mappata in shopforge_nav_icons().
 * Gli endpoint di plugin di terze parti che non sono in mappa
 * ricevono l'icona fallback (chevron-right) in modo che il layout
 * sia sempre uniforme.
 *
 * Sovrascrive: woocommerce/templates/myaccount/navigation.php
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

$icons    = function_exists( 'shopforge_nav_icons' )        ? shopforge_nav_icons()        : [];
$fallback = function_exists( 'shopforge_nav_icon_fallback' ) ? shopforge_nav_icon_fallback() : 'fa-solid fa-chevron-right';

do_action( 'woocommerce_before_account_navigation' );
?>
<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">
    <ul>
        <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) :
            $icon = $icons[ $endpoint ] ?? $fallback;
        ?>
            <li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
                    <i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
                    <span><?php echo esc_html( $label ); ?></span>
                    <?php if ( 'shopforge-notices' === $endpoint && function_exists( 'shopforge_unread_count' ) && is_user_logged_in() ) :
                        $unread = shopforge_unread_count( get_current_user_id() );
                        if ( $unread > 0 ) : ?>
                        <span class="shopforge-notif-badge"><?php echo esc_html( $unread ); ?></span>
                    <?php endif; endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
<?php

do_action( 'woocommerce_after_account_navigation' );
