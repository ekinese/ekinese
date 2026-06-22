<?php
/**
 * XGOUD spaardoel – klant stelt een doelbedrag in; voortgang volgt de
 * actuele portfoliowaarde (ekinese_portfolio_for). Opgeslagen per e-mail in een
 * optie. REST set/get beveiligd met de account-token. Render in account.js.
 * Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Optiesleutel voor het spaardoel van een e-mailadres. */
function ekinese_savings_goal_key( $email ) {
	return 'xg_goal_' . md5( strtolower( sanitize_email( $email ) ) );
}

/** Doel ophalen: { target, label }. */
function ekinese_savings_goal_get( $email ) {
	$g = get_option( ekinese_savings_goal_key( $email ) );
	if ( ! is_array( $g ) ) {
		return array( 'target' => 0, 'label' => '' );
	}
	return array( 'target' => (float) ( $g['target'] ?? 0 ), 'label' => (string) ( $g['label'] ?? '' ) );
}

/** Huidige portfoliowaarde van de klant (voor voortgang). */
function ekinese_savings_current( $email ) {
	if ( ! function_exists( 'ekinese_portfolio_for' ) ) {
		return 0.0;
	}
	$total = 0.0;
	foreach ( ekinese_portfolio_for( $email ) as $h ) {
		$total += (float) ( $h['value'] ?? 0 );
	}
	return round( $total, 2 );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/savings-goal', array(
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'ekinese_savings_goal_save',
		),
	) );
} );

/** Doel opslaan (account-token vereist). */
function ekinese_savings_goal_save( WP_REST_Request $req ) {
	$email = ekinese_account_verify_token( (string) $req->get_param( 'token' ) );
	if ( ! $email ) {
		return new WP_Error( 'unauthorized', __( 'Ongeldige of verlopen link.', 'ekinese' ), array( 'status' => 401 ) );
	}
	$target = max( 0, (float) $req->get_param( 'target' ) );
	$label  = sanitize_text_field( (string) $req->get_param( 'label' ) );
	update_option( ekinese_savings_goal_key( $email ), array( 'target' => $target, 'label' => $label ), false );
	return rest_ensure_response( array(
		'ok'      => true,
		'goal'    => array( 'target' => $target, 'label' => $label ),
		'current' => ekinese_savings_current( $email ),
	) );
}

add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$goal = ekinese_savings_goal_get( $email );
	$data['savings'] = array(
		'goal'    => $goal,
		'current' => ekinese_savings_current( $email ),
		'rest'    => esc_url_raw( rest_url( 'ekinese/v1/savings-goal' ) ),
	);
	return $data;
}, 10, 2 );
