<?php
/**
 * Modulo RMA — Admin: menu, lista, metabox dettaglio/conversazione, AJAX.
 *
 * Caricato solo in admin da inc/modules/shopforge-mod-rma.php.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// MENU ADMIN
// =============================================================================

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Product Support (RMA)', 'shopforge' ),
		__( 'Repairs & Warranty', 'shopforge' ),
		'manage_woocommerce',
		'shopforge-rma',
		function () {
			wp_safe_redirect( admin_url( 'edit.php?post_type=shopforge_rma' ) );
			exit;
		},
		'dashicons-admin-tools',
		57
	);

	add_submenu_page( 'shopforge-rma', __( 'All Requests', 'shopforge' ), __( 'All Requests', 'shopforge' ), 'manage_woocommerce', 'edit.php?post_type=shopforge_rma' );
} );


// =============================================================================
// ENQUEUE
// =============================================================================

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post_type;
	if ( 'shopforge_rma' !== $post_type && 'post.php' !== $hook && 'post-new.php' !== $hook && 'edit.php' !== $hook ) {
		return;
	}

	wp_enqueue_script( 'shopforge-rma-admin', SHOPFORGE_URL . 'assets/js/shopforge-rma-admin.js', [], SHOPFORGE_VERSION, true );
	wp_localize_script( 'shopforge-rma-admin', 'shopforgeRmaAdmin', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'shopforge_rma_admin' ),
	] );
	wp_enqueue_style( 'shopforge-rma-admin', SHOPFORGE_URL . 'assets/css/shopforge-rma-admin.css', [], SHOPFORGE_VERSION );
} );


// =============================================================================
// LISTA — colonne, filtri, ricerca, azioni bulk
// =============================================================================

add_filter( 'manage_shopforge_rma_posts_columns', function ( $columns ) {
	$new = [ 'cb' => $columns['cb'], 'title' => __( 'Request', 'shopforge' ) ];
	$new['shopforge_rma_tipo']      = __( 'Type', 'shopforge' );
	$new['shopforge_rma_cliente']   = __( 'Customer', 'shopforge' );
	$new['shopforge_rma_ordine']    = __( 'Order', 'shopforge' );
	$new['shopforge_rma_prodotto']  = __( 'Product', 'shopforge' );
	$new['shopforge_rma_stato']     = __( 'Status', 'shopforge' );
	$new['shopforge_rma_assegnato'] = __( 'Assigned to', 'shopforge' );
	$new['shopforge_rma_data']      = __( 'Created', 'shopforge' );
	return $new;
} );

add_action( 'manage_shopforge_rma_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'shopforge_rma_tipo':
			$tipo = get_post_meta( $post_id, '_shopforge_rma_tipo_richiesta', true );
			echo '<span class="shopforge-rma-admin-badge shopforge-rma-admin-badge--' . ( 'reso' === $tipo ? 'reso' : 'assistenza' ) . '">' . ( 'reso' === $tipo ? esc_html__( 'Return', 'shopforge' ) : esc_html__( 'Support', 'shopforge' ) ) . '</span>';
			break;

		case 'shopforge_rma_cliente':
			$user = get_userdata( (int) get_post_meta( $post_id, '_shopforge_rma_user_id', true ) );
			echo $user ? esc_html( $user->display_name ) . '<br><small>' . esc_html( $user->user_email ) . '</small>' : '—';
			break;

		case 'shopforge_rma_ordine':
			$order_id = (int) get_post_meta( $post_id, '_shopforge_rma_order_id', true );
			$order    = $order_id ? wc_get_order( $order_id ) : null;
			echo $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '—';
			break;

		case 'shopforge_rma_prodotto':
			$product_id = (int) get_post_meta( $post_id, '_shopforge_rma_product_id', true );
			$product    = $product_id ? wc_get_product( $product_id ) : null;
			echo $product ? '<a href="' . esc_url( admin_url( 'post.php?post=' . $product_id . '&action=edit' ) ) . '">' . esc_html( $product->get_name() ) . '</a>' : '—';
			break;

		case 'shopforge_rma_stato':
			$stato = get_post_meta( $post_id, '_shopforge_rma_stato', true ) ?: 'aperta';
			echo '<span class="shopforge-rma-admin-badge shopforge-rma-admin-status-' . esc_attr( $stato ) . '">' . esc_html( shopforge_rma_get_status_label( $stato ) ) . '</span>';
			break;

		case 'shopforge_rma_assegnato':
			$assigned = get_userdata( (int) get_post_meta( $post_id, '_shopforge_rma_assigned_to', true ) );
			echo $assigned ? esc_html( $assigned->display_name ) : '—';
			break;

		case 'shopforge_rma_data':
			$created = get_post_meta( $post_id, '_shopforge_rma_data_creazione', true );
			echo esc_html( $created ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created ) ) : get_the_date() );
			break;
	}
}, 10, 2 );

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'shopforge_rma' !== $post_type ) return;

	$current_status = sanitize_text_field( $_GET['shopforge_rma_stato'] ?? '' );
	?>
	<select name="shopforge_rma_stato">
		<option value=""><?php esc_html_e( 'All statuses', 'shopforge' ); ?></option>
		<?php foreach ( shopforge_rma_get_statuses() as $key => $label ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
	$current_order = absint( $_GET['shopforge_rma_order'] ?? 0 );
	?>
	<input type="number" name="shopforge_rma_order" placeholder="<?php esc_attr_e( 'Filter by Order ID', 'shopforge' ); ?>" value="<?php echo esc_attr( $current_order ?: '' ); ?>">
	<?php
	$current_user = absint( $_GET['shopforge_rma_user'] ?? 0 );
	wp_dropdown_users( [ 'name' => 'shopforge_rma_user', 'show_option_none' => __( 'All users', 'shopforge' ), 'selected' => $current_user ] );

	$current_assigned = absint( $_GET['shopforge_rma_assegnato'] ?? 0 );
	$assignable        = apply_filters( 'shopforge_rma_assignable_users', get_users( [ 'role__in' => [ 'administrator', 'shop_manager' ] ] ) );
	?>
	<select name="shopforge_rma_assegnato">
		<option value=""><?php esc_html_e( 'All operators', 'shopforge' ); ?></option>
		<?php foreach ( $assignable as $u ) : ?>
		<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $current_assigned, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" name="shopforge_rma_export_csv" value="1" class="button"><?php esc_html_e( 'Export CSV', 'shopforge' ); ?></button>
	<?php
}, 10, 1 );

add_filter( 'parse_query', function ( $query ) {
	global $pagenow, $post_type;
	if ( 'edit.php' !== $pagenow || 'shopforge_rma' !== $post_type ) return;

	$meta_query = [];
	if ( '' !== ( $_GET['shopforge_rma_stato'] ?? '' ) )     $meta_query[] = [ 'key' => '_shopforge_rma_stato', 'value' => sanitize_text_field( $_GET['shopforge_rma_stato'] ) ];
	if ( '' !== ( $_GET['shopforge_rma_order'] ?? '' ) )     $meta_query[] = [ 'key' => '_shopforge_rma_order_id', 'value' => absint( $_GET['shopforge_rma_order'] ) ];
	if ( '' !== ( $_GET['shopforge_rma_user'] ?? '' ) )      $meta_query[] = [ 'key' => '_shopforge_rma_user_id', 'value' => absint( $_GET['shopforge_rma_user'] ) ];
	if ( '' !== ( $_GET['shopforge_rma_assegnato'] ?? '' ) ) $meta_query[] = [ 'key' => '_shopforge_rma_assigned_to', 'value' => absint( $_GET['shopforge_rma_assegnato'] ) ];

	if ( $meta_query ) $query->set( 'meta_query', $meta_query );
} );

add_filter( 'posts_search', function ( $search, $wp_query ) {
	global $wpdb;
	if ( ! is_admin() || empty( $search ) || empty( $wp_query->query_vars['s'] ) ) return $search;
	if ( 'shopforge_rma' !== $wp_query->get( 'post_type' ) ) return $search;

	$term = '%' . $wpdb->esc_like( $wp_query->query_vars['s'] ) . '%';
	$meta_search = $wpdb->prepare(
		"OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_shopforge_rma_descrizione_problema','_shopforge_rma_messages') AND meta_value LIKE %s ) ",
		$term
	);
	$extended = preg_replace( '/\)\)\s*$/', ' ' . $meta_search . '))', $search, 1 );
	return $extended ?: $search;
}, 10, 2 );

add_filter( 'bulk_actions-edit-shopforge_rma', function ( $actions ) {
	foreach ( shopforge_rma_get_statuses() as $key => $label ) {
		/* translators: %s: status label */
		$actions[ 'shopforge_rma_set_status_' . $key ] = sprintf( __( 'Change status: %s', 'shopforge' ), $label );
	}
	$assignable = apply_filters( 'shopforge_rma_assignable_users', get_users( [ 'role__in' => [ 'administrator', 'shop_manager' ] ] ) );
	foreach ( $assignable as $u ) {
		/* translators: %s: user display name */
		$actions[ 'shopforge_rma_assign_' . $u->ID ] = sprintf( __( 'Assign to: %s', 'shopforge' ), $u->display_name );
	}
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-shopforge_rma', function ( $redirect_to, $action, $post_ids ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) return $redirect_to;

	if ( str_starts_with( $action, 'shopforge_rma_set_status_' ) ) {
		$status = substr( $action, strlen( 'shopforge_rma_set_status_' ) );
		if ( array_key_exists( $status, shopforge_rma_get_statuses() ) ) {
			$changed = 0;
			foreach ( $post_ids as $post_id ) {
				if ( 'shopforge_rma' === get_post_type( $post_id ) && shopforge_rma_update_status( $post_id, $status, get_current_user_id() ) ) {
					$changed++;
				}
			}
			return add_query_arg( [ 'shopforge_rma_bulk_status' => $changed ], $redirect_to );
		}
	}

	if ( str_starts_with( $action, 'shopforge_rma_assign_' ) ) {
		$assigned_to = absint( substr( $action, strlen( 'shopforge_rma_assign_' ) ) );
		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( 'shopforge_rma' === get_post_type( $post_id ) ) {
				update_post_meta( $post_id, '_shopforge_rma_assigned_to', $assigned_to );
				$count++;
			}
		}
		return add_query_arg( [ 'shopforge_rma_bulk_assigned' => $count ], $redirect_to );
	}

	return $redirect_to;
}, 10, 3 );

add_action( 'admin_notices', function () {
	if ( ! empty( $_REQUEST['shopforge_rma_bulk_status'] ) ) {
		/* translators: %d: number of requests */
		printf( '<div class="updated"><p>' . esc_html__( '%d requests updated.', 'shopforge' ) . '</p></div>', absint( $_REQUEST['shopforge_rma_bulk_status'] ) );
	}
	if ( ! empty( $_REQUEST['shopforge_rma_bulk_assigned'] ) ) {
		/* translators: %d: number of requests */
		printf( '<div class="updated"><p>' . esc_html__( '%d requests assigned.', 'shopforge' ) . '</p></div>', absint( $_REQUEST['shopforge_rma_bulk_assigned'] ) );
	}
} );


// =============================================================================
// METABOX — Dettagli richiesta + Conversazione
// =============================================================================

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'shopforge_rma_details', __( 'Request Details', 'shopforge' ), 'shopforge_rma_details_metabox_render', 'shopforge_rma', 'normal', 'high' );
	add_meta_box( 'shopforge_rma_messages', __( 'Conversation', 'shopforge' ), 'shopforge_rma_messages_metabox_render', 'shopforge_rma', 'normal', 'high' );
} );

function shopforge_rma_details_metabox_render( WP_Post $post ): void {
	$user_id     = (int) get_post_meta( $post->ID, '_shopforge_rma_user_id', true );
	$order_id    = (int) get_post_meta( $post->ID, '_shopforge_rma_order_id', true );
	$product_id  = (int) get_post_meta( $post->ID, '_shopforge_rma_product_id', true );
	$quantita    = get_post_meta( $post->ID, '_shopforge_rma_quantita', true ) ?: 1;
	$tipo        = get_post_meta( $post->ID, '_shopforge_rma_tipo_richiesta', true );
	$descrizione = get_post_meta( $post->ID, '_shopforge_rma_descrizione_problema', true );
	$rimedio     = get_post_meta( $post->ID, '_shopforge_rma_rimedio_scelto', true );
	$allegati    = get_post_meta( $post->ID, '_shopforge_rma_allegati', true ) ?: [];
	$motivo      = get_post_meta( $post->ID, '_shopforge_rma_motivo', true );
	$assigned_to = get_post_meta( $post->ID, '_shopforge_rma_assigned_to', true );
	$tr_corriere = get_post_meta( $post->ID, '_shopforge_rma_tracking_corriere', true );
	$tr_numero   = get_post_meta( $post->ID, '_shopforge_rma_tracking_numero', true );
	$history     = get_post_meta( $post->ID, '_shopforge_rma_status_history', true ) ?: [];
	$refund_id   = get_post_meta( $post->ID, '_shopforge_rma_refund_id', true );
	$stato       = get_post_meta( $post->ID, '_shopforge_rma_stato', true ) ?: 'aperta';

	wp_nonce_field( 'shopforge_rma_save_meta', 'shopforge_rma_meta_nonce' );

	$print_url = wp_nonce_url( add_query_arg( [ 'page' => 'shopforge-rma-print', 'request_id' => $post->ID ], admin_url( 'admin.php' ) ), 'shopforge_rma_print_request' );
	?>
	<p><a href="<?php echo esc_url( $print_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Print request', 'shopforge' ); ?></a></p>

	<table class="form-table">
		<tr>
			<th><label for="shopforge_rma_stato"><?php esc_html_e( 'Request Status', 'shopforge' ); ?></label></th>
			<td>
				<select name="shopforge_rma_stato" id="shopforge_rma_stato" class="shopforge-rma-status-select">
					<?php foreach ( shopforge_rma_get_statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $stato, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="shopforge_rma_assigned_to"><?php esc_html_e( 'Assigned to', 'shopforge' ); ?></label></th>
			<td>
				<select name="shopforge_rma_assigned_to" id="shopforge_rma_assigned_to">
					<option value="0"><?php esc_html_e( 'Unassigned', 'shopforge' ); ?></option>
					<?php foreach ( apply_filters( 'shopforge_rma_assignable_users', get_users( [ 'role__in' => [ 'administrator', 'shop_manager' ] ] ) ) as $u ) : ?>
					<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( (int) $assigned_to, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr><th><?php esc_html_e( 'Request Type', 'shopforge' ); ?></th><td><span class="shopforge-rma-admin-badge shopforge-rma-admin-badge--<?php echo 'reso' === $tipo ? 'reso' : 'assistenza'; ?>"><?php echo 'reso' === $tipo ? esc_html__( 'Return', 'shopforge' ) : esc_html__( 'Support', 'shopforge' ); ?></span></td></tr>
		<tr><th><?php esc_html_e( 'Quantity', 'shopforge' ); ?></th><td><?php echo esc_html( $quantita ); ?></td></tr>
		<tr>
			<th><?php esc_html_e( 'Customer', 'shopforge' ); ?></th>
			<td>
				<?php $user = $user_id ? get_userdata( $user_id ) : null; ?>
				<?php if ( $user ) : ?>
					<strong><?php echo esc_html( $user->display_name ); ?></strong><br><?php echo esc_html( $user->user_email ); ?><br>
					<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ); ?>"><?php esc_html_e( 'Edit user', 'shopforge' ); ?></a>
				<?php else : ?>—<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Order', 'shopforge' ); ?></th>
			<td>
				<?php $order = $order_id ? wc_get_order( $order_id ) : null; ?>
				<?php if ( $order ) : ?>
					<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a><br>
					<?php echo esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ); ?>
				<?php else : ?>—<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Product', 'shopforge' ); ?></th>
			<td>
				<?php $product = $product_id ? wc_get_product( $product_id ) : null; ?>
				<?php if ( $product ) : ?>
					<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $product_id . '&action=edit' ) ); ?>"><strong><?php echo esc_html( $product->get_name() ); ?></strong></a>
				<?php else : ?>—<?php endif; ?>
			</td>
		</tr>
		<tr><th><?php esc_html_e( 'Problem Description', 'shopforge' ); ?></th><td><?php echo wp_kses_post( wpautop( $descrizione ?: '—' ) ); ?></td></tr>
		<tr>
			<th><?php esc_html_e( 'Chosen Remedy', 'shopforge' ); ?></th>
			<td><?php echo $rimedio ? esc_html( shopforge_rma_get_remedy_options( $tipo )[ $rimedio ] ?? $rimedio ) : '—'; ?></td>
		</tr>
		<?php if ( 'rimborso_restituzione' === $rimedio && $order_id && $product_id ) : ?>
		<tr>
			<th><?php esc_html_e( 'WooCommerce Refund', 'shopforge' ); ?></th>
			<td>
				<?php if ( $refund_id ) :
					$refund_order  = wc_get_order( $refund_id );
					$refund_amount = $refund_order ? $refund_order->get_amount() : '';
				?>
					<span class="shopforge-rma-admin-badge shopforge-rma-admin-badge--reso"><?php /* translators: 1: refund ID, 2: refund amount */ printf( wp_kses_post( __( 'Refund #%1$d created (%2$s)', 'shopforge' ) ), (int) $refund_id, wp_kses_post( wc_price( $refund_amount ) ) ); ?></span>
				<?php elseif ( current_user_can( shopforge_rma_get_refund_capability() ) ) : ?>
					<button type="button" id="shopforge-rma-create-refund" class="button" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Create WooCommerce refund', 'shopforge' ); ?></button>
					<p class="description"><?php esc_html_e( 'Creates a real refund on the order for this request quantity and restores stock.', 'shopforge' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'You do not have permission to create a refund.', 'shopforge' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ( $allegati ) : ?>
		<tr>
			<th><?php esc_html_e( 'Attachments', 'shopforge' ); ?></th>
			<td><div class="shopforge-rma-attachments"><?php foreach ( $allegati as $attach_id ) shopforge_rma_render_attachment( $attach_id ); ?></div></td>
		</tr>
		<?php endif; ?>
		<?php if ( $motivo ) : ?>
		<tr><th><?php esc_html_e( 'Reason', 'shopforge' ); ?></th><td><?php echo esc_html( shopforge_rma_get_motivo_options()[ $motivo ] ?? $motivo ); ?></td></tr>
		<?php endif; ?>
		<tr>
			<th><?php esc_html_e( 'Acceptances', 'shopforge' ); ?></th>
			<td>
				<ul style="list-style:none;padding:0">
					<li><?php echo '1' === get_post_meta( $post->ID, '_shopforge_rma_accetto_termini', true ) ? esc_html__( 'Yes', 'shopforge' ) : esc_html__( 'No', 'shopforge' ); ?> — <?php esc_html_e( 'Terms and conditions', 'shopforge' ); ?></li>
					<li><?php echo '1' === get_post_meta( $post->ID, '_shopforge_rma_accetto_privacy', true ) ? esc_html__( 'Yes', 'shopforge' ) : esc_html__( 'No', 'shopforge' ); ?> — <?php esc_html_e( 'Privacy policy', 'shopforge' ); ?></li>
					<li><?php echo '1' === get_post_meta( $post->ID, '_shopforge_rma_accetto_procedura', true ) ? esc_html__( 'Yes', 'shopforge' ) : esc_html__( 'No', 'shopforge' ); ?> — <?php esc_html_e( 'Return procedure and conditions', 'shopforge' ); ?></li>
				</ul>
			</td>
		</tr>
		<tr>
			<th><label for="shopforge_rma_tracking_corriere"><?php esc_html_e( 'Shipment tracking', 'shopforge' ); ?></label></th>
			<td>
				<input type="text" name="shopforge_rma_tracking_corriere" id="shopforge_rma_tracking_corriere" value="<?php echo esc_attr( $tr_corriere ); ?>" placeholder="<?php esc_attr_e( 'Carrier', 'shopforge' ); ?>" style="margin-bottom:5px"><br>
				<input type="text" name="shopforge_rma_tracking_numero" id="shopforge_rma_tracking_numero" value="<?php echo esc_attr( $tr_numero ); ?>" placeholder="<?php esc_attr_e( 'Tracking number', 'shopforge' ); ?>">
				<p class="description"><?php esc_html_e( 'If filled in, it will be shown to the customer in the request detail.', 'shopforge' ); ?></p>
			</td>
		</tr>
		<?php if ( $history ) : ?>
		<tr>
			<th><?php esc_html_e( 'Status History', 'shopforge' ); ?></th>
			<td>
				<ul style="list-style:none;padding:0;margin:0">
					<?php foreach ( array_reverse( $history ) as $entry ) :
						$entry_user = ! empty( $entry['user_id'] ) ? get_userdata( $entry['user_id'] ) : null;
					?>
					<li style="margin-bottom:4px"><?php echo esc_html( shopforge_rma_get_status_label( $entry['to'] ) . ' — ' . $entry['date'] . ( $entry_user ? ' (' . $entry_user->display_name . ')' : '' ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
}

function shopforge_rma_render_attachment( int $attach_id ): void {
	$attachment = get_post( $attach_id );
	if ( ! $attachment ) return;
	$url      = wp_get_attachment_url( $attach_id );
	$is_image = str_contains( get_post_mime_type( $attach_id ), 'image' );
	?>
	<div class="shopforge-rma-attachment-item">
		<?php if ( $is_image ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo wp_get_attachment_image( $attach_id, 'thumbnail' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( basename( get_attached_file( $attach_id ) ) ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

function shopforge_rma_messages_metabox_render( WP_Post $post ): void {
	$messages = get_post_meta( $post->ID, '_shopforge_rma_messages', true ) ?: [];
	usort( $messages, fn( $a, $b ) => strtotime( $a['date'] ?? 'now' ) - strtotime( $b['date'] ?? 'now' ) );

	wp_nonce_field( 'shopforge_rma_add_admin_message', 'shopforge_rma_admin_message_nonce' );
	?>
	<div class="shopforge-rma-admin-messages">
		<?php if ( ! $messages ) : ?>
			<p><?php esc_html_e( 'No messages yet.', 'shopforge' ); ?></p>
		<?php else : ?>
			<?php foreach ( $messages as $message ) : ?>
			<div class="shopforge-rma-admin-message-item <?php echo ! empty( $message['is_admin'] ) ? 'is-admin' : 'is-customer'; ?>">
				<div class="shopforge-rma-admin-message-header">
					<strong><?php echo esc_html( shopforge_rma_get_message_author_label( ! empty( $message['is_admin'] ), $message['user_id'] ?? 0 ) ); ?></strong>
					<span><?php echo esc_html( ! empty( $message['date'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $message['date'] ) ) : '' ); ?></span>
				</div>
				<div class="shopforge-rma-admin-message-content"><?php echo wp_kses_post( wpautop( $message['message'] ?? '' ) ); ?></div>
				<?php if ( ! empty( $message['allegati'] ) ) : ?>
				<div class="shopforge-rma-attachments"><?php foreach ( $message['allegati'] as $attach_id ) shopforge_rma_render_attachment( $attach_id ); ?></div>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<div class="shopforge-rma-add-admin-message">
			<h4><?php esc_html_e( 'Add Message', 'shopforge' ); ?></h4>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'shopforge_rma_add_admin_message', 'shopforge_rma_admin_message_nonce' ); ?>
				<input type="hidden" name="shopforge_rma_id" value="<?php echo esc_attr( $post->ID ); ?>">
				<p><label for="shopforge_rma_admin_message_text"><?php esc_html_e( 'Message', 'shopforge' ); ?></label><br>
					<textarea name="shopforge_rma_admin_message_text" id="shopforge_rma_admin_message_text" rows="4" class="large-text"></textarea>
				</p>
				<p><label for="shopforge_rma_admin_message_allegati"><?php esc_html_e( 'Attachments (optional)', 'shopforge' ); ?></label><br>
					<input type="file" name="shopforge_rma_admin_message_allegati[]" id="shopforge_rma_admin_message_allegati" multiple accept="image/*,video/*,.pdf">
				</p>
				<p><button type="submit" class="button button-primary" name="shopforge_rma_send_admin_message"><?php esc_html_e( 'Send Message', 'shopforge' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
}

// Salvataggio campi metabox "Dettagli Richiesta" (stato/assegnazione/tracking).
add_action( 'save_post_shopforge_rma', function ( int $post_id ): void {
	if ( ! isset( $_POST['shopforge_rma_meta_nonce'] ) || ! wp_verify_nonce( $_POST['shopforge_rma_meta_nonce'], 'shopforge_rma_save_meta' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	if ( isset( $_POST['shopforge_rma_stato'] ) ) {
		shopforge_rma_update_status( $post_id, sanitize_text_field( $_POST['shopforge_rma_stato'] ), get_current_user_id() );
	}
	if ( isset( $_POST['shopforge_rma_assigned_to'] ) ) {
		update_post_meta( $post_id, '_shopforge_rma_assigned_to', absint( $_POST['shopforge_rma_assigned_to'] ) );
	}
	if ( isset( $_POST['shopforge_rma_tracking_corriere'] ) ) {
		update_post_meta( $post_id, '_shopforge_rma_tracking_corriere', sanitize_text_field( $_POST['shopforge_rma_tracking_corriere'] ) );
	}
	if ( isset( $_POST['shopforge_rma_tracking_numero'] ) ) {
		update_post_meta( $post_id, '_shopforge_rma_tracking_numero', sanitize_text_field( $_POST['shopforge_rma_tracking_numero'] ) );
	}
} );

// Invio messaggio admin (submit form classico dentro il metabox "Conversazione").
add_action( 'save_post_shopforge_rma', function (): void {
	if ( empty( $_POST['shopforge_rma_send_admin_message'] ) || empty( $_POST['shopforge_rma_admin_message_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['shopforge_rma_admin_message_nonce'], 'shopforge_rma_add_admin_message' ) ) return;
	if ( ! current_user_can( 'manage_woocommerce' ) ) return;

	$post_id = absint( $_POST['shopforge_rma_id'] ?? 0 );
	$text    = sanitize_textarea_field( $_POST['shopforge_rma_admin_message_text'] ?? '' );
	if ( ! $post_id || ! $text || 'shopforge_rma' !== get_post_type( $post_id ) ) return;

	$attachment_ids = [];
	if ( ! empty( $_FILES['shopforge_rma_admin_message_allegati'] ) ) {
		$attachment_ids = shopforge_rma_handle_uploaded_files( $_FILES['shopforge_rma_admin_message_allegati'] );
		foreach ( $attachment_ids as $attach_id ) {
			wp_update_post( [ 'ID' => $attach_id, 'post_parent' => $post_id ] );
		}
	}

	$messages   = get_post_meta( $post_id, '_shopforge_rma_messages', true ) ?: [];
	$messages[] = [ 'user_id' => get_current_user_id(), 'message' => $text, 'date' => current_time( 'mysql' ), 'is_admin' => true, 'allegati' => $attachment_ids ];
	update_post_meta( $post_id, '_shopforge_rma_messages', $messages );

	do_action( 'shopforge_rma_message_added', $post_id, end( $messages ), 'admin' );

	$current_status = get_post_meta( $post_id, '_shopforge_rma_stato', true ) ?: 'aperta';
	shopforge_rma_notify_status_update( $post_id, $current_status, 0, $text );

	wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&message=1' ) );
	exit;
}, 5 );


// =============================================================================
// AJAX ADMIN — cambio stato / rimborso
// =============================================================================

add_action( 'wp_ajax_shopforge_rma_update_status', function () {
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_rma_admin' ) ) {
		wp_send_json_error( [ 'message' => __( 'Security: invalid nonce.', 'shopforge' ) ] );
	}
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'shopforge' ) ] );
	}

	$post_id = absint( $_POST['post_id'] ?? 0 );
	$stato   = sanitize_text_field( $_POST['stato'] ?? '' );

	if ( ! $post_id || 'shopforge_rma' !== get_post_type( $post_id ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid request.', 'shopforge' ) ] );
	}
	if ( ! array_key_exists( $stato, shopforge_rma_get_statuses() ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid status.', 'shopforge' ) ] );
	}

	shopforge_rma_update_status( $post_id, $stato, get_current_user_id() );

	wp_send_json_success( [ 'stato' => $stato, 'label' => shopforge_rma_get_status_label( $stato ) ] );
} );

add_action( 'wp_ajax_shopforge_rma_create_refund', function () {
	if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'shopforge_rma_admin' ) ) {
		wp_send_json_error( [ 'message' => __( 'Security: invalid nonce.', 'shopforge' ) ] );
	}
	if ( ! current_user_can( shopforge_rma_get_refund_capability() ) ) {
		wp_send_json_error( [ 'message' => __( 'Insufficient permissions to create a refund.', 'shopforge' ) ] );
	}

	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id || 'shopforge_rma' !== get_post_type( $post_id ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid request.', 'shopforge' ) ] );
	}
	if ( get_post_meta( $post_id, '_shopforge_rma_refund_id', true ) ) {
		wp_send_json_error( [ 'message' => __( 'A refund was already created for this request.', 'shopforge' ) ] );
	}

	$order_id   = (int) get_post_meta( $post_id, '_shopforge_rma_order_id', true );
	$product_id = (int) get_post_meta( $post_id, '_shopforge_rma_product_id', true );
	$quantita   = max( 1, (int) get_post_meta( $post_id, '_shopforge_rma_quantita', true ) );

	$order = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_send_json_error( [ 'message' => __( 'Invalid order.', 'shopforge' ) ] );
	}

	$order_item    = null;
	$order_item_id = 0;
	foreach ( $order->get_items() as $item_id => $item ) {
		if ( (int) $item->get_product_id() === $product_id || (int) $item->get_variation_id() === $product_id ) {
			$order_item    = $item;
			$order_item_id = $item_id;
			break;
		}
	}
	if ( ! $order_item ) {
		wp_send_json_error( [ 'message' => __( 'Product not found in the order.', 'shopforge' ) ] );
	}

	$item_qty   = $order_item->get_quantity();
	$refund_qty = min( $quantita, $item_qty );
	$ratio      = $item_qty > 0 ? ( $refund_qty / $item_qty ) : 0;
	$decimals   = wc_get_price_decimals();

	$refund_total     = round( (float) $order_item->get_total() * $ratio, $decimals );
	$refund_tax_total = round( (float) $order_item->get_total_tax() * $ratio, $decimals );

	$refund_taxes = [];
	$item_taxes   = $order_item->get_taxes();
	foreach ( $item_taxes['total'] ?? [] as $tax_id => $tax_amount ) {
		$refund_taxes[ $tax_id ] = round( (float) $tax_amount * $ratio, $decimals );
	}

	$refund = wc_create_refund( [
		'order_id'      => $order_id,
		'amount'        => $refund_total + $refund_tax_total,
		/* translators: %d: request ID */
		'reason'        => sprintf( __( 'Refund for Product Support request #%d', 'shopforge' ), $post_id ),
		'line_items'    => [ $order_item_id => [ 'qty' => $refund_qty, 'refund_total' => $refund_total, 'refund_tax' => $refund_taxes ] ],
		'restock_items' => true,
	] );

	if ( is_wp_error( $refund ) ) {
		wp_send_json_error( [ 'message' => $refund->get_error_message() ] );
	}

	update_post_meta( $post_id, '_shopforge_rma_refund_id', $refund->get_id() );
	shopforge_rma_update_status( $post_id, 'rimborsata', get_current_user_id() );

	wp_send_json_success( [
		/* translators: %s: refund amount */
		'message'   => sprintf( __( 'Refund of %s created successfully.', 'shopforge' ), wp_strip_all_tags( wc_price( $refund_total + $refund_tax_total ) ) ),
		'refund_id' => $refund->get_id(),
	] );
} );
