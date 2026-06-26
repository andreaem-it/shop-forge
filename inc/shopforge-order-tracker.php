<?php
/**
 * Andrea Emili — Tracker visivo stato ordine
 *
 * Mostra un indicatore a step nella pagina dettaglio ordine
 * (My Account → Visualizza ordine).
 * Step: Ricevuto → Pagato → In Preparazione → Spedito → Consegnato
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// -------------------------------------------------------------------------
// Mappa: stato WooCommerce → livello di avanzamento (1-6)
// 6 = tutti gli step completati (ordine consegnato)
// -------------------------------------------------------------------------

function shopforge_order_tracker_progress( string $status ): int {
	$map = [
		'pending'    => 1, // Ricevuto (in attesa pagamento)
		'on-hold'    => 1, // Ricevuto (in attesa verifica)
		'processing' => 3, // Ricevuto ✓ · Pagato ✓ · In Preparazione ←
		'spedito'    => 4, // + Spedito ←
		'completed'  => 6, // tutti ✓ (progress > 5 → tutti is-completed)
	];
	return $map[ $status ] ?? 1;
}


// -------------------------------------------------------------------------
// Render del tracker
// -------------------------------------------------------------------------

function shopforge_render_order_tracker( WC_Order $order ): void {
	$status   = $order->get_status();
	$progress = shopforge_order_tracker_progress( $status );

	$error_statuses = [ 'cancelled', 'failed', 'refunded' ];
	if ( in_array( $status, $error_statuses, true ) ) {
		$label = wc_get_order_status_name( $status );
		?>
		<div class="shopforge-order-tracker shopforge-order-tracker--error">
			<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
			<span>Ordine <strong><?php echo esc_html( $label ); ?></strong> — nessun avanzamento disponibile.</span>
		</div>
		<?php
		return;
	}

	$steps = [
		[ 'label' => 'Ricevuto',        'icon' => 'fa-solid fa-inbox' ],
		[ 'label' => 'Pagato',           'icon' => 'fa-solid fa-credit-card' ],
		[ 'label' => 'In Preparazione',  'icon' => 'fa-solid fa-box-open' ],
		[ 'label' => 'Spedito',          'icon' => 'fa-solid fa-truck' ],
		[ 'label' => 'Consegnato',       'icon' => 'fa-solid fa-circle-check' ],
	];
	?>
	<div class="shopforge-order-tracker" role="list" aria-label="Stato ordine">
		<p class="shopforge-tracker-header">
			<i class="fa-solid fa-route" aria-hidden="true"></i>
			Stato ordine
		</p>

		<div class="shopforge-tracker-steps">
			<?php foreach ( $steps as $i => $step ) :
				$step_num = $i + 1;

				if ( $step_num < $progress ) {
					$css_class = 'is-completed';
					$aria      = 'Completato';
				} elseif ( $step_num === $progress ) {
					$css_class = 'is-active';
					$aria      = 'In corso';
				} else {
					$css_class = 'is-pending';
					$aria      = 'In attesa';
				}
			?>
				<div class="shopforge-tracker-step <?php echo esc_attr( $css_class ); ?>"
				     role="listitem"
				     aria-label="<?php echo esc_attr( $step['label'] . ': ' . $aria ); ?>">

					<div class="shopforge-tracker-step__bubble">
						<?php if ( 'is-completed' === $css_class ) : ?>
							<i class="fa-solid fa-check" aria-hidden="true"></i>
						<?php else : ?>
							<i class="<?php echo esc_attr( $step['icon'] ); ?>" aria-hidden="true"></i>
						<?php endif; ?>
					</div>

					<span class="shopforge-tracker-step__label"><?php echo esc_html( $step['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}


// -------------------------------------------------------------------------
// Hook: inietta il tracker prima della tabella ordine
// (escludi thank-you page)
// -------------------------------------------------------------------------

add_action( 'woocommerce_order_details_before_order_table', function ( WC_Order $order ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) return;
	// Il tracker è grafico: renderizzato solo se 'styles' è attivo
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles' ) ) return;
	shopforge_render_order_tracker( $order );
}, 5 );


// -------------------------------------------------------------------------
// Enqueue CSS + JS tracker (sostituisce tutti i blocchi wp_head inline)
// -------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_account_page() ) return;
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles' ) ) return;
	wp_enqueue_style(
		'shopforge-tracker',
		SHOPFORGE_URL . 'assets/css/shopforge-tracker.css',
		[],
		SHOPFORGE_VERSION
	);
	wp_register_script(
		'shopforge-tracker',
		SHOPFORGE_URL . 'assets/js/shopforge-tracker.js',
		[],
		SHOPFORGE_VERSION,
		true
	);
} );


// =============================================================================
// Card "Hai bisogno di assistenza?" + Modal ticket — fondo pagina dettaglio ordine
// =============================================================================

add_action( 'woocommerce_order_details_after_order_table', function ( WC_Order $order ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) return;

	$order_id     = $order->get_id();
	$order_number = $order->get_order_number();
	$nonce        = wp_create_nonce( 'shopforge_ticket_' . $order_id );
	?>
	<div class="shopforge-support-card" style="display:flex;align-items:center;gap:16px;padding:16px 20px;margin:20px 0;background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;">
		<div class="shopforge-support-card__icon" style="font-size:24px;color:#0369A1;flex-shrink:0;">
			<i class="fa-solid fa-headset" aria-hidden="true"></i>
		</div>
		<div class="shopforge-support-card__body" style="flex:1;min-width:0;">
			<p class="shopforge-support-card__title" style="margin:0 0 4px;font-weight:700;font-size:14px;">Serve aiuto con questo ordine?</p>
			<p class="shopforge-support-card__text" style="margin:0;font-size:13px;color:#555;">
				Il nostro team è a disposizione per qualsiasi domanda sui prodotti,
				sulla spedizione o per gestire resi e sostituzioni.
			</p>
		</div>
		<button type="button" class="shopforge-support-card__btn"
		        id="shopforge-open-ticket"
		        data-order="<?php echo esc_attr( $order_id ); ?>"
		        data-number="<?php echo esc_attr( $order_number ); ?>"
		        data-nonce="<?php echo esc_attr( $nonce ); ?>"
		        style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#0369A1;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
			Apri Una Richiesta
			<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
		</button>
	</div>

	<!-- Form ticket inline (collassabile) -->
	<div class="shopforge-ticket-inline" id="shopforge-ticket-backdrop" style="display:none">
		<div class="shopforge-ticket-inline__header">
			<h2 class="shopforge-ticket-inline__title">
				<i class="fa-solid fa-headset" aria-hidden="true"></i>
				Richiesta assistenza — Ordine <strong>#<?php echo esc_html( $order_number ); ?></strong>
			</h2>
			<button type="button" class="shopforge-ticket-inline__close" id="shopforge-close-ticket" aria-label="Chiudi">
				<i class="fa-solid fa-xmark" aria-hidden="true"></i>
			</button>
		</div>

		<div class="shopforge-ticket-inline__body">
			<!-- Form -->
			<div id="shopforge-ticket-form">
					<div class="shopforge-field">
						<label for="shopforge-ticket-subject">Motivo della richiesta</label>
						<select id="shopforge-ticket-subject" name="subject">
							<option value="">— Seleziona —</option>
							<option value="Problema con il prodotto">Problema con il prodotto</option>
							<option value="Spedizione o tracking">Spedizione o tracking</option>
							<option value="Reso o sostituzione">Reso o sostituzione</option>
							<option value="Rimborso">Rimborso</option>
							<option value="Altro">Altro</option>
						</select>
					</div>

					<div class="shopforge-field">
						<label>Prodotti coinvolti</label>
						<ul class="shopforge-product-list" id="shopforge-product-list">
							<?php foreach ( $order->get_items() as $item_id => $item ) :
								/** @var WC_Order_Item_Product $item */
								$product    = $item->get_product();
								$thumb      = $product
									? get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' )
									: '';
								$name      = $item->get_name();
								$price     = wc_price( $item->get_total() );
								$field_id  = 'shopforge-prod-' . $item_id;
							?>
							<li class="shopforge-product-list__item">
								<label for="<?php echo esc_attr( $field_id ); ?>" class="shopforge-product-row">
									<input type="checkbox"
									       id="<?php echo esc_attr( $field_id ); ?>"
									       class="shopforge-prod-check"
									       value="<?php echo esc_attr( $name ); ?>">
									<span class="shopforge-product-row__check-icon">
										<i class="fa-solid fa-check" aria-hidden="true"></i>
									</span>
									<span class="shopforge-product-row__thumb">
										<?php if ( $thumb ) : ?>
											<img src="<?php echo esc_url( $thumb ); ?>"
											     alt="" width="50" height="50" loading="lazy">
										<?php else : ?>
											<span class="shopforge-product-row__no-img">
												<i class="fa-solid fa-box" aria-hidden="true"></i>
											</span>
										<?php endif; ?>
									</span>
									<span class="shopforge-product-row__name">
										<span class="shopforge-product-row__title"><?php echo esc_html( $name ); ?></span>
										<span class="shopforge-product-row__price"><?php echo $price; ?></span>
									</span>
								</label>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="shopforge-field">
						<label for="shopforge-ticket-message">Descrivi il problema</label>
						<textarea id="shopforge-ticket-message" name="message" rows="5"
						          placeholder="Descrivi nel dettaglio la tua richiesta…"></textarea>
					</div>
					<div class="shopforge-form-group shopforge-form-group--file">
						<label for="shopforge-ticket-files">Allega foto o documenti (opzionale)</label>
						<input type="file" id="shopforge-ticket-files" name="files[]"
						       multiple accept="image/*,.pdf" class="shopforge-file-input">
						<div id="shopforge-ticket-file-preview" class="shopforge-file-preview"></div>
					</div>
					<p class="shopforge-field-note">
						Riceverai una risposta all'indirizzo email associato al tuo account.
					</p>
					<button type="button" class="shopforge-modal__submit" id="shopforge-submit-ticket">
						<span id="shopforge-btn-label">Invia richiesta</span>
						<span class="shopforge-st-spinner" id="shopforge-btn-spinner" style="display:none"></span>
					</button>
				</div>

			<!-- Stato successo -->
			<div id="shopforge-ticket-success" style="display:none" class="shopforge-ticket-success">
				<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
				<p class="shopforge-ts__title">Richiesta inviata!</p>
				<p class="shopforge-ts__text">
					Abbiamo ricevuto la tua segnalazione. Ti risponderemo
					via email nel più breve tempo possibile.
				</p>
				<button type="button" class="shopforge-modal__close-btn" id="shopforge-close-success">
					Chiudi
				</button>
			</div>

			<!-- Errore -->
			<p class="shopforge-ticket-error" id="shopforge-ticket-error" style="display:none"></p>
		</div>
	</div>

	<?php
	wp_enqueue_script( 'shopforge-tracker' );
	wp_localize_script( 'shopforge-tracker', 'shopforgeTicket', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'orderId' => $order_id,
		'nonce'   => $nonce,
	] );
	?>
	<?php
}, 20 );


// ---- AJAX handler: invia ticket ----

add_action( 'wp_ajax_shopforge_submit_ticket', 'shopforge_submit_ticket_handler' );

function shopforge_submit_ticket_handler(): void {
	$order_id = absint( $_POST['order_id'] ?? 0 );

	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_ticket_' . $order_id ) ) {
		wp_send_json_error( 'Sessione scaduta. Ricarica la pagina e riprova.' );
	}

	if ( function_exists( 'shopforge_check_rate_limit' )
		 && ! shopforge_check_rate_limit( 'submit_ticket', 60 ) ) {
		wp_send_json_error( 'Hai già aperto una richiesta di recente. Attendi un minuto e riprova.' );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( 'Ordine non trovato.' );
	}

	// Verifica che sia il proprietario dell'ordine
	$user_id = get_current_user_id();
	if ( ! $user_id || (int) $order->get_customer_id() !== $user_id ) {
		wp_send_json_error( 'Accesso non autorizzato.' );
	}

	$subject  = sanitize_text_field( $_POST['subject'] ?? '' );
	$message  = sanitize_textarea_field( $_POST['message'] ?? '' );
	$products = array_map( 'sanitize_text_field', (array) ( $_POST['products'] ?? [] ) );
	$products = array_filter( $products );

	if ( ! $subject || strlen( $message ) < 10 ) {
		wp_send_json_error( 'Dati mancanti o non validi.' );
	}

	// Gestione allegati (opzionale)
	$attachment_urls = [];
	if ( ! empty( $_FILES['files']['name'][0] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ];
		$max_size      = 5 * 1024 * 1024; // 5 MB per file

		foreach ( $_FILES['files']['name'] as $i => $name ) {
			$file_array = [
				'name'     => $_FILES['files']['name'][ $i ],
				'type'     => $_FILES['files']['type'][ $i ],
				'tmp_name' => $_FILES['files']['tmp_name'][ $i ],
				'error'    => $_FILES['files']['error'][ $i ],
				'size'     => $_FILES['files']['size'][ $i ],
			];
			if ( $file_array['error'] !== UPLOAD_ERR_OK ) continue;
			if ( $file_array['size'] > $max_size ) continue;
			$mime = mime_content_type( $file_array['tmp_name'] );
			if ( ! in_array( $mime, $allowed_types, true ) ) continue;

			$attach_id = media_handle_sideload( $file_array, $order_id );
			if ( ! is_wp_error( $attach_id ) ) {
				$attachment_urls[] = wp_get_attachment_url( $attach_id );
			}
		}
	}

	// 1. Salva il ticket come meta dell'ordine (storico permanente)
	$tickets   = $order->get_meta( '_shopforge_tickets' ) ?: [];
	$tickets[] = [
		'id'          => uniqid( 'tck_' ),
		'date'        => current_time( 'mysql' ),
		'subject'     => $subject,
		'products'    => $products,
		'message'     => $message,
		'status'      => 'open',
		'attachments' => $attachment_urls,
	];
	$order->update_meta_data( '_shopforge_tickets', $tickets );
	$order->save();

	// 2. Email admin + 3. Conferma cliente — via classi WooCommerce native
	$ticket_email_data = [
		'subject'  => $subject,
		'message'  => $message,
		'products' => $products,
	];
	$mailer = WC()->mailer();
	$wc_emails = $mailer->get_emails();
	if ( isset( $wc_emails['ShopForge_Email_Ticket_Admin'] ) ) {
		$wc_emails['ShopForge_Email_Ticket_Admin']->trigger( $order, $ticket_email_data );
	}
	if ( isset( $wc_emails['ShopForge_Email_Ticket_Customer'] ) ) {
		$wc_emails['ShopForge_Email_Ticket_Customer']->trigger( $order, $ticket_email_data );
	}

	// Notifica in-app al cliente
	$last_ticket = end( $tickets );
	do_action( 'shopforge_ticket_submitted', $user_id, $order_id, $last_ticket['id'] );

	wp_send_json_success();
}





// =============================================================================
// Frontend — Storico richieste di assistenza
// =============================================================================

add_action( 'woocommerce_order_details_after_order_table', function ( WC_Order $order ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) return;
	// Lo storico richieste ha layout custom: solo se 'styles' è attivo
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles' ) ) return;

	$tickets = $order->get_meta( '_shopforge_tickets' ) ?: [];
	if ( empty( $tickets ) ) return;

	// Ordina dal più recente
	usort( $tickets, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	$status_labels = [
		'open'   => [ 'label' => 'Aperto',   'class' => 'open' ],
		'closed' => [ 'label' => 'Chiuso',   'class' => 'closed' ],
	];
	?>
	<div class="shopforge-tickets-history">
		<p class="shopforge-tickets-history__title">
			<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
			Le tue richieste di assistenza
		</p>
		<ul class="shopforge-tickets-list">
			<?php foreach ( $tickets as $ticket ) :
				$st    = $status_labels[ $ticket['status'] ] ?? $status_labels['open'];
				$date  = date_i18n( 'd/m/Y \a\l\l\e H:i', strtotime( $ticket['date'] ) );
			?>
			<li class="shopforge-ticket-row">
				<div class="shopforge-ticket-row__main">
					<span class="shopforge-ticket-row__subject"><?php echo esc_html( $ticket['subject'] ); ?></span>
					<?php if ( ! empty( $ticket['products'] ) ) : ?>
					<span class="shopforge-ticket-row__products">
						<?php echo esc_html( implode( ', ', $ticket['products'] ) ); ?>
					</span>
					<?php endif; ?>
					<span class="shopforge-ticket-row__message"><?php echo esc_html( $ticket['message'] ); ?></span>
					<?php if ( ! empty( $ticket['reply'] ) ) : ?>
					<div class="shopforge-ticket-row__reply">
						<strong><?php esc_html_e( 'Risposta negozio:', 'shopforge' ); ?></strong>
						<?php echo esc_html( $ticket['reply'] ); ?>
					</div>
					<?php endif; ?>
				</div>
				<div class="shopforge-ticket-row__meta">
					<span class="shopforge-ticket-row__date"><?php echo esc_html( $date ); ?></span>
					<span class="shopforge-ticket-badge shopforge-ticket-badge--<?php echo esc_attr( $st['class'] ); ?>">
						<?php echo esc_html( $st['label'] ); ?>
					</span>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php
}, 25 );


// =============================================================================
// Admin — Pannello richieste nel metabox ordine
// =============================================================================

add_action( 'add_meta_boxes', function () {
	foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
		add_meta_box(
			'shopforge-tickets',
			'📩 Richieste assistenza',
			'shopforge_tickets_metabox_render',
			$screen,
			'normal',
			'default'
		);
	}
} );

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ], true ) ) return;
	wp_enqueue_style(
		'shopforge-admin',
		SHOPFORGE_URL . 'assets/css/shopforge-admin.css',
		[],
		SHOPFORGE_VERSION
	);
	wp_enqueue_script(
		'shopforge-admin',
		SHOPFORGE_URL . 'assets/js/shopforge-admin.js',
		[],
		SHOPFORGE_VERSION,
		true
	);
} );

function shopforge_tickets_metabox_render( $post_or_order ): void {
	$order = ( $post_or_order instanceof WP_Post )
		? wc_get_order( $post_or_order->ID )
		: $post_or_order;

	if ( ! $order ) return;

	$tickets = $order->get_meta( '_shopforge_tickets' ) ?: [];

	if ( empty( $tickets ) ) {
		echo '<p style="color:#646970;font-size:13px;margin:8px 0">Nessuna richiesta di assistenza per questo ordine.</p>';
		return;
	}

	usort( $tickets, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	$status_labels = [ 'open' => 'Aperto', 'closed' => 'Chiuso' ];
	$status_colors = [ 'open' => '#B45309', 'closed' => '#15803D' ];
	$status_bg     = [ 'open' => '#FEF3C7', 'closed' => '#DCFCE7' ];
	?>
	<table class="shopforge-adm-tickets">
		<thead>
			<tr>
				<th>Data</th>
				<th>Motivo / Prodotti / Messaggio</th>
				<th>Stato</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $tickets as $idx => $ticket ) :
			$date  = date_i18n( 'd/m/Y H:i', strtotime( $ticket['date'] ) );
			$st    = $ticket['status'] ?? 'open';
			$label = $status_labels[ $st ] ?? 'Aperto';
			$color = $status_colors[ $st ] ?? '#B45309';
			$bg    = $status_bg[ $st ] ?? '#FEF3C7';
		?>
		<tr>
			<td style="white-space:nowrap;color:#646970;font-size:12px"><?php echo esc_html( $date ); ?></td>
			<td>
				<strong><?php echo esc_html( $ticket['subject'] ); ?></strong>
				<?php if ( ! empty( $ticket['products'] ) ) : ?>
				<div class="shopforge-adm-ticket-products">
					<?php echo esc_html( implode( ', ', $ticket['products'] ) ); ?>
				</div>
				<?php endif; ?>
				<div class="shopforge-adm-ticket-msg"><?php echo esc_html( $ticket['message'] ); ?></div>
			</td>
			<td>
				<div class="shopforge-adm-actions">
					<span class="shopforge-adm-status" style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $color ); ?>">
						<?php echo esc_html( $label ); ?>
					</span>
					<br><br>
					<?php if ( ! empty( $ticket['reply'] ) ) : ?>
					<div style="margin:6px 0;padding:6px 8px;background:#f0f7ff;border-left:3px solid #2563eb;border-radius:3px;font-size:12px;">
						<strong>Risposta negozio:</strong> <?php echo esc_html( $ticket['reply'] ); ?>
					</div>
					<?php endif; ?>
					<textarea class="shopforge-reply-text" rows="2" placeholder="Risposta al cliente (opzionale)…"
					          style="width:100%;margin:4px 0;font-size:12px;resize:vertical;"
					><?php echo esc_textarea( $ticket['reply'] ?? '' ); ?></textarea>
					<select data-idx="<?php echo esc_attr( $idx ); ?>" class="shopforge-status-select">
						<option value="open"   <?php selected( $st, 'open' ); ?>>Aperto</option>
						<option value="closed" <?php selected( $st, 'closed' ); ?>>Chiuso</option>
					</select>
					<button type="button" class="button button-small shopforge-save-status"
					        data-idx="<?php echo esc_attr( $idx ); ?>"
					        data-order="<?php echo esc_attr( $order->get_id() ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_ticket_status' ) ); ?>">
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

add_action( 'wp_ajax_shopforge_update_ticket_status', function () {
	check_ajax_referer( 'shopforge_ticket_status', 'nonce' );
	if ( ! current_user_can( 'edit_shop_orders' ) ) wp_send_json_error();

	$order_id = absint( $_POST['order_id'] ?? 0 );
	$idx      = intval( $_POST['idx'] ?? -1 );
	$status   = in_array( $_POST['status'] ?? '', [ 'open', 'closed' ], true )
		? $_POST['status'] : 'open';
	$reply    = sanitize_textarea_field( $_POST['reply'] ?? '' );

	$order = wc_get_order( $order_id );
	if ( ! $order ) wp_send_json_error();

	$tickets = $order->get_meta( '_shopforge_tickets' ) ?: [];
	if ( ! isset( $tickets[ $idx ] ) ) wp_send_json_error();

	$prev_status = $tickets[ $idx ]['status'] ?? 'open';
	$tickets[ $idx ]['status']     = $status;
	$tickets[ $idx ]['reply']      = $reply;
	$tickets[ $idx ]['reply_date'] = current_time( 'mysql' );
	$order->update_meta_data( '_shopforge_tickets', $tickets );
	$order->save();

	// Email al cliente solo se cambia stato o c'è una risposta nuova
	if ( $status !== $prev_status || $reply ) {
		$mailer    = WC()->mailer();
		$wc_emails = $mailer->get_emails();
		if ( isset( $wc_emails['ShopForge_Email_Ticket_Status_Update'] ) ) {
			$wc_emails['ShopForge_Email_Ticket_Status_Update']->trigger( $order, [
				'subject'    => $tickets[ $idx ]['subject'] ?? '',
				'status'     => $status,
				'prev_status'=> $prev_status,
				'reply'      => $reply,
			] );
		}
	}

	wp_send_json_success();
} );


// =============================================================================
// PAGINA ADMIN — Lista globale ticket assistenza e resi
// =============================================================================

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		'Assistenza e Resi',
		'Assistenza e Resi',
		'edit_shop_orders',
		'shopforge-support',
		'shopforge_admin_support_page'
	);
} );

function shopforge_admin_support_page(): void {
	$ticket_statuses = [
		'open'   => [ 'label' => 'Aperto',   'bg' => '#DBEAFE', 'color' => '#1E40AF' ],
		'closed' => [ 'label' => 'Chiuso',   'bg' => '#F3F4F6', 'color' => '#6B7280' ],
	];
	$return_statuses = [
		'pending'    => [ 'label' => 'Ricevuta',       'bg' => '#FEF9C3', 'color' => '#854D0E' ],
		'processing' => [ 'label' => 'In lavorazione', 'bg' => '#DBEAFE', 'color' => '#1E40AF' ],
		'approved'   => [ 'label' => 'Approvata',      'bg' => '#DCFCE7', 'color' => '#166534' ],
		'refunded'   => [ 'label' => 'Rimborsata',     'bg' => '#D1FAE5', 'color' => '#065F46' ],
		'rejected'   => [ 'label' => 'Rifiutata',      'bg' => '#FEE2E2', 'color' => '#991B1B' ],
	];

	// Recupera tutti gli ordini e raggruppa ticket + resi
	$orders = wc_get_orders( [ 'limit' => -1, 'return' => 'objects' ] );
	$all_tickets = [];
	$all_returns = [];

	foreach ( $orders as $order ) {
		$oid    = $order->get_id();
		$on     = $order->get_order_number();
		$cid    = $order->get_customer_id();
		$cname  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$cemail = $order->get_billing_email();

		foreach ( $order->get_meta( '_shopforge_tickets' ) ?: [] as $idx => $t ) {
			$all_tickets[] = array_merge( $t, [
				'_oid' => $oid, '_on' => $on, '_cid' => $cid,
				'_cname' => $cname, '_cemail' => $cemail, '_idx' => $idx,
			] );
		}
		foreach ( $order->get_meta( '_shopforge_returns' ) ?: [] as $idx => $r ) {
			$all_returns[] = array_merge( $r, [
				'_oid' => $oid, '_on' => $on, '_cid' => $cid,
				'_cname' => $cname, '_cemail' => $cemail, '_idx' => $idx,
			] );
		}
	}

	usort( $all_tickets, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );
	usort( $all_returns, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	// Determina tab attiva
	$active_tab = ( $_GET['tab'] ?? 'tickets' ) === 'returns' ? 'returns' : 'tickets';
	$base_url   = admin_url( 'admin.php?page=shopforge-support' );
	?>
	<div class="wrap">
		<h1>Assistenza e Resi</h1>

		<nav class="nav-tab-wrapper" style="margin-bottom:16px">
			<a href="<?php echo esc_url( $base_url . '&tab=tickets' ); ?>"
			   class="nav-tab <?php echo $active_tab === 'tickets' ? 'nav-tab-active' : ''; ?>">
				Richieste assistenza (<?php echo count( $all_tickets ); ?>)
			</a>
			<a href="<?php echo esc_url( $base_url . '&tab=returns' ); ?>"
			   class="nav-tab <?php echo $active_tab === 'returns' ? 'nav-tab-active' : ''; ?>">
				Richieste di recesso (<?php echo count( $all_returns ); ?>)
			</a>
		</nav>

		<?php if ( $active_tab === 'tickets' ) : ?>

		<?php if ( empty( $all_tickets ) ) : ?>
		<p>Nessun ticket aperto.</p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:180px">Oggetto</th>
				<th>Cliente</th>
				<th>Ordine</th>
				<th style="width:120px">Data</th>
				<th style="width:90px">Stato</th>
				<th style="width:80px">Azioni</th>
			</tr></thead>
			<tbody>
			<?php foreach ( $all_tickets as $t ) :
				$st  = $ticket_statuses[ $t['status'] ?? 'open' ] ?? $ticket_statuses['open'];
				$key = $t['_oid'] . '-' . $t['_idx'];
			?>
			<tr>
				<td><strong><?php echo esc_html( $t['subject'] ); ?></strong></td>
				<td><?php echo esc_html( $t['_cname'] ); ?><br><small><?php echo esc_html( $t['_cemail'] ); ?></small></td>
				<td><a href="<?php echo esc_url( get_edit_post_link( $t['_oid'] ) ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $t['_oid'] ) ); ?>">#<?php echo esc_html( $t['_on'] ); ?></a></td>
				<td><?php echo date_i18n( 'd/m/Y H:i', strtotime( $t['date'] ) ); ?></td>
				<td><span style="display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;background:<?php echo esc_attr( $st['bg'] ); ?>;color:<?php echo esc_attr( $st['color'] ); ?>"><?php echo esc_html( $st['label'] ); ?></span></td>
				<td><button type="button" class="button button-small shopforge-sadm-edit" data-key="<?php echo esc_attr( $key ); ?>">Gestisci</button></td>
			</tr>
			<tr class="shopforge-sadm-panel" id="shopforge-spanel-<?php echo esc_attr( $key ); ?>" style="display:none">
				<td colspan="6" style="background:#f9f9f9;padding:16px">
					<?php if ( ! empty( $t['message'] ) ) : ?>
					<p style="margin-top:0"><strong>Messaggio cliente:</strong><br><?php echo nl2br( esc_html( $t['message'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $t['attachments'] ) ) :
						$atts = (array) $t['attachments'];
					?>
					<p><strong>Allegati:</strong><br>
					<?php foreach ( $atts as $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" style="display:inline-block;margin:2px 6px 2px 0"><?php echo esc_html( basename( $url ) ); ?></a>
					<?php endforeach; ?>
					</p>
					<?php endif; ?>
					<label style="display:block;margin-bottom:8px;font-weight:600">Stato:
						<select class="shopforge-sadm-status">
							<?php foreach ( $ticket_statuses as $val => $info ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $t['status'] ?? 'open', $val ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="display:block;margin-bottom:6px;font-weight:600">Risposta al cliente:</label>
					<textarea class="widefat shopforge-sadm-reply" rows="4" style="margin-bottom:8px"><?php echo esc_textarea( $t['reply'] ?? '' ); ?></textarea>
					<button type="button" class="button button-primary shopforge-sadm-save"
					        data-type="ticket"
					        data-order="<?php echo esc_attr( $t['_oid'] ); ?>"
					        data-idx="<?php echo esc_attr( $t['_idx'] ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_ticket_status' ) ); ?>">Salva</button>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php else : // tab returns ?>

		<?php if ( empty( $all_returns ) ) : ?>
		<p>Nessuna richiesta di recesso ricevuta.</p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:130px">Riferimento</th>
				<th>Cliente</th>
				<th>Ordine</th>
				<th>Motivazione</th>
				<th style="width:120px">Data</th>
				<th style="width:90px">Stato</th>
				<th style="width:80px">Azioni</th>
			</tr></thead>
			<tbody>
			<?php foreach ( $all_returns as $r ) :
				$st  = $return_statuses[ $r['status'] ?? 'pending' ] ?? $return_statuses['pending'];
				$key = 'r-' . $r['_oid'] . '-' . $r['_idx'];
			?>
			<tr>
				<td><strong><?php echo esc_html( $r['ref'] ); ?></strong></td>
				<td><?php echo esc_html( $r['_cname'] ); ?><br><small><?php echo esc_html( $r['_cemail'] ); ?></small></td>
				<td><a href="<?php echo esc_url( get_edit_post_link( $r['_oid'] ) ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $r['_oid'] ) ); ?>">#<?php echo esc_html( $r['_on'] ); ?></a></td>
				<td><small><?php echo esc_html( $r['reason'] ?? '' ); ?></small></td>
				<td><?php echo date_i18n( 'd/m/Y H:i', strtotime( $r['date'] ) ); ?></td>
				<td><span style="display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;background:<?php echo esc_attr( $st['bg'] ); ?>;color:<?php echo esc_attr( $st['color'] ); ?>"><?php echo esc_html( $st['label'] ); ?></span></td>
				<td><button type="button" class="button button-small shopforge-sadm-edit" data-key="<?php echo esc_attr( $key ); ?>">Gestisci</button></td>
			</tr>
			<tr class="shopforge-sadm-panel" id="shopforge-spanel-<?php echo esc_attr( $key ); ?>" style="display:none">
				<td colspan="7" style="background:#f9f9f9;padding:16px">
					<?php if ( ! empty( $r['products'] ) ) : ?>
					<p style="margin-top:0"><strong>Prodotti:</strong> <?php echo esc_html( implode( ', ', $r['products'] ) ); ?></p>
					<?php endif; ?>
					<label style="display:block;margin-bottom:8px;font-weight:600">Stato:
						<select class="shopforge-sadm-status">
							<?php foreach ( $return_statuses as $val => $info ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $r['status'] ?? 'pending', $val ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="display:block;margin-bottom:6px;font-weight:600">Risposta al cliente:</label>
					<textarea class="widefat shopforge-sadm-reply" rows="4" style="margin-bottom:8px"><?php echo esc_textarea( $r['reply'] ?? '' ); ?></textarea>
					<button type="button" class="button button-primary shopforge-sadm-save"
					        data-type="return"
					        data-order="<?php echo esc_attr( $r['_oid'] ); ?>"
					        data-idx="<?php echo esc_attr( $r['_idx'] ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_return_status' ) ); ?>">Salva</button>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		<?php endif; ?>
	</div>

	<script>
	document.querySelectorAll('.shopforge-sadm-edit').forEach(function(btn){
		btn.addEventListener('click', function(){
			var panel = document.getElementById('shopforge-spanel-' + this.dataset.key);
			var show  = panel.style.display === 'none';
			document.querySelectorAll('.shopforge-sadm-panel').forEach(function(p){ p.style.display='none'; });
			document.querySelectorAll('.shopforge-sadm-edit').forEach(function(b){ b.textContent='Gestisci'; });
			if (show) { panel.style.display='table-row'; this.textContent='Chiudi'; }
		});
	});
	document.querySelectorAll('.shopforge-sadm-save').forEach(function(btn){
		btn.addEventListener('click', function(){
			var td     = this.closest('td');
			var status = td.querySelector('.shopforge-sadm-status').value;
			var reply  = td.querySelector('.shopforge-sadm-reply').value;
			var type   = this.dataset.type;
			var action = type === 'ticket' ? 'shopforge_update_ticket_status' : 'shopforge_update_return_status';
			var me = this; me.disabled = true; me.textContent = '…';
			fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
				body: new URLSearchParams({
					action: action, nonce: this.dataset.nonce,
					order_id: this.dataset.order, idx: this.dataset.idx,
					status: status, reply: reply
				})
			}).then(function(r){ return r.json(); }).then(function(data){
				me.disabled = false;
				me.textContent = data.success ? 'Salvato ✓' : 'Errore';
				setTimeout(function(){ me.textContent = 'Salva'; }, 2500);
			}).catch(function(){
				me.disabled = false; me.textContent = 'Errore';
			});
		});
	});
	</script>
	<?php
}


