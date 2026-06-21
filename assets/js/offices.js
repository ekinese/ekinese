/**
 * XGOUD Kantoren – interaktive Karte (Leaflet) + Liste + Einzelseite.
 *
 * Datenquelle: window.XG_OFFICES (via wp_localize_script, siehe inc/offices.php).
 *   [{ id, name, slug, url, street, postcode, city, lat, lng, phone, email,
 *      hours:{ma,di,..}, is_hq, zone }]
 *
 * Übersicht: Element #xg-offices-map + .xg-offices-list (JS füllt beide).
 * Einzelseite: Element [data-xg-office="<slug|id>"] (JS rendert Detail + Karte).
 */
(function () {
	'use strict';

	var DAYS = [['ma', 'Maandag'], ['di', 'Dinsdag'], ['wo', 'Woensdag'], ['do', 'Donderdag'], ['vr', 'Vrijdag'], ['za', 'Zaterdag'], ['zo', 'Zondag']];
	var OFFICES = window.XG_OFFICES || [];

	function el(t, c, h) { var e = document.createElement(t); if (c) e.className = c; if (h != null) e.innerHTML = h; return e; }
	function openHoursToday(hours) {
		var idx = (new Date().getDay() + 6) % 7; // 0=Maandag
		var key = DAYS[idx][0];
		var v = hours && hours[key];
		return v ? { label: DAYS[idx][1], value: v } : { label: DAYS[idx][1], value: null };
	}
	function routeUrl(o) {
		return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent((o.street || '') + ', ' + (o.postcode || '') + ' ' + (o.city || ''));
	}
	function embedUrl(o) {
		return 'https://www.google.com/maps?q=' + encodeURIComponent((o.street || '') + ', ' + (o.postcode || '') + ' ' + (o.city || '')) + '&output=embed';
	}
	function pinIcon(isHq) {
		return L.divIcon({
			className: '',
			html: '<div class="xg-pin' + (isHq ? ' hq' : '') + '"><span>' + (isHq ? '★' : '') + '</span></div>',
			iconSize: isHq ? [34, 34] : [28, 28],
			iconAnchor: isHq ? [17, 34] : [14, 28],
			popupAnchor: [0, isHq ? -32 : -26]
		});
	}

	/* =========================================================
	   ÜBERSICHT
	========================================================= */
	function initOverview() {
		var mapEl = document.getElementById('xg-offices-map');
		var listEl = document.querySelector('.xg-offices-list');
		if (!mapEl || !window.L) return;

		var map = L.map(mapEl, { scrollWheelZoom: false }).setView([52.15, 5.3], 7);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19, attribution: '© OpenStreetMap'
		}).addTo(map);

		var markers = {}, cards = {};
		var bounds = [];

		// HQ zuerst sortieren
		var list = OFFICES.slice().sort(function (a, b) { return (b.is_hq ? 1 : 0) - (a.is_hq ? 1 : 0); });

		list.forEach(function (o) {
			if (!o.lat || !o.lng) return;
			bounds.push([o.lat, o.lng]);

			var m = L.marker([o.lat, o.lng], { icon: pinIcon(o.is_hq) }).addTo(map);
			var today = openHoursToday(o.hours);
			m.bindPopup(
				'<div class="xg-popup"><h4>' + o.name + (o.is_hq ? ' ★' : '') + '</h4>' +
				'<p>' + (o.street || '') + '<br>' + (o.postcode || '') + ' ' + (o.city || '') + '</p>' +
				'<a href="' + routeUrl(o) + '" target="_blank" rel="noopener">Route plannen →</a>' +
				(o.url ? ' &nbsp; <a href="' + o.url + '">Bekijk kantoor</a>' : '') +
				'</div>'
			);
			markers[o.id] = m;

			// Karte (Liste)
			if (listEl) {
				var card = el('button', 'xg-office-card' + (o.is_hq ? ' is-hq' : ''));
				card.type = 'button';
				card.innerHTML =
					'<div class="xg-office-card-top"><h3>' + o.name + '</h3>' +
					(o.is_hq ? '<span class="xg-hq-tag">Hoofdkantoor</span>' : '') + '</div>' +
					'<p class="xg-office-addr">' + (o.street || '') + ', ' + (o.postcode || '') + ' ' + (o.city || '') + '</p>' +
					'<div class="xg-office-hours">Vandaag (' + today.label + '): ' + (today.value ? '<b>' + today.value + '</b>' : 'Gesloten') + '</div>' +
					'<div class="xg-office-card-actions">' +
						(o.url ? '<a class="xg-office-btn primary" href="' + o.url + '">Bekijk kantoor</a>' : '') +
						'<a class="xg-office-btn" href="' + routeUrl(o) + '" target="_blank" rel="noopener">Route</a>' +
					'</div>';
				card.addEventListener('click', function (e) {
					if (e.target.closest('a')) return; // Buttons nicht abfangen
					map.setView([o.lat, o.lng], 13, { animate: true });
					m.openPopup();
					Object.keys(cards).forEach(function (k) { cards[k].classList.remove('active'); });
					card.classList.add('active');
				});
				cards[o.id] = card;
				listEl.appendChild(card);
			}
		});

		if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });

		// Suche/Filter
		var search = document.querySelector('.xg-offices-search input');
		var countEl = document.querySelector('.xg-offices-count');
		function applyFilter() {
			var q = (search && search.value || '').toLowerCase().trim();
			var shown = 0;
			list.forEach(function (o) {
				var match = !q || (o.city + ' ' + o.name + ' ' + (o.postcode || '')).toLowerCase().indexOf(q) !== -1;
				if (cards[o.id]) cards[o.id].style.display = match ? '' : 'none';
				if (markers[o.id]) { if (match) markers[o.id].addTo(map); else map.removeLayer(markers[o.id]); }
				if (match) shown++;
			});
			if (countEl) countEl.textContent = shown + ' van ' + list.length + ' kantoren';
		}
		if (search) search.addEventListener('input', applyFilter);
		applyFilter();
	}

	/* =========================================================
	   EINZELSEITE
	========================================================= */
	function initSingle() {
		var host = document.querySelector('[data-xg-office]');
		if (!host || !window.L) return;
		var ref = host.getAttribute('data-xg-office');
		var o = OFFICES.filter(function (x) { return String(x.id) === ref || x.slug === ref; })[0] || OFFICES[0];
		if (!o) return;

		// Detail-Infos
		var info = host.querySelector('.xg-office-info');
		if (info) {
			var rows =
				'<div class="xg-office-block"><h3>Adres</h3>' +
				'<div class="xg-office-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-7.2 7-12a7 7 0 1 0-14 0c0 4.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.4"/></svg>' +
				'<span>' + (o.street || '') + '<br>' + (o.postcode || '') + ' ' + (o.city || '') + '</span></div>' +
				(o.phone ? '<div class="xg-office-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2z"/></svg><span><a href="tel:' + o.phone.replace(/\s/g, '') + '">' + o.phone + '</a></span></div>' : '') +
				(o.email ? '<div class="xg-office-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18v12H3z"/><path d="M3 7l9 6 9-6"/></svg><span><a href="mailto:' + o.email + '">' + o.email + '</a></span></div>' : '') +
				'</div>';

			// Öffnungszeiten
			var todayIdx = (new Date().getDay() + 6) % 7;
			var hoursRows = DAYS.map(function (d, i) {
				var v = o.hours && o.hours[d[0]];
				var cls = (i === todayIdx ? ' class="today"' : '') ;
				return '<tr' + (v ? cls : ' class="closed"') + '><td>' + d[1] + '</td><td>' + (v || 'Gesloten') + '</td></tr>';
			}).join('');
			rows += '<div class="xg-office-block"><h3>Openingstijden</h3><table class="xg-hours-table">' + hoursRows + '</table></div>';

			rows += '<div class="xg-office-cta">' +
				'<a class="xg-office-btn primary" href="' + routeUrl(o) + '" target="_blank" rel="noopener">Route plannen</a>' +
				'<a class="xg-office-btn" href="/afspraak/?kantoor=' + encodeURIComponent(o.slug || o.id) + '">Afspraak bij dit kantoor</a>' +
				'</div>';
			info.innerHTML = rows;
		}

		// Karte + Google-Embed
		var mapCol = host.querySelector('.xg-office-mapcol');
		if (mapCol) {
			var mapDiv = mapCol.querySelector('.xg-office-map');
			if (mapDiv && o.lat && o.lng) {
				var map = L.map(mapDiv, { scrollWheelZoom: false }).setView([o.lat, o.lng], 14);
				L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
				L.marker([o.lat, o.lng], { icon: pinIcon(o.is_hq) }).addTo(map);
			}
			var embed = mapCol.querySelector('.xg-office-embed');
			if (embed) embed.src = embedUrl(o);
		}
	}

	function boot() { initOverview(); initSingle(); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
