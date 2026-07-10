<?php
/**
 * Pagina admin unificata ShopForge — menu proprio (fuori da WooCommerce),
 * a schede: Licenza / Moduli / Shortcode.
 *
 * Il contenuto delle singole tab resta nei rispettivi file (riuso, non
 * duplicazione):
 *  - Licenza  → shopforge_admin_tab_license()   in inc/shopforge-license.php
 *  - Moduli   → shopforge_admin_tab_modules()   in inc/shopforge-settings.php
 *  - Shortcode → shopforge_admin_tab_shortcodes() qui sotto
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// MENU ADMIN — voce unica, fuori dal menu WooCommerce
// =============================================================================

add_action( 'admin_menu', function () {
	add_menu_page(
		'ShopForge',
		'ShopForge',
		'manage_woocommerce',
		'shopforge',
		'shopforge_admin_page_render',
		'dashicons-admin-plugins',
		56
	);
} );


// =============================================================================
// ROUTER + WRAP CONDIVISO
// =============================================================================

function shopforge_admin_page_render(): void {
	$tabs = [
		'features'   => [ 'label' => __( 'Core features', 'shopforge' ), 'icon' => 'fa-solid fa-sliders' ],
		'modules'    => [ 'label' => __( 'Modules', 'shopforge' ),       'icon' => 'fa-solid fa-puzzle-piece' ],
		'config'     => [ 'label' => __( 'Configuration', 'shopforge' ), 'icon' => 'fa-solid fa-gear' ],
		'theme'      => [ 'label' => __( 'Theme', 'shopforge' ),        'icon' => 'fa-solid fa-swatchbook' ],
		'colors'     => [ 'label' => __( 'Colors', 'shopforge' ),       'icon' => 'fa-solid fa-palette' ],
		'license'    => [ 'label' => __( 'License', 'shopforge' ),      'icon' => 'fa-solid fa-key' ],
		'shortcodes' => [ 'label' => __( 'Shortcodes', 'shopforge' ),   'icon' => 'fa-solid fa-code' ],
	];

	if ( function_exists( 'shopforge_is_module_active' ) && shopforge_is_module_active( 'receipts' ) ) {
		$tabs['receipts'] = [ 'label' => __( 'Receipts', 'shopforge' ), 'icon' => 'fa-solid fa-receipt' ];
	}

	$active_tab = sanitize_key( $_GET['tab'] ?? 'features' );
	if ( ! isset( $tabs[ $active_tab ] ) ) {
		$active_tab = 'features';
	}

	// FontAwesome per le icone (già usato dalla tab Moduli, qui garantito
	// anche quando si apre direttamente su Licenza/Shortcode).
	shopforge_enqueue_fontawesome();
	?>
	<div class="wrap shopforge-settings-wrap">

		<div class="shopforge-settings-head">
			<h1>
				<span class="shopforge-settings-logo">
					<i class="fa-solid fa-puzzle-piece"></i>
				</span>
				ShopForge
			</h1>
			<p class="shopforge-settings-sub">
				<?php esc_html_e( 'License, active modules and available shortcodes in a single panel.', 'shopforge' ); ?>
			</p>
		</div>

		<nav class="nav-tab-wrapper shopforge-tabs">
			<?php foreach ( $tabs as $id => $tab ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=shopforge&tab=' . $id ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
				<i class="<?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></i>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
			<?php endforeach; ?>
		</nav>

		<div class="shopforge-tab-content">
			<?php
			$woocommerce_tabs = [ 'features', 'modules', 'config', 'theme', 'colors' ];
			if ( in_array( $active_tab, $woocommerce_tabs, true ) && ! function_exists( 'shopforge_admin_tab_features' ) ) {
				echo '<p>' . esc_html__( 'This section requires WooCommerce to be active.', 'shopforge' ) . '</p>';
			} else {
				switch ( $active_tab ) {
					case 'license':
						shopforge_admin_tab_license();
						break;
					case 'shortcodes':
						shopforge_admin_tab_shortcodes();
						break;
					case 'receipts':
						if ( function_exists( 'shopforge_admin_tab_receipts' ) ) {
							shopforge_admin_tab_receipts();
						}
						break;
					case 'modules':
						shopforge_admin_tab_modules();
						break;
					case 'config':
						shopforge_admin_tab_config();
						break;
					case 'theme':
						shopforge_admin_tab_theme();
						break;
					case 'colors':
						shopforge_admin_tab_colors();
						break;
					default:
						shopforge_admin_tab_features();
				}
			}
			?>
		</div>
	</div>

	<style>
	.shopforge-settings-head { margin: 20px 0 20px; }
	.shopforge-settings-head h1 {
		display: flex; align-items: center; gap: 12px;
		font-size: 22px; font-weight: 800; color: #1d2327; margin: 0 0 6px;
	}
	.shopforge-settings-logo {
		width: 38px; height: 38px; border-radius: 10px;
		background: #2271b1; color: #fff;
		display: flex; align-items: center; justify-content: center;
		font-size: 17px; flex-shrink: 0;
	}
	.shopforge-settings-sub { margin: 0; color: #646970; font-size: 14px; line-height: 1.6; max-width: 680px; }

	.shopforge-tabs { margin-bottom: 0; }
	.shopforge-tabs .nav-tab { display: inline-flex; align-items: center; gap: 7px; }
	.shopforge-tab-content { padding-top: 20px; }
	</style>
	<?php
}


// =============================================================================
// TAB SHORTCODE — riferimento sola lettura
// =============================================================================

function shopforge_admin_tab_shortcodes(): void {
	$shortcodes = [
		[
			'name'  => 'shopforge_variation_description',
			'desc'  => __( 'Shows the description of the selected variation on the product page (updates when the variation changes).', 'shopforge' ),
			'attrs' => [],
			'example' => '[shopforge_variation_description]',
		],
		[
			'name'  => 'wc_price_iva_box',
			'desc'  => __( 'Price box with two amounts, VAT included and excluded.', 'shopforge' ),
			'attrs' => [ 'id' => __( 'Product ID (optional, default: current product)', 'shopforge' ) ],
			'example' => '[wc_price_iva_box]',
		],
		[
			'name'  => 'data_consegna_prodotto',
			'desc'  => __( 'Estimates the delivery date based on business days, public holidays and the order cutoff time.', 'shopforge' ),
			'attrs' => [],
			'example' => '[data_consegna_prodotto]',
		],
		[
			'name'  => 'buy_now_button',
			'desc'  => __( 'Button that adds the product to the cart and goes straight to checkout.', 'shopforge' ),
			'attrs' => [],
			'example' => '[buy_now_button]',
		],
		[
			'name'  => 'stock_status_text',
			'desc'  => __( 'Availability label (colored dot + "In stock"/"Out of stock" text).', 'shopforge' ),
			'attrs' => [
				'font_size'   => __( 'CSS font size (optional, e.g. "16px")', 'shopforge' ),
				'font_family' => __( 'CSS font-family (optional)', 'shopforge' ),
			],
			'example' => '[stock_status_text]',
		],
		[
			'name'  => 'product_faq',
			'desc'  => __( 'Product FAQ, managed from the "Product FAQ" metabox on the product edit screen.', 'shopforge' ),
			'attrs' => [
				'product_id' => __( 'Product ID (optional, default: current product)', 'shopforge' ),
				'style'      => __( '"accordion" (default) or "list"', 'shopforge' ),
			],
			'example' => '[product_faq]',
		],
		[
			'name'  => 'product_compatibility',
			'desc'  => __( 'Product compatibility list, managed from the "Compatibility" metabox on the product edit screen.', 'shopforge' ),
			'attrs' => [ 'product_id' => __( 'Product ID (optional, default: current product)', 'shopforge' ) ],
			'example' => '[product_compatibility]',
		],
		[
			'name'  => 'product_datasheets',
			'desc'  => __( 'Product PDF datasheets, managed from the "Datasheets (PDF)" metabox on the product edit screen.', 'shopforge' ),
			'attrs' => [ 'product_id' => __( 'Product ID (optional, default: current product)', 'shopforge' ) ],
			'example' => '[product_datasheets]',
		],
	];
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-code" aria-hidden="true"></i>
		<?php esc_html_e( 'Available shortcodes', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'Use them in pages, posts, product descriptions or Elementor shortcode widgets.', 'shopforge' ); ?>
		</span>
	</div>

	<div class="shopforge-shortcode-grid">
		<?php foreach ( $shortcodes as $sc ) : ?>
		<div class="shopforge-shortcode-card">
			<h3 class="shopforge-shortcode-card__title">[<?php echo esc_html( $sc['name'] ); ?>]</h3>
			<p class="shopforge-shortcode-card__desc"><?php echo esc_html( $sc['desc'] ); ?></p>

			<?php if ( $sc['attrs'] ) : ?>
			<table class="shopforge-shortcode-card__attrs">
				<?php foreach ( $sc['attrs'] as $attr => $attr_desc ) : ?>
				<tr>
					<td><code><?php echo esc_html( $attr ); ?></code></td>
					<td><?php echo esc_html( $attr_desc ); ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>

			<div class="shopforge-shortcode-card__example">
				<code><?php echo esc_html( $sc['example'] ); ?></code>
				<button type="button" class="button button-small shopforge-shortcode-copy"
				        data-code="<?php echo esc_attr( $sc['example'] ); ?>"
				        data-copied="<?php esc_attr_e( 'Copied!', 'shopforge' ); ?>">
					<?php esc_html_e( 'Copy', 'shopforge' ); ?>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<style>
	.shopforge-shortcode-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
		gap: 14px;
	}
	.shopforge-shortcode-card {
		background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
		padding: 18px 20px;
	}
	.shopforge-shortcode-card__title {
		margin: 0 0 8px; font-size: 14px; font-weight: 700; color: #1d2327;
		font-family: Consolas, Monaco, monospace;
	}
	.shopforge-shortcode-card__desc { margin: 0 0 12px; font-size: 13px; color: #646970; line-height: 1.5; }
	.shopforge-shortcode-card__attrs { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
	.shopforge-shortcode-card__attrs td { padding: 4px 0; font-size: 12px; color: #646970; vertical-align: top; }
	.shopforge-shortcode-card__attrs td:first-child { width: 90px; white-space: nowrap; }
	.shopforge-shortcode-card__attrs code { background: #f6f7f7; padding: 1px 5px; border-radius: 3px; color: #1d2327; }
	.shopforge-shortcode-card__example {
		display: flex; align-items: center; gap: 8px;
		background: #f6f7f7; border-radius: 4px; padding: 8px 10px;
	}
	.shopforge-shortcode-card__example code { flex: 1; font-size: 12px; color: #1d2327; }
	</style>

	<script>
	document.querySelectorAll( '.shopforge-shortcode-copy' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			navigator.clipboard.writeText( btn.dataset.code ).then( function () {
				var original = btn.textContent;
				btn.textContent = btn.dataset.copied;
				setTimeout( function () { btn.textContent = original; }, 1500 );
			} );
		} );
	} );
	</script>
	<?php
}
