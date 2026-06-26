<?php
/**
 * XGOUD eenvoudige rate-limiting voor publieke REST-endpoints (plugin-vrij).
 * Beschermt tegen misbruik en houdt de AI-kosten in toom: per IP + een globaal
 * dagplafond voor de AI-assistent.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Beste gok voor het client-IP (achter proxy/Cloudflare). */
function ekinese_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ) )[0];
			return trim( $ip );
		}
	}
	return '0.0.0.0';
}

/**
 * Per-IP rate-limit. Geeft true als de aanvraag is toegestaan.
 *
 * @param string $bucket  Naam van de actie (bv. 'assistant').
 * @param int    $max     Max. aantal binnen het venster.
 * @param int    $window  Venster in seconden.
 */
function ekinese_rate_limit( $bucket, $max = 20, $window = 300 ) {
	$key = 'xg_rl_' . md5( $bucket . '|' . ekinese_client_ip() );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $key, $n + 1, $window );
	return true;
}

/** Globaal dagplafond (bv. om AI-kosten te begrenzen). */
function ekinese_quota_day( $bucket, $max ) {
	$key = 'xg_q_' . $bucket . '_' . gmdate( 'Ymd' );
	$n   = (int) get_option( $key, 0 );
	if ( $n >= $max ) {
		return false;
	}
	update_option( $key, $n + 1, false );
	return true;
}
