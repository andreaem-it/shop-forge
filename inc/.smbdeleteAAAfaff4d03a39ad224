<?php
/**
 * Andrea Emili — Personalizzazioni pagina prodotto WooCommerce
 *
 * Shortcode [shopforge_variation_description]:
 * Mostra la descrizione della variante selezionata.
 * Nasconde il blocco standard dalla colonna destra e sincronizza
 * il contenuto tramite JS (eventi WooCommerce found_variation /
 * reset_variation_data).
 *
 * Uso: inserisci [shopforge_variation_description] dove vuoi nella
 * colonna centrale del prodotto (shortcode widget, descrizione
 * breve, hook personalizzato The7, ecc.).
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// -------------------------------------------------------------------------
// Shortcode [shopforge_variation_description]
// -------------------------------------------------------------------------

add_shortcode( 'shopforge_variation_description', function () {
	global $product;

	if ( ! is_product() ) {
		return '';
	}

	return '<div class="shopforge-variation-description-placeholder" aria-live="polite"></div>';
} );


// -------------------------------------------------------------------------
// CSS + JS: solo sulle pagine prodotto con varianti
// -------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_product() ) {
		return;
	}

	global $product;
	if ( ! $product || ! is_a( $product, 'WC_Product_Variable' ) ) {
		return;
	}

	$css = '
		.woocommerce-variation-description {
			display: none !important;
		}

		.shopforge-variation-description-placeholder {
			display: none;
			margin: 16px 0 0;
			padding: 14px 18px;
			background: #F0F7FF;
			border-left: 3px solid var(--shopforge-primary, #006FEF);
			border-radius: 0 8px 8px 0;
			font-size: 14px;
			color: var(--shopforge-text-main, #07172F);
			line-height: 1.65;
		}

		.shopforge-variation-description-placeholder p:last-child {
			margin-bottom: 0;
		}
	';

	wp_add_inline_style( 'woocommerce-general', $css );

	$js = <<<'JS'
(function($) {
	$(function() {
		var $form   = $('form.variations_form');
		var $target = $('.shopforge-variation-description-placeholder');

		if ( ! $form.length || ! $target.length ) {
			return;
		}

		$form.on('found_variation', function(e, variation) {
			if ( variation.variation_description ) {
				$target.html( variation.variation_description ).slideDown( 200 );
			} else {
				$target.slideUp( 200, function() { $(this).empty(); });
			}
		});

		$form.on('reset_variation_data', function() {
			$target.slideUp( 200, function() { $(this).empty(); });
		});
	});
})(jQuery);
JS;

	wp_add_inline_script( 'wc-add-to-cart-variation', $js );

}, 20 );
