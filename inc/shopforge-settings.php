<?php
/**
 * Andrea Emili — Pagina impostazioni modulare
 *
 * Mostra due sezioni distinte:
 *  1. FUNZIONALITÀ BASE (type: 'feature') — Stili e Dashboard
 *  2. MODULI (type: 'module') — Tracking, Wishlist, Preventivi, Resi, Notifiche
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// La registrazione del menu è unica per tutte le tab: vedi
// inc/shopforge-admin-page.php (add_menu_page + router).


// =============================================================================
// SALVATAGGIO
// =============================================================================

/**
 * Salva solo il sottoinsieme di 'shopforge_modules_enabled' relativo al
 * $type_filter indicato ('feature' o 'module'), lasciando invariate le
 * voci dell'altro tipo — ogni tab ha il proprio form, quindi il POST
 * contiene solo i checkbox della sezione inviata.
 */
function shopforge_save_enabled_subset( string $type_filter ): void {
	$has_license = function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license();
	$registry    = shopforge_modules_registry();
	$old         = shopforge_get_enabled_modules();

	$kept = array_filter( $old, fn( $id ) => ( $registry[ $id ]['type'] ?? 'module' ) !== $type_filter );

	$new = [];
	foreach ( array_keys( $registry ) as $id ) {
		if ( ( $registry[ $id ]['type'] ?? 'module' ) !== $type_filter ) {
			continue;
		}
		if ( empty( $_POST[ 'module_' . $id ] ) ) {
			continue;
		}
		if ( $type_filter === 'module' && ! $has_license ) {
			continue;
		}
		$new[] = $id;
	}

	$enabled = array_values( array_unique( array_merge( $kept, $new ) ) );
	update_option( 'shopforge_modules_enabled', $enabled );

	if ( array_diff( $old, $enabled ) || array_diff( $enabled, $old ) ) {
		flush_rewrite_rules();
	}
}

add_action( 'admin_post_shopforge_save_settings', function () {
	if ( ! current_user_can( 'manage_woocommerce' )
	     || ! check_admin_referer( 'shopforge_save_settings' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'shopforge' ) );
	}

	$section = sanitize_key( $_POST['shopforge_settings_section'] ?? '' );

	switch ( $section ) {

		case 'features':
			shopforge_save_enabled_subset( 'feature' );
			break;

		case 'modules':
			shopforge_save_enabled_subset( 'module' );
			break;

		case 'config':
			$return_days = max( 1, (int) ( $_POST['shopforge_return_window_days'] ?? 14 ) );
			update_option( 'shopforge_return_window_days', $return_days );

			$contact_url = esc_url_raw( trim( $_POST['shopforge_contact_url'] ?? '' ) );
			update_option( 'shopforge_contact_url', $contact_url );

			update_option( 'shopforge_17track_key', sanitize_text_field( $_POST['shopforge_17track_key'] ?? '' ) );

			$loyalty_earn_rate = max( 0, (float) ( $_POST['shopforge_loyalty_earn_rate'] ?? 1 ) );
			update_option( 'shopforge_loyalty_earn_rate', $loyalty_earn_rate );

			$loyalty_point_value = max( 0, (float) ( $_POST['shopforge_loyalty_point_value'] ?? 0.05 ) );
			update_option( 'shopforge_loyalty_point_value', $loyalty_point_value );

			$loyalty_min_redeem = max( 1, (int) ( $_POST['shopforge_loyalty_min_redeem'] ?? 100 ) );
			update_option( 'shopforge_loyalty_min_redeem', $loyalty_min_redeem );
			break;

		case 'theme':
			$theme = sanitize_key( $_POST['shopforge_theme'] ?? 'boxed' );
			if ( ! isset( shopforge_theme_presets()[ $theme ] ) ) {
				$theme = 'boxed';
			}
			update_option( 'shopforge_theme', $theme );

			$overrides = [];
			foreach ( array_keys( shopforge_theme_contexts() ) as $ctx ) {
				$skin = sanitize_key( $_POST[ 'shopforge_theme_ctx_' . $ctx ] ?? '' );
				if ( isset( shopforge_theme_presets()[ $skin ] ) ) {
					$overrides[ $ctx ] = $skin;
				}
			}
			update_option( 'shopforge_theme_overrides', $overrides );
			break;

		case 'colors':
			$color_defaults = shopforge_color_defaults();
			$colors         = [];
			foreach ( array_keys( $color_defaults ) as $key ) {
				$val = sanitize_hex_color( $_POST[ 'shopforge_color_' . $key ] ?? '' );
				$colors[ $key ] = $val ?: $color_defaults[ $key ];
			}
			update_option( 'shopforge_colors', $colors );
			break;
	}

	$redirect_tab = in_array( $section, [ 'features', 'modules', 'config', 'theme', 'colors' ], true ) ? $section : 'features';
	wp_redirect( admin_url( 'admin.php?page=shopforge&tab=' . $redirect_tab . '&updated=1' ) );
	exit;
} );


// =============================================================================
// COLORI — default e helper
// =============================================================================

function shopforge_color_defaults(): array {
	return [
		'primary'       => '#006FEF',
		'primary_hover' => '#168BFF',
		'text_main'     => '#07172F',
		'text_muted'    => '#64748B',
		'border'        => '#E2E8F0',
		'border_soft'   => '#EEF2F7',
		'bg_soft'       => '#F8FAFC',
		'success'       => '#16A34A',
		'warning'       => '#F59E0B',
		'danger'        => '#DC2626',
	];
}

function shopforge_get_colors(): array {
	$saved = get_option( 'shopforge_colors', [] );
	return array_merge( shopforge_color_defaults(), is_array( $saved ) ? $saved : [] );
}


// =============================================================================
// TEMI (SKIN) — disposizione ed elementi diversi, non solo colori
// =============================================================================

/**
 * Ogni skin cambia layout, elementi e stile di carrello/checkout/account.
 * La base CSS è "clean"; le altre skin sovrascrivono tramite la classe
 * body .shopforge-skin-{slug} (vedi sezione SKIN in shopforge-woo-shop.css).
 * I colori restano personalizzabili a parte nella sezione Colori.
 */
function shopforge_theme_presets(): array {
	return [
		'clean' => [
			'label' => __( 'Clean', 'shopforge' ),
			'desc'  => __( 'Minimal on a white background: cards with a plain border, no shadows. Matches the WooCommerce block checkout.', 'shopforge' ),
		],
		'boxed' => [
			'label' => __( 'Boxed', 'shopforge' ),
			'desc'  => __( 'Content wrapped in a soft gray panel, elevated cards with subtle shadows and a highlighted footer.', 'shopforge' ),
		],
		'cards' => [
			'label' => __( 'Cards', 'shopforge' ),
			'desc'  => __( 'Every product is a separate rounded card with a shadow: airy layout, detached elements.', 'shopforge' ),
		],
	];
}

function shopforge_get_theme(): string {
	$theme = get_option( 'shopforge_theme', 'boxed' );
	return isset( shopforge_theme_presets()[ $theme ] ) ? $theme : 'boxed';
}

/**
 * Contesti a cui è possibile assegnare una skin diversa da quella globale.
 * 'other' copre le pagine non-WooCommerce dove vengono usati gli shortcode.
 */
function shopforge_theme_contexts(): array {
	return [
		'cart'     => __( 'Cart', 'shopforge' ),
		'checkout' => __( 'Checkout', 'shopforge' ),
		'account'  => __( 'Account area', 'shopforge' ),
		'other'    => __( 'Shortcodes and other pages', 'shopforge' ),
	];
}

/** Skin effettiva per un contesto: override specifico o tema globale. */
function shopforge_get_theme_for_context( string $context ): string {
	$overrides = get_option( 'shopforge_theme_overrides', [] );
	$skin      = is_array( $overrides ) ? ( $overrides[ $context ] ?? '' ) : '';
	return isset( shopforge_theme_presets()[ $skin ] ) ? $skin : shopforge_get_theme();
}

// Classe skin sul body in base alla pagina corrente: aggancio per il CSS
add_filter( 'body_class', function ( $classes ) {
	if ( ! function_exists( 'is_cart' ) ) {
		return $classes;
	}
	if ( is_cart() ) {
		$context = 'cart';
	} elseif ( is_checkout() ) {
		$context = 'checkout';
	} elseif ( is_account_page() ) {
		$context = 'account';
	} else {
		$context = 'other';
	}
	$classes[] = 'shopforge-skin-' . shopforge_get_theme_for_context( $context );
	return $classes;
} );

// Output CSS vars dinamici in frontend (sovrascrive i default del file CSS).
// Copre tutte le pagine shop, non solo l'account: carrello, checkout, prodotto.
add_action( 'wp_head', function () {
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles-colors' ) ) return;

	$c = shopforge_get_colors();
	echo '<style id="shopforge-colors">:root{'
		. '--shopforge-primary:'      . esc_attr( $c['primary'] )       . ';'
		. '--shopforge-primary-hover:' . esc_attr( $c['primary_hover'] ) . ';'
		. '--shopforge-text-main:'    . esc_attr( $c['text_main'] )     . ';'
		. '--shopforge-text-muted:'   . esc_attr( $c['text_muted'] )    . ';'
		. '--shopforge-border:'       . esc_attr( $c['border'] )        . ';'
		. '--shopforge-border-soft:'  . esc_attr( $c['border_soft'] )   . ';'
		. '--shopforge-bg-soft:'      . esc_attr( $c['bg_soft'] )       . ';'
		. '--shopforge-success:'      . esc_attr( $c['success'] )       . ';'
		. '--shopforge-warning:'      . esc_attr( $c['warning'] )       . ';'
		. '--shopforge-danger:'       . esc_attr( $c['danger'] )        . ';'
		. '}</style>' . "\n";
}, 99 );


// =============================================================================
// RENDER PAGINA
// =============================================================================

/** Notice "Impostazioni salvate", uguale su tutte le tab. */
function shopforge_admin_settings_notice(): void {
	if ( empty( $_GET['updated'] ) ) {
		return;
	}
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Settings saved.', 'shopforge' ); ?></p>
	</div>
	<?php
}

/** Apertura form condivisa da tutte le tab: nonce + campo sezione. */
function shopforge_admin_settings_form_open( string $section ): void {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'shopforge_save_settings' ); ?>
		<input type="hidden" name="action" value="shopforge_save_settings">
		<input type="hidden" name="shopforge_settings_section" value="<?php echo esc_attr( $section ); ?>">
	<?php
}

function shopforge_admin_settings_form_close(): void {
	?>
		<div class="shopforge-settings-actions">
			<?php submit_button( __( 'Save settings', 'shopforge' ), 'primary large', 'submit', false ); ?>
		</div>
	</form>
	<?php
}


// =============================================================================
// TAB: FUNZIONALITÀ DI BASE
// =============================================================================

function shopforge_admin_tab_features(): void {
	$registry = shopforge_modules_registry();
	$enabled  = shopforge_get_enabled_modules();
	$features = array_filter( $registry, fn( $m ) => ( $m['type'] ?? 'module' ) === 'feature' );

	shopforge_enqueue_fontawesome();
	shopforge_admin_settings_notice();
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-sliders" aria-hidden="true"></i>
		<?php esc_html_e( 'Core features', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'They control cross-cutting plugin behavior: global CSS styles and the dashboard. Disable them for a "zero visual impact" integration.', 'shopforge' ); ?>
		</span>
	</div>

	<?php shopforge_admin_settings_form_open( 'features' ); ?>
	<div class="shopforge-modules-grid shopforge-modules-grid--features">
		<?php foreach ( $features as $id => $module ) :
			$is_active = in_array( $id, $enabled, true );
		?>
		<div class="shopforge-module-card shopforge-module-card--feature <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
			<?php shopforge_settings_card_inner( $id, $module, $is_active ); ?>
		</div>
		<?php endforeach; ?>
	</div>
	<?php shopforge_admin_settings_form_close(); ?>

	<?php shopforge_admin_settings_styles(); ?>
	<?php
}


// =============================================================================
// TAB: MODULI
// =============================================================================

function shopforge_admin_tab_modules(): void {
	$registry    = shopforge_modules_registry();
	$enabled     = shopforge_get_enabled_modules();
	$has_license = function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license();
	$modules     = array_filter( $registry, fn( $m ) => ( $m['type'] ?? 'module' ) === 'module' );

	shopforge_enqueue_fontawesome();
	shopforge_admin_settings_notice();

	if ( ! $has_license ) : ?>
	<div class="shopforge-license-banner">
		<i class="fa-solid fa-lock" aria-hidden="true"></i>
		<span><?php esc_html_e( 'License missing or invalid — modules cannot be enabled.', 'shopforge' ); ?></span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=shopforge&tab=license' ) ); ?>" class="button button-small"><?php esc_html_e( 'Configure license', 'shopforge' ); ?></a>
	</div>
	<?php endif; ?>

	<div class="shopforge-section-label">
		<i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
		<?php esc_html_e( 'Modules', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'Each module adds specific functionality: account area endpoints, integrations, automatic emails, admin management.', 'shopforge' ); ?>
		</span>
	</div>

	<?php shopforge_admin_settings_form_open( 'modules' ); ?>
	<div class="shopforge-modules-grid">
		<?php foreach ( $modules as $id => $module ) :
			$is_active = in_array( $id, $enabled, true );
		?>
		<div class="shopforge-module-card <?php echo $is_active ? 'is-active' : 'is-inactive'; ?> <?php echo ! $has_license ? 'is-locked' : ''; ?>">
			<?php shopforge_settings_card_inner( $id, $module, $is_active, ! $has_license ); ?>
		</div>
		<?php endforeach; ?>
	</div>
	<?php shopforge_admin_settings_form_close(); ?>

	<?php shopforge_admin_settings_styles(); ?>
	<?php
}


// =============================================================================
// TAB: CONFIGURAZIONE
// =============================================================================

function shopforge_admin_tab_config(): void {
	shopforge_enqueue_fontawesome();
	shopforge_admin_settings_notice();
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-gear" aria-hidden="true"></i>
		<?php esc_html_e( 'Configuration', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'Global parameters that affect module behavior.', 'shopforge' ); ?>
		</span>
	</div>

	<?php shopforge_admin_settings_form_open( 'config' ); ?>
	<div class="shopforge-config-grid">

		<div class="shopforge-config-field">
			<label for="shopforge_return_window_days">
				<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
				<?php esc_html_e( 'Withdrawal window (days)', 'shopforge' ); ?>
			</label>
			<input type="number"
			       id="shopforge_return_window_days"
			       name="shopforge_return_window_days"
			       value="<?php echo esc_attr( get_option( 'shopforge_return_window_days', 14 ) ); ?>"
			       min="1" max="365" step="1"
			       class="shopforge-config-input">
			<p class="shopforge-config-desc">
				<?php esc_html_e( 'Number of days after delivery during which the customer can exercise the right of withdrawal. Default: 14 days (minimum legal term for EU consumers).', 'shopforge' ); ?>
			</p>
		</div>

		<div class="shopforge-config-field">
			<label for="shopforge_contact_url">
				<i class="fa-solid fa-headset" aria-hidden="true"></i>
				<?php esc_html_e( 'Contact page URL (optional)', 'shopforge' ); ?>
			</label>
			<input type="url"
			       id="shopforge_contact_url"
			       name="shopforge_contact_url"
			       value="<?php echo esc_attr( get_option( 'shopforge_contact_url', '' ) ); ?>"
			       placeholder="https://example.com/contact"
			       class="shopforge-config-input">
			<p class="shopforge-config-desc">
				<?php esc_html_e( 'URL shown in the "withdrawal window expired" message. If left empty, only the text is shown without a link.', 'shopforge' ); ?>
			</p>
		</div>

		<div class="shopforge-config-field">
			<label for="shopforge_17track_key">
				<i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
				<?php esc_html_e( '17track API key', 'shopforge' ); ?>
			</label>
			<input type="text"
			       id="shopforge_17track_key"
			       name="shopforge_17track_key"
			       value="<?php echo esc_attr( get_option( 'shopforge_17track_key', '' ) ); ?>"
			       autocomplete="off"
			       class="shopforge-config-input">
			<p class="shopforge-config-desc">
				<?php esc_html_e( 'Required by the Shipment tracking module. Get a free key at api.17track.net. Without a key the tracking widget is not shown.', 'shopforge' ); ?>
			</p>
		</div>

		<div class="shopforge-config-field">
			<label for="shopforge_loyalty_earn_rate">
				<i class="fa-solid fa-star" aria-hidden="true"></i>
				<?php esc_html_e( 'Loyalty — points per currency unit spent', 'shopforge' ); ?>
			</label>
			<input type="number"
			       id="shopforge_loyalty_earn_rate"
			       name="shopforge_loyalty_earn_rate"
			       value="<?php echo esc_attr( get_option( 'shopforge_loyalty_earn_rate', 1 ) ); ?>"
			       min="0" step="0.1"
			       class="shopforge-config-input">
			<p class="shopforge-config-desc">
				<?php esc_html_e( 'Points awarded per currency unit spent, credited when an order is marked Completed and reversed if it is later refunded or cancelled.', 'shopforge' ); ?>
			</p>
		</div>

		<div class="shopforge-config-field">
			<label for="shopforge_loyalty_point_value">
				<i class="fa-solid fa-coins" aria-hidden="true"></i>
				<?php esc_html_e( 'Loyalty — value of 1 point (redemption)', 'shopforge' ); ?>
			</label>
			<input type="number"
			       id="shopforge_loyalty_point_value"
			       name="shopforge_loyalty_point_value"
			       value="<?php echo esc_attr( get_option( 'shopforge_loyalty_point_value', 0.05 ) ); ?>"
			       min="0" step="0.01"
			       class="shopforge-config-input">
			<p class="shopforge-config-desc">
				<?php esc_html_e( 'Currency value of a single point when redeemed for a discount coupon. Default: 0.05 (100 points = 5 in your currency).', 'shopforge' ); ?>
			</p>
		</div>

		<div class="shopforge-config-field">
			<label for="shopforge_loyalty_min_redeem">
				<i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i>
				<?php esc_html_e( 'Loyalty — minimum points to redeem', 'shopforge' ); ?>
			</label>
			<input type="number"
			       id="shopforge_loyalty_min_redeem"
			       name="shopforge_loyalty_min_redeem"
			       value="<?php echo esc_attr( get_option( 'shopforge_loyalty_min_redeem', 100 ) ); ?>"
			       min="1" step="1"
			       class="shopforge-config-input">
			<p class="shopforge-config-desc">
				<?php esc_html_e( 'Minimum point balance a customer must have before the redeem form appears.', 'shopforge' ); ?>
			</p>
		</div>

	</div>
	<?php shopforge_admin_settings_form_close(); ?>

	<?php shopforge_admin_settings_styles(); ?>
	<?php
}


// =============================================================================
// TAB: TEMA
// =============================================================================

function shopforge_admin_tab_theme(): void {
	shopforge_enqueue_fontawesome();
	shopforge_admin_settings_notice();
	$current_theme = shopforge_get_theme();
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-swatchbook" aria-hidden="true"></i>
		<?php esc_html_e( 'Theme', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'Each theme changes the layout, elements and style of cart, checkout and shortcodes. Pick the global theme, then optionally a different theme per page.', 'shopforge' ); ?>
		</span>
	</div>

	<?php shopforge_admin_settings_form_open( 'theme' ); ?>
	<div class="shopforge-theme-grid">
		<?php foreach ( shopforge_theme_presets() as $slug => $preset ) : ?>
		<label class="shopforge-theme-card <?php echo $slug === $current_theme ? 'is-selected' : ''; ?>">
			<input type="radio"
			       name="shopforge_theme"
			       value="<?php echo esc_attr( $slug ); ?>"
			       <?php checked( $slug, $current_theme ); ?>>
			<span class="shopforge-theme-card__mock shopforge-theme-card__mock--<?php echo esc_attr( $slug ); ?>">
				<span class="mock-main"><i></i><i></i><i></i></span>
				<span class="mock-side"><i></i><b></b></span>
			</span>
			<span class="shopforge-theme-card__name"><?php echo esc_html( $preset['label'] ); ?></span>
			<span class="shopforge-theme-card__desc"><?php echo esc_html( $preset['desc'] ); ?></span>
		</label>
		<?php endforeach; ?>
	</div>

	<?php
	$overrides = get_option( 'shopforge_theme_overrides', [] );
	$overrides = is_array( $overrides ) ? $overrides : [];
	?>
	<div class="shopforge-theme-ctx">
		<p class="shopforge-theme-ctx__title">
			<i class="fa-solid fa-layer-group" aria-hidden="true"></i>
			<?php esc_html_e( 'Theme per page', 'shopforge' ); ?>
			<span class="shopforge-section-hint"><?php esc_html_e( 'Leave "Global theme" to apply the same theme everywhere.', 'shopforge' ); ?></span>
		</p>
		<div class="shopforge-theme-ctx__grid">
			<?php foreach ( shopforge_theme_contexts() as $ctx => $ctx_label ) : ?>
			<div class="shopforge-theme-ctx__field">
				<label for="shopforge_theme_ctx_<?php echo esc_attr( $ctx ); ?>"><?php echo esc_html( $ctx_label ); ?></label>
				<select id="shopforge_theme_ctx_<?php echo esc_attr( $ctx ); ?>"
				        name="shopforge_theme_ctx_<?php echo esc_attr( $ctx ); ?>">
					<option value=""><?php esc_html_e( 'Global theme', 'shopforge' ); ?></option>
					<?php foreach ( shopforge_theme_presets() as $slug => $preset ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $overrides[ $ctx ] ?? '', $slug ); ?>>
						<?php echo esc_html( $preset['label'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php shopforge_admin_settings_form_close(); ?>

	<?php shopforge_admin_settings_styles(); ?>

	<script>
	jQuery(document).ready(function($){
		$('input[name="shopforge_theme"]').on('change', function(){
			$('.shopforge-theme-card').removeClass('is-selected');
			$(this).closest('.shopforge-theme-card').addClass('is-selected');
		});
	});
	</script>
	<?php
}


// =============================================================================
// TAB: COLORI
// =============================================================================

function shopforge_admin_tab_colors(): void {
	shopforge_enqueue_fontawesome();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	shopforge_admin_settings_notice();

	$colors   = shopforge_get_colors();
	$defaults = shopforge_color_defaults();
	$color_labels = [
		'primary'       => [ 'label' => __( 'Primary color', 'shopforge' ),   'desc' => __( 'Buttons, active links, highlighted elements', 'shopforge' ) ],
		'primary_hover' => [ 'label' => __( 'Primary — hover', 'shopforge' ), 'desc' => __( 'Lighter shade for hover states', 'shopforge' ) ],
		'text_main'     => [ 'label' => __( 'Main text', 'shopforge' ),       'desc' => __( 'Headings and high-contrast text', 'shopforge' ) ],
		'text_muted'    => [ 'label' => __( 'Muted text', 'shopforge' ),      'desc' => __( 'Dates, labels, footnotes', 'shopforge' ) ],
		'border'        => [ 'label' => __( 'Border', 'shopforge' ),          'desc' => __( 'Borders of cards, tables and inputs', 'shopforge' ) ],
		'border_soft'   => [ 'label' => __( 'Soft border', 'shopforge' ),     'desc' => __( 'Light internal separators', 'shopforge' ) ],
		'bg_soft'       => [ 'label' => __( 'Neutral background', 'shopforge' ), 'desc' => __( 'Background of cards and secondary sections', 'shopforge' ) ],
		'success'       => [ 'label' => __( 'Success', 'shopforge' ),         'desc' => __( '"Completed" badges, positive messages', 'shopforge' ) ],
		'warning'       => [ 'label' => __( 'Warning', 'shopforge' ),         'desc' => __( '"Pending" badges, warning messages', 'shopforge' ) ],
		'danger'        => [ 'label' => __( 'Error / Danger', 'shopforge' ),  'desc' => __( 'Error messages, irreversible actions', 'shopforge' ) ],
	];
	?>
	<div class="shopforge-section-label">
		<i class="fa-solid fa-palette" aria-hidden="true"></i>
		<?php esc_html_e( 'Colors', 'shopforge' ); ?>
		<span class="shopforge-section-hint">
			<?php esc_html_e( 'Customize the ShopForge palette to match your site. Colors are injected as CSS variables and override the plugin defaults.', 'shopforge' ); ?>
		</span>
	</div>

	<?php shopforge_admin_settings_form_open( 'colors' ); ?>
	<div class="shopforge-color-grid">
		<?php foreach ( $color_labels as $key => $meta ) : ?>
		<div class="shopforge-color-field">
			<label for="shopforge_color_<?php echo esc_attr( $key ); ?>">
				<?php echo esc_html( $meta['label'] ); ?>
			</label>
			<div class="shopforge-color-row">
				<input type="text"
				       id="shopforge_color_<?php echo esc_attr( $key ); ?>"
				       name="shopforge_color_<?php echo esc_attr( $key ); ?>"
				       value="<?php echo esc_attr( $colors[ $key ] ); ?>"
				       class="shopforge-color-picker"
				       data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
			</div>
			<p class="shopforge-color-desc"><?php echo esc_html( $meta['desc'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Preview live -->
	<div class="shopforge-color-preview" id="shopforge-color-preview">
		<p class="shopforge-color-preview__label">
			<i class="fa-solid fa-eye" aria-hidden="true"></i>
			<?php esc_html_e( 'Preview', 'shopforge' ); ?>
		</p>
		<div class="shopforge-color-preview__card" id="shopforge-preview-card">
			<div class="shopforge-color-preview__btn" id="shopforge-preview-btn"><?php esc_html_e( 'Primary button', 'shopforge' ); ?></div>
			<div class="shopforge-color-preview__text" id="shopforge-preview-text"><?php esc_html_e( 'Main text', 'shopforge' ); ?> — <span id="shopforge-preview-muted"><?php esc_html_e( 'muted text', 'shopforge' ); ?></span></div>
			<div class="shopforge-color-preview__border" id="shopforge-preview-border"><?php esc_html_e( 'Card border', 'shopforge' ); ?></div>
		</div>
	</div>
	<?php shopforge_admin_settings_form_close(); ?>

	<?php shopforge_admin_settings_styles(); ?>

	<script>
	jQuery(document).ready(function($){
		$('.shopforge-color-picker').wpColorPicker({
			change: function(){ shopforgeUpdatePreview(); },
			clear:  function(){ setTimeout(shopforgeUpdatePreview, 10); }
		});

		function shopforgeUpdatePreview(){
			var get = function(key){
				return $('#shopforge_color_' + key).val() || $('#shopforge_color_' + key).data('default-color');
			};
			var primary    = get('primary');
			var textMain   = get('text_main');
			var textMuted  = get('text_muted');
			var border     = get('border');
			var bgSoft     = get('bg_soft');

			$('#shopforge-preview-card').css({ 'background': bgSoft, 'border': '1px solid ' + border, 'border-radius':'8px', 'padding':'16px 20px' });
			$('#shopforge-preview-btn').css({ 'background': primary, 'color':'#fff', 'padding':'9px 18px', 'border-radius':'6px', 'display':'inline-block', 'font-weight':'700', 'font-size':'13px', 'margin-bottom':'12px' });
			$('#shopforge-preview-text').css({ 'color': textMain, 'font-size':'14px' });
			$('#shopforge-preview-muted').css({ 'color': textMuted });
			$('#shopforge-preview-border').css({ 'border-top':'1px solid ' + border, 'margin-top':'12px', 'padding-top':'10px', 'font-size':'12px', 'color': textMuted });
		}
		shopforgeUpdatePreview();
	});
	</script>
	<?php
}


// =============================================================================
// STILE CONDIVISO — stampato una volta sola per richiesta: ogni tab è una
// pagina separata (switch/case nel router), quindi nessun rischio di
// duplicazione anche senza guardia statica.
// =============================================================================

function shopforge_admin_settings_styles(): void {
	?>
	<style>
	/* ---- Banner licenza mancante ---- */
	.shopforge-license-banner {
		display: flex; align-items: center; gap: 10px;
		background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E;
		border-radius: 8px; padding: 12px 16px; margin: 16px 0 20px;
		font-size: 13px; font-weight: 600;
	}
	.shopforge-license-banner i { font-size: 15px; }
	.shopforge-license-banner .button { margin-left: auto; }

	/* ---- Card modulo bloccata (senza licenza) ---- */
	.shopforge-module-card.is-locked { opacity: .6; }
	.shopforge-module-card.is-locked .shopforge-toggle { cursor: not-allowed; }

	/* ---- Etichette sezione ---- */
	.shopforge-section-label {
		display: flex; align-items: baseline; gap: 8px;
		font-size: 13px; font-weight: 700; color: #1d2327;
		text-transform: uppercase; letter-spacing: .06em;
		margin: 4px 0 10px;
		padding-bottom: 8px;
		border-bottom: 2px solid #dcdcde;
	}
	.shopforge-section-label i { color: #2271b1; }
	.shopforge-section-hint {
		font-size: 12px; font-weight: 400; color: #646970;
		text-transform: none; letter-spacing: 0;
	}

	/* ---- Griglia ---- */
	.shopforge-modules-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
		gap: 14px;
		margin-bottom: 6px;
	}
	.shopforge-modules-grid--features {
		grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	}

	/* ---- Card ---- */
	.shopforge-module-card {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 8px;
		padding: 18px 20px;
		transition: border-color .2s, box-shadow .2s;
	}
	.shopforge-module-card.is-active {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
	}
	/* Le feature hanno sfondo leggermente diverso per distinguersi visivamente */
	.shopforge-module-card--feature {
		background: #f9f9f9;
	}
	.shopforge-module-card--feature.is-active {
		background: #f0f6fc;
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
	}

	.shopforge-module-card__header {
		display: flex; align-items: center; gap: 14px; margin-bottom: 10px;
	}
	.shopforge-module-card__icon {
		width: 42px; height: 42px; flex-shrink: 0;
		background: #f0f6fc; border-radius: 10px;
		display: flex; align-items: center; justify-content: center;
		font-size: 19px; color: #2271b1;
	}
	.shopforge-module-card.is-inactive .shopforge-module-card__icon,
	.shopforge-module-card--feature.is-inactive .shopforge-module-card__icon {
		background: #f6f7f7; color: #8c8f94;
	}
	.shopforge-module-card__title-wrap { flex: 1; min-width: 0; }
	.shopforge-module-card__title {
		margin: 0 0 3px; font-size: 14px; font-weight: 700;
		color: #1d2327; line-height: 1.3;
	}
	.shopforge-module-card__meta { font-size: 11px; color: #8c8f94; }
	.shopforge-module-card__meta code {
		font-size: 11px; background: #f6f7f7;
		padding: 1px 5px; border-radius: 3px;
	}
	.shopforge-module-card__desc {
		margin: 0 0 12px; font-size: 13px; color: #646970; line-height: 1.5;
	}
	.shopforge-module-card__footer { display: flex; align-items: center; justify-content: flex-end; }
	.shopforge-module-status { font-size: 12px; font-weight: 600; }
	.shopforge-module-status.is-on  { color: #00a32a; }
	.shopforge-module-status.is-off { color: #8c8f94; }

	/* ---- Toggle switch ---- */
	.shopforge-toggle {
		position: relative; display: inline-block;
		width: 44px; height: 24px; flex-shrink: 0; cursor: pointer;
	}
	.shopforge-toggle input { opacity: 0; width: 0; height: 0; }
	.shopforge-toggle__slider {
		position: absolute; inset: 0;
		background: #dcdcde; border-radius: 999px;
		transition: background .2s;
	}
	.shopforge-toggle__slider::before {
		content: "";
		position: absolute; left: 3px; top: 3px;
		width: 18px; height: 18px;
		background: #fff; border-radius: 50%;
		box-shadow: 0 1px 3px rgba(0,0,0,.25);
		transition: transform .2s;
	}
	.shopforge-toggle input:checked + .shopforge-toggle__slider { background: #2271b1; }
	.shopforge-toggle input:checked + .shopforge-toggle__slider::before { transform: translateX(20px); }

	/* ---- Actions ---- */
	.shopforge-settings-actions { margin-top: 14px; }

	/* ---- Config grid ---- */
	.shopforge-config-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
		gap: 14px;
		margin-bottom: 6px;
	}
	.shopforge-config-field {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 8px;
		padding: 16px 18px;
	}
	.shopforge-config-field label {
		display: flex; align-items: center; gap: 8px;
		font-size: 13px; font-weight: 700; color: #1d2327;
		margin-bottom: 8px;
	}
	.shopforge-config-field label i { color: #2271b1; }
	.shopforge-config-input {
		width: 100%; box-sizing: border-box;
		padding: 7px 10px; border: 1px solid #8c8f94;
		border-radius: 4px; font-size: 13px;
	}
	.shopforge-config-input[type="number"] { max-width: 100px; }
	.shopforge-config-desc {
		margin: 8px 0 0; font-size: 12px; color: #646970; line-height: 1.5;
	}

	/* ---- Card tema ---- */
	.shopforge-theme-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
		gap: 14px;
		margin-bottom: 24px;
	}
	.shopforge-theme-card {
		display: block;
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 8px;
		padding: 14px 16px;
		cursor: pointer;
		transition: border-color .2s, box-shadow .2s;
	}
	.shopforge-theme-card:hover { border-color: #2271b1; }
	.shopforge-theme-card.is-selected {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		background: #f0f6fc;
	}
	.shopforge-theme-card input { position: absolute; opacity: 0; pointer-events: none; }
	.shopforge-theme-card__name {
		display: block; font-size: 13px; font-weight: 700; color: #1d2327;
	}
	.shopforge-theme-card__desc {
		display: block; font-size: 12px; color: #646970; line-height: 1.4; margin: 3px 0 0;
	}

	/* Mini-anteprima layout della skin */
	.shopforge-theme-card__mock {
		display: flex; gap: 6px;
		height: 64px; margin-bottom: 10px; padding: 7px;
		border: 1px solid #dcdcde; border-radius: 6px; background: #fff;
	}
	.shopforge-theme-card__mock .mock-main { flex: 2; display: flex; flex-direction: column; gap: 4px; }
	.shopforge-theme-card__mock .mock-side { flex: 1; display: flex; flex-direction: column; gap: 4px; }
	.shopforge-theme-card__mock i, .shopforge-theme-card__mock b { display: block; flex: 1; }
	.shopforge-theme-card__mock b { background: #2271b1; border-radius: 3px; flex: 0 0 10px; }
	/* clean: righe con solo bordo */
	.shopforge-theme-card__mock--clean i { border: 1px solid #dcdcde; border-radius: 3px; background: #fff; }
	/* boxed: pannello grigio, righe bianche in rilievo */
	.shopforge-theme-card__mock--boxed { background: #eef1f5; }
	.shopforge-theme-card__mock--boxed i { background: #fff; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,.14); }
	/* cards: card staccate molto arrotondate */
	.shopforge-theme-card__mock--cards i { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,.10); }

	/* ---- Tema per pagina ---- */
	.shopforge-theme-ctx {
		background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
		padding: 14px 18px; margin-bottom: 24px;
	}
	.shopforge-theme-ctx__title {
		display: flex; align-items: baseline; gap: 8px;
		font-size: 13px; font-weight: 700; color: #1d2327; margin: 0 0 12px;
	}
	.shopforge-theme-ctx__title i { color: #2271b1; }
	.shopforge-theme-ctx__grid {
		display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;
	}
	.shopforge-theme-ctx__field label {
		display: block; font-size: 12px; font-weight: 600; color: #1d2327; margin-bottom: 4px;
	}
	.shopforge-theme-ctx__field select { width: 100%; }

	/* ---- Griglia colori ---- */
	.shopforge-color-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
		gap: 20px 24px;
		margin-bottom: 24px;
	}
	.shopforge-color-field label {
		display: block; font-size: 12px; font-weight: 700;
		color: #1d2327; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .04em;
	}
	.shopforge-color-row { display: flex; align-items: center; gap: 8px; }
	.shopforge-color-desc { margin: 6px 0 0; font-size: 11px; color: #646970; line-height: 1.4; }
	/* Nasconde il classico input text del color picker (l'iris panel lo sostituisce) */
	.shopforge-color-field .wp-picker-container { width: 100%; }

	/* ---- Preview ---- */
	.shopforge-color-preview {
		margin: 0 0 28px;
		padding: 16px 20px;
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 6px;
	}
	.shopforge-color-preview__label {
		font-size: 12px; font-weight: 700; color: #646970;
		text-transform: uppercase; letter-spacing: .06em;
		margin: 0 0 14px;
		display: flex; align-items: center; gap: 6px;
	}
	.shopforge-color-preview__card { padding: 16px 20px; border-radius: 8px; }
	.shopforge-color-preview__btn {
		display: inline-block; padding: 9px 18px;
		border-radius: 6px; font-weight: 700; font-size: 13px;
		margin-bottom: 12px; cursor: default;
	}
	.shopforge-color-preview__text { font-size: 14px; }

	@media (max-width: 600px) {
		.shopforge-modules-grid,
		.shopforge-modules-grid--features,
		.shopforge-config-grid,
		.shopforge-color-grid { grid-template-columns: 1fr; }
	}
	</style>
	<?php
}

/**
 * Emette l'HTML interno di una card (header + descrizione + footer).
 * Usato sia per le feature che per i moduli.
 */
function shopforge_settings_card_inner( string $id, array $module, bool $is_active, bool $locked = false ): void {
	?>
	<div class="shopforge-module-card__header">
		<span class="shopforge-module-card__icon">
			<i class="<?php echo esc_attr( $module['icon'] ); ?>" aria-hidden="true"></i>
		</span>
		<div class="shopforge-module-card__title-wrap">
			<h3 class="shopforge-module-card__title"><?php echo esc_html( $module['label'] ); ?></h3>
			<span class="shopforge-module-card__meta">
			<?php if ( ( $module['type'] ?? 'module' ) === 'feature' ) : ?>
				<?php esc_html_e( 'core feature', 'shopforge' ); ?>
			<?php elseif ( ! empty( $module['endpoint'] ) ) : ?>
				<?php esc_html_e( 'endpoint:', 'shopforge' ); ?> <code><?php echo esc_html( $module['endpoint'] ); ?></code>
			<?php else : ?>
				<?php esc_html_e( 'module (no endpoint)', 'shopforge' ); ?>
			<?php endif; ?>
			</span>
		</div>
		<label class="shopforge-toggle" title="<?php echo esc_attr( $locked ? __( 'Requires an active license', 'shopforge' ) : ( $is_active ? __( 'Disable', 'shopforge' ) : __( 'Enable', 'shopforge' ) ) ); ?>">
			<input type="checkbox"
			       name="module_<?php echo esc_attr( $id ); ?>"
			       value="1"
			       <?php checked( $is_active && ! $locked ); ?>
			       <?php disabled( $locked ); ?>>
			<span class="shopforge-toggle__slider"></span>
		</label>
	</div>
	<p class="shopforge-module-card__desc"><?php echo esc_html( $module['description'] ); ?></p>
	<div class="shopforge-module-card__footer">
		<?php if ( $locked ) : ?>
		<span class="shopforge-module-status is-off"><i class="fa-solid fa-lock" aria-hidden="true"></i> <?php esc_html_e( 'Requires license', 'shopforge' ); ?></span>
		<?php else : ?>
		<span class="shopforge-module-status <?php echo $is_active ? 'is-on' : 'is-off'; ?>">
			<?php echo $is_active ? '● ' . esc_html__( 'Active', 'shopforge' ) : '○ ' . esc_html__( 'Inactive', 'shopforge' ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php
}
