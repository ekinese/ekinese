/**
 * XGOUD PWA — service-worker registreren + nette "installeer de app"-knop.
 */
(function () {
	var cfg = window.XGPWA || {};
	if ('serviceWorker' in navigator && cfg.sw) {
		window.addEventListener('load', function () {
			navigator.serviceWorker.register(cfg.sw, { scope: '/' }).catch(function () {});
		});
	}

	// Install-prompt (Android/Chrome). iOS: via "Deel → Zet op beginscherm".
	var deferred = null;
	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferred = e;
		showInstall();
	});

	function showInstall() {
		if (document.querySelector('.xg-pwa-install') || localStorage.getItem('xg_pwa_dismissed')) return;
		var bar = document.createElement('div');
		bar.className = 'xg-pwa-install';
		bar.innerHTML = '<span>Installeer de XGOUD-app voor live prijzen, uw portfolio en afspraken.</span>' +
			'<button class="xg-pwa-yes">Installeren</button><button class="xg-pwa-no" aria-label="Sluiten">×</button>';
		document.body.appendChild(bar);
		bar.querySelector('.xg-pwa-yes').addEventListener('click', function () {
			if (!deferred) return;
			deferred.prompt();
			deferred.userChoice.finally(function () { deferred = null; bar.remove(); });
		});
		bar.querySelector('.xg-pwa-no').addEventListener('click', function () {
			localStorage.setItem('xg_pwa_dismissed', '1'); bar.remove();
		});
	}
})();
