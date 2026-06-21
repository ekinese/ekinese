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
	contact:     { label: 'Contact',           html: safePattern('contact.php') },
	privacy:     { label: 'Privacy',           html: safePattern('page-privacy.php') },
	terms:       { label: 'Voorwaarden',       html: safePattern('page-terms.php') },
	cookies:     { label: 'Cookies',           html: safePattern('page-cookies.php') }
};

// Mock-Daten = Struktur aus inc/setup.php
const calcData = {
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

