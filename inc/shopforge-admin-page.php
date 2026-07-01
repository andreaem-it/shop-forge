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
		'modules'    => [ 'label' => 'Moduli',    'icon' => 'fa-solid fa-puzzle-piece' ],
		'license'    => [ 'label' => 'Licenza',    'icon' => 'fa-solid fa-key' ],
		'shortcodes' => [ 'label' => 'Shortcode', 'icon' => 'fa-solid fa-code' ],
	];

	$active_tab = sanitize_key( $_GET['tab'] ?? 'modules' );
	if ( ! isset( $tabs[ $active_tab ] ) ) {
		$active_tab = 'modules';
	}

	// FontAwesome per le icone (già usato dalla tab Moduli, qui garantito
	// anche quando si apre direttamente su Licenza/Shortcode).
	wp_enqueue_script( 'fontawesome-kit', SHOPFORGE_FA_KIT_URL, [], null, false );
	wp_script_add_data( 'fontawesome-kit', 'crossorigin', 'anonymous' );
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
				Licenza, moduli attivi e shortcode disponibili in un unico pannello.
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
			switch ( $active_tab ) {
				case 'license':
					shopforge_admin_tab_license();
					break;
				case 'shortcodes':
					shopforge_admin_tab_shortcodes();
					break;
				default:
					if ( function_exists( 'shopforge_admin_tab_modules' ) ) {
						shopforge_admin_tab_modules();
					} else {
						echo '<p>Questa sezione richiede WooCommerce attivo.</p>';
					}
			}
			?>
		</div>
	</div>

	<style>
	.shopforge-settings-wrap { max-width: 960px; }
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
			'desc'  => 'Mostra la descrizione della variante selezionata nella pagina prodotto (si aggiorna al cambio variante).',
			'attrs' => [],
			'example' => '[shopforge_variation_description]',
		],
		[
			'name'  => 'wc_price_iva_box',
			'desc'  => 'Box prezzo con doppio importo, IVA inclusa ed esclusa.',
			'attrs' => [ 'id' => 'ID prodotto (opzionale, default: prodotto corrente)' ],
			'example' => '[wc_price_iva_box]',
		],
		[
			'name'  => 'data_consegna_prodotto',
			'desc'  => 'Stima la data di consegna in base a giorni lavorativi, festivi italiani e orario di cutoff per l\'ordine.',
			'attrs' => [],
			'example' => '[data_consegna_prodotto]',
		],
		[
			'name'  => 'buy_now_button',
			'desc'  => 'Pulsante che aggiunge il prodotto al carrello e porta direttamente al checkout.',
			'attrs' => [],
			'example' => '[buy_now_button]',
		],
		[
			'name'  => 'product_faq',
			'desc'  => 'Domande frequenti del prodotto, gestite dal metabox "FAQ Prodotto" nella scheda prodotto.',
			'attrs' => [
				'product_id' => 'ID prodotto (opzionale, default: prodotto corrente)',
				'style'      => '"accordion" (default) oppure "list"',
			],
			'example' => '[product_faq]',
		],
		[
			'name'  => 'product_compatibility',
			'desc'  => 'Lista di compatibilità del prodotto, gestita dal metabox "Compatibilità" nella scheda prodotto.',
			'attrs' => [ 'product_id' => 'ID prodotto (opzionale, default: prodotto corrente)' ],
			'example' => '[product_compatibility]',
		],
		[
			'name'  => 'product_datasheets',
			'desc'  => 'Schede tecniche PDF del prodotto, gestite dal metabox "Schede Tecniche (PDF)" nella scheda prodotto.',
			'attrs' => [ 'product_id' => 'ID prodotto (opzionale, default: prodotto corrente)' ],
			'example' => '[product_datasheets]',
		],
	];
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-code" aria-hidden="true"></i>
		Shortcode disponibili
		<span class="shopforge-section-hint">
			Usali in pagine, articoli, descrizioni prodotto o widget shortcode di Elementor.
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
				<button type="button" class="button button-small shopforge-shortcode-copy" data-code="<?php echo esc_attr( $sc['example'] ); ?>">
					Copia
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
				btn.textContent = 'Copiato!';
				setTimeout( function () { btn.textContent = original; }, 1500 );
			} );
		} );
	} );
	</script>
	<?php
}
