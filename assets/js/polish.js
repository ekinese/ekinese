/**
 * XGOUD polish — count-up animaties + confetti. Globaal, niet-blokkerend.
 * Respecteert prefers-reduced-motion.
 */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- Count-up ---------- */
	function fmt(n, dec) {
		try { return n.toLocaleString('nl-NL', dec ? { minimumFractionDigits: 2, maximumFractionDigits: 2 } : { maximumFractionDigits: 0 }); }
		catch (e) { return String(Math.round(n)); }
	}
	function countUp(el) {
		if (el.__xgUp) return; el.__xgUp = 1;
		var txt = el.textContent || '';
		var m = txt.match(/-?[\d.]+(?:,\d+)?/);
		if (!m) return;
		var raw = m[0];
		var target = parseFloat(raw.replace(/\./g, '').replace(',', '.'));
		if (!isFinite(target) || target === 0) return;
		var dec = /,\d{1,2}$/.test(raw);
		var prefix = txt.slice(0, m.index), suffix = txt.slice(m.index + raw.length);
		if (reduce) { return; }
		var dur = 850, start = performance.now();
		function step(t) {
			var p = Math.min(1, (t - start) / dur);
			var v = target * (0.5 - Math.cos(Math.PI * p) / 2); // easeInOut
			el.textContent = prefix + fmt(v, dec) + suffix;
			if (p < 1) requestAnimationFrame(step); else el.textContent = prefix + fmt(target, dec) + suffix;
		}
		requestAnimationFrame(step);
	}
	var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
		entries.forEach(function (e) { if (e.isIntersecting) { countUp(e.target); io.unobserve(e.target); } });
	}, { threshold: 0.6 }) : null;
	function scan(root) {
		(root || document).querySelectorAll('.xg-wg-num, .xg-pf-total-val').forEach(function (el) {
			if (el.__xgSeen) return; el.__xgSeen = 1;
			if (io) io.observe(el); else countUp(el);
		});
	}
	if ('MutationObserver' in window) {
		new MutationObserver(function (muts) {
			muts.forEach(function (mu) {
				for (var i = 0; i < mu.addedNodes.length; i++) {
					var n = mu.addedNodes[i];
					if (n.nodeType === 1) scan(n);
				}
			});
		}).observe(document.body, { childList: true, subtree: true });
	}
	scan(document);

	/* ---------- Confetti ---------- */
	function confetti() {
		if (reduce) return;
		var cv = document.createElement('canvas');
		cv.id = 'xg-confetti';
		document.body.appendChild(cv);
		var ctx = cv.getContext('2d');
		function size() { cv.width = innerWidth; cv.height = innerHeight; }
		size();
		var colors = ['#AE1E1E', '#D0AC4B', '#161412', '#1f9d55', '#ffffff'];
		var bits = [];
		for (var i = 0; i < 130; i++) {
			bits.push({
				x: innerWidth / 2 + (Math.random() - 0.5) * 200,
				y: innerHeight / 3,
				vx: (Math.random() - 0.5) * 9,
				vy: Math.random() * -11 - 4,
				g: 0.3 + Math.random() * 0.2,
				s: 5 + Math.random() * 6,
				c: colors[(Math.random() * colors.length) | 0],
				r: Math.random() * 6, vr: (Math.random() - 0.5) * 0.4
			});
		}
		var t0 = performance.now();
		function frame(t) {
			ctx.clearRect(0, 0, cv.width, cv.height);
			var alive = false;
			bits.forEach(function (b) {
				b.vy += b.g; b.x += b.vx; b.y += b.vy; b.r += b.vr;
				if (b.y < cv.height + 20) alive = true;
				ctx.save(); ctx.translate(b.x, b.y); ctx.rotate(b.r);
				ctx.fillStyle = b.c; ctx.fillRect(-b.s / 2, -b.s / 2, b.s, b.s * 0.6); ctx.restore();
			});
			if (alive && t - t0 < 4000) requestAnimationFrame(frame);
			else cv.remove();
		}
		requestAnimationFrame(frame);
	}
	document.addEventListener('xg:celebrate', confetti);
	window.xgCelebrate = function () { document.dispatchEvent(new CustomEvent('xg:celebrate')); };
})();
