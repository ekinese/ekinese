/**
 * XGOUD AI-verkoopassistent — drijft de nav-zoekbalk (full-screen overlay).
 * Tekst + spraak (Web Speech API); rendert prijs-/grafiek-/link-/afspraakkaarten.
 * Taal = taal van de gebruiker (navigator.language).
 */
(function () {
	'use strict';
	var cfg = window.XGAssistant || {};
	var input = document.getElementById('xgSearchInput');
	var log = document.getElementById('xgSearchResults');
	if (!input || !log) return;

	var lang = (navigator.language || 'nl').slice(0, 2);
	var history = [];
	var busy = false;

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	function bubble(role, html) {
		var el = document.createElement('div');
		el.className = 'xg-as-msg xg-as-' + role;
		el.innerHTML = html;
		log.appendChild(el);
		log.scrollTop = log.scrollHeight;
		return el;
	}

	function renderCards(cards) {
		if (!cards || !cards.length) return '';
		return cards.map(function (c) {
			if (c.type === 'price') {
				return '<div class="xg-as-card xg-as-price"><div class="xg-as-price-metal">' + esc(c.metal) + '</div>' +
					'<div class="xg-as-price-main">' + esc(c.gram) + '<span>/g</span></div>' +
					'<div class="xg-as-price-sub">' + esc(c.kilo) + ' /kg · ' + esc(c.ounce) + ' /oz</div></div>';
			}
			if (c.type === 'chart') {
				return '<div class="xg-as-card xg-as-chart">' + (c.html || '') + '</div>';
			}
			if (c.type === 'links') {
				return '<div class="xg-as-card xg-as-links">' + (c.items || []).map(function (l) {
					return '<a class="xg-as-link" href="' + esc(l.url) + '">' + esc(l.label) + ' →</a>';
				}).join('') + '</div>';
			}
			if (c.type === 'appointment') {
				return '<div class="xg-as-card xg-as-appt"><a class="xg-as-cta" href="' + esc(c.url) + '">' + esc(c.label || 'Plan een afspraak') + '</a></div>';
			}
			return '';
		}).join('');
	}

	function speak(text) {
		try {
			if (!window.speechSynthesis || !text) return;
			var u = new SpeechSynthesisUtterance(text);
			u.lang = navigator.language || 'nl-NL';
			window.speechSynthesis.cancel();
			window.speechSynthesis.speak(u);
		} catch (e) {}
	}

	function send(text) {
		if (busy || !text.trim()) return;
		busy = true;
		bubble('user', esc(text));
		history.push({ role: 'user', content: text });
		var thinking = bubble('bot', '<span class="xg-as-typing"><i></i><i></i><i></i></span>');

		fetch(cfg.rest, {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ message: text, lang: lang, history: history.slice(0, -1) })
		}).then(function (r) { return r.json(); })
			.then(function (d) {
				busy = false;
				var reply = (d && d.reply) || 'Sorry, dat lukte niet.';
				thinking.innerHTML = esc(reply).replace(/\n/g, '<br>') + renderCards(d && d.cards);
				log.scrollTop = log.scrollHeight;
				history.push({ role: 'assistant', content: reply });
				speak(reply);
			})
			.catch(function () { busy = false; thinking.innerHTML = 'Er ging iets mis. Probeer het opnieuw.'; });
	}

	// Greeting bij eerste opening.
	var greeted = false;
	function greet() {
		if (greeted) return;
		greeted = true;
		log.innerHTML = '';
		bubble('bot', 'Hallo! Ik ben de XGOUD-assistent. Vertel me wat u wilt verkopen of vraag naar de actuele prijzen — bijvoorbeeld <em>“ik wil mijn gouden ring verkopen”</em>. U kunt ook op de microfoon tikken en spreken.');
	}
	var sb = document.getElementById('xgSearchBtn');
	if (sb) sb.addEventListener('click', greet);

	// Verzenden op Enter.
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); var v = input.value; input.value = ''; send(v); }
	});

	// Spraakherkenning (mic) + verzendknop, naast het invoerveld injecteren.
	var box = input.closest('.xg-search-box') || input.parentNode;
	var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
	if (box) {
		var sendBtn = document.createElement('button');
		sendBtn.type = 'button'; sendBtn.className = 'xg-as-send'; sendBtn.setAttribute('aria-label', 'Verstuur');
		sendBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
		sendBtn.addEventListener('click', function () { var v = input.value; input.value = ''; send(v); });
		box.appendChild(sendBtn);

		if (SR) {
			var micBtn = document.createElement('button');
			micBtn.type = 'button'; micBtn.className = 'xg-as-mic'; micBtn.setAttribute('aria-label', 'Spreek');
			micBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>';
			box.appendChild(micBtn);
			var rec = new SR();
			rec.lang = navigator.language || 'nl-NL';
			rec.interimResults = false;
			rec.maxAlternatives = 1;
			var listening = false;
			micBtn.addEventListener('click', function () {
				try {
					if (listening) { rec.stop(); return; }
					greet(); rec.start(); listening = true; micBtn.classList.add('is-on');
				} catch (e) {}
			});
			rec.onresult = function (e) {
				var t = e.results[0][0].transcript;
				input.value = t; send(t);
			};
			rec.onend = function () { listening = false; micBtn.classList.remove('is-on'); };
			rec.onerror = function () { listening = false; micBtn.classList.remove('is-on'); };
		}
	}
})();
