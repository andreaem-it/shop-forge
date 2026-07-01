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

$tipo_label = 'reso' === ( $rma_data['tipo'] ?? '' ) ? __( 'reso', 'shopforge' ) : __( 'assistenza', 'shopforge' );
?>

<p><?php printf( esc_html__( 'Gentile %s,', 'shopforge' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php printf( esc_html__( 'Abbiamo ricevuto la tua richiesta di %1$s per l\'ordine #%2$s. Il nostro team la esaminerà al più presto.', 'shopforge' ), esc_html( $tipo_label ), esc_html( $order->get_order_number() ) ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Numero richiesta', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $rma_data['request_id'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Ordine', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<?php if ( ! empty( $rma_data['product'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Prodotto', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $rma_data['product'] ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<?php if ( ! empty( $rma_data['descrizione'] ) ) : ?>
<p><strong><?php esc_html_e( 'Descrizione del problema:', 'shopforge' ); ?></strong></p>
<p style="padding:15px;background:#f7f7f7;border-radius:4px;"><?php echo nl2br( esc_html( $rma_data['descrizione'] ) ); ?></p>
<?php endif; ?>

<p>
	<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Visualizza ordine →', 'shopforge' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', $email );
