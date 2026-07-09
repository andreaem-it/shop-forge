<?php
/**
 * Modulo: Loyalty Points
 *
 * I clienti accumulano punti sugli ordini completati (guadagno proporzionale
 * alla spesa, configurabile) e possono convertirli in un coupon sconto
 * monouso dall'area account. Se un ordine già "completato" viene poi
 * rimborsato o annullato, i punti guadagnati vengono stornati.
 *
 * Meta utente:
 *  _shopforge_loyalty_points  → int, saldo corrente
 *  _shopforge_loyalty_history → array di voci { type, points, date, ref }
 *                               type: earn | reverse | redeem
 *
 * Meta ordine:
 *  _shopforge_loyalty_awarded → punti già accreditati per questo ordine
 *                                (guardia anti doppio accredito/storno)
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// CONFIGURAZIONE (gestita in ShopForge → Moduli → Configurazione)
// =============================================================================

/** Punti guadagnati per ogni unità di valuta spesa. Default: 1 punto per €1. */
function shopforge_loyalty_get_earn_rate(): float {
	return (float) apply_filters( 'shopforge_loyalty_earn_rate', (float) get_option( 'shopforge_loyalty_earn_rate', 1 ) );
}

/** Valore in valuta di 1 punto al momento del riscatto. Default: €0,05/punto (100 punti = €5). */
function shopforge_loyalty_get_point_value(): float {
	return (float) apply_filters( 'shopforge_loyalty_point_value', (float) get_option( 'shopforge_loyalty_point_value', 0.05 ) );
}

/** Punti minimi richiesti per poter riscattare. */
function shopforge_loyalty_get_min_redeem(): int {
	return max( 1, (int) apply_filters( 'shopforge_loyalty_min_redeem', (int) get_option( 'shopforge_loyalty_min_redeem', 100 ) ) );
}

function shopforge_loyalty_get_balance( int $user_id ): int {
	return (int) get_user_meta( $user_id, '_shopforge_loyalty_points', true );
}

function shopforge_loyalty_get_history( int $user_id ): array {
	$history = get_user_meta( $user_id, '_shopforge_loyalty_history', true );
	return is_array( $history ) ? $history : [];
}

/**
 * Aggiunge/sottrae punti al saldo utente e registra la voce nello storico.
 * $points può essere negativo (storno, riscatto).
 */
function shopforge_loyalty_add_points( int $user_id, int $points, string $type, string $ref = '' ): void {
	if ( ! $user_id || ! $points ) return;

	$balance = shopforge_loyalty_get_balance( $user_id ) + $points;
	update_user_meta( $user_id, '_shopforge_loyalty_points', max( 0, $balance ) );

	$history   = shopforge_loyalty_get_history( $user_id );
	$history[] = [
		'type'   => $type,
		'points' => $points,
		'date'   => current_time( 'mysql' ),
		'ref'    => $ref,
	];
	// Mantieni solo le ultime 100 voci
	if ( count( $history ) > 100 ) {
		$history = array_slice( $history, -100 );
	}
	update_user_meta( $user_id, '_shopforge_loyalty_history', $history );
}


// =============================================================================
// ACCREDITO — ordine completato
// =============================================================================

add_action( 'woocommerce_order_status_completed', function ( int $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	// Guardia anti doppio accredito (un ordine può ripassare per "completed" più volte)
	if ( $order->get_meta( '_shopforge_loyalty_awarded' ) ) return;

	$user_id = (int) $order->get_customer_id();
	if ( ! $user_id ) return;

	$points = (int) floor( (float) $order->get_total() * shopforge_loyalty_get_earn_rate() );
	if ( $points <= 0 ) return;

	$order->update_meta_data( '_shopforge_loyalty_awarded', $points );
	$order->save();

	/* translators: %s: order number */
	shopforge_loyalty_add_points( $user_id, $points, 'earn', sprintf( __( 'Order #%s', 'shopforge' ), $order->get_order_number() ) );

	do_action( 'shopforge_notification', $user_id, 'loyalty_earned', [
		/* translators: 1: points earned, 2: order number */
		'text' => sprintf( __( 'You earned %1$d points on order #%2$s', 'shopforge' ), $points, $order->get_order_number() ),
		'url'  => wc_get_account_endpoint_url( 'shopforge-loyalty' ),
	] );
}, 10, 1 );


// =============================================================================
// STORNO — ordine completato poi rimborsato/annullato
// =============================================================================

add_action( 'woocommerce_order_status_changed', function ( int $order_id, string $old_status, string $new_status ) {
	if ( ! in_array( $new_status, [ 'refunded', 'cancelled' ], true ) ) return;

	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	$awarded = (int) $order->get_meta( '_shopforge_loyalty_awarded' );
	if ( $awarded <= 0 ) return; // non aveva ricevuto punti, niente da stornare

	$user_id = (int) $order->get_customer_id();
	if ( ! $user_id ) return;

	$order->update_meta_data( '_shopforge_loyalty_awarded', 0 );
	$order->save();

	/* translators: %s: order number */
	shopforge_loyalty_add_points( $user_id, -$awarded, 'reverse', sprintf( __( 'Order #%s', 'shopforge' ), $order->get_order_number() ) );
}, 10, 3 );


// =============================================================================
// RISCATTO — genera coupon sconto monouso
// =============================================================================

/** @return string|WP_Error Codice coupon o errore. */
function shopforge_loyalty_issue_coupon( int $user_id, int $points ) {
	$amount = round( $points * shopforge_loyalty_get_point_value(), wc_get_price_decimals() );
	if ( $amount <= 0 ) {
		return new WP_Error( 'invalid_amount', __( 'Could not determine a discount amount for this coupon.', 'shopforge' ) );
	}

	$user  = get_userdata( $user_id );
	$email = $user ? $user->user_email : '';
	$code  = 'LOYALTY-' . strtoupper( substr( wp_generate_password( 10, false, false ), 0, 8 ) );

	$coupon = new WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'fixed_cart' );
	$coupon->set_amount( $amount );
	$coupon->set_usage_limit( 1 );
	if ( $email ) {
		$coupon->set_email_restrictions( [ $email ] );
	}
	/* translators: %d: points redeemed */
	$coupon->set_description( sprintf( __( 'Loyalty points redemption — %d points', 'shopforge' ), $points ) );
	$coupon_id = $coupon->save();

	if ( ! $coupon_id ) {
		return new WP_Error( 'coupon_creation_failed', __( 'Coupon creation failed.', 'shopforge' ) );
	}

	return $code;
}

add_action( 'wp_ajax_shopforge_loyalty_redeem', function () {
	$user_id = get_current_user_id();
	if ( ! $user_id ) wp_send_json_error( __( 'Unauthorized access.', 'shopforge' ) );

	check_ajax_referer( 'shopforge_loyalty_' . $user_id, 'nonce' );

	if ( function_exists( 'shopforge_check_rate_limit' )
	     && ! shopforge_check_rate_limit( 'loyalty_redeem', 30 ) ) {
		wp_send_json_error( __( 'Please wait a moment before trying again.', 'shopforge' ) );
	}

	$points  = absint( $_POST['points'] ?? 0 );
	$balance = shopforge_loyalty_get_balance( $user_id );
	$min     = shopforge_loyalty_get_min_redeem();

	if ( $points < $min ) {
		/* translators: %d: minimum points required */
		wp_send_json_error( sprintf( __( 'You need at least %d points to redeem.', 'shopforge' ), $min ) );
	}
	if ( $points > $balance ) {
		wp_send_json_error( __( 'You do not have enough points.', 'shopforge' ) );
	}

	$coupon_code = shopforge_loyalty_issue_coupon( $user_id, $points );
	if ( is_wp_error( $coupon_code ) ) {
		wp_send_json_error( $coupon_code->get_error_message() );
	}

	/* translators: %s: coupon code */
	shopforge_loyalty_add_points( $user_id, -$points, 'redeem', sprintf( __( 'Coupon %s', 'shopforge' ), $coupon_code ) );

	do_action( 'shopforge_notification', $user_id, 'loyalty_redeemed', [
		/* translators: %s: coupon code */
		'text' => sprintf( __( 'Your loyalty coupon %s is ready to use', 'shopforge' ), $coupon_code ),
		'url'  => wc_get_account_endpoint_url( 'shopforge-loyalty' ),
	] );

	wp_send_json_success( [
		'coupon_code' => $coupon_code,
		'balance'     => shopforge_loyalty_get_balance( $user_id ),
	] );
} );


// =============================================================================
// ENDPOINT — Contenuto pagina "Loyalty Points" nell'account
// =============================================================================

add_action( 'woocommerce_account_shopforge-loyalty_endpoint', function () {
	$user_id  = get_current_user_id();
	$balance  = shopforge_loyalty_get_balance( $user_id );
	$history  = array_reverse( shopforge_loyalty_get_history( $user_id ) );
	$min      = shopforge_loyalty_get_min_redeem();
	$value    = shopforge_loyalty_get_point_value();
	$nonce    = wp_create_nonce( 'shopforge_loyalty_' . $user_id );

	shopforge_account_section_header(
		__( 'Loyalty Points', 'shopforge' ),
		'fa-solid fa-star',
		/* translators: %d: points balance */
		sprintf( __( '%d points available', 'shopforge' ), $balance )
	);

	$type_labels = [
		'earn'    => [ 'label' => __( 'Earned', 'shopforge' ),   'class' => 'earn' ],
		'reverse' => [ 'label' => __( 'Reversed', 'shopforge' ), 'class' => 'reverse' ],
		'redeem'  => [ 'label' => __( 'Redeemed', 'shopforge' ), 'class' => 'redeem' ],
	];
	?>

	<div class="shopforge-loyalty-balance-card">
		<div class="shopforge-loyalty-balance-card__points">
			<span class="shopforge-loyalty-balance-card__value"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
			<span class="shopforge-loyalty-balance-card__label"><?php esc_html_e( 'points', 'shopforge' ); ?></span>
		</div>
		<p class="shopforge-loyalty-balance-card__worth">
			<?php
			/* translators: %s: monetary value of the current points balance */
			printf( esc_html__( 'Worth %s in discount coupons', 'shopforge' ), wp_kses_post( wc_price( $balance * $value ) ) );
			?>
		</p>

		<?php if ( $balance >= $min ) : ?>
		<form class="shopforge-loyalty-redeem-form" id="shopforge-loyalty-redeem-form">
			<div class="shopforge-field">
				<label for="shopforge-loyalty-points">
					<?php
					/* translators: 1: minimum points, 2: current balance */
					printf( esc_html__( 'Points to redeem (min. %1$d, max. %2$d)', 'shopforge' ), (int) $min, (int) $balance );
					?>
				</label>
				<input type="number" id="shopforge-loyalty-points" name="points" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $balance ); ?>" step="1" value="<?php echo esc_attr( $min ); ?>">
			</div>
			<p class="shopforge-loyalty-error" id="shopforge-loyalty-error" style="display:none"></p>
			<button type="submit" class="shopforge-btn shopforge-btn--primary" id="shopforge-loyalty-submit">
				<span id="shopforge-loyalty-label"><?php esc_html_e( 'Redeem for a coupon', 'shopforge' ); ?></span>
				<span class="shopforge-st-spinner" id="shopforge-loyalty-spinner" style="display:none"></span>
			</button>
			<div id="shopforge-loyalty-success" style="display:none" class="shopforge-loyalty-success">
				<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
				<span id="shopforge-loyalty-success-text"></span>
			</div>
		</form>
		<?php else : ?>
		<p class="shopforge-loyalty-note">
			<?php
			/* translators: %d: minimum points required to redeem */
			printf( esc_html__( 'You need at least %d points to redeem a coupon.', 'shopforge' ), (int) $min );
			?>
		</p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $history ) ) : ?>
	<div class="shopforge-loyalty-history">
		<h3 class="shopforge-loyalty-history__title">
			<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
			<?php esc_html_e( 'History', 'shopforge' ); ?>
		</h3>
		<div class="shopforge-loyalty-history-list">
			<?php foreach ( $history as $entry ) :
				$t    = $type_labels[ $entry['type'] ] ?? $type_labels['earn'];
				$date = date_i18n( get_option( 'date_format' ), strtotime( $entry['date'] ) );
			?>
			<div class="shopforge-loyalty-history-row">
				<span class="shopforge-badge shopforge-loyalty-badge--<?php echo esc_attr( $t['class'] ); ?>"><?php echo esc_html( $t['label'] ); ?></span>
				<span class="shopforge-loyalty-history-row__ref"><?php echo esc_html( $entry['ref'] ); ?></span>
				<span class="shopforge-loyalty-history-row__points<?php echo $entry['points'] < 0 ? ' is-negative' : ''; ?>">
					<?php echo esc_html( ( $entry['points'] > 0 ? '+' : '' ) . number_format_i18n( $entry['points'] ) ); ?>
				</span>
				<span class="shopforge-loyalty-history-row__date"><?php echo esc_html( $date ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php else : ?>
		<?php shopforge_account_empty_state(
			'fa-solid fa-star',
			__( 'No activity yet', 'shopforge' ),
			__( 'Complete an order to start earning loyalty points.', 'shopforge' )
		); ?>
	<?php endif; ?>

	<script>
	(function () {
		'use strict';
		var form   = document.getElementById('shopforge-loyalty-redeem-form');
		if ( ! form ) return;

		var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var nonce   = '<?php echo esc_js( $nonce ); ?>';

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var err     = document.getElementById('shopforge-loyalty-error');
			var label   = document.getElementById('shopforge-loyalty-label');
			var spinner = document.getElementById('shopforge-loyalty-spinner');
			var submit  = document.getElementById('shopforge-loyalty-submit');
			var success = document.getElementById('shopforge-loyalty-success');
			var points  = document.getElementById('shopforge-loyalty-points').value;

			err.style.display = 'none';
			spinner.style.display = 'inline-block';
			submit.disabled = true;

			fetch(ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'shopforge_loyalty_redeem', nonce: nonce, points: points })
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				spinner.style.display = 'none';
				submit.disabled = false;
				if (data.success) {
					document.getElementById('shopforge-loyalty-success-text').textContent = data.data.coupon_code;
					success.style.display = 'flex';
					submit.style.display = 'none';
					setTimeout(function () { location.reload(); }, 1800);
				} else {
					err.textContent = data.data || '<?php echo esc_js( __( 'Error. Try again.', 'shopforge' ) ); ?>';
					err.style.display = 'block';
				}
			})
			.catch(function () {
				spinner.style.display = 'none';
				submit.disabled = false;
				err.textContent = '<?php echo esc_js( __( 'Error. Try again.', 'shopforge' ) ); ?>';
				err.style.display = 'block';
			});
		});
	})();
	</script>
	<?php
} );


// =============================================================================
// CSS
// =============================================================================

add_action( 'wp_enqueue_scripts', function () {
	if ( false === get_query_var( 'shopforge-loyalty', false ) ) return;
	wp_enqueue_style( 'shopforge-loyalty', SHOPFORGE_URL . 'assets/css/shopforge-loyalty.css', [], SHOPFORGE_VERSION );
} );
