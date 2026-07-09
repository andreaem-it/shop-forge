<?php
/**
 * Receipt template — Modern
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
	body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; }
	.header { background: #006FEF; color: #fff; padding: 28px 36px; }
	.header table { width: 100%; border-collapse: collapse; }
	.header .logo img { max-height: 46px; }
	.header .company-name { font-size: 18px; font-weight: bold; }
	.header .receipt-title { font-size: 24px; font-weight: bold; text-align: right; }
	.header .receipt-meta { font-size: 11px; text-align: right; color: #DCEBFF; margin-top: 4px; }
	.body { padding: 28px 36px; }
	.addresses { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
	.addresses td { vertical-align: top; width: 50%; }
	.addresses .label { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #64748B; margin-bottom: 4px; }
	.addresses .value { font-size: 11px; line-height: 1.6; }
	table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
	table.items th {
		background: #F0F7FF; color: #006FEF; font-size: 9px; text-transform: uppercase;
		letter-spacing: .04em; text-align: left; padding: 8px 10px; border-bottom: 2px solid #006FEF;
	}
	table.items td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; }
	table.items .num { text-align: right; }
	table.totals { width: 260px; float: right; border-collapse: collapse; }
	table.totals td { padding: 5px 10px; font-size: 11px; }
	table.totals .num { text-align: right; }
	table.totals .grand td { border-top: 2px solid #006FEF; font-weight: bold; font-size: 13px; color: #006FEF; padding-top: 8px; }
	.footer { clear: both; margin-top: 60px; padding-top: 14px; border-top: 1px solid #E2E8F0; font-size: 9px; color: #64748B; }
</style>
</head>
<body>

	<div class="header">
		<table>
			<tr>
				<td class="logo">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
					<?php else : ?>
						<span class="company-name"><?php echo esc_html( $settings['company_name'] ); ?></span>
					<?php endif; ?>
				</td>
				<td class="receipt-title">
					<?php esc_html_e( 'RECEIPT', 'shopforge' ); ?>
					<div class="receipt-meta">
						<?php echo esc_html( $receipt_number ); ?><br>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $receipt_date ) ) ); ?>
					</div>
				</td>
			</tr>
		</table>
	</div>

	<div class="body">

		<table class="addresses">
			<tr>
				<td>
					<div class="label"><?php esc_html_e( 'From', 'shopforge' ); ?></div>
					<div class="value">
						<strong><?php echo esc_html( $settings['company_name'] ); ?></strong><br>
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

	</div>
</body>
</html>
