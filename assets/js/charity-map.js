/**
 * XGOUD Charity-kaart "Waar XGOUD helpt" – Leaflet-markers + count-up teller.
 * Datenquelle: window.XG_CHARITY_MAP (via wp_localize_script).
 */
(function () {
	'use strict';
	// Animatie van de spendenteller.
	var counter = document.querySelector('.xg-charity-counter');
	if (counter) {
		var to = parseFloat(counter.getAttribute('data-to')) || 0;
		var started = false;
		function fmt(n) { return '€ ' + n.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
		function run() {
			if (started) return; started = true;
			var t0 = performance.now(), dur = 1600;
			function step(t) {
				var p = Math.min(1, (t - t0) / dur);
				var e = 1 - Math.pow(1 - p, 3);
				counter.textContent = fmt(to * e);
				if (p < 1) requestAnimationFrame(step);
			}
			requestAnimationFrame(step);
		}
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (en) { en.forEach(function (e) { if (e.isIntersecting) run(); }); }, { threshold: .4 });
			io.observe(counter);
		} else { run(); }
	}

	// Leaflet-kaart met projectmarkers.
	var el = document.getElementById('xg-charity-map');
	if (!el || !window.L || !window.XG_CHARITY_MAP) return;
	var pts = window.XG_CHARITY_MAP;
	var map = L.map(el, { scrollWheelZoom: false, attributionControl: false }).setView([52.1, 5.1], 7);
	L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 18 }).addTo(map);
	var icon = L.divIcon({ className: 'xg-charity-pin', html: '<span>♥</span>', iconSize: [26, 26], iconAnchor: [13, 13] });
	var group = [];
	pts.forEach(function (p) {
		if (!p.lat || !p.lng) return;
		var m = L.marker([p.lat, p.lng], { icon: icon }).addTo(map);
		m.bindPopup('<strong>' + p.name + '</strong><br>' + (p.cat || '') + (p.city ? ' · ' + p.city : ''));
		group.push([p.lat, p.lng]);
	});
	if (group.length) {
		try { map.fitBounds(group, { padding: [40, 40], maxZoom: 9 }); } catch (e) {}
	}
})();
