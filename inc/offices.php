<?php
/**
 * XGOUD Offices (Kantoren) – Custom Post Type + Meta.
 *
 * Datenbasis für:
 *   - Übersichtsseite "Alle kantoren" (interaktive Karte + Liste)
 *   - Einzelseite je Kantoor (Details, Google-Maps, Routenplaner)
 *   - Zonen-/Mitarbeiter-Zuteilung (Terminplaner – folgt)
 *
 * Eindhoven = Hauptsitz (is_hq) und wird überall hervorgehoben.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT "xg_office" registrieren.
 */
function ekinese_register_office_cpt() {
	register_post_type(
		'xg_office',
		array(
			'labels'        => array(
				'name'          => __( 'Kantoren', 'ekinese' ),
				'singular_name' => __( 'Kantoor', 'ekinese' ),
				'add_new_item'  => __( 'Nieuw kantoor', 'ekinese' ),
				'edit_item'     => __( 'Kantoor bewerken', 'ekinese' ),
				'menu_name'     => __( 'Kantoren', 'ekinese' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-location',
			'supports'      => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'       => array( 'slug' => 'kantoren' ),
		)
	);
}
add_action( 'init', 'ekinese_register_office_cpt' );

/**
 * Meta-Felder je Kantoor (REST-fähig, im Editor nutzbar).
 */
function ekinese_register_office_meta() {
	$fields = array(
		'street'    => 'string',
		'postcode'  => 'string',
		'city'      => 'string',
		'lat'       => 'string',
		'lng'       => 'string',
		'phone'     => 'string',
		'email'     => 'string',
		'hours'     => 'string', // JSON: { "ma":"09:00-17:30", ... }
		'is_hq'     => 'boolean',
		'zone'      => 'string',  // Zonen-ID für Mitarbeiter-Zuteilung
		'open_days' => 'string',  // CSV "ma,di,wo,do,vr" (vom Terminplaner überschrieben)
	);

	foreach ( $fields as $key => $type ) {
		register_post_meta(
			'xg_office',
			$key,
			array(
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}
}
add_action( 'init', 'ekinese_register_office_meta' );

/**
 * Alle veröffentlichten Kantoren als flaches Array (für die Karte/JS).
 *
 * @return array
 */
function ekinese_get_offices() {
	$query = new WP_Query(
		array(
			'post_type'      => 'xg_office',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$offices = array();
	foreach ( $query->posts as $post ) {
		$id     = $post->ID;
		$hours  = get_post_meta( $id, 'hours', true );
		$offices[] = array(
			'id'       => $id,
			'name'     => get_the_title( $id ),
			'slug'     => $post->post_name,
			'url'      => get_permalink( $id ),
			'street'   => get_post_meta( $id, 'street', true ),
			'postcode' => get_post_meta( $id, 'postcode', true ),
			'city'     => get_post_meta( $id, 'city', true ),
			'lat'      => (float) get_post_meta( $id, 'lat', true ),
			'lng'      => (float) get_post_meta( $id, 'lng', true ),
			'phone'    => get_post_meta( $id, 'phone', true ),
			'email'    => get_post_meta( $id, 'email', true ),
			'hours'    => $hours ? json_decode( $hours, true ) : array(),
			'is_hq'    => (bool) get_post_meta( $id, 'is_hq', true ),
			'zone'     => get_post_meta( $id, 'zone', true ),
		);
	}
	wp_reset_postdata();

	return $offices;
}

/**
 * Offices-Assets + Daten laden (nur wo gebraucht: Archiv, Einzelseite,
 * oder wenn das Offices-Pattern auf einer Seite steckt).
 */
function ekinese_enqueue_offices_assets() {
	$needs = is_post_type_archive( 'xg_office' )
		|| is_singular( 'xg_office' )
		|| is_page_template( 'page-kantoren' )
		|| ( is_singular() && has_block( 'core/pattern' ) ); // grob: Seiten mit Pattern

	/**
	 * Erlaubt das Erzwingen des Ladens (z.B. Startseiten-Kartenblock).
	 *
	 * @param bool $needs
	 */
	$needs = apply_filters( 'ekinese_load_offices_assets', $needs );

	if ( ! $needs ) {
		return;
	}

	// Leaflet (leichtgewichtige Karte, kein API-Key) via CDN.
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

	$css = get_theme_file_path( 'assets/css/offices.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-offices', get_theme_file_uri( 'assets/css/offices.css' ), array( 'leaflet' ), (string) filemtime( $css ) );
	}

	$js = get_theme_file_path( 'assets/js/offices.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-offices', get_theme_file_uri( 'assets/js/offices.js' ), array( 'leaflet' ), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-offices', 'XG_OFFICES', ekinese_get_offices() );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_offices_assets' );
