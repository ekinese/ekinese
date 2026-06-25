<?php
/**
 * XGOUD Portfolio & wishlist.
 *
 * Klanten beheren hun edelmetaalbezit: per stuk product + inkoopprijs. Het
 * portfolio toont de actuele waarde met winst/verlies (op basis van de live
 * spotkoers). Vanuit het portfolio kan men verkopen (afspraak) of per product
 * een prijsalarm zetten. Daarnaast een wishlist (gewenste producten) die de
 * marktplaats-matching voedt (inc/marketplace.php). E-mailgebaseerd, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Holdings + wishlist CPT's (intern). */
function ekinese_register_portfolio() {
	register_post_type( 'xg_holding', array(
		'labels'  => array( 'name' => __( 'Portfolio', 'ekinese' ), 'singular_name' => __( 'Bezit', 'ekinese' ), 'menu_name' => __( 'Portfolio', 'ekinese' ) ),
		'public'  => false, 'show_ui' => true, 'menu_icon' => 'dashicons-chart-line', 'supports' => array( 'title' ),
	) );
	register_post_type( 'xg_wishlist', array(
		'labels'  => array( 'name' => __( 'Wishlists', 'ekinese' ), 'singular_name' => __( 'Wens', 'ekinese' ), 'menu_name' => __( 'Wishlists', 'ekinese' ) ),
		'public'  => false, 'show_ui' => true, 'menu_icon' => 'dashicons-heart', 'supports' => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_portfolio' );

/** Actuele waarde van een holding (fijn gewicht × aantal × spot). */
function ekinese_holding_value( $id ) {
	$metal = get_post_meta( $id, 'metal', true );
	$fine  = (float) get_post_meta( $id, 'fine_weight', true );
	$qty   = max( 1, (int) get_post_meta( $id, 'qty', true ) );
	$spot  = function_exists( 'ekinese_metal_spot' ) ? ekinese_metal_spot( $metal ) : 0;
	return $fine * $qty * $spot;
}

/** Portfolio van een e-mailadres met waarde + winst/verlies. */
function ekinese_portfolio_for( $email ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return array();
	}
	$items = get_posts( array(
		'post_type'   => 'xg_holding',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array( array( 'key' => 'email', 'value' => $email ) ),
	) );
	$out = array();
	foreach ( $items as $p ) {
		$id    = $p->ID;
		$buy   = (float) get_post_meta( $id, 'purchase_price', true );
		$value = ekinese_holding_value( $id );
		// Holdings zonder metaal/gewicht (bijv. automatisch uit een veiling) tonen
		// we neutraal op de inkoopprijs i.p.v. als 100% verlies.
		$metal = get_post_meta( $id, 'metal', true );
		$fine  = (float) get_post_meta( $id, 'fine_weight', true );
		if ( $value <= 0 && $buy > 0 && ( ! $metal || $fine <= 0 ) ) {
			$value = $buy;
		}
		$out[] = array(
			'id'       => $id,
			'name'     => $p->post_title,
			'metal'    => get_post_meta( $id, 'metal', true ),
			'qty'      => (int) get_post_meta( $id, 'qty', true ),
			'fine'     => (float) get_post_meta( $id, 'fine_weight', true ),
			'purchase' => $buy,
			'value'    => round( $value, 2 ),
			'gain'     => round( $value - $buy, 2 ),
			'gain_pct' => $buy > 0 ? round( ( $value - $buy ) / $buy * 100, 1 ) : 0,
		);
	}
	return $out;
}

/* =====================================================================
   REST – portfolio + wishlist beheren (account-token vereist)
===================================================================== */
function ekinese_portfolio_rest() {
	$auth = function ( WP_REST_Request $r ) {
		return function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $r->get_param( 'token' ) ) : false;
	};
	register_rest_route( 'ekinese/v1', '/portfolio', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) use ( $auth ) {
			$email = $auth( $r );
			if ( ! $email ) {
				return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
			}
			$items = ekinese_portfolio_for( $email );
			$total = array_sum( array_column( $items, 'value' ) );
			$cost  = array_sum( array_column( $items, 'purchase' ) );
			return rest_ensure_response( array(
				'items'    => $items,
				'total'    => round( $total, 2 ),
				'cost'     => round( $cost, 2 ),
				'gain'     => round( $total - $cost, 2 ),
				'gain_pct' => $cost > 0 ? round( ( $total - $cost ) / $cost * 100, 1 ) : 0,
			) );
		},
	) );
	register_rest_route( 'ekinese/v1', '/portfolio/add', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) use ( $auth ) {
			$email = $auth( $r );
			if ( ! $email ) {
				return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
			}
			$id = wp_insert_post( array(
				'post_type'   => 'xg_holding',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( (string) $r->get_param( 'name' ) ) ?: 'Bezit',
			) );
			update_post_meta( $id, 'email', $email );
			foreach ( array( 'metal', 'fine_weight', 'qty', 'purchase_price', 'purchase_date' ) as $f ) {
				update_post_meta( $id, $f, sanitize_text_field( (string) $r->get_param( $f ) ) );
			}
			return rest_ensure_response( array( 'ok' => true, 'id' => $id ) );
		},
	) );
	register_rest_route( 'ekinese/v1', '/portfolio/(?P<id>\d+)', array(
		'methods'             => 'DELETE',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) use ( $auth ) {
			$email = $auth( $r );
			$id    = (int) $r['id'];
			if ( ! $email || get_post_meta( $id, 'email', true ) !== $email ) {
				return new WP_Error( 'auth', 'forbidden', array( 'status' => 403 ) );
			}
			wp_delete_post( $id, true );
			return rest_ensure_response( array( 'ok' => true ) );
		},
	) );
	register_rest_route( 'ekinese/v1', '/wishlist/add', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) use ( $auth ) {
			$email = $auth( $r );
			if ( ! $email ) {
				return new WP_Error( 'auth', 'login', array( 'status' => 401 ) );
			}
			$id = wp_insert_post( array(
				'post_type'   => 'xg_wishlist',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( (string) $r->get_param( 'name' ) ) ?: 'Wens',
			) );
			update_post_meta( $id, 'email', $email );
			update_post_meta( $id, 'product_slug', sanitize_title( (string) $r->get_param( 'product_slug' ) ) );
			update_post_meta( $id, 'max_price', sanitize_text_field( (string) $r->get_param( 'max_price' ) ) );
			do_action( 'ekinese_wishlist_added', $id, $email );
			return rest_ensure_response( array( 'ok' => true, 'id' => $id ) );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_portfolio_rest' );

/* =====================================================================
   ACCOUNT-INTEGRATIE
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$items = ekinese_portfolio_for( $email );
	$total = array_sum( array_column( $items, 'value' ) );
	$cost  = array_sum( array_column( $items, 'purchase' ) );
	$data['portfolio'] = array(
		'items'    => $items,
		'total'    => round( $total, 2 ),
		'gain'     => round( $total - $cost, 2 ),
		'gain_pct' => $cost > 0 ? round( ( $total - $cost ) / $cost * 100, 1 ) : 0,
	);
	return $data;
}, 12, 2 );

/* ---- Admin metabox (handmatig bezit) ---- */
function ekinese_holding_metabox() {
	add_meta_box( 'xg_holding_meta', __( 'Bezit', 'ekinese' ), function ( $post ) {
		wp_nonce_field( 'xg_hold_save', 'xg_hold_nonce' );
		echo '<table class="form-table">';
		foreach ( array( 'email' => 'E-mail', 'metal' => 'Metaal', 'fine_weight' => 'Fijn gewicht (g)', 'qty' => 'Aantal', 'purchase_price' => 'Inkoopprijs (€)', 'purchase_date' => 'Inkoopdatum' ) as $k => $lbl ) {
			echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="text" name="xgh_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
		}
		echo '</table>';
	}, 'xg_holding', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_holding_metabox' );

function ekinese_holding_save( $post_id ) {
	if ( ! isset( $_POST['xg_hold_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_hold_nonce'] ), 'xg_hold_save' ) ) {
		return;
	}
	foreach ( array( 'email', 'metal', 'fine_weight', 'qty', 'purchase_price', 'purchase_date' ) as $f ) {
		if ( isset( $_POST[ 'xgh_' . $f ] ) ) {
			update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xgh_' . $f ] ) ) );
		}
	}
}
add_action( 'save_post_xg_holding', 'ekinese_holding_save' );
