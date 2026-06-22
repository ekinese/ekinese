/**
 * XGOUD stempel-herkenner – stempel (585/750/925…) → metaal + karaat +
 * prijsindicatie per gram. Data via data-hallmark (marks + spots).
 */
(function () {
	'use strict';
	document.querySelectorAll('.xg-hallmark').forEach(function (root) {
		var data;
		try { data = JSON.parse(root.getAttribute('data-hallmark')); } catch (e) { return; }
		var marks = data.marks || {}, spots = data.spots || {};
		var input = root.querySelector('.xg-hallmark-input');
		var btn = root.querySelector('.xg-hallmark-btn');
		var out = root.querySelector('.xg-hallmark-result');

		function eur(n) { return '€ ' + Number(n || 0).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

		function recognize(raw) {
			var key = String(raw || '').replace(/[^0-9]/g, '').slice(0, 3);
			var m = marks[key];
			if (!m) {
				out.hidden = false;
				out.className = 'xg-hallmark-result is-miss';
				out.innerHTML = '<p>Geen bekend stempel herkend. Veelvoorkomend: <strong>585</strong>, <strong>750</strong> (goud), <strong>925</strong> (zilver). Twijfelt u? Onze expert taxeert gratis.</p>';
				return;
			}
			var metal = m[0], purity = m[1], label = m[2];
			var spot = spots[metal] || 0;
			var perGram = spot * purity;
			out.hidden = false;
			out.className = 'xg-hallmark-result is-hit xg-hallmark-' + metal;
			out.innerHTML =
				'<div class="xg-hallmark-badge">' + key + '</div>' +
				'<div class="xg-hallmark-info"><strong>' + label + '</strong>' +
				'<span>Metaal: ' + metal.charAt(0).toUpperCase() + metal.slice(1) + ' · gehalte ' + Math.round(purity * 1000) / 10 + '%</span>' +
				(perGram > 0 ? '<span class="xg-hallmark-price">Indicatie: ± ' + eur(perGram) + ' per gram</span>' : '') +
				'<span class="xg-hallmark-note">Indicatief, op basis van de actuele dagprijs. Eindprijs na taxatie.</span></div>';
		}

		if (btn) btn.addEventListener('click', function () { recognize(input.value); });
		if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') recognize(input.value); });
		root.querySelectorAll('.xg-hallmark-chip').forEach(function (chip) {
			chip.addEventListener('click', function () { input.value = chip.getAttribute('data-mark'); recognize(input.value); });
		});
	});
})();
