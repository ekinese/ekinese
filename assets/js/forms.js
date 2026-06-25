/**
 * XGOUD formulieren — plugin-vrij. Verstuurt naar de REST-endpoint en toont
 * inline een bevestiging of foutmelding (geen pagina-herlaad).
 */
(function () {
	function show(el, text, ok) {
		if (!el) return;
		el.textContent = text;
		el.hidden = false;
		el.className = 'xg-form-msg ' + (ok ? 'is-ok' : 'is-err');
	}

	document.querySelectorAll('[data-xg-form]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('.xg-form-msg');
			var btn = form.querySelector('button[type="submit"]');
			var endpoint = form.getAttribute('data-endpoint') || '/wp-json/ekinese/v1/lead';
			var data = {};
			new FormData(form).forEach(function (v, k) { data[k] = v; });

			if (btn) { btn.disabled = true; }
			show(msg, 'Versturen…', true);

			fetch(endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(data)
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
				.then(function (res) {
					if (btn) { btn.disabled = false; }
					if (res.ok && res.body && res.body.ok) {
						form.reset();
						show(msg, res.body.message || 'Bedankt!', true);
					} else {
						show(msg, (res.body && res.body.message) || 'Controleer uw gegevens en probeer opnieuw.', false);
					}
				})
				.catch(function () {
					if (btn) { btn.disabled = false; }
					show(msg, 'Er ging iets mis. Probeer het later opnieuw.', false);
				});
		});
	});
})();
