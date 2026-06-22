<?php
/**
 * Verkopen-Navigation: 5-stufige URL-Struktur + Produkt-Permalinks + Redirects.
 *
 * Struktur:
 *   L1 /verkopen/
 *   L2 /verkopen/{groep}/                         (edelmetalen | edelstenen | horloges)
 *   L3 /verkopen/{groep}/{metaal}/                (goud | zilver | platina | palladium)
 *   L4 /verkopen/{groep}/{metaal}/{categorie}/    (baren | munten | sloop)
 *   L5 /verkopen/{groep}/{metaal}/{categorie}/{product}
 *
 * L1–L4 zijn hiërarchische WP-pagina's (zie inc/installer.php → ekinese_install_verkopen_tree()).
 * L5 is het CPT xg_product, met een rewrite + post_type_link onder hetzelfde pad.
 * Oude URL's (/edelmetalen-verkopen/…, /product/{slug}) → 301 naar de nieuwe structuur.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   SLUG-MAPS  (één bron van waarheid)
===================================================================== */

/** metal-meta → URL-slug (L3). */
function ekinese_verkopen_metal_slugs() {
	return array(
		'gold'      => 'goud',
		'silver'    => 'zilver',
		'platinum'  => 'platina',
		'palladium' => 'palladium',
	);
}

/** category-meta → URL-slug (L4). */
function ekinese_verkopen_cat_slugs() {
	return array(
		'Baar'   => 'baren',
		'Munten' => 'munten',
		'Sloop'  => 'sloop',
	);
}

/** metal-meta → productgroep-slug (L2). */
function ekinese_verkopen_group_for_metal( $metal ) {
	$edelmetalen = array( 'gold', 'silver', 'platinum', 'palladium' );
	if ( in_array( $metal, $edelmetalen, true ) ) {
		return 'edelmetalen';
	}
	// Toekomst: edelstenen/horloges zodra producten bestaan.
	return 'edelmetalen';
}

/**
 * Pad-segmenten (groep/metaal/categorie) voor een product, of null als de
 * meta ontbreekt (dan valt de permalink terug op de WP-default).
 *
 * @param int $id Product-ID.
 * @return array{group:string,metal:string,cat:string}|null
 */
function ekinese_verkopen_product_segments( $id ) {
	$metal = get_post_meta( $id, 'metal', true );
	$cat   = get_post_meta( $id, 'category', true );
	$ms    = ekinese_verkopen_metal_slugs();
	$cs    = ekinese_verkopen_cat_slugs();
	if ( empty( $metal ) || empty( $cat ) || ! isset( $ms[ $metal ] ) || ! isset( $cs[ $cat ] ) ) {
		return null;
	}
	return array(
		'group' => ekinese_verkopen_group_for_metal( $metal ),
		'metal' => $ms[ $metal ],
		'cat'   => $cs[ $cat ],
	);
}

/**
 * Volledige (relatieve) URL-pad voor een product binnen de verkopen-structuur.
 *
 * @param int $id Product-ID.
 * @return string|null  bv. "verkopen/edelmetalen/goud/baren/<slug>" of null.
 */
function ekinese_verkopen_product_path( $id ) {
	$seg = ekinese_verkopen_product_segments( $id );
	if ( null === $seg ) {
		return null;
	}
	$name = get_post_field( 'post_name', $id );
	if ( ! $name ) {
		return null;
	}
	return sprintf( 'verkopen/%s/%s/%s/%s', $seg['group'], $seg['metal'], $seg['cat'], $name );
}

/* =====================================================================
   PRODUCT-PERMALINK  (post_type_link)
===================================================================== */
function ekinese_verkopen_product_permalink( $url, $post ) {
	if ( ! $post || 'xg_product' !== $post->post_type || 'publish' !== $post->post_status ) {
		return $url;
	}
	$path = ekinese_verkopen_product_path( $post->ID );
	if ( null === $path ) {
		return $url;
	}
	return home_url( user_trailingslashit( $path ) );
}
add_filter( 'post_type_link', 'ekinese_verkopen_product_permalink', 10, 2 );

/* =====================================================================
   REWRITE  (5-segments pad → product)
===================================================================== */
function ekinese_verkopen_rewrite() {
	// Exact 5 segmenten: verkopen/{groep}/{metaal}/{categorie}/{product}.
	// 'top' zodat deze regel vóór de generieke pagina-regels wint.
	add_rewrite_rule(
		'^verkopen/[^/]+/[^/]+/[^/]+/([^/]+)/?$',
		'index.php?xg_product=$matches[1]',
		'top'
	);
}
add_action( 'init', 'ekinese_verkopen_rewrite' );

/* =====================================================================
   301-REDIRECTS  (oude structuur → nieuwe)
===================================================================== */

/** Vaste map oude pagina-slug-paden → nieuwe verkopen-paden. */
function ekinese_verkopen_redirect_map() {
	return array(
		'edelmetalen-verkopen'                 => 'verkopen/edelmetalen',
		'edelmetalen-verkopen/goud-verkopen'   => 'verkopen/edelmetalen/goud',
		'edelmetalen-verkopen/zilver-verkopen' => 'verkopen/edelmetalen/zilver',
		'edelmetalen-verkopen/platina-verkopen'=> 'verkopen/edelmetalen/platina',
		'edelmetalen-verkopen/palladium-verkopen' => 'verkopen/edelmetalen/palladium',
		'goud-verkopen'                        => 'verkopen/edelmetalen/goud',
		'zilver-verkopen'                      => 'verkopen/edelmetalen/zilver',
		'platina-verkopen'                     => 'verkopen/edelmetalen/platina',
		'palladium-verkopen'                   => 'verkopen/edelmetalen/palladium',
		'edelstenen-verkopen'                  => 'verkopen/edelstenen',
		'horloges-verkopen'                    => 'verkopen/horloges',
	);
}

function ekinese_verkopen_redirects() {
	// 1) Canonical voor producten: oud /product/{slug} (of elk ander pad) → nieuw pad.
	if ( is_singular( 'xg_product' ) ) {
		$id   = get_queried_object_id();
		$path = ekinese_verkopen_product_path( $id );
		if ( $path ) {
			$target  = home_url( user_trailingslashit( $path ) );
			$current = home_url( add_query_arg( array() ) );
			if ( untrailingslashit( strtok( $current, '?' ) ) !== untrailingslashit( $target ) ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}
		return;
	}

	// 2) Oude landingspagina-paden → nieuwe (alleen relevant bij 404 na verwijdering).
	$req = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$req = trim( (string) $req, '/' );
	if ( '' === $req ) {
		return;
	}
	$map = ekinese_verkopen_redirect_map();
	if ( isset( $map[ $req ] ) ) {
		wp_safe_redirect( home_url( user_trailingslashit( $map[ $req ] ) ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'ekinese_verkopen_redirects' );
