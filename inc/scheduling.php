<?php
/**
 * XGOUD Terminplaner-Backend.
 *
 * Datenmodell:
 *   - Taxonomie  xg_zone        : Zonen (gruppieren Kantoren + Medewerkers)
 *   - CPT        xg_employee    : Medewerker (Arbeitstage, Zonen, Heimatkantoor)
 *   - CPT        xg_appointment : Termin (Kunde, Produkte, Kantoor, Datum,
 *                                 Service, Auszahlung, Charity, Reisebonus,
 *                                 Pro-forma → Rechnung)
 *
 * Kernlogik:
 *   - Offene Tage eines Kantoors = Vereinigung der Arbeitstage aller
 *     Medewerkers, deren Zone das Kantoor enthält. Eindhoven (HQ) = 6 Tage.
 *   - Eindhoven-Reisebonus = f(Wegstrecke in km), Staffel editierbar.
 *   - Pro-forma-Nummer bei Bestätigung; bei Abschluss = echte Rechnung.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_HQ_CITY  = 'Eindhoven';
const XG_HQ_LAT   = 51.4361;
const XG_HQ_LNG   = 5.4836;

/** Wochentage (Schlüssel = Speicherung, Wert = Anzeige). */
function ekinese_weekdays() {
	return array(
		'ma' => 'Maandag', 'di' => 'Dinsdag', 'wo' => 'Woensdag', 'do' => 'Donderdag',
		'vr' => 'Vrijdag', 'za' => 'Zaterdag', 'zo' => 'Zondag',
	);
}

/* =====================================================================
   REGISTRIERUNG: Taxonomie + CPTs + Meta
===================================================================== */
function ekinese_register_scheduling() {
	// Zonen (für Kantoren + Medewerkers).
	register_taxonomy(
		'xg_zone',
		array( 'xg_office', 'xg_employee' ),
		array(
			'labels'       => array(
				'name'          => __( 'Zones', 'ekinese' ),
				'singular_name' => __( 'Zone', 'ekinese' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'hierarchical' => true,
		)
	);

	// Medewerker.
	register_post_type(
		'xg_employee',
		array(
			'labels'       => array(
				'name'          => __( 'Medewerkers', 'ekinese' ),
				'singular_name' => __( 'Medewerker', 'ekinese' ),
				'add_new_item'  => __( 'Nieuwe medewerker', 'ekinese' ),
				'menu_name'     => __( 'Medewerkers', 'ekinese' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-groups',
			'supports'     => array( 'title' ),
			'taxonomies'   => array( 'xg_zone' ),
		)
	);

	// Termin.
	register_post_type(
		'xg_appointment',
		array(
			'labels'       => array(
				'name'          => __( 'Afspraken', 'ekinese' ),
				'singular_name' => __( 'Afspraak', 'ekinese' ),
				'add_new_item'  => __( 'Nieuwe afspraak', 'ekinese' ),
				'menu_name'     => __( 'Afspraken', 'ekinese' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title' ),
		)
	);

	// Medewerker-Meta.
	$emp = array( 'work_days' => 'string', 'phone' => 'string', 'email' => 'string', 'home_office' => 'integer' );
	foreach ( $emp as $k => $t ) {
		register_post_meta( 'xg_employee', $k, array( 'type' => $t, 'single' => true, 'show_in_rest' => true ) );
	}

	// Termin-Meta.
	$appt = array(
		'status' => 'string', 'first' => 'string', 'last' => 'string', 'email' => 'string',
		'phone' => 'string', 'address' => 'string', 'postcode' => 'string', 'city' => 'string',
		'office_id' => 'integer', 'employee_id' => 'integer', 'date' => 'string', 'time' => 'string',
		'service' => 'string', 'payout' => 'string', 'products' => 'string',
		'market_total' => 'string', 'payout_total' => 'string', 'margin_total' => 'string', 'charity_total' => 'string',
		'charity_project' => 'string', 'charity_recipient' => 'string',
		'distance_km' => 'string', 'travel_bonus' => 'string',
		'proforma_number' => 'string', 'proforma_date' => 'string',
		'invoice_number' => 'string', 'invoice_date' => 'string',
		'notes' => 'string',
	);
	foreach ( $appt as $k => $t ) {
		register_post_meta( 'xg_appointment', $k, array( 'type' => $t, 'single' => true, 'show_in_rest' => true ) );
	}
}
add_action( 'init', 'ekinese_register_scheduling' );

/* =====================================================================
   KERNLOGIK
===================================================================== */

/**
 * Offene Tage eines Kantoors (Vereinigung der Arbeitstage der Medewerkers
 * in der Zone des Kantoors). Eindhoven (HQ) ist immer 6 Tage offen.
 *
 * @param int $office_id
 * @return array Liste von Wochentag-Schlüsseln (z.B. ['ma','di']).
 */
function ekinese_office_open_days( $office_id ) {
	if ( get_post_meta( $office_id, 'is_hq', true ) ) {
		return array( 'ma', 'di', 'wo', 'do', 'vr', 'za' ); // HQ: 6 Tage
	}

	$zones = wp_get_object_terms( $office_id, 'xg_zone', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $zones ) || empty( $zones ) ) {
		return array();
	}

	$employees = get_posts(
		array(
			'post_type'      => 'xg_employee',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array( 'taxonomy' => 'xg_zone', 'field' => 'term_id', 'terms' => $zones ),
			),
			'fields'         => 'ids',
		)
	);

	$days = array();
	foreach ( $employees as $emp_id ) {
		$wd = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $emp_id, 'work_days', true ) ) ) );
		$days = array_merge( $days, $wd );
	}
	// In Wochenreihenfolge zurückgeben.
	return array_values( array_intersect( array_keys( ekinese_weekdays() ), array_unique( $days ) ) );
}

/**
 * Reisebonus-Staffel (km → €). Editierbar über Option 'xg_travel_bonus'.
 *
 * @return array Liste [ ['max_km'=>int, 'bonus'=>float], ... ] aufsteigend.
 */
function ekinese_travel_bonus_tiers() {
	$default = array(
		array( 'max_km' => 25,  'bonus' => 0 ),
		array( 'max_km' => 50,  'bonus' => 15 ),
		array( 'max_km' => 100, 'bonus' => 30 ),
		array( 'max_km' => 150, 'bonus' => 50 ),
		array( 'max_km' => 9999, 'bonus' => 75 ),
	);
	$opt = get_option( 'xg_travel_bonus' );
	return ( is_array( $opt ) && $opt ) ? $opt : $default;
}

/**
 * Reisebonus für eine Wegstrecke (Wohnort Kunde → Eindhoven) berechnen.
 *
 * @param float $km
 * @return float €
 */
function ekinese_travel_bonus( $km ) {
	$km = (float) $km;
	foreach ( ekinese_travel_bonus_tiers() as $tier ) {
		if ( $km <= (float) $tier['max_km'] ) {
			return (float) $tier['bonus'];
		}
	}
	return 0.0;
}

/**
 * Haversine-Distanz in km (für spätere Geocoding-Anbindung).
 */
function ekinese_distance_km( $lat1, $lng1, $lat2, $lng2 ) {
	$r = 6371;
	$dlat = deg2rad( $lat2 - $lat1 );
	$dlng = deg2rad( $lng2 - $lng1 );
	$a = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
	return round( $r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) ), 1 );
}

/**
 * Pro-forma-Nummer erzeugen (XG-PF-JAHR-####).
 */
function ekinese_make_proforma_number( $appointment_id ) {
	return sprintf( 'XG-PF-%s-%04d', gmdate( 'Y' ), $appointment_id );
}

/* =====================================================================
   ADMIN: Medewerker-Metabox (Arbeitstage)
===================================================================== */
function ekinese_employee_metabox() {
	add_meta_box( 'xg_emp', __( 'Medewerker-gegevens', 'ekinese' ), 'ekinese_employee_metabox_html', 'xg_employee', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_employee_metabox' );

function ekinese_employee_metabox_html( $post ) {
	wp_nonce_field( 'xg_emp_save', 'xg_emp_nonce' );
	$days = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, 'work_days', true ) ) ) );
	$phone = esc_attr( get_post_meta( $post->ID, 'phone', true ) );
	$email = esc_attr( get_post_meta( $post->ID, 'email', true ) );
	echo '<p><strong>' . esc_html__( 'Werkdagen', 'ekinese' ) . '</strong><br>';
	foreach ( ekinese_weekdays() as $k => $label ) {
		printf(
			'<label style="margin-right:14px"><input type="checkbox" name="xg_work_days[]" value="%s" %s> %s</label>',
			esc_attr( $k ),
			in_array( $k, $days, true ) ? 'checked' : '',
			esc_html( $label )
		);
	}
	echo '</p>';
	echo '<p><label>' . esc_html__( 'Telefoon', 'ekinese' ) . '<br><input type="text" name="xg_emp_phone" value="' . $phone . '" class="regular-text"></label></p>';
	echo '<p><label>' . esc_html__( 'E-mail', 'ekinese' ) . '<br><input type="email" name="xg_emp_email" value="' . $email . '" class="regular-text"></label></p>';
	echo '<p class="description">' . esc_html__( 'Zones rechts toewijzen. De kantoren in die zone zijn alleen open op de werkdagen van de medewerkers.', 'ekinese' ) . '</p>';
}

function ekinese_employee_save( $post_id ) {
	if ( ! isset( $_POST['xg_emp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_emp_nonce'] ), 'xg_emp_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$days = isset( $_POST['xg_work_days'] ) ? array_map( 'sanitize_key', (array) $_POST['xg_work_days'] ) : array();
	update_post_meta( $post_id, 'work_days', implode( ',', $days ) );
	update_post_meta( $post_id, 'phone', sanitize_text_field( wp_unslash( $_POST['xg_emp_phone'] ?? '' ) ) );
	update_post_meta( $post_id, 'email', sanitize_email( wp_unslash( $_POST['xg_emp_email'] ?? '' ) ) );
}
add_action( 'save_post_xg_employee', 'ekinese_employee_save' );

/* =====================================================================
   ADMIN: Termin-Metabox (volle Kontrolle)
===================================================================== */
function ekinese_appointment_metabox() {
	add_meta_box( 'xg_appt', __( 'Afspraak – volledige controle', 'ekinese' ), 'ekinese_appointment_metabox_html', 'xg_appointment', 'normal', 'high' );
	add_meta_box( 'xg_appt_pf', __( 'Pro forma / Factuur', 'ekinese' ), 'ekinese_appointment_proforma_html', 'xg_appointment', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'ekinese_appointment_metabox' );

function ekinese_appt_field( $post, $key, $label, $type = 'text' ) {
	$val = esc_attr( get_post_meta( $post->ID, $key, true ) );
	if ( 'textarea' === $type ) {
		return '<p><label><strong>' . esc_html( $label ) . '</strong><br><textarea name="xg_' . esc_attr( $key ) . '" rows="4" class="large-text">' . esc_textarea( get_post_meta( $post->ID, $key, true ) ) . '</textarea></label></p>';
	}
	return '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" name="xg_' . esc_attr( $key ) . '" value="' . $val . '" class="regular-text"></label></p>';
}

function ekinese_appointment_metabox_html( $post ) {
	wp_nonce_field( 'xg_appt_save', 'xg_appt_nonce' );
	$status = get_post_meta( $post->ID, 'status', true ) ?: 'new';
	$service = get_post_meta( $post->ID, 'service', true );
	$payout = get_post_meta( $post->ID, 'payout', true );

	echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';

	// Status
	echo '<p><label><strong>Status</strong><br><select name="xg_status">';
	foreach ( array( 'new' => 'Nieuw', 'confirmed' => 'Bevestigd', 'completed' => 'Afgerond', 'cancelled' => 'Geannuleerd' ) as $k => $l ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $l ) );
	}
	echo '</select></label></p>';

	echo ekinese_appt_field( $post, 'date', 'Datum', 'date' );
	echo '</div><hr><strong>Klant</strong><div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
	echo ekinese_appt_field( $post, 'first', 'Voornaam' );
	echo ekinese_appt_field( $post, 'last', 'Achternaam' );
	echo ekinese_appt_field( $post, 'email', 'E-mail', 'email' );
	echo ekinese_appt_field( $post, 'phone', 'Telefoon' );
	echo ekinese_appt_field( $post, 'address', 'Adres' );
	echo ekinese_appt_field( $post, 'postcode', 'Postcode' );
	echo ekinese_appt_field( $post, 'city', 'Stad' );
	echo ekinese_appt_field( $post, 'time', 'Tijd', 'time' );
	echo '</div><hr><strong>Afspraak</strong><div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';

	// Kantoor
	echo '<p><label><strong>Kantoor</strong><br><select name="xg_office_id"><option value="">—</option>';
	foreach ( get_posts( array( 'post_type' => 'xg_office', 'numberposts' => -1 ) ) as $o ) {
		printf( '<option value="%d" %s>%s</option>', $o->ID, selected( get_post_meta( $post->ID, 'office_id', true ), $o->ID, false ), esc_html( $o->post_title ) );
	}
	echo '</select></label></p>';

	// Medewerker
	echo '<p><label><strong>Medewerker</strong><br><select name="xg_employee_id"><option value="">—</option>';
	foreach ( get_posts( array( 'post_type' => 'xg_employee', 'numberposts' => -1 ) ) as $e ) {
		printf( '<option value="%d" %s>%s</option>', $e->ID, selected( get_post_meta( $post->ID, 'employee_id', true ), $e->ID, false ), esc_html( $e->post_title ) );
	}
	echo '</select></label></p>';

	// Service
	echo '<p><label><strong>Service</strong><br><select name="xg_service">';
	foreach ( array( 'home' => 'Bezoek aan huis', 'office' => 'In een vestiging', 'pickup' => 'Ophaalservice' ) as $k => $l ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $service, $k, false ), esc_html( $l ) );
	}
	echo '</select></label></p>';

	// Auszahlung
	echo '<p><label><strong>Uitbetaling</strong><br><select name="xg_payout">';
	foreach ( array( 'bank' => 'Bankoverschrijving', 'cash' => 'Contant' ) as $k => $l ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $payout, $k, false ), esc_html( $l ) );
	}
	echo '</select></label></p>';
	echo '</div>';

	echo ekinese_appt_field( $post, 'products', 'Producten (uit calculator – JSON)', 'textarea' );

	echo '<hr><strong>Bedragen</strong><div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:0 16px">';
	echo ekinese_appt_field( $post, 'market_total', 'Marktwaarde €' );
	echo ekinese_appt_field( $post, 'payout_total', 'Uitbetaling €' );
	echo ekinese_appt_field( $post, 'margin_total', 'Marge €' );
	echo ekinese_appt_field( $post, 'charity_total', 'Goede doel €' );
	echo '</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
	echo ekinese_appt_field( $post, 'charity_project', 'Goede-doel categorie' );
	echo ekinese_appt_field( $post, 'charity_recipient', 'Goede-doel instelling' );
	echo ekinese_appt_field( $post, 'distance_km', 'Afstand → Eindhoven (km)' );
	$tb = esc_attr( get_post_meta( $post->ID, 'travel_bonus', true ) );
	echo '<p><label><strong>Reisbonus € (auto bij opslaan)</strong><br><input type="text" name="xg_travel_bonus" value="' . $tb . '" class="regular-text"></label></p>';
	echo '</div>';
	echo ekinese_appt_field( $post, 'notes', 'Interne notities', 'textarea' );
}

/** Pro-forma / Rechnung Seitenbox. */
function ekinese_appointment_proforma_html( $post ) {
	$pf = get_post_meta( $post->ID, 'proforma_number', true );
	$inv = get_post_meta( $post->ID, 'invoice_number', true );
	echo '<p>' . esc_html__( 'Pro forma nr.', 'ekinese' ) . '<br><strong>' . ( $pf ? esc_html( $pf ) : '—' ) . '</strong></p>';
	if ( $pf ) {
		echo '<p>' . esc_html__( 'Datum', 'ekinese' ) . ': ' . esc_html( get_post_meta( $post->ID, 'proforma_date', true ) ) . '</p>';
	}
	echo '<p><label><input type="checkbox" name="xg_make_proforma" value="1"> ' . esc_html__( 'Pro forma genereren', 'ekinese' ) . '</label></p>';
	echo '<hr>';
	echo '<p>' . esc_html__( 'Factuur nr.', 'ekinese' ) . '<br><strong>' . ( $inv ? esc_html( $inv ) : '—' ) . '</strong></p>';
	echo '<p class="description">' . esc_html__( 'Bij status “Afgerond” wordt de pro forma automatisch de definitieve factuur.', 'ekinese' ) . '</p>';
}

function ekinese_appointment_save( $post_id ) {
	if ( ! isset( $_POST['xg_appt_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_appt_nonce'] ), 'xg_appt_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$text_keys = array( 'status', 'first', 'last', 'phone', 'address', 'postcode', 'city', 'date', 'time', 'service', 'payout', 'market_total', 'payout_total', 'margin_total', 'charity_total', 'charity_project', 'charity_recipient', 'distance_km' );
	foreach ( $text_keys as $k ) {
		if ( isset( $_POST[ 'xg_' . $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ 'xg_' . $k ] ) ) );
		}
	}
	if ( isset( $_POST['xg_email'] ) ) {
		update_post_meta( $post_id, 'email', sanitize_email( wp_unslash( $_POST['xg_email'] ) ) );
	}
	foreach ( array( 'office_id', 'employee_id' ) as $k ) {
		if ( isset( $_POST[ 'xg_' . $k ] ) ) {
			update_post_meta( $post_id, $k, (int) $_POST[ 'xg_' . $k ] );
		}
	}
	foreach ( array( 'products', 'notes' ) as $k ) {
		if ( isset( $_POST[ 'xg_' . $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_textarea_field( wp_unslash( $_POST[ 'xg_' . $k ] ) ) );
		}
	}

	// Reisebonus automatisch aus Distanz (sofern Distanz gesetzt; manueller
	// Wert überschreibt nur, wenn explizit eingetragen und Distanz leer).
	$km = (float) get_post_meta( $post_id, 'distance_km', true );
	$manual = isset( $_POST['xg_travel_bonus'] ) ? sanitize_text_field( wp_unslash( $_POST['xg_travel_bonus'] ) ) : '';
	if ( $km > 0 ) {
		update_post_meta( $post_id, 'travel_bonus', (string) ekinese_travel_bonus( $km ) );
	} elseif ( '' !== $manual ) {
		update_post_meta( $post_id, 'travel_bonus', $manual );
	}

	// Pro-forma erzeugen.
	$status = get_post_meta( $post_id, 'status', true );
	if ( ( ! empty( $_POST['xg_make_proforma'] ) || in_array( $status, array( 'confirmed', 'completed' ), true ) ) && ! get_post_meta( $post_id, 'proforma_number', true ) ) {
		update_post_meta( $post_id, 'proforma_number', ekinese_make_proforma_number( $post_id ) );
		update_post_meta( $post_id, 'proforma_date', gmdate( 'Y-m-d' ) );
	}
	// Abschluss → echte Rechnung.
	if ( 'completed' === $status && ! get_post_meta( $post_id, 'invoice_number', true ) ) {
		$pf = get_post_meta( $post_id, 'proforma_number', true );
		update_post_meta( $post_id, 'invoice_number', str_replace( 'XG-PF', 'XG-INV', $pf ?: ekinese_make_proforma_number( $post_id ) ) );
		update_post_meta( $post_id, 'invoice_date', gmdate( 'Y-m-d' ) );
	}
}
add_action( 'save_post_xg_appointment', 'ekinese_appointment_save' );

/* =====================================================================
   ADMIN: Übersichtsspalten + Reisebonus-Einstellungen
===================================================================== */
function ekinese_appt_columns( $cols ) {
	return array(
		'cb'       => $cols['cb'],
		'title'    => __( 'Afspraak', 'ekinese' ),
		'xg_when'  => __( 'Datum/tijd', 'ekinese' ),
		'xg_who'   => __( 'Klant', 'ekinese' ),
		'xg_office'=> __( 'Kantoor', 'ekinese' ),
		'xg_status'=> __( 'Status', 'ekinese' ),
		'xg_total' => __( 'Uitbetaling', 'ekinese' ),
	);
}
add_filter( 'manage_xg_appointment_posts_columns', 'ekinese_appt_columns' );

function ekinese_appt_column( $col, $id ) {
	switch ( $col ) {
		case 'xg_when':
			echo esc_html( trim( get_post_meta( $id, 'date', true ) . ' ' . get_post_meta( $id, 'time', true ) ) ?: '—' );
			break;
		case 'xg_who':
			echo esc_html( trim( get_post_meta( $id, 'first', true ) . ' ' . get_post_meta( $id, 'last', true ) ) ?: '—' );
			break;
		case 'xg_office':
			$oid = (int) get_post_meta( $id, 'office_id', true );
			echo esc_html( $oid ? get_the_title( $oid ) : '—' );
			break;
		case 'xg_status':
			echo esc_html( get_post_meta( $id, 'status', true ) ?: 'new' );
			break;
		case 'xg_total':
			$v = get_post_meta( $id, 'payout_total', true );
			echo $v ? '€ ' . esc_html( $v ) : '—';
			break;
	}
}
add_action( 'manage_xg_appointment_posts_custom_column', 'ekinese_appt_column', 10, 2 );

// Einstellungen: Reisebonus-Staffel.
function ekinese_scheduling_settings_menu() {
	add_submenu_page( 'edit.php?post_type=xg_appointment', __( 'Reisbonus', 'ekinese' ), __( 'Reisbonus', 'ekinese' ), 'manage_options', 'xg-travel-bonus', 'ekinese_travel_bonus_page' );
}
add_action( 'admin_menu', 'ekinese_scheduling_settings_menu' );

function ekinese_travel_bonus_page() {
	if ( isset( $_POST['xg_tb_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_tb_nonce'] ), 'xg_tb' ) ) {
		$tiers = array();
		$kms = (array) ( $_POST['max_km'] ?? array() );
		$bon = (array) ( $_POST['bonus'] ?? array() );
		foreach ( $kms as $i => $km ) {
			if ( '' === $km ) {
				continue;
			}
			$tiers[] = array( 'max_km' => (int) $km, 'bonus' => (float) ( $bon[ $i ] ?? 0 ) );
		}
		usort( $tiers, function ( $a, $b ) { return $a['max_km'] <=> $b['max_km']; } );
		update_option( 'xg_travel_bonus', $tiers );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Opgeslagen.', 'ekinese' ) . '</p></div>';
	}
	$tiers = ekinese_travel_bonus_tiers();
	echo '<div class="wrap"><h1>' . esc_html__( 'Eindhoven-reisbonus', 'ekinese' ) . '</h1>';
	echo '<p>' . esc_html__( 'Bonus (in €) die de klant ontvangt op basis van de afstand van zijn woonplaats tot Eindhoven.', 'ekinese' ) . '</p>';
	echo '<form method="post"><table class="form-table"><tr><th>Tot km</th><th>Bonus €</th></tr>';
	for ( $i = 0; $i < 6; $i++ ) {
		$t = $tiers[ $i ] ?? array( 'max_km' => '', 'bonus' => '' );
		printf(
			'<tr><td><input type="number" name="max_km[]" value="%s"></td><td><input type="number" step="0.01" name="bonus[]" value="%s"></td></tr>',
			esc_attr( $t['max_km'] ),
			esc_attr( $t['bonus'] )
		);
	}
	echo '</table>';
	wp_nonce_field( 'xg_tb', 'xg_tb_nonce' );
	submit_button( __( 'Opslaan', 'ekinese' ) );
	echo '</form></div>';
}

/* =====================================================================
   REST: Termin vom Frontend-Calculator anlegen
===================================================================== */
function ekinese_register_appointment_rest() {
	register_rest_route(
		'ekinese/v1',
		'/appointment',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true', // öffentlich (Kunde)
			'callback'            => 'ekinese_rest_create_appointment',
		)
	);
}
add_action( 'rest_api_init', 'ekinese_register_appointment_rest' );

function ekinese_rest_create_appointment( WP_REST_Request $req ) {
	$d = $req->get_json_params();
	if ( empty( $d ) ) {
		return new WP_Error( 'xg_empty', 'Geen gegevens', array( 'status' => 400 ) );
	}

	$name  = trim( ( $d['first'] ?? '' ) . ' ' . ( $d['last'] ?? '' ) );
	$title = sprintf( 'Afspraak %s – %s', gmdate( 'Y-m-d H:i' ), $name ?: 'klant' );

	$id = wp_insert_post(
		array(
			'post_type'   => 'xg_appointment',
			'post_status' => 'publish',
			'post_title'  => $title,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$map = array( 'first', 'last', 'email', 'phone', 'address', 'postcode', 'city', 'service', 'payout', 'charity_project', 'charity_recipient', 'market_total', 'payout_total', 'margin_total', 'charity_total', 'office_id', 'date', 'time' );
	foreach ( $map as $k ) {
		if ( isset( $d[ $k ] ) ) {
			update_post_meta( $id, $k, sanitize_text_field( is_scalar( $d[ $k ] ) ? $d[ $k ] : '' ) );
		}
	}
	if ( isset( $d['products'] ) ) {
		update_post_meta( $id, 'products', wp_json_encode( $d['products'] ) );
	}
	update_post_meta( $id, 'status', 'new' );

	// Bestätigungsmail an Kunde (Terminversuch eingegangen).
	if ( ! empty( $d['email'] ) && is_email( $d['email'] ) ) {
		$recipient = $d['charity_recipient'] ?? '';
		$body  = "Beste " . ( $d['first'] ?? '' ) . ",\n\n";
		$body .= "Bedankt! Uw afspraakverzoek is bij ons binnengekomen. Wij nemen spoedig contact met u op om de afspraak te bevestigen.\n\n";
		if ( $recipient ) {
			$body .= "Met deze verkoop steunt u: " . $recipient . ". Dank u wel!\n\n";
		}
		$body .= "Met vriendelijke groet,\nXGOUD";
		wp_mail( $d['email'], 'Uw afspraakverzoek bij XGOUD', $body );
	}

	return array( 'ok' => true, 'id' => $id );
}
