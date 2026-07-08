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
			<span><?php
			/* translators: %s: order status name */
			printf( wp_kses_post( __( 'Order <strong>%s</strong> — no progress available.', 'shopforge' ) ), esc_html( $label ) );
			?></span>
		</div>
		<?php
		return;
	}

	$steps = [
		[ 'label' => __( 'Received', 'shopforge' ),   'icon' => 'fa-solid fa-inbox' ],
		[ 'label' => __( 'Paid', 'shopforge' ),       'icon' => 'fa-solid fa-credit-card' ],
		[ 'label' => __( 'Preparing', 'shopforge' ),  'icon' => 'fa-solid fa-box-open' ],
		[ 'label' => __( 'Shipped', 'shopforge' ),    'icon' => 'fa-solid fa-truck' ],
		[ 'label' => __( 'Delivered', 'shopforge' ),  'icon' => 'fa-solid fa-circle-check' ],
	];
	?>
	<div class="shopforge-order-tracker" role="list" aria-label="<?php esc_attr_e( 'Order status', 'shopforge' ); ?>">
		<p class="shopforge-tracker-header">
			<i class="fa-solid fa-route" aria-hidden="true"></i>
			<?php esc_html_e( 'Order status', 'shopforge' ); ?>
		</p>

		<div class="shopforge-tracker-steps">
			<?php foreach ( $steps as $i => $step ) :
				$step_num = $i + 1;

				if ( $step_num < $progress ) {
					$css_class = 'is-completed';
					$aria      = __( 'Completed', 'shopforge' );
				} elseif ( $step_num === $progress ) {
					$css_class = 'is-active';
					$aria      = __( 'In progress', 'shopforge' );
				} else {
					$css_class = 'is-pending';
					$aria      = __( 'Pending', 'shopforge' );
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
	// Il tracker è grafico: renderizzato solo se 'styles-account' è attivo
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles-account' ) ) return;
	shopforge_render_order_tracker( $order );
}, 5 );


// -------------------------------------------------------------------------
// Enqueue CSS + JS tracker (sostituisce tutti i blocchi wp_head inline)
// -------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_account_page() ) return;
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles-account' ) ) return;
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
			<p class="shopforge-support-card__title" style="margin:0 0 4px;font-weight:700;font-size:14px;"><?php esc_html_e( 'Need help with this order?', 'shopforge' ); ?></p>
			<p class="shopforge-support-card__text" style="margin:0;font-size:13px;color:#555;">
				<?php esc_html_e( 'Our team is available for any question about products, shipping, or to handle returns and replacements.', 'shopforge' ); ?>
			</p>
		</div>
		<button type="button" class="shopforge-support-card__btn"
		        id="shopforge-open-ticket"
		        data-order="<?php echo esc_attr( $order_id ); ?>"
		        data-number="<?php echo esc_attr( $order_number ); ?>"
		        data-nonce="<?php echo esc_attr( $nonce ); ?>"
		        style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#0369A1;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
			<?php esc_html_e( 'Open a Request', 'shopforge' ); ?>
			<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
		</button>
	</div>

	<!-- Form ticket inline (collassabile) -->
	<div class="shopforge-ticket-inline" id="shopforge-ticket-backdrop" style="display:none">
		<div class="shopforge-ticket-inline__header">
			<h2 class="shopforge-ticket-inline__title">
				<i class="fa-solid fa-headset" aria-hidden="true"></i>
				<?php
				/* translators: %s: order number */
				printf( wp_kses_post( __( 'Support request — Order <strong>#%s</strong>', 'shopforge' ) ), esc_html( $order_number ) );
				?>
			</h2>
			<button type="button" class="shopforge-ticket-inline__close" id="shopforge-close-ticket" aria-label="<?php esc_attr_e( 'Close', 'shopforge' ); ?>">
				<i class="fa-solid fa-xmark" aria-hidden="true"></i>
			</button>
		</div>

		<div class="shopforge-ticket-inline__body">
			<!-- Form -->
			<div id="shopforge-ticket-form">
					<div class="shopforge-field">
						<label for="shopforge-ticket-subject"><?php esc_html_e( 'Reason for the request', 'shopforge' ); ?></label>
						<select id="shopforge-ticket-subject" name="subject">
							<option value="">— <?php esc_html_e( 'Select', 'shopforge' ); ?> —</option>
							<option value="<?php esc_attr_e( 'Product issue', 'shopforge' ); ?>"><?php esc_html_e( 'Product issue', 'shopforge' ); ?></option>
							<option value="<?php esc_attr_e( 'Shipping or tracking', 'shopforge' ); ?>"><?php esc_html_e( 'Shipping or tracking', 'shopforge' ); ?></option>
							<option value="<?php esc_attr_e( 'Other', 'shopforge' ); ?>"><?php esc_html_e( 'Other', 'shopforge' ); ?></option>
						</select>
					</div>

					<div class="shopforge-field">
						<label><?php esc_html_e( 'Products involved', 'shopforge' ); ?></label>
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
						<label for="shopforge-ticket-message"><?php esc_html_e( 'Describe the problem', 'shopforge' ); ?></label>
						<textarea id="shopforge-ticket-message" name="message" rows="5"
						          placeholder="<?php esc_attr_e( 'Describe your request in detail…', 'shopforge' ); ?>"></textarea>
					</div>
					<div class="shopforge-form-group shopforge-form-group--file">
						<label for="shopforge-ticket-files"><?php esc_html_e( 'Attach photos or documents (optional)', 'shopforge' ); ?></label>
						<input type="file" id="shopforge-ticket-files" name="files[]"
						       multiple accept="image/*,.pdf" class="shopforge-file-input">
						<div id="shopforge-ticket-file-preview" class="shopforge-file-preview"></div>
					</div>
					<p class="shopforge-field-note">
						<?php esc_html_e( 'You will receive a reply at the email address associated with your account.', 'shopforge' ); ?>
					</p>
					<button type="button" class="shopforge-modal__submit" id="shopforge-submit-ticket">
						<span id="shopforge-btn-label"><?php esc_html_e( 'Send request', 'shopforge' ); ?></span>
						<span class="shopforge-st-spinner" id="shopforge-btn-spinner" style="display:none"></span>
					</button>
				</div>

			<!-- Stato successo -->
			<div id="shopforge-ticket-success" style="display:none" class="shopforge-ticket-success">
				<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
				<p class="shopforge-ts__title"><?php esc_html_e( 'Request sent!', 'shopforge' ); ?></p>
				<p class="shopforge-ts__text">
					<?php esc_html_e( 'We received your report. We will reply by email as soon as possible.', 'shopforge' ); ?>
				</p>
				<button type="button" class="shopforge-modal__close-btn" id="shopforge-close-success">
					<?php esc_html_e( 'Close', 'shopforge' ); ?>
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
		wp_send_json_error( __( 'Session expired. Reload the page and try again.', 'shopforge' ) );
	}

	if ( function_exists( 'shopforge_check_rate_limit' )
		 && ! shopforge_check_rate_limit( 'submit_ticket', 60 ) ) {
		wp_send_json_error( __( 'You already opened a request recently. Wait a minute and try again.', 'shopforge' ) );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( __( 'Order not found.', 'shopforge' ) );
	}

	// Verifica che sia il proprietario dell'ordine
	$user_id = get_current_user_id();
	if ( ! $user_id || (int) $order->get_customer_id() !== $user_id ) {
		wp_send_json_error( __( 'Unauthorized access.', 'shopforge' ) );
	}

	$subject  = sanitize_text_field( $_POST['subject'] ?? '' );
	$message  = sanitize_textarea_field( $_POST['message'] ?? '' );
	$products = array_map( 'sanitize_text_field', (array) ( $_POST['products'] ?? [] ) );
	$products = array_filter( $products );

	if ( ! $subject || strlen( $message ) < 10 ) {
		wp_send_json_error( __( 'Missing or invalid data.', 'shopforge' ) );
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
	// Lo storico richieste ha layout custom: solo se 'styles-account' è attivo
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles-account' ) ) return;

	$tickets = $order->get_meta( '_shopforge_tickets' ) ?: [];
	if ( empty( $tickets ) ) return;

	// Ordina dal più recente
	usort( $tickets, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	$status_labels = [
		'open'   => [ 'label' => __( 'Open', 'shopforge' ),   'class' => 'open' ],
		'closed' => [ 'label' => __( 'Closed', 'shopforge' ), 'class' => 'closed' ],
	];
	?>
	<div class="shopforge-tickets-history">
		<p class="shopforge-tickets-history__title">
			<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
			<?php esc_html_e( 'Your support requests', 'shopforge' ); ?>
		</p>
		<ul class="shopforge-tickets-list">
			<?php foreach ( $tickets as $ticket ) :
				$st    = $status_labels[ $ticket['status'] ] ?? $status_labels['open'];
				$date  = date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $ticket['date'] ) );
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
						<strong><?php esc_html_e( 'Store reply:', 'shopforge' ); ?></strong>
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
			'📩 ' . __( 'Support requests', 'shopforge' ),
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
		echo '<p style="color:#646970;font-size:13px;margin:8px 0">' . esc_html__( 'No support requests for this order.', 'shopforge' ) . '</p>';
		return;
	}

	usort( $tickets, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	$status_labels = [ 'open' => __( 'Open', 'shopforge' ), 'closed' => __( 'Closed', 'shopforge' ) ];
	$status_colors = [ 'open' => '#B45309', 'closed' => '#15803D' ];
	$status_bg     = [ 'open' => '#FEF3C7', 'closed' => '#DCFCE7' ];
	?>
	<table class="shopforge-adm-tickets">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Reason / Products / Message', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Status', 'shopforge' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $tickets as $idx => $ticket ) :
			$date  = date_i18n( 'd/m/Y H:i', strtotime( $ticket['date'] ) );
			$st    = $ticket['status'] ?? 'open';
			$label = $status_labels[ $st ] ?? __( 'Open', 'shopforge' );
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
						<strong><?php esc_html_e( 'Store reply:', 'shopforge' ); ?></strong> <?php echo esc_html( $ticket['reply'] ); ?>
					</div>
					<?php endif; ?>
					<textarea class="shopforge-reply-text" rows="2" placeholder="<?php esc_attr_e( 'Reply to customer (optional)…', 'shopforge' ); ?>"
					          style="width:100%;margin:4px 0;font-size:12px;resize:vertical;"
					><?php echo esc_textarea( $ticket['reply'] ?? '' ); ?></textarea>
					<select data-idx="<?php echo esc_attr( $idx ); ?>" class="shopforge-status-select">
						<option value="open"   <?php selected( $st, 'open' ); ?>><?php esc_html_e( 'Open', 'shopforge' ); ?></option>
						<option value="closed" <?php selected( $st, 'closed' ); ?>><?php esc_html_e( 'Closed', 'shopforge' ); ?></option>
					</select>
					<button type="button" class="button button-small shopforge-save-status"
					        data-idx="<?php echo esc_attr( $idx ); ?>"
					        data-order="<?php echo esc_attr( $order->get_id() ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_ticket_status' ) ); ?>">
						<?php esc_html_e( 'Save &amp; Notify', 'shopforge' ); ?>
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
		__( 'Support & Returns', 'shopforge' ),
		__( 'Support & Returns', 'shopforge' ),
		'edit_shop_orders',
		'shopforge-support',
		'shopforge_admin_support_page'
	);
} );

function shopforge_admin_support_page(): void {
	$ticket_statuses = [
		'open'   => [ 'label' => __( 'Open', 'shopforge' ),   'bg' => '#DBEAFE', 'color' => '#1E40AF' ],
		'closed' => [ 'label' => __( 'Closed', 'shopforge' ), 'bg' => '#F3F4F6', 'color' => '#6B7280' ],
	];
	$return_statuses = [
		'pending'    => [ 'label' => __( 'Received', 'shopforge' ),   'bg' => '#FEF9C3', 'color' => '#854D0E' ],
		'processing' => [ 'label' => __( 'Processing', 'shopforge' ), 'bg' => '#DBEAFE', 'color' => '#1E40AF' ],
		'approved'   => [ 'label' => __( 'Approved', 'shopforge' ),   'bg' => '#DCFCE7', 'color' => '#166534' ],
		'refunded'   => [ 'label' => __( 'Refunded', 'shopforge' ),   'bg' => '#D1FAE5', 'color' => '#065F46' ],
		'rejected'   => [ 'label' => __( 'Rejected', 'shopforge' ),   'bg' => '#FEE2E2', 'color' => '#991B1B' ],
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
		<h1><?php esc_html_e( 'Support & Returns', 'shopforge' ); ?></h1>

		<nav class="nav-tab-wrapper" style="margin-bottom:16px">
			<a href="<?php echo esc_url( $base_url . '&tab=tickets' ); ?>"
			   class="nav-tab <?php echo $active_tab === 'tickets' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Support requests', 'shopforge' ); ?> (<?php echo count( $all_tickets ); ?>)
			</a>
			<a href="<?php echo esc_url( $base_url . '&tab=returns' ); ?>"
			   class="nav-tab <?php echo $active_tab === 'returns' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Withdrawal requests', 'shopforge' ); ?> (<?php echo count( $all_returns ); ?>)
			</a>
		</nav>

		<?php if ( $active_tab === 'tickets' ) : ?>

		<?php if ( empty( $all_tickets ) ) : ?>
		<p><?php esc_html_e( 'No open tickets.', 'shopforge' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:180px"><?php esc_html_e( 'Subject', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Status', 'shopforge' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Actions', 'shopforge' ); ?></th>
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
				<td><button type="button" class="button button-small shopforge-sadm-edit" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Manage', 'shopforge' ); ?></button></td>
			</tr>
			<tr class="shopforge-sadm-panel" id="shopforge-spanel-<?php echo esc_attr( $key ); ?>" style="display:none">
				<td colspan="6" style="background:#f9f9f9;padding:16px">
					<?php if ( ! empty( $t['message'] ) ) : ?>
					<p style="margin-top:0"><strong><?php esc_html_e( 'Customer message:', 'shopforge' ); ?></strong><br><?php echo nl2br( esc_html( $t['message'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $t['attachments'] ) ) :
						$atts = (array) $t['attachments'];
					?>
					<p><strong><?php esc_html_e( 'Attachments:', 'shopforge' ); ?></strong><br>
					<?php foreach ( $atts as $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" style="display:inline-block;margin:2px 6px 2px 0"><?php echo esc_html( basename( $url ) ); ?></a>
					<?php endforeach; ?>
					</p>
					<?php endif; ?>
					<label style="display:block;margin-bottom:8px;font-weight:600"><?php esc_html_e( 'Status:', 'shopforge' ); ?>
						<select class="shopforge-sadm-status">
							<?php foreach ( $ticket_statuses as $val => $info ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $t['status'] ?? 'open', $val ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="display:block;margin-bottom:6px;font-weight:600"><?php esc_html_e( 'Reply to customer:', 'shopforge' ); ?></label>
					<textarea class="widefat shopforge-sadm-reply" rows="4" style="margin-bottom:8px"><?php echo esc_textarea( $t['reply'] ?? '' ); ?></textarea>
					<button type="button" class="button button-primary shopforge-sadm-save"
					        data-type="ticket"
					        data-order="<?php echo esc_attr( $t['_oid'] ); ?>"
					        data-idx="<?php echo esc_attr( $t['_idx'] ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_ticket_status' ) ); ?>"><?php esc_html_e( 'Save', 'shopforge' ); ?></button>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php else : // tab returns ?>

		<?php if ( empty( $all_returns ) ) : ?>
		<p><?php esc_html_e( 'No withdrawal requests received.', 'shopforge' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:130px"><?php esc_html_e( 'Reference', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'shopforge' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Status', 'shopforge' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Actions', 'shopforge' ); ?></th>
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
				<td><button type="button" class="button button-small shopforge-sadm-edit" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Manage', 'shopforge' ); ?></button></td>
			</tr>
			<tr class="shopforge-sadm-panel" id="shopforge-spanel-<?php echo esc_attr( $key ); ?>" style="display:none">
				<td colspan="7" style="background:#f9f9f9;padding:16px">
					<?php if ( ! empty( $r['products'] ) ) : ?>
					<p style="margin-top:0"><strong><?php esc_html_e( 'Products:', 'shopforge' ); ?></strong> <?php echo esc_html( implode( ', ', $r['products'] ) ); ?></p>
					<?php endif; ?>
					<label style="display:block;margin-bottom:8px;font-weight:600"><?php esc_html_e( 'Status:', 'shopforge' ); ?>
						<select class="shopforge-sadm-status">
							<?php foreach ( $return_statuses as $val => $info ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $r['status'] ?? 'pending', $val ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="display:block;margin-bottom:6px;font-weight:600"><?php esc_html_e( 'Reply to customer:', 'shopforge' ); ?></label>
					<textarea class="widefat shopforge-sadm-reply" rows="4" style="margin-bottom:8px"><?php echo esc_textarea( $r['reply'] ?? '' ); ?></textarea>
					<button type="button" class="button button-primary shopforge-sadm-save"
					        data-type="return"
					        data-order="<?php echo esc_attr( $r['_oid'] ); ?>"
					        data-idx="<?php echo esc_attr( $r['_idx'] ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_return_status' ) ); ?>"><?php esc_html_e( 'Save', 'shopforge' ); ?></button>
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
			document.querySelectorAll('.shopforge-sadm-edit').forEach(function(b){ b.textContent=<?php echo wp_json_encode( __( 'Manage', 'shopforge' ) ); ?>; });
			if (show) { panel.style.display='table-row'; this.textContent=<?php echo wp_json_encode( __( 'Close', 'shopforge' ) ); ?>; }
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
				me.textContent = data.success ? <?php echo wp_json_encode( __( 'Saved ✓', 'shopforge' ) ); ?> : <?php echo wp_json_encode( __( 'Error', 'shopforge' ) ); ?>;
				setTimeout(function(){ me.textContent = <?php echo wp_json_encode( __( 'Save', 'shopforge' ) ); ?>; }, 2500);
			}).catch(function(){
				me.disabled = false; me.textContent = <?php echo wp_json_encode( __( 'Error', 'shopforge' ) ); ?>;
			});
		});
	});
	</script>
	<?php
}


