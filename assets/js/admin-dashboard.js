/**
 * XGOUD admin-dashboard — Claude AI vraag + agent runs (admin-ajax).
 */
(function () {
	// Live chauffeurskaart (Leaflet).
	var mapEl = document.getElementById('xg-fleet-map');
	if (mapEl && window.L) {
		var markers = [];
		try { markers = JSON.parse(mapEl.getAttribute('data-markers') || '[]'); } catch (e) {}
		var map = L.map(mapEl).setView([52.13, 5.29], 7); // NL
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
		var pts = [];
		markers.forEach(function (m) {
			if (!m.lat) return;
			var driver = m.t === 'driver';
			var mk = L.circleMarker([m.lat, m.lng], {
				radius: driver ? 9 : 6, color: driver ? '#AE1E1E' : '#646970',
				fillColor: driver ? '#AE1E1E' : '#bdbdbd', fillOpacity: driver ? 0.9 : 0.7, weight: 2
			}).addTo(map);
			mk.bindPopup('<strong>' + (m.name || '') + '</strong>' + (m.time ? '<br>' + m.time : ''));
			pts.push([m.lat, m.lng]);
		});
		if (pts.length) { map.fitBounds(pts, { padding: [30, 30], maxZoom: 13 }); }
	}

	var root = document.getElementById('xg-ai');
	if (!root) return;
	var nonce = root.getAttribute('data-nonce');
	var out = document.getElementById('xg-ai-out');

	function run(params, btn) {
		var label = btn ? btn.textContent : '';
		if (btn) { btn.disabled = true; }
		out.style.display = 'block';
		out.textContent = 'Claude denkt na…';
		var body = new URLSearchParams();
		body.append('action', 'xg_admin_ai');
		body.append('nonce', nonce);
		Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
		fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (btn) { btn.disabled = false; }
				if (j && j.success) { out.textContent = j.data.reply; }
				else { out.textContent = 'Fout: ' + ((j && j.data && j.data.message) || 'onbekend'); }
			})
			.catch(function () { if (btn) { btn.disabled = false; } out.textContent = 'Netwerkfout.'; });
	}

	var ask = document.getElementById('xg-ai-ask');
	if (ask) {
		ask.addEventListener('click', function (e) {
			e.preventDefault();
			var q = (document.getElementById('xg-ai-q') || {}).value || '';
			if (!q.trim()) { out.style.display = 'block'; out.textContent = 'Typ eerst een vraag.'; return; }
			run({ q: q }, ask);
		});
	}
	root.querySelectorAll('.xg-ai-run').forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.preventDefault();
			run({ agent: b.getAttribute('data-agent') }, b);
		});
	});
})();
