/**
 * XGOUD Treuhandservice — aanvraagformulier (blok ekinese/escrow).
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-escrow');
	if (!root) return;
	var form = root.querySelector('.xg-escrow-form');
	var msg = root.querySelector('.xg-escrow-msg');
	var rest = root.getAttribute('data-rest');
	var token = new URLSearchParams(location.search).get('token');
	if (!form) return;

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (form.website && form.website.value) return;
		var btn = form.querySelector('button[type=submit]');
		var body = {
			role: form.role.value, type: form.type.value,
			email: form.email.value, counterparty: form.counterparty.value,
			item: form.item.value, amount: form.amount.value,
			description: form.description.value
		};
		if (token) body.token = token;
		btn.disabled = true; msg.textContent = 'Bezig…'; msg.className = 'xg-escrow-msg';
		fetch(rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (o) {
				btn.disabled = false;
				if (o.ok && o.j && o.j.ok) {
					msg.textContent = o.j.message || 'Aanvraag verstuurd.';
					msg.className = 'xg-escrow-msg is-ok';
					form.reset();
				} else {
					msg.textContent = (o.j && o.j.message) || 'Mislukt. Controleer de velden.';
					msg.className = 'xg-escrow-msg is-err';
				}
			})
			.catch(function () { btn.disabled = false; msg.textContent = 'Netwerkfout.'; msg.className = 'xg-escrow-msg is-err'; });
	});
})();
