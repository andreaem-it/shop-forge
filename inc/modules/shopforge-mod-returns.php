<?php
/**
 * Modulo: Resi e rimborsi
 *
 * Conforme a D.Lgs. 209/2025 — Art. 54-bis Codice del Consumo
 * Obbligo "pulsante di recesso" in vigore dal 19 giugno 2026.
 *
 * Flusso:
 *  1. Pulsante "Recedi dal contratto" nella pagina dettaglio ordine
 *     (visibile solo entro 14 gg dalla consegna, solo ordini B2C)
 *  2. Modal Step 1 — selezione prodotti, motivo, metodo rimborso, note
 *  3. Modal Step 2 — riepilogo dichiarazione di recesso + conferma esplicita
 *  4. Submit AJAX → salva in meta ordine + email admin + ricevuta cliente
 *  5. Storico resi nella sezione "Resi e rimborsi" dell'account
 *  6. Metabox admin nell'ordine con gestione stato
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// HELPER: finestra di recesso (configurabile, default 14 giorni)
// =============================================================================

/**
 * Restituisce il numero di giorni configurato per la finestra di recesso.
 * Modificabile da wp-admin → WooCommerce → ShopForge Moduli → Configurazione.
 */
function shopforge_get_return_window_days(): int {
	return max( 1, (int) get_option( 'shopforge_return_window_days', 14 ) );
}

/**
 * Restituisce l'URL della pagina contatti configurata (per il messaggio di scadenza).
 */
function shopforge_get_contact_url(): string {
	return (string) get_option( 'shopforge_contact_url', '' );
}

/**
 * Determina la data di consegna di un ordine.
 * Priorità: data 17track consegnato → data completamento WC → null.
 */
function shopforge_get_delivery_date( WC_Order $order ): ?DateTime {
	// 1. Controlla i dati 17track in cache
	$tracking_number = $order->get_meta( '_shopforge_tracking_number' );
	if ( $tracking_number ) {
		$cached = get_transient( 'shopforge_track_' . md5( $tracking_number ) );
		if ( $cached && ( $cached['status_code'] ?? 0 ) >= 40 ) {
			// Consegnato: usa la data dell'ultimo evento
			$events = $cached['events'] ?? [];
			if ( ! empty( $events[0]['timestamp'] ) ) {
				$dt = new DateTime();
				$dt->setTimestamp( $events[0]['timestamp'] );
				return $dt;
			}
		}
	}

	// 2. Data completamento ordine WooCommerce
	$date_completed = $order->get_date_completed();
	if ( $date_completed ) {
		return new DateTime( '@' . $date_completed->getTimestamp() );
	}

	return null;
}

/**
 * Verifica che l'ordine sia ancora entro la finestra di recesso configurata.
 * Se la data di consegna non è determinabile, si usa la data dell'ordine.
 * (Meglio mostrare in eccesso che non mostrare — rischio legale opposto.)
 */
function shopforge_is_within_recesso_window( WC_Order $order ): bool {
	$status = $order->get_status();

	// Ordini definitivamente chiusi: nessun recesso possibile
	if ( in_array( $status, [ 'cancelled', 'failed', 'refunded' ], true ) ) {
		return false;
	}

	// Ordini non ancora consegnati (pending, on-hold, processing):
	// la merce non è arrivata → il cliente può sempre recedere/annullare
	if ( in_array( $status, [ 'pending', 'on-hold', 'processing' ], true ) ) {
		return true;
	}

	$window = shopforge_get_return_window_days();

	$delivery = shopforge_get_delivery_date( $order );
	if ( ! $delivery ) {
		// Data consegna sconosciuta: usa data ordine con margine doppio
		$order_date = $order->get_date_created();
		$days_since = $order_date
			? ( time() - $order_date->getTimestamp() ) / DAY_IN_SECONDS
			: 999;
		return $days_since <= ( $window * 2 );
	}

	$days_since = ( time() - $delivery->getTimestamp() ) / DAY_IN_SECONDS;
	return $days_since <= $window;
}

/**
 * Verifica se un ordine è B2C (consumatore finale).
 * Controlla la presenza di partita IVA nei meta comuni dei plugin italiani.
 */
function shopforge_is_b2c_order( WC_Order $order ): bool {
	$piva_keys = [
		'_billing_vat',
		'billing_vat',
		'_vat_number',
		'_billing_piva',
		'billing_piva',
		'_wcj_eu_vat_number',
	];
	foreach ( $piva_keys as $key ) {
		$val = $order->get_meta( $key );
		if ( $val && strlen( trim( $val ) ) > 3 ) {
			return false; // ha P.IVA → B2B
		}
	}
	return true;
}

/**
 * Controlla se esiste già una richiesta di recesso per questo ordine.
 */
function shopforge_has_active_return( WC_Order $order ): bool {
	$returns = $order->get_meta( '_shopforge_returns' ) ?: [];
	foreach ( $returns as $r ) {
		if ( ! in_array( $r['status'] ?? '', [ 'rejected' ], true ) ) {
			return true;
		}
	}
	return false;
}


// =============================================================================
// Enqueue CSS + JS returns (view-order page only)
// =============================================================================

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_account_page() ) return;
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	// CSS caricato su view-order (card + modal) e sull'endpoint lista resi
	if ( is_wc_endpoint_url( 'view-order' ) || is_wc_endpoint_url( 'shopforge-returns' ) ) {
		wp_enqueue_style(
			'shopforge-returns',
			SHOPFORGE_URL . 'assets/css/shopforge-returns.css',
			[],
			SHOPFORGE_VERSION
		);
	}
	// JS solo su view-order (modal recesso)
	if ( is_wc_endpoint_url( 'view-order' ) ) {
		wp_register_script(
			'shopforge-returns',
			SHOPFORGE_URL . 'assets/js/shopforge-returns.js',
			[],
			SHOPFORGE_VERSION,
			true
		);
	}
} );


// =============================================================================
// CARD RECESSO — pagina dettaglio ordine (before table, priority 12)
// =============================================================================

add_action( 'woocommerce_order_details_before_order_table', function ( WC_Order $order ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) return;
	if ( ! shopforge_is_b2c_order( $order ) ) return;

	$within_window = shopforge_is_within_recesso_window( $order );
	$order_id      = $order->get_id();
	$order_number  = $order->get_order_number();
	$order_date    = $order->get_date_created()
		? $order->get_date_created()->date_i18n( 'd/m/Y' )
		: '';
	$customer      = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
	$nonce         = wp_create_nonce( 'shopforge_return_' . $order_id );
	$has_return    = shopforge_has_active_return( $order );
	$window        = shopforge_get_return_window_days();
	$contact_url   = shopforge_get_contact_url();

	$delivery   = shopforge_get_delivery_date( $order );
	$days_left  = $delivery
		? max( 0, $window - (int) floor( ( time() - $delivery->getTimestamp() ) / DAY_IN_SECONDS ) )
		: null;

	// Prodotti dell'ordine
	$items_data = [];
	foreach ( $order->get_items() as $item_id => $item ) {
		$product = $item->get_product();
		$thumb   = $product ? get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) : '';
		$items_data[] = [
			'id'    => $item_id,
			'name'  => $item->get_name(),
			'price' => wc_price( $item->get_total() ),
			'thumb' => $thumb,
		];
	}
	?>

	<?php if ( $has_return ) :
		// Recupera l'ultimo reso attivo per mostrare stato e risposta
		$_all_returns = $order->get_meta( '_shopforge_returns' ) ?: [];
		$_last_return = null;
		foreach ( array_reverse( $_all_returns ) as $_r ) {
			if ( ! in_array( $_r['status'] ?? '', [ 'rejected' ], true ) ) { $_last_return = $_r; break; }
		}
		$_ret_status_labels = [
			'pending'    => [ 'label' => 'Ricevuta',       'color' => '#854D0E', 'bg' => '#FEF9C3' ],
			'processing' => [ 'label' => 'In lavorazione', 'color' => '#1E40AF', 'bg' => '#DBEAFE' ],
			'approved'   => [ 'label' => 'Approvata',      'color' => '#166534', 'bg' => '#DCFCE7' ],
			'refunded'   => [ 'label' => 'Rimborsata',     'color' => '#065F46', 'bg' => '#D1FAE5' ],
		];
		$_ret_st = $_ret_status_labels[ $_last_return['status'] ?? 'pending' ] ?? $_ret_status_labels['pending'];
	?>
	<div class="shopforge-recesso-card shopforge-recesso-card--done">
		<div class="shopforge-recesso-card__icon">
			<i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
		</div>
		<div class="shopforge-recesso-card__body">
			<p class="shopforge-recesso-card__title">Richiesta di recesso inviata</p>
			<p class="shopforge-recesso-card__text">
				<?php if ( $_last_return ) : ?>
				Rif. <strong><?php echo esc_html( $_last_return['ref'] ); ?></strong> —
				Stato: <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:12px;font-weight:700;background:<?php echo esc_attr( $_ret_st['bg'] ); ?>;color:<?php echo esc_attr( $_ret_st['color'] ); ?>">
					<?php echo esc_html( $_ret_st['label'] ); ?>
				</span>
				<?php if ( ! empty( $_last_return['reply'] ) ) : ?>
				<br><span style="display:block;margin-top:8px;padding:7px 10px;background:#EFF6FF;border-left:3px solid #3B82F6;border-radius:0 4px 4px 0;font-size:13px;">
					<strong style="font-size:11px;color:#1D4ED8;display:block;margin-bottom:2px;">Messaggio dal negozio:</strong>
					<?php echo esc_html( $_last_return['reply'] ); ?>
				</span>
				<?php endif; ?>
				<?php endif; ?>
			</p>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url('shopforge-returns') ); ?>" class="shopforge-recesso-btn shopforge-recesso-btn--secondary">
				<i class="fa-solid fa-list" aria-hidden="true"></i>
				Tutti i miei resi
			</a>
		</div>
	</div>

	<?php elseif ( ! $within_window && ! in_array( $order->get_status(), [ 'pending', 'on-hold', 'cancelled', 'failed', 'refunded' ], true ) ) : ?>
	<!-- Finestra di recesso scaduta -->
	<div class="shopforge-recesso-card shopforge-recesso-card--expired">
		<div class="shopforge-recesso-card__icon">
			<i class="fa-solid fa-clock" aria-hidden="true"></i>
		</div>
		<div class="shopforge-recesso-card__body">
			<p class="shopforge-recesso-card__title">Termine di recesso scaduto</p>
			<p class="shopforge-recesso-card__text">
				Il periodo di <?php echo esc_html( $window ); ?> giorni per esercitare il diritto di recesso è scaduto.
				Per qualsiasi necessità, contatta il nostro negozio.
			</p>
		</div>
		<?php if ( $contact_url ) : ?>
		<a href="<?php echo esc_url( $contact_url ); ?>" class="shopforge-recesso-btn shopforge-recesso-btn--secondary">
			<i class="fa-solid fa-headset" aria-hidden="true"></i>
			Contatta il negozio
		</a>
		<?php else : ?>
		<span class="shopforge-recesso-expired-note">
			<i class="fa-solid fa-headset" aria-hidden="true"></i>
			Contatta il negozio per assistenza
		</span>
		<?php endif; ?>
	</div>

	<?php else : ?>

	<div class="shopforge-recesso-card" id="shopforge-recesso-card">
		<div class="shopforge-recesso-card__icon">
			<i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
		</div>
		<div class="shopforge-recesso-card__body">
			<p class="shopforge-recesso-card__title">Diritto di recesso</p>
			<p class="shopforge-recesso-card__text">
				Hai il diritto di recedere dal contratto entro <?php echo esc_html( $window ); ?> giorni dalla ricezione della merce, senza fornire alcuna motivazione.
				<?php if ( $days_left !== null ) : ?>
				<strong>Hai ancora <?php echo $days_left; ?> <?php echo $days_left === 1 ? 'giorno' : 'giorni'; ?>.</strong>
				<?php endif; ?>
			</p>
		</div>
		<button type="button" class="shopforge-recesso-btn" id="shopforge-open-recesso"
		        data-order="<?php echo esc_attr( $order_id ); ?>"
		        data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
			Recedi dal contratto
		</button>
	</div>

	<!-- ---- Modal recesso ---- -->
	<div class="shopforge-modal-backdrop" id="shopforge-recesso-backdrop" aria-hidden="true" style="display:none">
		<div class="shopforge-modal shopforge-recesso-modal" role="dialog" aria-modal="true" aria-labelledby="shopforge-recesso-title">

			<!-- Header -->
			<div class="shopforge-modal__header">
				<h2 class="shopforge-modal__title" id="shopforge-recesso-title">
					<i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
					Recesso dal contratto
				</h2>
				<button type="button" class="shopforge-modal__close" id="shopforge-close-recesso" aria-label="Chiudi">
					<i class="fa-solid fa-xmark" aria-hidden="true"></i>
				</button>
			</div>

			<!-- Step indicator -->
			<div class="shopforge-steps">
				<div class="shopforge-step is-active" id="shopforge-step-dot-1">
					<span class="shopforge-step__num">1</span>
					<span class="shopforge-step__label">Dettagli</span>
				</div>
				<div class="shopforge-steps__line"></div>
				<div class="shopforge-step" id="shopforge-step-dot-2">
					<span class="shopforge-step__num">2</span>
					<span class="shopforge-step__label">Conferma</span>
				</div>
			</div>

			<div class="shopforge-modal__body">

				<!-- STEP 1 -->
				<div id="shopforge-recesso-step1">
					<p class="shopforge-modal__ref">
						Ordine <strong>#<?php echo esc_html( $order_number ); ?></strong>
						del <?php echo esc_html( $order_date ); ?>
					</p>

					<div class="shopforge-field">
						<label>Prodotti da restituire</label>
						<ul class="shopforge-product-list">
							<?php foreach ( $items_data as $item ) : ?>
							<li class="shopforge-product-list__item">
								<label class="shopforge-product-row">
									<input type="checkbox" class="shopforge-ret-prod"
									       value="<?php echo esc_attr( $item['name'] ); ?>">
									<span class="shopforge-product-row__check-icon">
										<i class="fa-solid fa-check" aria-hidden="true"></i>
									</span>
									<span class="shopforge-product-row__thumb">
										<?php if ( $item['thumb'] ) : ?>
											<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" width="50" height="50" loading="lazy">
										<?php else : ?>
											<span class="shopforge-product-row__no-img"><i class="fa-solid fa-box"></i></span>
										<?php endif; ?>
									</span>
									<span class="shopforge-product-row__name">
										<span class="shopforge-product-row__title"><?php echo esc_html( $item['name'] ); ?></span>
										<span class="shopforge-product-row__price"><?php echo $item['price']; ?></span>
									</span>
								</label>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="shopforge-field">
						<label for="shopforge-ret-reason">Motivo del recesso</label>
						<select id="shopforge-ret-reason">
							<option value="">— Seleziona —</option>
							<option value="Ripensamento">Ripensamento (non sono più interessato)</option>
							<option value="Prodotto non conforme alla descrizione">Prodotto non conforme alla descrizione</option>
							<option value="Prodotto difettoso o danneggiato">Prodotto difettoso o danneggiato</option>
							<option value="Prodotto errato ricevuto">Prodotto errato ricevuto</option>
							<option value="Ritardo nella consegna">Ritardo nella consegna</option>
							<option value="Altro">Altro</option>
						</select>
					</div>

					<div class="shopforge-field">
						<label>Metodo di rimborso preferito</label>
						<div class="shopforge-radio-group">
							<label class="shopforge-radio">
								<input type="radio" name="shopforge-ret-refund" value="Rimborso sul metodo di pagamento originale" checked>
								<span class="shopforge-radio__box"></span>
								Rimborso sul metodo di pagamento originale
							</label>
							<label class="shopforge-radio">
								<input type="radio" name="shopforge-ret-refund" value="Buono sconto">
								<span class="shopforge-radio__box"></span>
								Buono sconto da utilizzare sul sito
							</label>
						</div>
					</div>

					<div class="shopforge-field">
						<label for="shopforge-ret-notes">Note aggiuntive <span style="font-weight:400;text-transform:none">(opzionale)</span></label>
						<textarea id="shopforge-ret-notes" rows="3" placeholder="Inserisci ulteriori dettagli…"></textarea>
					</div>

					<div class="shopforge-field shopforge-form-group--file">
						<label for="shopforge-ret-files">Foto o documenti <span style="font-weight:400;text-transform:none">(opzionale — max 5 MB per file)</span></label>
						<input type="file" id="shopforge-ret-files" name="ret_files[]"
						       multiple accept="image/*,.pdf" class="shopforge-file-input">
						<div id="shopforge-ret-file-preview" class="shopforge-file-preview"></div>
					</div>

					<p class="shopforge-field-note">
						Nella schermata successiva potrai leggere il testo completo della tua dichiarazione di recesso prima di confermare.
					</p>

					<p class="shopforge-ret-error" id="shopforge-ret-error-1" style="display:none"></p>

					<button type="button" class="shopforge-modal__submit" id="shopforge-ret-next">
						Avanti — Rivedi e conferma
						<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
					</button>
				</div>

				<!-- STEP 2 — riepilogo dichiarazione -->
				<div id="shopforge-recesso-step2" style="display:none">
					<div class="shopforge-declaration-box" id="shopforge-declaration-text">
						<!-- Compilato da JS -->
					</div>

					<div class="shopforge-declaration-notice">
						<i class="fa-solid fa-circle-info" aria-hidden="true"></i>
						Confermando, invieremo questa dichiarazione al venditore e riceverai una ricevuta automatica via email con data e ora della trasmissione.
					</div>

					<p class="shopforge-ret-error" id="shopforge-ret-error-2" style="display:none"></p>

					<div class="shopforge-step2-actions">
						<button type="button" class="shopforge-btn shopforge-btn--ghost" id="shopforge-ret-back">
							<i class="fa-solid fa-arrow-left"></i> Indietro
						</button>
						<button type="button" class="shopforge-modal__submit shopforge-modal__submit--danger" id="shopforge-ret-confirm">
							<span id="shopforge-ret-label">Confermo il recesso</span>
							<span class="shopforge-st-spinner" id="shopforge-ret-spinner" style="display:none"></span>
						</button>
					</div>
				</div>

				<!-- SUCCESS -->
				<div id="shopforge-recesso-success" style="display:none" class="shopforge-ticket-success">
					<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
					<p class="shopforge-ts__title">Recesso registrato</p>
					<p class="shopforge-ts__text" id="shopforge-ret-success-text"></p>
					<button type="button" class="shopforge-modal__close-btn" id="shopforge-close-recesso-ok">
						Chiudi
					</button>
				</div>

			</div><!-- /.shopforge-modal__body -->
		</div>
	</div><!-- /.shopforge-modal-backdrop -->

	<?php
	wp_enqueue_script( 'shopforge-returns' );
	wp_localize_script( 'shopforge-returns', 'shopforgeRecesso', [
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'orderId'   => $order_id,
		'nonce'     => $nonce,
		'customer'  => $customer,
		'orderNum'  => '#' . $order_number,
		'orderDate' => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
		'storeName' => get_bloginfo( 'name' ),
	] );
	?>

	<?php endif; // has_return ?>
	<?php
}, 12 );


// =============================================================================
// AJAX — Invia richiesta di recesso
// =============================================================================

add_action( 'wp_ajax_shopforge_submit_return', 'shopforge_submit_return_handler' );

function shopforge_submit_return_handler(): void {
	$order_id = absint( $_POST['order_id'] ?? 0 );

	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_return_' . $order_id ) ) {
		wp_send_json_error( 'Sessione scaduta. Ricarica la pagina e riprova.' );
	}

	if ( function_exists( 'shopforge_check_rate_limit' )
		 && ! shopforge_check_rate_limit( 'submit_return', 90 ) ) {
		wp_send_json_error( 'Hai già inviato una richiesta di recesso di recente. Attendi qualche minuto e riprova.' );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) wp_send_json_error( 'Ordine non trovato.' );

	$user_id = get_current_user_id();
	if ( ! $user_id || (int) $order->get_customer_id() !== $user_id ) {
		wp_send_json_error( 'Accesso non autorizzato.' );
	}

	if ( ! shopforge_is_within_recesso_window( $order ) ) {
		wp_send_json_error( sprintf( 'Il termine di %d giorni per esercitare il recesso è scaduto.', shopforge_get_return_window_days() ) );
	}

	if ( shopforge_has_active_return( $order ) ) {
		wp_send_json_error( 'Esiste già una richiesta di recesso per questo ordine.' );
	}

	$products    = array_map( 'sanitize_text_field', (array) ( $_POST['products'] ?? [] ) );
	$reason      = sanitize_text_field( $_POST['reason'] ?? '' );
	$refund      = sanitize_text_field( $_POST['refund'] ?? '' );
	$notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );
	$declaration = sanitize_textarea_field( $_POST['declaration'] ?? '' );
	$products    = array_filter( $products );

	if ( ! $reason || empty( $products ) ) {
		wp_send_json_error( 'Dati mancanti o non validi.' );
	}

	// Gestione allegati (opzionale)
	$attachment_urls = [];
	if ( ! empty( $_FILES['ret_files']['name'][0] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ];
		$max_size      = 5 * 1024 * 1024;
		foreach ( $_FILES['ret_files']['name'] as $i => $name ) {
			$fa = [
				'name'     => $name,
				'type'     => $_FILES['ret_files']['type'][ $i ],
				'tmp_name' => $_FILES['ret_files']['tmp_name'][ $i ],
				'error'    => $_FILES['ret_files']['error'][ $i ],
				'size'     => $_FILES['ret_files']['size'][ $i ],
			];
			if ( $fa['error'] !== UPLOAD_ERR_OK || $fa['size'] > $max_size ) continue;
			if ( ! in_array( mime_content_type( $fa['tmp_name'] ), $allowed_types, true ) ) continue;
			$aid = media_handle_sideload( $fa, $order_id );
			if ( ! is_wp_error( $aid ) ) {
				$attachment_urls[] = wp_get_attachment_url( $aid );
			}
		}
	}

	$ref = 'REC-' . strtoupper( substr( md5( $order_id . time() ), 0, 8 ) );
	$now = current_time( 'mysql' );

	// 1. Salva nel meta ordine
	$returns   = $order->get_meta( '_shopforge_returns' ) ?: [];
	$returns[] = [
		'ref'         => $ref,
		'date'        => $now,
		'products'    => $products,
		'reason'      => $reason,
		'refund'      => $refund,
		'notes'       => $notes,
		'declaration' => $declaration,
		'attachments' => $attachment_urls,
		'status'      => 'pending',
	];
	$order->update_meta_data( '_shopforge_returns', $returns );
	$order->save();

	$customer_email = $order->get_billing_email();
	$date_str       = date_i18n( 'd/m/Y \a\l\l\e H:i', strtotime( $now ) );

	// Dati condivisi tra le due email
	$return_email_data = [
		'ref'         => $ref,
		'products'    => $products,
		'reason'      => $reason,
		'refund'      => $refund,
		'notes'       => $notes,
		'declaration' => $declaration,
		'date_str'    => $date_str,
	];

	// 2. Email admin + 3. Ricevuta cliente — tramite classi WooCommerce native
	//    (modificabili da WooCommerce → Impostazioni → Email)
	$mailer = WC()->mailer();
	$emails = $mailer->get_emails();

	if ( isset( $emails['ShopForge_Email_Return_Admin'] ) ) {
		$emails['ShopForge_Email_Return_Admin']->trigger( $order, $return_email_data );
	}
	if ( isset( $emails['ShopForge_Email_Return_Customer'] ) ) {
		$emails['ShopForge_Email_Return_Customer']->trigger( $order, $return_email_data );
	}

	wp_send_json_success( [
		'ref'   => $ref,
		'email' => $customer_email,
	] );
}


// =============================================================================
// ADMIN — Metabox richieste di recesso
// =============================================================================

add_action( 'add_meta_boxes', function () {
	foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
		add_meta_box(
			'shopforge-returns',
			'↩ Richieste di recesso',
			'shopforge_returns_metabox_render',
			$screen,
			'normal',
			'default'
		);
	}
} );

function shopforge_returns_metabox_render( $post_or_order ): void {
	$order   = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
	if ( ! $order ) return;

	$returns = $order->get_meta( '_shopforge_returns' ) ?: [];

	if ( empty( $returns ) ) {
		echo '<p style="color:#646970;font-size:13px;margin:8px 0">Nessuna richiesta di recesso per questo ordine.</p>';
		return;
	}

	$status_map = [
		'pending'    => [ 'label' => 'Ricevuta',      'bg' => '#FEF9C3', 'color' => '#854D0E' ],
		'processing' => [ 'label' => 'In lavorazione','bg' => '#DBEAFE', 'color' => '#1E40AF' ],
		'approved'   => [ 'label' => 'Approvata',     'bg' => '#DCFCE7', 'color' => '#166534' ],
		'refunded'   => [ 'label' => 'Rimborsata',    'bg' => '#D1FAE5', 'color' => '#065F46' ],
		'rejected'   => [ 'label' => 'Rifiutata',     'bg' => '#FEE2E2', 'color' => '#991B1B' ],
	];
	?>

	<table class="shopforge-ret-adm">
		<thead>
			<tr>
				<th>Rif. / Data</th>
				<th>Prodotti / Motivo</th>
				<th>Dichiarazione</th>
				<th>Stato</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $returns as $idx => $ret ) :
			$st    = $status_map[ $ret['status'] ] ?? $status_map['pending'];
			$date  = date_i18n( 'd/m/Y H:i', strtotime( $ret['date'] ) );
		?>
		<tr>
			<td>
				<strong><?php echo esc_html( $ret['ref'] ); ?></strong><br>
				<span style="color:#646970;font-size:11px"><?php echo esc_html( $date ); ?></span>
			</td>
			<td>
				<?php if ( ! empty( $ret['products'] ) ) : ?>
				<ul style="margin:0 0 6px;padding-left:16px">
					<?php foreach ( $ret['products'] as $p ) : ?>
					<li><?php echo esc_html( $p ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<em style="color:#646970"><?php echo esc_html( $ret['reason'] ); ?></em><br>
				<span style="font-size:11px;color:#646970">Rimborso: <?php echo esc_html( $ret['refund'] ); ?></span>
				<?php if ( $ret['notes'] ) : ?>
				<br><span style="font-size:11px;color:#646970">Note: <?php echo esc_html( $ret['notes'] ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<div class="shopforge-ret-adm-decl"><?php echo esc_html( $ret['declaration'] ); ?></div>
			</td>
			<td>
				<span class="shopforge-ret-badge"
				      style="background:<?php echo esc_attr( $st['bg'] ); ?>;color:<?php echo esc_attr( $st['color'] ); ?>">
					<?php echo esc_html( $st['label'] ); ?>
				</span>
				<?php if ( ! empty( $ret['reply'] ) ) : ?>
				<div style="margin:6px 0;padding:5px 8px;background:#f0f7ff;border-left:3px solid #2563eb;border-radius:3px;font-size:11px;">
					<strong>Risposta negozio:</strong> <?php echo esc_html( $ret['reply'] ); ?>
				</div>
				<?php endif; ?>
				<textarea class="shopforge-ret-reply-text" rows="2" placeholder="Messaggio al cliente (opzionale)…"
				          style="width:100%;margin:4px 0;font-size:11px;resize:vertical;"
				><?php echo esc_textarea( $ret['reply'] ?? '' ); ?></textarea>
				<div class="shopforge-ret-adm-status">
					<select class="shopforge-ret-status-sel" data-idx="<?php echo esc_attr( $idx ); ?>">
						<?php foreach ( $status_map as $val => $info ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ret['status'], $val ); ?>>
							<?php echo esc_html( $info['label'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button button-small shopforge-ret-save-st"
					        data-idx="<?php echo esc_attr( $idx ); ?>"
					        data-order="<?php echo esc_attr( $order->get_id() ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce('shopforge_return_status') ); ?>">
						Salva &amp; Notifica
					</button>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php
}

add_action( 'wp_ajax_shopforge_update_return_status', function () {
	check_ajax_referer( 'shopforge_return_status', 'nonce' );
	if ( ! current_user_can( 'edit_shop_orders' ) ) wp_send_json_error();

	$order_id = absint( $_POST['order_id'] ?? 0 );
	$idx      = intval( $_POST['idx'] ?? -1 );
	$valid    = [ 'pending', 'processing', 'approved', 'refunded', 'rejected' ];
	$status   = in_array( $_POST['status'] ?? '', $valid, true ) ? $_POST['status'] : 'pending';
	$reply    = sanitize_textarea_field( $_POST['reply'] ?? '' );

	$order = wc_get_order( $order_id );
	if ( ! $order ) wp_send_json_error();

	$returns = $order->get_meta( '_shopforge_returns' ) ?: [];
	if ( ! isset( $returns[ $idx ] ) ) wp_send_json_error();

	$prev_status = $returns[ $idx ]['status'] ?? 'pending';
	$returns[ $idx ]['status']     = $status;
	$returns[ $idx ]['reply']      = $reply;
	$returns[ $idx ]['reply_date'] = current_time( 'mysql' );
	$order->update_meta_data( '_shopforge_returns', $returns );
	$order->save();

	if ( $status !== $prev_status || $reply ) {
		$mailer    = WC()->mailer();
		$wc_emails = $mailer->get_emails();
		if ( isset( $wc_emails['ShopForge_Email_Return_Status_Update'] ) ) {
			$wc_emails['ShopForge_Email_Return_Status_Update']->trigger( $order, [
				'ref'         => $returns[ $idx ]['ref']    ?? '',
				'status'      => $status,
				'prev_status' => $prev_status,
				'reply'       => $reply,
			] );
		}
	}

	wp_send_json_success();
} );


// =============================================================================
// ENDPOINT — Lista resi nell'account cliente
// =============================================================================

add_action( 'woocommerce_account_shopforge-returns_endpoint', function () {
	shopforge_account_section_header( 'Assistenza e Resi', 'fa-solid fa-headset' );

	$user_id = get_current_user_id();
	$orders  = wc_get_orders( [
		'customer_id' => $user_id,
		'limit'       => -1,
		'return'      => 'objects',
	] );

	// Raccoglie sia ticket che resi da tutti gli ordini
	$all_tickets = [];
	$all_returns = [];
	foreach ( $orders as $order ) {
		$on = $order->get_order_number();
		$oid = $order->get_id();
		foreach ( $order->get_meta( '_shopforge_tickets' ) ?: [] as $t ) {
			$all_tickets[] = array_merge( $t, [ '_order_number' => $on, '_order_id' => $oid ] );
		}
		foreach ( $order->get_meta( '_shopforge_returns' ) ?: [] as $r ) {
			$all_returns[] = array_merge( $r, [ '_order_number' => $on, '_order_id' => $oid ] );
		}
	}

	$has_any = ! empty( $all_tickets ) || ! empty( $all_returns );

	if ( ! $has_any ) {
		shopforge_account_empty_state(
			'fa-solid fa-headset',
			'Nessuna richiesta',
			sprintf( 'Dall\'area ordini puoi aprire un ticket di assistenza o richiedere il recesso (entro %d giorni dalla consegna).', shopforge_get_return_window_days() )
		);
		return;
	}

	// Ordina per data decrescente
	usort( $all_tickets, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );
	usort( $all_returns, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	$ticket_st_labels = [
		'open'   => [ 'label' => 'Aperto',  'class' => 'open' ],
		'closed' => [ 'label' => 'Chiuso',  'class' => 'closed' ],
	];
	$return_st_labels = [
		'pending'    => [ 'label' => 'Ricevuta',       'class' => 'pending' ],
		'processing' => [ 'label' => 'In lavorazione', 'class' => 'processing' ],
		'approved'   => [ 'label' => 'Approvata',      'class' => 'approved' ],
		'refunded'   => [ 'label' => 'Rimborsata',     'class' => 'refunded' ],
		'rejected'   => [ 'label' => 'Rifiutata',      'class' => 'rejected' ],
	];
	?>

	<?php if ( ! empty( $all_tickets ) ) : ?>
	<div class="shopforge-returns-section">
		<h3 class="shopforge-returns-section__title">
			<i class="fa-solid fa-headset" aria-hidden="true"></i>
			Richieste di assistenza
		</h3>
		<div class="shopforge-returns-list">
		<?php foreach ( $all_tickets as $t ) :
			$st   = $ticket_st_labels[ $t['status'] ?? 'open' ] ?? $ticket_st_labels['open'];
			$date = date_i18n( 'd/m/Y \a\l\l\e H:i', strtotime( $t['date'] ) );
		?>
		<div class="shopforge-return-row">
			<div class="shopforge-return-row__head">
				<span class="shopforge-return-row__ref"><?php echo esc_html( $t['subject'] ); ?></span>
				<span class="shopforge-return-badge shopforge-return-badge--<?php echo esc_attr( $st['class'] ); ?>">
					<?php echo esc_html( $st['label'] ); ?>
				</span>
			</div>
			<div class="shopforge-return-row__body">
				<p class="shopforge-return-row__meta">
					Ordine <strong>#<?php echo esc_html( $t['_order_number'] ); ?></strong>
					· <?php echo esc_html( $date ); ?>
				</p>
				<p class="shopforge-return-row__reason"><?php echo esc_html( $t['message'] ); ?></p>
				<?php if ( ! empty( $t['attachments'] ) ) : ?>
				<div class="shopforge-return-row__attachments">
					<strong>Allegati:</strong>
					<?php foreach ( $t['attachments'] as $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="shopforge-attachment-link">
						<i class="fa-solid fa-paperclip" aria-hidden="true"></i> <?php echo esc_html( basename( $url ) ); ?>
					</a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $t['reply'] ) ) : ?>
				<div class="shopforge-return-row__reply">
					<strong>Risposta negozio:</strong> <?php echo esc_html( $t['reply'] ); ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $all_returns ) ) : ?>
	<div class="shopforge-returns-section" <?php if ( ! empty( $all_tickets ) ) echo 'style="margin-top:28px"'; ?>>
		<h3 class="shopforge-returns-section__title">
			<i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
			Richieste di recesso
		</h3>
		<div class="shopforge-returns-list">
		<?php foreach ( $all_returns as $ret ) :
			$st   = $return_st_labels[ $ret['status'] ?? 'pending' ] ?? $return_st_labels['pending'];
			$date = date_i18n( 'd/m/Y \a\l\l\e H:i', strtotime( $ret['date'] ) );
		?>
		<div class="shopforge-return-row">
			<div class="shopforge-return-row__head">
				<span class="shopforge-return-row__ref"><?php echo esc_html( $ret['ref'] ); ?></span>
				<span class="shopforge-return-badge shopforge-return-badge--<?php echo esc_attr( $st['class'] ); ?>">
					<?php echo esc_html( $st['label'] ); ?>
				</span>
			</div>
			<div class="shopforge-return-row__body">
				<p class="shopforge-return-row__meta">
					Ordine <strong>#<?php echo esc_html( $ret['_order_number'] ); ?></strong>
					· <?php echo esc_html( $date ); ?>
				</p>
				<p class="shopforge-return-row__reason"><?php echo esc_html( $ret['reason'] ); ?></p>
				<?php if ( ! empty( $ret['products'] ) ) : ?>
				<p class="shopforge-return-row__products"><?php echo esc_html( implode( ', ', $ret['products'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $ret['attachments'] ) ) : ?>
				<div class="shopforge-return-row__attachments">
					<strong>Allegati:</strong>
					<?php foreach ( $ret['attachments'] as $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="shopforge-attachment-link">
						<i class="fa-solid fa-paperclip" aria-hidden="true"></i> <?php echo esc_html( basename( $url ) ); ?>
					</a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $ret['reply'] ) ) : ?>
				<div class="shopforge-return-row__reply">
					<strong>Risposta negozio:</strong> <?php echo esc_html( $ret['reply'] ); ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php
} );


// =============================================================================
// CSS
// =============================================================================

