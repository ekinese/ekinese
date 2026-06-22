<?php
/**
 * XGOUD sticky CTA-balk – "Bereken waarde" / "Maak afspraak" onderaan (mobiel).
 *
 * Verschijnt na scrollen onderaan het scherm met een snelle actie naar de
 * rekenaar (.xg-calc) of /afspraak/. Verhoogt conversie zonder opdringerig te
 * zijn. Verborgen op de driver-app en op de afspraakpagina zelf.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Render de sticky CTA-balk + laad JS/CSS (front-end). */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( get_query_var( 'xg_driver' ) || false !== strpos( $uri, '/rit/' ) || false !== strpos( $uri, '/afspraak' ) ) {
		return;
	}
	?>
	<div class="xg-sticky-cta" hidden>
		<span class="xg-sticky-cta-txt"><?php esc_html_e( 'Wat is uw goud waard?', 'ekinese' ); ?></span>
		<a class="xg-sticky-cta-btn" href="/afspraak/"><?php esc_html_e( 'Bereken waarde', 'ekinese' ); ?></a>
	</div>
	<?php
	$js = get_theme_file_path( 'assets/js/sticky-calc.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-sticky-cta', get_theme_file_uri( 'assets/js/sticky-calc.js' ), array(), (string) filemtime( $js ), true );
	}
}, 5 );
