/**
 * XGOUD Locale: taal + valuta + thema in één compacte nav-control (popover),
 * zodat de navigatie niet vol raakt. Onthoudt de keuze (localStorage),
 * zet <html lang>, en biedt window.XGLocale.format() voor de calculator.
 */
(function () {
	'use strict';

	var LANGS = [['nl', 'NL'], ['de', 'DE'], ['en', 'EN'], ['fr', 'FR'], ['es', 'ES'], ['it', 'IT'], ['tr', 'TR'], ['pl', 'PL']];
	// Statische koersen t.o.v. EUR (later vervangbaar door live rates).
	var CUR = {
		EUR: { rate: 1, symbol: '€', locale: 'nl-NL' },
		USD: { rate: 1.08, symbol: '$', locale: 'en-US' },
		GBP: { rate: 0.85, symbol: '£', locale: 'en-GB' },
		CHF: { rate: 0.96, symbol: 'CHF ', locale: 'de-CH' }
	};
	var KEY = 'xg-locale';

	var SECONDARY = ['de', 'en', 'fr', 'es', 'it', 'tr', 'pl'];

	// Huidige taal komt van de SERVER (URL-prefix), niet uit localStorage –
	// zo blijft de keuze SEO-echt en consistent met de gerenderde pagina.
	function langFromUrl() {
		var m = location.pathname.match(/^\/(de|en|fr|es|it|tr|pl)(\/|$)/);
		return m ? m[1] : 'nl';
	}
	// Bouw de URL van de huidige pagina in een andere taal (prefix wisselen).
	function urlForLang(code) {
		var path = location.pathname.replace(/^\/(de|en|fr|es|it|tr|pl)(\/|$)/, '/');
		return (code === 'nl' ? '' : '/' + code) + path + location.search + location.hash;
	}

	var state = { lang: 'nl', currency: 'EUR' };
	try { Object.assign(state, JSON.parse(localStorage.getItem(KEY)) || {}); } catch (e) {}
	state.lang = langFromUrl(); // URL is leidend

	function save() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} document.cookie = 'xg_locale=' + state.lang + ';path=/;max-age=31536000'; }

	function apply() {
		document.documentElement.lang = state.lang;
		save();
		document.dispatchEvent(new CustomEvent('xg:locale-change', { detail: state }));
	}

	window.XGLocale = {
		get: function () { return Object.assign({}, state); },
		format: function (amount, decimals) {
			var c = CUR[state.currency] || CUR.EUR;
			return new Intl.NumberFormat(c.locale, { style: 'currency', currency: state.currency, maximumFractionDigits: decimals == null ? 2 : decimals }).format((amount || 0) * c.rate);
		}
	};

	function build(host) {
		host.classList.add('xg-ctrl');
		host.innerHTML =
			'<button class="xg-ctrl-btn" aria-label="Taal en valuta">' +
				'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18"/></svg>' +
				'<span class="xg-ctrl-cur">' + state.lang.toUpperCase() + ' · ' + state.currency + '</span>' +
			'</button>' +
			'<div class="xg-ctrl-pop">' +
				'<div class="xg-ctrl-label">Taal</div><div class="xg-ctrl-row xg-ctrl-langs"></div>' +
				'<div class="xg-ctrl-label">Valuta</div><div class="xg-ctrl-row xg-ctrl-curs"></div>' +
				'<div class="xg-ctrl-theme"><span>Thema</span><span data-xg-theme-toggle></span></div>' +
			'</div>';

		var btn = host.querySelector('.xg-ctrl-btn');
		var pop = host.querySelector('.xg-ctrl-pop');
		var label = host.querySelector('.xg-ctrl-cur');
		btn.addEventListener('click', function (e) { e.stopPropagation(); host.classList.toggle('open'); });
		document.addEventListener('click', function (e) { if (!host.contains(e.target)) host.classList.remove('open'); });

		var langs = host.querySelector('.xg-ctrl-langs');
		LANGS.forEach(function (l) {
			var b = document.createElement('button');
			b.className = 'xg-ctrl-opt' + (state.lang === l[0] ? ' active' : '');
			b.textContent = l[1];
			b.addEventListener('click', function () {
				// Taalwissel = navigeren naar de server-gerenderde taal-URL (SEO-echt).
				if (l[0] === state.lang) return;
				save(); // valuta-/voorkeur bewaren over de navigatie heen
				location.href = urlForLang(l[0]);
			});
			langs.appendChild(b);
		});

		var curs = host.querySelector('.xg-ctrl-curs');
		Object.keys(CUR).forEach(function (c) {
			var b = document.createElement('button');
			b.className = 'xg-ctrl-opt' + (state.currency === c ? ' active' : '');
			b.textContent = c;
			b.addEventListener('click', function () {
				state.currency = c; apply();
				curs.querySelectorAll('.xg-ctrl-opt').forEach(function (x) { x.classList.remove('active'); });
				b.classList.add('active'); label.textContent = state.lang.toUpperCase() + ' · ' + state.currency;
			});
			curs.appendChild(b);
		});

		// Theme-Switch in das Popover mounten (sobald theme.js bereit ist).
		var themeHost = host.querySelector('[data-xg-theme-toggle]');
		if (window.XGTheme && window.XGTheme.mount) window.XGTheme.mount(themeHost);
	}

	function boot() {
		document.documentElement.lang = state.lang;
		document.querySelectorAll('[data-xg-controls]').forEach(build);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
