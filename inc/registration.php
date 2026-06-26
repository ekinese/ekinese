<?php
/**
 * XGOUD registratie & login-uitbreiding.
 *
 * Bovenop de bestaande passwordless magic-link (inc/account.php) en social-login
 * (inc/social-login.php) voegt dit toe:
 *   - Registratie (naam, e-mail, telefoon, wachtwoord) → profiel + auto-login.
 *   - E-mail + wachtwoord-login.
 *   - Telefoon-login via SMS-OTP (provider: MessageBird of Twilio; key in opties).
 *
 * Alle methodes eindigen in hetzelfde account-token (ekinese_account_make_token),
 * zodat Mijn XGOUD/het PWA-widget ongewijzigd werken. Identiteit blijft de e-mail
 * (alles is e-mail-gekoppeld); een telefoonnummer wordt aan een e-mailaccount
 * gekoppeld bij registratie.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   PROFIELOPSLAG  (CPT xg_account, gekoppeld op e-mail)
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_account', array(
		'labels'       => array( 'name' => 'Accounts', 'singular_name' => 'Account' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'xgoud',
		'menu_icon'    => 'dashicons-id',
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
	) );
} );

/** Telefoonnummer normaliseren naar E.164-achtig (+31…). */
function ekinese_normalize_phone( $phone ) {
	$p = preg_replace( '/[^\d+]/', '', (string) $phone );
	if ( '' === $p ) {
		return '';
	}
	if ( '+' === $p[0] ) {
		return $p;
	}
	if ( 0 === strpos( $p, '00' ) ) {
		return '+' . substr( $p, 2 );
	}
	if ( 0 === strpos( $p, '0' ) ) {
		return '+31' . substr( $p, 1 ); // NL-default
	}
	return '+' . $p;
}

/** Profiel-post voor een e-mail ophalen (of 0). */
function ekinese_account_profile_id( $email ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return 0;
	}
	$q = get_posts( array(
		'post_type'   => 'xg_account',
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => 'email',
		'meta_value'  => $email,
	) );
	return $q ? (int) $q[0] : 0;
}

/** Profiel aanmaken/bijwerken. */
function ekinese_account_upsert( $email, $fields = array() ) {
	$email = sanitize_email( $email );
	$id    = ekinese_account_profile_id( $email );
	if ( ! $id ) {
		$id = wp_insert_post( array(
			'post_type'   => 'xg_account',
			'post_status' => 'publish',
			'post_title'  => $email,
		), true );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, 'email', $email );
		update_post_meta( $id, 'created', current_time( 'mysql' ) );
	}
	foreach ( $fields as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return $id;
}

/** Account-id op telefoonnummer. */
function ekinese_account_by_phone( $phone ) {
	$phone = ekinese_normalize_phone( $phone );
	if ( ! $phone ) {
		return 0;
	}
	$q = get_posts( array(
		'post_type'   => 'xg_account',
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => 'phone',
		'meta_value'  => $phone,
	) );
	return $q ? (int) $q[0] : 0;
}

/* =====================================================================
   SMS-PROVIDER  (MessageBird / Twilio)
===================================================================== */
function ekinese_sms_send( $phone, $text ) {
	$phone    = ekinese_normalize_phone( $phone );
	$provider = get_option( 'xg_sms_provider', '' );
	if ( ! $phone || ! $provider ) {
		return new WP_Error( 'sms', 'Geen SMS-provider ingesteld.' );
	}
	if ( 'messagebird' === $provider ) {
		$key  = trim( (string) get_option( 'xg_sms_key', '' ) );
		$from = get_option( 'xg_sms_from', 'XGOUD' );
		if ( ! $key ) {
			return new WP_Error( 'sms', 'MessageBird-key ontbreekt.' );
		}
		$res = wp_remote_post( 'https://rest.messagebird.com/messages', array(
			'headers' => array( 'Authorization' => 'AccessKey ' . $key, 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array( 'originator' => $from, 'recipients' => $phone, 'body' => $text ),
			'timeout' => 15,
		) );
	} elseif ( 'twilio' === $provider ) {
		$sid   = trim( (string) get_option( 'xg_sms_twilio_sid', '' ) );
		$token = trim( (string) get_option( 'xg_sms_twilio_token', '' ) );
		$from  = get_option( 'xg_sms_from', '' );
		if ( ! $sid || ! $token || ! $from ) {
			return new WP_Error( 'sms', 'Twilio-gegevens ontbreken.' );
		}
		$res = wp_remote_post( 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json', array(
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ) ), // phpcs:ignore
			'body'    => array( 'To' => $phone, 'From' => $from, 'Body' => $text ),
			'timeout' => 15,
		) );
	} else {
		return new WP_Error( 'sms', 'Onbekende provider.' );
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	return ( $code >= 200 && $code < 300 ) ? true : new WP_Error( 'sms', 'SMS verzenden mislukt (' . $code . ').' );
}

/* =====================================================================
   REST
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/account/register', array(
		'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_account_register',
	) );
	register_rest_route( 'ekinese/v1', '/account/login-password', array(
		'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_account_login_password',
	) );
	register_rest_route( 'ekinese/v1', '/account/phone/start', array(
		'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_account_phone_start',
	) );
	register_rest_route( 'ekinese/v1', '/account/phone/verify', array(
		'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_account_phone_verify',
	) );
} );

function ekinese_account_register( WP_REST_Request $req ) {
	if ( ! empty( $req->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'ok' => true ) ); // honeypot
	}
	$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$phone = ekinese_normalize_phone( (string) $req->get_param( 'phone' ) );
	$pass  = (string) $req->get_param( 'password' );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'email', 'Vul een geldig e-mailadres in.', array( 'status' => 400 ) );
	}
	if ( strlen( $pass ) < 8 ) {
		return new WP_Error( 'pass', 'Kies een wachtwoord van minstens 8 tekens.', array( 'status' => 400 ) );
	}
	if ( ekinese_account_profile_id( $email ) ) {
		return new WP_Error( 'exists', 'Er bestaat al een account met dit e-mailadres. Log in.', array( 'status' => 409 ) );
	}
	if ( $phone && ekinese_account_by_phone( $phone ) ) {
		return new WP_Error( 'phone', 'Dit telefoonnummer is al gekoppeld aan een account.', array( 'status' => 409 ) );
	}
	$fields = array( 'name' => $name, 'pass_hash' => wp_hash_password( $pass ) );
	if ( $phone ) {
		$fields['phone'] = $phone;
	}
	$id = ekinese_account_upsert( $email, $fields );
	if ( ! $id ) {
		return new WP_Error( 'save', 'Registratie mislukt.', array( 'status' => 500 ) );
	}
	if ( function_exists( 'ekinese_notify' ) ) {
		ekinese_notify( $email, 'Welkom bij XGOUD', 'Je account is aangemaakt. Je kunt nu inloggen met je e-mail of telefoonnummer.' );
	}
	do_action( 'ekinese_account_registered', $email, $id );
	$token = ekinese_account_make_token( $email );
	return rest_ensure_response( array( 'ok' => true, 'token' => $token, 'redirect' => home_url( '/mijn-xgoud/?token=' . $token ) ) );
}

function ekinese_account_login_password( WP_REST_Request $req ) {
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$pass  = (string) $req->get_param( 'password' );
	$id    = ekinese_account_profile_id( $email );
	$hash  = $id ? (string) get_post_meta( $id, 'pass_hash', true ) : '';
	if ( ! $id || ! $hash || ! wp_check_password( $pass, $hash ) ) {
		// Anti-enumeratie: zelfde melding.
		return new WP_Error( 'auth', 'E-mail of wachtwoord onjuist.', array( 'status' => 401 ) );
	}
	$token = ekinese_account_make_token( $email );
	return rest_ensure_response( array( 'ok' => true, 'token' => $token, 'redirect' => home_url( '/mijn-xgoud/?token=' . $token ) ) );
}

function ekinese_account_phone_start( WP_REST_Request $req ) {
	$phone = ekinese_normalize_phone( (string) $req->get_param( 'phone' ) );
	if ( strlen( $phone ) < 8 ) {
		return new WP_Error( 'phone', 'Vul een geldig telefoonnummer in.', array( 'status' => 400 ) );
	}
	if ( ! ekinese_account_by_phone( $phone ) ) {
		return new WP_Error( 'nophone', 'Geen account met dit nummer. Registreer eerst met je telefoonnummer.', array( 'status' => 404 ) );
	}
	// Rate-limit: max 1 code per minuut.
	if ( get_transient( 'xg_otp_rl_' . md5( $phone ) ) ) {
		return new WP_Error( 'rate', 'Wacht even voordat je een nieuwe code aanvraagt.', array( 'status' => 429 ) );
	}
	$code = (string) wp_rand( 100000, 999999 );
	set_transient( 'xg_otp_' . md5( $phone ), wp_hash_password( $code ), 10 * MINUTE_IN_SECONDS );
	set_transient( 'xg_otp_rl_' . md5( $phone ), 1, MINUTE_IN_SECONDS );
	$sent = ekinese_sms_send( $phone, 'Je XGOUD-inlogcode is: ' . $code . ' (10 min geldig).' );
	if ( is_wp_error( $sent ) ) {
		return new WP_Error( 'sms', $sent->get_error_message(), array( 'status' => 502 ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => 'We hebben een code naar je telefoon gestuurd.' ) );
}

function ekinese_account_phone_verify( WP_REST_Request $req ) {
	$phone = ekinese_normalize_phone( (string) $req->get_param( 'phone' ) );
	$code  = preg_replace( '/\D/', '', (string) $req->get_param( 'code' ) );
	$id    = ekinese_account_by_phone( $phone );
	$hash  = get_transient( 'xg_otp_' . md5( $phone ) );
	if ( ! $id || ! $hash || ! wp_check_password( $code, $hash ) ) {
		return new WP_Error( 'otp', 'Code onjuist of verlopen.', array( 'status' => 401 ) );
	}
	delete_transient( 'xg_otp_' . md5( $phone ) );
	update_post_meta( $id, 'phone_verified', 1 );
	$email = (string) get_post_meta( $id, 'email', true );
	$token = ekinese_account_make_token( $email );
	return rest_ensure_response( array( 'ok' => true, 'token' => $token, 'redirect' => home_url( '/mijn-xgoud/?token=' . $token ) ) );
}

/* Telefoon ook beschikbaar in de accountdata (profiel). */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$id = ekinese_account_profile_id( $email );
	if ( $id ) {
		$data['profile'] = array(
			'name'  => (string) get_post_meta( $id, 'name', true ),
			'phone' => (string) get_post_meta( $id, 'phone', true ),
		);
	}
	return $data;
}, 5, 2 );

/* =====================================================================
   ADMIN: Login & SMS-instellingen
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Login & SMS', 'Login & SMS', 'manage_options', 'xg-login', 'ekinese_login_settings_page' );
}, 52 );

add_action( 'admin_init', function () {
	if ( ! isset( $_POST['xg_login_save'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'xg_login_save' );
	foreach ( array( 'xg_sms_provider', 'xg_sms_key', 'xg_sms_from', 'xg_sms_twilio_sid', 'xg_sms_twilio_token' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_option( $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	add_settings_error( 'xg_login', 'saved', 'Opgeslagen.', 'success' );
} );

function ekinese_login_settings_page() {
	settings_errors( 'xg_login' );
	$provider = get_option( 'xg_sms_provider', '' );
	?>
	<div class="wrap">
		<h1>Login &amp; SMS</h1>
		<p>Social-login (Google/Facebook/Apple) stel je in bij <a href="<?php echo esc_url( admin_url( 'admin.php?page=xg-integrations' ) ); ?>">Integraties</a>. Hier configureer je de SMS-provider voor telefoon-login (OTP).</p>
		<form method="post">
			<?php wp_nonce_field( 'xg_login_save' ); ?>
			<input type="hidden" name="xg_login_save" value="1">
			<table class="form-table"><tbody>
				<tr><th scope="row">SMS-provider</th><td>
					<select name="xg_sms_provider">
						<option value="" <?php selected( $provider, '' ); ?>>— uit (geen telefoon-login) —</option>
						<option value="messagebird" <?php selected( $provider, 'messagebird' ); ?>>MessageBird</option>
						<option value="twilio" <?php selected( $provider, 'twilio' ); ?>>Twilio</option>
					</select>
				</td></tr>
				<tr><th scope="row">Afzender (originator/From)</th><td>
					<input type="text" name="xg_sms_from" value="<?php echo esc_attr( get_option( 'xg_sms_from', 'XGOUD' ) ); ?>" class="regular-text">
					<p class="description">MessageBird: naam of nummer. Twilio: je geverifieerde Twilio-nummer (+31…).</p>
				</td></tr>
				<tr><th scope="row">MessageBird API-key</th><td>
					<input type="password" name="xg_sms_key" value="<?php echo esc_attr( get_option( 'xg_sms_key', '' ) ); ?>" class="regular-text" autocomplete="off">
				</td></tr>
				<tr><th scope="row">Twilio Account SID</th><td>
					<input type="text" name="xg_sms_twilio_sid" value="<?php echo esc_attr( get_option( 'xg_sms_twilio_sid', '' ) ); ?>" class="regular-text">
				</td></tr>
				<tr><th scope="row">Twilio Auth Token</th><td>
					<input type="password" name="xg_sms_twilio_token" value="<?php echo esc_attr( get_option( 'xg_sms_twilio_token', '' ) ); ?>" class="regular-text" autocomplete="off">
				</td></tr>
			</tbody></table>
			<?php submit_button( 'Opslaan' ); ?>
		</form>
		<p class="description">Telefoon-login werkt zodra een provider + sleutel zijn ingevuld. Per SMS gelden providerkosten.</p>
	</div>
	<?php
}
