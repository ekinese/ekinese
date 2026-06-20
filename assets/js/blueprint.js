/**
 * XGOUD Verkoop-Blaupause – Interaktionen.
 *  - FAQ-Akkordeon (Klick auf Frage öffnet/schließt Antwort)
 *  - Scroll-Reveal: Sektionen/Karten faden beim Scrollen sanft ein
 */
(function () {
	'use strict';

	function initFaq() {
		var questions = document.querySelectorAll('.xg-faq-question');
		if (!questions.length) return;
		questions.forEach(function (q) {
			q.addEventListener('click', function () {
				var item = this.closest('.xg-faq-item');
				if (item) item.classList.toggle('active');
			});
		});
	}

	// Sanftes Einblenden beim Scrollen (modern, dezent, mit Stagger).
	function initReveal() {
		if (!('IntersectionObserver' in window)) return;
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

		var sel = [
			'.xg-blueprint .xg-section-title',
			'.xg-blueprint .hero-kicker',
			'.xg-blueprint .xg-hero-v2 h1',
			'.xg-blueprint .hero-lead',
			'.xg-blueprint .xg-hero-buttons',
			'.xg-blueprint .xg-benefit',
			'.xg-blueprint .xg-c-card',
			'.xg-blueprint .xg-trust-card',
			'.xg-blueprint .xg-step-card',
			'.xg-blueprint .xg-author-card',
			'.xg-blueprint .xg-link-card',
			'.xg-blueprint .xg-review-card',
			'.xg-blueprint .xg-formula-box',
			'.xg-blueprint .xg-rapaport-box',
			'.xg-blueprint .xgoud-cta-modern',
			'.xg-blueprint .xg-final-cta-box',
			'.xg-blueprint .xg-table-wrapper',
			'.xg-blueprint .xgoud-toc-grid',
			'.xg-blueprint .xg-cert-image',
			'.xg-blueprint .xg-eeat-content'
		].join(',');

		var targets = document.querySelectorAll(sel);
		if (!targets.length) return;

		targets.forEach(function (el) {
			el.classList.add('xg-reveal');
			// Stagger anhand Position unter Geschwistern.
			var p = el.parentElement;
			var idx = p ? Array.prototype.indexOf.call(p.children, el) : 0;
			el.style.transitionDelay = (Math.min(idx, 6) * 0.07) + 's';
		});

		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) {
					e.target.classList.add('in');
					io.unobserve(e.target);
				}
			});
		}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

		targets.forEach(function (el) { io.observe(el); });
	}

	function boot() { initFaq(); initReveal(); }

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
