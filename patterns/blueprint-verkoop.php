<?php
/**
 * XGOUD Verkoop-Blaupause (generisch, alle 4 Taxonomie-Ebenen).
 *
 * Nutzt die ECHTEN Klassen aus assets/css/blueprint.css. Alle redaktionellen
 * Texte sind leere Gutenberg-Blöcke (wp:heading / wp:paragraph / wp:table /
 * wp:button) und im Editor befüllbar. Struktur-Wrapper liegen in wp:html.
 *
 * @package Ekinese
 *
 * Title: XGOUD Verkoop Blueprint
 * Slug: ekinese/blueprint-category
 * Categories: ekinese
 * Description: Leere, editierbare Verkoop-Landingpage (echte blueprint-Klassen).
 */
?>
<!-- wp:group {"tagName":"div","className":"xg-blueprint","layout":{"type":"default"}} -->
<div class="wp-block-group xg-blueprint">

<!-- wp:html -->
<section class="xg-hero-v2"><div class="xg-container">
<!-- /wp:html -->
<!-- wp:heading {"level":4,"className":"hero-kicker"} --><h4 class="hero-kicker"></h4><!-- /wp:heading -->
<!-- wp:heading {"level":1} --><h1></h1><!-- /wp:heading -->
<!-- wp:paragraph {"className":"hero-lead"} --><p class="hero-lead"></p><!-- /wp:paragraph -->
<!-- wp:html --><div class="xg-hero-buttons"><!-- /wp:html -->
<!-- wp:button {"className":"xg-btn-gold"} --><div class="wp-block-button"><a class="wp-block-button__link xg-btn-gold"></a></div><!-- /wp:button -->
<!-- wp:button {"className":"xg-btn-outline"} --><div class="wp-block-button"><a class="wp-block-button__link xg-btn-outline"></a></div><!-- /wp:button -->
<!-- wp:html --></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section><div class="xg-container"><div class="xg-why">
<!-- /wp:html -->
<!-- wp:paragraph {"className":"xg-eyebrow"} --><p class="xg-eyebrow"></p><!-- /wp:paragraph -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:paragraph {"className":"xg-intro"} --><p class="xg-intro"></p><!-- /wp:paragraph -->
<!-- wp:html --><div class="xg-benefits"><!-- /wp:html -->
<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
<!-- wp:html --><div class="xg-benefit"><span class="xg-b-num"><?php echo (int) $i; ?></span><!-- /wp:html -->
<!-- wp:heading {"level":3,"className":"xg-b-title"} --><h3 class="xg-b-title"></h3><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><!-- /wp:html -->
<?php endfor; ?>
<!-- wp:html --></div></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section class="xg-row-3"><div class="xg-container">
<!-- /wp:html -->
<!-- wp:heading {"level":2,"className":"xg-section-title"} --><h2 class="xg-section-title"></h2><!-- /wp:heading -->
<!-- wp:html --><div class="xg-grid-4"><!-- /wp:html -->
<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
<!-- wp:html --><div class="xg-c-card"><!-- /wp:html -->
<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><!-- /wp:html -->
<?php endfor; ?>
<!-- wp:html --></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section><div class="xg-container"><div class="xgoud-cta-modern"><div class="xgoud-cta-image" style="background:#ead9bd"></div><div class="xgoud-cta-content">
<!-- /wp:html -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:paragraph {"className":"xgoud-subtitle"} --><p class="xgoud-subtitle"></p><!-- /wp:paragraph -->
<!-- wp:button {"className":"cta-button"} --><div class="wp-block-button"><a class="wp-block-button__link cta-button"></a></div><!-- /wp:button -->
<!-- wp:html --></div></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section><div class="xg-container"><div class="xg-grid-2" style="align-items:start"><div class="xg-expert-box">
<!-- /wp:html -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><div class="xg-trust-grid"><!-- /wp:html -->
<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
<!-- wp:html --><div class="xg-trust-card"><div class="xg-trust-icon">◆</div><!-- /wp:html -->
<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><!-- /wp:html -->
<?php endfor; ?>
<!-- wp:html --></div></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section><div class="xg-container"><div class="xg-steps-content">
<!-- /wp:html -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><div class="xg-grid-4"><!-- /wp:html -->
<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
<!-- wp:html --><div class="xg-step-card"><div class="xg-step-number"><?php echo (int) $i; ?></div><!-- /wp:html -->
<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><!-- /wp:html -->
<?php endfor; ?>
<!-- wp:html --></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section class="xg-price-section"><div class="xg-container">
<!-- /wp:html -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --><div class="xg-table-wrapper"><!-- /wp:html -->
<!-- wp:table {"className":"xg-price-table"} -->
<figure class="wp-block-table xg-price-table"><table><thead><tr><th></th><th></th><th></th></tr></thead><tbody><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr></tbody></table></figure>
<!-- /wp:table -->
<!-- wp:html --></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section><div class="xg-container"><div class="xg-faq">
<!-- /wp:html -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:html --><div class="xg-faq-list"><!-- /wp:html -->
<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
<!-- wp:html --><div class="xg-faq-item"><div class="xg-faq-question"><!-- /wp:html -->
<!-- wp:heading {"level":3,"className":"xg-faq-q"} --><h3 class="xg-faq-q"></h3><!-- /wp:heading -->
<!-- wp:html --></div><div class="xg-faq-answer"><!-- /wp:html -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div></div><!-- /wp:html -->
<?php endfor; ?>
<!-- wp:html --></div></div></div></section><!-- /wp:html -->

<!-- wp:html -->
<section><div class="xg-container"><div class="xg-grid-2"><div class="xg-final-cta-content">
<!-- /wp:html -->
<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
<!-- wp:html --></div><div class="xg-final-cta-box"><!-- /wp:html -->
<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->
<!-- wp:button {"className":"xg-final-btn"} --><div class="wp-block-button"><a class="wp-block-button__link xg-final-btn"></a></div><!-- /wp:button -->
<!-- wp:html --></div></div></div></section><!-- /wp:html -->

</div>
<!-- /wp:group -->
