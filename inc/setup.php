<?php
/**
 * Theme-Setup: Supports und Asset-Loading.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme-Supports registrieren.
 */
function ekinese_setup() {
	// Übersetzungen.
	load_theme_textdomain( 'ekinese', get_template_directory() . '/languages' );

	// Standard-Block-Theme-Supports.
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// Zusätzliche Editor-Stylesheets laden (für Front- und Editor-Konsistenz).
	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'ekinese_setup' );

/**
 * Frontend-Assets laden.
 */
function ekinese_enqueue_assets() {
	wp_enqueue_style(
		'ekinese-style',
		get_stylesheet_uri(),
		array(),
		EKINESE_VERSION
	);

	$main_css = get_theme_file_path( 'assets/css/main.css' );
	if ( file_exists( $main_css ) ) {
		wp_enqueue_style(
			'ekinese-main',
			get_theme_file_uri( 'assets/css/main.css' ),
			array(),
			(string) filemtime( $main_css )
		);
	}

	// Theme-System (Day/Dark-Switch, Rot primär + Gold-Highlight). Lädt VOR
	// header-footer.css, damit die Dark-Token-Overrides greifen. theme.js im
	// <head>, damit data-theme früh gesetzt wird (kein Flash/FOUC).
	$theme_css = get_theme_file_path( 'assets/css/theme.css' );
	if ( file_exists( $theme_css ) ) {
		wp_enqueue_style(
			'ekinese-theme',
			get_theme_file_uri( 'assets/css/theme.css' ),
			array(),
			(string) filemtime( $theme_css )
		);
	}
	$theme_js = get_theme_file_path( 'assets/js/theme.js' );
	if ( file_exists( $theme_js ) ) {
		wp_enqueue_script(
			'ekinese-theme',
			get_theme_file_uri( 'assets/js/theme.js' ),
			array(),
			(string) filemtime( $theme_js ),
			false // im <head> laden (FOUC vermeiden)
		);
	}

	// Locale-Control (Sprache/Währung/Theme) – nach theme.js, vor Header.
	$loc_css = get_theme_file_path( 'assets/css/locale.css' );
	if ( file_exists( $loc_css ) ) {
		wp_enqueue_style( 'ekinese-locale', get_theme_file_uri( 'assets/css/locale.css' ), array( 'ekinese-theme' ), (string) filemtime( $loc_css ) );
	}
	$loc_js = get_theme_file_path( 'assets/js/locale.js' );
	if ( file_exists( $loc_js ) ) {
		wp_enqueue_script( 'ekinese-locale', get_theme_file_uri( 'assets/js/locale.js' ), array( 'ekinese-theme' ), (string) filemtime( $loc_js ), true );
	}
	// Onboarding-rondleiding (eenmalig, front-end).
	$ob = get_theme_file_path( 'assets/js/onboarding.js' );
	if ( file_exists( $ob ) && ! is_admin() ) {
		wp_enqueue_script( 'ekinese-onboarding', get_theme_file_uri( 'assets/js/onboarding.js' ), array(), (string) filemtime( $ob ), true );
	}

	// i18n.js alleen laden wanneer er iets te vertalen valt: op NL (default) is
	// het overbodig (server rendert NL), dus besparen we de meeste bezoekers JS.
	$cur_lang = function_exists( 'xg_current_lang' ) ? xg_current_lang() : 'nl';
	$i18n     = get_theme_file_path( 'assets/js/i18n.js' );
	if ( 'nl' !== $cur_lang && file_exists( $i18n ) ) {
		wp_enqueue_script( 'ekinese-i18n', get_theme_file_uri( 'assets/js/i18n.js' ), array( 'ekinese-locale' ), (string) filemtime( $i18n ), true );
	}

	// Header & Footer System (eigenständige Komponente: Ticker, Mega-Menu, Suche).
	$hf_css = get_theme_file_path( 'assets/css/header-footer.css' );
	if ( file_exists( $hf_css ) ) {
		wp_enqueue_style(
			'ekinese-header-footer',
			get_theme_file_uri( 'assets/css/header-footer.css' ),
			array(),
			(string) filemtime( $hf_css )
		);
	}

	$hf_js = get_theme_file_path( 'assets/js/header-footer.js' );
	if ( file_exists( $hf_js ) ) {
		wp_enqueue_script(
			'ekinese-header-footer',
			get_theme_file_uri( 'assets/js/header-footer.js' ),
			array(),
			(string) filemtime( $hf_js ),
			true // im Footer laden
		);
	}

	// Blueprint Styles & Interaktivität (für Verkoof-Landingpages).
	$bp_css = get_theme_file_path( 'assets/css/blueprint.css' );
	if ( file_exists( $bp_css ) ) {
		wp_enqueue_style(
			'ekinese-blueprint',
			get_theme_file_uri( 'assets/css/blueprint.css' ),
			array(),
			(string) filemtime( $bp_css )
		);
	}

	// Dark-Mode aanvullende laag – ALS LAATSTE laden, zodat de dark-overrides
	// onafhankelijk van de laadvolgorde winnen. Alleen actief in dark.
	$dark_css = get_theme_file_path( 'assets/css/dark.css' );
	if ( file_exists( $dark_css ) ) {
		wp_enqueue_style(
			'ekinese-dark',
			get_theme_file_uri( 'assets/css/dark.css' ),
			array( 'ekinese-theme', 'ekinese-blueprint' ),
			(string) filemtime( $dark_css )
		);
	}

	$bp_js = get_theme_file_path( 'assets/js/blueprint.js' );
	if ( file_exists( $bp_js ) ) {
		wp_enqueue_script(
			'ekinese-blueprint',
			get_theme_file_uri( 'assets/js/blueprint.js' ),
			array(),
			(string) filemtime( $bp_js ),
			true // im Footer laden
		);
	}

	// Smart Calculator (Wertberechnung + Verkauf + Charity).
	$calc_css = get_theme_file_path( 'assets/css/calculator.css' );
	if ( file_exists( $calc_css ) ) {
		wp_enqueue_style(
			'ekinese-calculator',
			get_theme_file_uri( 'assets/css/calculator.css' ),
			array(),
			(string) filemtime( $calc_css )
		);
	}

	$calc_js = get_theme_file_path( 'assets/js/calculator.js' );
	if ( file_exists( $calc_js ) ) {
		wp_enqueue_script(
			'ekinese-calculator',
			get_theme_file_uri( 'assets/js/calculator.js' ),
			array( 'ekinese-theme' ),
			(string) filemtime( $calc_js ),
			true // im Footer laden
		);

		/*
		 * Preis-/Margen-Daten an den Calculator übergeben.
		 *
		 * AKTUELL: Mock-Daten (siehe ekinese_calculator_data()).
		 * SPÄTER:  Diese Funktion liest aus den Cron-gecachten DB-Tabellen
		 *          (Swiss Forex / iDex) + wp_xg_margins. Das JS bleibt
		 *          unverändert – nur die Datenquelle wechselt.
		 */
		$calc_data = ekinese_calculator_data();
		// Performance: de zware productlijst (606 items) alleen meesturen op
		// pagina's die daadwerkelijk een calculator tonen.
		if ( ! ekinese_page_has_calculator() ) {
			unset( $calc_data['products'] );
		}
		wp_localize_script( 'ekinese-calculator', 'XG_CALC_DATA', $calc_data );
	}
}

/**
 * Heeft de huidige pagina (waarschijnlijk) een calculator?
 * Hero (front), productpagina's, categorie-/verkooparchieven, of inhoud met
 * een .xg-calc / hero-calculator-blok.
 */
function ekinese_page_has_calculator() {
	if ( is_front_page() || is_singular( 'xg_product' ) || is_post_type_archive( 'xg_product' ) ) {
		return true;
	}
	if ( is_tax( array( 'xg_verkoop_cat', 'xg_term_cat' ) ) ) {
		return true;
	}
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && ( false !== strpos( $post->post_content, 'xg-calc' ) || false !== strpos( $post->post_content, 'hero-calculator' ) || has_block( 'ekinese/product-detail', $post ) ) ) {
			return true;
		}
	}
	/** Forceren waar nodig (bv. losse landingspagina's). */
	return (bool) apply_filters( 'ekinese_page_has_calculator', false );
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_assets' );

/**
 * Niet-kritische theme-scripts uitstellen (defer) → minder render-blocking,
 * snellere first paint. theme.js blijft in de <head> (voorkomt FOUC/thema-flits).
 */
function ekinese_defer_scripts( $tag, $handle ) {
	$defer = array(
		'ekinese-locale', 'ekinese-i18n', 'ekinese-header-footer', 'ekinese-calculator',
		'ekinese-chat', 'ekinese-charity', 'ekinese-newsletter', 'ekinese-offices',
		'ekinese-account', 'ekinese-heatmap', 'ekinese-search-assist',
	);
	if ( in_array( $handle, $defer, true ) && false === strpos( $tag, ' defer' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'ekinese_defer_scripts', 10, 2 );

/**
 * Daten-Provider für den Smart Calculator.
 *
 * MOCK-PHASE: Statische Beispielwerte, deren Struktur exakt den späteren
 * DB-Tabellen entspricht. Sobald der Cron-Cache (Swiss Forex / iDex) und
 * das Margen-Admin stehen, liefert diese Funktion die echten Werte –
 * das Frontend (calculator.js) muss dafür NICHT angepasst werden.
 *
 * @return array
 */
function ekinese_calculator_data() {
	$data = array(
		// Echte producten (606 baren & munten) voor verkoop per stuk.
		'products'  => function_exists( 'ekinese_calculator_products' ) ? ekinese_calculator_products() : array(),
		// wp_xg_margins – später LIVE im Admin editierbar.
		'margins'   => array(
			'metal'         => 0.08,
			'diamond_range' => 0.10,
			'gem_range'     => 0.12,
			'watch_range'   => 0.10,
			'charity_share' => 0.05,
		),
		// wp_xg_metals – Spotpreis €/g (Mock; später Swiss Forex Cron-Cache).
		'metals'    => array(
			'goud'      => array( 'label' => 'Goud',      'spot' => 62.50 ),
			'zilver'    => array( 'label' => 'Zilver',    'spot' => 0.78 ),
			'platina'   => array( 'label' => 'Platina',   'spot' => 28.90 ),
			'palladium' => array( 'label' => 'Palladium', 'spot' => 30.10 ),
		),
		/*
		 * Reinheit ist METALL-ABHÄNGIG:
		 *   Gold       → Karat (8–24 karaat)
		 *   Silber/Platin/Palladium → Legierung (Tausendstel, z. B. 925, 950, 999)
		 * Werte = Feingehalt-Faktor (0–1).
		 */
		'metal_purities' => array(
			'goud'      => array(
				'8'  => 0.333,
				'14' => 0.585,
				'18' => 0.750,
				'21' => 0.875,
				'22' => 0.916,
				'24' => 0.999,
			),
			'zilver'    => array(
				'800' => 0.800,
				'835' => 0.835,
				'925' => 0.925,
				'999' => 0.999,
			),
			'platina'   => array(
				'850' => 0.850,
				'900' => 0.900,
				'950' => 0.950,
				'999' => 0.999,
			),
			'palladium' => array(
				'500' => 0.500,
				'950' => 0.950,
				'999' => 0.999,
			),
		),
		// Klartext-Labels für Legierungen (Gold nutzt direkt "karaat" im JS).
		'purity_labels' => array(
			'800' => '80,0 %',
			'835' => '83,5 %',
			'850' => '85,0 %',
			'900' => '90,0 %',
			'925' => 'Sterling 92,5%',
			'950' => '95,0 %',
			'999' => 'Fijn 99,9%',
			'500' => '50,0 %',
		),
		'conditions' => array(
			'nieuw'     => array( 'label' => 'Nieuwstaat',    'factor' => 1.00 ),
			'zeer_goed' => array( 'label' => 'Zeer goed',     'factor' => 0.99 ),
			'goed'      => array( 'label' => 'Goed',          'factor' => 0.98 ),
			'voldoende' => array( 'label' => 'Voldoende', 'factor' => 0.96 ),
		),
		'jewelry_tiers' => array(
			array( 'min' => 0,   'bonus' => 0.00 ),
			array( 'min' => 50,  'bonus' => 0.02 ),
			array( 'min' => 100, 'bonus' => 0.04 ),
			array( 'min' => 250, 'bonus' => 0.06 ),
		),
		// wp_xg_diamonds – iDex-Basis (Mock-Matrix).
		'diamond_base' => array(
			'color'   => array(
				'D' => 1.00, 'E' => 0.95, 'F' => 0.90, 'G' => 0.82,
				'H' => 0.74, 'I' => 0.66, 'J' => 0.58, 'K' => 0.48,
			),
			'clarity' => array(
				'FL' => 1.00, 'IF' => 0.95, 'VVS1' => 0.90, 'VVS2' => 0.86,
				'VS1' => 0.80, 'VS2' => 0.74, 'SI1' => 0.64, 'SI2' => 0.54,
				'I1' => 0.40, 'I2' => 0.30, 'I3' => 0.20,
			),
			'cut'     => array(
				'Excellent' => 1.00, 'Very Good' => 0.95, 'Good' => 0.88,
				'Fair' => 0.78, 'Poor' => 0.65,
			),
			'fluor'   => array(
				'None' => 1.00, 'Faint' => 0.98, 'Medium' => 0.94, 'Strong' => 0.88,
			),
			// Anchor (€/ct, 1ct D/IF/Excellent) – aus IDEX-Cron-Cache, sonst Mock.
			'anchor'  => function_exists( 'ekinese_idex_anchor' ) ? ekinese_idex_anchor() : 9000,
		),
		// Zertifizierungs-Labore für Diamanten & Edelsteine.
		'labs' => array( 'GIA', 'IGI', 'HRD', 'Geen certificaat' ),
		// wp_xg_gemstones – Basis €/ct.
		'gem_base' => array(
			'robijn'  => array( 'label' => 'Robijn',   'anchor' => 3500 ),
			'saffier' => array( 'label' => 'Saffier', 'anchor' => 2200 ),
			'smaragd' => array( 'label' => 'Smaragd',          'anchor' => 2800 ),
		),
		// wp_xg_watches – Marktpreis € (Mock; später eigene DB, ~25 Marken).
		'watches' => array(
			'rolex'   => array( 'label' => 'Rolex',   'models' => array( 'Submariner' => 11000, 'Datejust' => 7500, 'GMT-Master II' => 14000 ) ),
			'omega'   => array( 'label' => 'Omega',   'models' => array( 'Speedmaster' => 5500, 'Seamaster' => 4200 ) ),
			'patek'   => array( 'label' => 'Patek Philippe', 'models' => array( 'Nautilus' => 38000, 'Calatrava' => 18000 ) ),
			'cartier' => array( 'label' => 'Cartier', 'models' => array( 'Santos' => 6500, 'Tank' => 4800 ) ),
		),
		'watch_conditions' => array(
			'nieuw'     => array( 'label' => 'Nieuwstaat',           'factor' => 1.00 ),
			'zeer_goed' => array( 'label' => 'Zeer goed',            'factor' => 0.90 ),
			'goed'      => array( 'label' => 'Goed',                 'factor' => 0.78 ),
			'voldoende' => array( 'label' => 'Voldoende',        'factor' => 0.65 ),
			'service'   => array( 'label' => 'Revisie nodig','factor' => 0.50 ),
		),
		'watch_extras' => array(
			'box'    => array( 'label' => 'Originele doos',        'bonus' => 0.03 ),
			'papers' => array( 'label' => 'Certificaat/papieren', 'bonus' => 0.05 ),
		),
		// Uhren-Detailfelder (nicht preisrelevant im Mock, erleichtern die Suche/Bewertung).
		'watch_metals'    => array( 'Staal', 'Geelgoud', 'Witgoud', 'Roségoud', 'Platina', 'Staal/Goud', 'Titanium' ),
		'watch_bracelets' => array( 'Stalen band', 'Leren band', 'Rubber', 'Gouden band', 'NATO/Textiel' ),
		'watch_dials'     => array( 'Zwart', 'Wit', 'Zilver', 'Blauw', 'Groen', 'Champagne', 'Grijs', 'Overige' ),
		/*
		 * wp_xg_charity – Bereiche mit konkreten Empfängern + Verteilungsgewicht.
		 * Die Empfänger sind später pro Stadt aus einer eigenen DB befüllbar
		 * (Schulen, Kindergärten, Frauenhäuser …). 'weight' = Anteil am Topf.
		 */
		'charity_projects' => array(
			array(
				'id'         => 'social',
				'label'      => 'Maatschappelijk werk',
				'weight'     => 0.25,
				'recipients' => array( 'Stichting Buurtwerk Amsterdam', 'Sociaal Steunpunt Rotterdam', 'Voedselbank Den Haag' ),
			),
			array(
				'id'         => 'kindergarten',
				'label'      => 'Kinderdagverblijven',
				'weight'     => 0.20,
				'recipients' => array( 'Kinderopvang De Zonnebloem', 'KDV Het Speelkwartier', 'Peuterspeelzaal Pippeloentje' ),
			),
			array(
				'id'         => 'shelter',
				'label'      => 'Vrouwenopvang',
				'weight'     => 0.25,
				'recipients' => array( 'Blijf Groep Amsterdam', 'Vrouwenopvang Rotterdam', 'Veilig Thuis Utrecht' ),
			),
			array(
				'id'         => 'sport',
				'label'      => 'Sportcentra',
				'weight'     => 0.15,
				'recipients' => array( 'Sportclub Jeugd Eindhoven', 'Buurtsport Tilburg', 'Zwemvereniging De Dolfijn' ),
			),
			array(
				'id'         => 'school',
				'label'      => 'Scholen',
				'weight'     => 0.15,
				'recipients' => array( 'Basisschool De Regenboog', 'OBS Het Kompas', 'Vrije School Zutphen', 'Montessori Amsterdam' ),
			),
		),
		'currency' => 'EUR',
		// REST-Ziel für den Terminplaner (Calculator-Checkout → Afspraak).
		'rest_appointment' => esc_url_raw( rest_url( 'ekinese/v1/appointment' ) ),
		// REST-Ziel für "Bewaar mijn berekening" (#9).
		'rest_calc_save'   => esc_url_raw( rest_url( 'ekinese/v1/calc-save' ) ),
	);

	// Charity-Empfänger aus echten Projekten (CPT), falls vorhanden – sonst
	// bleiben die obigen Default-Empfänger.
	if ( function_exists( 'ekinese_charity_projects_for_calc' ) ) {
		$projects = ekinese_charity_projects_for_calc();
		if ( ! empty( $projects ) ) {
			$data['charity_projects'] = $projects;
		}
	}

	return $data;
}
