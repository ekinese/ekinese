<?php
/**
 * XGOUD SEO/GEO-Engine (self-built, performant, kein Plugin).
 *
 *  - JSON-LD: Organization + LocalBusiness (sitewide), Product (productpagina),
 *    BreadcrumbList, FAQPage (via helper).
 *  - Meta description / canonical / Open Graph / Twitter.
 *  - Eigene, gecachte XML-sitemap op /sitemap.xml.
 *  - robots: noindex voor interne CPT's (chat/subscriber/appointment).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Basis-bedrijfsgegevens (centraal – ook door schema gebruikt). */
function ekinese_business() {
	return array(
		'name'      => 'XGOUD',
		'url'       => home_url( '/' ),
		'logo'      => home_url( '/wp-content/uploads/logo.png' ),
		'telephone' => '+31850603009',
		'email'     => 'info@xgoud.nl',
		'street'    => 'Stratumsedijk 23',
		'postcode'  => '5611 NA',
		'city'      => 'Eindhoven',
		'country'   => 'NL',
		'lat'       => 51.4361,
		'lng'       => 5.4836,
		'sameAs'    => array(
			'https://www.facebook.com/xgoud',
			'https://www.instagram.com/xgoud',
		),
		'hours'     => array(
			array( 'days' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ), 'open' => '09:00', 'close' => '18:00' ),
			array( 'days' => array( 'Saturday' ), 'open' => '10:00', 'close' => '17:00' ),
		),
	);
}

/* =====================================================================
   JSON-LD
===================================================================== */
function ekinese_jsonld() {
	$b      = ekinese_business();
	$graph  = array();

	// Organization + LocalBusiness (sitewide).
	$graph[] = array(
		'@type'       => array( 'Organization', 'LocalBusiness' ),
		'@id'         => $b['url'] . '#org',
		'name'        => $b['name'],
		'url'         => $b['url'],
		'logo'        => $b['logo'],
		'telephone'   => $b['telephone'],
		'email'       => $b['email'],
		'sameAs'      => $b['sameAs'],
		'address'     => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $b['street'],
			'postalCode'      => $b['postcode'],
			'addressLocality' => $b['city'],
			'addressCountry'  => $b['country'],
		),
		'geo'         => array( '@type' => 'GeoCoordinates', 'latitude' => $b['lat'], 'longitude' => $b['lng'] ),
		'openingHoursSpecification' => array_map(
			function ( $h ) {
				return array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $h['days'],
					'opens'     => $h['open'],
					'closes'    => $h['close'],
				);
			},
			$b['hours']
		),
	);

	// Product.
	if ( is_singular( 'xg_product' ) && function_exists( 'ekinese_get_product' ) ) {
		$id = get_the_ID();
		$graph[] = array(
			'@type'       => 'Product',
			'name'        => get_the_title( $id ),
			'brand'       => array( '@type' => 'Brand', 'name' => get_post_meta( $id, 'manufacturer', true ) ?: 'XGOUD' ),
			'material'    => get_post_meta( $id, 'metal', true ),
			'weight'      => array( '@type' => 'QuantitativeValue', 'value' => get_post_meta( $id, 'weight', true ), 'unitCode' => 'GRM' ),
			'description' => wp_strip_all_tags( get_the_excerpt( $id ) ),
			'url'         => get_permalink( $id ),
		);
	}

	// Breadcrumbs.
	if ( is_singular() && ! is_front_page() ) {
		$items = array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $b['url'] ),
			array( '@type' => 'ListItem', 'position' => 2, 'name' => get_the_title(), 'item' => get_permalink() ),
		);
		$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
	}

	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'ekinese_jsonld', 20 );

/**
 * FAQPage-schema renderen (aanroepen vanuit een FAQ-pattern/template).
 *
 * @param array $faqs [ ['q'=>..,'a'=>..], ... ]
 */
function ekinese_faq_schema( $faqs ) {
	$items = array();
	foreach ( $faqs as $f ) {
		$items[] = array(
			'@type'          => 'Question',
			'name'           => $f['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}

/* =====================================================================
   META / OG / CANONICAL
===================================================================== */
function ekinese_meta_tags() {
	$desc = '';
	if ( is_singular() ) {
		$desc = get_post_meta( get_the_ID(), '_xg_meta_desc', true );
		if ( ! $desc ) {
			$desc = wp_strip_all_tags( get_the_excerpt() );
		}
	} elseif ( is_front_page() ) {
		$desc = get_bloginfo( 'description' );
	}
	$desc = trim( mb_substr( $desc, 0, 160 ) );
	$url  = is_singular() ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
	$img  = ( is_singular() && has_post_thumbnail() ) ? get_the_post_thumbnail_url( null, 'large' ) : ekinese_business()['logo'];

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:site_name" content="XGOUD">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( wp_get_document_title() ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}
add_action( 'wp_head', 'ekinese_meta_tags', 5 );

/** Interne CPT's niet indexeren. */
function ekinese_robots( $robots ) {
	if ( is_singular( array( 'xg_chat', 'xg_subscriber', 'xg_appointment' ) ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'ekinese_robots' );

/* =====================================================================
   META-DESCRIPTION VELD (per pagina/product)
===================================================================== */
function ekinese_meta_desc_box() {
	foreach ( array( 'page', 'post', 'xg_product' ) as $pt ) {
		add_meta_box( 'xg_seo', __( 'SEO – meta description', 'ekinese' ), 'ekinese_meta_desc_html', $pt, 'normal', 'low' );
	}
}
add_action( 'add_meta_boxes', 'ekinese_meta_desc_box' );

function ekinese_meta_desc_html( $post ) {
	wp_nonce_field( 'xg_seo_save', 'xg_seo_nonce' );
	$v = esc_attr( get_post_meta( $post->ID, '_xg_meta_desc', true ) );
	echo '<p><input type="text" name="xg_meta_desc" value="' . $v . '" maxlength="160" style="width:100%" placeholder="Korte meta description (max 160 tekens)…"></p>';
}

function ekinese_meta_desc_save( $post_id ) {
	if ( ! isset( $_POST['xg_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_seo_nonce'] ), 'xg_seo_save' ) ) {
		return;
	}
	if ( isset( $_POST['xg_meta_desc'] ) ) {
		update_post_meta( $post_id, '_xg_meta_desc', sanitize_text_field( wp_unslash( $_POST['xg_meta_desc'] ) ) );
	}
}
add_action( 'save_post', 'ekinese_meta_desc_save' );

/* =====================================================================
   EIGEN, GECACHTE XML-SITEMAP  →  /sitemap.xml
===================================================================== */
function ekinese_sitemap_rewrite() {
	add_rewrite_rule( '^sitemap\.xml$', 'index.php?xg_sitemap=1', 'top' );
}
add_action( 'init', 'ekinese_sitemap_rewrite' );

function ekinese_sitemap_qv( $vars ) {
	$vars[] = 'xg_sitemap';
	return $vars;
}
add_filter( 'query_vars', 'ekinese_sitemap_qv' );

function ekinese_sitemap_output() {
	if ( ! get_query_var( 'xg_sitemap' ) ) {
		return;
	}
	$xml = get_transient( 'xg_sitemap_xml' );
	if ( false === $xml ) {
		$urls = array( home_url( '/' ) );
		$q    = get_posts( array(
			'post_type'      => array( 'page', 'post', 'xg_product', 'xg_office', 'xg_charity_project' ),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );
		foreach ( $q as $id ) {
			$urls[] = get_permalink( $id );
		}
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( array_unique( $urls ) as $u ) {
			$xml .= '<url><loc>' . esc_url( $u ) . '</loc></url>' . "\n";
		}
		$xml .= '</urlset>';
		set_transient( 'xg_sitemap_xml', $xml, 6 * HOUR_IN_SECONDS );
	}
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo $xml; // phpcs:ignore
	exit;
}
add_action( 'template_redirect', 'ekinese_sitemap_output' );

// Sitemap-cache leegmaken bij publiceren.
add_action( 'save_post', function () { delete_transient( 'xg_sitemap_xml' ); } );
