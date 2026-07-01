<?php
/**
 * Gating licenza → moduli
 *
 * Richiesto in shopforge.php prima del check WooCommerce, subito dopo
 * shopforge-license.php (da cui riusa gli helper di lettura licenza).
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Vero se esiste una chiave di licenza salvata e l'ultima validazione
 * registrata è positiva e non scaduta. Nessuna chiamata di rete: usa la
 * cache salvata da shopforge_set_license_data() all'ultima validazione
 * riuscita (via la pagina Licenza).
 */
function shopforge_has_valid_license(): bool {
	if ( ! shopforge_get_license_key() ) {
		return false;
	}

	$data = shopforge_get_license_data();
	if ( empty( $data['valid'] ) ) {
		return false;
	}

	if ( ! empty( $data['expires_at'] ) && strtotime( $data['expires_at'] ) < time() ) {
		return false;
	}

	return true;
}
