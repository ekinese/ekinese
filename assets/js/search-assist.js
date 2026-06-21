/**
 * XGOUD Such-Assistent. Hängt sich an das Header-Such-Overlay (#xgSearchInput)
 * und gibt – statt blinder Suche – eine Bot-Antwort + gezielte Vorschläge.
 * Nutzt dieselbe Bot-Engine wie der Chat (REST /assist), mit lokalem Fallback.
 */
(function () {
	'use strict';

	var CFG = window.XG_CHAT || {};
	var input = document.getElementById('xgSearchInput');
	var results = document.getElementById('xgSearchResults');
	if (!input || !results) return;

	var box = document.createElement('div');
	box.className = 'xg-sa';
	box.style.display = 'none';
	results.parentNode.insertBefore(box, results);

	function render(data, q) {
		var sugg = (data.suggestions && data.suggestions.length ? data.suggestions : localSuggest(q));
		var html =
			'<div class="xg-sa-head"><span class="xg-sa-ava">XG</span><div><strong>XGOUD assistent</strong><div class="xg-sa-reply">' + escapeHtml(data.reply) + '</div></div></div>';
		if (sugg.length) {
			html += '<div class="xg-sa-sugg">';
			sugg.forEach(function (s) { html += '<a href="' + s.url + '">' + escapeHtml(s.label) + ' →</a>'; });
			html += '</div>';
		}
		box.innerHTML = html;
		box.style.display = 'block';
	}

	function ask(q) {
		if (q.length < 2) { box.style.display = 'none'; return; }
		if (CFG.rest_assist && window.fetch) {
			fetch(CFG.rest_assist, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ q: q }) })
				.then(function (r) { return r.json(); })
				.then(function (j) { render(j || {}, q); })
				.catch(function () { render(localReply(q), q); });
		} else {
			render(localReply(q), q);
		}
	}

	var timer;
	input.addEventListener('input', function () {
		clearTimeout(timer);
		var q = input.value.trim();
		timer = setTimeout(function () { ask(q); }, 300);
	});

	// Lokaler Fallback (Demo / Server offline)
	function localSuggest(t) {
		t = (t || '').toLowerCase();
		var map = [
			['goud', 'Goud verkopen', '/edelmetalen/goud-verkopen/'],
			['zilver', 'Zilver verkopen', '/edelmetalen/zilver-verkopen/'],
			['diamant', 'Diamanten verkopen', '/edelstenen/diamanten-verkopen/'],
			['rolex', 'Rolex verkopen', '/horloges/rolex-verkopen/'],
			['horloge', 'Horloge verkopen', '/horloges/'],
			['kantoor', 'Alle kantoren', '/kantoren/'],
			['afspraak', 'Afspraak maken', '/afspraak/']
		];
		var out = [];
		map.forEach(function (m) { if (t.indexOf(m[0]) !== -1) out.push({ label: m[1], url: m[2] }); });
		return out;
	}
	function localReply(q) {
		var s = localSuggest(q);
		var reply = s.length ? 'Ik denk dat dit u verder helpt:' : 'Vertel me kort wat u wilt verkopen (goud, diamant, horloge) — dan wijs ik u direct de juiste pagina.';
		return { reply: reply, suggestions: s };
	}
	function escapeHtml(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
})();
