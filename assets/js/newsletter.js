/** XGOUD Newsletter-Banner: aanmelden via REST (window.XG_NEWSLETTER.rest). */
(function () {
	'use strict';
	var CFG = window.XG_NEWSLETTER || {};
	document.querySelectorAll('.xg-newsletter-form').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var wrap = form.closest('.xg-newsletter') || document;
			var msg = wrap.querySelector('.xg-newsletter-msg');
			var email = (form.querySelector('input[type=email]') || {}).value || '';
			var city = (form.querySelector('input[type=text]') || {}).value || '';
			if (!/.+@.+\..+/.test(email)) { if (msg) { msg.className = 'xg-newsletter-msg err'; msg.textContent = 'Vul een geldig e-mailadres in.'; } return; }
			var payload = { email: email, city: city, source: location.href, lang: document.documentElement.lang || 'nl' };
			function ok() { if (msg) { msg.className = 'xg-newsletter-msg ok'; msg.textContent = 'Bedankt voor uw aanmelding!'; } form.reset(); }
			if (CFG.rest && window.fetch) {
				fetch(CFG.rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
					.then(function (r) { return r.json(); }).then(ok).catch(ok);
			} else { ok(); }
		});
	});
})();
