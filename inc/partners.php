<?php
/**
 * XGOUD Zakenpartners – producenten (leveranciers) & afnemers (groothandel).
 *
 * Beheer van vaste partners met hun condities. Afnemers nemen ingekochte
 * producten af; producenten leveren. De condities (bv. opslag/korting per
 * metaal) voeden later prognoses en de inventory-allocatie.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_PARTNER_FIELDS = array( 'ptype', 'company', 'contact', 'email', 'phone', 'country', 'metals', 'conditions', 'notes' );

function ekinese_register_partner_cpt() {
	register_post_type( 'xg_partner', array(
		'labels'    => array(
			'name'          => __( 'Zakenpartners', 'ekinese' ),
			'singular_name' => __( 'Partner', 'ekinese' ),
			'menu_name'     => __( 'Zakenpartners', 'ekinese' ),
		),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-networking',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_partner_cpt' );

/** Partners van een bepaald type (producer|buyer). */
function ekinese_get_partners( $type = '' ) {
	$args = array(
		'post_type'      => 'xg_partner',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( $type ) {
		$args['meta_query'] = array( array( 'key' => 'ptype', 'value' => $type ) );
	}
	$out = array();
	foreach ( get_posts( $args ) as $p ) {
		$row = array( 'id' => $p->ID, 'name' => $p->post_title );
		foreach ( XG_PARTNER_FIELDS as $f ) {
			$row[ $f ] = get_post_meta( $p->ID, $f, true );
		}
		$out[] = $row;
	}
	return $out;
}

/* ---- Admin metabox ---- */
function ekinese_partner_metabox() {
	add_meta_box( 'xg_partner_meta', __( 'Partnergegevens & condities', 'ekinese' ), 'ekinese_partner_metabox_html', 'xg_partner', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_partner_metabox' );

function ekinese_partner_metabox_html( $post ) {
	wp_nonce_field( 'xg_partner_save', 'xg_partner_nonce' );
	$type = get_post_meta( $post->ID, 'ptype', true ) ?: 'buyer';
	echo '<table class="form-table">';
	echo '<tr><th>Type</th><td><select name="xgp_ptype">';
	foreach ( array( 'producer' => 'Producent / leverancier', 'buyer' => 'Afnemer / groothandel' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></td></tr>';
	$rows = array(
		'company'  => array( 'Bedrijf', 'text' ),
		'contact'  => array( 'Contactpersoon', 'text' ),
		'email'    => array( 'E-mail', 'email' ),
		'phone'    => array( 'Telefoon', 'text' ),
		'country'  => array( 'Land', 'text' ),
		'metals'   => array( 'Metalen (CSV: goud,zilver…)', 'text' ),
	);
	foreach ( $rows as $k => $r ) {
		echo '<tr><th>' . esc_html( $r[0] ) . '</th><td><input type="' . esc_attr( $r[1] ) . '" name="xgp_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
	}
	echo '<tr><th>Condities</th><td><textarea name="xgp_conditions" rows="3" class="large-text" placeholder="bv. goud +1,5% / zilver spot / betaling 14 dagen">' . esc_textarea( get_post_meta( $post->ID, 'conditions', true ) ) . '</textarea></td></tr>';
	echo '<tr><th>Notities</th><td><textarea name="xgp_notes" rows="2" class="large-text">' . esc_textarea( get_post_meta( $post->ID, 'notes', true ) ) . '</textarea></td></tr>';
	echo '</table>';
}

function ekinese_partner_save( $post_id ) {
	if ( ! isset( $_POST['xg_partner_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_partner_nonce'] ), 'xg_partner_save' ) ) {
		return;
	}
	foreach ( XG_PARTNER_FIELDS as $f ) {
		if ( isset( $_POST[ 'xgp_' . $f ] ) ) {
			$val = wp_unslash( $_POST[ 'xgp_' . $f ] );
			$val = in_array( $f, array( 'conditions', 'notes' ), true ) ? sanitize_textarea_field( $val ) : sanitize_text_field( $val );
			update_post_meta( $post_id, $f, $val );
		}
	}
}
add_action( 'save_post_xg_partner', 'ekinese_partner_save' );

/** Type-kolom in de lijst. */
function ekinese_partner_columns( $cols ) {
	$cols['xg_ptype'] = __( 'Type', 'ekinese' );
	return $cols;
}
add_filter( 'manage_xg_partner_posts_columns', 'ekinese_partner_columns' );
function ekinese_partner_column( $col, $post_id ) {
	if ( 'xg_ptype' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'ptype', true ) === 'producer' ? 'Producent' : 'Afnemer' );
	}
}
add_action( 'manage_xg_partner_posts_custom_column', 'ekinese_partner_column', 10, 2 );
