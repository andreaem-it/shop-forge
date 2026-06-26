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
 * Disabilita il plugin se licenza non configurata
 */
add_action( 'plugins_loaded', function () {
	$key = shopforge_get_license_key();
	if ( ! $key ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p><strong>ShopForge</strong> – <a href="' . admin_url( 'admin.php?page=shopforge-license' ) . '">Configura la licenza</a></p></div>';
		} );
		remove_action( 'plugins_loaded', 'shopforge_load_modules' );
		return;
	}
}, 0 );

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
 * Settings page
 */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		'ShopForge — Licenza',
		'ShopForge',
		'manage_options',
		'shopforge-license',
		'shopforge_license_page'
	);
} );

function shopforge_license_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non hai permessi sufficienti.' );
	}

	$current_key = shopforge_get_license_key();
	$license_data = shopforge_get_license_data();
	?>
	<div class="wrap" style="max-width: 600px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;">
		<h1 style="margin-bottom: 30px; color: #333;">📜 ShopForge — Licenza</h1>

		<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<div class="form-group" style="margin-bottom: 20px;">
				<label for="shopforge_license_key" style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
					Chiave di licenza
				</label>
				<input 
					type="text" 
					id="shopforge_license_key" 
					placeholder="SHOP-ABC123XYZ" 
					value="<?php echo esc_attr( $current_key ); ?>"
					style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 4px; font-size: 14px; font-family: monospace;"
				/>
				<p style="margin-top: 8px; color: #666; font-size: 13px;">
					Inserisci la chiave di licenza fornita al momento dell'acquisto.
				</p>
			</div>

			<button 
				id="shopforge_validate_btn" 
				style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px;"
				onclick="shopforgeValidateLicense()"
			>
				Valida licenza
			</button>

			<div id="shopforge_status" style="margin-top: 20px; display: none;">
				<div id="shopforge_message" style="padding: 12px; border-radius: 4px; font-size: 14px;"></div>
				<div id="shopforge_details" style="margin-top: 12px; padding: 12px; background: #f5f5f5; border-radius: 4px; font-size: 13px; line-height: 1.6;"></div>
			</div>

			<div id="shopforge_loading" style="display: none; margin-top: 20px; text-align: center; color: #666;">
				<span>Validazione in corso...</span>
			</div>

			<?php if ( $current_key && ! empty( $license_data ) ): ?>
				<div style="margin-top: 30px; padding: 15px; background: #f0f9ff; border-left: 4px solid #0073aa; border-radius: 4px;">
					<p style="margin: 0; font-weight: 600; color: #0073aa;">✓ Licenza attiva</p>
					<div style="margin-top: 10px; font-size: 13px; color: #666; line-height: 1.6;">
						<?php
						if ( ! empty( $license_data['reseller_name'] ) ) {
							echo 'Reseller: <strong>' . htmlspecialchars( $license_data['reseller_name'] ) . '</strong><br>';
						}
						if ( ! empty( $license_data['product_name'] ) ) {
							echo 'Prodotto: <strong>' . htmlspecialchars( $license_data['product_name'] ) . '</strong><br>';
						}
						if ( ! empty( $license_data['activated_domain'] ) ) {
							echo 'Attivato su: <strong>' . htmlspecialchars( $license_data['activated_domain'] ) . '</strong><br>';
						}
						if ( ! empty( $license_data['expires_at'] ) ) {
							echo 'Scade il: <strong>' . htmlspecialchars( $license_data['expires_at'] ) . '</strong>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

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
