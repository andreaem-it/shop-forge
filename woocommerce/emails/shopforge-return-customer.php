<?php
/**
 * Template email HTML — Ricevuta di recesso al cliente
 * Obbligatoria ai sensi dell'art. 54-bis D.Lgs. 209/2025.
 * Sovrascrivibile dal tema: woocommerce/emails/shopforge-return-customer.php
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
 * @hooked WC_Emails::email_header()
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php printf(
		esc_html__( 'Dear %s,', 'shopforge' ),
		esc_html( $order->get_billing_first_name() )
	); ?>
</p>
<p>
	<?php esc_html_e( 'We received your withdrawal declaration. Below is the receipt with all the details of your request.', 'shopforge' ); ?>
</p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Reference', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $return_data['ref'] ?? '' ); ?></strong></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Date and time of transmission', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $return_data['date_str'] ?? '' ); ?></strong></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Products', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">
			<?php foreach ( (array) ( $return_data['products'] ?? [] ) as $p ) : ?>
			• <?php echo esc_html( $p ); ?><br>
			<?php endforeach; ?>
		</td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Reason', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $return_data['reason'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Preferred refund', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $return_data['refund'] ?? '' ); ?></td>
	</tr>
</table>

<h2 style="color:#96588a;font-size:18px;font-weight:bold;line-height:150%;margin:0 0 8px;">
	<?php esc_html_e( 'Withdrawal declaration', 'shopforge' ); ?>
</h2>
<div style="background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;padding:14px 18px;font-family:monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;margin-bottom:20px;"><?php echo esc_html( $return_data['declaration'] ?? '' ); ?></div>

<p style="color:#555;font-size:13px;line-height:1.5;margin-bottom:20px;">
	<?php esc_html_e( 'We will process your request and contact you within the legally required timeframe. Keep this email as proof of the transmission date.', 'shopforge' ); ?>
</p>

<p style="margin-bottom:20px;">
	<a href="<?php echo esc_url( wc_get_endpoint_url( 'shopforge-returns', '', wc_get_page_permalink( 'myaccount' ) ) ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'View your returns →', 'shopforge' ); ?>
	</a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer()
 */
do_action( 'woocommerce_email_footer', $email );
