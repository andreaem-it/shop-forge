<?php
/**
 * Template email HTML — Aggiornamento stato/risposta richiesta RMA al cliente
 * Sovrascrivibile dal tema: woocommerce/emails/shopforge-rma-status-update.php
 *
 * @var WC_Order $order
 * @var array    $rma_data { request_id, tipo, product, status, reply }
 * @var string   $email_heading
 * @var WC_Email $email
 * @package ShopForge
 */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_email_header', $email_heading, $email );

$status_label = function_exists( 'shopforge_rma_get_status_label' ) ? shopforge_rma_get_status_label( $rma_data['status'] ?? 'aperta' ) : ( $rma_data['status'] ?? '' );
?>

<p><?php printf( esc_html__( 'Gentile %s,', 'shopforge' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php esc_html_e( 'Ti scriviamo per informarti di un aggiornamento sulla tua richiesta di assistenza prodotti.', 'shopforge' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Ordine', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<?php if ( ! empty( $rma_data['product'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Prodotto', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $rma_data['product'] ); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Stato attuale', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $status_label ); ?></strong></td>
	</tr>
	<?php if ( ! empty( $rma_data['reply'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Messaggio dal negozio', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo nl2br( esc_html( $rma_data['reply'] ) ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<p>
	<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Visualizza ordine →', 'shopforge' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', $email );
