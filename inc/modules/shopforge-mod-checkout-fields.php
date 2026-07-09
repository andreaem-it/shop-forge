<?php
/**
 * ShopForge — Italian Fiscal Checkout Fields
 *
 * Registra 5 campi aggiuntivi nel checkout a blocchi (WooCommerce Blocks
 * Additional Checkout Fields API): Tipo cliente, Codice Fiscale, Partita
 * IVA, Codice Destinatario (SDI), PEC. Codice Fiscale è richiesto solo per
 * i clienti "Privato", gli altri tre solo per "Azienda" — la visibilità e
 * l'obbligatorietà sono gestite nativamente dal checkout tramite le regole
 * JSON Schema condizionali dell'API, non con JS custom.
 *
 * Questa API funziona solo con il checkout a blocchi (Cart & Checkout
 * blocks). Il checkout classico a shortcode non è coperto: i clienti che
 * usano [woocommerce_checkout] devono affidarsi a un plugin dedicato
 * (es. Checkout Field Editor) per lo stesso risultato lì.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'shopforge_checkout_fields_register' );

function shopforge_checkout_fields_register(): void {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	$is_business = [
		'customer' => [
			'properties' => [
				'additional_fields' => [
					'properties' => [
						'shopforge/customer-type' => [ 'const' => 'business' ],
					],
				],
			],
		],
	];

	$is_not_business = [
		'customer' => [
			'properties' => [
				'additional_fields' => [
					'properties' => [
						'shopforge/customer-type' => [ 'not' => [ 'const' => 'business' ] ],
					],
				],
			],
		],
	];

	$is_private = [
		'customer' => [
			'properties' => [
				'additional_fields' => [
					'properties' => [
						'shopforge/customer-type' => [ 'const' => 'private' ],
					],
				],
			],
		],
	];

	$is_not_private = [
		'customer' => [
			'properties' => [
				'additional_fields' => [
					'properties' => [
						'shopforge/customer-type' => [ 'not' => [ 'const' => 'private' ] ],
					],
				],
			],
		],
	];

	woocommerce_register_additional_checkout_field(
		[
			'id'       => 'shopforge/customer-type',
			'label'    => __( 'Customer type', 'shopforge' ),
			'location' => 'contact',
			'type'     => 'select',
			'required' => true,
			'options'  => [
				[ 'value' => 'private', 'label' => __( 'Private', 'shopforge' ) ],
				[ 'value' => 'business', 'label' => __( 'Business', 'shopforge' ) ],
			],
		]
	);

	woocommerce_register_additional_checkout_field(
		[
			'id'                => 'shopforge/codice-fiscale',
			'label'             => __( 'Tax code (Codice Fiscale)', 'shopforge' ),
			'location'          => 'contact',
			'type'              => 'text',
			'required'          => $is_private,
			'hidden'            => $is_not_private,
			'sanitize_callback' => 'shopforge_checkout_field_sanitize_uppercase',
			'validate_callback' => 'shopforge_checkout_field_validate_codice_fiscale',
		]
	);

	woocommerce_register_additional_checkout_field(
		[
			'id'                => 'shopforge/vat-number',
			'label'             => __( 'VAT number (Partita IVA)', 'shopforge' ),
			'location'          => 'contact',
			'type'              => 'text',
			'required'          => $is_business,
			'hidden'            => $is_not_business,
			'sanitize_callback' => 'shopforge_checkout_field_sanitize_digits',
			'validate_callback' => 'shopforge_checkout_field_validate_vat_number',
		]
	);

	woocommerce_register_additional_checkout_field(
		[
			'id'                => 'shopforge/receiver-code',
			'label'             => __( 'SDI code (Codice Destinatario)', 'shopforge' ),
			'location'          => 'contact',
			'type'              => 'text',
			'required'          => $is_business,
			'hidden'            => $is_not_business,
			'sanitize_callback' => 'shopforge_checkout_field_sanitize_uppercase',
			'validate_callback' => 'shopforge_checkout_field_validate_receiver_code',
		]
	);

	woocommerce_register_additional_checkout_field(
		[
			'id'                => 'shopforge/pec',
			'label'             => __( 'PEC address', 'shopforge' ),
			'location'          => 'contact',
			'type'              => 'text',
			'required'          => $is_business,
			'hidden'            => $is_not_business,
			'validate_callback' => 'shopforge_checkout_field_validate_pec',
		]
	);
}

function shopforge_checkout_field_sanitize_uppercase( $value ) {
	return strtoupper( sanitize_text_field( (string) $value ) );
}

function shopforge_checkout_field_sanitize_digits( $value ) {
	return preg_replace( '/\D+/', '', sanitize_text_field( (string) $value ) );
}

function shopforge_checkout_field_validate_codice_fiscale( $value ) {
	if ( '' === $value ) {
		return true;
	}
	if ( ! preg_match( '/^[A-Z0-9]{11,16}$/', $value ) ) {
		return new WP_Error( 'shopforge_invalid_codice_fiscale', __( 'Enter a valid tax code (Codice Fiscale).', 'shopforge' ) );
	}
	return true;
}

function shopforge_checkout_field_validate_vat_number( $value ) {
	if ( '' === $value ) {
		return true;
	}
	if ( ! preg_match( '/^\d{11}$/', $value ) ) {
		return new WP_Error( 'shopforge_invalid_vat_number', __( 'Enter a valid 11-digit VAT number (Partita IVA).', 'shopforge' ) );
	}
	return true;
}

function shopforge_checkout_field_validate_receiver_code( $value ) {
	if ( '' === $value ) {
		return true;
	}
	if ( ! preg_match( '/^[A-Z0-9]{6,7}$/', $value ) ) {
		return new WP_Error( 'shopforge_invalid_receiver_code', __( 'Enter a valid 7-character SDI code (Codice Destinatario), or leave it as zeros if you provided a PEC address.', 'shopforge' ) );
	}
	return true;
}

function shopforge_checkout_field_validate_pec( $value ) {
	if ( '' === $value ) {
		return true;
	}
	if ( ! is_email( $value ) ) {
		return new WP_Error( 'shopforge_invalid_pec', __( 'Enter a valid PEC email address.', 'shopforge' ) );
	}
	return true;
}
