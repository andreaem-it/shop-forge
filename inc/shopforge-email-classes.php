<?php
/**
 * Definizioni classi email ShopForge.
 *
 * Questo file viene caricato DENTRO il filtro woocommerce_email_classes,
 * momento in cui WC_Email è garantita disponibile.
 *
 * NON includere direttamente: usare shopforge-emails.php.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// EMAIL ADMIN — notifica nuova richiesta di recesso
// =============================================================================

class ShopForge_Email_Return_Admin extends WC_Email {

	/** @var WC_Order */
	public WC_Order $order;

	/** @var array Dati della richiesta di recesso */
	public array $return_data = [];

	public function __construct() {
		$this->id             = 'shopforge_return_admin';
		$this->title          = __( 'ShopForge – New withdrawal request (Admin)', 'shopforge' );
		$this->description    = __( 'Sent to the store manager when a customer submits a withdrawal declaration.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-return-admin.php';
		$this->template_plain = 'emails/shopforge-return-admin-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';

		$this->placeholders = [
			'{site_title}'   => $this->get_blogname(),
			'{order_number}' => '',
			'{ref}'          => '',
		];

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );

		parent::__construct();

		$this->heading = $this->get_option( 'heading',
			__( 'New withdrawal request', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject',
			__( '[{site_title}] Withdrawal — Order #{order_number} — Ref. {ref}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $return_data ): void {
		$this->setup_locale();
		$this->object      = $order;
		$this->return_data = $return_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{ref}']          = $return_data['ref'] ?? '';
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'return_data'   => $this->return_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'return_data'   => $this->return_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'   => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'recipient' => [ 'title' => __( 'Recipient', 'shopforge' ), 'type' => 'text', 'description' => __( 'Enter an email address. Separate multiple with commas.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'   => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Available placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'   => [ 'title' => __( 'Email heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Available placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'description' => __( 'Choose the format for this email.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Withdrawal — Order #{order_number} — Ref. {ref}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'New withdrawal request', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — ricevuta di recesso
// =============================================================================

class ShopForge_Email_Return_Customer extends WC_Email {

	public WC_Order $order;
	public array $return_data = [];

	public function __construct() {
		$this->id             = 'shopforge_return_customer';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – Withdrawal receipt (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer as confirmation of the withdrawal declaration, as required by EU consumer law.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-return-customer.php';
		$this->template_plain = 'emails/shopforge-return-customer-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [
			'{site_title}'    => $this->get_blogname(),
			'{order_number}'  => '',
			'{ref}'           => '',
			'{customer_name}' => '',
		];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Withdrawal receipt', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Withdrawal receipt — Order #{order_number} — Ref. {ref}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $return_data ): void {
		$this->setup_locale();
		$this->object      = $order;
		$this->return_data = $return_data;
		$this->placeholders['{order_number}']  = $order->get_order_number();
		$this->placeholders['{ref}']           = $return_data['ref'] ?? '';
		$this->placeholders['{customer_name}'] = $order->get_billing_first_name();
		$this->recipient = $order->get_billing_email();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'return_data'   => $this->return_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'return_data'   => $this->return_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Available placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}, {customer_name}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Email heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Available placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}, {customer_name}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'description' => __( 'Choose the format for this email.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Withdrawal receipt — Order #{order_number} — Ref. {ref}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Withdrawal receipt', 'shopforge' ); }
}


// =============================================================================
// EMAIL ADMIN — notifica nuovo ticket assistenza
// =============================================================================

class ShopForge_Email_Ticket_Admin extends WC_Email {

	public WC_Order $order;
	public array $ticket_data = [];

	public function __construct() {
		$this->id             = 'shopforge_ticket_admin';
		$this->title          = __( 'ShopForge – New support ticket (Admin)', 'shopforge' );
		$this->description    = __( 'Sent to the store manager when a customer opens a support request.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-ticket-admin.php';
		$this->template_plain = 'emails/shopforge-ticket-admin-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'New support request', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Support — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $ticket_data ): void {
		$this->setup_locale();
		$this->object      = $order;
		$this->ticket_data = $ticket_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'ticket_data'   => $this->ticket_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'ticket_data'   => $this->ticket_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'recipient'  => [ 'title' => __( 'Recipient', 'shopforge' ), 'type' => 'text', 'description' => __( 'Separate multiple addresses with commas.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'description' => __( 'Email format.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Support — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'New support request', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — conferma apertura ticket
// =============================================================================

class ShopForge_Email_Ticket_Customer extends WC_Email {

	public WC_Order $order;
	public array $ticket_data = [];

	public function __construct() {
		$this->id             = 'shopforge_ticket_customer';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – Support ticket confirmation (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer as confirmation the ticket was opened.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-ticket-customer.php';
		$this->template_plain = 'emails/shopforge-ticket-customer-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Support request received', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] We received your request — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $ticket_data ): void {
		$this->setup_locale();
		$this->object      = $order;
		$this->ticket_data = $ticket_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->recipient = $order->get_billing_email();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'ticket_data'   => $this->ticket_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'ticket_data'   => $this->ticket_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholders: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'description' => __( 'Email format.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] We received your request — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Support request received', 'shopforge' ); }
}


// =============================================================================
// EMAIL ADMIN — notifica nuovo preventivo
// =============================================================================

class ShopForge_Email_Quote_Admin extends WC_Email {

	public int $user_id = 0;
	public array $quote_data = [];

	public function __construct() {
		$this->id             = 'shopforge_quote_admin';
		$this->title          = __( 'ShopForge – New quote (Admin)', 'shopforge' );
		$this->description    = __( 'Sent to the store manager when a customer requests a quote.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-quote-admin.php';
		$this->template_plain = 'emails/shopforge-quote-admin-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname() ];
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'New quote request', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Quote requested', 'shopforge' ) );
	}

	public function trigger( int $user_id, array $quote_data ): void {
		$this->setup_locale();
		$this->user_id    = $user_id;
		$this->quote_data = $quote_data;
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'user_id'       => $this->user_id,
			'quote_data'    => $this->quote_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'user_id'       => $this->user_id,
			'quote_data'    => $this->quote_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'recipient'  => [ 'title' => __( 'Recipient', 'shopforge' ), 'type' => 'text', 'description' => __( 'Separate multiple addresses with commas.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholders: %s', 'shopforge' ), '<code>{site_title}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Quote requested', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'New quote request', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — conferma preventivo inviato
// =============================================================================

class ShopForge_Email_Quote_Customer extends WC_Email {

	public int $user_id = 0;
	public array $quote_data = [];

	public function __construct() {
		$this->id             = 'shopforge_quote_customer';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – Quote confirmation (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer as confirmation the quote request was submitted.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-quote-customer.php';
		$this->template_plain = 'emails/shopforge-quote-customer-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname() ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Quote received', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] We received your quote request', 'shopforge' ) );
	}

	public function trigger( int $user_id, array $quote_data ): void {
		$this->setup_locale();
		$this->user_id    = $user_id;
		$this->quote_data = $quote_data;
		$user = get_userdata( $user_id );
		$this->recipient  = $user ? $user->user_email : '';
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'user_id'       => $this->user_id,
			'quote_data'    => $this->quote_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'user_id'       => $this->user_id,
			'quote_data'    => $this->quote_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] We received your quote request', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Quote received', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — aggiornamento stato ticket assistenza
// =============================================================================

class ShopForge_Email_Ticket_Status_Update extends WC_Email {

	public WC_Order $order;
	public array $ticket_data = [];

	public function __construct() {
		$this->id             = 'shopforge_ticket_status_update';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – Ticket status update (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer when the store updates the ticket status.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-ticket-status-update.php';
		$this->template_plain = 'emails/shopforge-ticket-status-update-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Support request update', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Request update — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $ticket_data ): void {
		$this->setup_locale();
		$this->object      = $order;
		$this->ticket_data = $ticket_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->recipient = $order->get_billing_email();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'ticket_data'   => $this->ticket_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'ticket_data'   => $this->ticket_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Request update — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Support request update', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — aggiornamento stato reso/recesso
// =============================================================================

class ShopForge_Email_Return_Status_Update extends WC_Email {

	public WC_Order $order;
	public array $return_data = [];

	public function __construct() {
		$this->id             = 'shopforge_return_status_update';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – Return status update (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer when the store updates the return status.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-return-status-update.php';
		$this->template_plain = 'emails/shopforge-return-status-update-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Return request update', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Return update — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $return_data ): void {
		$this->setup_locale();
		$this->object      = $order;
		$this->return_data = $return_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->recipient = $order->get_billing_email();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'return_data'   => $this->return_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'return_data'   => $this->return_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Return update — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Return request update', 'shopforge' ); }
}


// =============================================================================
// EMAIL ADMIN — nuova richiesta RMA (assistenza/riparazione/sostituzione)
// =============================================================================

class ShopForge_Email_RMA_Admin extends WC_Email {

	public WC_Order $order;
	public array $rma_data = [];

	public function __construct() {
		$this->id             = 'shopforge_rma_admin';
		$this->title          = __( 'ShopForge – New RMA request (Admin)', 'shopforge' );
		$this->description    = __( 'Sent to the store manager when a customer opens a product support request, or cancels it themselves.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-rma-admin.php';
		$this->template_plain = 'emails/shopforge-rma-admin-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '', '{request_id}' => '' ];
		$this->recipient      = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'New product support request', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] RMA request #{request_id} — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $rma_data ): void {
		$this->setup_locale();
		$this->object   = $order;
		$this->rma_data = $rma_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{request_id}']   = $rma_data['request_id'] ?? '';
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'rma_data'      => $this->rma_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'rma_data'      => $this->rma_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'recipient'  => [ 'title' => __( 'Recipient', 'shopforge' ), 'type' => 'text', 'description' => __( 'Enter an email address. Separate multiple with commas.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] RMA request #{request_id} — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'New product support request', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — ricevuta richiesta RMA
// =============================================================================

class ShopForge_Email_RMA_Customer extends WC_Email {

	public WC_Order $order;
	public array $rma_data = [];

	public function __construct() {
		$this->id             = 'shopforge_rma_customer';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – RMA request receipt (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer when they open a support, repair, replacement or return request.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-rma-customer.php';
		$this->template_plain = 'emails/shopforge-rma-customer-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '', '{request_id}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'We received your request', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Request received — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $rma_data ): void {
		$this->setup_locale();
		$this->object   = $order;
		$this->rma_data = $rma_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{request_id}']   = $rma_data['request_id'] ?? '';
		$this->recipient = $order->get_billing_email();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'rma_data'      => $this->rma_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'rma_data'      => $this->rma_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Request received — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'We received your request', 'shopforge' ); }
}


// =============================================================================
// EMAIL CLIENTE — aggiornamento stato richiesta RMA
// =============================================================================

class ShopForge_Email_RMA_Status_Update extends WC_Email {

	public WC_Order $order;
	public array $rma_data = [];

	public function __construct() {
		$this->id             = 'shopforge_rma_status_update';
		$this->customer_email = true;
		$this->title          = __( 'ShopForge – RMA request update (Customer)', 'shopforge' );
		$this->description    = __( 'Sent to the customer when their RMA request status changes, or when they receive a new message from support.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-rma-status-update.php';
		$this->template_plain = 'emails/shopforge-rma-status-update-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'RMA request update', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Request update — Order #{order_number}', 'shopforge' ) );
	}

	public function trigger( WC_Order $order, array $rma_data ): void {
		$this->setup_locale();
		$this->object   = $order;
		$this->rma_data = $rma_data;
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->recipient = $order->get_billing_email();
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'rma_data'      => $this->rma_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'rma_data'      => $this->rma_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		], '', $this->template_base );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [ 'title' => __( 'Enable/Disable', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Enable this email notification', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Subject', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Heading', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Email type', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Request update — Order #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'RMA request update', 'shopforge' ); }
}
