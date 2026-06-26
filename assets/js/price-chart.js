/**
 * XGOUD koersgrafiek – live gevoel: tabwissel 7/30/90 dagen zonder herladen,
 * geanimeerd inteken-effect, pulserende live-punt en hover-tooltip.
 */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var W = 760, H = 220, P = 8;

	document.querySelectorAll('.xg-chart').forEach(function (chart) {
		var dataEl = chart.querySelector('.xg-chart-data');
		if (!dataEl) return;
		var sets;
		try { sets = JSON.parse(dataEl.textContent); } catch (e) { return; }
		var svg = chart.querySelector('.xg-chart-svg');
		var canvas = chart.querySelector('.xg-chart-canvas');
		var dot = chart.querySelector('.xg-chart-dot');
		var cross = chart.querySelector('.xg-chart-cross');
		var tip = chart.querySelector('.xg-chart-tip');
		var cur = sets['30'] || [];

		function coords(vals) {
			var min = Math.min.apply(null, vals), max = Math.max.apply(null, vals), rng = (max - min) || 1;
			return vals.map(function (v, i) {
				return {
					x: P + (i / (vals.length - 1)) * (W - 2 * P),
					y: H - P - ((v - min) / rng) * (H - 2 * P),
					v: v
				};
			});
		}

		function animateLine(poly) {
			if (reduce || !poly.getTotalLength) return;
			try {
				var len = poly.getTotalLength();
				poly.style.transition = 'none';
				poly.style.strokeDasharray = len;
				poly.style.strokeDashoffset = len;
				poly.getBoundingClientRect(); // forceer reflow
				poly.style.transition = 'stroke-dashoffset 1s ease';
				poly.style.strokeDashoffset = '0';
			} catch (e) {}
		}

		function draw(days, animate) {
			var vals = sets[days] || sets['30'] || [];
			if (!svg || vals.length < 2) return;
			cur = vals;
			var c = coords(vals);
			var ptsArr = c.map(function (p) { return Math.round(p.x * 10) / 10 + ',' + Math.round(p.y * 10) / 10; });
			var up = vals[vals.length - 1] >= vals[0];
			var color = up ? '#1f9d55' : '#AE1E1E';
			var poly = svg.querySelector('polyline'), area = svg.querySelector('polygon');
			if (poly) { poly.setAttribute('points', ptsArr.join(' ')); poly.setAttribute('stroke', color); if (animate) animateLine(poly); }
			if (area) area.setAttribute('points', '0,' + H + ' ' + ptsArr.join(' ') + ' ' + W + ',' + H);
			// Live-punt op het laatste datapunt.
			if (dot) {
				var last = c[c.length - 1];
				dot.style.left = (last.x / W * 100) + '%';
				dot.style.top = (last.y / H * 100) + '%';
				dot.style.background = color;
			}
			// Meta.
			var first = vals[0], lastv = vals[vals.length - 1];
			var pct = first ? Math.round((lastv - first) / first * 1000) / 10 : 0;
			var now = chart.querySelector('.xg-chart-now'), delta = chart.querySelector('.xg-chart-delta');
			if (now) now.textContent = '€ ' + lastv.toFixed(2).replace('.', ',') + '/g';
			if (delta) { delta.style.color = color; delta.textContent = (up ? '▲ ' : '▼ ') + Math.abs(pct) + '% (' + days + 'd)'; }
		}

		// Hover-tooltip + fadenkreuz.
		if (canvas && cross && tip) {
			canvas.addEventListener('mousemove', function (e) {
				if (cur.length < 2) return;
				var rect = canvas.getBoundingClientRect();
				var rel = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
				var i = Math.round(rel * (cur.length - 1));
				var c = coords(cur)[i];
				var leftPct = c.x / W * 100;
				cross.style.left = leftPct + '%';
				cross.style.display = 'block';
				tip.style.left = leftPct + '%';
				tip.style.top = (c.y / H * 100) + '%';
				tip.textContent = '€ ' + c.v.toFixed(2).replace('.', ',') + '/g';
				tip.style.display = 'block';
				var d = (cur.length - 1 - i);
				tip.setAttribute('data-day', d === 0 ? 'vandaag' : '-' + d + 'd');
			});
			canvas.addEventListener('mouseleave', function () {
				cross.style.display = 'none'; tip.style.display = 'none';
			});
		}

		chart.querySelectorAll('.xg-chart-tabs button').forEach(function (b) {
			b.addEventListener('click', function () {
				chart.querySelectorAll('.xg-chart-tabs button').forEach(function (x) { x.classList.remove('active'); });
				b.classList.add('active');
				draw(b.getAttribute('data-d'), true);
			});
		});

		// Eerste teken met animatie zodra zichtbaar.
		var active = chart.querySelector('.xg-chart-tabs button.active');
		var startDays = active ? active.getAttribute('data-d') : '30';
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (en) {
				en.forEach(function (x) { if (x.isIntersecting) { draw(startDays, true); io.disconnect(); } });
			}, { threshold: 0.3 });
			io.observe(chart);
		} else {
			draw(startDays, true);
		}
	});
})();
