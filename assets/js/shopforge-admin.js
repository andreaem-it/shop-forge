/* ShopForge — Admin JS (metabox ticket assistenza + resi) */
/* Richiede: ajaxurl (WP admin global) */

document.querySelectorAll('.shopforge-save-status').forEach(function(btn) {
	btn.addEventListener('click', function() {
		var idx    = this.dataset.idx;
		var select = document.querySelector('.shopforge-status-select[data-idx="' + idx + '"]');
		var reply  = btn.closest('div').querySelector('.shopforge-reply-text');
		btn.textContent = '…';
		fetch(ajaxurl, {
			method: 'POST',
			headers: {'Content-Type':'application/x-www-form-urlencoded'},
			body: 'action=shopforge_update_ticket_status&order_id=' + this.dataset.order +
			      '&nonce=' + this.dataset.nonce +
			      '&idx=' + idx + '&status=' + encodeURIComponent(select.value) +
			      '&reply=' + encodeURIComponent(reply ? reply.value : '')
		}).then(function(r){ return r.json(); }).then(function(d) {
			btn.textContent = d.success ? '✓ Salvato' : '✗';
			setTimeout(function(){ location.reload(); }, 800);
		});
	});
});

// Metabox resi: aggiornamento stato
document.querySelectorAll('.shopforge-ret-save-st').forEach(function(btn) {
	btn.addEventListener('click', function() {
		var idx   = this.dataset.idx;
		var sel   = document.querySelector('.shopforge-ret-status-sel[data-idx="' + idx + '"]');
		var reply = btn.closest('div').previousElementSibling;
		var replyVal = (reply && reply.classList.contains('shopforge-ret-reply-text')) ? reply.value : '';
		btn.textContent = '…';
		fetch(ajaxurl, {
			method: 'POST',
			headers: {'Content-Type':'application/x-www-form-urlencoded'},
			body: 'action=shopforge_update_return_status&order_id=' + this.dataset.order +
			      '&nonce=' + this.dataset.nonce + '&idx=' + idx +
			      '&status=' + encodeURIComponent(sel.value) +
			      '&reply=' + encodeURIComponent(replyVal)
		}).then(function(r){ return r.json(); }).then(function(d) {
			btn.textContent = d.success ? '✓ Salvato' : '✗';
			if (d.success) setTimeout(function(){ location.reload(); }, 600);
		});
	});
});
