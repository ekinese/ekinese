/**
 * XGOUD survey-builder (backend). Bouwt het vragen-JSON dat in het verborgen
 * textarea #xg-survey-questions wordt opgeslagen.
 */
(function () {
	'use strict';
	var mount = document.getElementById('xg-survey-builder');
	var store = document.getElementById('xg-survey-questions');
	if (!mount || !store) return;

	var questions = [];
	try { questions = JSON.parse(mount.getAttribute('data-questions') || '[]') || []; } catch (e) { questions = []; }
	if (!Array.isArray(questions)) questions = [];

	var TYPES = { single: 'Enkele keuze', multi: 'Meerkeuze', rating: 'Rating (1–5)', text: 'Tekst' };

	function sync() {
		store.value = JSON.stringify(questions);
	}

	function render() {
		mount.innerHTML = '';
		questions.forEach(function (q, idx) {
			var card = document.createElement('div');
			card.style.cssText = 'background:#fff;border:1px solid #dcdcde;padding:12px 14px;margin:0 0 10px';
			var needsOpts = q.type === 'single' || q.type === 'multi';
			card.innerHTML =
				'<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">' +
				'<strong>Vraag ' + (idx + 1) + '</strong>' +
				'<button type="button" class="button-link-delete" data-act="del" data-i="' + idx + '" style="margin-left:auto">Verwijderen</button>' +
				'<button type="button" class="button" data-act="up" data-i="' + idx + '">↑</button>' +
				'<button type="button" class="button" data-act="down" data-i="' + idx + '">↓</button></div>' +
				'<p><input type="text" data-f="text" data-i="' + idx + '" value="' + attr(q.text) + '" placeholder="Vraagtekst" style="width:100%"></p>' +
				'<p><label>Type <select data-f="type" data-i="' + idx + '">' +
				Object.keys(TYPES).map(function (t) { return '<option value="' + t + '"' + (q.type === t ? ' selected' : '') + '>' + TYPES[t] + '</option>'; }).join('') +
				'</select></label> &nbsp; <label><input type="checkbox" data-f="required" data-i="' + idx + '"' + (q.required ? ' checked' : '') + '> Verplicht</label></p>' +
				(needsOpts ? '<p><label>Opties (één per regel)<br><textarea data-f="options" data-i="' + idx + '" rows="3" style="width:100%">' + text((q.options || []).join('\n')) + '</textarea></label></p>' : '');
			mount.appendChild(card);
		});
		var add = document.createElement('button');
		add.type = 'button';
		add.className = 'button button-primary';
		add.textContent = '+ Vraag toevoegen';
		add.addEventListener('click', function () {
			questions.push({ text: '', type: 'single', required: true, options: ['Ja', 'Nee'] });
			sync(); render();
		});
		mount.appendChild(add);
	}

	function attr(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;'); }
	function text(s) { return String(s == null ? '' : s).replace(/</g, '&lt;'); }

	mount.addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]'); if (!b) return;
		var i = parseInt(b.getAttribute('data-i'), 10);
		var act = b.getAttribute('data-act');
		if (act === 'del') { questions.splice(i, 1); }
		else if (act === 'up' && i > 0) { var t = questions[i - 1]; questions[i - 1] = questions[i]; questions[i] = t; }
		else if (act === 'down' && i < questions.length - 1) { var t2 = questions[i + 1]; questions[i + 1] = questions[i]; questions[i] = t2; }
		sync(); render();
	});
	mount.addEventListener('input', function (e) {
		var el = e.target.closest('[data-f]'); if (!el) return;
		var i = parseInt(el.getAttribute('data-i'), 10);
		var f = el.getAttribute('data-f');
		if (!questions[i]) return;
		if (f === 'required') questions[i].required = el.checked;
		else if (f === 'options') questions[i].options = el.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
		else if (f === 'type') { questions[i].type = el.value; sync(); render(); return; }
		else questions[i][f] = el.value;
		sync();
	});

	render();
	sync();
})();
