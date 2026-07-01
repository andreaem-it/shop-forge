<?php
/**
 * Email WooCommerce — ShopForge
 *
 * Registra le email personalizzate nel sistema nativo WooCommerce.
 * Le definizioni di classe sono in shopforge-email-classes.php e vengono
 * caricate DENTRO il filtro woocommerce_email_classes, unico punto in cui
 * WC_Email è garantita disponibile.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_email_classes', function ( array $emails ): array {

	// Carica le classi qui: WC_Email è disponibile solo in questo contesto
	if ( ! class_exists( 'ShopForge_Email_Return_Admin' ) ) {
		require_once __DIR__ . '/shopforge-email-classes.php';
	}

	$emails['ShopForge_Email_Return_Admin']         = new ShopForge_Email_Return_Admin();
	$emails['ShopForge_Email_Return_Customer']      = new ShopForge_Email_Return_Customer();
	$emails['ShopForge_Email_Return_Status_Update'] = new ShopForge_Email_Return_Status_Update();
	$emails['ShopForge_Email_Ticket_Admin']         = new ShopForge_Email_Ticket_Admin();
	$emails['ShopForge_Email_Ticket_Customer']      = new ShopForge_Email_Ticket_Customer();
	$emails['ShopForge_Email_Ticket_Status_Update'] = new ShopForge_Email_Ticket_Status_Update();
	$emails['ShopForge_Email_Quote_Admin']          = new ShopForge_Email_Quote_Admin();
	$emails['ShopForge_Email_Quote_Customer']       = new ShopForge_Email_Quote_Customer();
	$emails['ShopForge_Email_RMA_Admin']            = new ShopForge_Email_RMA_Admin();
	$emails['ShopForge_Email_RMA_Customer']         = new ShopForge_Email_RMA_Customer();
	$emails['ShopForge_Email_RMA_Status_Update']    = new ShopForge_Email_RMA_Status_Update();

	return $emails;
} );
