/**
 * Accordion FAQ scheda prodotto.
 */
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.shopforge-faq-question').forEach(function (question) {
		question.addEventListener('click', function () {
			this.closest('.shopforge-faq-item').classList.toggle('active');
		});
	});
});
