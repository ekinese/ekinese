<?php
/**
 * XGOUD Virtueel Depot — veilige opslag van edelmetaal/waardevolle items.
 *
 * De klant laat items bij XGOUD opslaan tegen een JAARGELD. Ophalen of laten
 * leveren kan tegen een transportvergoeding. De klant ziet zijn opgeslagen items
 * in Mijn XGOUD. Voor alles zijn er punten.
 *
 * VERZEKERING: XGOUD rekent ALLEEN voor veilig opslaan — niet voor verzekering.
 * De klant sluit zélf een (kostbaarheden)verzekering af; wij tonen een keuze aan
 * verzekeraars en leggen vast welke polis de klant heeft. Geen advies/bemiddeling.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Tarieven (instelbaar). */
function ekinese_depot_fee( $kind ) {
	$map = array(
		'year'     => (float) get_option( 'xg_depot_fee_year', 49 ),
		'pickup'   => (float) get_option( 'xg_depot_fee_pickup', 25 ),
		'delivery' => (float) get_option( 'xg_depot_fee_delivery', 25 ),
	);
	return $map[ $kind ] ?? 0.0;
}

function ekinese_depot_points() {
	$r = function_exists( 'ekinese_reward_rules' ) ? ekinese_reward_rules() : array();
	return isset( $r['depot'] ) ? (int) $r['depot'] : 30;
}
add_filter( 'ekinese_reward_rules', function ( $r ) {
	if ( ! isset( $r['depot'] ) ) {
		$r['depot'] = 30;
	}
	return $r;
} );

/** Verzekeraars die kostbaarheden/edelmetaal-opslag kunnen dekken (klant regelt zelf). */
function ekinese_depot_insurers() {
	return apply_filters( 'ekinese_depot_insurers', array(
		array( 'name' => 'Centraal Beheer — Kostbaarhedenverzekering', 'url' => 'https://www.centraalbeheer.nl/' ),
		array( 'name' => 'Univé — Kostbaarheden', 'url' => 'https://www.unive.nl/' ),
		array( 'name' => 'Nationale-Nederlanden', 'url' => 'https://www.nn.nl/' ),
		array( 'name' => 'a.s.r. — Kostbaarheden', 'url' => 'https://www.asr.nl/' ),
		array( 'name' => 'Klaverblad Verzekeringen', 'url' => 'https://www.klaverblad.nl/' ),
		array( 'name' => 'Aon (verzekeringsmakelaar)', 'url' => 'https://www.aon.nl/' ),
	) );
}

/** Status van het depot-lidmaatschap van een klant (jaargeld). */
function ekinese_depot_until( $email ) {
	$id = function_exists( 'ekinese_account_profile_id' ) ? ekinese_account_profile_id( $email ) : 0;
	return $id ? (string) get_post_meta( $id, 'depot_until', true ) : '';
}
function ekinese_depot_active( $email ) {
	$until = ekinese_depot_until( $email );
	return $until && strtotime( $until ) > current_time( 'timestamp' );
}

/* =====================================================================
   CPT  xg_depot_item
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_depot_item', array(
		'labels'       => array( 'name' => 'Depot', 'singular_name' => 'Depot-item', 'menu_name' => 'Depot' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'xgoud',
		'menu_icon'    => 'dashicons-vault',
		'supports'     => array( 'title' ),
	) );
} );

function ekinese_depot_statuses() {
	return array(
		'aangevraagd' => 'Aangevraagd (op te halen)',
		'opgeslagen'  => 'Opgeslagen',
		'in_transport'=> 'In transport',
		'geleverd'    => 'Geleverd/opgehaald',
	);
}

/* =====================================================================
   STATUSOVERGANGEN (door betaling)
===================================================================== */
/** Jaargeld betaald → lidmaatschap met 1 jaar verlengen + punten. */
function ekinese_depot_mark_paid( $email ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) || ! function_exists( 'ekinese_account_upsert' ) ) {
		return;
	}
	$id   = ekinese_account_upsert( $email );
	$cur  = ekinese_depot_until( $email );
	$base = ( $cur && strtotime( $cur ) > current_time( 'timestamp' ) ) ? strtotime( $cur ) : current_time( 'timestamp' );
	$new  = gmdate( 'Y-m-d', strtotime( '+1 year', $base ) );
	update_post_meta( $id, 'depot_until', $new );
	if ( function_exists( 'ekinese_award_points' ) ) {
		ekinese_award_points( $email, ekinese_depot_points(), 'depot', 'depot-jaargeld' );
	}
	if ( function_exists( 'ekinese_notify' ) ) {
		ekinese_notify( $email, 'Depot verlengd', 'Je depot-lidmaatschap loopt nu tot ' . $new . '.', home_url( '/depot/' ), 'invoice' );
	}
}

/** Transport (ophaling/levering) betaald → status bijwerken. */
function ekinese_depot_move_paid( $id ) {
	if ( get_post_type( $id ) !== 'xg_depot_item' ) {
		return;
	}
	update_post_meta( $id, 'status', 'in_transport' );
	update_post_meta( $id, 'move_paid', current_time( 'mysql' ) );
	$email = get_post_meta( $id, 'email', true );
	if ( is_email( $email ) && function_exists( 'ekinese_notify' ) ) {
		ekinese_notify( $email, 'Depot-transport ingepland', 'We hebben je transportaanvraag voor "' . get_the_title( $id ) . '" ontvangen en plannen het in.', home_url( '/depot/' ) );
	}
}

/* =====================================================================
   REST
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/depot/store', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_depot_store' ) );
	register_rest_route( 'ekinese/v1', '/depot/move', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_depot_move' ) );
	register_rest_route( 'ekinese/v1', '/depot/insure', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ekinese_depot_insure' ) );
} );

function ekinese_depot_email( $req ) {
	$token = (string) $req->get_param( 'token' );
	return $token && function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( $token ) : '';
}

function ekinese_depot_store( WP_REST_Request $req ) {
	$email = ekinese_depot_email( $req );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'auth', 'Log eerst in via Mijn XGOUD.', array( 'status' => 401 ) );
	}
	$desc = sanitize_text_field( (string) $req->get_param( 'item' ) );
	$val  = (float) $req->get_param( 'value' );
	if ( '' === $desc ) {
		return new WP_Error( 'invalid', 'Omschrijf het item.', array( 'status' => 400 ) );
	}
	$id = wp_insert_post( array( 'post_type' => 'xg_depot_item', 'post_status' => 'publish', 'post_title' => $desc ), true );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', 'Mislukt.', array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'est_value', round( $val, 2 ) );
	update_post_meta( $id, 'status', 'aangevraagd' );
	update_post_meta( $id, 'since', current_time( 'mysql' ) );
	if ( function_exists( 'ekinese_notify' ) ) {
		$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
		ekinese_notify( $admin, 'Nieuwe depot-aanvraag', $desc . ' (€ ' . number_format( $val, 2 ) . ')', admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Aanvraag ontvangen. We nemen contact op voor het ophalen en opslaan.' ) );
}

function ekinese_depot_move( WP_REST_Request $req ) {
	$email = ekinese_depot_email( $req );
	$id    = (int) $req->get_param( 'id' );
	if ( ! is_email( $email ) || get_post_type( $id ) !== 'xg_depot_item' || get_post_meta( $id, 'email', true ) !== $email ) {
		return new WP_Error( 'auth', 'Geen toegang tot dit item.', array( 'status' => 403 ) );
	}
	$mv = 'delivery' === $req->get_param( 'kind' ) ? 'delivery' : 'pickup';
	update_post_meta( $id, 'move_type', $mv );
	return rest_ensure_response( array( 'ok' => true, 'pay' => esc_url_raw( rest_url( 'ekinese/v1/pay/start' ) . '?type=depot_move&id=' . $id ) ) );
}

function ekinese_depot_insure( WP_REST_Request $req ) {
	$email = ekinese_depot_email( $req );
	$id    = (int) $req->get_param( 'id' );
	if ( ! is_email( $email ) || get_post_type( $id ) !== 'xg_depot_item' || get_post_meta( $id, 'email', true ) !== $email ) {
		return new WP_Error( 'auth', 'Geen toegang.', array( 'status' => 403 ) );
	}
	update_post_meta( $id, 'insurer', sanitize_text_field( (string) $req->get_param( 'insurer' ) ) );
	update_post_meta( $id, 'policy_no', sanitize_text_field( (string) $req->get_param( 'policy' ) ) );
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Verzekeringsgegevens opgeslagen.' ) );
}

/* =====================================================================
   FRONT-END: blok ekinese/depot
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/depot', array( 'render_callback' => 'ekinese_render_depot' ) );
} );

function ekinese_render_depot() {
	$ins = '';
	foreach ( ekinese_depot_insurers() as $i ) {
		$ins .= '<li><a href="' . esc_url( $i['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $i['name'] ) . '</a></li>';
	}
	return '<section class="xg-depot" data-store="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/depot/store' ) ) )
		. '" data-pay="' . esc_attr( esc_url_raw( rest_url( 'ekinese/v1/pay/start' ) ) ) . '"><div class="xg-container">'
		. '<h2>Virtueel Depot — veilig opgeslagen</h2>'
		. '<p class="xg-depot-intro">Laat je edelmetaal, munten of waardevolle items veilig bij XGOUD opslaan tegen een vast jaargeld van € ' . esc_html( number_format_i18n( ekinese_depot_fee( 'year' ), 2 ) ) . '. Ophalen of laten leveren kan tegen een transportvergoeding. Je ziet al je items in Mijn XGOUD.</p>'
		. '<div class="xg-depot-cta"><a class="xg-depot-pay" href="#" data-type="depot">Depot openen / jaargeld betalen (€ ' . esc_html( number_format_i18n( ekinese_depot_fee( 'year' ), 2 ) ) . ')</a></div>'
		. '<div class="xg-depot-insure"><h3>Verzekering — regel je zélf</h3>'
		. '<p>XGOUD zorgt voor veilige opslag, maar <strong>verzekeren doe je zelf</strong>. Sluit een kostbaarhedenverzekering af bij een verzekeraar naar keuze. Geschikte aanbieders:</p>'
		. '<ul class="xg-depot-insurers">' . $ins . '</ul></div>'
		. '<div class="xg-depot-store"><h3>Item aanmelden voor opslag</h3>'
		. '<form class="xg-depot-form"><input type="text" name="item" placeholder="Wat wil je opslaan? (bijv. 10× Krugerrand)" required>'
		. '<input type="number" name="value" min="0" step="0.01" placeholder="Geschatte waarde €">'
		. '<button type="submit">Aanmelden</button><span class="xg-depot-msg" role="status"></span></form>'
		. '<p class="xg-depot-note">Inloggen via Mijn XGOUD is nodig om aan te melden en je items te zien.</p></div>'
		. '</div></section>';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$p = get_post();
	if ( ! $p || ! has_block( 'ekinese/depot', $p ) ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/depot.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-depot', get_theme_file_uri( 'assets/css/depot.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/depot.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-depot', get_theme_file_uri( 'assets/js/depot.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* Depot in Mijn XGOUD. */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$ids = get_posts( array( 'post_type' => 'xg_depot_item', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => 'email', 'meta_value' => $email ) );
	$labels = ekinese_depot_statuses();
	$items  = array();
	foreach ( $ids as $id ) {
		$st = (string) get_post_meta( $id, 'status', true ) ?: 'aangevraagd';
		$items[] = array(
			'id'      => $id,
			'item'    => get_the_title( $id ),
			'value'   => number_format( (float) get_post_meta( $id, 'est_value', true ), 2 ),
			'status'  => $labels[ $st ] ?? $st,
			'insurer' => (string) get_post_meta( $id, 'insurer', true ),
			'move'    => esc_url_raw( rest_url( 'ekinese/v1/depot/move' ) ),
		);
	}
	$data['depot']        = $items;
	$data['depot_active'] = ekinese_depot_active( $email ) ? 1 : 0;
	$data['depot_until']  = ekinese_depot_until( $email );
	$data['depot_pay']    = esc_url_raw( rest_url( 'ekinese/v1/pay/start' ) . '?type=depot' );
	return $data;
}, 19, 2 );

/* =====================================================================
   ADMIN: metabox, kolommen, tarieven, KPI, taken
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_depot_box', 'Depot-item', 'ekinese_depot_metabox', 'xg_depot_item', 'normal', 'high' );
} );
function ekinese_depot_metabox( $post ) {
	wp_nonce_field( 'xg_depot_save', 'xg_depot_nonce' );
	$g = function ( $k ) use ( $post ) { return esc_attr( get_post_meta( $post->ID, $k, true ) ); };
	echo '<table class="form-table"><tbody>';
	echo '<tr><th>Klant (e-mail)</th><td><input type="email" name="xgd_email" value="' . $g( 'email' ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Geschatte waarde €</th><td><input type="number" step="0.01" name="xgd_est_value" value="' . $g( 'est_value' ) . '"></td></tr>';
	echo '<tr><th>Locatie (kluis)</th><td><input type="text" name="xgd_location" value="' . $g( 'location' ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Status</th><td><select name="xgd_status">';
	$cur = get_post_meta( $post->ID, 'status', true ) ?: 'aangevraagd';
	foreach ( ekinese_depot_statuses() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>Verzekering (klant)</th><td>' . esc_html( get_post_meta( $post->ID, 'insurer', true ) ?: '—' ) . ' ' . esc_html( get_post_meta( $post->ID, 'policy_no', true ) ) . '</td></tr>';
	echo '</tbody></table>';
}
add_action( 'save_post_xg_depot_item', function ( $post_id ) {
	if ( ! isset( $_POST['xg_depot_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_depot_nonce'] ), 'xg_depot_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	update_post_meta( $post_id, 'email', sanitize_email( wp_unslash( $_POST['xgd_email'] ?? '' ) ) );
	update_post_meta( $post_id, 'est_value', round( (float) ( $_POST['xgd_est_value'] ?? 0 ), 2 ) );
	update_post_meta( $post_id, 'location', sanitize_text_field( wp_unslash( $_POST['xgd_location'] ?? '' ) ) );
	update_post_meta( $post_id, 'status', sanitize_key( $_POST['xgd_status'] ?? 'aangevraagd' ) );
} );

add_filter( 'manage_xg_depot_item_posts_columns', function ( $c ) {
	$c['xg_email'] = 'Klant'; $c['xg_val'] = 'Waarde'; $c['xg_st'] = 'Status'; $c['xg_ins'] = 'Verzekerd';
	return $c;
} );
add_action( 'manage_xg_depot_item_posts_custom_column', function ( $col, $id ) {
	if ( 'xg_email' === $col ) { echo esc_html( get_post_meta( $id, 'email', true ) ); }
	elseif ( 'xg_val' === $col ) { echo '€ ' . esc_html( number_format_i18n( (float) get_post_meta( $id, 'est_value', true ), 2 ) ); }
	elseif ( 'xg_st' === $col ) { $s = ekinese_depot_statuses(); echo esc_html( $s[ get_post_meta( $id, 'status', true ) ] ?? '—' ); }
	elseif ( 'xg_ins' === $col ) { echo get_post_meta( $id, 'insurer', true ) ? '✓' : '<span style="color:#d63638">nee</span>'; } // phpcs:ignore
}, 10, 2 );

/* Tarieven-instellingen. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Depot-tarieven', 'Depot-tarieven', 'manage_options', 'xg-depot-fees', 'ekinese_depot_fees_page' );
}, 53 );
add_action( 'admin_init', function () {
	if ( isset( $_POST['xg_depot_fees'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'xg_depot_fees' ) ) {
		update_option( 'xg_depot_fee_year', round( (float) ( $_POST['xg_depot_fee_year'] ?? 49 ), 2 ) );
		update_option( 'xg_depot_fee_pickup', round( (float) ( $_POST['xg_depot_fee_pickup'] ?? 25 ), 2 ) );
		update_option( 'xg_depot_fee_delivery', round( (float) ( $_POST['xg_depot_fee_delivery'] ?? 25 ), 2 ) );
		add_settings_error( 'xg_depot', 'saved', 'Tarieven opgeslagen.', 'success' );
	}
} );
function ekinese_depot_fees_page() {
	settings_errors( 'xg_depot' );
	echo '<div class="wrap"><h1>Depot-tarieven</h1><form method="post">';
	wp_nonce_field( 'xg_depot_fees' );
	echo '<input type="hidden" name="xg_depot_fees" value="1"><table class="form-table"><tbody>';
	echo '<tr><th>Jaargeld (€)</th><td><input type="number" step="0.01" name="xg_depot_fee_year" value="' . esc_attr( ekinese_depot_fee( 'year' ) ) . '"></td></tr>';
	echo '<tr><th>Ophaling (€)</th><td><input type="number" step="0.01" name="xg_depot_fee_pickup" value="' . esc_attr( ekinese_depot_fee( 'pickup' ) ) . '"></td></tr>';
	echo '<tr><th>Levering (€)</th><td><input type="number" step="0.01" name="xg_depot_fee_delivery" value="' . esc_attr( ekinese_depot_fee( 'delivery' ) ) . '"></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form></div>';
}

/* KPI op het dashboard + daily task (verlengingen / aanvragen). */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$req = count( get_posts( array( 'post_type' => 'xg_depot_item', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'status', 'meta_value' => 'aangevraagd' ) ) );
	if ( $req > 0 ) {
		$tasks[] = array( 'key' => 'depot_req', 'label' => 'Depot: nieuwe opslag-aanvragen ophalen', 'count' => $req, 'link' => admin_url( 'edit.php?post_type=xg_depot_item' ) );
	}
	$trans = count( get_posts( array( 'post_type' => 'xg_depot_item', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'status', 'meta_value' => 'in_transport' ) ) );
	if ( $trans > 0 ) {
		$tasks[] = array( 'key' => 'depot_transport', 'label' => 'Depot: transport uitvoeren', 'count' => $trans, 'link' => admin_url( 'edit.php?post_type=xg_depot_item' ) );
	}
	return $tasks;
} );
