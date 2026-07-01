/* ShopForge — Order Tracker JS */
/* Dati PHP passati via wp_localize_script come shopforgeTicket: { ajaxUrl, orderId, nonce } */

// Fix larghezza uniforme colonna account (Elementor)
(function() {
	function shopforgeFixWidth() {
		var widget = document.querySelector(
			'.woocommerce-account .e-con-inner > *:has(.woocommerce-MyAccount-navigation)'
		);
		if ( ! widget ) return;
		widget.style.setProperty('flex', '1 1 0%', 'important');
		widget.style.setProperty('min-width', '0', 'important');
		var wc = widget.querySelector('.elementor-widget-container');
		if ( wc ) wc.style.width = '100%';
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener('DOMContentLoaded', shopforgeFixWidth);
	} else {
		shopforgeFixWidth();
	}
})();

(function () {
	'use strict';

	var cfg      = window.shopforgeTicket || {};
	var backdrop = document.getElementById('shopforge-ticket-backdrop');
	var openBtn  = document.getElementById('shopforge-open-ticket');
	var closeBtn = document.getElementById('shopforge-close-ticket');
	var closeOk  = document.getElementById('shopforge-close-success');
	var submitBtn= document.getElementById('shopforge-submit-ticket');
	var form     = document.getElementById('shopforge-ticket-form');
	var success  = document.getElementById('shopforge-ticket-success');
	var errorEl  = document.getElementById('shopforge-ticket-error');
	var spinner  = document.getElementById('shopforge-btn-spinner');
	var btnLabel = document.getElementById('shopforge-btn-label');

	function open() {
		if (!backdrop) return;
		backdrop.style.display = 'block';
		backdrop.scrollIntoView({ behavior: 'smooth', block: 'start' });
		if (openBtn) openBtn.style.display = 'none';
	}
	function close() {
		if (!backdrop) return;
		backdrop.style.display = 'none';
		if (openBtn) openBtn.style.display = '';
		// reset form
		if (form) form.style.display = 'block';
		if (success) success.style.display = 'none';
	}

	openBtn?.addEventListener('click', open);
	closeBtn?.addEventListener('click', close);
	closeOk?.addEventListener('click', close);
	document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });

	submitBtn?.addEventListener('click', function () {
		var subject  = document.getElementById('shopforge-ticket-subject').value.trim();
		var message  = document.getElementById('shopforge-ticket-message').value.trim();
		var checked  = Array.from(document.querySelectorAll('.shopforge-prod-check:checked'))
		                    .map(function(el){ return el.value; });

		errorEl.style.display = 'none';

		if (!subject) { showError('Seleziona il motivo della richiesta.'); return; }
		if (message.length < 10) { showError('Descrivi il problema in almeno 10 caratteri.'); return; }

		btnLabel.textContent = 'Invio in corso…';
		spinner.style.display = 'inline-block';
		submitBtn.disabled = true;

		var fd = new FormData();
		fd.append('action',   'shopforge_submit_ticket');
		fd.append('order_id', cfg.orderId || '');
		fd.append('nonce',    cfg.nonce   || '');
		fd.append('subject',  subject);
		fd.append('message',  message);
		checked.forEach(function(p){ fd.append('products[]', p); });

		// Allegati
		var fileInput = document.getElementById('shopforge-ticket-files');
		if (fileInput && fileInput.files.length) {
			Array.from(fileInput.files).forEach(function(f){ fd.append('files[]', f); });
		}

		fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: fd   // no Content-Type header: FormData lo imposta con boundary
		})
		.then(function(r){ return r.json(); })
		.then(function(data) {
			if (data.success) {
				form.style.display = 'none';
				success.style.display = 'block';
			} else {
				showError(data.data || 'Errore nell\'invio. Riprova tra qualche istante.');
			}
		})
		.catch(function() {
			showError('Errore di rete. Controlla la connessione e riprova.');
		})
		.finally(function() {
			btnLabel.textContent = 'Invia richiesta';
			spinner.style.display = 'none';
			submitBtn.disabled = false;
		});
	});

	// Preview allegati
	var fileInput = document.getElementById('shopforge-ticket-files');
	var filePreview = document.getElementById('shopforge-ticket-file-preview');
	if (fileInput && filePreview) {
		fileInput.addEventListener('change', function () {
			filePreview.innerHTML = '';
			Array.from(this.files).forEach(function(f) {
				var chip = document.createElement('span');
				chip.className = 'shopforge-file-chip';
				chip.textContent = f.name + ' (' + (f.size > 1048576 ? (f.size/1048576).toFixed(1)+'MB' : (f.size/1024).toFixed(0)+'KB') + ')';
				filePreview.appendChild(chip);
			});
		});
	}

	function showError(msg) {
		errorEl.textContent = msg;
		errorEl.style.display = 'block';
	}

	// Fallback: se la select è vuota (cache, opcache) la ripopola lato client
	document.addEventListener('DOMContentLoaded', function () {
		var sel = document.getElementById('shopforge-ticket-subject');
		if ( sel && sel.options.length <= 1 ) {
			['Problema con il prodotto','Spedizione o tracking','Altro']
			.forEach(function(label) {
				var o = document.createElement('option');
				o.value = label; o.textContent = label;
				sel.appendChild(o);
			});
		}
	});
})();
