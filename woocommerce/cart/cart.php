<?php
/**
 * Cart template — Andrea Emili layout
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.8.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
?>

<div class="shopforge-cart-wrap">

    <!-- ── Colonna prodotti ── -->
    <div class="shopforge-cart-main">
        <div class="shopforge-cart-card">

            <div class="shopforge-cart-card-header">
                <h2><?php esc_html_e( 'Il tuo carrello', 'woocommerce' ); ?></h2>
                <span class="shopforge-cart-count"><?php echo esc_html( WC()->cart->get_cart_contents_count() ); ?></span>
            </div>

            <?php do_action( 'woocommerce_before_cart_table' ); ?>

            <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

                <div class="shopforge-cart-items">
                    <?php do_action( 'woocommerce_before_cart_contents' ); ?>

                    <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                        $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                        $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                        $visible    = apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key );

                        if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) :
                            $product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
                            $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                            $thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'thumbnail' ), $cart_item, $cart_item_key );
                    ?>
                    <div class="shopforge-cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

                        <div class="shopforge-cart-item-image">
                            <?php if ( $product_permalink ) : ?>
                                <a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo $thumbnail; // phpcs:ignore ?></a>
                            <?php else : ?>
                                <?php echo $thumbnail; // phpcs:ignore ?>
                            <?php endif; ?>
                        </div>

                        <div class="shopforge-cart-item-info">
                            <div class="shopforge-cart-item-name">
                                <?php if ( $product_permalink ) : ?>
                                    <a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo wp_kses_post( $product_name ); ?></a>
                                <?php else : ?>
                                    <?php echo wp_kses_post( $product_name ); ?>
                                <?php endif; ?>
                            </div>
                            <?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore ?>
                            <?php if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) : ?>
                                <p class="backorder_notification"><?php esc_html_e( 'Available on backorder', 'woocommerce' ); ?></p>
                            <?php endif; ?>
                            <div class="shopforge-cart-item-unit-price">
                                <?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
                            </div>
                        </div>

                        <div class="shopforge-cart-item-qty">
                            <?php
                            $min = $_product->is_sold_individually() ? 1 : 0;
                            $max = $_product->is_sold_individually() ? 1 : $_product->get_max_purchase_quantity();
                            echo apply_filters( 'woocommerce_cart_item_quantity',
                                woocommerce_quantity_input( [
                                    'input_name'   => "cart[{$cart_item_key}][qty]",
                                    'input_value'  => $cart_item['quantity'],
                                    'max_value'    => $max,
                                    'min_value'    => $min,
                                    'product_name' => $product_name,
                                ], $_product, false ),
                                $cart_item_key, $cart_item
                            ); // phpcs:ignore
                            ?>
                        </div>

                        <div class="shopforge-cart-item-subtotal">
                            <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
                        </div>

                        <div class="shopforge-cart-item-remove">
                            <?php
                            echo apply_filters( 'woocommerce_cart_item_remove_link', sprintf(
                                '<a role="button" href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
                                esc_attr( $product_id ),
                                esc_attr( $_product->get_sku() )
                            ), $cart_item_key ); // phpcs:ignore
                            ?>
                        </div>

                    </div>
                    <?php
                        endif;
                    endforeach;
                    ?>

                    <?php do_action( 'woocommerce_cart_contents' ); ?>
                    <?php do_action( 'woocommerce_after_cart_contents' ); ?>
                </div>

                <div class="shopforge-cart-footer">
                    <button type="submit" class="shopforge-update-btn" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                        <?php esc_html_e( 'Aggiorna carrello', 'woocommerce' ); ?>
                    </button>
                    <?php do_action( 'woocommerce_cart_actions' ); ?>
                    <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                </div>

            </form>

            <?php do_action( 'woocommerce_after_cart_table' ); ?>
        </div>
    </div><!-- .shopforge-cart-main -->

    <!-- ── Colonna riepilogo ── -->
    <div class="shopforge-cart-sidebar">
        <?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
        <?php woocommerce_cart_totals(); ?>
    </div>

</div><!-- .shopforge-cart-wrap -->

<?php do_action( 'woocommerce_after_cart' ); ?>
