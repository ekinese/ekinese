/**
 * XGOUD admin-dashboard — Claude AI vraag + agent runs (admin-ajax).
 */
(function () {
	var root = document.getElementById('xg-ai');
	if (!root) return;
	var nonce = root.getAttribute('data-nonce');
	var out = document.getElementById('xg-ai-out');

	function run(params, btn) {
		var label = btn ? btn.textContent : '';
		if (btn) { btn.disabled = true; }
		out.style.display = 'block';
		out.textContent = 'Claude denkt na…';
		var body = new URLSearchParams();
		body.append('action', 'xg_admin_ai');
		body.append('nonce', nonce);
		Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
		fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (btn) { btn.disabled = false; }
				if (j && j.success) { out.textContent = j.data.reply; }
				else { out.textContent = 'Fout: ' + ((j && j.data && j.data.message) || 'onbekend'); }
			})
			.catch(function () { if (btn) { btn.disabled = false; } out.textContent = 'Netwerkfout.'; });
	}

	var ask = document.getElementById('xg-ai-ask');
	if (ask) {
		ask.addEventListener('click', function (e) {
			e.preventDefault();
			var q = (document.getElementById('xg-ai-q') || {}).value || '';
			if (!q.trim()) { out.style.display = 'block'; out.textContent = 'Typ eerst een vraag.'; return; }
			run({ q: q }, ask);
		});
	}
	root.querySelectorAll('.xg-ai-run').forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.preventDefault();
			run({ agent: b.getAttribute('data-agent') }, b);
		});
	});
})();
