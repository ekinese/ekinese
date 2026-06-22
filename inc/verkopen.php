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
   HORLOGES  (xg_watch → /verkopen/horloges/{merk}/{collectie}/)
   Merk → collectie (4 niveaus): de collectie-post is de productpagina.
===================================================================== */

/** Pad voor een horloge, of null als brand/collection ontbreekt. */
function ekinese_verkopen_watch_path( $id ) {
	$brand = get_post_meta( $id, 'brand', true );
	$coll  = get_post_meta( $id, 'collection', true );
	if ( empty( $brand ) || empty( $coll ) ) {
		return null;
	}
	return sprintf( 'verkopen/horloges/%s/%s', sanitize_title( $brand ), sanitize_title( $coll ) );
}

/** Merk-naam bij merk-slug (bron: watch-seed-data). */
function ekinese_verkopen_watch_brand_by_slug( $slug ) {
	if ( ! function_exists( 'ekinese_watch_seed_data' ) ) {
		return null;
	}
	foreach ( array_keys( ekinese_watch_seed_data() ) as $brand ) {
		if ( sanitize_title( $brand ) === $slug ) {
			return $brand;
		}
	}
	return null;
}

/** Collectie-naam bij (merk-naam, collectie-slug). */
function ekinese_verkopen_watch_coll_by_slug( $brand, $slug ) {
	if ( ! function_exists( 'ekinese_watch_seed_data' ) ) {
		return null;
	}
	$data = ekinese_watch_seed_data();
	foreach ( ( $data[ $brand ] ?? array() ) as $coll ) {
		if ( sanitize_title( $coll ) === $slug ) {
			return $coll;
		}
	}
	return null;
}

/* =====================================================================
   EDELSTENEN  (xg_gemstone → /verkopen/edelstenen/{steen}/{categorie}/{slug}/)
===================================================================== */

/** Pad voor een edelsteen, of null als steen/categorie ontbreekt. */
function ekinese_verkopen_gemstone_path( $id ) {
	$stone = get_post_meta( $id, 'stone', true );
	$cat   = get_post_meta( $id, 'category', true );
	if ( empty( $stone ) || empty( $cat ) ) {
		return null;
	}
	$name = get_post_field( 'post_name', $id );
	if ( ! $name ) {
		return null;
	}
	return sprintf( 'verkopen/edelstenen/%s/%s/%s', sanitize_title( $stone ), sanitize_title( $cat ), $name );
}

/* =====================================================================
   PERMALINKS  (post_type_link voor xg_product, xg_watch én xg_gemstone)
===================================================================== */
function ekinese_verkopen_product_permalink( $url, $post ) {
	if ( ! $post || 'publish' !== $post->post_status ) {
		return $url;
	}
	if ( 'xg_product' === $post->post_type ) {
		$path = ekinese_verkopen_product_path( $post->ID );
	} elseif ( 'xg_watch' === $post->post_type ) {
		$path = ekinese_verkopen_watch_path( $post->ID );
	} elseif ( 'xg_gemstone' === $post->post_type ) {
		$path = ekinese_verkopen_gemstone_path( $post->ID );
	} else {
		return $url;
	}
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
	// Edelmetalen-product (5 segmenten, leaf = xg_product, globaal-unieke slug).
	add_rewrite_rule(
		'^verkopen/edelmetalen/[^/]+/[^/]+/([^/]+)/?$',
		'index.php?xg_product=$matches[1]',
		'top'
	);
	// Edelstenen-product (5 segmenten, leaf = xg_gemstone).
	add_rewrite_rule(
		'^verkopen/edelstenen/[^/]+/[^/]+/([^/]+)/?$',
		'index.php?xg_gemstone=$matches[1]',
		'top'
	);
	// Horloge (4 segmenten): /verkopen/horloges/{merk}/{collectie}/.
	// Wordt via de request-filter naar het juiste xg_watch-bericht vertaald.
	add_rewrite_rule(
		'^verkopen/horloges/([^/]+)/([^/]+)/?$',
		'index.php?xg_watch_merk=$matches[1]&xg_watch_coll=$matches[2]',
		'top'
	);
}
add_action( 'init', 'ekinese_verkopen_rewrite' );

/** Eigen query-vars registreren (horloge-resolver). */
function ekinese_verkopen_query_vars( $vars ) {
	$vars[] = 'xg_watch_merk';
	$vars[] = 'xg_watch_coll';
	return $vars;
}
add_filter( 'query_vars', 'ekinese_verkopen_query_vars' );

/**
 * Vertaalt /verkopen/horloges/{merk}/{collectie}/ naar het concrete xg_watch.
 * Merk+collectie zijn samen uniek; reverse-mapping via de watch-seed-data.
 */
function ekinese_verkopen_resolve_watch( $qv ) {
	if ( empty( $qv['xg_watch_merk'] ) || empty( $qv['xg_watch_coll'] ) ) {
		return $qv;
	}
	$merk_slug = $qv['xg_watch_merk'];
	$coll_slug = $qv['xg_watch_coll'];
	unset( $qv['xg_watch_merk'], $qv['xg_watch_coll'] );

	$brand = ekinese_verkopen_watch_brand_by_slug( $merk_slug );
	$coll  = $brand ? ekinese_verkopen_watch_coll_by_slug( $brand, $coll_slug ) : null;
	if ( $brand && $coll ) {
		$found = get_posts(
			array(
				'post_type'   => 'xg_watch',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array(
					'relation' => 'AND',
					array( 'key' => 'brand', 'value' => $brand ),
					array( 'key' => 'collection', 'value' => $coll ),
				),
			)
		);
		if ( ! empty( $found ) ) {
			$qv['post_type'] = 'xg_watch';
			$qv['xg_watch']  = get_post_field( 'post_name', $found[0] );
			return $qv;
		}
	}
	// Niet gevonden → 404.
	$qv['error'] = '404';
	return $qv;
}
add_filter( 'request', 'ekinese_verkopen_resolve_watch' );

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
	// 1) Canonical: oud pad (/product/…, /horloges/…, /edelsteen/…) → nieuw.
	$path = null;
	if ( is_singular( 'xg_product' ) ) {
		$path = ekinese_verkopen_product_path( get_queried_object_id() );
	} elseif ( is_singular( 'xg_watch' ) ) {
		$path = ekinese_verkopen_watch_path( get_queried_object_id() );
	} elseif ( is_singular( 'xg_gemstone' ) ) {
		$path = ekinese_verkopen_gemstone_path( get_queried_object_id() );
	}
	if ( is_singular( array( 'xg_product', 'xg_watch', 'xg_gemstone' ) ) ) {
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
