<?php
/**
 * XGOUD Social-media-beheer.
 *
 * Vanuit het backend posts/reels opstellen, plannen en (bij gekoppelde API's)
 * publiceren naar Instagram, LinkedIn, Facebook, Google Business & Yelp.
 * Plus een bot die vragen/reviews/chats beantwoordt op basis van de site-data.
 *
 * API-sleutels staan UITSLUITEND in de WP-opties (admin), nooit in de repo. De
 * publicatie-koppeling is een scaffold (queue + status); zodra de sleutels en
 * endpoints er zijn, publiceert ekinese_social_publish() echt.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ondersteunde platforms. */
function ekinese_social_platforms() {
	return array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'linkedin'  => 'LinkedIn',
		'google'    => 'Google Business',
		'yelp'      => 'Yelp',
		'x'         => 'X (Twitter)',
	);
}

/** Post-CPT. */
function ekinese_register_social() {
	register_post_type( 'xg_social_post', array(
		'labels'    => array( 'name' => __( 'Social posts', 'ekinese' ), 'singular_name' => __( 'Social post', 'ekinese' ), 'menu_name' => __( 'Social media', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-share',
		'supports'  => array( 'title', 'editor', 'thumbnail' ),
	) );
}
add_action( 'init', 'ekinese_register_social' );

/* ---- Compose-metabox ---- */
function ekinese_social_metabox() {
	add_meta_box( 'xg_social_meta', __( 'Publicatie', 'ekinese' ), 'ekinese_social_metabox_html', 'xg_social_post', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_social_metabox' );

function ekinese_social_metabox_html( $post ) {
	wp_nonce_field( 'xg_social_save', 'xg_social_nonce' );
	$sel = (array) get_post_meta( $post->ID, 'platforms', true );
	echo '<p><strong>' . esc_html__( 'Platforms', 'ekinese' ) . '</strong></p>';
	foreach ( ekinese_social_platforms() as $k => $lbl ) {
		echo '<label style="display:block"><input type="checkbox" name="xgs_platforms[]" value="' . esc_attr( $k ) . '"' . ( in_array( $k, $sel, true ) ? ' checked' : '' ) . '> ' . esc_html( $lbl ) . '</label>';
	}
	echo '<p><label><strong>' . esc_html__( 'Plannen op', 'ekinese' ) . '</strong><br><input type="datetime-local" name="xgs_schedule" value="' . esc_attr( get_post_meta( $post->ID, 'schedule', true ) ) . '"></label></p>';
	$status = get_post_meta( $post->ID, 'pub_status', true ) ?: 'draft';
	echo '<p><strong>' . esc_html__( 'Status', 'ekinese' ) . ':</strong> ' . esc_html( $status ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Bij gekoppelde API-sleutels wordt op het geplande moment automatisch gepubliceerd.', 'ekinese' ) . '</p>';
}

function ekinese_social_save( $post_id ) {
	if ( ! isset( $_POST['xg_social_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_social_nonce'] ), 'xg_social_save' ) ) {
		return;
	}
	$plats = isset( $_POST['xgs_platforms'] ) ? array_map( 'sanitize_key', (array) $_POST['xgs_platforms'] ) : array();
	update_post_meta( $post_id, 'platforms', $plats );
	if ( isset( $_POST['xgs_schedule'] ) ) {
		update_post_meta( $post_id, 'schedule', sanitize_text_field( wp_unslash( $_POST['xgs_schedule'] ) ) );
	}
	if ( ! get_post_meta( $post_id, 'pub_status', true ) ) {
		update_post_meta( $post_id, 'pub_status', 'scheduled' );
	}
}
add_action( 'save_post_xg_social_post', 'ekinese_social_save' );

/**
 * Publiceren naar een platform (scaffold — vereist API-koppeling).
 *
 * @return true|WP_Error
 */
function ekinese_social_publish( $post_id, $platform ) {
	$token = get_option( 'xg_social_' . $platform . '_token' );
	if ( ! $token ) {
		return new WP_Error( 'nokey', sprintf( 'Geen API-sleutel voor %s.', $platform ) );
	}
	/**
	 * Echte publicatie-implementatie haakt hier in (per platform endpoint).
	 *
	 * @param int    $post_id
	 * @param string $platform
	 * @param string $token
	 */
	return apply_filters( 'ekinese_social_publish', new WP_Error( 'todo', 'Publicatie-endpoint nog niet gekoppeld.' ), $post_id, $platform, $token );
}

/* =====================================================================
   BOT – antwoorden op vragen/reviews op basis van site-data
===================================================================== */
/**
 * Stel een antwoord op voor een binnenkomend bericht (review/vraag/chat).
 * Hergebruikt de site-bot (ekinese_bot_reply) zodat antwoorden consistent zijn
 * met de gegevens op de pagina's.
 *
 * @return string
 */
function ekinese_social_answer( $message, $context = 'social' ) {
	if ( function_exists( 'ekinese_bot_reply' ) ) {
		$r = ekinese_bot_reply( $message, $context );
		return $r['reply'] ?? '';
	}
	return __( 'Bedankt voor uw bericht! Een medewerker reageert zo snel mogelijk.', 'ekinese' );
}

/** REST: concept-antwoord ophalen (admin-only). */
function ekinese_social_rest() {
	register_rest_route( 'ekinese/v1', '/social/answer', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( WP_REST_Request $r ) {
			return rest_ensure_response( array( 'reply' => ekinese_social_answer( (string) $r->get_param( 'message' ) ) ) );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_social_rest' );

/* =====================================================================
   ADMIN – API-sleutels
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=xg_social_post', __( 'API-sleutels', 'ekinese' ), __( 'API-sleutels', 'ekinese' ), 'manage_options', 'xg-social-keys', function () {
		if ( isset( $_POST['xg_sk_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_sk_nonce'] ), 'xg_sk' ) ) {
			foreach ( array_keys( ekinese_social_platforms() ) as $p ) {
				if ( isset( $_POST[ 'xg_social_' . $p ] ) ) {
					update_option( 'xg_social_' . $p . '_token', sanitize_text_field( wp_unslash( $_POST[ 'xg_social_' . $p ] ) ) );
				}
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Social media — API-sleutels</h1><p>Sleutels staan alleen hier (database), nooit in de themacode.</p><form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_sk', 'xg_sk_nonce' );
		foreach ( ekinese_social_platforms() as $p => $lbl ) {
			echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="password" name="xg_social_' . esc_attr( $p ) . '" value="' . esc_attr( get_option( 'xg_social_' . $p . '_token', '' ) ) . '" class="regular-text"></td></tr>';
		}
		submit_button();
		echo '</table></form></div>';
	} );
} );
