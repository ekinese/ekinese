/**
 * Mijn XGOUD – self-service account (magic-link).
 * Datenquelle: REST ekinese/v1/account/{login,data}. Geen wachtwoord.
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-account');
	if (!root) return;

	var loginBox = root.querySelector('.xg-account-login');
	var dash = root.querySelector('.xg-account-dash');
	var form = root.querySelector('.xg-account-form');
	var msg = root.querySelector('.xg-account-msg');
	var restLogin = root.getAttribute('data-rest-login');
	var restData = root.getAttribute('data-rest-data');

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	// 1) Magic-link aanvragen.
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var email = form.querySelector('[name=email]').value.trim();
			if (!email) return;
			msg.textContent = 'Bezig…';
			fetch(restLogin, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: email })
			}).then(function (r) { return r.json(); })
				.then(function (d) { msg.textContent = (d && d.message) || 'Controleer uw e-mail.'; })
				.catch(function () { msg.textContent = 'Er ging iets mis. Probeer het later opnieuw.'; });
		});
	}

	// 2) Token uit de URL → overzicht laden.
	var token = new URLSearchParams(location.search).get('token');
	if (!token) return;

	fetch(restData + '?token=' + encodeURIComponent(token))
		.then(function (r) {
			if (!r.ok) throw new Error('unauthorized');
			return r.json();
		})
		.then(function (d) { renderDash(d); })
		.catch(function () {
			if (msg) msg.textContent = 'Uw inloglink is ongeldig of verlopen. Vraag een nieuwe aan.';
		});

	function section(title, rows, cols) {
		if (!rows || !rows.length) {
			return '<section class="xg-acc-sec"><h3>' + esc(title) + '</h3><p class="xg-acc-empty">Geen items.</p></section>';
		}
		var head = cols.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('');
		var body = rows.map(function (row) {
			return '<tr>' + cols.map(function (c) { return '<td>' + esc(row[c.key]) + '</td>'; }).join('') + '</tr>';
		}).join('');
		return '<section class="xg-acc-sec"><h3>' + esc(title) + '</h3><div class="xg-table-scroll"><table class="xg-spec-table"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table></div></section>';
	}

	function renderDash(d) {
		if (loginBox) loginBox.hidden = true;
		dash.hidden = false;
		dash.innerHTML =
			'<h2>Mijn XGOUD</h2><p class="xg-acc-email">' + esc(d.email) + '</p>' +
			section('Afspraken', d.appointments, [
				{ key: 'date', label: 'Datum' }, { key: 'time', label: 'Tijd' },
				{ key: 'service', label: 'Service' }, { key: 'status', label: 'Status' }
			]) +
			section('Tickets', d.tickets, [
				{ key: 'reference', label: 'Referentie' }, { key: 'subject', label: 'Onderwerp' }, { key: 'status', label: 'Status' }
			]) +
			section('Zendingen', d.pickups, [
				{ key: 'reference', label: 'Referentie' }, { key: 'status', label: 'Status' }
			]) +
			section('Prijsalarmen', d.alerts, [
				{ key: 'metal', label: 'Metaal' }, { key: 'direction', label: 'Richting' },
				{ key: 'target', label: 'Doelprijs' }, { key: 'active', label: 'Actief' }
			]);
	}
})();
