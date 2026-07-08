<?php
/**
 * Template email HTML — Aggiornamento stato ticket al cliente
 * @var WC_Order $order
 * @var array    $ticket_data { subject, status, prev_status, reply }
 * @var string   $email_heading
 * @var WC_Email $email
 */
defined( 'ABSPATH' ) || exit;

$status_labels = [ 'open' => 'Aperto', 'closed' => 'Chiuso' ];
$status_label  = $status_labels[ $ticket_data['status'] ?? 'open' ] ?? 'Aperto';

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Dear %s,', 'shopforge' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php esc_html_e( 'We are writing to inform you of an update on your support request.', 'shopforge' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Request', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $ticket_data['subject'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Current status', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $status_label ); ?></strong></td>
	</tr>
	<?php if ( ! empty( $ticket_data['reply'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Message from the store', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo nl2br( esc_html( $ticket_data['reply'] ) ); ?></td>
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
