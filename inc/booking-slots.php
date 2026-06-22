<?php
/**
 * XGOUD live-afspraak-kalender (#24).
 *
 * Toont per kantoor de werkelijk beschikbare slots, berekend uit de open dagen
 * (ekinese_office_open_days, inc/scheduling.php) minus reeds bezette afspraken.
 * Boeken gaat via de bestaande /appointment-REST. Zo wordt "aanvraag" een echte
 * boeking. Blok ekinese/booking-slots + assets/js/booking.js. Self-built.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Slotconfiguratie (openingstijden + slotlengte), filterbaar. */
function ekinese_booking_config() {
	return apply_filters( 'ekinese_booking_config', array(
		'start'    => 9,   // 09:00
		'end'      => 17,  // laatste slot 16:30
		'step_min' => 30,  // minuten per slot
		'days'     => 14,  // hoeveel dagen vooruit tonen
		'per_slot' => 1,   // capaciteit per slot per kantoor
	) );
}

/** Map ekinese_office_open_days-codes (ma..zo) → PHP 'N' (1=ma..7=zo). */
function ekinese_booking_daycode_to_n() {
	return array( 'ma' => 1, 'di' => 2, 'wo' => 3, 'do' => 4, 'vr' => 5, 'za' => 6, 'zo' => 7 );
}

/**
 * Beschikbare slots voor een kantoor in de komende dagen.
 *
 * @param int $office_id
 * @return array Lijst [ { date(Y-m-d), label, times:[ 'HH:MM', ... ] } ]
 */
function ekinese_booking_slots( $office_id ) {
	$cfg      = ekinese_booking_config();
	$open     = function_exists( 'ekinese_office_open_days' ) ? ekinese_office_open_days( $office_id ) : array();
	$open_n   = array_map( function ( $c ) {
		$m = ekinese_booking_daycode_to_n();
		return $m[ $c ] ?? 0;
	}, $open );
	$open_n   = array_filter( $open_n );
	if ( ! $open_n ) {
		return array();
	}
	$taken = ekinese_booking_taken( $office_id );
	$out   = array();
	$tz    = wp_timezone();
	$now   = new DateTime( 'now', $tz );
	for ( $i = 1; $i <= (int) $cfg['days']; $i++ ) {
		$day = ( clone $now )->modify( "+$i day" );
		if ( ! in_array( (int) $day->format( 'N' ), $open_n, true ) ) {
			continue;
		}
		$date  = $day->format( 'Y-m-d' );
		$times = array();
		for ( $h = (int) $cfg['start']; $h < (int) $cfg['end']; $h++ ) {
			for ( $m = 0; $m < 60; $m += (int) $cfg['step_min'] ) {
				$hhmm = sprintf( '%02d:%02d', $h, $m );
				$key  = $date . ' ' . $hhmm;
				if ( ( $taken[ $key ] ?? 0 ) < (int) $cfg['per_slot'] ) {
					$times[] = $hhmm;
				}
			}
		}
		if ( $times ) {
			$out[] = array(
				'date'  => $date,
				'label' => ekinese_booking_daylabel( $day ),
				'times' => $times,
			);
		}
	}
	return $out;
}

/** Bezette slots (datum HH:MM => aantal) voor een kantoor. */
function ekinese_booking_taken( $office_id ) {
	$appts = get_posts( array(
		'post_type'   => 'xg_appointment',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array( array( 'key' => 'office_id', 'value' => (int) $office_id ) ),
	) );
	$taken = array();
	foreach ( $appts as $a ) {
		$d = get_post_meta( $a->ID, 'date', true );
		$t = get_post_meta( $a->ID, 'time', true );
		if ( $d && $t ) {
			$key           = $d . ' ' . substr( $t, 0, 5 );
			$taken[ $key ] = ( $taken[ $key ] ?? 0 ) + 1;
		}
	}
	return $taken;
}

/** Nederlandse dag-label (ma 24 jun). */
function ekinese_booking_daylabel( DateTime $d ) {
	$days   = array( 1 => 'ma', 2 => 'di', 3 => 'wo', 4 => 'do', 5 => 'vr', 6 => 'za', 7 => 'zo' );
	$months = array( 1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr', 5 => 'mei', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec' );
	return $days[ (int) $d->format( 'N' ) ] . ' ' . (int) $d->format( 'j' ) . ' ' . $months[ (int) $d->format( 'n' ) ];
}

/* =====================================================================
   REST – slots ophalen
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/slots', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_booking_slots_rest',
		'args'                => array( 'office' => array( 'sanitize_callback' => 'absint' ) ),
	) );
} );

function ekinese_booking_slots_rest( WP_REST_Request $req ) {
	$office = (int) $req->get_param( 'office' );
	if ( ! $office || get_post_type( $office ) !== 'xg_office' ) {
		return new WP_Error( 'office', __( 'Onbekend kantoor.', 'ekinese' ), array( 'status' => 400 ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'slots' => ekinese_booking_slots( $office ) ) );
}

/* =====================================================================
   Blok + assets
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/booking-slots', array( 'render_callback' => 'ekinese_render_booking' ) );
} );

/** Render de kalender (kantoorkeuze + slots). */
function ekinese_render_booking() {
	$offices = function_exists( 'ekinese_get_offices' ) ? ekinese_get_offices() : array();
	$list    = array();
	foreach ( $offices as $o ) {
		$list[] = array( 'id' => $o['id'], 'name' => $o['name'], 'city' => $o['city'] );
	}
	$data = wp_json_encode( array(
		'offices'   => $list,
		'restSlots' => esc_url_raw( rest_url( 'ekinese/v1/slots' ) ),
		'restBook'  => esc_url_raw( rest_url( 'ekinese/v1/appointment' ) ),
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	ob_start();
	echo '<section class="xg-booking"><div class="xg-container"><div class="xg-booking-app" data-booking="' . esc_attr( $data ) . '">';
	echo '<h3>Plan direct uw afspraak</h3>';
	echo '<p>Kies een kantoor en een beschikbaar tijdslot. U boekt meteen — geen wachten op bevestiging.</p>';
	echo '<div class="xg-booking-mount"><p class="xg-booking-loading">Beschikbaarheid laden…</p></div>';
	echo '</div></div></section>';
	return ob_get_clean();
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/booking-slots' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/booking.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-booking', get_theme_file_uri( 'assets/js/booking.js' ), array(), (string) filemtime( $js ), true );
	}
} );
