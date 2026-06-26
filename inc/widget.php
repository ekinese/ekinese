<?php
/**
 * XGOUD app-widget (/app/) — het glanceable startscherm van de PWA:
 * live prijzen (4 metalen), profiel (spaarpunten/niveau), portfolio (waarde +
 * winst) en de eerstvolgende afspraken. Inloggen via dezelfde magic-link.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Publieke prijzen-endpoint (live €/g per metaal). */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/prices', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$out = array();
			foreach ( array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' ) as $code => $label ) {
				$spot         = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $code ) : 0;
				$out[ $code ] = array( 'label' => $label, 'gram' => round( $spot, 2 ) );
			}
			return rest_ensure_response( array( 'prices' => $out, 'updated' => get_option( 'xg_spot_updated', '' ) ) );
		},
	) );
} );

/* Block ekinese/widget. */
add_action( 'init', function () {
	register_block_type( 'ekinese/widget', array( 'render_callback' => 'ekinese_render_widget' ) );
} );

function ekinese_render_widget() {
	return '<div class="xg-widget"'
		. ' data-prices="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/prices' ) ) ) . '"'
		. ' data-login="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/account/login' ) ) ) . '"'
		. ' data-data="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/account/data' ) ) ) . '">'
		. '<div class="xg-w-loading">Laden…</div></div>';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/widget' ) ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/widget.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-widget', get_theme_file_uri( 'assets/css/widget.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/widget.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-widget', get_theme_file_uri( 'assets/js/widget.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* /app/ niet indexeren. */
add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular() && has_block( 'ekinese/widget' ) ) {
		$r['noindex'] = true;
	}
	return $r;
} );
