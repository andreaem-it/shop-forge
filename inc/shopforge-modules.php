<?php
/**
 * Andrea Emili — Registro moduli e loader condizionale
 *
 * Il plugin è diviso in due livelli:
 *
 *  FUNZIONALITÀ BASE (type: 'feature') — non hanno endpoint né file separato,
 *  controllano comportamenti trasversali come CSS e dashboard.
 *
 *  MODULI (type: 'module') — endpoint WC, voce di menu, file PHP separato.
 *  Ciascuno è completamente indipendente.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// REGISTRO — funzionalità + moduli
// =============================================================================

function shopforge_modules_registry(): array {
	return [

		// ---- FUNZIONALITÀ BASE ------------------------------------------------
		// type:'feature' → nessun endpoint, nessun file separato, nessuna voce menu.
		// Controllano il comportamento trasversale del plugin.

		'styles-account' => [
			'id'          => 'styles-account',
			'type'        => 'feature',
			'label'       => __( 'Account area styles', 'shopforge' ),
			'description' => __( 'CSS for the dashboard, orders, addresses and modules (returns, support, notifications): card layout, status badges, typography. Disable to rely entirely on your theme in the account area too.', 'shopforge' ),
			'icon'        => 'fa-solid fa-user-gear',
			'file'        => null,
			'menu_item'   => false,
		],

		'styles-shop' => [
			'id'          => 'styles-shop',
			'type'        => 'feature',
			'label'       => __( 'Shop/catalog styles', 'shopforge' ),
			'description' => __( 'CSS for the product catalog and shop pages. Disable to rely entirely on your theme for the catalog.', 'shopforge' ),
			'icon'        => 'fa-solid fa-shop',
			'file'        => null,
			'menu_item'   => false,
		],

		'styles-colors' => [
			'id'          => 'styles-colors',
			'type'        => 'feature',
			'label'       => __( 'Custom colors', 'shopforge' ),
			'description' => __( 'Color palette (buttons, badges, text) injected as CSS variables, including the configuration below. When disabled, the returns/support modals also fall back to the default theme style.', 'shopforge' ),
			'icon'        => 'fa-solid fa-palette',
			'file'        => null,
			'menu_item'   => false,
		],

		'dashboard' => [
			'id'          => 'dashboard',
			'type'        => 'feature',
			'label'       => __( 'Custom dashboard', 'shopforge' ),
			'description' => __( 'Replaces the WooCommerce dashboard with a custom one: order statistics, tracking widget, addresses. Disable to use the default WooCommerce dashboard.', 'shopforge' ),
			'icon'        => 'fa-solid fa-gauge-high',
			'file'        => null,
			'menu_item'   => false,
		],

		// ---- MODULI ----------------------------------------------------------
		// type:'module' → file PHP caricato condizionalmente, opzionale endpoint.

		'tracking' => [
			'id'          => 'tracking',
			'type'        => 'module',
			'label'       => __( 'Shipment tracking', 'shopforge' ),
			'description' => __( 'Tracking widget on the order detail page with 17track integration. The admin enters the tracking number from the order in the backend.', 'shopforge' ),
			'icon'        => 'fa-solid fa-truck-fast',
			'file'        => 'shopforge-mod-tracking.php',
			'menu_item'   => false,
		],

		'wishlist' => [
			'id'          => 'wishlist',
			'type'        => 'module',
			'label'       => __( 'Wishlist', 'shopforge' ),
			'description' => __( 'Lets customers save favorite products and access them from their account. Adds the heart button to the catalog and product page.', 'shopforge' ),
			'icon'        => 'fa-solid fa-heart',
			'file'        => 'shopforge-mod-wishlist.php',
			'menu_item'   => true,
			'endpoint'    => 'shopforge-wishlist',
			'menu_label'  => __( 'Wishlist', 'shopforge' ),
		],

		'quotes' => [
			'id'          => 'quotes',
			'type'        => 'module',
			'label'       => __( 'Quotes', 'shopforge' ),
			'description' => __( 'Dedicated section for quote requests. Customers add products and quantities, the admin replies via email and manages everything from a dedicated page.', 'shopforge' ),
			'icon'        => 'fa-solid fa-file-invoice',
			'file'        => 'shopforge-mod-quotes.php',
			'menu_item'   => true,
			'endpoint'    => 'shopforge-quotes',
			'menu_label'  => __( 'Quotes', 'shopforge' ),
		],

		'returns' => [
			'id'          => 'returns',
			'type'        => 'module',
			'label'       => __( 'Support & Returns', 'shopforge' ),
			'description' => __( 'Digital withdrawal procedure compliant with EU consumer law. Button on the order page, 2-step modal, automatic email receipt.', 'shopforge' ),
			'icon'        => 'fa-solid fa-rotate-left',
			'file'        => 'shopforge-mod-returns.php',
			'menu_item'   => true,
			'endpoint'    => 'shopforge-returns',
			'menu_label'  => __( 'Support & Returns', 'shopforge' ),
		],

		'rma' => [
			'id'          => 'rma',
			'type'        => 'module',
			'label'       => __( 'Product Support (RMA)', 'shopforge' ),
			'description' => __( 'Structured repair, replacement or refund requests for defects/warranty: remedy selection, quantity tracking, automatic WooCommerce refund, message thread.', 'shopforge' ),
			'icon'        => 'fa-solid fa-screwdriver-wrench',
			'file'        => 'shopforge-mod-rma.php',
			'menu_item'   => true,
			'endpoint'    => 'shopforge-rma',
			'menu_label'  => __( 'Product Support', 'shopforge' ),
		],

		'notifications' => [
			'id'          => 'notifications',
			'type'        => 'module',
			'label'       => __( 'Notifications', 'shopforge' ),
			'description' => __( 'Notification center fed by real events: order status changes, support tickets, return updates, quote replies. Badge with unread count.', 'shopforge' ),
			'icon'        => 'fa-solid fa-bell',
			'file'        => 'shopforge-mod-notifications.php',
			'menu_item'   => true,
			'endpoint'    => 'shopforge-notices',
			'menu_label'  => __( 'Notifications', 'shopforge' ),
		],

		'loyalty' => [
			'id'          => 'loyalty',
			'type'        => 'module',
			'label'       => __( 'Loyalty Points', 'shopforge' ),
			'description' => __( 'Customers earn points on completed orders and redeem them for a discount coupon. Earn rate, point value and minimum redemption are configurable.', 'shopforge' ),
			'icon'        => 'fa-solid fa-star',
			'file'        => 'shopforge-mod-loyalty.php',
			'menu_item'   => true,
			'endpoint'    => 'shopforge-loyalty',
			'menu_label'  => __( 'Loyalty Points', 'shopforge' ),
		],

	];
}


// =============================================================================
// HELPER: stato moduli / feature
// =============================================================================

/**
 * Restituisce gli ID di moduli e feature attivi.
 * Default: tutti attivi (primo avvio senza impostazioni salvate).
 * Se l'opzione è salvata, restituisce esattamente quello che l'utente ha scelto —
 * nessuna auto-migrazione che potrebbe ri-abilitare feature disattivate intenzionalmente,
 * a parte l'espansione una-tantum del vecchio id 'styles' (vedi sotto).
 *
 * Filtra sempre i moduli (type:'module') se la licenza non è valida — sia sul
 * default di prima installazione sia su un'opzione già salvata, così i moduli
 * a pagamento non compaiono mai (menu account, endpoint) senza licenza attiva.
 */
function shopforge_get_enabled_modules(): array {
	$registry   = shopforge_modules_registry();
	$module_ids = array_keys( array_filter( $registry, fn( $m ) => ( $m['type'] ?? 'module' ) === 'module' ) );

	$saved = get_option( 'shopforge_modules_enabled', null );

	// Prima installazione: nessuna opzione salvata → tutto abilitato
	$enabled = $saved === null ? array_keys( $registry ) : (array) $saved;

	// Migrazione una-tantum: il vecchio id unico 'styles' diventa i 3 nuovi toggle granulari.
	if ( in_array( 'styles', $enabled, true ) ) {
		$enabled = array_diff( $enabled, [ 'styles' ] );
		$enabled = array_merge( $enabled, [ 'styles-account', 'styles-shop', 'styles-colors' ] );
		$enabled = array_values( array_unique( $enabled ) );
		update_option( 'shopforge_modules_enabled', $enabled );
	}

	$has_license = function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license();
	if ( ! $has_license ) {
		$enabled = array_diff( $enabled, $module_ids );
	}

	return array_values( $enabled );
}

/**
 * Verifica se un modulo o una feature è attiva.
 */
function shopforge_is_module_active( string $id ): bool {
	return in_array( $id, shopforge_get_enabled_modules(), true );
}


// =============================================================================
// LOADER: carica i file dei moduli attivi
// (le feature non hanno file proprio, vengono gestite da shopforge-account.php)
// =============================================================================

function shopforge_load_modules(): void {
	// Gate a monte: senza licenza valida nessun modulo (type:'module') si carica.
	// Le feature di base (styles/dashboard) non passano da qui — restano libere.
	if ( ! function_exists( 'shopforge_has_valid_license' ) || ! shopforge_has_valid_license() ) {
		return;
	}

	foreach ( shopforge_modules_registry() as $id => $module ) {
		if ( ( $module['type'] ?? 'module' ) === 'feature' ) continue; // nessun file
		if ( shopforge_is_module_active( $id ) ) {
			$file = SHOPFORGE_DIR . 'inc/modules/' . $module['file'];
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
}


// =============================================================================
// RATE LIMITING — helper per AJAX di submit
// =============================================================================

/**
 * Verifica il rate limit per un'azione AJAX per-utente.
 * Restituisce true se l'utente può procedere, false se troppo veloce.
 *
 * @param string $action  Nome dell'azione (es. 'submit_return').
 * @param int    $ttl     Secondi di attesa tra una chiamata e la successiva (default 60).
 * @param int    $user_id ID utente (default: utente corrente).
 */
function shopforge_check_rate_limit( string $action, int $ttl = 60, int $user_id = 0 ): bool {
	if ( $user_id === 0 ) {
		$user_id = get_current_user_id();
	}
	if ( $user_id === 0 ) {
		return false; // Utente non loggato: blocca sempre
	}
	$key = 'shopforge_rl_' . $action . '_' . $user_id;
	if ( get_transient( $key ) ) {
		return false; // Troppo veloce
	}
	set_transient( $key, 1, $ttl );
	return true;
}

// =============================================================================
// REWRITE ENDPOINTS: sempre registrati per stabilità degli URL
// =============================================================================

add_action( 'init', function () {
	foreach ( shopforge_modules_registry() as $module ) {
		if ( ( $module['type'] ?? 'module' ) === 'feature' ) continue;
		if ( ! empty( $module['endpoint'] ) ) {
			add_rewrite_endpoint( $module['endpoint'], EP_ROOT | EP_PAGES );
		}
	}

	// Flush una-tantum dopo l'attivazione (flag settato in shopforge.php):
	// senza, gli endpoint account rispondono 404 fino a un salvataggio permalink.
	if ( get_option( 'shopforge_flush_rewrite' ) ) {
		delete_option( 'shopforge_flush_rewrite' );
		flush_rewrite_rules();
	}
} );


// =============================================================================
// MENU ACCOUNT: voci dinamiche in base ai moduli attivi
// =============================================================================

add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	// Salva logout per aggiungerlo in fondo
	$logout = [];
	if ( isset( $items['customer-logout'] ) ) {
		$logout['customer-logout'] = $items['customer-logout'];
		unset( $items['customer-logout'] );
	}

	// Rimuovi dal menu le voci degli endpoint del plugin (se già presenti)
	foreach ( shopforge_modules_registry() as $module ) {
		if ( ( $module['type'] ?? 'module' ) === 'feature' ) continue;
		if ( ! empty( $module['endpoint'] ) ) {
			unset( $items[ $module['endpoint'] ] );
		}
	}
	unset( $items['shopforge-products'], $items['shopforge-wishlist'] );

	// Aggiungi solo i moduli attivi con voce di menu
	foreach ( shopforge_modules_registry() as $id => $module ) {
		if ( ( $module['type'] ?? 'module' ) === 'feature' ) continue;
		if ( ! empty( $module['menu_item'] ) && shopforge_is_module_active( $id ) ) {
			$items[ $module['endpoint'] ] = $module['menu_label'];
		}
	}

	return array_merge( $items, $logout );
}, 20 );


// =============================================================================
// TITOLI PAGINA: endpoint → titolo
// =============================================================================

add_action( 'init', function () {
	foreach ( shopforge_modules_registry() as $module ) {
		if ( ( $module['type'] ?? 'module' ) === 'feature' ) continue;
		if ( ! empty( $module['endpoint'] ) ) {
			add_filter(
				'woocommerce_endpoint_' . $module['endpoint'] . '_title',
				fn() => $module['menu_label']
			);
		}
	}
} );
