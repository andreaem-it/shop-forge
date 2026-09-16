<?php
/**
 * Test minimale, senza framework né bootstrap WordPress: le due funzioni
 * testate (inc/modules/shopforge-mod-loyalty.php) sono matematica pura,
 * estratta apposta per essere verificabile in isolamento.
 *
 * Esecuzione: php tests/test-loyalty-math.php
 */

function shopforge_loyalty_calc_points_earned( float $order_total, float $earn_rate ): int {
	return (int) floor( $order_total * $earn_rate );
}

function shopforge_loyalty_calc_redeem_value( int $points, float $point_value, int $decimals ): float {
	return round( $points * $point_value, $decimals );
}

// Punti guadagnati: floor(totale * tasso)
assert( shopforge_loyalty_calc_points_earned( 100.0, 1.0 ) === 100 );
assert( shopforge_loyalty_calc_points_earned( 99.99, 1.0 ) === 99 );
assert( shopforge_loyalty_calc_points_earned( 10.0, 0.5 ) === 5 );
assert( shopforge_loyalty_calc_points_earned( 0.0, 1.0 ) === 0 );

// Valore di riscatto: punti * valore_punto, arrotondato ai decimali del negozio
assert( shopforge_loyalty_calc_redeem_value( 100, 0.05, 2 ) === 5.0 );
assert( shopforge_loyalty_calc_redeem_value( 1, 0.05, 2 ) === 0.05 );
assert( shopforge_loyalty_calc_redeem_value( 0, 0.05, 2 ) === 0.0 );
assert( shopforge_loyalty_calc_redeem_value( 333, 0.013, 2 ) === 4.33 );

echo "OK: shopforge-mod-loyalty math\n";
