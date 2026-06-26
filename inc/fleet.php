<?php
/**
 * XGOUD Fleet — volwaardige fahrer-app + backoffice-tracking, bovenop inc/driver.php.
 *
 *  - Fahrer-identiteit (xg_driver) met persoonlijke code; alles loopt via de app.
 *  - Dienst (xg_shift): start/eind, km-stand begin/eind (fahrtenbuch), GPS tijdens dienst.
 *  - Expenses (xg_expense): bon scannen → Claude Vision stelt bedrag/categorie voor
 *    (hybride: chauffeur bevestigt) → wekelijkse uitbetaling in het dashboard.
 *  - Per stop: status, handtekening, ID-scan (Vision → bevestigen) en IBAN (geen
 *    bankkaart-foto's — alleen IBAN), gespiegeld naar de afspraak/KYC.
 *  - ETA: echte rijtijden via OpenRouteService (key) → prognose wanneer de fahrer
 *    waar is; backoffice ziet live GPS + volgende stop + hoelang onderweg.
 *
 * Gevoelige data (ID, handtekening) privé; bewaartermijn Wwft. Geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT's
===================================================================== */
function ekinese_register_fleet() {
	$common = array( 'public' => false, 'show_ui' => true, 'show_in_menu' => 'xgoud', 'supports' => array( 'title' ) );
	register_post_type( 'xg_driver', array_merge( $common, array(
		'labels'    => array( 'name' => __( 'Chauffeurs', 'ekinese' ), 'singular_name' => __( 'Chauffeur', 'ekinese' ), 'menu_name' => __( 'Chauffeurs', 'ekinese' ) ),
		'menu_icon' => 'dashicons-id',
	) ) );
	register_post_type( 'xg_shift', array_merge( $common, array(
		'labels'    => array( 'name' => __( 'Diensten', 'ekinese' ), 'singular_name' => __( 'Dienst', 'ekinese' ), 'menu_name' => __( 'Diensten', 'ekinese' ) ),
		'menu_icon' => 'dashicons-clock',
	) ) );
	register_post_type( 'xg_expense', array_merge( $common, array(
		'labels'    => array( 'name' => __( 'Onkosten', 'ekinese' ), 'singular_name' => __( 'Onkostenpost', 'ekinese' ), 'menu_name' => __( 'Onkosten', 'ekinese' ) ),
		'menu_icon' => 'dashicons-media-spreadsheet',
	) ) );
	foreach ( array( 'code', 'email', 'phone', 'active', 'last_gps' ) as $f ) {
		register_post_meta( 'xg_driver', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
	foreach ( array( 'driver', 'route', 'date', 'start', 'end', 'km_start', 'km_end', 'gps_track' ) as $f ) {
		register_post_meta( 'xg_shift', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
	foreach ( array( 'driver', 'date', 'week', 'amount', 'category', 'vendor', 'note', 'image', 'status', 'reimbursed' ) as $f ) {
		register_post_meta( 'xg_expense', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_fleet' );

function ekinese_expense_categories() {
	return array( 'brandstof' => 'Brandstof', 'parkeren' => 'Parkeren', 'tol' => 'Tol', 'verpleging' => 'Verpleging/eten', 'onderhoud' => 'Onderhoud', 'overig' => 'Overig' );
}

/* =====================================================================
   AUTH — chauffeur via persoonlijke code
===================================================================== */
function ekinese_fleet_driver( $code ) {
	$code = sanitize_text_field( $code );
	if ( '' === $code ) {
		return 0;
	}
	$q = get_posts( array( 'post_type' => 'xg_driver', 'numberposts' => 1, 'post_status' => 'publish', 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'code', 'value' => $code ) ) ) );
	return $q ? (int) $q[0] : 0;
}

/** De route van vandaag (1 dagroute; later per chauffeur te splitsen). */
function ekinese_fleet_today_route() {
	$today = date( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime
	$q     = get_posts( array( 'post_type' => 'xg_route', 'numberposts' => 1, 'post_status' => 'publish', 'fields' => 'ids', 'meta_key' => 'date', 'meta_value' => $today ) );
	return $q ? (int) $q[0] : 0;
}

/** Open dienst van een chauffeur vandaag. */
function ekinese_fleet_open_shift( $driver_id ) {
	$today = date( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime
	$q     = get_posts( array(
		'post_type' => 'xg_shift', 'numberposts' => 1, 'post_status' => 'publish', 'fields' => 'ids',
		'meta_query' => array( 'relation' => 'AND', array( 'key' => 'driver', 'value' => $driver_id ), array( 'key' => 'date', 'value' => $today ) ),
	) );
	return $q ? (int) $q[0] : 0;
}

/* =====================================================================
   OpenRouteService — echte rijtijden/afstanden + ETA
===================================================================== */
function ekinese_ors_key() {
	return trim( (string) get_option( 'xg_ors_key', '' ) );
}

/** Duur (min) + afstand (km) matrix voor een lijst [lat,lng]. */
function ekinese_ors_matrix( $points ) {
	$key = ekinese_ors_key();
	if ( '' === $key || count( $points ) < 2 ) {
		return null;
	}
	$locations = array_map( function ( $p ) { return array( (float) $p[1], (float) $p[0] ); }, $points ); // [lng,lat]
	$resp      = wp_remote_post( 'https://api.openrouteservice.org/v2/matrix/driving-car', array(
		'timeout' => 20,
		'headers' => array( 'Authorization' => $key, 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array( 'locations' => $locations, 'metrics' => array( 'duration', 'distance' ) ) ),
	) );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return null;
	}
	return json_decode( (string) wp_remote_retrieve_body( $resp ), true );
}

/**
 * Verrijk de stops van een route met ETA op basis van ORS (of haversine-fallback).
 * Start = HQ op start_time (default 09:00). Geeft de verrijkte stops terug.
 */
function ekinese_fleet_compute_eta( $stops, $start_time = '09:00', $service_min = 20 ) {
	if ( ! $stops ) {
		return $stops;
	}
	list( $hlat, $hlng ) = ekinese_hq_coords();
	$points = array( array( $hlat, $hlng ) );
	foreach ( $stops as $s ) {
		$points[] = array( (float) ( $s['lat'] ?? 0 ), (float) ( $s['lng'] ?? 0 ) );
	}
	$matrix = ekinese_ors_matrix( $points );
	$t      = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) . ' ' . $start_time ); // phpcs:ignore WordPress.DateTime
	for ( $i = 0; $i < count( $stops ); $i++ ) {
		$leg_min = null; $leg_km = $stops[ $i ]['leg_km'] ?? null;
		if ( $matrix && isset( $matrix['durations'][ $i ][ $i + 1 ] ) ) {
			$leg_min = round( $matrix['durations'][ $i ][ $i + 1 ] / 60 );
			$leg_km  = round( ( $matrix['distances'][ $i ][ $i + 1 ] ?? 0 ) / 1000, 1 );
		} elseif ( null !== $leg_km && function_exists( 'ekinese_distance_km' ) ) {
			$leg_min = round( $leg_km / 50 * 60 ); // grove schatting 50 km/u
		}
		if ( null !== $leg_min ) {
			$t += $leg_min * 60;
			$stops[ $i ]['eta']     = date( 'H:i', $t ); // phpcs:ignore WordPress.DateTime
			$stops[ $i ]['leg_min'] = $leg_min;
			$stops[ $i ]['leg_km']  = $leg_km;
			$t += $service_min * 60;
		}
	}
	return $stops;
}

/* =====================================================================
   CLAUDE VISION — bon/ID uitlezen (hybride: voorstel, mens bevestigt)
===================================================================== */
function ekinese_ai_vision( $base64, $mime, $instruction ) {
	$key = trim( (string) get_option( 'xg_anthropic_key', '' ) );
	if ( '' === $key || '' === $base64 ) {
		return '';
	}
	$model = function_exists( 'ekinese_ai_model' ) ? ekinese_ai_model() : 'claude-haiku-4-5';
	$resp  = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 40,
		'headers' => array( 'content-type' => 'application/json', 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'max_tokens' => 500,
			'messages'   => array( array( 'role' => 'user', 'content' => array(
				array( 'type' => 'image', 'source' => array( 'type' => 'base64', 'media_type' => $mime, 'data' => $base64 ) ),
				array( 'type' => 'text', 'text' => $instruction ),
			) ) ),
		) ),
	) );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return '';
	}
	$d = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	return isset( $d['content'][0]['text'] ) ? (string) $d['content'][0]['text'] : '';
}

/** JSON uit een AI-antwoord vissen. */
function ekinese_json_from( $text ) {
	if ( preg_match( '/\{.*\}/s', (string) $text, $m ) ) {
		$j = json_decode( $m[0], true );
		if ( is_array( $j ) ) {
			return $j;
		}
	}
	return array();
}

/** Base64-afbeelding opslaan als privé-attachment. */
function ekinese_fleet_store_image( $base64, $prefix = 'fleet' ) {
	if ( ! preg_match( '#^data:(image/\w+);base64,(.+)$#s', (string) $base64, $m ) ) {
		return 0;
	}
	$mime = $m[1];
	$bin  = base64_decode( $m[2] );
	if ( ! $bin || strlen( $bin ) > 8 * MB_IN_BYTES ) {
		return 0;
	}
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $mime ] ?? 'jpg';
	$up   = wp_upload_bits( $prefix . '-' . gmdate( 'YmdHis' ) . '-' . wp_rand( 100, 999 ) . '.' . $ext, null, $bin );
	if ( ! empty( $up['error'] ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$att_id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => $prefix, 'post_status' => 'private' ), $up['file'] );
	if ( $att_id && ! is_wp_error( $att_id ) ) {
		wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $up['file'] ) );
	}
	return (int) $att_id;
}

/* =====================================================================
   REST  /fleet/*
===================================================================== */
add_action( 'rest_api_init', function () {
	$routes = array(
		'me'        => 'ekinese_fleet_me',
		'shift'     => 'ekinese_fleet_shift',
		'gps'       => 'ekinese_fleet_gps',
		'ocr'       => 'ekinese_fleet_ocr',
		'expense'   => 'ekinese_fleet_expense',
		'kyc'       => 'ekinese_fleet_kyc',
	);
	foreach ( $routes as $path => $cb ) {
		register_rest_route( 'ekinese/v1', '/fleet/' . $path, array(
			'methods'             => 'me' === $path ? 'GET' : 'POST',
			'permission_callback' => '__return_true',
			'callback'            => $cb,
		) );
	}
} );

/** Auth-helper: geeft driver-id of WP_Error. */
function ekinese_fleet_auth( WP_REST_Request $r ) {
	$code = (string) ( $r->get_param( 'code' ) ?: ( $r->get_json_params()['code'] ?? '' ) );
	$id   = ekinese_fleet_driver( $code );
	return $id ? $id : new WP_Error( 'auth', 'Onbekende code.', array( 'status' => 401 ) );
}

function ekinese_fleet_me( WP_REST_Request $r ) {
	$did = ekinese_fleet_auth( $r );
	if ( is_wp_error( $did ) ) {
		return $did;
	}
	$rid    = ekinese_fleet_today_route();
	$stops  = $rid ? ( json_decode( (string) get_post_meta( $rid, 'stops', true ), true ) ?: array() ) : array();
	$shift  = ekinese_fleet_open_shift( $did );
	$week   = gmdate( 'oW' );
	$exp    = get_posts( array( 'post_type' => 'xg_expense', 'numberposts' => 50, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array( array( 'key' => 'driver', 'value' => $did ) ) ) );
	$exp_rows = array(); $week_total = 0;
	foreach ( $exp as $e ) {
		$amt = (float) get_post_meta( $e->ID, 'amount', true );
		if ( get_post_meta( $e->ID, 'week', true ) === $week ) {
			$week_total += $amt;
		}
		$exp_rows[] = array( 'date' => get_the_date( 'd-m', $e ), 'amount' => number_format_i18n( $amt, 2 ), 'category' => get_post_meta( $e->ID, 'category', true ), 'status' => get_post_meta( $e->ID, 'status', true ) ?: 'ingediend' );
	}
	return rest_ensure_response( array(
		'driver'      => get_the_title( $did ),
		'date'        => $rid ? get_post_meta( $rid, 'date', true ) : date( 'Y-m-d', current_time( 'timestamp' ) ), // phpcs:ignore WordPress.DateTime
		'route_token' => $rid ? get_post_meta( $rid, 'token', true ) : '',
		'stops'       => ekinese_fleet_compute_eta( $stops ),
		'shift'       => $shift ? array( 'open' => true, 'start' => get_post_meta( $shift, 'start', true ), 'km_start' => get_post_meta( $shift, 'km_start', true ) ) : array( 'open' => false ),
		'expenses'    => $exp_rows,
		'week_total'  => number_format_i18n( $week_total, 2 ),
		'categories'  => ekinese_expense_categories(),
		'ai'          => function_exists( 'ekinese_ai_enabled' ) && ekinese_ai_enabled(),
	) );
}

/** Dienst starten/eindigen + km (fahrtenbuch). */
function ekinese_fleet_shift( WP_REST_Request $r ) {
	$did = ekinese_fleet_auth( $r );
	if ( is_wp_error( $did ) ) {
		return $did;
	}
	$p     = $r->get_json_params();
	$act   = sanitize_key( $p['action'] ?? '' );
	$km    = (int) ( $p['km'] ?? 0 );
	$today = date( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime
	$shift = ekinese_fleet_open_shift( $did );
	if ( 'start' === $act ) {
		if ( $shift ) {
			return new WP_Error( 'open', 'Dienst is al gestart.', array( 'status' => 400 ) );
		}
		$id = wp_insert_post( array( 'post_type' => 'xg_shift', 'post_status' => 'publish', 'post_title' => 'Dienst ' . get_the_title( $did ) . ' ' . $today ) );
		update_post_meta( $id, 'driver', $did );
		update_post_meta( $id, 'date', $today );
		update_post_meta( $id, 'start', current_time( 'mysql' ) );
		update_post_meta( $id, 'km_start', $km );
		update_post_meta( $id, 'route', ekinese_fleet_today_route() );
		return rest_ensure_response( array( 'ok' => true ) );
	}
	if ( 'end' === $act && $shift ) {
		update_post_meta( $shift, 'end', current_time( 'mysql' ) );
		update_post_meta( $shift, 'km_end', $km );
		return rest_ensure_response( array( 'ok' => true ) );
	}
	return new WP_Error( 'noop', 'Geen actieve dienst.', array( 'status' => 400 ) );
}

/** GPS-ping (alleen tijdens dienst). */
function ekinese_fleet_gps( WP_REST_Request $r ) {
	$did = ekinese_fleet_auth( $r );
	if ( is_wp_error( $did ) ) {
		return $did;
	}
	$p   = $r->get_json_params();
	$gps = array( 'lat' => (float) ( $p['lat'] ?? 0 ), 'lng' => (float) ( $p['lng'] ?? 0 ), 'time' => current_time( 'mysql' ) );
	update_post_meta( $did, 'last_gps', $gps );
	$shift = ekinese_fleet_open_shift( $did );
	if ( $shift ) {
		$track = json_decode( (string) get_post_meta( $shift, 'gps_track', true ), true ) ?: array();
		$track[] = $gps;
		update_post_meta( $shift, 'gps_track', wp_json_encode( array_slice( $track, -500 ) ) );
	}
	return rest_ensure_response( array( 'ok' => true ) );
}

/** Hybride OCR: bon of ID → voorstel (geen opslag). */
function ekinese_fleet_ocr( WP_REST_Request $r ) {
	$did = ekinese_fleet_auth( $r );
	if ( is_wp_error( $did ) ) {
		return $did;
	}
	$p    = $r->get_json_params();
	$img  = (string) ( $p['image'] ?? '' );
	$mode = sanitize_key( $p['mode'] ?? 'receipt' );
	if ( ! preg_match( '#^data:(image/\w+);base64,(.+)$#s', $img, $m ) ) {
		return new WP_Error( 'img', 'Geen afbeelding.', array( 'status' => 400 ) );
	}
	if ( 'id' === $mode ) {
		$instr = 'Dit is een identiteitsbewijs. Geef ALLEEN JSON: {"name":"","birthdate":"YYYY-MM-DD","doc_type":"","doc_number":""}. Geen extra tekst.';
	} else {
		$cats  = implode( ', ', array_keys( ekinese_expense_categories() ) );
		$instr = "Dit is een bon/kassabon. Geef ALLEEN JSON: {\"amount\":0.00,\"vendor\":\"\",\"category\":\"\"}. Kies category uit: $cats. Geen extra tekst.";
	}
	$out = ekinese_ai_vision( $m[2], $m[1], $instr );
	return rest_ensure_response( array( 'ok' => true, 'suggestion' => ekinese_json_from( $out ) ) );
}

/** Onkostenpost opslaan (na bevestiging). */
function ekinese_fleet_expense( WP_REST_Request $r ) {
	$did = ekinese_fleet_auth( $r );
	if ( is_wp_error( $did ) ) {
		return $did;
	}
	$p      = $r->get_json_params();
	$amount = round( (float) ( $p['amount'] ?? 0 ), 2 );
	$cat    = sanitize_key( $p['category'] ?? 'overig' );
	if ( $amount <= 0 ) {
		return new WP_Error( 'amt', 'Vul een bedrag in.', array( 'status' => 400 ) );
	}
	$att = ekinese_fleet_store_image( (string) ( $p['image'] ?? '' ), 'bon' );
	$id  = wp_insert_post( array( 'post_type' => 'xg_expense', 'post_status' => 'publish', 'post_title' => 'Onkosten ' . $cat . ' € ' . number_format( $amount, 2 ) ) );
	update_post_meta( $id, 'driver', $did );
	update_post_meta( $id, 'date', date( 'Y-m-d', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime
	update_post_meta( $id, 'week', gmdate( 'oW' ) );
	update_post_meta( $id, 'amount', $amount );
	update_post_meta( $id, 'category', $cat );
	update_post_meta( $id, 'vendor', sanitize_text_field( $p['vendor'] ?? '' ) );
	update_post_meta( $id, 'note', sanitize_textarea_field( $p['note'] ?? '' ) );
	update_post_meta( $id, 'image', $att );
	update_post_meta( $id, 'status', 'ingediend' );
	return rest_ensure_response( array( 'ok' => true ) );
}

/** KYC per stop: handtekening + ID-scan + IBAN → gespiegeld naar de afspraak. */
function ekinese_fleet_kyc( WP_REST_Request $r ) {
	$did = ekinese_fleet_auth( $r );
	if ( is_wp_error( $did ) ) {
		return $did;
	}
	$p    = $r->get_json_params();
	$appt = (int) ( $p['appointment'] ?? 0 );
	if ( ! $appt || get_post_type( $appt ) !== 'xg_appointment' ) {
		return new WP_Error( 'appt', 'Onbekende afspraak.', array( 'status' => 400 ) );
	}
	$sig = ekinese_fleet_store_image( (string) ( $p['signature'] ?? '' ), 'sig' );
	$idi = ekinese_fleet_store_image( (string) ( $p['id_image'] ?? '' ), 'id' );
	if ( $sig ) {
		update_post_meta( $appt, 'kyc_signature', $sig );
	}
	if ( $idi ) {
		update_post_meta( $appt, 'kyc_id_image', $idi );
	}
	foreach ( array( 'iban' => 'iban', 'id_name' => 'kyc_name', 'id_number' => 'kyc_id_number', 'id_birthdate' => 'kyc_birthdate' ) as $src => $meta ) {
		if ( isset( $p[ $src ] ) ) {
			update_post_meta( $appt, $meta, sanitize_text_field( $p[ $src ] ) );
		}
	}
	update_post_meta( $appt, 'kyc_captured', current_time( 'mysql' ) );
	update_post_meta( $appt, 'kyc_by', $did );
	return rest_ensure_response( array( 'ok' => true ) );
}

/* =====================================================================
   DASHBOARD-PANEEL — live positie, ETA, onkosten
===================================================================== */
function ekinese_fleet_panel() {
	$drivers = get_posts( array( 'post_type' => 'xg_driver', 'numberposts' => -1, 'post_status' => 'publish' ) );
	echo '<h2 style="margin-top:24px">Chauffeurs — live</h2>';
	if ( ! $drivers ) {
		echo '<p style="color:#646970">Nog geen chauffeurs. Voeg er een toe onder XGOUD → Chauffeurs (geef een persoonlijke code).</p>';
	}
	$rid   = ekinese_fleet_today_route();
	$stops = $rid ? ( json_decode( (string) get_post_meta( $rid, 'stops', true ), true ) ?: array() ) : array();
	$stops = ekinese_fleet_compute_eta( $stops );

	echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">';
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h3 style="margin-top:0;font-size:14px">Posities &amp; dienst</h3>';
	foreach ( $drivers as $d ) {
		$gps   = get_post_meta( $d->ID, 'last_gps', true );
		$shift = ekinese_fleet_open_shift( $d->ID );
		$since = $shift ? human_time_diff( strtotime( get_post_meta( $shift, 'start', true ) ), current_time( 'timestamp' ) ) : '';
		echo '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1">';
		echo '<strong>' . esc_html( $d->post_title ) . '</strong> — ' . ( $shift ? '<span style="color:#1f9d55">in dienst (' . esc_html( $since ) . ')</span>' : '<span style="color:#8c8f94">offline</span>' );
		if ( is_array( $gps ) && ! empty( $gps['lat'] ) ) {
			echo '<br><span style="color:#646970;font-size:12px">Laatste positie: ' . esc_html( round( $gps['lat'], 4 ) . ', ' . round( $gps['lng'], 4 ) ) . ' · ' . esc_html( $gps['time'] ?? '' ) . ' <a href="https://www.google.com/maps?q=' . esc_attr( $gps['lat'] . ',' . $gps['lng'] ) . '" target="_blank">kaart</a></span>';
		}
		echo '</div>';
	}
	echo '</div>';

	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h3 style="margin-top:0;font-size:14px">Route vandaag — ETA</h3>';
	if ( ! $stops ) {
		echo '<p style="color:#8c8f94;margin:0">Geen route gepland.</p>';
	} else {
		echo '<ol style="margin:0;padding-left:18px">';
		foreach ( $stops as $s ) {
			$done = in_array( $s['status'] ?? '', array( 'picked_up', 'delivered' ), true );
			echo '<li style="padding:4px 0' . ( $done ? ';color:#8c8f94;text-decoration:line-through' : '' ) . '">'
				. '<strong>' . esc_html( $s['eta'] ?? ( $s['time'] ?? '—' ) ) . '</strong> · ' . esc_html( $s['name'] ?? '' ) . ' <span style="color:#646970">' . esc_html( $s['address'] ?? '' ) . ( isset( $s['leg_km'] ) ? ' (' . esc_html( $s['leg_km'] ) . ' km)' : '' ) . '</span></li>';
		}
		echo '</ol>';
	}
	echo '</div></div>';

	// Wekelijkse onkosten om uit te betalen.
	$week = gmdate( 'oW' );
	$exp  = get_posts( array( 'post_type' => 'xg_expense', 'numberposts' => -1, 'post_status' => 'publish', 'meta_query' => array( 'relation' => 'AND', array( 'key' => 'week', 'value' => $week ), array( 'key' => 'reimbursed', 'value' => '1', 'compare' => '!=' ) ) ) );
	$total = 0;
	foreach ( $exp as $e ) {
		$total += (float) get_post_meta( $e->ID, 'amount', true );
	}
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-top:18px"><h3 style="margin-top:0;font-size:14px">Onkosten deze week (' . esc_html( $week ) . ') — uit te betalen</h3>';
	echo '<p style="font-size:22px;font-weight:800;color:#AE1E1E;margin:0">€ ' . esc_html( number_format_i18n( $total, 2 ) ) . ' <span style="font-size:13px;font-weight:400;color:#646970">over ' . count( $exp ) . ' posten</span> · <a href="' . esc_url( admin_url( 'edit.php?post_type=xg_expense' ) ) . '">bekijken/afvinken</a></p></div>';
}

/* =====================================================================
   APP-PAGINA — block ekinese/driver-app gebruikt nu de fleet-app
===================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/driver-app' ) ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/driver-app.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-driver-app', get_theme_file_uri( 'assets/css/driver-app.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/driver-app.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-driver-app', get_theme_file_uri( 'assets/js/driver-app.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-driver-app', 'XGFleet', array( 'rest' => esc_url_raw( rest_url( 'ekinese/v1/fleet' ) ) ) );
	}
}, 20 );

/* Chauffeur-metabox: persoonlijke code. */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_driver_meta', __( 'Chauffeur', 'ekinese' ), function ( $post ) {
		wp_nonce_field( 'xg_driver_save', 'xg_driver_nonce' );
		foreach ( array( 'code' => 'Persoonlijke code (login)', 'email' => 'E-mail', 'phone' => 'Telefoon' ) as $k => $lbl ) {
			echo '<p><label>' . esc_html( $lbl ) . '<br><input type="text" name="xgd_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="widefat"></label></p>';
		}
		$gps = get_post_meta( $post->ID, 'last_gps', true );
		if ( is_array( $gps ) && ! empty( $gps['lat'] ) ) {
			echo '<p class="description">Laatste GPS: ' . esc_html( $gps['lat'] . ', ' . $gps['lng'] . ' (' . ( $gps['time'] ?? '' ) . ')</p>' );
		}
	}, 'xg_driver', 'side', 'high' );
} );
add_action( 'save_post_xg_driver', function ( $post_id ) {
	if ( ! isset( $_POST['xg_driver_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_driver_nonce'] ), 'xg_driver_save' ) ) {
		return;
	}
	foreach ( array( 'code', 'email', 'phone' ) as $k ) {
		if ( isset( $_POST[ 'xgd_' . $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ 'xgd_' . $k ] ) ) );
		}
	}
} );

/* ORS-key onder XGOUD → Analytics (hergebruik) of eigen veld via Metaalprijzen-stijl. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Routing (ORS)', 'ekinese' ), __( 'Routing (ORS)', 'ekinese' ), 'manage_options', 'xg-ors', function () {
		if ( isset( $_POST['xg_ors_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_ors_nonce'] ), 'xg_ors' ) ) {
			update_option( 'xg_ors_key', sanitize_text_field( wp_unslash( $_POST['xg_ors_key'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Routing — OpenRouteService</h1><p>Gratis API-key op openrouteservice.org. Hiermee berekenen wij echte rijtijden/afstanden voor de routeplanning en ETA. Zonder key gebruiken wij een schatting (hemelsbrede afstand).</p>';
		echo '<form method="post"><table class="form-table"><tr><th>ORS API-key</th><td><input type="text" name="xg_ors_key" value="' . esc_attr( get_option( 'xg_ors_key', '' ) ) . '" class="regular-text"></td></tr></table>';
		wp_nonce_field( 'xg_ors', 'xg_ors_nonce' );
		submit_button();
		echo '</form></div>';
	} );
} );
