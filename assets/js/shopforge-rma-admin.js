/* ShopForge — Assistenza Prodotti (RMA) — Admin JS */
/* Dati PHP passati via wp_localize_script come shopforgeRmaAdmin: { ajaxUrl, nonce } */

(function () {
	'use strict';

	var cfg = window.shopforgeRmaAdmin || {};

	// ---- Cambio stato dal metabox "Dettagli Richiesta" ----
	var statusSelect = document.querySelector( '.shopforge-rma-status-select' );
	if ( statusSelect ) {
		var originalValue = statusSelect.value;

		statusSelect.addEventListener( 'change', function () {
			var newStatus = statusSelect.value;
			if ( newStatus === originalValue ) return;

			var postId = document.getElementById( 'post_ID' )?.value;
			statusSelect.disabled = true;

			var body = new URLSearchParams( {
				action: 'shopforge_rma_update_status',
				nonce: cfg.nonce,
				post_id: postId,
				stato: newStatus,
			} );

			fetch( cfg.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					statusSelect.disabled = false;
					if ( res.success ) {
						originalValue = newStatus;
						var msg = document.createElement( 'p' );
						msg.className = 'shopforge-rma-status-message';
						msg.style.color = 'green';
						msg.style.marginTop = '5px';
						msg.textContent = 'Stato aggiornato con successo.';
						statusSelect.insertAdjacentElement( 'afterend', msg );
						setTimeout( function () { msg.remove(); }, 3000 );
					} else {
						alert( res.data?.message || 'Errore durante l\'aggiornamento.' );
						statusSelect.value = originalValue;
					}
				} )
				.catch( function () {
					statusSelect.disabled = false;
					statusSelect.value = originalValue;
					alert( 'Errore di comunicazione con il server.' );
				} );
		} );
	}

	// ---- Crea rimborso WooCommerce ----
	var refundBtn = document.getElementById( 'shopforge-rma-create-refund' );
	refundBtn?.addEventListener( 'click', function () {
		if ( ! window.confirm( 'Confermi la creazione del rimborso su WooCommerce per questa richiesta?' ) ) return;

		refundBtn.disabled = true;

		var body = new URLSearchParams( {
			action: 'shopforge_rma_create_refund',
			nonce: cfg.nonce,
			post_id: refundBtn.dataset.postId,
		} );

		fetch( cfg.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success ) {
					alert( res.data.message );
					window.location.reload();
				} else {
					alert( res.data?.message || 'Errore durante la creazione del rimborso.' );
					refundBtn.disabled = false;
				}
			} )
			.catch( function () {
				alert( 'Errore di comunicazione con il server.' );
				refundBtn.disabled = false;
			} );
	} );
})();
