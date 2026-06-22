/**
 * XGOUD Chat-Widget. Bot + Foto/Video-Upload. Spricht mit der REST-API
 * (inc/chat.php); ohne Server (Demo) greift ein lokaler Fallback-Bot.
 * Datenquelle: window.XG_CHAT { rest_message, rest_upload, open }.
 */
(function () {
	'use strict';

	var CFG = window.XG_CHAT || { open: false };
	var LS = 'xg-chat-session';
	var session = {};
	try { session = JSON.parse(localStorage.getItem(LS)) || {}; } catch (e) {}

	var pending = []; // hochzuladende Anhänge (urls nach Upload)

	function el(t, c, h) { var e = document.createElement(t); if (c) e.className = c; if (h != null) e.innerHTML = h; return e; }
	function save() { try { localStorage.setItem(LS, JSON.stringify(session)); } catch (e) {} }

	var ICON = {
		chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.6L3 21l1.9-5.7A8.5 8.5 0 1 1 21 11.5z"/></svg>',
		send: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 20l18-8L3 4v6l12 2-12 2z"/></svg>',
		clip: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.5l-8.5 8.5a5 5 0 0 1-7-7l9-9a3.3 3.3 0 0 1 4.7 4.7l-9 9a1.6 1.6 0 0 1-2.3-2.3l8.5-8.5"/></svg>'
	};

	var panel, msgs, quickBar, previewBar, fab;

	function build() {
		fab = el('button', 'xg-chat-fab', ICON.chat + '<span class="xg-chat-badge"></span>');
		fab.setAttribute('aria-label', 'Chat openen');
		document.body.appendChild(fab);

		panel = el('div', 'xg-chat-panel');
		panel.innerHTML =
			'<div class="xg-chat-head ' + (CFG.open ? '' : 'closed') + '">' +
				'<div class="xg-chat-ava">XG</div>' +
				'<div><h4>XGOUD assistent</h4><div class="xg-chat-status"><span class="dot"></span>' + (CFG.open ? 'Online — we reageren direct' : 'Offline — laat een bericht achter') + '</div></div>' +
				'<button class="xg-chat-close" aria-label="Sluiten">×</button>' +
			'</div>' +
			'<div class="xg-chat-msgs"></div>' +
			'<div class="xg-chat-quick"></div>' +
			'<div class="xg-chat-preview" style="display:none"></div>' +
			'<div class="xg-chat-input">' +
				'<button class="xg-chat-attach" aria-label="Foto of video">' + ICON.clip + '</button>' +
				'<input type="file" accept="image/*,video/*" hidden>' +
				'<input type="text" placeholder="Typ uw bericht…">' +
				'<button class="xg-chat-send" aria-label="Versturen">' + ICON.send + '</button>' +
			'</div>';
		document.body.appendChild(panel);

		msgs = panel.querySelector('.xg-chat-msgs');
		quickBar = panel.querySelector('.xg-chat-quick');
		previewBar = panel.querySelector('.xg-chat-preview');
		var input = panel.querySelector('input[type=text]');
		var fileInput = panel.querySelector('input[type=file]');

		fab.addEventListener('click', open);
		panel.querySelector('.xg-chat-close').addEventListener('click', close);
		panel.querySelector('.xg-chat-send').addEventListener('click', function () { sendText(input); });
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendText(input); });
		panel.querySelector('.xg-chat-attach').addEventListener('click', function () { fileInput.click(); });
		fileInput.addEventListener('change', function () { handleFile(fileInput.files[0]); fileInput.value = ''; });
	}

	function open() {
		panel.classList.add('open'); fab.style.display = 'none';
		if (!msgs.childElementCount) {
			botSay(CFG.open
				? 'Hallo! Welkom bij XGOUD. Waarmee kan ik u helpen — goud, diamanten, horloges of een afspraak?'
				: 'Hallo! Wij zijn nu gesloten, maar ik help u graag. Stel uw vraag of laat uw e-mail achter, dan reageren wij snel.',
				['Goud verkopen', 'Diamanten verkopen', 'Afspraak maken', 'Alle kantoren']);
		}
	}
	function close() { panel.classList.remove('open'); fab.style.display = 'flex'; }

	function addMsg(who, text, attachments) {
		var m = el('div', 'xg-chat-msg ' + who);
		if (text) m.appendChild(document.createTextNode(text));
		(attachments || []).forEach(function (a) {
			var node = /\.(mp4|mov|webm)$/i.test(a) ? el('video') : el('img');
			node.src = a; if (node.tagName === 'VIDEO') node.controls = true;
			m.appendChild(node);
		});
		msgs.appendChild(m); msgs.scrollTop = msgs.scrollHeight;
	}
	function botSay(text, quick) { addMsg('bot', text); renderQuick(quick || []); }
	function renderQuick(items) {
		quickBar.innerHTML = '';
		items.forEach(function (q) {
			var b = el('button', 'xg-chat-qbtn', q);
			b.addEventListener('click', function () { quickBar.innerHTML = ''; userSend(q); });
			quickBar.appendChild(b);
		});
	}
	function typing(on) {
		var t = msgs.querySelector('.xg-chat-typing');
		if (on && !t) { msgs.appendChild(el('div', 'xg-chat-typing', '<span></span><span></span><span></span>')); msgs.scrollTop = msgs.scrollHeight; }
		if (!on && t) t.remove();
	}

	function sendText(input) {
		var v = input.value.trim();
		if (!v && !pending.length) return;
		input.value = '';
		userSend(v);
	}

	function userSend(text) {
		var atts = pending.slice(); clearPreview();
		addMsg('user', text, atts);
		typing(true);
		var lang = (document.documentElement.getAttribute('lang') || 'nl').slice(0, 2).toLowerCase();
		var body = { text: text, attachments: atts, id: session.id || 0, token: session.token || '', lang: lang };
		fetch(CFG.rest_message, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				typing(false);
				if (j && j.id) { session.id = j.id; session.token = j.token; save(); }
				botSay((j && j.reply) || localBot(text).reply, (j && j.quick) || localBot(text).quick);
			})
			.catch(function () { typing(false); var lb = localBot(text); botSay(lb.reply, lb.quick); });
	}

	// Foto/Video
	function handleFile(file) {
		if (!file) return;
		var url = URL.createObjectURL(file);
		var isVid = /^video\//.test(file.type);
		var item = el('div', 'xg-chat-preview-item');
		item.innerHTML = (isVid ? '<video src="' + url + '"></video>' : '<img src="' + url + '">') + '<button aria-label="Verwijderen">×</button>';
		previewBar.style.display = 'flex'; previewBar.appendChild(item);

		// Upload zum Server; bei Erfolg URL merken, sonst Objekt-URL (Demo).
		var fd = new FormData(); fd.append('file', file);
		var slot = { url: url };
		pending.push(slot.url);
		var idx = pending.length - 1;
		item.querySelector('button').addEventListener('click', function () { pending.splice(idx, 1); item.remove(); if (!previewBar.childElementCount) previewBar.style.display = 'none'; });

		if (CFG.rest_upload) {
			fetch(CFG.rest_upload, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (j) { if (j && j.url) pending[idx] = j.url; })
				.catch(function () {});
		}
	}
	function clearPreview() { pending = []; previewBar.innerHTML = ''; previewBar.style.display = 'none'; }

	// Lokaler Fallback-Bot (Demo / wenn Server nicht erreichbar)
	function localBot(text) {
		var t = (text || '').toLowerCase();
		var r = [
			[/goud|zilver|platina|gram|munt|baar/, 'De actuele goudprijs ziet u live bovenaan. Noem het gewicht en karaat voor een indicatie.', ['Goud verkopen']],
			[/afspraak|langskomen|thuis/, 'U kunt een afspraak maken: bezoek aan huis, een kantoor of ophaalservice. Wat heeft uw voorkeur?', ['Afspraak maken']],
			[/kantoor|vestiging|adres|waar/, 'Wij hebben 40+ vestigingen, Eindhoven is hoofdkantoor (6 dagen open). In welke stad zoekt u?', ['Alle kantoren']],
			[/diamant|rapaport|gia/, 'Voor diamanten taxeren wij volgens Rapaport. Heeft u een GIA/IGI/HRD-certificaat?', ['Diamanten verkopen']],
			[/horloge|rolex|omega/, 'Welk merk en model horloge wilt u verkopen?', ['Horloge verkopen']],
			[/foto|video|sturen/, 'Ja, stuur gerust een foto of video via de 📎-knop.', []],
			[/hallo|hoi|hi|hey/, 'Hallo! Waarmee kan ik u helpen?', ['Goud verkopen', 'Afspraak maken']]
		];
		for (var i = 0; i < r.length; i++) if (r[i][0].test(t)) return { reply: r[i][1], quick: r[i][2] };
		return { reply: CFG.open ? 'Daar help ik u graag mee. Kunt u het iets specifieker omschrijven?' : 'Bedankt! Laat uw vraag en e-mail achter, dan reageren wij snel.', quick: ['Goud verkopen', 'Afspraak maken', 'Alle kantoren'] };
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build);
	else build();
})();
