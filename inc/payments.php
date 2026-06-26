<?php
/**
 * XGOUD betalingen via Mollie (iDEAL e.a.). Self-built (directe API-call), geen
 * plugin. De gewonnen-veiling-flow: winnaar logt in op Mijn XGOUD → "Betalen" →
 * Mollie-checkout → webhook bevestigt → factuur + charity vrijgegeven.
 *
 * Vereist een Mollie API-key (test_/live_). Webhook moet publiek bereikbaar zijn;
 * dit is alleen live te testen.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_mollie_key() {
	return trim( (string) get_option( 'xg_mollie_key', '' ) );
}

/** Lichte Mollie API-call. */
function ekinese_mollie_request( $method, $path, $body = null ) {
	$key = ekinese_mollie_key();
	if ( '' === $key ) {
		return null;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$resp = wp_remote_request( 'https://api.mollie.com/v2' . $path, $args );
	if ( is_wp_error( $resp ) ) {
		return null;
	}
	return json_decode( (string) wp_remote_retrieve_body( $resp ), true );
}

/** Maak een betaling; geeft [id, checkout_url] of null. */
function ekinese_mollie_create_payment( $amount, $description, $redirect, $metadata ) {
	$data = ekinese_mollie_request( 'POST', '/payments', array(
		'amount'      => array( 'currency' => 'EUR', 'value' => number_format( (float) $amount, 2, '.', '' ) ),
		'description' => mb_substr( (string) $description, 0, 200 ),
		'redirectUrl' => $redirect,
		'webhookUrl'  => rest_url( 'ekinese/v1/pay/webhook' ),
		'metadata'    => $metadata,
	) );
	if ( ! $data || empty( $data['id'] ) || empty( $data['_links']['checkout']['href'] ) ) {
		return null;
	}
	return array( $data['id'], $data['_links']['checkout']['href'] );
}

/* =====================================================================
   REST – start + webhook
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/pay/start', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_pay_start',
	) );
	register_rest_route( 'ekinese/v1', '/pay/webhook', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_pay_webhook',
	) );
} );

function ekinese_pay_start( WP_REST_Request $req ) {
	$type  = sanitize_key( (string) $req->get_param( 'type' ) );
	$id    = (int) $req->get_param( 'id' );
	$token = (string) $req->get_param( 'token' );
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( $token ) : '';
	$back  = home_url( '/mijn-xgoud/?token=' . rawurlencode( $token ) );

	if ( ! $email || ekinese_mollie_key() === '' ) {
		wp_safe_redirect( $back . '&pay=error' );
		exit;
	}

	$amount = 0; $desc = '';
	if ( 'auction' === $type && get_post_type( $id ) === 'xg_auction' ) {
		if ( get_post_meta( $id, 'winner_email', true ) !== $email || get_post_meta( $id, 'paid', true ) === '1' ) {
			wp_safe_redirect( $back . '&pay=error' );
			exit;
		}
		$amount = (float) get_post_meta( $id, 'current_bid', true );
		$desc   = 'XGOUD veiling: ' . get_the_title( $id );
	} elseif ( 'market' === $type && get_post_type( $id ) === 'xg_market' ) {
		$amount = (float) get_post_meta( $id, 'price', true );
		$desc   = 'XGOUD marktplaats: ' . get_the_title( $id );
	} elseif ( 'escrow' === $type && get_post_type( $id ) === 'xg_escrow' ) {
		// Alleen de koper kan betalen, alleen zodra de factuur is verstuurd.
		if ( get_post_meta( $id, 'buyer_email', true ) !== $email || get_post_meta( $id, 'status', true ) !== 'factuur' ) {
			wp_safe_redirect( $back . '&pay=error' );
			exit;
		}
		$amount = (float) get_post_meta( $id, 'amount', true );
		$desc   = 'XGOUD Treuhandservice: ' . get_the_title( $id );
	} else {
		wp_safe_redirect( $back . '&pay=error' );
		exit;
	}
	if ( $amount <= 0 ) {
		wp_safe_redirect( $back . '&pay=error' );
		exit;
	}
	$pay = ekinese_mollie_create_payment( $amount, $desc, $back . '&pay=done', array( 'type' => $type, 'id' => (string) $id ) );
	if ( ! $pay ) {
		wp_safe_redirect( $back . '&pay=error' );
		exit;
	}
	update_post_meta( $id, 'mollie_id', $pay[0] );
	wp_redirect( $pay[1] ); // naar Mollie-checkout
	exit;
}

function ekinese_pay_webhook( WP_REST_Request $req ) {
	$pid = sanitize_text_field( (string) $req->get_param( 'id' ) );
	if ( '' === $pid ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	$pay = ekinese_mollie_request( 'GET', '/payments/' . rawurlencode( $pid ) );
	if ( ! $pay || ( $pay['status'] ?? '' ) !== 'paid' ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	$type = sanitize_key( $pay['metadata']['type'] ?? '' );
	$id   = (int) ( $pay['metadata']['id'] ?? 0 );
	if ( 'auction' === $type ) {
		ekinese_pay_mark_auction_paid( $id );
	} elseif ( 'market' === $type ) {
		ekinese_pay_mark_market_paid( $id );
	} elseif ( 'escrow' === $type && function_exists( 'ekinese_escrow_mark_paid' ) ) {
		ekinese_escrow_mark_paid( $id );
	}
	return rest_ensure_response( array( 'ok' => true ) );
}

/** Veiling als betaald markeren → factuur + charity vrijgeven + melding. */
function ekinese_pay_mark_auction_paid( $id ) {
	if ( get_post_type( $id ) !== 'xg_auction' || get_post_meta( $id, 'paid', true ) === '1' ) {
		return;
	}
	update_post_meta( $id, 'paid', '1' );
	if ( ! get_post_meta( $id, 'invoice_number', true ) ) {
		update_post_meta( $id, 'invoice_number', 'INV-' . gmdate( 'Y' ) . '-' . $id );
		update_post_meta( $id, 'invoice_date', gmdate( 'Y-m-d' ) );
	}
	update_post_meta( $id, 'charity_released', '1' );
	$winner = get_post_meta( $id, 'winner_email', true );
	$title  = get_the_title( $id );
	if ( is_email( $winner ) ) {
		wp_mail( $winner, 'Betaling ontvangen — ' . $title, sprintf( "Bedankt! Wij hebben uw betaling voor '%s' ontvangen. Uw factuur is %s.", $title, get_post_meta( $id, 'invoice_number', true ) ) );
		if ( function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $winner, 'Betaling ontvangen', sprintf( "Wij ontvingen uw betaling voor '%s'. Factuur %s.", $title, get_post_meta( $id, 'invoice_number', true ) ), get_permalink( $id ), 'invoice' );
		}
	}
}

/** Marktplaats-advertentie als verkocht+betaald markeren. */
function ekinese_pay_mark_market_paid( $id ) {
	if ( get_post_type( $id ) !== 'xg_market' ) {
		return;
	}
	update_post_meta( $id, 'status', 'sold' );
	update_post_meta( $id, 'paid', '1' );
	$pct   = (float) get_post_meta( $id, 'charity_pct', true );
	$price = (float) get_post_meta( $id, 'price', true );
	if ( $pct > 0 && $price > 0 ) {
		update_post_meta( $id, 'charity_amount', round( $price * $pct / 100, 2 ) );
	}
	update_post_meta( $id, 'charity_released', '1' );
}

/* =====================================================================
   ADMIN — Mollie-key
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Betalingen (Mollie)', 'ekinese' ), __( 'Betalingen', 'ekinese' ), 'manage_options', 'xg-pay', function () {
		if ( isset( $_POST['xg_pay_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_pay_nonce'] ), 'xg_pay' ) ) {
			update_option( 'xg_mollie_key', sanitize_text_field( wp_unslash( $_POST['xg_mollie_key'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		$key  = (string) get_option( 'xg_mollie_key', '' );
		$mode = 0 === strpos( $key, 'live_' ) ? 'LIVE' : ( 0 === strpos( $key, 'test_' ) ? 'TEST' : '—' );
		echo '<div class="wrap"><h1>Betalingen — Mollie</h1>';
		echo '<p>Vul uw Mollie API-key in (test_… of live_…). Hiermee kunnen winnaars van veilingen en marktplaats-kopers veilig betalen (iDEAL e.a.). Modus: <strong>' . esc_html( $mode ) . '</strong>.</p>';
		echo '<p>Webhook-URL: <code>' . esc_html( rest_url( 'ekinese/v1/pay/webhook' ) ) . '</code></p>';
		echo '<form method="post"><table class="form-table"><tr><th>Mollie API-key</th><td><input type="text" name="xg_mollie_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="test_… / live_…"></td></tr></table>';
		wp_nonce_field( 'xg_pay', 'xg_pay_nonce' );
		submit_button();
		echo '</form></div>';
	} );
} );
