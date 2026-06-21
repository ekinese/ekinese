<?php
/**
 * XGOUD Producten – zentrale, performante Produktdaten.
 *
 * Strategie (statt „immer die grosse JSON laden"):
 *   1. Einmaliger Import data/products.json → CPT xg_product (im Backend
 *      editierbar, neue Produkte anlegbar – auch für unerfahrene Nutzer).
 *   2. Ein gecachtes Aggregat (Objektcache/Redis + Transient), das Calculator,
 *      Wizards und Bots nutzen – nie wieder 699 Posts oder die JSON parsen.
 *
 * Quelle der Spezifikationen (Specs-Tabelle): die Produktfelder selbst
 * (gewicht, zuiverheid, fijn gewicht, fabrikant, land, serie, nominale waarde).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_PRODUCT_FIELDS = array( 'metal', 'category', 'type', 'weight', 'carat', 'fine_weight', 'multipliar', 'manufacturer', 'country', 'series', 'face_value', 'aliases', 'related_mode' );

/* =====================================================================
   CPT + Meta
===================================================================== */
function ekinese_register_products() {
	register_post_type(
		'xg_product',
		array(
			'labels'       => array(
				'name'          => __( 'Producten', 'ekinese' ),
				'singular_name' => __( 'Product', 'ekinese' ),
				'add_new_item'  => __( 'Nieuw product', 'ekinese' ),
				'menu_name'     => __( 'Producten', 'ekinese' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-database',
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'      => array( 'slug' => 'product' ),
		)
	);
	foreach ( XG_PRODUCT_FIELDS as $f ) {
		register_post_meta( 'xg_product', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	}
}
add_action( 'init', 'ekinese_register_products' );

/* =====================================================================
   IMPORT  (data/products.json → CPT, upsert per slug)
===================================================================== */
function ekinese_import_products() {
	$file = get_theme_file_path( 'data/products.json' );
	if ( ! file_exists( $file ) ) {
		return 0;
	}
	$data = json_decode( file_get_contents( $file ), true ); // phpcs:ignore
	if ( ! is_array( $data ) ) {
		return 0;
	}

	$count = 0;
	foreach ( $data as $metal => $cats ) {
		foreach ( $cats as $category => $items ) {
			foreach ( $items as $p ) {
				$slug = sanitize_title( $p['slug'] ?? $p['name'] );
				if ( ! $slug ) {
					continue;
				}
				$existing = get_page_by_path( $slug, OBJECT, 'xg_product' );
				$postarr  = array(
					'post_type'   => 'xg_product',
					'post_status' => 'publish',
					'post_title'  => $p['name'] ?? $slug,
					'post_name'   => $slug,
				);
				if ( $existing ) {
					$postarr['ID'] = $existing->ID;
				}
				$id = wp_insert_post( $postarr );
				if ( is_wp_error( $id ) ) {
					continue;
				}
				update_post_meta( $id, 'metal', sanitize_text_field( $p['metal'] ?? strtolower( $metal ) ) );
				update_post_meta( $id, 'category', sanitize_text_field( $category ) );
				foreach ( array( 'type', 'weight', 'carat', 'fine_weight', 'multipliar', 'manufacturer', 'country', 'series', 'face_value' ) as $f ) {
					if ( isset( $p[ $f ] ) ) {
						update_post_meta( $id, $f, sanitize_text_field( (string) $p[ $f ] ) );
					}
				}
				if ( ! empty( $p['aliases'] ) && is_array( $p['aliases'] ) ) {
					update_post_meta( $id, 'aliases', wp_json_encode( array_map( 'sanitize_text_field', $p['aliases'] ) ) );
				}
				$count++;
			}
		}
	}
	ekinese_flush_products_cache();
	return $count;
}

/* =====================================================================
   GECACHTES AGGREGAT  (Objektcache/Redis + Transient)
===================================================================== */
function ekinese_flush_products_cache() {
	wp_cache_delete( 'xg_dataset', 'xg' );
	delete_transient( 'xg_products_dataset' );
}
add_action( 'save_post_xg_product', 'ekinese_flush_products_cache' );
add_action( 'deleted_post', 'ekinese_flush_products_cache' );

/**
 * Komplettes Produkt-Dataset (für Calculator/Bots): nach Metall+Kategorie
 * gruppiert. Wird gecacht – kein erneutes Parsen/Query.
 *
 * @return array
 */
function ekinese_products_dataset() {
	$cached = wp_cache_get( 'xg_dataset', 'xg' );
	if ( false !== $cached ) {
		return $cached;
	}
	$cached = get_transient( 'xg_products_dataset' );
	if ( false !== $cached ) {
		wp_cache_set( 'xg_dataset', $cached, 'xg', HOUR_IN_SECONDS );
		return $cached;
	}

	$out   = array();
	$posts = get_posts( array( 'post_type' => 'xg_product', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
	foreach ( $posts as $p ) {
		$metal = get_post_meta( $p->ID, 'metal', true ) ?: 'gold';
		$cat   = get_post_meta( $p->ID, 'category', true ) ?: 'Overig';
		$out[ $metal ][ $cat ][] = array(
			'id'           => $p->ID,
			'name'         => $p->post_title,
			'slug'         => $p->post_name,
			'weight'       => (float) str_replace( ',', '.', get_post_meta( $p->ID, 'weight', true ) ),
			'carat'        => get_post_meta( $p->ID, 'carat', true ),
			'fine_weight'  => (float) str_replace( ',', '.', get_post_meta( $p->ID, 'fine_weight', true ) ),
			'multipliar'   => (float) ( get_post_meta( $p->ID, 'multipliar', true ) ?: 0.99 ),
			'type'         => get_post_meta( $p->ID, 'type', true ),
			'manufacturer' => get_post_meta( $p->ID, 'manufacturer', true ),
		);
	}
	set_transient( 'xg_products_dataset', $out, 12 * HOUR_IN_SECONDS );
	wp_cache_set( 'xg_dataset', $out, 'xg', HOUR_IN_SECONDS );
	return $out;
}

/** Einzelnes Produkt (gecacht über get_page_by_path/Objektcache). */
function ekinese_get_product( $slug ) {
	$post = get_page_by_path( sanitize_title( $slug ), OBJECT, 'xg_product' );
	if ( ! $post ) {
		return null;
	}
	$out = array( 'id' => $post->ID, 'name' => $post->post_title, 'slug' => $post->post_name );
	foreach ( XG_PRODUCT_FIELDS as $f ) {
		$out[ $f ] = get_post_meta( $post->ID, $f, true );
	}
	return $out;
}

/* =====================================================================
   SPECS-TABELLE  (Produktseite)
===================================================================== */
function ekinese_product_specs_table( $product_id ) {
	$rows = array(
		'Fabrikant'      => get_post_meta( $product_id, 'manufacturer', true ),
		'Type'           => get_post_meta( $product_id, 'type', true ),
		'Gewicht'        => ( $w = get_post_meta( $product_id, 'weight', true ) ) ? $w . ' g' : '',
		'Zuiverheid'     => get_post_meta( $product_id, 'carat', true ),
		'Fijn gewicht'   => ( $fw = get_post_meta( $product_id, 'fine_weight', true ) ) ? $fw . ' g' : '',
		'Land'           => get_post_meta( $product_id, 'country', true ),
		'Serie'          => get_post_meta( $product_id, 'series', true ),
		'Nominale waarde'=> get_post_meta( $product_id, 'face_value', true ),
	);
	$html = '<table class="xg-price-table xg-spec-table"><tbody>';
	foreach ( $rows as $label => $val ) {
		if ( '' === $val || null === $val ) {
			continue;
		}
		$html .= '<tr><th style="width:40%">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}
	$html .= '</tbody></table>';
	return $html;
}

/**
 * Related products: per Zufall aus derselben Kategorie oder gleicher Größe.
 * Modus pro Produkt im Backend wählbar (meta 'related_mode': category|weight).
 */
function ekinese_related_products( $product_id, $limit = 4 ) {
	$mode = get_post_meta( $product_id, 'related_mode', true ) ?: 'category';
	$args = array(
		'post_type'      => 'xg_product',
		'posts_per_page' => $limit,
		'post__not_in'   => array( $product_id ),
		'orderby'        => 'rand',
		'post_status'    => 'publish',
	);
	if ( 'weight' === $mode ) {
		$args['meta_query'] = array( array( 'key' => 'weight', 'value' => get_post_meta( $product_id, 'weight', true ) ) );
	} else {
		$args['meta_query'] = array(
			array( 'key' => 'metal', 'value' => get_post_meta( $product_id, 'metal', true ) ),
			array( 'key' => 'category', 'value' => get_post_meta( $product_id, 'category', true ) ),
		);
	}
	return get_posts( $args );
}

/* =====================================================================
   ADMIN: Metabox (editieren/aanmaken) + Import-knop
===================================================================== */
function ekinese_product_metabox() {
	add_meta_box( 'xg_product', __( 'Productgegevens', 'ekinese' ), 'ekinese_product_metabox_html', 'xg_product', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_product_metabox' );

function ekinese_product_metabox_html( $post ) {
	wp_nonce_field( 'xg_product_save', 'xg_product_nonce' );
	$f = function ( $key ) use ( $post ) { return esc_attr( get_post_meta( $post->ID, $key, true ) ); };
	echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 18px">';
	$labels = array(
		'metal' => 'Metaal (gold/silver/platinum/palladium)', 'category' => 'Categorie (Baar/Munten/Sloop)', 'type' => 'Type',
		'weight' => 'Gewicht (g)', 'carat' => 'Zuiverheid', 'fine_weight' => 'Fijn gewicht (g)',
		'multipliar' => 'Marge-factor (bv 0.99)', 'manufacturer' => 'Fabrikant', 'country' => 'Land',
		'series' => 'Serie', 'face_value' => 'Nominale waarde',
	);
	foreach ( $labels as $k => $l ) {
		echo '<p><label><strong>' . esc_html( $l ) . '</strong><br><input type="text" name="xg_' . esc_attr( $k ) . '" value="' . $f( $k ) . '" style="width:100%"></label></p>';
	}
	echo '</div>';
	echo '<p><label><strong>Related products</strong><br><select name="xg_related_mode"><option value="category"' . selected( get_post_meta( $post->ID, 'related_mode', true ), 'category', false ) . '>Zelfde categorie (random)</option><option value="weight"' . selected( get_post_meta( $post->ID, 'related_mode', true ), 'weight', false ) . '>Zelfde gewicht (random)</option></select></label></p>';
}

function ekinese_product_save( $post_id ) {
	if ( ! isset( $_POST['xg_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_product_nonce'] ), 'xg_product_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	foreach ( array( 'metal', 'category', 'type', 'weight', 'carat', 'fine_weight', 'multipliar', 'manufacturer', 'country', 'series', 'face_value', 'related_mode' ) as $k ) {
		if ( isset( $_POST[ 'xg_' . $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ 'xg_' . $k ] ) ) );
		}
	}
}
add_action( 'save_post_xg_product', 'ekinese_product_save' );

// Import-Seite.
function ekinese_products_import_menu() {
	add_submenu_page( 'edit.php?post_type=xg_product', __( 'Importeren', 'ekinese' ), __( 'Importeren', 'ekinese' ), 'manage_options', 'xg-products-import', 'ekinese_products_import_page' );
}
add_action( 'admin_menu', 'ekinese_products_import_menu' );

function ekinese_products_import_page() {
	if ( isset( $_POST['xg_imp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_imp_nonce'] ), 'xg_imp' ) ) {
		$n = ekinese_import_products();
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( '%d producten geïmporteerd/bijgewerkt.', $n ) ) . '</p></div>';
	}
	$total = wp_count_posts( 'xg_product' );
	echo '<div class="wrap"><h1>Producten importeren</h1>';
	echo '<p>Bron: <code>data/products.json</code>. Bestaande producten worden bijgewerkt (op slug), nieuwe toegevoegd.</p>';
	echo '<p>Huidig aantal producten: <strong>' . (int) ( $total->publish ?? 0 ) . '</strong></p>';
	echo '<form method="post">';
	wp_nonce_field( 'xg_imp', 'xg_imp_nonce' );
	submit_button( __( 'Nu importeren / synchroniseren', 'ekinese' ) );
	echo '</form></div>';
}

/* =====================================================================
   REST  (voor bots/calculators)
===================================================================== */
function ekinese_register_products_rest() {
	register_rest_route( 'ekinese/v1', '/products', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () { return ekinese_products_dataset(); },
	) );
	register_rest_route( 'ekinese/v1', '/product/(?P<slug>[a-z0-9\-]+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $r ) {
			$p = ekinese_get_product( $r['slug'] );
			return $p ?: new WP_Error( 'xg_nf', 'Niet gevonden', array( 'status' => 404 ) );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_register_products_rest' );
