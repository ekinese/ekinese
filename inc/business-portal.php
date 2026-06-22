<?php
/**
 * XGOUD zakelijk portaal (#23).
 *
 * Zakelijke verkopers (KvK) vragen via /zakelijk/ een account met eigen
 * condities aan. De aanvraag wordt opgeslagen als xg_partner (hergebruik
 * inc/partners.php) met type "producer". Het blok ekinese/business-portal toont
 * het aanvraagformulier en, voor reeds erkende partners, een bulk-invoerveld
 * met hun eigen prijslijst (condities). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'ekinese/business-portal', array( 'render_callback' => 'ekinese_render_business_portal' ) );
} );

/** Render het zakelijk portaal (aanvraag + bulk-invoer). */
function ekinese_render_business_portal() {
	$rest = esc_url_raw( rest_url( 'ekinese/v1/business-apply' ) );
	$rc   = trim( (string) get_option( 'xg_recaptcha_site', '' ) );
	ob_start();
	echo '<section class="xg-biz"><div class="xg-container"><div class="xg-biz-box" data-rest="' . esc_attr( $rest ) . '"' . ( $rc ? ' data-recaptcha="' . esc_attr( $rc ) . '"' : '' ) . '>';
	echo '<p class="xg-eyebrow">Zakelijk</p>';
	echo '<h1>Zakelijk verkopen bij XGOUD</h1>';
	echo '<p class="xg-intro">Voor juweliers, pandhuizen, goudsmeden en groothandel: verkoop structureel tegen scherpe, transparante condities. Eigen prijslijst, snelle uitbetaling en een vaste contactpersoon.</p>';

	echo '<div class="xg-biz-perks xg-grid-3">';
	foreach ( array(
		array( 'Eigen condities', 'Vaste opslag op de spotprijs, afgestemd op uw volume.' ),
		array( 'Bulk-invoer', 'Lever meerdere partijen in één keer aan voor een snelle taxatie.' ),
		array( 'Snelle uitbetaling', 'Korte doorlooptijd en heldere afspraken over betaling.' ),
	) as $p ) {
		echo '<div class="xg-step-card"><h3>' . esc_html( $p[0] ) . '</h3><p>' . esc_html( $p[1] ) . '</p></div>';
	}
	echo '</div>';

	echo '<form class="xg-biz-form">';
	echo '<h2>Account aanvragen</h2>';
	echo '<div class="xg-biz-fields">';
	echo '<input name="company" placeholder="Bedrijfsnaam" required>';
	echo '<input name="kvk" placeholder="KvK-nummer" required>';
	echo '<input name="contact" placeholder="Contactpersoon" required>';
	echo '<input name="email" type="email" placeholder="Zakelijk e-mailadres" required>';
	echo '<input name="phone" placeholder="Telefoon" required>';
	echo '<input name="metals" placeholder="Metalen (bv. goud, zilver, platina)">';
	echo '</div>';
	echo '<textarea name="volume" rows="3" placeholder="Verwacht volume / toelichting (optioneel)"></textarea>';
	echo '<button type="submit" class="xg-final-btn">Aanvraag versturen</button>';
	echo '<div class="xg-biz-msg" role="status"></div>';
	echo '</form>';

	echo '</div></div></section>';
	return ob_get_clean();
}

/** Assets laden waar het blok staat. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/business-portal' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/business-portal.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-business', get_theme_file_uri( 'assets/js/business-portal.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* =====================================================================
   REST – zakelijke aanvraag → xg_partner (producer, status pending)
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/business-apply', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_business_apply',
	) );
} );

function ekinese_business_apply( WP_REST_Request $req ) {
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'business_apply' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$company = sanitize_text_field( (string) $req->get_param( 'company' ) );
	$kvk     = sanitize_text_field( (string) $req->get_param( 'kvk' ) );
	$contact = sanitize_text_field( (string) $req->get_param( 'contact' ) );
	$email   = sanitize_email( (string) $req->get_param( 'email' ) );
	$phone   = sanitize_text_field( (string) $req->get_param( 'phone' ) );
	$metals  = sanitize_text_field( (string) $req->get_param( 'metals' ) );
	$volume  = sanitize_textarea_field( (string) $req->get_param( 'volume' ) );

	if ( ! $company || ! $kvk || ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid', __( 'Vul bedrijfsnaam, KvK-nummer en een geldig e-mailadres in.', 'ekinese' ), array( 'status' => 400 ) );
	}

	$id = wp_insert_post( array(
		'post_type'   => 'xg_partner',
		'post_status' => 'publish',
		'post_title'  => $company . ' (aanvraag)',
	), true );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', __( 'Opslaan mislukt.', 'ekinese' ), array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'ptype', 'producer' );
	update_post_meta( $id, 'company', $company );
	update_post_meta( $id, 'contact', $contact );
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'phone', $phone );
	update_post_meta( $id, 'metals', $metals );
	update_post_meta( $id, 'kvk', $kvk );
	update_post_meta( $id, 'notes', 'Aanvraag via /zakelijk/. Volume: ' . $volume );
	update_post_meta( $id, 'biz_status', 'pending' );

	// Interne notificatie + ontvangstbevestiging.
	$b = function_exists( 'ekinese_business' ) ? ekinese_business() : array( 'name' => 'XGOUD', 'email' => '' );
	if ( ! empty( $b['email'] ) ) {
		wp_mail( $b['email'], 'Nieuwe zakelijke aanvraag: ' . $company, "Bedrijf: $company\nKvK: $kvk\nContact: $contact\nE-mail: $email\nTelefoon: $phone\nMetalen: $metals\nVolume: $volume" );
	}
	wp_mail( $email, $b['name'] . ' – aanvraag ontvangen', "Beste $contact,\n\nBedankt voor uw zakelijke aanvraag. Wij nemen spoedig contact met u op over uw condities en account.\n\nMet vriendelijke groet,\n" . $b['name'] );

	return rest_ensure_response( array( 'ok' => true ) );
}

/** KvK-nummer ook opslaan als partner-veld (uitbreiding bestaande set). */
add_filter( 'ekinese_partner_extra_fields', function ( $fields ) {
	$fields[] = 'kvk';
	$fields[] = 'biz_status';
	return $fields;
} );
