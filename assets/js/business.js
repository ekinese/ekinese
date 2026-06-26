/**
 * XGOUD zakelijk portaal — login (magic-link) + prijslijst + bulk-inlevering.
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-bizpro');
	if (!root) return;
	var loginBox = root.querySelector('.xg-bizpro-login');
	var dash = root.querySelector('.xg-bizpro-dash');
	var msg = root.querySelector('.xg-bizpro-msg');
	var form = root.querySelector('.xg-bizpro-loginform');
	var urls = { login: root.getAttribute('data-login'), portal: root.getAttribute('data-portal'), batch: root.getAttribute('data-batch') };
	var token = new URLSearchParams(location.search).get('token');
	var prices = {};

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var email = form.querySelector('[name=email]').value.trim();
			if (!email) return;
			msg.textContent = 'Bezig…';
			fetch(urls.login, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: email }) })
				.then(function (r) { return r.json(); })
				.then(function (d) { msg.textContent = (d && d.message) || 'Controleer uw e-mail.'; })
				.catch(function () { msg.textContent = 'Er ging iets mis.'; });
		});
	}

	if (!token) return;
	fetch(urls.portal + '?token=' + encodeURIComponent(token))
		.then(function (r) { if (!r.ok) throw 0; return r.json(); })
		.then(render)
		.catch(function () { if (msg) msg.textContent = 'Uw link is ongeldig of verlopen. Vraag een nieuwe aan.'; });

	function render(d) {
		if (!d || !d.approved) {
			if (msg) msg.textContent = (d && d.message) || 'Account nog niet geactiveerd.';
			return;
		}
		prices = d.prices || {};
		loginBox.hidden = true; dash.hidden = false;

		var priceRows = Object.keys(prices).map(function (k) {
			var p = prices[k];
			return '<tr><td>' + esc(p.label) + '</td><td>€ ' + esc(p.spot) + '</td><td><strong>€ ' + esc(p.price) + '</strong></td></tr>';
		}).join('');

		var hist = (d.batches && d.batches.length)
			? '<table class="xg-spec-table"><thead><tr><th>Datum</th><th>Referentie</th><th>Regels</th><th>Indicatief</th><th>Status</th></tr></thead><tbody>' +
			d.batches.map(function (b) { return '<tr><td>' + esc(b.date) + '</td><td>' + esc(b.ref) + '</td><td>' + esc(b.lines) + '</td><td>' + esc(b.total) + '</td><td>' + esc(b.status) + '</td></tr>'; }).join('') + '</tbody></table>'
			: '<p class="xg-acc-empty">Nog geen partijen ingeleverd.</p>';

		dash.innerHTML =
			'<h1>Welkom, ' + esc(d.company) + '</h1>' +
			'<p class="xg-bizpro-terms">Uw condities: marge ' + esc(d.margin) + '% op spot' + (d.payment_terms ? ' · betaaltermijn ' + esc(d.payment_terms) : '') + '.</p>' +
			'<div class="xg-bizpro-grid">' +
			'<section class="xg-acc-sec"><h3>Uw prijslijst (€/g fijn)</h3><div class="xg-table-scroll"><table class="xg-spec-table"><thead><tr><th>Metaal</th><th>Spot</th><th>Uw prijs</th></tr></thead><tbody>' + priceRows + '</tbody></table></div></section>' +
			'<section class="xg-acc-sec"><h3>Partij inleveren</h3><div class="xg-batch"><div class="xg-batch-rows"></div>' +
			'<button type="button" class="xg-batch-add">+ Regel toevoegen</button>' +
			'<div class="xg-batch-total">Indicatieve waarde: <strong>€ 0,00</strong></div>' +
			'<button type="button" class="xg-final-btn xg-batch-submit">Partij aanmelden</button>' +
			'<div class="xg-batch-msg" role="status"></div></div></section>' +
			'</div>' +
			'<section class="xg-acc-sec"><h3>Mijn partijen</h3><div class="xg-table-scroll">' + hist + '</div></section>';

		bind();
	}

	function metalOptions() {
		return Object.keys(prices).map(function (k) { return '<option value="' + k + '">' + esc(prices[k].label) + '</option>'; }).join('');
	}
	function addRow() {
		var wrap = dash.querySelector('.xg-batch-rows');
		var row = document.createElement('div');
		row.className = 'xg-batch-row';
		row.innerHTML = '<select class="b-metal">' + metalOptions() + '</select>' +
			'<input class="b-weight" type="number" min="0" step="0.1" placeholder="Gewicht (g)">' +
			'<input class="b-purity" type="number" min="0" max="1000" step="1" placeholder="Zuiverheid ‰ (bv. 585)">' +
			'<input class="b-qty" type="number" min="1" value="1" placeholder="Aantal">' +
			'<span class="b-val">€ 0,00</span><button type="button" class="b-del" aria-label="Verwijder">×</button>';
		wrap.appendChild(row);
		recalc();
	}
	function recalc() {
		var total = 0;
		dash.querySelectorAll('.xg-batch-row').forEach(function (row) {
			var m = row.querySelector('.b-metal').value;
			var w = parseFloat(row.querySelector('.b-weight').value) || 0;
			var p = parseFloat(row.querySelector('.b-purity').value) || 0;
			var q = parseInt(row.querySelector('.b-qty').value) || 1;
			var price = prices[m] ? prices[m].price : 0;
			var v = w * (p / 1000) * q * price;
			total += v;
			row.querySelector('.b-val').textContent = '€ ' + v.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		});
		var t = dash.querySelector('.xg-batch-total strong');
		if (t) t.textContent = '€ ' + total.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function bind() {
		dash.querySelector('.xg-batch-add').addEventListener('click', addRow);
		dash.addEventListener('input', recalc);
		dash.addEventListener('click', function (e) {
			if (e.target.classList.contains('b-del')) { e.target.closest('.xg-batch-row').remove(); recalc(); }
		});
		addRow();
		dash.querySelector('.xg-batch-submit').addEventListener('click', function () {
			var items = [];
			dash.querySelectorAll('.xg-batch-row').forEach(function (row) {
				var w = parseFloat(row.querySelector('.b-weight').value) || 0;
				if (w > 0) {
					items.push({ metal: row.querySelector('.b-metal').value, weight: w, purity: parseFloat(row.querySelector('.b-purity').value) || 0, qty: parseInt(row.querySelector('.b-qty').value) || 1 });
				}
			});
			var bmsg = dash.querySelector('.xg-batch-msg');
			if (!items.length) { bmsg.textContent = 'Voeg minstens één regel met gewicht toe.'; return; }
			var btn = dash.querySelector('.xg-batch-submit'); btn.disabled = true; bmsg.textContent = 'Versturen…';
			fetch(urls.batch, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: token, items: items }) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					btn.disabled = false;
					if (o.ok && o.j && o.j.ok) { bmsg.textContent = o.j.message + ' (' + o.j.ref + ')'; setTimeout(function () { location.reload(); }, 1500); }
					else { bmsg.textContent = (o.j && o.j.message) || 'Mislukt.'; }
				})
				.catch(function () { btn.disabled = false; bmsg.textContent = 'Netwerkfout.'; });
		});
	}
})();
