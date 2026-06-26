<?php
defined( 'ABSPATH' ) || exit;
echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Ciao %s,\n\n", $order->get_billing_first_name() );
printf( "Abbiamo ricevuto la tua richiesta per l'ordine #%s.\n\n", $order->get_order_number() );
printf( "Motivo: %s\n", $ticket_data['subject'] ?? '' );
printf( "Messaggio:\n%s\n\n", $ticket_data['message'] ?? '' );
printf( "Visualizza ordine: %s\n\n", wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ) );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
