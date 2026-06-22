/**
 * XGOUD trust-wall – roterende reviews + count-up van de statistieken.
 */
(function () {
	'use strict';

	// Reviews roteren.
	document.querySelectorAll('.xg-tw-reviews[data-rotate]').forEach(function (wrap) {
		var slides = wrap.querySelectorAll('.xg-tw-review');
		var dots = wrap.querySelectorAll('.xg-tw-dot');
		if (slides.length < 2) return;
		var idx = 0, timer = null;
		function show(i) {
			idx = (i + slides.length) % slides.length;
			slides.forEach(function (s, k) { s.classList.toggle('active', k === idx); });
			dots.forEach(function (d, k) { d.classList.toggle('active', k === idx); });
		}
		function next() { show(idx + 1); }
		function start() { stop(); timer = setInterval(next, 5000); }
		function stop() { if (timer) clearInterval(timer); }
		dots.forEach(function (d) { d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-i'), 10)); start(); }); });
		wrap.addEventListener('mouseenter', stop);
		wrap.addEventListener('mouseleave', start);
		start();
	});

	// Statistieken count-up (alleen numerieke voorvoegsels zoals 16+, 40+).
	var stats = document.querySelector('.xg-tw-stats');
	if (stats && 'IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (en) {
			en.forEach(function (e) {
				if (!e.isIntersecting) return;
				io.disconnect();
				stats.querySelectorAll('.xg-tw-num').forEach(function (el) {
					var m = el.textContent.match(/^(\d+)(\D*)$/);
					if (!m) return;
					var to = parseInt(m[1], 10), suf = m[2], t0 = performance.now(), dur = 1400;
					function step(t) {
						var p = Math.min(1, (t - t0) / dur), e2 = 1 - Math.pow(1 - p, 3);
						el.textContent = Math.round(to * e2) + suf;
						if (p < 1) requestAnimationFrame(step);
					}
					requestAnimationFrame(step);
				});
			});
		}, { threshold: .4 });
		io.observe(stats);
	}
})();
