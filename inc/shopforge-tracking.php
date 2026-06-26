<?php
/**
 * Andrea Emili — Integrazione tracking spedizioni via 17track
 *
 * Flusso:
 *  1. Admin inserisce tracking number + nome corriere nel metabox ordine
 *  2. Il numero viene registrato su 17track automaticamente al salvataggio
 *  3. Cliente apre vedi-ordine → JS chiama REST endpoint /shopforge/v1/tracking
 *  4. PHP controlla transient (2h) → se scaduto chiama 17track API → ritorna JSON
 *  5. JS renderizza timeline eventi nel widget sotto il tracker step
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

define( 'BNCOM_17TRACK_KEY', 'D778B41030ADFD3C0657F8169093631A' );
define( 'BNCOM_17TRACK_API', 'https://api.17track.net/track/v2.2/' );


// =============================================================================
// ADMIN — Metabox tracking nel dettaglio ordine
// =============================================================================

add_action( 'add_meta_boxes', function () {
	// Classic orders
	add_meta_box(
		'shopforge-tracking',
		'📦 Tracking Spedizione',
		'shopforge_tracking_metabox_render',
		'shop_order',
		'side',
		'high'
	);
	// HPOS (WC 7+)
	add_meta_box(
		'shopforge-tracking',
		'📦 Tracking Spedizione',
		'shopforge_tracking_metabox_render',
		'woocommerce_page_wc-orders',
		'side',
		'high'
	);
} );

function shopforge_tracking_metabox_render( $post_or_order ): void {
	$order = ( $post_or_order instanceof WP_Post )
		? wc_get_order( $post_or_order->ID )
		: $post_or_order;

	if ( ! $order ) return;

	$tracking_number = $order->get_meta( '_shopforge_tracking_number' );
	$carrier_name    = $order->get_meta( '_shopforge_tracking_carrier' );

	wp_nonce_field( 'shopforge_tracking_save', 'shopforge_tracking_nonce' );
	?>
	<style>
	.shopforge-mb label { display:block; font-weight:600; font-size:11px; text-transform:uppercase;
	                  letter-spacing:.05em; color:#646970; margin:12px 0 4px; }
	.shopforge-mb label:first-child { margin-top:0; }
	.shopforge-mb input[type=text] { width:100%; padding:6px 8px; border-radius:4px;
	                              border:1px solid #8c8f94; box-sizing:border-box; font-size:13px; }
	.shopforge-mb .shopforge-mb-actions { margin-top:12px; display:flex; gap:8px; align-items:center; }
	.shopforge-mb .shopforge-mb-clear { font-size:12px; color:#d63638; background:none; border:none;
	                             cursor:pointer; padding:0; text-decoration:underline; }
	.shopforge-mb .shopforge-mb-status { font-size:11px; color:#646970; }
	</style>

	<div class="shopforge-mb">
		<label for="shopforge_tracking_carrier">Corriere</label>
		<input type="text" id="shopforge_tracking_carrier" name="shopforge_tracking_carrier"
		       value="<?php echo esc_attr( $carrier_name ); ?>"
		       placeholder="BRT, GLS, SDA, DHL…">

		<label for="shopforge_tracking_number">Numero tracking</label>
		<input type="text" id="shopforge_tracking_number" name="shopforge_tracking_number"
		       value="<?php echo esc_attr( $tracking_number ); ?>"
		       placeholder="Inserisci numero tracking">

		<?php if ( $tracking_number ) : ?>
		<div class="shopforge-mb-actions">
			<button type="button" class="shopforge-mb-clear" id="shopforge-clear-cache"
			        data-order="<?php echo esc_attr( $order->get_id() ); ?>"
			        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_clear_cache' ) ); ?>">
				🔄 Aggiorna cache
			</button>
			<span class="shopforge-mb-status" id="shopforge-cache-status"></span>
		</div>
		<script>
		document.getElementById('shopforge-clear-cache')?.addEventListener('click', function() {
			var btn = this;
			var status = document.getElementById('shopforge-cache-status');
			btn.disabled = true;
			status.textContent = 'Pulizia…';
			fetch(ajaxurl, {
				method: 'POST',
				headers: {'Content-Type':'application/x-www-form-urlencoded'},
				body: 'action=shopforge_clear_tracking_cache&order_id=' + btn.dataset.order + '&nonce=' + btn.dataset.nonce
			}).then(function(r){ return r.json(); }).then(function(d) {
				status.textContent = d.success ? '✓ Cache svuotata' : '✗ Errore';
				btn.disabled = false;
				setTimeout(function(){ status.textContent=''; }, 3000);
			});
		});
		</script>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Salva i campi del metabox (compatibile classic + HPOS).
 */
function shopforge_save_tracking_meta( int $order_id ): void {
	if ( ! isset( $_POST['shopforge_tracking_nonce'] )
	     || ! wp_verify_nonce( $_POST['shopforge_tracking_nonce'], 'shopforge_tracking_save' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	$old_number = $order->get_meta( '_shopforge_tracking_number' );
	$new_number = sanitize_text_field( $_POST['shopforge_tracking_number'] ?? '' );
	$carrier    = sanitize_text_field( $_POST['shopforge_tracking_carrier'] ?? '' );

	$order->update_meta_data( '_shopforge_tracking_number', $new_number );
	$order->update_meta_data( '_shopforge_tracking_carrier', $carrier );
	$order->save();

	// Nuovo numero: registra su 17track e invalida cache
	if ( $new_number && $new_number !== $old_number ) {
		shopforge_17track_register( $new_number );
		delete_transient( 'shopforge_track_' . md5( $new_number ) );
	}
}
add_action( 'woocommerce_process_shop_order_meta', 'shopforge_save_tracking_meta' );


// =============================================================================
// AJAX — Svuota cache tracking (admin)
// =============================================================================

add_action( 'wp_ajax_shopforge_clear_tracking_cache', function () {
	check_ajax_referer( 'shopforge_clear_cache', 'nonce' );

	$order_id = absint( $_POST['order_id'] ?? 0 );
	$order    = wc_get_order( $order_id );

	if ( $order ) {
		$number = $order->get_meta( '_shopforge_tracking_number' );
		if ( $number ) {
			delete_transient( 'shopforge_track_' . md5( $number ) );
		}
	}

	wp_send_json_success( [ 'cleared' => true ] );
} );


// =============================================================================
// 17TRACK API — Helpers
// =============================================================================

/**
 * Registra un numero tracking su 17track (chiamata one-time al salvataggio).
 */
function shopforge_17track_register( string $number ): bool {
	$response = wp_remote_post( BNCOM_17TRACK_API . 'register', [
		'headers' => [
			'17token'      => BNCOM_17TRACK_KEY,
			'Content-Type' => 'application/json',
		],
		'body'    => wp_json_encode( [ [ 'number' => $number ] ] ),
		'timeout' => 15,
	] );

	return ! is_wp_error( $response );
}

/**
 * Recupera info tracking con cache transient (2h).
 *
 * @return array|false  Array normalizzato o false in caso di errore API.
 */
function shopforge_17track_get( string $number ): array|false {
	$cache_key = 'shopforge_track_' . md5( $number );
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) return $cached;

	$response = wp_remote_post( BNCOM_17TRACK_API . 'gettrackinfo', [
		'headers' => [
			'17token'      => BNCOM_17TRACK_KEY,
			'Content-Type' => 'application/json',
		],
		'body'    => wp_json_encode( [ [ 'number' => $number ] ] ),
		'timeout' => 15,
	] );

	if ( is_wp_error( $response ) ) return false;

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['data']['accepted'][0] ) ) return false;

	$track  = $body['data']['accepted'][0]['track'] ?? [];
	$result = shopforge_17track_normalize( $number, $track );

	set_transient( $cache_key, $result, 2 * HOUR_IN_SECONDS );

	return $result;
}

/**
 * Normalizza la risposta 17track in un array pulito per il frontend.
 *
 * Campi 17track:
 *  z0 = ultimo stato { a: testo, b: data, c: codice, d: paese }
 *  z1 = array eventi [ { a: datetime, b: descrizione, c: location } ]
 *
 * Codici status (z0.c):
 *  0=Pending, 10=NotFound, 20=Pickup, 30=InTransit, 35=Undelivered, 40=Delivered
 */
function shopforge_17track_normalize( string $number, array $track ): array {
	$events_raw  = $track['z1'] ?? [];
	$latest      = $track['z0'] ?? [];
	$status_code = intval( $latest['c'] ?? 0 );

	$status_labels = [
		0  => 'In attesa',
		10 => 'Non trovato',
		20 => 'Ritirato',
		30 => 'In transito',
		35 => 'Tentativo fallito',
		40 => 'Consegnato',
		50 => 'Scaduto',
		60 => 'Allerta',
	];

	$events = array_map( function ( $e ) {
		try {
			$dt = new DateTime( $e['a'] ?? 'now' );
		} catch ( Exception $ex ) {
			$dt = new DateTime();
		}
		return [
			'date'        => $dt->format( 'd/m/Y' ),
			'time'        => $dt->format( 'H:i' ),
			'description' => sanitize_text_field( $e['b'] ?? '' ),
			'location'    => sanitize_text_field( $e['c'] ?? '' ),
			'timestamp'   => $dt->getTimestamp(),
		];
	}, $events_raw );

	// Ordina dal più recente al più vecchio
	usort( $events, fn( $a, $b ) => $b['timestamp'] - $a['timestamp'] );

	return [
		'tracking_number' => $number,
		'status'          => $status_labels[ $status_code ] ?? ( $latest['a'] ?? 'Sconosciuto' ),
		'status_code'     => $status_code,
		'events'          => $events,
		'updated_at'      => ( new DateTime() )->format( 'd/m/Y \a\l\l\e H:i' ),
	];
}


// =============================================================================
// REST API — GET /wp-json/shopforge/v1/tracking?order_id=X
// =============================================================================

add_action( 'rest_api_init', function () {
	register_rest_route( 'shopforge/v1', '/tracking', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'shopforge_tracking_rest_handler',
		'permission_callback' => 'shopforge_tracking_rest_permission',
		'args'                => [
			'order_id' => [
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		],
	] );

	// Webhook 17track (push updates, opzionale)
	register_rest_route( 'shopforge/v1', '/tracking-update', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'shopforge_tracking_webhook_handler',
		'permission_callback' => '__return_true',
	] );
} );

function shopforge_tracking_rest_permission( WP_REST_Request $request ): bool {
	$order_id = $request->get_param( 'order_id' );
	$order    = wc_get_order( $order_id );
	if ( ! $order ) return false;

	if ( current_user_can( 'edit_shop_orders' ) ) return true;

	$user_id = get_current_user_id();
	return $user_id > 0 && (int) $order->get_customer_id() === $user_id;
}

function shopforge_tracking_rest_handler( WP_REST_Request $request ): WP_REST_Response {
	$order_id = $request->get_param( 'order_id' );
	$order    = wc_get_order( $order_id );

	$tracking_number = $order->get_meta( '_shopforge_tracking_number' );
	$carrier_name    = $order->get_meta( '_shopforge_tracking_carrier' );

	if ( ! $tracking_number ) {
		return new WP_REST_Response( [
			'success' => false,
			'message' => 'Numero di tracking non ancora disponibile.',
		], 200 );
	}

	$data = shopforge_17track_get( $tracking_number );

	if ( $data === false ) {
		return new WP_REST_Response( [
			'success' => false,
			'message' => 'Impossibile recuperare il tracking al momento. Riprova tra qualche minuto.',
		], 200 );
	}

	$data['success']      = true;
	$data['carrier_name'] = $carrier_name;

	return new WP_REST_Response( $data, 200 );
}

/**
 * Webhook 17track: invalida la cache quando arriva un aggiornamento push.
 */
function shopforge_tracking_webhook_handler( WP_REST_Request $request ): WP_REST_Response {
	$body = $request->get_json_params();

	$number = $body['data']['number'] ?? ( $body['number'] ?? '' );
	if ( $number ) {
		delete_transient( 'shopforge_track_' . md5( $number ) );
	}

	return new WP_REST_Response( [ 'ok' => true ], 200 );
}


// =============================================================================
// FRONTEND — Widget tracking nella pagina vedi-ordine
// =============================================================================

/**
 * Stampa il widget solo se l'ordine ha un tracking number.
 * Priority 10 = dopo il tracker step (priority 5) ma prima della tabella prodotti.
 */
add_action( 'woocommerce_order_details_before_order_table', function ( WC_Order $order ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) return;

	$tracking_number = $order->get_meta( '_shopforge_tracking_number' );
	$carrier_name    = $order->get_meta( '_shopforge_tracking_carrier' );

	if ( ! $tracking_number ) return;

	$rest_url = rest_url( 'shopforge/v1/tracking' );
	$order_id = $order->get_id();
	$nonce    = wp_create_nonce( 'wp_rest' );
	?>
	<div class="shopforge-shiptrack" id="shopforge-shiptrack">
		<div class="shopforge-shiptrack__header">
			<span class="shopforge-shiptrack__icon">
				<i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
			</span>
			<div class="shopforge-shiptrack__title-group">
				<p class="shopforge-shiptrack__title">Tracciamento spedizione</p>
				<p class="shopforge-shiptrack__meta">
					<?php if ( $carrier_name ) : ?>
						<strong><?php echo esc_html( $carrier_name ); ?></strong> ·
					<?php endif; ?>
					<code><?php echo esc_html( $tracking_number ); ?></code>
				</p>
			</div>
			<span class="shopforge-shiptrack__badge" id="shopforge-st-badge" aria-live="polite">—</span>
		</div>
		<div class="shopforge-shiptrack__body" id="shopforge-st-body">
			<div class="shopforge-shiptrack__loading">
				<span class="shopforge-st-spinner" aria-hidden="true"></span>
				Caricamento tracking…
			</div>
		</div>
	</div>

	<script>
	(function () {
		'use strict';

		var body  = document.getElementById('shopforge-st-body');
		var badge = document.getElementById('shopforge-st-badge');
		if (!body || !badge) return;

		var STATUS_CLASS = {
			0:  'pending',
			10: 'pending',
			20: 'pickup',
			30: 'transit',
			35: 'alert',
			40: 'delivered',
			50: 'pending',
			60: 'alert'
		};

		fetch('<?php echo esc_url( $rest_url ); ?>?order_id=<?php echo esc_js( $order_id ); ?>', {
			headers: { 'X-WP-Nonce': '<?php echo esc_js( $nonce ); ?>' }
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			if (!data.success) {
				body.innerHTML = '<p class="shopforge-st-empty">' +
					esc(data.message || 'Tracking non disponibile.') + '</p>';
				return;
			}

			// --- Badge ---
			var sc = data.status_code || 0;
			badge.textContent = data.status;
			badge.className = 'shopforge-shiptrack__badge shopforge-st-badge--' + (STATUS_CLASS[sc] || 'pending');

			if (!data.events || !data.events.length) {
				body.innerHTML = '<p class="shopforge-st-empty">Nessun aggiornamento disponibile ancora. ' +
					'Il corriere potrebbe impiegare qualche ora ad aggiornare lo stato.</p>';
				return;
			}

			// --- Timeline ---
			var html = '<ul class="shopforge-st-timeline" role="list">';
			data.events.forEach(function (ev, i) {
				html += '<li class="shopforge-st-event' + (i === 0 ? ' is-latest' : '') + '" role="listitem">' +
					'<div class="shopforge-st-event__dot" aria-hidden="true"></div>' +
					'<div class="shopforge-st-event__content">' +
						'<span class="shopforge-st-event__time">' + esc(ev.date) + ' · ' + esc(ev.time) + '</span>' +
						'<span class="shopforge-st-event__desc">' + esc(ev.description) + '</span>' +
						(ev.location
							? '<span class="shopforge-st-event__loc"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' + esc(ev.location) + '</span>'
							: '') +
					'</div>' +
				'</li>';
			});
			html += '</ul>';
			html += '<p class="shopforge-st-footer">Aggiornato <?php echo esc_js( '' ); ?>' +
				'<span id="shopforge-st-updated">' + esc(data.updated_at) + '</span></p>';

			body.innerHTML = html;
		})
		.catch(function () {
			body.innerHTML = '<p class="shopforge-st-empty">Errore nel caricamento del tracking.</p>';
		});

		function esc(str) {
			if (!str) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}
	})();
	</script>
	<?php
}, 10 );


// =============================================================================
// CSS — Widget tracking (iniettato solo nella pagina vedi-ordine)
// =============================================================================

add_action( 'wp_head', function () {
	if ( ! is_wc_endpoint_url( 'view-order' ) ) return;
	?>
	<style id="shopforge-shiptrack-css">

	/* ---- Contenitore ---- */
	.shopforge-shiptrack {
		margin-bottom: 20px;
		background: #fff;
		border: 1px solid var(--shopforge-border);
		border-radius: var(--shopforge-radius);
		box-shadow: var(--shopforge-shadow);
		overflow: hidden;
	}

	/* ---- Header ---- */
	.shopforge-shiptrack__header {
		display: flex;
		align-items: center;
		gap: 14px;
		padding: 18px 22px;
		border-bottom: 1px solid var(--shopforge-border-soft);
		background: var(--shopforge-bg-soft);
	}
	.shopforge-shiptrack__icon {
		width: 40px; height: 40px;
		background: var(--shopforge-primary);
		border-radius: 10px;
		display: flex; align-items: center; justify-content: center;
		color: #fff; font-size: 18px; flex-shrink: 0;
	}
	.shopforge-shiptrack__title-group { flex: 1; min-width: 0; }
	.shopforge-shiptrack__title {
		margin: 0;
		font-size: 12px; font-weight: 800;
		text-transform: uppercase; letter-spacing: .06em;
		color: var(--shopforge-text-main);
	}
	.shopforge-shiptrack__meta {
		margin: 4px 0 0;
		font-size: 12px;
		color: var(--shopforge-text-muted);
	}
	.shopforge-shiptrack__meta code {
		font-family: monospace; font-size: 11px;
		background: var(--shopforge-border-soft);
		padding: 1px 6px; border-radius: 4px;
		color: var(--shopforge-text-main);
	}

	/* ---- Badge stato ---- */
	.shopforge-shiptrack__badge {
		margin-left: auto; flex-shrink: 0;
		padding: 4px 13px;
		border-radius: 999px;
		font-size: 12px; font-weight: 700;
		background: var(--shopforge-bg-soft);
		color: var(--shopforge-text-muted);
		border: 1px solid var(--shopforge-border);
		transition: background .25s, color .25s;
	}
	.shopforge-st-badge--pending   { background:#F3F4F6; color:#4B5563; border-color:#E5E7EB; }
	.shopforge-st-badge--pickup    { background:#EFF6FF; color:#1D4ED8; border-color:#BFDBFE; }
	.shopforge-st-badge--transit   { background:#DBEAFE; color:#1565C0; border-color:#93C5FD; }
	.shopforge-st-badge--delivered { background:#DCFCE7; color:#15803D; border-color:#86EFAC; }
	.shopforge-st-badge--alert     { background:#FEF3C7; color:#B45309; border-color:#FDE68A; }

	/* ---- Body ---- */
	.shopforge-shiptrack__body { padding: 22px; }

	/* ---- Loading spinner ---- */
	.shopforge-shiptrack__loading {
		display: flex; align-items: center; gap: 10px;
		color: var(--shopforge-text-muted); font-size: 13px;
	}
	.shopforge-st-spinner {
		display: inline-block;
		width: 16px; height: 16px;
		border: 2px solid var(--shopforge-border);
		border-top-color: var(--shopforge-primary);
		border-radius: 50%;
		animation: shopforge-spin .7s linear infinite;
		flex-shrink: 0;
	}
	@keyframes shopforge-spin { to { transform: rotate(360deg); } }

	/* ---- Timeline ---- */
	.shopforge-st-timeline {
		list-style: none; margin: 0; padding: 0;
		position: relative;
	}
	.shopforge-st-timeline::before {
		content: "";
		position: absolute; left: 7px; top: 10px; bottom: 10px;
		width: 2px;
		background: var(--shopforge-border-soft);
	}
	.shopforge-st-event {
		display: flex; gap: 16px;
		padding-bottom: 20px;
		position: relative;
	}
	.shopforge-st-event:last-child { padding-bottom: 0; }

	.shopforge-st-event__dot {
		width: 16px; height: 16px;
		flex-shrink: 0;
		border-radius: 50%;
		background: var(--shopforge-border);
		border: 2px solid #fff;
		box-shadow: 0 0 0 2px var(--shopforge-border);
		margin-top: 3px;
		position: relative; z-index: 1;
		transition: background .2s, box-shadow .2s;
	}
	.shopforge-st-event.is-latest .shopforge-st-event__dot {
		background: var(--shopforge-primary);
		box-shadow: 0 0 0 2px var(--shopforge-primary);
	}

	.shopforge-st-event__content {
		display: flex; flex-direction: column; gap: 3px;
		min-width: 0;
	}
	.shopforge-st-event__time {
		font-size: 11px; font-weight: 600;
		color: var(--shopforge-text-muted);
	}
	.shopforge-st-event.is-latest .shopforge-st-event__time { color: var(--shopforge-primary); }

	.shopforge-st-event__desc {
		font-size: 13px; font-weight: 500;
		color: var(--shopforge-text-main);
		line-height: 1.4;
	}
	.shopforge-st-event.is-latest .shopforge-st-event__desc { font-weight: 700; }

	.shopforge-st-event__loc {
		font-size: 11px;
		color: var(--shopforge-text-muted);
	}
	.shopforge-st-event__loc i { font-size: 10px; }

	/* ---- Footer / vuoto ---- */
	.shopforge-st-footer {
		margin: 18px 0 0;
		font-size: 11px;
		color: var(--shopforge-text-muted);
		text-align: right;
	}
	.shopforge-st-empty {
		margin: 0;
		font-size: 13px;
		color: var(--shopforge-text-muted);
		line-height: 1.5;
	}

	/* ---- Responsive ---- */
	@media (max-width: 600px) {
		.shopforge-shiptrack__header { padding: 14px 16px; gap: 10px; }
		.shopforge-shiptrack__body   { padding: 16px; }
		.shopforge-shiptrack__badge  { display: none; } /* troppo stretto: mostra solo nella timeline */
	}

	</style>
	<?php
} );
