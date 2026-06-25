/**
 * XGOUD Marktplaats — advertentie plaatsen (met foto's) + categoriefilter.
 * Geen onderlinge communicatie: het formulier verzendt alleen naar XGOUD.
 */
(function () {
	// Plaats-formulier (multipart, met foto's en veiling-keuze).
	document.querySelectorAll('[data-xg-market-submit]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('.xg-form-msg');
			var btn = form.querySelector('button[type="submit"]');
			function show(t, ok) { if (!msg) return; msg.textContent = t; msg.hidden = false; msg.className = 'xg-form-msg ' + (ok ? 'is-ok' : 'is-err'); }

			var mode = form.querySelector('input[name="mode"]:checked');
			if (mode && mode.value === 'auction' &&
				!window.confirm('U kiest voor een XGOUD-veiling. Een veiling kan NIET worden teruggetrokken. Doorgaan?')) {
				return;
			}
			var photos = form.querySelector('input[type="file"]');
			if (photos && photos.files && photos.files.length > 5) { show('Maximaal 5 foto’s.', false); return; }

			var fd = new FormData(form);
			if (btn) btn.disabled = true;
			show('Bezig met plaatsen…', true);
			fetch(form.getAttribute('data-endpoint'), { method: 'POST', body: fd })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					if (btn) btn.disabled = false;
					if (o.ok && o.j && o.j.ok) {
						show(o.j.message || 'Geplaatst!', true);
						form.reset();
						if (o.j.url) { setTimeout(function () { location.href = o.j.url; }, 1400); }
					} else {
						show((o.j && o.j.message) || 'Plaatsen mislukt. Controleer de velden.', false);
					}
				})
				.catch(function () { if (btn) btn.disabled = false; show('Er ging iets mis. Probeer het later opnieuw.', false); });
		});
	});

	// Categoriefilter op het overzicht.
	document.querySelectorAll('.xg-mp').forEach(function (root) {
		var btns = root.querySelectorAll('.xg-mp-fbtn');
		var cards = root.querySelectorAll('.xg-mp-card');
		btns.forEach(function (b) {
			b.addEventListener('click', function () {
				var cat = b.getAttribute('data-cat');
				btns.forEach(function (x) { x.classList.toggle('active', x === b); });
				cards.forEach(function (c) { c.style.display = (!cat || c.getAttribute('data-cat') === cat) ? '' : 'none'; });
			});
		});
	});
})();
