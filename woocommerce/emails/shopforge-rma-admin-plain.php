<?php
defined( 'ABSPATH' ) || exit;

$tipo_label = 'reso' === ( $rma_data['tipo'] ?? '' ) ? 'Reso' : 'Assistenza';

echo "= " . esc_html( $email_heading ) . " =\n\n";
if ( ! empty( $rma_data['is_status_update'] ) ) {
	printf( "Il cliente ha aggiornato lo stato della richiesta RMA #%d — Ordine #%s\n\n", (int) ( $rma_data['request_id'] ?? 0 ), $order->get_order_number() );
} else {
	printf( "Nuova richiesta di %s — Ordine #%s\n\n", $tipo_label, $order->get_order_number() );
}
printf( "Cliente: %s (%s)\n", $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $order->get_billing_email() );
printf( "Tipo: %s\n", $tipo_label );
if ( ! empty( $rma_data['product'] ) ) printf( "Prodotto: %s\n", $rma_data['product'] );
if ( ! empty( $rma_data['descrizione'] ) ) printf( "\nDescrizione problema:\n%s\n", $rma_data['descrizione'] );
printf( "\nGestisci richiesta: %s\n\n", admin_url( 'edit.php?post_type=shopforge_rma_request' ) );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
