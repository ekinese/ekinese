/**
 * XGOUD Charity – Live-Ticker-Summe + Transparenz-Auflistung.
 * Datenquelle: window.XG_CHARITY (via wp_localize_script, inc/charity.php).
 *   { month_total, currency, categories:[{id,label,projects:[{name,city,accrued,paid,payouts,website}]}], rest_total }
 */
(function () {
	'use strict';

	var D = window.XG_CHARITY;
	if (!D) return;

	function euro(n) {
		return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: D.currency || 'EUR', maximumFractionDigits: 2 }).format(isFinite(n) ? n : 0);
	}
	function el(t, c, h) { var e = document.createElement(t); if (c) e.className = c; if (h != null) e.innerHTML = h; return e; }

	// Count-up
	function animate(node, to) {
		var start = null, dur = 900;
		function frame(t) {
			if (!start) start = t;
			var p = Math.min((t - start) / dur, 1);
			node.textContent = euro((1 - Math.pow(1 - p, 3)) * to);
			if (p < 1) requestAnimationFrame(frame);
		}
		requestAnimationFrame(frame);
	}

	function updateTicker() {
		document.querySelectorAll('[data-xg-charity-total]').forEach(function (n) { animate(n, D.month_total || 0); });
	}

	// Transparenz-Board: Kategorien + Projekte (wer/wieviel/wann)
	function renderBoard() {
		var host = document.querySelector('.xg-charity-board');
		if (!host) return;
		host.innerHTML = '';

		var tabs = el('div', 'xg-charity-tabs');
		var body = el('div', 'xg-charity-body');
		host.appendChild(tabs); host.appendChild(body);

		var cats = (D.categories || []).filter(function (c) { return c.projects && c.projects.length; });
		if (!cats.length) { body.appendChild(el('p', 'xg-charity-empty', 'Nog geen projecten beschikbaar.')); return; }

		function show(cat) {
			Array.prototype.forEach.call(tabs.children, function (b) { b.classList.toggle('active', b.dataset.id === cat.id); });
			body.innerHTML = '';
			var grid = el('div', 'xg-charity-grid');
			cat.projects.forEach(function (p) {
				var card = el('div', 'xg-charity-card');
				var payouts = (p.payouts || []).map(function (x) {
					return '<div class="xg-charity-payrow"><span>' + (x.period || x.date || '') + '</span><b>' + euro(x.amount) + '</b></div>';
				}).join('') || '<div class="xg-charity-payrow muted"><span>Nog niet uitbetaald</span></div>';
				card.innerHTML =
					'<div class="xg-charity-card-head"><h3>' + p.name + '</h3>' + (p.city ? '<span>' + p.city + '</span>' : '') + '</div>' +
					'<div class="xg-charity-accrued"><span>Opgebouwd dit kwartaal</span><strong>' + euro(p.accrued) + '</strong></div>' +
					'<div class="xg-charity-pay"><div class="xg-charity-pay-label">Uitbetaald</div>' + payouts + '</div>' +
					(p.website ? '<a class="xg-charity-link" href="' + p.website + '" target="_blank" rel="noopener">Bekijk project →</a>' : '');
				grid.appendChild(card);
			});
			body.appendChild(grid);
		}

		cats.forEach(function (cat, i) {
			var b = el('button', 'xg-charity-tab' + (i === 0 ? ' active' : ''), cat.label);
			b.type = 'button'; b.dataset.id = cat.id;
			b.addEventListener('click', function () { show(cat); });
			tabs.appendChild(b);
		});
		show(cats[0]);
	}

	function liveRefresh() {
		if (!D.rest_total || !window.fetch) return;
		setInterval(function () {
			fetch(D.rest_total).then(function (r) { return r.json(); }).then(function (j) {
				if (j && typeof j.month_total === 'number' && j.month_total !== D.month_total) {
					D.month_total = j.month_total; updateTicker();
				}
			}).catch(function () {});
		}, 60000);
	}

	function boot() { updateTicker(); renderBoard(); liveRefresh(); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
