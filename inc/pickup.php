<?php
/**
 * XGOUD Trace & Pickup (xg_pickup).
 *
 * Volgsysteem voor ophaalservice/verzending: de klant volgt zijn zending via
 * een token, met een statustijdlijn (aangemeld → onderweg → ontvangen →
 * getaxeerd → uitbetaald). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stappen in de tijdlijn (volgorde = voortgang). */
function ekinese_pickup_steps() {
	return array(
		'registered' => __( 'Aangemeld', 'ekinese' ),
		'picked_up'  => __( 'Opgehaald', 'ekinese' ),
		'in_transit' => __( 'Onderweg', 'ekinese' ),
		'received'   => __( 'Ontvangen', 'ekinese' ),
		'appraised'  => __( 'Getaxeerd', 'ekinese' ),
		'paid'       => __( 'Uitbetaald', 'ekinese' ),
	);
}

function ekinese_register_pickup_cpt() {
	register_post_type( 'xg_pickup', array(
		'labels'    => array( 'name' => __( 'Zendingen', 'ekinese' ), 'singular_name' => __( 'Zending', 'ekinese' ), 'menu_name' => __( 'Zendingen', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-archive',
		'supports'  => array( 'title', 'editor' ),
	) );
}
add_action( 'init', 'ekinese_register_pickup_cpt' );

/** Referentie XG-ZD-JJJJMM-XXXX. */
function ekinese_pickup_reference() {
	return sprintf( 'XG-ZD-%s-%04d', gmdate( 'Ym' ), wp_rand( 0, 9999 ) );
}

/** Tijdlijn ophalen (array van [step,label,time]). */
function ekinese_pickup_timeline( $id ) {
	$raw   = get_post_meta( $id, 'timeline', true );
	$saved = $raw ? json_decode( $raw, true ) : array();
	return is_array( $saved ) ? $saved : array();
}

/** Stap toevoegen aan de tijdlijn (idempotent per stap). */
function ekinese_pickup_set_step( $id, $step ) {
	$steps = ekinese_pickup_steps();
	if ( ! isset( $steps[ $step ] ) ) {
		return;
	}
	$timeline = ekinese_pickup_timeline( $id );
	foreach ( $timeline as $t ) {
		if ( ( $t['step'] ?? '' ) === $step ) {
			return; // al gezet
		}
	}
	$timeline[] = array( 'step' => $step, 'label' => $steps[ $step ], 'time' => current_time( 'mysql' ) );
	update_post_meta( $id, 'timeline', wp_json_encode( $timeline ) );
	update_post_meta( $id, 'status', $step );

	// Klant op de hoogte houden.
	$email = get_post_meta( $id, 'email', true );
	if ( $email && is_email( $email ) ) {
		$ref   = get_post_meta( $id, 'reference', true );
		$token = get_post_meta( $id, 'token', true );
		wp_mail(
			$email,
			sprintf( __( 'Zending %s: %s', 'ekinese' ), $ref, $steps[ $step ] ),
			sprintf( "De status van uw zending %s is nu: %s.\n\nVolg uw zending: %s\n\nXGOUD", $ref, $steps[ $step ], home_url( '/trace/?token=' . $token ) )
		);
	}
}

/* =====================================================================
   REST – aanmaken + volgen
===================================================================== */
function ekinese_pickup_rest() {
	register_rest_route( 'ekinese/v1', '/pickup', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_pickup_create',
	) );
	register_rest_route( 'ekinese/v1', '/pickup/(?P<token>[a-zA-Z0-9]{20,40})', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_pickup_trace',
	) );
}
add_action( 'rest_api_init', 'ekinese_pickup_rest' );

function ekinese_pickup_create( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'pickup' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$desc  = sanitize_textarea_field( (string) $req->get_param( 'description' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid', __( 'Vul een geldig e-mailadres in.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$ref   = ekinese_pickup_reference();
	$token = wp_generate_password( 28, false, false );
	$id    = wp_insert_post( array(
		'post_type'    => 'xg_pickup',
		'post_status'  => 'publish',
		'post_title'   => $ref . ' – ' . ( $name ?: $email ),
		'post_content' => $desc,
	) );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', __( 'Opslaan mislukt.', 'ekinese' ), array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'reference', $ref );
	update_post_meta( $id, 'token', $token );
	update_post_meta( $id, 'name', $name );
	update_post_meta( $id, 'email', $email );
	ekinese_pickup_set_step( $id, 'registered' );

	return rest_ensure_response( array( 'ok' => true, 'reference' => $ref, 'token' => $token, 'trace' => home_url( '/trace/?token=' . $token ) ) );
}

function ekinese_pickup_trace( WP_REST_Request $req ) {
	$token = sanitize_text_field( (string) $req['token'] );
	$q     = get_posts( array( 'post_type' => 'xg_pickup', 'meta_key' => 'token', 'meta_value' => $token, 'numberposts' => 1 ) );
	if ( ! $q ) {
		return new WP_Error( 'notfound', __( 'Zending niet gevonden.', 'ekinese' ), array( 'status' => 404 ) );
	}
	$id = $q[0]->ID;
	return rest_ensure_response( array(
		'reference' => get_post_meta( $id, 'reference', true ),
		'status'    => get_post_meta( $id, 'status', true ),
		'steps'     => ekinese_pickup_steps(),
		'timeline'  => ekinese_pickup_timeline( $id ),
	) );
}

/* =====================================================================
   ADMIN – stap zetten via metabox
===================================================================== */
function ekinese_pickup_metabox() {
	add_meta_box( 'xg_pickup_meta', __( 'Zending & status', 'ekinese' ), 'ekinese_pickup_metabox_html', 'xg_pickup', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_pickup_metabox' );

function ekinese_pickup_metabox_html( $post ) {
	wp_nonce_field( 'xg_pickup_save', 'xg_pickup_nonce' );
	echo '<p><strong>Referentie:</strong> ' . esc_html( get_post_meta( $post->ID, 'reference', true ) ) . '</p>';
	echo '<p><strong>Klant:</strong> ' . esc_html( get_post_meta( $post->ID, 'name', true ) ) . ' (' . esc_html( get_post_meta( $post->ID, 'email', true ) ) . ')</p>';
	$current = get_post_meta( $post->ID, 'status', true );
	echo '<p><label><strong>Volgende status zetten</strong><br><select name="xg_pickup_step" style="width:100%"><option value="">—</option>';
	foreach ( ekinese_pickup_steps() as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $current, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></label></p><p class="description">Bij opslaan wordt de stap toegevoegd en de klant gemaild.</p>';
	$timeline = ekinese_pickup_timeline( $post->ID );
	if ( $timeline ) {
		echo '<ul style="margin-top:10px">';
		foreach ( $timeline as $t ) {
			echo '<li>' . esc_html( $t['label'] ) . ' — <small>' . esc_html( $t['time'] ) . '</small></li>';
		}
		echo '</ul>';
	}
}

function ekinese_pickup_save( $post_id ) {
	if ( ! isset( $_POST['xg_pickup_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_pickup_nonce'] ), 'xg_pickup_save' ) ) {
		return;
	}
	if ( ! empty( $_POST['xg_pickup_step'] ) ) {
		ekinese_pickup_set_step( $post_id, sanitize_key( $_POST['xg_pickup_step'] ) );
	}
}
add_action( 'save_post_xg_pickup', 'ekinese_pickup_save' );
