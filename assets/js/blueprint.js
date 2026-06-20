/**
 * XGOUD Verkoop-Blaupause – Interaktionen.
 * Aktuell nur das FAQ-Akkordeon (Klick auf Frage öffnet/schließt Antwort).
 */
(function () {
	'use strict';

	function initFaq() {
		var questions = document.querySelectorAll('.xg-faq-question');
		if (!questions.length) {
			return;
		}
		questions.forEach(function (q) {
			q.addEventListener('click', function () {
				var item = this.closest('.xg-faq-item');
				if (item) {
					item.classList.toggle('active');
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFaq);
	} else {
		initFaq();
	}
})();
