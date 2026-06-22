/**
 * XGOUD koersgrafiek – tabwissel 7/30/90 dagen zonder herladen.
 * Leest de vooraf gerenderde puntsets uit het JSON-script en hertekent de SVG.
 */
(function () {
	'use strict';
	document.querySelectorAll('.xg-chart').forEach(function (chart) {
		var dataEl = chart.querySelector('.xg-chart-data');
		if (!dataEl) return;
		var sets;
		try { sets = JSON.parse(dataEl.textContent); } catch (e) { return; }
		var svg = chart.querySelector('.xg-chart-svg');
		var W = 760, H = 220, P = 8;

		function draw(days) {
			var vals = sets[days] || sets['30'] || [];
			if (!svg || vals.length < 2) return;
			var min = Math.min.apply(null, vals), max = Math.max.apply(null, vals), rng = (max - min) || 1;
			var pts = vals.map(function (v, i) {
				var x = P + (i / (vals.length - 1)) * (W - 2 * P);
				var y = H - P - ((v - min) / rng) * (H - 2 * P);
				return Math.round(x * 10) / 10 + ',' + Math.round(y * 10) / 10;
			});
			var up = vals[vals.length - 1] >= vals[0];
			var color = up ? '#1f9d55' : '#AE1E1E';
			var poly = svg.querySelector('polyline'), area = svg.querySelector('polygon');
			if (poly) { poly.setAttribute('points', pts.join(' ')); poly.setAttribute('stroke', color); }
			if (area) area.setAttribute('points', '0,' + H + ' ' + pts.join(' ') + ' ' + W + ',' + H);
			// Meta bijwerken.
			var first = vals[0], last = vals[vals.length - 1];
			var pct = first ? Math.round((last - first) / first * 1000) / 10 : 0;
			var now = chart.querySelector('.xg-chart-now'), delta = chart.querySelector('.xg-chart-delta');
			if (now) now.textContent = '€ ' + last.toFixed(2).replace('.', ',') + '/g';
			if (delta) { delta.style.color = color; delta.textContent = (up ? '▲ ' : '▼ ') + Math.abs(pct) + '% (' + days + 'd)'; }
		}

		chart.querySelectorAll('.xg-chart-tabs button').forEach(function (b) {
			b.addEventListener('click', function () {
				chart.querySelectorAll('.xg-chart-tabs button').forEach(function (x) { x.classList.remove('active'); });
				b.classList.add('active');
				draw(b.getAttribute('data-d'));
			});
		});
	});
})();
