/**
 * XGOUD Surveys — front-end vragenlijst (blok ekinese/survey / single).
 */
(function () {
	'use strict';
	var root = document.querySelector('.xg-survey');
	if (!root) return;

	var box = root.querySelector('.xg-survey-box');
	var rest = root.getAttribute('data-rest');
	var submitUrl = root.getAttribute('data-submit');
	var shareUrl = root.getAttribute('data-share');
	var pageUrl = root.getAttribute('data-url');
	var id = root.getAttribute('data-id');
	var token = new URLSearchParams(location.search).get('token');
	var def = null;

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	fetch(rest).then(function (r) { return r.json(); }).then(render).catch(function () {
		box.textContent = 'Vragenlijst kon niet geladen worden.';
	});

	function questionHtml(q) {
		var req = q.required ? ' <span class="xg-sv-req">*</span>' : '';
		var body = '';
		if (q.type === 'single' || q.type === 'multi') {
			var inp = q.type === 'single' ? 'radio' : 'checkbox';
			body = q.options.map(function (o, i) {
				return '<label class="xg-sv-opt"><input type="' + inp + '" name="' + q.id + '" value="' + esc(o) + '"> <span>' + esc(o) + '</span></label>';
			}).join('');
		} else if (q.type === 'rating') {
			body = '<div class="xg-sv-rating" data-q="' + q.id + '">' +
				[1, 2, 3, 4, 5].map(function (n) { return '<button type="button" class="xg-sv-star" data-v="' + n + '" aria-label="' + n + '">★</button>'; }).join('') +
				'<input type="hidden" name="' + q.id + '" value=""></div>';
		} else {
			body = '<textarea name="' + q.id + '" rows="3" placeholder="Je antwoord…"></textarea>';
		}
		return '<div class="xg-sv-q" data-type="' + q.type + '" data-id="' + q.id + '"><h3>' + esc(q.text) + req + '</h3>' + body + '</div>';
	}

	function render(d) {
		def = d;
		if (!d || !d.questions) { box.textContent = 'Onbekende vragenlijst.'; return; }
		if (!d.open) {
			box.innerHTML = '<div class="xg-sv-closed"><h2>' + esc(d.title) + '</h2><p>Deze vragenlijst is gesloten. Bedankt voor je interesse!</p></div>';
			return;
		}
		var emailField = token ? '' : '<div class="xg-sv-q"><h3>Je e-mailadres <span class="xg-sv-req">*</span></h3><input type="email" name="__email" placeholder="voor je spaarpunten" required></div>';
		box.innerHTML = '<div class="xg-sv-head"><h2>' + esc(d.title) + '</h2>' +
			(d.intro ? '<p>' + esc(d.intro) + '</p>' : '') +
			'<p class="xg-sv-points">Vul in en ontvang <strong>' + (d.points || 0) + ' spaarpunten</strong>.</p></div>' +
			'<form class="xg-sv-form">' +
			'<input type="text" name="website" class="xg-hp" tabindex="-1" autocomplete="off" aria-hidden="true">' +
			d.questions.map(questionHtml).join('') + emailField +
			'<button type="submit" class="xg-sv-submit">Verstuur</button><span class="xg-sv-msg" role="status"></span></form>';
		bind();
	}

	function bind() {
		var form = box.querySelector('.xg-sv-form');
		// Sterren-rating.
		box.querySelectorAll('.xg-sv-rating').forEach(function (wrap) {
			wrap.addEventListener('click', function (e) {
				var b = e.target.closest('.xg-sv-star'); if (!b) return;
				var v = b.getAttribute('data-v');
				wrap.querySelector('input').value = v;
				wrap.querySelectorAll('.xg-sv-star').forEach(function (s) {
					s.classList.toggle('on', parseInt(s.getAttribute('data-v'), 10) <= parseInt(v, 10));
				});
			});
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('.xg-sv-msg');
			var btn = form.querySelector('.xg-sv-submit');
			var answers = {};
			def.questions.forEach(function (q) {
				if (q.type === 'multi') {
					answers[q.id] = [].slice.call(form.querySelectorAll('input[name="' + q.id + '"]:checked')).map(function (i) { return i.value; });
				} else if (q.type === 'single') {
					var c = form.querySelector('input[name="' + q.id + '"]:checked');
					answers[q.id] = c ? c.value : '';
				} else {
					var el = form.querySelector('[name="' + q.id + '"]');
					answers[q.id] = el ? el.value : '';
				}
			});
			var body = { survey: id, answers: answers };
			if (token) body.token = token;
			else { var em = form.querySelector('[name="__email"]'); body.email = em ? em.value : ''; }
			if (form.website && form.website.value) return;
			btn.disabled = true; msg.textContent = 'Bezig…'; msg.className = 'xg-sv-msg';
			fetch(submitUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (o) {
					btn.disabled = false;
					if (o.ok && o.j && o.j.ok) {
						box.innerHTML = '<div class="xg-sv-done"><h2>Bedankt! 🎉</h2><p>' + esc(o.j.message || '') + '</p>' + shareBlock(body.email) + '</div>';
						bindShare(body.email);
					} else {
						msg.textContent = (o.j && o.j.message) || 'Versturen mislukt.';
						msg.className = 'xg-sv-msg is-err';
					}
				})
				.catch(function () { btn.disabled = false; msg.textContent = 'Netwerkfout.'; msg.className = 'xg-sv-msg is-err'; });
		});
	}

	function shareBlock() {
		var u = encodeURIComponent(pageUrl);
		return '<div class="xg-sv-share"><p>Deel deze vragenlijst:</p>' +
			'<a class="xg-sv-sh" data-p="whatsapp" target="_blank" rel="noopener" href="https://wa.me/?text=' + u + '">WhatsApp</a>' +
			'<a class="xg-sv-sh" data-p="facebook" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' + u + '">Facebook</a>' +
			'<a class="xg-sv-sh" data-p="x" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=' + u + '">X</a>' +
			'<a class="xg-sv-sh" data-p="linkedin" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=' + u + '">LinkedIn</a></div>';
	}
	function bindShare(email) {
		box.querySelectorAll('.xg-sv-sh').forEach(function (a) {
			a.addEventListener('click', function () {
				if (!email) return;
				fetch(shareUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: email, platform: a.getAttribute('data-p') }) }).catch(function () {});
			});
		});
	}
})();
