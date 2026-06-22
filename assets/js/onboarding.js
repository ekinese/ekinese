/**
 * XGOUD Onboarding – lichte, self-built rondleiding voor nieuwe bezoekers.
 * Toont eenmalig (localStorage) tooltips bij belangrijke elementen. Meertalig
 * via window.XGI18N.t indien aanwezig. Geen plugin, geen externe library.
 */
(function () {
	'use strict';
	var KEY = 'xg-onboarded';
	try { if (localStorage.getItem(KEY)) return; } catch (e) {}

	// Stappen: selector → titel/tekst (NL; vertaald indien XGI18N beschikbaar).
	var STEPS = [
		['.xg-nav', 'Navigatie', 'Hier vindt u alles: edelmetalen, edelstenen, horloges, dagprijzen en service.'],
		['.xg-calc', 'Bereken uw waarde', 'Kies uw product en aantal — u ziet direct een eerlijke indicatie.'],
		['[data-xg-controls]', 'Taal & valuta', 'Wissel hier eenvoudig van taal, valuta en dag/nacht-modus.'],
		['.xg-charity-ticker', 'Goede doelen', 'Een vast deel van elke marge gaat naar een project in uw stad.'],
		['.xg-cta', 'Direct verkopen', 'Klaar? Plan een afspraak — thuis, op kantoor of via ophaalservice.']
	];

	function t(s) { return (window.XGI18N && window.XGI18N.t) ? window.XGI18N.t(s) : s; }
	function done() { try { localStorage.setItem(KEY, '1'); } catch (e) {} cleanup(); }

	var overlay, pop, idx = 0;
	function cleanup() { if (overlay) overlay.remove(); if (pop) pop.remove(); overlay = pop = null; window.removeEventListener('resize', position); window.removeEventListener('scroll', position); }

	// Alleen starten als er iets zinvols te tonen is.
	var available = STEPS.filter(function (s) { return document.querySelector(s[0]); });
	if (available.length < 2) return;

	function build() {
		overlay = document.createElement('div'); overlay.className = 'xg-ob-overlay';
		pop = document.createElement('div'); pop.className = 'xg-ob-pop';
		document.body.appendChild(overlay); document.body.appendChild(pop);
		overlay.addEventListener('click', done);
		render();
		window.addEventListener('resize', position);
		window.addEventListener('scroll', position, { passive: true });
	}

	function render() {
		var step = available[idx];
		pop.innerHTML = '<div class="xg-ob-step">' + (idx + 1) + ' / ' + available.length + '</div>' +
			'<h4>' + t(step[1]) + '</h4><p>' + t(step[2]) + '</p>' +
			'<div class="xg-ob-actions"><button class="xg-ob-skip">' + t('Overslaan') + '</button>' +
			'<button class="xg-ob-next">' + (idx === available.length - 1 ? t('Klaar') : t('Volgende')) + '</button></div>';
		pop.querySelector('.xg-ob-skip').addEventListener('click', done);
		pop.querySelector('.xg-ob-next').addEventListener('click', function () {
			if (idx === available.length - 1) return done();
			idx++; render();
		});
		position();
	}

	function position() {
		if (!pop) return;
		var el = document.querySelector(available[idx][0]);
		if (!el) { return; }
		var r = el.getBoundingClientRect();
		// Highlight-ring via overlay box-shadow.
		overlay.style.setProperty('--x', r.left + 'px');
		overlay.style.setProperty('--y', r.top + 'px');
		overlay.style.setProperty('--w', r.width + 'px');
		overlay.style.setProperty('--h', r.height + 'px');
		var top = r.bottom + 12;
		if (top + 160 > window.innerHeight) top = Math.max(12, r.top - 172);
		pop.style.top = top + 'px';
		pop.style.left = Math.min(Math.max(12, r.left), window.innerWidth - pop.offsetWidth - 12) + 'px';
	}

	// Start na load + korte vertraging (zodat JS-widgets gemount zijn).
	if (document.readyState === 'complete') setTimeout(build, 1200);
	else window.addEventListener('load', function () { setTimeout(build, 1200); });
})();
