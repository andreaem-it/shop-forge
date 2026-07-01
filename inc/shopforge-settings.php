<?php
/**
 * Andrea Emili — Pagina impostazioni modulare
 *
 * Mostra due sezioni distinte:
 *  1. FUNZIONALITÀ BASE (type: 'feature') — Stili e Dashboard
 *  2. MODULI (type: 'module') — Tracking, Wishlist, Preventivi, Resi, Notifiche
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

// La registrazione del menu è unica per tutte le tab: vedi
// inc/shopforge-admin-page.php (add_menu_page + router).


// =============================================================================
// SALVATAGGIO
// =============================================================================

add_action( 'admin_post_shopforge_save_modules', function () {
	if ( ! current_user_can( 'manage_woocommerce' )
	     || ! check_admin_referer( 'shopforge_save_modules' ) ) {
		wp_die( 'Accesso non autorizzato.' );
	}

	// ---- Moduli / feature ----
	// Senza licenza valida i moduli (type:'module') non sono attivabili,
	// anche se il POST li includesse (toggle disabilitati lato UI, ma
	// questo è il controllo che conta davvero). Le feature restano libere.
	$has_license = function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license();
	$registry    = shopforge_modules_registry();
	$enabled     = [];
	foreach ( array_keys( $registry ) as $id ) {
		if ( empty( $_POST[ 'module_' . $id ] ) ) {
			continue;
		}
		if ( ( $registry[ $id ]['type'] ?? 'module' ) === 'module' && ! $has_license ) {
			continue;
		}
		$enabled[] = $id;
	}

	$old = shopforge_get_enabled_modules();
	update_option( 'shopforge_modules_enabled', $enabled );

	if ( array_diff( $old, $enabled ) || array_diff( $enabled, $old ) ) {
		flush_rewrite_rules();
	}

	// ---- Impostazioni generali ----
	$return_days = max( 1, (int) ( $_POST['shopforge_return_window_days'] ?? 14 ) );
	update_option( 'shopforge_return_window_days', $return_days );

	$contact_url = esc_url_raw( trim( $_POST['shopforge_contact_url'] ?? '' ) );
	update_option( 'shopforge_contact_url', $contact_url );

	// ---- Colori personalizzati ----
	$color_defaults = shopforge_color_defaults();
	$colors         = [];
	foreach ( array_keys( $color_defaults ) as $key ) {
		$val = sanitize_hex_color( $_POST[ 'shopforge_color_' . $key ] ?? '' );
		$colors[ $key ] = $val ?: $color_defaults[ $key ];
	}
	update_option( 'shopforge_colors', $colors );

	wp_redirect( admin_url( 'admin.php?page=shopforge&tab=modules&updated=1' ) );
	exit;
} );


// =============================================================================
// COLORI — default e helper
// =============================================================================

function shopforge_color_defaults(): array {
	return [
		'primary'       => '#006FEF',
		'primary_hover' => '#168BFF',
		'text_main'     => '#07172F',
		'text_muted'    => '#64748B',
		'border'        => '#E2E8F0',
		'border_soft'   => '#EEF2F7',
		'bg_soft'       => '#F8FAFC',
		'success'       => '#16A34A',
		'warning'       => '#F59E0B',
		'danger'        => '#DC2626',
	];
}

function shopforge_get_colors(): array {
	$saved = get_option( 'shopforge_colors', [] );
	return array_merge( shopforge_color_defaults(), is_array( $saved ) ? $saved : [] );
}

// Output CSS vars dinamici in frontend (sovrascrive i default del file CSS)
add_action( 'wp_head', function () {
	if ( ! is_account_page() ) return;
	if ( ! function_exists( 'shopforge_is_module_active' ) ) return;
	if ( ! shopforge_is_module_active( 'styles' ) ) return;

	$c = shopforge_get_colors();
	echo '<style id="shopforge-colors">:root{'
		. '--shopforge-primary:'      . esc_attr( $c['primary'] )       . ';'
		. '--shopforge-primary-hover:' . esc_attr( $c['primary_hover'] ) . ';'
		. '--shopforge-text-main:'    . esc_attr( $c['text_main'] )     . ';'
		. '--shopforge-text-muted:'   . esc_attr( $c['text_muted'] )    . ';'
		. '--shopforge-border:'       . esc_attr( $c['border'] )        . ';'
		. '--shopforge-border-soft:'  . esc_attr( $c['border_soft'] )   . ';'
		. '--shopforge-bg-soft:'      . esc_attr( $c['bg_soft'] )       . ';'
		. '--shopforge-success:'      . esc_attr( $c['success'] )       . ';'
		. '--shopforge-warning:'      . esc_attr( $c['warning'] )       . ';'
		. '--shopforge-danger:'       . esc_attr( $c['danger'] )        . ';'
		. '}</style>' . "\n";
}, 99 );


// =============================================================================
// RENDER PAGINA
// =============================================================================

function shopforge_admin_tab_modules(): void {
	$registry    = shopforge_modules_registry();
	$enabled     = shopforge_get_enabled_modules();
	$has_license = function_exists( 'shopforge_has_valid_license' ) && shopforge_has_valid_license();

	// Separa feature da moduli
	$features = array_filter( $registry, fn( $m ) => ( $m['type'] ?? 'module' ) === 'feature' );
	$modules  = array_filter( $registry, fn( $m ) => ( $m['type'] ?? 'module' ) === 'module' );

	// FontAwesome per le icone nella pagina admin
	wp_enqueue_script( 'fontawesome-kit', SHOPFORGE_FA_KIT_URL, [], null, false );
	wp_script_add_data( 'fontawesome-kit', 'crossorigin', 'anonymous' );
	// Color picker WordPress
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	?>
		<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>✓ Impostazioni salvate.
			<?php if ( count( $enabled ) === 0 ) : ?>
				<strong>Attenzione:</strong> nessun modulo o funzionalità attiva.
			<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( ! $has_license ) : ?>
		<div class="shopforge-license-banner">
			<i class="fa-solid fa-lock" aria-hidden="true"></i>
			<span>Licenza non valida o non configurata — i moduli non possono essere attivati.</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=shopforge&tab=license' ) ); ?>" class="button button-small">Configura licenza</a>
		</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'shopforge_save_modules' ); ?>
			<input type="hidden" name="action" value="shopforge_save_modules">

			<!-- ═══════════════════════════════════════════
			     SEZIONE 1: FUNZIONALITÀ BASE
			     ═══════════════════════════════════════════ -->
			<div class="shopforge-section-label">
				<i class="fa-solid fa-sliders" aria-hidden="true"></i>
				Funzionalità di base
				<span class="shopforge-section-hint">
					Controllano il comportamento trasversale del plugin: stili CSS globali e dashboard.
					Disattivale se vuoi un'integrazione "zero impatto visivo".
				</span>
			</div>
			<div class="shopforge-modules-grid shopforge-modules-grid--features">
				<?php foreach ( $features as $id => $module ) :
					$is_active = in_array( $id, $enabled, true );
				?>
				<div class="shopforge-module-card shopforge-module-card--feature <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
					<?php shopforge_settings_card_inner( $id, $module, $is_active ); ?>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- ═══════════════════════════════════════════
			     SEZIONE 2: MODULI
			     ═══════════════════════════════════════════ -->
			<div class="shopforge-section-label">
				<i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
				Moduli
				<span class="shopforge-section-hint">
					Ogni modulo aggiunge funzionalità specifiche: endpoint nell'area account,
					integrazioni, email automatiche, gestione admin.
				</span>
			</div>
			<div class="shopforge-modules-grid">
				<?php foreach ( $modules as $id => $module ) :
					$is_active = in_array( $id, $enabled, true );
				?>
				<div class="shopforge-module-card <?php echo $is_active ? 'is-active' : 'is-inactive'; ?> <?php echo ! $has_license ? 'is-locked' : ''; ?>">
					<?php shopforge_settings_card_inner( $id, $module, $is_active, ! $has_license ); ?>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- ═══════════════════════════════════════════
			     SEZIONE 3: CONFIGURAZIONE
			     ═══════════════════════════════════════════ -->
			<div class="shopforge-section-label">
				<i class="fa-solid fa-gear" aria-hidden="true"></i>
				Configurazione
				<span class="shopforge-section-hint">
					Parametri globali che influenzano il comportamento dei moduli.
				</span>
			</div>
			<div class="shopforge-config-grid">

				<div class="shopforge-config-field">
					<label for="shopforge_return_window_days">
						<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
						Finestra di recesso (giorni)
					</label>
					<input type="number"
					       id="shopforge_return_window_days"
					       name="shopforge_return_window_days"
					       value="<?php echo esc_attr( get_option( 'shopforge_return_window_days', 14 ) ); ?>"
					       min="1" max="365" step="1"
					       class="shopforge-config-input">
					<p class="shopforge-config-desc">
						Numero di giorni dalla consegna entro cui il cliente può esercitare il diritto di recesso.
						Default: 14 giorni (termine legale minimo per il consumatore in UE).
					</p>
				</div>

				<div class="shopforge-config-field">
					<label for="shopforge_contact_url">
						<i class="fa-solid fa-headset" aria-hidden="true"></i>
						URL pagina contatti (opzionale)
					</label>
					<input type="url"
					       id="shopforge_contact_url"
					       name="shopforge_contact_url"
					       value="<?php echo esc_attr( get_option( 'shopforge_contact_url', '' ) ); ?>"
					       placeholder="https://esempio.it/contatti"
					       class="shopforge-config-input">
					<p class="shopforge-config-desc">
						URL mostrato nel messaggio "recesso scaduto".
						Se lasciato vuoto, viene mostrato solo il testo senza link.
					</p>
				</div>

			</div>

			<!-- ═══════════════════════════════════════════
			     SEZIONE 4: COLORI
			     ═══════════════════════════════════════════ -->
			<div class="shopforge-section-label">
				<i class="fa-solid fa-palette" aria-hidden="true"></i>
				Colori
				<span class="shopforge-section-hint">
					Personalizza la palette ShopForge per adattarla al tuo tema.
					I colori vengono iniettati come variabili CSS e sovrascrivono i default del plugin.
				</span>
			</div>

			<?php
			$colors   = shopforge_get_colors();
			$defaults = shopforge_color_defaults();
			$color_labels = [
				'primary'       => [ 'label' => 'Colore primario',        'desc' => 'Pulsanti, link attivi, elementi in evidenza' ],
				'primary_hover' => [ 'label' => 'Primario — hover',        'desc' => 'Tonalità più chiara per gli stati hover' ],
				'text_main'     => [ 'label' => 'Testo principale',        'desc' => 'Titoli e testo ad alto contrasto' ],
				'text_muted'    => [ 'label' => 'Testo secondario',        'desc' => 'Date, etichette, note a piè di campo' ],
				'border'        => [ 'label' => 'Bordo',                   'desc' => 'Bordi di card, tabelle e input' ],
				'border_soft'   => [ 'label' => 'Bordo morbido',           'desc' => 'Separatori interni leggeri' ],
				'bg_soft'       => [ 'label' => 'Sfondo neutro',           'desc' => 'Sfondo di card e sezioni secondarie' ],
				'success'       => [ 'label' => 'Successo',                'desc' => 'Badge "Completato", messaggi positivi' ],
				'warning'       => [ 'label' => 'Avviso',                  'desc' => 'Badge "In attesa", messaggi di avviso' ],
				'danger'        => [ 'label' => 'Errore / Pericolo',       'desc' => 'Messaggi di errore, azioni irreversibili' ],
			];
			?>

			<div class="shopforge-color-grid">
				<?php foreach ( $color_labels as $key => $meta ) : ?>
				<div class="shopforge-color-field">
					<label for="shopforge_color_<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $meta['label'] ); ?>
					</label>
					<div class="shopforge-color-row">
						<input type="text"
						       id="shopforge_color_<?php echo esc_attr( $key ); ?>"
						       name="shopforge_color_<?php echo esc_attr( $key ); ?>"
						       value="<?php echo esc_attr( $colors[ $key ] ); ?>"
						       class="shopforge-color-picker"
						       data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
					</div>
					<p class="shopforge-color-desc"><?php echo esc_html( $meta['desc'] ); ?></p>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- Preview live -->
			<div class="shopforge-color-preview" id="shopforge-color-preview">
				<p class="shopforge-color-preview__label">
					<i class="fa-solid fa-eye" aria-hidden="true"></i>
					Anteprima
				</p>
				<div class="shopforge-color-preview__card" id="shopforge-preview-card">
					<div class="shopforge-color-preview__btn" id="shopforge-preview-btn">Pulsante primario</div>
					<div class="shopforge-color-preview__text" id="shopforge-preview-text">Testo principale — <span id="shopforge-preview-muted">testo secondario</span></div>
					<div class="shopforge-color-preview__border" id="shopforge-preview-border">Bordo card</div>
				</div>
			</div>

			<div class="shopforge-settings-actions">
				<?php submit_button( 'Salva impostazioni', 'primary large', 'submit', false ); ?>
			</div>
		</form>

	<script>
	jQuery(document).ready(function($){
		// Inizializza tutti i color picker
		$('.shopforge-color-picker').wpColorPicker({
			change: function(){ shopforgeUpdatePreview(); },
			clear:  function(){ setTimeout(shopforgeUpdatePreview, 10); }
		});

		function shopforgeUpdatePreview(){
			var get = function(key){
				return $('#shopforge_color_' + key).val() || $('#shopforge_color_' + key).data('default-color');
			};
			var primary    = get('primary');
			var textMain   = get('text_main');
			var textMuted  = get('text_muted');
			var border     = get('border');
			var bgSoft     = get('bg_soft');

			$('#shopforge-preview-card').css({ 'background': bgSoft, 'border': '1px solid ' + border, 'border-radius':'8px', 'padding':'16px 20px' });
			$('#shopforge-preview-btn').css({ 'background': primary, 'color':'#fff', 'padding':'9px 18px', 'border-radius':'6px', 'display':'inline-block', 'font-weight':'700', 'font-size':'13px', 'margin-bottom':'12px' });
			$('#shopforge-preview-text').css({ 'color': textMain, 'font-size':'14px' });
			$('#shopforge-preview-muted').css({ 'color': textMuted });
			$('#shopforge-preview-border').css({ 'border-top':'1px solid ' + border, 'margin-top':'12px', 'padding-top':'10px', 'font-size':'12px', 'color': textMuted });
		}
		shopforgeUpdatePreview();
	});
	</script>

	<style>
	/* ---- Banner licenza mancante ---- */
	.shopforge-license-banner {
		display: flex; align-items: center; gap: 10px;
		background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E;
		border-radius: 8px; padding: 12px 16px; margin: 16px 0 20px;
		font-size: 13px; font-weight: 600;
	}
	.shopforge-license-banner i { font-size: 15px; }
	.shopforge-license-banner .button { margin-left: auto; }

	/* ---- Card modulo bloccata (senza licenza) ---- */
	.shopforge-module-card.is-locked { opacity: .6; }
	.shopforge-module-card.is-locked .shopforge-toggle { cursor: not-allowed; }

	/* ---- Etichette sezione ---- */
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

	/* ---- Griglia ---- */
	.shopforge-modules-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
		gap: 14px;
		margin-bottom: 6px;
	}
	.shopforge-modules-grid--features {
		grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	}

	/* ---- Card ---- */
	.shopforge-module-card {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 8px;
		padding: 18px 20px;
		transition: border-color .2s, box-shadow .2s;
	}
	.shopforge-module-card.is-active {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
	}
	/* Le feature hanno sfondo leggermente diverso per distinguersi visivamente */
	.shopforge-module-card--feature {
		background: #f9f9f9;
	}
	.shopforge-module-card--feature.is-active {
		background: #f0f6fc;
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
	}

	.shopforge-module-card__header {
		display: flex; align-items: center; gap: 14px; margin-bottom: 10px;
	}
	.shopforge-module-card__icon {
		width: 42px; height: 42px; flex-shrink: 0;
		background: #f0f6fc; border-radius: 10px;
		display: flex; align-items: center; justify-content: center;
		font-size: 19px; color: #2271b1;
	}
	.shopforge-module-card.is-inactive .shopforge-module-card__icon,
	.shopforge-module-card--feature.is-inactive .shopforge-module-card__icon {
		background: #f6f7f7; color: #8c8f94;
	}
	.shopforge-module-card__title-wrap { flex: 1; min-width: 0; }
	.shopforge-module-card__title {
		margin: 0 0 3px; font-size: 14px; font-weight: 700;
		color: #1d2327; line-height: 1.3;
	}
	.shopforge-module-card__meta { font-size: 11px; color: #8c8f94; }
	.shopforge-module-card__meta code {
		font-size: 11px; background: #f6f7f7;
		padding: 1px 5px; border-radius: 3px;
	}
	.shopforge-module-card__desc {
		margin: 0 0 12px; font-size: 13px; color: #646970; line-height: 1.5;
	}
	.shopforge-module-card__footer { display: flex; align-items: center; justify-content: flex-end; }
	.shopforge-module-status { font-size: 12px; font-weight: 600; }
	.shopforge-module-status.is-on  { color: #00a32a; }
	.shopforge-module-status.is-off { color: #8c8f94; }

	/* ---- Toggle switch ---- */
	.shopforge-toggle {
		position: relative; display: inline-block;
		width: 44px; height: 24px; flex-shrink: 0; cursor: pointer;
	}
	.shopforge-toggle input { opacity: 0; width: 0; height: 0; }
	.shopforge-toggle__slider {
		position: absolute; inset: 0;
		background: #dcdcde; border-radius: 999px;
		transition: background .2s;
	}
	.shopforge-toggle__slider::before {
		content: "";
		position: absolute; left: 3px; top: 3px;
		width: 18px; height: 18px;
		background: #fff; border-radius: 50%;
		box-shadow: 0 1px 3px rgba(0,0,0,.25);
		transition: transform .2s;
	}
	.shopforge-toggle input:checked + .shopforge-toggle__slider { background: #2271b1; }
	.shopforge-toggle input:checked + .shopforge-toggle__slider::before { transform: translateX(20px); }

	/* ---- Actions ---- */
	.shopforge-settings-actions { margin-top: 14px; }

	/* ---- Config grid ---- */
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
	.shopforge-config-field label i { color: #2271b1; }
	.shopforge-config-input {
		width: 100%; box-sizing: border-box;
		padding: 7px 10px; border: 1px solid #8c8f94;
		border-radius: 4px; font-size: 13px;
	}
	.shopforge-config-input[type="number"] { max-width: 100px; }
	.shopforge-config-desc {
		margin: 8px 0 0; font-size: 12px; color: #646970; line-height: 1.5;
	}

	/* ---- Griglia colori ---- */
	.shopforge-color-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
		gap: 20px 24px;
		margin-bottom: 24px;
	}
	.shopforge-color-field label {
		display: block; font-size: 12px; font-weight: 700;
		color: #1d2327; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .04em;
	}
	.shopforge-color-row { display: flex; align-items: center; gap: 8px; }
	.shopforge-color-desc { margin: 6px 0 0; font-size: 11px; color: #646970; line-height: 1.4; }
	/* Nasconde il classico input text del color picker (l'iris panel lo sostituisce) */
	.shopforge-color-field .wp-picker-container { width: 100%; }

	/* ---- Preview ---- */
	.shopforge-color-preview {
		margin: 0 0 28px;
		padding: 16px 20px;
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 6px;
	}
	.shopforge-color-preview__label {
		font-size: 12px; font-weight: 700; color: #646970;
		text-transform: uppercase; letter-spacing: .06em;
		margin: 0 0 14px;
		display: flex; align-items: center; gap: 6px;
	}
	.shopforge-color-preview__card { padding: 16px 20px; border-radius: 8px; }
	.shopforge-color-preview__btn {
		display: inline-block; padding: 9px 18px;
		border-radius: 6px; font-weight: 700; font-size: 13px;
		margin-bottom: 12px; cursor: default;
	}
	.shopforge-color-preview__text { font-size: 14px; }

	@media (max-width: 600px) {
		.shopforge-modules-grid,
		.shopforge-modules-grid--features,
		.shopforge-config-grid,
		.shopforge-color-grid { grid-template-columns: 1fr; }
	}
	</style>
	<?php
}

/**
 * Emette l'HTML interno di una card (header + descrizione + footer).
 * Usato sia per le feature che per i moduli.
 */
function shopforge_settings_card_inner( string $id, array $module, bool $is_active, bool $locked = false ): void {
	?>
	<div class="shopforge-module-card__header">
		<span class="shopforge-module-card__icon">
			<i class="<?php echo esc_attr( $module['icon'] ); ?>" aria-hidden="true"></i>
		</span>
		<div class="shopforge-module-card__title-wrap">
			<h3 class="shopforge-module-card__title"><?php echo esc_html( $module['label'] ); ?></h3>
			<span class="shopforge-module-card__meta">
			<?php if ( ( $module['type'] ?? 'module' ) === 'feature' ) : ?>
				funzionalità base
			<?php elseif ( ! empty( $module['endpoint'] ) ) : ?>
				endpoint: <code><?php echo esc_html( $module['endpoint'] ); ?></code>
			<?php else : ?>
				modulo (nessun endpoint)
			<?php endif; ?>
			</span>
		</div>
		<label class="shopforge-toggle" title="<?php echo $locked ? 'Richiede licenza attiva' : ( $is_active ? 'Disattiva' : 'Attiva' ); ?>">
			<input type="checkbox"
			       name="module_<?php echo esc_attr( $id ); ?>"
			       value="1"
			       <?php checked( $is_active ); ?>
			       <?php disabled( $locked ); ?>>
			<span class="shopforge-toggle__slider"></span>
		</label>
	</div>
	<p class="shopforge-module-card__desc"><?php echo esc_html( $module['description'] ); ?></p>
	<div class="shopforge-module-card__footer">
		<?php if ( $locked ) : ?>
		<span class="shopforge-module-status is-off"><i class="fa-solid fa-lock" aria-hidden="true"></i> Richiede licenza</span>
		<?php else : ?>
		<span class="shopforge-module-status <?php echo $is_active ? 'is-on' : 'is-off'; ?>">
			<?php echo $is_active ? '● Attivo' : '○ Inattivo'; ?>
		</span>
		<?php endif; ?>
	</div>
	<?php
}
