<?php
/**
 * XGOUD Social login – Google, Apple, Facebook.
 *
 * Koppelt OAuth-login aan het wachtwoordloze account (inc/account.php): na een
 * succesvolle login krijgt de bezoeker een ondertekend account-token en landt op
 * "Mijn XGOUD". Sleutels staan UITSLUITEND in de WP-opties (admin), nooit in de
 * repo. Google & Facebook volledig (OAuth2 authorization code); Apple voorbereid
 * (vereist team-id/key-id + .p8-sleutel voor de client-secret-JWT).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_oauth_providers() {
	return array(
		'google'   => array( 'label' => 'Google', 'auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token', 'scope' => 'openid email profile', 'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo' ),
		'facebook' => array( 'label' => 'Facebook', 'auth' => 'https://www.facebook.com/v19.0/dialog/oauth', 'token' => 'https://graph.facebook.com/v19.0/oauth/access_token', 'scope' => 'email', 'userinfo' => 'https://graph.facebook.com/me?fields=email,name' ),
		'apple'    => array( 'label' => 'Apple', 'auth' => 'https://appleid.apple.com/auth/authorize', 'token' => 'https://appleid.apple.com/auth/token', 'scope' => 'email name', 'userinfo' => '' ),
	);
}

/** Actieve providers (sleutels ingevuld). */
function ekinese_oauth_active() {
	$out = array();
	foreach ( ekinese_oauth_providers() as $k => $p ) {
		if ( get_option( 'xg_oauth_' . $k . '_id' ) ) {
			$out[ $k ] = $p;
		}
	}
	return $out;
}

function ekinese_oauth_redirect_uri( $provider ) {
	return home_url( '/xg-oauth/' . $provider . '/callback/' );
}

/* ---- Routing ---- */
add_action( 'init', function () {
	add_rewrite_rule( '^xg-oauth/([a-z]+)(/callback)?/?$', 'index.php?xg_oauth=$matches[1]&xg_oauth_cb=$matches[2]', 'top' );
} );
add_filter( 'query_vars', function ( $v ) { $v[] = 'xg_oauth'; $v[] = 'xg_oauth_cb'; return $v; } );

add_action( 'template_redirect', function () {
	$provider = get_query_var( 'xg_oauth' );
	if ( ! $provider ) {
		return;
	}
	$providers = ekinese_oauth_providers();
	if ( ! isset( $providers[ $provider ] ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	if ( get_query_var( 'xg_oauth_cb' ) ) {
		ekinese_oauth_callback( $provider, $providers[ $provider ] );
	} else {
		ekinese_oauth_start( $provider, $providers[ $provider ] );
	}
	exit;
} );

/** Stap 1: doorsturen naar de provider. */
function ekinese_oauth_start( $provider, $cfg ) {
	$id = get_option( 'xg_oauth_' . $provider . '_id' );
	if ( ! $id ) {
		wp_safe_redirect( home_url( '/mijn-xgoud/' ) );
		exit;
	}
	$state = wp_generate_password( 24, false, false );
	set_transient( 'xg_oauth_state_' . $state, $provider, 600 );
	$args = array(
		'client_id'     => $id,
		'redirect_uri'  => ekinese_oauth_redirect_uri( $provider ),
		'response_type' => 'code',
		'scope'         => $cfg['scope'],
		'state'         => $state,
	);
	if ( 'apple' === $provider ) {
		$args['response_mode'] = 'form_post';
	}
	wp_redirect( $cfg['auth'] . '?' . http_build_query( $args ) ); // phpcs:ignore
	exit;
}

/** Stap 2: code inwisselen → e-mail → account-token. */
function ekinese_oauth_callback( $provider, $cfg ) {
	$code  = isset( $_REQUEST['code'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['code'] ) ) : '';
	$state = isset( $_REQUEST['state'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['state'] ) ) : '';
	if ( ! $code || get_transient( 'xg_oauth_state_' . $state ) !== $provider ) {
		wp_safe_redirect( home_url( '/mijn-xgoud/?error=oauth' ) );
		exit;
	}
	delete_transient( 'xg_oauth_state_' . $state );

	$secret = ( 'apple' === $provider ) ? ekinese_apple_client_secret() : get_option( 'xg_oauth_' . $provider . '_secret' );
	$res = wp_remote_post( $cfg['token'], array(
		'timeout' => 12,
		'body'    => array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => get_option( 'xg_oauth_' . $provider . '_id' ),
			'client_secret' => $secret,
			'redirect_uri'  => ekinese_oauth_redirect_uri( $provider ),
		),
	) );
	if ( is_wp_error( $res ) ) {
		wp_safe_redirect( home_url( '/mijn-xgoud/?error=oauth' ) );
		exit;
	}
	$body  = json_decode( wp_remote_retrieve_body( $res ), true );
	$email = '';

	// E-mail uit id_token (OIDC: Google/Apple) of via userinfo (Facebook).
	if ( ! empty( $body['id_token'] ) ) {
		$email = ekinese_jwt_email( $body['id_token'] );
	}
	if ( ! $email && ! empty( $body['access_token'] ) && $cfg['userinfo'] ) {
		$ui = wp_remote_get( $cfg['userinfo'], array( 'timeout' => 12, 'headers' => array( 'Authorization' => 'Bearer ' . $body['access_token'] ) ) );
		if ( ! is_wp_error( $ui ) ) {
			$u     = json_decode( wp_remote_retrieve_body( $ui ), true );
			$email = sanitize_email( $u['email'] ?? '' );
		}
	}
	if ( ! $email || ! is_email( $email ) ) {
		wp_safe_redirect( home_url( '/mijn-xgoud/?error=noemail' ) );
		exit;
	}

	// Inloggen via account-token (wachtwoordloos). DOB/leeftijd wordt in het
	// profiel afgerond (KYC, 18+).
	$token = function_exists( 'ekinese_account_make_token' ) ? ekinese_account_make_token( $email ) : '';
	update_option( 'xg_oauth_seen_' . md5( strtolower( $email ) ), $provider, false );
	wp_safe_redirect( home_url( '/mijn-xgoud/?token=' . rawurlencode( $token ) ) );
	exit;
}

/** E-mail uit een (ongeverifieerde) JWT-payload halen. */
function ekinese_jwt_email( $jwt ) {
	$parts = explode( '.', $jwt );
	if ( count( $parts ) < 2 ) {
		return '';
	}
	$payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ); // phpcs:ignore
	return isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '';
}

/** Apple client-secret (JWT) – vereist key-id, team-id en .p8 private key. */
function ekinese_apple_client_secret() {
	// Scaffold: zonder de .p8-sleutel kan de JWT niet worden ondertekend.
	// Vul team-id, key-id en de private key in de instellingen in.
	return apply_filters( 'ekinese_apple_client_secret', get_option( 'xg_oauth_apple_secret', '' ) );
}

/* ---- Login-knoppen (in het account-blok) ---- */
function ekinese_oauth_buttons() {
	$active = ekinese_oauth_active();
	if ( ! $active ) {
		return '';
	}
	$html = '<div class="xg-social-login"><span class="xg-social-or">of log in met</span><div class="xg-social-btns">';
	foreach ( $active as $k => $p ) {
		$html .= '<a class="xg-social-btn xg-social-' . esc_attr( $k ) . '" href="' . esc_url( home_url( '/xg-oauth/' . $k . '/' ) ) . '">' . esc_html( $p['label'] ) . '</a>';
	}
	return $html . '</div></div>';
}

/* ---- Admin ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'options-general.php', __( 'Social login', 'ekinese' ), __( 'Social login', 'ekinese' ), 'manage_options', 'xg-oauth', function () {
		if ( isset( $_POST['xg_oauth_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_oauth_nonce'] ), 'xg_oauth' ) ) {
			foreach ( array_keys( ekinese_oauth_providers() ) as $p ) {
				update_option( 'xg_oauth_' . $p . '_id', sanitize_text_field( wp_unslash( $_POST[ 'xg_oauth_' . $p . '_id' ] ?? '' ) ) );
				update_option( 'xg_oauth_' . $p . '_secret', sanitize_textarea_field( wp_unslash( $_POST[ 'xg_oauth_' . $p . '_secret' ] ?? '' ) ) );
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Social login</h1><p>Sleutels staan alleen hier (database). Gebruik onderstaande redirect-URI bij de provider.</p><form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_oauth', 'xg_oauth_nonce' );
		foreach ( ekinese_oauth_providers() as $p => $cfg ) {
			echo '<tr><th>' . esc_html( $cfg['label'] ) . ' Client ID</th><td><input type="text" name="xg_oauth_' . esc_attr( $p ) . '_id" value="' . esc_attr( get_option( 'xg_oauth_' . $p . '_id', '' ) ) . '" class="regular-text"><br><small>Redirect URI: <code>' . esc_html( ekinese_oauth_redirect_uri( $p ) ) . '</code></small></td></tr>';
			echo '<tr><th>' . esc_html( $cfg['label'] ) . ' Secret' . ( 'apple' === $p ? ' / .p8' : '' ) . '</th><td><textarea name="xg_oauth_' . esc_attr( $p ) . '_secret" rows="' . ( 'apple' === $p ? 4 : 1 ) . '" class="large-text">' . esc_textarea( get_option( 'xg_oauth_' . $p . '_secret', '' ) ) . '</textarea></td></tr>';
		}
		submit_button();
		echo '</table></form></div>';
	} );
} );
