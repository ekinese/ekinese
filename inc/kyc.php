<?php
/**
 * XGOUD KYC / Wwft & Opkopersregister (DOR) – compliant edelmetaal-inkoop.
 *
 * Op basis van de Nederlandse regelgeving:
 *   - Wwft: cliëntonderzoek + melding ongebruikelijke transacties (FIU-NL).
 *   - Identificatieplicht: bij INkoop vanaf € 3.000, bij verkoop vanaf € 250.
 *   - Contant ≥ € 10.000: verscherpt onderzoek + meldplicht.
 *   - Digitaal Opkopersregister (DOR): per object ID-nummer, land van afgifte,
 *     documenttype; kopie legitimatie 5 jaar bewaren.
 *   - Minimumleeftijd 18 jaar (geen toegang/verkoop onder 18).
 *
 * Self-built, geen plugin. Documenten worden veilig (niet-publiek) opgeslagen.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Wettelijke minimumleeftijd. */
const XG_MIN_AGE = 18;

/** Drempelbedragen (€). */
function ekinese_kyc_thresholds() {
	return array(
		'id_purchase'    => 3000,  // identificatie bij inkoop vanaf
		'id_sale'        => 250,   // identificatie bij verkoop vanaf
		'cash_unusual'   => 10000, // contant: verscherpt + melding
	);
}

/**
 * Verplichte documenten/velden bij een inkoop (afhankelijk van bedrag/betaling).
 *
 * @param float  $amount
 * @param string $payment cash|bank
 * @param bool   $business
 * @return array Checklist [ key => [label, required] ]
 */
function ekinese_kyc_required_docs( $amount = 0, $payment = 'bank', $business = false ) {
	$t    = ekinese_kyc_thresholds();
	$idok = $amount >= $t['id_purchase'];
	$list = array(
		'id_document'   => array( 'Geldig legitimatiebewijs (paspoort / ID-kaart / EU-ID), voor- en achterzijde', $idok ),
		'personal_data' => array( 'Naam, adres, woonplaats, geboortedatum, nationaliteit', $idok ),
		'doc_details'   => array( 'Documenttype, -nummer, land van afgifte en geldigheidsdatum', $idok ),
		'age_check'     => array( 'Leeftijdsverificatie (minimaal 18 jaar)', true ),
		'ownership'     => array( 'Verklaring/bewijs van rechtmatig eigendom & herkomst', $amount >= 500 ),
		'object_photo'  => array( "Foto's van de aangeboden objecten", true ),
		'signature'     => array( 'Handtekening / akkoordverklaring verkoper', true ),
	);
	if ( $payment === 'cash' && $amount >= $t['cash_unusual'] ) {
		$list['source_of_funds'] = array( 'Herkomst van de middelen (verscherpt cliëntonderzoek)', true );
		$list['unusual_report']  = array( 'Beoordeling melding ongebruikelijke transactie (FIU-NL)', true );
	}
	if ( $business ) {
		$list['kvk']  = array( 'KvK-uittreksel zakelijke verkoper', true );
		$list['ubo']  = array( 'UBO-/PEP-controle', true );
	}
	return $list;
}

/* =====================================================================
   LEEFTIJDSSCHRANS – geen toegang/registratie onder 18
===================================================================== */
/** Bereken leeftijd uit geboortedatum (Y-m-d). */
function ekinese_age_from_dob( $dob ) {
	$ts = strtotime( (string) $dob );
	if ( ! $ts ) {
		return 0;
	}
	return (int) floor( ( time() - $ts ) / YEAR_IN_SECONDS );
}

/** Is iemand oud genoeg? */
function ekinese_is_adult( $dob ) {
	return ekinese_age_from_dob( $dob ) >= XG_MIN_AGE;
}

/**
 * Registratie/aanmelding blokkeren onder 18. Sluit aan op het account/registratie-
 * proces (filter ekinese_registration_allowed).
 */
add_filter( 'ekinese_registration_allowed', function ( $allowed, $data ) {
	if ( ! empty( $data['dob'] ) && ! ekinese_is_adult( $data['dob'] ) ) {
		return new WP_Error( 'underage', __( 'U moet minimaal 18 jaar oud zijn om een account aan te maken of edelmetaal te verkopen.', 'ekinese' ) );
	}
	return $allowed;
}, 10, 2 );

/* =====================================================================
   KYC-/DOR-RECORD (per inkoop)
===================================================================== */
const XG_KYC_FIELDS = array(
	'customer_name', 'address', 'city', 'postcode', 'country', 'dob', 'nationality',
	'id_type', 'id_number', 'id_country', 'id_expiry',
	'amount', 'payment', 'business', 'kvk',
	'object_desc', 'origin', 'status', 'unusual', 'appointment',
);

function ekinese_register_kyc() {
	register_post_type( 'xg_kyc', array(
		'labels'    => array( 'name' => __( 'KYC / Opkopersregister', 'ekinese' ), 'singular_name' => __( 'KYC-dossier', 'ekinese' ), 'menu_name' => __( 'KYC / DOR', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-id-alt',
		'supports'  => array( 'title' ),
	) );
	// Documenten worden als attachments (niet-publiek) gekoppeld.
}
add_action( 'init', 'ekinese_register_kyc' );

/* ---- Admin: dossier + checklist + uploads ---- */
function ekinese_kyc_metabox() {
	add_meta_box( 'xg_kyc_meta', __( 'KYC-dossier (Wwft / DOR)', 'ekinese' ), 'ekinese_kyc_metabox_html', 'xg_kyc', 'normal', 'high' );
	add_meta_box( 'xg_kyc_check', __( 'Verplichte documenten', 'ekinese' ), 'ekinese_kyc_checklist_html', 'xg_kyc', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_kyc_metabox' );

function ekinese_kyc_metabox_html( $post ) {
	wp_nonce_field( 'xg_kyc_save', 'xg_kyc_nonce' );
	$txt = function ( $k, $lbl, $type = 'text' ) use ( $post ) {
		echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="' . esc_attr( $type ) . '" name="xgk_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
	};
	echo '<table class="form-table">';
	$txt( 'customer_name', 'Naam verkoper' );
	$txt( 'dob', 'Geboortedatum', 'date' );
	$txt( 'nationality', 'Nationaliteit' );
	$txt( 'address', 'Adres' );
	$txt( 'postcode', 'Postcode' );
	$txt( 'city', 'Woonplaats' );
	$txt( 'country', 'Land' );
	echo '<tr><th>Legitimatie</th><td>';
	echo '<select name="xgk_id_type"><option value="">—</option>';
	foreach ( array( 'paspoort' => 'Paspoort', 'idkaart' => 'ID-kaart', 'eu_id' => 'EU-identiteitskaart', 'rijbewijs' => 'Rijbewijs' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( get_post_meta( $post->ID, 'id_type', true ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select> nr <input type="text" name="xgk_id_number" value="' . esc_attr( get_post_meta( $post->ID, 'id_number', true ) ) . '"> land <input type="text" name="xgk_id_country" value="' . esc_attr( get_post_meta( $post->ID, 'id_country', true ) ) . '" size="6"> geldig tot <input type="date" name="xgk_id_expiry" value="' . esc_attr( get_post_meta( $post->ID, 'id_expiry', true ) ) . '"></td></tr>';
	$txt( 'amount', 'Inkoopbedrag (€)', 'number' );
	echo '<tr><th>Betaling</th><td><select name="xgk_payment"><option value="bank"' . selected( get_post_meta( $post->ID, 'payment', true ), 'bank', false ) . '>Bank</option><option value="cash"' . selected( get_post_meta( $post->ID, 'payment', true ), 'cash', false ) . '>Contant</option></select> &nbsp; <label><input type="checkbox" name="xgk_business" value="1"' . checked( get_post_meta( $post->ID, 'business', true ), '1', false ) . '> Zakelijke verkoper</label></td></tr>';
	echo '<tr><th>Object(en)</th><td><textarea name="xgk_object_desc" rows="2" class="large-text">' . esc_textarea( get_post_meta( $post->ID, 'object_desc', true ) ) . '</textarea></td></tr>';
	echo '<tr><th>Herkomst</th><td><input type="text" name="xgk_origin" value="' . esc_attr( get_post_meta( $post->ID, 'origin', true ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Documenten (scan)</th><td>';
	$docs = (array) get_post_meta( $post->ID, 'documents', true );
	foreach ( array_filter( $docs ) as $att ) {
		echo '<a href="' . esc_url( wp_get_attachment_url( $att ) ) . '" target="_blank">' . esc_html( get_the_title( $att ) ?: ( 'doc#' . $att ) ) . '</a><br>';
	}
	echo '<input type="file" name="xgk_doc[]" multiple accept="image/*,application/pdf"><p class="description">Legitimatie & bewijsstukken — niet-publiek, 5 jaar bewaard.</p></td></tr>';
	echo '</table>';
}

function ekinese_kyc_checklist_html( $post ) {
	$amount   = (float) get_post_meta( $post->ID, 'amount', true );
	$payment  = get_post_meta( $post->ID, 'payment', true ) ?: 'bank';
	$business = get_post_meta( $post->ID, 'business', true ) === '1';
	$dob      = get_post_meta( $post->ID, 'dob', true );
	// Leeftijdswaarschuwing.
	if ( $dob && ! ekinese_is_adult( $dob ) ) {
		echo '<div class="notice notice-error" style="padding:8px;margin:0 0 10px"><strong>⚠ Onder 18 — inkoop niet toegestaan.</strong></div>';
	}
	$done = (array) get_post_meta( $post->ID, 'checklist', true );
	echo '<p class="description">Vereist o.b.v. bedrag/betaling:</p>';
	foreach ( ekinese_kyc_required_docs( $amount, $payment, $business ) as $key => $d ) {
		if ( ! $d[1] ) {
			continue;
		}
		$chk = in_array( $key, $done, true ) ? ' checked' : '';
		echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="xgk_check[]" value="' . esc_attr( $key ) . '"' . $chk . '> ' . esc_html( $d[0] ) . '</label>';
	}
	if ( $payment === 'cash' && $amount >= ekinese_kyc_thresholds()['cash_unusual'] ) {
		echo '<div class="notice notice-warning" style="padding:8px;margin:10px 0"><strong>Contant ≥ € 10.000:</strong> verscherpt onderzoek + melding FIU-NL vereist.</div>';
	}
}

function ekinese_kyc_save( $post_id ) {
	if ( ! isset( $_POST['xg_kyc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_kyc_nonce'] ), 'xg_kyc_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	foreach ( XG_KYC_FIELDS as $f ) {
		if ( isset( $_POST[ 'xgk_' . $f ] ) ) {
			$v = wp_unslash( $_POST[ 'xgk_' . $f ] );
			update_post_meta( $post_id, $f, ( 'object_desc' === $f ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ) );
		}
	}
	update_post_meta( $post_id, 'business', isset( $_POST['xgk_business'] ) ? '1' : '' );
	update_post_meta( $post_id, 'checklist', isset( $_POST['xgk_check'] ) ? array_map( 'sanitize_key', (array) $_POST['xgk_check'] ) : array() );
	// Markeer ongebruikelijke transactie.
	$amount  = (float) get_post_meta( $post_id, 'amount', true );
	$payment = get_post_meta( $post_id, 'payment', true );
	update_post_meta( $post_id, 'unusual', ( 'cash' === $payment && $amount >= ekinese_kyc_thresholds()['cash_unusual'] ) ? '1' : '' );

	// Documenten uploaden (niet-publiek).
	if ( ! empty( $_FILES['xgk_doc']['name'][0] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$docs  = (array) get_post_meta( $post_id, 'documents', true );
		$files = $_FILES['xgk_doc'];
		$count = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
		for ( $i = 0; $i < $count; $i++ ) {
			if ( empty( $files['name'][ $i ] ) ) {
				continue;
			}
			$_FILES['xgk_doc_single'] = array(
				'name' => $files['name'][ $i ], 'type' => $files['type'][ $i ], 'tmp_name' => $files['tmp_name'][ $i ],
				'error' => $files['error'][ $i ], 'size' => $files['size'][ $i ],
			);
			$att = media_handle_upload( 'xgk_doc_single', $post_id );
			if ( ! is_wp_error( $att ) ) {
				$docs[] = $att;
			}
		}
		update_post_meta( $post_id, 'documents', array_values( array_filter( $docs ) ) );
	}
}
add_action( 'save_post_xg_kyc', 'ekinese_kyc_save' );

/** Bewaarplicht: KYC-dossiers 5 jaar (geen automatische verwijdering vóór dat). */
add_filter( 'ekinese_retention_days', function ( $days, $type ) {
	return ( 'xg_kyc' === $type ) ? max( $days, 5 * 365 ) : $days;
}, 10, 2 );

/** Niet indexeren. */
add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular( 'xg_kyc' ) ) {
		$r['noindex'] = true;
	}
	return $r;
} );
