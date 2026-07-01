<?php
/**
 * Scheda prodotto — FAQ, Compatibilità, Schede tecniche (PDF)
 *
 * Portato da woo-ordini-e-resi (wc-resi-assistenza): 3 shortcode + 3 metabox
 * sulla scheda prodotto, dati statici del prodotto senza logica d'ordine —
 * sempre attivi, non è un modulo disattivabile.
 *
 * Meta prodotto:
 *  _shopforge_product_faqs          → [ { question, answer } ]
 *  _shopforge_product_compatibility → [ 'stringa', ... ]
 *  _shopforge_product_datasheets    → [ { attachment_id } ]
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// SHORTCODE [product_faq]
// =============================================================================

add_shortcode( 'product_faq', function ( $atts ) {
	$atts = shortcode_atts( [
		'product_id' => 0,
		'style'      => 'accordion',
	], $atts, 'product_faq' );

	$product_id = absint( $atts['product_id'] );
	if ( ! $product_id && is_product() ) {
		$product_id = get_the_ID();
	}
	if ( ! $product_id ) {
		return '';
	}

	$faqs = get_post_meta( $product_id, '_shopforge_product_faqs', true );
	$faqs = is_array( $faqs ) ? $faqs : [];
	if ( empty( $faqs ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="shopforge-faq-section shopforge-faq-<?php echo esc_attr( $atts['style'] ); ?>">
		<h2>Domande Frequenti</h2>
		<div class="shopforge-faq-list">
			<?php foreach ( $faqs as $faq ) : ?>
				<div class="shopforge-faq-item">
					<div class="shopforge-faq-question">
						<h3><?php echo esc_html( $faq['question'] ); ?></h3>
						<span class="shopforge-faq-toggle">+</span>
					</div>
					<div class="shopforge-faq-answer">
						<?php echo wp_kses_post( wpautop( $faq['answer'] ) ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );


// =============================================================================
// SHORTCODE [product_compatibility]
// =============================================================================

add_shortcode( 'product_compatibility', function ( $atts ) {
	$atts = shortcode_atts( [
		'product_id' => 0,
	], $atts, 'product_compatibility' );

	$product_id = absint( $atts['product_id'] );
	if ( ! $product_id && is_product() ) {
		$product_id = get_the_ID();
	}
	if ( ! $product_id ) {
		return '';
	}

	$items = get_post_meta( $product_id, '_shopforge_product_compatibility', true );
	$items = is_array( $items ) ? $items : [];
	if ( empty( $items ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="shopforge-compat-section">
		<h2>Compatibilità</h2>
		<ul class="shopforge-compat-list">
			<?php foreach ( $items as $item ) : ?>
				<li><span class="dashicons dashicons-yes"></span><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
} );


// =============================================================================
// SHORTCODE [product_datasheets]
// =============================================================================

add_shortcode( 'product_datasheets', function ( $atts ) {
	$atts = shortcode_atts( [
		'product_id' => 0,
	], $atts, 'product_datasheets' );

	$product_id = absint( $atts['product_id'] );
	if ( ! $product_id && is_product() ) {
		$product_id = get_the_ID();
	}
	if ( ! $product_id ) {
		return '';
	}

	$datasheets = get_post_meta( $product_id, '_shopforge_product_datasheets', true );
	$datasheets = is_array( $datasheets ) ? $datasheets : [];
	if ( empty( $datasheets ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="shopforge-datasheets-section">
		<h2>Schede Tecniche</h2>
		<div class="shopforge-datasheets-list">
			<?php foreach ( $datasheets as $datasheet ) :
				$attachment_id = isset( $datasheet['attachment_id'] ) ? absint( $datasheet['attachment_id'] ) : 0;
				if ( ! $attachment_id ) continue;
				$attachment = get_post( $attachment_id );
				if ( ! $attachment ) continue;
				$url = wp_get_attachment_url( $attachment_id );
			?>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="shopforge-datasheet-btn" download>
					<span class="dashicons dashicons-media-document"></span>
					<?php echo esc_html( basename( $attachment->post_title ) ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );


// =============================================================================
// FRONTEND — enqueue CSS/JS
// =============================================================================

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'shopforge-product-info',
		SHOPFORGE_URL . 'assets/css/shopforge-product-info.css',
		[],
		SHOPFORGE_VERSION
	);
	wp_enqueue_script(
		'shopforge-product-info',
		SHOPFORGE_URL . 'assets/js/shopforge-product-info.js',
		[],
		SHOPFORGE_VERSION,
		true
	);
} );


// =============================================================================
// ADMIN — Metabox FAQ
// =============================================================================

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'shopforge-product-faq',
		'FAQ Prodotto',
		'shopforge_product_faq_metabox_render',
		'product',
		'normal',
		'default'
	);
} );

function shopforge_product_faq_metabox_render( WP_Post $post ): void {
	$faqs = get_post_meta( $post->ID, '_shopforge_product_faqs', true );
	$faqs = is_array( $faqs ) ? $faqs : [];

	wp_nonce_field( 'shopforge_save_product_faq', 'shopforge_product_faq_nonce' );
	?>
	<div id="shopforge-faq-container">
		<?php foreach ( $faqs as $index => $faq ) : ?>
			<?php shopforge_product_faq_row( $index, $faq ); ?>
		<?php endforeach; ?>
	</div>
	<button type="button" class="button button-secondary" id="shopforge-add-faq-btn">+ Aggiungi FAQ</button>

	<script>
	jQuery( function ( $ ) {
		let faqCount = <?php echo count( $faqs ); ?>;

		$( '#shopforge-add-faq-btn' ).on( 'click', function () {
			const html = `<?php echo shopforge_product_faq_row_template(); ?>`;
			$( '#shopforge-faq-container' ).append( html.replace( /\{\{INDEX\}\}/g, faqCount ) );
			faqCount++;
		} );

		$( document ).on( 'click', '.shopforge-remove-faq-btn', function () {
			$( this ).closest( '.shopforge-mb-row' ).remove();
		} );
	} );
	</script>
	<?php
}

function shopforge_product_faq_row( $index, array $faq ): void {
	$question = $faq['question'] ?? '';
	$answer   = $faq['answer'] ?? '';
	?>
	<div class="shopforge-mb-row">
		<span class="shopforge-mb-row__label">Domanda</span>
		<input type="text" name="shopforge_product_faqs[<?php echo esc_attr( $index ); ?>][question]" value="<?php echo esc_attr( $question ); ?>" placeholder="Es. Come faccio a contattarvi?">

		<span class="shopforge-mb-row__label">Risposta</span>
		<textarea name="shopforge_product_faqs[<?php echo esc_attr( $index ); ?>][answer]" placeholder="Es. Puoi contattarci via email…"><?php echo esc_textarea( $answer ); ?></textarea>

		<button type="button" class="shopforge-remove-faq-btn">Rimuovi</button>
	</div>
	<?php
}

function shopforge_product_faq_row_template(): string {
	ob_start();
	shopforge_product_faq_row( '{{INDEX}}', [ 'question' => '', 'answer' => '' ] );
	return ob_get_clean();
}

add_action( 'save_post_product', function ( int $product_id ): void {
	if ( ! isset( $_POST['shopforge_product_faq_nonce'] )
	     || ! wp_verify_nonce( $_POST['shopforge_product_faq_nonce'], 'shopforge_save_product_faq' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_product', $product_id ) ) return;

	$faqs = [];
	foreach ( (array) ( $_POST['shopforge_product_faqs'] ?? [] ) as $row ) {
		if ( ! isset( $row['question'], $row['answer'] ) ) continue;
		$question = sanitize_text_field( $row['question'] );
		$answer   = wp_kses_post( $row['answer'] );
		if ( $question && $answer ) {
			$faqs[] = [ 'question' => $question, 'answer' => $answer ];
		}
	}
	update_post_meta( $product_id, '_shopforge_product_faqs', $faqs );
} );


// =============================================================================
// ADMIN — Metabox Compatibilità
// =============================================================================

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'shopforge-product-compatibility',
		'Compatibilità',
		'shopforge_product_compatibility_metabox_render',
		'product',
		'normal',
		'default'
	);
} );

function shopforge_product_compatibility_metabox_render( WP_Post $post ): void {
	$items = get_post_meta( $post->ID, '_shopforge_product_compatibility', true );
	$items = is_array( $items ) ? $items : [];

	wp_nonce_field( 'shopforge_save_product_compatibility', 'shopforge_product_compatibility_nonce' );
	?>
	<div id="shopforge-compatibility-container">
		<?php foreach ( $items as $index => $item ) : ?>
			<?php shopforge_product_compatibility_row( $index, $item ); ?>
		<?php endforeach; ?>
	</div>
	<button type="button" class="button button-secondary" id="shopforge-add-compatibility-btn">+ Aggiungi compatibilità</button>

	<script>
	jQuery( function ( $ ) {
		let itemCount = <?php echo count( $items ); ?>;

		$( '#shopforge-add-compatibility-btn' ).on( 'click', function () {
			const html = `<?php echo shopforge_product_compatibility_row_template(); ?>`;
			$( '#shopforge-compatibility-container' ).append( html.replace( /\{\{INDEX\}\}/g, itemCount ) );
			itemCount++;
		} );

		$( document ).on( 'click', '.shopforge-remove-compatibility-btn', function () {
			$( this ).closest( '.shopforge-mb-row' ).remove();
		} );
	} );
	</script>
	<?php
}

function shopforge_product_compatibility_row( $index, string $item ): void {
	?>
	<div class="shopforge-mb-row">
		<input type="text" name="shopforge_product_compatibility[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $item ); ?>" placeholder="Es. iOS 14+, Android 10+">
		<button type="button" class="shopforge-remove-compatibility-btn">Rimuovi</button>
	</div>
	<?php
}

function shopforge_product_compatibility_row_template(): string {
	ob_start();
	shopforge_product_compatibility_row( '{{INDEX}}', '' );
	return ob_get_clean();
}

add_action( 'save_post_product', function ( int $product_id ): void {
	if ( ! isset( $_POST['shopforge_product_compatibility_nonce'] )
	     || ! wp_verify_nonce( $_POST['shopforge_product_compatibility_nonce'], 'shopforge_save_product_compatibility' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_product', $product_id ) ) return;

	$items = [];
	foreach ( (array) ( $_POST['shopforge_product_compatibility'] ?? [] ) as $value ) {
		$value = sanitize_text_field( $value );
		if ( $value ) $items[] = $value;
	}
	update_post_meta( $product_id, '_shopforge_product_compatibility', $items );
} );


// =============================================================================
// ADMIN — Metabox Schede tecniche (PDF)
// =============================================================================

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'shopforge-product-datasheets',
		'Schede Tecniche (PDF)',
		'shopforge_product_datasheets_metabox_render',
		'product',
		'normal',
		'default'
	);
} );

add_action( 'admin_enqueue_scripts', function () {
	$screen = get_current_screen();
	if ( $screen && 'product' === $screen->post_type ) {
		wp_enqueue_media();
	}
} );

function shopforge_product_datasheets_metabox_render( WP_Post $post ): void {
	$datasheets = get_post_meta( $post->ID, '_shopforge_product_datasheets', true );
	$datasheets = is_array( $datasheets ) ? $datasheets : [];

	wp_nonce_field( 'shopforge_save_product_datasheets', 'shopforge_product_datasheets_nonce' );
	?>
	<div id="shopforge-datasheets-container">
		<?php foreach ( $datasheets as $index => $datasheet ) : ?>
			<?php shopforge_product_datasheet_row( $index, $datasheet ); ?>
		<?php endforeach; ?>
	</div>
	<button type="button" class="button button-secondary" id="shopforge-add-datasheet-btn">+ Aggiungi Scheda Tecnica</button>

	<script>
	jQuery( function ( $ ) {
		let datasheetCount = <?php echo count( $datasheets ); ?>;

		function initUploader( $btn ) {
			$btn.on( 'click', function ( e ) {
				e.preventDefault();
				const $row   = $( this ).closest( '.shopforge-mb-row' );
				const $input = $row.find( '.shopforge-datasheet-file-input' );

				const frame = wp.media( {
					title: 'Seleziona PDF',
					button: { text: 'Usa questo file' },
					library: { type: 'application/pdf' },
					multiple: false,
				} );

				frame.on( 'select', function () {
					const attachment = frame.state().get( 'selection' ).first().toJSON();
					$input.val( attachment.id );
					$row.find( '.shopforge-datasheet-preview' ).html(
						'<span class="dashicons dashicons-media-document"></span> ' + attachment.filename
					);
				} );

				frame.open();
			} );
		}

		$( '#shopforge-add-datasheet-btn' ).on( 'click', function () {
			const html = `<?php echo shopforge_product_datasheet_row_template(); ?>`;
			const $new = $( html.replace( /\{\{INDEX\}\}/g, datasheetCount ) );
			$( '#shopforge-datasheets-container' ).append( $new );
			initUploader( $new.find( '.shopforge-datasheet-upload-btn' ) );
			datasheetCount++;
		} );

		$( document ).on( 'click', '.shopforge-remove-datasheet-btn', function () {
			$( this ).closest( '.shopforge-mb-row' ).remove();
		} );

		$( '.shopforge-datasheet-upload-btn' ).each( function () {
			initUploader( $( this ) );
		} );
	} );
	</script>
	<?php
}

function shopforge_product_datasheet_row( $index, array $datasheet ): void {
	$attachment_id = isset( $datasheet['attachment_id'] ) ? absint( $datasheet['attachment_id'] ) : 0;
	$attachment    = $attachment_id ? get_post( $attachment_id ) : null;
	$filename      = $attachment ? basename( $attachment->post_title ) : '';
	?>
	<div class="shopforge-mb-row">
		<span class="shopforge-mb-row__label">Scheda Tecnica</span>
		<div class="shopforge-datasheet-controls">
			<div class="shopforge-datasheet-preview">
				<?php if ( $filename ) : ?>
					<span class="dashicons dashicons-media-document"></span> <?php echo esc_html( $filename ); ?>
				<?php else : ?>
					<span class="shopforge-datasheet-empty">Nessun file selezionato</span>
				<?php endif; ?>
			</div>
			<button type="button" class="button shopforge-datasheet-upload-btn">Seleziona PDF</button>
			<button type="button" class="shopforge-remove-datasheet-btn">Rimuovi</button>
		</div>
		<input type="hidden" class="shopforge-datasheet-file-input" name="shopforge_product_datasheets[<?php echo esc_attr( $index ); ?>][attachment_id]" value="<?php echo esc_attr( $attachment_id ); ?>">
	</div>
	<?php
}

function shopforge_product_datasheet_row_template(): string {
	ob_start();
	shopforge_product_datasheet_row( '{{INDEX}}', [] );
	return ob_get_clean();
}

add_action( 'save_post_product', function ( int $product_id ): void {
	if ( ! isset( $_POST['shopforge_product_datasheets_nonce'] )
	     || ! wp_verify_nonce( $_POST['shopforge_product_datasheets_nonce'], 'shopforge_save_product_datasheets' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_product', $product_id ) ) return;

	$datasheets = [];
	foreach ( (array) ( $_POST['shopforge_product_datasheets'] ?? [] ) as $row ) {
		$attachment_id = absint( $row['attachment_id'] ?? 0 );
		if ( $attachment_id ) $datasheets[] = [ 'attachment_id' => $attachment_id ];
	}
	update_post_meta( $product_id, '_shopforge_product_datasheets', $datasheets );
} );


// =============================================================================
// ADMIN — CSS condiviso dai 3 metabox (scheda prodotto)
// =============================================================================

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'product' !== $screen->post_type ) return;
	?>
	<style>
	.shopforge-mb-row {
		background: #f9f9f9;
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 12px;
		margin-bottom: 12px;
	}
	.shopforge-mb-row__label {
		font-weight: 600;
		display: block;
		margin-bottom: 8px;
	}
	.shopforge-mb-row input[type="text"],
	.shopforge-mb-row textarea {
		width: 100%;
		margin-bottom: 8px;
	}
	.shopforge-mb-row textarea { min-height: 80px; }
	.shopforge-remove-faq-btn,
	.shopforge-remove-compatibility-btn,
	.shopforge-remove-datasheet-btn {
		background: #dc3545;
		color: #fff;
		border: none;
		padding: 4px 8px;
		border-radius: 2px;
		cursor: pointer;
	}
	.shopforge-remove-faq-btn:hover,
	.shopforge-remove-compatibility-btn:hover,
	.shopforge-remove-datasheet-btn:hover {
		background: #c82333;
	}
	.shopforge-datasheet-controls {
		display: flex;
		gap: 8px;
		align-items: flex-start;
	}
	.shopforge-datasheet-preview {
		flex: 1;
		padding: 8px;
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 3px;
		display: flex;
		align-items: center;
		gap: 8px;
		color: #666;
	}
	.shopforge-datasheet-empty { color: #999; }
	.shopforge-datasheet-file-input { display: none; }
	</style>
	<?php
} );
