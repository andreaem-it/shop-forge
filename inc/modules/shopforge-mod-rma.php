<?php
/**
 * Modulo: Assistenza Prodotti (RMA)
 *
 * Portato da woo-ordini-e-resi (wc-resi-assistenza): richieste strutturate
 * di riparazione/sostituzione/rimborso per difetti o garanzia, distinte dal
 * recesso legale (modulo 'returns') e dai ticket generici
 * (inc/shopforge-order-tracker.php).
 *
 * Modello dati: CPT `shopforge_rma_request` (non order-meta come il resto
 * del plugin) — deviazione motivata: qui servono liste filtrabili/ordinabili
 * in admin, ricerca full-text, export CSV, statistiche, "le mie richieste"
 * cross-ordine e conteggio giornaliero anti-abuso, cose che l'order-meta non
 * regge senza scansionare tutti gli ordini ad ogni richiesta.
 *
 * Meta richiesta (_shopforge_rma_*): user_id, order_id, product_id,
 * quantita, tipo_richiesta (assistenza|reso), stato, motivo,
 * descrizione_problema, rimedio_scelto, accetto_termini/privacy/procedura,
 * allegati, data_creazione, status_history, messages, assigned_to,
 * refund_id, tracking_corriere, tracking_numero.
 *
 * Meta prodotto: _shopforge_rma_escludi, _shopforge_rma_return_period_days.
 * Meta utente: _shopforge_rma_last_read_{request_id}.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// Schermate admin (menu, lista, metabox dettaglio/conversazione, AJAX stato/rimborso,
// stampa, export CSV, statistiche) — caricate solo in wp-admin.
if ( is_admin() ) {
	require_once __DIR__ . '/shopforge-mod-rma-admin.php';
	require_once __DIR__ . '/shopforge-mod-rma-print.php';
	require_once __DIR__ . '/shopforge-mod-rma-export.php';
	require_once __DIR__ . '/shopforge-mod-rma-stats.php';
}


// =============================================================================
// CPT
// =============================================================================

add_action( 'init', function () {
	register_post_type( 'shopforge_rma_request', [
		'label'               => __( 'RMA Request', 'shopforge' ),
		'labels'              => [
			'name'          => __( 'RMA Requests', 'shopforge' ),
			'singular_name' => __( 'RMA Request', 'shopforge' ),
			'menu_name'     => __( 'Product Support', 'shopforge' ),
			'all_items'     => __( 'All Requests', 'shopforge' ),
			'search_items'  => __( 'Search Requests', 'shopforge' ),
			'not_found'     => __( 'Not found', 'shopforge' ),
		],
		'supports'            => [ 'title' ],
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false, // Menu custom, vedi inc/admin/shopforge-rma-admin.php
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'post',
		'capabilities'        => [ 'create_posts' => 'do_not_allow' ], // creabile solo via shopforge_rma_create_request()
		'map_meta_cap'        => true,
	] );
} );


// =============================================================================
// VOCABOLARI / OPZIONI (filtrabili)
// =============================================================================

function shopforge_rma_get_statuses(): array {
	return apply_filters( 'shopforge_rma_statuses', [
		'aperta'            => __( 'Open', 'shopforge' ),
		'in_lavorazione'    => __( 'Processing', 'shopforge' ),
		'approvata'         => __( 'Approved', 'shopforge' ),
		'attesa_spedizione' => __( 'Awaiting shipment', 'shopforge' ),
		'prodotto_ricevuto' => __( 'Product received', 'shopforge' ),
		'rimborsata'        => __( 'Refunded', 'shopforge' ),
		'sostituita'        => __( 'Replaced', 'shopforge' ),
		'rifiutata'         => __( 'Rejected', 'shopforge' ),
		'annullata'         => __( 'Cancelled by customer', 'shopforge' ),
		'chiusa'            => __( 'Closed', 'shopforge' ),
	] );
}

function shopforge_rma_get_status_label( string $status ): string {
	return shopforge_rma_get_statuses()[ $status ] ?? $status;
}

function shopforge_rma_get_open_statuses(): array {
	return apply_filters( 'shopforge_rma_open_statuses', [ 'aperta', 'in_lavorazione', 'approvata', 'attesa_spedizione', 'prodotto_ricevuto' ] );
}

function shopforge_rma_get_non_consuming_statuses(): array {
	return apply_filters( 'shopforge_rma_non_consuming_statuses', [ 'rifiutata', 'annullata' ] );
}

function shopforge_rma_get_valid_order_statuses(): array {
	return apply_filters( 'shopforge_rma_valid_order_statuses', [ 'processing', 'completed' ] );
}

function shopforge_rma_get_motivo_options(): array {
	return apply_filters( 'shopforge_rma_motivo_options', [
		'danno_caduta_urto'    => __( 'Damage caused by drop or impact', 'shopforge' ),
		'danneggiamento_acqua' => __( 'Water damage', 'shopforge' ),
		'non_si_accende'       => __( 'Does not turn on', 'shopforge' ),
		'non_funziona'         => __( 'Does not work properly', 'shopforge' ),
		'controllo_revisione'  => __( 'Check / Service / Cleaning', 'shopforge' ),
		'altro'                => __( 'Other', 'shopforge' ),
	] );
}

function shopforge_rma_get_remedy_options( string $tipo_richiesta = 'assistenza' ): array {
	if ( 'reso' === $tipo_richiesta ) {
		return apply_filters( 'shopforge_rma_remedy_options_reso', [
			'rimborso_restituzione' => __( 'I want to request a refund and return the product (conditions apply).', 'shopforge' ),
			'sostituzione'          => __( 'I want to request a replacement of the product (conditions apply).', 'shopforge' ),
		] );
	}
	return apply_filters( 'shopforge_rma_remedy_options_assistenza', [
		'preventivo_riparazione' => __( 'I want to receive a repair quote', 'shopforge' ),
		'riparazione_150'        => __( 'The repair service can be processed up to €150 plus shipping, otherwise I want to receive a quote', 'shopforge' ),
		'riparazione_350'        => __( 'The repair service can be processed up to €350 plus shipping, otherwise I want to receive a quote', 'shopforge' ),
		'garanzia_europea'       => __( 'I want to use the European legal warranty of the product (required documents are attached)', 'shopforge' ),
	] );
}


// =============================================================================
// CONFIGURAZIONE (opzioni singole, gestite in ShopForge → Moduli)
// =============================================================================

function shopforge_rma_get_return_period_days(): int {
	return (int) apply_filters( 'shopforge_rma_return_period_days', max( 1, (int) get_option( 'shopforge_rma_return_period_days', 17 ) ) );
}

function shopforge_rma_get_warranty_months(): int {
	return (int) apply_filters( 'shopforge_rma_warranty_months', max( 1, (int) get_option( 'shopforge_rma_warranty_months', 24 ) ) );
}

function shopforge_rma_get_max_requests_per_day(): int {
	return (int) apply_filters( 'shopforge_rma_max_requests_per_day', (int) get_option( 'shopforge_rma_max_requests_per_day', 5 ) );
}

function shopforge_rma_get_notification_email(): string {
	$email = get_option( 'shopforge_rma_notification_email', '' );
	return apply_filters( 'shopforge_rma_notification_email', $email ?: get_option( 'admin_email' ) );
}

function shopforge_rma_get_refund_capability(): string {
	return apply_filters( 'shopforge_rma_refund_capability', 'manage_woocommerce' );
}


// =============================================================================
// HELPERS — ordine / prodotto
// =============================================================================

function shopforge_rma_verify_order_ownership( int $order_id, int $user_id ): bool {
	$order = wc_get_order( $order_id );
	return $order && (int) $order->get_user_id() === $user_id;
}

function shopforge_rma_verify_product_in_order( int $order_id, int $product_id ): bool {
	$order = wc_get_order( $order_id );
	if ( ! $order ) return false;
	foreach ( $order->get_items() as $item ) {
		if ( (int) $item->get_product_id() === $product_id ) return true;
		if ( $item->get_variation_id() && (int) $item->get_variation_id() === $product_id ) return true;
	}
	return false;
}

function shopforge_rma_get_product_quantity_in_order( int $order_id, int $product_id ): int {
	$order = wc_get_order( $order_id );
	if ( ! $order ) return 0;
	foreach ( $order->get_items() as $item ) {
		if ( (int) $item->get_product_id() === $product_id || ( $item->get_variation_id() && (int) $item->get_variation_id() === $product_id ) ) {
			return (int) $item->get_quantity();
		}
	}
	return 0;
}

function shopforge_rma_is_product_excluded( ?WC_Product $product ): bool {
	if ( ! $product ) return false;
	if ( 'yes' === get_post_meta( $product->get_id(), '_shopforge_rma_escludi', true ) ) return true;

	$excluded_categories = (array) get_option( 'shopforge_rma_excluded_categories', [] );
	if ( $excluded_categories && has_term( $excluded_categories, 'product_cat', $product->get_id() ) ) return true;

	return apply_filters( 'shopforge_rma_is_product_excluded', false, $product );
}

function shopforge_rma_is_return_period_expired( WC_Order $order, ?WC_Product $product = null ): bool {
	$order_date = $order->get_date_created();
	if ( ! $order_date ) return false;

	$days_limit = shopforge_rma_get_return_period_days();
	if ( $product ) {
		$override = get_post_meta( $product->get_id(), '_shopforge_rma_return_period_days', true );
		if ( '' !== $override && is_numeric( $override ) ) $days_limit = (int) $override;
	}

	$expiry = clone $order_date;
	$expiry->modify( "+{$days_limit} days" );

	return new DateTime( 'now', wp_timezone() ) > $expiry;
}

function shopforge_rma_is_warranty_expired( WC_Order $order ): bool {
	$order_date = $order->get_date_created();
	if ( ! $order_date ) return false;

	$expiry = clone $order_date;
	$expiry->modify( '+' . shopforge_rma_get_warranty_months() . ' months' );

	return new DateTime( 'now', wp_timezone() ) > $expiry;
}

function shopforge_rma_get_product_brand( WC_Product $product ): string {
	$brand = '';
	foreach ( apply_filters( 'shopforge_rma_product_brand_taxonomies', [ 'product_brand', 'yith_product_brand', 'pwb-brand' ] ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) continue;
		$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$brand = $terms[0]->name;
			break;
		}
	}
	if ( ! $brand ) $brand = get_post_meta( $product->get_id(), '_product_brand', true );
	if ( ! $brand && $product->is_type( 'variable' ) ) {
		$attributes = $product->get_attributes();
		if ( isset( $attributes['pa_brand'] ) ) $brand = $attributes['pa_brand'];
	}
	return apply_filters( 'shopforge_rma_product_brand', $brand, $product );
}


// =============================================================================
// HELPERS — richieste (query sul CPT)
// =============================================================================

function shopforge_rma_has_open_request( int $user_id, int $order_id, int $product_id ) {
	$requests = get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_shopforge_rma_user_id', 'value' => $user_id ],
			[ 'key' => '_shopforge_rma_order_id', 'value' => $order_id ],
			[ 'key' => '_shopforge_rma_product_id', 'value' => $product_id ],
			[ 'key' => '_shopforge_rma_stato', 'value' => shopforge_rma_get_open_statuses(), 'compare' => 'IN' ],
		],
	] );
	return $requests ? (int) $requests[0] : false;
}

function shopforge_rma_get_open_requests_map( int $user_id ): array {
	$requests = get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_shopforge_rma_user_id', 'value' => $user_id ],
			[ 'key' => '_shopforge_rma_stato', 'value' => shopforge_rma_get_open_statuses(), 'compare' => 'IN' ],
		],
	] );

	$map = [];
	foreach ( $requests as $request ) {
		$order_id   = get_post_meta( $request->ID, '_shopforge_rma_order_id', true );
		$product_id = get_post_meta( $request->ID, '_shopforge_rma_product_id', true );
		if ( $order_id && $product_id ) $map[ $order_id . '_' . $product_id ] = $request->ID;
	}
	return $map;
}

function shopforge_rma_get_requested_quantities_map( int $user_id ): array {
	$requests = get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_shopforge_rma_user_id', 'value' => $user_id ],
			[ 'key' => '_shopforge_rma_stato', 'value' => shopforge_rma_get_non_consuming_statuses(), 'compare' => 'NOT IN' ],
		],
	] );

	$map = [];
	foreach ( $requests as $request ) {
		$order_id   = get_post_meta( $request->ID, '_shopforge_rma_order_id', true );
		$product_id = get_post_meta( $request->ID, '_shopforge_rma_product_id', true );
		$qty        = max( 1, (int) get_post_meta( $request->ID, '_shopforge_rma_quantita', true ) );
		if ( $order_id && $product_id ) {
			$key = $order_id . '_' . $product_id;
			$map[ $key ] = ( $map[ $key ] ?? 0 ) + $qty;
		}
	}
	return $map;
}

function shopforge_rma_get_remaining_quantity( int $order_id, int $product_id ): int {
	$total_qty = shopforge_rma_get_product_quantity_in_order( $order_id, $product_id );

	$requests = get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_shopforge_rma_order_id', 'value' => $order_id ],
			[ 'key' => '_shopforge_rma_product_id', 'value' => $product_id ],
			[ 'key' => '_shopforge_rma_stato', 'value' => shopforge_rma_get_non_consuming_statuses(), 'compare' => 'NOT IN' ],
		],
	] );

	$requested = 0;
	foreach ( $requests as $request ) {
		$requested += max( 1, (int) get_post_meta( $request->ID, '_shopforge_rma_quantita', true ) );
	}
	return max( 0, $total_qty - $requested );
}

function shopforge_rma_has_reached_daily_limit( int $user_id ): bool {
	$max = shopforge_rma_get_max_requests_per_day();
	if ( $max <= 0 ) return false;

	$count = count( get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'date_query'     => [ [ 'after' => '24 hours ago' ] ],
		'meta_query'     => [ [ 'key' => '_shopforge_rma_user_id', 'value' => $user_id ] ],
	] ) );

	return $count >= $max;
}

function shopforge_rma_get_unread_messages_count( int $user_id ): int {
	$requests = get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => [ [ 'key' => '_shopforge_rma_user_id', 'value' => $user_id ] ],
	] );

	$unread = 0;
	foreach ( $requests as $request ) {
		$messages  = get_post_meta( $request->ID, '_shopforge_rma_messages', true ) ?: [];
		$last_read = get_user_meta( $user_id, '_shopforge_rma_last_read_' . $request->ID, true );
		$last_read_time = $last_read ? strtotime( $last_read ) : 0;
		foreach ( $messages as $message ) {
			if ( ! empty( $message['is_admin'] ) && strtotime( $message['date'] ?? 'now' ) > $last_read_time ) {
				$unread++;
			}
		}
	}
	return $unread;
}

function shopforge_rma_get_message_author_label( bool $is_admin, int $user_id = 0 ): string {
	if ( $is_admin ) return __( 'Support', 'shopforge' );
	$user = get_userdata( $user_id );
	return $user ? $user->display_name : __( 'Customer', 'shopforge' );
}


// =============================================================================
// UPLOAD ALLEGATI
// =============================================================================

function shopforge_rma_handle_uploaded_files( array $files ): array {
	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) return [];

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$allowed_mimes = apply_filters( 'shopforge_rma_allowed_upload_mimes', [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'heic'         => 'image/heic',
		'mp4|m4v'      => 'video/mp4',
		'mov|qt'       => 'video/quicktime',
		'pdf'          => 'application/pdf',
	] );
	$max_size = apply_filters( 'shopforge_rma_max_upload_size', 20 * MB_IN_BYTES );

	$uploaded = [];
	foreach ( $files['name'] as $key => $name ) {
		if ( empty( $name ) ) continue;
		if ( ( $files['error'][ $key ] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) continue;
		if ( ( $files['size'][ $key ] ?? 0 ) > $max_size ) continue;

		$filetype = wp_check_filetype_and_ext( $files['tmp_name'][ $key ], $name, $allowed_mimes );
		if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) continue;

		$file = [
			'name'     => $name,
			'type'     => $files['type'][ $key ],
			'tmp_name' => $files['tmp_name'][ $key ],
			'error'    => $files['error'][ $key ],
			'size'     => $files['size'][ $key ],
		];

		$upload = wp_handle_upload( $file, [ 'test_form' => false, 'mimes' => $allowed_mimes ] );
		if ( isset( $upload['error'] ) ) continue;

		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		], $upload['file'] );

		if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
			$uploaded[] = $attachment_id;
		}
	}
	return $uploaded;
}


// =============================================================================
// CREAZIONE / STATO RICHIESTA
// =============================================================================

function shopforge_rma_sanitize_data( array $data ): array {
	$s = [];
	if ( isset( $data['user_id'] ) )    $s['user_id']    = absint( $data['user_id'] );
	if ( isset( $data['order_id'] ) )   $s['order_id']   = absint( $data['order_id'] );
	if ( isset( $data['product_id'] ) ) $s['product_id'] = absint( $data['product_id'] );
	if ( isset( $data['quantita'] ) )   $s['quantita']   = max( 1, absint( $data['quantita'] ) );

	if ( isset( $data['tipo_richiesta'] ) ) {
		$s['tipo_richiesta'] = in_array( $data['tipo_richiesta'], [ 'assistenza', 'reso' ], true ) ? $data['tipo_richiesta'] : 'assistenza';
	}
	if ( isset( $data['motivo'] ) )               $s['motivo']               = sanitize_text_field( $data['motivo'] );
	if ( isset( $data['descrizione_problema'] ) )  $s['descrizione_problema'] = sanitize_textarea_field( $data['descrizione_problema'] );

	if ( isset( $data['rimedio_scelto'] ) ) {
		$tipo    = $data['tipo_richiesta'] ?? 'assistenza';
		$options = array_keys( shopforge_rma_get_remedy_options( $tipo ) );
		$s['rimedio_scelto'] = in_array( $data['rimedio_scelto'], $options, true ) ? $data['rimedio_scelto'] : '';
	}

	foreach ( [ 'accetto_termini', 'accetto_privacy', 'accetto_procedura' ] as $key ) {
		if ( isset( $data[ $key ] ) ) $s[ $key ] = '1' === $data[ $key ] ? '1' : '0';
	}

	return $s;
}

/**
 * Cambia stato di una richiesta, registra lo storico, notifica via hook.
 */
function shopforge_rma_update_status( int $post_id, string $new_status, int $changed_by = 0 ): bool {
	if ( ! array_key_exists( $new_status, shopforge_rma_get_statuses() ) ) return false;

	$old_status = get_post_meta( $post_id, '_shopforge_rma_stato', true ) ?: 'aperta';
	if ( $old_status === $new_status ) return false;

	update_post_meta( $post_id, '_shopforge_rma_stato', $new_status );

	$history   = get_post_meta( $post_id, '_shopforge_rma_status_history', true ) ?: [];
	$history[] = [ 'from' => $old_status, 'to' => $new_status, 'user_id' => $changed_by, 'date' => current_time( 'mysql' ) ];
	update_post_meta( $post_id, '_shopforge_rma_status_history', $history );

	do_action( 'shopforge_rma_status_changed', $post_id, $new_status, $old_status, $changed_by );

	shopforge_rma_notify_status_update( $post_id, $new_status, $changed_by );

	return true;
}

/**
 * Invia l'email di aggiornamento stato al cliente (o all'admin se è stato il
 * cliente stesso a cambiare stato, es. autoannullamento).
 */
function shopforge_rma_notify_status_update( int $post_id, string $status, int $changed_by = 0, string $reply = '' ): void {
	$user_id  = (int) get_post_meta( $post_id, '_shopforge_rma_user_id', true );
	$order_id = (int) get_post_meta( $post_id, '_shopforge_rma_order_id', true );
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) return;

	$rma_data = [
		'request_id' => $post_id,
		'tipo'       => get_post_meta( $post_id, '_shopforge_rma_tipo_richiesta', true ),
		'product'    => wc_get_product( (int) get_post_meta( $post_id, '_shopforge_rma_product_id', true ) )?->get_name() ?: '',
		'status'     => $status,
		'reply'      => $reply,
	];

	$emails = WC()->mailer()->get_emails();

	if ( $changed_by && $changed_by === $user_id ) {
		if ( isset( $emails['ShopForge_Email_RMA_Admin'] ) ) {
			$emails['ShopForge_Email_RMA_Admin']->trigger( $order, array_merge( $rma_data, [ 'is_status_update' => true ] ) );
		}
		return;
	}

	if ( isset( $emails['ShopForge_Email_RMA_Status_Update'] ) ) {
		$emails['ShopForge_Email_RMA_Status_Update']->trigger( $order, $rma_data );
	}

	if ( $user_id ) {
		do_action( 'shopforge_notification', $user_id, 'rma_status', [
			/* translators: %d: RMA request ID */
			'text' => sprintf( __( 'Update on your product support request #%d', 'shopforge' ), $post_id ),
			'url'  => add_query_arg( [ 'request_id' => $post_id ], wc_get_account_endpoint_url( 'shopforge-rma' ) ),
		] );
	}
}

/**
 * Crea una richiesta RMA. Ritorna l'ID del post o un WP_Error.
 *
 * @return int|WP_Error
 */
function shopforge_rma_create_request( array $data, array $uploaded_files = [] ) {
	$s = shopforge_rma_sanitize_data( $data );

	if ( empty( $s['user_id'] ) || empty( $s['order_id'] ) || empty( $s['product_id'] ) ) {
		return new WP_Error( 'missing_fields', __( 'Required fields missing.', 'shopforge' ) );
	}
	if ( shopforge_rma_has_reached_daily_limit( $s['user_id'] ) ) {
		return new WP_Error( 'rate_limited', __( 'You reached the maximum number of requests allowed today. Try again tomorrow.', 'shopforge' ) );
	}
	if ( ! shopforge_rma_verify_order_ownership( $s['order_id'], $s['user_id'] ) ) {
		return new WP_Error( 'invalid_ownership', __( 'Invalid order.', 'shopforge' ) );
	}
	if ( ! shopforge_rma_verify_product_in_order( $s['order_id'], $s['product_id'] ) ) {
		return new WP_Error( 'invalid_product', __( 'Product not in the order.', 'shopforge' ) );
	}

	$order   = wc_get_order( $s['order_id'] );
	$product = wc_get_product( $s['product_id'] );
	if ( ! $order || ! $product ) {
		return new WP_Error( 'invalid_data', __( 'Invalid data.', 'shopforge' ) );
	}
	if ( shopforge_rma_is_product_excluded( $product ) ) {
		return new WP_Error( 'product_excluded', __( 'This product is not eligible for return or support requests.', 'shopforge' ) );
	}

	$quantity  = $s['quantita'] ?? 1;
	$remaining = shopforge_rma_get_remaining_quantity( $s['order_id'], $s['product_id'] );
	if ( $remaining <= 0 ) {
		$existing = shopforge_rma_has_open_request( $s['user_id'], $s['order_id'], $s['product_id'] );
		return new WP_Error( 'existing_request', __( 'You already requested return/support for all units of this product.', 'shopforge' ), $existing );
	}
	if ( $quantity > $remaining ) $quantity = $remaining;

	if ( ( $s['rimedio_scelto'] ?? '' ) === 'garanzia_europea' && shopforge_rma_is_warranty_expired( $order ) ) {
		return new WP_Error( 'warranty_expired', __( 'The legal warranty period for this order has ended.', 'shopforge' ) );
	}

	$tipo_label = ( $s['tipo_richiesta'] ?? 'assistenza' ) === 'reso' ? __( 'Return', 'shopforge' ) : __( 'Support', 'shopforge' );

	$post_id = wp_insert_post( [
		/* translators: 1: request type, 2: order number, 3: product name */
		'post_title'   => sprintf( __( '%1$s request - Order #%2$s - %3$s', 'shopforge' ), $tipo_label, $order->get_order_number(), $product->get_name() ),
		'post_content' => '',
		'post_status'  => 'publish',
		'post_type'    => 'shopforge_rma_request',
	] );
	if ( is_wp_error( $post_id ) ) return $post_id;

	update_post_meta( $post_id, '_shopforge_rma_user_id', $s['user_id'] );
	update_post_meta( $post_id, '_shopforge_rma_order_id', $s['order_id'] );
	update_post_meta( $post_id, '_shopforge_rma_product_id', $s['product_id'] );
	update_post_meta( $post_id, '_shopforge_rma_quantita', $quantity );
	update_post_meta( $post_id, '_shopforge_rma_tipo_richiesta', $s['tipo_richiesta'] ?? 'assistenza' );
	update_post_meta( $post_id, '_shopforge_rma_descrizione_problema', $s['descrizione_problema'] ?? '' );
	update_post_meta( $post_id, '_shopforge_rma_rimedio_scelto', $s['rimedio_scelto'] ?? '' );
	update_post_meta( $post_id, '_shopforge_rma_motivo', $s['motivo'] ?? '' );
	update_post_meta( $post_id, '_shopforge_rma_accetto_termini', $s['accetto_termini'] ?? '0' );
	update_post_meta( $post_id, '_shopforge_rma_accetto_privacy', $s['accetto_privacy'] ?? '0' );
	update_post_meta( $post_id, '_shopforge_rma_accetto_procedura', $s['accetto_procedura'] ?? '0' );
	update_post_meta( $post_id, '_shopforge_rma_stato', 'aperta' );
	update_post_meta( $post_id, '_shopforge_rma_data_creazione', current_time( 'mysql' ) );
	update_post_meta( $post_id, '_shopforge_rma_status_history', [
		[ 'from' => '', 'to' => 'aperta', 'user_id' => $s['user_id'], 'date' => current_time( 'mysql' ) ],
	] );

	$first_message = [];
	if ( ! empty( $s['descrizione_problema'] ) ) {
		$first_message = [ [ 'user_id' => $s['user_id'], 'message' => $s['descrizione_problema'], 'date' => current_time( 'mysql' ), 'is_admin' => false ] ];
	}
	update_post_meta( $post_id, '_shopforge_rma_messages', $first_message );

	if ( $uploaded_files ) {
		update_post_meta( $post_id, '_shopforge_rma_allegati', $uploaded_files );
		foreach ( $uploaded_files as $attach_id ) {
			wp_update_post( [ 'ID' => $attach_id, 'post_parent' => $post_id ] );
		}
	}

	do_action( 'shopforge_rma_request_created', $post_id, $s );
	do_action( 'shopforge_rma_submitted', $s['user_id'], $s['order_id'], $post_id );

	$rma_data = [
		'request_id'  => $post_id,
		'tipo'        => $s['tipo_richiesta'] ?? 'assistenza',
		'product'     => $product->get_name(),
		'descrizione' => $s['descrizione_problema'] ?? '',
	];
	$emails = WC()->mailer()->get_emails();
	if ( isset( $emails['ShopForge_Email_RMA_Customer'] ) ) {
		$emails['ShopForge_Email_RMA_Customer']->trigger( $order, $rma_data );
	}
	if ( isset( $emails['ShopForge_Email_RMA_Admin'] ) ) {
		$emails['ShopForge_Email_RMA_Admin']->trigger( $order, $rma_data );
	}

	return $post_id;
}


// =============================================================================
// ENDPOINT ACCOUNT — routing interno (default | action=richiesta | view=richieste | request_id=X)
// =============================================================================

function shopforge_rma_get_request_url( int $order_id, int $product_id, string $tipo = 'assistenza' ): string {
	return add_query_arg( [ 'action' => 'richiesta', 'order_id' => $order_id, 'product_id' => $product_id, 'tipo' => $tipo ], wc_get_account_endpoint_url( 'shopforge-rma' ) );
}

function shopforge_rma_get_my_requests_url(): string {
	return add_query_arg( [ 'view' => 'richieste' ], wc_get_account_endpoint_url( 'shopforge-rma' ) );
}

add_action( 'woocommerce_account_shopforge-rma_endpoint', function () {
	if ( isset( $_GET['request_sent'] ) && '1' === $_GET['request_sent'] ) {
		echo '<div class="woocommerce-message">' . esc_html__( 'Request sent successfully.', 'shopforge' ) . '</div>';
	}

	if ( isset( $_GET['action'] ) && 'richiesta' === $_GET['action'] ) {
		shopforge_rma_render_request_form();
		return;
	}
	if ( isset( $_GET['view'] ) && 'richieste' === $_GET['view'] ) {
		shopforge_rma_render_my_requests();
		return;
	}
	if ( isset( $_GET['request_id'] ) ) {
		shopforge_rma_render_request_detail( absint( $_GET['request_id'] ) );
		return;
	}

	shopforge_rma_render_orders_list();
} );

/**
 * Vista predefinita: ordini idonei con pulsanti "Richiedi assistenza/reso" per prodotto.
 */
function shopforge_rma_render_orders_list(): void {
	shopforge_account_section_header( __( 'Product Support', 'shopforge' ), 'fa-solid fa-screwdriver-wrench' );

	$user_id = get_current_user_id();
	$orders  = wc_get_orders( [
		'customer_id' => $user_id,
		'status'      => shopforge_rma_get_valid_order_statuses(),
		'orderby'     => 'date',
		'order'       => 'DESC',
		'limit'       => 20,
		'return'      => 'objects',
	] );

	if ( ! $orders ) {
		shopforge_account_empty_state( 'fa-solid fa-screwdriver-wrench', __( 'No eligible orders', 'shopforge' ), __( 'Support and returns are available for completed or processing orders.', 'shopforge' ) );
		return;
	}

	$open_map = shopforge_rma_get_open_requests_map( $user_id );
	$qty_map  = shopforge_rma_get_requested_quantities_map( $user_id );

	echo '<p><a href="' . esc_url( shopforge_rma_get_my_requests_url() ) . '" class="shopforge-btn shopforge-btn--secondary">' . esc_html__( 'My requests', 'shopforge' ) . '</a></p>';

	foreach ( $orders as $order ) {
		echo '<div class="shopforge-rma-order-card">';
		echo '<p class="shopforge-rma-order-card__title">' . sprintf( /* translators: 1: order number, 2: order date */ esc_html__( 'Order #%1$s — %2$s', 'shopforge' ), esc_html( $order->get_order_number() ), esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) ) . '</p>';

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) continue;

			$order_id   = $order->get_id();
			$product_id = $product->get_id();
			$key        = $order_id . '_' . $product_id;

			echo '<div class="shopforge-rma-product-row">';
			echo '<span class="shopforge-rma-product-row__name">' . esc_html( $item->get_name() ) . ' × ' . esc_html( $item->get_quantity() ) . '</span>';
			echo '<div class="shopforge-rma-product-row__actions">';

			if ( isset( $open_map[ $key ] ) ) {
				echo '<a href="' . esc_url( add_query_arg( [ 'request_id' => $open_map[ $key ] ], wc_get_account_endpoint_url( 'shopforge-rma' ) ) ) . '" class="shopforge-btn shopforge-btn--secondary">' . esc_html__( 'Request already open', 'shopforge' ) . '</a>';
			} elseif ( shopforge_rma_is_product_excluded( $product ) ) {
				echo '<span class="shopforge-rma-note">' . esc_html__( 'Not available for this product', 'shopforge' ) . '</span>';
			} elseif ( shopforge_rma_get_remaining_quantity( $order_id, $product_id ) <= 0 ) {
				echo '<span class="shopforge-rma-note">' . esc_html__( 'All units already covered by a request', 'shopforge' ) . '</span>';
			} else {
				echo '<a href="' . esc_url( shopforge_rma_get_request_url( $order_id, $product_id, 'assistenza' ) ) . '" class="shopforge-btn shopforge-btn--primary">' . esc_html__( 'Request support', 'shopforge' ) . '</a>';
				if ( ! shopforge_rma_is_return_period_expired( $order, $product ) ) {
					echo '<a href="' . esc_url( shopforge_rma_get_request_url( $order_id, $product_id, 'reso' ) ) . '" class="shopforge-btn shopforge-btn--secondary">' . esc_html__( 'Request return', 'shopforge' ) . '</a>';
				}
			}

			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}
}

/**
 * "Le mie richieste": elenco cronologico delle richieste dell'utente.
 */
function shopforge_rma_render_my_requests(): void {
	shopforge_account_section_header( __( 'My Requests', 'shopforge' ), 'fa-solid fa-screwdriver-wrench' );

	$user_id  = get_current_user_id();
	$requests = get_posts( [
		'post_type'      => 'shopforge_rma_request',
		'posts_per_page' => 20,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => [ [ 'key' => '_shopforge_rma_user_id', 'value' => $user_id ] ],
	] );

	if ( ! $requests ) {
		shopforge_account_empty_state( 'fa-solid fa-screwdriver-wrench', __( 'No requests', 'shopforge' ), __( 'Support or return requests you open will appear here.', 'shopforge' ) );
		return;
	}

	foreach ( $requests as $request ) {
		$order_id   = (int) get_post_meta( $request->ID, '_shopforge_rma_order_id', true );
		$product_id = (int) get_post_meta( $request->ID, '_shopforge_rma_product_id', true );
		$product    = wc_get_product( $product_id );
		$stato      = get_post_meta( $request->ID, '_shopforge_rma_stato', true );
		$tipo       = get_post_meta( $request->ID, '_shopforge_rma_tipo_richiesta', true );
		?>
		<div class="shopforge-rma-request-card">
			<div class="shopforge-rma-request-card__head">
				<span><?php echo esc_html( $product ? $product->get_name() : '#' . $product_id ); ?></span>
				<span class="shopforge-rma-badge"><?php echo esc_html( shopforge_rma_get_status_label( $stato ) ); ?></span>
			</div>
			<p class="shopforge-rma-request-card__meta">
				<?php esc_html_e( 'Order', 'shopforge' ); ?> #<?php echo esc_html( $order_id ); ?> ·
				<?php echo esc_html( 'reso' === $tipo ? __( 'Return', 'shopforge' ) : __( 'Support', 'shopforge' ) ); ?> ·
				<?php echo esc_html( get_the_date( get_option( 'date_format' ), $request ) ); ?>
			</p>
			<a href="<?php echo esc_url( add_query_arg( [ 'request_id' => $request->ID ], wc_get_account_endpoint_url( 'shopforge-rma' ) ) ); ?>" class="shopforge-btn shopforge-btn--secondary"><?php esc_html_e( 'View Details', 'shopforge' ); ?></a>
		</div>
		<?php
	}
}

/**
 * Form di creazione richiesta per un prodotto/ordine specifico.
 */
function shopforge_rma_render_request_form(): void {
	$user_id    = get_current_user_id();
	$order_id   = absint( $_GET['order_id'] ?? 0 );
	$product_id = absint( $_GET['product_id'] ?? 0 );
	$tipo       = in_array( $_GET['tipo'] ?? '', [ 'assistenza', 'reso' ], true ) ? $_GET['tipo'] : 'assistenza';

	if ( ! $order_id || ! $product_id
	     || ! shopforge_rma_verify_order_ownership( $order_id, $user_id )
	     || ! shopforge_rma_verify_product_in_order( $order_id, $product_id ) ) {
		wp_safe_redirect( wc_get_account_endpoint_url( 'shopforge-rma' ) );
		exit;
	}

	$order   = wc_get_order( $order_id );
	$product = wc_get_product( $product_id );
	if ( ! $order || ! $product || shopforge_rma_is_product_excluded( $product ) ) {
		wp_safe_redirect( wc_get_account_endpoint_url( 'shopforge-rma' ) );
		exit;
	}

	$remaining = shopforge_rma_get_remaining_quantity( $order_id, $product_id );
	if ( $remaining <= 0 ) {
		$existing = shopforge_rma_has_open_request( $user_id, $order_id, $product_id );
		wp_safe_redirect( $existing ? add_query_arg( [ 'request_id' => $existing ], wc_get_account_endpoint_url( 'shopforge-rma' ) ) : shopforge_rma_get_my_requests_url() );
		exit;
	}

	shopforge_account_section_header( 'assistenza' === $tipo ? __( 'Request support', 'shopforge' ) : __( 'Request return', 'shopforge' ), 'fa-solid fa-screwdriver-wrench' );

	$warranty_expired = shopforge_rma_is_warranty_expired( $order );
	$brand            = shopforge_rma_get_product_brand( $product );
	$nonce            = wp_create_nonce( 'shopforge_rma_submit_request' );
	$store_name       = get_bloginfo( 'name' );

	wp_enqueue_script( 'shopforge-rma' );
	wp_localize_script( 'shopforge-rma', 'shopforgeRma', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => $nonce,
	] );
	?>
	<form class="shopforge-rma-form" id="shopforge-rma-form" enctype="multipart/form-data">
		<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
		<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
		<input type="hidden" name="tipo_richiesta" value="<?php echo esc_attr( $tipo ); ?>">

		<p><strong><?php echo esc_html( $product->get_name() ); ?></strong><?php if ( $brand ) echo ' — ' . esc_html( $brand ); ?></p>

		<?php if ( $remaining > 1 ) : ?>
		<div class="shopforge-field">
			<label for="shopforge-rma-quantita"><?php esc_html_e( 'Quantity', 'shopforge' ); ?></label>
			<input type="number" id="shopforge-rma-quantita" name="quantita" value="1" min="1" max="<?php echo esc_attr( $remaining ); ?>">
		</div>
		<?php endif; ?>

		<?php if ( 'assistenza' === $tipo ) : ?>
		<div class="shopforge-field">
			<label for="shopforge-rma-motivo"><?php esc_html_e( 'Reason', 'shopforge' ); ?></label>
			<select id="shopforge-rma-motivo" name="motivo" required>
				<option value="">— <?php esc_html_e( 'Select', 'shopforge' ); ?> —</option>
				<?php foreach ( shopforge_rma_get_motivo_options() as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<div class="shopforge-field">
			<label for="shopforge-rma-descrizione"><?php esc_html_e( 'Description of the problem', 'shopforge' ); ?></label>
			<textarea id="shopforge-rma-descrizione" name="descrizione_problema" rows="4" required></textarea>
		</div>

		<div class="shopforge-field">
			<label><?php esc_html_e( 'What would you prefer?', 'shopforge' ); ?></label>
			<div class="shopforge-radio-group">
			<?php foreach ( shopforge_rma_get_remedy_options( $tipo ) as $val => $label ) :
				$disabled = ( 'garanzia_europea' === $val && $warranty_expired );
			?>
			<label class="shopforge-radio">
				<input type="radio" name="rimedio_scelto" value="<?php echo esc_attr( $val ); ?>" <?php disabled( $disabled ); ?> <?php checked( ! $disabled && $val === array_key_first( shopforge_rma_get_remedy_options( $tipo ) ) ); ?>>
				<span class="shopforge-radio__box"></span>
				<?php echo esc_html( $label ); ?>
				<?php if ( $disabled ) echo ' <em>(' . esc_html__( 'warranty expired', 'shopforge' ) . ')</em>'; ?>
			</label>
			<?php endforeach; ?>
			</div>
		</div>

		<div class="shopforge-field">
			<label for="shopforge-rma-allegati"><?php esc_html_e( 'Photos or documents', 'shopforge' ); ?> <span>(<?php esc_html_e( 'optional', 'shopforge' ); ?>)</span></label>
			<input type="file" id="shopforge-rma-allegati" name="allegati[]" multiple accept="image/*,.pdf">
		</div>

		<label class="shopforge-rma-check"><input type="checkbox" name="accetto_termini" value="1" required> <?php esc_html_e( 'I accept the terms and conditions of the service.', 'shopforge' ); ?></label>
		<label class="shopforge-rma-check"><input type="checkbox" name="accetto_privacy" value="1" required> <?php esc_html_e( 'I have read the privacy policy.', 'shopforge' ); ?></label>
		<label class="shopforge-rma-check"><input type="checkbox" name="accetto_procedura" value="1" required>
			<?php
			/* translators: %s: store name */
			printf( esc_html__( 'I declare I understood the return/support procedure of %s and its conditions.', 'shopforge' ), esc_html( $store_name ) );
			?>
		</label>

		<p class="shopforge-rma-error" id="shopforge-rma-error" style="display:none"></p>

		<button type="submit" class="shopforge-btn shopforge-btn--primary" id="shopforge-rma-submit"><?php esc_html_e( 'Send request', 'shopforge' ); ?></button>
	</form>
	<?php
}

/**
 * Dettaglio richiesta: thread messaggi + annulla (se ancora "aperta").
 */
function shopforge_rma_render_request_detail( int $request_id ): void {
	$user_id = get_current_user_id();
	$request = get_post( $request_id );

	if ( ! $request || 'shopforge_rma_request' !== $request->post_type
	     || (int) get_post_meta( $request_id, '_shopforge_rma_user_id', true ) !== $user_id ) {
		wp_safe_redirect( wc_get_account_endpoint_url( 'shopforge-rma' ) );
		exit;
	}

	update_user_meta( $user_id, '_shopforge_rma_last_read_' . $request_id, current_time( 'mysql' ) );

	$order_id   = (int) get_post_meta( $request_id, '_shopforge_rma_order_id', true );
	$product_id = (int) get_post_meta( $request_id, '_shopforge_rma_product_id', true );
	$product    = wc_get_product( $product_id );
	$stato      = get_post_meta( $request_id, '_shopforge_rma_stato', true );
	$tipo       = get_post_meta( $request_id, '_shopforge_rma_tipo_richiesta', true );
	$rimedio    = get_post_meta( $request_id, '_shopforge_rma_rimedio_scelto', true );
	$messages   = get_post_meta( $request_id, '_shopforge_rma_messages', true ) ?: [];

	/* translators: %d: request ID */
	shopforge_account_section_header( sprintf( __( 'Request #%d', 'shopforge' ), $request_id ), 'fa-solid fa-screwdriver-wrench' );

	wp_enqueue_script( 'shopforge-rma' );
	wp_localize_script( 'shopforge-rma', 'shopforgeRma', [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonceMessage' => wp_create_nonce( 'shopforge_rma_add_message' ),
		'nonceCancel'  => wp_create_nonce( 'shopforge_rma_cancel_request' ),
		'requestId'    => $request_id,
	] );
	?>
	<p><a href="<?php echo esc_url( shopforge_rma_get_my_requests_url() ); ?>">← <?php esc_html_e( 'My requests', 'shopforge' ); ?></a></p>

	<div class="shopforge-rma-request-detail">
		<p><strong><?php echo esc_html( $product ? $product->get_name() : '#' . $product_id ); ?></strong> — <?php esc_html_e( 'Order', 'shopforge' ); ?> #<?php echo esc_html( $order_id ); ?></p>
		<p><span class="shopforge-rma-badge"><?php echo esc_html( shopforge_rma_get_status_label( $stato ) ); ?></span></p>
		<p><?php echo esc_html( 'reso' === $tipo ? __( 'Return', 'shopforge' ) : __( 'Support', 'shopforge' ) ); ?><?php if ( $rimedio ) echo ' · ' . esc_html( shopforge_rma_get_remedy_options( $tipo )[ $rimedio ] ?? $rimedio ); ?></p>

		<?php if ( 'aperta' === $stato ) : ?>
		<button type="button" class="shopforge-btn shopforge-btn--danger" id="shopforge-rma-cancel"><?php esc_html_e( 'Cancel request', 'shopforge' ); ?></button>
		<?php endif; ?>

		<div class="shopforge-rma-messages" id="shopforge-rma-messages">
			<?php foreach ( $messages as $message ) shopforge_rma_render_message( $message ); ?>
		</div>

		<form class="shopforge-rma-message-form" id="shopforge-rma-message-form">
			<textarea name="message_text" rows="3" placeholder="<?php esc_attr_e( 'Write a message…', 'shopforge' ); ?>" required></textarea>
			<button type="submit" class="shopforge-btn shopforge-btn--primary"><?php esc_html_e( 'Send', 'shopforge' ); ?></button>
		</form>
	</div>
	<?php
}

function shopforge_rma_render_message( array $message ): void {
	$is_admin = ! empty( $message['is_admin'] );
	?>
	<div class="shopforge-rma-message <?php echo $is_admin ? 'is-admin' : 'is-customer'; ?>">
		<div class="shopforge-rma-message__head">
			<strong><?php echo esc_html( shopforge_rma_get_message_author_label( $is_admin, $message['user_id'] ?? 0 ) ); ?></strong>
			<span><?php echo esc_html( ! empty( $message['date'] ) ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $message['date'] ) ) : '' ); ?></span>
		</div>
		<div class="shopforge-rma-message__body"><?php echo wp_kses_post( wpautop( $message['message'] ?? '' ) ); ?></div>
	</div>
	<?php
}


// =============================================================================
// AJAX — Frontend
// =============================================================================

add_action( 'wp_ajax_shopforge_rma_submit_request', function () {
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_rma_submit_request' ) ) {
		wp_send_json_error( [ 'message' => __( 'Session expired. Reload the page and try again.', 'shopforge' ) ] );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'shopforge' ) ] );
	}
	if ( ! shopforge_check_rate_limit( 'rma_submit_request', 15, $user_id ) ) {
		wp_send_json_error( [ 'message' => __( 'Wait a few seconds before sending again.', 'shopforge' ) ] );
	}

	$data = [
		'user_id'              => $user_id,
		'order_id'             => absint( $_POST['order_id'] ?? 0 ),
		'product_id'           => absint( $_POST['product_id'] ?? 0 ),
		'quantita'             => absint( $_POST['quantita'] ?? 1 ),
		'tipo_richiesta'       => sanitize_text_field( $_POST['tipo_richiesta'] ?? 'assistenza' ),
		'motivo'               => sanitize_text_field( $_POST['motivo'] ?? '' ),
		'descrizione_problema' => sanitize_textarea_field( $_POST['descrizione_problema'] ?? '' ),
		'rimedio_scelto'       => sanitize_text_field( $_POST['rimedio_scelto'] ?? '' ),
		'accetto_termini'      => isset( $_POST['accetto_termini'] ) ? '1' : '0',
		'accetto_privacy'      => isset( $_POST['accetto_privacy'] ) ? '1' : '0',
		'accetto_procedura'    => isset( $_POST['accetto_procedura'] ) ? '1' : '0',
	];

	if ( '1' !== $data['accetto_termini'] || '1' !== $data['accetto_privacy'] || '1' !== $data['accetto_procedura'] ) {
		wp_send_json_error( [ 'message' => __( 'You must accept all conditions to proceed.', 'shopforge' ) ] );
	}

	$uploaded_files = ! empty( $_FILES['allegati']['name'][0] ) ? shopforge_rma_handle_uploaded_files( $_FILES['allegati'] ) : [];

	$request_id = shopforge_rma_create_request( $data, $uploaded_files );

	if ( is_wp_error( $request_id ) ) {
		wp_send_json_error( [ 'message' => $request_id->get_error_message() ] );
	}

	wp_send_json_success( [
		'redirect_url' => add_query_arg( [ 'request_sent' => '1' ], wc_get_account_endpoint_url( 'shopforge-rma' ) ),
	] );
} );

add_action( 'wp_ajax_shopforge_rma_add_message', function () {
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_rma_add_message' ) ) {
		wp_send_json_error( [ 'message' => __( 'Security: invalid nonce.', 'shopforge' ) ] );
	}
	$user_id    = get_current_user_id();
	$request_id = absint( $_POST['request_id'] ?? 0 );
	$text       = sanitize_textarea_field( $_POST['message_text'] ?? '' );

	if ( ! $user_id || ! $request_id || ! $text ) {
		wp_send_json_error( [ 'message' => __( 'Missing data.', 'shopforge' ) ] );
	}
	if ( (int) get_post_meta( $request_id, '_shopforge_rma_user_id', true ) !== $user_id ) {
		wp_send_json_error( [ 'message' => __( 'Invalid request.', 'shopforge' ) ] );
	}

	$messages   = get_post_meta( $request_id, '_shopforge_rma_messages', true ) ?: [];
	$messages[] = [ 'user_id' => $user_id, 'message' => $text, 'date' => current_time( 'mysql' ), 'is_admin' => false ];
	update_post_meta( $request_id, '_shopforge_rma_messages', $messages );

	do_action( 'shopforge_rma_message_added', $request_id, end( $messages ), 'customer' );

	ob_start();
	shopforge_rma_render_message( end( $messages ) );
	wp_send_json_success( [ 'message_html' => ob_get_clean() ] );
} );

add_action( 'wp_ajax_shopforge_rma_cancel_request', function () {
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_rma_cancel_request' ) ) {
		wp_send_json_error( [ 'message' => __( 'Security: invalid nonce.', 'shopforge' ) ] );
	}
	$user_id    = get_current_user_id();
	$request_id = absint( $_POST['request_id'] ?? 0 );

	if ( ! $request_id || 'shopforge_rma_request' !== get_post_type( $request_id ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid request.', 'shopforge' ) ] );
	}
	if ( (int) get_post_meta( $request_id, '_shopforge_rma_user_id', true ) !== $user_id ) {
		wp_send_json_error( [ 'message' => __( 'Invalid request.', 'shopforge' ) ] );
	}
	if ( 'aperta' !== get_post_meta( $request_id, '_shopforge_rma_stato', true ) ) {
		wp_send_json_error( [ 'message' => __( 'This request is already being processed and can no longer be cancelled on your own.', 'shopforge' ) ] );
	}

	shopforge_rma_update_status( $request_id, 'annullata', $user_id );

	wp_send_json_success( [ 'label' => shopforge_rma_get_status_label( 'annullata' ) ] );
} );


// =============================================================================
// FRONTEND — enqueue asset (solo pagine account, coerente con shopforge-mod-returns.php)
// =============================================================================

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_account_page() ) return;
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;

	// ponytail: is_wc_endpoint_url() non riconosce gli endpoint custom del plugin
	// (mai registrati nel registro interno di WC via woocommerce_get_query_vars).
	// get_query_var() legge i query var di WP direttamente, sempre affidabile.
	if ( false !== get_query_var( 'shopforge-rma', false ) ) {
		// Niente dipendenza hard su shopforge-tracker: quel foglio (dove vive
		// il componente condiviso .shopforge-field/.shopforge-radio) si carica
		// solo se 'styles-account' è attivo. Dichiararlo come dep bloccherebbe
		// l'enqueue di questo file quando quell'handle non esiste.
		wp_enqueue_style( 'shopforge-rma', SHOPFORGE_URL . 'assets/css/shopforge-rma.css', [], SHOPFORGE_VERSION );
	}
	wp_register_script( 'shopforge-rma', SHOPFORGE_URL . 'assets/js/shopforge-rma.js', [], SHOPFORGE_VERSION, true );
} );


// =============================================================================
// PRODOTTO — metabox esclusione / periodo reso personalizzato
// =============================================================================

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'shopforge-rma-product', __( 'Support & Returns (RMA)', 'shopforge' ), 'shopforge_rma_product_metabox_render', 'product', 'side', 'default' );
} );

function shopforge_rma_product_metabox_render( WP_Post $post ): void {
	$escludi = get_post_meta( $post->ID, '_shopforge_rma_escludi', true );
	$period  = get_post_meta( $post->ID, '_shopforge_rma_return_period_days', true );

	wp_nonce_field( 'shopforge_rma_save_product', 'shopforge_rma_product_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="shopforge_rma_escludi" value="yes" <?php checked( $escludi, 'yes' ); ?>>
			Escludi questo prodotto da assistenza/resi
		</label>
	</p>
	<p>
		<label for="shopforge_rma_return_period_days">Giorni per il reso (vuoto = default globale)</label><br>
		<input type="number" min="0" id="shopforge_rma_return_period_days" name="shopforge_rma_return_period_days" value="<?php echo esc_attr( $period ); ?>" style="width:100%">
	</p>
	<?php
}

add_action( 'save_post_product', function ( int $product_id ): void {
	if ( ! isset( $_POST['shopforge_rma_product_nonce'] )
	     || ! wp_verify_nonce( $_POST['shopforge_rma_product_nonce'], 'shopforge_rma_save_product' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_product', $product_id ) ) return;

	update_post_meta( $product_id, '_shopforge_rma_escludi', isset( $_POST['shopforge_rma_escludi'] ) ? 'yes' : 'no' );

	$period = $_POST['shopforge_rma_return_period_days'] ?? '';
	if ( '' === $period ) {
		delete_post_meta( $product_id, '_shopforge_rma_return_period_days' );
	} else {
		update_post_meta( $product_id, '_shopforge_rma_return_period_days', max( 0, absint( $period ) ) );
	}
} );
