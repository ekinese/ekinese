<?php
/**
 * XGOUD Diamant-Preise via IDEX (RealTimePrices/SinglePrice).
 *
 * Performance (Cron-Cache): ein Cron holt periodisch den IDEX-Referenzpreis
 * (1,00 ct, D, IF, Excellent) und legt ihn als "anchor" (in EUR) ab. Der
 * Frontend-Calculator rechnet sofort mit anchor × 4C-Faktoren – keine
 * Live-API-Calls beim Kunden (schützt das API-Limit).
 *
 * Auth: API-Key + Secret-Key werden im Backend hinterlegt (NICHT im Code).
 *   Instellingen → IDEX prijzen.
 *
 * HINWEIS: Endpoint/Logik stehen. Falls IDEX andere Parameter-/Feldnamen nutzt,
 * zeigt der „Test nu"-knop im Admin die ROHE response – danach ist nur das
 * Parsing in ekinese_idex_parse_price() ggf. um ein Feld zu ergänzen.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_IDEX_ENDPOINT = 'https://api.idexonline.com/RealTimePrices/SinglePrice';

function ekinese_idex_default_anchor() {
	return 9000.0; // €/ct, 1,00 ct D/IF/Excellent (Fallback bis IDEX liefert).
}

/** Aktueller Anchor in EUR (Cache, sonst Default). */
function ekinese_idex_anchor() {
	$cache = get_option( 'xg_idex_cache' );
	if ( is_array( $cache ) && ! empty( $cache['anchor'] ) ) {
		return (float) $cache['anchor'];
	}
	return ekinese_idex_default_anchor();
}

/** USD→EUR-Kurs (IDEX liefert i.d.R. USD). Editierbar im Admin. */
function ekinese_idex_usd_eur() {
	$r = (float) get_option( 'xg_idex_usdeur' );
	return $r > 0 ? $r : 0.92;
}

/**
 * Preis ($/ct) aus einer IDEX-Response extrahieren. Akzeptiert JSON
 * (verschiedene Feldnamen) oder reinen Zahlentext.
 *
 * @param string $raw
 * @return float|null
 */
function ekinese_idex_parse_price( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return null;
	}
	// JSON?
	$json = json_decode( $raw, true );
	if ( is_array( $json ) ) {
		foreach ( array( 'price', 'Price', 'PricePerCarat', 'pricePerCarat', 'CaratPrice', 'value', 'Value' ) as $k ) {
			if ( isset( $json[ $k ] ) && is_numeric( $json[ $k ] ) ) {
				return (float) $json[ $k ];
			}
		}
		// Verschachtelt (erstes numerisches Feld).
		array_walk_recursive( $json, function ( $v ) use ( &$found ) {
			if ( null === $found && is_numeric( $v ) ) {
				$found = (float) $v;
			}
		}, $found = null );
		if ( null !== $found ) {
			return $found;
		}
	}
	// Reiner Zahlentext (evtl. mit Tausenderkomma)?
	$num = preg_replace( '/[^0-9.\-]/', '', str_replace( ',', '', $raw ) );
	return is_numeric( $num ) ? (float) $num : null;
}

/**
 * IDEX-Request. Liefert €/ct (nach USD→EUR) oder null. Speichert die rohe
 * Antwort für das Admin-Debug.
 */
function ekinese_idex_request( $carat = 1.0, $color = 'D', $clarity = 'IF', $cut = 'Excellent', $shape = 'Round' ) {
	$key    = get_option( 'xg_idex_key' );
	$secret = get_option( 'xg_idex_secret' );
	if ( ! $key || ! $secret ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'apiKey'       => $key,
			'secretKey'    => $secret,
			'Shape'        => $shape,
			'Carat'        => $carat,
			'Color'        => $color,
			'Clarity'      => $clarity,
			'Cut'          => $cut,
			'Fluorescence' => 'None',
		),
		XG_IDEX_ENDPOINT
	);

	$res = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'Accept' => 'application/json' ) ) );
	if ( is_wp_error( $res ) ) {
		update_option( 'xg_idex_lastraw', 'WP_Error: ' . $res->get_error_message() );
		return null;
	}
	$code = wp_remote_retrieve_response_code( $res );
	$body = wp_remote_retrieve_body( $res );
	update_option( 'xg_idex_lastraw', 'HTTP ' . $code . "\n" . mb_substr( $body, 0, 2000 ) );

	if ( 200 !== $code ) {
		return null;
	}
	$usd = ekinese_idex_parse_price( $body );
	if ( null === $usd || $usd <= 0 ) {
		return null;
	}
	return round( $usd * ekinese_idex_usd_eur(), 2 ); // €/ct
}

/** Cron: Anchor aktualisieren. */
function ekinese_idex_refresh() {
	$anchor = ekinese_idex_request( 1.0, 'D', 'IF', 'Excellent' );
	if ( null !== $anchor && $anchor > 0 ) {
		update_option( 'xg_idex_cache', array( 'anchor' => $anchor, 'updated' => gmdate( 'c' ) ) );
	}
}
add_action( 'xg_idex_refresh_event', 'ekinese_idex_refresh' );

function ekinese_idex_schedule() {
	if ( ! wp_next_scheduled( 'xg_idex_refresh_event' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'xg_idex_refresh_event' );
	}
}
add_action( 'init', 'ekinese_idex_schedule' );

/* =====================================================================
   ADMIN
===================================================================== */
function ekinese_idex_menu() {
	add_submenu_page( 'options-general.php', __( 'IDEX diamantprijzen', 'ekinese' ), __( 'IDEX prijzen', 'ekinese' ), 'manage_options', 'xg-idex', 'ekinese_idex_page' );
}
add_action( 'admin_menu', 'ekinese_idex_menu' );

function ekinese_idex_page() {
	if ( isset( $_POST['xg_idex_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_idex_nonce'] ), 'xg_idex' ) ) {
		update_option( 'xg_idex_key', sanitize_text_field( wp_unslash( $_POST['xg_idex_key'] ?? '' ) ) );
		update_option( 'xg_idex_secret', sanitize_text_field( wp_unslash( $_POST['xg_idex_secret'] ?? '' ) ) );
		update_option( 'xg_idex_usdeur', (float) ( $_POST['xg_idex_usdeur'] ?? 0.92 ) );
		if ( ! empty( $_POST['xg_idex_test'] ) ) {
			ekinese_idex_refresh();
		}
		echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
	}

	$cache = get_option( 'xg_idex_cache' );
	$raw   = get_option( 'xg_idex_lastraw' );
	echo '<div class="wrap"><h1>IDEX diamantprijzen</h1>';
	echo '<p>Endpoint: <code>' . esc_html( XG_IDEX_ENDPOINT ) . '</code></p>';
	echo '<p><strong>Huidige anchor (€/ct, 1,00 ct D/IF/Excellent):</strong> € ' . esc_html( number_format( ekinese_idex_anchor(), 2, ',', '.' ) );
	if ( is_array( $cache ) && ! empty( $cache['updated'] ) ) {
		echo ' <em>(' . esc_html( $cache['updated'] ) . ')</em>';
	}
	echo '</p>';

	echo '<form method="post"><table class="form-table">';
	echo '<tr><th>API-key</th><td><input type="text" name="xg_idex_key" value="' . esc_attr( get_option( 'xg_idex_key' ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Secret-key</th><td><input type="password" name="xg_idex_secret" value="' . esc_attr( get_option( 'xg_idex_secret' ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>USD → EUR koers</th><td><input type="number" step="0.0001" name="xg_idex_usdeur" value="' . esc_attr( ekinese_idex_usd_eur() ) . '"></td></tr>';
	echo '<tr><th>Test nu</th><td><label><input type="checkbox" name="xg_idex_test" value="1"> ophalen bij opslaan</label></td></tr>';
	echo '</table>';
	wp_nonce_field( 'xg_idex', 'xg_idex_nonce' );
	submit_button();
	echo '</form>';

	if ( $raw ) {
		echo '<h2>Laatste ruwe API-response</h2><pre style="background:#1d1b19;color:#d9d4c8;padding:14px;overflow:auto;max-height:320px">' . esc_html( $raw ) . '</pre>';
		echo '<p class="description">Toont het exacte antwoordformaat van IDEX – zo stemmen we het parsen (ekinese_idex_parse_price) 1-op-1 af.</p>';
	}
	echo '</div>';
}
