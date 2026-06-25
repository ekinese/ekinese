<?php
/**
 * XGOUD gebruikersmeldingen — een meldingen-/activiteitencentrum per klant
 * (passwordless, op e-mailadres). Meldingen verschijnen in "Mijn XGOUD" met een
 * "nieuw"-markering; andere modules (veilingen, producten, …) maken meldingen via
 * ekinese_notify(). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT  xg_notification
===================================================================== */
function ekinese_register_notifications() {
	register_post_type( 'xg_notification', array(
		'labels'    => array( 'name' => __( 'Meldingen', 'ekinese' ), 'singular_name' => __( 'Melding', 'ekinese' ), 'menu_name' => __( 'Meldingen', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-bell',
		'supports'  => array( 'title', 'editor' ),
	) );
	foreach ( array( 'email', 'url', 'type', 'is_read' ) as $f ) {
		register_post_meta( 'xg_notification', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_notifications' );

/**
 * Maak een melding voor een klant.
 *
 * @param string $email   Ontvanger (e-mailadres).
 * @param string $title   Korte titel.
 * @param string $message Tekst.
 * @param string $url     Optionele link (bv. naar de veiling).
 * @param string $type    info|bid|outbid|won|invoice|charity (voor iconen later).
 * @return int|false      Post-ID of false.
 */
function ekinese_notify( $email, $title, $message, $url = '', $type = 'info' ) {
	$email = sanitize_email( $email );
	if ( ! $email || ! is_email( $email ) ) {
		return false;
	}
	$id = wp_insert_post( array(
		'post_type'    => 'xg_notification',
		'post_status'  => 'publish',
		'post_title'   => wp_strip_all_tags( $title ),
		'post_content' => wp_kses_post( $message ),
	) );
	if ( ! $id || is_wp_error( $id ) ) {
		return false;
	}
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'url', esc_url_raw( $url ) );
	update_post_meta( $id, 'type', sanitize_key( $type ) );
	update_post_meta( $id, 'is_read', '0' );
	return $id;
}

/** Meldingen van een klant (nieuwste eerst). */
function ekinese_user_notifications( $email, $limit = 30 ) {
	$posts = get_posts( array(
		'post_type'   => 'xg_notification',
		'post_status' => 'publish',
		'numberposts' => $limit,
		'meta_key'    => 'email',
		'meta_value'  => sanitize_email( $email ),
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );
	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'      => $p->ID,
			'title'   => $p->post_title,
			'message' => wp_strip_all_tags( $p->post_content ),
			'url'     => get_post_meta( $p->ID, 'url', true ),
			'type'    => get_post_meta( $p->ID, 'type', true ),
			'read'    => get_post_meta( $p->ID, 'is_read', true ) === '1',
			'date'    => get_the_date( 'j M Y H:i', $p ),
		);
	}
	return $out;
}

/** Aantal ongelezen meldingen. */
function ekinese_user_unread_count( $email ) {
	$ids = get_posts( array(
		'post_type'   => 'xg_notification',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'email', 'value' => sanitize_email( $email ) ),
			array( 'key' => 'is_read', 'value' => '1', 'compare' => '!=' ),
		),
	) );
	return count( $ids );
}

/* =====================================================================
   ACCOUNT-INTEGRATIE
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$data['notifications']        = ekinese_user_notifications( $email );
	$data['notifications_unread'] = ekinese_user_unread_count( $email );
	return $data;
}, 10, 2 );

/* =====================================================================
   REST – meldingen als gelezen markeren
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/account/notifications/read', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_notifications_mark_read',
	) );
} );

function ekinese_notifications_mark_read( WP_REST_Request $req ) {
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $req->get_param( 'token' ) ) : '';
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'unauthorized', 'Ongeldige sessie.', array( 'status' => 401 ) );
	}
	$ids = get_posts( array(
		'post_type'   => 'xg_notification',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => 'email',
		'meta_value'  => $email,
	) );
	foreach ( $ids as $id ) {
		update_post_meta( $id, 'is_read', '1' );
	}
	return rest_ensure_response( array( 'ok' => true ) );
}
