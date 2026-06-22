/**
 * XGOUD – Theme-Switch (Day ↔ Dark).
 *
 * Setzt data-theme auf <html>, merkt sich die Wahl (localStorage) und
 * respektiert beim ersten Besuch die System-Einstellung. Im Dark-Mode
 * wechselt die Markenfarbe von Rot auf Gold (siehe CSS-Tokens).
 *
 * Der Schalter wird in jedes Element mit [data-xg-theme-toggle] gerendert.
 * Mehrere Schalter (Header, Calculator …) bleiben synchron.
 */
(function () {
	'use strict';

	var KEY = 'xg-theme';
	var root = document.documentElement;

	function current() { return root.getAttribute('data-theme') || 'light'; }

	function apply(theme, persist) {
		root.setAttribute('data-theme', theme);
		if (persist) { try { localStorage.setItem(KEY, theme); } catch (e) {} }
		document.querySelectorAll('[data-xg-theme-toggle]').forEach(syncToggle);
		document.dispatchEvent(new CustomEvent('xg:theme-change', { detail: { theme: theme } }));
	}

	function toggle() { apply(current() === 'dark' ? 'light' : 'dark', true); }

	// Initialwert: gespeichert > System > light
	// Ohne gespeicherte Wahl: dark nur wenn das System dunkel ist (kein Zeitfenster).
	function autoDefault() {
		var sysDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		return sysDark ? 'dark' : 'light';
	}
	function init() {
		// JS ist da → Reveal-Verstecken aktivieren (CSS gated auf html.xg-js).
		root.classList.add('xg-js');
		var saved = null;
		try { saved = localStorage.getItem(KEY); } catch (e) {}
		apply(saved || autoDefault(), false);
	}

	// #7 Reveal-animaties: elementen met .xg-reveal faden in bij scroll.
	function initReveal() {
		var els = document.querySelectorAll('.xg-reveal');
		if (!els.length) return;
		// Elementen die al in beeld zijn meteen tonen; rest via observer.
		function inView(el) {
			var r = el.getBoundingClientRect();
			return r.top < (window.innerHeight || document.documentElement.clientHeight) && r.bottom > 0;
		}
		if (!('IntersectionObserver' in window)) {
			els.forEach(function (el) { el.classList.add('in'); });
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
			});
		}, { rootMargin: '0px 0px -10% 0px' });
		els.forEach(function (el) { if (inView(el)) { el.classList.add('in'); } else { io.observe(el); } });
		// Fail-safe: na ~1,2 s alles tonen — niets blijft ooit onzichtbaar.
		setTimeout(function () {
			document.querySelectorAll('.xg-reveal:not(.in)').forEach(function (el) { el.classList.add('in'); });
		}, 1200);
	}

	// Moderner Schalter (Sonne/Mond, gleitender Knopf)
	function buildToggle(host) {
		host.innerHTML = '';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'xg-theme-switch';
		btn.setAttribute('aria-label', 'Dark Mode umschalten');
		btn.innerHTML =
			'<span class="xg-theme-track">' +
				'<span class="xg-theme-ico sun" aria-hidden="true">' +
					'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></svg>' +
				'</span>' +
				'<span class="xg-theme-ico moon" aria-hidden="true">' +
					'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8z"/></svg>' +
				'</span>' +
				'<span class="xg-theme-knob"></span>' +
			'</span>';
		btn.addEventListener('click', toggle);
		host.appendChild(btn);
		syncToggle(host);
	}

	function syncToggle(host) {
		var dark = current() === 'dark';
		var btn = host.querySelector('.xg-theme-switch');
		if (btn) btn.classList.toggle('is-dark', dark);
	}

	function boot() {
		document.querySelectorAll('[data-xg-theme-toggle]').forEach(buildToggle);
		initReveal();
	}

	// API für andere Skripte (z.B. Calculator-internen Schalter)
	window.XGTheme = {
		toggle: toggle,
		set: function (t) { apply(t, true); },
		get: current,
		mount: function (host) { buildToggle(host); } // Switch on demand erzeugen
	};

	init(); // so früh wie möglich (kein FOUC-Flash)
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
