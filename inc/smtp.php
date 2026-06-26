<?php
/**
 * XGOUD e-mail / SMTP — betrouwbare aflevering van magic-links, bevestigingen en
 * meldingen. Zonder SMTP belanden mails vaak in spam. Stelt een consistente
 * afzender in en (optioneel) een SMTP-server. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Consistente afzender (ook zonder SMTP) — beter voor SPF/DKIM/DMARC. */
add_filter( 'wp_mail_from', function ( $from ) {
	$f = trim( (string) get_option( 'xg_smtp_from', '' ) );
	return $f ? $f : $from;
} );
add_filter( 'wp_mail_from_name', function ( $name ) {
	$n = trim( (string) get_option( 'xg_smtp_fromname', '' ) );
	return $n ? $n : ( function_exists( 'ekinese_business' ) ? ekinese_business()['name'] : $name );
} );

/* SMTP-configuratie toepassen indien ingeschakeld. */
add_action( 'phpmailer_init', function ( $phpmailer ) {
	if ( get_option( 'xg_smtp_enabled', '' ) !== '1' ) {
		return;
	}
	$phpmailer->isSMTP();
	$phpmailer->Host       = (string) get_option( 'xg_smtp_host', '' );
	$phpmailer->Port       = (int) get_option( 'xg_smtp_port', 587 );
	$enc                   = (string) get_option( 'xg_smtp_enc', 'tls' );
	$phpmailer->SMTPSecure = in_array( $enc, array( 'tls', 'ssl' ), true ) ? $enc : '';
	if ( '' === $phpmailer->SMTPSecure ) {
		$phpmailer->SMTPAutoTLS = false;
	}
	$user = (string) get_option( 'xg_smtp_user', '' );
	if ( '' !== $user ) {
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = $user;
		$phpmailer->Password = (string) get_option( 'xg_smtp_pass', '' );
	}
	$from = trim( (string) get_option( 'xg_smtp_from', '' ) );
	if ( $from ) {
		$phpmailer->setFrom( $from, (string) get_option( 'xg_smtp_fromname', 'XGOUD' ), false );
	}
} );

/* =====================================================================
   ADMIN — instellingen + testmail
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'E-mail / SMTP', 'ekinese' ), __( 'E-mail / SMTP', 'ekinese' ), 'manage_options', 'xg-smtp', 'ekinese_smtp_page' );
} );

function ekinese_smtp_page() {
	if ( isset( $_POST['xg_smtp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_smtp_nonce'] ), 'xg_smtp' ) ) {
		if ( isset( $_POST['xg_smtp_test'] ) ) {
			$to   = sanitize_email( wp_unslash( $_POST['xg_smtp_testto'] ?? '' ) );
			$sent = $to ? wp_mail( $to, 'XGOUD testmail', "Dit is een testmail van uw XGOUD-website.\nAls u dit ontvangt, werkt de e-mailconfiguratie." ) : false;
			echo '<div class="notice notice-' . ( $sent ? 'success' : 'error' ) . '"><p>' . ( $sent ? 'Testmail verzonden.' : 'Verzenden mislukt (controleer de instellingen).' ) . '</p></div>';
		} else {
			update_option( 'xg_smtp_enabled', ! empty( $_POST['xg_smtp_enabled'] ) ? '1' : '' );
			foreach ( array( 'xg_smtp_host', 'xg_smtp_port', 'xg_smtp_enc', 'xg_smtp_user', 'xg_smtp_from', 'xg_smtp_fromname' ) as $o ) {
				if ( isset( $_POST[ $o ] ) ) {
					update_option( $o, sanitize_text_field( wp_unslash( $_POST[ $o ] ) ) );
				}
			}
			if ( isset( $_POST['xg_smtp_pass'] ) && '' !== $_POST['xg_smtp_pass'] ) {
				update_option( 'xg_smtp_pass', (string) wp_unslash( $_POST['xg_smtp_pass'] ) ); // leeg = behouden
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
	}
	$g = function ( $k, $d = '' ) { return esc_attr( get_option( $k, $d ) ); };
	echo '<div class="wrap"><h1>E-mail / SMTP</h1>';
	echo '<p>Voor betrouwbare aflevering (magic-links, bevestigingen, meldingen) raden wij een SMTP-server aan, met een afzender op uw eigen domein en correcte SPF/DKIM/DMARC-records.</p>';
	echo '<form method="post"><table class="form-table">';
	wp_nonce_field( 'xg_smtp', 'xg_smtp_nonce' );
	echo '<tr><th>Afzender e-mail</th><td><input type="email" name="xg_smtp_from" value="' . $g( 'xg_smtp_from' ) . '" class="regular-text" placeholder="noreply@xgoud.nl"></td></tr>';
	echo '<tr><th>Afzender naam</th><td><input type="text" name="xg_smtp_fromname" value="' . $g( 'xg_smtp_fromname', 'XGOUD' ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>SMTP gebruiken</th><td><label><input type="checkbox" name="xg_smtp_enabled" value="1" ' . checked( get_option( 'xg_smtp_enabled', '' ), '1', false ) . '> Inschakelen</label></td></tr>';
	echo '<tr><th>SMTP-host</th><td><input type="text" name="xg_smtp_host" value="' . $g( 'xg_smtp_host' ) . '" class="regular-text" placeholder="smtp.uwprovider.nl"></td></tr>';
	echo '<tr><th>Poort</th><td><input type="number" name="xg_smtp_port" value="' . $g( 'xg_smtp_port', '587' ) . '" class="small-text"></td></tr>';
	$enc = get_option( 'xg_smtp_enc', 'tls' );
	echo '<tr><th>Encryptie</th><td><select name="xg_smtp_enc"><option value="tls"' . selected( $enc, 'tls', false ) . '>TLS (587)</option><option value="ssl"' . selected( $enc, 'ssl', false ) . '>SSL (465)</option><option value="none"' . selected( $enc, 'none', false ) . '>Geen</option></select></td></tr>';
	echo '<tr><th>Gebruikersnaam</th><td><input type="text" name="xg_smtp_user" value="' . $g( 'xg_smtp_user' ) . '" class="regular-text" autocomplete="off"></td></tr>';
	echo '<tr><th>Wachtwoord</th><td><input type="password" name="xg_smtp_pass" value="" class="regular-text" autocomplete="new-password" placeholder="' . ( get_option( 'xg_smtp_pass', '' ) ? '••••••••' : '' ) . '"><p class="description">Leeg laten = bestaand wachtwoord behouden.</p></td></tr>';
	echo '</table>';
	submit_button( 'Opslaan' );
	echo '<hr><h2>Testmail</h2><p><input type="email" name="xg_smtp_testto" class="regular-text" placeholder="ontvanger@example.com"> <button type="submit" name="xg_smtp_test" value="1" class="button">Verstuur testmail</button></p>';
	echo '</form></div>';
}
