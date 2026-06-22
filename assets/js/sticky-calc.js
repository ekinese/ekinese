/**
 * XGOUD sticky CTA-balk – verschijnt na scrollen, scrollt naar de rekenaar als
 * die op de pagina staat, anders linkt naar /afspraak/. Niet opdringerig:
 * verschijnt pas na ~40% scrollen en verdwijnt onderaan de pagina.
 */
(function () {
	'use strict';
	var bar = document.querySelector('.xg-sticky-cta');
	if (!bar) return;
	if (document.querySelector('.xg-driver')) { bar.remove(); return; }

	var btn = bar.querySelector('.xg-sticky-cta-btn');
	var calc = document.querySelector('.xg-calc, #rekenaar, [data-calc]');
	if (calc && btn) {
		btn.setAttribute('href', '#');
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			calc.scrollIntoView({ behavior: 'smooth', block: 'center' });
			var input = calc.querySelector('select, input');
			if (input) setTimeout(function () { input.focus(); }, 500);
		});
	}

	var shown = false;
	function onScroll() {
		var y = window.scrollY || window.pageYOffset;
		var h = document.documentElement.scrollHeight - window.innerHeight;
		var ratio = h > 0 ? y / h : 0;
		// Tonen tussen 35% en 92% van de pagina (niet in hero, niet in footer).
		var should = ratio > 0.35 && ratio < 0.92;
		if (should && !shown) { bar.hidden = false; requestAnimationFrame(function () { bar.classList.add('in'); }); shown = true; }
		else if (!should && shown) { bar.classList.remove('in'); shown = false; setTimeout(function () { if (!shown) bar.hidden = true; }, 250); }
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
})();
