<?php
/**
 * Modulo: Preventivi
 *
 * I clienti possono richiedere un preventivo personalizzato indicando
 * prodotti, quantità e note. L'admin gestisce le richieste dalla pagina
 * dedicata sotto WooCommerce e può rispondere via email.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// ENDPOINT — Contenuto pagina "Preventivi" nell'account
// =============================================================================

add_action( 'woocommerce_account_shopforge-quotes_endpoint', function () {
	$user_id = get_current_user_id();
	$quotes  = get_user_meta( $user_id, '_shopforge_quotes', true ) ?: [];
	usort( $quotes, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );

	$nonce = wp_create_nonce( 'shopforge_quote_' . $user_id );

	shopforge_account_section_header(
		__( 'Quotes', 'shopforge' ),
		'fa-solid fa-file-invoice',
		/* translators: %d: number of quote requests */
		sprintf( _n( '%d request', '%d requests', count( $quotes ), 'shopforge' ), count( $quotes ) )
	);

	$status_labels = [
		'pending'  => [ 'label' => __( 'Pending', 'shopforge' ),  'class' => 'pending' ],
		'sent'     => [ 'label' => __( 'Sent', 'shopforge' ),     'class' => 'sent' ],
		'accepted' => [ 'label' => __( 'Accepted', 'shopforge' ), 'class' => 'accepted' ],
		'declined' => [ 'label' => __( 'Declined', 'shopforge' ), 'class' => 'declined' ],
		'expired'  => [ 'label' => __( 'Expired', 'shopforge' ),  'class' => 'expired' ],
	];
	?>

	<!-- Form nuova richiesta -->
	<div class="shopforge-quote-new-card">
		<button type="button" class="shopforge-quote-toggle" id="shopforge-quote-toggle">
			<i class="fa-solid fa-plus" aria-hidden="true"></i>
			<?php esc_html_e( 'New quote request', 'shopforge' ); ?>
		</button>

		<form id="shopforge-quote-form" class="shopforge-quote-form" style="display:none">
			<p class="shopforge-quote-form__intro">
				<?php esc_html_e( 'List the products you would like a quote for. We will reply by email as soon as possible.', 'shopforge' ); ?>
			</p>

			<div id="shopforge-quote-rows">
				<div class="shopforge-quote-row-input">
					<input type="text" class="shopforge-qrow-name" placeholder="<?php esc_attr_e( 'Product name / SKU', 'shopforge' ); ?>" required>
					<input type="number" class="shopforge-qrow-qty" placeholder="<?php esc_attr_e( 'Qty', 'shopforge' ); ?>" min="1" value="1">
					<button type="button" class="shopforge-qrow-remove" aria-label="<?php esc_attr_e( 'Remove', 'shopforge' ); ?>">
						<i class="fa-solid fa-xmark"></i>
					</button>
				</div>
			</div>

			<button type="button" class="shopforge-btn shopforge-btn--ghost" id="shopforge-quote-add-row">
				<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add product', 'shopforge' ); ?>
			</button>

			<div class="shopforge-field">
				<label for="shopforge-quote-notes"><?php esc_html_e( 'Notes / special requests', 'shopforge' ); ?> <span style="font-weight:400;text-transform:none">(<?php esc_html_e( 'optional', 'shopforge' ); ?>)</span></label>
				<textarea id="shopforge-quote-notes" rows="3" placeholder="<?php esc_attr_e( 'Variants, alternative quantities, timing…', 'shopforge' ); ?>"></textarea>
			</div>

			<p class="shopforge-ret-error" id="shopforge-quote-error" style="display:none"></p>

			<button type="submit" class="shopforge-modal__submit" id="shopforge-quote-submit">
				<span id="shopforge-quote-label"><?php esc_html_e( 'Send request', 'shopforge' ); ?></span>
				<span class="shopforge-st-spinner" id="shopforge-quote-spinner" style="display:none"></span>
			</button>

			<div id="shopforge-quote-success" style="display:none" class="shopforge-ticket-success">
				<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
				<p class="shopforge-ts__title"><?php esc_html_e( 'Request sent', 'shopforge' ); ?></p>
				<p class="shopforge-ts__text" id="shopforge-quote-success-text"></p>
			</div>
		</form>
	</div>

	<!-- Storico preventivi -->
	<?php if ( ! empty( $quotes ) ) : ?>
	<div class="shopforge-quotes-list">
		<?php foreach ( $quotes as $quote ) :
			$st   = $status_labels[ $quote['status'] ] ?? $status_labels['pending'];
			$date = date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $quote['date'] ) );
		?>
		<div class="shopforge-quote-row-card">
			<div class="shopforge-quote-row-card__head">
				<span class="shopforge-quote-ref"><?php echo esc_html( $quote['ref'] ); ?></span>
				<span class="shopforge-quote-badge shopforge-quote-badge--<?php echo esc_attr( $st['class'] ); ?>">
					<?php echo esc_html( $st['label'] ); ?>
				</span>
			</div>
			<div class="shopforge-quote-row-card__body">
				<p class="shopforge-quote-meta"><?php echo esc_html( $date ); ?></p>
				<?php if ( ! empty( $quote['items'] ) ) : ?>
				<ul class="shopforge-quote-items">
					<?php foreach ( $quote['items'] as $item ) : ?>
					<li><strong><?php echo esc_html( $item['qty'] ); ?>×</strong> <?php echo esc_html( $item['name'] ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<?php if ( ! empty( $quote['notes'] ) ) : ?>
				<p class="shopforge-quote-notes"><?php echo esc_html( $quote['notes'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $quote['reply'] ) ) : ?>
				<div class="shopforge-quote-reply">
					<p class="shopforge-quote-reply__label">
						<i class="fa-solid fa-reply" aria-hidden="true"></i> <?php esc_html_e( 'Seller reply:', 'shopforge' ); ?>
					</p>
					<p class="shopforge-quote-reply__text"><?php echo nl2br( esc_html( $quote['reply'] ) ); ?></p>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php else : ?>
		<?php shopforge_account_empty_state(
			'fa-solid fa-file-invoice',
			__( 'No quote requests', 'shopforge' ),
			__( 'Use the form above to request a custom quote.', 'shopforge' )
		); ?>
	<?php endif; ?>

	<script>
	(function () {
		'use strict';
		var ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
		var nonce   = '<?php echo esc_js( $nonce ); ?>';
		var i18n = {
			atLeastOne: <?php echo wp_json_encode( __( 'Enter at least one product.', 'shopforge' ) ); ?>,
			sending:    <?php echo wp_json_encode( __( 'Sending…', 'shopforge' ) ); ?>,
			send:       <?php echo wp_json_encode( __( 'Send request', 'shopforge' ) ); ?>,
			genericErr: <?php echo wp_json_encode( __( 'Error. Try again.', 'shopforge' ) ); ?>,
			successTpl: <?php echo wp_json_encode( __( 'Ref. %1$s — you will receive a confirmation at %2$s', 'shopforge' ) ); ?>
		};

		document.getElementById('shopforge-quote-toggle')?.addEventListener('click', function () {
			var form = document.getElementById('shopforge-quote-form');
			var icon = this.querySelector('i');
			var show = form.style.display === 'none';
			form.style.display = show ? 'block' : 'none';
			icon.className = show ? 'fa-solid fa-minus' : 'fa-solid fa-plus';
		});

		document.getElementById('shopforge-quote-add-row')?.addEventListener('click', function () {
			var rows = document.getElementById('shopforge-quote-rows');
			var tpl  = rows.querySelector('.shopforge-quote-row-input').cloneNode(true);
			tpl.querySelector('.shopforge-qrow-name').value = '';
			tpl.querySelector('.shopforge-qrow-qty').value  = '1';
			rows.appendChild(tpl);
			tpl.querySelector('.shopforge-qrow-name').focus();
		});

		document.getElementById('shopforge-quote-rows')?.addEventListener('click', function (e) {
			var btn = e.target.closest('.shopforge-qrow-remove');
			if ( ! btn ) return;
			var rows = this.querySelectorAll('.shopforge-quote-row-input');
			if (rows.length > 1) btn.closest('.shopforge-quote-row-input').remove();
		});

		document.getElementById('shopforge-quote-form')?.addEventListener('submit', function (e) {
			e.preventDefault();
			var err    = document.getElementById('shopforge-quote-error');
			var label  = document.getElementById('shopforge-quote-label');
			var spin   = document.getElementById('shopforge-quote-spinner');
			var submit = document.getElementById('shopforge-quote-submit');
			err.style.display = 'none';

			var items = [];
			document.querySelectorAll('.shopforge-quote-row-input').forEach(function (row) {
				var name = row.querySelector('.shopforge-qrow-name').value.trim();
				var qty  = parseInt(row.querySelector('.shopforge-qrow-qty').value) || 1;
				if (name) items.push({ name: name, qty: qty });
			});

			if ( ! items.length) { err.textContent = i18n.atLeastOne; err.style.display = 'block'; return; }

			label.textContent = i18n.sending;
			spin.style.display = 'inline-block';
			submit.disabled = true;

			var params = new URLSearchParams({ action: 'shopforge_submit_quote', nonce: nonce, notes: document.getElementById('shopforge-quote-notes').value.trim() });
			items.forEach(function (it, i) {
				params.append('items[' + i + '][name]', it.name);
				params.append('items[' + i + '][qty]',  it.qty);
			});

			fetch(ajaxUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: params.toString() })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d.success) {
					document.getElementById('shopforge-quote-success').style.display = 'block';
					document.getElementById('shopforge-quote-success-text').textContent = i18n.successTpl.replace('%1$s', d.data.ref).replace('%2$s', d.data.email);
					submit.style.display = 'none';
				} else {
					err.textContent = d.data || i18n.genericErr;
					err.style.display = 'block';
					label.textContent = i18n.send;
					spin.style.display = 'none';
					submit.disabled = false;
				}
			});
		});
	})();
	</script>
	<?php
} );


// =============================================================================
// AJAX — Invia richiesta preventivo
// =============================================================================

add_action( 'wp_ajax_shopforge_submit_quote', 'shopforge_submit_quote_handler' );

function shopforge_submit_quote_handler(): void {
	$user_id = get_current_user_id();
	if ( ! $user_id ) wp_send_json_error( __( 'Unauthorized access.', 'shopforge' ) );

	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_quote_' . $user_id ) ) {
		wp_send_json_error( __( 'Session expired. Reload and try again.', 'shopforge' ) );
	}

	if ( function_exists( 'shopforge_check_rate_limit' )
		 && ! shopforge_check_rate_limit( 'submit_quote', 120 ) ) {
		wp_send_json_error( __( 'You already sent a quote request recently. Wait a few minutes and try again.', 'shopforge' ) );
	}

	$items = [];
	foreach ( (array) ( $_POST['items'] ?? [] ) as $item ) {
		$name = sanitize_text_field( $item['name'] ?? '' );
		$qty  = absint( $item['qty'] ?? 1 );
		if ( $name ) $items[] = [ 'name' => $name, 'qty' => max( 1, $qty ) ];
	}
	if ( empty( $items ) ) wp_send_json_error( __( 'Enter at least one product.', 'shopforge' ) );

	$notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
	$ref   = 'PRV-' . strtoupper( substr( md5( $user_id . time() ), 0, 8 ) );
	$now   = current_time( 'mysql' );

	$quotes   = get_user_meta( $user_id, '_shopforge_quotes', true ) ?: [];
	$quotes[] = [ 'ref' => $ref, 'date' => $now, 'items' => $items, 'notes' => $notes, 'status' => 'pending', 'reply' => '' ];
	update_user_meta( $user_id, '_shopforge_quotes', $quotes );

	// Email admin + conferma cliente — via classi WooCommerce native
	$quote_email_data = [
		'ref'   => $ref,
		'date'  => date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $now ) ),
		'items' => $items,
		'notes' => $notes,
	];
	$mailer    = WC()->mailer();
	$wc_emails = $mailer->get_emails();
	if ( isset( $wc_emails['ShopForge_Email_Quote_Admin'] ) ) {
		$wc_emails['ShopForge_Email_Quote_Admin']->trigger( $user_id, $quote_email_data );
	}
	if ( isset( $wc_emails['ShopForge_Email_Quote_Customer'] ) ) {
		$wc_emails['ShopForge_Email_Quote_Customer']->trigger( $user_id, $quote_email_data );
	}

	do_action( 'shopforge_notification', $user_id, 'quote_received', [
		/* translators: %s: quote reference */
		'text' => sprintf( __( 'Your quote request %s has been received.', 'shopforge' ), $ref ),
		'url'  => wc_get_account_endpoint_url( 'shopforge-quotes' ),
	] );

	wp_send_json_success( [ 'ref' => $ref, 'email' => wp_get_current_user()->user_email ] );
}


// =============================================================================
// ADMIN — Pagina gestione preventivi (WooCommerce → Preventivi)
// =============================================================================

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', __( 'Customer quotes', 'shopforge' ), __( 'Quotes', 'shopforge' ), 'edit_shop_orders', 'shopforge-quotes', 'shopforge_admin_quotes_page' );
} );

function shopforge_admin_quotes_page(): void {
	$status_map = [
		'pending'  => [ 'label' => __( 'Pending', 'shopforge' ),  'bg' => '#FEF9C3', 'color' => '#854D0E' ],
		'sent'     => [ 'label' => __( 'Sent', 'shopforge' ),     'bg' => '#DBEAFE', 'color' => '#1E40AF' ],
		'accepted' => [ 'label' => __( 'Accepted', 'shopforge' ), 'bg' => '#DCFCE7', 'color' => '#166534' ],
		'declined' => [ 'label' => __( 'Declined', 'shopforge' ), 'bg' => '#FEE2E2', 'color' => '#991B1B' ],
		'expired'  => [ 'label' => __( 'Expired', 'shopforge' ),  'bg' => '#F3F4F6', 'color' => '#6B7280' ],
	];

	$users = get_users( [ 'meta_key' => '_shopforge_quotes' ] );
	$all   = [];
	foreach ( $users as $user ) {
		foreach ( get_user_meta( $user->ID, '_shopforge_quotes', true ) ?: [] as $idx => $q ) {
			$all[] = array_merge( $q, [ '_user_id' => $user->ID, '_user_name' => $user->display_name, '_user_email' => $user->user_email, '_idx' => $idx ] );
		}
	}
	usort( $all, fn( $a, $b ) => strtotime( $b['date'] ) - strtotime( $a['date'] ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Customer quotes', 'shopforge' ); ?></h1>
		<?php if ( empty( $all ) ) : ?>
		<p><?php esc_html_e( 'No requests received.', 'shopforge' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped" style="margin-top:16px">
			<thead><tr>
				<th style="width:110px"><?php esc_html_e( 'Reference', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'shopforge' ); ?></th>
				<th><?php esc_html_e( 'Products', 'shopforge' ); ?></th>
				<th style="width:110px"><?php esc_html_e( 'Date', 'shopforge' ); ?></th>
				<th style="width:110px"><?php esc_html_e( 'Status', 'shopforge' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Actions', 'shopforge' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $all as $q ) :
				$st   = $status_map[ $q['status'] ] ?? $status_map['pending'];
				$key  = $q['_user_id'] . '-' . $q['_idx'];
			?>
			<tr>
				<td><strong><?php echo esc_html( $q['ref'] ); ?></strong></td>
				<td><?php echo esc_html( $q['_user_name'] ); ?><br><small><?php echo esc_html( $q['_user_email'] ); ?></small></td>
				<td>
					<?php foreach ( $q['items'] ?? [] as $it ) : ?><div><?php echo esc_html( $it['qty'] ); ?>× <?php echo esc_html( $it['name'] ); ?></div><?php endforeach; ?>
					<?php if ( $q['notes'] ) : ?><small style="color:#646970"><em><?php echo esc_html( $q['notes'] ); ?></em></small><?php endif; ?>
				</td>
				<td><?php echo date_i18n( 'd/m/Y', strtotime( $q['date'] ) ); ?></td>
				<td><span style="display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;background:<?php echo esc_attr( $st['bg'] ); ?>;color:<?php echo esc_attr( $st['color'] ); ?>"><?php echo esc_html( $st['label'] ); ?></span></td>
				<td><button type="button" class="button button-small shopforge-qadm-edit" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Manage', 'shopforge' ); ?></button></td>
			</tr>
			<tr class="shopforge-qadm-panel" id="shopforge-qpanel-<?php echo esc_attr( $key ); ?>" style="display:none">
				<td colspan="6" style="background:#f9f9f9;padding:16px">
					<label style="display:block;margin-bottom:8px;font-weight:600"><?php esc_html_e( 'Status:', 'shopforge' ); ?>
						<select class="shopforge-qadm-status">
							<?php foreach ( $status_map as $val => $info ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $q['status'], $val ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="display:block;margin-bottom:6px;font-weight:600"><?php esc_html_e( 'Reply to customer:', 'shopforge' ); ?></label>
					<textarea class="widefat shopforge-qadm-reply" rows="4" style="margin-bottom:8px"><?php echo esc_textarea( $q['reply'] ?? '' ); ?></textarea>
					<label style="display:block;margin-bottom:12px">
						<input type="checkbox" class="shopforge-qadm-email" checked> <?php esc_html_e( 'Send reply by email', 'shopforge' ); ?>
					</label>
					<button type="button" class="button button-primary shopforge-qadm-save"
					        data-user="<?php echo esc_attr( $q['_user_id'] ); ?>"
					        data-idx="<?php echo esc_attr( $q['_idx'] ); ?>"
					        data-ref="<?php echo esc_attr( $q['ref'] ); ?>"
					        data-email="<?php echo esc_attr( $q['_user_email'] ); ?>"
					        data-name="<?php echo esc_attr( $q['_user_name'] ); ?>"
					        data-nonce="<?php echo esc_attr( wp_create_nonce('shopforge_quote_admin') ); ?>"><?php esc_html_e( 'Save', 'shopforge' ); ?></button>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
	<script>
	document.querySelectorAll('.shopforge-qadm-edit').forEach(function(btn){
		btn.addEventListener('click', function(){
			var panel = document.getElementById('shopforge-qpanel-' + this.dataset.key);
			var show  = panel.style.display === 'none';
			panel.style.display = show ? 'table-row' : 'none';
			this.textContent = show ? <?php echo wp_json_encode( __( 'Close', 'shopforge' ) ); ?> : <?php echo wp_json_encode( __( 'Manage', 'shopforge' ) ); ?>;
		});
	});
	document.querySelectorAll('.shopforge-qadm-save').forEach(function(btn){
		btn.addEventListener('click', function(){
			var td    = this.closest('td');
			var reply  = td.querySelector('.shopforge-qadm-reply').value;
			var status = td.querySelector('.shopforge-qadm-status').value;
			var doMail = td.querySelector('.shopforge-qadm-email').checked ? '1' : '0';
			var me = this; me.disabled = true; me.textContent = '…';
			fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
				body: new URLSearchParams({ action:'shopforge_admin_quote_update', nonce:this.dataset.nonce,
					user_id:this.dataset.user, idx:this.dataset.idx, status:status, reply:reply,
					send_email:doMail, customer_email:this.dataset.email, customer_name:this.dataset.name, ref:this.dataset.ref }).toString()
			}).then(function(r){return r.json();}).then(function(d){
				me.disabled = false; me.textContent = d.success ? '✓ ' + <?php echo wp_json_encode( __( 'Saved', 'shopforge' ) ); ?> : '✗ ' + <?php echo wp_json_encode( __( 'Error', 'shopforge' ) ); ?>;
				if (d.success) setTimeout(function(){ location.reload(); }, 800);
			});
		});
	});
	</script>
	<?php
}

add_action( 'wp_ajax_shopforge_admin_quote_update', function () {
	check_ajax_referer( 'shopforge_quote_admin', 'nonce' );
	if ( ! current_user_can( 'edit_shop_orders' ) ) wp_send_json_error();

	$user_id  = absint( $_POST['user_id'] );
	$idx      = intval( $_POST['idx'] );
	$valid    = [ 'pending', 'sent', 'accepted', 'declined', 'expired' ];
	$status   = in_array( $_POST['status'], $valid, true ) ? $_POST['status'] : 'pending';
	$reply    = sanitize_textarea_field( $_POST['reply'] ?? '' );
	$do_mail  = ( $_POST['send_email'] ?? '' ) === '1';

	$quotes = get_user_meta( $user_id, '_shopforge_quotes', true ) ?: [];
	if ( ! isset( $quotes[ $idx ] ) ) wp_send_json_error();

	$quotes[ $idx ]['status'] = $status;
	$quotes[ $idx ]['reply']  = $reply;
	update_user_meta( $user_id, '_shopforge_quotes', $quotes );

	if ( $do_mail && $reply ) {
		$customer_email = sanitize_email( $_POST['customer_email'] ?? '' );
		$customer_name  = sanitize_text_field( $_POST['customer_name'] ?? '' );
		$ref            = sanitize_text_field( $_POST['ref'] ?? '' );
		$site_name      = get_bloginfo( 'name' );

		wp_mail(
			$customer_email,
			/* translators: 1: site name, 2: quote reference */
			sprintf( __( '[%1$s] Quote reply — Ref. %2$s', 'shopforge' ), $site_name, $ref ),
			/* translators: 1: customer name, 2: quote reference, 3: reply text, 4: site name */
			sprintf( __( "Dear %1$s,\n\nWe processed your quote request (ref. %2$s).\n\n--- Reply ---\n%3$s\n---\n\nThank you,\n%4$s", 'shopforge' ), $customer_name, $ref, $reply, $site_name ),
			[ 'Content-Type: text/plain; charset=UTF-8' ]
		);

		do_action( 'shopforge_notification', $user_id, 'quote_replied', [
			/* translators: %s: quote reference */
			'text' => sprintf( __( 'Your quote %s received a reply.', 'shopforge' ), $ref ),
			'url'  => wc_get_account_endpoint_url( 'shopforge-quotes' ),
		] );
	}

	wp_send_json_success();
} );


// =============================================================================
// CSS
// =============================================================================

add_action( 'wp_head', function () {
	// ponytail: is_wc_endpoint_url() non vede gli endpoint custom del plugin
	// (mai nel registro interno di WC) — get_query_var() legge WP direttamente.
	if ( false === get_query_var( 'shopforge-quotes', false ) ) return;
	?>
	<style id="shopforge-quotes-css">
	.shopforge-quote-new-card {
		margin-bottom: 24px;
		border: 1px solid var(--shopforge-border);
		border-radius: var(--shopforge-radius);
		background: #fff; box-shadow: var(--shopforge-shadow);
		overflow: hidden;
	}
	.shopforge-quote-toggle {
		width: 100%; padding: 16px 20px;
		display: flex; align-items: center; gap: 10px;
		background: none; border: none; cursor: pointer;
		font-size: 14px; font-weight: 700; color: var(--shopforge-primary);
		text-align: left; letter-spacing: normal; text-transform: none;
	}
	.shopforge-quote-toggle:hover { background: var(--shopforge-bg-soft); }
	.shopforge-quote-form {
		padding: 0 20px 20px;
		border-top: 1px solid var(--shopforge-border-soft);
	}
	.shopforge-quote-form__intro {
		margin: 14px 0 16px; font-size: 13px; color: var(--shopforge-text-muted);
	}
	.shopforge-quote-row-input {
		display: flex; gap: 8px; align-items: center; margin-bottom: 8px;
	}
	.shopforge-quote-row-input .shopforge-qrow-name { flex: 1; }
	.shopforge-quote-row-input .shopforge-qrow-qty  { width: 70px; }
	.shopforge-qrow-remove {
		width: 32px; height: 32px; flex-shrink: 0;
		background: none; border: 1px solid var(--shopforge-border);
		border-radius: 6px; cursor: pointer; color: var(--shopforge-text-muted);
		display: flex; align-items: center; justify-content: center;
	}
	.shopforge-qrow-remove:hover { background:#FEE2E2; color:#991B1B; border-color:#FECACA; }
	#shopforge-quote-add-row { margin-bottom: 16px; }

	/* Storico */
	.shopforge-quotes-list { display: flex; flex-direction: column; gap: 12px; }
	.shopforge-quote-row-card {
		padding: 16px 20px; background: #fff;
		border: 1px solid var(--shopforge-border);
		border-radius: var(--shopforge-radius); box-shadow: var(--shopforge-shadow);
	}
	.shopforge-quote-row-card__head {
		display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;
	}
	.shopforge-quote-ref { font-family: monospace; font-size: 13px; font-weight: 700; color: var(--shopforge-text-main); }
	.shopforge-quote-badge { padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
	.shopforge-quote-badge--pending  { background:#FEF9C3; color:#854D0E; }
	.shopforge-quote-badge--sent     { background:#DBEAFE; color:#1E40AF; }
	.shopforge-quote-badge--accepted { background:#DCFCE7; color:#166534; }
	.shopforge-quote-badge--declined { background:#FEE2E2; color:#991B1B; }
	.shopforge-quote-badge--expired  { background:#F3F4F6; color:#6B7280; }
	.shopforge-quote-meta  { margin: 0 0 6px; font-size: 12px; color: var(--shopforge-text-muted); }
	.shopforge-quote-items { margin: 0 0 6px; padding-left: 18px; font-size: 13px; }
	.shopforge-quote-notes { margin: 0 0 6px; font-size: 13px; color: var(--shopforge-text-muted); font-style: italic; }
	.shopforge-quote-reply {
		margin-top: 12px; padding: 12px 14px;
		background: #EFF6FF; border: 1px solid #BFDBFE;
		border-radius: var(--shopforge-radius);
	}
	.shopforge-quote-reply__label { margin: 0 0 6px; font-size: 12px; font-weight: 700; color: #1E40AF; }
	.shopforge-quote-reply__text  { margin: 0; font-size: 13px; color: #1E40AF; }
	</style>
	<?php
} );
