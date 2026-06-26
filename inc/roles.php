<?php
/**
 * XGOUD teamrollen — duidelijke verdeling "wie doet wat" (3 personen).
 *
 * Drie werkgebieden; elke medewerker krijgt er één toegewezen. De dagelijkse
 * taken in het dashboard worden per werkgebied gelabeld, zodat iedereen ziet
 * wat van hem/haar is.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** De drie werkgebieden. */
function ekinese_role_areas() {
	return array(
		'beheer'    => array( 'label' => 'Beheer & financiën', 'color' => '#161412', 'desc' => 'Overzicht, financiën, treuhand-uitbetaling, boekhouding, KYC-eindcontrole, veilingafhandeling.' ),
		'operatie'  => array( 'label' => 'Operatie & klant',   'color' => '#AE1E1E', 'desc' => 'Afspraken, tickets, vragen, pickup, fleet, KYC-intake, treuhand-facturen.' ),
		'marketing' => array( 'label' => 'Marketing & content','color' => '#D0AC4B', 'desc' => 'Content, SEO, nieuws, social media, momenten-moderatie, vragenlijsten.' ),
	);
}

/** Map een taak-key naar een werkgebied. */
function ekinese_task_area( $key ) {
	$key = (string) $key;
	$map = array(
		'operatie'  => array( 'appt', 'tickets', 'questions', 'kyc', 'pickup', 'mp_', 'fleet', 'escrow_inv', 'verify', 'depot' ),
		'marketing' => array( 'moments', 'survey', 'si_', 'news', 'seo', 'marketing', 'mkt_' ),
		'beheer'    => array( 'auctions', 'escrow_rel', 'dossier', 'depot_renew' ),
	);
	$result = 'beheer';
	foreach ( $map as $area => $frags ) {
		foreach ( $frags as $f ) {
			if ( false !== strpos( $key, $f ) ) {
				$result = $area;
				break 2;
			}
		}
	}
	return apply_filters( 'ekinese_task_area_override', $result, $key );
}

/** Het werkgebied van de huidige gebruiker (default beheer voor admins). */
function ekinese_current_area() {
	$a = get_user_meta( get_current_user_id(), 'xg_area', true );
	$areas = ekinese_role_areas();
	return isset( $areas[ $a ] ) ? $a : 'beheer';
}

/* Badge-helper voor een werkgebied. */
function ekinese_area_badge( $area ) {
	$areas = ekinese_role_areas();
	if ( ! isset( $areas[ $area ] ) ) {
		return '';
	}
	$c = $areas[ $area ];
	return '<span style="background:' . esc_attr( $c['color'] ) . ';color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;white-space:nowrap">' . esc_html( $c['label'] ) . '</span>';
}

/* =====================================================================
   PROFIELVELD: werkgebied per gebruiker
===================================================================== */
function ekinese_role_profile_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$cur   = get_user_meta( $user->ID, 'xg_area', true );
	$areas = ekinese_role_areas();
	echo '<h2>XGOUD werkgebied</h2><table class="form-table"><tr><th><label for="xg_area">Werkgebied</label></th><td>';
	echo '<select name="xg_area" id="xg_area">';
	echo '<option value="">— geen —</option>';
	foreach ( $areas as $k => $a ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $a['label'] ) . '</option>';
	}
	echo '</select><p class="description">Bepaalt welke dagelijkse taken als "voor jou" worden gemarkeerd.</p></td></tr></table>';
}
add_action( 'show_user_profile', 'ekinese_role_profile_field' );
add_action( 'edit_user_profile', 'ekinese_role_profile_field' );

function ekinese_role_profile_save( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['xg_area'] ) ) {
		update_user_meta( $user_id, 'xg_area', sanitize_key( wp_unslash( $_POST['xg_area'] ) ) );
	}
}
add_action( 'personal_options_update', 'ekinese_role_profile_save' );
add_action( 'edit_user_profile_update', 'ekinese_role_profile_save' );

/* =====================================================================
   ADMIN-PAGINA "Teamrollen"
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Teamrollen', 'Teamrollen', 'manage_options', 'xg-roles', 'ekinese_roles_page' );
}, 3 );

add_action( 'admin_init', function () {
	if ( isset( $_POST['xg_roles_save'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'xg_roles_save' ) ) {
		$assign = isset( $_POST['xg_user_area'] ) ? (array) $_POST['xg_user_area'] : array();
		foreach ( $assign as $uid => $area ) {
			update_user_meta( (int) $uid, 'xg_area', sanitize_key( $area ) );
		}
		add_settings_error( 'xg_roles', 'saved', 'Toewijzingen opgeslagen.', 'success' );
	}
} );

function ekinese_roles_page() {
	settings_errors( 'xg_roles' );
	$areas = ekinese_role_areas();
	$users = get_users( array( 'orderby' => 'display_name' ) );
	echo '<div class="wrap"><h1>Teamrollen</h1>';
	echo '<p>Wie doet wat? Wijs elke medewerker een werkgebied toe. In het dashboard worden de dagelijkse taken hiermee gelabeld.</p>';

	echo '<h2>Werkgebieden</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:22px">';
	foreach ( $areas as $a ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-top:4px solid ' . esc_attr( $a['color'] ) . ';padding:14px 16px">';
		echo '<h3 style="margin:0 0 6px">' . esc_html( $a['label'] ) . '</h3><p style="margin:0;color:#646970">' . esc_html( $a['desc'] ) . '</p></div>';
	}
	echo '</div>';

	echo '<h2>Toewijzing per medewerker</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'xg_roles_save' );
	echo '<input type="hidden" name="xg_roles_save" value="1">';
	echo '<table class="widefat striped"><thead><tr><th>Naam</th><th>E-mail</th><th>WP-rol</th><th>XGOUD-werkgebied</th></tr></thead><tbody>';
	foreach ( $users as $u ) {
		$cur = get_user_meta( $u->ID, 'xg_area', true );
		echo '<tr><td><strong>' . esc_html( $u->display_name ) . '</strong></td><td>' . esc_html( $u->user_email ) . '</td><td>' . esc_html( implode( ', ', $u->roles ) ) . '</td><td>';
		echo '<select name="xg_user_area[' . (int) $u->ID . ']"><option value="">— geen —</option>';
		foreach ( $areas as $k => $a ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $a['label'] ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form></div>';
}
