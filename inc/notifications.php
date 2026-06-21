<?php
/**
 * XGOUD Prijsalarmen / notificaties (xg_price_alert).
 *
 * Bezoeker stelt een alarm in: "mail mij als goud boven/onder € X komt".
 * Een cron vergelijkt de actuele spotprijs met de drempels en mailt bij een
 * match. Self-built, geen plugin. De spotbron is pluggable zodat de live-API
 * later moeiteloos kan worden aangesloten.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actuele spotprijs (€/g) voor een metaal.
 *
 * Leest uit optie xg_metals_spot (door de live-API/cron bij te werken) en valt
 * terug op de calculator-standaardwaarden. Filterbaar voor de API-fase.
 *
 * @param string $metal goud|zilver|platina|palladium
 * @return float
 */
function ekinese_metal_spot( $metal ) {
	$metal = sanitize_key( $metal );
	$opt   = get_option( 'xg_metals_spot', array() );
	if ( isset( $opt[ $metal ] ) ) {
		$spot = (float) $opt[ $metal ];
	} else {
		$fallback = array( 'goud' => 62.50, 'zilver' => 0.78, 'platina' => 28.90, 'palladium' => 30.10 );
		$spot     = $fallback[ $metal ] ?? 0.0;
	}
	/** Live spotprijs (API-fase haakt hier in). */
	return (float) apply_filters( 'ekinese_metal_spot', $spot, $metal );
}

/** CPT (intern, niet publiek). */
function ekinese_register_price_alert_cpt() {
	register_post_type( 'xg_price_alert', array(
		'labels'    => array( 'name' => __( 'Prijsalarmen', 'ekinese' ), 'singular_name' => __( 'Prijsalarm', 'ekinese' ), 'menu_name' => __( 'Prijsalarmen', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-bell',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_price_alert_cpt' );

/** Niet indexeren. */
add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular( 'xg_price_alert' ) ) {
		$r['noindex'] = true;
	}
	return $r;
} );

/* =====================================================================
   REST – alarm aanmaken + uitschrijven via token
===================================================================== */
function ekinese_price_alert_rest() {
	register_rest_route( 'ekinese/v1', '/price-alert', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_price_alert_create',
	) );
	register_rest_route( 'ekinese/v1', '/price-alert/(?P<token>[a-f0-9A-Z]{20,40})', array(
		'methods'             => 'DELETE',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_price_alert_delete',
	) );
}
add_action( 'rest_api_init', 'ekinese_price_alert_rest' );

function ekinese_price_alert_create( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'price_alert' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$email     = sanitize_email( (string) $req->get_param( 'email' ) );
	$metal     = sanitize_key( (string) $req->get_param( 'metal' ) );
	$direction = in_array( $req->get_param( 'direction' ), array( 'above', 'below' ), true ) ? $req->get_param( 'direction' ) : 'above';
	$target    = (float) $req->get_param( 'target' );

	if ( ! $email || ! is_email( $email ) || ! in_array( $metal, array( 'goud', 'zilver', 'platina', 'palladium' ), true ) || $target <= 0 ) {
		return new WP_Error( 'invalid', __( 'Controleer e-mail, metaal en doelprijs.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$token = wp_generate_password( 24, false, false );
	$id    = wp_insert_post( array(
		'post_type'   => 'xg_price_alert',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s %s € %.2f – %s', ucfirst( $metal ), 'above' === $direction ? '≥' : '≤', $target, $email ),
	) );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', __( 'Opslaan mislukt.', 'ekinese' ), array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'metal', $metal );
	update_post_meta( $id, 'direction', $direction );
	update_post_meta( $id, 'target', $target );
	update_post_meta( $id, 'token', $token );
	update_post_meta( $id, 'active', '1' );

	return rest_ensure_response( array( 'ok' => true, 'unsubscribe' => home_url( '/price-alert/?token=' . $token ) ) );
}

function ekinese_price_alert_delete( WP_REST_Request $req ) {
	$token = sanitize_text_field( (string) $req['token'] );
	$q     = get_posts( array( 'post_type' => 'xg_price_alert', 'meta_key' => 'token', 'meta_value' => $token, 'numberposts' => 1 ) );
	if ( ! $q ) {
		return new WP_Error( 'notfound', __( 'Alarm niet gevonden.', 'ekinese' ), array( 'status' => 404 ) );
	}
	wp_delete_post( $q[0]->ID, true );
	return rest_ensure_response( array( 'ok' => true ) );
}

/* =====================================================================
   CRON – alarmen controleren
===================================================================== */
function ekinese_price_alert_schedule() {
	if ( ! wp_next_scheduled( 'xg_price_alert_check' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'xg_price_alert_check' );
	}
}
add_action( 'init', 'ekinese_price_alert_schedule' );

function ekinese_price_alert_check() {
	$alerts = get_posts( array(
		'post_type'   => 'xg_price_alert',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array( array( 'key' => 'active', 'value' => '1' ) ),
	) );
	if ( ! $alerts ) {
		return;
	}
	$spots = array();
	foreach ( $alerts as $a ) {
		$metal = get_post_meta( $a->ID, 'metal', true );
		if ( ! isset( $spots[ $metal ] ) ) {
			$spots[ $metal ] = ekinese_metal_spot( $metal );
		}
		$spot      = $spots[ $metal ];
		$target    = (float) get_post_meta( $a->ID, 'target', true );
		$direction = get_post_meta( $a->ID, 'direction', true );
		$hit       = ( 'above' === $direction && $spot >= $target ) || ( 'below' === $direction && $spot <= $target );
		if ( ! $hit ) {
			continue;
		}
		$email = get_post_meta( $a->ID, 'email', true );
		$token = get_post_meta( $a->ID, 'token', true );
		if ( $email && is_email( $email ) ) {
			wp_mail(
				$email,
				sprintf( __( 'Prijsalarm: %s staat op € %.2f/g', 'ekinese' ), ucfirst( $metal ), $spot ),
				sprintf(
					"Goed nieuws!\n\nDe %s-prijs is € %.2f per gram en heeft uw doel (%s € %.2f) bereikt.\nNu verkopen: %s\n\nUitschrijven: %s\n\nXGOUD",
					$metal,
					$spot,
					'above' === $direction ? '≥' : '≤',
					$target,
					home_url( '/afspraak/' ),
					home_url( '/price-alert/?token=' . $token )
				)
			);
		}
		// Eénmalig: deactiveren na trigger.
		update_post_meta( $a->ID, 'active', '0' );
		update_post_meta( $a->ID, 'triggered', current_time( 'mysql' ) );
	}
}
add_action( 'xg_price_alert_check', 'ekinese_price_alert_check' );
