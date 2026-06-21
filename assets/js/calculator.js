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
		if (window.XGLocale && window.XGLocale.format) return window.XGLocale.format(isFinite(n) ? n : 0, 0);
			return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: DATA.currency || 'EUR', maximumFractionDigits: 0 }).format(isFinite(n) ? n : 0);
	}
	function euro2(n) {
		if (window.XGLocale && window.XGLocale.format) return window.XGLocale.format(isFinite(n) ? n : 0, 2);
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

		// KERNLOGICA:
		//   Baar/Munt  → verkoop PER STUK: vast gewicht per stuk × aantal stuks.
		//   Sloop/sieraden → verkoop PER GEWICHT: opgegeven gram (+ staffel/staat).
		var grams, condFactor = 1, tierBonus = 0;
		if (f.form === 'sieraad') {
			grams = parseFloat(f.weight) || 0;
			DATA.jewelry_tiers.forEach(function (t) { if (grams >= t.min) tierBonus = t.bonus; });
		} else {
			var per = parseFloat(f.unit_weight) || 0;
			var qty = parseInt(f.qty, 10) || 0;
			grams = per * qty;
			if (f.condition && DATA.conditions[f.condition]) condFactor = DATA.conditions[f.condition].factor;
		}
		var market = metal.spot * grams * purity * condFactor;
		var marginPct = Math.max(DATA.margins.metal - tierBonus, 0);
		var marginAbs = market * marginPct;
		var payout = market - marginAbs;
		return {
			market: market, marginPct: marginPct, marginAbs: marginAbs,
			charity: marginAbs * DATA.margins.charity_share, low: payout, high: payout,
			indicative: false, grams: grams, qty: (f.form === 'sieraad') ? null : (parseInt(f.qty, 10) || 0)
		};
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

		/*
		 * Preset (Deep-Link für Produktseiten): data-preset='{"type":"metal",
		 * "form":{"metal":"goud","form":"munt","purity":"22"}}'.
		 * Der Wizard startet dann direkt bei diesem Produkt (Schritt "Details"),
		 * statt beim Typ-Picker – der Kunde muss nichts suchen.
		 */
		var presetRaw = root.getAttribute('data-preset');
		if (presetRaw) {
			try {
				var preset = JSON.parse(presetRaw);
				if (preset.type) state.type = preset.type;
				if (preset.form) state.form = preset.form;
			} catch (e) {}
		}

		/*
		 * Scope/Lock für Hero, Kategorie- und Produktseiten:
		 *   data-only="metal"          → Hero: nur Edelmetaal (kein Typ-Picker)
		 *   data-only="diamond,gem"    → Kategorie Edelstenen (kleiner Picker)
		 *   data-only="watch"          → Kategorie Horloges
		 *   data-lock="1" + data-preset→ Produktseite: Produkt steht fest,
		 *                                nur noch Gewicht abfragen
		 */
		var onlyAttr = root.getAttribute('data-only');
		var onlyTypes = onlyAttr ? onlyAttr.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : null;
		var only = (onlyTypes && onlyTypes.length === 1) ? onlyTypes[0] : null; // genau ein Typ → kein Picker
		var lock = root.getAttribute('data-lock') === '1';
		if (only && !state.type) state.type = only;

		// Grundgerüst
		root.innerHTML = '';
		var head = h('div', 'xg-calc-head');
		head.appendChild(liveBlock());
		head.appendChild(themeToggle());
		root.appendChild(head);

		var tabs = h('div', 'xg-calc-tabs');
		var tabCalc = h('button', 'xg-calc-tab active', ICON.search + ' Berekenen');
		var tabCart = h('button', 'xg-calc-tab', ICON.cart + ' <span>Selectie</span> <span class="xg-calc-tab-badge" style="display:none">0</span>');
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
			var b = h('div', 'xg-calc-live', '<span class="xg-calc-live-dot"></span> Live prijzen');
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
				steps = ['Gegevens', 'Service', 'Uitbetaling', 'Goede doel', 'Klaar'];
				idx = state.checkout.step;
			} else if (only) {
				// Kein Typ-Picker → nur Gegevens + Resultaat.
				steps = ['Gegevens', 'Resultaat'];
				idx = state.result ? 1 : 0;
			} else {
				steps = ['Product', 'Details', 'Resultaat'];
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
			if (!state.type) {
				if (only) { state.type = only; }   // Only-Modus: kein Typ-Picker
				else return renderTypePicker();
			}
			if (state.result) return renderResult();
			renderDetails();
		}

		// Step 1: Produkt-Typ
		function renderTypePicker() {
			var p = h('div', 'xg-calc-panel');
			p.appendChild(panelHead('Wat wilt u verkopen?', 'Kies een categorie – wij leiden u naar de prijs.'));
			if (!onlyTypes) p.appendChild(findBox()); // Suche nur im vollen Modus

			var types = [
				{ k: 'metal',   t: 'Edelmetaal', d: 'Goud, zilver, platina, palladium' },
				{ k: 'diamond', t: 'Diamant',    d: 'Losse stenen & sieraden' },
				{ k: 'gem',     t: 'Edelsteen',  d: 'Robijn, saffier, smaragd' },
				{ k: 'watch',   t: 'Horloge',    d: 'Luxe horloges van alle merken' }
			];
			if (onlyTypes) types = types.filter(function (ty) { return onlyTypes.indexOf(ty.k) !== -1; });
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
			var titles = { metal: 'Uw edelmetaal', diamond: 'Uw diamant', gem: 'Uw edelsteen', watch: 'Uw horloge' };
			p.appendChild(panelHead(titles[state.type], 'Vul de gegevens aan – de prijs wordt live berekend.', !only));

			var body = h('div');
			if (state.type === 'metal') buildMetal(body);
			else if (state.type === 'diamond') buildDiamond(body);
			else if (state.type === 'gem') buildGem(body);
			else if (state.type === 'watch') buildWatch(body);
			p.appendChild(body);

			var actions = h('div', 'xg-calc-next-wrap');
			var btn = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Prijs berekenen');
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
		function num(name, ph, step, def) {
			var i = h('input', 'xg-calc-input'); i.type = 'number'; i.name = name;
			i.min = '0'; i.step = step || '0.1'; i.placeholder = ph;
			if (def != null) { i.value = def; state.form[name] = String(def); }
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
		function seg(name, entries, onchange) {
			var wrap = h('div', 'xg-calc-seg');
			var cur = state.form[name] || entries[0][0];
			entries.forEach(function (e) {
				var b = h('button', e[0] === cur ? 'active' : '', e[1]); b.type = 'button';
				b.addEventListener('click', function () {
					Array.prototype.forEach.call(wrap.children, function (c) { c.classList.remove('active'); });
					b.classList.add('active'); state.form[name] = e[0];
					if (onchange) onchange();
				});
				wrap.appendChild(b);
			});
			state.form[name] = cur;
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

		// ----- METAL: Baar/Munt per stuk · Sloop per gewicht -----
		function buildMetal(wrap) {
			// Lock-Modus (Produktseite): Produkt steht fest, nur Gewicht abfragen.
			if (lock && state.form.metal) {
				var mLbl = DATA.metals[state.form.metal] ? DATA.metals[state.form.metal].label : '';
				var fLbl = { barren: 'Baar', munt: 'Munt', sieraad: 'Sieraad' }[state.form.form] || '';
				var pLbl = state.form.purity ? (state.form.metal === 'goud' ? state.form.purity + ' karaat' : (DATA.purity_labels[state.form.purity] || state.form.purity)) : '';
				var summary = [mLbl, fLbl, pLbl].filter(Boolean).join(' · ');
				var box = h('div', 'xg-calc-input'); box.style.display = 'flex'; box.style.alignItems = 'center'; box.style.fontWeight = '700'; box.textContent = summary;
				wrap.appendChild(field('Product', box, true));
				wrap.appendChild(field('Gewicht', rangeNum('weight', 0, 1000, 1, 'g', state.form.weight || 0), true));
				return;
			}
			// Leitwert: Metall als Karten
			var cards = h('div', 'xg-calc-opts cols-3');
			Object.keys(DATA.metals).forEach(function (k) {
				var c = optCardMini(DATA.metals[k].label, function () { state.form.metal = k; state.form.purity = null; render(); });
				if (state.form.metal === k) c.classList.add('selected');
				cards.appendChild(c);
			});
			wrap.appendChild(field('Metaal', cards, true));
			if (!state.form.metal) return;

			if (!state.form.form) state.form.form = 'barren';

			var grid = h('div', 'xg-calc-grid');
			// Vorm — bepaalt of we per stuk (baar/munt) of per gewicht (sloop) rekenen.
			var sub = h('div', 'xg-calc-form-sub');
			grid.appendChild(field('Vorm', seg('form', [['barren', 'Baar'], ['munt', 'Munt'], ['sieraad', 'Sloop & sieraden']], function () { buildMetalFields(sub); })));

			// Reinheit: Gold → Karat, sonst → Legierung
			var purEntries = Object.keys(DATA.metal_purities[state.form.metal]).map(function (key) {
				var lbl = (state.form.metal === 'goud') ? (key + ' karaat') : (key + ' (' + DATA.purity_labels[key] + ')');
				return [key, lbl];
			});
			var purLabel = (state.form.metal === 'goud') ? 'Karaat / gehalte' : 'Legering';
			grid.appendChild(field(purLabel, sel('purity', purEntries)));
			wrap.appendChild(grid);

			// Vorm-afhankelijke velden (per stuk vs. per gewicht).
			wrap.appendChild(sub);
			buildMetalFields(sub);
		}

		// Gangbare gewichten per stuk (gram) voor baren en munten.
		var PIECE_WEIGHTS = {
			barren: [['1', '1 g'], ['2.5', '2,5 g'], ['5', '5 g'], ['10', '10 g'], ['20', '20 g'], ['31.1035', '1 oz (31,1 g)'], ['50', '50 g'], ['100', '100 g'], ['250', '250 g'], ['500', '500 g'], ['1000', '1 kg']],
			munt: [['1.5552', '1/20 oz (1,56 g)'], ['3.1103', '1/10 oz (3,11 g)'], ['7.7759', '1/4 oz (7,78 g)'], ['15.5517', '1/2 oz (15,55 g)'], ['31.1035', '1 oz (31,10 g)'], ['33.9305', 'Krugerrand 1 oz (33,93 g)'], ['7.9881', 'Sovereign (7,99 g)'], ['8.359', '10 Gulden (8,36 g)']]
		};

		// Velden die afhangen van de gekozen vorm — herbouwd bij vormwissel.
		function buildMetalFields(sub) {
			sub.innerHTML = '';
			if (state.form.form === 'sieraad') {
				// PER GEWICHT: gram-slider + staat.
				var g = h('div', 'xg-calc-grid');
				g.appendChild(field('Staat', sel('condition', Object.keys(DATA.conditions).map(function (k) { return [k, DATA.conditions[k].label]; }))));
				sub.appendChild(g);
				sub.appendChild(field('Totaal gewicht', rangeNum('weight', 0, 1000, 1, 'g', state.form.weight || 0), true, 'in gram'));
			} else {
				// PER STUK: gewicht per stuk + aantal.
				var presets = PIECE_WEIGHTS[state.form.form] || PIECE_WEIGHTS.barren;
				// purity-default niet door vorm overschrijven
				var g2 = h('div', 'xg-calc-grid');
				g2.appendChild(field('Gewicht per stuk', sel('unit_weight', presets), false, state.form.form === 'munt' ? 'standaardmunten' : 'standaardbaren'));
				g2.appendChild(field('Aantal stuks', num('qty', '1', '1', state.form.qty || 1)));
				sub.appendChild(g2);
			}
		}

		// ----- DIAMOND: + Labor + Zertifikatsnummer -----
		function buildDiamond(wrap) {
			var b = DATA.diamond_base;
			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Karaatgewicht', rangeNum('carat', 0, 10, 0.01, 'ct', 1), true));
			grid.appendChild(field('Kleur', sel('color', Object.keys(b.color).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Zuiverheid', sel('clarity', Object.keys(b.clarity).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Slijpvorm', sel('cut', Object.keys(b.cut).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Fluorescentie', sel('fluor', Object.keys(b.fluor).map(function (k) { return [k, k]; }))));
			grid.appendChild(field('Laboratorium', sel('lab', DATA.labs.map(function (l) { return [l, l]; }))));
			grid.appendChild(field('Certificaatnummer', text('cert', 'optioneel'), true, 'optioneel'));
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
			wrap.appendChild(field('Edelsteen', cards, true));
			if (!state.form.gem) return;

			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Karaatgewicht', rangeNum('carat', 0, 10, 0.01, 'ct', 1), true));
			grid.appendChild(field('Kwaliteit', sel('quality', [['5', 'Uitstekend'], ['4', 'Zeer goed'], ['3', 'Goed'], ['2', 'Gemiddeld'], ['1', 'Eenvoudig']])));
			grid.appendChild(field('Laboratorium', sel('lab', DATA.labs.map(function (l) { return [l, l]; }))));
			grid.appendChild(field('Certificaatnummer', text('cert', 'optioneel'), true, 'optioneel'));
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
			wrap.appendChild(field('Merk', cards, true));
			if (!state.form.brand) return;

			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Model', sel('model', Object.keys(DATA.watches[state.form.brand].models).map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Staat', sel('condition', Object.keys(DATA.watch_conditions).map(function (k) { return [k, DATA.watch_conditions[k].label]; }))));
			grid.appendChild(field('Kast-metaal', sel('wmetal', DATA.watch_metals.map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Bandtype', sel('bracelet', DATA.watch_bracelets.map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Bouwjaar', num('year', 'bijv. 2015', '1'), false, 'maakt zoeken makkelijker'));
			grid.appendChild(field('Wijzerplaat', sel('dial', DATA.watch_dials.map(function (m) { return [m, m]; }))));
			grid.appendChild(field('Editie', text('edition', 'bijv. Limited / Standaard'), true));
			var pills = h('div', 'xg-calc-pills');
			pills.appendChild(pill('box', DATA.watch_extras.box.label));
			pills.appendChild(pill('papers', DATA.watch_extras.papers.label));
			grid.appendChild(field('Accessoires', pills, true));
			wrap.appendChild(grid);
		}

		// Step 3: Resultat
		function renderResult() {
			var r = state.result;
			var p = h('div', 'xg-calc-panel');
			var res = h('div', 'xg-calc-result');

			var top = h('div', 'xg-calc-result-top');
			top.appendChild(h('span', 'xg-calc-result-type', typeLabel()));
			top.appendChild(h('span', 'xg-calc-badge ' + (r.indicative ? 'ind' : 'fix'), r.indicative ? 'Prijsindicatie' : 'Vaste prijs'));
			res.appendChild(top);

			var price = h('div', 'xg-calc-price');
			res.appendChild(price);
			res.appendChild(h('div', 'xg-calc-price-note', r.indicative
				? 'Vrijblijvende indicatie. Eindprijs na taxatie.'
				: 'Vaste inkoopprijs op basis van actuele spotprijzen.'));

			// Count-up
			if (r.indicative) {
				price.innerHTML = '<span class="lo">–</span> – <span class="hi">–</span>';
				animateValue(price.querySelector('.lo'), r.low, euro);
				animateValue(price.querySelector('.hi'), r.high, euro);
			} else {
				animateValue(price, r.low, euro);
			}

			var brk = h('div', 'xg-calc-break');
			brk.appendChild(breakRow('Marktprijs', euro2(r.market)));
			brk.appendChild(breakRow('Marge', pct(r.marginPct) + ' · ' + euro2(r.marginAbs)));
			brk.appendChild(breakRow('Verschil markt ↔ inkoop', euro2(r.market - r.low)));
			res.appendChild(brk);

			// Charity prominent
			var ch = h('div', 'xg-calc-charity');
			ch.appendChild(h('div', 'xg-calc-charity-head', ICON.heart + ' Hiervan naar het goede doel'));
			ch.appendChild(h('div', 'xg-calc-charity-amt', euro2(r.charity)));
			ch.appendChild(h('div', 'xg-calc-charity-txt', 'In elke marge zit een vast deel voor het goede doel. De ontvanger kiest u later zelf.'));
			res.appendChild(ch);

			var add = h('button', 'xg-calc-btn xg-calc-btn-primary', ICON.cart + ' Aan selectie toevoegen');
			add.addEventListener('click', function () { addToCart(r); });
			res.appendChild(add);
			res.appendChild(h('div', '', '<div style="height:10px"></div>'));
			var again = h('button', 'xg-calc-btn xg-calc-btn-ghost', 'Nog een product berekenen');
			again.addEventListener('click', function () { state.type = null; state.form = {}; state.result = null; render(); });
			res.appendChild(again);

			p.appendChild(res);
			setPanel(p);
		}
		function breakRow(l, v, cls) { return h('div', 'xg-calc-break-row' + (cls ? ' ' + cls : ''), '<span>' + l + '</span><b>' + v + '</b>'); }

		function typeLabel() { return { metal: 'Edelmetaal', diamond: 'Diamant', gem: 'Edelsteen', watch: 'Horloge' }[state.type]; }
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
				wrap.appendChild(h('div', 'xg-calc-cart-empty', ICON.cart + '<p>Nog geen producten geselecteerd.</p>'));
				var back = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Product berekenen');
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
			r1.innerHTML = '<span>Geschatte inkoopwaarde</span><b>' + (totalLow === totalHigh ? euro(totalLow) : euro(totalLow) + ' – ' + euro(totalHigh)) + '</b>';
			sum.appendChild(r1);
			wrap.appendChild(sum);

			// Charity GROSS + Aufschlüsselung wer/wieviel
			var ch = h('div', 'xg-calc-cart-charity');
			ch.appendChild(h('div', 'xg-calc-cart-charity-top', ICON.heart + ' Uw bijdrage aan het goede doel'));
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
			ch.appendChild(h('div', 'xg-calc-charity-txt', '<div style="font-size:12px;color:var(--xc-ink-soft);margin-top:10px">In de volgende stap kiest u gericht een instelling in uw stad.</div>'));
			wrap.appendChild(ch);

			var actions = h('div', 'xg-calc-cart-actions');
			var go = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Afspraak maken & verkopen');
			go.addEventListener('click', function () { state.view = 'checkout'; state.checkout.step = 0; render(); });
			var more = h('button', 'xg-calc-btn xg-calc-btn-ghost', 'Nog een product toevoegen');
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
			coHead(node, 'Uw contactgegevens', 'Zodat wij uw afspraak kunnen bevestigen.');
			var grid = h('div', 'xg-calc-grid');
			grid.appendChild(field('Voornaam', coInput('first')));
			grid.appendChild(field('Achternaam', coInput('last')));
			grid.appendChild(field('E-mail', coInput('email', 'email')));
			grid.appendChild(field('Telefoon', coInput('phone', 'tel')));
			grid.appendChild(field('Stad', coInput('city'), true));
			node.appendChild(grid);
			coNav(node, null, 'Verder', function () { state.checkout.step = 1; render(); });
		}
		function coInput(name, type) {
			var i = h('input', 'xg-calc-input'); i.type = type || 'text';
			i.value = state.checkout.data[name] || '';
			i.addEventListener('input', function () { state.checkout.data[name] = i.value; });
			return i;
		}

		// 2) Service
		function checkoutService(node) {
			coHead(node, 'Hoe wilt u verkopen?', 'Kies de voor u makkelijkste optie.');
			var svc = h('div', 'xg-calc-svc');
			[
				{ k: 'home', i: ICON.home, t: 'Bezoek aan huis', d: 'Onze expert komt naar u toe (gratis & verzekerd).' },
				{ k: 'office', i: ICON.office, t: 'In een vestiging', d: 'Bezoek een van onze kantoren.' },
				{ k: 'pickup', i: ICON.truck, t: 'Ophaalservice', d: 'Verzekerde ophaling per koerier.' }
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
			coHead(node, 'Uitbetaling', 'Hoe wilt u uw geld ontvangen?');
			var grid = h('div', 'xg-calc-opts');
			[
				{ k: 'bank', t: 'Bankoverschrijving', d: 'Direct op uw rekening.' },
				{ k: 'cash', t: 'Contante uitbetaling', d: 'Direct ter plaatse (tot limiet).' }
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
			coHead(node, 'Waar gaat uw bijdrage naartoe?', 'Kies een concrete instelling – het liefst in uw stad.');
			var totalCharity = state.cart.reduce(function (a, b) { return a + b.charity; }, 0);
			node.appendChild(h('div', 'xg-calc-charity', ICON.heart + ' <b style="color:var(--xc-red)"> ' + euro2(totalCharity) + '</b> gaat naar de gekozen instelling.'));

			var typeSel = sel2('charity_type', DATA.charity_projects.map(function (p) { return [p.id, p.label]; }), function (v) { fillRecipients(v); });
			node.appendChild(field('Gebied', typeSel, true));

			var recipientWrap = field('Instelling', h('select', 'xg-calc-input'), true);
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

			coNav(node, function () { state.checkout.step = 2; render(); }, 'Afspraak aanvragen', function () { state.checkout.step = 4; render(); });
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
			t.appendChild(h('h3', '', 'Hartelijk dank!'));
			var name = state.checkout.data.first || '';
			t.appendChild(h('p', '', 'Uw afspraakverzoek is bij ons binnengekomen' + (name ? ', ' + name : '') + '. U ontvangt binnenkort een bevestigingsmail met alle details.'));
			var ch = state.checkout.charity;
			if (ch) t.appendChild(h('p', '', '<span style="color:var(--xc-red);font-weight:700">♥</span> Uw bijdrage gaat naar: <b>' + ch.recipient + '</b>'));

			// Übergabe an Backend: Event + REST-POST (Terminplaner legt Afspraak an).
			document.dispatchEvent(new CustomEvent('xg:appointment-submit', {
				detail: { cart: state.cart, checkout: state.checkout }
			}));
			submitAppointment();

			var done = h('button', 'xg-calc-btn xg-calc-btn-primary', 'Nieuwe berekening starten');
			done.style.marginTop = '20px'; done.style.maxWidth = '280px'; done.style.marginLeft = 'auto'; done.style.marginRight = 'auto';
			done.addEventListener('click', function () {
				state.cart = []; state.type = null; state.form = {}; state.result = null;
				state.checkout = { step: 0, data: {}, service: null, payout: null, charity: null };
				state.view = 'calc'; render();
			});
			t.appendChild(done);
			node.appendChild(t);
		}

		// Termin an das Backend senden (REST). Im Demo/ohne WP schlägt es
		// still fehl – die Danke-Seite wird trotzdem angezeigt.
		function submitAppointment() {
			var rest = (window.XG_CALC_DATA && window.XG_CALC_DATA.rest_appointment) || '/wp-json/ekinese/v1/appointment';
			var c = state.checkout, d = c.data || {};
			var totalLow = state.cart.reduce(function (a, b) { return a + b.low; }, 0);
			var charity = state.cart.reduce(function (a, b) { return a + b.charity; }, 0);
			var payload = {
				first: d.first, last: d.last, email: d.email, phone: d.phone,
				city: d.city, address: d.address || '', postcode: d.postcode || '',
				service: c.service, payout: c.payout,
				charity_project: c.charity ? c.charity.type : '',
				charity_recipient: c.charity ? c.charity.recipient : '',
				payout_total: Math.round(totalLow), charity_total: Math.round(charity * 100) / 100,
				products: state.cart
			};
			try {
				if (window.fetch) {
					fetch(rest, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(payload)
					}).catch(function () {});
				}
			} catch (e) {}
		}

		function coNav(node, back, nextLabel, nextFn) {
			var wrap = h('div', 'xg-calc-next-wrap');
			if (back) { var b = h('button', 'xg-calc-btn xg-calc-btn-ghost', '← Terug'); b.addEventListener('click', back); wrap.appendChild(b); }
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
				var b = h('button', 'xg-calc-back', '← Terug');
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
			var inp = h('input'); inp.type = 'text'; inp.placeholder = 'Product zoeken (bijv. Rolex, diamant, goud)…';
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
