/**
 * XGOUD cookie-consent — laadt GA4/FB-pixel pas na toestemming.
 * Keuze wordt 6 maanden bewaard (cookie xg_consent = granted|denied).
 */
(function () {
	var cfg = window.XGConsent || {};
	var KEY = 'xg_consent';

	function getCookie(n) {
		var m = document.cookie.match('(^|;)\\s*' + n + '\\s*=\\s*([^;]+)');
		return m ? m.pop() : '';
	}
	function setCookie(n, v, days) {
		var d = new Date(); d.setTime(d.getTime() + days * 864e5);
		document.cookie = n + '=' + v + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
	}

	function loadGA4(id) {
		if (!id) return;
		var s = document.createElement('script');
		s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
		document.head.appendChild(s);
		window.dataLayer = window.dataLayer || [];
		function gtag() { dataLayer.push(arguments); }
		window.gtag = gtag; gtag('js', new Date()); gtag('config', id, { anonymize_ip: true });
	}
	function loadPixel(id) {
		if (id) {
			/* eslint-disable */
			!function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s) }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
			/* eslint-enable */
			window.fbq('init', id); window.fbq('track', 'PageView');
		}
	}
	function loadAll() { loadGA4(cfg.ga4); loadPixel(cfg.pixel); }

	var choice = getCookie(KEY);
	if (choice === 'granted') { loadAll(); return; }
	if (choice === 'denied') { return; }

	// Geen keuze → banner tonen.
	document.addEventListener('DOMContentLoaded', function () {
		var box = document.getElementById('xgConsent');
		if (!box) return;
		box.hidden = false;
		var accept = document.getElementById('xgConsentAccept');
		var reject = document.getElementById('xgConsentReject');
		if (accept) accept.addEventListener('click', function () { setCookie(KEY, 'granted', 180); box.hidden = true; loadAll(); });
		if (reject) reject.addEventListener('click', function () { setCookie(KEY, 'denied', 180); box.hidden = true; });
	});
})();
