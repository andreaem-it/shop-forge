<?php
defined( 'ABSPATH' ) || exit;
$status_labels = [ 'pending' => 'Ricevuta', 'processing' => 'In lavorazione', 'approved' => 'Approvata', 'refunded' => 'Rimborsata', 'rejected' => 'Rifiutata' ];
echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Gentile %s,\n\n", $order->get_billing_first_name() );
echo "Aggiornamento sulla tua richiesta di reso:\n\n";
printf( "Ordine: #%s\n", $order->get_order_number() );
printf( "Riferimento: %s\n", $return_data['ref'] ?? '' );
printf( "Stato: %s\n", $status_labels[ $return_data['status'] ?? 'pending' ] ?? 'Ricevuta' );
if ( ! empty( $return_data['reply'] ) ) printf( "\nMessaggio dal negozio:\n%s\n", $return_data['reply'] );
printf( "\nVisualizza ordine: %s\n\n", $order->get_view_order_url() );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
