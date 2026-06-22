<?php
/**
 * XGOUD stempel-herkenner (#13) – sieraad-keurmerk → karaat + indicatie.
 *
 * Blok ekinese/hallmark: de klant voert een stempel in (585, 750, 925, 999…)
 * en krijgt direct het metaal + gehalte + een prijsindicatie per gram op basis
 * van ekinese_metal_spot(). Pure client-side rekenlogica met server-data; geen
 * API-key nodig. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keurmerken-tabel: stempel => [metaal, gehalte 0-1, label]. */
function ekinese_hallmarks() {
	return array(
		// Goud
		'375' => array( 'goud', 0.375, '9 karaat goud' ),
		'417' => array( 'goud', 0.417, '10 karaat goud' ),
		'585' => array( 'goud', 0.585, '14 karaat goud' ),
		'750' => array( 'goud', 0.750, '18 karaat goud' ),
		'833' => array( 'goud', 0.833, '20 karaat goud' ),
		'916' => array( 'goud', 0.916, '22 karaat goud' ),
		'990' => array( 'goud', 0.990, '23,8 karaat goud' ),
		'999' => array( 'goud', 0.999, '24 karaat goud' ),
		// Zilver
		'800' => array( 'zilver', 0.800, '800 zilver' ),
		'835' => array( 'zilver', 0.835, '835 zilver' ),
		'925' => array( 'zilver', 0.925, 'Sterling zilver (925)' ),
		'958' => array( 'zilver', 0.958, 'Britannia zilver (958)' ),
		// Platina
		'850' => array( 'platina', 0.850, '850 platina' ),
		'950' => array( 'platina', 0.950, '950 platina' ),
	);
}

add_action( 'init', function () {
	register_block_type( 'ekinese/hallmark', array( 'render_callback' => 'ekinese_render_hallmark' ) );
} );

/** Render het herkenner-widget; data via data-attr voor de JS. */
function ekinese_render_hallmark() {
	$marks = ekinese_hallmarks();
	$spots = array(
		'goud'    => function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( 'goud' ) : 0,
		'zilver'  => function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( 'zilver' ) : 0,
		'platina' => function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( 'platina' ) : 0,
	);
	$payload = wp_json_encode( array( 'marks' => $marks, 'spots' => $spots ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	ob_start();
	echo '<div class="xg-hallmark" data-hallmark="' . esc_attr( $payload ) . '">';
	echo '<h3 class="xg-hallmark-title">Wat zegt het stempel?</h3>';
	echo '<p class="xg-hallmark-lead">Veel sieraden hebben een klein stempel met het gehalte. Voer het in en zie direct het metaal en een prijsindicatie.</p>';
	echo '<div class="xg-hallmark-row">';
	echo '<input type="text" class="xg-hallmark-input" inputmode="numeric" placeholder="bijv. 585, 750 of 925" aria-label="Stempel">';
	echo '<button type="button" class="xg-hallmark-btn">Herken</button>';
	echo '</div>';
	echo '<div class="xg-hallmark-quick">';
	foreach ( array( '585', '750', '925', '999' ) as $q ) {
		echo '<button type="button" class="xg-hallmark-chip" data-mark="' . esc_attr( $q ) . '">' . esc_html( $q ) . '</button>';
	}
	echo '</div>';
	echo '<div class="xg-hallmark-result" hidden></div>';
	echo '<p class="xg-hallmark-cta"><a class="xg-btn-gold" href="/afspraak/">Bereken de totale waarde</a></p>';
	echo '</div>';
	return ob_get_clean();
}

/** Assets laden waar het blok staat. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/hallmark' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/hallmark.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-hallmark', get_theme_file_uri( 'assets/js/hallmark.js' ), array(), (string) filemtime( $js ), true );
	}
} );
