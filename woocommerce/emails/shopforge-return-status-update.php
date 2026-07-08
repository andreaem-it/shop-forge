<?php
/**
 * Template email HTML — Aggiornamento stato reso al cliente
 * @var WC_Order $order
 * @var array    $return_data { ref, status, prev_status, reply }
 * @var string   $email_heading
 * @var WC_Email $email
 */
defined( 'ABSPATH' ) || exit;

$status_labels = [
	'pending'    => __( 'Received', 'shopforge' ),
	'processing' => __( 'Processing', 'shopforge' ),
	'approved'   => __( 'Approved', 'shopforge' ),
	'refunded'   => __( 'Refunded', 'shopforge' ),
	'rejected'   => __( 'Rejected', 'shopforge' ),
];
$status_label  = $status_labels[ $return_data['status'] ?? 'pending' ] ?? $status_labels['pending'];

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Dear %s,', 'shopforge' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php esc_html_e( 'We are writing to inform you of an update on your return request.', 'shopforge' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Return reference', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $return_data['ref'] ?? '' ); ?></strong></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Current status', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $status_label ); ?></strong></td>
	</tr>
	<?php if ( ! empty( $return_data['reply'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Message from the store', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo nl2br( esc_html( $return_data['reply'] ) ); ?></td>
	</tr>
	<?php endif; ?>
	<?php if ( ! empty( $return_data['coupon_code'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Store credit coupon', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $return_data['coupon_code'] ); ?></strong></td>
	</tr>
	<?php endif; ?>
</table>

<p>
	<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'View order →', 'shopforge' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', $email );
