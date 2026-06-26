<?php
/**
 * XGOUD marketingplan — vaste routine-taken die automatisch in de dagelijkse
 * takenlijst verschijnen wanneer ze "due" zijn (dagelijks/wekelijks/maandelijks).
 * Zo wordt het marketingplan een herhaalbare routine i.p.v. een document.
 *
 * Afvinken gebeurt via het bestaande dagtaak-mechanisme (xg_task, per dag).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Het standaard-marketingplan. freq: 'daily' | 'weekly:N' (N=1 ma … 7 zo) |
 * 'monthly:D' (D = dag van de maand).
 */
function ekinese_marketing_plan() {
	$admin = admin_url();
	$plan = array(
		'mkt_social_reply'  => array( 'label' => 'Reageer op social-reacties (alle kanalen)', 'freq' => 'daily',      'link' => $admin . 'admin.php?page=xg-social-insights' ),
		'mkt_gbp'           => array( 'label' => 'Check Google Bedrijfsprofiel + nieuwe reviews', 'freq' => 'daily',  'link' => 'https://business.google.com/' ),
		'mkt_moments'       => array( 'label' => 'Modereer momenten-inzendingen', 'freq' => 'daily',                  'link' => $admin . 'edit.php?post_status=pending&post_type=xg_moment' ),
		'mkt_social_plan'   => array( 'label' => 'Plan social posts voor deze week', 'freq' => 'weekly:1',           'link' => $admin . 'edit.php?post_type=xg_social_post' ),
		'mkt_news'          => array( 'label' => 'Publiceer minstens 1 nieuwsartikel', 'freq' => 'weekly:2',         'link' => $admin . 'post-new.php' ),
		'mkt_seo_weak'      => array( 'label' => 'Verbeter 2 zwakke SEO/GEO-pagina\'s', 'freq' => 'weekly:4',        'link' => $admin . 'admin.php?page=xg-optimizer' ),
		'mkt_newsletter'    => array( 'label' => 'Stuur de wekelijkse nieuwsbrief', 'freq' => 'weekly:5',            'link' => $admin . 'edit.php?post_type=xg_subscriber' ),
		'mkt_ai_review'     => array( 'label' => 'Maandcijfers + AI-optimalisatieadvies bekijken', 'freq' => 'monthly:1', 'link' => $admin . 'admin.php?page=xg-optimizer' ),
		'mkt_survey'        => array( 'label' => 'Nieuwe vragenlijst of actie opzetten', 'freq' => 'monthly:15',     'link' => $admin . 'post-new.php?post_type=xg_survey' ),
	);
	$plan = apply_filters( 'ekinese_marketing_plan', $plan );
	if ( func_num_args() > 0 && false === func_get_arg( 0 ) ) {
		return $plan; // volledig plan, zonder off-filter
	}
	$off = (array) get_option( 'xg_marketing_off', array() );
	foreach ( array_keys( $plan ) as $k ) {
		if ( in_array( $k, $off, true ) ) {
			unset( $plan[ $k ] );
		}
	}
	return $plan;
}

/** Is een routine vandaag aan de beurt? */
function ekinese_marketing_due( $freq ) {
	if ( 'daily' === $freq ) {
		return true;
	}
	if ( 0 === strpos( $freq, 'weekly:' ) ) {
		return (int) substr( $freq, 7 ) === (int) current_time( 'N' );
	}
	if ( 0 === strpos( $freq, 'monthly:' ) ) {
		return (int) substr( $freq, 8 ) === (int) current_time( 'j' );
	}
	return false;
}

/* Voeg de "due" routines toe aan de dagelijkse taken (werkgebied = marketing via
   de 'mkt_'-prefix → ekinese_task_area). */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	foreach ( ekinese_marketing_plan() as $key => $r ) {
		if ( ekinese_marketing_due( $r['freq'] ) ) {
			$tasks[] = array( 'key' => $key, 'label' => '📣 ' . $r['label'], 'count' => 1, 'link' => $r['link'] );
		}
	}
	return $tasks;
} );

/* 'mkt_' → marketing-werkgebied (uitbreiding van de roles-mapping). */
add_filter( 'ekinese_task_area_override', function ( $area, $key ) {
	return ( 0 === strpos( (string) $key, 'mkt_' ) ) ? 'marketing' : $area;
}, 10, 2 );

/* =====================================================================
   ADMIN-PAGINA  "Marketingplan"
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Marketingplan', 'Marketingplan', 'manage_options', 'xg-marketing', 'ekinese_marketing_page' );
}, 47 );

add_action( 'admin_init', function () {
	if ( isset( $_POST['xg_mkt_save'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'xg_mkt_save' ) ) {
		// Alles wat NIET aangevinkt is → uit.
		$on  = isset( $_POST['xg_mkt_on'] ) ? array_map( 'sanitize_key', (array) $_POST['xg_mkt_on'] ) : array();
		$all = array_keys( ekinese_marketing_plan_all() );
		$off = array_values( array_diff( $all, $on ) );
		update_option( 'xg_marketing_off', $off, false );
		add_settings_error( 'xg_mkt', 'saved', 'Marketingplan opgeslagen.', 'success' );
	}
} );

/** Volledig plan (zonder de off-filter) voor de instellingenpagina. */
function ekinese_marketing_plan_all() {
	return ekinese_marketing_plan( false );
}

function ekinese_marketing_page() {
	settings_errors( 'xg_mkt' );
	$all = ekinese_marketing_plan_all();
	$off = (array) get_option( 'xg_marketing_off', array() );
	$freq_lbl = function ( $f ) {
		if ( 'daily' === $f ) { return 'Dagelijks'; }
		$days = array( 1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag', 5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag' );
		if ( 0 === strpos( $f, 'weekly:' ) ) { return 'Wekelijks (' . ( $days[ (int) substr( $f, 7 ) ] ?? '' ) . ')'; }
		if ( 0 === strpos( $f, 'monthly:' ) ) { return 'Maandelijks (dag ' . (int) substr( $f, 8 ) . ')'; }
		return $f;
	};
	echo '<div class="wrap"><h1>Marketingplan</h1>';
	echo '<p>Vaste marketing-routines. Aangevinkte routines verschijnen automatisch in de dagelijkse taken op de dag dat ze aan de beurt zijn (werkgebied: Marketing).</p>';
	echo '<form method="post">';
	wp_nonce_field( 'xg_mkt_save' );
	echo '<input type="hidden" name="xg_mkt_save" value="1">';
	echo '<table class="widefat striped"><thead><tr><th></th><th>Routine</th><th>Frequentie</th></tr></thead><tbody>';
	foreach ( $all as $key => $r ) {
		$on = ! in_array( $key, $off, true );
		echo '<tr><td><input type="checkbox" name="xg_mkt_on[]" value="' . esc_attr( $key ) . '" ' . checked( $on, true, false ) . '></td>';
		echo '<td>' . esc_html( $r['label'] ) . '</td><td>' . esc_html( $freq_lbl( $r['freq'] ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form></div>';
}
