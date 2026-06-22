<?php
/**
 * XGOUD Edelstenen-catalogus (xg_gemstone).
 *
 * Losse edelstenen die XGOUD inkoopt: steen, categorie, karaat, kleur,
 * helderheid, slijpvorm. Structuur spiegelt inc/products.php / inc/watches.php
 * (CPT + meta + cache + seed + lijst-blok). De /verkopen/-URL's komen uit
 * inc/verkopen.php (post_type_link + rewrite).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_GEMSTONE_FIELDS = array( 'stone', 'category', 'carat', 'color', 'clarity', 'shape', 'aliases' );

/* =====================================================================
   CPT + META
===================================================================== */
function ekinese_register_gemstones() {
	register_post_type(
		'xg_gemstone',
		array(
			'labels'       => array(
				'name'          => __( 'Edelstenen', 'ekinese' ),
				'singular_name' => __( 'Edelsteen', 'ekinese' ),
				'add_new_item'  => __( 'Nieuwe edelsteen', 'ekinese' ),
				'menu_name'     => __( 'Edelstenen', 'ekinese' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-art',
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'      => array( 'slug' => 'edelsteen' ),
		)
	);
	foreach ( XG_GEMSTONE_FIELDS as $f ) {
		register_post_meta( 'xg_gemstone', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	}
}
add_action( 'init', 'ekinese_register_gemstones' );

/* =====================================================================
   SEED-DATA  (steen → categorie → producten)
===================================================================== */
function ekinese_gemstone_seed_data() {
	return array(
		'Diamant' => array(
			'Geslepen' => array( '0,25 ct', '0,50 ct', '0,75 ct', '1,00 ct', '1,50 ct', '2,00 ct', '3,00 ct', '5,00 ct' ),
			'Ruw'      => array( 'Ruwe diamant 1 ct', 'Ruwe diamant 2 ct', 'Ruwe diamant 5 ct' ),
		),
		'Saffier' => array(
			'Blauw' => array( 'Blauwe saffier 1 ct', 'Blauwe saffier 2 ct', 'Blauwe saffier 3 ct' ),
			'Geel'  => array( 'Gele saffier 1 ct', 'Gele saffier 2 ct' ),
			'Roze'  => array( 'Roze saffier 1 ct', 'Roze saffier 2 ct' ),
		),
		'Smaragd' => array(
			'Geslepen' => array( 'Smaragd 0,50 ct', 'Smaragd 1,00 ct', 'Smaragd 2,00 ct' ),
		),
		'Robijn'  => array(
			'Geslepen' => array( 'Robijn 0,50 ct', 'Robijn 1,00 ct', 'Robijn 2,00 ct' ),
		),
	);
}

/* =====================================================================
   SEED  (upsert per steen+categorie+product)
===================================================================== */
function ekinese_seed_gemstones() {
	$count = 0;
	foreach ( ekinese_gemstone_seed_data() as $stone => $cats ) {
		foreach ( $cats as $cat => $products ) {
			foreach ( $products as $name ) {
				$title = $stone . ' ' . $name;
				$slug  = sanitize_title( $title );
				if ( ! $slug ) {
					continue;
				}
				$existing = get_page_by_path( $slug, OBJECT, 'xg_gemstone' );
				$postarr  = array(
					'post_type'    => 'xg_gemstone',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => sprintf(
						'Verkoop uw %1$s tegen een eerlijke dagprijs bij XGOUD. Onze edelsteen-experts taxeren uw %2$s op basis van karaat, kleur, helderheid en slijpvorm. Maak een afspraak voor een gratis, vrijblijvende taxatie.',
						$title,
						$stone
					),
				);
				if ( $existing ) {
					$postarr['ID'] = $existing->ID;
				}
				$id = wp_insert_post( $postarr );
				if ( is_wp_error( $id ) || ! $id ) {
					continue;
				}
				update_post_meta( $id, 'stone', $stone );
				update_post_meta( $id, 'category', $cat );
				$count++;
			}
		}
	}
	return $count;
}

/* =====================================================================
   DYNAMISCH BLOK  ekinese/gemstone-list
===================================================================== */
function ekinese_register_gemstone_block() {
	register_block_type(
		'ekinese/gemstone-list',
		array(
			'attributes'      => array(
				'stone'    => array( 'type' => 'string', 'default' => '' ),
				'category' => array( 'type' => 'string', 'default' => '' ),
				'limit'    => array( 'type' => 'number', 'default' => 48 ),
				'title'    => array( 'type' => 'string', 'default' => '' ),
			),
			'render_callback' => 'ekinese_render_gemstone_list',
		)
	);
}
add_action( 'init', 'ekinese_register_gemstone_block' );

function ekinese_render_gemstone_list( $attr ) {
	$meta = array();
	if ( ! empty( $attr['stone'] ) ) {
		$meta[] = array( 'key' => 'stone', 'value' => sanitize_text_field( $attr['stone'] ) );
	}
	if ( ! empty( $attr['category'] ) ) {
		$meta[] = array( 'key' => 'category', 'value' => sanitize_text_field( $attr['category'] ) );
	}
	$args = array(
		'post_type'      => 'xg_gemstone',
		'posts_per_page' => (int) ( $attr['limit'] ?? 48 ),
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( $meta ) {
		$meta['relation'] = 'AND';
		$args['meta_query'] = $meta;
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
			esc_html( get_post_meta( $p->ID, 'category', true ) )
		);
	}
	echo '</div></div></section>';
	return ob_get_clean();
}
