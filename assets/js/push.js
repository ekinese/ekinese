/**
 * XGOUD Web-Push — abonneren op meldingen (alleen voor ingelogde gebruikers).
 */
(function () {
	var cfg = window.XGPush || {};
	var token = new URLSearchParams(location.search).get('token') || localStorage.getItem('xg_acct_token') || '';
	if (!token || !cfg.key || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;

	function b64ToUint8(b64) {
		var pad = '='.repeat((4 - b64.length % 4) % 4);
		var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
		var arr = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
		return arr;
	}

	function subscribe() {
		fetch(cfg.key).then(function (r) { return r.json(); }).then(function (d) {
			if (!d.key) return;
			navigator.serviceWorker.ready.then(function (reg) {
				reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(d.key) })
					.then(function (sub) {
						fetch(cfg.subscribe, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: token, subscription: sub }) });
					}).catch(function () {});
			});
		});
	}

	if (Notification.permission === 'granted') { subscribe(); return; }
	if (Notification.permission === 'denied') return;

	// Vriendelijke opt-in-balk.
	if (localStorage.getItem('xg_push_dismissed')) return;
	document.addEventListener('DOMContentLoaded', function () {
		var bar = document.createElement('div');
		bar.className = 'xg-pwa-install xg-push-ask';
		bar.innerHTML = '<span>Meldingen op uw toestel ontvangen (overboden, gewonnen, antwoorden)?</span>' +
			'<button class="xg-pwa-yes">Aanzetten</button><button class="xg-pwa-no" aria-label="Sluiten">×</button>';
		document.body.appendChild(bar);
		bar.querySelector('.xg-pwa-yes').addEventListener('click', function () {
			Notification.requestPermission().then(function (p) { if (p === 'granted') subscribe(); bar.remove(); });
		});
		bar.querySelector('.xg-pwa-no').addEventListener('click', function () { localStorage.setItem('xg_push_dismissed', '1'); bar.remove(); });
	});
})();
