<?php
/**
 * XGOUD FAQ-blok (#18) – herbruikbaar accordeon + FAQPage-schema.
 *
 * Eén blok ekinese/faq dat zowel een toegankelijk UI-accordeon als geldige
 * FAQPage-JSON-LD rendert (via ekinese_faq_schema). Vragen komen uit een
 * benoemde set (attribuut "set") of uit losse q/a-attributen. Plaatsbaar op
 * kern-pagina's voor "People also ask"-zichtbaarheid. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'ekinese/faq', array(
		'render_callback' => 'ekinese_render_faq',
		'attributes'      => array(
			'set'   => array( 'type' => 'string', 'default' => 'algemeen' ),
			'title' => array( 'type' => 'string', 'default' => 'Veelgestelde vragen' ),
		),
	) );
} );

/** Benoemde FAQ-sets (uitbreidbaar via filter). */
function ekinese_faq_sets() {
	$sets = array(
		'algemeen' => array(
			array( 'q' => 'Hoe verkoop ik mijn goud bij XGOUD?', 'a' => 'U maakt online een afspraak, kiest voor een kantoorbezoek, thuisbezoek of ophaalservice, en onze expert taxeert gratis. Bij akkoord betalen we direct uit.' ),
			array( 'q' => 'Hoe wordt de prijs bepaald?', 'a' => 'De prijs is gebaseerd op de actuele spotprijs (dagprijs) en het gehalte. U ziet de marge altijd vooraf en kunt online een indicatie berekenen.' ),
			array( 'q' => 'Is de taxatie echt gratis?', 'a' => 'Ja, de taxatie is altijd gratis en vrijblijvend. U bent nergens toe verplicht.' ),
			array( 'q' => 'Word ik direct uitbetaald?', 'a' => 'Ja. Na akkoord op het bod betalen wij u direct uit, contant of per overboeking.' ),
			array( 'q' => 'Wat moet ik meenemen?', 'a' => 'Een geldig identiteitsbewijs (wettelijk verplicht, Wwft/KYC) en de objecten die u wilt verkopen. U moet minimaal 18 jaar zijn.' ),
		),
		'verkopen' => array(
			array( 'q' => 'Welke edelmetalen koopt XGOUD in?', 'a' => 'Goud, zilver, platina en palladium – in de vorm van munten, baren, sieraden en sloopgoud.' ),
			array( 'q' => 'Koopt XGOUD ook diamanten en horloges?', 'a' => 'Ja, wij taxeren en kopen diamanten, edelstenen en luxe horloges (zoals Rolex) in.' ),
			array( 'q' => 'Kan ik ook online verkopen?', 'a' => 'U start online met een berekening en afspraak. De definitieve taxatie gebeurt altijd door een expert, op kantoor, thuis of via de verzekerde ophaalservice.' ),
		),
	);
	return apply_filters( 'ekinese_faq_sets', $sets );
}

/** Render het FAQ-accordeon + schema. */
function ekinese_render_faq( $attr = array() ) {
	$set   = isset( $attr['set'] ) ? sanitize_key( $attr['set'] ) : 'algemeen';
	$title = isset( $attr['title'] ) ? sanitize_text_field( $attr['title'] ) : 'Veelgestelde vragen';
	$sets  = ekinese_faq_sets();
	$faqs  = $sets[ $set ] ?? $sets['algemeen'];
	if ( ! $faqs ) {
		return '';
	}
	ob_start();
	echo '<section class="xg-faq"><div class="xg-container">';
	echo '<h2>' . esc_html( $title ) . '</h2>';
	echo '<div class="xg-faq-list">';
	foreach ( $faqs as $f ) {
		echo '<details class="xg-faq-item"><summary>' . esc_html( $f['q'] ) . '</summary><div class="xg-faq-a">' . esc_html( $f['a'] ) . '</div></details>';
	}
	echo '</div></div></section>';
	// FAQPage-schema (één keer per set-render).
	if ( function_exists( 'ekinese_faq_schema' ) ) {
		ekinese_faq_schema( $faqs );
	}
	return ob_get_clean();
}
