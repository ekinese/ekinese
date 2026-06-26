<?php
/**
 * XGOUD installer – one-click setup voor (staging-)installatie.
 *
 * Maakt alle pagina's aan met de juiste patterns/blokken, in de juiste
 * hiërarchie, zet de homepage, en draait de imports (producten, locaties,
 * lexicon-seed, horloges-seed, charity-seed). Idempotent: bestaande pagina's
 * worden niet gedupliceerd. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lees de block-markup uit een pattern-bestand (zonder PHP-header). */
function ekinese_pattern_content( $slug ) {
	$file = get_theme_file_path( 'patterns/' . $slug . '.php' );
	if ( ! file_exists( $file ) ) {
		return '';
	}
	$raw = (string) file_get_contents( $file ); // phpcs:ignore
	$pos = strpos( $raw, '?>' );
	return $pos !== false ? trim( substr( $raw, $pos + 2 ) ) : $raw;
}

/**
 * Paginastructuur: slug => [titel, pattern|block, parent-slug|null].
 * Volgorde zo dat parents vóór children komen.
 */
function ekinese_install_pages() {
	return array(
		// Top.
		'home'                  => array( 'XGOUD – Goud verkopen', 'page-home', null, 'front' ),
		'afspraak'              => array( 'Afspraak maken', 'hero-calculator', null ),
		'mijn-xgoud'            => array( 'Mijn XGOUD', '<!-- wp:ekinese/account /-->', null ),
		'registreren'           => array( 'Registreren', '<!-- wp:ekinese/account /-->', null ),
		'app'                   => array( 'XGOUD App', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:ekinese/widget /--></div>\n<!-- /wp:group -->", null ),
		'verify'                => array( 'Bezoek verifiëren', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:ekinese/verify /--></div>\n<!-- /wp:group -->", null ),
		'rit'                   => array( 'XGOUD Rit', '<!-- wp:ekinese/driver-app /-->', null ),
		// Veilingen-overzicht: bewerkbare pagina (intro = native blokken) + dynamisch grid.
		'veilingen'             => array( 'Veilingen', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:group {\"tagName\":\"section\",\"className\":\"xg-container\"} -->\n<section class=\"wp-block-group xg-container\"><!-- wp:heading {\"className\":\"xg-section-title\"} -->\n<h2 class=\"xg-section-title\">Veilingen</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Bied mee op bijzondere edelmetalen, munten, sieraden en horloges. Een deel van elke opbrengst gaat naar onze goede doelen. Let op: een bod is bindend en kan niet worden ingetrokken.</p>\n<!-- /wp:paragraph --></section>\n<!-- /wp:group -->\n\n<!-- wp:ekinese/auctions /--></div>\n<!-- /wp:group -->", null ),
		// Marktplaats: overzicht + plaats-pagina + voorwaarden (bewerkbaar).
		'marktplaats'           => array( 'Marktplaats', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:ekinese/marketplace /--></div>\n<!-- /wp:group -->", null ),
		'plaatsen'              => array( 'Advertentie plaatsen', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:ekinese/market-submit /--></div>\n<!-- /wp:group -->", 'marktplaats' ),
		'marktplaats-voorwaarden' => array( 'Marktplaats-voorwaarden', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:group {\"tagName\":\"section\",\"className\":\"xg-container\"} -->\n<section class=\"wp-block-group xg-container\"><!-- wp:heading {\"className\":\"xg-section-title\"} -->\n<h2 class=\"xg-section-title\">Marktplaats-voorwaarden</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Op de XGOUD-Marktplaats verloopt alle communicatie en betaling via XGOUD. Het uitwisselen van contactgegevens tussen kopers en verkopers is niet toegestaan. Een product dat via een XGOUD-veiling wordt aangeboden, kan niet worden teruggetrokken.</p>\n<!-- /wp:paragraph --></section>\n<!-- /wp:group --></div>\n<!-- /wp:group -->", 'marktplaats' ),
		'momenten'              => array( 'Momenten', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:ekinese/moments {\"title\":\"Momenten van onze klanten\",\"limit\":12} /-->\n\n<!-- wp:ekinese/moment-form /--></div>\n<!-- /wp:group -->", null ),
		// Edelmetalen / Edelstenen / Horloges: zie ekinese_install_verkopen_tree()
		// (hiërarchische /verkopen/-structuur, 5 niveaus). Bewust NIET hier.
		// Dagprijzen.
		'dagprijzen'            => array( 'Dagprijzen', 'price-dagprijzen', null ),
		'goudprijs'             => array( 'Goudprijs vandaag', 'price-goudprijs', 'dagprijzen' ),
		'zilverprijs'           => array( 'Zilverprijs vandaag', 'price-zilverprijs', 'dagprijzen' ),
		'platinaprijs'          => array( 'Platinaprijs vandaag', 'price-platinaprijs', 'dagprijzen' ),
		'palladiumprijs'        => array( 'Palladiumprijs', 'price-palladiumprijs', 'dagprijzen' ),
		// Metaal-ratio's (hub + 6 paren onder /dagprijzen/ratio/).
		'ratio'                 => array( "Metaal-ratio's", 'ratio-hub', 'dagprijzen' ),
		'goud-zilver'           => array( 'Goud/Zilver-ratio', ekinese_ratio_page_markup( 'goud', 'zilver' ), 'ratio' ),
		'goud-platina'          => array( 'Goud/Platina-ratio', ekinese_ratio_page_markup( 'goud', 'platina' ), 'ratio' ),
		'goud-palladium'        => array( 'Goud/Palladium-ratio', ekinese_ratio_page_markup( 'goud', 'palladium' ), 'ratio' ),
		'platina-zilver'        => array( 'Platina/Zilver-ratio', ekinese_ratio_page_markup( 'platina', 'zilver' ), 'ratio' ),
		'palladium-zilver'      => array( 'Palladium/Zilver-ratio', ekinese_ratio_page_markup( 'palladium', 'zilver' ), 'ratio' ),
		'platina-palladium'     => array( 'Platina/Palladium-ratio', ekinese_ratio_page_markup( 'platina', 'palladium' ), 'ratio' ),
		'inkoopprijzen'         => array( 'Inkoopprijzen', 'price-inkoopprijzen', null ),
		// Service.
		'prijsgarantie'         => array( 'Prijsgarantie', 'page-prijsgarantie', null ),
		'vergelijken'           => array( 'Vergelijken', 'page-vergelijken', null ),
		'kennisbank'            => array( 'Kennisbank', 'kennisbank-hub', null ),
		'zakelijk'              => array( 'Zakelijk verkopen', 'page-zakelijk', null ),
		'portaal'               => array( 'Zakelijk portaal', "<!-- wp:group {\"className\":\"xg-blueprint\"} -->\n<div class=\"wp-block-group xg-blueprint\"><!-- wp:ekinese/business-dashboard /--></div>\n<!-- /wp:group -->", 'zakelijk' ),

		'service'               => array( 'Service', 'page-services', null ),
		'gratis-taxatie'        => array( 'Gratis taxatie', 'services-taxatie', 'service' ),
		'thuisbezoek'           => array( 'Thuisbezoek', 'services-thuisbezoek', 'service' ),
		'inruilen'              => array( 'Inruilen', 'services-inruilen', 'service' ),
		'faq'                   => array( 'Veelgestelde vragen', 'services-faq', 'service' ),
		'hoe-werkt-het'         => array( 'Hoe werkt het', 'services-hoe-werkt-het', 'service' ),
		'kantoorbezoek'         => array( 'Kantoorbezoek', 'services-kantoorbezoek', 'service' ),
		'ophaalservice'         => array( 'Ophaalservice', 'services-ophaalservice', 'service' ),
		'zakelijk-verkopen'     => array( 'Zakelijk verkopen', 'services-zakelijk', 'service' ),
		// Over ons.
		'over-ons'              => array( 'Over ons', 'page-over-ons', null ),
		'bedrijf'               => array( 'Het bedrijf', 'over-ons-bedrijf', 'over-ons' ),
		'team'                  => array( 'Ons team', 'over-ons-experts', 'over-ons' ),
		'geschiedenis'          => array( 'Ons verhaal', 'over-ons-verhaal', 'over-ons' ),
		'beoordelingen'         => array( 'Beoordelingen', 'over-ons-beoordelingen', 'over-ons' ),
		'certificaten'          => array( 'Certificaten', 'over-ons-certificaten', 'over-ons' ),
		'partners'              => array( 'Partners', 'over-ons-partners', 'over-ons' ),
		'nieuws'                => array( 'Nieuws', 'over-ons-nieuws', 'over-ons' ),
		'pers'                  => array( 'Pers', 'over-ons-pers', 'over-ons' ),
		'vacatures'             => array( 'Vacatures', 'over-ons-vacatures', 'over-ons' ),
		// Overig.
		'contact'               => array( 'Contact', 'contact', null ),
		'privacy'               => array( 'Privacyverklaring', 'page-privacy', null ),
		'voorwaarden'           => array( 'Algemene voorwaarden', 'page-terms', null ),
		'cookies'               => array( 'Cookiebeleid', 'page-cookies', null ),
	);
}

/**
 * Bouwt de hiërarchische /verkopen/-structuur (L1–L4) als WP-pagina's.
 * Idempotent: matcht bestaande kinderen op (post_name + parent).
 * L4-categorieën worden alleen aangemaakt als er ten minste 1 product is
 * (voorkomt dunne, lege landingspagina's). Geeft het aantal upserts terug.
 *
 * @return int
 */
function ekinese_install_verkopen_tree() {
	if ( ! function_exists( 'ekinese_verkopen_metal_slugs' ) ) {
		return 0;
	}
	$count = 0;

	$upsert = function ( $name, $title, $content, $parent ) use ( &$count ) {
		$existing = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => $name,
				'post_parent' => $parent,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $name,
			'post_content' => $content,
			'post_parent'  => $parent,
		);
		if ( ! empty( $existing ) ) {
			$postarr['ID'] = (int) $existing[0];
		}
		$pid = wp_insert_post( $postarr );
		if ( ! is_wp_error( $pid ) && $pid ) {
			$count++;
			return (int) $pid;
		}
		return 0;
	};

	// Wrapt dynamische lijst-blokken in een .xg-blueprint-groep, zodat de
	// layout-CSS (.xg-blueprint .xg-container/.xg-grid-4) greift (anders rendert
	// het grid niet → "reusachtige boxen").
	$bp = function ( $inner ) {
		return '<!-- wp:group {"className":"xg-blueprint"} --><div class="wp-block-group xg-blueprint">' . $inner . '</div><!-- /wp:group -->';
	};

	// L1.
	$vk = $upsert( 'verkopen', 'Verkopen', ekinese_pattern_content( 'verkopen-hub' ), 0 );
	if ( ! $vk ) {
		return 0;
	}

	// L2 productgroepen.
	$groups = array(
		'edelmetalen' => array( 'Edelmetalen verkopen', 'blueprint-edelmetalen' ),
		'edelstenen'  => array( 'Edelstenen verkopen', 'blueprint-edelstenen' ),
		'horloges'    => array( 'Horloges verkopen', 'blueprint-horloges' ),
	);
	$gid = array();
	foreach ( $groups as $slug => $g ) {
		$gid[ $slug ] = $upsert( $slug, $g[0], ekinese_pattern_content( $g[1] ), $vk );
	}

	// L3 metalen (onder edelmetalen) + L4 categorieën.
	$metals = array(
		'goud'      => array( 'label' => 'Goud', 'pattern' => 'cat-goud', 'meta' => 'gold' ),
		'zilver'    => array( 'label' => 'Zilver', 'pattern' => 'cat-zilver', 'meta' => 'silver' ),
		'platina'   => array( 'label' => 'Platina', 'pattern' => 'cat-platina', 'meta' => 'platinum' ),
		'palladium' => array( 'label' => 'Palladium', 'pattern' => 'cat-palladium', 'meta' => 'palladium' ),
	);
	// L4 categorie-slug → category-meta.
	$cats = array(
		'baren'  => 'Baar',
		'munten' => 'Munten',
		'sloop'  => 'Sloop',
	);

	foreach ( $metals as $mslug => $m ) {
		if ( empty( $gid['edelmetalen'] ) ) {
			break;
		}
		$mid = $upsert( $mslug, $m['label'] . ' verkopen', ekinese_pattern_content( $m['pattern'] ), $gid['edelmetalen'] );
		if ( ! $mid ) {
			continue;
		}
		foreach ( $cats as $cslug => $cmeta ) {
			// Alleen aanmaken als er producten zijn voor dit metaal + categorie.
			$has = get_posts(
				array(
					'post_type'   => 'xg_product',
					'post_status' => 'publish',
					'numberposts' => 1,
					'fields'      => 'ids',
					'meta_query'  => array(
						'relation' => 'AND',
						array( 'key' => 'metal', 'value' => $m['meta'] ),
						array( 'key' => 'category', 'value' => $cmeta ),
					),
				)
			);
			if ( empty( $has ) ) {
				continue;
			}
			$title = sprintf( '%s %s verkopen', $m['label'], ucfirst( $cslug ) );
			$block = $bp( sprintf(
				'<!-- wp:ekinese/product-list {"metal":"%s","category":"%s","title":"%s","limit":120} /-->',
				esc_attr( $m['meta'] ),
				esc_attr( $cmeta ),
				esc_attr( $title )
			) );
			$upsert( $cslug, $title, $block, $mid );
		}
	}

	// L3 horloge-merken (onder horloges). De collectie (xg_watch) is de
	// productpagina (L4) en krijgt zijn permalink via inc/verkopen.php.
	if ( ! empty( $gid['horloges'] ) && function_exists( 'ekinese_watch_seed_data' ) ) {
		foreach ( array_keys( ekinese_watch_seed_data() ) as $brand ) {
			$bslug = sanitize_title( $brand );
			$title = $brand . ' verkopen';
			$block = $bp( sprintf(
				'<!-- wp:ekinese/watch-list {"brand":"%s","title":"%s","limit":48} /-->',
				esc_attr( $brand ),
				esc_attr( $title )
			) );
			$upsert( $bslug, $title, $block, $gid['horloges'] );
		}
	}

	// L3 edelstenen (steen) + L4 categorieën (onder edelstenen). Leaf = xg_gemstone.
	if ( ! empty( $gid['edelstenen'] ) && function_exists( 'ekinese_gemstone_seed_data' ) ) {
		foreach ( ekinese_gemstone_seed_data() as $stone => $cats ) {
			$sslug = sanitize_title( $stone );
			$sblock = $bp( sprintf(
				'<!-- wp:ekinese/gemstone-list {"stone":"%s","title":"%s","limit":96} /-->',
				esc_attr( $stone ),
				esc_attr( $stone . ' verkopen' )
			) );
			$sid = $upsert( $sslug, $stone . ' verkopen', $sblock, $gid['edelstenen'] );
			if ( ! $sid ) {
				continue;
			}
			foreach ( array_keys( $cats ) as $cat ) {
				// Alleen aanmaken als er edelstenen zijn voor deze steen + categorie.
				$has = get_posts(
					array(
						'post_type'   => 'xg_gemstone',
						'post_status' => 'publish',
						'numberposts' => 1,
						'fields'      => 'ids',
						'meta_query'  => array(
							'relation' => 'AND',
							array( 'key' => 'stone', 'value' => $stone ),
							array( 'key' => 'category', 'value' => $cat ),
						),
					)
				);
				if ( empty( $has ) ) {
					continue;
				}
				$ctitle = sprintf( '%s %s verkopen', $stone, $cat );
				$cblock = $bp( sprintf(
					'<!-- wp:ekinese/gemstone-list {"stone":"%s","category":"%s","title":"%s","limit":96} /-->',
					esc_attr( $stone ),
					esc_attr( $cat ),
					esc_attr( $ctitle )
				) );
				$upsert( sanitize_title( $cat ), $ctitle, $cblock, $sid );
			}
		}
	}

	return $count;
}

/**
 * Verplaatst de oude platte *-verkopen-pagina's naar de prullenbak, zodat hun
 * URL's vrijkomen voor de nieuwe /verkopen/-structuur en de 301-redirects
 * (inc/verkopen.php) op een 404 kunnen ingrijpen. Idempotent.
 */
function ekinese_verkopen_cleanup_old_pages() {
	if ( ! function_exists( 'ekinese_verkopen_redirect_map' ) ) {
		return;
	}
	foreach ( array_keys( ekinese_verkopen_redirect_map() ) as $old_path ) {
		$page = get_page_by_path( $old_path );
		if ( $page && 'trash' !== $page->post_status ) {
			wp_trash_post( $page->ID );
		}
	}
}

/**
 * Ruimt dubbele pagina's op die eerder ontstonden door de kapotte idempotentie
 * (kind-pagina's werden bij elke run opnieuw aangemaakt; WP gaf hen slugs als
 * "goudprijs-2"). Groepeert op (titel + parent) en verplaatst alle behalve het
 * origineel (laagste ID) naar de prullenbak. Geeft het aantal opgeruimde terug.
 *
 * @return int
 */
function ekinese_dedupe_pages() {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT post_title, post_parent, GROUP_CONCAT(ID ORDER BY ID ASC) AS ids, COUNT(*) AS c
		 FROM {$wpdb->posts}
		 WHERE post_type = 'page' AND post_status IN ('publish','draft','pending','private')
		 GROUP BY post_title, post_parent
		 HAVING c > 1"
	); // phpcs:ignore WordPress.DB
	$trashed = 0;
	foreach ( (array) $rows as $r ) {
		$ids = explode( ',', $r->ids );
		array_shift( $ids ); // origineel (laagste ID) behouden.
		foreach ( $ids as $id ) {
			if ( wp_trash_post( (int) $id ) ) {
				$trashed++;
			}
		}
	}
	return $trashed;
}

/** Voer de volledige installatie uit. */
function ekinese_run_install() {
	$ids   = array();
	$front = 0;
	foreach ( ekinese_install_pages() as $slug => $def ) {
		list( $title, $source, $parent ) = array( $def[0], $def[1], $def[2] );
		$is_front = isset( $def[3] ) && 'front' === $def[3];
		// Content: pattern of directe block-markup.
		$content = ( 0 === strpos( $source, '<!--' ) ) ? $source : ekinese_pattern_content( $source );

		// Idempotent op (post_name + parent). get_page_by_path($slug) faalt voor
		// kind-pagina's (verwacht het volledige pad) → zou bij elke run duplicaten
		// maken. Daarom hier zoeken op naam binnen de juiste parent.
		$parent_id = ( $parent && isset( $ids[ $parent ] ) ) ? $ids[ $parent ] : 0;
		$existing  = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => $slug,
				'post_parent' => $parent_id,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_parent'  => $parent_id,
		);
		if ( ! empty( $existing ) ) {
			$postarr['ID'] = (int) $existing[0];
		}
		$pid = wp_insert_post( $postarr );
		if ( ! is_wp_error( $pid ) ) {
			$ids[ $slug ] = $pid;
			if ( $is_front ) {
				$front = $pid;
			}
		}
	}
	// Homepage instellen.
	if ( $front ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
	}
	// Imports/seeds (indien beschikbaar).
	$report = array( 'pages' => count( $ids ) );
	if ( function_exists( 'ekinese_import_products' ) ) {
		$report['products'] = (int) ekinese_import_products();
	}
	// Horloges + edelstenen seeden VÓÓR de verkopen-boom, zodat L3/L4 alleen
	// worden aangemaakt waar producten bestaan.
	if ( function_exists( 'ekinese_seed_watches' ) ) {
		$report['watches'] = (int) ekinese_seed_watches();
	}
	if ( function_exists( 'ekinese_seed_gemstones' ) ) {
		$report['gemstones'] = (int) ekinese_seed_gemstones();
	}
	if ( function_exists( 'ekinese_seed_dummy_auctions' ) ) {
		$report['auctions'] = (int) ekinese_seed_dummy_auctions();
	}
	if ( function_exists( 'ekinese_seed_dummy_news' ) ) {
		$report['news'] = (int) ekinese_seed_dummy_news();
	}
	if ( function_exists( 'ekinese_seed_dummy_market' ) ) {
		$report['market'] = (int) ekinese_seed_dummy_market();
	}
	// Verkopen-boom (5 niveaus) NA de imports, zodat lege L4-categorieën
	// kunnen worden overgeslagen. Telt extra pagina's mee in het rapport.
	$report['pages'] += (int) ekinese_install_verkopen_tree();
	// Oude *-verkopen-pagina's opruimen, zodat de nieuwe /verkopen/-structuur de
	// URL's bezit en de 301-redirects (inc/verkopen.php) kunnen werken.
	ekinese_verkopen_cleanup_old_pages();
	// Eerder ontstane dubbele pagina's opruimen (naar prullenbak).
	$report['dedupe'] = ekinese_dedupe_pages();
	if ( function_exists( 'ekinese_import_locations' ) ) {
		$report['locations'] = (int) ekinese_import_locations();
	}
	if ( function_exists( 'ekinese_import_cities' ) ) {
		$report['cities'] = (int) ekinese_import_cities();
	}
	if ( function_exists( 'ekinese_seed_lexicon' ) ) {
		ekinese_seed_lexicon();
	}
	// Steden horen alleen onder Kantoren, niet dubbel in het lexicon.
	if ( function_exists( 'ekinese_lexicon_remove_locations' ) ) {
		ekinese_lexicon_remove_locations();
	}
	// (horloges + edelstenen worden hierboven, vóór de verkopen-boom, geseed.)
	flush_rewrite_rules( false );
	update_option( 'xg_installed', gmdate( 'c' ) );
	return $report;
}

/* ---- Admin: Setup-pagina ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'tools.php', __( 'XGOUD installatie', 'ekinese' ), __( 'XGOUD setup', 'ekinese' ), 'manage_options', 'xg-install', function () {
		if ( isset( $_POST['xg_install_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_install_nonce'] ), 'xg_install' ) ) {
			$r = ekinese_run_install();
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Klaar: %d pagina\'s, %d producten, %d locaties, %d horloges, %d edelstenen, %d veilingen.', $r['pages'] ?? 0, $r['products'] ?? 0, $r['locations'] ?? 0, $r['watches'] ?? 0, $r['gemstones'] ?? 0, $r['auctions'] ?? 0 ) ) . '</p></div>';
		}
		$done = get_option( 'xg_installed' );
		echo '<div class="wrap"><h1>XGOUD installatie</h1>';
		echo '<p>Maakt alle pagina\'s aan (home, edelmetalen, dagprijzen, service, over ons, juridisch, account, driver), zet de homepage en importeert producten, locaties, lexicon en horloges. Veilig herhaalbaar.</p>';
		if ( $done ) {
			echo '<p><em>Laatste installatie: ' . esc_html( $done ) . '</em></p>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'xg_install', 'xg_install_nonce' );
		submit_button( __( 'Installeren / bijwerken', 'ekinese' ) );
		echo '</form></div>';
	} );
} );
