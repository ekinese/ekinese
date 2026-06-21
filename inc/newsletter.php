<?php
/**
 * XGOUD Newsletter.
 *
 * - CPT xg_subscriber: E-Mail + Stad + Bron-URL + Datum + Taal (wer/wann/wo).
 * - REST: aanmelden vanaf de banner.
 * - Admin: lijst + bulk-verzending (onderwerp/bericht → wp_mail).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_register_newsletter() {
	register_post_type(
		'xg_subscriber',
		array(
			'labels'    => array(
				'name'          => __( 'Nieuwsbrief', 'ekinese' ),
				'singular_name' => __( 'Abonnee', 'ekinese' ),
				'menu_name'     => __( 'Nieuwsbrief', 'ekinese' ),
			),
			'public'    => false,
			'show_ui'   => true,
			'menu_icon' => 'dashicons-email-alt',
			'supports'  => array( 'title' ),
		)
	);
	foreach ( array( 'city', 'source', 'lang', 'status' ) as $k ) {
		register_post_meta( 'xg_subscriber', $k, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_newsletter' );

/* REST: aanmelden */
function ekinese_register_newsletter_rest() {
	register_rest_route( 'ekinese/v1', '/newsletter', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_rest_newsletter',
	) );
}
add_action( 'rest_api_init', 'ekinese_register_newsletter_rest' );

function ekinese_rest_newsletter( WP_REST_Request $req ) {
	$d     = $req->get_json_params();
	$email = sanitize_email( $d['email'] ?? '' );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'xg_email', 'Ongeldig e-mailadres', array( 'status' => 400 ) );
	}

	// Dedupe.
	$exists = get_posts( array( 'post_type' => 'xg_subscriber', 'title' => $email, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $exists ) {
		return array( 'ok' => true, 'dupe' => true );
	}

	$id = wp_insert_post( array(
		'post_type'   => 'xg_subscriber',
		'post_status' => 'publish',
		'post_title'  => $email,
	) );
	update_post_meta( $id, 'city', sanitize_text_field( $d['city'] ?? '' ) );
	update_post_meta( $id, 'source', esc_url_raw( $d['source'] ?? '' ) );
	update_post_meta( $id, 'lang', sanitize_text_field( $d['lang'] ?? 'nl' ) );
	update_post_meta( $id, 'status', 'subscribed' );

	return array( 'ok' => true );
}

/* Assets + banner-data */
function ekinese_enqueue_newsletter_assets() {
	$css = get_theme_file_path( 'assets/css/newsletter.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-newsletter', get_theme_file_uri( 'assets/css/newsletter.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/newsletter.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-newsletter', get_theme_file_uri( 'assets/js/newsletter.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-newsletter', 'XG_NEWSLETTER', array( 'rest' => esc_url_raw( rest_url( 'ekinese/v1/newsletter' ) ) ) );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_newsletter_assets' );

/* Admin: kolommen */
function ekinese_subscriber_columns( $cols ) {
	return array(
		'cb'        => $cols['cb'] ?? '',
		'title'     => __( 'E-mail', 'ekinese' ),
		'xg_city'   => __( 'Stad', 'ekinese' ),
		'xg_source' => __( 'Bron', 'ekinese' ),
		'date'      => __( 'Aangemeld', 'ekinese' ),
	);
}
add_filter( 'manage_xg_subscriber_posts_columns', 'ekinese_subscriber_columns' );

function ekinese_subscriber_column( $col, $id ) {
	if ( 'xg_city' === $col ) {
		echo esc_html( get_post_meta( $id, 'city', true ) ?: '—' );
	} elseif ( 'xg_source' === $col ) {
		$u = get_post_meta( $id, 'source', true );
		echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank">' . esc_html( wp_parse_url( $u, PHP_URL_PATH ) ?: $u ) . '</a>' : '—';
	}
}
add_action( 'manage_xg_subscriber_posts_custom_column', 'ekinese_subscriber_column', 10, 2 );

/* Admin: bulk-verzending */
function ekinese_newsletter_send_menu() {
	add_submenu_page( 'edit.php?post_type=xg_subscriber', __( 'Versturen', 'ekinese' ), __( 'Versturen', 'ekinese' ), 'manage_options', 'xg-newsletter-send', 'ekinese_newsletter_send_page' );
}
add_action( 'admin_menu', 'ekinese_newsletter_send_menu' );

function ekinese_newsletter_send_page() {
	if ( isset( $_POST['xg_nl_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_nl_nonce'] ), 'xg_nl' ) ) {
		$subject = sanitize_text_field( wp_unslash( $_POST['xg_nl_subject'] ?? '' ) );
		$body    = wp_kses_post( wp_unslash( $_POST['xg_nl_body'] ?? '' ) );
		$subs    = get_posts( array( 'post_type' => 'xg_subscriber', 'numberposts' => -1, 'fields' => 'all' ) );
		$sent    = 0;
		foreach ( $subs as $s ) {
			if ( 'subscribed' === get_post_meta( $s->ID, 'status', true ) && is_email( $s->post_title ) ) {
				wp_mail( $s->post_title, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
				$sent++;
			}
		}
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Verzonden naar %d abonnees.', $sent ) ) . '</p></div>';
	}
	$count = wp_count_posts( 'xg_subscriber' );
	echo '<div class="wrap"><h1>Nieuwsbrief versturen</h1>';
	echo '<p>' . esc_html( sprintf( '%d abonnees.', (int) ( $count->publish ?? 0 ) ) ) . '</p>';
	echo '<form method="post"><table class="form-table"><tr><th>Onderwerp</th><td><input type="text" name="xg_nl_subject" class="regular-text"></td></tr>';
	echo '<tr><th>Bericht (HTML)</th><td><textarea name="xg_nl_body" rows="10" class="large-text"></textarea></td></tr></table>';
	wp_nonce_field( 'xg_nl', 'xg_nl_nonce' );
	submit_button( __( 'Versturen', 'ekinese' ) );
	echo '</form></div>';
}
