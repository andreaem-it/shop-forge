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
		$this->title          = __( 'ShopForge – Nuova richiesta di recesso (Admin)', 'shopforge' );
		$this->description    = __( 'Inviata al gestore del negozio quando un cliente invia una dichiarazione di recesso.', 'shopforge' );
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
			__( 'Nuova richiesta di recesso', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject',
			__( '[{site_title}] Recesso — Ordine #{order_number} — Rif. {ref}', 'shopforge' ) );
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
			'enabled'   => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'recipient' => [ 'title' => __( 'Destinatario', 'shopforge' ), 'type' => 'text', 'description' => __( 'Inserisci un indirizzo email. Separane più di uno con virgola.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'   => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder disponibili: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'   => [ 'title' => __( 'Intestazione email', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder disponibili: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'description' => __( 'Scegli il formato per questa email.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Recesso — Ordine #{order_number} — Rif. {ref}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Nuova richiesta di recesso', 'shopforge' ); }
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
		$this->title          = __( 'ShopForge – Ricevuta di recesso (Cliente)', 'shopforge' );
		$this->description    = __( 'Inviata al cliente come conferma della dichiarazione di recesso. Obbligatoria ai sensi dell\'art. 54-bis D.Lgs. 209/2025.', 'shopforge' );
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
		$this->heading = $this->get_option( 'heading', __( 'Ricevuta di recesso', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Ricevuta di recesso — Ordine #{order_number} — Rif. {ref}', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder disponibili: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}, {customer_name}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione email', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder disponibili: %s', 'shopforge' ), '<code>{site_title}, {order_number}, {ref}, {customer_name}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'description' => __( 'Scegli il formato per questa email.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Ricevuta di recesso — Ordine #{order_number} — Rif. {ref}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Ricevuta di recesso', 'shopforge' ); }
}


// =============================================================================
// EMAIL ADMIN — notifica nuovo ticket assistenza
// =============================================================================

class ShopForge_Email_Ticket_Admin extends WC_Email {

	public WC_Order $order;
	public array $ticket_data = [];

	public function __construct() {
		$this->id             = 'shopforge_ticket_admin';
		$this->title          = __( 'ShopForge – Nuovo ticket assistenza (Admin)', 'shopforge' );
		$this->description    = __( 'Inviata al gestore del negozio quando un cliente apre una richiesta di assistenza.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-ticket-admin.php';
		$this->template_plain = 'emails/shopforge-ticket-admin-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Nuova richiesta di assistenza', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Assistenza — Ordine #{order_number}', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'recipient'  => [ 'title' => __( 'Destinatario', 'shopforge' ), 'type' => 'text', 'description' => __( 'Separa più indirizzi con virgola.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'description' => __( 'Formato email.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Assistenza — Ordine #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Nuova richiesta di assistenza', 'shopforge' ); }
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
		$this->title          = __( 'ShopForge – Conferma ticket assistenza (Cliente)', 'shopforge' );
		$this->description    = __( 'Inviata al cliente come conferma dell\'apertura del ticket.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-ticket-customer.php';
		$this->template_plain = 'emails/shopforge-ticket-customer-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Richiesta di assistenza ricevuta', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Abbiamo ricevuto la tua richiesta — Ordine #{order_number}', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder: %s', 'shopforge' ), '<code>{site_title}, {order_number}</code>' ), 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'description' => __( 'Formato email.', 'shopforge' ), 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'desc_tip' => true, 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Abbiamo ricevuto la tua richiesta — Ordine #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Richiesta di assistenza ricevuta', 'shopforge' ); }
}


// =============================================================================
// EMAIL ADMIN — notifica nuovo preventivo
// =============================================================================

class ShopForge_Email_Quote_Admin extends WC_Email {

	public int $user_id = 0;
	public array $quote_data = [];

	public function __construct() {
		$this->id             = 'shopforge_quote_admin';
		$this->title          = __( 'ShopForge – Nuovo preventivo (Admin)', 'shopforge' );
		$this->description    = __( 'Inviata al gestore del negozio quando un cliente richiede un preventivo.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-quote-admin.php';
		$this->template_plain = 'emails/shopforge-quote-admin-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname() ];
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Nuova richiesta di preventivo', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Preventivo richiesto', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'recipient'  => [ 'title' => __( 'Destinatario', 'shopforge' ), 'type' => 'text', 'description' => __( 'Separa più indirizzi con virgola.', 'shopforge' ), 'placeholder' => get_option( 'admin_email' ), 'default' => '', 'desc_tip' => true ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'description' => sprintf( __( 'Placeholder: %s', 'shopforge' ), '<code>{site_title}</code>' ), 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Preventivo richiesto', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Nuova richiesta di preventivo', 'shopforge' ); }
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
		$this->title          = __( 'ShopForge – Conferma preventivo (Cliente)', 'shopforge' );
		$this->description    = __( 'Inviata al cliente come conferma dell\'invio della richiesta di preventivo.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-quote-customer.php';
		$this->template_plain = 'emails/shopforge-quote-customer-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname() ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Preventivo ricevuto', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Abbiamo ricevuto la tua richiesta di preventivo', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Abbiamo ricevuto la tua richiesta di preventivo', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Preventivo ricevuto', 'shopforge' ); }
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
		$this->title          = __( 'ShopForge – Aggiornamento stato ticket (Cliente)', 'shopforge' );
		$this->description    = __( 'Inviata al cliente quando il negozio aggiorna lo stato del ticket.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-ticket-status-update.php';
		$this->template_plain = 'emails/shopforge-ticket-status-update-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Aggiornamento richiesta di assistenza', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Aggiornamento richiesta — Ordine #{order_number}', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Aggiornamento richiesta — Ordine #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Aggiornamento richiesta di assistenza', 'shopforge' ); }
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
		$this->title          = __( 'ShopForge – Aggiornamento stato reso (Cliente)', 'shopforge' );
		$this->description    = __( 'Inviata al cliente quando il negozio aggiorna lo stato del reso.', 'shopforge' );
		$this->template_html  = 'emails/shopforge-return-status-update.php';
		$this->template_plain = 'emails/shopforge-return-status-update-plain.php';
		$this->template_base  = SHOPFORGE_DIR . 'woocommerce/';
		$this->placeholders   = [ '{site_title}' => $this->get_blogname(), '{order_number}' => '' ];
		parent::__construct();
		$this->heading = $this->get_option( 'heading', __( 'Aggiornamento richiesta di reso', 'shopforge' ) );
		$this->subject = $this->get_option( 'subject', __( '[{site_title}] Aggiornamento reso — Ordine #{order_number}', 'shopforge' ) );
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
			'enabled'    => [ 'title' => __( 'Abilita/Disabilita', 'shopforge' ), 'type' => 'checkbox', 'label' => __( 'Abilita questa notifica email', 'shopforge' ), 'default' => 'yes' ],
			'subject'    => [ 'title' => __( 'Oggetto', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_subject(), 'default' => '' ],
			'heading'    => [ 'title' => __( 'Intestazione', 'shopforge' ), 'type' => 'text', 'desc_tip' => true, 'placeholder' => $this->get_default_heading(), 'default' => '' ],
			'email_type' => [ 'title' => __( 'Tipo email', 'shopforge' ), 'type' => 'select', 'default' => 'html', 'class' => 'email_type wc-enhanced-select', 'options' => $this->get_email_type_options() ],
		];
	}

	public function get_default_subject(): string { return __( '[{site_title}] Aggiornamento reso — Ordine #{order_number}', 'shopforge' ); }
	public function get_default_heading(): string { return __( 'Aggiornamento richiesta di reso', 'shopforge' ); }
}
