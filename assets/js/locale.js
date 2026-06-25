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
		// Sprache + Währung bewusst entfernt: de site is NL-only / EUR-only.
		// Alleen de thema-schakelaar (licht/donker) blijft in de header.
		host.classList.add('xg-ctrl', 'xg-ctrl--theme-only');
		host.innerHTML = '';
		var themeHost = document.createElement('span');
		themeHost.setAttribute('data-xg-theme-toggle', '');
		host.appendChild(themeHost);

		function mountTheme() {
			if (window.XGTheme && window.XGTheme.mount) { window.XGTheme.mount(themeHost); return true; }
			return false;
		}
		if (!mountTheme()) { window.addEventListener('load', mountTheme); }
	}

	function boot() {
		document.documentElement.lang = state.lang;
		document.querySelectorAll('[data-xg-controls]').forEach(build);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
