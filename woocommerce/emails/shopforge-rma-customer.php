<?php
/**
 * Template email HTML — Ricevuta richiesta RMA al cliente
 * Sovrascrivibile dal tema: woocommerce/emails/shopforge-rma-customer.php
 *
 * @var WC_Order $order
 * @var array    $rma_data { request_id, tipo, product, descrizione }
 * @var string   $email_heading
 * @var WC_Email $email
 * @package ShopForge
 */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_email_header', $email_heading, $email );

$tipo_label = 'reso' === ( $rma_data['tipo'] ?? '' ) ? __( 'return', 'shopforge' ) : __( 'support', 'shopforge' );
?>

<p><?php printf( esc_html__( 'Dear %s,', 'shopforge' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php printf( esc_html__( 'We received your %1$s request for order #%2$s. Our team will review it as soon as possible.', 'shopforge' ), esc_html( $tipo_label ), esc_html( $order->get_order_number() ) ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Request number', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $rma_data['request_id'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<?php if ( ! empty( $rma_data['product'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Product', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $rma_data['product'] ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<?php if ( ! empty( $rma_data['descrizione'] ) ) : ?>
<p><strong><?php esc_html_e( 'Description of the problem:', 'shopforge' ); ?></strong></p>
<p style="padding:15px;background:#f7f7f7;border-radius:4px;"><?php echo nl2br( esc_html( $rma_data['descrizione'] ) ); ?></p>
<?php endif; ?>

<p>
	<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'View order →', 'shopforge' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', $email );
