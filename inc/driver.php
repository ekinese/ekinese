<?php
/**
 * XGOUD Fahrer / pickup-tracking (PWA) + nachtelijke routeplanner.
 *
 * De pickup-medewerker gebruikt een web-app (PWA) op de telefoon — geen extra
 * apparaat nodig, een iPhone volstaat. Live-GPS, statusupdates, pickup-/delivery-
 * notes, handtekening en foto. Daarnaast een optionele AirTag-referentie aan het
 * pakket als extra diefstalbeveiliging/locatie van de wáár.
 *
 * Elke nacht om 00:00 plant een cron de route: alle afspraken van de volgende
 * dag die reizen vereisen (thuisbezoek/ophaalservice) worden per zone geclusterd
 * en geordend (nearest-neighbour vanaf het hoofdkantoor). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT's
===================================================================== */
function ekinese_register_driver() {
	// Dagroute met geordende stops.
	register_post_type( 'xg_route', array(
		'labels'    => array( 'name' => __( 'Routes', 'ekinese' ), 'singular_name' => __( 'Route', 'ekinese' ), 'menu_name' => __( 'Routes', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-location-alt',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_driver' );

/** Hoofdkantoor-coördinaten (start/eind van de route). */
function ekinese_hq_coords() {
	$hq = get_posts( array( 'post_type' => 'xg_office', 'numberposts' => 1, 'meta_key' => 'is_hq', 'meta_value' => '1', 'fields' => 'ids' ) );
	if ( $hq ) {
		$lat = (float) get_post_meta( $hq[0], 'lat', true );
		$lng = (float) get_post_meta( $hq[0], 'lng', true );
		if ( $lat && $lng ) {
			return array( $lat, $lng );
		}
	}
	return array( 51.4361, 5.4836 ); // Eindhoven fallback
}

/* =====================================================================
   NACHTELIJKE ROUTEPLANNER  (00:00)
===================================================================== */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_plan_routes' ) ) {
		// Eerstvolgende middernacht (lokale tijd) inplannen.
		$next = strtotime( 'tomorrow 00:00' );
		wp_schedule_event( $next, 'daily', 'xg_plan_routes' );
	}
} );

/**
 * Plan de route(s) voor de volgende dag.
 *
 * @param string $date Y-m-d (default: morgen).
 * @return int route-id of 0
 */
function ekinese_plan_routes( $date = '' ) {
	$date = $date ?: gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
	// Afspraken die reizen vereisen.
	$appts = get_posts( array(
		'post_type'   => 'xg_appointment',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'date', 'value' => $date ),
			array( 'key' => 'service', 'value' => array( 'home', 'thuisbezoek', 'pickup', 'ophaalservice' ), 'compare' => 'IN' ),
		),
	) );
	if ( ! $appts ) {
		return 0;
	}
	// Stops met coördinaten verzamelen.
	$stops = array();
	foreach ( $appts as $a ) {
		$lat = (float) get_post_meta( $a->ID, 'lat', true );
		$lng = (float) get_post_meta( $a->ID, 'lng', true );
		$stops[] = array(
			'appointment' => $a->ID,
			'name'        => get_post_meta( $a->ID, 'name', true ) ?: get_the_title( $a->ID ),
			'address'     => trim( get_post_meta( $a->ID, 'address', true ) . ' ' . get_post_meta( $a->ID, 'city', true ) ),
			'lat'         => $lat,
			'lng'         => $lng,
			'time'        => get_post_meta( $a->ID, 'time', true ),
			'service'     => get_post_meta( $a->ID, 'service', true ),
			'status'      => 'pending',
		);
	}
	// Nearest-neighbour-ordening vanaf het HQ (alleen voor stops met coördinaten).
	list( $hlat, $hlng ) = ekinese_hq_coords();
	$ordered = array();
	$cur     = array( $hlat, $hlng );
	$remain  = $stops;
	while ( $remain ) {
		$bi = 0; $bd = PHP_FLOAT_MAX;
		foreach ( $remain as $i => $s ) {
			$d = ( $s['lat'] && $s['lng'] && function_exists( 'ekinese_distance_km' ) )
				? ekinese_distance_km( $cur[0], $cur[1], $s['lat'], $s['lng'] )
				: 9999; // zonder coördinaten achteraan
			if ( $d < $bd ) { $bd = $d; $bi = $i; }
		}
		$next = $remain[ $bi ];
		$next['leg_km'] = ( $bd < 9999 ) ? round( $bd, 1 ) : null;
		$ordered[] = $next;
		if ( $next['lat'] && $next['lng'] ) {
			$cur = array( $next['lat'], $next['lng'] );
		}
		array_splice( $remain, $bi, 1 );
	}

	// Route opslaan (1 route/dag; later per zone/medewerker uit te splitsen).
	$existing = get_posts( array( 'post_type' => 'xg_route', 'numberposts' => 1, 'meta_key' => 'date', 'meta_value' => $date, 'fields' => 'ids' ) );
	$postarr  = array( 'post_type' => 'xg_route', 'post_status' => 'publish', 'post_title' => 'Route ' . $date );
	if ( $existing ) {
		$postarr['ID'] = $existing[0];
	}
	$rid = wp_insert_post( $postarr );
	update_post_meta( $rid, 'date', $date );
	update_post_meta( $rid, 'stops', wp_json_encode( $ordered ) );
	update_post_meta( $rid, 'token', get_post_meta( $rid, 'token', true ) ?: wp_generate_password( 20, false, false ) );
	return $rid;
}
add_action( 'xg_plan_routes', 'ekinese_plan_routes' );

/* =====================================================================
   DRIVER REST  – route ophalen, status/gps/notes
===================================================================== */
function ekinese_driver_rest() {
	$auth = function ( WP_REST_Request $r ) {
		$token = (string) $r->get_param( 'token' );
		$q     = $token ? get_posts( array( 'post_type' => 'xg_route', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'token', 'meta_value' => $token ) ) : array();
		return $q ? (int) $q[0] : 0;
	};
	register_rest_route( 'ekinese/v1', '/driver/route', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) use ( $auth ) {
			$id = $auth( $r );
			if ( ! $id ) {
				return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
			}
			return rest_ensure_response( array(
				'date'  => get_post_meta( $id, 'date', true ),
				'stops' => json_decode( (string) get_post_meta( $id, 'stops', true ), true ) ?: array(),
			) );
		},
	) );
	register_rest_route( 'ekinese/v1', '/driver/stop', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_driver_update_stop',
	) );
	register_rest_route( 'ekinese/v1', '/driver/gps', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) use ( $auth ) {
			$id = $auth( $r );
			if ( ! $id ) {
				return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
			}
			update_post_meta( $id, 'last_gps', array(
				'lat'  => (float) $r->get_param( 'lat' ),
				'lng'  => (float) $r->get_param( 'lng' ),
				'time' => current_time( 'mysql' ),
			) );
			return rest_ensure_response( array( 'ok' => true ) );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_driver_rest' );

function ekinese_driver_update_stop( WP_REST_Request $r ) {
	$token = (string) $r->get_param( 'token' );
	$q     = $token ? get_posts( array( 'post_type' => 'xg_route', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'token', 'meta_value' => $token ) ) : array();
	if ( ! $q ) {
		return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
	}
	$rid    = (int) $q[0];
	$idx    = (int) $r->get_param( 'index' );
	$status = sanitize_key( (string) $r->get_param( 'status' ) ); // arrived|picked_up|delivered|failed
	$note   = sanitize_textarea_field( (string) $r->get_param( 'note' ) );
	$stops  = json_decode( (string) get_post_meta( $rid, 'stops', true ), true ) ?: array();
	if ( ! isset( $stops[ $idx ] ) ) {
		return new WP_Error( 'badindex', 'stop', array( 'status' => 400 ) );
	}
	if ( $status ) {
		$stops[ $idx ]['status'] = $status;
	}
	if ( $note ) {
		$field = ( in_array( $status, array( 'delivered' ), true ) ) ? 'delivery_note' : 'pickup_note';
		$stops[ $idx ][ $field ] = $note;
	}
	$stops[ $idx ]['updated'] = current_time( 'mysql' );
	update_post_meta( $rid, 'stops', wp_json_encode( $stops ) );

	// Koppel terug naar de afspraak/zending (statusspiegeling).
	$appt = (int) ( $stops[ $idx ]['appointment'] ?? 0 );
	if ( $appt && function_exists( 'ekinese_pickup_set_step' ) ) {
		$map = array( 'picked_up' => 'picked_up', 'delivered' => 'received' );
		if ( isset( $map[ $status ] ) ) {
			// Indien er een gekoppelde zending bestaat, stap zetten.
			do_action( 'ekinese_driver_stop_update', $appt, $status, $note );
		}
	}
	return rest_ensure_response( array( 'ok' => true, 'stops' => $stops ) );
}

/* =====================================================================
   DRIVER-PWA  (block + manifest)
===================================================================== */
function ekinese_register_driver_block() {
	register_block_type( 'ekinese/driver-app', array( 'render_callback' => 'ekinese_render_driver_app' ) );
}
add_action( 'init', 'ekinese_register_driver_block' );

function ekinese_render_driver_app() {
	return '<div class="xg-driver" data-rest="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/driver' ) ) ) . '">'
		. '<div class="xg-driver-login"><h2>XGOUD Rit</h2><p>Voer uw routecode in.</p><form class="xg-driver-form"><input type="text" name="token" placeholder="Routecode" required><button class="xg-final-btn" type="submit">Start route</button></form></div>'
		. '<div class="xg-driver-route" hidden></div></div>';
}

/** Driver-assets + (optioneel) PWA-manifest op de driverpagina. */
function ekinese_driver_assets() {
	if ( ! is_singular() || ! has_block( 'ekinese/driver-app' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/driver.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-driver', get_theme_file_uri( 'assets/js/driver.js' ), array(), (string) filemtime( $js ), true );
	}
	add_action( 'wp_head', function () {
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="XGOUD Rit">' . "\n";
	} );
}
add_action( 'wp_enqueue_scripts', 'ekinese_driver_assets' );

/** Driverpagina niet indexeren. */
add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular() && has_block( 'ekinese/driver-app' ) ) {
		$r['noindex'] = true;
	}
	return $r;
} );

/* =====================================================================
   ADMIN – route bekijken + AirTag-referentie op zending
===================================================================== */
function ekinese_route_metabox() {
	add_meta_box( 'xg_route_meta', __( 'Routestops', 'ekinese' ), function ( $post ) {
		$stops = json_decode( (string) get_post_meta( $post->ID, 'stops', true ), true ) ?: array();
		$token = get_post_meta( $post->ID, 'token', true );
		echo '<p><strong>Routecode (voor de chauffeur):</strong> <code>' . esc_html( $token ) . '</code> &nbsp; <a href="' . esc_url( home_url( '/rit/?token=' . $token ) ) . '" target="_blank">Open driver-app</a></p>';
		if ( ! $stops ) {
			echo '<p>Geen stops.</p>'; return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Klant</th><th>Adres</th><th>Tijd</th><th>Afstand</th><th>Status</th></tr></thead><tbody>';
		foreach ( $stops as $i => $s ) {
			echo '<tr><td>' . esc_html( $i + 1 ) . '</td><td>' . esc_html( $s['name'] ?? '' ) . '</td><td>' . esc_html( $s['address'] ?? '' ) . '</td><td>' . esc_html( $s['time'] ?? '' ) . '</td><td>' . esc_html( isset( $s['leg_km'] ) ? $s['leg_km'] . ' km' : '—' ) . '</td><td>' . esc_html( $s['status'] ?? 'pending' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'xg_route', 'normal', 'high' );

	// AirTag-referentie op de zending.
	add_meta_box( 'xg_airtag', __( 'AirTag-referentie', 'ekinese' ), function ( $post ) {
		wp_nonce_field( 'xg_airtag_save', 'xg_airtag_nonce' );
		echo '<p><label>AirTag-ID / serienummer aan het pakket<br><input type="text" name="xg_airtag" value="' . esc_attr( get_post_meta( $post->ID, 'airtag', true ) ) . '" class="widefat"></label></p>';
		echo '<p class="description">Extra diefstalbeveiliging; locatie via Find My (geen API).</p>';
	}, 'xg_pickup', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'ekinese_route_metabox' );

function ekinese_airtag_save( $post_id ) {
	if ( isset( $_POST['xg_airtag_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_airtag_nonce'] ), 'xg_airtag_save' ) && isset( $_POST['xg_airtag'] ) ) {
		update_post_meta( $post_id, 'airtag', sanitize_text_field( wp_unslash( $_POST['xg_airtag'] ) ) );
	}
}
add_action( 'save_post_xg_pickup', 'ekinese_airtag_save' );

/** Handmatig (her)plannen. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=xg_route', __( 'Nu plannen', 'ekinese' ), __( 'Nu plannen', 'ekinese' ), 'manage_options', 'xg-plan-route', function () {
		if ( isset( $_POST['xg_pr_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_pr_nonce'] ), 'xg_pr' ) ) {
			$d   = sanitize_text_field( wp_unslash( $_POST['xg_pr_date'] ?? '' ) );
			$rid = ekinese_plan_routes( $d ?: '' );
			echo '<div class="notice notice-success"><p>' . ( $rid ? esc_html__( 'Route gepland.', 'ekinese' ) : esc_html__( 'Geen reisafspraken gevonden.', 'ekinese' ) ) . '</p></div>';
		}
		echo '<div class="wrap"><h1>Route plannen</h1><form method="post"><p>Datum (leeg = morgen): <input type="date" name="xg_pr_date"></p>';
		wp_nonce_field( 'xg_pr', 'xg_pr_nonce' );
		submit_button( 'Plan route' );
		echo '</form></div>';
	} );
} );
