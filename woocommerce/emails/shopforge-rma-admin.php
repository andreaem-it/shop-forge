<?php
/**
 * Template email HTML — Notifica admin: nuova richiesta RMA
 * Sovrascrivibile dal tema: woocommerce/emails/shopforge-rma-admin.php
 *
 * @var WC_Order $order
 * @var array    $rma_data { request_id, tipo, product, descrizione, is_status_update? }
 * @var string   $email_heading
 * @var WC_Email $email
 * @package ShopForge
 */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_email_header', $email_heading, $email );

$tipo_label = 'reso' === ( $rma_data['tipo'] ?? '' ) ? __( 'Return', 'shopforge' ) : __( 'Support', 'shopforge' );
?>

<?php if ( ! empty( $rma_data['is_status_update'] ) ) : ?>
<p><?php printf( esc_html__( 'The customer updated the status of RMA request #%d for order #%s.', 'shopforge' ), (int) ( $rma_data['request_id'] ?? 0 ), esc_html( $order->get_order_number() ) ); ?></p>
<?php else : ?>
<p><?php printf( esc_html__( 'New %1$s request received for order #%2$s.', 'shopforge' ), esc_html( $tipo_label ), esc_html( $order->get_order_number() ) ); ?></p>
<?php endif; ?>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:30%;"><?php esc_html_e( 'Customer', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">
			<?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>
			— <a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a>
		</td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Type', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $tipo_label ); ?></strong></td>
	</tr>
	<?php if ( ! empty( $rma_data['product'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Product', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $rma_data['product'] ); ?></td>
	</tr>
	<?php endif; ?>
	<?php if ( ! empty( $rma_data['descrizione'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Problem description', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo nl2br( esc_html( $rma_data['descrizione'] ) ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<p>
	<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shopforge_rma_request' ) ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Manage request →', 'shopforge' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', $email );
