/**
 * XGOUD fahrer-app (PWA-stijl). Alles loopt via deze app: dienst + fahrtenbuch,
 * stops met ETA, onkosten (bon scannen → AI-voorstel → bevestigen), handtekening
 * + ID/IBAN voor KYC, en GPS tijdens dienst.
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-fleet-app');
	if (!root) return;
	var base = (window.XGFleet || {}).rest || '/wp-json/ekinese/v1/fleet';
	var code = localStorage.getItem('xg_fleet_code') || '';
	var data = null, tab = 'route', gpsTimer = null, lastGps = 0;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function api(path, body) {
		var opt = body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ code: code }, body)) } : {};
		var url = body ? base + path : base + path + '?code=' + encodeURIComponent(code);
		return fetch(url, opt).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
	}
	function readFile(file) {
		return new Promise(function (res) { var fr = new FileReader(); fr.onload = function () { res(fr.result); }; fr.readAsDataURL(file); });
	}

	function login() {
		root.innerHTML = '<div class="xg-fa-login"><h2>XGOUD Rit</h2><p>Voer uw persoonlijke code in.</p>' +
			'<input id="xg-fa-code" type="text" placeholder="Code" value="' + esc(code) + '"><button id="xg-fa-go">Inloggen</button><p class="xg-fa-msg"></p></div>';
		root.querySelector('#xg-fa-go').addEventListener('click', function () {
			code = root.querySelector('#xg-fa-code').value.trim();
			localStorage.setItem('xg_fleet_code', code);
			load();
		});
	}

	function load() {
		api('/me').then(function (o) {
			if (!o.ok || !o.j || !o.j.driver) { root.querySelector('.xg-fa-msg') && (root.querySelector('.xg-fa-msg').textContent = 'Onbekende code.'); return; }
			data = o.j; render(); manageGps();
		}).catch(function () { login(); });
	}

	/* ---------- GPS tijdens dienst ---------- */
	function manageGps() {
		var on = data && data.shift && data.shift.open;
		if (on && !gpsTimer && navigator.geolocation) {
			gpsTimer = navigator.geolocation.watchPosition(function (pos) {
				var now = Date.now();
				if (now - lastGps < 30000) return; // max 1×/30s
				lastGps = now;
				api('/gps', { lat: pos.coords.latitude, lng: pos.coords.longitude });
			}, function () {}, { enableHighAccuracy: true, maximumAge: 20000 });
		}
		if (!on && gpsTimer) { navigator.geolocation.clearWatch(gpsTimer); gpsTimer = null; }
	}

	/* ---------- Render ---------- */
	function render() {
		var s = data.shift || {};
		var head = '<div class="xg-fa-head"><div><strong>' + esc(data.driver) + '</strong><span>' + esc(data.date) + '</span></div>' +
			(s.open ? '<span class="xg-fa-badge on">In dienst</span>' : '<span class="xg-fa-badge">Offline</span>') + '</div>';
		var body = tab === 'route' ? routeView() : tab === 'kosten' ? kostenView() : tab === 'contact' ? contactView() : profielView();
		var tabs = '<nav class="xg-fa-tabs">' +
			['route|Route', 'kosten|Onkosten', 'contact|Contact', 'profiel|Profiel'].map(function (t) {
				var k = t.split('|');
				return '<button data-tab="' + k[0] + '"' + (tab === k[0] ? ' class="active"' : '') + '>' + k[1] + '</button>';
			}).join('') + '</nav>';
		root.innerHTML = '<div class="xg-fa">' + head + '<div class="xg-fa-body">' + body + '</div>' + tabs + '</div>';
		bind();
	}

	function routeView() {
		var s = data.shift || {};
		var shiftBox = s.open
			? '<div class="xg-fa-shift"><div>Gestart ' + esc(s.start || '') + ' · km-begin ' + esc(s.km_start || '—') + '</div><button class="xg-fa-btn red" data-act="endshift">Dienst beëindigen</button></div>'
			: '<div class="xg-fa-shift"><button class="xg-fa-btn green" data-act="startshift">Dienst starten</button></div>';
		var stops = (data.stops || []).map(function (st, i) {
			var done = st.status === 'picked_up' || st.status === 'delivered';
			return '<div class="xg-fa-stop' + (done ? ' done' : '') + '" data-stop="' + i + '">' +
				'<div class="xg-fa-stop-h"><span class="xg-fa-eta">' + esc(st.eta || st.time || '—') + '</span><strong>' + esc(st.name || '') + '</strong></div>' +
				'<div class="xg-fa-stop-a">' + esc(st.address || '') + (st.leg_km ? ' · ' + esc(st.leg_km) + ' km' : '') + '</div>' +
				'<div class="xg-fa-stop-s">Waar: ' + esc(st.service || 'ophalen') + ' · ' + esc(st.status || 'open') + '</div></div>';
		}).join('') || '<p class="xg-fa-empty">Geen stops vandaag.</p>';
		return shiftBox + '<h3>Route</h3>' + stops;
	}

	function kostenView() {
		var rows = (data.expenses || []).map(function (e) {
			return '<tr><td>' + esc(e.date) + '</td><td>€ ' + esc(e.amount) + '</td><td>' + esc(e.category) + '</td><td>' + esc(e.status) + '</td></tr>';
		}).join('');
		return '<h3>Onkosten</h3>' +
			'<button class="xg-fa-btn gold" data-act="scan">📷 Bon scannen / toevoegen</button>' +
			'<div class="xg-fa-expform" hidden></div>' +
			'<div class="xg-fa-week">Deze week: <strong>€ ' + esc(data.week_total) + '</strong></div>' +
			(rows ? '<table class="xg-fa-table"><thead><tr><th>Datum</th><th>Bedrag</th><th>Categorie</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody></table>' : '<p class="xg-fa-empty">Nog geen onkosten.</p>');
	}

	function contactView() {
		var wa = data.whatsapp ? '<a class="xg-fa-btn green" href="https://wa.me/' + esc(data.whatsapp) + '" target="_blank">💬 WhatsApp kantoor</a>' : '';
		var call = data.phone ? '<a class="xg-fa-btn" href="tel:' + esc(data.phone) + '">📞 Bel kantoor</a>' : '';
		var tickets = (data.tickets || []).map(function (t) {
			return '<div class="xg-fa-tk"><strong>' + esc(t.reference || '') + '</strong> ' + esc(t.subject || '') + ' <em>' + esc(t.status || '') + '</em></div>';
		}).join('') || '<p class="xg-fa-empty">Nog geen tickets.</p>';
		return '<h3>Contact kantoor</h3>' + wa + call +
			'<h3>Nieuw ticket</h3><div class="xg-fa-tkform">' +
			'<input class="t-subject" type="text" placeholder="Onderwerp">' +
			'<textarea class="t-message" rows="3" placeholder="Omschrijf het probleem of de vraag"></textarea>' +
			'<button class="xg-fa-btn gold" data-act="ticket">Ticket versturen</button><span class="t-msg"></span></div>' +
			'<h3>Mijn tickets</h3>' + tickets;
	}

	function profielView() {
		return '<h3>Profiel</h3><p>Chauffeur: <strong>' + esc(data.driver) + '</strong></p>' +
			'<p>Datum: ' + esc(data.date) + '</p>' +
			'<p class="xg-fa-note">GPS wordt alleen tijdens uw dienst gedeeld. U kunt de dienst altijd beëindigen.</p>' +
			'<button class="xg-fa-btn" data-act="logout">Uitloggen</button>';
	}

	/* ---------- Interacties ---------- */
	function bind() {
		root.querySelectorAll('.xg-fa-tabs button').forEach(function (b) {
			b.addEventListener('click', function () { tab = b.getAttribute('data-tab'); render(); });
		});
		root.querySelectorAll('[data-act]').forEach(function (b) {
			b.addEventListener('click', function () { act(b.getAttribute('data-act')); });
		});
		root.querySelectorAll('.xg-fa-stop').forEach(function (el) {
			el.addEventListener('click', function () { openStop(parseInt(el.getAttribute('data-stop'))); });
		});
	}

	function act(a) {
		if (a === 'startshift') { var km = prompt('Kilometerstand bij start?'); if (km === null) return; api('/shift', { action: 'start', km: parseInt(km) || 0 }).then(load); }
		else if (a === 'endshift') { var km2 = prompt('Kilometerstand bij einde?'); if (km2 === null) return; api('/shift', { action: 'end', km: parseInt(km2) || 0 }).then(load); }
		else if (a === 'logout') { localStorage.removeItem('xg_fleet_code'); code = ''; login(); }
		else if (a === 'scan') { expenseForm(); }
		else if (a === 'ticket') { submitTicket(); }
	}

	function submitTicket() {
		var subj = (root.querySelector('.t-subject') || {}).value || '';
		var msg = (root.querySelector('.t-message') || {}).value || '';
		var out = root.querySelector('.t-msg');
		if (!msg.trim()) { out.textContent = 'Vul een bericht in.'; return; }
		out.textContent = 'Versturen…';
		fetch((window.XGFleet || {}).ticket, {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ name: data.driver, email: data.email, subject: subj || 'Melding chauffeur', message: msg })
		}).then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.ok) { out.textContent = 'Verstuurd (' + (j.reference || '') + ')'; load(); } else { out.textContent = 'Mislukt.'; } })
			.catch(function () { out.textContent = 'Netwerkfout.'; });
	}

	/* ---------- Onkosten met OCR ---------- */
	function expenseForm() {
		var box = root.querySelector('.xg-fa-expform');
		box.hidden = false;
		box.innerHTML = '<label class="xg-fa-file">📷 Foto van de bon<input type="file" accept="image/*" capture="environment"></label>' +
			'<input class="e-amount" type="number" step="0.01" placeholder="Bedrag €">' +
			'<select class="e-cat">' + Object.keys(data.categories).map(function (k) { return '<option value="' + k + '">' + esc(data.categories[k]) + '</option>'; }).join('') + '</select>' +
			'<input class="e-vendor" type="text" placeholder="Leverancier (optioneel)">' +
			'<button class="xg-fa-btn green e-save">Opslaan</button><span class="e-msg"></span>';
		var img = '';
		box.querySelector('input[type=file]').addEventListener('change', function (e) {
			if (!e.target.files[0]) return;
			readFile(e.target.files[0]).then(function (d) {
				img = d;
				if (!data.ai) return;
				box.querySelector('.e-msg').textContent = 'AI leest de bon…';
				api('/ocr', { image: img, mode: 'receipt' }).then(function (o) {
					box.querySelector('.e-msg').textContent = '';
					var s = (o.j && o.j.suggestion) || {};
					if (s.amount) box.querySelector('.e-amount').value = s.amount;
					if (s.category) box.querySelector('.e-cat').value = s.category;
					if (s.vendor) box.querySelector('.e-vendor').value = s.vendor;
				});
			});
		});
		box.querySelector('.e-save').addEventListener('click', function () {
			var amount = parseFloat(box.querySelector('.e-amount').value) || 0;
			if (amount <= 0) { box.querySelector('.e-msg').textContent = 'Vul een bedrag in.'; return; }
			api('/expense', { image: img, amount: amount, category: box.querySelector('.e-cat').value, vendor: box.querySelector('.e-vendor').value }).then(function (o) {
				if (o.ok && o.j && o.j.ok) { load(); } else { box.querySelector('.e-msg').textContent = 'Mislukt.'; }
			});
		});
	}

	/* ---------- Stop-detail: status + KYC ---------- */
	function openStop(i) {
		var st = (data.stops || [])[i]; if (!st) return;
		var appt = st.appointment || 0;
		var nav = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent((st.lat && st.lng) ? (st.lat + ',' + st.lng) : (st.address || ''));
		var ov = document.createElement('div');
		ov.className = 'xg-fa-ov';
		ov.innerHTML = '<div class="xg-fa-ov-box"><button class="xg-fa-ov-x">×</button>' +
			'<h3>' + esc(st.name || '') + '</h3><p>' + esc(st.address || '') + '</p>' +
			'<a class="xg-fa-btn" href="' + nav + '" target="_blank">Navigeer</a>' +
			'<div class="xg-fa-statusbtns"><button data-st="arrived">Aangekomen</button><button data-st="picked_up">Opgehaald</button><button data-st="failed">Niet gelukt</button></div>' +
			'<textarea class="s-note" rows="2" placeholder="Notitie (waar/opmerkingen)"></textarea>' +
			'<h4>KYC vastleggen</h4>' +
			'<label class="xg-fa-file">📷 ID-scan<input type="file" accept="image/*" capture="environment" class="id-file"></label>' +
			'<input class="k-name" type="text" placeholder="Naam (van ID)"><input class="k-idnum" type="text" placeholder="Documentnr."><input class="k-dob" type="text" placeholder="Geboortedatum">' +
			'<input class="k-iban" type="text" placeholder="IBAN (voor uitbetaling)">' +
			'<div class="xg-fa-sig"><span>Handtekening klant:</span><canvas class="sig" width="300" height="120"></canvas><button class="sig-clear" type="button">Wissen</button></div>' +
			'<button class="xg-fa-btn green kyc-save">KYC opslaan</button><span class="kyc-msg"></span></div>';
		document.body.appendChild(ov);
		ov.querySelector('.xg-fa-ov-x').addEventListener('click', function () { ov.remove(); });

		ov.querySelectorAll('.xg-fa-statusbtns button').forEach(function (b) {
			b.addEventListener('click', function () {
				fetch((window.XGFleet || {}).rest.replace('/fleet', '/driver') + '/stop', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: (data.route_token || ''), index: i, status: b.getAttribute('data-st'), note: ov.querySelector('.s-note').value }) })
					.then(function () { b.classList.add('done'); });
			});
		});

		var idImg = '';
		ov.querySelector('.id-file').addEventListener('change', function (e) {
			if (!e.target.files[0]) return;
			readFile(e.target.files[0]).then(function (d) {
				idImg = d;
				if (!data.ai) return;
				ov.querySelector('.kyc-msg').textContent = 'AI leest ID…';
				api('/ocr', { image: idImg, mode: 'id' }).then(function (o) {
					ov.querySelector('.kyc-msg').textContent = '';
					var s = (o.j && o.j.suggestion) || {};
					if (s.name) ov.querySelector('.k-name').value = s.name;
					if (s.doc_number) ov.querySelector('.k-idnum').value = s.doc_number;
					if (s.birthdate) ov.querySelector('.k-dob').value = s.birthdate;
				});
			});
		});

		// Handtekening-canvas.
		var cv = ov.querySelector('.sig'), ctx = cv.getContext('2d'), drawing = false;
		ctx.lineWidth = 2; ctx.lineCap = 'round';
		function pos(e) { var r = cv.getBoundingClientRect(); var t = e.touches ? e.touches[0] : e; return { x: t.clientX - r.left, y: t.clientY - r.top }; }
		function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
		function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
		function stop() { drawing = false; }
		cv.addEventListener('mousedown', start); cv.addEventListener('mousemove', move); document.addEventListener('mouseup', stop);
		cv.addEventListener('touchstart', start); cv.addEventListener('touchmove', move); cv.addEventListener('touchend', stop);
		ov.querySelector('.sig-clear').addEventListener('click', function () { ctx.clearRect(0, 0, cv.width, cv.height); });

		ov.querySelector('.kyc-save').addEventListener('click', function () {
			if (!appt) { ov.querySelector('.kyc-msg').textContent = 'Geen gekoppelde afspraak.'; return; }
			api('/kyc', {
				appointment: appt, id_image: idImg, signature: cv.toDataURL('image/png'),
				iban: ov.querySelector('.k-iban').value, id_name: ov.querySelector('.k-name').value,
				id_number: ov.querySelector('.k-idnum').value, id_birthdate: ov.querySelector('.k-dob').value
			}).then(function (o) { ov.querySelector('.kyc-msg').textContent = (o.ok && o.j && o.j.ok) ? 'Opgeslagen ✓' : 'Mislukt'; });
		});
	}

	if (!code) login(); else load();
})();
