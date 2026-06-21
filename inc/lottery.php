<?php
/**
 * XGOUD Loterij – maandelijkse verloting van producten.
 *
 * Beheerder maakt een loterij met een prijs (product) en trekkingsdatum.
 * Ingelogde gebruikers doen mee door punten in te zetten (inc/rewards.php);
 * voorwaarde is voldoende saldo. Elke inzet = één lot. Winnaar wordt willekeurig
 * getrokken. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Loterij-CPT (publiek: overzichtspagina mogelijk). */
function ekinese_register_lottery_cpt() {
	register_post_type( 'xg_lottery', array(
		'labels'       => array( 'name' => __( 'Loterijen', 'ekinese' ), 'singular_name' => __( 'Loterij', 'ekinese' ), 'menu_name' => __( 'Loterijen', 'ekinese' ) ),
		'public'       => true,
		'has_archive'  => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-tickets',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'rewrite'      => array( 'slug' => 'loterij' ),
	) );
	foreach ( array( 'prize', 'draw_date', 'entry_cost', 'status', 'winner' ) as $f ) {
		register_post_meta( 'xg_lottery', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	}
	// Lot (intern).
	register_post_type( 'xg_lottery_entry', array(
		'labels'  => array( 'name' => __( 'Loten', 'ekinese' ) ),
		'public'  => false,
		'show_ui' => true,
		'supports'=> array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_lottery_cpt' );

/** Loopt de loterij nog? */
function ekinese_lottery_is_open( $id ) {
	if ( get_post_meta( $id, 'status', true ) === 'drawn' ) {
		return false;
	}
	$d = get_post_meta( $id, 'draw_date', true );
	return $d ? ( strtotime( $d ) > time() ) : true;
}

/** Aantal loten in een loterij. */
function ekinese_lottery_ticket_count( $id ) {
	$q = new WP_Query( array(
		'post_type'   => 'xg_lottery_entry',
		'post_status' => 'publish',
		'fields'      => 'ids',
		'posts_per_page' => 1,
		'meta_query'  => array( array( 'key' => 'lottery', 'value' => $id ) ),
	) );
	return (int) $q->found_posts;
}

/* =====================================================================
   REST – meedoen
===================================================================== */
function ekinese_lottery_rest() {
	register_rest_route( 'ekinese/v1', '/lottery/(?P<id>\d+)/enter', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_lottery_enter',
	) );
}
add_action( 'rest_api_init', 'ekinese_lottery_rest' );

function ekinese_lottery_enter( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( get_post_type( $id ) !== 'xg_lottery' ) {
		return new WP_Error( 'notfound', __( 'Loterij niet gevonden.', 'ekinese' ), array( 'status' => 404 ) );
	}
	if ( ! ekinese_lottery_is_open( $id ) ) {
		return new WP_Error( 'closed', __( 'Deze loterij is gesloten.', 'ekinese' ), array( 'status' => 409 ) );
	}
	// E-mail uit account-token (alleen ingelogde gebruikers).
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $req->get_param( 'token' ) ) : sanitize_email( (string) $req->get_param( 'email' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'auth', __( 'Log in om mee te doen.', 'ekinese' ), array( 'status' => 401 ) );
	}
	$cost = max( 1, (int) get_post_meta( $id, 'entry_cost', true ) );
	if ( ! function_exists( 'ekinese_points_balance' ) || ekinese_points_balance( $email ) < $cost ) {
		return new WP_Error( 'points', sprintf( __( 'U heeft minimaal %d punten nodig om mee te doen.', 'ekinese' ), $cost ), array( 'status' => 403 ) );
	}
	// Punten inzetten + lot aanmaken.
	ekinese_award_points( $email, -$cost, 'lottery', 'lot#' . $id );
	$lot = wp_insert_post( array(
		'post_type'   => 'xg_lottery_entry',
		'post_status' => 'publish',
		'post_title'  => 'Lot ' . get_the_title( $id ) . ' · ' . $email,
	) );
	update_post_meta( $lot, 'lottery', $id );
	update_post_meta( $lot, 'email', $email );

	return rest_ensure_response( array( 'ok' => true, 'tickets' => ekinese_lottery_ticket_count( $id ), 'balance' => ekinese_points_balance( $email ) ) );
}

/* =====================================================================
   TREKKING (admin)
===================================================================== */
function ekinese_lottery_draw( $id ) {
	$lots = get_posts( array(
		'post_type'   => 'xg_lottery_entry',
		'numberposts' => -1,
		'post_status' => 'publish',
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'lottery', 'value' => $id ) ),
	) );
	if ( ! $lots ) {
		return new WP_Error( 'empty', __( 'Geen deelnemers.', 'ekinese' ) );
	}
	$win   = $lots[ wp_rand( 0, count( $lots ) - 1 ) ];
	$email = get_post_meta( $win, 'email', true );
	update_post_meta( $id, 'winner', $email );
	update_post_meta( $id, 'status', 'drawn' );
	if ( $email && is_email( $email ) ) {
		wp_mail( $email, sprintf( __( 'Gefeliciteerd! U won de loterij: %s', 'ekinese' ), get_the_title( $id ) ), sprintf( "Beste klant,\n\nU heeft de XGOUD-loterij '%s' gewonnen! Wij nemen contact met u op over uw prijs: %s.\n\nXGOUD", get_the_title( $id ), get_post_meta( $id, 'prize', true ) ) );
	}
	return $email;
}

/* =====================================================================
   ADMIN – metabox (prijs, datum, inzet) + trekkingsknop
===================================================================== */
function ekinese_lottery_metabox() {
	add_meta_box( 'xg_lottery_meta', __( 'Loterij', 'ekinese' ), 'ekinese_lottery_metabox_html', 'xg_lottery', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_lottery_metabox' );

function ekinese_lottery_metabox_html( $post ) {
	wp_nonce_field( 'xg_lottery_save', 'xg_lottery_nonce' );
	$fields = array( 'prize' => 'Prijs (product)', 'draw_date' => 'Trekkingsdatum (Y-m-d)', 'entry_cost' => 'Inzet (punten)' );
	echo '<table class="form-table">';
	foreach ( $fields as $k => $lbl ) {
		echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="text" name="xgl_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="widefat"></td></tr>';
	}
	echo '</table>';
	echo '<p><strong>' . esc_html__( 'Loten:', 'ekinese' ) . '</strong> ' . esc_html( (string) ekinese_lottery_ticket_count( $post->ID ) ) . '</p>';
	$winner = get_post_meta( $post->ID, 'winner', true );
	if ( $winner ) {
		echo '<p><strong>' . esc_html__( 'Winnaar:', 'ekinese' ) . '</strong> ' . esc_html( $winner ) . '</p>';
	} else {
		echo '<p><label><input type="checkbox" name="xgl_draw" value="1"> ' . esc_html__( 'Nu trekken bij opslaan', 'ekinese' ) . '</label></p>';
	}
}

function ekinese_lottery_save( $post_id ) {
	if ( ! isset( $_POST['xg_lottery_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_lottery_nonce'] ), 'xg_lottery_save' ) ) {
		return;
	}
	foreach ( array( 'prize', 'draw_date', 'entry_cost' ) as $k ) {
		if ( isset( $_POST[ 'xgl_' . $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ 'xgl_' . $k ] ) ) );
		}
	}
	if ( ! empty( $_POST['xgl_draw'] ) && ! get_post_meta( $post_id, 'winner', true ) ) {
		ekinese_lottery_draw( $post_id );
	}
}
add_action( 'save_post_xg_lottery', 'ekinese_lottery_save', 20 );

/* =====================================================================
   ACCOUNT-INTEGRATIE – lopende loterijen tonen
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$open = get_posts( array(
		'post_type'   => 'xg_lottery',
		'numberposts' => 10,
		'post_status' => 'publish',
		'meta_query'  => array( 'relation' => 'OR', array( 'key' => 'status', 'value' => 'drawn', 'compare' => '!=' ), array( 'key' => 'status', 'compare' => 'NOT EXISTS' ) ),
	) );
	$data['lotteries'] = array_map( function ( $p ) {
		return array(
			'id'      => $p->ID,
			'title'   => $p->post_title,
			'prize'   => get_post_meta( $p->ID, 'prize', true ),
			'cost'    => (int) get_post_meta( $p->ID, 'entry_cost', true ),
			'draw'    => get_post_meta( $p->ID, 'draw_date', true ),
			'tickets' => ekinese_lottery_ticket_count( $p->ID ),
		);
	}, $open );
	return $data;
}, 11, 2 );
