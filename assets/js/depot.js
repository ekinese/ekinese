/**
 * XGOUD Virtueel Depot — aanmelden voor opslag + jaargeld betalen.
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-depot');
	if (!root) return;
	var token = new URLSearchParams(location.search).get('token');
	var storeUrl = root.getAttribute('data-store');
	var payUrl = root.getAttribute('data-pay');

	// Jaargeld betalen.
	var payBtn = root.querySelector('.xg-depot-pay');
	if (payBtn) {
		payBtn.addEventListener('click', function (e) {
			e.preventDefault();
			if (!token) { alert('Log eerst in via Mijn XGOUD om je depot te openen.'); location.href = '/mijn-xgoud/'; return; }
			location.href = payUrl + '?type=depot&token=' + encodeURIComponent(token);
		});
	}

	// Item aanmelden.
	var form = root.querySelector('.xg-depot-form');
	var msg = root.querySelector('.xg-depot-msg');
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!token) { msg.textContent = ' Log eerst in via Mijn XGOUD.'; msg.className = 'xg-depot-msg is-err'; return; }
			var btn = form.querySelector('button');
			btn.disabled = true; msg.textContent = 'Bezig…'; msg.className = 'xg-depot-msg';
			fetch(storeUrl, {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: token, item: form.item.value, value: form.value.value })
			}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					btn.disabled = false;
					if (o.ok && o.j && o.j.ok) { msg.textContent = o.j.message || 'Aangemeld.'; msg.className = 'xg-depot-msg is-ok'; form.reset(); }
					else { msg.textContent = (o.j && o.j.message) || 'Mislukt.'; msg.className = 'xg-depot-msg is-err'; }
				})
				.catch(function () { btn.disabled = false; msg.textContent = 'Netwerkfout.'; msg.className = 'xg-depot-msg is-err'; });
		});
	}
})();
