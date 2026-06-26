<?php
defined( 'ABSPATH' ) || exit;
echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Nuova richiesta di assistenza — Ordine #%s\n\n", $order->get_order_number() );
printf( "Cliente: %s (%s)\n", $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $order->get_billing_email() );
printf( "Motivo: %s\n", $ticket_data['subject'] ?? '' );
if ( ! empty( $ticket_data['products'] ) ) {
	echo "Prodotti:\n";
	foreach ( (array) $ticket_data['products'] as $p ) echo "  • " . esc_html( $p ) . "\n";
}
printf( "\nMessaggio:\n%s\n\n", $ticket_data['message'] ?? '' );
printf( "Gestisci ordine: %s\n\n", admin_url( 'admin.php?page=wc-orders&id=' . $order->get_id() ) );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
