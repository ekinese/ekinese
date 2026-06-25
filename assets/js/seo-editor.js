/**
 * XGOUD SEO/GEO-analyzer + AI-tekstgenerator in de blok-editor.
 */
(function () {
	'use strict';
	var dataSel = window.wp && wp.data;

	/* ---------- SEO/GEO-analyzer ---------- */
	var box = document.getElementById('xg-seo-analyzer');
	var CITIES = ['amsterdam', 'rotterdam', 'den haag', 'utrecht', 'eindhoven', 'tilburg', 'groningen', 'breda', 'nijmegen', 'antwerpen', 'brussel', 'nederland', 'belgië', 'belgie'];

	function val(name) {
		var el = document.querySelector('[name="' + name + '"]');
		return el ? (el.value || '') : '';
	}

	function analyze() {
		if (!box || !dataSel || !wp.data.select('core/editor')) return;
		var content = wp.data.select('core/editor').getEditedPostContent() || '';
		var title = (wp.data.select('core/editor').getEditedPostAttribute('title') || '');
		var plain = content.replace(/<!--[\s\S]*?-->/g, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
		var words = plain ? plain.split(' ').length : 0;
		var lc = (plain + ' ' + title).toLowerCase();
		var seoTitle = val('xg_seo_title') || title;
		var metaDesc = val('xg_meta_desc');
		var h2 = (content.match(/<h2/gi) || []).length + (content.match(/"level":2/g) || []).length;
		var intLinks = (content.match(/href="\/[^"]*"/g) || []).length + (content.match(/href="https?:\/\/[^"]*xgoud/gi) || []).length;
		var imgs = (content.match(/<img/gi) || []).length;
		var imgsNoAlt = (content.match(/<img(?![^>]*alt=)[^>]*>/gi) || []).length;
		var hasGeo = CITIES.some(function (c) { return lc.indexOf(c) !== -1; });
		var hasSchema = /wp:ekinese\/(faq|offices|price)/.test(content) || /FAQPage|LocalBusiness/.test(content);

		var checks = [
			['SEO-titel 30–60 tekens', seoTitle.length >= 30 && seoTitle.length <= 60, seoTitle.length + ' tekens'],
			['Meta description 70–160', metaDesc.length >= 70 && metaDesc.length <= 160, metaDesc.length + ' tekens'],
			['Minstens 1 tussenkop (H2)', h2 >= 1, h2 + '×'],
			['Minstens 300 woorden', words >= 300, words + ' woorden'],
			['Interne links aanwezig', intLinks >= 1, intLinks + '×'],
			['Alle afbeeldingen alt-tekst', imgs === 0 || imgsNoAlt === 0, imgsNoAlt + ' zonder alt'],
			['Lokaal/GEO-signaal (stad/regio)', hasGeo, hasGeo ? 'ja' : 'nee'],
			['Schema/FAQ/Local aanwezig', hasSchema, hasSchema ? 'ja' : 'nee']
		];
		var pass = checks.filter(function (c) { return c[1]; }).length;
		var pct = Math.round(pass / checks.length * 100);
		var color = pct >= 80 ? '#1f9d55' : pct >= 50 ? '#dba617' : '#d63638';

		var html = '<div style="font-size:13px">';
		html += '<div style="font-size:26px;font-weight:800;color:' + color + '">' + pct + '<span style="font-size:13px">/100</span></div>';
		html += '<div style="height:6px;background:#e0e0e0;margin:6px 0 12px"><span style="display:block;height:100%;width:' + pct + '%;background:' + color + '"></span></div>';
		html += '<ul style="margin:0;list-style:none">';
		checks.forEach(function (c) {
			html += '<li style="padding:4px 0;display:flex;gap:6px"><span>' + (c[1] ? '✅' : '⬜') + '</span><span style="flex:1">' + c[0] + '<br><span style="color:#646970">' + c[2] + '</span></span></li>';
		});
		html += '</ul></div>';
		box.innerHTML = html;
	}

	if (box && dataSel && window.wp.domReady) {
		wp.domReady(function () {
			analyze();
			var t;
			wp.data.subscribe(function () { clearTimeout(t); t = setTimeout(analyze, 800); });
			document.addEventListener('input', function (e) {
				if (e.target && /^xg_(seo_title|meta_desc)$/.test(e.target.name || '')) analyze();
			});
		});
	}

	/* ---------- AI-tekstgenerator ---------- */
	var writer = document.getElementById('xg-ai-writer');
	if (writer) {
		var nonce = writer.getAttribute('data-nonce');
		var genBtn = document.getElementById('xg-ai-gen');
		var out = document.getElementById('xg-ai-gen-out');
		var outText = document.getElementById('xg-ai-gen-text');
		genBtn.addEventListener('click', function () {
			var prompt = (document.getElementById('xg-ai-prompt') || {}).value || '';
			if (!prompt.trim()) return;
			genBtn.disabled = true; genBtn.textContent = 'Bezig…';
			var body = new URLSearchParams();
			body.append('action', 'xg_ai_gen'); body.append('nonce', nonce); body.append('prompt', prompt);
			fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					genBtn.disabled = false; genBtn.textContent = 'Genereer tekst';
					if (j && j.success) { out.style.display = 'block'; outText.value = j.data.text; }
					else { alert((j && j.data && j.data.message) || 'Mislukt.'); }
				})
				.catch(function () { genBtn.disabled = false; genBtn.textContent = 'Genereer tekst'; alert('Netwerkfout.'); });
		});
		document.getElementById('xg-ai-insert').addEventListener('click', function () {
			var text = outText.value.trim();
			if (!text || !window.wp || !wp.blocks || !wp.data) return;
			var blocks = text.split(/\n{2,}/).map(function (p) {
				return wp.blocks.createBlock('core/paragraph', { content: p.replace(/\n/g, '<br>') });
			});
			wp.data.dispatch('core/block-editor').insertBlocks(blocks);
		});
	}
})();
