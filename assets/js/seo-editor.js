/**
 * XGOUD SEO/GEO-analyzer + AI-tekstgenerator in de blok-editor.
 */
(function () {
	'use strict';
	var dataSel = window.wp && wp.data;

	/* ---------- SEO/GEO-analyzer (RankMath-stijl) ---------- */
	var box = document.getElementById('xg-seo-analyzer');
	var CITIES = ['amsterdam', 'rotterdam', 'den haag', 'utrecht', 'eindhoven', 'tilburg', 'groningen', 'breda', 'nijmegen', 'arnhem', 'haarlem', 'zwolle', 'maastricht', 'antwerpen', 'brussel', 'gent', 'nederland', 'belgië', 'belgie'];
	var POWER = ['gratis', 'direct', 'beste', 'veilig', 'snel', 'eenvoudig', 'vandaag', 'betrouwbaar', 'hoogste', 'expert', 'gegarandeerd', 'eerlijk', 'nu', 'top', 'voordelig', 'exclusief', 'bewezen', 'simpel'];

	function val(name) {
		var el = document.querySelector('[name="' + name + '"]');
		return el ? (el.value || '') : '';
	}
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
	function countOcc(hay, needle) {
		if (!needle) return 0;
		try { return (hay.match(new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi')) || []).length; } catch (e) { return 0; }
	}

	function analyze() {
		if (!box || !dataSel || !wp.data.select('core/editor')) return;
		var ed = wp.data.select('core/editor');
		var content = ed.getEditedPostContent() || '';
		var title = (ed.getEditedPostAttribute('title') || '');
		var slug = (ed.getEditedPostAttribute('slug') || '') || (ed.getEditedPostSlug && ed.getEditedPostSlug()) || '';
		var plain = content.replace(/<!--[\s\S]*?-->/g, ' ').replace(/<[^>]+>/g, ' ').replace(/&[a-z]+;/gi, ' ').replace(/\s+/g, ' ').trim();
		var words = plain ? plain.split(' ').length : 0;
		var lc = plain.toLowerCase();
		var seoTitle = val('xg_seo_title') || title;
		var metaDesc = val('xg_meta_desc');
		var kw = (val('xg_focus_keyword') || '').trim().toLowerCase();

		var headings = (content.match(/<h[2-4][^>]*>(.*?)<\/h[2-4]>/gi) || []).join(' ').toLowerCase()
			+ ' ' + (content.match(/"level":[2-4]/g) || []).map(function () { return ''; }).join('');
		var h2 = (content.match(/<h2/gi) || []).length + (content.match(/"level":2/g) || []).length;
		var subs = h2 + (content.match(/<h3/gi) || []).length + (content.match(/"level":3/g) || []).length;
		var qHeads = (content.match(/<h[2-4][^>]*>[^<]*\?[^<]*<\/h[2-4]>/gi) || []).length;
		var intLinks = (content.match(/href="\/[^"]*"/g) || []).length + (content.match(/href="https?:\/\/[^"]*xgoud/gi) || []).length;
		var extLinks = (content.match(/href="https?:\/\/(?![^"]*xgoud)[^"]*"/gi) || []).length;
		var imgs = (content.match(/<img/gi) || []).length;
		var imgAlts = (content.match(/<img[^>]*alt="[^"]+"[^>]*>/gi) || []);
		var imgsNoAlt = imgs - imgAlts.length;
		var first10 = lc.slice(0, Math.max(120, Math.floor(lc.length * 0.1)));
		var kwCount = kw ? countOcc(lc, kw) : 0;
		var density = (kw && words) ? (kwCount / words * 100) : 0;
		var paras = plain.split(/(?:\.\s){3,}|\n{2,}/);
		var longPara = (content.match(/<p[^>]*>([\s\S]{700,}?)<\/p>/gi) || []).length;
		var hasGeo = CITIES.some(function (c) { return lc.indexOf(c) !== -1; });
		var hasSchema = /wp:ekinese\/(faq|offices|price)/.test(content) || /FAQPage|LocalBusiness|wp:ekinese\/seo-index/.test(content);
		var hasMedia = imgs > 0 || /<iframe|wp:video|wp:embed|xg-lyt/.test(content);
		var hasNumberTitle = /\d/.test(seoTitle);
		var hasPower = POWER.some(function (p) { return seoTitle.toLowerCase().indexOf(p) !== -1; });
		var titleStartsKw = kw && seoTitle.toLowerCase().trim().indexOf(kw) === 0;
		var directAnswer = /<p[^>]*>[\s\S]{40,300}?<\/p>/i.test(content.replace(/<!--[\s\S]*?-->/g, ''));

		// Groepen: [label, pass, info, weight]
		var groups = {
			'Basis SEO': kw ? [
				['Focus-keyword in SEO-titel', seoTitle.toLowerCase().indexOf(kw) !== -1, '', 4],
				['Focus-keyword in meta description', metaDesc.toLowerCase().indexOf(kw) !== -1, '', 3],
				['Focus-keyword in de URL (slug)', (slug || '').toLowerCase().indexOf(kw.replace(/\s+/g, '-')) !== -1 || (slug || '').toLowerCase().indexOf(kw.split(' ')[0]) !== -1, slug || '(nog geen slug)', 3],
				['Focus-keyword in eerste 10% inhoud', first10.indexOf(kw) !== -1, '', 3],
				['Focus-keyword in de inhoud', kwCount > 0, kwCount + '×', 3],
				['Inhoud ≥ 600 woorden', words >= 600, words + ' woorden', 3]
			] : [
				['Stel een focus-keyword in (SEO-veld)', false, 'zonder keyword geen keyword-checks', 6],
				['Inhoud ≥ 600 woorden', words >= 600, words + ' woorden', 3]
			],
			'Extra': [
				['Keyword in tussenkop(pen)', kw ? headings.indexOf(kw) !== -1 : false, '', 2],
				['Keyword in afbeelding-alt', kw ? imgAlts.join(' ').toLowerCase().indexOf(kw) !== -1 : false, '', 2],
				['Keyword-dichtheid 0,5–2,5%', kw ? (density >= 0.5 && density <= 2.5) : false, kw ? density.toFixed(2) + '%' : '—', 2],
				['URL ≤ 75 tekens', (slug || '').length <= 75 && (slug || '').length > 0, (slug || '').length + ' tekens', 1],
				['Externe link aanwezig', extLinks >= 1, extLinks + '×', 1],
				['Interne link aanwezig', intLinks >= 1, intLinks + '×', 2],
				['Alle afbeeldingen alt-tekst', imgs === 0 || imgsNoAlt === 0, imgsNoAlt + ' zonder alt', 2]
			],
			'Leesbaarheid titel': [
				['SEO-titel 30–60 tekens', seoTitle.length >= 30 && seoTitle.length <= 60, seoTitle.length + ' tekens', 2],
				['Keyword aan het begin van de titel', !!titleStartsKw, '', 1],
				['Titel bevat een krachtterm', hasPower, '', 1],
				['Titel bevat een getal', hasNumberTitle, '', 1]
			],
			'Leesbaarheid inhoud': [
				['Minstens 2 tussenkoppen', subs >= 2, subs + '×', 2],
				['Korte alinea\'s', longPara === 0, longPara + ' lange alinea(s)', 1],
				['Inhoud bevat afbeelding/video', hasMedia, '', 1]
			],
			'GEO (AI-vindbaarheid)': [
				['Lokaal signaal (stad/regio)', hasGeo, hasGeo ? 'ja' : 'nee', 2],
				['Schema / FAQ / LocalBusiness', hasSchema, hasSchema ? 'ja' : 'nee', 3],
				['Direct antwoord vroeg in de tekst', directAnswer, '', 2],
				['Vraag-kop(pen) (people-also-ask)', qHeads >= 1, qHeads + '×', 2],
				['Meta description 70–160', metaDesc.length >= 70 && metaDesc.length <= 160, metaDesc.length + ' tekens', 1]
			]
		};

		// Scores: SEO = alles behalve GEO-groep; GEO = GEO-groep.
		function scoreOf(list) {
			var w = 0, p = 0;
			list.forEach(function (c) { w += c[3]; if (c[1]) p += c[3]; });
			return w ? Math.round(p / w * 100) : 0;
		}
		var seoList = [].concat(groups['Basis SEO'], groups['Extra'], groups['Leesbaarheid titel'], groups['Leesbaarheid inhoud']);
		var seoPct = scoreOf(seoList);
		var geoPct = scoreOf(groups['GEO (AI-vindbaarheid)']);

		box.innerHTML = renderScore(seoPct, geoPct, groups, kw);
		var g = box.querySelector('.xg-seo-guide-t');
		if (g) g.addEventListener('click', function () { var d = box.querySelector('.xg-seo-guide-b'); if (d) d.style.display = d.style.display === 'none' ? 'block' : 'none'; });
	}

	function ring(label, pct) {
		var color = pct >= 80 ? '#1f9d55' : pct >= 50 ? '#dba617' : '#d63638';
		return '<div style="flex:1;text-align:center"><div style="font-size:24px;font-weight:800;color:' + color + '">' + pct + '<span style="font-size:12px">/100</span></div>' +
			'<div style="height:6px;background:#e0e0e0;margin:5px 0"><span style="display:block;height:100%;width:' + pct + '%;background:' + color + '"></span></div>' +
			'<div style="font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.4px">' + label + '</div></div>';
	}
	function renderScore(seoPct, geoPct, groups, kw) {
		var html = '<div style="font-size:13px">';
		html += '<div style="display:flex;gap:12px;margin-bottom:12px">' + ring('SEO', seoPct) + ring('GEO', geoPct) + '</div>';
		if (!kw) html += '<p style="background:#fcf3d3;padding:6px 8px;margin:0 0 10px">💡 Vul een <strong>focus-keyword</strong> in (SEO-veld hierboven) voor de keyword-checks.</p>';
		Object.keys(groups).forEach(function (name) {
			var list = groups[name];
			var pass = list.filter(function (c) { return c[1]; }).length;
			html += '<div style="margin:10px 0 4px;font-weight:700;border-top:1px solid #eee;padding-top:8px">' + name + ' <span style="color:#646970;font-weight:400">(' + pass + '/' + list.length + ')</span></div>';
			html += '<ul style="margin:0;list-style:none">';
			list.forEach(function (c) {
				html += '<li style="padding:3px 0;display:flex;gap:6px"><span>' + (c[1] ? '✅' : '❌') + '</span><span style="flex:1">' + esc(c[0]) + (c[2] ? ' <span style="color:#646970">— ' + esc(c[2]) + '</span>' : '') + '</span></li>';
			});
			html += '</ul>';
		});
		html += '<div style="margin-top:12px;border-top:1px solid #eee;padding-top:8px">';
		html += '<button type="button" class="button-link xg-seo-guide-t" style="font-weight:700">📋 SEO/GEO-richtlijn tonen</button>';
		html += '<div class="xg-seo-guide-b" style="display:none;color:#50575e;font-size:12px;line-height:1.6;margin-top:8px">' + GUIDE + '</div></div>';
		html += '</div>';
		return html;
	}

	var GUIDE = [
		'<strong>SEO-doel: ≥ 80/100.</strong>',
		'1. <strong>Focus-keyword</strong>: kies één hoofdzoekterm (bv. “goud verkopen Utrecht”). Gebruik die in de titel (vooraan), URL, meta description, eerste alinea en 1–2 tussenkoppen.',
		'2. <strong>Titel</strong>: 30–60 tekens, keyword vooraan, een krachtterm (betrouwbaar, hoogste…) en een getal mag.',
		'3. <strong>Inhoud</strong>: ≥ 600 woorden, korte alinea’s, ≥ 2 tussenkoppen, minstens 1 interne + 1 externe link, alt-tekst op alle afbeeldingen.',
		'4. <strong>Dichtheid</strong>: keyword 0,5–2,5% — natuurlijk, niet stapelen.',
		'<strong>GEO (AI-vindbaarheid): ≥ 80/100.</strong>',
		'5. <strong>Lokaal</strong>: noem stad/regio waar relevant.',
		'6. <strong>Schema</strong>: voeg een FAQ-blok, kantoren- of prijsblok toe (gestructureerde data).',
		'7. <strong>Direct antwoord</strong>: beantwoord de hoofdvraag in 2–3 zinnen bovenaan (AI citeert die).',
		'8. <strong>Vraag-koppen</strong>: gebruik koppen als “Wat is mijn goud waard?” (people-also-ask).'
	].join('<br>');

	if (box && dataSel && window.wp.domReady) {
		wp.domReady(function () {
			analyze();
			var t;
			wp.data.subscribe(function () { clearTimeout(t); t = setTimeout(analyze, 800); });
			document.addEventListener('input', function (e) {
				if (e.target && /^xg_(seo_title|meta_desc|focus_keyword)$/.test(e.target.name || '')) analyze();
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
