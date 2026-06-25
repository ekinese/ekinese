/**
 * XGOUD interne analytics — logt betekenisvolle kliks (geen PII).
 * Zoek-/assistent-vragen worden server-side gelogd via /assistant.
 */
(function () {
	var cfg = window.XGTrack || {};
	if (!cfg.rest) return;

	function send(type, term, meta) {
		try {
			var body = JSON.stringify({ type: type, term: (term || '').slice(0, 120), url: location.pathname, meta: meta || '' });
			if (navigator.sendBeacon) {
				navigator.sendBeacon(cfg.rest, new Blob([body], { type: 'application/json' }));
			} else {
				fetch(cfg.rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
			}
		} catch (e) {}
	}

	// Alleen betekenisvolle elementen tellen (nav, CTA's, kaarten, knoppen).
	var SEL = '.xg-nav-item a, .xg-nav-item, .xg-cta, .xg-final-btn, .xg-btn-gold, .xg-c-card, .xg-mp-card, .xg-auction-card, .xg-ptab-btn, .footer__link';
	document.addEventListener('click', function (e) {
		var el = e.target.closest(SEL);
		if (!el) return;
		var label = (el.getAttribute('aria-label') || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120);
		if (label) { send('click', label); }
	}, { passive: true });
})();
