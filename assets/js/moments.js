/**
 * XGOUD Momenten — publieke wall (ekinese/moments) + inzendformulier
 * (ekinese/moment-form). Geen onderlinge communicatie: alleen tonen + anonieme ❤.
 */
(function () {
	'use strict';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	/* ---------------- Wall ---------------- */
	var wall = document.querySelector('.xg-moments');
	if (wall) {
		var grid = wall.querySelector('.xg-moments-grid');
		var more = wall.querySelector('.xg-moments-more');
		var rest = wall.getAttribute('data-rest');
		var likeUrl = wall.getAttribute('data-like');
		var per = parseInt(wall.getAttribute('data-limit'), 10) || 8;
		var page = 0, pages = 1;
		var liked = {};
		try { liked = JSON.parse(localStorage.getItem('xg_moment_liked') || '{}'); } catch (e) {}

		function card(m) {
			var badge = m.type ? '<span class="xg-moment-type">' + esc(m.type) + '</span>' : '';
			var who = esc(m.name) + (m.city ? ' · ' + esc(m.city) : '');
			var isLiked = liked[m.id] ? ' is-liked' : '';
			return '<figure class="xg-moment" data-id="' + m.id + '">' +
				'<div class="xg-moment-img" style="background-image:url(' + esc(m.photo) + ')" role="img" aria-label="' + esc(m.text) + '">' + badge +
				'<button type="button" class="xg-moment-like' + isLiked + '" aria-label="Vind ik leuk"><span class="xg-moment-heart">♥</span><span class="xg-moment-likes">' + (m.likes || 0) + '</span></button></div>' +
				'<figcaption><p>' + esc(m.text) + '</p><span class="xg-moment-who">' + who + '</span></figcaption>' +
				'</figure>';
		}

		function load() {
			if (page >= pages) return;
			page++;
			fetch(rest + '?page=' + page + '&per=' + per)
				.then(function (r) { return r.json(); })
				.then(function (d) {
					pages = d.pages || 1;
					(d.moments || []).forEach(function (m) {
						grid.insertAdjacentHTML('beforeend', card(m));
					});
					if (page < pages) {
						more.innerHTML = '<button type="button" class="xg-moments-load">Meer momenten</button>';
					} else {
						more.innerHTML = '';
					}
					if (!grid.children.length) {
						grid.innerHTML = '<p class="xg-moments-empty">Wees de eerste die een moment deelt!</p>';
					}
				})
				.catch(function () {});
		}

		grid.addEventListener('click', function (e) {
			var btn = e.target.closest('.xg-moment-like');
			if (!btn) return;
			var fig = btn.closest('.xg-moment');
			var id = fig && fig.getAttribute('data-id');
			if (!id || liked[id]) return;
			liked[id] = 1;
			try { localStorage.setItem('xg_moment_liked', JSON.stringify(liked)); } catch (e2) {}
			btn.classList.add('is-liked');
			var span = btn.querySelector('.xg-moment-likes');
			fetch(likeUrl, {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id: id })
			}).then(function (r) { return r.json(); })
				.then(function (d) { if (d && d.likes != null && span) span.textContent = d.likes; })
				.catch(function () {});
		});
		more.addEventListener('click', function (e) {
			if (e.target.closest('.xg-moments-load')) load();
		});
		load();
	}

	/* ---------------- Inzendformulier ---------------- */
	var fwrap = document.querySelector('.xg-moment-form');
	if (fwrap) {
		var form = fwrap.querySelector('.xg-mf-form');
		var msg = fwrap.querySelector('.xg-mf-msg');
		var endpoint = fwrap.getAttribute('data-rest');
		var token = new URLSearchParams(location.search).get('token');

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = form.querySelector('button[type=submit]');
			var fd = new FormData(form);
			if (token) fd.append('token', token);
			btn.disabled = true;
			msg.textContent = 'Bezig…';
			msg.className = 'xg-mf-msg';
			fetch(endpoint, { method: 'POST', body: fd })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					btn.disabled = false;
					if (o.ok && o.j && o.j.ok) {
						msg.textContent = o.j.message || 'Bedankt! Je moment wordt beoordeeld.';
						msg.className = 'xg-mf-msg is-ok';
						form.reset();
					} else {
						msg.textContent = (o.j && o.j.message) || 'Mislukt. Controleer de velden.';
						msg.className = 'xg-mf-msg is-err';
					}
				})
				.catch(function () {
					btn.disabled = false;
					msg.textContent = 'Netwerkfout. Probeer het later opnieuw.';
					msg.className = 'xg-mf-msg is-err';
				});
		});
	}
})();
