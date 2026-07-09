<?php
/**
 * Modulo: PDF Receipts
 *
 * Genera una ricevuta PDF vera (non una pagina stampabile) per ogni ordine,
 * con template selezionabile, logo, dati azienda e note a piè di pagina
 * configurabili da ShopForge → Ricevute. Il rendering usa Dompdf (bundle
 * locale in assets/vendor/dompdf, licenza LGPL — vedi LICENSE.LGPL) su un
 * template HTML/CSS: stesso approccio "stampa il documento" già usato per
 * il modulo RMA, solo che qui il PDF è generato lato server invece di
 * lasciare al browser il compito di stampare.
 *
 * Numerazione: il numero ricevuta è assegnato una sola volta al primo
 * download/invio (mai rigenerato), con incremento atomico via query diretta
 * per evitare duplicati in caso di richieste concorrenti.
 *
 * Meta ordine:
 *  _shopforge_receipt_number → stringa, es. "INV-2026-000123"
 *  _shopforge_receipt_date   → data ISO di prima emissione
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// TEMPLATE — registro
// =============================================================================

function shopforge_receipt_templates(): array {
	return [
		'modern'  => [
			'label' => __( 'Modern', 'shopforge' ),
			'desc'  => __( 'Bold color header band, clean sans-serif typography.', 'shopforge' ),
			'file'  => 'template-modern.php',
		],
		'classic' => [
			'label' => __( 'Classic', 'shopforge' ),
			'desc'  => __( 'Traditional centered letterhead layout with rule lines.', 'shopforge' ),
			'file'  => 'template-classic.php',
		],
		'minimal' => [
			'label' => __( 'Minimal', 'shopforge' ),
			'desc'  => __( 'Black and white, no color, ink-friendly for printing.', 'shopforge' ),
			'file'  => 'template-minimal.php',
		],
	];
}

function shopforge_receipt_get_template(): string {
	$template = get_option( 'shopforge_receipt_template', 'modern' );
	return isset( shopforge_receipt_templates()[ $template ] ) ? $template : 'modern';
}


// =============================================================================
// IMPOSTAZIONI — dati azienda, logo, note (con fallback ai dati negozio WC)
// =============================================================================

function shopforge_receipt_get_settings(): array {
	$defaults = [
		'company_name'    => get_bloginfo( 'name' ),
		'company_address' => trim( implode( ', ', array_filter( [
			get_option( 'woocommerce_store_address' ),
			get_option( 'woocommerce_store_address_2' ),
			trim( get_option( 'woocommerce_store_postcode' ) . ' ' . get_option( 'woocommerce_store_city' ) ),
		] ) ) ),
		'company_vat'     => '',
		'company_email'   => get_option( 'admin_email' ),
		'footer_note'     => '',
		'number_prefix'   => 'REC-' . gmdate( 'Y' ) . '-',
		'logo_id'         => (int) get_theme_mod( 'custom_logo' ),
	];

	$saved = get_option( 'shopforge_receipt_settings', [] );
	return array_merge( $defaults, is_array( $saved ) ? array_filter( $saved, fn( $v ) => '' !== $v && null !== $v ) : [] );
}


// =============================================================================
// NUMERAZIONE — assegnazione atomica, mai rigenerata
// =============================================================================

/**
 * Restituisce il numero ricevuta dell'ordine, generandolo alla prima chiamata.
 * L'incremento del contatore avviene con una query SQL diretta (non
 * read-then-write su un option) per restare corretto anche con richieste
 * di generazione concorrenti sullo stesso negozio.
 */
function shopforge_receipt_get_number( WC_Order $order ): string {
	$existing = $order->get_meta( '_shopforge_receipt_number' );
	if ( $existing ) {
		return $existing;
	}

	global $wpdb;
	$option_name = 'shopforge_receipt_next_number';

	// Assicura che l'opzione esista prima dell'UPDATE atomico.
	if ( false === get_option( $option_name, false ) ) {
		add_option( $option_name, 1, '', false );
	}

	$wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
		$option_name
	) );
	$next = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
		$option_name
	) );
	wp_cache_delete( $option_name, 'options' );

	$settings = shopforge_receipt_get_settings();
	$number   = $settings['number_prefix'] . str_pad( (string) $next, 6, '0', STR_PAD_LEFT );

	$order->update_meta_data( '_shopforge_receipt_number', $number );
	$order->update_meta_data( '_shopforge_receipt_date', current_time( 'mysql' ) );
	$order->save();

	return $number;
}


// =============================================================================
// DATI RIGHE — normalizzati una volta sola, riusati da tutti i template
// =============================================================================

/**
 * Righe prodotto + riepilogo totali in un formato semplice, così i template
 * non ripetono ciascuno la stessa logica di iterazione sugli item WC.
 */
function shopforge_receipt_get_line_items( WC_Order $order ): array {
	$items = [];
	foreach ( $order->get_items() as $item ) {
		/** @var WC_Order_Item_Product $item */
		$qty = $item->get_quantity();
		$items[] = [
			'name'     => $item->get_name(),
			'qty'      => $qty,
			'unit'     => $qty ? wc_price( $item->get_total() / $qty ) : wc_price( 0 ),
			'total'    => wc_price( $item->get_total() ),
		];
	}

	$totals = [
		'subtotal' => wc_price( $order->get_subtotal() ),
		'shipping' => $order->get_shipping_total() > 0 ? wc_price( $order->get_shipping_total() ) : null,
		'discount' => $order->get_discount_total() > 0 ? wc_price( $order->get_discount_total() ) : null,
		'tax'      => $order->get_total_tax() > 0 ? wc_price( $order->get_total_tax() ) : null,
		'total'    => wc_price( $order->get_total() ),
	];

	return [ 'items' => $items, 'totals' => $totals ];
}


// =============================================================================
// RENDER — HTML del template scelto, poi PDF via Dompdf
// =============================================================================

function shopforge_receipt_render_html( WC_Order $order, string $template_id = '' ): string {
	$template_id = $template_id ?: shopforge_receipt_get_template();
	$templates   = shopforge_receipt_templates();
	$file        = SHOPFORGE_DIR . 'inc/receipt-templates/' . ( $templates[ $template_id ]['file'] ?? $templates['modern']['file'] );
	if ( ! file_exists( $file ) ) {
		$file = SHOPFORGE_DIR . 'inc/receipt-templates/' . $templates['modern']['file'];
	}

	$settings       = shopforge_receipt_get_settings();
	$receipt_number = shopforge_receipt_get_number( $order );
	$receipt_date   = $order->get_meta( '_shopforge_receipt_date' ) ?: current_time( 'mysql' );
	$logo_url       = $settings['logo_id'] ? wp_get_attachment_image_url( $settings['logo_id'], 'medium' ) : '';
	$billing        = $order->get_formatted_billing_address() ?: __( 'No billing address', 'shopforge' );
	$line_data      = shopforge_receipt_get_line_items( $order );
	$line_items     = $line_data['items'];
	$totals         = $line_data['totals'];

	ob_start();
	include $file;
	return ob_get_clean();
}

/**
 * Genera il PDF binario per l'ordine. I warning di deprecazione PHP 8.x
 * emessi dalla dipendenza thecodingmachine/safe (interna a Dompdf, non
 * nostra) sono innocui — silenziati solo per la durata del rendering.
 */
function shopforge_receipt_generate_pdf( WC_Order $order, string $template_id = '' ): string {
	$autoload = SHOPFORGE_DIR . 'assets/vendor/dompdf/vendor/autoload.php';
	if ( ! file_exists( $autoload ) ) {
		return '';
	}
	require_once $autoload;

	$html = shopforge_receipt_render_html( $order, $template_id );

	$options = new \Dompdf\Options();
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'defaultFont', 'DejaVu Sans' );
	$options->set( 'fontDir', SHOPFORGE_DIR . 'assets/vendor/dompdf/vendor/dompdf/dompdf/lib/fonts' );
	$options->set( 'fontCache', SHOPFORGE_DIR . 'assets/vendor/dompdf/vendor/dompdf/dompdf/lib/fonts' );
	$options->set( 'tempDir', get_temp_dir() );
	$options->set( 'chroot', ABSPATH );

	$previous_error_reporting = error_reporting();
	error_reporting( $previous_error_reporting & ~E_DEPRECATED );

	$dompdf = new \Dompdf\Dompdf( $options );
	$dompdf->loadHtml( $html );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();
	$pdf = $dompdf->output();

	error_reporting( $previous_error_reporting );

	return (string) $pdf;
}

function shopforge_receipt_filename( WC_Order $order ): string {
	$number = $order->get_meta( '_shopforge_receipt_number' ) ?: $order->get_order_number();
	return sanitize_file_name( 'receipt-' . $number . '.pdf' );
}


// =============================================================================
// DOWNLOAD — admin (metabox ordine) + cliente (area account)
// =============================================================================

add_action( 'admin_post_shopforge_download_receipt', 'shopforge_receipt_handle_download' );
add_action( 'admin_post_nopriv_shopforge_download_receipt', 'shopforge_receipt_handle_download' );

function shopforge_receipt_handle_download(): void {
	$order_id = absint( $_GET['order_id'] ?? 0 );
	$nonce    = $_GET['nonce'] ?? '';
	$order    = $order_id ? wc_get_order( $order_id ) : null;

	if ( ! $order || ! wp_verify_nonce( $nonce, 'shopforge_receipt_' . $order_id ) ) {
		wp_die( esc_html__( 'Invalid or expired link.', 'shopforge' ) );
	}

	$user_id  = get_current_user_id();
	$is_owner = $user_id && (int) $order->get_customer_id() === $user_id;
	$is_staff = current_user_can( 'edit_shop_orders' );
	if ( ! $is_owner && ! $is_staff ) {
		wp_die( esc_html__( 'Unauthorized access.', 'shopforge' ) );
	}

	$pdf = shopforge_receipt_generate_pdf( $order );
	if ( ! $pdf ) {
		wp_die( esc_html__( 'Could not generate the receipt PDF.', 'shopforge' ) );
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . shopforge_receipt_filename( $order ) . '"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	echo $pdf; // phpcs:ignore
	exit;
}

function shopforge_receipt_download_url( WC_Order $order ): string {
	return add_query_arg( [
		'action'   => 'shopforge_download_receipt',
		'order_id' => $order->get_id(),
		'nonce'    => wp_create_nonce( 'shopforge_receipt_' . $order->get_id() ),
	], admin_url( 'admin-post.php' ) );
}


// =============================================================================
// EMAIL — invio manuale dell'allegato PDF (admin)
// =============================================================================

add_action( 'wp_ajax_shopforge_email_receipt', function () {
	check_ajax_referer( 'shopforge_receipt_email', 'nonce' );
	if ( ! current_user_can( 'edit_shop_orders' ) ) wp_send_json_error();

	$order_id = absint( $_POST['order_id'] ?? 0 );
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) wp_send_json_error( __( 'Invalid order.', 'shopforge' ) );

	$to = $order->get_billing_email();
	if ( ! $to ) wp_send_json_error( __( 'This order has no billing email.', 'shopforge' ) );

	$pdf = shopforge_receipt_generate_pdf( $order );
	if ( ! $pdf ) wp_send_json_error( __( 'Could not generate the receipt PDF.', 'shopforge' ) );

	$tmp_file = wp_tempnam( 'shopforge-receipt' );
	file_put_contents( $tmp_file, $pdf ); // phpcs:ignore

	$site_name = get_bloginfo( 'name' );
	/* translators: 1: site name, 2: order number */
	$subject = sprintf( __( '[%1$s] Receipt for order #%2$s', 'shopforge' ), $site_name, $order->get_order_number() );
	/* translators: 1: customer name, 2: order number, 3: site name */
	$body = sprintf(
		__( "Dear %1\$s,\n\nPlease find attached the receipt for your order #%2\$s.\n\nThank you,\n%3\$s", 'shopforge' ),
		$order->get_billing_first_name(),
		$order->get_order_number(),
		$site_name
	);

	$sent = wp_mail( $to, $subject, $body, [], [ $tmp_file ] );
	wp_delete_file( $tmp_file );

	if ( ! $sent ) wp_send_json_error( __( 'Sending failed.', 'shopforge' ) );

	wp_send_json_success();
} );


// =============================================================================
// ADMIN — metabox sull'ordine
// =============================================================================

add_action( 'add_meta_boxes', function () {
	foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
		add_meta_box(
			'shopforge-receipt',
			__( 'Receipt', 'shopforge' ),
			'shopforge_receipt_metabox_render',
			$screen,
			'side',
			'default'
		);
	}
} );

function shopforge_receipt_metabox_render( $post_or_order ): void {
	$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
	if ( ! $order ) return;

	$number = $order->get_meta( '_shopforge_receipt_number' );
	$date   = $order->get_meta( '_shopforge_receipt_date' );
	?>
	<p>
		<strong><?php esc_html_e( 'Receipt number:', 'shopforge' ); ?></strong>
		<?php echo $number ? esc_html( $number ) : esc_html__( 'Not issued yet — assigned on first download.', 'shopforge' ); ?>
	</p>
	<?php if ( $date ) : ?>
	<p>
		<strong><?php esc_html_e( 'Issued on:', 'shopforge' ); ?></strong>
		<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ); ?>
	</p>
	<?php endif; ?>
	<p>
		<a href="<?php echo esc_url( shopforge_receipt_download_url( $order ) ); ?>" class="button" target="_blank">
			<?php esc_html_e( 'Download PDF', 'shopforge' ); ?>
		</a>
		<button type="button" class="button" id="shopforge-email-receipt"
		        data-order="<?php echo esc_attr( $order->get_id() ); ?>"
		        data-nonce="<?php echo esc_attr( wp_create_nonce( 'shopforge_receipt_email' ) ); ?>">
			<?php esc_html_e( 'Email to customer', 'shopforge' ); ?>
		</button>
	</p>
	<p class="description" id="shopforge-email-receipt-status"></p>
	<script>
	document.getElementById('shopforge-email-receipt')?.addEventListener('click', function () {
		var btn    = this;
		var status = document.getElementById('shopforge-email-receipt-status');
		btn.disabled = true;
		status.textContent = <?php echo wp_json_encode( __( 'Sending…', 'shopforge' ) ); ?>;
		fetch(ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=shopforge_email_receipt&order_id=' + btn.dataset.order + '&nonce=' + btn.dataset.nonce
		}).then(function (r) { return r.json(); }).then(function (d) {
			btn.disabled = false;
			status.textContent = d.success
				? <?php echo wp_json_encode( __( 'Sent.', 'shopforge' ) ); ?>
				: (d.data || <?php echo wp_json_encode( __( 'Error.', 'shopforge' ) ); ?>);
		}).catch(function () {
			btn.disabled = false;
			status.textContent = <?php echo wp_json_encode( __( 'Error.', 'shopforge' ) ); ?>;
		});
	});
	</script>
	<?php
}


// =============================================================================
// ACCOUNT — l'ordine mostra un link download nella pagina "Visualizza ordine"
// =============================================================================

add_action( 'woocommerce_order_details_after_order_table', function ( WC_Order $order ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) return;
	$user_id = get_current_user_id();
	if ( ! $user_id || (int) $order->get_customer_id() !== $user_id ) return;
	?>
	<p class="shopforge-receipt-download">
		<a href="<?php echo esc_url( shopforge_receipt_download_url( $order ) ); ?>" class="button" target="_blank">
			<?php esc_html_e( 'Download Receipt (PDF)', 'shopforge' ); ?>
		</a>
	</p>
	<?php
}, 30 );


// =============================================================================
// IMPOSTAZIONI — tab "Receipts" nella pagina ShopForge
// =============================================================================

add_action( 'admin_post_shopforge_save_receipt_settings', function () {
	if ( ! current_user_can( 'manage_woocommerce' )
	     || ! check_admin_referer( 'shopforge_save_receipt_settings' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'shopforge' ) );
	}

	$template = sanitize_key( $_POST['shopforge_receipt_template'] ?? 'modern' );
	if ( ! isset( shopforge_receipt_templates()[ $template ] ) ) {
		$template = 'modern';
	}
	update_option( 'shopforge_receipt_template', $template );

	$settings = [
		'company_name'    => sanitize_text_field( $_POST['company_name'] ?? '' ),
		'company_address' => sanitize_textarea_field( $_POST['company_address'] ?? '' ),
		'company_vat'     => sanitize_text_field( $_POST['company_vat'] ?? '' ),
		'company_email'   => sanitize_email( $_POST['company_email'] ?? '' ),
		'footer_note'     => sanitize_textarea_field( $_POST['footer_note'] ?? '' ),
		'number_prefix'   => sanitize_text_field( $_POST['number_prefix'] ?? '' ),
		'logo_id'         => absint( $_POST['logo_id'] ?? 0 ),
	];
	update_option( 'shopforge_receipt_settings', $settings );

	wp_redirect( admin_url( 'admin.php?page=shopforge&tab=receipts&updated=1' ) );
	exit;
} );

function shopforge_admin_tab_receipts(): void {
	$current_template = shopforge_receipt_get_template();
	$settings          = shopforge_receipt_get_settings();

	wp_enqueue_media();
	?>
	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p>&#10003; <?php esc_html_e( 'Receipt settings saved.', 'shopforge' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'shopforge_save_receipt_settings' ); ?>
		<input type="hidden" name="action" value="shopforge_save_receipt_settings">

		<div class="shopforge-section-label">
			<i class="fa-solid fa-palette" aria-hidden="true"></i>
			<?php esc_html_e( 'Template', 'shopforge' ); ?>
			<span class="shopforge-section-hint"><?php esc_html_e( 'Choose the visual style used for every generated PDF receipt.', 'shopforge' ); ?></span>
		</div>

		<div class="shopforge-theme-grid">
			<?php foreach ( shopforge_receipt_templates() as $slug => $tpl ) : ?>
			<label class="shopforge-theme-card <?php echo $slug === $current_template ? 'is-selected' : ''; ?>">
				<input type="radio" name="shopforge_receipt_template" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $current_template ); ?>>
				<span class="shopforge-theme-card__mock shopforge-receipt-mock--<?php echo esc_attr( $slug ); ?>">
					<span class="mock-line-a"></span>
					<span class="mock-line-b"></span>
					<span class="mock-row"><i></i><i></i><i></i></span>
					<span class="mock-row"><i></i><i></i><i></i></span>
				</span>
				<span class="shopforge-theme-card__name"><?php echo esc_html( $tpl['label'] ); ?></span>
				<span class="shopforge-theme-card__desc"><?php echo esc_html( $tpl['desc'] ); ?></span>
			</label>
			<?php endforeach; ?>
		</div>

		<div class="shopforge-section-label">
			<i class="fa-solid fa-building" aria-hidden="true"></i>
			<?php esc_html_e( 'Company details', 'shopforge' ); ?>
			<span class="shopforge-section-hint"><?php esc_html_e( 'Shown on every receipt. Defaults are pre-filled from your WooCommerce store settings.', 'shopforge' ); ?></span>
		</div>

		<div class="shopforge-config-grid">
			<div class="shopforge-config-field">
				<label for="company_name"><?php esc_html_e( 'Company name', 'shopforge' ); ?></label>
				<input type="text" id="company_name" name="company_name" class="shopforge-config-input" value="<?php echo esc_attr( $settings['company_name'] ); ?>">
			</div>
			<div class="shopforge-config-field">
				<label for="company_vat"><?php esc_html_e( 'VAT / Tax ID', 'shopforge' ); ?></label>
				<input type="text" id="company_vat" name="company_vat" class="shopforge-config-input" value="<?php echo esc_attr( $settings['company_vat'] ); ?>">
			</div>
			<div class="shopforge-config-field">
				<label for="company_email"><?php esc_html_e( 'Contact email', 'shopforge' ); ?></label>
				<input type="email" id="company_email" name="company_email" class="shopforge-config-input" value="<?php echo esc_attr( $settings['company_email'] ); ?>">
			</div>
			<div class="shopforge-config-field">
				<label for="number_prefix"><?php esc_html_e( 'Receipt number prefix', 'shopforge' ); ?></label>
				<input type="text" id="number_prefix" name="number_prefix" class="shopforge-config-input" value="<?php echo esc_attr( $settings['number_prefix'] ); ?>">
				<p class="shopforge-config-desc"><?php esc_html_e( 'The running number is appended automatically (e.g. INV-2026-000123). Changing the prefix does not affect already-issued receipt numbers.', 'shopforge' ); ?></p>
			</div>
			<div class="shopforge-config-field" style="grid-column: 1 / -1;">
				<label for="company_address"><?php esc_html_e( 'Company address', 'shopforge' ); ?></label>
				<textarea id="company_address" name="company_address" class="shopforge-config-input" rows="3"><?php echo esc_textarea( $settings['company_address'] ); ?></textarea>
			</div>
			<div class="shopforge-config-field" style="grid-column: 1 / -1;">
				<label for="footer_note"><?php esc_html_e( 'Footer note', 'shopforge' ); ?></label>
				<textarea id="footer_note" name="footer_note" class="shopforge-config-input" rows="3" placeholder="<?php esc_attr_e( 'E.g. payment terms, bank details, legal notes…', 'shopforge' ); ?>"><?php echo esc_textarea( $settings['footer_note'] ); ?></textarea>
				<p class="shopforge-config-desc"><?php esc_html_e( 'Printed at the bottom of every receipt.', 'shopforge' ); ?></p>
			</div>
		</div>

		<div class="shopforge-section-label">
			<i class="fa-solid fa-image" aria-hidden="true"></i>
			<?php esc_html_e( 'Logo', 'shopforge' ); ?>
			<span class="shopforge-section-hint"><?php esc_html_e( 'Defaults to your site logo if set. PNG or JPG recommended.', 'shopforge' ); ?></span>
		</div>

		<div class="shopforge-receipt-logo-field">
			<div class="shopforge-receipt-logo-preview" id="shopforge-receipt-logo-preview">
				<?php if ( $settings['logo_id'] ) : ?>
					<?php echo wp_get_attachment_image( $settings['logo_id'], 'medium' ); ?>
				<?php endif; ?>
			</div>
			<input type="hidden" name="logo_id" id="shopforge-receipt-logo-id" value="<?php echo esc_attr( $settings['logo_id'] ); ?>">
			<button type="button" class="button" id="shopforge-receipt-logo-select"><?php esc_html_e( 'Select logo', 'shopforge' ); ?></button>
			<button type="button" class="button-link" id="shopforge-receipt-logo-remove" <?php echo $settings['logo_id'] ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'shopforge' ); ?></button>
		</div>

		<div class="shopforge-settings-actions">
			<?php submit_button( __( 'Save settings', 'shopforge' ), 'primary large', 'submit', false ); ?>
		</div>
	</form>

	<script>
	jQuery(document).ready(function ($) {
		$('input[name="shopforge_receipt_template"]').on('change', function () {
			$('.shopforge-theme-card').removeClass('is-selected');
			$(this).closest('.shopforge-theme-card').addClass('is-selected');
		});

		var frame;
		$('#shopforge-receipt-logo-select').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({
				title: <?php echo wp_json_encode( __( 'Select logo', 'shopforge' ) ); ?>,
				button: { text: <?php echo wp_json_encode( __( 'Use this image', 'shopforge' ) ); ?> },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#shopforge-receipt-logo-id').val(attachment.id);
				$('#shopforge-receipt-logo-preview').html('<img src="' + attachment.url + '" style="max-height:80px">');
				$('#shopforge-receipt-logo-remove').show();
			});
			frame.open();
		});
		$('#shopforge-receipt-logo-remove').on('click', function (e) {
			e.preventDefault();
			$('#shopforge-receipt-logo-id').val('');
			$('#shopforge-receipt-logo-preview').empty();
			$(this).hide();
		});
	});
	</script>

	<style>
	/* ---- Etichette sezione (stesso stile della tab Moduli) ---- */
	.shopforge-section-label {
		display: flex; align-items: baseline; gap: 8px;
		font-size: 13px; font-weight: 700; color: #1d2327;
		text-transform: uppercase; letter-spacing: .06em;
		margin: 28px 0 10px;
		padding-bottom: 8px;
		border-bottom: 2px solid #dcdcde;
	}
	.shopforge-section-label i { color: #2271b1; }
	.shopforge-section-hint {
		font-size: 12px; font-weight: 400; color: #646970;
		text-transform: none; letter-spacing: 0;
	}

	/* ---- Card template ---- */
	.shopforge-theme-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
		gap: 14px;
		margin-bottom: 24px;
	}
	.shopforge-theme-card {
		display: block;
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 8px;
		padding: 14px 16px;
		cursor: pointer;
		transition: border-color .2s, box-shadow .2s;
	}
	.shopforge-theme-card:hover { border-color: #2271b1; }
	.shopforge-theme-card.is-selected {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		background: #f0f6fc;
	}
	.shopforge-theme-card input { position: absolute; opacity: 0; pointer-events: none; }
	.shopforge-theme-card__name {
		display: block; font-size: 13px; font-weight: 700; color: #1d2327;
	}
	.shopforge-theme-card__desc {
		display: block; font-size: 12px; color: #646970; line-height: 1.4; margin: 3px 0 0;
	}
	.shopforge-theme-card__mock {
		display: flex; flex-direction: column; gap: 4px;
		height: 64px; margin-bottom: 10px; padding: 7px;
		border: 1px solid #dcdcde; border-radius: 6px; background: #fff;
	}
	.shopforge-theme-card__mock .mock-line-a { background: #dcdcde; height: 6px; border-radius: 2px; }
	.shopforge-theme-card__mock .mock-line-b { background: #f0f0f1; height: 5px; width: 70%; border-radius: 2px; }
	.shopforge-theme-card__mock .mock-row { display: flex; gap: 4px; flex: 1; }
	.shopforge-theme-card__mock .mock-row i { display: block; flex: 1; background: #f0f0f1; border-radius: 2px; }

	/* ---- Griglia dati aziendali ---- */
	.shopforge-config-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
		gap: 14px;
		margin-bottom: 6px;
	}
	.shopforge-config-field {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 8px;
		padding: 16px 18px;
	}
	.shopforge-config-field label {
		display: flex; align-items: center; gap: 8px;
		font-size: 13px; font-weight: 700; color: #1d2327;
		margin-bottom: 8px;
	}
	.shopforge-config-input {
		width: 100%; box-sizing: border-box;
		padding: 7px 10px; border: 1px solid #8c8f94;
		border-radius: 4px; font-size: 13px;
	}
	.shopforge-config-desc {
		margin: 8px 0 0; font-size: 12px; color: #646970; line-height: 1.5;
	}

	/* ---- Logo ---- */
	.shopforge-receipt-logo-field { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
	.shopforge-receipt-logo-preview {
		width: 120px; height: 80px; border: 1px dashed #dcdcde; border-radius: 6px;
		display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff;
	}
	.shopforge-receipt-logo-preview img { max-width: 100%; max-height: 100%; }

	/* ---- Azioni ---- */
	.shopforge-settings-actions { margin-top: 14px; }

	/* ---- Anteprima template ricevuta ---- */
	.shopforge-receipt-mock--modern { background: #eef1f5; }
	.shopforge-receipt-mock--modern .mock-line-a { background: #006FEF; height: 14px; border-radius: 2px; margin-bottom: 6px; }
	.shopforge-receipt-mock--classic .mock-line-a { background: #1d2327; height: 3px; width: 60%; margin: 0 auto 6px; border-radius: 1px; }
	.shopforge-receipt-mock--minimal .mock-line-a { background: #1d2327; height: 2px; margin-bottom: 8px; }

	@media (max-width: 600px) {
		.shopforge-theme-grid,
		.shopforge-config-grid { grid-template-columns: 1fr; }
	}
	</style>
	<?php
}
