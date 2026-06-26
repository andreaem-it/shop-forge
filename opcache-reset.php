<?php
/**
 * Reset OPcache — solo per sviluppo locale.
 * Questo file NON deve essere presente in produzione.
 * @package ShopForge
 */
defined( 'ABSPATH' ) || exit; // Blocca accesso diretto
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accesso non autorizzato.' );
}
if ( function_exists( 'opcache_reset' ) ) {
    opcache_reset();
    echo 'OPcache svuotata.';
} else {
    echo 'OPcache non disponibile.';
}
