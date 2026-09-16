<?php
/**
 * Modulo: Lista desideri
 *
 * Salva prodotti preferiti nel meta utente e li mostra
 * nella pagina /lista-desideri/ dell'account.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// ponytail: shopforge_get_wishlist_layout() vive in inc/shopforge-product.php
// (sempre caricato) e non qui, perché questo file viene incluso solo se il
// modulo Wishlist è attivo — il Customizer (sempre disponibile in admin) la
// chiama a prescindere dallo stato del modulo/licenza, e qui sarebbe stata
// undefined in quei casi.

// ---- Notifica "di nuovo disponibile" a chi ha il prodotto in wishlist ----

/**
 * Ad ogni cambio di stato scorta: se il prodotto torna disponibile e non
 * abbiamo già notificato questo giro (guardia via post meta, azzerata
 * quando il prodotto torna di nuovo esaurito), avvisa tutti gli utenti che
 * lo hanno salvato in wishlist. Ricerca diretta su usermeta — un WP_User_Query
 * su tutti gli utenti sarebbe troppo costoso da eseguire ad ogni sync stock.
 */
add_action( 'woocommerce_product_set_stock_status', function ( int $product_id, string $status, $product ) {
	$notified_flag = '_shopforge_wishlist_notified';

	if ( 'instock' !== $status ) {
		delete_post_meta( $product_id, $notified_flag );
		return;
	}

	if ( get_post_meta( $product_id, $notified_flag, true ) ) {
		return;
	}
	update_post_meta( $product_id, $notified_flag, 1 );

	global $wpdb;
	$user_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_shopforge_wishlist' AND meta_value LIKE %s",
		'%i:' . $product_id . ';%'
	) );
	if ( ! $user_ids ) return;

	$product_name = $product instanceof WC_Product ? $product->get_name() : get_the_title( $product_id );
	$url          = get_permalink( $product_id );

	foreach ( $user_ids as $user_id ) {
		do_action( 'shopforge_notification', (int) $user_id, 'back_in_stock', [
			/* translators: %s: product name */
			'text' => sprintf( __( '%s is back in stock', 'shopforge' ), $product_name ),
			'url'  => $url,
		] );
	}
}, 10, 3 );

// ---- Contenuto endpoint ----

/**
 * Griglia prodotti condivisa fra la pagina account (con pulsante rimuovi)
 * e la pagina pubblica di condivisione (sola lettura).
 */
function shopforge_render_wishlist_grid( array $wishlist, bool $show_remove ): void {
	echo '<div class="shopforge-wishlist-grid">';
	foreach ( $wishlist as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_visible() ) continue;

		$thumb = get_the_post_thumbnail_url( $product_id, 'woocommerce_thumbnail' );
		$price = $product->get_price_html();
		$url   = get_permalink( $product_id );
		?>
		<div class="shopforge-wishlist-item">
			<a href="<?php echo esc_url( $url ); ?>" class="shopforge-wishlist-item__thumb">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy">
				<?php else : ?>
					<span class="shopforge-wishlist-item__no-img"><i class="fa-solid fa-box"></i></span>
				<?php endif; ?>
			</a>
			<div class="shopforge-wishlist-item__body">
				<a href="<?php echo esc_url( $url ); ?>" class="shopforge-wishlist-item__name">
					<?php echo esc_html( $product->get_name() ); ?>
				</a>
				<span class="shopforge-wishlist-item__price"><?php echo $price; ?></span>
			</div>
			<div class="shopforge-wishlist-item__actions">
				<?php if ( $product->is_in_stock() ) : ?>
				<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
				   class="shopforge-btn shopforge-btn--primary">
					<i class="fa-solid fa-cart-plus"></i> <?php esc_html_e( 'Add to cart', 'shopforge' ); ?>
				</a>
				<?php else : ?>
				<span class="shopforge-badge shopforge-badge--muted"><?php esc_html_e( 'Out of stock', 'shopforge' ); ?></span>
				<?php endif; ?>
				<?php if ( $show_remove ) : ?>
				<button type="button" class="shopforge-btn shopforge-btn--ghost shopforge-remove-wishlist"
				        data-product="<?php echo esc_attr( $product_id ); ?>"
				        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_wishlist' ) ); ?>">
					<i class="fa-solid fa-trash-can"></i>
				</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
	echo '</div>';
}

/**
 * Token di condivisione pubblica della wishlist: generato al primo utilizzo
 * e stabile finché l'utente non lo rigenera esplicitamente.
 */
function shopforge_get_wishlist_share_token( int $user_id ): string {
	$token = get_user_meta( $user_id, '_shopforge_wishlist_share_token', true );
	if ( ! $token ) {
		$token = wp_generate_password( 24, false );
		update_user_meta( $user_id, '_shopforge_wishlist_share_token', $token );
	}
	return $token;
}

add_action( 'woocommerce_account_shopforge-wishlist_endpoint', function () {
	$user_id  = get_current_user_id();
	$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];

	shopforge_account_section_header(
		__( 'Wishlist', 'shopforge' ),
		'fa-solid fa-heart',
		/* translators: %d: number of saved products */
		sprintf( _n( '%d saved product', '%d saved products', count( $wishlist ), 'shopforge' ), count( $wishlist ) )
	);

	if ( empty( $wishlist ) ) {
		shopforge_account_empty_state(
			'fa-solid fa-heart',
			__( 'No saved products', 'shopforge' ),
			__( 'Add products to your wishlist to find them easily.', 'shopforge' )
		);
		return;
	}

	$share_url = add_query_arg( 'shopforge_wl', shopforge_get_wishlist_share_token( $user_id ), home_url( '/' ) );
	?>
	<div class="shopforge-wishlist-share">
		<i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
		<input type="text" readonly value="<?php echo esc_url( $share_url ); ?>" onclick="this.select();" class="shopforge-wishlist-share__input">
		<button type="button" class="shopforge-btn shopforge-btn--ghost shopforge-wishlist-share__copy"><?php esc_html_e( 'Copy link', 'shopforge' ); ?></button>
	</div>
	<style>.shopforge-wishlist-share{display:flex;align-items:center;gap:8px;margin-bottom:16px;color:#646970;}.shopforge-wishlist-share__input{flex:1;max-width:420px;}</style>
	<?php
	shopforge_render_wishlist_grid( $wishlist, true );
	?>
	<script>
	document.querySelectorAll('.shopforge-remove-wishlist').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var row = this.closest('.shopforge-wishlist-item');
			fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
				method: 'POST',
				headers: {'Content-Type':'application/x-www-form-urlencoded'},
				body: 'action=shopforge_remove_wishlist&product_id=' + this.dataset.product + '&nonce=' + this.dataset.nonce
			}).then(function(r){ return r.json(); }).then(function(d) {
				if (d.success) row.remove();
			});
		});
	});
	var copyBtn = document.querySelector('.shopforge-wishlist-share__copy');
	if (copyBtn) {
		copyBtn.addEventListener('click', function() {
			var input = document.querySelector('.shopforge-wishlist-share__input');
			input.select();
			navigator.clipboard && navigator.clipboard.writeText(input.value);
		});
	}
	</script>
	<?php
} );

// ---- Pagina pubblica di condivisione (?shopforge_wl=token) ----

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'shopforge_wl';
	return $vars;
} );

add_action( 'template_redirect', function () {
	$token = sanitize_text_field( get_query_var( 'shopforge_wl' ) );
	if ( ! $token ) return;

	$users = get_users( [
		'meta_key'   => '_shopforge_wishlist_share_token',
		'meta_value' => $token,
		'number'     => 1,
		'fields'     => 'ID',
	] );
	if ( ! $users ) {
		wp_die( esc_html__( 'This wishlist link is not valid.', 'shopforge' ), '', [ 'response' => 404 ] );
	}

	$wishlist = get_user_meta( $users[0], '_shopforge_wishlist', true ) ?: [];

	get_header();
	echo '<div class="shopforge-account-section" style="max-width:1000px;margin:40px auto;padding:0 20px;">';
	shopforge_account_section_header( __( 'Shared wishlist', 'shopforge' ), 'fa-solid fa-heart', '' );
	if ( empty( $wishlist ) ) {
		shopforge_account_empty_state( 'fa-solid fa-heart', __( 'No saved products', 'shopforge' ), '' );
	} else {
		shopforge_render_wishlist_grid( $wishlist, false );
	}
	echo '</div>';
	get_footer();
	exit;
} );


// ---- AJAX: aggiungi / rimuovi dalla wishlist ----

add_action( 'wp_ajax_shopforge_toggle_wishlist', function () {
	check_ajax_referer( 'shopforge_wishlist', 'nonce' );
	$product_id = absint( $_POST['product_id'] ?? 0 );
	$user_id    = get_current_user_id();
	if ( ! $user_id || ! $product_id ) wp_send_json_error();

	$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];
	$key      = array_search( $product_id, $wishlist, true );

	if ( $key !== false ) {
		array_splice( $wishlist, $key, 1 );
		$action = 'removed';
	} else {
		$wishlist[] = $product_id;
		$action     = 'added';
	}

	update_user_meta( $user_id, '_shopforge_wishlist', array_values( $wishlist ) );
	wp_send_json_success( [ 'action' => $action, 'count' => count( $wishlist ) ] );
} );

add_action( 'wp_ajax_shopforge_remove_wishlist', function () {
	check_ajax_referer( 'shopforge_wishlist', 'nonce' );
	$product_id = absint( $_POST['product_id'] ?? 0 );
	$user_id    = get_current_user_id();
	if ( ! $user_id || ! $product_id ) wp_send_json_error();

	$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];
	$wishlist = array_values( array_filter( $wishlist, fn( $id ) => $id !== $product_id ) );
	update_user_meta( $user_id, '_shopforge_wishlist', $wishlist );
	wp_send_json_success();
} );


// ---- AJAX: non autenticato → redirect login ----

add_action( 'wp_ajax_nopriv_shopforge_toggle_wishlist', function () {
	wp_send_json_error( [ 'redirect' => wc_get_page_permalink( 'myaccount' ) ] );
} );


// ---- Pulsante wishlist sul catalogo prodotti ----

add_action( 'woocommerce_after_shop_loop_item', function () {
	global $product;
	if ( ! $product ) return;

	$product_id = $product->get_id();
	$user_id    = get_current_user_id();
	$in_list    = false;

	if ( $user_id ) {
		$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];
		$in_list  = in_array( $product_id, $wishlist, true );
	}

	$login_url = wc_get_page_permalink( 'myaccount' );
	$nonce     = $user_id ? wp_create_nonce( 'shopforge_wishlist' ) : '';
	?>
	<button type="button"
	        class="shopforge-wl-btn <?php echo $in_list ? 'is-active' : ''; ?>"
	        data-product="<?php echo esc_attr( $product_id ); ?>"
	        data-nonce="<?php echo esc_attr( $nonce ); ?>"
	        data-login="<?php echo esc_url( $login_url ); ?>"
	        aria-label="<?php echo esc_attr( $in_list ? __( 'Remove from wishlist', 'shopforge' ) : __( 'Add to wishlist', 'shopforge' ) ); ?>"
	        title="<?php echo esc_attr( $in_list ? __( 'Remove from wishlist', 'shopforge' ) : __( 'Add to wishlist', 'shopforge' ) ); ?>">
		<i class="<?php echo $in_list ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true"></i>
	</button>
	<?php
}, 15 );


// ---- Pulsante wishlist nella pagina singolo prodotto ----

add_action( 'woocommerce_after_add_to_cart_button', function () {
	global $product;
	if ( ! $product ) return;

	$product_id = $product->get_id();
	$user_id    = get_current_user_id();
	$in_list    = false;

	if ( $user_id ) {
		$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];
		$in_list  = in_array( $product_id, $wishlist, true );
	}

	$login_url = wc_get_page_permalink( 'myaccount' );
	$nonce     = $user_id ? wp_create_nonce( 'shopforge_wishlist' ) : '';
	?>
	<button type="button"
	        class="shopforge-wl-btn shopforge-wl-btn--single <?php echo $in_list ? 'is-active' : ''; ?>"
	        data-product="<?php echo esc_attr( $product_id ); ?>"
	        data-nonce="<?php echo esc_attr( $nonce ); ?>"
	        data-login="<?php echo esc_url( $login_url ); ?>"
	        aria-label="<?php echo esc_attr( $in_list ? __( 'Remove from wishlist', 'shopforge' ) : __( 'Add to wishlist', 'shopforge' ) ); ?>">
		<i class="<?php echo $in_list ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true"></i>
		<span class="shopforge-wl-btn__label">
			<?php echo esc_html( $in_list ? __( 'In your wishlist', 'shopforge' ) : __( 'Add to wishlist', 'shopforge' ) ); ?>
		</span>
	</button>
	<?php
} );


// ---- JS wishlist globale (catalogo + single) ----

add_action( 'wp_footer', function () {
	if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product() && ! is_search() ) return;
	?>
	<script>
	(function () {
		'use strict';
		var ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
		var i18n = {
			inList:   <?php echo wp_json_encode( __( 'In your wishlist', 'shopforge' ) ); ?>,
			add:      <?php echo wp_json_encode( __( 'Add to wishlist', 'shopforge' ) ); ?>,
			remove:   <?php echo wp_json_encode( __( 'Remove from wishlist', 'shopforge' ) ); ?>
		};

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.shopforge-wl-btn');
			if ( ! btn ) return;

			// Guest → redirect login
			if ( ! btn.dataset.nonce ) {
				window.location.href = btn.dataset.login;
				return;
			}

			btn.classList.add('shopforge-wl-btn--loading');

			fetch(ajaxUrl, {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: 'action=shopforge_toggle_wishlist&product_id=' + btn.dataset.product + '&nonce=' + btn.dataset.nonce
			})
			.then(function (r) { return r.json(); })
			.then(function (d) {
				btn.classList.remove('shopforge-wl-btn--loading');

				if ( ! d.success ) {
					if (d.data && d.data.redirect) window.location.href = d.data.redirect;
					return;
				}

				var added = d.data.action === 'added';
				btn.classList.toggle('is-active', added);

				var icon = btn.querySelector('i');
				if (icon) {
					icon.className = (added ? 'fa-solid' : 'fa-regular') + ' fa-heart';
				}

				var label = btn.querySelector('.shopforge-wl-btn__label');
				if (label) {
					label.textContent = added ? i18n.inList : i18n.add;
				}

				btn.setAttribute('aria-label', added ? i18n.remove : i18n.add);
				btn.setAttribute('title', added ? i18n.remove : i18n.add);

				// Feedback visivo breve
				btn.classList.add('shopforge-wl-btn--pulse');
				setTimeout(function () { btn.classList.remove('shopforge-wl-btn--pulse'); }, 400);
			})
			.catch(function () {
				btn.classList.remove('shopforge-wl-btn--loading');
			});
		});
	})();
	</script>
	<?php
} );


// ---- CSS ----

add_action( 'wp_head', function () {
	if ( ! is_account_page() && ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product() && ! is_search() ) return;

	// ponytail: is_wc_endpoint_url() non vede gli endpoint custom del plugin
	// (mai nel registro interno di WC) — get_query_var() legge WP direttamente.
	$shopforge_on_wishlist = false !== get_query_var( 'shopforge-wishlist', false );
	if ( $shopforge_on_wishlist || ! is_account_page() ) : ?>
	<style id="shopforge-wishlist-btn-css">
	/* ---- Pulsante wishlist sul catalogo ---- */
	.shopforge-wl-btn {
		display: inline-flex; align-items: center; justify-content: center; gap: 6px;
		padding: 7px 10px;
		background: #fff;
		border: 1px solid var(--shopforge-border, #E2E8F0);
		border-radius: 6px;
		color: var(--shopforge-text-muted, #64748B);
		font-size: 15px; cursor: pointer;
		transition: color .2s, border-color .2s, background .2s, transform .15s;
		margin-top: 8px;
		position: relative;
	}
	.shopforge-wl-btn:hover {
		color: #e11d48; border-color: #e11d48;
	}
	.shopforge-wl-btn.is-active {
		color: #e11d48; border-color: #fda4af; background: #fff1f2;
	}
	.shopforge-wl-btn--pulse { transform: scale(1.15); }
	.shopforge-wl-btn--loading { opacity: .5; pointer-events: none; }

	/* ---- Versione singolo prodotto ---- */
	.shopforge-wl-btn--single {
		display: inline-flex; align-items: center; gap: 8px;
		padding: 10px 18px; font-size: 14px; font-weight: 600;
		margin-top: 10px; width: 100%; justify-content: center;
	}
	.shopforge-wl-btn__label { font-size: 14px; font-weight: 600; }
	<?php
	$shopforge_wl_layout = shopforge_get_wishlist_layout();
	$shopforge_wl_wide   = in_array( $shopforge_wl_layout['width'], [ 'full', 'stacked' ], true );
	?>
	.shopforge-wl-btn--single {
		width: <?php echo $shopforge_wl_wide ? '100%' : 'auto'; ?> !important;
		flex: <?php echo $shopforge_wl_wide ? '1 1 100%' : '0 0 auto'; ?> !important;
		align-self: center !important;
		<?php if ( $shopforge_wl_layout['width'] === 'stacked' ) : ?>order: 5 !important;<?php endif; ?>
		margin-<?php echo is_rtl() ? 'right' : 'left'; ?>: <?php echo (int) $shopforge_wl_layout['gap']; ?>px !important;
		<?php if ( $shopforge_wl_layout['margin_top'] ) : ?>margin-top: <?php echo (int) $shopforge_wl_layout['margin_top']; ?>px !important;<?php endif; ?>
		<?php if ( $shopforge_wl_layout['margin_right'] ) : ?>margin-<?php echo is_rtl() ? 'left' : 'right'; ?>: <?php echo (int) $shopforge_wl_layout['margin_right']; ?>px !important;<?php endif; ?>
		<?php if ( $shopforge_wl_layout['margin_bottom'] ) : ?>margin-bottom: <?php echo (int) $shopforge_wl_layout['margin_bottom']; ?>px !important;<?php endif; ?>
	}
	<?php if ( ! $shopforge_wl_layout['show_label'] ) : ?>
	.shopforge-wl-btn--single .shopforge-wl-btn__label { display: none; }
	<?php endif; ?>
	<?php if ( $shopforge_wl_layout['colors_enabled'] ) : ?>
	.shopforge-wl-btn--single {
		background: <?php echo esc_html( $shopforge_wl_layout['bg'] ); ?> !important;
		border-color: <?php echo esc_html( $shopforge_wl_layout['bg'] ); ?> !important;
	}
	.shopforge-wl-btn--single i { color: <?php echo esc_html( $shopforge_wl_layout['icon_color'] ); ?> !important; }
	.shopforge-wl-btn--single .shopforge-wl-btn__label { color: <?php echo esc_html( $shopforge_wl_layout['text_color'] ); ?> !important; }
	<?php endif; ?>
	<?php if ( $shopforge_wl_layout['height'] || $shopforge_wl_layout['min_width'] || $shopforge_wl_layout['font_size'] ) : ?>
	.shopforge-wl-btn--single {
		<?php if ( $shopforge_wl_layout['height'] ) : ?>height: <?php echo (int) $shopforge_wl_layout['height']; ?>px !important;<?php endif; ?>
		<?php if ( $shopforge_wl_layout['min_width'] ) : ?>min-width: <?php echo (int) $shopforge_wl_layout['min_width']; ?>px !important;<?php endif; ?>
		<?php if ( $shopforge_wl_layout['font_size'] ) : ?>font-size: <?php echo (int) $shopforge_wl_layout['font_size']; ?>px !important;<?php endif; ?>
	}
	<?php endif; ?>
	</style>
	<?php
	if ( $shopforge_wl_layout['width'] === 'stacked' ) : ?>
	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.querySelector( '.shopforge-wl-btn--single' );
		if ( btn && btn.parentElement ) btn.parentElement.style.flexWrap = 'wrap';
	} );
	</script>
	<?php endif;
	endif;

	if ( ! $shopforge_on_wishlist ) return;
	?>
	<style id="shopforge-wishlist-css">
	.shopforge-wishlist-grid { display: flex; flex-direction: column; gap: 12px; }
	.shopforge-wishlist-item {
		display: flex; align-items: center; gap: 16px;
		padding: 14px 18px;
		background: #fff;
		border: 1px solid var(--shopforge-border);
		border-radius: var(--shopforge-radius);
		box-shadow: var(--shopforge-shadow);
	}
	.shopforge-wishlist-item__thumb {
		width: 64px; height: 64px; flex-shrink: 0;
		border-radius: 8px; overflow: hidden;
		border: 1px solid var(--shopforge-border-soft);
		background: var(--shopforge-bg-soft);
		display: flex; align-items: center; justify-content: center;
	}
	.shopforge-wishlist-item__thumb img { width: 64px; height: 64px; object-fit: cover; display: block; }
	.shopforge-wishlist-item__no-img { font-size: 22px; color: var(--shopforge-border); }
	.shopforge-wishlist-item__body { flex: 1; min-width: 0; }
	.shopforge-wishlist-item__name {
		display: block; font-size: 14px; font-weight: 700;
		color: var(--shopforge-text-main); text-decoration: none;
		margin-bottom: 4px; line-height: 1.3;
	}
	.shopforge-wishlist-item__name:hover { color: var(--shopforge-primary); }
	.shopforge-wishlist-item__price { font-size: 13px; color: var(--shopforge-text-muted); }
	.shopforge-wishlist-item__actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
	@media (max-width: 600px) {
		.shopforge-wishlist-item { flex-wrap: wrap; }
		.shopforge-wishlist-item__actions { width: 100%; }
		.shopforge-wishlist-item__actions .shopforge-btn--primary { flex: 1; justify-content: center; }
	}
	</style>
	<?php
} );

// ---- REST API (utente autenticato: cookie o application password) ----

add_action( 'rest_api_init', function () {
	register_rest_route( 'shopforge/v1', '/wishlist', [
		'methods'             => 'GET',
		'permission_callback' => fn() => is_user_logged_in(),
		'callback'            => function () {
			$wishlist = get_user_meta( get_current_user_id(), '_shopforge_wishlist', true ) ?: [];
			return rest_ensure_response( array_values( array_map( 'intval', $wishlist ) ) );
		},
	] );

	register_rest_route( 'shopforge/v1', '/wishlist/(?P<product_id>\d+)', [
		[
			'methods'             => 'POST',
			'permission_callback' => fn() => is_user_logged_in(),
			'callback'            => function ( WP_REST_Request $req ) {
				$user_id    = get_current_user_id();
				$product_id = absint( $req['product_id'] );
				if ( ! wc_get_product( $product_id ) ) {
					return new WP_Error( 'shopforge_invalid_product', __( 'Invalid product.', 'shopforge' ), [ 'status' => 404 ] );
				}
				$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];
				if ( ! in_array( $product_id, $wishlist, true ) ) {
					$wishlist[] = $product_id;
					update_user_meta( $user_id, '_shopforge_wishlist', $wishlist );
				}
				return rest_ensure_response( array_values( array_map( 'intval', $wishlist ) ) );
			},
		],
		[
			'methods'             => 'DELETE',
			'permission_callback' => fn() => is_user_logged_in(),
			'callback'            => function ( WP_REST_Request $req ) {
				$user_id    = get_current_user_id();
				$product_id = absint( $req['product_id'] );
				$wishlist   = array_diff( get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [], [ $product_id ] );
				update_user_meta( $user_id, '_shopforge_wishlist', array_values( $wishlist ) );
				return rest_ensure_response( array_values( array_map( 'intval', $wishlist ) ) );
			},
		],
	] );
} );
