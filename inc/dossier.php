<?php
/**
 * XGOUD klant-dossier & verificatie (anti-criminaliteit).
 *
 *  - IBAN-controle (structuur + mod-97).
 *  - Per-klant dossier (op e-mail): wie, waar vandaan (adres), beroep,
 *    verificatiestatus, risico-inschatting (achtergrondcheck) en een activiteiten-
 *    log. Wettelijk kader: Wwft/opkopersregister (zie ook inc/kyc.php).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   IBAN-CONTROLE
===================================================================== */
function ekinese_iban_format( $iban ) {
	return strtoupper( preg_replace( '/\s+/', '', (string) $iban ) );
}

/** Geldig IBAN? (lengte per land + mod-97 = 1). */
function ekinese_iban_valid( $iban ) {
	$iban = ekinese_iban_format( $iban );
	if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/', $iban ) ) {
		return false;
	}
	$lengths = array( 'NL' => 18, 'BE' => 16, 'DE' => 22, 'FR' => 27, 'LU' => 20, 'GB' => 22, 'ES' => 24, 'IT' => 27, 'AT' => 20, 'CH' => 21, 'PL' => 28, 'PT' => 25 );
	$cc = substr( $iban, 0, 2 );
	if ( isset( $lengths[ $cc ] ) && strlen( $iban ) !== $lengths[ $cc ] ) {
		return false;
	}
	$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
	$digits = '';
	for ( $i = 0, $n = strlen( $rearranged ); $i < $n; $i++ ) {
		$ch = $rearranged[ $i ];
		$digits .= ctype_alpha( $ch ) ? (string) ( ord( $ch ) - 55 ) : $ch;
	}
	// mod-97 op een lange string (stuksgewijs).
	$rem = '';
	for ( $i = 0, $n = strlen( $digits ); $i < $n; $i++ ) {
		$rem = ( $rem . $digits[ $i ] ) % 97;
	}
	return 1 === (int) $rem;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/iban/check', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			$iban = ekinese_iban_format( (string) $r->get_param( 'iban' ) );
			return rest_ensure_response( array( 'valid' => ekinese_iban_valid( $iban ), 'iban' => $iban ) );
		},
	) );
} );

/* =====================================================================
   CPT  xg_dossier  (op e-mail)
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_dossier', array(
		'labels'       => array( 'name' => 'Dossiers', 'singular_name' => 'Dossier' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => false, // eigen pagina
		'supports'     => array( 'title' ),
	) );
} );

function ekinese_dossier_risks() {
	return array(
		'laag'        => array( 'label' => 'Laag', 'color' => '#1f9d55' ),
		'midden'      => array( 'label' => 'Midden', 'color' => '#dba617' ),
		'hoog'        => array( 'label' => 'Hoog', 'color' => '#d63638' ),
		'geblokkeerd' => array( 'label' => 'Geblokkeerd', 'color' => '#161412' ),
	);
}

function ekinese_dossier_id( $email, $create = false ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return 0;
	}
	$q = get_posts( array( 'post_type' => 'xg_dossier', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'email', 'meta_value' => $email ) );
	if ( $q ) {
		return (int) $q[0];
	}
	if ( ! $create ) {
		return 0;
	}
	$id = wp_insert_post( array( 'post_type' => 'xg_dossier', 'post_status' => 'publish', 'post_title' => $email ) );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'email', $email );
		update_post_meta( $id, 'created', current_time( 'mysql' ) );
		// Naam/telefoon overnemen uit het account-profiel.
		if ( function_exists( 'ekinese_account_profile_id' ) ) {
			$pid = ekinese_account_profile_id( $email );
			if ( $pid ) {
				update_post_meta( $id, 'name', get_post_meta( $pid, 'name', true ) );
				update_post_meta( $id, 'phone', get_post_meta( $pid, 'phone', true ) );
			}
		}
		return (int) $id;
	}
	return 0;
}

/** Activiteit loggen (max 100 regels per dossier). */
function ekinese_dossier_log( $email, $event, $detail = '' ) {
	$id = ekinese_dossier_id( $email, true );
	if ( ! $id ) {
		return;
	}
	$log = json_decode( (string) get_post_meta( $id, 'log', true ), true );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => current_time( 'mysql' ), 'e' => sanitize_text_field( $event ), 'd' => sanitize_text_field( $detail ) ) );
	$log = array_slice( $log, 0, 100 );
	update_post_meta( $id, 'log', wp_json_encode( $log ) );
}

function ekinese_dossier_is_blocked( $email ) {
	$id = ekinese_dossier_id( $email );
	return $id && 'geblokkeerd' === get_post_meta( $id, 'risk', true );
}

/** Automatische risico-signalen (achtergrondcheck, intern). */
function ekinese_dossier_signals( $id ) {
	$email   = (string) get_post_meta( $id, 'email', true );
	$signals = array();
	if ( ! get_post_meta( $id, 'id_verified', true ) ) {
		$signals[] = 'Identiteit niet geverifieerd';
	}
	if ( ! get_post_meta( $id, 'address_verified', true ) ) {
		$signals[] = 'Adres niet geverifieerd';
	}
	// Hoog transactievolume?
	$appts = $email ? count( get_posts( array( 'post_type' => 'xg_appointment', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'email', 'meta_value' => $email ) ) ) : 0;
	if ( $appts >= 10 ) {
		$signals[] = 'Veel transacties (' . $appts . ') — controleer herkomst';
	}
	// KYC ontbreekt terwijl er afspraken zijn.
	$kyc = $email ? count( get_posts( array( 'post_type' => 'xg_kyc', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'email', 'meta_value' => $email ) ) ) : 0;
	if ( $appts > 0 && ! $kyc ) {
		$signals[] = 'Transacties zonder KYC-registratie';
	}
	return $signals;
}

/* =====================================================================
   AUTO-HOOKS: dossier vullen + loggen
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	// Eénmaal per dag een "ingelogd"-log + zorg dat het dossier bestaat.
	$flag = 'xg_dlog_' . md5( $email . current_time( 'Y-m-d' ) );
	if ( ! get_transient( $flag ) ) {
		ekinese_dossier_log( $email, 'ingelogd', 'Mijn XGOUD geopend' );
		set_transient( $flag, 1, DAY_IN_SECONDS );
	}
	$data['blocked'] = ekinese_dossier_is_blocked( $email ) ? 1 : 0;
	return $data;
}, 4, 2 );

add_action( 'save_post_xg_appointment', function ( $id, $post ) {
	if ( wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	$email = get_post_meta( $id, 'email', true );
	if ( is_email( $email ) ) {
		ekinese_dossier_log( $email, 'afspraak', 'Afspraak #' . $id . ' (' . get_post_meta( $id, 'service', true ) . ')' );
	}
}, 20, 2 );

/* =====================================================================
   ADMIN: Dossiers (lijst + detail)
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Dossiers', 'Dossiers', 'manage_options', 'xg-dossiers', 'ekinese_dossiers_page' );
}, 4 );

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['xg_dossier_save'] ) || ! check_admin_referer( 'xg_dossier_save' ) ) {
		return;
	}
	$id = (int) $_POST['xg_dossier_id'];
	if ( get_post_type( $id ) !== 'xg_dossier' ) {
		return;
	}
	foreach ( array( 'name', 'phone', 'address', 'postcode', 'city', 'country', 'birthdate', 'profession', 'iban', 'risk_note' ) as $f ) {
		if ( isset( $_POST[ 'xg_d_' . $f ] ) ) {
			update_post_meta( $id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xg_d_' . $f ] ) ) );
		}
	}
	update_post_meta( $id, 'id_verified', isset( $_POST['xg_d_id_verified'] ) ? 1 : 0 );
	update_post_meta( $id, 'address_verified', isset( $_POST['xg_d_address_verified'] ) ? 1 : 0 );
	$risk = sanitize_key( $_POST['xg_d_risk'] ?? 'laag' );
	update_post_meta( $id, 'risk', array_key_exists( $risk, ekinese_dossier_risks() ) ? $risk : 'laag' );
	// IBAN-validatie-melding.
	$iban = ekinese_iban_format( $_POST['xg_d_iban'] ?? '' );
	update_post_meta( $id, 'iban_valid', ( $iban && ekinese_iban_valid( $iban ) ) ? 1 : 0 );
	add_settings_error( 'xg_dossier', 'saved', 'Dossier opgeslagen.', 'success' );
} );

function ekinese_dossiers_page() {
	settings_errors( 'xg_dossier' );
	$risks = ekinese_dossier_risks();
	// Detailweergave?
	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
	if ( $email ) {
		ekinese_dossier_detail( ekinese_dossier_id( $email, true ) );
		return;
	}
	echo '<div class="wrap"><h1>Dossiers</h1>';
	echo '<p>Klantdossiers met verificatie- en risico-inschatting (anti-criminaliteit, Wwft).</p>';
	// Zoeken.
	echo '<form method="get"><input type="hidden" name="page" value="xg-dossiers"><p><input type="search" name="email" placeholder="zoek op e-mailadres" class="regular-text"> <button class="button">Open dossier</button></p></form>';
	$all = get_posts( array( 'post_type' => 'xg_dossier', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'modified' ) );
	echo '<table class="widefat striped"><thead><tr><th>E-mail</th><th>Naam</th><th>Plaats</th><th>Geverifieerd</th><th>Risico</th><th></th></tr></thead><tbody>';
	foreach ( $all as $p ) {
		$r = (string) get_post_meta( $p->ID, 'risk', true ) ?: 'laag';
		$rc = $risks[ $r ] ?? $risks['laag'];
		$idv = get_post_meta( $p->ID, 'id_verified', true ) ? 'ID' : '';
		$adv = get_post_meta( $p->ID, 'address_verified', true ) ? 'adres' : '';
		$em = (string) get_post_meta( $p->ID, 'email', true );
		echo '<tr><td>' . esc_html( $em ) . '</td><td>' . esc_html( get_post_meta( $p->ID, 'name', true ) ) . '</td><td>' . esc_html( get_post_meta( $p->ID, 'city', true ) ) . '</td>';
		echo '<td>' . esc_html( trim( $idv . ' ' . $adv ) ?: '—' ) . '</td>';
		echo '<td><span style="background:' . esc_attr( $rc['color'] ) . ';color:#fff;padding:1px 7px;border-radius:3px">' . esc_html( $rc['label'] ) . '</span></td>';
		echo '<td><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=xg-dossiers&email=' . rawurlencode( $em ) ) ) . '">Open</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

function ekinese_dossier_detail( $id ) {
	$risks = ekinese_dossier_risks();
	$m = function ( $k ) use ( $id ) { return esc_attr( get_post_meta( $id, $k, true ) ); };
	$email = (string) get_post_meta( $id, 'email', true );
	$signals = ekinese_dossier_signals( $id );
	$log = json_decode( (string) get_post_meta( $id, 'log', true ), true );
	$log = is_array( $log ) ? $log : array();
	$iban = (string) get_post_meta( $id, 'iban', true );

	echo '<div class="wrap"><h1>Dossier: ' . esc_html( $email ) . '</h1>';
	echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=xg-dossiers' ) ) . '">&larr; Alle dossiers</a></p>';
	echo '<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px">';

	// Linkerkolom: gegevens + verificatie.
	echo '<form method="post"><div style="background:#fff;border:1px solid #dcdcde;padding:16px">';
	wp_nonce_field( 'xg_dossier_save' );
	echo '<input type="hidden" name="xg_dossier_save" value="1"><input type="hidden" name="xg_dossier_id" value="' . (int) $id . '">';
	echo '<h2 style="margin-top:0">Wie &amp; waar vandaan</h2><table class="form-table"><tbody>';
	$row = function ( $lbl, $k, $type = 'text' ) use ( $m ) {
		echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="' . esc_attr( $type ) . '" name="xg_d_' . esc_attr( $k ) . '" value="' . $m( $k ) . '" class="regular-text"></td></tr>';
	};
	$row( 'Naam', 'name' ); $row( 'Telefoon', 'phone' ); $row( 'Geboortedatum', 'birthdate', 'date' ); $row( 'Beroep', 'profession' );
	$row( 'Adres', 'address' ); $row( 'Postcode', 'postcode' ); $row( 'Plaats', 'city' ); $row( 'Land', 'country' );
	echo '<tr><th>IBAN</th><td><input type="text" name="xg_d_iban" value="' . esc_attr( $iban ) . '" class="regular-text">';
	if ( $iban ) {
		echo ekinese_iban_valid( $iban ) ? ' <span style="color:#1f9d55">✓ geldig</span>' : ' <span style="color:#d63638">✗ ongeldig</span>'; // phpcs:ignore
	}
	echo '</td></tr>';
	echo '<tr><th>Verificatie</th><td><label><input type="checkbox" name="xg_d_id_verified" ' . checked( get_post_meta( $id, 'id_verified', true ), 1, false ) . '> Identiteit (ID) geverifieerd</label><br>';
	echo '<label><input type="checkbox" name="xg_d_address_verified" ' . checked( get_post_meta( $id, 'address_verified', true ), 1, false ) . '> Adres geverifieerd</label></td></tr>';
	echo '<tr><th>Risico</th><td><select name="xg_d_risk">';
	$cur = get_post_meta( $id, 'risk', true ) ?: 'laag';
	foreach ( $risks as $k => $r ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $r['label'] ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>Notitie</th><td><input type="text" name="xg_d_risk_note" value="' . $m( 'risk_note' ) . '" class="large-text"></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Dossier opslaan' );
	echo '</div></form>';

	// Rechterkolom: achtergrondcheck + log.
	echo '<div>';
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-bottom:14px"><h2 style="margin-top:0">Achtergrondcheck</h2>';
	if ( $signals ) {
		echo '<ul style="margin:0;color:#b32d2e">';
		foreach ( $signals as $s ) {
			echo '<li>⚠ ' . esc_html( $s ) . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<p style="color:#1f9d55">Geen interne risicosignalen.</p>';
	}
	echo '<p class="description" style="margin-top:10px">Externe checks: ';
	echo '<a target="_blank" rel="noopener" href="https://www.kvk.nl/zoeken/?source=all&q=' . rawurlencode( get_post_meta( $id, 'name', true ) ) . '">KvK</a> · ';
	echo '<a target="_blank" rel="noopener" href="https://www.opensanctions.org/search/?q=' . rawurlencode( get_post_meta( $id, 'name', true ) ) . '">OpenSanctions</a></p>';
	echo '</div>';

	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0">Activiteitenlog</h2>';
	if ( $log ) {
		echo '<ul style="margin:0;font-size:13px">';
		foreach ( array_slice( $log, 0, 30 ) as $l ) {
			echo '<li><strong>' . esc_html( $l['e'] ) . '</strong> — ' . esc_html( $l['d'] ) . '<br><span style="color:#646970">' . esc_html( $l['t'] ) . '</span></li>';
		}
		echo '</ul>';
	} else {
		echo '<p>Nog geen activiteit.</p>';
	}
	echo '</div></div>';

	echo '</div></div>';
}

/* Daily task: hoog-risico of nieuwe ongeverifieerde dossiers. */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$hoog = count( get_posts( array( 'post_type' => 'xg_dossier', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'risk', 'value' => array( 'hoog', 'geblokkeerd' ), 'compare' => 'IN' ) ) ) ) );
	if ( $hoog > 0 ) {
		$tasks[] = array( 'key' => 'dossier_risk', 'label' => 'Hoog-risico dossiers controleren', 'count' => $hoog, 'link' => admin_url( 'admin.php?page=xg-dossiers' ) );
	}
	return $tasks;
} );
