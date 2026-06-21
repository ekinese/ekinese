/**
 * Baut charity-demo.html: Live-Ticker, Transparenz-Board (wer/wieviel/wann)
 * und ein XGOUD-Zertifikat. Eigenständig, Header/Footer + Theme inline.
 *
 * Aufruf: node build-charity.js
 */
const fs = require('fs');
const read = p => fs.readFileSync(p, 'utf8');
const strip = s => s.replace(/<!--\s*\/?wp:[^>]*?-->/g, '');

const header = strip(read('parts/header.html')).trim();
const footer = strip(read('parts/footer.html')).trim();
const css = ['assets/css/theme.css', 'assets/css/header-footer.css', 'assets/css/charity.css'].map(read).join('\n\n');
const js = ['assets/js/theme.js', 'assets/js/header-footer.js', 'assets/js/charity.js'].map(read).join('\n;\n');

const DATA = {
	month_total: 65168.36,
	currency: 'EUR',
	categories: [
		{ id: 'social', label: 'Maatschappelijk werk', projects: [
			{ name: 'Stichting Buurtwerk', city: 'Amsterdam', accrued: 4210.50, paid: 12000, website: '#', payouts: [{ period: 'Q1 2026', amount: 6000 }, { period: 'Q4 2025', amount: 6000 }] },
			{ name: 'Sociaal Steunpunt', city: 'Rotterdam', accrued: 1875.00, paid: 3000, website: '#', payouts: [{ period: 'Q1 2026', amount: 3000 }] }
		] },
		{ id: 'kindergarten', label: 'Kinderdagverblijven', projects: [
			{ name: 'De Zonnebloem', city: 'Eindhoven', accrued: 3120.80, paid: 4500, website: '#', payouts: [{ period: 'Q1 2026', amount: 4500 }] },
			{ name: 'Het Speelkwartier', city: 'Tilburg', accrued: 980.00, paid: 0, website: '#', payouts: [] }
		] },
		{ id: 'shelter', label: 'Vrouwenopvang', projects: [
			{ name: 'Blijf Groep', city: 'Amsterdam', accrued: 5400.00, paid: 8000, website: '#', payouts: [{ period: 'Q1 2026', amount: 8000 }] }
		] },
		{ id: 'sport', label: 'Sportcentra', projects: [
			{ name: 'Buurtsport', city: 'Tilburg', accrued: 1260.00, paid: 2000, website: '#', payouts: [{ period: 'Q1 2026', amount: 2000 }] }
		] },
		{ id: 'school', label: 'Scholen', projects: [
			{ name: 'Basisschool De Regenboog', city: 'Utrecht', accrued: 2240.00, paid: 5000, website: '#', payouts: [{ period: 'Q1 2026', amount: 5000 }] },
			{ name: 'OBS Het Kompas', city: 'Groningen', accrued: 760.00, paid: 0, website: '#', payouts: [] }
		] }
	]
};

const certificate = `
<div class="xg-certificate">
	<div class="xg-cert-brand">X<span>GOUD</span></div>
	<div class="xg-cert-kicker">Certificaat van steun</div>
	<h2>Hartelijk dank, Anna de Vries</h2>
	<p>Met uw verkoop bij XGOUD heeft u <strong>€ 38,50</strong> bijgedragen aan</p>
	<div class="xg-cert-project">Basisschool De Regenboog (Utrecht)</div>
	<p class="xg-cert-sub">U heeft hiermee iets goeds gedaan voor een goed doel in uw omgeving.</p>
	<div class="xg-cert-foot"><span>2026-06-21</span><span>XGOUD &middot; xgoud.nl</span></div>
</div>`;

const html = `<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XGOUD – Goede doelen (demo)</title>
<style>
${css}
.xg-charity-hero { max-width: var(--max-w,1400px); margin: 0 auto; padding: 40px 40px 0; }
.xg-charity-hero h1 { font-size: 48px; margin: 0 0 12px; color: var(--ink,#1c1a16); }
.xg-charity-hero p { font-size: 18px; color: var(--ink-soft,#6b665c); max-width: 760px; }
.xg-demo-sep { max-width: var(--max-w,1400px); margin: 0 auto; padding: 30px 40px 0; }
.xg-demo-sep h2 { font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: var(--ink-soft,#777); border-top: 1px solid var(--line,#e3ddd0); padding-top: 24px; }
</style>
</head>
<body>
${header}

<main>
	<div class="xg-charity-ticker">
		<span class="xg-charity-ticker-label">XGOUD heeft deze maand aan goede doelen gegeven:</span>
		<span class="xg-charity-ticker-amount" data-xg-charity-total>€ 0,00</span>
		<div class="xg-charity-ticker-projects">
			<span>Maatschappelijk werk</span><span>Kinderdagverblijven</span><span>Vrouwenopvang</span><span>Sportcentra</span><span>Scholen</span>
		</div>
	</div>

	<div class="xg-charity-hero">
		<h1>Wat wij teruggeven</h1>
		<p>In elke transactie zit een vast deel voor het goede doel. Hieronder ziet u transparant welk project hoeveel ontvangt — en wanneer. Klik op een categorie.</p>
	</div>

	<div class="xg-charity-board"></div>

	<div class="xg-demo-sep"><h2>↓ XGOUD-certificaat voor de klant</h2></div>
	${certificate}
</main>

${footer}

<script>window.XG_CHARITY = ${JSON.stringify(DATA)};</script>
<script>${js}</script>
</body>
</html>`;

fs.writeFileSync('charity-demo.html', html);
console.log('charity-demo.html geschrieben (' + (html.length / 1024).toFixed(0) + ' KB)');
