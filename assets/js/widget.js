/**
 * XGOUD app-widget — live prijzen + profiel + portfolio + afspraken.
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-widget');
	if (!root) return;
	var U = { prices: root.getAttribute('data-prices'), login: root.getAttribute('data-login'), data: root.getAttribute('data-data') };
	var token = new URLSearchParams(location.search).get('token') || localStorage.getItem('xg_acct_token') || '';
	if (token) localStorage.setItem('xg_acct_token', token);

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	var pricesHtml = '<div class="xg-w-prices">…</div>';
	function loadPrices() {
		fetch(U.prices).then(function (r) { return r.json(); }).then(function (d) {
			var p = (d && d.prices) || {};
			pricesHtml = '<div class="xg-w-prices">' + Object.keys(p).map(function (k) {
				return '<div class="xg-w-price"><span>' + esc(p[k].label) + '</span><strong>€ ' + esc(p[k].gram) + '</strong><small>/g</small></div>';
			}).join('') + '</div>';
			paint();
		}).catch(paint);
	}

	var account = null, accountState = 'none'; // none | loading | error
	function loadAccount() {
		if (!token) return;
		accountState = 'loading';
		fetch(U.data + '?token=' + encodeURIComponent(token)).then(function (r) {
			if (!r.ok) throw 0; return r.json();
		}).then(function (d) { account = d; accountState = 'ok'; paint(); })
			.catch(function () { accountState = 'error'; localStorage.removeItem('xg_acct_token'); token = ''; paint(); });
	}

	function paint() {
		var head = '<div class="xg-w-head"><div class="xg-w-logo">X<span>GOUD</span></div><a class="xg-w-full" href="/mijn-xgoud/' + (token ? '?token=' + encodeURIComponent(token) : '') + '">Volledig overzicht →</a></div>';
		var body;
		if (account) {
			var pf = account.portfolio || {};
			var lo = account.loyalty || {};
			var appts = (account.appointments || []).slice(0, 3);
			body =
				'<section class="xg-w-card xg-w-profile"><div class="xg-w-pts"><strong>' + esc(account.points != null ? account.points : 0) + '</strong><span>spaarpunten</span></div>' +
				'<div class="xg-w-meta"><div>' + esc(account.email) + '</div>' + (lo.tier ? '<div class="xg-w-tier">' + esc(lo.tier) + ' · +' + esc(lo.bonus || 0) + '% bonus</div>' : '') + '</div></section>' +
				'<section class="xg-w-card"><h3>Mijn portfolio</h3><div class="xg-w-pf"><div class="xg-w-pf-val">€ ' + esc(pf.total != null ? pf.total : 0) + '</div>' +
				'<div class="xg-w-pf-gain ' + ((pf.gain || 0) >= 0 ? 'up' : 'down') + '">' + ((pf.gain || 0) >= 0 ? '▲' : '▼') + ' € ' + esc(Math.abs(pf.gain || 0)) + ' (' + esc(pf.gain_pct || 0) + '%)</div></div></section>' +
				'<section class="xg-w-card"><h3>Afspraken</h3>' +
				(appts.length ? appts.map(function (a) {
					return '<div class="xg-w-appt"><span class="xg-w-appt-d">' + esc(a.date || '') + ' ' + esc(a.time || '') + '</span><span>' + esc(a.service || '') + '</span><em>' + esc(a.status || '') + '</em></div>';
				}).join('') : '<p class="xg-w-empty">Geen afspraken. <a href="/afspraak/">Plan er een →</a></p>') +
				'</section>';
		} else if (accountState === 'loading') {
			body = '<section class="xg-w-card">Profiel laden…</section>';
		} else {
			body = '<section class="xg-w-card xg-w-login"><h3>Inloggen</h3><p>Ontvang een inloglink voor uw profiel, portfolio en afspraken.</p>' +
				'<form class="xg-w-loginform"><input type="email" name="email" placeholder="uw@email.nl" required><button type="submit">Stuur link</button></form>' +
				'<p class="xg-w-msg" role="status"></p></section>';
		}
		root.innerHTML = head + pricesHtml + body;
		bind();
	}

	function bind() {
		var f = root.querySelector('.xg-w-loginform');
		if (f) {
			f.addEventListener('submit', function (e) {
				e.preventDefault();
				var email = f.querySelector('[name=email]').value.trim();
				if (!email) return;
				var msg = root.querySelector('.xg-w-msg'); msg.textContent = 'Bezig…';
				fetch(U.login, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: email, redirect: '/app/' }) })
					.then(function (r) { return r.json(); })
					.then(function (d) { msg.textContent = (d && d.message) || 'Controleer uw e-mail.'; })
					.catch(function () { msg.textContent = 'Er ging iets mis.'; });
			});
		}
	}

	paint();
	loadPrices();
	loadAccount();
})();
