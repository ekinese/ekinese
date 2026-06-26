<?php
/**
 * XGOUD bezoek-verificatie. De chauffeur komt anoniem; met een per-afspraak
 * geheime code (alleen XGOUD kent die) tonen klant en chauffeur elkaar dat de
 * juiste persoon voor de juiste afspraak komt.
 *
 *  - De KLANT ziet op een verify-pagina/bevestiging welke code de chauffeur toont.
 *  - De CHAUFFEUR ziet dezelfde code in de app en vinkt "klant geverifieerd" af
 *    (of scant de code met de native barcode-scanner). Dit zet meteen de check-in.
 *
 * Code-first (de code is leidend); de scanner is optioneel comfort. Geen QR-
 * generatie, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Per-afspraak verificatiecode (6 tekens, niet-ambigu). */
function ekinese_visit_code( $appt ) {
	$appt = (int) $appt;
	$c    = get_post_meta( $appt, 'vcode', true );
	if ( ! $c ) {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$c     = '';
		for ( $i = 0; $i < 6; $i++ ) {
			$c .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}
		update_post_meta( $appt, 'vcode', $c );
	}
	return $c;
}

/* =====================================================================
   REST
===================================================================== */
add_action( 'rest_api_init', function () {
	// Klant controleert: is dit een echte XGOUD-afspraak/medewerker?
	register_rest_route( 'ekinese/v1', '/verify/check', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_verify_check',
	) );
	// Chauffeur bevestigt de klant (route-token) → markeren + check-in.
	register_rest_route( 'ekinese/v1', '/fleet/verify', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_fleet_verify',
	) );
} );

function ekinese_verify_check( WP_REST_Request $r ) {
	$appt = (int) $r->get_param( 'a' );
	$code = strtoupper( sanitize_text_field( (string) $r->get_param( 'c' ) ) );
	if ( get_post_type( $appt ) !== 'xg_appointment' ) {
		return rest_ensure_response( array( 'valid' => false ) );
	}
	if ( ! hash_equals( ekinese_visit_code( $appt ), $code ) ) {
		return rest_ensure_response( array( 'valid' => false ) );
	}
	return rest_ensure_response( array(
		'valid'   => true,
		'code'    => $code,
		'date'    => get_post_meta( $appt, 'date', true ),
		'time'    => get_post_meta( $appt, 'time', true ),
		'service' => get_post_meta( $appt, 'service', true ),
		'city'    => get_post_meta( $appt, 'city', true ),
	) );
}

/** Chauffeur bevestigt de klant via route-token + stop-index. */
function ekinese_fleet_verify( WP_REST_Request $r ) {
	$token = (string) $r->get_param( 'token' );
	$q     = $token ? get_posts( array( 'post_type' => 'xg_route', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'token', 'meta_value' => $token ) ) : array();
	if ( ! $q ) {
		return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
	}
	$rid   = (int) $q[0];
	$idx   = (int) $r->get_param( 'index' );
	$stops = json_decode( (string) get_post_meta( $rid, 'stops', true ), true ) ?: array();
	if ( ! isset( $stops[ $idx ] ) ) {
		return new WP_Error( 'badindex', 'stop', array( 'status' => 400 ) );
	}
	$appt = (int) ( $stops[ $idx ]['appointment'] ?? 0 );
	// Optioneel: gescande/ingevoerde code controleren tegen de afspraak.
	$scanned = strtoupper( sanitize_text_field( (string) $r->get_param( 'code' ) ) );
	if ( $appt && '' !== $scanned && ! hash_equals( ekinese_visit_code( $appt ), $scanned ) ) {
		return new WP_Error( 'mismatch', 'Code komt niet overeen.', array( 'status' => 400 ) );
	}
	$stops[ $idx ]['verified']    = current_time( 'mysql' );
	if ( empty( $stops[ $idx ]['arrived_at'] ) ) {
		$stops[ $idx ]['arrived_at'] = current_time( 'mysql' );
		$stops[ $idx ]['status']     = 'arrived';
	}
	update_post_meta( $rid, 'stops', wp_json_encode( $stops ) );
	if ( $appt ) {
		update_post_meta( $appt, 'verified_at', current_time( 'mysql' ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'name' => $appt ? ( get_post_meta( $appt, 'name', true ) ?: get_the_title( $appt ) ) : '' ) );
}

/* =====================================================================
   KLANT-VERIFY-PAGINA  (block ekinese/verify)
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/verify', array( 'render_callback' => 'ekinese_render_verify' ) );
} );

function ekinese_render_verify() {
	return '<section class="xg-verify" data-rest="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/verify/check' ) ) ) . '">'
		. '<div class="xg-container"><div class="xg-verify-box">Controleren…</div></div></section>';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/verify' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/verify.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-verify', get_theme_file_uri( 'assets/js/verify.js' ), array(), (string) filemtime( $js ), true );
	}
} );

add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular() && has_block( 'ekinese/verify' ) ) {
		$r['noindex'] = true;
	}
	return $r;
} );

/* Verify-link + code per afspraak in Mijn XGOUD. */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	if ( ! empty( $data['appointments'] ) && is_array( $data['appointments'] ) ) {
		foreach ( $data['appointments'] as &$a ) {
			if ( ! empty( $a['_id'] ) ) {
				$a['vcode']      = ekinese_visit_code( (int) $a['_id'] );
				$a['verify_url'] = home_url( '/verify/?a=' . (int) $a['_id'] . '&c=' . $a['vcode'] );
				$a['verified']   = get_post_meta( (int) $a['_id'], 'verified_at', true ) ? 1 : 0;
			}
		}
		unset( $a );
	}
	return $data;
}, 14, 2 );
