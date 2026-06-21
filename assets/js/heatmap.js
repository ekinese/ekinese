/**
 * XGOUD Heatmap-tracker – gesampled, gebatcht, privacyvriendelijk.
 * Stuurt relatieve klikposities (rastercel) via sendBeacon. Geen pixels,
 * geen persoonsgegevens. Datenquelle: window.XG_HEATMAP.
 */
(function () {
	'use strict';
	var CFG = window.XG_HEATMAP;
	if (!CFG || !CFG.rest) return;

	// Per-bezoeker sampling: meet maar een deel, beslist één keer per sessie.
	var KEY = 'xg_hm_sample';
	var inSample;
	try {
		var s = sessionStorage.getItem(KEY);
		if (s === null) { inSample = Math.random() < (CFG.sample || 0.25) ? '1' : '0'; sessionStorage.setItem(KEY, inSample); }
		else inSample = s;
	} catch (e) { inSample = Math.random() < (CFG.sample || 0.25) ? '1' : '0'; }
	if (inSample !== '1') return;

	var COLS = CFG.cols || 20;
	var ROWS = CFG.rows || 40;
	var buffer = [];

	document.addEventListener('click', function (e) {
		var w = window.innerWidth || document.documentElement.clientWidth;
		var docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight) || 1;
		var x = e.pageX, y = e.pageY;
		var c = Math.min(COLS - 1, Math.max(0, Math.floor((x / w) * COLS)));
		var r = Math.min(ROWS - 1, Math.max(0, Math.floor((y / docH) * ROWS)));
		buffer.push({ c: c, r: r });
		if (buffer.length >= 25) flush();
	}, { passive: true });

	function flush() {
		if (!buffer.length) return;
		var payload = JSON.stringify({ path: location.pathname, hits: buffer.splice(0, buffer.length) });
		try {
			if (navigator.sendBeacon) {
				navigator.sendBeacon(CFG.rest, new Blob([payload], { type: 'application/json' }));
			} else {
				fetch(CFG.rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true });
			}
		} catch (e) { /* stil falen, nooit de UI blokkeren */ }
	}

	// Bij verlaten/verbergen de rest wegschrijven.
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flush(); });
	window.addEventListener('pagehide', flush);
})();
