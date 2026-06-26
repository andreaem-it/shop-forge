<?php
/**
 * Template email HTML — Notifica admin: nuova richiesta di recesso
 * Sovrascrivibile dal tema: woocommerce/emails/shopforge-return-admin.php
 *
 * @var WC_Order $order
 * @var array    $return_data  { ref, products[], reason, refund, notes, declaration, date_str }
 * @var string   $email_heading
 * @var WC_Email $email
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Hai ricevuto una nuova richiesta di recesso per l\'ordine #%s.', 'shopforge' ), esc_html( $order->get_order_number() ) ); ?></p>

<h2 style="color:#96588a;font-size:18px;font-weight:bold;line-height:150%;margin:0 0 16px;">
	<?php esc_html_e( 'Dettagli della richiesta', 'shopforge' ); ?>
</h2>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:30%;"><?php esc_html_e( 'Riferimento', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $return_data['ref'] ?? '' ); ?></strong></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Data trasmissione', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $return_data['date_str'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Cliente', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">
			<?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>
			— <a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a>
		</td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Ordine', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Prodotti', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">
			<?php foreach ( (array) ( $return_data['products'] ?? [] ) as $p ) : ?>
			• <?php echo esc_html( $p ); ?><br>
			<?php endforeach; ?>
		</td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Motivo', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $return_data['reason'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Rimborso preferito', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $return_data['refund'] ?? '' ); ?></td>
	</tr>
	<?php if ( ! empty( $return_data['notes'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Note', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo nl2br( esc_html( $return_data['notes'] ) ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<h2 style="color:#96588a;font-size:18px;font-weight:bold;line-height:150%;margin:0 0 8px;">
	<?php esc_html_e( 'Dichiarazione di recesso', 'shopforge' ); ?>
</h2>
<div style="background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;padding:14px 18px;font-family:monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;margin-bottom:20px;"><?php echo esc_html( $return_data['declaration'] ?? '' ); ?></div>

<p style="margin-bottom:20px;">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&id=' . $order->get_id() ) ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Gestisci l\'ordine →', 'shopforge' ); ?>
	</a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
