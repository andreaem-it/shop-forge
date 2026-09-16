<?php
/**
 * Blocchi Gutenberg per i principali shortcode del prodotto.
 *
 * Nessun build step: i blocchi sono wrapper dinamici (render_callback lato
 * server via do_shortcode) con un editor script minimale in JS puro che usa
 * ServerSideRender per l'anteprima — niente attributi da configurare, dato
 * che questi shortcode leggono già il prodotto corrente per default.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

function shopforge_gutenberg_blocks_registry(): array {
	return [
		'price-iva-box' => [
			'title'     => __( 'ShopForge: Price (VAT incl./excl.)', 'shopforge' ),
			'shortcode' => 'wc_price_iva_box',
			'icon'      => 'tag',
		],
		'delivery-date' => [
			'title'     => __( 'ShopForge: Delivery estimate', 'shopforge' ),
			'shortcode' => 'data_consegna_prodotto',
			'icon'      => 'calendar-alt',
		],
		'buy-now'       => [
			'title'     => __( 'ShopForge: Buy now button', 'shopforge' ),
			'shortcode' => 'buy_now_button',
			'icon'      => 'cart',
		],
		'stock-status'  => [
			'title'     => __( 'ShopForge: Stock status', 'shopforge' ),
			'shortcode' => 'stock_status_text',
			'icon'      => 'info',
		],
		'product-faq'   => [
			'title'     => __( 'ShopForge: Product FAQ', 'shopforge' ),
			'shortcode' => 'product_faq',
			'icon'      => 'editor-help',
		],
	];
}

add_action( 'init', function () {
	if ( ! function_exists( 'register_block_type' ) ) return;

	wp_register_script(
		'shopforge-blocks',
		SHOPFORGE_URL . 'assets/js/shopforge-blocks.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ],
		SHOPFORGE_VERSION,
		true
	);

	$blocks = shopforge_gutenberg_blocks_registry();
	$js_config = [];

	foreach ( $blocks as $slug => $block ) {
		$name = 'shopforge/' . $slug;
		register_block_type( $name, [
			'title'           => $block['title'],
			'category'        => 'woocommerce',
			'icon'            => $block['icon'],
			'editor_script'   => 'shopforge-blocks',
			'render_callback' => fn() => do_shortcode( '[' . $block['shortcode'] . ']' ),
		] );
		$js_config[] = [ 'name' => $name, 'title' => $block['title'], 'icon' => $block['icon'] ];
	}

	wp_localize_script( 'shopforge-blocks', 'ShopForgeBlocks', $js_config );
} );
