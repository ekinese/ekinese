<?php
/**
 * XGOUD Charity-System.
 *
 * - CPT xg_charity_project : Projekte je Stadt (Kategorie, Stadt, Website,
 *   Auszahlungs-Log). Im Backend editierbar.
 * - Live-Summe: Charity-Beträge aus Afspraken sammeln sich pro Projekt an,
 *   bis sie (zum Quartalsende) ausgezahlt werden.
 * - Frontend: Auflistung wer/wieviel/wann + Live-Ticker-Summe.
 * - XGOUD-Zertifikat für Kunden (Bestätigung der Unterstützung).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Charity-Kategorien (Schlüssel = Speicherung, Wert = Anzeige NL). */
function ekinese_charity_categories() {
	return array(
		'social'       => 'Maatschappelijk werk',
		'kindergarten' => 'Kinderdagverblijven',
		'shelter'      => 'Vrouwenopvang',
		'sport'        => 'Sportcentra',
		'school'       => 'Scholen',
	);
}

/* =====================================================================
   CPT + Meta
===================================================================== */
function ekinese_register_charity() {
	register_post_type(
		'xg_charity_project',
		array(
			'labels'       => array(
				'name'          => __( 'Goede doelen', 'ekinese' ),
				'singular_name' => __( 'Goed doel', 'ekinese' ),
				'add_new_item'  => __( 'Nieuw project', 'ekinese' ),
				'menu_name'     => __( 'Goede doelen', 'ekinese' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-heart',
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'      => array( 'slug' => 'goede-doelen' ),
		)
	);

	$fields = array(
		'category' => 'string',
		'city'     => 'string',
		'website'  => 'string',
		'payouts'  => 'string', // JSON [ {date, amount, period} ]
	);
	foreach ( $fields as $k => $t ) {
		register_post_meta( 'xg_charity_project', $k, array( 'type' => $t, 'single' => true, 'show_in_rest' => true ) );
	}
}
add_action( 'init', 'ekinese_register_charity' );

/* =====================================================================
   BERECHNUNGEN
===================================================================== */

/**
 * Bisher ausgezahlte Summe eines Projekts (aus dem Payout-Log).
 */
function ekinese_charity_paid( $project_id ) {
	$log = json_decode( (string) get_post_meta( $project_id, 'payouts', true ), true );
	$sum = 0;
	if ( is_array( $log ) ) {
		foreach ( $log as $p ) {
			$sum += (float) ( $p['amount'] ?? 0 );
		}
	}
	return $sum;
}

/**
 * Aufgelaufener (noch nicht ausgezahlter) Betrag eines Projekts.
 * = Summe charity_total der zugeordneten Afspraken (bevestigd/afgerond)
 *   − bereits ausgezahlt.
 */
function ekinese_charity_accrued( $project_id ) {
	$title = get_the_title( $project_id );
	$appts = get_posts(
		array(
			'post_type'      => 'xg_appointment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array( 'key' => 'charity_recipient', 'value' => $title ),
			),
			'fields'         => 'ids',
		)
	);
	$sum = 0;
	foreach ( $appts as $id ) {
		if ( in_array( get_post_meta( $id, 'status', true ), array( 'confirmed', 'completed' ), true ) ) {
			$sum += (float) get_post_meta( $id, 'charity_total', true );
		}
	}
	// Extra bijdragen (bv. afgerekende veilingen) — andere modules haken hierop in.
	$sum += (float) apply_filters( 'ekinese_charity_accrued_extra', 0, $project_id, $title );
	return max( 0, $sum - ekinese_charity_paid( $project_id ) );
}

/**
 * Charity-Gesamtsumme eines Zeitraums (für den Live-Ticker).
 * Standard: laufender Monat, abgeschlossene Afspraken.
 *
 * @param string $since Y-m-d (Default: Monatsanfang)
 * @return float
 */
function ekinese_charity_total_since( $since = '' ) {
	if ( ! $since ) {
		$since = gmdate( 'Y-m-01' );
	}
	$appts = get_posts(
		array(
			'post_type'      => 'xg_appointment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array( 'key' => 'status', 'value' => array( 'confirmed', 'completed' ), 'compare' => 'IN' ),
			),
			'date_query'     => array( array( 'after' => $since ) ),
			'fields'         => 'ids',
		)
	);
	$sum = 0;
	foreach ( $appts as $id ) {
		$sum += (float) get_post_meta( $id, 'charity_total', true );
	}
	return $sum;
}

/**
 * Alle Projekte mit aufgelaufenem Betrag, nach Kategorie gruppiert.
 * Für Frontend-Auflistung + Calculator-Empfängerliste.
 */
function ekinese_charity_projects_grouped() {
	$cats = ekinese_charity_categories();
	$out  = array();
	foreach ( $cats as $key => $label ) {
		$out[ $key ] = array( 'id' => $key, 'label' => $label, 'projects' => array() );
	}
	$projects = get_posts( array( 'post_type' => 'xg_charity_project', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
	foreach ( $projects as $p ) {
		$cat = get_post_meta( $p->ID, 'category', true );
		if ( ! isset( $out[ $cat ] ) ) {
			continue;
		}
		$out[ $cat ]['projects'][] = array(
			'id'      => $p->ID,
			'name'    => $p->post_title,
			'city'    => get_post_meta( $p->ID, 'city', true ),
			'website' => get_post_meta( $p->ID, 'website', true ),
			'accrued' => round( ekinese_charity_accrued( $p->ID ), 2 ),
			'paid'    => round( ekinese_charity_paid( $p->ID ), 2 ),
			'payouts' => json_decode( (string) get_post_meta( $p->ID, 'payouts', true ), true ) ?: array(),
		);
	}
	return array_values( $out );
}

/**
 * Empfängerliste für den Calculator (Kategorie → Projektnamen).
 * Greift, wenn Projekte existieren – sonst nutzt setup.php seine Defaults.
 */
function ekinese_charity_projects_for_calc() {
	$grouped = ekinese_charity_projects_grouped();
	$out = array();
	foreach ( $grouped as $g ) {
		if ( empty( $g['projects'] ) ) {
			continue;
		}
		$recipients = array();
		foreach ( $g['projects'] as $pr ) {
			$recipients[] = $pr['city'] ? $pr['name'] . ' (' . $pr['city'] . ')' : $pr['name'];
		}
		$out[] = array( 'id' => $g['id'], 'label' => $g['label'], 'recipients' => $recipients );
	}
	return $out;
}

/* =====================================================================
   ZERTIFIKAT
===================================================================== */
/**
 * XGOUD-Zertifikat (HTML) für die Charity-Unterstützung eines Kunden.
 *
 * @param int $appointment_id
 * @return string HTML
 */
function ekinese_render_certificate( $appointment_id ) {
	$first  = get_post_meta( $appointment_id, 'first', true );
	$last   = get_post_meta( $appointment_id, 'last', true );
	$amount = (float) get_post_meta( $appointment_id, 'charity_total', true );
	$proj   = get_post_meta( $appointment_id, 'charity_recipient', true );
	$date   = get_post_meta( $appointment_id, 'date', true ) ?: gmdate( 'Y-m-d' );
	$euro   = '€ ' . number_format( $amount, 2, ',', '.' );

	ob_start(); ?>
	<div class="xg-certificate">
		<div class="xg-cert-brand">X<span>GOUD</span></div>
		<div class="xg-cert-kicker">Certificaat van steun</div>
		<h2>Hartelijk dank, <?php echo esc_html( trim( $first . ' ' . $last ) ); ?></h2>
		<p>Met uw verkoop bij XGOUD heeft u <strong><?php echo esc_html( $euro ); ?></strong> bijgedragen aan</p>
		<div class="xg-cert-project"><?php echo esc_html( $proj ); ?></div>
		<p class="xg-cert-sub">U heeft hiermee iets goeds gedaan voor een goed doel in uw omgeving.</p>
		<div class="xg-cert-foot"><span><?php echo esc_html( $date ); ?></span><span>XGOUD &middot; xgoud.nl</span></div>
	</div>
	<?php
	return ob_get_clean();
}

/** Shortcode [xg_certificate id="123"]. */
function ekinese_certificate_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts );
	return $a['id'] ? ekinese_render_certificate( (int) $a['id'] ) : '';
}
add_shortcode( 'xg_certificate', 'ekinese_certificate_shortcode' );

/* =====================================================================
   FRONTEND-ASSETS + DATEN (Ticker + Auflistung)
===================================================================== */
function ekinese_enqueue_charity_assets() {
	$css = get_theme_file_path( 'assets/css/charity.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-charity', get_theme_file_uri( 'assets/css/charity.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/charity.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-charity', get_theme_file_uri( 'assets/js/charity.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script(
			'ekinese-charity',
			'XG_CHARITY',
			array(
				'month_total' => round( ekinese_charity_total_since(), 2 ),
				'currency'    => 'EUR',
				'categories'  => ekinese_charity_projects_grouped(),
				'rest_total'  => esc_url_raw( rest_url( 'ekinese/v1/charity-total' ) ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_charity_assets' );

/** REST: aktuelle Monatssumme (für Live-Aktualisierung des Tickers). */
function ekinese_register_charity_rest() {
	register_rest_route(
		'ekinese/v1',
		'/charity-total',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				return array( 'month_total' => round( ekinese_charity_total_since(), 2 ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'ekinese_register_charity_rest' );

/* =====================================================================
   ADMIN: Projekt-Metabox + Auszahlung + Spalten
===================================================================== */
function ekinese_charity_metabox() {
	add_meta_box( 'xg_charity', __( 'Project-gegevens', 'ekinese' ), 'ekinese_charity_metabox_html', 'xg_charity_project', 'side', 'high' );
	add_meta_box( 'xg_charity_pay', __( 'Uitbetalingen', 'ekinese' ), 'ekinese_charity_payout_html', 'xg_charity_project', 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'ekinese_charity_metabox' );

function ekinese_charity_metabox_html( $post ) {
	wp_nonce_field( 'xg_charity_save', 'xg_charity_nonce' );
	$cat = get_post_meta( $post->ID, 'category', true );
	$city = esc_attr( get_post_meta( $post->ID, 'city', true ) );
	$web = esc_attr( get_post_meta( $post->ID, 'website', true ) );
	echo '<p><label><strong>Categorie</strong><br><select name="xg_cat" style="width:100%">';
	foreach ( ekinese_charity_categories() as $k => $l ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $cat, $k, false ), esc_html( $l ) );
	}
	echo '</select></label></p>';
	echo '<p><label><strong>Stad</strong><br><input type="text" name="xg_city" value="' . $city . '" style="width:100%"></label></p>';
	echo '<p><label><strong>Website</strong><br><input type="text" name="xg_web" value="' . $web . '" style="width:100%"></label></p>';
	echo '<hr><p><strong>Opgebouwd (open):</strong><br><span style="font-size:20px;color:#c8a24a;font-weight:700">€ ' . esc_html( number_format( ekinese_charity_accrued( $post->ID ), 2, ',', '.' ) ) . '</span></p>';
	echo '<p class="description">Som uit afspraken (bevestigd/afgerond) minus uitbetaald.</p>';
}

function ekinese_charity_payout_html( $post ) {
	$log = json_decode( (string) get_post_meta( $post->ID, 'payouts', true ), true ) ?: array();
	echo '<table class="widefat"><thead><tr><th>Datum</th><th>Periode</th><th>Bedrag €</th></tr></thead><tbody>';
	foreach ( $log as $p ) {
		printf( '<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( $p['date'] ?? '' ), esc_html( $p['period'] ?? '' ), esc_html( number_format( (float) ( $p['amount'] ?? 0 ), 2, ',', '.' ) ) );
	}
	echo '</tbody></table>';
	echo '<p><strong>' . esc_html__( 'Nieuwe uitbetaling toevoegen', 'ekinese' ) . '</strong></p>';
	echo '<p>Datum <input type="date" name="xg_pay_date"> &nbsp; Periode <input type="text" name="xg_pay_period" placeholder="Q2 2026"> &nbsp; Bedrag € <input type="number" step="0.01" name="xg_pay_amount"></p>';
}

function ekinese_charity_save( $post_id ) {
	if ( ! isset( $_POST['xg_charity_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_charity_nonce'] ), 'xg_charity_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	update_post_meta( $post_id, 'category', sanitize_key( $_POST['xg_cat'] ?? '' ) );
	update_post_meta( $post_id, 'city', sanitize_text_field( wp_unslash( $_POST['xg_city'] ?? '' ) ) );
	update_post_meta( $post_id, 'website', sanitize_text_field( wp_unslash( $_POST['xg_web'] ?? '' ) ) );

	// Neue Auszahlung anhängen.
	$amount = (float) ( $_POST['xg_pay_amount'] ?? 0 );
	if ( $amount > 0 ) {
		$log = json_decode( (string) get_post_meta( $post_id, 'payouts', true ), true ) ?: array();
		$log[] = array(
			'date'   => sanitize_text_field( wp_unslash( $_POST['xg_pay_date'] ?? gmdate( 'Y-m-d' ) ) ),
			'period' => sanitize_text_field( wp_unslash( $_POST['xg_pay_period'] ?? '' ) ),
			'amount' => $amount,
		);
		update_post_meta( $post_id, 'payouts', wp_json_encode( $log ) );
	}
}
add_action( 'save_post_xg_charity_project', 'ekinese_charity_save' );

function ekinese_charity_columns( $cols ) {
	$cols['xg_cat']     = __( 'Categorie', 'ekinese' );
	$cols['xg_city']    = __( 'Stad', 'ekinese' );
	$cols['xg_accrued'] = __( 'Opgebouwd', 'ekinese' );
	return $cols;
}
add_filter( 'manage_xg_charity_project_posts_columns', 'ekinese_charity_columns' );

function ekinese_charity_column( $col, $id ) {
	$cats = ekinese_charity_categories();
	if ( 'xg_cat' === $col ) {
		echo esc_html( $cats[ get_post_meta( $id, 'category', true ) ] ?? '—' );
	} elseif ( 'xg_city' === $col ) {
		echo esc_html( get_post_meta( $id, 'city', true ) ?: '—' );
	} elseif ( 'xg_accrued' === $col ) {
		echo '€ ' . esc_html( number_format( ekinese_charity_accrued( $id ), 2, ',', '.' ) );
	}
}
add_action( 'manage_xg_charity_project_posts_custom_column', 'ekinese_charity_column', 10, 2 );

/* =====================================================================
   CHARITY-KAART  "Waar XGOUD helpt"  (Leaflet) + animatie-teller
===================================================================== */
/** Bekende NL/BE-stadcoördinaten (fallback wanneer er geen kantoor is). */
function ekinese_city_coords() {
	return array(
		'amsterdam' => array( 52.3676, 4.9041 ), 'rotterdam' => array( 51.9244, 4.4777 ),
		'den haag' => array( 52.0705, 4.3007 ), 'utrecht' => array( 52.0907, 5.1214 ),
		'eindhoven' => array( 51.4416, 5.4697 ), 'groningen' => array( 53.2194, 6.5665 ),
		'tilburg' => array( 51.5555, 5.0913 ), 'almere' => array( 52.3508, 5.2647 ),
		'breda' => array( 51.5719, 4.7683 ), 'nijmegen' => array( 51.8126, 5.8372 ),
		'enschede' => array( 52.2215, 6.8937 ), 'haarlem' => array( 52.3874, 4.6462 ),
		'arnhem' => array( 51.9851, 5.8987 ), 'zwolle' => array( 52.5168, 6.0830 ),
		'amersfoort' => array( 52.1561, 5.3878 ), 'maastricht' => array( 50.8514, 5.6910 ),
		'leiden' => array( 52.1601, 4.4970 ), 'dordrecht' => array( 51.8133, 4.6901 ),
		'antwerpen' => array( 51.2194, 4.4025 ), 'brussel' => array( 50.8503, 4.3517 ),
		'gent' => array( 51.0543, 3.7174 ), 'brugge' => array( 51.2093, 3.2247 ),
	);
}

/** Projecten met coördinaten voor de kaart. */
function ekinese_charity_map_points() {
	$offices = function_exists( 'ekinese_get_offices' ) ? ekinese_get_offices() : array();
	$by_city = array();
	foreach ( $offices as $o ) {
		if ( ! empty( $o['city'] ) && $o['lat'] && $o['lng'] ) {
			$by_city[ strtolower( $o['city'] ) ] = array( $o['lat'], $o['lng'] );
		}
	}
	$coords = ekinese_city_coords();
	$points = array();
	foreach ( ekinese_charity_projects_grouped() as $cat ) {
		foreach ( $cat['projects'] as $pr ) {
			$city = strtolower( trim( (string) ( $pr['city'] ?? '' ) ) );
			$ll   = $by_city[ $city ] ?? ( $coords[ $city ] ?? null );
			if ( ! $ll ) {
				continue;
			}
			$points[] = array(
				'name'  => $pr['name'],
				'city'  => $pr['city'],
				'cat'   => $cat['label'] ?? '',
				'lat'   => $ll[0],
				'lng'   => $ll[1],
				'total' => function_exists( 'ekinese_charity_total_since' ) ? 0 : 0,
			);
		}
	}
	return $points;
}

function ekinese_register_charity_map() {
	register_block_type( 'ekinese/charity-map', array( 'render_callback' => 'ekinese_render_charity_map' ) );
	register_block_type( 'ekinese/charity-payouts', array( 'render_callback' => 'ekinese_render_charity_payouts' ) );
	register_block_type( 'ekinese/charity-supporters', array( 'render_callback' => 'ekinese_render_charity_supporters' ) );
}
add_action( 'init', 'ekinese_register_charity_map' );

/** Simpele EUR-weergave (NL-notatie). */
function ekinese_eur( $v ) {
	return '€ ' . number_format( (float) $v, 2, ',', '.' );
}

/**
 * Blok ekinese/charity-payouts: toont de eerstvolgende uitkering + per project
 * hoeveel klaarstaat ("accrued") en hoeveel al is uitgekeerd. Datum komt uit de
 * optie xg_charity_next_payout (in te stellen onder Goede doelen → Volgende uitkering).
 */
function ekinese_render_charity_payouts() {
	$next    = get_option( 'xg_charity_next_payout', '' );
	$grouped = ekinese_charity_projects_grouped();
	ob_start();
	echo '<section><div class="xg-container">';
	echo '<div class="xg-eyebrow">GOEDE DOELEN</div><h2 class="xg-section-title">Volgende uitkering &amp; verdeling</h2>';
	if ( $next && strtotime( $next ) ) {
		echo '<p class="xg-charity-next">Eerstvolgende uitkering: <strong>' . esc_html( date_i18n( 'j F Y', strtotime( $next ) ) ) . '</strong></p>';
	} else {
		echo '<p class="xg-charity-next">De datum van de eerstvolgende uitkering wordt binnenkort bekendgemaakt.</p>';
	}
	$any = false;
	foreach ( $grouped as $g ) {
		if ( empty( $g['projects'] ) ) {
			continue;
		}
		$any = true;
		echo '<h3 class="xg-charity-cat">' . esc_html( $g['label'] ) . '</h3>';
		echo '<div class="xg-grid-3">';
		foreach ( $g['projects'] as $pr ) {
			echo '<div class="xg-c-card">';
			echo '<h3>' . esc_html( $pr['name'] ) . '</h3>';
			if ( ! empty( $pr['city'] ) ) {
				echo '<p class="xg-charity-city">' . esc_html( $pr['city'] ) . '</p>';
			}
			echo '<p class="xg-charity-accrued">Staat klaar: <strong>' . esc_html( ekinese_eur( $pr['accrued'] ) ) . '</strong></p>';
			echo '<p class="xg-charity-paid">Al uitgekeerd: ' . esc_html( ekinese_eur( $pr['paid'] ) ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}
	if ( ! $any ) {
		echo '<p>Er zijn nog geen projecten gepubliceerd.</p>';
	}
	echo '</div></section>';
	return ob_get_clean();
}

/**
 * Blok ekinese/charity-supporters: dankt de klanten en toont het totaal dat al
 * is uitgekeerd + de meest recente uitkeringen.
 */
function ekinese_render_charity_supporters() {
	$grouped    = ekinese_charity_projects_grouped();
	$total_paid = 0;
	$payouts    = array();
	foreach ( $grouped as $g ) {
		foreach ( $g['projects'] as $pr ) {
			$total_paid += (float) $pr['paid'];
			foreach ( (array) $pr['payouts'] as $po ) {
				if ( ! empty( $po['date'] ) ) {
					$payouts[] = array(
						'date'    => $po['date'],
						'amount'  => (float) ( $po['amount'] ?? 0 ),
						'project' => $pr['name'],
						'city'    => $pr['city'] ?? '',
					);
				}
			}
		}
	}
	usort( $payouts, function ( $a, $b ) { return strtotime( $b['date'] ) <=> strtotime( $a['date'] ); } );
	$recent = array_slice( $payouts, 0, 12 );

	ob_start();
	echo '<section><div class="xg-container">';
	echo '<div class="xg-eyebrow">SAMEN MOGELIJK GEMAAKT</div><h2 class="xg-section-title">Dankzij onze klanten</h2>';
	echo '<p class="xg-intro">Iedere verkoop draagt bij. Samen hebben wij al uitgekeerd aan goede doelen:</p>';
	echo '<div class="xg-charity-counter" data-to="' . esc_attr( round( $total_paid, 2 ) ) . '">' . esc_html( ekinese_eur( $total_paid ) ) . '</div>';
	if ( $recent ) {
		echo '<div class="xg-supporters-list">';
		foreach ( $recent as $po ) {
			echo '<div class="xg-supporter-row"><span class="xg-supporter-date">' . esc_html( date_i18n( 'j M Y', strtotime( $po['date'] ) ) ) . '</span>';
			echo '<span class="xg-supporter-proj">' . esc_html( $po['project'] ) . ( $po['city'] ? ' · ' . esc_html( $po['city'] ) : '' ) . '</span>';
			echo '<span class="xg-supporter-amt">' . esc_html( ekinese_eur( $po['amount'] ) ) . '</span></div>';
		}
		echo '</div>';
	}
	echo '</div></section>';
	return ob_get_clean();
}

/** Admin: datum van de eerstvolgende uitkering instellen. */
function ekinese_charity_payout_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=xg_charity_project',
		__( 'Volgende uitkering', 'ekinese' ),
		__( 'Volgende uitkering', 'ekinese' ),
		'manage_options',
		'xg-charity-payout',
		'ekinese_charity_payout_admin_page'
	);
}
add_action( 'admin_menu', 'ekinese_charity_payout_admin_menu' );

function ekinese_charity_payout_admin_page() {
	if ( isset( $_POST['xg_cp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_cp_nonce'] ), 'xg_cp' ) ) {
		update_option( 'xg_charity_next_payout', sanitize_text_field( wp_unslash( $_POST['xg_next'] ?? '' ) ) );
		echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
	}
	$v = esc_attr( get_option( 'xg_charity_next_payout', '' ) );
	echo '<div class="wrap"><h1>Volgende uitkering</h1>';
	echo '<p>Deze datum verschijnt op de pagina met het blok <code>ekinese/charity-payouts</code>.</p>';
	echo '<form method="post"><table class="form-table"><tr><th>Datum eerstvolgende uitkering</th><td>';
	wp_nonce_field( 'xg_cp', 'xg_cp_nonce' );
	echo '<input type="date" name="xg_next" value="' . $v . '"></td></tr></table>';
	submit_button( 'Opslaan' );
	echo '</form></div>';
}

function ekinese_render_charity_map() {
	$total = function_exists( 'ekinese_charity_total_since' ) ? ekinese_charity_total_since() : 0;
	ob_start();
	echo '<section><div class="xg-container"><div class="xg-charity-map-wrap">';
	echo '<div class="xg-charity-map-head"><div class="xg-eyebrow">GOEDE DOELEN</div><h2>Waar XGOUD helpt</h2>';
	echo '<p class="xg-intro">Een vast deel van elke marge gaat naar projecten in heel Nederland en België. Samen hebben wij al bijgedragen:</p>';
	echo '<div class="xg-charity-counter" data-to="' . esc_attr( round( $total, 2 ) ) . '">€ 0</div></div>';
	echo '<div id="xg-charity-map" class="xg-charity-map"></div>';
	echo '</div></div></section>';
	return ob_get_clean();
}

/** Leaflet + charity-map-assets laden waar het blok staat. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/charity-map' ) ) {
		return;
	}
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	$js = get_theme_file_path( 'assets/js/charity-map.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-charity-map', get_theme_file_uri( 'assets/js/charity-map.js' ), array( 'leaflet' ), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-charity-map', 'XG_CHARITY_MAP', ekinese_charity_map_points() );
	}
} );
