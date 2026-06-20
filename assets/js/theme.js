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
	function init() {
		var saved = null;
		try { saved = localStorage.getItem(KEY); } catch (e) {}
		if (!saved) {
			saved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
		}
		apply(saved, false);
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
