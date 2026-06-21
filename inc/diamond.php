<?php
/**
 * XGOUD Diamant-Preise via IDEX (RealTimePrices/SinglePrice).
 *
 * Performance-Konzept (Cron-Cache):
 *   - Ein Cron holt periodisch den IDEX-Referenzpreis (1,00 ct, D, IF,
 *     Excellent) und legt ihn als "anchor" im Cache ab.
 *   - Der Frontend-Calculator rechnet sofort mit diesem Anchor × 4C-Faktoren
 *     (keine Live-API-Calls beim Kunden, schützt das API-Limit).
 *
 * HINWEIS: Endpoint steht (api.idexonline.com/RealTimePrices/SinglePrice).
 * Sobald Auth (API-Key/Header) und das Request-/Response-Format vorliegen,
 * sind nur ekinese_idex_request() und das Parsing anzupassen – der Rest bleibt.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_IDEX_ENDPOINT = 'https://api.idexonline.com/RealTimePrices/SinglePrice';

/** Default-Anchor (€/ct für 1,00 ct D/IF/Excellent), bis IDEX liefert. */
function ekinese_idex_default_anchor() {
	return 9000.0;
}

/** Aktueller Anchor (aus Cache, sonst Default). */
function ekinese_idex_anchor() {
	$cache = get_option( 'xg_idex_cache' );
	if ( is_array( $cache ) && ! empty( $cache['anchor'] ) ) {
		return (float) $cache['anchor'];
	}
	return ekinese_idex_default_anchor();
}

/**
 * IDEX-Request (Platzhalter). Muss an die echte Doku angepasst werden:
 *   - Auth: API-Key in Header (z.B. 'Authorization') oder Query.
 *   - Params: shape/carat/color/clarity/cut.
 *   - Response: Feld mit Preis (€/$ pro ct) extrahieren.
 *
 * @return float|null €/ct oder null bei Fehler.
 */
function ekinese_idex_request( $carat = 1.0, $color = 'D', $clarity = 'IF', $cut = 'Excellent' ) {
	$key = get_option( 'xg_idex_key' );
	if ( ! $key ) {
		return null;
	}

	$url = add_query_arg(
		array(
			// TODO: echte Parameter-Namen laut IDEX-Doku.
			'shape'   => 'Round',
			'carat'   => $carat,
			'color'   => $color,
			'clarity' => $clarity,
			'cut'     => $cut,
		),
		XG_IDEX_ENDPOINT
	);

	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 15,
			'headers' => array(
				// TODO: echtes Auth-Schema laut IDEX-Doku.
				'Authorization' => 'Bearer ' . $key,
				'Accept'        => 'application/json',
			),
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );

	// TODO: echtes Response-Feld laut IDEX-Doku.
	$price = $body['price'] ?? ( $body['PricePerCarat'] ?? null );
	return is_numeric( $price ) ? (float) $price : null;
}

/** Cron: Anchor aktualisieren. */
function ekinese_idex_refresh() {
	$anchor = ekinese_idex_request( 1.0, 'D', 'IF', 'Excellent' );
	if ( null !== $anchor && $anchor > 0 ) {
		update_option( 'xg_idex_cache', array( 'anchor' => $anchor, 'updated' => gmdate( 'c' ) ) );
	}
}
add_action( 'xg_idex_refresh_event', 'ekinese_idex_refresh' );

/** Cron einplanen (stündlich). */
function ekinese_idex_schedule() {
	if ( ! wp_next_scheduled( 'xg_idex_refresh_event' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'xg_idex_refresh_event' );
	}
}
add_action( 'init', 'ekinese_idex_schedule' );

/* =====================================================================
   ADMIN: API-Key + Status
===================================================================== */
function ekinese_idex_menu() {
	add_submenu_page( 'options-general.php', __( 'IDEX diamantprijzen', 'ekinese' ), __( 'IDEX prijzen', 'ekinese' ), 'manage_options', 'xg-idex', 'ekinese_idex_page' );
}
add_action( 'admin_menu', 'ekinese_idex_menu' );

function ekinese_idex_page() {
	if ( isset( $_POST['xg_idex_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_idex_nonce'] ), 'xg_idex' ) ) {
		update_option( 'xg_idex_key', sanitize_text_field( wp_unslash( $_POST['xg_idex_key'] ?? '' ) ) );
		if ( ! empty( $_POST['xg_idex_refresh'] ) ) {
			ekinese_idex_refresh();
		}
		echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
	}
	$cache = get_option( 'xg_idex_cache' );
	echo '<div class="wrap"><h1>IDEX diamantprijzen</h1>';
	echo '<p>Endpoint: <code>' . esc_html( XG_IDEX_ENDPOINT ) . '</code></p>';
	echo '<p><strong>Huidige anchor (€/ct, 1,00 ct D/IF/Excellent):</strong> € ' . esc_html( number_format( ekinese_idex_anchor(), 2, ',', '.' ) );
	if ( is_array( $cache ) && ! empty( $cache['updated'] ) ) {
		echo ' <em>(' . esc_html( $cache['updated'] ) . ')</em>';
	}
	echo '</p><form method="post"><table class="form-table"><tr><th>API-key</th><td><input type="text" name="xg_idex_key" value="' . esc_attr( get_option( 'xg_idex_key' ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Nu verversen</th><td><label><input type="checkbox" name="xg_idex_refresh" value="1"> ophalen bij opslaan</label></td></tr></table>';
	wp_nonce_field( 'xg_idex', 'xg_idex_nonce' );
	submit_button();
	echo '<p class="description">Zodra auth/parameters van IDEX bekend zijn, worden alleen ekinese_idex_request() en het parsen aangepast.</p></form></div>';
}
