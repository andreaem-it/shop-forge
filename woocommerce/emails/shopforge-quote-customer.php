<?php
/**
 * Template email HTML — Conferma preventivo al cliente
 * @var int    $user_id
 * @var array  $quote_data  { ref, date, items[], notes }
 * @var string $email_heading
 * @var WC_Email $email
 * @package ShopForge
 */
defined( 'ABSPATH' ) || exit;
$user = get_userdata( $user_id );
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Dear %s,', 'shopforge' ), esc_html( $user ? $user->display_name : '' ) ); ?></p>
<p><?php esc_html_e( 'We received your quote request. We will reply as soon as possible with a personalized offer.', 'shopforge' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e0e0e0;border-collapse:collapse;margin-bottom:20px;">
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;width:35%;"><?php esc_html_e( 'Reference', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><strong><?php echo esc_html( $quote_data['ref'] ?? '' ); ?></strong></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo esc_html( $quote_data['date'] ?? '' ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Requested products', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;">
			<?php foreach ( (array) ( $quote_data['items'] ?? [] ) as $item ) : ?>
			• <?php echo esc_html( $item['qty'] ?? 1 ); ?>× <?php echo esc_html( $item['name'] ?? '' ); ?><br>
			<?php endforeach; ?>
		</td>
	</tr>
	<?php if ( ! empty( $quote_data['notes'] ) ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;background:#f8f8f8;"><?php esc_html_e( 'Notes', 'shopforge' ); ?></th>
		<td style="text-align:left;border:1px solid #e0e0e0;padding:8px 12px;"><?php echo nl2br( esc_html( $quote_data['notes'] ) ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<p>
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'shopforge-quotes' ) ); ?>"
	   style="display:inline-block;background:#96588a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'My quotes →', 'shopforge' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', $email );
