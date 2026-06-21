/**
 * Baut eine eigenständige Demo (offices-demo.html) für die Kantoren:
 * Übersicht (interaktive Karte + Liste, Eindhoven = HQ) + Einzelseite.
 * Leaflet via CDN (Internet nötig beim Öffnen). Header/Footer + Theme inline.
 *
 * Aufruf: node build-offices.js
 */
const fs = require('fs');
const read = p => fs.readFileSync(p, 'utf8');
const strip = s => s.replace(/<!--\s*\/?wp:[^>]*?-->/g, '');

const header = strip(read('parts/header.html')).trim();
const footer = strip(read('parts/footer.html')).trim();
const css = ['assets/css/theme.css', 'assets/css/header-footer.css', 'assets/css/offices.css'].map(read).join('\n\n');
const js = ['assets/js/theme.js', 'assets/js/header-footer.js', 'assets/js/offices.js'].map(read).join('\n;\n');

const H = { ma: '09:00-17:30', di: '09:00-17:30', wo: '09:00-17:30', do: '09:00-17:30', vr: '09:00-17:30', za: '10:00-16:00' };
const HQH = { ma: '09:00-18:00', di: '09:00-18:00', wo: '09:00-18:00', do: '09:00-18:00', vr: '09:00-18:00', za: '10:00-17:00' };

const OFFICES = [
	{ id: 1, name: 'XGOUD Eindhoven', slug: 'eindhoven', url: '#eindhoven', street: 'Stratumsedijk 23', postcode: '5611 NA', city: 'Eindhoven', lat: 51.4361, lng: 5.4836, phone: '085 060 3009', email: 'eindhoven@xgoud.nl', hours: HQH, is_hq: true, zone: 'zuid' },
	{ id: 2, name: 'XGOUD Amsterdam', slug: 'amsterdam', url: '#', street: 'Damrak 70', postcode: '1012 LM', city: 'Amsterdam', lat: 52.3743, lng: 4.8950, phone: '085 060 3009', email: 'amsterdam@xgoud.nl', hours: H, is_hq: false, zone: 'noord' },
	{ id: 3, name: 'XGOUD Rotterdam', slug: 'rotterdam', url: '#', street: 'Coolsingel 100', postcode: '3012 AG', city: 'Rotterdam', lat: 51.9225, lng: 4.4792, phone: '085 060 3009', email: 'rotterdam@xgoud.nl', hours: H, is_hq: false, zone: 'zuid-holland' },
	{ id: 4, name: 'XGOUD Den Haag', slug: 'den-haag', url: '#', street: 'Spui 70', postcode: '2511 BT', city: 'Den Haag', lat: 52.0766, lng: 4.3127, phone: '085 060 3009', email: 'denhaag@xgoud.nl', hours: H, is_hq: false, zone: 'zuid-holland' },
	{ id: 5, name: 'XGOUD Utrecht', slug: 'utrecht', url: '#', street: 'Oudegracht 200', postcode: '3511 NR', city: 'Utrecht', lat: 52.0908, lng: 5.1217, phone: '085 060 3009', email: 'utrecht@xgoud.nl', hours: H, is_hq: false, zone: 'midden' },
	{ id: 6, name: 'XGOUD Tilburg', slug: 'tilburg', url: '#', street: 'Heuvelstraat 50', postcode: '5038 AD', city: 'Tilburg', lat: 51.5606, lng: 5.0833, phone: '085 060 3009', email: 'tilburg@xgoud.nl', hours: H, is_hq: false, zone: 'zuid' },
	{ id: 7, name: 'XGOUD Groningen', slug: 'groningen', url: '#', street: 'Grote Markt 24', postcode: '9711 LV', city: 'Groningen', lat: 53.2192, lng: 6.5680, phone: '085 060 3009', email: 'groningen@xgoud.nl', hours: H, is_hq: false, zone: 'noord' }
];

const overview = `
<section class="xg-offices">
	<div class="xg-offices-intro">
		<h1>Onze kantoren</h1>
		<p>Bezoek een van onze 40+ vestigingen in Nederland of laat onze expert bij u langskomen. Vind hieronder het kantoor bij u in de buurt.</p>
	</div>
	<div class="xg-hq-banner">
		<span class="xg-hq-badge">Hoofdkantoor</span>
		<div><strong>XGOUD Eindhoven</strong> &nbsp; <span>6 dagen per week geopend — altijd een expert aanwezig.</span></div>
		<span class="xg-hq-perk">Reisbonus bij bezoek aan Eindhoven</span>
	</div>
	<div class="xg-offices-bar">
		<div class="xg-offices-search">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
			<input type="text" placeholder="Zoek op stad of postcode…">
		</div>
		<div class="xg-offices-count"></div>
	</div>
	<div class="xg-offices-layout">
		<div id="xg-offices-map"></div>
		<div class="xg-offices-list"></div>
	</div>
</section>
`;

const single = `
<section class="xg-office-single" data-xg-office="eindhoven">
	<div class="xg-office-hero">
		<h1>XGOUD Eindhoven</h1>
		<span class="xg-hq-tag">Hoofdkantoor</span>
	</div>
	<div class="xg-office-grid">
		<div class="xg-office-info"></div>
		<div class="xg-office-mapcol">
			<div class="xg-office-map"></div>
			<iframe class="xg-office-embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
		</div>
	</div>
</section>
`;

const html = `<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XGOUD – Kantoren (demo)</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
${css}
.xg-demo-sep { max-width: var(--max-w,1400px); margin: 10px auto; padding: 0 40px; }
.xg-demo-sep h2 { font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: var(--ink-soft,#777); border-top: 1px solid var(--line,#e3ddd0); padding-top: 24px; }
</style>
</head>
<body>
${header}

<main>
${overview}
<div class="xg-demo-sep"><h2>↓ Voorbeeld: één kantoorpagina</h2></div>
${single}
</main>

${footer}

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.XG_OFFICES = ${JSON.stringify(OFFICES)};</script>
<script>${js}</script>
</body>
</html>`;

fs.writeFileSync('offices-demo.html', html);
console.log('offices-demo.html geschrieben (' + (html.length / 1024).toFixed(0) + ' KB)');
