/**
 * XGOUD Driver-PWA – route, live-GPS, status, pickup/delivery-notes.
 * Datenquelle: REST ekinese/v1/driver/*. Routecode = sessietoken.
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-driver');
	if (!root) return;
	var base = root.getAttribute('data-rest');
	var loginBox = root.querySelector('.xg-driver-login');
	var routeBox = root.querySelector('.xg-driver-route');
	var form = root.querySelector('.xg-driver-form');
	var token = new URLSearchParams(location.search).get('token') || '';
	var gpsTimer = null;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function api(path, opts) {
		opts = opts || {};
		var url = base + path + (opts.method === 'GET' || !opts.method ? ('?token=' + encodeURIComponent(token)) : '');
		if (opts.body) opts.body.token = token;
		return fetch(url, {
			method: opts.method || 'GET',
			headers: opts.body ? { 'Content-Type': 'application/json' } : undefined,
			body: opts.body ? JSON.stringify(opts.body) : undefined
		}).then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); });
	}

	if (form) form.addEventListener('submit', function (e) {
		e.preventDefault();
		token = form.querySelector('[name=token]').value.trim();
		loadRoute();
	});

	if (token) loadRoute();

	function loadRoute() {
		api('/route', { method: 'GET' })
			.then(function (d) { renderRoute(d); startGps(); })
			.catch(function () { alert('Ongeldige routecode.'); });
	}

	function startGps() {
		if (!navigator.geolocation || gpsTimer) return;
		function ping() {
			navigator.geolocation.getCurrentPosition(function (p) {
				api('/gps', { method: 'POST', body: { lat: p.coords.latitude, lng: p.coords.longitude } });
			}, function () {}, { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 });
		}
		ping();
		gpsTimer = setInterval(ping, 60000); // elke minuut
	}

	function renderRoute(d) {
		loginBox.hidden = true;
		routeBox.hidden = false;
		var stops = d.stops || [];
		var done = stops.filter(function (s) { return s.status === 'delivered' || s.status === 'picked_up'; }).length;
		var html = '<div class="xg-driver-head"><h2>Route ' + esc(d.date) + '</h2><div class="xg-driver-prog">' + done + ' / ' + stops.length + ' afgerond</div></div>';
		stops.forEach(function (s, i) {
			var maps = s.lat && s.lng ? ('https://www.google.com/maps/dir/?api=1&destination=' + s.lat + ',' + s.lng) : ('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(s.address || ''));
			html += '<div class="xg-driver-stop" data-i="' + i + '">' +
				'<div class="xg-driver-stop-h"><strong>' + (i + 1) + '. ' + esc(s.name) + '</strong><span class="xg-driver-status">' + esc(s.status || 'pending') + '</span></div>' +
				'<div class="xg-driver-addr">' + esc(s.address) + (s.time ? ' · ' + esc(s.time) : '') + (s.leg_km != null ? ' · ' + esc(s.leg_km) + ' km' : '') + '</div>' +
				'<a class="xg-driver-nav" href="' + maps + '" target="_blank" rel="noopener">Navigeren ›</a>' +
				'<textarea class="xg-driver-note" placeholder="Pickup-/delivery-notitie…">' + esc(s.pickup_note || s.delivery_note || '') + '</textarea>' +
				'<div class="xg-driver-actions">' +
				'<button data-act="arrived">Aangekomen</button>' +
				'<button data-act="picked_up">Opgehaald</button>' +
				'<button data-act="delivered">Afgeleverd</button>' +
				'</div></div>';
		});
		routeBox.innerHTML = html;
		routeBox.querySelectorAll('.xg-driver-stop').forEach(function (el) {
			var i = parseInt(el.getAttribute('data-i'), 10);
			el.querySelectorAll('[data-act]').forEach(function (b) {
				b.addEventListener('click', function () {
					var note = el.querySelector('.xg-driver-note').value.trim();
					api('/stop', { method: 'POST', body: { index: i, status: b.getAttribute('data-act'), note: note } })
						.then(function (r) { renderRoute({ date: d.date, stops: r.stops }); })
						.catch(function () { alert('Opslaan mislukt.'); });
				});
			});
		});
	}
})();
