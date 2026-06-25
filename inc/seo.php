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

	// Kantoor (LocalBusiness per stad).
	if ( is_singular( 'xg_office' ) ) {
		$id = get_the_ID();
		$graph[] = array(
			'@type'       => 'LocalBusiness',
			'name'        => 'XGOUD ' . get_the_title( $id ),
			'url'         => get_permalink( $id ),
			'telephone'   => get_post_meta( $id, 'phone', true ),
			'email'       => get_post_meta( $id, 'email', true ),
			'parentOrganization' => array( '@id' => $b['url'] . '#org' ),
			'address'     => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => get_post_meta( $id, 'street', true ),
				'postalCode'      => get_post_meta( $id, 'postcode', true ),
				'addressLocality' => get_post_meta( $id, 'city', true ),
				'addressCountry'  => 'NL',
			),
			'geo'         => array( '@type' => 'GeoCoordinates', 'latitude' => get_post_meta( $id, 'lat', true ), 'longitude' => get_post_meta( $id, 'lng', true ) ),
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
	/** Modules kunnen de canonical overschrijven (bv. stadpagina → kantoor). */
	$url  = apply_filters( 'ekinese_canonical_url', $url );
	// Social-afbeelding: per-pagina veld → uitgelichte afbeelding → logo.
	$img = '';
	if ( is_singular() ) {
		$img = get_post_meta( get_the_ID(), '_xg_og_image', true );
		if ( ! $img && has_post_thumbnail() ) {
			$img = get_the_post_thumbnail_url( null, 'large' );
		}
	}
	if ( ! $img ) {
		$img = ekinese_business()['logo'];
	}
	$title = wp_get_document_title();

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:type" content="' . ( is_singular( array( 'post' ) ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:site_name" content="XGOUD">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
}
add_action( 'wp_head', 'ekinese_meta_tags', 5 );

/** Interne CPT's niet indexeren. */
function ekinese_robots( $robots ) {
	if ( is_singular( array( 'xg_chat', 'xg_subscriber', 'xg_appointment' ) ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	// Per-pagina noindex via het SEO-veld.
	if ( is_singular() && get_post_meta( get_the_ID(), '_xg_noindex', true ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'ekinese_robots' );

/* =====================================================================
   SEO-VELDEN per pagina/product (titel, description, social, index, canonical)
===================================================================== */
function ekinese_seo_box() {
	foreach ( array( 'page', 'post', 'xg_product', 'xg_watch', 'xg_gemstone' ) as $pt ) {
		add_meta_box( 'xg_seo', __( 'SEO', 'ekinese' ), 'ekinese_seo_html', $pt, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'ekinese_seo_box' );

function ekinese_seo_html( $post ) {
	wp_nonce_field( 'xg_seo_save', 'xg_seo_nonce' );
	$title   = get_post_meta( $post->ID, '_xg_seo_title', true );
	$desc    = get_post_meta( $post->ID, '_xg_meta_desc', true );
	$ogimg   = get_post_meta( $post->ID, '_xg_og_image', true );
	$canon   = get_post_meta( $post->ID, '_xg_canonical', true );
	$noindex = get_post_meta( $post->ID, '_xg_noindex', true );
	$auto_t  = wp_strip_all_tags( get_the_title( $post ) );

	echo '<style>.xg-seo-f{margin:0 0 14px}.xg-seo-f label{display:block;font-weight:600;margin-bottom:4px}.xg-seo-f input[type=text]{width:100%}.xg-seo-c{color:#646970;font-size:12px;margin-top:3px}</style>';

	echo '<div class="xg-seo-f"><label>SEO-titel <span class="xg-seo-c">(leeg = automatisch: "' . esc_html( $auto_t ) . '")</span></label>';
	echo '<input type="text" name="xg_seo_title" value="' . esc_attr( $title ) . '" maxlength="70" placeholder="Titel zoals in Google (max ±60 tekens)"></div>';

	echo '<div class="xg-seo-f"><label>Meta description</label>';
	echo '<input type="text" name="xg_meta_desc" value="' . esc_attr( $desc ) . '" maxlength="160" placeholder="Korte omschrijving (max 160 tekens)…"></div>';

	echo '<div class="xg-seo-f"><label>Social-afbeelding (Open Graph / Twitter) <span class="xg-seo-c">(URL; leeg = uitgelichte afbeelding of logo)</span></label>';
	echo '<input type="text" name="xg_og_image" value="' . esc_attr( $ogimg ) . '" placeholder="https://…/afbeelding.jpg"></div>';

	echo '<div class="xg-seo-f"><label>Canonical-URL <span class="xg-seo-c">(optioneel; leeg = deze pagina zelf)</span></label>';
	echo '<input type="text" name="xg_canonical" value="' . esc_attr( $canon ) . '" placeholder="https://…"></div>';

	echo '<div class="xg-seo-f"><label><input type="checkbox" name="xg_noindex" value="1" ' . checked( $noindex, '1', false ) . '> Niet indexeren (noindex) — verberg deze pagina voor Google</label></div>';
}

function ekinese_seo_save( $post_id ) {
	if ( ! isset( $_POST['xg_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_seo_nonce'] ), 'xg_seo_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$text = array(
		'xg_seo_title' => '_xg_seo_title',
		'xg_meta_desc' => '_xg_meta_desc',
	);
	foreach ( $text as $field => $key ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}
	foreach ( array( 'xg_og_image' => '_xg_og_image', 'xg_canonical' => '_xg_canonical' ) as $field => $key ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $key, esc_url_raw( wp_unslash( $_POST[ $field ] ) ) );
		}
	}
	update_post_meta( $post_id, '_xg_noindex', isset( $_POST['xg_noindex'] ) ? '1' : '' );
}
add_action( 'save_post', 'ekinese_seo_save' );

/* ---- SEO-titel-override (exact, zonder site-naam-suffix) ---- */
function ekinese_seo_document_title( $title ) {
	if ( is_singular() ) {
		$t = get_post_meta( get_the_ID(), '_xg_seo_title', true );
		if ( $t ) {
			return $t;
		}
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'ekinese_seo_document_title' );

/* ---- Canonical-override per pagina (haakt op de bestaande filter) ---- */
function ekinese_seo_canonical( $url ) {
	if ( is_singular() ) {
		$c = get_post_meta( get_the_ID(), '_xg_canonical', true );
		if ( $c ) {
			return $c;
		}
	}
	return $url;
}
add_filter( 'ekinese_canonical_url', 'ekinese_seo_canonical' );

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
			'post_type'      => array( 'page', 'post', 'xg_product', 'xg_watch', 'xg_gemstone', 'xg_office', 'xg_charity_project' ),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );
		foreach ( $q as $id ) {
			$urls[] = get_permalink( $id );
		}
		/** Taalvarianten/extra URL's toevoegen (server-i18n hreflang). */
		$urls = apply_filters( 'ekinese_sitemap_urls', $urls );
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
