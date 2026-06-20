/**
 * Fertiger NL-Beispielinhalt "Diamanten verkopen" (befüllte Blaupause).
 * Wird von build-test.js (als Seite in der Test-Suite) und build-demo.js
 * (als eigenständige Datei) genutzt.
 */
const ICON = '<svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><circle cx="24" cy="24" r="20"/><path d="M16 24l6 6 12-12"/></svg>';

const diamantenVerkopen = `
<div class="xg-blueprint">
<div class="xg-charity-ticker">
	<span class="xg-charity-ticker-label">XGOUD heeft deze maand aan goede doelen gegeven:</span>
	<span class="xg-charity-ticker-amount">€65.168,36</span>
	<div class="xg-charity-ticker-projects">
		<span>Maatschappelijk werk</span><span>Kinderdagverblijven</span><span>Vrouwenopvang</span><span>Sportcentra</span><span>Scholen</span>
	</div>
</div>

<div class="xg-hero-v2">
	<div class="xg-hero-background">
		<svg class="xg-hero-blob" viewBox="0 0 1440 600" preserveAspectRatio="none"><path d="M0,300 Q360,100 720,250 T1440,300 L1440,600 L0,600 Z" fill="rgba(208,172,75,0.10)"/></svg>
	</div>
	<div class="xg-hero-container">
		<div class="xg-hero-content">
			<h4 class="xg-hero-kicker">DIAMANTEN VERKOPEN</h4>
			<h1 class="xg-hero-title">Verkoop uw diamanten tegen de hoogste dagprijs</h1>
			<p class="xg-hero-lead">Bij XGOUD krijgt u een eerlijke, transparante taxatie op basis van de actuele Rapaport-prijslijst. Gratis, vrijblijvend en met directe uitbetaling.</p>
		</div>
		<div class="xg-hero-buttons">
			<div class="wp-block-button"><a class="wp-block-button__link xg-btn-gold" href="#">Bereken uw waarde</a></div>
			<div class="wp-block-button"><a class="wp-block-button__link xg-btn-outline" href="#">Maak een afspraak</a></div>
		</div>
	</div>
</div>

<div class="xgoud-toc-wrapper">
	<div class="xgoud-toc">
		<h3 class="xgoud-toc-title">Inhoudsopgave</h3>
		<div class="xgoud-toc-grid">
			<ul class="xgoud-toc-list">
				<li><a href="#">Waarom XGOUD</a></li>
				<li><a href="#">De 4 C's</a></li>
				<li><a href="#">Zo werkt het</a></li>
				<li><a href="#">Inkoopprijzen</a></li>
				<li><a href="#">Rapaport</a></li>
				<li><a href="#">Veelgestelde vragen</a></li>
			</ul>
		</div>
	</div>
</div>

<div class="xg-why-section">
	<div class="xg-why-container">
		<h2 class="xg-section-title">Waarom uw diamant verkopen bij XGOUD?</h2>
		<div class="xg-why-grid">
			${[
				['Rapaport-prijzen','Wij taxeren volgens de internationale Rapaport-prijslijst — de wereldwijde standaard voor diamantprijzen. Zo weet u zeker dat u een eerlijke prijs ontvangt.'],
				['Gratis taxatie','Onze gecertificeerde experts taxeren uw diamant gratis en geheel vrijblijvend, ter plaatse of bij u thuis.'],
				['Directe uitbetaling','Akkoord met ons bod? U ontvangt uw geld direct per bankoverschrijving of contant.'],
				['Discreet & verzekerd','Elke transactie verloopt discreet, veilig en volledig verzekerd. Uw vertrouwen staat voorop.']
			].map(c => `
			<div class="xg-c-card">
				<div class="xg-c-card-icon">${ICON}</div>
				<h3 class="xg-c-card-title">${c[0]}</h3>
				<p class="xg-c-card-text">${c[1]}</p>
			</div>`).join('')}
		</div>
	</div>
</div>

<div class="xgoud-cta-modern">
	<div class="xgoud-cta-modern-content">
		<h2>Benieuwd naar de waarde van uw diamant?</h2>
		<p>Gebruik onze rekentool of maak direct een afspraak met een van onze diamantexperts.</p>
		<div class="wp-block-button"><a class="wp-block-button__link xg-btn-gold" href="#">Bereken nu uw waarde</a></div>
	</div>
</div>

<div class="xg-expert-box">
	<div class="xg-expert-box-container">
		<h3 class="xg-expert-box-title">Onze diamantexpert</h3>
		<p class="xg-expert-box-text">Met meer dan 20 jaar ervaring in de diamanthandel taxeert onze GIA-gecertificeerde gemmoloog elke steen nauwkeurig op kleur, zuiverheid, slijpvorm en karaatgewicht. U krijgt altijd een eerlijke, onderbouwde waardebepaling.</p>
	</div>
</div>

<div class="xg-market-content">
	<div class="xg-market-content-container">
		<h2 class="xg-section-title">De 4 C's — zo bepalen wij de waarde</h2>
		<p>De waarde van een diamant wordt internationaal bepaald aan de hand van vier criteria: Carat (gewicht), Color (kleur), Clarity (zuiverheid) en Cut (slijpvorm). Samen vormen zij de basis voor elke taxatie.</p>
		<div class="xg-formula-box">
			<div class="xg-formula-content">
				<h3>Carat · Color · Clarity · Cut</h3>
				<p>Een diamant van 1,00 ct met kleur D en zuiverheid IF (intern flawless) en een excellent geslepen vorm behaalt de hoogste waarde. Elke afwijking in een van de C's beïnvloedt de uiteindelijke prijs.</p>
			</div>
		</div>
	</div>
</div>

<div class="xg-steps-section">
	<div class="xg-steps-container">
		<h2 class="xg-section-title">Zo werkt het — in 4 stappen</h2>
		<div class="xg-steps-grid">
			${[
				['Aanmelden','Bereken online een indicatie of maak direct een afspraak.'],
				['Taxatie',"Onze expert beoordeelt uw diamant op de 4 C's."],
				['Bod & akkoord','U ontvangt een transparant bod op basis van Rapaport.'],
				['Uitbetaling','Bij akkoord betalen wij direct uit — per bank of contant.']
			].map((s,i) => `
			<div class="xg-step-card">
				<div class="xg-step-number">${i+1}</div>
				<h3 class="xg-step-title">${s[0]}</h3>
				<p class="xg-step-text">${s[1]}</p>
			</div>`).join('')}
		</div>
	</div>
</div>

<div class="xg-price-table-section">
	<div class="xg-price-table-container">
		<h2 class="xg-section-title">Indicatieve inkoopprijzen</h2>
		<figure class="wp-block-table"><table class="xg-price-table">
			<thead><tr><th>Karaatgewicht</th><th>Kwaliteit</th><th>Indicatie (van–tot)</th></tr></thead>
			<tbody>
				<tr><td>0,50 ct</td><td>G / VS1 / Excellent</td><td>€ 900 – € 1.300</td></tr>
				<tr><td>1,00 ct</td><td>F / VVS2 / Excellent</td><td>€ 4.500 – € 6.200</td></tr>
				<tr><td>1,50 ct</td><td>E / VS2 / Very Good</td><td>€ 8.000 – € 10.500</td></tr>
				<tr><td>2,00 ct</td><td>D / IF / Excellent</td><td>€ 22.000 – € 28.000</td></tr>
			</tbody>
		</table></figure>
		<p style="font-size:13px;color:#888;margin-top:10px">Indicatieve bedragen. De definitieve prijs volgt na taxatie en is afhankelijk van het GIA-certificaat.</p>
	</div>
</div>

<div class="xg-rapaport-section">
	<div class="xg-rapaport-container">
		<h2 class="xg-section-title">Wat is de Rapaport-prijslijst?</h2>
		<p>De Rapaport Diamond Report is sinds 1978 de internationale referentie voor diamantprijzen. De lijst wordt wekelijks bijgewerkt en wordt wereldwijd door de handel gebruikt. Door volgens Rapaport te taxeren, garanderen wij u een marktconforme en eerlijke prijs — volledig transparant.</p>
	</div>
</div>

<div class="xg-certification-section">
	<div class="xg-certification-container">
		<h2 class="xg-section-title">Certificering: GIA, IGI & HRD</h2>
		<p>Een diamant met certificaat van GIA, IGI of HRD laat zich nauwkeuriger taxeren en levert doorgaans meer op. Heeft u geen certificaat? Geen probleem — onze gemmoloog beoordeelt uw steen ter plaatse volgens dezelfde internationale criteria.</p>
	</div>
</div>

<div class="xg-eeat-section">
	<div class="xg-eeat-container">
		<h2 class="xg-section-title">Expertise waar u op kunt vertrouwen</h2>
		<div class="xg-eeat-grid">
			<div class="xg-author-card">
				<h3>GIA-gecertificeerd gemmoloog</h3>
				<p>Onze taxateurs zijn opgeleid en gecertificeerd volgens de strengste internationale normen van het Gemological Institute of America.</p>
			</div>
			<div class="xg-author-card">
				<h3>20+ jaar in de diamanthandel</h3>
				<p>Met decennia ervaring in inkoop en taxatie kent ons team de markt door en door — uw diamant is bij ons in vertrouwde handen.</p>
			</div>
		</div>
	</div>
</div>

<div class="xg-faq-section">
	<div class="xg-faq-container">
		<h2 class="xg-section-title">Veelgestelde vragen</h2>
		<div class="xg-faq-list">
			${[
				['Heb ik een certificaat nodig om mijn diamant te verkopen?','Nee. Een GIA-, IGI- of HRD-certificaat helpt bij een nauwkeurige taxatie, maar onze gemmoloog kan uw diamant ook zonder certificaat volledig beoordelen.'],
				['Hoe wordt de prijs van mijn diamant bepaald?',"Wij taxeren op basis van de 4 C's en de actuele Rapaport-prijslijst, de internationale standaard voor diamantprijzen."],
				['Is de taxatie echt gratis?','Ja, de taxatie is volledig gratis en vrijblijvend. U beslist daarna zelf of u verkoopt.'],
				['Wanneer ontvang ik mijn geld?','Bij akkoord betalen wij direct uit — per bankoverschrijving of contant ter plaatse.']
			].map(f => `
			<div class="xg-faq-item">
				<div class="xg-faq-question"><h3 class="xg-faq-q-text">${f[0]}</h3></div>
				<div class="xg-faq-answer"><p class="xg-faq-a-text">${f[1]}</p></div>
			</div>`).join('')}
		</div>
	</div>
</div>

<div class="xg-final-cta-box">
	<div class="xg-final-cta-content">
		<h2>Klaar om uw diamant te verkopen?</h2>
		<p>Maak vandaag nog een afspraak of bereken direct online een indicatie van uw waarde.</p>
		<div class="wp-block-button"><a class="wp-block-button__link xg-btn-gold" href="#">Start uw verkoop</a></div>
	</div>
</div>
</div>
`;

module.exports = { diamantenVerkopen };
