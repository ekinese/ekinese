<?php
/**
 * XGOUD "Mijn XGOUD" – self-service account zonder wachtwoord.
 *
 * De klant vraagt een magic-link aan op zijn e-mailadres en ziet daarna al zijn
 * afspraken, tickets, zendingen en prijsalarmen op één plek. Sluit aan op de
 * token-aanpak van de andere modules. Self-built, geen plugin, geen WP-users.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Korte, ondertekende sessietoken voor een e-mailadres (24 u geldig). */
function ekinese_account_sign( $email, $ts ) {
	return hash_hmac( 'sha256', strtolower( $email ) . '|' . $ts, wp_salt( 'auth' ) );
}

function ekinese_account_make_token( $email ) {
	$ts  = time();
	$sig = ekinese_account_sign( $email, $ts );
	return rtrim( strtr( base64_encode( $email . '|' . $ts . '|' . $sig ), '+/', '-_' ), '=' );
}

/**
 * Token valideren → e-mailadres of false.
 *
 * @return string|false
 */
function ekinese_account_verify_token( $token ) {
	$raw   = base64_decode( strtr( $token, '-_', '+/' ), true );
	if ( ! $raw ) {
		return false;
	}
	$parts = explode( '|', $raw );
	if ( count( $parts ) !== 3 ) {
		return false;
	}
	list( $email, $ts, $sig ) = $parts;
	if ( ! hash_equals( ekinese_account_sign( $email, $ts ), $sig ) ) {
		return false;
	}
	if ( time() - (int) $ts > DAY_IN_SECONDS ) {
		return false; // verlopen
	}
	return is_email( $email ) ? $email : false;
}

/* =====================================================================
   REST – magic-link aanvragen + accountdata ophalen
===================================================================== */
function ekinese_account_rest() {
	register_rest_route( 'ekinese/v1', '/account/login', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_account_request_link',
	) );
	register_rest_route( 'ekinese/v1', '/account/data', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_account_data',
	) );
}
add_action( 'rest_api_init', 'ekinese_account_rest' );

function ekinese_account_request_link( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'account' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid', __( 'Vul een geldig e-mailadres in.', 'ekinese' ), array( 'status' => 400 ) );
	}
	// Optionele bestemming (bv. /app/ voor het PWA-widget); alleen veilige paden.
	$redirect = '/mijn-xgoud/';
	$rin      = (string) $req->get_param( 'redirect' );
	if ( $rin && '/' === $rin[0] && false === strpos( $rin, '//' ) && false === strpos( $rin, ' ' ) ) {
		$redirect = '/' . trim( $rin, '/' ) . '/';
	}
	$link = home_url( $redirect . '?token=' . ekinese_account_make_token( $email ) );
	wp_mail(
		$email,
		__( 'Uw inloglink voor Mijn XGOUD', 'ekinese' ),
		sprintf( "Hallo,\n\nGebruik onderstaande link om uw overzicht te openen (24 uur geldig):\n%s\n\nNiet zelf aangevraagd? Negeer dan deze e-mail.\n\nXGOUD", $link )
	);
	// We onthullen nooit of het adres bestaat (privacy/enumeratie).
	return rest_ensure_response( array( 'ok' => true, 'message' => __( 'Als dit adres bij ons bekend is, ontvangt u een inloglink.', 'ekinese' ) ) );
}

/** Alle posts van een bepaald type met een e-mail-meta verzamelen. */
function ekinese_account_collect( $type, $email, $fields ) {
	$posts = get_posts( array(
		'post_type'   => $type,
		'numberposts' => 50,
		'post_status' => 'publish',
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => array( array( 'key' => 'email', 'value' => $email ) ),
	) );
	$out = array();
	foreach ( $posts as $p ) {
		$row = array( '_id' => $p->ID, 'date' => get_the_date( 'Y-m-d', $p ) );
		foreach ( $fields as $f ) {
			$v = get_post_meta( $p->ID, $f, true );
			// Lege meta de bestaande fallback (bijv. post-datum) niet laten overschrijven.
			if ( '' !== $v || ! isset( $row[ $f ] ) ) {
				$row[ $f ] = $v;
			}
		}
		$out[] = $row;
	}
	return $out;
}

function ekinese_account_data( WP_REST_Request $req ) {
	$email = ekinese_account_verify_token( (string) $req->get_param( 'token' ) );
	if ( ! $email ) {
		return new WP_Error( 'unauthorized', __( 'Ongeldige of verlopen link.', 'ekinese' ), array( 'status' => 401 ) );
	}
	$data = array(
		'email'        => $email,
		'appointments' => ekinese_account_collect( 'xg_appointment', $email, array( 'date', 'time', 'service', 'status', 'proforma' ) ),
		'tickets'      => ekinese_account_collect( 'xg_ticket', $email, array( 'reference', 'subject', 'status' ) ),
		'pickups'      => ekinese_account_collect( 'xg_pickup', $email, array( 'reference', 'status' ) ),
		'alerts'       => ekinese_account_collect( 'xg_price_alert', $email, array( 'metal', 'direction', 'target', 'active' ) ),
	);
	/** Modules (rewards, loterij, portfolio) kunnen het overzicht uitbreiden. */
	$data = apply_filters( 'ekinese_account_data', $data, $email );
	return rest_ensure_response( $data );
}

/* =====================================================================
   FRONT-END – blok ekinese/account (mount-punt voor account.js)
===================================================================== */
function ekinese_register_account_block() {
	register_block_type( 'ekinese/account', array( 'render_callback' => 'ekinese_render_account' ) );
}
add_action( 'init', 'ekinese_register_account_block' );

function ekinese_render_account() {
	$social = function_exists( 'ekinese_oauth_buttons' ) ? ekinese_oauth_buttons() : '';
	return '<div class="xg-account" data-rest-login="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/account/login' ) ) ) . '" data-rest-data="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/account/data' ) ) ) . '"><div class="xg-account-login"><h2>Mijn XGOUD</h2><p>Vul uw e-mailadres in en ontvang een inloglink.</p><form class="xg-account-form"><input type="email" name="email" placeholder="uw@email.nl" required><button type="submit" class="xg-final-btn">Stuur inloglink</button></form>' . $social . '<div class="xg-account-msg" role="status"></div></div><div class="xg-account-dash" hidden></div></div>';
}

/** account.js laden waar het blok staat. */
function ekinese_account_assets() {
	if ( ! is_singular() || ! has_block( 'ekinese/account' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/account.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-account', get_theme_file_uri( 'assets/js/account.js' ), array(), (string) filemtime( $js ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_account_assets' );

/** Mijn-XGOUD pagina niet indexeren. */
add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular() && has_block( 'ekinese/account' ) ) {
		$r['noindex'] = true;
	}
	return $r;
} );
