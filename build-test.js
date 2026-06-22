/**
 * Baut eine eigenständige Test-HTML (test-suite.html) aus den ECHTEN
 * Theme-Dateien: parts/header.html, parts/footer.html und den Patterns.
 * Entfernt Gutenberg-/PHP-Hüllen und verlinkt die echten CSS/JS-Assets.
 *
 * Aufruf:  node build-test.js
 */
const fs = require('fs');
const { execSync } = require('child_process');

function read(p) { return fs.readFileSync(p, 'utf8'); }

// Gutenberg-Kommentare entfernen (<!-- wp:... -->, <!-- /wp:... -->, self-closing)
function stripBlocks(s) {
	return s.replace(/<!--\s*\/?wp:[^>]*?-->/g, '');
}
// Pattern via PHP-CLI rendern (die Patterns rufen die Sektions-Library auf),
// danach Gutenberg-Kommentare entfernen.
function pattern(file) {
	// Via een tijdelijk PHP-script (geen -r) → geen shell-expansie van $-variabelen.
	// Lichte shims voor WP-escaping/i18n zodat patterns die deze gebruiken (o.a.
	// de juridische pagina's) ook buiten WordPress renderen.
	var php = '<?php\n'
		+ 'define("ABSPATH","/tmp/");\n'
		+ 'function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}\n'
		+ 'function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);}\n'
		+ 'function esc_url($s){return (string)$s;}\n'
		+ 'function esc_html__($s){return $s;}\n'
		+ 'function esc_html_e($s){echo $s;}\n'
		+ 'function __($s){return $s;}\n'
		+ 'function _e($s){echo $s;}\n'
		+ 'function wp_kses_post($s){return $s;}\n'
		+ 'function home_url($s=""){return $s;}\n'
		+ 'function get_theme_file_uri($s=""){return $s;}\n'
		+ 'require "inc/blueprint-sections.php";\n'
		+ 'ob_start();include "patterns/' + file + '";echo ob_get_clean();\n';
	var tmp = '.tst-render.php';
	fs.writeFileSync(tmp, php);
	var out = execSync('php -d display_errors=1 ' + tmp, { encoding: 'utf8' });
	// Demo voor het dynamische prijsblok (server-rendered in WordPress).
	out = out.replace(/<!--\s*wp:ekinese\/metal-prices[^>]*?-->/g,
		'<section><div class="xg-container"><div class="xg-spec-table"><table><thead><tr><th>Metaal</th><th>Spotprijs (€/g)</th><th>Inkoopprijs (€/g)</th></tr></thead><tbody>' +
		'<tr><td>Goud</td><td>€ 62,50</td><td><strong>€ 57,50</strong></td></tr>' +
		'<tr><td>Zilver</td><td>€ 0,78</td><td><strong>€ 0,72</strong></td></tr>' +
		'<tr><td>Platina</td><td>€ 28,90</td><td><strong>€ 26,59</strong></td></tr>' +
		'<tr><td>Palladium</td><td>€ 30,10</td><td><strong>€ 27,69</strong></td></tr>' +
		'</tbody></table></div></div></section>');
	// Demo voor de trust-wall.
	out = out.replace(/<!--\s*wp:ekinese\/trust-wall[^>]*?-->/g,
		'<section class="xg-trustwall"><div class="xg-container"><div class="xg-tw-stats"><div class="xg-tw-stat"><div class="xg-tw-num">16+</div><div class="xg-tw-lbl">jaar ervaring</div></div><div class="xg-tw-stat"><div class="xg-tw-num">40+</div><div class="xg-tw-lbl">vestigingen</div></div><div class="xg-tw-stat"><div class="xg-tw-num">4,8/5</div><div class="xg-tw-lbl">klantbeoordeling</div></div><div class="xg-tw-stat"><div class="xg-tw-num">100%</div><div class="xg-tw-lbl">verzekerd</div></div></div>' +
		'<div class="xg-tw-reviews"><figure class="xg-tw-review active"><div class="xg-tw-stars">★★★★★</div><blockquote>Snel, eerlijk en vriendelijk. Binnen een half uur getaxeerd en direct uitbetaald!</blockquote><figcaption>Mariska de V. · Amsterdam</figcaption></figure></div>' +
		'<div class="xg-tw-press"><span class="xg-tw-press-lbl">Bekend van</span><span class="xg-tw-press-item">Telegraaf</span><span class="xg-tw-press-item">AD</span><span class="xg-tw-press-item">RTL Z</span><span class="xg-tw-press-item">Quote</span></div></div></section>');
	// Demo voor de charity-kaart (Leaflet + data server-side).
	out = out.replace(/<!--\s*wp:ekinese\/charity-map[^>]*?-->/g,
		'<section><div class="xg-container"><div class="xg-charity-map-wrap"><div class="xg-charity-map-head"><div class="xg-eyebrow">GOEDE DOELEN</div><h2>Waar XGOUD helpt</h2>' +
		'<p class="xg-intro">Een vast deel van elke marge gaat naar projecten in heel Nederland en België.</p>' +
		'<div class="xg-charity-counter" data-to="65168.36">€ 65.168,36</div></div>' +
		'<div class="xg-charity-map" style="display:flex;align-items:center;justify-content:center;color:#6b665c">Interactieve kaart (Leaflet) – zichtbaar in WordPress</div></div></div></section>');
	// Demo voor de koersgrafiek (server-rendered in WordPress).
	out = out.replace(/<!--\s*wp:ekinese\/price-chart[^>]*?-->/g,
		'<section><div class="xg-container"><div class="xg-chart"><div class="xg-chart-head"><h2>Goudprijs – koersverloop</h2>' +
		'<div class="xg-chart-tabs"><button>7d</button><button class="active">30d</button><button>90d</button></div></div>' +
		'<div class="xg-chart-canvas"><svg viewBox="0 0 760 220" preserveAspectRatio="none" class="xg-chart-svg">' +
		'<defs><linearGradient id="xgcd" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1f9d55" stop-opacity=".18"/><stop offset="100%" stop-color="#1f9d55" stop-opacity="0"/></linearGradient></defs>' +
		'<polygon points="0,220 8,150 130,160 260,120 390,135 520,90 650,70 752,55 760,220" fill="url(#xgcd)"/>' +
		'<polyline points="8,150 130,160 260,120 390,135 520,90 650,70 752,55" fill="none" stroke="#1f9d55" stroke-width="2.5" vector-effect="non-scaling-stroke"/></svg></div>' +
		'<div class="xg-chart-meta"><span class="xg-chart-now">€ 62,50/g</span> <span class="xg-chart-delta" style="color:#1f9d55">▲ 4,1% (30d)</span> <span class="xg-chart-range">laag € 58,90 · hoog € 63,10</span></div></div></div></section>');
	// Demo voor de prijsgarantie-badge (#2).
	out = out.replace(/<!--\s*wp:ekinese\/price-guarantee[^>]*?-->/g,
		'<div class="xg-guarantee"><div class="xg-guarantee-ico"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="currentColor" d="M12 2l8 3v6c0 5-3.4 8.5-8 11-4.6-2.5-8-6-8-11V5l8-3z"/></svg></div>' +
		'<div class="xg-guarantee-body"><strong>Beste-prijsgarantie</strong><p>Vindt u binnen 7 dagen aantoonbaar een beter bod voor hetzelfde object? Dan evenaren wij dat bod.</p></div></div>');
	// Demo voor de vergelijker (#3).
	out = out.replace(/<!--\s*wp:ekinese\/compare-table[^>]*?-->/g,
		'<div class="xg-compare"><div class="xg-table-scroll"><table class="xg-cmp-table"><thead><tr><th class="xg-cmp-feat">Wat u mag verwachten</th><th class="xg-cmp-us">XGOUD</th><th class="xg-cmp-them">Doorsnee inkoper</th></tr></thead><tbody>' +
		'<tr><td class="xg-cmp-feat">Uitbetaling op actuele dagprijs</td><td class="xg-cmp-us"><span class="xg-cmp-yes">✓</span></td><td class="xg-cmp-them">Vaak lager</td></tr>' +
		'<tr><td class="xg-cmp-feat">Marge vooraf zichtbaar</td><td class="xg-cmp-us"><span class="xg-cmp-yes">✓</span></td><td class="xg-cmp-them"><span class="xg-cmp-no">—</span></td></tr>' +
		'<tr><td class="xg-cmp-feat">Deel naar goed doel</td><td class="xg-cmp-us"><span class="xg-cmp-yes">✓</span></td><td class="xg-cmp-them"><span class="xg-cmp-no">—</span></td></tr>' +
		'</tbody></table></div><div class="xg-cmp-cta"><a class="xg-btn-gold" href="/afspraak/">Bereken uw waarde</a></div></div>');
	// Demo voor het FAQ-blok (#18).
	out = out.replace(/<!--\s*wp:ekinese\/faq[^>]*?-->/g,
		'<section class="xg-faq"><div class="xg-container"><h2>Veelgestelde vragen</h2><div class="xg-faq-list">' +
		'<details class="xg-faq-item" open><summary>Hoe verkoop ik mijn goud bij XGOUD?</summary><div class="xg-faq-a">U maakt online een afspraak, kiest kantoor/thuis/ophaal, en onze expert taxeert gratis. Bij akkoord betalen we direct uit.</div></details>' +
		'<details class="xg-faq-item"><summary>Hoe wordt de prijs bepaald?</summary><div class="xg-faq-a">Op basis van de actuele spotprijs en het gehalte. U ziet de marge altijd vooraf.</div></details>' +
		'<details class="xg-faq-item"><summary>Word ik direct uitbetaald?</summary><div class="xg-faq-a">Ja, na akkoord betalen wij direct uit, contant of per overboeking.</div></details>' +
		'</div></div></section>');
	// Demo voor de prijsprognose (#14).
	out = out.replace(/<!--\s*wp:ekinese\/price-forecast[^>]*?-->/g,
		'<section class="xg-forecast"><div class="xg-container"><div class="xg-forecast-box xg-forecast-up">' +
		'<div class="xg-forecast-head"><span class="xg-forecast-ico">▲</span><h3>Trend goud</h3></div>' +
		'<p class="xg-forecast-txt">Op basis van de trend van de afgelopen 30 dagen is de prijs van goud <strong>licht stijgend</strong>. Indicatief rond <strong>€ 64,80 / gram</strong> over 30 dagen (+3,7%).</p>' +
		'<p class="xg-forecast-disc">Let op: indicatieve trendweergave, géén beleggingsadvies.</p></div></div></section>');
	// Demo voor de kennisbank-hub (#16).
	out = out.replace(/<!--\s*wp:ekinese\/kennisbank-hub[^>]*?-->/g,
		'<section class="xg-kb"><div class="xg-container"><p class="xg-eyebrow">Kennisbank</p><h1>Alles over edelmetaal verkopen</h1>' +
		'<p class="xg-intro">Praktische uitleg, zodat u goed voorbereid verkoopt.</p><div class="xg-kb-grid">' +
		'<a class="xg-kb-card" href="#"><h3>Echt goud herkennen</h3><p>Stempels, magneettest en zuurtest.</p><span class="xg-kb-more">Lees meer ›</span></a>' +
		'<a class="xg-kb-card" href="#"><h3>14 vs 18 karaat</h3><p>Het verschil in goudgehalte en waarde.</p><span class="xg-kb-more">Lees meer ›</span></a>' +
		'<a class="xg-kb-card" href="#"><h3>Wat is sloopgoud waard?</h3><p>Hoe oude sieraden worden getaxeerd.</p><span class="xg-kb-more">Lees meer ›</span></a>' +
		'</div></div></section>');
	// Demo voor de stempel-herkenner (#13).
	out = out.replace(/<!--\s*wp:ekinese\/hallmark[^>]*?-->/g,
		'<div class="xg-hallmark"><h3 class="xg-hallmark-title">Wat zegt het stempel?</h3>' +
		'<p class="xg-hallmark-lead">Voer het stempel van uw sieraad in en zie het metaal en een prijsindicatie.</p>' +
		'<div class="xg-hallmark-row"><input class="xg-hallmark-input" placeholder="bijv. 585, 750 of 925"><button class="xg-hallmark-btn">Herken</button></div>' +
		'<div class="xg-hallmark-quick"><button class="xg-hallmark-chip">585</button><button class="xg-hallmark-chip">750</button><button class="xg-hallmark-chip">925</button><button class="xg-hallmark-chip">999</button></div>' +
		'<div class="xg-hallmark-result is-hit"><div class="xg-hallmark-badge">585</div><div class="xg-hallmark-info"><strong>14 karaat goud</strong><span>Metaal: Goud · gehalte 58,5%</span><span class="xg-hallmark-price">Indicatie: ± € 36,56 per gram</span></div></div></div>');
	// Demo voor het prijsalarm-widget (#19).
	out = out.replace(/<!--\s*wp:ekinese\/price-alert[^>]*?-->/g,
		'<section class="xg-pa"><div class="xg-container"><div class="xg-pa-box"><div class="xg-pa-head"><span class="xg-pa-ico">🔔</span><div><h3>Prijsalarm instellen</h3><p>Ontvang een mail zodra goud uw doelprijs bereikt.</p></div></div>' +
		'<div class="xg-pa-form"><select><option>Goud</option></select><select><option>stijgt boven</option></select><input placeholder="€ / gram" value="62,50"><input placeholder="uw@email.nl"><button class="xg-pa-btn">Activeer</button></div></div></div></section>');
	// Demo voor foto-taxatie (#11, scaffold-weergave).
	out = out.replace(/<!--\s*wp:ekinese\/photo-appraisal[^>]*?-->/g,
		'<section class="xg-photo"><div class="xg-container"><div class="xg-photo-box"><h3>Foto-taxatie</h3>' +
		'<p class="xg-photo-soon">Foto-taxatie is <strong>binnenkort beschikbaar</strong>. Gebruik nu onze rekentool of maak een gratis afspraak.</p>' +
		'<p><a class="xg-btn-gold" href="/afspraak/">Bereken uw waarde</a></p></div></div></section>');
	return stripBlocks(out).trim();
}

const { diamantenVerkopen, productPage } = require('./demo-content.js');

const header = stripBlocks(read('parts/header.html')).trim();
const footer = stripBlocks(read('parts/footer.html')).trim();

// Veilig een pattern renderen; bij een fout een nette placeholder tonen i.p.v.
// de hele build te laten crashen (sommige patterns gebruiken dynamische blocks).
function safePattern(file) {
	try {
		var out = pattern(file);
		return out && out.trim() ? out : '<section class="xg-container" style="padding:80px 40px"><p style="opacity:.5">[' + file + ' – dynamische inhoud, alleen in WordPress]</p></section>';
	} catch (e) {
		return '<section class="xg-container" style="padding:80px 40px"><p style="opacity:.5">[' + file + ' niet renderbaar buiten WordPress]</p></section>';
	}
}

const pages = {
	home:        { label: '★ Home',            html: safePattern('page-home.php') },
	hero:        { label: 'Hero + Calculator', html: safePattern('hero-calculator.php') },
	diamanten:   { label: '★ Diamanten',       html: diamantenVerkopen },
	edelmetalen: { label: 'Edelmetalen',       html: safePattern('blueprint-edelmetalen.php') },
	edelstenen:  { label: 'Edelstenen',        html: safePattern('blueprint-edelstenen.php') },
	horloges:    { label: 'Horloges',          html: safePattern('blueprint-horloges.php') },
	verkoop:     { label: 'Verkoop-Blaupause', html: safePattern('blueprint-verkoop.php') },
	product:     { label: '★ Produkt: Krugerrand', html: productPage },
	'cat-goud':  { label: 'Categorie: Goud',   html: safePattern('cat-goud.php') },
	'cat-zilver':{ label: 'Categorie: Zilver', html: safePattern('cat-zilver.php') },
	'cat-platina':{label: 'Categorie: Platina',html: safePattern('cat-platina.php') },
	overons:     { label: 'Over ons',          html: safePattern('page-over-ons.php') },
	'oo-verhaal':{ label: '— Ons verhaal',     html: safePattern('over-ons-verhaal.php') },
	'oo-experts':{ label: '— Team/Experts',    html: safePattern('over-ons-experts.php') },
	'oo-bedrijf':{ label: '— Bedrijf',         html: safePattern('over-ons-bedrijf.php') },
	'oo-reviews':{ label: '— Beoordelingen',   html: safePattern('over-ons-beoordelingen.php') },
	'oo-cert':   { label: '— Certificaten',    html: safePattern('over-ons-certificaten.php') },
	'oo-partner':{ label: '— Partners',        html: safePattern('over-ons-partners.php') },
	'oo-nieuws': { label: '— Nieuws',          html: safePattern('over-ons-nieuws.php') },
	'oo-pers':   { label: '— Pers',            html: safePattern('over-ons-pers.php') },
	'oo-vac':    { label: '— Vacatures',       html: safePattern('over-ons-vacatures.php') },
	services:    { label: 'Service',           html: safePattern('page-services.php') },
	'sv-taxatie':{ label: '— Gratis taxatie',  html: safePattern('services-taxatie.php') },
	'sv-thuis':  { label: '— Thuisbezoek',     html: safePattern('services-thuisbezoek.php') },
	'sv-inruil': { label: '— Inruilen',        html: safePattern('services-inruilen.php') },
	'sv-faq':    { label: '— FAQ',             html: safePattern('services-faq.php') },
	'sv-hoe':    { label: '— Hoe werkt het',   html: safePattern('services-hoe-werkt-het.php') },
	'sv-kantoor':{ label: '— Kantoorbezoek',   html: safePattern('services-kantoorbezoek.php') },
	'sv-ophaal': { label: '— Ophaalservice',   html: safePattern('services-ophaalservice.php') },
	'sv-zakelijk':{label: '— Zakelijk',        html: safePattern('services-zakelijk.php') },
	dagprijzen:  { label: 'Dagprijzen',        html: safePattern('price-dagprijzen.php') },
	goudprijs:   { label: '— Goudprijs',       html: safePattern('price-goudprijs.php') },
	contact:     { label: 'Contact',           html: safePattern('contact.php') },
	privacy:     { label: 'Privacy',           html: safePattern('page-privacy.php') },
	terms:       { label: 'Voorwaarden',       html: safePattern('page-terms.php') },
	cookies:     { label: 'Cookies',           html: safePattern('page-cookies.php') }
};

// Mock-Daten = Struktur aus inc/setup.php
// Compacte productindex (zelfde mapping als inc/products.php) → echte producten
// in de preview-calculator (per stuk: baar/munt).
function buildProductIndex() {
	try {
		const raw = JSON.parse(read('data/products.json'));
		const mm = { Gold: 'goud', Silver: 'zilver', Platinum: 'platina', Palladium: 'palladium' };
		const fm = { Baar: 'barren', Munten: 'munt', Munt: 'munt' };
		const out = {};
		for (const mk of Object.keys(raw)) {
			const mc = mm[mk]; if (!mc) continue;
			for (const fk of Object.keys(raw[mk])) {
				const fc = fm[fk]; if (!fc || !Array.isArray(raw[mk][fk])) continue;
				for (const p of raw[mk][fk]) {
					if (!p.name || !p.weight) continue;
					let fine = p.fine_weight || 0;
					if (!fine) { let c = parseFloat(String(p.carat).replace(',', '.')); if (c > 100) c /= 1000; else if (c > 1) c /= 24; fine = c > 0 ? +(p.weight * c).toFixed(4) : p.weight; }
					(out[mc] = out[mc] || {})[fc] = (out[mc][fc] || []); out[mc][fc].push([p.name, fine, p.weight]);
				}
			}
		}
		return out;
	} catch (e) { return {}; }
}

const calcData = {
	products: buildProductIndex(),
	margins: { metal: 0.08, diamond_range: 0.10, gem_range: 0.12, watch_range: 0.10, charity_share: 0.05 },
	metals: { goud:{label:'Goud',spot:62.50}, zilver:{label:'Zilver',spot:0.78}, platina:{label:'Platina',spot:28.90}, palladium:{label:'Palladium',spot:30.10} },
	metal_purities: {
		goud:{'8':0.333,'14':0.585,'18':0.750,'21':0.875,'22':0.916,'24':0.999},
		zilver:{'800':0.800,'835':0.835,'925':0.925,'999':0.999},
		platina:{'850':0.850,'900':0.900,'950':0.950,'999':0.999},
		palladium:{'500':0.500,'950':0.950,'999':0.999}
	},
	purity_labels: {'800':'80,0 %','835':'83,5 %','850':'85,0 %','900':'90,0 %','925':'Sterling 92,5%','950':'95,0 %','999':'Fijn 99,9%','500':'50,0 %'},
	conditions: { nieuw:{label:'Nieuwstaat',factor:1.00}, zeer_goed:{label:'Zeer goed',factor:0.99}, goed:{label:'Goed',factor:0.98}, voldoende:{label:'Voldoende',factor:0.96} },
	jewelry_tiers: [{min:0,bonus:0},{min:50,bonus:0.02},{min:100,bonus:0.04},{min:250,bonus:0.06}],
	diamond_base: {
		color:{D:1.00,E:0.95,F:0.90,G:0.82,H:0.74,I:0.66,J:0.58,K:0.48},
		clarity:{FL:1.00,IF:0.95,VVS1:0.90,VVS2:0.86,VS1:0.80,VS2:0.74,SI1:0.64,SI2:0.54,I1:0.40,I2:0.30,I3:0.20},
		cut:{Excellent:1.00,'Very Good':0.95,Good:0.88,Fair:0.78,Poor:0.65},
		fluor:{None:1.00,Faint:0.98,Medium:0.94,Strong:0.88}, anchor:9000
	},
	labs: ['GIA','IGI','HRD','Geen certificaat'],
	gem_base: { robijn:{label:'Robijn',anchor:3500}, saffier:{label:'Saffier',anchor:2200}, smaragd:{label:'Smaragd',anchor:2800} },
	watches: {
		rolex:{label:'Rolex',models:{'Submariner':11000,'Datejust':7500,'GMT-Master II':14000}},
		omega:{label:'Omega',models:{'Speedmaster':5500,'Seamaster':4200}},
		patek:{label:'Patek Philippe',models:{'Nautilus':38000,'Calatrava':18000}},
		cartier:{label:'Cartier',models:{'Santos':6500,'Tank':4800}}
	},
	watch_conditions: { nieuw:{label:'Nieuwstaat',factor:1.00}, zeer_goed:{label:'Zeer goed',factor:0.90}, goed:{label:'Goed',factor:0.78}, voldoende:{label:'Voldoende',factor:0.65}, service:{label:'Revisie nodig',factor:0.50} },
	watch_extras: { box:{label:'Originele doos',bonus:0.03}, papers:{label:'Certificaat/papieren',bonus:0.05} },
	watch_metals: ['Staal','Geelgoud','Witgoud','Roségoud','Platina','Staal/Goud','Titanium'],
	watch_bracelets: ['Stalen band','Leren band','Rubber','Gouden band','NATO/Textiel'],
	watch_dials: ['Zwart','Wit','Zilver','Blauw','Groen','Champagne','Grijs','Overige'],
	charity_projects: [
		{id:'social',label:'Maatschappelijk werk',weight:0.25,recipients:['Stichting Buurtwerk Amsterdam','Sociaal Steunpunt Rotterdam','Voedselbank Den Haag']},
		{id:'kindergarten',label:'Kinderdagverblijven',weight:0.20,recipients:['Kinderopvang De Zonnebloem','KDV Het Speelkwartier','Peuterspeelzaal Pippeloentje']},
		{id:'shelter',label:'Vrouwenopvang',weight:0.25,recipients:['Blijf Groep Amsterdam','Vrouwenopvang Rotterdam','Veilig Thuis Utrecht']},
		{id:'sport',label:'Sportcentra',weight:0.15,recipients:['Sportclub Jeugd Eindhoven','Buurtsport Tilburg','Zwemvereniging De Dolfijn']},
		{id:'school',label:'Scholen',weight:0.15,recipients:['Basisschool De Regenboog','OBS Het Kompas','Vrije School Zutphen','Montessori Amsterdam']}
	],
	currency: 'EUR'
};

const nav = Object.keys(pages).map((k, i) =>
	`<option value="${k}"${i===0?' selected':''}>${pages[k].label}</option>`
).join('');

const sections = Object.keys(pages).map((k, i) =>
	`<section class="tst-page" data-page="${k}"${i===0?'':' hidden'}>${pages[k].html}</section>`
).join('\n');

// Assets INLINE einbetten → eine einzige portable Datei (funktioniert auch
// allein im Downloads-Ordner, ohne assets/-Verzeichnis daneben).
const css = [
	'assets/css/theme.css',
	'assets/css/header-footer.css',
	'assets/css/blueprint.css',
	'assets/css/calculator.css',
	'assets/css/chat.css',
	'assets/css/locale.css',
	'assets/css/newsletter.css',
	'assets/css/offices.css',
	'assets/css/charity.css'
].map(read).join('\n\n');

const js = [
	'assets/js/theme.js',
	'assets/js/locale.js',
	'assets/js/i18n.js',
	'assets/js/header-footer.js',
	'assets/js/blueprint.js',
	'assets/js/calculator.js',
	'assets/js/chat.js',
	'assets/js/search-assist.js',
	'assets/js/newsletter.js'
].map(read).join('\n;\n');

const html = `<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XGOUD – Test-Suite (alle Seiten)</title>
<style>
${css}

/* ---- Test-Toolbar ---- */
.tst-toolbar {
	position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%);
	z-index: 100002; display: flex; gap: 8px; align-items: center;
	background: var(--white,#fff); border: 1px solid var(--line,#ddd);
	box-shadow: 0 12px 40px rgba(0,0,0,.2); padding: 8px 12px; border-radius: 40px; max-width: 94vw;
}
.tst-label { font: 700 11px Arial, sans-serif; letter-spacing: .5px; color: var(--red,#AE1E1E); white-space: nowrap; }
.tst-select {
	font: 700 13px Arial, sans-serif; color: var(--ink,#222);
	border: 1px solid var(--line,#ddd); background: #fff; padding: 8px 12px;
	border-radius: 30px; cursor: pointer; max-width: 52vw;
}
.tst-tab {
	border: none; background: var(--red,#AE1E1E); color: #fff; cursor: pointer;
	font: 700 12px Arial, sans-serif; letter-spacing: .3px;
	padding: 8px 14px; border-radius: 30px; white-space: nowrap;
}
.tst-nav-btn {
	border: 1px solid var(--line,#ddd); background:#fff; color: var(--ink,#222);
	cursor: pointer; font: 700 14px Arial; width: 34px; height: 34px; border-radius: 50%;
}
</style>
</head>
<body>

${header}

<main>
${sections}
<section class="xg-newsletter" style="margin:40px auto"><div class="xg-newsletter-inner"><div class="xg-newsletter-text"><h2>Blijf op de hoogte</h2><p>Ontvang de actuele goudprijs en aanbiedingen.</p></div><form class="xg-newsletter-form"><input type="email" required placeholder="Uw e-mailadres"><input type="text" placeholder="Stad (optioneel)"><button type="submit">Aanmelden</button></form><div class="xg-newsletter-msg"></div></div></section>
</main>

${footer}

<div class="tst-toolbar">
	<span class="tst-label">XGOUD</span>
	<button class="tst-nav-btn" id="tstPrev" title="Vorige">‹</button>
	<select class="tst-select" id="tstSelect">${nav}</select>
	<button class="tst-nav-btn" id="tstNext" title="Volgende">›</button>
	<button class="tst-tab" id="tstFill">Demo-tekst</button>
</div>

<script>
window.XG_CALC_DATA = ${JSON.stringify(calcData, null, 1)};
window.XG_CHAT = { open: true }; window.XG_NEWSLETTER = {}; /* Demo: lokaler Fallback-Bot (kein Server) */
</script>
<script>
${js}
</script>
<script>
/* Seiten-Umschaltung via Dropdown + Pfeile */
var tstSelect = document.getElementById('tstSelect');
function showPage(go) {
	document.querySelectorAll('.tst-page').forEach(function (p) { p.hidden = (p.getAttribute('data-page') !== go); });
	tstSelect.value = go;
	window.scrollTo(0, 0);
}
tstSelect.addEventListener('change', function () { showPage(this.value); });
document.getElementById('tstPrev').addEventListener('click', function () {
	var i = tstSelect.selectedIndex; if (i > 0) { tstSelect.selectedIndex = i - 1; showPage(tstSelect.value); }
});
document.getElementById('tstNext').addEventListener('click', function () {
	var i = tstSelect.selectedIndex; if (i < tstSelect.options.length - 1) { tstSelect.selectedIndex = i + 1; showPage(tstSelect.value); }
});

/* Demo-Text (Nederlands) in leere Gutenberg-Blöcke – standardmäßig AN,
   damit die Klassen/Struktur sofort sichtbar sind. */
var FILLED = false;
var SAMPLE = {
	H1: 'Goud verkopen tegen de beste prijs',
	H2: 'Waarom kiezen voor XGOUD?',
	H3: 'Eerlijk, snel en transparant',
	H4: 'Vertrouwd sinds 2009',
	P: 'Bij XGOUD krijgt u een eerlijke prijs voor uw goud, diamanten en horloges. Transparant, veilig en met persoonlijke aandacht voor elke transactie.'
};
function applyFill(on) {
	FILLED = on;
	document.querySelectorAll('.tst-page').forEach(function (page) {
		page.querySelectorAll('h1,h2,h3,h4,p,li,th,td,.wp-block-button__link').forEach(function (el) {
			if (el.closest('.xg-calc')) return; // Calculator nie überschreiben
			if (FILLED) {
				if (!el.textContent.trim()) {
					el.dataset.tstFilled = '1';
					var t = el.tagName;
					el.textContent = SAMPLE[t] || (t === 'LI' ? 'Navigatie-item' : (t === 'TH' ? 'Kolom' : (t === 'TD' ? 'Waarde' : 'Bekijk meer')));
				}
			} else if (el.dataset.tstFilled) {
				el.textContent = ''; delete el.dataset.tstFilled;
			}
		});
	});
}
document.getElementById('tstFill').addEventListener('click', function () { applyFill(!FILLED); });
applyFill(true); // Demo-Text beim Laden direkt anzeigen
</script>
</body>
</html>`;

try { fs.unlinkSync('.tst-render.php'); } catch (e) { /* nooit fataal */ }

fs.writeFileSync('test-suite.html', html);
console.log('test-suite.html geschrieben (' + (html.length / 1024).toFixed(0) + ' KB) – vollständig eigenständig');

