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
	var apiBase = (restData || '').replace('/account/data', '');

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

	function statCards(d) {
		var pf = d.portfolio || {};
		var cards = [
			['Spaarpunten', (d.points != null ? d.points : 0)],
			['Portfoliowaarde', pf.total != null ? ('€ ' + esc(pf.total)) : '€ 0'],
			['Winst/verlies', (pf.gain != null ? ('€ ' + esc(pf.gain) + ' (' + esc(pf.gain_pct || 0) + '%)') : '—')]
		];
		var html = '<div class="xg-acc-stats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0">';
		cards.forEach(function (c) {
			html += '<div class="xg-c-card" style="padding:16px"><div style="font-size:22px;font-weight:700;color:var(--red,#AE1E1E)">' + c[1] + '</div><div style="font-size:12px;color:var(--ink-soft,#6b665c)">' + esc(c[0]) + '</div></div>';
		});
		html += '</div>';
		var lo = d.loyalty;
		if (lo) {
			html += '<div class="xg-loyalty"><div class="xg-loyalty-head"><span class="xg-loyalty-tier">' + esc(lo.tier) + '</span>' +
				'<span class="xg-loyalty-bonus">+' + esc(lo.bonus) + '% trouwbonus</span></div>' +
				'<div class="xg-loyalty-bar"><span style="width:' + esc(lo.progress) + '%"></span></div>';
			if (lo.next_tier && lo.to_next > 0) {
				html += '<div class="xg-loyalty-next">Nog ' + esc(lo.to_next) + ' verkoop(en) tot ' + esc(lo.next_tier) + '</div>';
			} else {
				html += '<div class="xg-loyalty-next">Hoogste niveau bereikt — bedankt!</div>';
			}
			html += '</div>';
		}
		return html;
	}

	// #21 Referral-dashboard – uitnodig-link, aantal aanmeldingen, voortgang.
	function referralCard(d) {
		if (!d.referral_url) return '';
		var count = d.referral_count != null ? d.referral_count : 0;
		var next = 5; // mijlpaal voor extra beloning
		var pct = Math.min(100, Math.round((count % next) / next * 100));
		return '<section class="xg-acc-sec xg-ref-card">' +
			'<h3>Vrienden uitnodigen</h3>' +
			'<p>Deel uw persoonlijke link. U én uw vriend ontvangen spaarpunten zodra hij of zij zich aanmeldt.</p>' +
			'<div class="xg-ref-link"><input type="text" readonly value="' + esc(d.referral_url) + '"><button type="button" class="xg-ref-copy" data-link="' + esc(d.referral_url) + '">Kopieer</button></div>' +
			'<div class="xg-ref-stat"><strong>' + count + '</strong> aanmelding' + (count === 1 ? '' : 'en') + ' via uw link</div>' +
			'<div class="xg-loyalty-bar"><span style="width:' + pct + '%"></span></div>' +
			'<div class="xg-ref-next">Nog ' + (next - (count % next)) + ' tot uw volgende bonus</div>' +
			'</section>';
	}

	// #20 Jaaroverzicht – deelbare "year in review"-kaarten.
	function yearReviewCard(d) {
		if (!d.year_review) return '';
		var years = Object.keys(d.year_review).sort(function (a, b) { return b - a; });
		if (!years.length) return '';
		var cards = years.map(function (y) {
			var v = d.year_review[y];
			return '<div class="xg-yr-card">' +
				'<div class="xg-yr-year">' + esc(y) + '</div>' +
				'<div class="xg-yr-row"><span>Verkopen</span><strong>' + esc(v.count) + '</strong></div>' +
				'<div class="xg-yr-row"><span>Uitbetaald</span><strong>€ ' + esc(v.payout) + '</strong></div>' +
				'<div class="xg-yr-row xg-yr-charity"><span>Naar goede doelen</span><strong>€ ' + esc(v.charity) + '</strong></div>' +
				'</div>';
		}).join('');
		return '<section class="xg-acc-sec"><h3>Mijn jaaroverzicht</h3><div class="xg-yr-grid">' + cards + '</div></section>';
	}

	// #22 Spaardoel – doel instellen, voortgang op portfoliowaarde.
	function savingsCard(d) {
		if (!d.savings) return '';
		var s = d.savings, goal = s.goal || {}, target = goal.target || 0, cur = s.current || 0;
		var pct = target > 0 ? Math.min(100, Math.round(cur / target * 100)) : 0;
		var prog = target > 0
			? '<div class="xg-loyalty-bar"><span style="width:' + pct + '%"></span></div><div class="xg-goal-meta">€ ' + esc(cur) + ' van € ' + esc(target) + ' (' + pct + '%)' + (goal.label ? ' · ' + esc(goal.label) : '') + '</div>'
			: '<p class="xg-acc-empty">Nog geen doel ingesteld.</p>';
		return '<section class="xg-acc-sec xg-goal-card" data-rest="' + esc(s.rest) + '">' +
			'<h3>Mijn spaardoel</h3>' + prog +
			'<div class="xg-goal-form"><input type="text" class="xg-goal-label" placeholder="Bijv. nieuwe keuken" value="' + esc(goal.label || '') + '">' +
			'<input type="number" class="xg-goal-target" min="0" step="50" placeholder="Doelbedrag €" value="' + (target || '') + '">' +
			'<button type="button" class="xg-goal-save">Opslaan</button></div>' +
			'<div class="xg-goal-msg" role="status"></div></section>';
	}

	function notifyCard(d) {
		var items = d.notifications || [];
		if (!items.length) return '';
		var unread = d.notifications_unread || 0;
		var rows = items.map(function (n) {
			var inner = '<div class="xg-notif-dot"></div><div class="xg-notif-body"><strong>' + esc(n.title) + '</strong><span>' + esc(n.message) + '</span><time>' + esc(n.date) + '</time></div>';
			var cls = 'xg-notif' + (n.read ? '' : ' is-new');
			return n.url
				? '<a class="' + cls + '" href="' + esc(n.url) + '">' + inner + '</a>'
				: '<div class="' + cls + '">' + inner + '</div>';
		}).join('');
		return '<section class="xg-acc-sec xg-notifs"><h3>Meldingen' +
			(unread ? ' <span class="xg-notif-badge">' + unread + '</span>' : '') +
			(unread ? ' <button type="button" class="xg-notif-read">Alles gelezen</button>' : '') +
			'</h3><div class="xg-notif-list">' + rows + '</div></section>';
	}

	// Zelfbediening-formulieren: bezit toevoegen, ticket aanmaken, prijsalarm.
	// data-endpoint = REST-pad; data-token=1 stuurt het account-token mee.
	function holdingForm() {
		return '<form class="xg-acc-form" data-acc-form data-endpoint="' + apiBase + '/portfolio/add" data-token="1">' +
			'<h4>Bezit toevoegen</h4>' +
			'<div class="xg-acc-form-row">' +
			'<input name="name" placeholder="Omschrijving (bijv. Krugerrand 1 oz)" required>' +
			'<select name="metal"><option value="goud">Goud</option><option value="zilver">Zilver</option><option value="platina">Platina</option><option value="palladium">Palladium</option></select>' +
			'</div>' +
			'<div class="xg-acc-form-row">' +
			'<input name="fine_weight" type="number" step="0.01" min="0" placeholder="Fijn gewicht (g)" required>' +
			'<input name="qty" type="number" min="1" value="1" placeholder="Aantal">' +
			'<input name="purchase_price" type="number" step="0.01" min="0" placeholder="Inkoopprijs € (totaal)">' +
			'</div>' +
			'<button type="submit">Toevoegen</button><span class="xg-acc-form-msg" role="status"></span></form>';
	}
	function ticketForm(d) {
		return '<form class="xg-acc-form" data-acc-form data-endpoint="' + apiBase + '/ticket">' +
			'<h4>Nieuw ticket</h4>' +
			'<input type="hidden" name="email" value="' + esc(d.email) + '">' +
			'<div class="xg-acc-form-row"><input name="subject" placeholder="Onderwerp" required></div>' +
			'<textarea name="message" rows="3" placeholder="Uw vraag of bericht" required></textarea>' +
			'<button type="submit">Versturen</button><span class="xg-acc-form-msg" role="status"></span></form>';
	}
	function alertForm(d) {
		return '<form class="xg-acc-form" data-acc-form data-endpoint="' + apiBase + '/price-alert">' +
			'<h4>Nieuw prijsalarm</h4>' +
			'<input type="hidden" name="email" value="' + esc(d.email) + '">' +
			'<div class="xg-acc-form-row">' +
			'<select name="metal"><option value="goud">Goud</option><option value="zilver">Zilver</option><option value="platina">Platina</option><option value="palladium">Palladium</option></select>' +
			'<select name="direction"><option value="above">Stijgt boven</option><option value="below">Daalt onder</option></select>' +
			'<input name="target" type="number" step="0.01" min="0" placeholder="Doelprijs € per gram" required>' +
			'</div>' +
			'<button type="submit">Alarm instellen</button><span class="xg-acc-form-msg" role="status"></span></form>';
	}

	function renderDash(d) {
		if (loginBox) loginBox.hidden = true;
		dash.hidden = false;
		dash.innerHTML =
			'<h2>Mijn XGOUD</h2><p class="xg-acc-email">' + esc(d.email) + '</p>' +
			notifyCard(d) +
			statCards(d) +
			savingsCard(d) +
			yearReviewCard(d) +
			referralCard(d) +
			section('Mijn portfolio', (d.portfolio || {}).items, [
				{ key: 'name', label: 'Product' }, { key: 'qty', label: 'Aantal' },
				{ key: 'value', label: 'Waarde (€)' }, { key: 'gain', label: 'Winst/verlies (€)' },
				{ key: 'gain_pct', label: '%' }
			]) +
			holdingForm() +
			section('Lopende loterijen', d.lotteries, [
				{ key: 'title', label: 'Loterij' }, { key: 'prize', label: 'Prijs' },
				{ key: 'cost', label: 'Inzet (punten)' }, { key: 'tickets', label: 'Loten' }
			]) +
			section('Afspraken', d.appointments, [
				{ key: 'date', label: 'Datum' }, { key: 'time', label: 'Tijd' },
				{ key: 'service', label: 'Service' }, { key: 'status', label: 'Status' }
			]) +
			section('Mijn veilingen', d.auctions, [
				{ key: 'title', label: 'Veiling' }, { key: 'amount', label: 'Eindbod' },
				{ key: 'proforma', label: 'Pro forma' }, { key: 'invoice', label: 'Factuur' }, { key: 'paid', label: 'Betaald' }
			]) +
			section('Tickets', d.tickets, [
				{ key: 'reference', label: 'Referentie' }, { key: 'subject', label: 'Onderwerp' }, { key: 'status', label: 'Status' }
			]) +
			ticketForm(d) +
			section('Zendingen', d.pickups, [
				{ key: 'reference', label: 'Referentie' }, { key: 'status', label: 'Status' }
			]) +
			section('Prijsalarmen', d.alerts, [
				{ key: 'metal', label: 'Metaal' }, { key: 'direction', label: 'Richting' },
				{ key: 'target', label: 'Doelprijs' }, { key: 'active', label: 'Actief' }
			]) +
			alertForm(d);
		bindDashEvents(d);
	}

	// Dashboard opnieuw laden (na een wijziging) met hetzelfde token.
	function reloadDash() {
		fetch(restData + '?token=' + encodeURIComponent(token))
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (d) { if (d) renderDash(d); })
			.catch(function () {});
	}

	// Interacties in het dashboard (referral kopiëren, spaardoel opslaan).
	function bindDashEvents(d) {
		// Zelfbediening-formulieren (bezit/ticket/alarm) → POST naar REST.
		dash.querySelectorAll('[data-acc-form]').forEach(function (f) {
			f.addEventListener('submit', function (e) {
				e.preventDefault();
				var fmsg = f.querySelector('.xg-acc-form-msg');
				var btn = f.querySelector('button[type="submit"]');
				var body = {};
				new FormData(f).forEach(function (v, k) { body[k] = v; });
				if (f.getAttribute('data-token')) { body.token = token; }
				if (btn) btn.disabled = true;
				if (fmsg) { fmsg.textContent = 'Bezig…'; fmsg.className = 'xg-acc-form-msg'; }
				fetch(f.getAttribute('data-endpoint'), {
					method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
				}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (o) {
						if (btn) btn.disabled = false;
						if (o.ok && o.j && o.j.ok) {
							if (fmsg) { fmsg.textContent = 'Opgeslagen.'; fmsg.className = 'xg-acc-form-msg is-ok'; }
							f.reset();
							reloadDash();
						} else if (fmsg) {
							fmsg.textContent = (o.j && o.j.message) || 'Mislukt. Controleer de velden.';
							fmsg.className = 'xg-acc-form-msg is-err';
						}
					}).catch(function () {
						if (btn) btn.disabled = false;
						if (fmsg) { fmsg.textContent = 'Netwerkfout.'; fmsg.className = 'xg-acc-form-msg is-err'; }
					});
			});
		});

		var notifRead = dash.querySelector('.xg-notif-read');
		if (notifRead) {
			notifRead.addEventListener('click', function () {
				notifRead.disabled = true;
				fetch(restData.replace('/account/data', '/account/notifications/read'), {
					method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: token })
				}).then(function () {
					dash.querySelectorAll('.xg-notif.is-new').forEach(function (el) { el.classList.remove('is-new'); });
					var badge = dash.querySelector('.xg-notif-badge'); if (badge) badge.remove();
					notifRead.remove();
				}).catch(function () { notifRead.disabled = false; });
			});
		}
		var copy = dash.querySelector('.xg-ref-copy');
		if (copy) {
			copy.addEventListener('click', function () {
				var link = copy.getAttribute('data-link');
				try { (navigator.clipboard ? navigator.clipboard.writeText(link) : null); } catch (e) {}
				var inp = dash.querySelector('.xg-ref-link input');
				if (inp) { inp.select(); try { document.execCommand('copy'); } catch (e) {} }
				copy.textContent = 'Gekopieerd';
				setTimeout(function () { copy.textContent = 'Kopieer'; }, 1800);
			});
		}
		var goalCard = dash.querySelector('.xg-goal-card');
		if (goalCard) {
			var saveBtn = goalCard.querySelector('.xg-goal-save');
			saveBtn.addEventListener('click', function () {
				var target = parseFloat(goalCard.querySelector('.xg-goal-target').value) || 0;
				var label = goalCard.querySelector('.xg-goal-label').value || '';
				var msg = goalCard.querySelector('.xg-goal-msg');
				saveBtn.disabled = true; msg.textContent = 'Opslaan…';
				fetch(goalCard.getAttribute('data-rest'), {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: token, target: target, label: label })
				}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (o) {
						saveBtn.disabled = false;
						if (o.ok) { msg.textContent = 'Doel opgeslagen.'; d.savings.goal = o.j.goal; d.savings.current = o.j.current; renderDash(d); }
						else { msg.textContent = (o.j && o.j.message) || 'Opslaan mislukt.'; }
					})
					.catch(function () { saveBtn.disabled = false; msg.textContent = 'Netwerkfout.'; });
			});
		}
	}
})();
