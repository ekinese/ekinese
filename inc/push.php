<?php
/**
 * XGOUD Web-Push — meldingen rechtstreeks op het toestel, gekoppeld aan
 * ekinese_notify() (overboden, veiling gewonnen, vraag beantwoord, afspraak …).
 *
 * Volledig self-built met native PHP (openssl + hash_hkdf): VAPID (RFC 8292) en
 * payload-encryptie aes128gcm (RFC 8291). Geen plugin/library.
 *
 * LET OP: web-push-crypto is exact-of-niets. Dit moet LIVE getest worden tegen
 * een echte browser/push-service; lokaal is het niet te verifiëren.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   HELPERS — base64url
===================================================================== */
function ekinese_b64url( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}
function ekinese_b64url_dec( $s ) {
	return base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) );
}

/* =====================================================================
   VAPID-sleutelpaar (eenmalig gegenereerd, in opties)
===================================================================== */
function ekinese_vapid_keys() {
	$keys = get_option( 'xg_vapid', array() );
	if ( ! empty( $keys['public'] ) && ! empty( $keys['private'] ) ) {
		return $keys;
	}
	if ( ! function_exists( 'openssl_pkey_new' ) ) {
		return array();
	}
	$res = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	if ( ! $res ) {
		return array();
	}
	openssl_pkey_export( $res, $pem );
	$d = openssl_pkey_get_details( $res );
	$x = str_pad( $d['ec']['x'], 32, "\x00", STR_PAD_LEFT );
	$y = str_pad( $d['ec']['y'], 32, "\x00", STR_PAD_LEFT );
	$keys = array(
		'private'    => $pem,                                  // PEM (server)
		'public'     => ekinese_b64url( "\x04" . $x . $y ),    // applicationServerKey (client)
		'public_raw' => "\x04" . $x . $y,
	);
	update_option( 'xg_vapid', $keys, false );
	return $keys;
}

/* =====================================================================
   ABONNEMENTEN (xg_pushsub)
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_pushsub', array(
		'labels'  => array( 'name' => __( 'Push-abonnementen', 'ekinese' ), 'singular_name' => __( 'Push-abonnement', 'ekinese' ) ),
		'public'  => false, 'show_ui' => false, 'supports' => array( 'title' ),
	) );
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/push/key', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$k = ekinese_vapid_keys();
			return rest_ensure_response( array( 'key' => $k['public'] ?? '' ) );
		},
	) );
	register_rest_route( 'ekinese/v1', '/push/subscribe', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_push_subscribe',
	) );
} );

function ekinese_push_subscribe( WP_REST_Request $req ) {
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $req->get_param( 'token' ) ) : '';
	if ( ! $email ) {
		return new WP_Error( 'auth', 'Login vereist.', array( 'status' => 401 ) );
	}
	$sub = $req->get_param( 'subscription' );
	if ( ! is_array( $sub ) || empty( $sub['endpoint'] ) || empty( $sub['keys']['p256dh'] ) || empty( $sub['keys']['auth'] ) ) {
		return new WP_Error( 'invalid', 'Ongeldig abonnement.', array( 'status' => 400 ) );
	}
	$endpoint = esc_url_raw( $sub['endpoint'] );
	// Bestaand abonnement met dit endpoint bijwerken i.p.v. dupliceren.
	$exist = get_posts( array( 'post_type' => 'xg_pushsub', 'numberposts' => 1, 'post_status' => 'publish', 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'endpoint', 'value' => $endpoint ) ) ) );
	$id    = $exist ? (int) $exist[0] : wp_insert_post( array( 'post_type' => 'xg_pushsub', 'post_status' => 'publish', 'post_title' => 'Push ' . substr( md5( $endpoint ), 0, 8 ) ) );
	if ( ! $id || is_wp_error( $id ) ) {
		return new WP_Error( 'save', 'Opslaan mislukt.', array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'email', sanitize_email( $email ) );
	update_post_meta( $id, 'endpoint', $endpoint );
	update_post_meta( $id, 'p256dh', sanitize_text_field( $sub['keys']['p256dh'] ) );
	update_post_meta( $id, 'auth', sanitize_text_field( $sub['keys']['auth'] ) );
	return rest_ensure_response( array( 'ok' => true ) );
}

/* =====================================================================
   VERSTUREN — VAPID + aes128gcm
===================================================================== */
function ekinese_p256_pub_from_raw( $raw ) {
	$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $raw;
	$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	return openssl_pkey_get_public( $pem );
}

/** DER ECDSA-handtekening → raw 64-byte (R||S) voor JWS. */
function ekinese_der2raw( $der ) {
	$off = 0;
	if ( "\x30" !== $der[ $off++ ] ) {
		return '';
	}
	$len = ord( $der[ $off++ ] );
	if ( $len & 0x80 ) {
		$n = $len & 0x7f;
		while ( $n-- > 0 ) {
			$off++;
		}
	}
	$read = function () use ( $der, &$off ) {
		$off++; // 0x02
		$l = ord( $der[ $off++ ] );
		$v = substr( $der, $off, $l );
		$off += $l;
		return ltrim( $v, "\x00" );
	};
	$r = $read();
	$s = $read();
	return str_pad( $r, 32, "\x00", STR_PAD_LEFT ) . str_pad( $s, 32, "\x00", STR_PAD_LEFT );
}

/** VAPID Authorization-header voor een endpoint-origin. */
function ekinese_vapid_header( $endpoint ) {
	$keys = ekinese_vapid_keys();
	if ( empty( $keys['private'] ) ) {
		return '';
	}
	$p    = wp_parse_url( $endpoint );
	$aud  = $p['scheme'] . '://' . $p['host'];
	$head = ekinese_b64url( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
	$body = ekinese_b64url( wp_json_encode( array(
		'aud' => $aud,
		'exp' => time() + 12 * HOUR_IN_SECONDS,
		'sub' => 'mailto:' . ( function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' ) ),
	) ) );
	$signing = $head . '.' . $body;
	$der     = '';
	if ( ! openssl_sign( $signing, $der, $keys['private'], OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}
	$jwt = $signing . '.' . ekinese_b64url( ekinese_der2raw( $der ) );
	return 'vapid t=' . $jwt . ', k=' . $keys['public'];
}

/**
 * Stuur één push (aes128gcm). Geeft de HTTP-status of 0.
 *
 * @return int
 */
function ekinese_push_send( $endpoint, $p256dh_b64, $auth_b64, $payload ) {
	if ( ! function_exists( 'openssl_pkey_derive' ) || ! function_exists( 'hash_hkdf' ) ) {
		return 0;
	}
	$ua_pub_raw = ekinese_b64url_dec( $p256dh_b64 );
	$auth       = ekinese_b64url_dec( $auth_b64 );
	$ua_pub     = ekinese_p256_pub_from_raw( $ua_pub_raw );
	if ( ! $ua_pub ) {
		return 0;
	}
	// Ephemeral server-sleutel.
	$as     = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	$asd    = openssl_pkey_get_details( $as );
	$as_pub = "\x04" . str_pad( $asd['ec']['x'], 32, "\x00", STR_PAD_LEFT ) . str_pad( $asd['ec']['y'], 32, "\x00", STR_PAD_LEFT );
	$shared = openssl_pkey_derive( $ua_pub, $as );
	if ( ! $shared ) {
		return 0;
	}
	// RFC 8291.
	$ikm   = hash_hkdf( 'sha256', $shared, 32, "WebPush: info\x00" . $ua_pub_raw . $as_pub, $auth );
	$salt  = random_bytes( 16 );
	$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt );
	$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt );
	$tag   = '';
	$cipher = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
	if ( false === $cipher ) {
		return 0;
	}
	$bodyenc = $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_pub . $cipher . $tag;

	$vapid = ekinese_vapid_header( $endpoint );
	if ( '' === $vapid ) {
		return 0;
	}
	$resp = wp_remote_post( $endpoint, array(
		'timeout' => 15,
		'headers' => array(
			'Authorization'    => $vapid,
			'Content-Encoding' => 'aes128gcm',
			'Content-Type'     => 'application/octet-stream',
			'TTL'              => '86400',
		),
		'body'    => $bodyenc,
	) );
	if ( is_wp_error( $resp ) ) {
		return 0;
	}
	return (int) wp_remote_retrieve_response_code( $resp );
}

/** Alle abonnementen van een e-mailadres een melding sturen. */
function ekinese_push_dispatch( $email, $title, $message, $url = '' ) {
	$subs = get_posts( array( 'post_type' => 'xg_pushsub', 'numberposts' => -1, 'post_status' => 'publish', 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'email', 'value' => sanitize_email( $email ) ) ) ) );
	if ( ! $subs ) {
		return;
	}
	$payload = wp_json_encode( array( 'title' => 'XGOUD — ' . wp_strip_all_tags( $title ), 'body' => wp_strip_all_tags( $message ), 'url' => $url ?: home_url( '/app/' ) ) );
	foreach ( $subs as $sid ) {
		$code = ekinese_push_send(
			get_post_meta( $sid, 'endpoint', true ),
			get_post_meta( $sid, 'p256dh', true ),
			get_post_meta( $sid, 'auth', true ),
			$payload
		);
		// Opgeruimd: verlopen/ongeldige abonnementen verwijderen.
		if ( 404 === $code || 410 === $code ) {
			wp_delete_post( $sid, true );
		}
	}
}

// Koppel aan elke melding.
add_action( 'ekinese_notified', function ( $email, $title, $message, $url ) {
	ekinese_push_dispatch( $email, $title, $message, $url );
}, 10, 4 );

/* =====================================================================
   FRONTEND — abonneren (op /app/ en /mijn-xgoud/)
===================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ( ! has_block( 'ekinese/widget' ) && ! has_block( 'ekinese/account' ) ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/push.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-push', get_theme_file_uri( 'assets/js/push.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-push', 'XGPush', array(
			'key'       => esc_url_raw( rest_url( 'ekinese/v1/push/key' ) ),
			'subscribe' => esc_url_raw( rest_url( 'ekinese/v1/push/subscribe' ) ),
		) );
	}
} );
