<?php
/**
 * XGOUD Prijs-tabs (ekinese/price-tabs) — live inkoopprijzen per metaal in
 * tabbladen (Goud/Zilver/Platina/Palladium). Conversion-gericht: per metaal
 * de prijs per gram/kilo/troy ounce + onze inkoopprijs en een directe CTA.
 * Indexeerbare, server-gerenderde content (goed voor GEO/SEO).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_register_price_tabs() {
	register_block_type( 'ekinese/price-tabs', array( 'render_callback' => 'ekinese_render_price_tabs' ) );
}
add_action( 'init', 'ekinese_register_price_tabs' );

function ekinese_render_price_tabs() {
	$metals = array(
		'goud'      => 'Goud',
		'zilver'    => 'Zilver',
		'platina'   => 'Platina',
		'palladium' => 'Palladium',
	);
	$margins = get_option( 'xg_calc_margins', array() );
	$margin  = isset( $margins['metal'] ) ? (float) $margins['metal'] : 0.08;
	$oz      = 31.1035; // troy ounce in gram.
	$eur     = function ( $v, $d = 2 ) { return '€ ' . number_format_i18n( (float) $v, $d ); };

	ob_start();
	echo '<section class="xg-ptabs"><div class="xg-container">';
	echo '<h2 class="xg-section-title">Actuele inkoopprijzen</h2>';
	echo '<p class="xg-intro">Onze prijzen volgen live de officiële spotkoers (LBMA). Kies een metaal voor de actuele prijs per gram, kilo en troy ounce — en bereken direct uw waarde.</p>';

	// Tabbladen.
	echo '<div class="xg-ptabs-nav" role="tablist">';
	$first = true;
	foreach ( $metals as $code => $label ) {
		printf(
			'<button type="button" class="xg-ptab%s" data-pt="%s" role="tab">%s</button>',
			$first ? ' active' : '',
			esc_attr( $code ),
			esc_html( $label )
		);
		$first = false;
	}
	echo '</div>';

	// Panelen.
	echo '<div class="xg-ptabs-panels">';
	$first = true;
	foreach ( $metals as $code => $label ) {
		$spot = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $code ) : 0;
		$buy  = $spot * ( 1 - $margin );
		printf( '<div class="xg-ptab-panel%s" data-pt="%s" role="tabpanel">', $first ? ' active' : '', esc_attr( $code ) );
		echo '<div class="xg-ptab-grid">';
		echo '<div class="xg-ptab-cell"><span>Spotprijs per gram</span><strong>' . esc_html( $eur( $spot ) ) . '</strong></div>';
		echo '<div class="xg-ptab-cell"><span>Per kilo</span><strong>' . esc_html( $eur( $spot * 1000, 0 ) ) . '</strong></div>';
		echo '<div class="xg-ptab-cell"><span>Per troy ounce</span><strong>' . esc_html( $eur( $spot * $oz ) ) . '</strong></div>';
		echo '<div class="xg-ptab-cell xg-ptab-cell--buy"><span>Onze inkoopprijs / gram</span><strong>' . esc_html( $eur( $buy ) ) . '</strong></div>';
		echo '</div>';
		echo '<div class="xg-ptab-cta">';
		echo '<a class="xg-btn-gold xg-ptab-btn" href="' . esc_url( home_url( '/verkopen/edelmetalen/' . $code . '/' ) ) . '">' . esc_html( $label ) . ' verkopen</a>';
		echo '<a class="xg-ptab-link" href="' . esc_url( home_url( '/afspraak/' ) ) . '">Bereken uw waarde →</a>';
		echo '</div>';
		echo '</div>';
		$first = false;
	}
	echo '</div>';
	echo '<p class="xg-ptabs-note">Laatst bijgewerkt: ' . esc_html( date_i18n( 'd-m-Y H:i' ) ) . '. Prijzen indicatief op basis van de actuele spotkoers.</p>';
	echo '</div></section>';
	return ob_get_clean();
}

/** Kleine tab-schakelaar laden waar het blok staat. */
function ekinese_price_tabs_assets() {
	if ( ! is_singular() || ! has_block( 'ekinese/price-tabs' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/price-tabs.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-price-tabs', get_theme_file_uri( 'assets/js/price-tabs.js' ), array(), (string) filemtime( $js ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_price_tabs_assets' );
