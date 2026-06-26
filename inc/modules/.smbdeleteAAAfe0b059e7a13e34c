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

// ---- Contenuto endpoint ----

add_action( 'woocommerce_account_shopforge-wishlist_endpoint', function () {
	$user_id  = get_current_user_id();
	$wishlist = get_user_meta( $user_id, '_shopforge_wishlist', true ) ?: [];

	shopforge_account_section_header(
		'Lista desideri',
		'fa-solid fa-heart',
		count( $wishlist ) . ' ' . _n( 'prodotto salvato', 'prodotti salvati', count( $wishlist ) )
	);

	if ( empty( $wishlist ) ) {
		shopforge_account_empty_state(
			'fa-solid fa-heart',
			'Nessun prodotto salvato',
			'Aggiungi prodotti alla lista desideri per ritrovarli facilmente.'
		);
		return;
	}

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
					<i class="fa-solid fa-cart-plus"></i> Aggiungi al carrello
				</a>
				<?php else : ?>
				<span class="shopforge-badge shopforge-badge--muted">Non disponibile</span>
				<?php endif; ?>
				<button type="button" class="shopforge-btn shopforge-btn--ghost shopforge-remove-wishlist"
				        data-product="<?php echo esc_attr( $product_id ); ?>"
				        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_wishlist' ) ); ?>">
					<i class="fa-solid fa-trash-can"></i>
				</button>
			</div>
		</div>
		<?php
	}
	echo '</div>';

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
	</script>
	<?php
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
	        aria-label="<?php echo $in_list ? 'Rimuovi dalla lista desideri' : 'Aggiungi alla lista desideri'; ?>"
	        title="<?php echo $in_list ? 'Rimuovi dalla lista desideri' : 'Aggiungi alla lista desideri'; ?>">
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
	        aria-label="<?php echo $in_list ? 'Rimuovi dalla lista desideri' : 'Aggiungi alla lista desideri'; ?>">
		<i class="<?php echo $in_list ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true"></i>
		<span class="shopforge-wl-btn__label">
			<?php echo $in_list ? 'Nella lista desideri' : 'Aggiungi alla lista desideri'; ?>
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
					label.textContent = added ? 'Nella lista desideri' : 'Aggiungi alla lista desideri';
				}

				btn.setAttribute('aria-label', added ? 'Rimuovi dalla lista desideri' : 'Aggiungi alla lista desideri');
				btn.setAttribute('title', added ? 'Rimuovi dalla lista desideri' : 'Aggiungi alla lista desideri');

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

	if ( is_wc_endpoint_url( 'shopforge-wishlist' ) || ! is_account_page() ) : ?>
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
	</style>
	<?php endif;

	if ( ! is_wc_endpoint_url( 'shopforge-wishlist' ) ) return;
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
