<?php
/**
 * XGOUD Produktseite (4. Ebene) – konkretes Produkt mit gesperrtem Calculator.
 * Echte blueprint.css-Klassen + Sektions-Library. Calculator via data-preset/
 * data-lock pro Produkt konfigurierbar (hier generisches Edelmetaal-Beispiel).
 *
 * @package Ekinese
 *
 * Title: XGOUD Produktseite
 * Slug: ekinese/product-page
 * Categories: ekinese
 */
?>
<!-- wp:group {"className":"xg-blueprint"} -->
<div class="wp-block-group xg-blueprint">

<!-- wp:html -->
<section class="xg-hero-v2"><div class="xg-container"><div class="xg-grid-2" style="align-items:center;gap:50px"><div>
<!-- /wp:html -->
<!-- wp:heading {"level":4,"className":"hero-kicker"} --><h4 class="hero-kicker"></h4><!-- /wp:heading -->
<!-- wp:heading {"level":1} --><h1></h1><!-- /wp:heading -->
<!-- wp:paragraph {"className":"hero-lead"} --><p class="hero-lead"></p><!-- /wp:paragraph -->
<!-- wp:list {"className":"xg-diamond-list"} --><ul class="xg-diamond-list"><li></li><li></li><li></li></ul><!-- /wp:list -->
<!-- wp:html -->
</div>
<div class="xg-calc" data-mode="compact" data-lock="1" data-preset='{"type":"metal","form":{"metal":"goud","form":"munt","purity":"22"}}'></div>
</div></div></section>
<!-- /wp:html -->

<?php
xg_sec_market();
xg_sec_price();
xg_sec_steps();
xg_sec_faq();
xg_sec_final();
?>
</div>
<!-- /wp:group -->
