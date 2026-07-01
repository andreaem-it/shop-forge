/* ShopForge — Assistenza Prodotti (RMA) JS */
/* Dati PHP passati via wp_localize_script come shopforgeRma:
   { ajaxUrl, nonce, nonceMessage, nonceCancel, requestId } */

(function () {
	'use strict';

	var cfg = window.shopforgeRma || {};

	// ---- Form di creazione richiesta ----
	var form = document.getElementById('shopforge-rma-form');
	form?.addEventListener('submit', function (e) {
		e.preventDefault();

		var errBox  = document.getElementById('shopforge-rma-error');
		var submit  = document.getElementById('shopforge-rma-submit');
		errBox.style.display = 'none';

		var data = new FormData(form);
		data.append('action', 'shopforge_rma_submit_request');
		data.append('nonce', cfg.nonce);

		submit.disabled = true;

		fetch(cfg.ajaxUrl, { method: 'POST', body: data })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res.success) {
					window.location.href = res.data.redirect_url;
					return;
				}
				submit.disabled = false;
				errBox.textContent = res.data?.message || 'Errore durante l\'invio.';
				errBox.style.display = 'block';
			})
			.catch(function () {
				submit.disabled = false;
				errBox.textContent = 'Errore di rete. Riprova.';
				errBox.style.display = 'block';
			});
	});

	// ---- Form aggiunta messaggio ----
	var messageForm = document.getElementById('shopforge-rma-message-form');
	messageForm?.addEventListener('submit', function (e) {
		e.preventDefault();

		var textarea = messageForm.querySelector('textarea[name="message_text"]');
		var list     = document.getElementById('shopforge-rma-messages');
		var text     = textarea.value.trim();
		if (!text) return;

		var body = new URLSearchParams({
			action: 'shopforge_rma_add_message',
			nonce: cfg.nonceMessage,
			request_id: cfg.requestId,
			message_text: text,
		});

		fetch(cfg.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res.success) {
					list.insertAdjacentHTML('beforeend', res.data.message_html);
					textarea.value = '';
				}
			});
	});

	// ---- Annulla richiesta ----
	var cancelBtn = document.getElementById('shopforge-rma-cancel');
	cancelBtn?.addEventListener('click', function () {
		var body = new URLSearchParams({
			action: 'shopforge_rma_cancel_request',
			nonce: cfg.nonceCancel,
			request_id: cfg.requestId,
		});

		cancelBtn.disabled = true;

		fetch(cfg.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					cancelBtn.disabled = false;
					alert(res.data?.message || 'Errore.');
				}
			});
	});
})();
