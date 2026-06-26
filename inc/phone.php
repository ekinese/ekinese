<?php
/**
 * XGOUD telefonie / IVR.
 *
 * Koppelt een telefoonnummer (via een programmeerbare voice-provider, Twilio-
 * compatibel) aan een automatische ansage + keuzemenu. De beller kiest met het
 * toetsenbord (DTMF) óf met de stem (speech) en wordt doorverbonden naar de
 * juiste persoon. Volledig in te stellen in het backend.
 *
 * Werkt zodra je een (Twilio-)nummer hebt en de Voice-webhook laat wijzen naar:
 *   https://<jouw-site>/?xg_ivr=1
 * (POST). De provider stuurt Digits/SpeechResult; wij antwoorden met TwiML.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** De drie keuzes (gekoppeld aan de werkgebieden/rollen). */
function ekinese_ivr_options() {
	return array(
		'1' => array( 'label' => get_option( 'xg_ivr_opt1_label', 'verkoop en afspraken' ), 'num' => get_option( 'xg_ivr_opt1_num', '' ), 'kw' => array( 'verkoop', 'afspraak', 'afspraken', 'taxatie', 'verkopen' ) ),
		'2' => array( 'label' => get_option( 'xg_ivr_opt2_label', 'bestaande order of dossier' ), 'num' => get_option( 'xg_ivr_opt2_num', '' ), 'kw' => array( 'order', 'dossier', 'bestaande', 'klant', 'vraag' ) ),
		'3' => array( 'label' => get_option( 'xg_ivr_opt3_label', 'zakelijk en overig' ), 'num' => get_option( 'xg_ivr_opt3_num', '' ), 'kw' => array( 'zakelijk', 'overig', 'pers', 'media', 'beheer' ) ),
	);
}

/* Query-endpoint /?xg_ivr=1 → TwiML. */
add_filter( 'query_vars', function ( $v ) { $v[] = 'xg_ivr'; return $v; } );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'xg_ivr' ) ) {
		return;
	}
	// Optionele Twilio-signatuurcontrole.
	$token = trim( (string) get_option( 'xg_ivr_twilio_token', '' ) );
	// (Validatie is provider-specifiek; bij ingevulde token kun je hier de
	//  X-Twilio-Signature verifiëren. Scaffold: we accepteren de call.)
	unset( $token );

	$home  = home_url( '/?xg_ivr=1' );
	$step  = sanitize_key( (string) ( $_REQUEST['step'] ?? 'menu' ) );
	$opts  = ekinese_ivr_options();
	$greet = (string) get_option( 'xg_ivr_greeting', 'Welkom bij XGOUD, uw specialist in edelmetaal.' );

	header( 'Content-Type: text/xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n<Response>";

	if ( 'route' === $step ) {
		// Keuze bepalen uit toets of spraak.
		$digit  = preg_replace( '/\D/', '', (string) ( $_REQUEST['Digits'] ?? '' ) );
		$speech = strtolower( (string) ( $_REQUEST['SpeechResult'] ?? '' ) );
		$choice = '';
		if ( isset( $opts[ $digit ] ) ) {
			$choice = $digit;
		} elseif ( $speech ) {
			foreach ( $opts as $k => $o ) {
				foreach ( $o['kw'] as $kw ) {
					if ( false !== strpos( $speech, $kw ) ) {
						$choice = $k;
						break 2;
					}
				}
			}
		}
		// Loggen.
		ekinese_ivr_log( (string) ( $_REQUEST['From'] ?? '' ), $choice ? $opts[ $choice ]['label'] : 'geen keuze' );

		$num = $choice ? trim( (string) $opts[ $choice ]['num'] ) : '';
		if ( $num ) {
			echo '<Say language="nl-NL" voice="alice">Een moment, ik verbind u door.</Say>';
			echo '<Dial timeout="25">' . esc_html( $num ) . '</Dial>';
			echo '<Say language="nl-NL" voice="alice">De medewerker is niet bereikbaar. Probeer het later opnieuw of mail naar info at x goud punt nl. Tot ziens.</Say>';
		} else {
			echo '<Say language="nl-NL" voice="alice">We konden uw keuze niet verwerken of er is niemand beschikbaar. Mail gerust naar info at x goud punt nl. Tot ziens.</Say>';
		}
		echo '<Hangup/></Response>';
		exit;
	}

	// Hoofdmenu.
	$menu = $greet . ' ';
	foreach ( $opts as $k => $o ) {
		$menu .= 'Voor ' . $o['label'] . ', toets ' . $k . '. ';
	}
	$menu .= 'U mag uw keuze ook inspreken.';
	echo '<Gather input="dtmf speech" numDigits="1" timeout="6" speechTimeout="auto" language="nl-NL" action="' . esc_url( $home . '&step=route' ) . '" method="POST">';
	echo '<Say language="nl-NL" voice="alice">' . esc_html( $menu ) . '</Say>';
	echo '</Gather>';
	echo '<Say language="nl-NL" voice="alice">We hebben niets ontvangen. Tot ziens.</Say><Hangup/></Response>';
	exit;
} );

/** Belletje loggen (laatste 100). */
function ekinese_ivr_log( $from, $choice ) {
	$log = get_option( 'xg_ivr_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => current_time( 'mysql' ), 'from' => sanitize_text_field( $from ), 'choice' => sanitize_text_field( $choice ) ) );
	update_option( 'xg_ivr_log', array_slice( $log, 0, 100 ), false );
}

/* =====================================================================
   ADMIN: Telefonie-instellingen + recente oproepen
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Telefonie / IVR', 'Telefonie', 'manage_options', 'xg-phone', 'ekinese_phone_page' );
}, 54 );

add_action( 'admin_init', function () {
	if ( isset( $_POST['xg_ivr_save'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'xg_ivr_save' ) ) {
		update_option( 'xg_ivr_greeting', sanitize_text_field( wp_unslash( $_POST['xg_ivr_greeting'] ?? '' ) ) );
		update_option( 'xg_ivr_twilio_token', sanitize_text_field( wp_unslash( $_POST['xg_ivr_twilio_token'] ?? '' ) ) );
		foreach ( array( '1', '2', '3' ) as $i ) {
			update_option( 'xg_ivr_opt' . $i . '_label', sanitize_text_field( wp_unslash( $_POST[ 'xg_ivr_opt' . $i . '_label' ] ?? '' ) ) );
			update_option( 'xg_ivr_opt' . $i . '_num', sanitize_text_field( wp_unslash( $_POST[ 'xg_ivr_opt' . $i . '_num' ] ?? '' ) ) );
		}
		add_settings_error( 'xg_ivr', 'saved', 'Telefonie-instellingen opgeslagen.', 'success' );
	}
} );

function ekinese_phone_page() {
	settings_errors( 'xg_ivr' );
	$opts = ekinese_ivr_options();
	echo '<div class="wrap"><h1>Telefonie / IVR</h1>';
	echo '<p>Automatische ansage + keuzemenu voor inkomende oproepen. De beller kiest met toets of stem en wordt doorverbonden. Koppel je (Twilio-)nummer aan de Voice-webhook:</p>';
	echo '<p><code>' . esc_html( home_url( '/?xg_ivr=1' ) ) . '</code> (HTTP POST)</p>';

	echo '<form method="post"><input type="hidden" name="xg_ivr_save" value="1">';
	wp_nonce_field( 'xg_ivr_save' );
	echo '<table class="form-table"><tbody>';
	echo '<tr><th>Begroeting</th><td><input type="text" name="xg_ivr_greeting" value="' . esc_attr( get_option( 'xg_ivr_greeting', 'Welkom bij XGOUD, uw specialist in edelmetaal.' ) ) . '" class="large-text"></td></tr>';
	foreach ( array( '1', '2', '3' ) as $i ) {
		echo '<tr><th>Keuze ' . esc_html( $i ) . '</th><td>';
		echo 'Omschrijving <input type="text" name="xg_ivr_opt' . $i . '_label" value="' . esc_attr( get_option( 'xg_ivr_opt' . $i . '_label', $opts[ $i ]['label'] ) ) . '" class="regular-text"> ';
		echo 'Doorschakelen naar <input type="text" name="xg_ivr_opt' . $i . '_num" value="' . esc_attr( get_option( 'xg_ivr_opt' . $i . '_num', '' ) ) . '" placeholder="+31 6 …" class="regular-text">';
		echo '</td></tr>';
	}
	echo '<tr><th>Twilio Auth Token (optioneel)</th><td><input type="password" name="xg_ivr_twilio_token" value="' . esc_attr( get_option( 'xg_ivr_twilio_token', '' ) ) . '" class="regular-text" autocomplete="off"><p class="description">Voor latere webhook-handtekeningverificatie.</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form>';

	// Recente oproepen.
	$log = (array) get_option( 'xg_ivr_log', array() );
	echo '<h2>Recente oproepen</h2>';
	if ( $log ) {
		echo '<table class="widefat striped"><thead><tr><th>Tijd</th><th>Van</th><th>Keuze</th></tr></thead><tbody>';
		foreach ( array_slice( $log, 0, 30 ) as $l ) {
			echo '<tr><td>' . esc_html( $l['t'] ) . '</td><td>' . esc_html( $l['from'] ?: 'onbekend' ) . '</td><td>' . esc_html( $l['choice'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>Nog geen oproepen geregistreerd.</p>';
	}
	echo '<p class="description" style="margin-top:14px">Providers: <strong>Twilio</strong> (programmable voice, TwiML — direct compatibel). Andere providers met TwiML-ondersteuning werken ook; Voys/MessageBird gebruiken een eigen flow-formaat.</p>';
	echo '</div>';
}
