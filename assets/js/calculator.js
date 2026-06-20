/**
 * XGOUD Smart Calculator – geführter Wizard.
 *
 * Konzept: Der Kunde wird zu seinem Produkt GELEITET (Karten-Auswahl,
 * Schritt für Schritt) statt sich durch Dropdown-Felder zu arbeiten.
 * Calculator UND Warenkorb stecken in EINEM Widget (Tab-Umschaltung).
 *
 * Flow:
 *   Typ → Detail → Resultat (live) → Warenkorb
 *      → Checkout: Daten → Service → Auszahlung → Charity-Empfänger → Danke
 *
 * Design: reines Vanilla JS, keine Frameworks. Light/Dark über data-theme.
 *
 * DATENQUELLE: window.XG_CALC_DATA (Mock via wp_localize_script).
 * Struktur = spätere DB-Tabellen → Cron-Cache-Swap ohne JS-Änderung.
 */
(function () {
	'use strict';

	/* =====================================================================
	   ICONS (inline SVG, kein externes Icon-Set)
	===================================================================== */
	var ICON = {
		metal:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8l3-4h12l3 4-9 12L3 8z"/><path d="M3 8h18M9 4l3 4 3-4M12 8v12"/></svg>',
		diamond: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h12l3 6-9 12L3 9l3-6z"/><path d="M3 9h18M9 3L7 9l5 12 5-12-2-6"/></svg>',
		gem:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="13" r="7"/><path d="M8 4h8l-2 4h-4L8 4zM12 9v8M8 13h8"/></svg>',
		watch:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="6"/><path d="M12 9v3l2 1M9 2h6M9 22h6"/></svg>',
		search:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>',
		cart:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2 13h11l2-9H6"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>',
		check:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 13l4 4L19 7"/></svg>',
		heart:   '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7-4.6-9.5-9C1 9 2.5 5.5 6 5.5c2 0 3.2 1.3 4 2.5.8-1.2 2-2.5 4-2.5 3.5 0 5 3.5 3.5 6.5C19 16.4 12 21 12 21z"/></svg>',
		home:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10z"/></svg>',
		office:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 21V9h4a1 1 0 0 1 1 1v11M2 21h20M7 8h2M7 12h2M7 16h2"/></svg>',
		truck:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 5h11v11H2zM13 8h4l3 3v5h-7M6 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>'
	};

	/* =====================================================================
	   DATEN (Fallback; window.XG_CALC_DATA überschreibt)
	===================================================================== */
	var DATA = window.XG_CALC_DATA || {};

	/* =====================================================================
	   HELFER
	===================================================================== */
	function euro(n) {
		return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: DATA.currency || 'EUR', maximumFractionDigits: 0 }).format(isFinite(n) ? n : 0);
	}
	function euro2(n) {
		return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: DATA.currency || 'EUR', maximumFractionDigits: 2 }).format(isFinite(n) ? n : 0);
	}
	function pct(n) { return (n * 100).toFixed(1).replace('.', ',') + ' %'; }
	function h(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
	function opt(v, l) { var o = document.createElement('option'); o.value = v; o.textContent = l; return o; }

	// Preis-Count-up Animation
	function animateValue(node, to, fmt) {
		var from = 0, start = null, dur = 600;
		function frame(t) {
			if (!start) start = t;
			var p = Math.min((t - start) / dur, 1);
			var eased = 1 - Math.pow(1 - p, 3);
			node.textContent = fmt(from + (to - from) * eased);
			if (p < 1) requestAnimationFrame(frame);
		}
		requestAnimationFrame(frame);
	}

	/* =====================================================================
	   BERECHNUNGEN
	   result = { market, marginPct, marginAbs, charity, low, high, indicative }
	===================================================================== */
	function calcMetal(f) {
		var metal = DATA.metals[f.metal];
		if (!metal) return null;
		var purity = (DATA.metal_purities[f.metal] && DATA.metal_purities[f.metal][f.purity]) || 0;
		var weight = parseFloat(f.weight) || 0;
		var market = metal.spot * weight * purity;

		var condFactor = 1, tierBonus = 0;
		if (f.form !== 'sieraad' && f.condition && DATA.conditions[f.condition]) {
			condFactor = DATA.conditions[f.condition].factor;
		}
		if (f.form === 'sieraad') {
			DATA.jewelry_tiers.forEach(function (t) { if (weight >= t.min) tierBonus = t.bonus; });
		}
		market *= condFactor;
		var marginPct = Math.max(DATA.margins.metal - tierBonus, 0);
		var marginAbs = market * marginPct;
		var payout = market - marginAbs;
		return { market: market, marginPct: marginPct, marginAbs: marginAbs, charity: marginAbs * DATA.margins.charity_share, low: payout, high: payout, indicative: false };
	}
	function calcDiamond(f) {
		var b = DATA.diamond_base, carat = parseFloat(f.carat) || 0;
		var factor = (b.color[f.color] || 0) * (b.clarity[f.clarity] || 0) * (b.cut[f.cut] || 0) * (b.fluor[f.fluor] || 1);
		return indicative(b.anchor * factor * carat, DATA.margins.diamond_range);
	}
	function calcGem(f) {
		var g = DATA.gem_base[f.gem]; if (!g) return null;
		var carat = parseFloat(f.carat) || 0, q = (parseFloat(f.quality) || 3) / 5;
		return indicative(g.anchor * carat * q, DATA.margins.gem_range);
	}
	function calcWatch(f) {
		var brand = DATA.watches[f.brand];
		var base = brand && brand.models[f.model] ? brand.models[f.model] : 0;
		var cf = DATA.watch_conditions[f.condition] ? DATA.watch_conditions[f.condition].factor : 1;
		var bonus = 0;
		if (f.box) bonus += DATA.watch_extras.box.bonus;
		if (f.papers) bonus += DATA.watch_extras.papers.bonus;
		return indicative(base * cf * (1 + bonus), DATA.margins.watch_range);
	}
	function indicative(market, range) {
		var marginAbs = market * range;
		return {
			market: market, marginPct: range, marginAbs: marginAbs,
			charity: marginAbs * DATA.margins.charity_share,
			low: market * (1 - range - 0.03), high: market * (1 - range + 0.03),
			indicative: true
		};
	}

	/* =====================================================================
	   WIDGET-INSTANZ
	===================================================================== */
	function Calculator(root) {
		var mode = root.getAttribute('data-mode') || 'full';
		var state = {
			view: 'calc',          // calc | cart | checkout
			type: null,            // metal | diamond | gem | watch
			subStep: 0,            // innerhalb des Typs
			form: {},
			result: null,
			cart: [],
			checkout: { step: 0, data: {}, service: null, payout: null, charity: null }
		};

		// Grundgerüst
		root.innerHTML = '';
		var head = h('div', 'xg-calc-head');
		head.appendChild(liveBlock());
		head.appendChild(themeToggle());
		root.appendChild(head);

		var tabs = h('div', 'xg-calc-tabs');
		var tabCalc = h('button', 'xg-calc-tab active', ICON.search + ' Berechnen');
		var tabCart = h('button', 'xg-calc-tab', ICON.cart + ' <span>Auswahl</span> <span class="xg-calc-tab-badge" style="display:none">0</span>');
		tabs.appendChild(tabCalc); tabs.appendChild(tabCart);
		root.appendChild(tabs);

		var progress = h('div', 'xg-calc-progress');
		root.appendChild(progress);

		var stage = h('div', 'xg-calc-stage');
		root.appendChild(stage);

		tabCalc.addEventListener('click', function () { state.view = 'calc'; render(); });
		tabCart.addEventListener('click', function () { if (state.cart.length || state.view === 'checkout') { state.view = 'cart'; render(); } });

		/* ---------- LIVE / THEME ---------- */
		function liveBlock() {
			var b = h('div', 'xg-calc-live', '<span class="xg-calc-live-dot"></span> Live-Preise');
			var t = h('span', 'xg-calc-live-time');
			function tick() {
				var d = new Date();
				t.innerHTML = '&middot; <strong>' + d.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '</strong> CET';
			}
			tick(); setInterval(tick, 30000);
			b.appendChild(t);
			return b;
		}
		function themeToggle() {
			// Nutzt den globalen XGOUD-Switch (Sync + Persistenz über theme.js).
			var host = h('div');
			host.setAttribute('data-xg-theme-toggle', '');
			if (window.XGTheme && window.XGTheme.mount) {
				window.XGTheme.mount(host);
			} else {
				// Fallback: einfacher Toggle, falls theme.js nicht geladen ist.
				var b = h('button', '', '◐'); b.type = 'button';
				b.addEventListener('click', function () {
					var d = document.documentElement;
					d.setAttribute('data-theme', d.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
				});
				host.appendChild(b);
			}
			return host;
		}

		/* ---------- PROGRESS ---------- */
		function renderProgress() {
			progress.innerHTML = '';
			var steps, idx;
			if (state.view === 'checkout') {
				steps = ['Daten', 'Service', 'Auszahlung', 'Charity', 'Fertig'];
				idx = state.checkout.step;
			} else {
				steps = ['Produkt', 'Details', 'Resultat'];
				idx = !state.type ? 0 : (state.result ? 2 : 1);
			}
			var line = h('div', 'xg-calc-prog-line');
			var fill = h('div', 'xg-calc-prog-fill');
			fill.style.width = (idx / (steps.length - 1) * 100) + '%';
			line.appendChild(fill); progress.appendChild(line);

			var row = h('div', 'xg-calc-prog-steps');
			steps.forEach(function (s, i) {
				var st = h('div', 'xg-calc-pstep' + (i === idx ? ' active' : i < idx ? ' done' : ''));
				st.appendChild(h('div', 'xg-calc-pstep-num', i < idx ? '✓' : (i + 1)));
				st.appendChild(h('div', 'xg-calc-pstep-lbl', s));
				row.appendChild(st);
			});
			progress.appendChild(row);
		}

		/* ---------- PANEL-WECHSEL mit Animation ---------- */
		function setPanel(node) {
			var old = stage.querySelector('.xg-calc-panel, .xg-calc-cart, .xg-calc-checkout');
			if (old) { old.classList.add('out'); setTimeout(function () { mount(); }, 180); }
			else mount();
			function mount() { stage.innerHTML = ''; stage.appendChild(node); }
		}

		/* =================================================================
		   RENDER (Router)
		================================================================= */
		function render() {
			tabCalc.classList.toggle('active', state.view === 'calc');
			tabCart.classList.toggle('active', state.view !== 'calc');
			updateBadge();
			renderProgress();
			if (state.view === 'calc') renderCalc();
			else if (state.view === 'cart') renderCart();
			else renderCheckout();
		}

		function updateBadge() {
			var badge = tabCart.querySelector('.xg-calc-tab-badge');
			if (state.cart.length) {
				badge.style.display = '';
				if (badge.textContent !== String(state.cart.length)) {
					badge.textContent = state.cart.length;
					badge.classList.remove('bump'); void badge.offsetWidth; badge.classList.add('bump');
				}
			} else { badge.style.display = 'none'; }
		}

		/* =================================================================
		   CALC-VIEW
		================================================================= */
		function renderCalc() {
			if (!state.type) return renderTypePicker();
			if (state.result) return renderResult();
			renderDetails();
		}

		// Step 1: Produkt-Typ
		function renderTypePicker() {
			var p = h('div', 'xg-calc-panel');
			p.appendChild(panelHead('Was möchten Sie verkaufen?', 'Wählen Sie eine Kategorie – wir führen Sie zum Preis.'));
			p.appendChild(findBox());

			var types = [
				{ k: 'metal',   t: 'Edelmetall', d: 'Gold, Silber, Platin, Palladium' },
				{ k: 'diamond', t: 'Diamant',    d: 'Lose Steine & Schmuck' },
				{ k: 'gem',     t: 'Edelstein',  d: 'Rubin, Saphir, Smaragd' },
				{ k: 'watch',   t: 'Uhr',        d: 'Luxusuhren aller Marken' }
			];
			var grid = h('div', 'xg-calc-opts');
			types.forEach(function (ty) {
				grid.appendChild(optCard(ICON[ty.k], ty.t, ty.d, function () {
					state.type = ty.k; state.form = {}; state.subStep = 0; state.result = null; render();
				}));
			});
			p.appendChild(grid);
			setPanel(p);
		}

		// Step 2: Details (geführt – Leitwert als Karten, Rest kompakt)
		function renderDetails() {
			var p = h('div', 'xg-calc-panel');
			var titles = { metal: 'Ihr Edelmetall', diamond: 'Ihr Diamant', gem: 'Ihr Edelstein', watch: 'Ihre Uhr' };
			p.appendChild(panelHead(titles[state.type], 'Angaben ergänzen – der Preis berechnet sich live.', true));

			var body = h('div');
			if (state.type === 'metal') buildMetal(body);
			else if (state.type === 'diamond') buildDiamond(body);
			else if (state.type === 'gem') buildGem(body);
			else if (state.type === 'watch') buildWatch(body);
			p.appendChild(body);

			var actions = h('div', 'xg-calc-next-wrap');
			var btn = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Preis berechnen');
			btn.addEventListener('click', function () {
				var r = compute();
				if (r) { state.result = r; render(); }
			});
			actions.appendChild(btn);
			p.appendChild(actions);
			setPanel(p);
		}

		function compute() {
			if (state.type === 'metal') return calcMetal(state.form);
			if (state.type === 'diamond') return calcDiamond(state.form);
			if (state.type === 'gem') return calcGem(state.form);
			if (state.type === 'watch') return calcWatch(state.form);
			return null;
		}

		/* ---------- FELD-BUILDER ---------- */
		function field(label, control, span, hint) {
			var f = h('div', 'xg-calc-field' + (span ? ' span-2' : ''));
			var l = h('label', 'xg-calc-label', label);
			if (hint) l.appendChild(h('small', '', ' · ' + hint));
			f.appendChild(l); f.appendChild(control);
			return f;
		}
		function sel(name, entries, onchange) {
			var s = h('select', 'xg-calc-input'); s.name = name;
			entries.forEach(function (e) { s.appendChild(opt(e[0], e[1])); });
			s.addEventListener('change', function () { state.form[name] = s.value; if (onchange) onchange(); });
			state.form[name] = s.value;
			return s;
		}
		function num(name, ph, step) {
			var i = h('input', 'xg-calc-input'); i.type = 'number'; i.name = name;
			i.min = '0'; i.step = step || '0.1'; i.placeholder = ph;
			i.addEventListener('input', function () { state.form[name] = i.value; });
			return i;
		}
		function text(name, ph) {
			var i = h('input', 'xg-calc-input'); i.type = 'text'; i.name = name; i.placeholder = ph;
			i.addEventListener('input', function () { state.form[name] = i.value; });
			return i;
		}
		// Range-Slider + Zahleneingabe kombiniert (erleichtert die Eingabe).
		function rangeNum(name, min, max, step, unit, def) {
			var wrap = h('div', 'xg-calc-range');
			var top = h('div', 'xg-calc-range-top');
			var input = h('input', 'xg-calc-input'); input.type = 'number'; input.min = min; input.max = max; input.step = step;
			var bubble = h('div', 'xg-calc-range-bubble');
			var slider = h('input', 'xg-calc-slider'); slider.type = 'range'; slider.min = min; slider.max = max; slider.step = step;
			var val = (def != null) ? def : min;
			function fill() { var p = (val - min) / (max - min) * 100; slider.style.backgroundSize = p + '% 100%'; }
			function set(v, fromSlider) {
				if (isNaN(v)) v = 0;
				if (v > max) v = max; if (v < min) v = min;
				val = v; state.form[name] = v;
				bubble.innerHTML = (Math.round(v * 100) / 100) + ' <small>' + unit + '</small>';
				if (!fromSlider) slider.value = v;
				input.value = v; fill();
			}
			slider.addEventListener('input', function () { set(parseFloat(slider.value), true); });
			input.addEventListener('input', function () { set(parseFloat(input.value), false); });
			top.appendChild(input); top.appendChild(bubble);
			wrap.appendChild(top); wrap.appendChild(slider);
			wrap.appendChild(h('div', 'xg-calc-range-scale', '<span>' + min + ' ' + unit + '</span><span>' + max + ' ' + unit + '</span>'));
			set(val, false);
			return wrap;
		}
		function seg(name, entries) {
			var wrap = h('div', 'xg-calc-seg');
			entries.forEach(function (e, i) {
				var b = h('button', i === 0 ? 'active' : '', e[1]); b.type = 'button';
				b.addEventListener('click', function () {
					Array.prototype.forEach.call(wrap.children, function (c) { c.classList.remove('active'); });
					b.classList.add('active'); state.form[name] = e[0];
					if (name === 'form') refreshMetalCond();
				});
				wrap.appendChild(b);
			});
			state.form[name] = entries[0][0];
			return wrap;
		}
		function pill(name, label) {
			var w = h('label', 'xg-calc-pill');
			var inp = h('input'); inp.type = 'checkbox';
			var box = h('span', 'xc-box', ICON.check);
			w.appendChild(inp); w.appendChild(box); w.appendChild(document.createTextNode(label));
			inp.addEventListener('change', function () { state.form[name] = inp.checked; w.classList.toggle('on', inp.checked); });
			return w;
		}

		var _metalCondField = null;
		function refreshMetalCond() {
			if (_metalCondField) _metalCondField.style.display = (state.form.form === 'sieraad') ? 'none' : '';
		}

		// ----- METAL: nur Gold = Karat, andere = Legierung -----
		function buildMetal(wrap) {
			// Leitwert: Metall als Karten
			var cards = h('div', 'xg-calc-opts cols-3');
			Object.keys(DATA.metals).forEach(function (k) {
				var c = optCardMini(DATA.metals[k].label, function () { state.form.metal = k; state.form.purity = null; render(); });
				if (state.form.metal === k) c.classList.add('selected');
				cards.appendChild(c);
			});
			wrap.appendChild(field('Metall', cards, true));
			if (!state.form.metal) return;

			var grid = h('div', 'xg-calc-grid');
			// Form
			grid.appendChild(field('Form', seg('form', [['barren', 'Barren'], ['munt', 'Münze'], ['sieraad', 'Schmuck']])));

			// Reinheit: Gold → Karat, sonst → Legierung
			var purEntries = Object.keys(DATA.metal_purities[state.form.metal]).map(function (key) {
				var lbl = (state.form.metal === 'goud') ? (key + ' karaat') : (key + ' (' + DATA.purity_labels[key] + ')');
				return [key, lbl];
			});
			var purLabel = (state.form.metal === 'goud') ? 'Karat' : 'Legierung';
			grid.appendChild(field(purLabel, sel('purity', purEntries)));

			_metalCondField = field('Zustand', sel('condition', Object.keys(DATA.conditions).map(function (k) { return [k, DATA.conditions[k].label]; })));
			grid.appendChild(_metalCondField);
			wrap.appendChild(grid);
			// Gewicht als Range-Slider (volle Breite, leicht bedienbar).
			wrap.appendChild(field('Gewicht', rangeNum('weight', 0, 1000, 1, 'g', 0), true));
			refreshMetalCond();
		}

		// ----- DIAMOND: + Labor + Zertifikatsnummer -----
		function buildDiamond(wrap) {
			var b = DATA.diamond_base;
			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Karatgewicht', rangeNum('carat', 0, 10, 0.01, 'ct', 1), true));
			grid.appendChild(field('Farbe', sel('color', Object.keys(b.color).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Reinheit', sel('clarity', Object.keys(b.clarity).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Schliff', sel('cut', Object.keys(b.cut).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Fluoreszenz', sel('fluor', Object.keys(b.fluor).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Labor', sel('lab', DATA.labs.map(function (l) { return [l, l]; }))));
			grid.appendChild(field('Zertifikatsnummer', text('cert', 'optional'), true, 'optional'));
			wrap.appendChild(grid);
		}

		// ----- GEM: + Labor + Zertifikat -----
		function buildGem(wrap) {
			var cards = h('div', 'xg-calc-opts cols-3');
			Object.keys(DATA.gem_base).forEach(function (k) {
				var c = optCardMini(DATA.gem_base[k].label, function () { state.form.gem = k; render(); });
				if (state.form.gem === k) c.classList.add('selected');
				cards.appendChild(c);
			});
			wrap.appendChild(field('Edelstein', cards, true));
			if (!state.form.gem) return;

			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Karatgewicht', rangeNum('carat', 0, 10, 0.01, 'ct', 1), true));
			grid.appendChild(field('Qualität', sel('quality', [['5', 'Exzellent'], ['4', 'Sehr gut'], ['3', 'Gut'], ['2', 'Mittel'], ['1', 'Einfach']])));
			grid.appendChild(field('Labor', sel('lab', DATA.labs.map(function (l) { return [l, l]; }))));
			grid.appendChild(field('Zertifikatsnummer', text('cert', 'optional'), true, 'optional'));
			wrap.appendChild(grid);
		}

		// ----- WATCH: Marke→Modell + Metall, Armband, Jahr, Zifferblatt, Edition -----
		function buildWatch(wrap) {
			var cards = h('div', 'xg-calc-opts cols-3');
			Object.keys(DATA.watches).forEach(function (k) {
				var c = optCardMini(DATA.watches[k].label, function () { state.form.brand = k; state.form.model = null; render(); });
				if (state.form.brand === k) c.classList.add('selected');
				cards.appendChild(c);
			});
			wrap.appendChild(field('Marke', cards, true));
			if (!state.form.brand) return;

			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Modell', sel('model', Object.keys(DATA.watches[state.form.brand].models).map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Zustand', sel('condition', Object.keys(DATA.watch_conditions).map(function (k) { return [k, DATA.watch_conditions[k].label]; }))));
			grid.appendChild(field('Gehäuse-Metall', sel('wmetal', DATA.watch_metals.map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Armbandtyp', sel('bracelet', DATA.watch_bracelets.map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Baujahr', num('year', 'z.B. 2015', '1'), false, 'erleichtert die Suche'));
			grid.appendChild(field('Zifferblatt', sel('dial', DATA.watch_dials.map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Edition', text('edition', 'z.B. Limited / Standard'), true));
			var pills = h('div', 'xg-calc-pills');
			pills.appendChild(pill('box', DATA.watch_extras.box.label));
			pills.appendChild(pill('papers', DATA.watch_extras.papers.label));
			grid.appendChild(field('Zubehör', pills, true));
			wrap.appendChild(grid);
		}

		// Step 3: Resultat
		function renderResult() {
			var r = state.result;
			var p = h('div', 'xg-calc-panel');
			var res = h('div', 'xg-calc-result');

			var top = h('div', 'xg-calc-result-top');
			top.appendChild(h('span', 'xg-calc-result-type', typeLabel()));
			top.appendChild(h('span', 'xg-calc-badge ' + (r.indicative ? 'ind' : 'fix'), r.indicative ? 'Preisindikation' : 'Festpreis'));
			res.appendChild(top);

			var price = h('div', 'xg-calc-price');
			res.appendChild(price);
			res.appendChild(h('div', 'xg-calc-price-note', r.indicative
				? 'Unverbindliche Indikation. Endpreis nach Begutachtung.'
				: 'Fester Ankaufspreis auf Basis aktueller Spot-Preise.'));

			// Count-up
			if (r.indicative) {
				price.innerHTML = '<span class="lo">–</span> – <span class="hi">–</span>';
				animateValue(price.querySelector('.lo'), r.low, euro);
				animateValue(price.querySelector('.hi'), r.high, euro);
			} else {
				animateValue(price, r.low, euro);
			}

			var brk = h('div', 'xg-calc-break');
			brk.appendChild(breakRow('Marktpreis', euro2(r.market)));
			brk.appendChild(breakRow('Marge', pct(r.marginPct) + ' · ' + euro2(r.marginAbs)));
			brk.appendChild(breakRow('Differenz Markt ↔ Ankauf', euro2(r.market - r.low)));
			res.appendChild(brk);

			// Charity prominent
			var ch = h('div', 'xg-calc-charity');
			ch.appendChild(h('div', 'xg-calc-charity-head', ICON.heart + ' Davon für den guten Zweck'));
			ch.appendChild(h('div', 'xg-calc-charity-amt', euro2(r.charity)));
			ch.appendChild(h('div', 'xg-calc-charity-txt', 'In jeder Marge steckt ein fester Charity-Anteil. Den Empfänger wählen Sie später selbst.'));
			res.appendChild(ch);

			var add = h('button', 'xg-calc-btn xg-calc-btn-primary', ICON.cart + ' Zur Auswahl hinzufügen');
			add.addEventListener('click', function () { addToCart(r); });
			res.appendChild(add);
			res.appendChild(h('div', '', '<div style="height:10px"></div>'));
			var again = h('button', 'xg-calc-btn xg-calc-btn-ghost', 'Weiteres Produkt berechnen');
			again.addEventListener('click', function () { state.type = null; state.form = {}; state.result = null; render(); });
			res.appendChild(again);

			p.appendChild(res);
			setPanel(p);
		}
		function breakRow(l, v, cls) { return h('div', 'xg-calc-break-row' + (cls ? ' ' + cls : ''), '<span>' + l + '</span><b>' + v + '</b>'); }

		function typeLabel() { return { metal: 'Edelmetall', diamond: 'Diamant', gem: 'Edelstein', watch: 'Uhr' }[state.type]; }
		function specLabel() {
			var f = state.form;
			if (state.type === 'metal') return (DATA.metals[f.metal] ? DATA.metals[f.metal].label : '') + ' · ' + (f.weight || 0) + ' g';
			if (state.type === 'diamond') return (f.carat || 0) + ' ct · ' + (f.color || '') + '/' + (f.clarity || '');
			if (state.type === 'gem') return (DATA.gem_base[f.gem] ? DATA.gem_base[f.gem].label : '') + ' · ' + (f.carat || 0) + ' ct';
			if (state.type === 'watch') return (DATA.watches[f.brand] ? DATA.watches[f.brand].label : '') + ' ' + (f.model || '');
			return '';
		}

		function addToCart(r) {
			state.cart.push({
				type: state.type, typeLabel: typeLabel(), spec: specLabel(),
				low: r.low, high: r.high, indicative: r.indicative, charity: r.charity
			});
			state.type = null; state.form = {}; state.result = null;
			state.view = 'cart';
			render();
		}

		/* =================================================================
		   CART-VIEW (kombiniert)
		================================================================= */
		function renderCart() {
			var wrap = h('div', 'xg-calc-cart');
			if (!state.cart.length) {
				wrap.appendChild(h('div', 'xg-calc-cart-empty', ICON.cart + '<p>Noch keine Produkte ausgewählt.</p>'));
				var back = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Produkt berechnen');
				back.addEventListener('click', function () { state.view = 'calc'; render(); });
				wrap.appendChild(back);
				setPanel(wrap); return;
			}

			var totalLow = 0, totalHigh = 0, totalCharity = 0;
			state.cart.forEach(function (it, i) {
				totalLow += it.low; totalHigh += it.high; totalCharity += it.charity;
				var row = h('div', 'xg-calc-citem');
				row.appendChild(h('div', 'xg-calc-citem-ico', ICON[it.type]));
				var body = h('div', 'xg-calc-citem-body');
				body.appendChild(h('div', 'xg-calc-citem-title', it.typeLabel));
				body.appendChild(h('div', 'xg-calc-citem-spec', it.spec));
				row.appendChild(body);
				row.appendChild(h('div', 'xg-calc-citem-price', it.indicative ? euro(it.low) + '–' + euro(it.high) : euro(it.low)));
				var del = h('button', 'xg-calc-citem-del', '✕');
				del.addEventListener('click', function () { state.cart.splice(i, 1); render(); });
				row.appendChild(del);
				wrap.appendChild(row);
			});

			var sum = h('div', 'xg-calc-cart-sum');
			var r1 = h('div', 'xg-calc-cart-sum-row total');
			r1.innerHTML = '<span>Geschätzter Ankaufswert</span><b>' + (totalLow === totalHigh ? euro(totalLow) : euro(totalLow) + ' – ' + euro(totalHigh)) + '</b>';
			sum.appendChild(r1);
			wrap.appendChild(sum);

			// Charity GROSS + Aufschlüsselung wer/wieviel
			var ch = h('div', 'xg-calc-cart-charity');
			ch.appendChild(h('div', 'xg-calc-cart-charity-top', ICON.heart + ' Ihr Beitrag für den guten Zweck'));
			ch.appendChild(h('div', 'xg-calc-cart-charity-amt', euro2(totalCharity)));
			var list = h('div', 'xg-calc-cart-charity-list');
			var palette = ['#AE1E1E', '#1f9d55', '#c8a24a', '#3a6ea5', '#7a4fa3'];
			DATA.charity_projects.forEach(function (pj, i) {
				var share = totalCharity * (pj.weight || (1 / DATA.charity_projects.length));
				var line = h('div', 'xg-calc-charity-line');
				line.innerHTML =
					'<span class="xg-calc-charity-line-name"><span style="background:' + palette[i % palette.length] + '"></span>' + pj.label + '</span>' +
					'<span class="xg-calc-charity-line-amt">' + euro2(share) + '</span>';
				list.appendChild(line);
			});
			ch.appendChild(list);
			ch.appendChild(h('div', 'xg-calc-charity-txt', '<div style="font-size:12px;color:var(--xc-ink-soft);margin-top:10px">Im nächsten Schritt können Sie gezielt eine Einrichtung in Ihrer Stadt wählen.</div>'));
			wrap.appendChild(ch);

			var actions = h('div', 'xg-calc-cart-actions');
			var go = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Termin vereinbaren & verkaufen');
			go.addEventListener('click', function () { state.view = 'checkout'; state.checkout.step = 0; render(); });
			var more = h('button', 'xg-calc-btn xg-calc-btn-ghost', 'Weiteres Produkt hinzufügen');
			more.addEventListener('click', function () { state.view = 'calc'; render(); });
			actions.appendChild(go); actions.appendChild(more);
			wrap.appendChild(actions);

			setPanel(wrap);
		}

		/* =================================================================
		   CHECKOUT-FLOW
		================================================================= */
		function renderCheckout() {
			var c = state.checkout;
			var node = h('div', 'xg-calc-checkout');
			if (c.step === 0) checkoutData(node);
			else if (c.step === 1) checkoutService(node);
			else if (c.step === 2) checkoutPayout(node);
			else if (c.step === 3) checkoutCharity(node);
			else checkoutThanks(node);
			setPanel(node);
		}
		function coHead(node, title, sub) { node.appendChild(panelHead(title, sub, true, true)); }

		// 1) Daten
		function checkoutData(node) {
			coHead(node, 'Ihre Kontaktdaten', 'Damit wir Ihren Termin bestätigen können.');
			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Vorname', coInput('first')));
			grid.appendChild(field('Nachname', coInput('last')));
			grid.appendChild(field('E-Mail', coInput('email', 'email')));
			grid.appendChild(field('Telefon', coInput('phone', 'tel')));
			grid.appendChild(field('Stadt', coInput('city'), true));
			node.appendChild(grid);
			coNav(node, null, 'Weiter', function () { state.checkout.step = 1; render(); });
		}
		function coInput(name, type) {
			var i = h('input', 'xg-calc-input'); i.type = type || 'text';
			i.value = state.checkout.data[name] || '';
			i.addEventListener('input', function () { state.checkout.data[name] = i.value; });
			return i;
		}

		// 2) Service
		function checkoutService(node) {
			coHead(node, 'Wie möchten Sie verkaufen?', 'Wählen Sie die für Sie bequemste Option.');
			var svc = h('div', 'xg-calc-svc');
			[
				{ k: 'home', i: ICON.home, t: 'Hausbesuch', d: 'Unser Experte kommt zu Ihnen (kostenlos & versichert).' },
				{ k: 'office', i: ICON.office, t: 'In einer Filiale', d: 'Besuchen Sie eines unserer Büros.' },
				{ k: 'pickup', i: ICON.truck, t: 'Abhol-Service', d: 'Versicherte Abholung per Kurier.' }
			].forEach(function (s) {
				var card = optCard(s.i, s.t, s.d, function () { state.checkout.service = s.k; state.checkout.step = 2; render(); });
				if (state.checkout.service === s.k) card.classList.add('selected');
				svc.appendChild(card);
			});
			node.appendChild(svc);
			coNav(node, function () { state.checkout.step = 0; render(); }, null, null);
		}

		// 3) Auszahlung
		function checkoutPayout(node) {
			coHead(node, 'Auszahlungsart', 'Wie möchten Sie Ihr Geld erhalten?');
			var grid = h('div', 'xg-calc-opts');
			[
				{ k: 'bank', t: 'Banküberweisung', d: 'Direkt auf Ihr Konto.' },
				{ k: 'cash', t: 'Barauszahlung', d: 'Sofort vor Ort (bis Limit).' }
			].forEach(function (pp) {
				var card = optCardMini(pp.t + ' — ' + pp.d, function () { state.checkout.payout = pp.k; state.checkout.step = 3; render(); });
				if (state.checkout.payout === pp.k) card.classList.add('selected');
				grid.appendChild(card);
			});
			node.appendChild(grid);
			coNav(node, function () { state.checkout.step = 1; render(); }, null, null);
		}

		// 4) Charity-Empfänger gezielt wählen
		function checkoutCharity(node) {
			coHead(node, 'Wohin soll Ihr Beitrag gehen?', 'Wählen Sie eine konkrete Einrichtung – gerne in Ihrer Stadt.');
			var totalCharity = state.cart.reduce(function (a, b) { return a + b.charity; }, 0);
			node.appendChild(h('div', 'xg-calc-charity', ICON.heart + ' <b style="color:var(--xc-red)"> ' + euro2(totalCharity) + '</b> fließen an die gewählte Einrichtung.'));

			var typeSel = sel2('charity_type', DATA.charity_projects.map(function (p) { return [p.id, p.label]; }), function (v) { fillRecipients(v); });
			node.appendChild(field('Bereich', typeSel, true));

			var recipientWrap = field('Einrichtung', h('select', 'xg-calc-input'), true);
			node.appendChild(recipientWrap);
			function fillRecipients(typeId) {
				var prj = DATA.charity_projects.filter(function (p) { return p.id === typeId; })[0];
				var s = recipientWrap.querySelector('select');
				s.innerHTML = '';
				(prj.recipients || []).forEach(function (r) { s.appendChild(opt(r, r)); });
				state.checkout.charity = { type: typeId, recipient: s.value };
				s.onchange = function () { state.checkout.charity.recipient = s.value; };
			}
			fillRecipients(DATA.charity_projects[0].id);

			coNav(node, function () { state.checkout.step = 2; render(); }, 'Termin anfragen', function () { state.checkout.step = 4; render(); });
		}
		function sel2(name, entries, onchange) {
			var s = h('select', 'xg-calc-input'); s.name = name;
			entries.forEach(function (e) { s.appendChild(opt(e[0], e[1])); });
			s.addEventListener('change', function () { onchange(s.value); });
			return s;
		}

		// 5) Danke
		function checkoutThanks(node) {
			var t = h('div', 'xg-calc-thanks');
			t.appendChild(h('div', 'xg-calc-thanks-check', ICON.check));
			t.appendChild(h('h3', '', 'Vielen Dank!'));
			var name = state.checkout.data.first || '';
			t.appendChild(h('p', '', 'Ihr Terminversuch ist bei uns eingegangen' + (name ? ', ' + name : '') + '. Sie erhalten in Kürze eine Bestätigungs-E-Mail mit allen Details.'));
			var ch = state.checkout.charity;
			if (ch) t.appendChild(h('p', '', '<span style="color:var(--xc-red);font-weight:700">♥</span> Ihr Beitrag geht an: <b>' + ch.recipient + '</b>'));

			// Übergabe an Backend (später: POST → wp_xg_appointments)
			document.dispatchEvent(new CustomEvent('xg:appointment-submit', {
				detail: { cart: state.cart, checkout: state.checkout }
			}));

			var done = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Neue Berechnung starten');
			done.style.marginTop = '20px'; done.style.maxWidth = '280px'; done.style.marginLeft = 'auto'; done.style.marginRight = 'auto';
			done.addEventListener('click', function () {
				state.cart = []; state.type = null; state.form = {}; state.result = null;
				state.checkout = { step: 0, data: {}, service: null, payout: null, charity: null };
				state.view = 'calc'; render();
			});
			t.appendChild(done);
			node.appendChild(t);
		}

		function coNav(node, back, nextLabel, nextFn) {
			var wrap = h('div', 'xg-calc-next-wrap');
			if (back) { var b = h('button', 'xg-calc-btn xg-calc-btn-ghost', '← Zurück'); b.addEventListener('click', back); wrap.appendChild(b); }
			if (nextLabel) { var n = h('button', 'xg-calc-btn xg-calc-btn-primary', nextLabel); n.addEventListener('click', nextFn); wrap.appendChild(n); }
			node.appendChild(wrap);
		}

		/* ---------- gemeinsame UI-Bausteine ---------- */
		function panelHead(title, sub, showBack, checkout) {
			var head = h('div', 'xg-calc-panel-head');
			var left = h('div');
			left.appendChild(h('div', 'xg-calc-panel-title', title));
			if (sub) left.appendChild(h('div', 'xg-calc-panel-sub', sub));
			head.appendChild(left);
			if (showBack) {
				var b = h('button', 'xg-calc-back', '← Zurück');
				b.addEventListener('click', function () {
					if (checkout) { state.view = 'cart'; render(); }
					else { state.type = null; state.form = {}; state.result = null; render(); }
				});
				head.appendChild(b);
			}
			return head;
		}
		function findBox() {
			var box = h('div', 'xg-calc-find', ICON.search);
			var inp = h('input'); inp.type = 'text'; inp.placeholder = 'Produkt suchen (z.B. Rolex, Diamant, Goud)…';
			box.appendChild(inp);
			inp.addEventListener('input', function () {
				var q = inp.value.toLowerCase();
				if (!q) return;
				if (/rolex|omega|patek|cartier|uhr|horloge/.test(q)) quickType('watch');
				else if (/diamant|diamond|brilliant/.test(q)) quickType('diamond');
				else if (/robijn|rubin|saffier|saphir|smaragd|emerald|edelste/.test(q)) quickType('gem');
				else if (/goud|gold|zilver|silber|platin|palladium|barren|munt|münz/.test(q)) quickType('metal');
			});
			return box;
		}
		function quickType(t) { state.type = t; state.form = {}; state.result = null; render(); }
		function optCard(icon, title, desc, onclick) {
			var c = h('button', 'xg-calc-opt'); c.type = 'button';
			c.appendChild(h('div', 'xg-calc-opt-ico', icon));
			var b = h('div', 'xg-calc-opt-body');
			b.appendChild(h('div', 'xg-calc-opt-title', title));
			b.appendChild(h('div', 'xg-calc-opt-desc', desc));
			c.appendChild(b);
			c.addEventListener('click', onclick);
			return c;
		}
		function optCardMini(label, onclick) {
			var c = h('button', 'xg-calc-opt'); c.type = 'button';
			c.appendChild(h('div', 'xg-calc-opt-body', '<div class="xg-calc-opt-title">' + label + '</div>'));
			c.addEventListener('click', onclick);
			return c;
		}

		render();
	}

	/* =====================================================================
	   BOOT
	===================================================================== */
	function boot() {
		document.querySelectorAll('.xg-calc').forEach(function (root) {
			if (!root.__xgInit) { root.__xgInit = true; new Calculator(root); }
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
