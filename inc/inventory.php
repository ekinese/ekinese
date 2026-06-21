<?php
/**
 * XGOUD Inventory – voorraad van ingekochte artikelen.
 *
 * Elk ingekocht stuk komt in de voorraad. Van daaruit stuurt u de doorstroom:
 * status (op voorraad → toegewezen → verkocht → verzonden) en naar welke
 * afnemer (xg_partner) het gaat. Voedt later boekhouding & prognoses.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_INV_FIELDS = array( 'metal', 'weight', 'fine_weight', 'purity', 'purchase_price', 'purchase_date', 'source', 'status', 'buyer', 'sale_price', 'sale_date' );

function ekinese_inventory_statuses() {
	return array(
		'in_stock'  => __( 'Op voorraad', 'ekinese' ),
		'allocated' => __( 'Toegewezen', 'ekinese' ),
		'sold'      => __( 'Verkocht', 'ekinese' ),
		'shipped'   => __( 'Verzonden', 'ekinese' ),
	);
}

function ekinese_register_inventory_cpt() {
	register_post_type( 'xg_inventory', array(
		'labels'    => array(
			'name'          => __( 'Voorraad', 'ekinese' ),
			'singular_name' => __( 'Voorraaditem', 'ekinese' ),
			'menu_name'     => __( 'Voorraad', 'ekinese' ),
		),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-archive',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_inventory_cpt' );

/**
 * Voorraaditem aanmaken (bv. automatisch na een afgeronde inkoop-afspraak).
 *
 * @param array $args metal/gewicht/inkoopprijs etc.
 * @return int post-id
 */
function ekinese_inventory_add( $args ) {
	$title = $args['title'] ?? ( ucfirst( $args['metal'] ?? 'Item' ) . ' ' . ( $args['weight'] ?? '' ) . 'g' );
	$id    = wp_insert_post( array(
		'post_type'   => 'xg_inventory',
		'post_status' => 'publish',
		'post_title'  => sanitize_text_field( $title ),
	) );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	$args['status']        = $args['status'] ?? 'in_stock';
	$args['purchase_date'] = $args['purchase_date'] ?? current_time( 'Y-m-d' );
	foreach ( XG_INV_FIELDS as $f ) {
		if ( isset( $args[ $f ] ) ) {
			update_post_meta( $id, $f, sanitize_text_field( (string) $args[ $f ] ) );
		}
	}
	return $id;
}

/** Voorraadwaarde + telling per status (gecachet, voor stats/prognose). */
function ekinese_inventory_summary() {
	$items = get_posts( array( 'post_type' => 'xg_inventory', 'numberposts' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) );
	$sum   = array( 'count' => 0, 'in_stock' => 0, 'purchase_value' => 0.0, 'sold_value' => 0.0 );
	foreach ( $items as $id ) {
		$sum['count']++;
		$status = get_post_meta( $id, 'status', true );
		if ( 'in_stock' === $status || 'allocated' === $status ) {
			$sum['in_stock']++;
			$sum['purchase_value'] += (float) get_post_meta( $id, 'purchase_price', true );
		}
		if ( 'sold' === $status || 'shipped' === $status ) {
			$sum['sold_value'] += (float) get_post_meta( $id, 'sale_price', true );
		}
	}
	return $sum;
}

/* ---- Admin metabox ---- */
function ekinese_inventory_metabox() {
	add_meta_box( 'xg_inv_meta', __( 'Voorraad & doorstroom', 'ekinese' ), 'ekinese_inventory_metabox_html', 'xg_inventory', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_inventory_metabox' );

function ekinese_inventory_metabox_html( $post ) {
	wp_nonce_field( 'xg_inv_save', 'xg_inv_nonce' );
	$txt = function ( $k, $lbl, $type = 'text' ) use ( $post ) {
		echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="' . esc_attr( $type ) . '" name="xgi_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
	};
	echo '<table class="form-table">';
	$txt( 'metal', 'Metaal' );
	$txt( 'weight', 'Bruto gewicht (g)' );
	$txt( 'fine_weight', 'Fijn gewicht (g)' );
	$txt( 'purity', 'Zuiverheid' );
	$txt( 'purchase_price', 'Inkoopprijs (€)' );
	$txt( 'purchase_date', 'Inkoopdatum', 'date' );
	$txt( 'source', 'Herkomst (klant/afspraak)' );
	// Status
	echo '<tr><th>Status</th><td><select name="xgi_status">';
	$cur = get_post_meta( $post->ID, 'status', true ) ?: 'in_stock';
	foreach ( ekinese_inventory_statuses() as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></td></tr>';
	// Afnemer (partner buyer)
	echo '<tr><th>Afnemer</th><td><select name="xgi_buyer"><option value="">—</option>';
	$buyer = get_post_meta( $post->ID, 'buyer', true );
	if ( function_exists( 'ekinese_get_partners' ) ) {
		foreach ( ekinese_get_partners( 'buyer' ) as $b ) {
			echo '<option value="' . esc_attr( $b['id'] ) . '"' . selected( $buyer, $b['id'], false ) . '>' . esc_html( $b['name'] ) . '</option>';
		}
	}
	echo '</select></td></tr>';
	$txt( 'sale_price', 'Verkoopprijs (€)' );
	$txt( 'sale_date', 'Verkoopdatum', 'date' );
	echo '</table>';
}

function ekinese_inventory_save( $post_id ) {
	if ( ! isset( $_POST['xg_inv_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_inv_nonce'] ), 'xg_inv_save' ) ) {
		return;
	}
	foreach ( XG_INV_FIELDS as $f ) {
		if ( isset( $_POST[ 'xgi_' . $f ] ) ) {
			update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xgi_' . $f ] ) ) );
		}
	}
}
add_action( 'save_post_xg_inventory', 'ekinese_inventory_save' );

/** Statuskolom. */
function ekinese_inventory_columns( $cols ) {
	$cols['xg_inv_status'] = __( 'Status', 'ekinese' );
	$cols['xg_inv_metal']  = __( 'Metaal', 'ekinese' );
	return $cols;
}
add_filter( 'manage_xg_inventory_posts_columns', 'ekinese_inventory_columns' );
function ekinese_inventory_column( $col, $post_id ) {
	if ( 'xg_inv_status' === $col ) {
		$s = ekinese_inventory_statuses();
		echo esc_html( $s[ get_post_meta( $post_id, 'status', true ) ] ?? '' );
	} elseif ( 'xg_inv_metal' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'metal', true ) );
	}
}
add_action( 'manage_xg_inventory_posts_custom_column', 'ekinese_inventory_column', 10, 2 );
