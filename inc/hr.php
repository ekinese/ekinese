<?php
/**
 * XGOUD HR – personeelsbeheer & urenregistratie.
 *
 * Breidt de bestaande medewerker-CPT (xg_employee, inc/scheduling.php) uit met
 * HR-velden en een urenregistratie (xg_timeentry). Statistieken per medewerker:
 * gewerkte uren, aantal afspraken/deals. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_HR_FIELDS = array( 'role', 'hire_date', 'contract_hours', 'hourly_rate', 'phone', 'email', 'iban' );

/** Time-entry CPT. */
function ekinese_register_hr() {
	register_post_type( 'xg_timeentry', array(
		'labels'    => array( 'name' => __( 'Urenregistratie', 'ekinese' ), 'singular_name' => __( 'Urenboeking', 'ekinese' ), 'menu_name' => __( 'Uren', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-clock',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_hr' );

/* ---- HR-velden op de medewerker ---- */
function ekinese_hr_metabox() {
	add_meta_box( 'xg_hr_meta', __( 'HR-gegevens', 'ekinese' ), 'ekinese_hr_metabox_html', 'xg_employee', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'ekinese_hr_metabox' );

function ekinese_hr_metabox_html( $post ) {
	wp_nonce_field( 'xg_hr_save', 'xg_hr_nonce' );
	$rows = array(
		'role'           => array( 'Functie', 'text' ),
		'hire_date'      => array( 'In dienst sinds', 'date' ),
		'contract_hours' => array( 'Contracturen/week', 'number' ),
		'hourly_rate'    => array( 'Uurtarief (€)', 'number' ),
		'phone'          => array( 'Telefoon', 'text' ),
		'email'          => array( 'E-mail', 'email' ),
		'iban'           => array( 'IBAN', 'text' ),
	);
	echo '<table class="form-table">';
	foreach ( $rows as $k => $r ) {
		echo '<tr><th style="width:120px">' . esc_html( $r[0] ) . '</th><td><input type="' . esc_attr( $r[1] ) . '" name="xghr_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" style="width:100%"></td></tr>';
	}
	echo '</table>';
	// Samenvatting uren (deze maand).
	$stats = ekinese_hr_stats( $post->ID );
	echo '<p><strong>' . esc_html__( 'Deze maand:', 'ekinese' ) . '</strong> ' . esc_html( $stats['hours_month'] ) . ' uur · ' . esc_html( (string) $stats['appointments'] ) . ' afspraken</p>';
}

function ekinese_hr_save( $post_id ) {
	if ( ! isset( $_POST['xg_hr_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_hr_nonce'] ), 'xg_hr_save' ) ) {
		return;
	}
	foreach ( XG_HR_FIELDS as $f ) {
		if ( isset( $_POST[ 'xghr_' . $f ] ) ) {
			update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xghr_' . $f ] ) ) );
		}
	}
}
add_action( 'save_post_xg_employee', 'ekinese_hr_save' );

/* ---- Urenboeking ---- */
function ekinese_hr_entry_metabox() {
	add_meta_box( 'xg_te_meta', __( 'Urenboeking', 'ekinese' ), 'ekinese_hr_entry_html', 'xg_timeentry', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_hr_entry_metabox' );

function ekinese_hr_entry_html( $post ) {
	wp_nonce_field( 'xg_te_save', 'xg_te_nonce' );
	echo '<table class="form-table">';
	// Medewerker
	echo '<tr><th>Medewerker</th><td><select name="xgte_employee">';
	$emp = get_post_meta( $post->ID, 'employee', true );
	foreach ( get_posts( array( 'post_type' => 'xg_employee', 'numberposts' => -1, 'post_status' => 'publish' ) ) as $e ) {
		echo '<option value="' . esc_attr( $e->ID ) . '"' . selected( $emp, $e->ID, false ) . '>' . esc_html( $e->post_title ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>Datum</th><td><input type="date" name="xgte_date" value="' . esc_attr( get_post_meta( $post->ID, 'date', true ) ?: current_time( 'Y-m-d' ) ) . '"></td></tr>';
	echo '<tr><th>Uren</th><td><input type="number" step="0.25" name="xgte_hours" value="' . esc_attr( get_post_meta( $post->ID, 'hours', true ) ) . '"></td></tr>';
	echo '<tr><th>Opmerking</th><td><input type="text" name="xgte_note" value="' . esc_attr( get_post_meta( $post->ID, 'note', true ) ) . '" class="regular-text"></td></tr>';
	echo '</table>';
}

function ekinese_hr_entry_save( $post_id ) {
	if ( ! isset( $_POST['xg_te_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_te_nonce'] ), 'xg_te_save' ) ) {
		return;
	}
	foreach ( array( 'employee', 'date', 'hours', 'note' ) as $f ) {
		if ( isset( $_POST[ 'xgte_' . $f ] ) ) {
			update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xgte_' . $f ] ) ) );
		}
	}
}
add_action( 'save_post_xg_timeentry', 'ekinese_hr_entry_save' );

/**
 * Statistieken per medewerker: gewerkte uren (deze maand) + afspraken.
 *
 * @return array { hours_month, hours_total, appointments }
 */
function ekinese_hr_stats( $employee_id ) {
	$entries = get_posts( array(
		'post_type'   => 'xg_timeentry',
		'numberposts' => -1,
		'post_status' => 'publish',
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'employee', 'value' => $employee_id ) ),
	) );
	$month = gmdate( 'Y-m' );
	$hm    = 0.0;
	$ht    = 0.0;
	foreach ( $entries as $id ) {
		$h = (float) get_post_meta( $id, 'hours', true );
		$ht += $h;
		if ( strpos( (string) get_post_meta( $id, 'date', true ), $month ) === 0 ) {
			$hm += $h;
		}
	}
	$appts = new WP_Query( array(
		'post_type'      => 'xg_appointment',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => 'employee', 'value' => $employee_id ) ),
	) );
	return array( 'hours_month' => round( $hm, 2 ), 'hours_total' => round( $ht, 2 ), 'appointments' => (int) $appts->found_posts );
}
