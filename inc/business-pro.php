<?php
/**
 * XGOUD zakelijk PORTAAL (pro) — bovenop de bestaande aanvraag (inc/business-
 * portal.php) en partners (inc/partners.php). Een erkende zakelijke partner logt
 * in met een magic-link en krijgt:
 *   - zijn eigen prijslijst (spotkoers minus afgesproken marge);
 *   - bulk-inlevering van partijen (metaal/gewicht/zuiverheid/aantal) met een
 *     directe indicatieve waarde tegen zijn condities;
 *   - een overzicht van zijn ingeleverde partijen + status.
 * Admin keurt de partner goed en stelt marge/betaaltermijn in.
 *
 * Geen onderlinge communicatie, geen plugin. Hergebruikt het account-token.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT  xg_batch  — ingeleverde zakelijke partij
===================================================================== */
function ekinese_register_batch() {
	register_post_type( 'xg_batch', array(
		'labels'    => array( 'name' => __( 'Zakelijke partijen', 'ekinese' ), 'singular_name' => __( 'Partij', 'ekinese' ), 'menu_name' => __( 'Zakelijke partijen', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'show_in_menu' => 'xgoud',
		'menu_icon' => 'dashicons-archive',
		'supports'  => array( 'title' ),
	) );
	foreach ( array( 'email', 'company', 'items', 'total', 'status', 'invoice' ) as $f ) {
		register_post_meta( 'xg_batch', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_batch' );

/* =====================================================================
   PARTNER-HELPERS
===================================================================== */
function ekinese_partner_by_email( $email ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return 0;
	}
	$q = get_posts( array(
		'post_type'   => 'xg_partner',
		'numberposts' => 1,
		'post_status' => 'publish',
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'email', 'value' => $email ) ),
	) );
	return $q ? (int) $q[0] : 0;
}

/** Prijslijst van een partner: €/g per metaal tegen zijn condities. */
function ekinese_partner_pricelist( $pid ) {
	$margin = (float) get_post_meta( $pid, 'biz_margin', true ); // bv. 2 (%)
	$out    = array();
	foreach ( array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' ) as $code => $label ) {
		$spot = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $code ) : 0;
		$out[ $code ] = array( 'label' => $label, 'spot' => round( $spot, 2 ), 'price' => round( $spot * ( 1 - $margin / 100 ), 2 ) );
	}
	return $out;
}

/* =====================================================================
   ADMIN — zakelijk account goedkeuren + condities
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_bizpro', __( 'Zakelijk account (portaal)', 'ekinese' ), 'ekinese_bizpro_metabox', 'xg_partner', 'side', 'high' );
} );

function ekinese_bizpro_metabox( $post ) {
	wp_nonce_field( 'xg_bizpro_save', 'xg_bizpro_nonce' );
	$status = get_post_meta( $post->ID, 'biz_status', true ) ?: 'pending';
	$margin = get_post_meta( $post->ID, 'biz_margin', true );
	$terms  = get_post_meta( $post->ID, 'payment_terms', true );
	echo '<p><label>Status<br><select name="xg_biz_status">';
	foreach ( array( 'pending' => 'In behandeling', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p><label>Marge op spot (%)<br><input type="number" step="0.1" min="0" max="100" name="xg_biz_margin" value="' . esc_attr( $margin ) . '" class="small-text"> %</label><br><span class="description">De partner ontvangt spot − marge per gram fijn.</span></p>';
	echo '<p><label>Betaaltermijn<br><input type="text" name="xg_biz_terms" value="' . esc_attr( $terms ) . '" placeholder="bv. 14 dagen" class="regular-text"></label></p>';
	echo '<p class="description">Bij goedkeuren ontvangt de partner automatisch een inloglink voor het portaal.</p>';
}

add_action( 'save_post_xg_partner', function ( $post_id ) {
	if ( ! isset( $_POST['xg_bizpro_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_bizpro_nonce'] ), 'xg_bizpro_save' ) ) {
		return;
	}
	$was = get_post_meta( $post_id, 'biz_status', true );
	$new = sanitize_key( wp_unslash( $_POST['xg_biz_status'] ?? 'pending' ) );
	update_post_meta( $post_id, 'biz_status', $new );
	if ( isset( $_POST['xg_biz_margin'] ) ) {
		update_post_meta( $post_id, 'biz_margin', (float) $_POST['xg_biz_margin'] );
	}
	if ( isset( $_POST['xg_biz_terms'] ) ) {
		update_post_meta( $post_id, 'payment_terms', sanitize_text_field( wp_unslash( $_POST['xg_biz_terms'] ) ) );
	}
	// Net goedgekeurd → inloglink mailen.
	if ( 'approved' === $new && 'approved' !== $was ) {
		$email = get_post_meta( $post_id, 'email', true );
		if ( is_email( $email ) && function_exists( 'ekinese_account_make_token' ) ) {
			$link = home_url( '/zakelijk/portaal/?token=' . ekinese_account_make_token( $email ) );
			wp_mail( $email, 'Uw XGOUD zakelijk portaal is geactiveerd', "Beste,\n\nUw zakelijke account is goedgekeurd. Open uw portaal via onderstaande link (24 uur geldig); u kunt altijd een nieuwe link aanvragen op /zakelijk/portaal/:\n$link\n\nMet vriendelijke groet,\nXGOUD" );
		}
	}
}, 25 );

/* =====================================================================
   BLOK  ekinese/business-dashboard
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/business-dashboard', array( 'render_callback' => 'ekinese_render_business_dashboard' ) );
} );

function ekinese_render_business_dashboard() {
	$login = esc_url_raw( rest_url( 'ekinese/v1/business/login' ) );
	$data  = esc_url_raw( rest_url( 'ekinese/v1/business/portal' ) );
	$batch = esc_url_raw( rest_url( 'ekinese/v1/business/batch' ) );
	ob_start();
	echo '<section class="xg-bizpro" data-login="' . esc_attr( $login ) . '" data-portal="' . esc_attr( $data ) . '" data-batch="' . esc_attr( $batch ) . '">';
	echo '<div class="xg-container">';
	echo '<div class="xg-bizpro-login"><h1>Zakelijk portaal</h1><p>Log in met uw zakelijke e-mailadres; u ontvangt een beveiligde inloglink.</p>';
	echo '<form class="xg-bizpro-loginform"><input type="email" name="email" placeholder="zakelijk@bedrijf.nl" required><button type="submit" class="xg-final-btn">Stuur inloglink</button></form>';
	echo '<p class="xg-bizpro-msg" role="status"></p>';
	echo '<p class="xg-bizpro-apply">Nog geen account? <a href="/zakelijk/">Vraag een zakelijk account aan →</a></p></div>';
	echo '<div class="xg-bizpro-dash" hidden></div>';
	echo '</div></section>';
	return ob_get_clean();
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/business-dashboard' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/business.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-bizpro', get_theme_file_uri( 'assets/js/business.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* =====================================================================
   REST – login / portal / batch
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/business/login', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_business_login',
	) );
	register_rest_route( 'ekinese/v1', '/business/portal', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_business_portal_data',
	) );
	register_rest_route( 'ekinese/v1', '/business/batch', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_business_batch',
	) );
} );

function ekinese_business_login( WP_REST_Request $req ) {
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( is_email( $email ) && function_exists( 'ekinese_account_make_token' ) ) {
		$pid = ekinese_partner_by_email( $email );
		// Alleen een link sturen als er een (goedgekeurd) account is — maar nooit
		// onthullen of dat zo is (privacy).
		if ( $pid && get_post_meta( $pid, 'biz_status', true ) === 'approved' ) {
			$link = home_url( '/zakelijk/portaal/?token=' . ekinese_account_make_token( $email ) );
			wp_mail( $email, 'Uw inloglink — XGOUD zakelijk portaal', "Open uw portaal (24 uur geldig):\n$link" );
		}
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Als dit adres bij ons bekend is, ontvangt u een inloglink.' ) );
}

function ekinese_business_auth( WP_REST_Request $req ) {
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $req->get_param( 'token' ) ) : '';
	if ( ! $email ) {
		return array( 0, '' );
	}
	$pid = ekinese_partner_by_email( $email );
	if ( ! $pid || get_post_meta( $pid, 'biz_status', true ) !== 'approved' ) {
		return array( 0, $email );
	}
	return array( $pid, $email );
}

function ekinese_business_portal_data( WP_REST_Request $req ) {
	list( $pid, $email ) = ekinese_business_auth( $req );
	if ( ! $email ) {
		return new WP_Error( 'auth', 'Ongeldige of verlopen link.', array( 'status' => 401 ) );
	}
	if ( ! $pid ) {
		return rest_ensure_response( array( 'approved' => false, 'message' => 'Uw zakelijke account is nog niet geactiveerd.' ) );
	}
	$batches = array();
	foreach ( get_posts( array( 'post_type' => 'xg_batch', 'numberposts' => 50, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array( array( 'key' => 'email', 'value' => $email ) ) ) ) as $b ) {
		$items = json_decode( (string) get_post_meta( $b->ID, 'items', true ), true ) ?: array();
		$batches[] = array(
			'date'   => get_the_date( 'Y-m-d', $b ),
			'ref'    => $b->post_title,
			'lines'  => count( $items ),
			'total'  => '€ ' . number_format_i18n( (float) get_post_meta( $b->ID, 'total', true ), 2 ),
			'status' => get_post_meta( $b->ID, 'status', true ) ?: 'aangemeld',
		);
	}
	return rest_ensure_response( array(
		'approved'      => true,
		'company'       => get_post_meta( $pid, 'company', true ) ?: get_the_title( $pid ),
		'payment_terms' => get_post_meta( $pid, 'payment_terms', true ),
		'margin'        => (float) get_post_meta( $pid, 'biz_margin', true ),
		'prices'        => ekinese_partner_pricelist( $pid ),
		'batches'       => $batches,
	) );
}

function ekinese_business_batch( WP_REST_Request $req ) {
	list( $pid, $email ) = ekinese_business_auth( $req );
	if ( ! $pid ) {
		return new WP_Error( 'auth', 'Niet toegestaan.', array( 'status' => 403 ) );
	}
	$raw   = $req->get_param( 'items' );
	$items = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $items ) || ! $items ) {
		return new WP_Error( 'invalid', 'Voeg minstens één regel toe.', array( 'status' => 400 ) );
	}
	$prices = ekinese_partner_pricelist( $pid );
	$clean  = array();
	$total  = 0;
	foreach ( array_slice( $items, 0, 100 ) as $it ) {
		$metal  = sanitize_key( $it['metal'] ?? '' );
		$weight = (float) ( $it['weight'] ?? 0 );
		$purity = max( 0, min( 1000, (float) ( $it['purity'] ?? 0 ) ) );
		$qty    = max( 1, (int) ( $it['qty'] ?? 1 ) );
		if ( ! isset( $prices[ $metal ] ) || $weight <= 0 ) {
			continue;
		}
		$fine  = $weight * ( $purity / 1000 ) * $qty;
		$value = $fine * $prices[ $metal ]['price'];
		$total += $value;
		$clean[] = array( 'metal' => $metal, 'weight' => $weight, 'purity' => $purity, 'qty' => $qty, 'value' => round( $value, 2 ) );
	}
	if ( ! $clean ) {
		return new WP_Error( 'invalid', 'Geen geldige regels.', array( 'status' => 400 ) );
	}
	$ref = 'PARTIJ-' . gmdate( 'Ymd' ) . '-' . wp_rand( 100, 999 );
	$id  = wp_insert_post( array( 'post_type' => 'xg_batch', 'post_status' => 'publish', 'post_title' => $ref ) );
	if ( is_wp_error( $id ) || ! $id ) {
		return new WP_Error( 'save', 'Opslaan mislukt.', array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'company', get_post_meta( $pid, 'company', true ) );
	update_post_meta( $id, 'items', wp_json_encode( $clean ) );
	update_post_meta( $id, 'total', round( $total, 2 ) );
	update_post_meta( $id, 'status', 'aangemeld' );

	$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
	wp_mail( $admin, 'Nieuwe zakelijke partij: ' . $ref, sprintf( "Bedrijf: %s\nRegels: %d\nIndicatieve waarde: € %s\n", get_post_meta( $pid, 'company', true ), count( $clean ), number_format( $total, 2 ) ) );
	if ( function_exists( 'ekinese_notify' ) ) {
		ekinese_notify( $email, 'Partij aangemeld', sprintf( "Uw partij %s (indicatief € %s) is ontvangen. Wij plannen de taxatie en nemen contact op.", $ref, number_format( $total, 2 ) ), '', 'info' );
	}
	return rest_ensure_response( array( 'ok' => true, 'ref' => $ref, 'total' => '€ ' . number_format_i18n( $total, 2 ), 'message' => 'Uw partij is aangemeld. Wij plannen de taxatie.' ) );
}
