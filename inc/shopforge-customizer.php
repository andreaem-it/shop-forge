<?php
/**
 * Tab "Customizer" — anteprima live della pagina prodotto reale (iframe,
 * stesso dominio) con evidenziazione dei contenitori ed editing dal vivo di
 * pulsante wishlist, pulsante "Aggiungi al carrello" e selettore quantità.
 * Non è un mock: è la pagina vera del sito.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_shopforge_save_customizer', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'shopforge_save_customizer' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'shopforge' ) );
	}

	$width = sanitize_key( $_POST['shopforge_wl_width'] ?? 'auto' );
	update_option( 'shopforge_wishlist_layout', [
		'width'          => in_array( $width, [ 'full', 'stacked' ], true ) ? $width : 'auto',
		'gap'            => max( 0, min( 60, (int) ( $_POST['shopforge_wl_gap'] ?? 10 ) ) ),
		'show_label'     => ! empty( $_POST['shopforge_wl_show_label'] ),
		'colors_enabled' => ! empty( $_POST['shopforge_wl_colors_enabled'] ),
		'bg'             => sanitize_hex_color( $_POST['shopforge_wl_bg'] ?? '' ) ?: '#FFFFFF',
		'icon_color'     => sanitize_hex_color( $_POST['shopforge_wl_icon_color'] ?? '' ) ?: '#64748B',
		'text_color'     => sanitize_hex_color( $_POST['shopforge_wl_text_color'] ?? '' ) ?: '#64748B',
		'height'         => max( 0, min( 100, (int) ( $_POST['shopforge_wl_height'] ?? 0 ) ) ),
		'min_width'      => max( 0, min( 400, (int) ( $_POST['shopforge_wl_min_width'] ?? 0 ) ) ),
		'font_size'      => max( 0, min( 40, (int) ( $_POST['shopforge_wl_font_size'] ?? 0 ) ) ),
		'margin_top'     => max( -40, min( 40, (int) ( $_POST['shopforge_wl_margin_top'] ?? 0 ) ) ),
		'margin_right'   => max( -40, min( 40, (int) ( $_POST['shopforge_wl_margin_right'] ?? 0 ) ) ),
		'margin_bottom'  => max( -40, min( 40, (int) ( $_POST['shopforge_wl_margin_bottom'] ?? 0 ) ) ),
	] );

	$atc_width = sanitize_key( $_POST['shopforge_atc_width'] ?? 'auto' );
	update_option( 'shopforge_atc_layout', [
		'width'          => in_array( $atc_width, [ 'full', 'stacked' ], true ) ? $atc_width : 'auto',
		'colors_enabled' => ! empty( $_POST['shopforge_atc_colors_enabled'] ),
		'bg'             => sanitize_hex_color( $_POST['shopforge_atc_bg'] ?? '' ) ?: '#7C3AED',
		'text_color'     => sanitize_hex_color( $_POST['shopforge_atc_text_color'] ?? '' ) ?: '#FFFFFF',
		'radius'         => max( 0, min( 40, (int) ( $_POST['shopforge_atc_radius'] ?? 6 ) ) ),
		'height'         => max( 0, min( 100, (int) ( $_POST['shopforge_atc_height'] ?? 0 ) ) ),
		'min_width'      => max( 0, min( 400, (int) ( $_POST['shopforge_atc_min_width'] ?? 0 ) ) ),
		'font_size'      => max( 0, min( 40, (int) ( $_POST['shopforge_atc_font_size'] ?? 0 ) ) ),
		'margin_top'     => max( -40, min( 40, (int) ( $_POST['shopforge_atc_margin_top'] ?? 0 ) ) ),
		'margin_right'   => max( -40, min( 40, (int) ( $_POST['shopforge_atc_margin_right'] ?? 0 ) ) ),
		'margin_bottom'  => max( -40, min( 40, (int) ( $_POST['shopforge_atc_margin_bottom'] ?? 0 ) ) ),
		'margin_left'    => max( -40, min( 40, (int) ( $_POST['shopforge_atc_margin_left'] ?? 0 ) ) ),
	] );

	update_option( 'shopforge_qty_layout', [
		'colors_enabled' => ! empty( $_POST['shopforge_qty_colors_enabled'] ),
		'bg'             => sanitize_hex_color( $_POST['shopforge_qty_bg'] ?? '' ) ?: '#F8FAFC',
		'icon_color'     => sanitize_hex_color( $_POST['shopforge_qty_icon_color'] ?? '' ) ?: '#64748B',
		'radius'         => max( 0, min( 40, (int) ( $_POST['shopforge_qty_radius'] ?? 6 ) ) ),
		'height'         => max( 0, min( 100, (int) ( $_POST['shopforge_qty_height'] ?? 0 ) ) ),
		'width'          => max( 0, min( 100, (int) ( $_POST['shopforge_qty_width'] ?? 0 ) ) ),
		'font_size'      => max( 0, min( 40, (int) ( $_POST['shopforge_qty_font_size'] ?? 0 ) ) ),
		'margin_top'     => max( -40, min( 40, (int) ( $_POST['shopforge_qty_margin_top'] ?? 0 ) ) ),
		'margin_right'   => max( -40, min( 40, (int) ( $_POST['shopforge_qty_margin_right'] ?? 0 ) ) ),
		'margin_bottom'  => max( -40, min( 40, (int) ( $_POST['shopforge_qty_margin_bottom'] ?? 0 ) ) ),
		'margin_left'    => max( -40, min( 40, (int) ( $_POST['shopforge_qty_margin_left'] ?? 0 ) ) ),
	] );

	$product_id = (int) ( $_POST['shopforge_preview_product'] ?? 0 );
	wp_redirect( admin_url( 'admin.php?page=shopforge&tab=customizer&updated=1' . ( $product_id ? '&preview=' . $product_id : '' ) ) );
	exit;
} );

/** Riga di slider margine (top/right/bottom[/left]) riusata dalle 3 sezioni. */
function shopforge_customizer_margin_fields( string $prefix, array $values, bool $include_left = true ): void {
	$fields = [
		'margin_top'    => __( 'Top', 'shopforge' ),
		'margin_right'  => __( 'Right', 'shopforge' ),
		'margin_bottom' => __( 'Bottom', 'shopforge' ),
	];
	if ( $include_left ) {
		$fields['margin_left'] = __( 'Left', 'shopforge' );
	}
	?>
	<div class="shopforge-field shopforge-size-row">
		<?php foreach ( $fields as $key => $label ) :
			$id = 'shopforge_' . $prefix . '_' . $key;
		?>
		<label class="shopforge-size-field">
			<?php echo esc_html( $label ); ?> (<span id="<?php echo esc_attr( str_replace( '_', '-', $id ) ); ?>-val"><?php echo (int) $values[ $key ]; ?></span>px)
			<input type="range" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" min="-40" max="40" step="1" value="<?php echo (int) $values[ $key ]; ?>" data-live="<?php echo esc_attr( $prefix ); ?>">
		</label>
		<?php endforeach; ?>
		<p class="shopforge-size-hint"><?php esc_html_e( 'Margin (can be negative, e.g. to nudge alignment)', 'shopforge' ); ?></p>
	</div>
	<?php
}

function shopforge_admin_tab_customizer(): void {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return;
	}

	$products = wc_get_products( [ 'status' => 'publish', 'limit' => 50, 'orderby' => 'title', 'order' => 'ASC' ] );
	if ( empty( $products ) ) {
		echo '<p>' . esc_html__( 'No published products found to preview.', 'shopforge' ) . '</p>';
		return;
	}

	$preview_id = (int) ( $_GET['preview'] ?? $products[0]->get_id() );
	$preview    = wc_get_product( $preview_id ) ?: $products[0];
	$wl         = shopforge_get_wishlist_layout();
	$atc        = shopforge_get_atc_layout();
	$qty        = shopforge_get_qty_layout();

	shopforge_enqueue_fontawesome();
	shopforge_admin_settings_notice();
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
		<?php esc_html_e( 'Customizer', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'This is your real product page, loaded live. Hover the quantity/wishlist/add-to-cart row to see the containers, adjust the controls and save.', 'shopforge' ); ?>
		</span>
	</div>

	<div class="shopforge-customizer">

		<div class="shopforge-customizer-preview">
			<div class="shopforge-customizer-toolbar">
				<label for="shopforge-preview-product"><?php esc_html_e( 'Preview product:', 'shopforge' ); ?></label>
				<select id="shopforge-preview-product">
					<?php foreach ( $products as $p ) : ?>
					<option value="<?php echo esc_attr( $p->get_id() ); ?>" <?php selected( $p->get_id(), $preview->get_id() ); ?>>
						<?php echo esc_html( $p->get_name() ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="shopforge-preview-reload"><i class="fa-solid fa-rotate" aria-hidden="true"></i> <?php esc_html_e( 'Reload', 'shopforge' ); ?></button>
			</div>
			<iframe id="shopforge-preview-frame" src="<?php echo esc_url( add_query_arg( 'shopforge_preview_nonce', time(), get_permalink( $preview->get_id() ) ) ); ?>"></iframe>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="shopforge-customizer-panel">
			<?php wp_nonce_field( 'shopforge_save_customizer' ); ?>
			<input type="hidden" name="action" value="shopforge_save_customizer">
			<input type="hidden" name="shopforge_preview_product" id="shopforge-preview-product-field" value="<?php echo esc_attr( $preview->get_id() ); ?>">

			<p class="shopforge-customizer-hint shopforge-customizer-hint--top"><i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i> <?php esc_html_e( 'Click an element in the preview to open its controls.', 'shopforge' ); ?></p>

			<!-- ==== WISHLIST ==== -->
			<details class="shopforge-customizer-section" data-section="wl">
			<summary><i class="fa-solid fa-heart" aria-hidden="true"></i> <?php esc_html_e( 'Wishlist button', 'shopforge' ); ?></summary>

			<div class="shopforge-field">
				<label><?php esc_html_e( 'Width', 'shopforge' ); ?></label>
				<label class="shopforge-radio"><input type="radio" name="shopforge_wl_width" value="auto" data-live="wl" <?php checked( $wl['width'], 'auto' ); ?>> <?php esc_html_e( 'Auto (fits its content — recommended for flex rows like The7)', 'shopforge' ); ?></label>
				<label class="shopforge-radio"><input type="radio" name="shopforge_wl_width" value="full" data-live="wl" <?php checked( $wl['width'], 'full' ); ?>> <?php esc_html_e( 'Full width, same row', 'shopforge' ); ?></label>
				<label class="shopforge-radio"><input type="radio" name="shopforge_wl_width" value="stacked" data-live="wl" <?php checked( $wl['width'], 'stacked' ); ?>> <?php esc_html_e( 'Stacked on its own row', 'shopforge' ); ?></label>
			</div>

			<div class="shopforge-field">
				<label for="shopforge_wl_gap"><?php esc_html_e( 'Gap from quantity selector', 'shopforge' ); ?> (<span id="shopforge-wl-gap-val"><?php echo (int) $wl['gap']; ?></span>px)</label>
				<input type="range" id="shopforge_wl_gap" name="shopforge_wl_gap" min="0" max="60" step="2" value="<?php echo (int) $wl['gap']; ?>" data-live="wl">
			</div>

			<div class="shopforge-field">
				<label class="shopforge-toggle-inline">
					<input type="checkbox" name="shopforge_wl_show_label" value="1" data-live="wl" <?php checked( $wl['show_label'] ); ?>>
					<?php esc_html_e( 'Show text label ("Add to wishlist")', 'shopforge' ); ?>
				</label>
			</div>

			<div class="shopforge-field">
				<label class="shopforge-toggle-inline">
					<input type="checkbox" name="shopforge_wl_colors_enabled" value="1" data-live="wl" class="shopforge-colors-toggle" <?php checked( $wl['colors_enabled'] ); ?>>
					<?php esc_html_e( 'Override colors', 'shopforge' ); ?>
				</label>
				<div class="shopforge-color-row" <?php echo $wl['colors_enabled'] ? '' : 'style="display:none"'; ?>>
					<label><?php esc_html_e( 'Background', 'shopforge' ); ?> <input type="color" name="shopforge_wl_bg" value="<?php echo esc_attr( $wl['bg'] ); ?>" data-live="wl"></label>
					<label><?php esc_html_e( 'Icon', 'shopforge' ); ?> <input type="color" name="shopforge_wl_icon_color" value="<?php echo esc_attr( $wl['icon_color'] ); ?>" data-live="wl"></label>
					<label><?php esc_html_e( 'Text', 'shopforge' ); ?> <input type="color" name="shopforge_wl_text_color" value="<?php echo esc_attr( $wl['text_color'] ); ?>" data-live="wl"></label>
				</div>
			</div>

			<div class="shopforge-field shopforge-size-row">
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Height', 'shopforge' ); ?> (<span id="shopforge-wl-height-val"><?php echo (int) $wl['height']; ?></span>px)
					<input type="range" id="shopforge_wl_height" name="shopforge_wl_height" min="0" max="100" step="1" value="<?php echo (int) $wl['height']; ?>" data-live="wl">
				</label>
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Min width', 'shopforge' ); ?> (<span id="shopforge-wl-min-width-val"><?php echo (int) $wl['min_width']; ?></span>px)
					<input type="range" id="shopforge_wl_min_width" name="shopforge_wl_min_width" min="0" max="400" step="5" value="<?php echo (int) $wl['min_width']; ?>" data-live="wl">
				</label>
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Font size', 'shopforge' ); ?> (<span id="shopforge-wl-font-size-val"><?php echo (int) $wl['font_size']; ?></span>px)
					<input type="range" id="shopforge_wl_font_size" name="shopforge_wl_font_size" min="0" max="40" step="1" value="<?php echo (int) $wl['font_size']; ?>" data-live="wl">
				</label>
				<p class="shopforge-size-hint"><?php esc_html_e( '0 = theme default (no override)', 'shopforge' ); ?></p>
			</div>

			<?php shopforge_customizer_margin_fields( 'wl', $wl, false ); ?>

			</details>

			<!-- ==== ADD TO CART ==== -->
			<details class="shopforge-customizer-section" data-section="atc">
			<summary><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> <?php esc_html_e( 'Add to cart button', 'shopforge' ); ?></summary>

			<div class="shopforge-field">
				<label><?php esc_html_e( 'Width', 'shopforge' ); ?></label>
				<label class="shopforge-radio"><input type="radio" name="shopforge_atc_width" value="auto" data-live="atc" <?php checked( $atc['width'], 'auto' ); ?>> <?php esc_html_e( 'Theme default', 'shopforge' ); ?></label>
				<label class="shopforge-radio"><input type="radio" name="shopforge_atc_width" value="full" data-live="atc" <?php checked( $atc['width'], 'full' ); ?>> <?php esc_html_e( 'Full width, same row', 'shopforge' ); ?></label>
				<label class="shopforge-radio"><input type="radio" name="shopforge_atc_width" value="stacked" data-live="atc" <?php checked( $atc['width'], 'stacked' ); ?>> <?php esc_html_e( 'Stacked on its own row', 'shopforge' ); ?></label>
			</div>

			<div class="shopforge-field">
				<label class="shopforge-toggle-inline">
					<input type="checkbox" name="shopforge_atc_colors_enabled" value="1" data-live="atc" class="shopforge-colors-toggle" <?php checked( $atc['colors_enabled'] ); ?>>
					<?php esc_html_e( 'Override colors', 'shopforge' ); ?>
				</label>
				<div class="shopforge-color-row" <?php echo $atc['colors_enabled'] ? '' : 'style="display:none"'; ?>>
					<label><?php esc_html_e( 'Background', 'shopforge' ); ?> <input type="color" name="shopforge_atc_bg" value="<?php echo esc_attr( $atc['bg'] ); ?>" data-live="atc"></label>
					<label><?php esc_html_e( 'Text', 'shopforge' ); ?> <input type="color" name="shopforge_atc_text_color" value="<?php echo esc_attr( $atc['text_color'] ); ?>" data-live="atc"></label>
				</div>
			</div>

			<div class="shopforge-field">
				<label for="shopforge_atc_radius"><?php esc_html_e( 'Corner radius', 'shopforge' ); ?> (<span id="shopforge-atc-radius-val"><?php echo (int) $atc['radius']; ?></span>px)</label>
				<input type="range" id="shopforge_atc_radius" name="shopforge_atc_radius" min="0" max="40" step="1" value="<?php echo (int) $atc['radius']; ?>" data-live="atc">
			</div>

			<div class="shopforge-field shopforge-size-row">
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Height', 'shopforge' ); ?> (<span id="shopforge-atc-height-val"><?php echo (int) $atc['height']; ?></span>px)
					<input type="range" id="shopforge_atc_height" name="shopforge_atc_height" min="0" max="100" step="1" value="<?php echo (int) $atc['height']; ?>" data-live="atc">
				</label>
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Min width', 'shopforge' ); ?> (<span id="shopforge-atc-min-width-val"><?php echo (int) $atc['min_width']; ?></span>px)
					<input type="range" id="shopforge_atc_min_width" name="shopforge_atc_min_width" min="0" max="400" step="5" value="<?php echo (int) $atc['min_width']; ?>" data-live="atc">
				</label>
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Font size', 'shopforge' ); ?> (<span id="shopforge-atc-font-size-val"><?php echo (int) $atc['font_size']; ?></span>px)
					<input type="range" id="shopforge_atc_font_size" name="shopforge_atc_font_size" min="0" max="40" step="1" value="<?php echo (int) $atc['font_size']; ?>" data-live="atc">
				</label>
				<p class="shopforge-size-hint"><?php esc_html_e( '0 = theme default (no override)', 'shopforge' ); ?></p>
			</div>

			<?php shopforge_customizer_margin_fields( 'atc', $atc ); ?>

			</details>

			<!-- ==== QUANTITY ==== -->
			<details class="shopforge-customizer-section" data-section="qty">
			<summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> <?php esc_html_e( 'Quantity selector', 'shopforge' ); ?></summary>

			<div class="shopforge-field">
				<label class="shopforge-toggle-inline">
					<input type="checkbox" name="shopforge_qty_colors_enabled" value="1" data-live="qty" class="shopforge-colors-toggle" <?php checked( $qty['colors_enabled'] ); ?>>
					<?php esc_html_e( 'Override colors', 'shopforge' ); ?>
				</label>
				<div class="shopforge-color-row" <?php echo $qty['colors_enabled'] ? '' : 'style="display:none"'; ?>>
					<label><?php esc_html_e( 'Background', 'shopforge' ); ?> <input type="color" name="shopforge_qty_bg" value="<?php echo esc_attr( $qty['bg'] ); ?>" data-live="qty"></label>
					<label><?php esc_html_e( 'Icon', 'shopforge' ); ?> <input type="color" name="shopforge_qty_icon_color" value="<?php echo esc_attr( $qty['icon_color'] ); ?>" data-live="qty"></label>
				</div>
			</div>

			<div class="shopforge-field">
				<label for="shopforge_qty_radius"><?php esc_html_e( 'Corner radius', 'shopforge' ); ?> (<span id="shopforge-qty-radius-val"><?php echo (int) $qty['radius']; ?></span>px)</label>
				<input type="range" id="shopforge_qty_radius" name="shopforge_qty_radius" min="0" max="40" step="1" value="<?php echo (int) $qty['radius']; ?>" data-live="qty">
			</div>

			<div class="shopforge-field shopforge-size-row">
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Height', 'shopforge' ); ?> (<span id="shopforge-qty-height-val"><?php echo (int) $qty['height']; ?></span>px)
					<input type="range" id="shopforge_qty_height" name="shopforge_qty_height" min="0" max="100" step="1" value="<?php echo (int) $qty['height']; ?>" data-live="qty">
				</label>
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Width', 'shopforge' ); ?> (<span id="shopforge-qty-width-val"><?php echo (int) $qty['width']; ?></span>px)
					<input type="range" id="shopforge_qty_width" name="shopforge_qty_width" min="0" max="100" step="1" value="<?php echo (int) $qty['width']; ?>" data-live="qty">
				</label>
				<label class="shopforge-size-field">
					<?php esc_html_e( 'Font size', 'shopforge' ); ?> (<span id="shopforge-qty-font-size-val"><?php echo (int) $qty['font_size']; ?></span>px)
					<input type="range" id="shopforge_qty_font_size" name="shopforge_qty_font_size" min="0" max="40" step="1" value="<?php echo (int) $qty['font_size']; ?>" data-live="qty">
				</label>
				<p class="shopforge-size-hint"><?php esc_html_e( '0 = theme default (no override)', 'shopforge' ); ?></p>
			</div>

			<?php shopforge_customizer_margin_fields( 'qty', $qty ); ?>

			</details>

			<p class="shopforge-customizer-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?php esc_html_e( 'Changes apply live in the preview above as you edit them. Nothing is saved until you click Save.', 'shopforge' ); ?></p>

			<?php submit_button( __( 'Save layout', 'shopforge' ), 'primary large', 'submit', false ); ?>
		</form>
	</div>

	<style>
	.shopforge-customizer { display: flex; gap: 20px; align-items: flex-start; margin-top: 16px; flex-wrap: wrap; }
	.shopforge-customizer-preview { flex: 1 1 600px; min-width: 320px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden; position: sticky; top: 32px; }
	.shopforge-customizer-toolbar { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f6f7f7; border-bottom: 1px solid #dcdcde; }
	.shopforge-customizer-toolbar select { flex: 1; max-width: 320px; }
	#shopforge-preview-frame { width: 100%; height: 760px; border: 0; display: block; }
	.shopforge-customizer-panel { flex: 0 0 340px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 18px; }
	.shopforge-customizer-section { border: 1px solid #dcdcde; border-radius: 6px; margin-bottom: 12px; padding: 0 14px; transition: background .2s, border-color .2s; }
	.shopforge-customizer-section[open] { padding-bottom: 14px; }
	.shopforge-customizer-section.shopforge-customizer-section--pulse { border-color: #2271b1; background: #f0f6fc; }
	.shopforge-customizer-section summary { margin: 0 -14px; padding: 12px 14px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px; cursor: pointer; list-style: none; }
	.shopforge-customizer-section summary::-webkit-details-marker { display: none; }
	.shopforge-customizer-section summary::after { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; margin-left: auto; color: #646970; }
	.shopforge-customizer-section[open] summary::after { content: '\f106'; }
	.shopforge-customizer-hint--top { margin-bottom: 14px; }
	.shopforge-field { margin-bottom: 16px; }
	.shopforge-customizer-section .shopforge-field:last-child { margin-bottom: 0; }
	.shopforge-field > label:first-child { display: block; font-weight: 600; margin-bottom: 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #646970; }
	.shopforge-radio { display: block; font-weight: 400; margin: 4px 0; }
	.shopforge-toggle-inline { display: flex; align-items: center; gap: 6px; font-weight: 400; }
	#shopforge_wl_gap, #shopforge_atc_radius, #shopforge_qty_radius { width: 100%; }
	.shopforge-color-row { display: flex; gap: 14px; margin-top: 10px; flex-wrap: wrap; }
	.shopforge-color-row label { display: flex; align-items: center; gap: 6px; font-weight: 400; font-size: 13px; }
	.shopforge-color-row input[type="color"] { width: 34px; height: 26px; padding: 0; border: 1px solid #dcdcde; border-radius: 4px; }
	.shopforge-size-row { display: flex; flex-wrap: wrap; gap: 12px; }
	.shopforge-size-field { flex: 1 1 90px; font-weight: 400; font-size: 12px; color: #646970; }
	.shopforge-size-field input[type="range"] { width: 100%; margin-top: 4px; }
	.shopforge-size-hint { flex-basis: 100%; margin: 4px 0 0; font-size: 11px; color: #8c8f94; }
	.shopforge-customizer-hint { font-size: 12px; color: #646970; background: #f6f7f7; padding: 8px 10px; border-radius: 6px; }
	</style>

	<script>
	( function () {
		var frame  = document.getElementById( 'shopforge-preview-frame' );
		var select = document.getElementById( 'shopforge-preview-product' );
		var hidden = document.getElementById( 'shopforge-preview-product-field' );
		var reload = document.getElementById( 'shopforge-preview-reload' );

		var HIGHLIGHT = [
			{ selector: '.shopforge-wl-btn--single', label: <?php echo wp_json_encode( __( 'Wishlist button', 'shopforge' ) ); ?>, section: 'wl' },
			{ selector: '.single_add_to_cart_button', label: <?php echo wp_json_encode( __( 'Add to cart button', 'shopforge' ) ); ?>, section: 'atc' },
			{ selector: '.quantity.buttons_added', label: <?php echo wp_json_encode( __( 'Quantity selector', 'shopforge' ) ); ?>, section: 'qty' },
			{ selector: 'form.cart', label: <?php echo wp_json_encode( __( 'Add-to-cart form', 'shopforge' ) ); ?>, section: null }
		];

		function openSection( id ) {
			document.querySelectorAll( '.shopforge-customizer-section' ).forEach( function ( section ) {
				var match = section.dataset.section === id;
				section.open = match;
				if ( match ) {
					section.classList.add( 'shopforge-customizer-section--pulse' );
					section.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					setTimeout( function () { section.classList.remove( 'shopforge-customizer-section--pulse' ); }, 900 );
				}
			} );
		}

		function val( name ) {
			var el = document.querySelector( '[name="' + name + '"]:checked' ) || document.querySelector( '[name="' + name + '"]' );
			return el ? ( el.type === 'checkbox' ? el.checked : el.value ) : null;
		}

		function sizingCSS( selector, prefix, fields ) {
			var decls = '';
			fields.forEach( function ( pair ) {
				var input = document.getElementById( 'shopforge_' + prefix + '_' + pair[0] );
				if ( input && parseInt( input.value, 10 ) > 0 ) {
					decls += pair[1] + ':' + input.value + 'px!important;';
				}
			} );
			return decls ? selector + '{' + decls + '}' : '';
		}

		function marginCSS( selector, prefix, sides ) {
			var decls = '';
			sides.forEach( function ( side ) {
				var input = document.getElementById( 'shopforge_' + prefix + '_margin_' + side );
				if ( input && parseInt( input.value, 10 ) !== 0 ) {
					decls += 'margin-' + side + ':' + input.value + 'px!important;';
				}
			} );
			return decls ? selector + '{' + decls + '}' : '';
		}

		function applyLiveStyle() {
			var doc = frame.contentDocument;
			if ( ! doc ) return;

			var wlWidth  = val( 'shopforge_wl_width' ) || 'auto';
			var wlWide   = wlWidth === 'full' || wlWidth === 'stacked';
			var wlGap    = document.getElementById( 'shopforge_wl_gap' ).value;
			var wlLabel  = val( 'shopforge_wl_show_label' );
			var wlColors = val( 'shopforge_wl_colors_enabled' );

			var atcWidth  = val( 'shopforge_atc_width' ) || 'auto';
			var atcColors = val( 'shopforge_atc_colors_enabled' );
			var atcRadius = document.getElementById( 'shopforge_atc_radius' ).value;

			var qtyColors = val( 'shopforge_qty_colors_enabled' );
			var qtyRadius = document.getElementById( 'shopforge_qty_radius' ).value;

			var css = '.shopforge-wl-btn--single,.single_add_to_cart_button,.quantity.buttons_added .minus,.quantity.buttons_added .plus{align-self:center!important;}';

			css += '.shopforge-wl-btn--single{'
				+ 'width:' + ( wlWide ? '100%' : 'auto' ) + '!important;'
				+ 'flex:' + ( wlWide ? '1 1 100%' : '0 0 auto' ) + '!important;'
				+ 'margin-left:' + wlGap + 'px!important;'
				+ ( wlWidth === 'stacked' ? 'order:5!important;' : '' )
				+ '}';
			if ( ! wlLabel ) css += '.shopforge-wl-btn--single .shopforge-wl-btn__label{display:none!important;}';
			if ( wlColors ) {
				css += '.shopforge-wl-btn--single{background:' + val( 'shopforge_wl_bg' ) + '!important;border-color:' + val( 'shopforge_wl_bg' ) + '!important;}'
					+ '.shopforge-wl-btn--single i{color:' + val( 'shopforge_wl_icon_color' ) + '!important;}'
					+ '.shopforge-wl-btn--single .shopforge-wl-btn__label{color:' + val( 'shopforge_wl_text_color' ) + '!important;}';
			}

			if ( atcWidth !== 'auto' ) {
				css += '.single_add_to_cart_button{width:100%!important;flex:' + ( atcWidth === 'stacked' ? '1 1 100%' : '1 1 auto' ) + '!important;' + ( atcWidth === 'stacked' ? 'order:6!important;' : '' ) + '}';
			}
			if ( atcColors ) {
				css += '.single_add_to_cart_button{background:' + val( 'shopforge_atc_bg' ) + '!important;color:' + val( 'shopforge_atc_text_color' ) + '!important;}';
			}
			css += '.single_add_to_cart_button{border-radius:' + atcRadius + 'px!important;}';

			if ( qtyColors ) {
				css += '.quantity.buttons_added .minus,.quantity.buttons_added .plus{background:' + val( 'shopforge_qty_bg' ) + '!important;color:' + val( 'shopforge_qty_icon_color' ) + '!important;}';
			}
			css += '.quantity.buttons_added .minus,.quantity.buttons_added .plus{border-radius:' + qtyRadius + 'px!important;}';

			var SIZE_FIELDS = [ [ 'height', 'height' ], [ 'min_width', 'min-width' ], [ 'font_size', 'font-size' ] ];
			css += sizingCSS( '.shopforge-wl-btn--single', 'wl', SIZE_FIELDS );
			css += sizingCSS( '.single_add_to_cart_button', 'atc', SIZE_FIELDS );
			css += sizingCSS( '.quantity.buttons_added .minus,.quantity.buttons_added .plus', 'qty', [ [ 'height', 'height' ], [ 'width', 'width' ], [ 'font_size', 'font-size' ] ] );
			var qtyFontSize = document.getElementById( 'shopforge_qty_font_size' ).value;
			if ( qtyFontSize > 0 ) {
				css += '.quantity.buttons_added .minus svg,.quantity.buttons_added .plus svg{width:' + qtyFontSize + 'px!important;height:' + qtyFontSize + 'px!important;}';
			}

			css += marginCSS( '.shopforge-wl-btn--single', 'wl', [ 'top', 'right', 'bottom' ] );
			css += marginCSS( '.single_add_to_cart_button', 'atc', [ 'top', 'right', 'bottom', 'left' ] );
			css += marginCSS( '.quantity.buttons_added .minus,.quantity.buttons_added .plus', 'qty', [ 'top', 'right', 'bottom', 'left' ] );

			var styleEl = doc.getElementById( 'shopforge-customizer-live' );
			if ( ! styleEl ) {
				styleEl = doc.createElement( 'style' );
				styleEl.id = 'shopforge-customizer-live';
				doc.head.appendChild( styleEl );
			}
			styleEl.textContent = css;

			[ [ '.shopforge-wl-btn--single', wlWidth ], [ '.single_add_to_cart_button', atcWidth ] ].forEach( function ( pair ) {
				var el = doc.querySelector( pair[0] );
				if ( el && el.parentElement ) el.parentElement.style.flexWrap = pair[1] === 'stacked' ? 'wrap' : '';
			} );
		}

		function setupHighlight() {
			var doc = frame.contentDocument;
			if ( ! doc || doc.getElementById( 'shopforge-customizer-highlight-css' ) ) return;

			var style = doc.createElement( 'style' );
			style.id = 'shopforge-customizer-highlight-css';
			style.textContent = '.shopforge-customizer-hover{outline:2px dashed #2271b1!important;outline-offset:2px!important;position:relative!important;}'
				+ '.shopforge-customizer-tag{position:absolute;top:-22px;left:0;background:#2271b1;color:#fff;font:11px/1.4 sans-serif;padding:2px 6px;border-radius:3px;z-index:99999;white-space:nowrap;pointer-events:none;}';
			doc.head.appendChild( style );

			HIGHLIGHT.forEach( function ( item ) {
				doc.querySelectorAll( item.selector ).forEach( function ( el ) {
					el.addEventListener( 'mouseenter', function () {
						el.classList.add( 'shopforge-customizer-hover' );
						var tag = doc.createElement( 'span' );
						tag.className = 'shopforge-customizer-tag';
						tag.textContent = item.label + ( item.section ? ' — <?php echo esc_js( __( 'click to edit', 'shopforge' ) ); ?>' : '' );
						el.appendChild( tag );
					} );
					el.addEventListener( 'mouseleave', function () {
						el.classList.remove( 'shopforge-customizer-hover' );
						var tag = el.querySelector( '.shopforge-customizer-tag' );
						if ( tag ) tag.remove();
					} );
					if ( item.section ) {
						// Fase di cattura: intercetta il click prima dei listener reali
						// della pagina (aggiungi al carrello / attiva wishlist) — qui il
						// click serve solo a selezionare l'elemento da modificare.
						el.addEventListener( 'click', function ( e ) {
							e.preventDefault();
							e.stopPropagation();
							openSection( item.section );
						}, true );
					}
				} );
			} );
		}

		frame.addEventListener( 'load', function () {
			setupHighlight();
			applyLiveStyle();
		} );

		document.querySelectorAll( '[data-live]' ).forEach( function ( input ) {
			input.addEventListener( 'input', applyLiveStyle );
			input.addEventListener( 'change', applyLiveStyle );
		} );

		document.querySelectorAll( '.shopforge-colors-toggle' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'change', function () {
				this.closest( '.shopforge-field' ).querySelector( '.shopforge-color-row' ).style.display = this.checked ? 'flex' : 'none';
			} );
		} );

		document.querySelectorAll( '.shopforge-customizer-panel input[type="range"]' ).forEach( function ( range ) {
			var valEl = document.getElementById( range.id.replace( /_/g, '-' ) + '-val' );
			if ( ! valEl ) return;
			range.addEventListener( 'input', function () {
				valEl.textContent = this.value;
			} );
		} );

		function reloadFrame() {
			var id = select.value;
			hidden.value = id;
			window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=shopforge&tab=customizer' ) ); ?>&preview=' + id;
		}

		reload.addEventListener( 'click', reloadFrame );
		select.addEventListener( 'change', reloadFrame );
	} )();
	</script>
	<?php
}
