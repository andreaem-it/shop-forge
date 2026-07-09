<?php
/**
 * Receipt template — Classic
 *
 * Variabili disponibili (vedi shopforge_receipt_render_html()):
 * $order, $settings, $receipt_number, $receipt_date, $logo_url, $billing,
 * $line_items, $totals.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
	body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 40px 42px; }
	.letterhead { text-align: center; margin-bottom: 24px; }
	.letterhead img { max-height: 50px; margin-bottom: 10px; }
	.letterhead .company-name { font-size: 17px; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
	.letterhead .company-address { font-size: 10px; color: #444; margin-top: 4px; }
	.rule { border: 0; border-top: 2px solid #1a1a1a; margin: 18px 0; }
	.receipt-meta-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
	.receipt-meta-table td { padding: 3px 0; font-size: 11px; }
	.receipt-meta-table .label { width: 120px; color: #444; }
	.receipt-title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; margin: 18px 0; }
	.addresses { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
	.addresses td { vertical-align: top; width: 50%; padding-top: 8px; border-top: 1px solid #ccc; }
	.addresses .label { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #444; margin-bottom: 4px; }
	.addresses .value { font-size: 11px; line-height: 1.6; }
	table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
	table.items th {
		border-top: 2px solid #1a1a1a; border-bottom: 1px solid #1a1a1a;
		text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .04em;
	}
	table.items td { padding: 7px 8px; border-bottom: 1px solid #ddd; font-size: 11px; }
	table.items .num { text-align: right; }
	table.totals { width: 260px; float: right; border-collapse: collapse; }
	table.totals td { padding: 4px 8px; font-size: 11px; }
	table.totals .num { text-align: right; }
	table.totals .grand td { border-top: 2px solid #1a1a1a; font-weight: bold; font-size: 13px; padding-top: 8px; }
	.footer { clear: both; margin-top: 60px; padding-top: 14px; border-top: 1px solid #ccc; font-size: 9px; color: #444; text-align: center; }
</style>
</head>
<body>

	<div class="letterhead">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
		<?php endif; ?>
		<div class="company-name"><?php echo esc_html( $settings['company_name'] ); ?></div>
		<div class="company-address">
			<?php echo nl2br( esc_html( $settings['company_address'] ) ); ?>
			<?php if ( $settings['company_vat'] ) : ?> &middot; <?php echo esc_html( $settings['company_vat'] ); ?><?php endif; ?>
		</div>
	</div>

	<hr class="rule">

	<div class="receipt-title"><?php esc_html_e( 'Receipt', 'shopforge' ); ?></div>

	<table class="receipt-meta-table">
		<tr>
			<td class="label"><?php esc_html_e( 'Receipt number', 'shopforge' ); ?></td>
			<td><?php echo esc_html( $receipt_number ); ?></td>
		</tr>
		<tr>
			<td class="label"><?php esc_html_e( 'Date', 'shopforge' ); ?></td>
			<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $receipt_date ) ) ); ?></td>
		</tr>
		<tr>
			<td class="label"><?php esc_html_e( 'Order', 'shopforge' ); ?></td>
			<td>#<?php echo esc_html( $order->get_order_number() ); ?></td>
		</tr>
	</table>

	<table class="addresses">
		<tr>
			<td>
				<div class="label"><?php esc_html_e( 'Bill to', 'shopforge' ); ?></div>
				<div class="value"><?php echo wp_kses_post( $billing ); ?></div>
			</td>
			<td>
				<div class="label"><?php esc_html_e( 'Contact', 'shopforge' ); ?></div>
				<div class="value"><?php echo esc_html( $settings['company_email'] ); ?></div>
			</td>
		</tr>
	</table>

	<table class="items">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Item', 'shopforge' ); ?></th>
				<th class="num"><?php esc_html_e( 'Qty', 'shopforge' ); ?></th>
				<th class="num"><?php esc_html_e( 'Unit price', 'shopforge' ); ?></th>
				<th class="num"><?php esc_html_e( 'Total', 'shopforge' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $line_items as $item ) : ?>
			<tr>
				<td><?php echo esc_html( $item['name'] ); ?></td>
				<td class="num"><?php echo esc_html( $item['qty'] ); ?></td>
				<td class="num"><?php echo wp_kses_post( $item['unit'] ); ?></td>
				<td class="num"><?php echo wp_kses_post( $item['total'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="totals">
		<tr><td><?php esc_html_e( 'Subtotal', 'shopforge' ); ?></td><td class="num"><?php echo wp_kses_post( $totals['subtotal'] ); ?></td></tr>
		<?php if ( $totals['discount'] ) : ?>
		<tr><td><?php esc_html_e( 'Discount', 'shopforge' ); ?></td><td class="num">-<?php echo wp_kses_post( $totals['discount'] ); ?></td></tr>
		<?php endif; ?>
		<?php if ( $totals['shipping'] ) : ?>
		<tr><td><?php esc_html_e( 'Shipping', 'shopforge' ); ?></td><td class="num"><?php echo wp_kses_post( $totals['shipping'] ); ?></td></tr>
		<?php endif; ?>
		<?php if ( $totals['tax'] ) : ?>
		<tr><td><?php esc_html_e( 'Tax', 'shopforge' ); ?></td><td class="num"><?php echo wp_kses_post( $totals['tax'] ); ?></td></tr>
		<?php endif; ?>
		<tr class="grand"><td><?php esc_html_e( 'Total', 'shopforge' ); ?></td><td class="num"><?php echo wp_kses_post( $totals['total'] ); ?></td></tr>
	</table>

	<?php if ( $settings['footer_note'] ) : ?>
	<div class="footer"><?php echo nl2br( esc_html( $settings['footer_note'] ) ); ?></div>
	<?php endif; ?>

</body>
</html>
