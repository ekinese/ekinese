/**
 * XGOUD Live-verkocht-ticker – roterende toast rechtsonder (sociale bewijskracht).
 * Datenquelle: window.XG_LIVE_SALES (via wp_localize_script, inc/live-ticker.php).
 */
(function () {
	'use strict';
	var sales = window.XG_LIVE_SALES;
	if (!sales || !sales.length) return;
	// Niet tonen op de driver-app.
	if (document.querySelector('.xg-driver')) return;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function eur(n) { return '€ ' + Number(n || 0).toLocaleString('nl-NL'); }

	var box = document.createElement('div');
	box.className = 'xg-livesale';
	box.setAttribute('aria-live', 'polite');
	document.body.appendChild(box);

	var i = 0, hideT = null, dismissed = false;
	box.addEventListener('click', function (e) {
		if (e.target.classList.contains('xg-livesale-x')) { dismissed = true; box.remove(); }
	});

	function show() {
		if (dismissed) return;
		var s = sales[i % sales.length]; i++;
		box.innerHTML = '<button class="xg-livesale-x" aria-label="Sluiten">×</button>' +
			'<div class="xg-livesale-dot"></div>' +
			'<div class="xg-livesale-body"><strong>' + esc(s.product) + '</strong>' +
			(s.amount ? ' · ' + eur(s.amount) : '') +
			'<span>' + (s.city ? esc(s.city) + ' · ' : '') + (s.demo ? 'voorbeeld' : esc(s.ago) + ' geleden') + '</span></div>';
		box.classList.add('in');
		clearTimeout(hideT);
		hideT = setTimeout(function () {
			box.classList.remove('in');
			setTimeout(show, 6000);
		}, 6000);
	}
	setTimeout(show, 3500);
})();
