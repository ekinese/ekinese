<?php
/**
 * XGOUD Loyaliteit & veilingen.
 *
 *  - Trouwprogramma: terugkerende verkopers bouwen een status op (op basis van
 *    afgeronde afspraken per e-mailadres) met een oplopende trouwbonus.
 *  - Veilingen: lichte CPT xg_auction met huidig bod + sluitingstijd en een
 *    REST-bod-endpoint. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   TROUWPROGRAMMA
===================================================================== */
/** Tiers: drempel (aantal afgeronde verkopen) → [label, bonus%]. */
function ekinese_loyalty_tiers() {
	return array(
		0 => array( 'label' => 'Brons',   'bonus' => 0.00 ),
		2 => array( 'label' => 'Zilver',  'bonus' => 0.01 ),
		5 => array( 'label' => 'Goud',    'bonus' => 0.02 ),
		10 => array( 'label' => 'Platina', 'bonus' => 0.03 ),
	);
}

/** Aantal afgeronde afspraken voor een e-mailadres. */
function ekinese_loyalty_completed( $email ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return 0;
	}
	$q = new WP_Query( array(
		'post_type'      => 'xg_appointment',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => 'email', 'value' => $email ),
			array( 'key' => 'status', 'value' => array( 'completed', 'paid', 'afgerond', 'uitbetaald' ), 'compare' => 'IN' ),
		),
	) );
	return (int) $q->found_posts;
}

/**
 * Status + bonus voor een e-mailadres.
 *
 * @return array { label, bonus, count, next_at, to_next }
 */
function ekinese_loyalty_status( $email ) {
	$count = ekinese_loyalty_completed( $email );
	$tiers = ekinese_loyalty_tiers();
	ksort( $tiers );
	$current = $tiers[0];
	$next_at = null;
	foreach ( $tiers as $threshold => $tier ) {
		if ( $count >= $threshold ) {
			$current = $tier;
		} elseif ( null === $next_at ) {
			$next_at = $threshold;
		}
	}
	return array(
		'label'   => $current['label'],
		'bonus'   => $current['bonus'],
		'count'   => $count,
		'next_at' => $next_at,
		'to_next' => null !== $next_at ? max( 0, $next_at - $count ) : 0,
	);
}

/** REST: status opvragen via account-token (privacyveilig). */
function ekinese_loyalty_rest() {
	register_rest_route( 'ekinese/v1', '/loyalty', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $req->get_param( 'token' ) ) : false;
			if ( ! $email ) {
				return new WP_Error( 'unauthorized', __( 'Ongeldige link.', 'ekinese' ), array( 'status' => 401 ) );
			}
			return rest_ensure_response( ekinese_loyalty_status( $email ) );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_loyalty_rest' );

/* =====================================================================
   VEILINGEN
===================================================================== */
function ekinese_register_auction_cpt() {
	register_post_type( 'xg_auction', array(
		'labels'       => array( 'name' => __( 'Veilingen', 'ekinese' ), 'singular_name' => __( 'Veiling', 'ekinese' ), 'menu_name' => __( 'Veilingen', 'ekinese' ) ),
		'public'       => true,
		'has_archive'  => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-hammer',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'rewrite'      => array( 'slug' => 'veilingen' ),
	) );
	foreach ( array( 'start_price', 'current_bid', 'bid_count', 'ends', 'leader', 'charity_pct', 'charity_project', 'status' ) as $f ) {
		register_post_meta( 'xg_auction', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	}
	// winner_email/winner_name bewust NIET in REST (privacy).
}
add_action( 'init', 'ekinese_register_auction_cpt' );

/** Loopt de veiling nog? */
function ekinese_auction_is_live( $id ) {
	$ends = get_post_meta( $id, 'ends', true );
	return $ends ? ( strtotime( $ends ) > time() ) : false;
}

/** REST: bod uitbrengen. */
function ekinese_auction_rest() {
	register_rest_route( 'ekinese/v1', '/auction/(?P<id>\d+)/bid', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_auction_bid',
	) );
}
add_action( 'rest_api_init', 'ekinese_auction_rest' );

function ekinese_auction_bid( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'bid' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$id = (int) $req['id'];
	if ( get_post_type( $id ) !== 'xg_auction' ) {
		return new WP_Error( 'notfound', __( 'Veiling niet gevonden.', 'ekinese' ), array( 'status' => 404 ) );
	}
	if ( ! ekinese_auction_is_live( $id ) ) {
		return new WP_Error( 'closed', __( 'Deze veiling is gesloten.', 'ekinese' ), array( 'status' => 409 ) );
	}
	if ( ! empty( $req->get_param( 'website' ) ) ) { // honeypot
		return rest_ensure_response( array( 'ok' => true ) );
	}
	$email  = sanitize_email( (string) $req->get_param( 'email' ) );
	$name   = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$amount = (float) $req->get_param( 'amount' );
	if ( ! $email || ! is_email( $email ) || $amount <= 0 ) {
		return new WP_Error( 'invalid', __( 'Controleer uw e-mail en bod.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$current = (float) ( get_post_meta( $id, 'current_bid', true ) ?: get_post_meta( $id, 'start_price', true ) );
	$min     = $current + max( 1, round( $current * 0.02, 2 ) ); // minimaal +2%
	if ( $amount < $min ) {
		return new WP_Error( 'too_low', sprintf( __( 'Minimaal bod is € %.2f.', 'ekinese' ), $min ), array( 'status' => 400 ) );
	}
	update_post_meta( $id, 'current_bid', $amount );
	update_post_meta( $id, 'leader', md5( $email ) ); // pseudoniem voor publieke weergave
	update_post_meta( $id, 'winner_email', $email );  // privé: voor winnaarsmelding (bod is bindend)
	update_post_meta( $id, 'winner_name', $name );
	update_post_meta( $id, 'bid_count', (int) get_post_meta( $id, 'bid_count', true ) + 1 );
	// Bevestiging — bod is bindend, geen herroeping.
	wp_mail( $email, __( 'Uw bod is geregistreerd', 'ekinese' ), sprintf( "Bedankt! Uw bod van € %.2f op '%s' is geregistreerd. Let op: een bod is bindend.\n\nXGOUD", $amount, get_the_title( $id ) ) );

	return rest_ensure_response( array( 'ok' => true, 'current_bid' => $amount, 'bid_count' => (int) get_post_meta( $id, 'bid_count', true ), 'min_next' => $amount + max( 1, round( $amount * 0.02, 2 ) ) ) );
}

/** Admin-metabox voor veilingvelden. */
function ekinese_auction_metabox() {
	add_meta_box( 'xg_auction_meta', __( 'Veilinggegevens', 'ekinese' ), function ( $post ) {
		wp_nonce_field( 'xg_auction_save', 'xg_auction_nonce' );
		$fields = array(
			'start_price'     => 'Startprijs (€)',
			'current_bid'     => 'Huidig bod (€)',
			'ends'            => 'Sluit op (Y-m-d H:i)',
			'charity_pct'     => 'Goede doel (% van de opbrengst)',
			'charity_project' => 'Goede-doel project',
		);
		echo '<table class="form-table">';
		foreach ( $fields as $k => $lbl ) {
			echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="text" name="xga_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
		}
		echo '<tr><th>Aantal biedingen</th><td>' . esc_html( (string) ( get_post_meta( $post->ID, 'bid_count', true ) ?: 0 ) ) . '</td></tr>';
		echo '<tr><th>Hoogste bieder</th><td>' . esc_html( get_post_meta( $post->ID, 'winner_name', true ) ?: '—' ) . ' &lt;' . esc_html( get_post_meta( $post->ID, 'winner_email', true ) ?: '—' ) . '&gt;</td></tr>';
		echo '</table>';
	}, 'xg_auction', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_auction_metabox' );

function ekinese_auction_save( $post_id ) {
	if ( ! isset( $_POST['xg_auction_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_auction_nonce'] ), 'xg_auction_save' ) ) {
		return;
	}
	foreach ( array( 'start_price', 'current_bid', 'ends', 'charity_pct', 'charity_project' ) as $k ) {
		if ( isset( $_POST[ 'xga_' . $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ 'xga_' . $k ] ) ) );
		}
	}
}
add_action( 'save_post_xg_auction', 'ekinese_auction_save' );

/* =====================================================================
   ACCOUNT-INTEGRATIE – trouwstatus + voortgang naar volgende tier
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$s     = ekinese_loyalty_status( $email );
	$tiers = ekinese_loyalty_tiers();
	ksort( $tiers );
	$thresholds = array_keys( $tiers );
	// Bepaal huidige drempel + volgende drempel voor een voortgangsbalk.
	$cur_thr = 0;
	foreach ( $thresholds as $thr ) {
		if ( $s['count'] >= $thr ) {
			$cur_thr = $thr;
		}
	}
	$next_thr = $s['next_at'];
	$progress = 100;
	if ( null !== $next_thr && $next_thr > $cur_thr ) {
		$progress = (int) round( ( $s['count'] - $cur_thr ) / ( $next_thr - $cur_thr ) * 100 );
	}
	$next_label = '';
	if ( null !== $next_thr && isset( $tiers[ $next_thr ] ) ) {
		$next_label = $tiers[ $next_thr ]['label'];
	}
	$data['loyalty'] = array(
		'tier'       => $s['label'],
		'bonus'      => round( $s['bonus'] * 100, 1 ),
		'completed'  => $s['count'],
		'to_next'    => $s['to_next'],
		'next_tier'  => $next_label,
		'progress'   => max( 0, min( 100, $progress ) ),
	);
	return $data;
}, 13, 2 );
