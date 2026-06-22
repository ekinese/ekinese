/**
 * XGOUD live-afspraak-kalender – kantoorkeuze → beschikbare slots → boeken via
 * de bestaande /appointment-REST.
 */
(function () {
	'use strict';
	var app = document.querySelector('.xg-booking-app');
	if (!app) return;
	var cfg;
	try { cfg = JSON.parse(app.getAttribute('data-booking')); } catch (e) { return; }
	var mount = app.querySelector('.xg-booking-mount');
	var state = { office: null, slots: [], date: null, time: null };

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function officePicker() {
		if (!cfg.offices || !cfg.offices.length) return '<p>Geen kantoren beschikbaar.</p>';
		var opts = cfg.offices.map(function (o) {
			return '<option value="' + o.id + '"' + (state.office == o.id ? ' selected' : '') + '>' + esc(o.name) + (o.city ? ' (' + esc(o.city) + ')' : '') + '</option>';
		}).join('');
		return '<label class="xg-booking-lbl">Kantoor</label><select class="xg-booking-office"><option value="">Kies een kantoor…</option>' + opts + '</select>';
	}

	function render() {
		var html = officePicker();
		if (state.office) {
			if (!state.slots.length) {
				html += '<p class="xg-booking-empty">Geen vrije momenten gevonden voor dit kantoor in de komende twee weken. Probeer een ander kantoor of neem contact op.</p>';
			} else {
				html += '<div class="xg-booking-days">';
				state.slots.forEach(function (d) {
					html += '<div class="xg-booking-day"><div class="xg-booking-date">' + esc(d.label) + '</div><div class="xg-booking-times">';
					d.times.forEach(function (t) {
						var sel = (state.date === d.date && state.time === t) ? ' is-sel' : '';
						html += '<button type="button" class="xg-booking-slot' + sel + '" data-date="' + esc(d.date) + '" data-time="' + esc(t) + '">' + esc(t) + '</button>';
					});
					html += '</div></div>';
				});
				html += '</div>';
				if (state.date && state.time) {
					html += bookingForm();
				}
			}
		}
		mount.innerHTML = html;
		wire();
	}

	function bookingForm() {
		return '<form class="xg-booking-form"><h4>Bevestig uw afspraak op ' + esc(state.date) + ' om ' + esc(state.time) + '</h4>' +
			'<div class="xg-booking-fields">' +
			'<input name="first" placeholder="Voornaam" required>' +
			'<input name="last" placeholder="Achternaam" required>' +
			'<input name="email" type="email" placeholder="E-mail" required>' +
			'<input name="phone" placeholder="Telefoon" required>' +
			'</div>' +
			'<button type="submit" class="xg-final-btn">Afspraak bevestigen</button>' +
			'<div class="xg-booking-msg" role="status"></div></form>';
	}

	function wire() {
		var sel = mount.querySelector('.xg-booking-office');
		if (sel) sel.addEventListener('change', function () {
			state.office = sel.value || null; state.date = null; state.time = null; state.slots = [];
			if (state.office) loadSlots(); else render();
		});
		mount.querySelectorAll('.xg-booking-slot').forEach(function (b) {
			b.addEventListener('click', function () { state.date = b.getAttribute('data-date'); state.time = b.getAttribute('data-time'); render(); });
		});
		var form = mount.querySelector('.xg-booking-form');
		if (form) form.addEventListener('submit', submitBooking);
	}

	function loadSlots() {
		mount.innerHTML = officePicker() + '<p class="xg-booking-loading">Beschikbaarheid laden…</p>';
		fetch(cfg.restSlots + '?office=' + encodeURIComponent(state.office))
			.then(function (r) { return r.json(); })
			.then(function (d) { state.slots = (d && d.slots) || []; render(); })
			.catch(function () { state.slots = []; render(); });
	}

	function submitBooking(e) {
		e.preventDefault();
		var form = e.target;
		var msg = form.querySelector('.xg-booking-msg');
		var btn = form.querySelector('button[type=submit]');
		var body = {
			first: form.first.value, last: form.last.value, email: form.email.value, phone: form.phone.value,
			office_id: state.office, date: state.date, time: state.time, service: 'kantoorbezoek'
		};
		btn.disabled = true; msg.textContent = 'Bevestigen…';
		fetch(cfg.restBook, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (o) {
				if (o.ok) {
					mount.innerHTML = '<div class="xg-booking-done"><h4>Afspraak bevestigd ✓</h4><p>Wij zien u graag op ' + esc(state.date) + ' om ' + esc(state.time) + '. U ontvangt een bevestiging per e-mail.</p></div>';
				} else { btn.disabled = false; msg.textContent = (o.j && o.j.message) || 'Boeken mislukt. Probeer een ander tijdslot.'; }
			})
			.catch(function () { btn.disabled = false; msg.textContent = 'Netwerkfout. Probeer het later opnieuw.'; });
	}

	render();
})();
