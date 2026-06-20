/**
 * Baut eine eigenständige Test-HTML (test-suite.html) aus den ECHTEN
 * Theme-Dateien: parts/header.html, parts/footer.html und den Patterns.
 * Entfernt Gutenberg-/PHP-Hüllen und verlinkt die echten CSS/JS-Assets.
 *
 * Aufruf:  node build-test.js
 */
const fs = require('fs');

function read(p) { return fs.readFileSync(p, 'utf8'); }

// Gutenberg-Kommentare entfernen (<!-- wp:... -->, <!-- /wp:... -->, self-closing)
function stripBlocks(s) {
	return s.replace(/<!--\s*\/?wp:[^>]*?-->/g, '');
}
// PHP-Header am Anfang einer Pattern-Datei entfernen
function stripPhp(s) {
	return s.replace(/^<\?php[\s\S]*?\?>\s*/, '');
}
function pattern(file) { return stripBlocks(stripPhp(read('patterns/' + file))).trim(); }

const header = stripBlocks(read('parts/header.html')).trim();
const footer = stripBlocks(read('parts/footer.html')).trim();

const pages = {
	hero:        { label: 'Hero + Calculator', html: pattern('hero-calculator.php') },
	edelmetalen: { label: 'Edelmetalen',       html: pattern('blueprint-edelmetalen.php') },
	edelstenen:  { label: 'Edelstenen',        html: pattern('blueprint-edelstenen.php') },
	horloges:    { label: 'Horloges',          html: pattern('blueprint-horloges.php') },
	verkoop:     { label: 'Verkoop-Blaupause', html: pattern('blueprint-verkoop.php') },
	product:     { label: 'Produktseite',      html: pattern('product-page.php') }
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
	`<button class="tst-tab${i===0?' active':''}" data-go="${k}">${pages[k].label}</button>`
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
	'assets/css/calculator.css'
].map(read).join('\n\n');

const js = [
	'assets/js/theme.js',
	'assets/js/header-footer.js',
	'assets/js/blueprint.js',
	'assets/js/calculator.js'
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
	z-index: 100002; display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;
	background: var(--white,#fff); border: 1px solid var(--line,#ddd);
	box-shadow: 0 12px 40px rgba(0,0,0,.2); padding: 8px; border-radius: 40px; max-width: 94vw;
}
.tst-tab {
	border: none; background: transparent; cursor: pointer;
	font: 700 12px Arial, sans-serif; letter-spacing: .5px;
	color: var(--ink-soft,#666); padding: 8px 14px; border-radius: 30px;
	transition: background .2s, color .2s;
}
.tst-tab:hover { color: var(--red,#AE1E1E); }
.tst-tab.active { background: var(--red,#AE1E1E); color: #fff; }
.tst-fill { margin-left: 6px; }
</style>
</head>
<body>

${header}

<main>
${sections}
</main>

${footer}

<div class="tst-toolbar">
	${nav}
	<button class="tst-tab tst-fill" id="tstFill">Demo-tekst aan/uit</button>
</div>

<script>
window.XG_CALC_DATA = ${JSON.stringify(calcData, null, 1)};
</script>
<script>
${js}
</script>
<script>
/* Seiten-Umschaltung */
document.querySelectorAll('.tst-tab[data-go]').forEach(function (b) {
	b.addEventListener('click', function () {
		document.querySelectorAll('.tst-tab[data-go]').forEach(function (x) { x.classList.remove('active'); });
		b.classList.add('active');
		var go = b.getAttribute('data-go');
		document.querySelectorAll('.tst-page').forEach(function (p) { p.hidden = (p.getAttribute('data-page') !== go); });
		window.scrollTo(0, 0);
	});
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

fs.writeFileSync('test-suite.html', html);
console.log('test-suite.html geschrieben (' + (html.length / 1024).toFixed(0) + ' KB) – vollständig eigenständig');

