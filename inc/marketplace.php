<?php
/**
 * XGOUD Marktplaats – wishlist ↔ portfolio matching met commissie (escrow).
 *
 * Wanneer een gewenst product (wishlist) overeenkomt met het bezit (portfolio)
 * van een ándere klant, legt XGOUD een verbinding — zónder dat de klanten met
 * elkaar in contact komen. XGOUD is commissionair:
 *
 *   1. Match → verkoper krijgt (anonieme) vraag of hij wil verkopen.
 *   2. Koper doet een bod; commissie wordt berekend.
 *   3. Koper betaalt XGOUD, verkoper stuurt de waar naar XGOUD.
 *   4. XGOUD controleert → vrijgave: koper krijgt de waar, verkoper het geld.
 *
 * Beide partijen zien elkaars gegevens nooit. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Commissiepercentage (per kant of totaal). */
function ekinese_marketplace_commission_pct() {
	return (float) get_option( 'xg_marketplace_commission', 0.06 );
}

/** Deal-statussen. */
function ekinese_deal_statuses() {
	return array(
		'matched'        => __( 'Match gevonden', 'ekinese' ),
		'seller_ok'      => __( 'Verkoper akkoord', 'ekinese' ),
		'offer_made'     => __( 'Bod uitgebracht', 'ekinese' ),
		'accepted'       => __( 'Bod geaccepteerd', 'ekinese' ),
		'buyer_paid'     => __( 'Koper heeft betaald', 'ekinese' ),
		'goods_received' => __( 'Waar ontvangen', 'ekinese' ),
		'completed'      => __( 'Afgerond', 'ekinese' ),
		'cancelled'      => __( 'Geannuleerd', 'ekinese' ),
	);
}

function ekinese_register_marketplace() {
	register_post_type( 'xg_deal', array(
		'labels'    => array( 'name' => __( 'Marktplaats-deals', 'ekinese' ), 'singular_name' => __( 'Deal', 'ekinese' ), 'menu_name' => __( 'Marktplaats', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-randomize',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_marketplace' );

/**
 * Bij een nieuwe wishlist-wens: zoek matchend bezit van ándere klanten.
 *
 * @param int    $wish_id
 * @param string $buyer_email
 */
function ekinese_marketplace_match( $wish_id, $buyer_email ) {
	$slug = get_post_meta( $wish_id, 'product_slug', true );
	if ( ! $slug ) {
		return;
	}
	$holdings = get_posts( array(
		'post_type'   => 'xg_holding',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array( array( 'key' => 'product_slug', 'value' => $slug ) ),
	) );
	foreach ( $holdings as $hold ) {
		$seller = get_post_meta( $hold->ID, 'email', true );
		if ( ! $seller || strtolower( $seller ) === strtolower( $buyer_email ) ) {
			continue; // niet je eigen bezit
		}
		// Bestaat er al een open deal voor dit paar?
		$dup = get_posts( array(
			'post_type'   => 'xg_deal', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'publish',
			'meta_query'  => array( 'relation' => 'AND', array( 'key' => 'wishlist', 'value' => $wish_id ), array( 'key' => 'holding', 'value' => $hold->ID ) ),
		) );
		if ( $dup ) {
			continue;
		}
		$deal = wp_insert_post( array(
			'post_type'   => 'xg_deal',
			'post_status' => 'publish',
			'post_title'  => 'Deal · ' . get_the_title( $hold->ID ),
		) );
		update_post_meta( $deal, 'wishlist', $wish_id );
		update_post_meta( $deal, 'holding', $hold->ID );
		update_post_meta( $deal, 'buyer_email', $buyer_email );
		update_post_meta( $deal, 'seller_email', $seller );
		update_post_meta( $deal, 'product', get_the_title( $hold->ID ) );
		update_post_meta( $deal, 'token_seller', wp_generate_password( 20, false, false ) );
		update_post_meta( $deal, 'token_buyer', wp_generate_password( 20, false, false ) );
		ekinese_deal_set_status( $deal, 'matched' );

		// Verkoper anoniem benaderen.
		ekinese_deal_mail( $seller, __( 'Interesse in uw edelmetaal', 'ekinese' ), sprintf(
			"Beste klant,\n\nVia XGOUD is er interesse in een product uit uw portfolio: %s.\nWilt u dit verkopen? XGOUD regelt de hele transactie veilig als tussenpersoon — u komt niet in contact met de koper.\n\nReageren: %s\n\nXGOUD",
			get_the_title( $hold->ID ),
			home_url( '/marktplaats/?deal=' . get_post_meta( $deal, 'token_seller', true ) )
		) );
	}
}
add_action( 'ekinese_wishlist_added', 'ekinese_marketplace_match', 10, 2 );

/** Status zetten + loggen. */
function ekinese_deal_set_status( $deal_id, $status ) {
	$labels = ekinese_deal_statuses();
	if ( ! isset( $labels[ $status ] ) ) {
		return;
	}
	update_post_meta( $deal_id, 'status', $status );
	update_post_meta( $deal_id, 'status_' . $status . '_at', current_time( 'mysql' ) );
}

/** Mail-helper (los, nooit beide partijen samen). */
function ekinese_deal_mail( $to, $subject, $body ) {
	if ( $to && is_email( $to ) ) {
		wp_mail( $to, $subject, $body );
	}
}

/** Commissie + uitbetaling berekenen voor een bod. */
function ekinese_deal_breakdown( $offer ) {
	$offer = (float) $offer;
	$comm  = round( $offer * ekinese_marketplace_commission_pct(), 2 );
	return array( 'offer' => $offer, 'commission' => $comm, 'seller_payout' => round( $offer - $comm, 2 ), 'buyer_pays' => $offer );
}

/* =====================================================================
   REST – verkoper akkoord, koper bod, koper accepteert
===================================================================== */
function ekinese_marketplace_rest() {
	register_rest_route( 'ekinese/v1', '/deal/(?P<token>[a-zA-Z0-9]{20})', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_deal_view',
	) );
	register_rest_route( 'ekinese/v1', '/deal/(?P<token>[a-zA-Z0-9]{20})/act', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_deal_act',
	) );
}
add_action( 'rest_api_init', 'ekinese_marketplace_rest' );

/** Deal + rol bepalen uit een token. */
function ekinese_deal_by_token( $token ) {
	foreach ( array( 'token_seller' => 'seller', 'token_buyer' => 'buyer' ) as $key => $role ) {
		$q = get_posts( array( 'post_type' => 'xg_deal', 'numberposts' => 1, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => $key, 'value' => $token ) ) ) );
		if ( $q ) {
			return array( $q[0]->ID, $role );
		}
	}
	return array( 0, '' );
}

function ekinese_deal_view( WP_REST_Request $req ) {
	list( $id, $role ) = ekinese_deal_by_token( (string) $req['token'] );
	if ( ! $id ) {
		return new WP_Error( 'notfound', 'deal', array( 'status' => 404 ) );
	}
	$status = get_post_meta( $id, 'status', true );
	$offer  = (float) get_post_meta( $id, 'offer', true );
	$bd     = ekinese_deal_breakdown( $offer );
	return rest_ensure_response( array(
		'role'         => $role,
		'product'      => get_post_meta( $id, 'product', true ),
		'status'       => $status,
		'status_label' => ekinese_deal_statuses()[ $status ] ?? $status,
		// Verkoper ziet uitbetaling, koper ziet wat hij betaalt.
		'amount'       => 'seller' === $role ? $bd['seller_payout'] : $bd['buyer_pays'],
		'has_offer'    => $offer > 0,
	) );
}

function ekinese_deal_act( WP_REST_Request $req ) {
	list( $id, $role ) = ekinese_deal_by_token( (string) $req['token'] );
	if ( ! $id ) {
		return new WP_Error( 'notfound', 'deal', array( 'status' => 404 ) );
	}
	$action = sanitize_key( (string) $req->get_param( 'action' ) );
	$status = get_post_meta( $id, 'status', true );
	$buyer  = get_post_meta( $id, 'buyer_email', true );
	$seller = get_post_meta( $id, 'seller_email', true );

	if ( 'seller' === $role && 'agree' === $action && 'matched' === $status ) {
		ekinese_deal_set_status( $id, 'seller_ok' );
		// Koper vragen om een bod.
		ekinese_deal_mail( $buyer, __( 'Uw gewenste product is beschikbaar', 'ekinese' ), sprintf( "Goed nieuws! Een product van uw wishlist is beschikbaar via XGOUD: %s.\nBreng een bod uit: %s\n\nXGOUD", get_post_meta( $id, 'product', true ), home_url( '/marktplaats/?deal=' . get_post_meta( $id, 'token_buyer', true ) ) ) );
		return rest_ensure_response( array( 'ok' => true, 'status' => 'seller_ok' ) );
	}
	if ( 'buyer' === $role && 'offer' === $action && in_array( $status, array( 'seller_ok', 'matched' ), true ) ) {
		$offer = (float) $req->get_param( 'amount' );
		if ( $offer <= 0 ) {
			return new WP_Error( 'invalid', __( 'Ongeldig bod.', 'ekinese' ), array( 'status' => 400 ) );
		}
		update_post_meta( $id, 'offer', $offer );
		ekinese_deal_set_status( $id, 'offer_made' );
		$bd = ekinese_deal_breakdown( $offer );
		// Verkoper het netto-bod voorleggen (zonder koper-identiteit).
		ekinese_deal_mail( $seller, __( 'Een bod op uw product', 'ekinese' ), sprintf( "Er is een bod uitgebracht op %s.\nUw netto-uitbetaling zou € %s zijn.\nAccepteren: %s\n\nXGOUD", get_post_meta( $id, 'product', true ), number_format_i18n( $bd['seller_payout'], 2 ), home_url( '/marktplaats/?deal=' . get_post_meta( $id, 'token_seller', true ) ) ) );
		return rest_ensure_response( array( 'ok' => true, 'status' => 'offer_made' ) );
	}
	if ( 'seller' === $role && 'accept' === $action && 'offer_made' === $status ) {
		ekinese_deal_set_status( $id, 'accepted' );
		$bd = ekinese_deal_breakdown( (float) get_post_meta( $id, 'offer', true ) );
		ekinese_deal_mail( $buyer, __( 'Bod geaccepteerd — veilig betalen', 'ekinese' ), sprintf( "Uw bod is geaccepteerd. Maak € %s over aan XGOUD; zodra wij de waar ontvangen en gecontroleerd hebben, sturen wij die naar u. XGOUD bewaakt de transactie.\n\nXGOUD", number_format_i18n( $bd['buyer_pays'], 2 ) ) );
		ekinese_deal_mail( $seller, __( 'Verstuur uw product naar XGOUD', 'ekinese' ), sprintf( "U accepteerde het bod op %s. Stuur het product verzekerd naar XGOUD; na controle betalen wij u € %s uit.\n\nXGOUD", get_post_meta( $id, 'product', true ), number_format_i18n( $bd['seller_payout'], 2 ) ) );
		return rest_ensure_response( array( 'ok' => true, 'status' => 'accepted' ) );
	}
	return new WP_Error( 'badstate', __( 'Actie niet mogelijk in deze status.', 'ekinese' ), array( 'status' => 409 ) );
}

/* =====================================================================
   ADMIN – escrow-afhandeling (betaling/waar/vrijgave)
===================================================================== */
function ekinese_deal_metabox() {
	add_meta_box( 'xg_deal_meta', __( 'Escrow & afhandeling', 'ekinese' ), 'ekinese_deal_metabox_html', 'xg_deal', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_deal_metabox' );

function ekinese_deal_metabox_html( $post ) {
	wp_nonce_field( 'xg_deal_save', 'xg_deal_nonce' );
	$status = get_post_meta( $post->ID, 'status', true );
	$bd     = ekinese_deal_breakdown( (float) get_post_meta( $post->ID, 'offer', true ) );
	echo '<p><strong>Product:</strong> ' . esc_html( get_post_meta( $post->ID, 'product', true ) ) . '</p>';
	echo '<p><strong>Koper:</strong> ' . esc_html( get_post_meta( $post->ID, 'buyer_email', true ) ) . ' &nbsp; <strong>Verkoper:</strong> ' . esc_html( get_post_meta( $post->ID, 'seller_email', true ) ) . '</p>';
	echo '<p><strong>Bod:</strong> € ' . esc_html( number_format_i18n( $bd['offer'], 2 ) ) . ' &nbsp; <strong>Commissie:</strong> € ' . esc_html( number_format_i18n( $bd['commission'], 2 ) ) . ' &nbsp; <strong>Uitbetaling verkoper:</strong> € ' . esc_html( number_format_i18n( $bd['seller_payout'], 2 ) ) . '</p>';
	echo '<p><strong>Status:</strong> ' . esc_html( ekinese_deal_statuses()[ $status ] ?? $status ) . '</p>';
	echo '<p><label><strong>Volgende stap</strong><br><select name="xg_deal_step" style="width:100%"><option value="">—</option>';
	foreach ( array( 'buyer_paid' => 'Betaling koper ontvangen', 'goods_received' => 'Waar ontvangen & gecontroleerd', 'completed' => 'Vrijgeven & afronden', 'cancelled' => 'Annuleren' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></label></p>';
}

function ekinese_deal_save( $post_id ) {
	if ( ! isset( $_POST['xg_deal_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_deal_nonce'] ), 'xg_deal_save' ) ) {
		return;
	}
	$step = sanitize_key( $_POST['xg_deal_step'] ?? '' );
	if ( ! $step ) {
		return;
	}
	ekinese_deal_set_status( $post_id, $step );
	$buyer  = get_post_meta( $post_id, 'buyer_email', true );
	$seller = get_post_meta( $post_id, 'seller_email', true );
	$bd     = ekinese_deal_breakdown( (float) get_post_meta( $post_id, 'offer', true ) );
	if ( 'completed' === $step ) {
		ekinese_deal_mail( $buyer, __( 'Uw product is onderweg', 'ekinese' ), "De transactie is afgerond. Uw product is gecontroleerd en wordt naar u verzonden.\n\nXGOUD" );
		ekinese_deal_mail( $seller, __( 'Uw uitbetaling is onderweg', 'ekinese' ), sprintf( "De transactie is afgerond. Wij betalen € %s aan u uit.\n\nXGOUD", number_format_i18n( $bd['seller_payout'], 2 ) ) );
	} elseif ( 'cancelled' === $step ) {
		ekinese_deal_mail( $buyer, __( 'Transactie geannuleerd', 'ekinese' ), "De transactie is geannuleerd. Een eventuele betaling storten wij terug.\n\nXGOUD" );
		ekinese_deal_mail( $seller, __( 'Transactie geannuleerd', 'ekinese' ), "De transactie is geannuleerd.\n\nXGOUD" );
	}
}
add_action( 'save_post_xg_deal', 'ekinese_deal_save', 20 );

/** Commissie-instelling. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=xg_deal', __( 'Instellingen', 'ekinese' ), __( 'Instellingen', 'ekinese' ), 'manage_options', 'xg-marketplace', function () {
		if ( isset( $_POST['xg_mp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_mp_nonce'] ), 'xg_mp' ) ) {
			update_option( 'xg_marketplace_commission', min( 1, max( 0, (float) ( $_POST['xg_mp_comm'] ?? 0.06 ) ) ) );
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Marktplaats-instellingen</h1><form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_mp', 'xg_mp_nonce' );
		echo '<tr><th>Commissie (0–1)</th><td><input type="number" step="0.01" min="0" max="1" name="xg_mp_comm" value="' . esc_attr( ekinese_marketplace_commission_pct() ) . '"></td></tr>';
		submit_button();
		echo '</table></form></div>';
	} );
} );
