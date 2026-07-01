<?php
/**
 * Sistema di licensing ShopForge — AJAX validation
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

function shopforge_get_license_key() {
	return get_option( 'shopforge_license_key' );
}

function shopforge_set_license_key( $key ) {
	return update_option( 'shopforge_license_key', sanitize_text_field( $key ) );
}

function shopforge_get_license_data() {
	return get_option( 'shopforge_license_data', [] );
}

function shopforge_set_license_data( $data ) {
	return update_option( 'shopforge_license_data', $data );
}

/**
 * Avviso in admin se la licenza non è configurata/valida.
 * Il blocco effettivo dei moduli è centralizzato in
 * shopforge_load_modules() (inc/shopforge-modules.php), gated da
 * shopforge_has_valid_license() (inc/shopforge-modules-check.php).
 */
add_action( 'admin_notices', function () {
	if ( function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license() ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>ShopForge</strong> – <a href="' . esc_url( admin_url( 'admin.php?page=shopforge&tab=license' ) ) . '">Configura la licenza</a></p></div>';
} );

/**
 * AJAX: valida licenza
 */
add_action( 'wp_ajax_shopforge_validate_license', function () {
	check_ajax_referer( 'shopforge_validate', false );

	$key = $_POST['key'] ?? '';
	$site = home_url();

	if ( ! $key ) {
		wp_send_json( [ 'valid' => false, 'message' => 'Chiave mancante' ], 400 );
	}

	// Chiama il server di licensing
	$response = wp_remote_post( SHOPFORGE_LICENSE_SERVER, [
		'body'      => [ 'key' => $key, 'site' => $site ],
		'timeout'   => 5,
		'sslverify' => true,
	] );

	if ( is_wp_error( $response ) ) {
		wp_send_json( [
			'valid'   => false,
			'message' => 'Errore di connessione: ' . $response->get_error_message(),
		], 500 );
	}

	$status = wp_remote_retrieve_response_code( $response );
	$body   = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status === 200 && ( $body['valid'] ?? false ) ) {
		// Salva chiave e dati
		shopforge_set_license_key( $key );
		shopforge_set_license_data( $body );

		$message = 'Licenza valida ✓';
		$details = [];

		if ( ! empty( $body['reseller_name'] ) ) {
			$details[] = 'Reseller: <strong>' . htmlspecialchars( $body['reseller_name'] ) . '</strong>';
		}

		if ( ! empty( $body['product_name'] ) ) {
			$details[] = 'Prodotto: <strong>' . htmlspecialchars( $body['product_name'] ) . '</strong>';
		}

		if ( ! empty( $body['activated_domain'] ) ) {
			$details[] = 'Attivato su: <strong>' . htmlspecialchars( $body['activated_domain'] ) . '</strong>';
		}

		if ( ! empty( $body['expires_at'] ) ) {
			$details[] = 'Scade il: <strong>' . htmlspecialchars( $body['expires_at'] ) . '</strong>';
		}

		wp_send_json( [
			'valid'   => true,
			'message' => $message,
			'details' => implode( '<br>', $details ),
		] );
	} else {
		wp_send_json( [
			'valid'   => false,
			'message' => $body['reason'] ?? 'Licenza non valida',
		], 403 );
	}
} );

/**
 * Tab "Licenza" — vedi inc/shopforge-admin-page.php per il router e il
 * menu (unico per tutte le tab).
 */
function shopforge_admin_tab_license(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<p>Non hai i permessi per gestire la licenza.</p>';
		return;
	}

	$current_key  = shopforge_get_license_key();
	$license_data = shopforge_get_license_data();
	$is_valid     = function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license();
	?>
	<div class="shopforge-license-card">
		<div class="shopforge-license-card__field">
			<label for="shopforge_license_key">Chiave di licenza</label>
			<input type="text" id="shopforge_license_key" placeholder="SHOP-ABC123XYZ"
			       value="<?php echo esc_attr( $current_key ); ?>" autocomplete="off">
			<p class="shopforge-license-card__hint">Inserisci la chiave di licenza fornita al momento dell'acquisto.</p>
		</div>

		<button type="button" id="shopforge_validate_btn" class="button button-primary" onclick="shopforgeValidateLicense()">
			Valida licenza
		</button>

		<div id="shopforge_status" class="shopforge-license-result" style="display:none">
			<div id="shopforge_message" class="shopforge-license-result__message"></div>
			<div id="shopforge_details" class="shopforge-license-result__details"></div>
		</div>

		<div id="shopforge_loading" class="shopforge-license-loading" style="display:none">
			<span>Validazione in corso…</span>
		</div>
	</div>

	<?php if ( $current_key && ! empty( $license_data ) ) : ?>
	<div class="shopforge-module-card <?php echo $is_valid ? 'is-active' : 'is-inactive'; ?>" style="margin-top:16px">
		<div class="shopforge-module-card__header">
			<span class="shopforge-module-card__icon">
				<i class="fa-solid <?php echo $is_valid ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" aria-hidden="true"></i>
			</span>
			<div class="shopforge-module-card__title-wrap">
				<h3 class="shopforge-module-card__title"><?php echo $is_valid ? 'Licenza attiva' : 'Licenza non valida o scaduta'; ?></h3>
			</div>
		</div>
		<div class="shopforge-module-card__desc" style="line-height:1.8">
			<?php if ( ! empty( $license_data['reseller_name'] ) ) : ?>
				Reseller: <strong><?php echo esc_html( $license_data['reseller_name'] ); ?></strong><br>
			<?php endif; ?>
			<?php if ( ! empty( $license_data['product_name'] ) ) : ?>
				Prodotto: <strong><?php echo esc_html( $license_data['product_name'] ); ?></strong><br>
			<?php endif; ?>
			<?php if ( ! empty( $license_data['activated_domain'] ) ) : ?>
				Attivato su: <strong><?php echo esc_html( $license_data['activated_domain'] ); ?></strong><br>
			<?php endif; ?>
			<?php if ( ! empty( $license_data['expires_at'] ) ) : ?>
				Scade il: <strong><?php echo esc_html( $license_data['expires_at'] ); ?></strong>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<style>
	.shopforge-license-card {
		background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
		padding: 20px 22px; max-width: 480px;
	}
	.shopforge-license-card__field { margin-bottom: 16px; }
	.shopforge-license-card__field label {
		display: block; font-size: 13px; font-weight: 700; color: #1d2327; margin-bottom: 8px;
	}
	.shopforge-license-card__field input {
		width: 100%; box-sizing: border-box; padding: 8px 10px;
		border: 1px solid #8c8f94; border-radius: 4px;
		font-size: 14px; font-family: monospace;
	}
	.shopforge-license-card__hint { margin: 8px 0 0; font-size: 12px; color: #646970; }
	.shopforge-license-result { margin-top: 16px; }
	.shopforge-license-result__message { padding: 10px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; }
	.shopforge-license-result__details { margin-top: 10px; padding: 10px 12px; background: #f6f7f7; border-radius: 4px; font-size: 12px; line-height: 1.6; }
	.shopforge-license-loading { margin-top: 16px; color: #646970; font-size: 13px; }
	</style>

	<script>
	function shopforgeValidateLicense() {
		const key = document.getElementById('shopforge_license_key').value.trim();
		const btn = document.getElementById('shopforge_validate_btn');
		const status = document.getElementById('shopforge_status');
		const loading = document.getElementById('shopforge_loading');
		const message = document.getElementById('shopforge_message');
		const details = document.getElementById('shopforge_details');

		if (!key) {
			message.style.background = '#fee';
			message.style.color = '#c33';
			message.textContent = '✗ Inserisci una chiave';
			status.style.display = 'block';
			return;
		}

		btn.disabled = true;
		loading.style.display = 'block';
		status.style.display = 'none';

		fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'shopforge_validate_license',
				key: key,
				_ajax_nonce: '<?php echo wp_create_nonce( 'shopforge_validate' ); ?>',
			}),
		})
		.then(res => res.json())
		.then(data => {
			loading.style.display = 'none';
			status.style.display = 'block';

			if (data.valid) {
				message.style.background = '#eef';
				message.style.color = '#060';
				message.textContent = '✓ ' + (data.message || 'Licenza valida');
				details.innerHTML = data.details || '';
				setTimeout(() => location.reload(), 1500);
			} else {
				message.style.background = '#fee';
				message.style.color = '#c33';
				message.textContent = '✗ ' + (data.message || 'Licenza non valida');
				details.innerHTML = '';
			}

			btn.disabled = false;
		})
		.catch(err => {
			loading.style.display = 'none';
			status.style.display = 'block';
			message.style.background = '#fee';
			message.style.color = '#c33';
			message.textContent = '✗ Errore: ' + err.message;
			details.innerHTML = '';
			btn.disabled = false;
		});
	}

	document.getElementById('shopforge_license_key').addEventListener('keypress', function(e) {
		if (e.key === 'Enter') {
			shopforgeValidateLicense();
		}
	});
	</script>
	<?php
}
