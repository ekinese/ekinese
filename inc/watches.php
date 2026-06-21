<?php
/**
 * XGOUD Horloge-database (xg_watch).
 *
 * Luxe horloges die XGOUD inkoopt: merk, collectie, model, referentie,
 * materiaal en staat. Voedt de horlogepagina's, de horloge-calculator en de
 * zoek-/chatassistent. Geen plugin – self-built, Redis-gecachet aggregaat.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_WATCH_FIELDS = array( 'brand', 'collection', 'model', 'reference', 'material', 'movement', 'year', 'condition', 'box_papers', 'aliases' );

/** CPT registreren. */
function ekinese_register_watch_cpt() {
	register_post_type( 'xg_watch', array(
		'labels'       => array(
			'name'          => __( 'Horloges', 'ekinese' ),
			'singular_name' => __( 'Horloge', 'ekinese' ),
			'menu_name'     => __( 'Horloges', 'ekinese' ),
		),
		'public'       => true,
		'has_archive'  => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-clock',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'rewrite'      => array( 'slug' => 'horloges' ),
	) );
	register_taxonomy( 'xg_watch_brand', 'xg_watch', array(
		'labels'       => array( 'name' => __( 'Merken', 'ekinese' ) ),
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
	) );
}
add_action( 'init', 'ekinese_register_watch_cpt' );

function ekinese_register_watch_meta() {
	foreach ( XG_WATCH_FIELDS as $f ) {
		register_post_meta( 'xg_watch', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	}
}
add_action( 'init', 'ekinese_register_watch_meta' );

/* =====================================================================
   GECACHET AGGREGAAT (Redis-object-cache + transient)
===================================================================== */
function ekinese_watches_dataset() {
	$data = wp_cache_get( 'watches_dataset', 'xg' );
	if ( false !== $data ) {
		return $data;
	}
	$data = get_transient( 'xg_watches_dataset' );
	if ( false === $data ) {
		$posts = get_posts( array(
			'post_type'      => 'xg_watch',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$data = array();
		foreach ( $posts as $p ) {
			$row = array( 'id' => $p->ID, 'name' => $p->post_title, 'slug' => $p->post_name, 'url' => get_permalink( $p->ID ) );
			foreach ( XG_WATCH_FIELDS as $f ) {
				$row[ $f ] = get_post_meta( $p->ID, $f, true );
			}
			$data[] = $row;
		}
		set_transient( 'xg_watches_dataset', $data, DAY_IN_SECONDS );
	}
	wp_cache_set( 'watches_dataset', $data, 'xg' );
	return $data;
}

/** Cache legen bij opslaan. */
function ekinese_flush_watches_cache( $post_id ) {
	if ( get_post_type( $post_id ) === 'xg_watch' ) {
		delete_transient( 'xg_watches_dataset' );
		wp_cache_delete( 'watches_dataset', 'xg' );
	}
}
add_action( 'save_post', 'ekinese_flush_watches_cache' );
add_action( 'deleted_post', 'ekinese_flush_watches_cache' );

/* =====================================================================
   SEED  (ingebouwde merk/collectie-dataset – geen externe bron nodig)
===================================================================== */
function ekinese_watch_seed_data() {
	return array(
		'Rolex'              => array( 'Submariner', 'Datejust', 'Daytona', 'GMT-Master II', 'Oyster Perpetual', 'Sea-Dweller', 'Day-Date', 'Explorer' ),
		'Omega'              => array( 'Speedmaster', 'Seamaster', 'Constellation', 'De Ville' ),
		'Patek Philippe'     => array( 'Nautilus', 'Aquanaut', 'Calatrava', 'Complications' ),
		'Audemars Piguet'    => array( 'Royal Oak', 'Royal Oak Offshore', 'Code 11.59' ),
		'Cartier'            => array( 'Santos', 'Tank', 'Ballon Bleu', 'Panthère' ),
		'Breitling'          => array( 'Navitimer', 'Superocean', 'Chronomat', 'Avenger' ),
		'IWC'                => array( 'Portugieser', 'Pilot', 'Portofino', 'Aquatimer' ),
		'Jaeger-LeCoultre'   => array( 'Reverso', 'Master', 'Polaris' ),
		'Tudor'              => array( 'Black Bay', 'Pelagos', 'Ranger' ),
		'TAG Heuer'          => array( 'Carrera', 'Monaco', 'Aquaracer' ),
		'Panerai'            => array( 'Luminor', 'Radiomir', 'Submersible' ),
		'Vacheron Constantin'=> array( 'Overseas', 'Patrimony', 'Traditionnelle' ),
	);
}

function ekinese_seed_watches() {
	$count = 0;
	foreach ( ekinese_watch_seed_data() as $brand => $collections ) {
		foreach ( $collections as $coll ) {
			$title = $brand . ' ' . $coll;
			$slug  = sanitize_title( $title );
			if ( get_page_by_path( $slug, OBJECT, 'xg_watch' ) ) {
				continue;
			}
			$id = wp_insert_post( array(
				'post_type'    => 'xg_watch',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => sprintf( 'Verkoop uw %1$s %2$s tegen een eerlijke dagprijs bij XGOUD. Onze horloge-experts taxeren uw %1$s op basis van model, referentie, staat en compleetheid (doos en papieren).', $brand, $coll ),
			) );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, 'brand', $brand );
			update_post_meta( $id, 'collection', $coll );
			wp_set_object_terms( $id, $brand, 'xg_watch_brand', false );
			$count++;
		}
	}
	delete_transient( 'xg_watches_dataset' );
	wp_cache_delete( 'watches_dataset', 'xg' );
	return $count;
}

/* =====================================================================
   DYNAMISCH BLOK  ekinese/watch-list
===================================================================== */
function ekinese_register_watch_block() {
	register_block_type( 'ekinese/watch-list', array(
		'attributes'      => array(
			'brand' => array( 'type' => 'string', 'default' => '' ),
			'limit' => array( 'type' => 'number', 'default' => 24 ),
			'title' => array( 'type' => 'string', 'default' => '' ),
		),
		'render_callback' => 'ekinese_render_watch_list',
	) );
}
add_action( 'init', 'ekinese_register_watch_block' );

function ekinese_render_watch_list( $attr ) {
	$args = array(
		'post_type'      => 'xg_watch',
		'posts_per_page' => (int) ( $attr['limit'] ?? 24 ),
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( ! empty( $attr['brand'] ) ) {
		$args['meta_query'] = array( array( 'key' => 'brand', 'value' => sanitize_text_field( $attr['brand'] ) ) );
	}
	$posts = get_posts( $args );
	if ( ! $posts ) {
		return '';
	}
	ob_start();
	echo '<section><div class="xg-container">';
	if ( ! empty( $attr['title'] ) ) {
		echo '<h2 class="xg-section-title">' . esc_html( $attr['title'] ) . '</h2>';
	}
	echo '<div class="xg-grid-4">';
	foreach ( $posts as $p ) {
		printf(
			'<a class="xg-c-card" href="%s" style="text-decoration:none"><h3>%s</h3><p>%s</p></a>',
			esc_url( get_permalink( $p->ID ) ),
			esc_html( $p->post_title ),
			esc_html( get_post_meta( $p->ID, 'collection', true ) )
		);
	}
	echo '</div></div></section>';
	return ob_get_clean();
}

/* =====================================================================
   REST  /watches
===================================================================== */
function ekinese_watches_rest() {
	register_rest_route( 'ekinese/v1', '/watches', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			return rest_ensure_response( ekinese_watches_dataset() );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_watches_rest' );

/* =====================================================================
   ADMIN – metabox + seed-knop
===================================================================== */
function ekinese_watch_metabox() {
	add_meta_box( 'xg_watch_meta', __( 'Horloge-gegevens', 'ekinese' ), 'ekinese_watch_metabox_html', 'xg_watch', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_watch_metabox' );

function ekinese_watch_metabox_html( $post ) {
	wp_nonce_field( 'xg_watch_save', 'xg_watch_nonce' );
	echo '<table class="form-table">';
	foreach ( XG_WATCH_FIELDS as $f ) {
		$v = esc_attr( get_post_meta( $post->ID, $f, true ) );
		echo '<tr><th>' . esc_html( ucfirst( str_replace( '_', ' ', $f ) ) ) . '</th><td><input type="text" name="xgw_' . esc_attr( $f ) . '" value="' . $v . '" class="regular-text"></td></tr>';
	}
	echo '</table>';
}

function ekinese_watch_save( $post_id ) {
	if ( ! isset( $_POST['xg_watch_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_watch_nonce'] ), 'xg_watch_save' ) ) {
		return;
	}
	foreach ( XG_WATCH_FIELDS as $f ) {
		if ( isset( $_POST[ 'xgw_' . $f ] ) ) {
			update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xgw_' . $f ] ) ) );
		}
	}
}
add_action( 'save_post_xg_watch', 'ekinese_watch_save' );

function ekinese_watch_seed_menu() {
	add_submenu_page( 'edit.php?post_type=xg_watch', __( 'Seed', 'ekinese' ), __( 'Merken-seed', 'ekinese' ), 'manage_options', 'xg-watch-seed', function () {
		if ( isset( $_POST['xg_ws_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_ws_nonce'] ), 'xg_ws' ) ) {
			$n = ekinese_seed_watches();
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( '%d horloges toegevoegd.', $n ) ) . '</p></div>';
		}
		echo '<div class="wrap"><h1>Horloge-seed</h1><p>Vult de database met de bekendste merken en collecties.</p><form method="post">';
		wp_nonce_field( 'xg_ws', 'xg_ws_nonce' );
		submit_button( 'Merken & collecties toevoegen' );
		echo '</form></div>';
	} );
}
add_action( 'admin_menu', 'ekinese_watch_seed_menu' );
