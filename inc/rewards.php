<?php
/**
 * XGOUD Reward & referral – puntensysteem.
 *
 * Klanten sparen punten:
 *   - afgeronde deal (afspraak → afgerond/uitbetaald)
 *   - delen op social media
 *   - een referral die een account aanmaakt
 * Punten zijn de toegang tot de maandelijkse loterij (inc/lottery.php) en geven
 * een hogere trouwbonus. Self-built, e-mailgebaseerd (geen WP-users), geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Puntwaarden (filterbaar/aanpasbaar). */
function ekinese_reward_rules() {
	return apply_filters( 'ekinese_reward_rules', array(
		'deal'            => 100, // afgeronde verkoop
		'deal_per_100eur' => 5,   // + per €100 uitbetaling
		'share'           => 10,  // per share (max 1×/platform/dag)
		'referral'        => 50,  // referrer bij aanmelding referral
		'welcome'         => 25,  // nieuwe referral zelf
	) );
}

/** Ledger-CPT. */
function ekinese_register_reward_cpt() {
	register_post_type( 'xg_reward', array(
		'labels'    => array( 'name' => __( 'Punten', 'ekinese' ), 'singular_name' => __( 'Puntenboeking', 'ekinese' ), 'menu_name' => __( 'Punten', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-star-filled',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_reward_cpt' );

/**
 * Punten toekennen (ledger-boeking).
 *
 * @param string $email
 * @param int    $points
 * @param string $reason
 * @param string $ref     vrije referentie (bv. afspraak-id, platform).
 */
function ekinese_award_points( $email, $points, $reason, $ref = '' ) {
	$email  = sanitize_email( $email );
	$points = (int) $points;
	if ( ! $email || ! is_email( $email ) || 0 === $points ) {
		return;
	}
	$id = wp_insert_post( array(
		'post_type'   => 'xg_reward',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%+d · %s · %s', $points, $reason, $email ),
	) );
	if ( is_wp_error( $id ) ) {
		return;
	}
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'points', $points );
	update_post_meta( $id, 'reason', sanitize_text_field( $reason ) );
	update_post_meta( $id, 'ref', sanitize_text_field( $ref ) );
	wp_cache_delete( 'pts_' . md5( $email ), 'xg' );
}

/** Puntensaldo van een e-mailadres (gecachet). */
function ekinese_points_balance( $email ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return 0;
	}
	$ck     = 'pts_' . md5( $email );
	$cached = wp_cache_get( $ck, 'xg' );
	if ( false !== $cached ) {
		return (int) $cached;
	}
	$q = get_posts( array(
		'post_type'   => 'xg_reward',
		'numberposts' => -1,
		'post_status' => 'publish',
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'email', 'value' => $email ) ),
	) );
	$sum = 0;
	foreach ( $q as $id ) {
		$sum += (int) get_post_meta( $id, 'points', true );
	}
	wp_cache_set( $ck, $sum, 'xg' );
	return $sum;
}

/* =====================================================================
   REFERRAL – code ↔ e-mail
===================================================================== */
function ekinese_referral_code( $email ) {
	$email = strtolower( sanitize_email( $email ) );
	$code  = strtoupper( substr( hash_hmac( 'sha1', $email, wp_salt() ), 0, 8 ) );
	$map   = get_option( 'xg_referral_map', array() );
	if ( ! isset( $map[ $code ] ) ) {
		$map[ $code ] = $email;
		update_option( 'xg_referral_map', $map, false );
	}
	return $code;
}

function ekinese_referral_email( $code ) {
	$map = get_option( 'xg_referral_map', array() );
	return $map[ strtoupper( sanitize_text_field( $code ) ) ] ?? '';
}

/* =====================================================================
   AUTOMATISCHE TOEKENNING – afgeronde afspraak
===================================================================== */
function ekinese_reward_on_appointment( $post_id ) {
	if ( get_post_type( $post_id ) !== 'xg_appointment' ) {
		return;
	}
	$status = get_post_meta( $post_id, 'status', true );
	if ( ! in_array( $status, array( 'completed', 'paid', 'afgerond', 'uitbetaald' ), true ) ) {
		return;
	}
	if ( get_post_meta( $post_id, '_xg_rewarded', true ) ) {
		return; // eenmalig
	}
	$email = get_post_meta( $post_id, 'email', true );
	if ( ! $email ) {
		return;
	}
	$rules  = ekinese_reward_rules();
	$payout = (float) get_post_meta( $post_id, 'payout', true );
	$points = $rules['deal'] + (int) floor( $payout / 100 ) * $rules['deal_per_100eur'];
	ekinese_award_points( $email, $points, 'deal', 'appt#' . $post_id );
	update_post_meta( $post_id, '_xg_rewarded', '1' );
}
add_action( 'save_post_xg_appointment', 'ekinese_reward_on_appointment', 20 );

/* =====================================================================
   REST – share + referral-aanmelding
===================================================================== */
function ekinese_rewards_rest() {
	register_rest_route( 'ekinese/v1', '/reward/share', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_reward_share',
	) );
	register_rest_route( 'ekinese/v1', '/reward/referral', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_reward_referral',
	) );
}
add_action( 'rest_api_init', 'ekinese_rewards_rest' );

function ekinese_reward_share( WP_REST_Request $req ) {
	$email    = sanitize_email( (string) $req->get_param( 'email' ) );
	$platform = sanitize_key( (string) $req->get_param( 'platform' ) );
	if ( ! $email || ! is_email( $email ) || ! $platform ) {
		return new WP_Error( 'invalid', __( 'Ongeldige aanvraag.', 'ekinese' ), array( 'status' => 400 ) );
	}
	// Max 1× per platform per dag (anti-misbruik).
	$flag = 'xg_share_' . md5( $email . $platform . gmdate( 'Ymd' ) );
	if ( get_transient( $flag ) ) {
		return rest_ensure_response( array( 'ok' => true, 'awarded' => 0, 'balance' => ekinese_points_balance( $email ) ) );
	}
	set_transient( $flag, 1, DAY_IN_SECONDS );
	$pts = ekinese_reward_rules()['share'];
	ekinese_award_points( $email, $pts, 'share', $platform );
	return rest_ensure_response( array( 'ok' => true, 'awarded' => $pts, 'balance' => ekinese_points_balance( $email ) ) );
}

function ekinese_reward_referral( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'referral' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$code  = sanitize_text_field( (string) $req->get_param( 'ref' ) );
	if ( ! $email || ! is_email( $email ) || ! $code ) {
		return new WP_Error( 'invalid', __( 'Ongeldige aanvraag.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$referrer = ekinese_referral_email( $code );
	if ( ! $referrer || strtolower( $referrer ) === strtolower( $email ) ) {
		return new WP_Error( 'badref', __( 'Ongeldige referral.', 'ekinese' ), array( 'status' => 400 ) );
	}
	// Eenmalig per nieuwe gebruiker.
	$flag = 'xg_ref_' . md5( strtolower( $email ) );
	if ( get_option( $flag ) ) {
		return rest_ensure_response( array( 'ok' => true, 'awarded' => 0 ) );
	}
	update_option( $flag, $referrer, false );
	$rules = ekinese_reward_rules();
	ekinese_award_points( $referrer, $rules['referral'], 'referral', $email );
	ekinese_award_points( $email, $rules['welcome'], 'welcome', $code );
	return rest_ensure_response( array( 'ok' => true, 'awarded' => $rules['welcome'], 'balance' => ekinese_points_balance( $email ) ) );
}

/* =====================================================================
   ACCOUNT-INTEGRATIE – punten + referral in /account/data
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$data['points']        = ekinese_points_balance( $email );
	$data['referral_code'] = ekinese_referral_code( $email );
	$data['referral_url']  = add_query_arg( 'ref', ekinese_referral_code( $email ), home_url( '/' ) );
	return $data;
}, 10, 2 );
