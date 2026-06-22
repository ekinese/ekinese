<?php
/**
 * XGOUD "Bewaar mijn berekening" – deelbare deep-link + optioneel e-mailen.
 *
 * De rekenaar (assets/js/calculator.js) bouwt zelf een deep-link
 * /afspraak/?calc=<base64>. Dit endpoint mailt die berekening desgewenst naar
 * de klant, zodat hij later kan terugkeren. Anti-spam via reCAPTCHA (indien
 * geconfigureerd). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/calc-save', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_calc_save',
	) );
} );

/**
 * Mail de bewaarde berekening naar de klant.
 *
 * @param WP_REST_Request $req Verzoek.
 * @return WP_REST_Response|WP_Error
 */
function ekinese_calc_save( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'calc_save' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$calc  = (string) $req->get_param( 'calc' );
	$label = sanitize_text_field( (string) $req->get_param( 'label' ) );
	$value = sanitize_text_field( (string) $req->get_param( 'value' ) );

	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid', __( 'Vul een geldig e-mailadres in.', 'ekinese' ), array( 'status' => 400 ) );
	}
	// Token enkel als veilige base64url (we mailen het ongewijzigd terug in de link).
	if ( '' === $calc || ! preg_match( '#^[A-Za-z0-9_\-=]{1,2000}$#', $calc ) ) {
		return new WP_Error( 'invalid', __( 'Ongeldige berekening.', 'ekinese' ), array( 'status' => 400 ) );
	}

	$link = home_url( '/afspraak/?calc=' . rawurlencode( $calc ) );
	$b    = function_exists( 'ekinese_business' ) ? ekinese_business() : array( 'name' => 'XGOUD' );

	$subject = sprintf( '%s – uw bewaarde berekening', $b['name'] );
	$body    = "Beste,\n\n";
	$body   .= "U heeft uw berekening bij " . $b['name'] . " bewaard.\n\n";
	if ( $label ) {
		$body .= $label . ( $value ? ': ' . $value : '' ) . "\n\n";
	}
	$body   .= "Open uw berekening en maak direct een afspraak:\n" . $link . "\n\n";
	$body   .= "Let op: prijsindicaties zijn vrijblijvend; de eindprijs volgt na taxatie.\n\n";
	$body   .= "Met vriendelijke groet,\n" . $b['name'];

	$sent = wp_mail( $email, $subject, $body );

	// Log (hergebruik xg_maillog indien aanwezig), niet-blokkerend.
	if ( $sent && post_type_exists( 'xg_maillog' ) ) {
		wp_insert_post( array(
			'post_type'   => 'xg_maillog',
			'post_status' => 'publish',
			'post_title'  => 'calc-save → ' . $email,
		) );
	}

	if ( ! $sent ) {
		return new WP_Error( 'mail', __( 'Versturen mislukt. Probeer het later opnieuw.', 'ekinese' ), array( 'status' => 500 ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'link' => $link ) );
}
