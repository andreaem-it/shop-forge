<?php
defined( 'ABSPATH' ) || exit;
$status_labels = [ 'open' => 'Aperto', 'closed' => 'Chiuso' ];
echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Gentile %s,\n\n", $order->get_billing_first_name() );
echo "Aggiornamento sulla tua richiesta di assistenza:\n\n";
printf( "Ordine: #%s\n", $order->get_order_number() );
printf( "Richiesta: %s\n", $ticket_data['subject'] ?? '' );
printf( "Stato: %s\n", $status_labels[ $ticket_data['status'] ?? 'open' ] ?? 'Aperto' );
if ( ! empty( $ticket_data['reply'] ) ) printf( "\nMessaggio dal negozio:\n%s\n", $ticket_data['reply'] );
printf( "\nVisualizza ordine: %s\n\n", $order->get_view_order_url() );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
