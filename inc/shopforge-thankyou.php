<?php
/**
 * Andrea Emili — Personalizzazione Thank You page WooCommerce
 *
 * Migliora la conferma ordine con un alert grafico
 * e un link al pannello Socio.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


add_filter( 'woocommerce_thankyou_order_received_text', function ( $text, $order ) {
	return '
		<div class="custom-order-confirmation-alert">
			<div class="custom-order-confirmation-icon">✓</div>
			<div class="custom-order-confirmation-content">
				<h2>' . esc_html__( 'Order received successfully', 'shopforge' ) . '</h2>
				<p>' . esc_html( $text ) . '</p>
			</div>
		</div>
	';
}, 10, 2 );


add_action( 'wp_head', function () {
	if ( ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	?>
	<style>
		.woocommerce-order {
			display: flex;
			flex-direction: column;
			align-items: center;
		}

		.woocommerce-notice.woocommerce-notice--success.woocommerce-thankyou-order-received {
			width: 100%;
			max-width: 760px;
			margin: 0 auto 32px auto;
			padding: 0;
			background: transparent;
			border: none;
			text-align: center;
		}

		.custom-order-confirmation-alert {
			width: 100%;
			box-sizing: border-box;
			padding: 36px 28px;
			background: #e8f8ef;
			border: 1px solid #b7e4c7;
			border-radius: 18px;
			color: #14532d;
			text-align: center;
			box-shadow: 0 12px 32px rgba(20, 83, 45, 0.12);
		}

		.custom-order-confirmation-icon {
			width: 72px;
			height: 72px;
			margin: 0 auto 18px auto;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #16a34a;
			color: #ffffff;
			border-radius: 50%;
			font-size: 42px;
			font-weight: 700;
			line-height: 1;
		}

		.custom-order-confirmation-content h2 {
			margin: 0 0 8px 0;
			color: #14532d;
			font-size: 28px;
			font-weight: 700;
			line-height: 1.2;
		}

		.custom-order-confirmation-content p {
			margin: 0;
			color: #166534;
			font-size: 17px;
			line-height: 1.5;
		}

		.woocommerce-order-overview {
			width: 100%;
			max-width: 960px;
			margin-top: 0;
		}

		@media (max-width: 768px) {
			.custom-order-confirmation-alert {
				padding: 28px 20px;
				border-radius: 14px;
			}

			.custom-order-confirmation-icon {
				width: 60px;
				height: 60px;
				font-size: 34px;
			}

			.custom-order-confirmation-content h2 {
				font-size: 23px;
			}

			.custom-order-confirmation-content p {
				font-size: 15px;
			}
		}
	</style>
	<?php
} );
