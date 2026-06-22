/**
 * XGOUD foto-taxatie – stuurt een foto naar /photo-appraisal en toont de
 * AI-indicatie. De foto wordt niet opgeslagen.
 */
(function () {
	'use strict';
	document.querySelectorAll('.xg-photo-box').forEach(function (box) {
		var rest = box.getAttribute('data-rest');
		var file = box.querySelector('.xg-photo-file');
		var btn = box.querySelector('.xg-photo-btn');
		var out = box.querySelector('.xg-photo-result');
		var drop = box.querySelector('.xg-photo-drop span');
		if (!file || !btn) return;

		file.addEventListener('change', function () {
			if (file.files && file.files[0]) {
				btn.disabled = false;
				if (drop) drop.textContent = file.files[0].name;
			} else {
				btn.disabled = true;
			}
		});

		btn.addEventListener('click', function () {
			if (!file.files || !file.files[0]) return;
			var f = file.files[0];
			if (f.size > 8 * 1024 * 1024) { show('De foto is te groot (max 8 MB).', true); return; }
			btn.disabled = true; show('Analyseren…', false);
			var fd = new FormData();
			fd.append('photo', f);
			fetch(rest, { method: 'POST', body: fd })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					btn.disabled = false;
					if (o.ok && o.j.analysis) {
						out.hidden = false;
						out.className = 'xg-photo-result is-hit';
						out.innerHTML = '<p>' + esc(o.j.analysis).replace(/\n/g, '<br>') + '</p>' +
							'<p class="xg-photo-disc">Indicatief op basis van de foto. De definitieve waarde bepaalt onze expert bij een gratis taxatie.</p>' +
							'<a class="xg-btn-gold" href="/afspraak/">Maak een gratis afspraak</a>';
					} else {
						show((o.j && o.j.message) || 'Analyse mislukt. Probeer een duidelijkere foto.', true);
					}
				})
				.catch(function () { btn.disabled = false; show('Netwerkfout. Probeer het later opnieuw.', true); });
		});

		function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
		function show(msg, isErr) {
			out.hidden = false;
			out.className = 'xg-photo-result' + (isErr ? ' is-miss' : '');
			out.innerHTML = '<p>' + esc(msg) + '</p>';
		}
	});
})();
