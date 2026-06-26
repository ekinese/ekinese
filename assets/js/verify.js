/**
 * XGOUD klant-verificatie — toont of de afspraak/medewerker echt is en welke
 * code de chauffeur toont. Code-first.
 */
(function () {
	var root = document.querySelector('.xg-verify');
	if (!root) return;
	var box = root.querySelector('.xg-verify-box');
	var rest = root.getAttribute('data-rest');
	var p = new URLSearchParams(location.search);
	var a = p.get('a'), c = (p.get('c') || '').toUpperCase();

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (x) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[x]; }); }

	if (!a || !c) {
		box.innerHTML = '<h1>Verificatie</h1><p>Open de persoonlijke link uit uw afspraakbevestiging om uw bezoek te verifiëren.</p>';
		return;
	}
	fetch(rest + '?a=' + encodeURIComponent(a) + '&c=' + encodeURIComponent(c))
		.then(function (r) { return r.json(); })
		.then(function (d) {
			if (!d || !d.valid) {
				box.innerHTML = '<div class="xg-verify-bad"><h1>Niet geldig</h1><p>Deze code hoort niet bij een geldige afspraak. Laat niemand binnen die zich niet kan legitimeren en bel ons via 085 060 3009.</p></div>';
				return;
			}
			box.innerHTML = '<div class="xg-verify-ok">' +
				'<div class="xg-verify-check">✓</div>' +
				'<h1>Geverifieerd</h1>' +
				'<p>Er komt een erkende XGOUD-medewerker langs voor uw afspraak' +
				(d.date ? ' op <strong>' + esc(d.date) + (d.time ? ' ' + esc(d.time) : '') + '</strong>' : '') +
				(d.city ? ' in ' + esc(d.city) : '') + '.</p>' +
				'<p class="xg-verify-codelabel">Uw chauffeur toont deze code. Controleer dat ze exact overeenkomen:</p>' +
				'<div class="xg-verify-code">' + esc(d.code) + '</div>' +
				'<p class="xg-verify-warn">Komt de code niet overeen of kan de medewerker zich niet legitimeren? Laat niemand binnen en bel 085 060 3009.</p>' +
				'</div>';
		})
		.catch(function () { box.innerHTML = '<p>Er ging iets mis. Probeer het later opnieuw.</p>'; });
})();
