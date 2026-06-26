<?php
/**
 * XGOUD Treuhandservice (escrow).
 *
 * XGOUD treedt op als vertrouwde tussenpartij bij een transactie tussen een
 * koper en een verkoper (privé of zakelijk): factuur → geld in bewaring →
 * vrijgave aan de verkoper. Beide partijen sparen punten; alles is in het
 * backend-dashboard te volgen.
 *
 * Statusflow:
 *   nieuw → factuur → betaald (in escrow) → vrijgegeven   (of: geannuleerd)
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT + helpers
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_escrow', array(
		'labels'       => array( 'name' => 'Treuhand', 'singular_name' => 'Treuhand-transactie', 'menu_name' => 'Treuhand' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'xgoud',
		'menu_icon'    => 'dashicons-bank',
		'supports'     => array( 'title' ),
	) );
} );

function ekinese_escrow_statuses() {
	return array(
		'nieuw'       => 'Aanvraag ontvangen',
		'factuur'     => 'Factuur verstuurd',
		'betaald'     => 'Geld in bewaring',
		'vrijgegeven' => 'Vrijgegeven aan verkoper',
		'geannuleerd' => 'Geannuleerd',
	);
}

function ekinese_escrow_fee_pct( $type ) {
	return 'professional' === $type
		? (float) get_option( 'xg_escrow_fee_pro', 1.5 )
		: (float) get_option( 'xg_escrow_fee_private', 3.0 );
}

function ekinese_escrow_points() {
	$r = function_exists( 'ekinese_reward_rules' ) ? ekinese_reward_rules() : array();
	return isset( $r['escrow'] ) ? (int) $r['escrow'] : 50;
}
add_filter( 'ekinese_reward_rules', function ( $r ) {
	if ( ! isset( $r['escrow'] ) ) {
		$r['escrow'] = 50;
	}
	return $r;
} );

/** Eén transactie genormaliseerd. */
function ekinese_escrow_row( $id ) {
	$amount = (float) get_post_meta( $id, 'amount', true );
	$type   = (string) get_post_meta( $id, 'type', true ) ?: 'private';
	$fee    = (float) ( get_post_meta( $id, 'fee', true ) ?: round( $amount * ekinese_escrow_fee_pct( $type ) / 100, 2 ) );
	$st     = (string) get_post_meta( $id, 'status', true ) ?: 'nieuw';
	$labels = ekinese_escrow_statuses();
	return array(
		'id'       => $id,
		'item'     => get_the_title( $id ),
		'type'     => $type,
		'buyer'    => (string) get_post_meta( $id, 'buyer_email', true ),
		'seller'   => (string) get_post_meta( $id, 'seller_email', true ),
		'amount'   => $amount,
		'fee'      => $fee,
		'payout'   => max( 0, $amount - $fee ),
		'status'   => $st,
		'status_l' => $labels[ $st ] ?? $st,
		'invoice'  => (string) get_post_meta( $id, 'invoice_number', true ),
	);
}

/* =====================================================================
   STATUSOVERGANGEN
===================================================================== */
/** Factuur versturen → koper ontvangt betaallink. */
function ekinese_escrow_send_invoice( $id ) {
	if ( get_post_type( $id ) !== 'xg_escrow' ) {
		return;
	}
	if ( ! get_post_meta( $id, 'invoice_number', true ) ) {
		update_post_meta( $id, 'invoice_number', 'XG-ESC-' . gmdate( 'Y' ) . '-' . $id );
		update_post_meta( $id, 'invoice_date', gmdate( 'Y-m-d' ) );
	}
	// Fee vastzetten bij facturatie.
	$row = ekinese_escrow_row( $id );
	update_post_meta( $id, 'fee', $row['fee'] );
	update_post_meta( $id, 'status', 'factuur' );
	$buyer = get_post_meta( $id, 'buyer_email', true );
	if ( is_email( $buyer ) ) {
		$token = function_exists( 'ekinese_account_make_token' ) ? ekinese_account_make_token( $buyer ) : '';
		$pay   = home_url( '/mijn-xgoud/?token=' . $token );
		$inv   = home_url( '/?xg_escrow_invoice=' . $id . '&token=' . $token );
		wp_mail( $buyer, 'Factuur Treuhandservice — ' . get_the_title( $id ),
			sprintf( "Beste,\n\nUw factuur %s voor de treuhandtransactie '%s' staat klaar (€ %s).\n\nFactuur bekijken: %s\nBetalen: %s\n\nNa ontvangst houden wij het bedrag veilig in bewaring tot de transactie is afgerond.\n\nXGOUD", $row['invoice'], get_the_title( $id ), number_format( $row['amount'], 2 ), $inv, $pay ) );
		if ( function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $buyer, 'Factuur Treuhandservice', 'Factuur ' . $row['invoice'] . ' staat klaar om te betalen.', $pay, 'invoice' );
		}
	}
}

/** Geld ontvangen → in bewaring (Mollie-webhook of handmatig). */
function ekinese_escrow_mark_paid( $id ) {
	if ( get_post_type( $id ) !== 'xg_escrow' || get_post_meta( $id, 'status', true ) === 'betaald' ) {
		return;
	}
	update_post_meta( $id, 'status', 'betaald' );
	update_post_meta( $id, 'paid_at', current_time( 'mysql' ) );
	foreach ( array( 'buyer_email', 'seller_email' ) as $f ) {
		$e = get_post_meta( $id, $f, true );
		if ( is_email( $e ) && function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $e, 'Treuhand: geld in bewaring', 'XGOUD houdt het bedrag voor ' . get_the_title( $id ) . ' veilig in bewaring tot de transactie is afgerond.', '', 'info' );
		}
	}
}

/** Vrijgeven aan verkoper → afronden + punten + charity. */
function ekinese_escrow_release( $id ) {
	if ( get_post_type( $id ) !== 'xg_escrow' || get_post_meta( $id, 'status', true ) !== 'betaald' ) {
		return;
	}
	$row = ekinese_escrow_row( $id );
	update_post_meta( $id, 'status', 'vrijgegeven' );
	update_post_meta( $id, 'released_at', current_time( 'mysql' ) );
	// Punten voor beide partijen (1×).
	if ( ! get_post_meta( $id, 'points_awarded', true ) && function_exists( 'ekinese_award_points' ) ) {
		foreach ( array( $row['buyer'], $row['seller'] ) as $e ) {
			if ( is_email( $e ) ) {
				ekinese_award_points( $e, ekinese_escrow_points(), 'escrow', 'escrow#' . $id );
			}
		}
		update_post_meta( $id, 'points_awarded', 1 );
	}
	if ( is_email( $row['seller'] ) ) {
		wp_mail( $row['seller'], 'Uitbetaling Treuhandservice — ' . $row['item'],
			sprintf( "Beste,\n\nDe transactie '%s' is afgerond. Wij betalen € %s aan u uit (na € %s servicekosten).\n\nBedankt voor het gebruik van de XGOUD Treuhandservice.\n\nXGOUD", $row['item'], number_format( $row['payout'], 2 ), number_format( $row['fee'], 2 ) ) );
		if ( function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $row['seller'], 'Uitbetaling onderweg', 'Uw uitbetaling van € ' . number_format( $row['payout'], 2 ) . ' is vrijgegeven.', '', 'invoice' );
		}
	}
}

function ekinese_escrow_cancel( $id ) {
	if ( get_post_type( $id ) === 'xg_escrow' ) {
		update_post_meta( $id, 'status', 'geannuleerd' );
	}
}

/* =====================================================================
   REST: aanvraag
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/escrow/request', array(
		'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_escrow_request',
	) );
} );

function ekinese_escrow_request( WP_REST_Request $req ) {
	if ( ! empty( $req->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'ok' => true ) ); // honeypot
	}
	$role  = 'verkoper' === $req->get_param( 'role' ) ? 'verkoper' : 'koper';
	$type  = 'professional' === $req->get_param( 'type' ) ? 'professional' : 'private';
	$item  = sanitize_text_field( (string) $req->get_param( 'item' ) );
	$amount = (float) $req->get_param( 'amount' );
	$me    = sanitize_email( (string) $req->get_param( 'email' ) );
	$other = sanitize_email( (string) $req->get_param( 'counterparty' ) );
	$token = (string) $req->get_param( 'token' );
	if ( $token && function_exists( 'ekinese_account_verify_token' ) ) {
		$te = ekinese_account_verify_token( $token );
		if ( $te ) {
			$me = $te;
		}
	}
	if ( ! is_email( $me ) || ! is_email( $other ) || '' === $item || $amount <= 0 ) {
		return new WP_Error( 'invalid', 'Vul alle velden in (omschrijving, bedrag, beide e-mailadressen).', array( 'status' => 400 ) );
	}
	// Geen contactgegevens in de omschrijving (consistent met marktplaats).
	if ( function_exists( 'ekinese_market_has_contact' ) && ekinese_market_has_contact( $item ) ) {
		return new WP_Error( 'contact', 'Geen contactgegevens in de omschrijving.', array( 'status' => 400 ) );
	}
	$buyer  = 'koper' === $role ? $me : $other;
	$seller = 'koper' === $role ? $other : $me;
	$id = wp_insert_post( array(
		'post_type'   => 'xg_escrow',
		'post_status' => 'publish',
		'post_title'  => $item,
	), true );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', 'Aanvraag mislukt.', array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'type', $type );
	update_post_meta( $id, 'buyer_email', $buyer );
	update_post_meta( $id, 'seller_email', $seller );
	update_post_meta( $id, 'amount', round( $amount, 2 ) );
	update_post_meta( $id, 'fee', round( $amount * ekinese_escrow_fee_pct( $type ) / 100, 2 ) );
	update_post_meta( $id, 'status', 'nieuw' );
	update_post_meta( $id, 'description', sanitize_textarea_field( (string) $req->get_param( 'description' ) ) );
	if ( function_exists( 'ekinese_notify' ) ) {
		$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
		ekinese_notify( $admin, 'Nieuwe Treuhand-aanvraag', $item . ' · € ' . number_format( $amount, 2 ) . ' (' . $type . ')', admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt! We beoordelen je aanvraag en sturen de koper een factuur.' ) );
}

/* =====================================================================
   FACTUUR-WEERGAVE  (/?xg_escrow_invoice=ID&token=…)
===================================================================== */
add_filter( 'query_vars', function ( $v ) { $v[] = 'xg_escrow_invoice'; return $v; } );
add_action( 'template_redirect', function () {
	$id = (int) get_query_var( 'xg_escrow_invoice' );
	if ( ! $id || get_post_type( $id ) !== 'xg_escrow' ) {
		return;
	}
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) ( $_GET['token'] ?? '' ) ) : '';
	$row   = ekinese_escrow_row( $id );
	if ( ! $email || ! in_array( $email, array( $row['buyer'], $row['seller'] ), true ) ) {
		wp_die( 'Geen toegang tot deze factuur.', 'Factuur', array( 'response' => 403 ) );
	}
	$b = function_exists( 'ekinese_business' ) ? ekinese_business() : array( 'name' => 'XGOUD', 'street' => '', 'postcode' => '', 'city' => '' );
	$kvk = get_option( 'xg_legal_kvk', '' );
	$btw = get_option( 'xg_legal_btw', '' );
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo '<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>Factuur ' . esc_html( $row['invoice'] ) . '</title>';
	echo '<style>body{font-family:Arial,sans-serif;color:#1c1a16;max-width:720px;margin:30px auto;padding:0 20px}h1{color:#AE1E1E}table{width:100%;border-collapse:collapse;margin:20px 0}td,th{border:1px solid #ddd;padding:8px;text-align:left}.tot{font-weight:700}.muted{color:#666;font-size:13px}@media print{.np{display:none}}</style></head><body>';
	echo '<h1>Factuur</h1><p class="muted">' . esc_html( $b['name'] ) . ( $kvk ? ' · KvK ' . esc_html( $kvk ) : '' ) . ( $btw ? ' · btw ' . esc_html( $btw ) : '' ) . '</p>';
	echo '<p><strong>Factuurnummer:</strong> ' . esc_html( $row['invoice'] ?: '—' ) . '<br><strong>Datum:</strong> ' . esc_html( get_post_meta( $id, 'invoice_date', true ) ?: gmdate( 'Y-m-d' ) ) . '<br><strong>Koper:</strong> ' . esc_html( $row['buyer'] ) . '</p>';
	echo '<table><tr><th>Omschrijving</th><th>Bedrag</th></tr>';
	echo '<tr><td>Treuhandservice: ' . esc_html( $row['item'] ) . '</td><td>€ ' . esc_html( number_format( $row['amount'], 2 ) ) . '</td></tr>';
	echo '<tr><td>Servicekosten (' . esc_html( ekinese_escrow_fee_pct( $row['type'] ) ) . '%)</td><td>€ ' . esc_html( number_format( $row['fee'], 2 ) ) . '</td></tr>';
	echo '<tr class="tot"><td>Te betalen (in bewaring)</td><td>€ ' . esc_html( number_format( $row['amount'], 2 ) ) . '</td></tr></table>';
	echo '<p class="muted">Het volledige bedrag wordt door XGOUD in bewaring gehouden en na afronding (minus servicekosten) aan de verkoper uitbetaald.</p>';
	echo '<p class="np"><button onclick="window.print()">Afdrukken / PDF</button></p></body></html>';
	exit;
} );

/* =====================================================================
   FRONT-END: blok ekinese/escrow (uitleg + aanvraagformulier)
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/escrow', array( 'render_callback' => 'ekinese_render_escrow' ) );
} );

function ekinese_render_escrow() {
	$rest = esc_url_raw( rest_url( 'ekinese/v1/escrow/request' ) );
	$fp   = number_format( (float) get_option( 'xg_escrow_fee_private', 3.0 ), 1 );
	$fpro = number_format( (float) get_option( 'xg_escrow_fee_pro', 1.5 ), 1 );
	return '<section class="xg-escrow" data-rest="' . esc_attr( $rest ) . '"><div class="xg-container">'
		. '<h2>Treuhandservice — veilig kopen en verkopen</h2>'
		. '<p class="xg-escrow-intro">Koopt of verkoopt u edelmetaal, sieraden of horloges met een andere partij? XGOUD houdt het geld veilig in bewaring tot de transactie rond is. De koper betaalt aan ons, wij betalen de verkoper pas uit zodra alles klopt. Voor particulieren én zakelijke partijen.</p>'
		. '<div class="xg-escrow-steps">'
		. '<div class="xg-escrow-step"><span>1</span> Aanvraag &amp; factuur</div>'
		. '<div class="xg-escrow-step"><span>2</span> Koper betaalt aan XGOUD</div>'
		. '<div class="xg-escrow-step"><span>3</span> Geld in bewaring</div>'
		. '<div class="xg-escrow-step"><span>4</span> Vrijgave aan verkoper</div>'
		. '</div>'
		. '<p class="xg-escrow-fee">Servicekosten: particulier ' . esc_html( $fp ) . '% · zakelijk ' . esc_html( $fpro ) . '%. Beide partijen sparen punten.</p>'
		. '<form class="xg-escrow-form">'
		. '<input type="text" name="website" class="xg-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
		. '<div class="xg-escrow-row"><label>Ik ben<select name="role"><option value="koper">Koper</option><option value="verkoper">Verkoper</option></select></label>'
		. '<label>Type<select name="type"><option value="private">Particulier</option><option value="professional">Zakelijk</option></select></label></div>'
		. '<div class="xg-escrow-row"><input type="email" name="email" placeholder="Uw e-mailadres" required><input type="email" name="counterparty" placeholder="E-mail van de andere partij" required></div>'
		. '<input type="text" name="item" placeholder="Wat wordt verhandeld? (bijv. Rolex Submariner)" required>'
		. '<input type="number" name="amount" min="1" step="0.01" placeholder="Bedrag in €" required>'
		. '<textarea name="description" rows="3" placeholder="Korte omschrijving / afspraken"></textarea>'
		. '<button type="submit">Aanvraag versturen</button><span class="xg-escrow-msg" role="status"></span>'
		. '</form></div></section>';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$p = get_post();
	if ( ! $p || ! has_block( 'ekinese/escrow', $p ) ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/escrow.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-escrow', get_theme_file_uri( 'assets/css/escrow.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/escrow.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-escrow', get_theme_file_uri( 'assets/js/escrow.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* Eigen treuhand-transacties in Mijn XGOUD. */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$ids = get_posts( array(
		'post_type'   => 'xg_escrow',
		'post_status' => 'publish',
		'numberposts' => 20,
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'OR',
			array( 'key' => 'buyer_email', 'value' => $email ),
			array( 'key' => 'seller_email', 'value' => $email ),
		),
	) );
	$out = array();
	foreach ( $ids as $id ) {
		$r = ekinese_escrow_row( $id );
		$is_buyer = ( $r['buyer'] === $email );
		$pay = ( $is_buyer && 'factuur' === $r['status'] )
			? esc_url_raw( rest_url( 'ekinese/v1/pay/start' ) . '?type=escrow&id=' . $id )
			: '';
		$out[] = array(
			'id'     => $id,
			'item'   => $r['item'],
			'role'   => $is_buyer ? 'Koper' : 'Verkoper',
			'amount' => number_format( $r['amount'], 2 ),
			'status' => $r['status_l'],
			'pay'    => $pay,
		);
	}
	$data['escrow'] = $out;
	return $data;
}, 18, 2 );

/* =====================================================================
   ADMIN: metabox + acties + kolommen
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_escrow_box', 'Treuhand-transactie', 'ekinese_escrow_metabox', 'xg_escrow', 'normal', 'high' );
} );

function ekinese_escrow_metabox( $post ) {
	wp_nonce_field( 'xg_escrow_save', 'xg_escrow_nonce' );
	$r     = ekinese_escrow_row( $post->ID );
	$types = array( 'private' => 'Particulier', 'professional' => 'Zakelijk' );
	echo '<style>.xg-e-f{margin:0 0 10px}.xg-e-f label{font-weight:600;display:block;margin-bottom:2px}.xg-e-f input,.xg-e-f select{width:100%;max-width:340px}</style>';
	echo '<div class="xg-e-f"><label>Type</label><select name="xg_e_type">';
	foreach ( $types as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $r['type'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></div>';
	echo '<div class="xg-e-f"><label>Koper (e-mail)</label><input type="email" name="xg_e_buyer" value="' . esc_attr( $r['buyer'] ) . '"></div>';
	echo '<div class="xg-e-f"><label>Verkoper (e-mail)</label><input type="email" name="xg_e_seller" value="' . esc_attr( $r['seller'] ) . '"></div>';
	echo '<div class="xg-e-f"><label>Bedrag (€)</label><input type="number" step="0.01" name="xg_e_amount" value="' . esc_attr( $r['amount'] ) . '"></div>';
	echo '<p><strong>Status:</strong> ' . esc_html( $r['status_l'] ) . ' · Servicekosten € ' . esc_html( number_format( $r['fee'], 2 ) ) . ' · Uitbetaling verkoper € ' . esc_html( number_format( $r['payout'], 2 ) );
	if ( $r['invoice'] ) {
		echo ' · Factuur ' . esc_html( $r['invoice'] );
	}
	echo '</p>';
	// Actieknoppen (per status).
	$base = wp_nonce_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ), 'xg_escrow_action', 'xg_e_nonce' );
	echo '<p>';
	if ( 'nieuw' === $r['status'] ) {
		echo '<a class="button button-primary" href="' . esc_url( $base . '&xg_e_do=invoice' ) . '">Factuur versturen</a> ';
	}
	if ( 'factuur' === $r['status'] ) {
		echo '<a class="button" href="' . esc_url( $base . '&xg_e_do=paid' ) . '">Geld ontvangen (handmatig)</a> ';
	}
	if ( 'betaald' === $r['status'] ) {
		echo '<a class="button button-primary" href="' . esc_url( $base . '&xg_e_do=release' ) . '">Vrijgeven aan verkoper</a> ';
	}
	if ( ! in_array( $r['status'], array( 'vrijgegeven', 'geannuleerd' ), true ) ) {
		echo '<a class="button" href="' . esc_url( $base . '&xg_e_do=cancel' ) . '" onclick="return confirm(\'Annuleren?\')">Annuleren</a>';
	}
	echo '</p>';
}

add_action( 'save_post_xg_escrow', function ( $post_id ) {
	if ( ! isset( $_POST['xg_escrow_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_escrow_nonce'] ), 'xg_escrow_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'type', 'professional' === ( $_POST['xg_e_type'] ?? '' ) ? 'professional' : 'private' );
	update_post_meta( $post_id, 'buyer_email', sanitize_email( wp_unslash( $_POST['xg_e_buyer'] ?? '' ) ) );
	update_post_meta( $post_id, 'seller_email', sanitize_email( wp_unslash( $_POST['xg_e_seller'] ?? '' ) ) );
	update_post_meta( $post_id, 'amount', round( (float) ( $_POST['xg_e_amount'] ?? 0 ), 2 ) );
	if ( ! get_post_meta( $post_id, 'status', true ) ) {
		update_post_meta( $post_id, 'status', 'nieuw' );
	}
} );

/* Actieknoppen verwerken. */
add_action( 'admin_init', function () {
	if ( empty( $_GET['xg_e_do'] ) || empty( $_GET['post'] ) ) {
		return;
	}
	$id = (int) $_GET['post'];
	if ( ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( sanitize_key( $_GET['xg_e_nonce'] ?? '' ), 'xg_escrow_action' ) ) {
		return;
	}
	switch ( sanitize_key( $_GET['xg_e_do'] ) ) {
		case 'invoice': ekinese_escrow_send_invoice( $id ); break;
		case 'paid':    ekinese_escrow_mark_paid( $id ); break;
		case 'release': ekinese_escrow_release( $id ); break;
		case 'cancel':  ekinese_escrow_cancel( $id ); break;
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	exit;
} );

add_filter( 'manage_xg_escrow_posts_columns', function ( $c ) {
	$new = array( 'cb' => $c['cb'] ?? '', 'title' => $c['title'] ?? 'Item' );
	$new['xg_type']   = 'Type';
	$new['xg_amount'] = 'Bedrag';
	$new['xg_status'] = 'Status';
	$new['xg_party']  = 'Partijen';
	return $new;
} );
add_action( 'manage_xg_escrow_posts_custom_column', function ( $col, $id ) {
	$r = ekinese_escrow_row( $id );
	if ( 'xg_type' === $col ) { echo esc_html( 'professional' === $r['type'] ? 'Zakelijk' : 'Particulier' ); }
	elseif ( 'xg_amount' === $col ) { echo '€ ' . esc_html( number_format( $r['amount'], 2 ) ); }
	elseif ( 'xg_status' === $col ) { echo esc_html( $r['status_l'] ); }
	elseif ( 'xg_party' === $col ) { echo esc_html( $r['buyer'] ) . ' → ' . esc_html( $r['seller'] ); }
}, 10, 2 );

/* =====================================================================
   BACKEND-DASHBOARD: KPI-helper, daily tasks, AI-context
===================================================================== */
function ekinese_escrow_in_bewaring() {
	$ids = get_posts( array( 'post_type' => 'xg_escrow', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'status', 'meta_value' => 'betaald' ) );
	$sum = 0;
	foreach ( $ids as $id ) {
		$sum += (float) get_post_meta( $id, 'amount', true );
	}
	return array( 'count' => count( $ids ), 'sum' => $sum );
}
function ekinese_escrow_count_status( $status ) {
	return count( get_posts( array( 'post_type' => 'xg_escrow', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'status', 'meta_value' => $status ) ) );
}

add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$new = ekinese_escrow_count_status( 'nieuw' );
	$rel = ekinese_escrow_count_status( 'betaald' );
	$base = admin_url( 'edit.php?post_type=xg_escrow' );
	if ( $new > 0 ) {
		$tasks[] = array( 'key' => 'escrow_inv', 'label' => 'Treuhand: facturen versturen', 'count' => $new, 'link' => $base );
	}
	if ( $rel > 0 ) {
		$tasks[] = array( 'key' => 'escrow_rel', 'label' => 'Treuhand: vrijgeven aan verkoper', 'count' => $rel, 'link' => $base );
	}
	return $tasks;
} );

add_filter( 'ekinese_dashboard_ai_context_lines', function ( $lines ) {
	$b = ekinese_escrow_in_bewaring();
	if ( $b['count'] ) {
		$lines[] = sprintf( 'Treuhand: € %s in bewaring over %d transacties; %d wachten op factuur, %d op vrijgave.', number_format( $b['sum'], 2 ), $b['count'], ekinese_escrow_count_status( 'nieuw' ), ekinese_escrow_count_status( 'betaald' ) );
	}
	return $lines;
} );
