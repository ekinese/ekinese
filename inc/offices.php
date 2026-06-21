<?php
/**
 * XGOUD Offices (Kantoren) – Custom Post Type + Meta.
 *
 * Datenbasis für:
 *   - Übersichtsseite "Alle kantoren" (interaktive Karte + Liste)
 *   - Einzelseite je Kantoor (Details, Google-Maps, Routenplaner)
 *   - Zonen-/Mitarbeiter-Zuteilung (Terminplaner – folgt)
 *
 * Eindhoven = Hauptsitz (is_hq) und wird überall hervorgehoben.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT "xg_office" registrieren.
 */
function ekinese_register_office_cpt() {
	register_post_type(
		'xg_office',
		array(
			'labels'        => array(
				'name'          => __( 'Kantoren', 'ekinese' ),
				'singular_name' => __( 'Kantoor', 'ekinese' ),
				'add_new_item'  => __( 'Nieuw kantoor', 'ekinese' ),
				'edit_item'     => __( 'Kantoor bewerken', 'ekinese' ),
				'menu_name'     => __( 'Kantoren', 'ekinese' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-location',
			'supports'      => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'       => array( 'slug' => 'kantoren' ),
		)
	);
}
add_action( 'init', 'ekinese_register_office_cpt' );

/**
 * Meta-Felder je Kantoor (REST-fähig, im Editor nutzbar).
 */
function ekinese_register_office_meta() {
	$fields = array(
		'street'    => 'string',
		'postcode'  => 'string',
		'city'      => 'string',
		'lat'       => 'string',
		'lng'       => 'string',
		'phone'     => 'string',
		'email'     => 'string',
		'hours'     => 'string', // JSON: { "ma":"09:00-17:30", ... }
		'is_hq'     => 'boolean',
		'zone'      => 'string',  // Zonen-ID für Mitarbeiter-Zuteilung
		'open_days' => 'string',  // CSV "ma,di,wo,do,vr" (vom Terminplaner überschrieben)
	);

	foreach ( $fields as $key => $type ) {
		register_post_meta(
			'xg_office',
			$key,
			array(
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}
}
add_action( 'init', 'ekinese_register_office_meta' );

/**
 * Alle veröffentlichten Kantoren als flaches Array (für die Karte/JS).
 *
 * @return array
 */
function ekinese_get_offices() {
	$query = new WP_Query(
		array(
			'post_type'      => 'xg_office',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$offices = array();
	foreach ( $query->posts as $post ) {
		$id     = $post->ID;
		$hours  = get_post_meta( $id, 'hours', true );
		$offices[] = array(
			'id'       => $id,
			'name'     => get_the_title( $id ),
			'slug'     => $post->post_name,
			'url'      => get_permalink( $id ),
			'street'   => get_post_meta( $id, 'street', true ),
			'postcode' => get_post_meta( $id, 'postcode', true ),
			'city'     => get_post_meta( $id, 'city', true ),
			'lat'      => (float) get_post_meta( $id, 'lat', true ),
			'lng'      => (float) get_post_meta( $id, 'lng', true ),
			'phone'    => get_post_meta( $id, 'phone', true ),
			'email'    => get_post_meta( $id, 'email', true ),
			'hours'    => $hours ? json_decode( $hours, true ) : array(),
			'is_hq'    => (bool) get_post_meta( $id, 'is_hq', true ),
			'zone'     => get_post_meta( $id, 'zone', true ),
			// Offene Tage = aus Zonen-Medewerkers berechnet (Terminplaner).
			'open_days' => function_exists( 'ekinese_office_open_days' ) ? ekinese_office_open_days( $id ) : array(),
		);
	}
	wp_reset_postdata();

	return $offices;
}

/* =====================================================================
   IMPORT  (data/locations.csv → xg_office, upsert per slug)
===================================================================== */
function ekinese_import_locations() {
	$file = get_theme_file_path( 'data/locations.csv' );
	if ( ! file_exists( $file ) || ! ( $fp = fopen( $file, 'r' ) ) ) { // phpcs:ignore
		return 0;
	}
	$days_map = array(
		'Maandag Hours' => 'ma', 'Dinsdag Hours' => 'di', 'Woensdag Hours' => 'wo',
		'Donderdag Hours' => 'do', 'Vrijdag Hours' => 'vr', 'Zaterdag Hours' => 'za', 'Zondag Hours' => 'zo',
	);
	$header = null;
	$count  = 0;
	while ( ( $row = fgetcsv( $fp, 0, ',' ) ) !== false ) {
		if ( null === $header ) {
			$header    = $row;
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] ); // BOM weg
			continue;
		}
		$r = @array_combine( $header, $row );
		if ( ! $r || empty( $r['Slug'] ) ) {
			continue;
		}
		$slug = sanitize_title( $r['Slug'] );
		$city = sanitize_text_field( $r['Title'] );

		$existing = get_page_by_path( $slug, OBJECT, 'xg_office' );
		$postarr  = array(
			'post_type'    => 'xg_office',
			'post_status'  => 'publish',
			'post_title'   => $city,
			'post_name'    => $slug,
			'post_content' => wp_kses_post( $r['Description'] ?? '' ),
		);
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
		}
		$id = wp_insert_post( $postarr );
		if ( is_wp_error( $id ) ) {
			continue;
		}

		// Adres splitsen: "Straat 12, 1234 AB Stad".
		$addr   = trim( (string) ( $r['Address'] ?? '' ) );
		$street = $addr; $postcode = '';
		if ( strpos( $addr, ',' ) !== false ) {
			list( $street, $rest ) = array_map( 'trim', explode( ',', $addr, 2 ) );
			if ( preg_match( '/(\d{4}\s?[A-Z]{2})/', $rest, $m ) ) {
				$postcode = $m[1];
			}
		}
		update_post_meta( $id, 'street', $street );
		update_post_meta( $id, 'postcode', $postcode );
		update_post_meta( $id, 'city', $city );
		update_post_meta( $id, 'lat', sanitize_text_field( $r['Latitude'] ?? '' ) );
		update_post_meta( $id, 'lng', sanitize_text_field( $r['Longitude'] ?? '' ) );
		update_post_meta( $id, 'phone', sanitize_text_field( $r['Phone'] ?? '' ) );
		update_post_meta( $id, 'email', sanitize_email( $r['Email'] ?? '' ) );
		update_post_meta( $id, 'is_hq', ( strtolower( $city ) === 'eindhoven' ) ? '1' : '' );

		$hours = array();
		foreach ( $days_map as $col => $key ) {
			$v = trim( (string) ( $r[ $col ] ?? '' ) );
			if ( $v && stripos( $v, 'off' ) === false && stripos( $v, 'gesloten' ) === false ) {
				$hours[ $key ] = str_replace( array( ' - ', ' ' ), array( '-', '' ), $v );
			}
		}
		update_post_meta( $id, 'hours', wp_json_encode( $hours ) );
		$count++;
	}
	fclose( $fp );
	return $count;
}

function ekinese_locations_import_menu() {
	add_submenu_page( 'edit.php?post_type=xg_office', __( 'Importeren', 'ekinese' ), __( 'Importeren', 'ekinese' ), 'manage_options', 'xg-locations-import', 'ekinese_locations_import_page' );
}
add_action( 'admin_menu', 'ekinese_locations_import_menu' );

function ekinese_locations_import_page() {
	if ( isset( $_POST['xg_loc_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_loc_nonce'] ), 'xg_loc' ) ) {
		$n = ekinese_import_locations();
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( '%d locaties geïmporteerd/bijgewerkt.', $n ) ) . '</p></div>';
	}
	echo '<div class="wrap"><h1>Locaties importeren</h1><p>Bron: <code>data/locations.csv</code>. Maakt/actualiseert kantoorpagina\'s met SEO-tekst, openingstijden en kaart.</p><form method="post">';
	wp_nonce_field( 'xg_loc', 'xg_loc_nonce' );
	submit_button( __( 'Nu importeren', 'ekinese' ) );
	echo '</form></div>';
}

/* =====================================================================
   SERVER-GERENDERDE BLOKKEN (SEO) – single + overzicht
===================================================================== */
function ekinese_register_office_blocks() {
	register_block_type( 'ekinese/office-detail', array( 'render_callback' => 'ekinese_render_office_detail' ) );
	register_block_type( 'ekinese/offices-overview', array( 'render_callback' => 'ekinese_render_offices_overview' ) );
}
add_action( 'init', 'ekinese_register_office_blocks' );

function ekinese_render_office_detail() {
	if ( ! is_singular( 'xg_office' ) ) {
		return '';
	}
	$id   = get_the_ID();
	$city = get_post_meta( $id, 'city', true ) ?: get_the_title( $id );
	$slug = get_post_field( 'post_name', $id );
	ob_start();
	?>
	<div class="xg-office-single" data-xg-office="<?php echo esc_attr( $slug ); ?>">
		<div class="xg-office-hero">
			<h1>Goud verkopen in <?php echo esc_html( $city ); ?></h1>
			<?php if ( get_post_meta( $id, 'is_hq', true ) ) : ?><span class="xg-hq-tag">Hoofdkantoor</span><?php endif; ?>
		</div>
		<div class="xg-office-grid">
			<div class="xg-office-info"></div>
			<div class="xg-office-mapcol">
				<div class="xg-office-map"></div>
				<iframe class="xg-office-embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
			</div>
		</div>
		<?php
		$content = get_post_field( 'post_content', $id );
		if ( trim( wp_strip_all_tags( $content ) ) ) {
			echo '<div class="xg-legal" style="padding:40px 0;max-width:none">' . wp_kses_post( apply_filters( 'the_content', $content ) ) . '</div>';
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}

function ekinese_render_offices_overview() {
	ob_start();
	?>
	<section class="xg-offices">
		<div class="xg-offices-intro">
			<h1>Onze kantoren</h1>
			<p>Bezoek een van onze vestigingen in Nederland of laat onze expert bij u langskomen.</p>
		</div>
		<div class="xg-hq-banner">
			<span class="xg-hq-badge">Hoofdkantoor</span>
			<div><strong>XGOUD Eindhoven</strong> &nbsp; <span>6 dagen per week geopend.</span></div>
			<span class="xg-hq-perk">Reisbonus bij bezoek aan Eindhoven</span>
		</div>
		<div class="xg-offices-bar">
			<div class="xg-offices-search">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
				<input type="text" placeholder="Zoek op stad of postcode…">
			</div>
			<div class="xg-offices-count"></div>
		</div>
		<div class="xg-offices-layout">
			<div id="xg-offices-map"></div>
			<div class="xg-offices-list"></div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Offices-Assets + Daten laden (nur wo gebraucht: Archiv, Einzelseite,
 * oder wenn das Offices-Pattern auf einer Seite steckt).
 */
function ekinese_enqueue_offices_assets() {
	$needs = is_post_type_archive( 'xg_office' )
		|| is_singular( 'xg_office' )
		|| ( is_singular() && ( has_block( 'ekinese/offices-overview' ) || has_block( 'ekinese/office-detail' ) ) );

	/**
	 * Erlaubt das Erzwingen des Ladens (z.B. Startseiten-Kartenblock).
	 *
	 * @param bool $needs
	 */
	$needs = apply_filters( 'ekinese_load_offices_assets', $needs );

	if ( ! $needs ) {
		return;
	}

	// Leaflet (leichtgewichtige Karte, kein API-Key) via CDN.
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

	$css = get_theme_file_path( 'assets/css/offices.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-offices', get_theme_file_uri( 'assets/css/offices.css' ), array( 'leaflet' ), (string) filemtime( $css ) );
	}

	$js = get_theme_file_path( 'assets/js/offices.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-offices', get_theme_file_uri( 'assets/js/offices.js' ), array( 'leaflet' ), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-offices', 'XG_OFFICES', ekinese_get_offices() );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_offices_assets' );
