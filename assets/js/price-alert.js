/**
 * XGOUD prijsalarm-widget – schrijft in op de bestaande /price-alert REST.
 * Optioneel reCAPTCHA (v3) als een site-key is geconfigureerd.
 */
(function () {
	'use strict';
	document.querySelectorAll('.xg-pa-box').forEach(function (box) {
		var rest = box.getAttribute('data-rest');
		var rcKey = box.getAttribute('data-recaptcha') || '';
		var btn = box.querySelector('.xg-pa-btn');
		var msg = box.querySelector('.xg-pa-msg');
		if (!btn) return;

		if (rcKey && !window.grecaptcha && !document.getElementById('xg-rc-pa')) {
			var s = document.createElement('script');
			s.id = 'xg-rc-pa';
			s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(rcKey);
			document.head.appendChild(s);
		}

		function withToken(cb) {
			if (rcKey && window.grecaptcha && grecaptcha.execute) {
				grecaptcha.ready(function () {
					grecaptcha.execute(rcKey, { action: 'price_alert' }).then(cb).catch(function () { cb(''); });
				});
			} else { cb(''); }
		}

		btn.addEventListener('click', function () {
			var metal = box.querySelector('.xg-pa-metal').value;
			var direction = box.querySelector('.xg-pa-dir').value;
			var target = parseFloat(box.querySelector('.xg-pa-target').value) || 0;
			var email = (box.querySelector('.xg-pa-email').value || '').trim();
			if (!email || email.indexOf('@') < 1) { msg.textContent = 'Vul een geldig e-mailadres in.'; return; }
			if (target <= 0) { msg.textContent = 'Vul een doelprijs in.'; return; }
			btn.disabled = true; msg.textContent = 'Activeren…';
			withToken(function (rc) {
				fetch(rest, {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ email: email, metal: metal, direction: direction, target: target, recaptcha: rc })
				}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (o) {
						btn.disabled = false;
						msg.textContent = o.ok ? 'Prijsalarm geactiveerd! U ontvangt een mail zodra de doelprijs bereikt is.' : (o.j && o.j.message ? o.j.message : 'Activeren mislukt.');
						if (o.ok) box.querySelector('.xg-pa-email').value = '';
					})
					.catch(function () { btn.disabled = false; msg.textContent = 'Netwerkfout. Probeer later opnieuw.'; });
			});
		});
	});
})();
