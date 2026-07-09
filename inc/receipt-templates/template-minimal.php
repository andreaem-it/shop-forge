<?php
/**
 * Receipt template — Minimal
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
	body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #000; margin: 0; padding: 36px 40px; }
	.header { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
	.header td { vertical-align: top; }
	.header img { max-height: 40px; }
	.header .company-name { font-size: 14px; font-weight: bold; }
	.header .receipt-title { text-align: right; font-size: 16px; font-weight: bold; }
	.header .receipt-meta { text-align: right; font-size: 10px; color: #555; margin-top: 3px; }
	.addresses { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
	.addresses td { vertical-align: top; width: 50%; }
	.addresses .label { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #555; margin-bottom: 3px; }
	.addresses .value { font-size: 11px; line-height: 1.5; }
	table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
	table.items th { border-bottom: 1px solid #000; text-align: left; padding: 5px 6px; font-size: 10px; }
	table.items td { padding: 5px 6px; border-bottom: 1px solid #ddd; font-size: 11px; }
	table.items .num { text-align: right; }
	table.totals { width: 240px; float: right; border-collapse: collapse; }
	table.totals td { padding: 3px 6px; font-size: 11px; }
	table.totals .num { text-align: right; }
	table.totals .grand td { border-top: 1px solid #000; font-weight: bold; padding-top: 6px; }
	.footer { clear: both; margin-top: 50px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #555; }
</style>
</head>
<body>

	<table class="header">
		<tr>
			<td>
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
				<?php else : ?>
					<span class="company-name"><?php echo esc_html( $settings['company_name'] ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<div class="receipt-title"><?php esc_html_e( 'Receipt', 'shopforge' ); ?></div>
				<div class="receipt-meta">
					<?php echo esc_html( $receipt_number ); ?><br>
					<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $receipt_date ) ) ); ?>
				</div>
			</td>
		</tr>
	</table>

	<table class="addresses">
		<tr>
			<td>
				<div class="label"><?php esc_html_e( 'From', 'shopforge' ); ?></div>
				<div class="value">
					<?php echo esc_html( $settings['company_name'] ); ?><br>
					<?php echo nl2br( esc_html( $settings['company_address'] ) ); ?><br>
					<?php if ( $settings['company_vat'] ) : ?><?php echo esc_html( $settings['company_vat'] ); ?><br><?php endif; ?>
					<?php echo esc_html( $settings['company_email'] ); ?>
				</div>
			</td>
			<td>
				<div class="label"><?php esc_html_e( 'Bill to', 'shopforge' ); ?></div>
				<div class="value"><?php echo wp_kses_post( $billing ); ?></div>
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
