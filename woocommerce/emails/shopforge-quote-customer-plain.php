<?php
defined( 'ABSPATH' ) || exit;
$user = get_userdata( $user_id );
echo "= " . esc_html( $email_heading ) . " =\n\n";
if ( $user ) printf( "Gentile %s,\n\n", $user->display_name );
echo "Abbiamo ricevuto la tua richiesta di preventivo. Ti risponderemo il prima possibile.\n\n";
printf( "Riferimento: %s\nData: %s\n\n", $quote_data['ref'] ?? '', $quote_data['date'] ?? '' );
echo "Prodotti:\n";
foreach ( (array) ( $quote_data['items'] ?? [] ) as $i ) {
	printf( "  • %d× %s\n", $i['qty'] ?? 1, $i['name'] ?? '' );
}
if ( ! empty( $quote_data['notes'] ) ) printf( "\nNote: %s\n", $quote_data['notes'] );
printf( "\nI miei preventivi: %s\n\n", wc_get_account_endpoint_url( 'shopforge-quotes' ) );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
