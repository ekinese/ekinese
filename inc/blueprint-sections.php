<?php
/**
 * Wiederverwendbare Blueprint-Sektionen (echte blueprint.css-Klassen, leere
 * editierbare Gutenberg-Blöcke). Wird von den Pattern-Dateien genutzt, damit
 * alle Verkoop-Seiten konsistent und wartbar bleiben.
 *
 * Jede Funktion gibt das Markup per echo aus.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hero (kicker, h1, lead, 2 buttons). */
function xg_sec_hero() {
	echo '<!-- wp:html --><section class="xg-hero-v2"><div class="xg-container"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":4,"className":"hero-kicker"} --><h4 class="hero-kicker"></h4><!-- /wp:heading -->';
	echo '<!-- wp:heading {"level":1} --><h1></h1><!-- /wp:heading -->';
	echo '<!-- wp:paragraph {"className":"hero-lead"} --><p class="hero-lead"></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --><div class="xg-hero-buttons"><!-- /wp:html -->';
	echo '<!-- wp:button {"className":"xg-btn-gold"} --><div class="wp-block-button"><a class="wp-block-button__link xg-btn-gold"></a></div><!-- /wp:button -->';
	echo '<!-- wp:button {"className":"xg-btn-outline"} --><div class="wp-block-button"><a class="wp-block-button__link xg-btn-outline"></a></div><!-- /wp:button -->';
	echo '<!-- wp:html --></div></div></section><!-- /wp:html -->';
}

/** Waarom + 4 benefits. */
function xg_sec_why() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-why"><!-- /wp:html -->';
	echo '<!-- wp:paragraph {"className":"xg-eyebrow"} --><p class="xg-eyebrow"></p><!-- /wp:paragraph -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph {"className":"xg-intro"} --><p class="xg-intro"></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --><div class="xg-benefits"><!-- /wp:html -->';
	for ( $i = 1; $i <= 4; $i++ ) {
		echo '<!-- wp:html --><div class="xg-benefit"><span class="xg-b-num">' . (int) $i . '</span><!-- /wp:html -->';
		echo '<!-- wp:heading {"level":3,"className":"xg-b-title"} --><h3 class="xg-b-title"></h3><!-- /wp:heading -->';
		echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		echo '<!-- wp:html --></div><!-- /wp:html -->';
	}
	echo '<!-- wp:html --></div></div></div></section><!-- /wp:html -->';
}

/** Titel + 4 große Karten (z.B. 4 C's). */
function xg_sec_cards() {
	echo '<!-- wp:html --><section class="xg-row-3"><div class="xg-container"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2,"className":"xg-section-title"} --><h2 class="xg-section-title"></h2><!-- /wp:heading -->';
	echo '<!-- wp:html --><div class="xg-grid-4"><!-- /wp:html -->';
	for ( $i = 1; $i <= 4; $i++ ) {
		echo '<!-- wp:html --><div class="xg-c-card"><!-- /wp:html -->';
		echo '<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->';
		echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		echo '<!-- wp:html --></div><!-- /wp:html -->';
	}
	echo '<!-- wp:html --></div></div></section><!-- /wp:html -->';
}

/** CTA-Modern. */
function xg_sec_cta() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xgoud-cta-modern"><div class="xgoud-cta-image" style="background:#ead9bd"></div><div class="xgoud-cta-content"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph {"className":"xgoud-subtitle"} --><p class="xgoud-subtitle"></p><!-- /wp:paragraph -->';
	echo '<!-- wp:button {"className":"cta-button"} --><div class="wp-block-button"><a class="wp-block-button__link cta-button"></a></div><!-- /wp:button -->';
	echo '<!-- wp:html --></div></div></div></section><!-- /wp:html -->';
}

/** Expert-box + 4 trust-cards. */
function xg_sec_expert_trust() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-grid-2" style="align-items:start"><div class="xg-expert-box"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --></div><div class="xg-trust-grid"><!-- /wp:html -->';
	for ( $i = 1; $i <= 4; $i++ ) {
		echo '<!-- wp:html --><div class="xg-trust-card"><div class="xg-trust-icon">◆</div><!-- /wp:html -->';
		echo '<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->';
		echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		echo '<!-- wp:html --></div><!-- /wp:html -->';
	}
	echo '<!-- wp:html --></div></div></div></section><!-- /wp:html -->';
}

/** Stappen (4). */
function xg_sec_steps() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-steps-content"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --></div><div class="xg-grid-4"><!-- /wp:html -->';
	for ( $i = 1; $i <= 4; $i++ ) {
		echo '<!-- wp:html --><div class="xg-step-card"><div class="xg-step-number">' . (int) $i . '</div><!-- /wp:html -->';
		echo '<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->';
		echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		echo '<!-- wp:html --></div><!-- /wp:html -->';
	}
	echo '<!-- wp:html --></div></div></section><!-- /wp:html -->';
}

/** Markt + Formula-box. */
function xg_sec_market() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-market-content"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --><div class="xg-formula-box"><div class="xg-formula-title"></div><!-- /wp:html -->';
	echo '<!-- wp:paragraph {"className":"xg-formula"} --><p class="xg-formula"></p><!-- /wp:paragraph -->';
	echo '<!-- wp:paragraph {"className":"xg-formula-note"} --><p class="xg-formula-note"></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --></div></div></div></section><!-- /wp:html -->';
}

/** Prijstabel (leer). */
function xg_sec_price() {
	echo '<!-- wp:html --><section class="xg-price-section"><div class="xg-container"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --><div class="xg-table-wrapper"><!-- /wp:html -->';
	echo '<!-- wp:table {"className":"xg-price-table"} --><figure class="wp-block-table xg-price-table"><table><thead><tr><th></th><th></th><th></th></tr></thead><tbody><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr></tbody></table></figure><!-- /wp:table -->';
	echo '<!-- wp:html --></div></div></section><!-- /wp:html -->';
}

/** Rapaport-box. */
function xg_sec_rapaport() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-grid-2"><div class="xg-rapaport-left-column"><div class="xg-rapaport-left"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --></div></div><div class="xg-rapaport-right-column"><div class="xg-rapaport-box"><!-- /wp:html -->';
	for ( $i = 1; $i <= 4; $i++ ) {
		echo '<!-- wp:paragraph {"className":"xg-rapaport-item"} --><p class="xg-rapaport-item"></p><!-- /wp:paragraph -->';
	}
	echo '<!-- wp:html --></div></div></div></div></section><!-- /wp:html -->';
}

/** Certificering (tekst + lijst + beeld). */
function xg_sec_cert() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-cert-section"><div class="xg-cert-content"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:list {"className":"xg-cert-list"} --><ul class="xg-cert-list"><li></li><li></li><li></li></ul><!-- /wp:list -->';
	echo '<!-- wp:html --></div><div class="xg-cert-image" style="background:#ead9bd;min-height:340px"></div></div></div></section><!-- /wp:html -->';
}

/** E-E-A-T / auteurs (2). */
function xg_sec_eeat() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-eeat-content"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --></div><div class="xg-author-row"><!-- /wp:html -->';
	for ( $i = 1; $i <= 2; $i++ ) {
		echo '<!-- wp:html --><div class="xg-author-card"><!-- /wp:html -->';
		echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
		echo '<!-- wp:paragraph {"className":"xg-author-role"} --><p class="xg-author-role"></p><!-- /wp:paragraph -->';
		echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		echo '<!-- wp:html --></div><!-- /wp:html -->';
	}
	echo '<!-- wp:html --></div></div></section><!-- /wp:html -->';
}

/** FAQ (4). */
function xg_sec_faq() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-faq"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:html --><div class="xg-faq-list"><!-- /wp:html -->';
	for ( $i = 1; $i <= 4; $i++ ) {
		echo '<!-- wp:html --><div class="xg-faq-item"><div class="xg-faq-question"><!-- /wp:html -->';
		echo '<!-- wp:heading {"level":3,"className":"xg-faq-q"} --><h3 class="xg-faq-q"></h3><!-- /wp:heading -->';
		echo '<!-- wp:html --></div><div class="xg-faq-answer"><!-- /wp:html -->';
		echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		echo '<!-- wp:html --></div></div><!-- /wp:html -->';
	}
	echo '<!-- wp:html --></div></div></div></section><!-- /wp:html -->';
}

/** Final CTA. */
function xg_sec_final() {
	echo '<!-- wp:html --><section><div class="xg-container"><div class="xg-grid-2"><div class="xg-final-cta-content"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';
	echo '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
	echo '<!-- wp:html --></div><div class="xg-final-cta-box"><!-- /wp:html -->';
	echo '<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->';
	echo '<!-- wp:button {"className":"xg-final-btn"} --><div class="wp-block-button"><a class="wp-block-button__link xg-final-btn"></a></div><!-- /wp:button -->';
	echo '<!-- wp:html --></div></div></div></section><!-- /wp:html -->';
}
