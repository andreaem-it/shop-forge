/* ShopForge — Returns (Recesso) JS */
/* Dati PHP passati via wp_localize_script come shopforgeRecesso:
   { ajaxUrl, orderId, nonce, customer, orderNum, orderDate, storeName } */

(function () {
	'use strict';

	var cfg = window.shopforgeRecesso || {};

	var backdrop   = document.getElementById('shopforge-recesso-backdrop');
	var openBtn    = document.getElementById('shopforge-open-recesso');
	var closeBtn   = document.getElementById('shopforge-close-recesso');
	var closeOk    = document.getElementById('shopforge-close-recesso-ok');
	var step1      = document.getElementById('shopforge-recesso-step1');
	var step2      = document.getElementById('shopforge-recesso-step2');
	var success    = document.getElementById('shopforge-recesso-success');
	var nextBtn    = document.getElementById('shopforge-ret-next');
	var backBtn    = document.getElementById('shopforge-ret-back');
	var confirmBtn = document.getElementById('shopforge-ret-confirm');
	var dot1       = document.getElementById('shopforge-step-dot-1');
	var dot2       = document.getElementById('shopforge-step-dot-2');
	var err1       = document.getElementById('shopforge-ret-error-1');
	var err2       = document.getElementById('shopforge-ret-error-2');
	var declBox    = document.getElementById('shopforge-declaration-text');

	var customer  = cfg.customer  || '';
	var orderNum  = cfg.orderNum  || '';
	var orderDate = cfg.orderDate || '';
	var storeName = cfg.storeName || '';

	function open()  {
		backdrop.style.display = 'flex';
		backdrop.removeAttribute('aria-hidden');
		backdrop.classList.add('is-open');
		document.body.style.overflow = 'hidden';
	}
	function close() {
		backdrop.style.display = 'none';
		backdrop.setAttribute('aria-hidden', 'true');
		backdrop.classList.remove('is-open');
		document.body.style.overflow = '';
	}

	openBtn?.addEventListener('click', open);
	closeBtn?.addEventListener('click', close);
	closeOk?.addEventListener('click', function() { close(); location.reload(); });
	backdrop?.addEventListener('click', function(e) { if (e.target === backdrop) close(); });
	document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });

	// ---- Step 1 → Step 2 ----
	nextBtn?.addEventListener('click', function() {
		err1.style.display = 'none';

		var checked     = Array.from(document.querySelectorAll('.shopforge-ret-prod:checked')).map(function(el){ return el.value; });
		var reason      = document.getElementById('shopforge-ret-reason').value;
		var refundInput = document.querySelector('input[name="shopforge-ret-refund"]:checked');
		var refund      = refundInput?.value || '';
		var refundLabel = refundInput?.dataset.label || refund;
		var notes       = document.getElementById('shopforge-ret-notes').value.trim();

		if (!checked.length) { showErr(err1, 'Seleziona almeno un prodotto da restituire.'); return; }
		if (!reason)         { showErr(err1, 'Seleziona il motivo del recesso.'); return; }

		var now    = new Date();
		var nowStr = now.toLocaleDateString('it-IT') + ' alle ore ' + now.toLocaleTimeString('it-IT', {hour:'2-digit',minute:'2-digit'});
		var prodStr = checked.map(function(p){ return '  • ' + p; }).join('\n');

		var decl =
			'Destinatario: ' + esc(storeName) + '\n' +
			'Oggetto: Dichiarazione di recesso — Ordine ' + esc(orderNum) + '\n\n' +
			'Con la presente, io sottoscritto/a ' + esc(customer) + ' esercito il diritto di recesso ' +
			'dal contratto di compravendita relativo all\'acquisto dei seguenti beni:\n\n' +
			esc(prodStr) + '\n\n' +
			'Ordinato il ' + esc(orderDate) + '.\n\n' +
			'Motivo del recesso: ' + esc(reason) + '\n' +
			'Metodo di rimborso preferito: ' + esc(refundLabel) +
			(notes ? '\nNote: ' + esc(notes) : '') + '\n\n' +
			esc(customer) + '\n' +
			'Data trasmissione: ' + esc(nowStr);

		declBox.textContent = decl;

		step1.style.display = 'none';
		step2.style.display = 'block';
		dot1.classList.remove('is-active');
		dot1.classList.add('is-done');
		dot2.classList.add('is-active');
	});

	// ---- Step 2 → Step 1 ----
	backBtn?.addEventListener('click', function() {
		step2.style.display = 'none';
		step1.style.display = 'block';
		dot2.classList.remove('is-active');
		dot1.classList.remove('is-done');
		dot1.classList.add('is-active');
	});

	// ---- Conferma finale ----
	confirmBtn?.addEventListener('click', function() {
		err2.style.display = 'none';

		var checked     = Array.from(document.querySelectorAll('.shopforge-ret-prod:checked')).map(function(el){ return el.value; });
		var reason      = document.getElementById('shopforge-ret-reason').value;
		var refund      = document.querySelector('input[name="shopforge-ret-refund"]:checked')?.value || '';
		var notes       = document.getElementById('shopforge-ret-notes').value.trim();
		var declaration = declBox.textContent;

		document.getElementById('shopforge-ret-label').textContent = 'Invio in corso…';
		document.getElementById('shopforge-ret-spinner').style.display = 'inline-block';
		confirmBtn.disabled = true;

		var fd = new FormData();
		fd.append('action',      'shopforge_submit_return');
		fd.append('order_id',    cfg.orderId || '');
		fd.append('nonce',       cfg.nonce   || '');
		fd.append('reason',      reason);
		fd.append('refund',      refund);
		fd.append('notes',       notes);
		fd.append('declaration', declaration);
		checked.forEach(function(p){ fd.append('products[]', p); });

		// Allegati
		var fileInput = document.getElementById('shopforge-ret-files');
		if (fileInput && fileInput.files.length) {
			Array.from(fileInput.files).forEach(function(f){ fd.append('ret_files[]', f); });
		}

		fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: fd
		})
		.then(function(r){ return r.json(); })
		.then(function(data) {
			if (data.success) {
				step2.style.display = 'none';
				success.style.display = 'block';
				document.getElementById('shopforge-ret-success-text').textContent =
					'La tua dichiarazione di recesso è stata registrata (rif. ' + data.data.ref + '). ' +
					'Ti invieremo la ricevuta all\'indirizzo ' + data.data.email + '.';
			} else {
				showErr(err2, data.data || 'Errore durante l\'invio. Riprova.');
				document.getElementById('shopforge-ret-label').textContent = 'Confermo il recesso';
				document.getElementById('shopforge-ret-spinner').style.display = 'none';
				confirmBtn.disabled = false;
			}
		})
		.catch(function() {
			showErr(err2, 'Errore di rete. Controlla la connessione e riprova.');
			document.getElementById('shopforge-ret-label').textContent = 'Confermo il recesso';
			document.getElementById('shopforge-ret-spinner').style.display = 'none';
			confirmBtn.disabled = false;
		});
	});

	// Preview allegati recesso
	var retFileInput = document.getElementById('shopforge-ret-files');
	var retFilePreview = document.getElementById('shopforge-ret-file-preview');
	if (retFileInput && retFilePreview) {
		retFileInput.addEventListener('change', function () {
			retFilePreview.innerHTML = '';
			Array.from(this.files).forEach(function(f) {
				var chip = document.createElement('span');
				chip.className = 'shopforge-file-chip';
				chip.textContent = f.name + ' (' + (f.size > 1048576 ? (f.size/1048576).toFixed(1)+'MB' : (f.size/1024).toFixed(0)+'KB') + ')';
				retFilePreview.appendChild(chip);
			});
		});
	}

	function showErr(el, msg) { el.textContent = msg; el.style.display = 'block'; }
	function esc(str) { return str ? String(str).replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
})();
