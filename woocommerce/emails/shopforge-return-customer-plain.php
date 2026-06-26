<?php
/**
 * Template email plain-text — Ricevuta di recesso al cliente
 *
 * @var WC_Order $order
 * @var array    $return_data
 * @var string   $email_heading
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf( "Gentile %s,\n\n", $order->get_billing_first_name() );
echo "Abbiamo ricevuto la tua dichiarazione di recesso.\n\n";

echo "RIEPILOGO\n---------\n";
printf( "Riferimento: %s\n", $return_data['ref'] ?? '' );
printf( "Data e ora di trasmissione: %s\n", $return_data['date_str'] ?? '' );
printf( "Ordine: #%s\n", $order->get_order_number() );
echo "Prodotti:\n";
foreach ( (array) ( $return_data['products'] ?? [] ) as $p ) {
	echo "  • " . esc_html( $p ) . "\n";
}
printf( "Motivo: %s\n", $return_data['reason'] ?? '' );
printf( "Rimborso preferito: %s\n\n", $return_data['refund'] ?? '' );

echo "DICHIARAZIONE DI RECESSO\n------------------------\n";
echo esc_html( $return_data['declaration'] ?? '' );
echo "\n------------------------\n\n";

echo "Elaboreremo la tua richiesta entro i termini previsti dalla legge.\n";
echo "Conserva questa email come prova della data di trasmissione.\n\n";

printf( "I tuoi resi: %s\n\n", wc_get_endpoint_url( 'shopforge-returns', '', wc_get_page_permalink( 'myaccount' ) ) );

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
