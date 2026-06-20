/**
 * Fertiger NL-Beispielinhalt "Diamanten verkopen" (befüllte Blaupause).
 * Nutzt EXAKT die Klassen aus assets/css/blueprint.css.
 * Wird von build-test.js (als Seite in der Test-Suite) genutzt.
 */

const benefits = [
	['01','Rapaport-prijzen','Wij taxeren volgens de internationale Rapaport-prijslijst — de wereldwijde standaard. Zo weet u zeker dat u een eerlijke prijs ontvangt.'],
	['02','Gratis taxatie','Onze gecertificeerde experts taxeren uw diamant gratis en vrijblijvend, ter plaatse of bij u thuis.'],
	['03','Directe uitbetaling','Akkoord met ons bod? U ontvangt uw geld direct per bankoverschrijving of contant.'],
	['04','Discreet & verzekerd','Elke transactie verloopt discreet, veilig en volledig verzekerd. Uw vertrouwen staat voorop.']
];
const toc = [
	['01','Waarom XGOUD'],['02',"De 4 C's"],['03','Onze experts'],
	['04','Zo werkt het'],['05','Inkoopprijzen'],['06','Veelgestelde vragen']
];
const cs = [
	['Carat','Het karaatgewicht bepaalt de massa van de diamant. Hoe hoger het gewicht, hoe zeldzamer en waardevoller — bij gelijke kwaliteit.'],
	['Color','Kleur wordt beoordeeld van D (kleurloos) tot Z. Hoe kleurlozer de diamant, hoe hoger doorgaans de waarde.'],
	['Clarity','Zuiverheid loopt van FL (flawless) tot I3. Minder insluitsels betekent een hogere zuiverheid en waarde.'],
	['Cut','De slijpvorm bepaalt de schittering. Een excellent geslepen diamant reflecteert het licht optimaal.']
];
const steps = [
	['1','Aanmelden','Bereken online een indicatie of maak direct een afspraak met een expert.'],
	['2','Taxatie',"Onze gemmoloog beoordeelt uw diamant op de 4 C's en het certificaat."],
	['3','Bod & akkoord','U ontvangt een transparant bod op basis van de Rapaport-prijslijst.'],
	['4','Uitbetaling','Bij akkoord betalen wij direct uit — per bank of contant ter plaatse.']
];
const trust = [
	['◆','GIA-gecertificeerd','Onze taxateurs werken volgens de strengste internationale normen van het GIA.'],
	['◆','Rapaport-basis','Elke taxatie is gebaseerd op de actuele, wereldwijd erkende Rapaport-prijslijst.'],
	['◆','100% verzekerd','Uw diamant is tijdens het hele proces volledig verzekerd en veilig.'],
	['◆','20+ jaar ervaring','Decennia aan kennis van de diamantmarkt staan tot uw beschikking.']
];
const rap = [
	'Wekelijks bijgewerkte, internationaal erkende prijsreferentie.',
	'Transparante en marktconforme waardebepaling.',
	'Sinds 1978 de standaard in de diamanthandel.',
	'Gebruikt door handelaren wereldwijd.'
];
const reviews = [
	['Snelle en eerlijke taxatie. Direct uitbetaald, precies zoals afgesproken.','— Anna K.'],
	['Zeer professioneel en discreet. Een betere prijs dan ik elders kreeg.','— Mark V.'],
	['Persoonlijk advies en een transparant bod op basis van Rapaport. Aanrader.','— Sophie D.']
];
const links = [
	['Goud verkopen','Bekijk onze actuele inkoopprijzen voor goud per gram.'],
	['Horloges verkopen','Verkoop uw luxe horloge tegen een eerlijke dagprijs.'],
	['Onze vestigingen','Vind een XGOUD-kantoor bij u in de buurt.']
];
const faqs = [
	['Heb ik een certificaat nodig om mijn diamant te verkopen?','Nee. Een GIA-, IGI- of HRD-certificaat helpt bij een nauwkeurige taxatie, maar onze gemmoloog kan uw diamant ook zonder certificaat volledig beoordelen.'],
	['Hoe wordt de prijs van mijn diamant bepaald?',"Wij taxeren op basis van de 4 C's en de actuele Rapaport-prijslijst, de internationale standaard voor diamantprijzen."],
	['Is de taxatie echt gratis?','Ja, de taxatie is volledig gratis en vrijblijvend. U beslist daarna zelf of u verkoopt.'],
	['Wanneer ontvang ik mijn geld?','Bij akkoord betalen wij direct uit — per bankoverschrijving of contant ter plaatse.']
];

const diamantenVerkopen = `
<div class="xg-blueprint">

<div class="xg-charity-ticker">
	<span class="xg-charity-ticker-label">XGOUD heeft deze maand aan goede doelen gegeven:</span>
	<span class="xg-charity-ticker-amount">€65.168,36</span>
	<div class="xg-charity-ticker-projects">
		<span>Maatschappelijk werk</span><span>Kinderdagverblijven</span><span>Vrouwenopvang</span><span>Sportcentra</span><span>Scholen</span>
	</div>
</div>

<section class="xg-hero-v2"><div class="xg-container">
	<div class="hero-kicker">DIAMANTEN VERKOPEN</div>
	<h1>Verkoop uw diamanten tegen de hoogste dagprijs</h1>
	<p class="hero-lead">Bij XGOUD krijgt u een eerlijke, transparante taxatie op basis van de actuele Rapaport-prijslijst. Gratis, vrijblijvend en met directe uitbetaling.</p>
	<div class="xg-hero-buttons">
		<a class="xg-btn-gold" href="#">Bereken uw waarde</a>
		<a class="xg-btn-outline" href="#">Maak een afspraak</a>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xgoud-toc">
		<div class="xgoud-toc-header"><p>Alles wat u moet weten over het verkopen van uw diamanten — overzichtelijk op een rij.</p></div>
		<div class="xgoud-toc-grid">
			${toc.map(t => `<a class="xgoud-toc-item" href="#"><span class="nr">${t[0]}</span><span class="title">${t[1]}</span><span class="arrow">›</span></a>`).join('')}
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-why">
		<div class="xg-eyebrow">WAAROM XGOUD</div>
		<h2>Waarom uw diamant verkopen bij XGOUD?</h2>
		<p class="xg-intro">Met meer dan 20 jaar ervaring, GIA-gecertificeerde experts en taxaties volgens de Rapaport-standaard bent u verzekerd van een eerlijke prijs en een betrouwbare afhandeling.</p>
		<div class="xg-benefits">
			${benefits.map(b => `<div class="xg-benefit"><span class="xg-b-num">${b[0]}</span><span class="xg-b-title">${b[1]}</span><p>${b[2]}</p></div>`).join('')}
		</div>
	</div>
</div></section>

<section class="xg-row-3"><div class="xg-container">
	<h2 class="xg-section-title">De 4 C's — zo bepalen wij de waarde</h2>
	<div class="xg-grid-4">
		${cs.map(c => `<div class="xg-c-card"><h3>${c[0]}</h3><p>${c[1]}</p></div>`).join('')}
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xgoud-cta-modern">
		<div class="xgoud-cta-image" style="background:#ead9bd"></div>
		<div class="xgoud-cta-content">
			<h2>Benieuwd naar de <span>waarde</span> van uw diamant?</h2>
			<p class="xgoud-subtitle">Gebruik onze rekentool of maak direct een afspraak met een van onze diamantexperts. Gratis en vrijblijvend.</p>
			<a class="cta-button" href="#">Bereken nu uw waarde</a>
			<div class="cta-offices">Of bezoek een van onze 40+ vestigingen in Nederland.</div>
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-grid-2" style="align-items:start">
		<div class="xg-expert-box">
			<h2>Onze diamantexpert</h2>
			<p>Met meer dan 20 jaar ervaring in de diamanthandel taxeert onze GIA-gecertificeerde gemmoloog elke steen nauwkeurig op kleur, zuiverheid, slijpvorm en karaatgewicht.</p>
			<ul class="xg-diamond-list">
				<li>Persoonlijke en deskundige beoordeling</li>
				<li>Taxatie volgens internationale normen</li>
				<li>Eerlijk en onderbouwd bod</li>
			</ul>
		</div>
		<div class="xg-trust-grid">
			${trust.map(t => `<div class="xg-trust-card"><div class="xg-trust-icon">${t[0]}</div><h3>${t[1]}</h3><p>${t[2]}</p></div>`).join('')}
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-steps-content">
		<h2>Zo werkt het</h2>
		<p>In vier eenvoudige stappen verkoopt u uw diamant — snel, veilig en transparant.</p>
	</div>
	<div class="xg-grid-4">
		${steps.map(s => `<div class="xg-step-card"><div class="xg-step-number">${s[0]}</div><h3>${s[1]}</h3><p>${s[2]}</p></div>`).join('')}
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-market-content">
		<h2>De diamantmarkt</h2>
		<p>Diamantprijzen worden internationaal bepaald door vraag, aanbod en kwaliteit. Wij volgen dagelijks de markt zodat u altijd een actuele, eerlijke prijs krijgt.</p>
		<div class="xg-formula-box">
			<div class="xg-formula-title">Prijsopbouw</div>
			<div class="xg-formula">Rapaport-prijs × kwaliteitsfactor − marge</div>
			<div class="xg-formula-note">De kwaliteitsfactor volgt uit de 4 C's. De marge dekt onze dienstverlening en een vast deel gaat naar het goede doel.</div>
		</div>
	</div>
</div></section>

<section class="xg-price-section"><div class="xg-container">
	<h2>Indicatieve inkoopprijzen</h2>
	<p>Onderstaande bedragen zijn indicatief. De definitieve prijs volgt na taxatie en is afhankelijk van het certificaat.</p>
	<div class="xg-table-wrapper">
		<table class="xg-price-table">
			<thead><tr><th>Karaatgewicht</th><th>Kwaliteit</th><th>Indicatie (van–tot)</th></tr></thead>
			<tbody>
				<tr><td>0,50 ct</td><td>G / VS1 / Excellent</td><td>€ 900 – € 1.300</td></tr>
				<tr><td>1,00 ct</td><td>F / VVS2 / Excellent</td><td>€ 4.500 – € 6.200</td></tr>
				<tr><td>1,50 ct</td><td>E / VS2 / Very Good</td><td>€ 8.000 – € 10.500</td></tr>
				<tr><td>2,00 ct</td><td>D / IF / Excellent</td><td>€ 22.000 – € 28.000</td></tr>
			</tbody>
		</table>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-grid-2">
		<div class="xg-rapaport-left-column">
			<div class="xg-rapaport-left">
				<h2>Wat is de Rapaport-prijslijst?</h2>
				<p>De Rapaport Diamond Report is sinds 1978 de internationale referentie voor diamantprijzen. De lijst wordt wekelijks bijgewerkt en wereldwijd door de handel gebruikt.</p>
				<p>Door volgens Rapaport te taxeren, garanderen wij u een marktconforme en eerlijke prijs — volledig transparant.</p>
			</div>
		</div>
		<div class="xg-rapaport-right-column">
			<div class="xg-rapaport-box">
				${rap.map(r => `<div class="xg-rapaport-item">${r}</div>`).join('')}
			</div>
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-cert-section">
		<div class="xg-cert-content">
			<h2>Certificering: GIA, IGI & HRD</h2>
			<p>Een diamant met certificaat laat zich nauwkeuriger taxeren en levert doorgaans meer op. Geen certificaat? Onze gemmoloog beoordeelt uw steen ter plaatse.</p>
			<ul class="xg-cert-list">
				<li>GIA — Gemological Institute of America</li>
				<li>IGI — International Gemological Institute</li>
				<li>HRD — Hoge Raad voor Diamant (Antwerpen)</li>
			</ul>
		</div>
		<div class="xg-cert-image" style="background:#ead9bd;min-height:360px"></div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-eeat-content">
		<h2>Expertise waar u op kunt vertrouwen</h2>
		<p>Onze taxaties worden uitgevoerd door gecertificeerde specialisten met jarenlange ervaring in de internationale diamanthandel.</p>
	</div>
	<div class="xg-author-row">
		<div class="xg-author-card">
			<h2>Daniël Visser</h2>
			<div class="xg-author-role">GIA-gecertificeerd gemmoloog</div>
			<div class="xg-author-company">XGOUD Diamantexpertise</div>
			<a class="xg-author-website" href="#">xgoud.nl</a>
			<p>Daniël taxeert al meer dan 20 jaar diamanten volgens de strengste internationale normen en begeleidt klanten persoonlijk bij elke verkoop.</p>
		</div>
		<div class="xg-author-card">
			<h2>Lisa Bakker</h2>
			<div class="xg-author-role">Senior taxateur edelstenen</div>
			<div class="xg-author-company">XGOUD Diamantexpertise</div>
			<a class="xg-author-website" href="#">xgoud.nl</a>
			<p>Lisa is gespecialiseerd in kleurstenen en diamanten en staat bekend om haar nauwkeurige, eerlijke beoordelingen.</p>
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-trustpilot-row">
		<div class="xg-trustpilot-score">
			<h2>Wat onze klanten zeggen</h2>
			<div class="xg-trustpilot-stars">★★★★★</div>
			<div class="xg-trustpilot-rating">4,8</div>
			<div class="xg-trustpilot-count">op basis van 2.400+ beoordelingen</div>
			<div class="xg-trustpilot-brand">Trustpilot</div>
		</div>
		<div class="xg-trustpilot-reviews">
			${reviews.map(r => `<div class="xg-review-card"><div class="xg-review-stars">★★★★★</div><p>${r[0]}</p><span>${r[1]}</span></div>`).join('')}
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-links-content">
		<h2>Meer informatie</h2>
		<p>Ontdek wat u nog meer bij XGOUD kunt verkopen.</p>
	</div>
	<div class="xg-links-grid">
		${links.map(l => `<a class="xg-link-card" href="#"><div class="xg-link-content"><h3>${l[0]}</h3><p>${l[1]}</p></div><span>›</span></a>`).join('')}
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-faq">
		<h2>Veelgestelde vragen</h2>
		<div class="xg-faq-list">
			${faqs.map(f => `<div class="xg-faq-item"><div class="xg-faq-question">${f[0]}</div><div class="xg-faq-answer"><p>${f[1]}</p></div></div>`).join('')}
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-grid-2">
		<div class="xg-final-cta-content">
			<h2>Klaar om uw diamant te verkopen?</h2>
			<p>Maak vandaag nog een afspraak of bereken direct online een indicatie van uw waarde.</p>
		</div>
		<div class="xg-final-cta-box">
			<h3>Start vandaag nog</h3>
			<div class="xg-final-list">
				<div class="xg-final-item">✓ Gratis &amp; vrijblijvende taxatie</div>
				<div class="xg-final-item">✓ Directe uitbetaling</div>
				<div class="xg-final-item">✓ 100% verzekerd &amp; discreet</div>
			</div>
			<a class="xg-final-btn" href="#">Maak een afspraak</a>
		</div>
	</div>
</div></section>

</div>
`;

module.exports = { diamantenVerkopen };
