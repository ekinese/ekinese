/**
 * XGOUD prijs-tabs — wisselt tussen metaal-panelen (plugin-vrij).
 */
(function () {
	document.querySelectorAll('.xg-ptabs').forEach(function (root) {
		var tabs = root.querySelectorAll('.xg-ptab');
		var panels = root.querySelectorAll('.xg-ptab-panel');
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				var key = tab.getAttribute('data-pt');
				tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
				panels.forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-pt') === key); });
			});
		});
	});
})();
