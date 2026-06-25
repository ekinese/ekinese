/**
 * XGOUD veilingen — aftelklokken + bindend bod (AJAX, plugin-vrij).
 */
(function () {
	function fmtLeft(ms) {
		if (ms <= 0) return 'Gesloten';
		var s = Math.floor(ms / 1000);
		var d = Math.floor(s / 86400); s -= d * 86400;
		var h = Math.floor(s / 3600); s -= h * 3600;
		var m = Math.floor(s / 60); s -= m * 60;
		if (d > 0) return d + 'd ' + h + 'u ' + m + 'm';
		if (h > 0) return h + 'u ' + m + 'm ' + s + 's';
		return m + 'm ' + s + 's';
	}

	function tick() {
		var now = Date.now();
		document.querySelectorAll('[data-ends]').forEach(function (el) {
			var t = new Date((el.getAttribute('data-ends') || '').replace(' ', 'T')).getTime();
			if (isNaN(t)) { return; }
			el.textContent = (t > now ? 'Nog ' : '') + fmtLeft(t - now);
			el.classList.toggle('is-ending', t > now && t - now < 3600000);
		});
	}
	setInterval(tick, 1000); tick();

	// Live-verversing van het hoogste bod (poll, plugin-vrij).
	function eur(v) {
		try { return '€ ' + Number(v).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
		catch (e) { return '€ ' + Number(v).toFixed(2); }
	}
	var restBase = (window.XGAuction && window.XGAuction.rest) || '';
	function refreshAuctions() {
		if (!restBase) return;
		var nodes = document.querySelectorAll('[data-auction-id]');
		nodes.forEach(function (node) {
			var id = node.getAttribute('data-auction-id');
			if (!id) return;
			fetch(restBase + id, { headers: { 'Accept': 'application/json' } })
				.then(function (r) { return r.ok ? r.json() : null; })
				.then(function (d) {
					if (!d) return;
					var val = node.querySelector('.xg-auction-bid-val');
					if (val) val.textContent = eur(d.current_bid);
					var cnt = node.querySelector('.xg-auction-count');
					if (cnt) cnt.textContent = d.bid_count;
					// Op de detailpagina ook het minimumbod in het formulier bijwerken.
					var form = node.querySelector('[data-xg-auction]');
					if (form && d.min_next) {
						form.setAttribute('data-min', d.min_next);
						var inp = form.querySelector('input[name="amount"]');
						if (inp && inp !== document.activeElement) { inp.min = d.min_next; }
					}
				})
				.catch(function () {});
		});
	}
	if (document.querySelector('[data-auction-id]')) {
		setInterval(refreshAuctions, 15000);
	}

	function show(el, text, ok) {
		if (!el) return;
		el.textContent = text; el.hidden = false;
		el.className = 'xg-form-msg ' + (ok ? 'is-ok' : 'is-err');
	}

	document.querySelectorAll('[data-xg-auction]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('.xg-form-msg');
			var btn = form.querySelector('button[type="submit"]');
			var min = parseFloat(form.getAttribute('data-min') || '0');
			var data = {};
			new FormData(form).forEach(function (v, k) { data[k] = v; });
			if (parseFloat(data.amount) < min) { show(msg, 'Uw bod moet minstens € ' + min.toFixed(2) + ' zijn.', false); return; }
			if (!window.confirm('Een bod is bindend en kan niet worden ingetrokken. Bod van € ' + parseFloat(data.amount).toFixed(2) + ' uitbrengen?')) { return; }

			if (btn) btn.disabled = true;
			show(msg, 'Bod versturen…', true);
			fetch(form.getAttribute('data-endpoint'), {
				method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
				.then(function (res) {
					if (btn) btn.disabled = false;
					if (res.ok && res.body && res.body.ok) {
						show(msg, 'Uw bod is geregistreerd. U bent nu de hoogste bieder!', true);
						form.reset();
					} else {
						show(msg, (res.body && (res.body.message || res.body.code)) || 'Bod afgewezen.', false);
					}
				})
				.catch(function () { if (btn) btn.disabled = false; show(msg, 'Er ging iets mis. Probeer opnieuw.', false); });
		});
	});
})();
