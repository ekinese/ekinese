<?php
/**
 * XGOUD stad-landingpages (#15) – geïndexeerde "goud verkopen in {stad}"-pagina's.
 *
 * CPT xg_city (URL-namespace /goud-verkopen/{stad}/) gevoed uit data/listings.csv
 * (45 steden met rijke, lokale SEO-tekst). Per pagina: lokale H1, actuele
 * dagprijs-CTA, LocalBusiness + FAQPage-schema, route-link. Waar een echt kantoor
 * bestaat (/kantoren/{stad}/) wijst de canonical daarheen → geen duplicate content.
 * Volgt exact het patroon van inc/offices.php (CPT + CSV-upsert + detail-blok).
 * Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT + detail-blok
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_city', array(
		'labels'       => array( 'name' => __( 'Stadpagina\'s', 'ekinese' ), 'singular_name' => __( 'Stadpagina', 'ekinese' ) ),
		'public'       => true,
		'has_archive'  => false,
		'show_in_menu' => 'xgoud',
		'menu_icon'    => 'dashicons-location',
		'supports'     => array( 'title', 'editor' ),
		'rewrite'      => array( 'slug' => 'goud-verkopen', 'with_front' => false ),
	) );
	register_block_type( 'ekinese/city-detail', array( 'render_callback' => 'ekinese_render_city_detail' ) );
} );

/** Heeft deze stad een echt kantoor? Geef de kantoor-URL terug, anders ''. */
function ekinese_city_office_url( $slug ) {
	$office = get_page_by_path( sanitize_title( $slug ), OBJECT, 'xg_office' );
	return $office ? get_permalink( $office ) : '';
}

/** Render van de stad-landingpage (blok in templates/single-xg_city.html). */
function ekinese_render_city_detail() {
	$id   = get_the_ID();
	$city = get_the_title( $id );
	$slug = get_post_field( 'post_name', $id );
	$spot = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( 'goud' ) : 0;
	$lat  = (float) get_post_meta( $id, 'lat', true );
	$lng  = (float) get_post_meta( $id, 'lng', true );
	$office_url = ekinese_city_office_url( $slug );

	ob_start();
	echo '<section class="xg-citylp"><div class="xg-container">';
	echo '<p class="xg-eyebrow">Goud verkopen · ' . esc_html( $city ) . '</p>';
	echo '<h1>Goud verkopen in ' . esc_html( $city ) . '</h1>';
	echo '<p class="xg-intro">Bij XGOUD verkoopt u uw goud, zilver en sieraden in ' . esc_html( $city ) . ' tegen een eerlijke, transparante dagprijs. Gratis en verzekerde taxatie, direct uitbetaald.</p>';

	// Lokale prijs-CTA.
	echo '<div class="xg-citylp-cta">';
	if ( $spot > 0 ) {
		echo '<div class="xg-citylp-price"><span class="xg-citylp-price-lbl">Actuele goudprijs</span><span class="xg-citylp-price-val">€ ' . esc_html( number_format_i18n( $spot, 2 ) ) . ' / gram</span></div>';
	}
	echo '<a class="xg-btn-gold" href="/afspraak/?stad=' . esc_attr( $slug ) . '">Bereken uw waarde</a>';
	if ( $office_url ) {
		echo ' <a class="xg-btn-outline" href="' . esc_url( $office_url ) . '">Bekijk ons kantoor in ' . esc_html( $city ) . '</a>';
	}
	echo '</div>';

	// Route-link (Google Maps) als coördinaten bekend zijn.
	if ( $lat && $lng ) {
		echo '<p class="xg-citylp-route"><a href="https://www.google.com/maps/dir/?api=1&destination=' . esc_attr( $lat . ',' . $lng ) . '" target="_blank" rel="noopener nofollow">Plan uw route &rsaquo;</a></p>';
	}

	// Rijke, geïmporteerde lokale tekst.
	$content = get_post_field( 'post_content', $id );
	if ( trim( wp_strip_all_tags( $content ) ) ) {
		echo '<div class="xg-citylp-body">' . wp_kses_post( apply_filters( 'the_content', $content ) ) . '</div>';
	}

	// Lokale FAQ (UI-accordeon; schema apart in wp_head).
	$faqs = ekinese_city_faqs( $city );
	echo '<div class="xg-citylp-faq"><h2>Veelgestelde vragen – ' . esc_html( $city ) . '</h2>';
	foreach ( $faqs as $f ) {
		echo '<details class="xg-faq-item"><summary>' . esc_html( $f['q'] ) . '</summary><div class="xg-faq-a">' . esc_html( $f['a'] ) . '</div></details>';
	}
	echo '</div>';

	echo '</div></section>';
	return ob_get_clean();
}

/** Lokale FAQ-set voor een stad (hergebruikt door schema). */
function ekinese_city_faqs( $city ) {
	return array(
		array( 'q' => 'Waar kan ik goud verkopen in ' . $city . '?', 'a' => 'Bij XGOUD verkoopt u goud in ' . $city . ' uitsluitend op afspraak, met een gratis en verzekerde taxatie. Maak online een afspraak en kom langs of laat onze expert bij u thuis komen.' ),
		array( 'q' => 'Hoeveel krijg ik voor mijn goud in ' . $city . '?', 'a' => 'De prijs is gebaseerd op de actuele dagprijs (spotprijs) en het gehalte van uw goud. U ziet de marge altijd vooraf en kunt online een indicatie berekenen.' ),
		array( 'q' => 'Word ik direct uitbetaald?', 'a' => 'Ja. Na akkoord op het bod betalen wij u direct uit, contant of per overboeking.' ),
		array( 'q' => 'Is de taxatie gratis?', 'a' => 'Ja, de taxatie bij XGOUD is altijd gratis en vrijblijvend.' ),
	);
}

/* =====================================================================
   Schema (LocalBusiness + FAQPage) + canonical
===================================================================== */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'xg_city' ) ) {
		return;
	}
	$id   = get_the_ID();
	$city = get_the_title( $id );
	$slug = get_post_field( 'post_name', $id );
	$b    = function_exists( 'ekinese_business' ) ? ekinese_business() : array();
	$lat  = (float) get_post_meta( $id, 'lat', true );
	$lng  = (float) get_post_meta( $id, 'lng', true );

	$local = array(
		'@context' => 'https://schema.org',
		'@type'    => 'LocalBusiness',
		'name'     => 'XGOUD ' . $city,
		'url'      => get_permalink( $id ),
		'areaServed' => $city,
		'telephone'  => $b['telephone'] ?? '',
		'priceRange' => '€€',
	);
	if ( $lat && $lng ) {
		$local['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng );
	}
	if ( ! empty( $b['name'] ) ) {
		$local['parentOrganization'] = array( '@type' => 'Organization', 'name' => $b['name'] );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $local, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	if ( function_exists( 'ekinese_faq_schema' ) ) {
		ekinese_faq_schema( ekinese_city_faqs( $city ) );
	}
}, 22 );

// Canonical → kantoorpagina waar die bestaat (anders self-canonical via de theme-default).
add_filter( 'ekinese_canonical_url', function ( $url ) {
	if ( is_singular( 'xg_city' ) ) {
		$office = ekinese_city_office_url( get_post_field( 'post_name', get_the_ID() ) );
		if ( $office ) {
			return $office;
		}
	}
	return $url;
} );

/* =====================================================================
   Sitemap + template
===================================================================== */
add_filter( 'ekinese_sitemap_urls', function ( $urls ) {
	$q = get_posts( array( 'post_type' => 'xg_city', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) );
	foreach ( $q as $id ) {
		// Alleen self-canonical steden in de sitemap (geen duplicates van kantoren).
		if ( ! ekinese_city_office_url( get_post_field( 'post_name', $id ) ) ) {
			$urls[] = get_permalink( $id );
		}
	}
	return $urls;
} );

// Block-theme: WordPress kiest templates/single-xg_city.html automatisch.

/* =====================================================================
   Import uit data/listings.csv (upsert per slug) + admin-knop
===================================================================== */
function ekinese_import_cities() {
	$file = get_theme_file_path( 'data/listings.csv' );
	if ( ! file_exists( $file ) || ! ( $fp = fopen( $file, 'r' ) ) ) { // phpcs:ignore
		return 0;
	}
	$header = null;
	$count  = 0;
	while ( ( $row = fgetcsv( $fp, 0, ',' ) ) !== false ) {
		if ( null === $header ) {
			$header    = $row;
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
			continue;
		}
		$r = @array_combine( $header, $row ); // phpcs:ignore
		if ( ! $r || empty( $r['Slug'] ) ) {
			continue;
		}
		$slug = sanitize_title( $r['Slug'] );
		$city = sanitize_text_field( $r['Title'] );
		$existing = get_page_by_path( $slug, OBJECT, 'xg_city' );
		$postarr  = array(
			'post_type'    => 'xg_city',
			'post_status'  => 'publish',
			'post_title'   => $city,
			'post_name'    => $slug,
			'post_content' => wp_kses_post( $r['Description'] ?? '' ),
		);
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
		}
		$id = wp_insert_post( $postarr );
		if ( $id && ! is_wp_error( $id ) ) {
			if ( ! empty( $r['Latitude'] ) ) {
				update_post_meta( $id, 'lat', (float) $r['Latitude'] );
			}
			if ( ! empty( $r['Longitude'] ) ) {
				update_post_meta( $id, 'lng', (float) $r['Longitude'] );
			}
			if ( ! empty( $r['Excerpt'] ) ) {
				update_post_meta( $id, '_xg_meta_desc', wp_strip_all_tags( $r['Excerpt'] ) );
			}
			$count++;
		}
	}
	fclose( $fp ); // phpcs:ignore
	delete_transient( 'xg_sitemap_xml' );
	flush_rewrite_rules();
	return $count;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Stadpagina\'s importeren', 'ekinese' ), __( 'Stadpagina\'s', 'ekinese' ), 'manage_options', 'xg-cities', function () {
		if ( isset( $_POST['xg_cities_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_cities_nonce'] ), 'xg_cities' ) ) {
			$n = ekinese_import_cities();
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( '%d stadpagina\'s geïmporteerd/bijgewerkt.', $n ) ) . '</p></div>';
		}
		$have = wp_count_posts( 'xg_city' );
		echo '<div class="wrap"><h1>Stadpagina\'s</h1><p>Bron: <code>data/listings.csv</code> (45 steden). URL: <code>/goud-verkopen/{stad}/</code>. Canonical wijst naar het kantoor waar dat bestaat.</p>';
		echo '<p>Huidig gepubliceerd: <strong>' . esc_html( (int) ( $have->publish ?? 0 ) ) . '</strong></p>';
		echo '<form method="post">';
		wp_nonce_field( 'xg_cities', 'xg_cities_nonce' );
		submit_button( 'Nu importeren' );
		echo '</form></div>';
	} );
} );
