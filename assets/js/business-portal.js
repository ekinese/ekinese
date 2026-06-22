/**
 * XGOUD zakelijk portaal – verstuurt de account-aanvraag naar /business-apply.
 * Optioneel reCAPTCHA v3.
 */
(function () {
	'use strict';
	var box = document.querySelector('.xg-biz-box');
	if (!box) return;
	var rest = box.getAttribute('data-rest');
	var rcKey = box.getAttribute('data-recaptcha') || '';
	var form = box.querySelector('.xg-biz-form');
	if (!form) return;
	var msg = form.querySelector('.xg-biz-msg');

	if (rcKey && !window.grecaptcha && !document.getElementById('xg-rc-biz')) {
		var s = document.createElement('script');
		s.id = 'xg-rc-biz';
		s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(rcKey);
		document.head.appendChild(s);
	}
	function withToken(cb) {
		if (rcKey && window.grecaptcha && grecaptcha.execute) {
			grecaptcha.ready(function () { grecaptcha.execute(rcKey, { action: 'business_apply' }).then(cb).catch(function () { cb(''); }); });
		} else { cb(''); }
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var btn = form.querySelector('button[type=submit]');
		var data = {};
		['company', 'kvk', 'contact', 'email', 'phone', 'metals', 'volume'].forEach(function (k) {
			if (form[k]) data[k] = form[k].value;
		});
		if (!data.company || !data.kvk || !data.email) { msg.textContent = 'Vul bedrijfsnaam, KvK en e-mail in.'; return; }
		btn.disabled = true; msg.textContent = 'Versturen…';
		withToken(function (rc) {
			data.recaptcha = rc;
			fetch(rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					if (o.ok) { form.innerHTML = '<h2>Aanvraag ontvangen ✓</h2><p>Bedankt! Wij nemen spoedig contact met u op over uw zakelijke condities.</p>'; }
					else { btn.disabled = false; msg.textContent = (o.j && o.j.message) || 'Versturen mislukt.'; }
				})
				.catch(function () { btn.disabled = false; msg.textContent = 'Netwerkfout. Probeer het later opnieuw.'; });
		});
	});
})();
