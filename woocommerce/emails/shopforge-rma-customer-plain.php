<?php
defined( 'ABSPATH' ) || exit;

$tipo_label = 'reso' === ( $rma_data['tipo'] ?? '' ) ? 'reso' : 'assistenza';

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Gentile %s,\n\n", $order->get_billing_first_name() );
printf( "Abbiamo ricevuto la tua richiesta di %s per l'ordine #%s.\n\n", $tipo_label, $order->get_order_number() );
printf( "Numero richiesta: #%s\n", $rma_data['request_id'] ?? '' );
if ( ! empty( $rma_data['product'] ) ) printf( "Prodotto: %s\n", $rma_data['product'] );
if ( ! empty( $rma_data['descrizione'] ) ) printf( "\nDescrizione problema:\n%s\n", $rma_data['descrizione'] );
printf( "\nVisualizza ordine: %s\n\n", $order->get_view_order_url() );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
