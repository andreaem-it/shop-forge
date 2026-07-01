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


// -------------------------------------------------------------------------
// CSS — shortcode [wc_price_iva_box] e [stock_status_text]
// -------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'shopforge-product-extras',
		SHOPFORGE_URL . 'assets/css/shopforge-product-extras.css',
		[],
		SHOPFORGE_VERSION
	);
} );


// -------------------------------------------------------------------------
// Shortcode [wc_price_iva_box]
// Prezzo con/senza IVA. Portato dal functions.php del tema.
// -------------------------------------------------------------------------

add_shortcode( 'wc_price_iva_box', function ( $atts ) {
	$atts = shortcode_atts( [
		'id' => 0,
	], $atts, 'wc_price_iva_box' );

	$product = ! empty( $atts['id'] ) ? wc_get_product( (int) $atts['id'] ) : wc_get_product();

	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$price_including_tax = wc_get_price_including_tax( $product );
	$price_excluding_tax = wc_get_price_excluding_tax( $product );

	if ( $price_including_tax === '' || $price_including_tax === null ) {
		return '';
	}

	ob_start();
	?>
	<div class="wc-iva-price-box">
		<div class="wc-iva-price-main">
			<?php echo wc_price( $price_including_tax ); ?>
			<span class="wc-iva-label-main">IVA inclusa</span>
		</div>
		<div class="wc-iva-price-sub">
			<?php echo wc_price( $price_excluding_tax ); ?>
			<span class="wc-iva-label-sub">IVA esclusa</span>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );


// -------------------------------------------------------------------------
// Shortcode [data_consegna_prodotto]
// Stima data di consegna in base a giorni lavorativi, festivi italiani e
// orario di cutoff. Portato dal functions.php del tema.
// -------------------------------------------------------------------------

if ( ! function_exists( 'shopforge_italian_holidays' ) ) {
	function shopforge_italian_holidays( $year ) {
		$year = (int) $year;

		$holidays = [
			$year . '-01-01', // Capodanno
			$year . '-01-06', // Epifania
			$year . '-04-25', // Festa della Liberazione
			$year . '-05-01', // Festa dei Lavoratori
			$year . '-06-02', // Festa della Repubblica
			$year . '-08-15', // Ferragosto
			$year . '-11-01', // Ognissanti
			$year . '-12-08', // Immacolata
			$year . '-12-25', // Natale
			$year . '-12-26', // Santo Stefano
		];

		/*
		 * Calcolo della Pasqua con algoritmo Meeus/Jones/Butcher.
		 * Evita easter_date(), che richiede l'estensione calendar di PHP.
		 */
		$a = $year % 19;
		$b = intdiv( $year, 100 );
		$c = $year % 100;
		$d = intdiv( $b, 4 );
		$e = $b % 4;
		$f = intdiv( $b + 8, 25 );
		$g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

		$easter = DateTime::createFromFormat(
			'Y-n-j',
			$year . '-' . $month . '-' . $day,
			wp_timezone()
		);

		if ( $easter instanceof DateTime ) {
			$easter_monday = clone $easter;
			$easter_monday->modify( '+1 day' );
			$holidays[] = $easter_monday->format( 'Y-m-d' );
		}

		return $holidays;
	}
}

add_shortcode( 'data_consegna_prodotto', function () {
	global $product;

	if ( ! is_product() ) {
		return '';
	}
	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}
	if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return '';
	}

	$shipping = [
		'days'  => 3,
		'label' => 'Corriere Espresso',
	];
	$cutoff_hour = 14;
	$timezone    = wp_timezone();
	$now         = new DateTime( 'now', $timezone );

	$date = clone $now;
	if ( (int) $date->format( 'H' ) >= $cutoff_hour ) {
		$date->modify( '+1 day' );
	}

	$holidays = array_merge(
		shopforge_italian_holidays( (int) $date->format( 'Y' ) ),
		shopforge_italian_holidays( (int) $date->format( 'Y' ) + 1 )
	);

	$days_to_add = (int) $shipping['days'];
	while ( $days_to_add > 0 ) {
		$date->modify( '+1 day' );
		$weekday = (int) $date->format( 'N' );
		$ymd     = $date->format( 'Y-m-d' );
		if ( in_array( $weekday, [ 6, 7 ], true ) ) {
			continue;
		}
		if ( in_array( $ymd, $holidays, true ) ) {
			continue;
		}
		$days_to_add--;
	}

	$data_consegna = date_i18n( 'l j F', $date->getTimestamp() );
	$data_consegna = ucfirst( $data_consegna );

	$cutoff = new DateTime( 'today ' . $cutoff_hour . ':00:00', $timezone );
	if ( $now >= $cutoff ) {
		$cutoff->modify( '+1 day' );
		while (
			in_array( (int) $cutoff->format( 'N' ), [ 6, 7 ], true ) ||
			in_array( $cutoff->format( 'Y-m-d' ), $holidays, true )
		) {
			$cutoff->modify( '+1 day' );
		}
		$cutoff->setTime( $cutoff_hour, 0, 0 );
	}

	$diff_seconds = max( 0, $cutoff->getTimestamp() - $now->getTimestamp() );
	$hours        = (int) floor( $diff_seconds / 3600 );
	$minutes      = (int) floor( ( $diff_seconds % 3600 ) / 60 );

	$tempo_ordine = '';
	if ( $hours > 0 ) {
		$tempo_ordine .= $hours . 'h ';
	}
	$tempo_ordine .= $minutes . 'm';

	$tooltip = 'La data riportata è indicativa e potrebbe subire slittamenti non imputabili al nostro controllo.';

	return '
		<div class="woocommerce-delivery-estimate-content" style="display:flex; align-items:flex-start; gap:10px; line-height:1.25;">
			<div class="woocommerce-delivery-estimate-icon" style="flex:0 0 auto; width:45px; color:#0046d5; margin-top:2px;">
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 54.36 37.37" width="45" height="45" fill="currentColor" aria-hidden="true" focusable="false" style="display:block;">
		<path d="M53.22,17.72c-.3-.42-.63-.82-.95-1.22-.65-.81-1.33-1.64-1.82-2.62l-1.56-3.1c-1.05-2.09-2.1-4.17-3.14-6.27-.36-.74-.82-1.02-1.63-1-1.12,.02-2.25,.02-3.37,.02-1.03,0-2.06,0-3.09,.01-.3,.02-.69-.02-.97-.33-.27-.3-.26-.7-.23-.97,.02-.17,.01-.34,0-.51v-.29c0-1.01-.44-1.44-1.44-1.44C25.47,0,15.93,0,6.38,0c-.18,0-.37,.02-.57,.07-.43,.1-.77,.47-.82,.92-.04,.39,.04,.72,.25,.95,.23,.26,.62,.41,1.1,.41h1.4c8.41,0,16.82,0,25.23,0,.31,.03,.63,.03,.88,.29,.25,.25,.28,.6,.28,.87-.02,3.79-.02,7.59,0,11.38,0,1.05,.41,1.46,1.46,1.47h1.61c3.75,0,7.5,0,11.25,0,.34,0,.82,.06,1.21,.58,.57,.77,1.17,1.52,1.76,2.28l.25,.32c.17,.21,.39,.55,.39,1.06,0,2.75-.01,5.49,0,8.24,0,.21-.02,.52-.27,.76-.27,.26-.63,.26-.78,.25-.94-.02-1.7-.02-2.41,0-.82,.04-1.06-.61-1.14-.82-.9-2.69-3.35-4.5-6.09-4.5h-.02c-2.77,0-5.22,1.82-6.1,4.51-.11,.35-.35,.81-1.08,.81h0c-3.18-.01-6.36,0-9.54,0h0c-.54,0-.88-.25-1.05-.77-.91-2.76-3.31-4.55-6.12-4.55h0c-2.76,0-5.21,1.82-6.1,4.51-.24,.72-.73,.82-1.14,.8-.9,0-1.8,0-2.7,0h-1.21c-.39,0-.68,.09-.91,.29-.37,.31-.51,.82-.36,1.26,.17,.49,.62,.79,1.19,.8,1.33,.02,2.66,.02,4,0,.76-.06,1.05,.46,1.17,.83,.94,2.65,3.32,4.36,6.06,4.37h.01c2.7,0,5.13-1.75,6.04-4.36,.26-.75,.81-.84,1.2-.84h.01c3.11,.02,6.21,.02,9.32,0h.02c.86,0,1.14,.5,1.3,.95,.89,2.54,3.56,4.39,6.1,4.25,2.83-.12,5.02-1.73,5.99-4.42,.12-.34,.34-.8,1.1-.77,.56,0,1.12,0,1.68,0,.8,0,1.6,0,2.4,.02,.91,.04,1.41-.21,1.73-.84v-12c-.18-.39-.46-.74-.75-1.11-.13-.17-.27-.34-.39-.52Zm-5.9-4.07c-.23,.37-.65,.37-.89,.36-1.1,0-2.2,0-3.31,0h-2.39c-1.08,0-2.15,0-3.23,0-.01,0-.02,0-.04,0-.22,0-.52-.03-.75-.26-.24-.24-.27-.55-.27-.79,.02-2.04,.01-4.07,0-6.11,0-.15,0-.51,.26-.77,.24-.24,.54-.26,.76-.26,1.87,.02,3.75,.01,5.62,0,.46-.02,.81,.21,1.03,.64,1.04,2.09,2.08,4.17,3.14,6.25,.08,.15,.29,.56,.05,.93ZM21.51,30.92c0,2.24-1.81,4.08-4.04,4.09h-.02c-1.07,0-2.12-.44-2.89-1.22-.78-.79-1.21-1.81-1.2-2.88,.02-2.23,1.85-4.05,4.09-4.05h0c2.24,0,4.06,1.82,4.06,4.06h0Zm19.85,4.09h0c-2.23,0-4.05-1.82-4.06-4.07,0-2.24,1.8-4.08,4.04-4.08h.02c1.08,0,2.15,.45,2.93,1.23,.77,.78,1.19,1.8,1.18,2.86-.03,2.24-1.87,4.06-4.1,4.06Z"></path>
		<path d="M16.9,16.08c.37-.38,.45-.78,.27-1.27-.18-.47-.6-.73-1.18-.73H2.67c-.52,0-1.04,0-1.56,0-.53,.02-1.01,.43-1.09,.94-.09,.55,.21,1.1,.73,1.29,.23,.09,.48,.1,.8,.1H6.27s2.37,0,2.37,0h2.37s4.73,0,4.73,0h0c.59,0,.92-.1,1.15-.34Z"></path>
		<path d="M4,9.51c1.69,0,3.39,0,5.08,0h4.24c.61,0,1.23,0,1.84,0,1.23,0,2.46,0,3.69,0h0c.34,0,.58-.07,.79-.23,.41-.31,.57-.83,.41-1.31-.11-.34-.38-.78-1.35-.79-4.8,0-9.61,0-14.41,0-.25,0-.49,0-.68,.08-.49,.19-.83,.73-.76,1.19,.1,.62,.55,1.04,1.14,1.04Z"></path>
		<path d="M6.82,23.29h2.53c.92,0,1.84,0,2.76,0,.55,0,1.01-.41,1.12-.98,.09-.5-.23-1.05-.72-1.25-.18-.08-.41-.11-.73-.11H4.3c-.1,0-.2,0-.3,.02-.71,.1-1.2,.6-1.15,1.21,.05,.68,.55,1.11,1.31,1.12,.89,0,1.77,0,2.66,0Z"></path>
	</svg>
</div>
			<div class="woocommerce-delivery-estimate-text">
				<div style="font-size:12px; font-weight:700; color:#1c2430;">
					Consegna stimata
				</div>
				<div style="font-size:12px; font-weight:700; color:#000;">
					<span title="' . esc_attr( $tooltip ) . '" style="cursor:help;">
						' . esc_html( $data_consegna ) . '
					</span>
				</div>
				<div style="font-size:12px; color:#1c2430; margin-top:4px;">
					Ordina entro <strong style="color:#00a651;">' . esc_html( $tempo_ordine ) . '</strong>
					per riceverlo entro la data stimata
				</div>
			</div>
		</div>';
} );


// -------------------------------------------------------------------------
// Shortcode [buy_now_button]
// Aggiunge al carrello e porta dritto al checkout. Portato dal
// functions.php del tema.
// -------------------------------------------------------------------------

add_shortcode( 'buy_now_button', function () {
	global $product;

	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}
	if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return '';
	}

	$checkout_url = wc_get_checkout_url();
	$product_id   = $product->get_id();

	$url = add_query_arg( [
		'add-to-cart' => $product_id,
		'quantity'    => 1,
	], $checkout_url );

	return sprintf(
		'<a href="%s" class="mp-buy-now-button">Acquista ora</a>',
		esc_url( $url )
	);
} );


// -------------------------------------------------------------------------
// Shortcode [stock_status_text]
// Etichetta disponibilità (pallino + testo). Portato dal functions.php del
// tema.
// -------------------------------------------------------------------------

add_shortcode( 'stock_status_text', function ( $atts ) {
	$atts = shortcode_atts( [
		'font_size'   => '',
		'font_family' => '',
	], $atts, 'stock_status_text' );

	global $product;

	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$is_in_stock = $product->is_in_stock();
	$class       = $is_in_stock ? 'stock-status-available' : 'stock-status-out';
	$label       = $is_in_stock ? 'Disponibile' : 'Non disponibile';

	$inline_style = '';
	if ( ! empty( $atts['font_size'] ) ) {
		$inline_style .= '--stock-status-font-size:' . esc_attr( $atts['font_size'] ) . ';';
	}
	if ( ! empty( $atts['font_family'] ) ) {
		$inline_style .= '--stock-status-font-family:' . esc_attr( $atts['font_family'] ) . ';';
	}

	ob_start();
	?>
	<span class="stock-status-text <?php echo esc_attr( $class ); ?>"<?php echo $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : ''; ?>>
		<span class="stock-status-dot"></span><?php echo esc_html( $label ); ?>
	</span>
	<?php
	return ob_get_clean();
} );
