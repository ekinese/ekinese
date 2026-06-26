<?php
/**
 * XGOUD live spotprijzen. Vult de optie xg_metals_spot (€/gram) die
 * ekinese_metal_spot() leest — overal op de site (ticker, rekenaar, assistent,
 * dashboard, prijslijsten). Twee bronnen:
 *   - HANDMATIG: u zet zelf de 4 referentieprijzen (volledige controle).
 *   - AUTOMATISCH: optioneel via goldapi.io (API-key) — uurlijkse cron.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_spot_symbols() {
	return array( 'goud' => 'XAU', 'zilver' => 'XAG', 'platina' => 'XPT', 'palladium' => 'XPD' );
}

/** Haalt de live prijzen op bij goldapi.io en schrijft €/gram naar de optie. */
function ekinese_spot_fetch() {
	$key = trim( (string) get_option( 'xg_spot_api_key', '' ) );
	if ( '' === $key ) {
		return false;
	}
	$spot = (array) get_option( 'xg_metals_spot', array() );
	$ok   = false;
	foreach ( ekinese_spot_symbols() as $metal => $sym ) {
		$resp = wp_remote_get( "https://www.goldapi.io/api/{$sym}/EUR", array(
			'timeout' => 15,
			'headers' => array( 'x-access-token' => $key, 'Content-Type' => 'application/json' ),
		) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			continue;
		}
		$d = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$g = 0.0;
		if ( isset( $d['price_gram_24k'] ) && $d['price_gram_24k'] > 0 ) {
			$g = (float) $d['price_gram_24k'];
		} elseif ( isset( $d['price'] ) && $d['price'] > 0 ) {
			$g = (float) $d['price'] / 31.1035; // per ounce → per gram
		}
		if ( $g > 0 ) {
			$spot[ $metal ] = round( $g, 4 );
			$ok             = true;
		}
	}
	if ( $ok ) {
		update_option( 'xg_metals_spot', $spot, false );
		update_option( 'xg_spot_updated', current_time( 'mysql' ), false );
		delete_transient( 'xg_stats' );
	}
	return $ok;
}

/* Uurlijkse cron (alleen actief bij een API-key + auto aan). */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_spot_fetch' ) ) {
		wp_schedule_event( time() + 120, 'hourly', 'xg_spot_fetch' );
	}
} );
add_action( 'xg_spot_fetch', function () {
	if ( get_option( 'xg_spot_auto', '' ) === '1' ) {
		ekinese_spot_fetch();
	}
} );

/* =====================================================================
   ADMIN — Metaalprijzen
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Metaalprijzen', 'ekinese' ), __( 'Metaalprijzen', 'ekinese' ), 'manage_options', 'xg-spot', 'ekinese_spot_page' );
} );

function ekinese_spot_page() {
	$labels = array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' );

	if ( isset( $_POST['xg_spot_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_spot_nonce'] ), 'xg_spot' ) ) {
		if ( isset( $_POST['xg_spot_fetch_now'] ) ) {
			$done = ekinese_spot_fetch();
			echo '<div class="notice notice-' . ( $done ? 'success' : 'error' ) . '"><p>' . ( $done ? 'Live prijzen opgehaald.' : 'Ophalen mislukt (controleer de API-key).' ) . '</p></div>';
		} else {
			$spot = array();
			foreach ( array_keys( $labels ) as $m ) {
				if ( isset( $_POST[ 'spot_' . $m ] ) ) {
					$spot[ $m ] = round( (float) $_POST[ 'spot_' . $m ], 4 );
				}
			}
			update_option( 'xg_metals_spot', $spot, false );
			update_option( 'xg_spot_api_key', sanitize_text_field( wp_unslash( $_POST['xg_spot_api_key'] ?? '' ) ) );
			update_option( 'xg_spot_auto', ! empty( $_POST['xg_spot_auto'] ) ? '1' : '' );
			update_option( 'xg_spot_updated', current_time( 'mysql' ), false );
			delete_transient( 'xg_stats' );
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
	}

	$spot = (array) get_option( 'xg_metals_spot', array() );
	echo '<div class="wrap"><h1>Metaalprijzen (€/gram, 999)</h1>';
	echo '<p>Deze prijzen worden overal gebruikt: ticker, rekenaar, assistent, dashboard en prijslijsten. Laatst bijgewerkt: <strong>' . esc_html( get_option( 'xg_spot_updated', '—' ) ) . '</strong>.</p>';
	echo '<form method="post"><table class="form-table">';
	wp_nonce_field( 'xg_spot', 'xg_spot_nonce' );
	foreach ( $labels as $m => $lbl ) {
		$cur = isset( $spot[ $m ] ) ? esc_attr( $spot[ $m ] ) : '';
		echo '<tr><th>' . esc_html( $lbl ) . ' (€/g)</th><td><input type="number" step="0.0001" min="0" name="spot_' . esc_attr( $m ) . '" value="' . $cur . '" class="regular-text"></td></tr>';
	}
	echo '<tr><th>Automatisch ophalen</th><td><label><input type="checkbox" name="xg_spot_auto" value="1" ' . checked( get_option( 'xg_spot_auto', '' ), '1', false ) . '> Elk uur via goldapi.io</label></td></tr>';
	echo '<tr><th>goldapi.io API-key</th><td><input type="text" name="xg_spot_api_key" value="' . esc_attr( get_option( 'xg_spot_api_key', '' ) ) . '" class="regular-text" placeholder="goldapi-…"><p class="description">Gratis tier op goldapi.io. Zonder key gebruikt de site uw handmatige prijzen.</p></td></tr>';
	echo '</table>';
	submit_button( 'Opslaan' );
	echo '<p><button type="submit" name="xg_spot_fetch_now" value="1" class="button">Nu live ophalen</button></p>';
	echo '</form></div>';
}
