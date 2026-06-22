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
		'rit'                   => array( 'XGOUD Rit', '<!-- wp:ekinese/driver-app /-->', null ),
		// Edelmetalen.
		'edelmetalen-verkopen'  => array( 'Edelmetalen verkopen', 'blueprint-edelmetalen', null ),
		'goud-verkopen'         => array( 'Goud verkopen', 'cat-goud', 'edelmetalen-verkopen' ),
		'zilver-verkopen'       => array( 'Zilver verkopen', 'cat-zilver', 'edelmetalen-verkopen' ),
		'platina-verkopen'      => array( 'Platina verkopen', 'cat-platina', 'edelmetalen-verkopen' ),
		'palladium-verkopen'    => array( 'Palladium verkopen', 'cat-palladium', 'edelmetalen-verkopen' ),
		// Edelstenen / horloges.
		'edelstenen-verkopen'   => array( 'Edelstenen verkopen', 'blueprint-edelstenen', null ),
		'horloges-verkopen'     => array( 'Horloges verkopen', 'blueprint-horloges', null ),
		// Dagprijzen.
		'dagprijzen'            => array( 'Dagprijzen', 'price-dagprijzen', null ),
		'goudprijs'             => array( 'Goudprijs vandaag', 'price-goudprijs', 'dagprijzen' ),
		'zilverprijs'           => array( 'Zilverprijs vandaag', 'price-zilverprijs', 'dagprijzen' ),
		'platinaprijs'          => array( 'Platinaprijs vandaag', 'price-platinaprijs', 'dagprijzen' ),
		'palladiumprijs'        => array( 'Palladiumprijs', 'price-palladiumprijs', 'dagprijzen' ),
		'inkoopprijzen'         => array( 'Inkoopprijzen', 'price-inkoopprijzen', null ),
		// Service.
		'prijsgarantie'         => array( 'Prijsgarantie', 'page-prijsgarantie', null ),
		'vergelijken'           => array( 'Vergelijken', 'page-vergelijken', null ),
		'kennisbank'            => array( 'Kennisbank', 'kennisbank-hub', null ),

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

/** Voer de volledige installatie uit. */
function ekinese_run_install() {
	$ids   = array();
	$front = 0;
	foreach ( ekinese_install_pages() as $slug => $def ) {
		list( $title, $source, $parent ) = array( $def[0], $def[1], $def[2] );
		$is_front = isset( $def[3] ) && 'front' === $def[3];
		// Content: pattern of directe block-markup.
		$content = ( 0 === strpos( $source, '<!--' ) ) ? $source : ekinese_pattern_content( $source );

		$existing = get_page_by_path( $slug );
		$postarr  = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_parent'  => ( $parent && isset( $ids[ $parent ] ) ) ? $ids[ $parent ] : 0,
		);
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
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
	if ( function_exists( 'ekinese_import_locations' ) ) {
		$report['locations'] = (int) ekinese_import_locations();
	}
	if ( function_exists( 'ekinese_import_cities' ) ) {
		$report['cities'] = (int) ekinese_import_cities();
	}
	if ( function_exists( 'ekinese_seed_lexicon' ) ) {
		ekinese_seed_lexicon();
	}
	if ( function_exists( 'ekinese_seed_watches' ) ) {
		$report['watches'] = (int) ekinese_seed_watches();
	}
	flush_rewrite_rules( false );
	update_option( 'xg_installed', gmdate( 'c' ) );
	return $report;
}

/* ---- Admin: Setup-pagina ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'tools.php', __( 'XGOUD installatie', 'ekinese' ), __( 'XGOUD setup', 'ekinese' ), 'manage_options', 'xg-install', function () {
		if ( isset( $_POST['xg_install_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_install_nonce'] ), 'xg_install' ) ) {
			$r = ekinese_run_install();
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Klaar: %d pagina\'s, %d producten, %d locaties, %d horloges.', $r['pages'] ?? 0, $r['products'] ?? 0, $r['locations'] ?? 0, $r['watches'] ?? 0 ) ) . '</p></div>';
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
