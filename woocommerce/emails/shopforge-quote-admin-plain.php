<?php
defined( 'ABSPATH' ) || exit;
$user = get_userdata( $user_id );
echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Riferimento: %s\nData: %s\n", $quote_data['ref'] ?? '', $quote_data['date'] ?? '' );
if ( $user ) printf( "Cliente: %s (%s)\n", $user->display_name, $user->user_email );
echo "Prodotti:\n";
foreach ( (array) ( $quote_data['items'] ?? [] ) as $i ) {
	printf( "  • %d× %s\n", $i['qty'] ?? 1, $i['name'] ?? '' );
}
if ( ! empty( $quote_data['notes'] ) ) printf( "Note: %s\n", $quote_data['notes'] );
printf( "\nGestisci preventivi: %s\n\n", admin_url( 'admin.php?page=shopforge-quotes' ) );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
