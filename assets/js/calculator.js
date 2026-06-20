/**
 * XGOUD Smart Calculator – Wertberechnung & Verkauf.
 *
 * Performance-first: reines Vanilla JS, keine Frameworks, eine IIFE.
 *
 * DATENQUELLE:
 *   Aktuell aus window.XG_CALC_DATA (Mock, via wp_localize_script gesetzt).
 *   Spätere DB/API-Anbindung (Cron-Cache): NUR dieses Objekt wird durch
 *   echte Werte aus der REST-Route ersetzt – die Berechnungslogik bleibt gleich.
 *
 * PREIS-LOGIK:
 *   Edelmetalle → FIXPREIS  (Spot × Gewicht × Reinheit% − Marge%)
 *   Edelsteine  → INDIKATION (iDex-Basis ± Range%)   = VON–BIS
 *   Uhren       → INDIKATION (DB-Preis ± Range%)      = VON–BIS
 *   Schmuck     → Staffelpreis (mehr Gramm ⇒ höher €/g)
 *   Charity     → fester %-Anteil der Marge fließt in Charity-Topf
 */
(function () {
	'use strict';

	/* =========================================================
	   FALLBACK-MOCKDATEN
	   Werden von window.XG_CALC_DATA (PHP) überschrieben, sobald
	   vorhanden. Struktur = spätere DB-Tabellen.
	========================================================= */
	var DATA = window.XG_CALC_DATA || {
		// wp_xg_margins (LIVE editierbar im Admin)
		margins: {
			metal: 0.08,        // 8 % Marge auf Spot
			diamond_range: 0.10, // ±10 % Indikation
			gem_range: 0.12,
			watch_range: 0.10,
			charity_share: 0.05 // 5 % der Marge → Charity
		},
		// wp_xg_metals – Spotpreis €/g (Mock; später Swiss Forex Cron-Cache)
		metals: {
			goud:     { label: 'Goud',     spot: 62.50 },
			zilver:   { label: 'Zilver',   spot: 0.78 },
			platina:  { label: 'Platina',  spot: 28.90 },
			palladium:{ label: 'Palladium',spot: 30.10 }
		},
		// Reinheit (karaat → Faktor)
		purities: {
			'8':  0.333, '14': 0.585, '18': 0.750,
			'21': 0.875, '22': 0.916, '24': 0.999
		},
		// Zustand (nur Barren/Münzen)
		conditions: {
			nieuw:        { label: 'Neuwertig',    factor: 1.00 },
			zeer_goed:    { label: 'Sehr gut',     factor: 0.99 },
			goed:         { label: 'Gut',          factor: 0.98 },
			voldoende:    { label: 'Befriedigend', factor: 0.96 }
		},
		// Schmuck-Staffel: ab Gramm-Schwelle besserer €/g-Bonus
		jewelry_tiers: [
			{ min: 0,   bonus: 0.00 },
			{ min: 50,  bonus: 0.02 },
			{ min: 100, bonus: 0.04 },
			{ min: 250, bonus: 0.06 }
		],
		// wp_xg_diamonds – iDex-Basis €/ct (Mock-Matrix, vereinfacht)
		diamond_base: {
			// Basis €/ct nach Farbe (D..Z gruppiert) × Reinheit-Faktor
			color:   { D:1.00, E:0.95, F:0.90, G:0.82, H:0.74, I:0.66, J:0.58, K:0.48 },
			clarity: { FL:1.00, IF:0.95, VVS1:0.90, VVS2:0.86, VS1:0.80, VS2:0.74, SI1:0.64, SI2:0.54, I1:0.40, I2:0.30, I3:0.20 },
			cut:     { Excellent:1.00, 'Very Good':0.95, Good:0.88, Fair:0.78, Poor:0.65 },
			fluor:   { None:1.00, Faint:0.98, Medium:0.94, Strong:0.88 },
			anchor:  9000 // €/ct Referenz für D/FL/Excellent/None bei 1ct
		},
		// wp_xg_gemstones – Basis €/ct
		gem_base: {
			robijn:   { label: 'Robijn (Rubin)',   anchor: 3500 },
			saffier:  { label: 'Saffier (Saphir)', anchor: 2200 },
			smaragd:  { label: 'Smaragd',          anchor: 2800 }
		},
		// wp_xg_watches – Marktpreis € (Mock; später eigene DB)
		watches: {
			rolex:    { label: 'Rolex',    models: { 'Submariner': 11000, 'Datejust': 7500, 'GMT-Master II': 14000 } },
			omega:    { label: 'Omega',    models: { 'Speedmaster': 5500, 'Seamaster': 4200 } },
			patek:    { label: 'Patek Philippe', models: { 'Nautilus': 38000, 'Calatrava': 18000 } },
			cartier:  { label: 'Cartier',  models: { 'Santos': 6500, 'Tank': 4800 } }
		},
		watch_conditions: {
			nieuw:     { label: 'Neuwertig',          factor: 1.00 },
			zeer_goed: { label: 'Sehr gut',           factor: 0.90 },
			goed:      { label: 'Gut',                factor: 0.78 },
			voldoende: { label: 'Befriedigend',       factor: 0.65 },
			service:   { label: 'Restaurierungsbedarf',factor: 0.50 }
		},
		watch_extras: {
			box:  { label: 'Originalbox',  bonus: 0.03 },
			papers:{ label: 'Zertifikat/Papiere', bonus: 0.05 }
		},
		// wp_xg_charity – Projekte
		charity_projects: [
			{ id: 'social',     label: 'Sozialarbeit' },
			{ id: 'kindergarten', label: 'Kindergärten' },
			{ id: 'shelter',    label: 'Frauenhäuser' },
			{ id: 'sport',      label: 'Sportzentren' },
			{ id: 'school',     label: 'Schulen' }
		],
		currency: 'EUR'
	};

	/* =========================================================
	   HELFER
	========================================================= */
	function euro(n) {
		return new Intl.NumberFormat('nl-NL', {
			style: 'currency', currency: DATA.currency || 'EUR',
			maximumFractionDigits: 2
		}).format(isFinite(n) ? n : 0);
	}
	function pct(n) { return (n * 100).toFixed(1).replace('.', ',') + ' %'; }
	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (html != null) e.innerHTML = html;
		return e;
	}
	function opt(value, label) {
		var o = document.createElement('option');
		o.value = value; o.textContent = label;
		return o;
	}

	/* =========================================================
	   BERECHNUNGEN  (geben einheitliches Result-Objekt zurück)
	   result = { market, marginPct, marginAbs, charity, payoutLow, payoutHigh, indicative }
	========================================================= */
	function calcMetal(form) {
		var metal = DATA.metals[form.metal];
		var purity = DATA.purities[form.karaat] || 0;
		var weight = parseFloat(form.weight) || 0;
		var market = metal.spot * weight * purity;

		// Zustand nur bei Barren/Münzen
		var condFactor = 1;
		if (form.shape !== 'sieraad' && form.condition && DATA.conditions[form.condition]) {
			condFactor = DATA.conditions[form.condition].factor;
		}
		// Schmuck-Staffel
		var tierBonus = 0;
		if (form.shape === 'sieraad') {
			DATA.jewelry_tiers.forEach(function (t) { if (weight >= t.min) tierBonus = t.bonus; });
		}
		market = market * condFactor;
		var marginPct = DATA.margins.metal - tierBonus; // Staffel reduziert Marge ⇒ mehr €/g
		if (marginPct < 0) marginPct = 0;
		var marginAbs = market * marginPct;
		var payout = market - marginAbs;
		var charity = marginAbs * DATA.margins.charity_share;

		return {
			market: market, marginPct: marginPct, marginAbs: marginAbs,
			charity: charity, payoutLow: payout, payoutHigh: payout,
			indicative: false
		};
	}

	function calcDiamond(form) {
		var b = DATA.diamond_base;
		var carat = parseFloat(form.carat) || 0;
		var f = (b.color[form.color] || 0) * (b.clarity[form.clarity] || 0) *
			(b.cut[form.cut] || 0) * (b.fluor[form.fluor] || 1);
		var market = b.anchor * f * carat;
		return indicative(market, DATA.margins.diamond_range);
	}

	function calcGem(form) {
		var g = DATA.gem_base[form.gem];
		var carat = parseFloat(form.carat) || 0;
		// vereinfachte Qualitäts-Skala 1..5
		var q = (parseFloat(form.quality) || 3) / 5;
		var market = g.anchor * carat * q;
		return indicative(market, DATA.margins.gem_range);
	}

	function calcWatch(form) {
		var brand = DATA.watches[form.brand];
		var base = brand && brand.models[form.model] ? brand.models[form.model] : 0;
		var cf = DATA.watch_conditions[form.condition] ? DATA.watch_conditions[form.condition].factor : 1;
		var bonus = 0;
		if (form.box) bonus += DATA.watch_extras.box.bonus;
		if (form.papers) bonus += DATA.watch_extras.papers.bonus;
		var market = base * cf * (1 + bonus);
		return indicative(market, DATA.margins.watch_range);
	}

	// Indikationspreis von–bis (Edelsteine + Uhren)
	function indicative(market, range) {
		var marginPct = range;
		var marginAbs = market * marginPct;
		var mid = market - marginAbs;
		var low = market * (1 - range - 0.03);
		var high = market * (1 - range + 0.03);
		var charity = marginAbs * DATA.margins.charity_share;
		return {
			market: market, marginPct: marginPct, marginAbs: marginAbs,
			charity: charity, payoutLow: low, payoutHigh: high,
			indicative: true
		};
	}

	/* =========================================================
	   WARENKORB  (quotes → später wp_xg_quotes → Terminplaner)
	========================================================= */
	var cart = [];

	function addToCart(entry) {
		cart.push(entry);
		renderCart();
	}
	function removeFromCart(idx) {
		cart.splice(idx, 1);
		renderCart();
	}

	/* =========================================================
	   UI: FELD-DEFINITIONEN PRO PRODUKTTYP
	========================================================= */
	function buildMetalFields(wrap) {
		var grid = el('div', 'xg-calc-grid');

		var mSel = el('select', 'xg-calc-input'); mSel.name = 'metal';
		Object.keys(DATA.metals).forEach(function (k) { mSel.appendChild(opt(k, DATA.metals[k].label)); });
		grid.appendChild(field('Metall', mSel));

		var kSel = el('select', 'xg-calc-input'); kSel.name = 'karaat';
		Object.keys(DATA.purities).forEach(function (k) { kSel.appendChild(opt(k, k + ' karaat')); });
		grid.appendChild(field('Reinheit', kSel));

		var shape = el('select', 'xg-calc-input'); shape.name = 'shape';
		shape.appendChild(opt('barren', 'Barren'));
		shape.appendChild(opt('munt', 'Münze'));
		shape.appendChild(opt('sieraad', 'Gold-Schmuckstücke'));
		grid.appendChild(field('Form', shape));

		var w = el('input', 'xg-calc-input'); w.type = 'number'; w.name = 'weight';
		w.min = '0'; w.step = '0.1'; w.placeholder = 'Gewicht in g';
		grid.appendChild(field('Gewicht (g)', w));

		var condWrap = field('Zustand', (function () {
			var c = el('select', 'xg-calc-input'); c.name = 'condition';
			Object.keys(DATA.conditions).forEach(function (k) { c.appendChild(opt(k, DATA.conditions[k].label)); });
			return c;
		})());
		grid.appendChild(condWrap);

		// Zustand bei Schmuck ausblenden
		shape.addEventListener('change', function () {
			condWrap.style.display = (shape.value === 'sieraad') ? 'none' : '';
		});

		wrap.appendChild(grid);
		return function () {
			return {
				metal: mSel.value, karaat: kSel.value, shape: shape.value,
				weight: w.value, condition: condWrap.style.display === 'none' ? null : condWrap.querySelector('select').value
			};
		};
	}

	function buildDiamondFields(wrap) {
		var grid = el('div', 'xg-calc-grid');
		var b = DATA.diamond_base;

		var carat = el('input', 'xg-calc-input'); carat.type = 'number';
		carat.name = 'carat'; carat.min = '0'; carat.step = '0.01'; carat.placeholder = 'ct';
		grid.appendChild(field('Karatgewicht (ct)', carat));

		grid.appendChild(field('Farbe', selectFrom('color', Object.keys(b.color))));
		grid.appendChild(field('Reinheit', selectFrom('clarity', Object.keys(b.clarity))));
		grid.appendChild(field('Schliff', selectFrom('cut', Object.keys(b.cut))));
		grid.appendChild(field('Fluoreszenz', selectFrom('fluor', Object.keys(b.fluor))));

		wrap.appendChild(grid);
		return function () {
			return {
				carat: carat.value,
				color: grid.querySelector('[name=color]').value,
				clarity: grid.querySelector('[name=clarity]').value,
				cut: grid.querySelector('[name=cut]').value,
				fluor: grid.querySelector('[name=fluor]').value
			};
		};
	}

	function buildGemFields(wrap) {
		var grid = el('div', 'xg-calc-grid');
		var g = el('select', 'xg-calc-input'); g.name = 'gem';
		Object.keys(DATA.gem_base).forEach(function (k) { g.appendChild(opt(k, DATA.gem_base[k].label)); });
		grid.appendChild(field('Edelstein', g));

		var carat = el('input', 'xg-calc-input'); carat.type = 'number';
		carat.name = 'carat'; carat.min = '0'; carat.step = '0.01'; carat.placeholder = 'ct';
		grid.appendChild(field('Karatgewicht (ct)', carat));

		var q = el('select', 'xg-calc-input'); q.name = 'quality';
		[['5','Exzellent'],['4','Sehr gut'],['3','Gut'],['2','Mittel'],['1','Einfach']]
			.forEach(function (p) { q.appendChild(opt(p[0], p[1])); });
		grid.appendChild(field('Qualität', q));

		wrap.appendChild(grid);
		return function () {
			return { gem: g.value, carat: carat.value, quality: q.value };
		};
	}

	function buildWatchFields(wrap) {
		var grid = el('div', 'xg-calc-grid');

		var brand = el('select', 'xg-calc-input'); brand.name = 'brand';
		Object.keys(DATA.watches).forEach(function (k) { brand.appendChild(opt(k, DATA.watches[k].label)); });
		grid.appendChild(field('Marke', brand));

		var model = el('select', 'xg-calc-input'); model.name = 'model';
		function fillModels() {
			model.innerHTML = '';
			Object.keys(DATA.watches[brand.value].models).forEach(function (m) { model.appendChild(opt(m, m)); });
		}
		fillModels();
		brand.addEventListener('change', fillModels);
		grid.appendChild(field('Modell', model));

		var cond = el('select', 'xg-calc-input'); cond.name = 'condition';
		Object.keys(DATA.watch_conditions).forEach(function (k) { cond.appendChild(opt(k, DATA.watch_conditions[k].label)); });
		grid.appendChild(field('Zustand', cond));

		var extras = el('div', 'xg-calc-checks');
		var box = checkbox('box', DATA.watch_extras.box.label);
		var papers = checkbox('papers', DATA.watch_extras.papers.label);
		extras.appendChild(box.wrap); extras.appendChild(papers.wrap);
		grid.appendChild(field('Zubehör', extras));

		wrap.appendChild(grid);
		return function () {
			return {
				brand: brand.value, model: model.value, condition: cond.value,
				box: box.input.checked, papers: papers.input.checked
			};
		};
	}

	function selectFrom(name, keys) {
		var s = el('select', 'xg-calc-input'); s.name = name;
		keys.forEach(function (k) { s.appendChild(opt(k, k)); });
		return s;
	}
	function field(label, control) {
		var f = el('div', 'xg-calc-field');
		f.appendChild(el('label', 'xg-calc-label', label));
		f.appendChild(control);
		return f;
	}
	function checkbox(name, label) {
		var wrap = el('label', 'xg-calc-check');
		var input = el('input'); input.type = 'checkbox'; input.name = name;
		wrap.appendChild(input);
		wrap.appendChild(document.createTextNode(' ' + label));
		return { wrap: wrap, input: input };
	}

	/* =========================================================
	   UI: RESULTAT-KARTE
	========================================================= */
	function renderResult(container, r, typeLabel) {
		container.innerHTML = '';
		var card = el('div', 'xg-calc-result-card');

		var payout = r.indicative
			? euro(r.payoutLow) + ' – ' + euro(r.payoutHigh)
			: euro(r.payoutLow);

		card.appendChild(el('div', 'xg-calc-result-head',
			'<span class="xg-calc-result-type">' + typeLabel + '</span>' +
			(r.indicative ? '<span class="xg-calc-badge">Preisindikation</span>'
			              : '<span class="xg-calc-badge xg-calc-badge-fix">Festpreis</span>')
		));

		card.appendChild(el('div', 'xg-calc-payout',
			'<span class="xg-calc-payout-label">Ankaufspreis</span>' +
			'<span class="xg-calc-payout-value">' + payout + '</span>'
		));

		var rows = el('div', 'xg-calc-breakdown');
		rows.appendChild(breakRow('Marktpreis', euro(r.market)));
		rows.appendChild(breakRow('Marge', pct(r.marginPct) + ' · ' + euro(r.marginAbs)));
		rows.appendChild(breakRow('Differenz Markt ↔ Ankauf', euro(r.market - r.payoutLow), 'xg-calc-diff'));
		rows.appendChild(breakRow('♥ Charity-Anteil', euro(r.charity), 'xg-calc-charity'));
		card.appendChild(rows);

		// Charity-Wahl
		var charityWrap = el('div', 'xg-calc-charity-pick');
		charityWrap.appendChild(el('label', 'xg-calc-label', 'Wohin soll Ihr Charity-Anteil fließen?'));
		var cSel = el('select', 'xg-calc-input'); cSel.name = 'charity_project';
		DATA.charity_projects.forEach(function (p) { cSel.appendChild(opt(p.id, p.label)); });
		charityWrap.appendChild(cSel);
		card.appendChild(charityWrap);

		// Aktionen
		var actions = el('div', 'xg-calc-actions');
		var addBtn = el('button', 'xg-btn-gold', 'Zum Verkauf hinzufügen');
		addBtn.type = 'button';
		addBtn.addEventListener('click', function () {
			addToCart({
				type: typeLabel, payout: payout, charity: r.charity,
				charityProject: cSel.options[cSel.selectedIndex].text,
				market: r.market, marginAbs: r.marginAbs, indicative: r.indicative
			});
		});
		actions.appendChild(addBtn);
		card.appendChild(actions);

		container.appendChild(card);
	}
	function breakRow(label, value, cls) {
		return el('div', 'xg-calc-break-row' + (cls ? ' ' + cls : ''),
			'<span>' + label + '</span><span>' + value + '</span>');
	}

	/* =========================================================
	   UI: WARENKORB-RENDER
	========================================================= */
	function renderCart() {
		var box = document.querySelector('.xg-calc-cart');
		if (!box) return;
		box.innerHTML = '';
		if (!cart.length) {
			box.appendChild(el('p', 'xg-calc-cart-empty', 'Noch keine Produkte ausgewählt.'));
			return;
		}
		box.appendChild(el('h3', 'xg-calc-cart-title', 'Ihre Auswahl'));

		var totalCharity = 0;
		cart.forEach(function (item, i) {
			totalCharity += item.charity || 0;
			var row = el('div', 'xg-calc-cart-item');
			row.appendChild(el('div', 'xg-calc-cart-info',
				'<strong>' + item.type + '</strong>' +
				'<span>' + item.payout + '</span>' +
				'<small>♥ ' + euro(item.charity) + ' → ' + item.charityProject + '</small>'
			));
			var del = el('button', 'xg-calc-cart-del', '✕');
			del.type = 'button';
			del.addEventListener('click', function () { removeFromCart(i); });
			row.appendChild(del);
			box.appendChild(row);
		});

		box.appendChild(el('div', 'xg-calc-cart-total',
			'Gesamt-Charity dieser Auswahl: <strong>' + euro(totalCharity) + '</strong>'));

		var toPlanner = el('button', 'xg-btn-gold xg-calc-to-planner', 'Termin vereinbaren & verkaufen');
		toPlanner.type = 'button';
		toPlanner.addEventListener('click', function () {
			// Übergabe an Terminplaner (später: POST → wp_xg_appointments)
			document.dispatchEvent(new CustomEvent('xg:cart-to-planner', { detail: { cart: cart } }));
			var planner = document.querySelector('.xg-planner');
			if (planner) planner.scrollIntoView({ behavior: 'smooth' });
		});
		box.appendChild(toPlanner);
	}

	/* =========================================================
	   INIT: Calculator-Widget aufbauen
	========================================================= */
	function initCalculator(root) {
		var typeNav = root.querySelector('.xg-calc-types');
		var fieldsWrap = root.querySelector('.xg-calc-fields');
		var resultWrap = root.querySelector('.xg-calc-result');
		if (!typeNav || !fieldsWrap || !resultWrap) return;

		var types = [
			{ key: 'metal',   label: 'Edelmetall', build: buildMetalFields, calc: calcMetal },
			{ key: 'diamond', label: 'Diamant',    build: buildDiamondFields, calc: calcDiamond },
			{ key: 'gem',     label: 'Edelstein',  build: buildGemFields,  calc: calcGem },
			{ key: 'watch',   label: 'Uhr',        build: buildWatchFields, calc: calcWatch }
		];

		var active = null, getValues = null;

		function selectType(t, btn) {
			active = t;
			Array.prototype.forEach.call(typeNav.children, function (c) { c.classList.remove('active'); });
			if (btn) btn.classList.add('active');
			fieldsWrap.innerHTML = '';
			resultWrap.innerHTML = '';
			getValues = t.build(fieldsWrap);
			bindLive();
			recalc();
		}

		function recalc() {
			if (!active || !getValues) return;
			var r = active.calc(getValues());
			renderResult(resultWrap, r, active.label);
		}

		function bindLive() {
			fieldsWrap.querySelectorAll('input, select').forEach(function (i) {
				i.addEventListener('input', recalc);
				i.addEventListener('change', recalc);
			});
		}

		// Typ-Buttons
		types.forEach(function (t) {
			var btn = el('button', 'xg-calc-type-btn', t.label);
			btn.type = 'button';
			btn.addEventListener('click', function () { selectType(t, btn); });
			typeNav.appendChild(btn);
		});

		// Default: erster Typ
		selectType(types[0], typeNav.children[0]);
		renderCart();
	}

	function boot() {
		document.querySelectorAll('.xg-calc').forEach(initCalculator);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
