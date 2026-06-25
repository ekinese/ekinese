<?php
/**
 * XGOUD – Startseite (Home). Conversion-gericht, SEO/H1-H4 correct.
 * Dynamische blokken staan als zelfstandige siblings in een .xg-blueprint-group
 * (anders renderen ze niet en missen ze de container-styling).
 *
 * @package Ekinese
 *
 * Title: XGOUD Home
 * Slug: ekinese/page-home
 * Categories: ekinese
 */
?>
<!-- wp:html -->
<div class="xg-blueprint">

<section class="xg-hero-split">
	<div class="xg-hero-split-text">
		<div class="hero-kicker">GOUD · ZILVER · DIAMANTEN · HORLOGES</div>
		<h1>Verkoop uw edelmetaal tegen de beste dagprijs</h1>
		<p>Bij XGOUD krijgt u een eerlijke, transparante prijs — direct uitbetaald en met een vast deel voor het goede doel. Bereken hiernaast in enkele seconden uw waarde.</p>
		<div class="xg-hero-split-cta">
			<a class="xg-final-btn" href="/afspraak/">Maak een afspraak</a>
			<a class="xg-hero-split-link" href="/dagprijzen/">Bekijk de dagprijzen →</a>
		</div>
		<div class="xg-hero-trust">
			<span>★★★★★ 4,8/5 op Trustpilot</span><span>40+ vestigingen</span><span>100% verzekerd</span>
		</div>
	</div>
	<div class="xg-hero-split-calc">
		<div class="xg-calc" data-mode="compact" data-only="metal"></div>
	</div>
</section>

<div class="xg-charity-ticker">
	<span class="xg-charity-ticker-label">XGOUD heeft deze maand aan goede doelen gegeven:</span>
	<span class="xg-charity-ticker-amount" data-xg-charity-total>€ 65.168,36</span>
	<div class="xg-charity-ticker-projects"><span>Scholen</span><span>Kinderdagverblijven</span><span>Vrouwenopvang</span><span>Sportcentra</span></div>
</div>

</div>
<!-- /wp:html -->

<!-- Live inkoopprijzen met metaal-tabs -->
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint"><!-- wp:ekinese/price-tabs /--></div>
<!-- /wp:group -->

<!-- Waarom XGOUD + Zo werkt het -->
<!-- wp:html -->
<div class="xg-blueprint">

<section><div class="xg-container"><div class="xg-why">
	<div class="xg-eyebrow">WAAROM XGOUD</div>
	<h2>De vertrouwde inkoper sinds 2009</h2>
	<p class="xg-intro">Eerlijke dagprijzen, deskundige taxatie en een persoonlijke aanpak — bij u thuis, op kantoor of via onze ophaalservice.</p>
	<div class="xg-benefits">
		<div class="xg-benefit"><span class="xg-b-num">01</span><span class="xg-b-title">Beste dagprijs</span><p>Vaste inkoopprijs voor edelmetaal op basis van de actuele spotkoers.</p></div>
		<div class="xg-benefit"><span class="xg-b-num">02</span><span class="xg-b-title">Direct uitbetaald</span><p>Per bank of contant, meteen na akkoord.</p></div>
		<div class="xg-benefit"><span class="xg-b-num">03</span><span class="xg-b-title">40+ vestigingen</span><p>Altijd een locatie bij u in de buurt, met Eindhoven als hoofdkantoor.</p></div>
		<div class="xg-benefit"><span class="xg-b-num">04</span><span class="xg-b-title">Goed doel</span><p>Een vast deel van elke marge gaat naar een project in uw stad.</p></div>
	</div>
</div></div></section>

<section><div class="xg-container"><div class="xg-steps-content">
	<h2>Zo werkt het</h2>
	<p>In vier eenvoudige stappen van edelmetaal naar geld.</p>
</div><div class="xg-grid-4">
	<div class="xg-step-card"><div class="xg-step-number">1</div><h3>Bereken</h3><p>Bepaal online een indicatie van uw waarde.</p></div>
	<div class="xg-step-card"><div class="xg-step-number">2</div><h3>Afspraak</h3><p>Kies thuisbezoek, een kantoor of de ophaalservice.</p></div>
	<div class="xg-step-card"><div class="xg-step-number">3</div><h3>Taxatie</h3><p>Onze expert taxeert en doet een transparant bod.</p></div>
	<div class="xg-step-card"><div class="xg-step-number">4</div><h3>Uitbetaling</h3><p>Akkoord? Direct uitbetaald.</p></div>
</div></div></section>

<section><div class="xg-container">
	<h2 class="xg-section-title">Wat kunt u verkopen?</h2>
	<div class="xg-grid-4">
		<a class="xg-c-card" href="/verkopen/edelmetalen/goud/" style="text-decoration:none"><h3>Goud</h3><p>Baren, munten en sloopgoud.</p></a>
		<a class="xg-c-card" href="/verkopen/edelmetalen/zilver/" style="text-decoration:none"><h3>Zilver</h3><p>Baren en munten.</p></a>
		<a class="xg-c-card" href="/verkopen/edelstenen/" style="text-decoration:none"><h3>Diamanten</h3><p>Volgens Rapaport getaxeerd.</p></a>
		<a class="xg-c-card" href="/verkopen/horloges/" style="text-decoration:none"><h3>Horloges</h3><p>Luxe merken van alle soorten.</p></a>
	</div>
</div></section>

</div>
<!-- /wp:html -->

<!-- Veilingen — producten uit de veiling -->
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint"><!-- wp:ekinese/auctions {"limit":3} /-->
<!-- wp:html --><div class="xg-container xg-home-more"><a class="xg-home-more-link" href="/veilingen/">Bekijk alle veilingen →</a></div><!-- /wp:html --></div>
<!-- /wp:group -->

<!-- Reviews / klantbeoordelingen -->
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint"><!-- wp:ekinese/trust-wall /--></div>
<!-- /wp:group -->

<!-- Nieuws-cards -->
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint"><!-- wp:html -->
<section class="xg-home-head"><div class="xg-container"><div class="xg-eyebrow">NIEUWS &amp; MARKT</div><h2 class="xg-section-title">Laatste nieuws &amp; marktinzichten</h2></div></section>
<!-- /wp:html -->
<!-- wp:ekinese/news {"limit":6} /--></div>
<!-- /wp:group -->

<!-- Kaart + kantoor-cards -->
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint"><!-- wp:ekinese/offices-overview {"embed":true} /--></div>
<!-- /wp:group -->

<!-- FAQ -->
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint"><!-- wp:ekinese/faq /--></div>
<!-- /wp:group -->

<!-- Interne links (GEO/SEO) + slot-CTA -->
<!-- wp:html -->
<div class="xg-blueprint">

<section class="xg-linkhub"><div class="xg-container">
	<h2 class="xg-section-title">Goud verkopen in heel Nederland</h2>
	<div class="xg-linkhub-grid">
		<div class="xg-linkhub-col">
			<h3>Populaire steden</h3>
			<a href="/kantoren/amsterdam/">Goud verkopen Amsterdam</a>
			<a href="/kantoren/rotterdam/">Goud verkopen Rotterdam</a>
			<a href="/kantoren/den-haag/">Goud verkopen Den Haag</a>
			<a href="/kantoren/utrecht/">Goud verkopen Utrecht</a>
			<a href="/kantoren/eindhoven/">Goud verkopen Eindhoven</a>
			<a href="/kantoren/">Alle vestigingen →</a>
		</div>
		<div class="xg-linkhub-col">
			<h3>Wat u kunt verkopen</h3>
			<a href="/verkopen/edelmetalen/goud/">Goud verkopen</a>
			<a href="/verkopen/edelmetalen/zilver/">Zilver verkopen</a>
			<a href="/verkopen/edelmetalen/platina/">Platina verkopen</a>
			<a href="/verkopen/edelstenen/">Diamanten &amp; edelstenen</a>
			<a href="/verkopen/horloges/">Luxe horloges</a>
		</div>
		<div class="xg-linkhub-col">
			<h3>Dagprijzen &amp; kennis</h3>
			<a href="/dagprijzen/goudprijs/">Goudprijs vandaag</a>
			<a href="/dagprijzen/zilverprijs/">Zilverprijs vandaag</a>
			<a href="/dagprijzen/ratio/goud-zilver/">Goud/zilver-ratio</a>
			<a href="/kennisbank/">Kennisbank</a>
			<a href="/service/faq/">Veelgestelde vragen</a>
		</div>
		<div class="xg-linkhub-col">
			<h3>Service</h3>
			<a href="/service/gratis-taxatie/">Gratis taxatie</a>
			<a href="/service/thuisbezoek/">Thuisbezoek</a>
			<a href="/service/zakelijk-verkopen/">Zakelijk verkopen</a>
			<a href="/veilingen/">Veilingen</a>
			<a href="/contact/">Contact</a>
		</div>
	</div>
</div></section>

<section><div class="xg-container">
	<div class="xg-grid-2"><div class="xg-final-cta-content">
		<h2>Klaar om te verkopen?</h2>
		<p>Bereken uw waarde of maak direct een afspraak met een van onze experts. Eerlijk, snel en met een vast deel voor het goede doel.</p>
	</div><div class="xg-final-cta-box">
		<h3>Start vandaag nog</h3>
		<div class="xg-final-list"><div class="xg-final-item">✓ Gratis taxatie</div><div class="xg-final-item">✓ Directe uitbetaling</div><div class="xg-final-item">✓ 100% verzekerd</div></div>
		<a class="xg-final-btn" href="/afspraak/">Maak een afspraak</a>
	</div></div>
</div></section>

</div>
<!-- /wp:html -->
