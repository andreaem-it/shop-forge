<?php
defined( 'ABSPATH' ) || exit;
$status_labels = [
	'pending'    => __( 'Received', 'shopforge' ),
	'processing' => __( 'Processing', 'shopforge' ),
	'approved'   => __( 'Approved', 'shopforge' ),
	'refunded'   => __( 'Refunded', 'shopforge' ),
	'rejected'   => __( 'Rejected', 'shopforge' ),
];
echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( esc_html__( 'Dear %s,', 'shopforge' ) . "\n\n", $order->get_billing_first_name() );
echo esc_html__( 'We are writing to inform you of an update on your return request.', 'shopforge' ) . "\n\n";
printf( esc_html__( 'Order: #%s', 'shopforge' ) . "\n", $order->get_order_number() );
printf( esc_html__( 'Reference: %s', 'shopforge' ) . "\n", $return_data['ref'] ?? '' );
printf( esc_html__( 'Status: %s', 'shopforge' ) . "\n", $status_labels[ $return_data['status'] ?? 'pending' ] ?? $status_labels['pending'] );
if ( ! empty( $return_data['coupon_code'] ) ) {
	printf( "\n" . esc_html__( 'Store credit coupon: %s', 'shopforge' ) . "\n", $return_data['coupon_code'] );
}
if ( ! empty( $return_data['reply'] ) ) {
	printf( "\n" . esc_html__( 'Message from the store:', 'shopforge' ) . "\n%s\n", $return_data['reply'] );
}
printf( "\n" . esc_html__( 'View order: %s', 'shopforge' ) . "\n\n", $order->get_view_order_url() );
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
