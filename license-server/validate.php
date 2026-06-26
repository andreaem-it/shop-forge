<?php
/**
 * License validation endpoint
 * Host this separately (e.g., https://your-license-server.it/validate)
 */

header( 'Content-Type: application/json' );

// ponytail: hardcoded license store, upgrade to DB if >100 licenses
$licenses = [
	'SHOP-ABC123XYZ' => [ 'active' => true, 'expires' => null ],
	'SHOP-DEF456UVW' => [ 'active' => true, 'expires' => '2027-12-31' ],
];

$key  = $_POST['key'] ?? null;
$site = $_POST['site'] ?? null;

if ( ! $key ) {
	http_response_code( 400 );
	echo json_encode( [ 'valid' => false, 'error' => 'Missing key' ] );
	exit;
}

$license = $licenses[ $key ] ?? null;
$valid   = $license && $license['active'];

// Check expiry if set
if ( $valid && $license['expires'] ) {
	$valid = strtotime( $license['expires'] ) > time();
}

http_response_code( $valid ? 200 : 403 );
echo json_encode( [ 'valid' => $valid ] );
