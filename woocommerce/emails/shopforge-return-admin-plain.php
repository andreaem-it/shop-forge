<?php
/**
 * Template email plain-text — Notifica admin: nuova richiesta di recesso
 *
 * @var WC_Order $order
 * @var array    $return_data
 * @var string   $email_heading
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Nuova richiesta di recesso per l'ordine #%s.\n\n", esc_html( $order->get_order_number() ) );

echo "DETTAGLI\n--------\n";
printf( "Riferimento: %s\n", $return_data['ref'] ?? '' );
printf( "Data trasmissione: %s\n", $return_data['date_str'] ?? '' );
printf( "Cliente: %s (%s)\n", $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $order->get_billing_email() );
printf( "Ordine: #%s\n", $order->get_order_number() );
echo "Prodotti:\n";
foreach ( (array) ( $return_data['products'] ?? [] ) as $p ) {
	echo "  • " . esc_html( $p ) . "\n";
}
printf( "Motivo: %s\n", $return_data['reason'] ?? '' );
printf( "Rimborso preferito: %s\n", $return_data['refund'] ?? '' );
if ( ! empty( $return_data['notes'] ) ) {
	printf( "Note: %s\n", $return_data['notes'] );
}

echo "\nDICHIARAZIONE DI RECESSO\n------------------------\n";
echo esc_html( $return_data['declaration'] ?? '' );
echo "\n------------------------\n\n";

printf( "Gestisci l'ordine: %s\n\n", admin_url( 'admin.php?page=wc-orders&id=' . $order->get_id() ) );

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
