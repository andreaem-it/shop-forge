<?php
defined( 'ABSPATH' ) || exit;

$status_label = function_exists( 'shopforge_rma_get_status_label' ) ? shopforge_rma_get_status_label( $rma_data['status'] ?? 'aperta' ) : ( $rma_data['status'] ?? '' );

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( "Gentile %s,\n\n", $order->get_billing_first_name() );
echo "Aggiornamento sulla tua richiesta di assistenza prodotti.\n\n";
printf( "Ordine: #%s\n", $order->get_order_number() );
if ( ! empty( $rma_data['product'] ) ) printf( "Prodotto: %s\n", $rma_data['product'] );
printf( "Stato attuale: %s\n", $status_label );
if ( ! empty( $rma_data['reply'] ) ) printf( "\nMessaggio dal negozio:\n%s\n", $rma_data['reply'] );
printf( "\nVisualizza ordine: %s\n\n", $order->get_view_order_url() );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
