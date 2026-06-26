<?php
/**
 * XGOUD Analytics & integraties.
 *
 *  1) INTERNE analytics (eigen tabel): wat zoeken/vragen/klikken bezoekers?
 *     - REST /track logt events (zoekopdracht, assistent-vraag, klik).
 *     - Admin-overzicht met top-zoektermen/vragen/kliks → voedt de daily tasks.
 *  2) EXTERNE integraties: Google Analytics 4, Search Console, Bing Webmaster,
 *     Facebook (Pixel + domeinverificatie), YouTube. Verificatie + tracking
 *     worden echt geïnjecteerd; YouTube-kanaalstatistiek wordt via API-key
 *     opgehaald. Volledige rapport-data van GA4/GSC vereist OAuth — daarvoor
 *     staan de velden klaar (in te vullen zodra de credentials er zijn).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   EVENT-TABEL
===================================================================== */
function ekinese_analytics_table() {
	global $wpdb;
	return $wpdb->prefix . 'xg_events';
}

function ekinese_analytics_install() {
	if ( get_option( 'xg_events_db' ) === '1' ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = ekinese_analytics_table();
	$collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE $table (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ev_type VARCHAR(20) NOT NULL DEFAULT '',
		term VARCHAR(190) NOT NULL DEFAULT '',
		url VARCHAR(255) NOT NULL DEFAULT '',
		meta VARCHAR(190) NOT NULL DEFAULT '',
		created DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY ev_type (ev_type),
		KEY created (created),
		KEY term (term)
	) $collate;" );
	update_option( 'xg_events_db', '1', false );
}
add_action( 'init', 'ekinese_analytics_install' );
add_action( 'after_switch_theme', function () { delete_option( 'xg_events_db' ); ekinese_analytics_install(); } );

/** Een event loggen (server-side helper). */
function ekinese_track( $type, $term = '', $url = '', $meta = '' ) {
	global $wpdb;
	$allowed = array( 'search', 'assistant', 'click', 'noanswer' );
	$type    = in_array( $type, $allowed, true ) ? $type : 'search';
	// PII-light: e-mailadressen uit de term verwijderen.
	$term = preg_replace( '/[\w.+-]+@[\w-]+\.[\w.-]+/', '[email]', (string) $term );
	$term = trim( mb_substr( wp_strip_all_tags( $term ), 0, 180 ) );
	$wpdb->insert( ekinese_analytics_table(), array( // phpcs:ignore WordPress.DB
		'ev_type' => $type,
		'term'    => $term,
		'url'     => esc_url_raw( mb_substr( (string) $url, 0, 250 ) ),
		'meta'    => sanitize_text_field( mb_substr( (string) $meta, 0, 180 ) ),
		'created' => current_time( 'mysql' ),
	) );
}

/** Top-termen per type over N dagen. */
function ekinese_top_events( $type, $days = 30, $limit = 10 ) {
	global $wpdb;
	$table = ekinese_analytics_table();
	$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
	return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"SELECT term, COUNT(*) AS n FROM $table WHERE ev_type = %s AND term <> '' AND created >= %s GROUP BY term ORDER BY n DESC LIMIT %d",
		$type, $since, $limit
	) );
}

function ekinese_events_count( $type, $days = 7 ) {
	global $wpdb;
	$table = ekinese_analytics_table();
	$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE ev_type = %s AND created >= %s", $type, $since ) ); // phpcs:ignore WordPress.DB
}

/* =====================================================================
   REST  /track  — frontend events
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/track', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			$p = $r->get_json_params();
			ekinese_track( sanitize_key( $p['type'] ?? 'search' ), (string) ( $p['term'] ?? '' ), (string) ( $p['url'] ?? '' ), (string) ( $p['meta'] ?? '' ) );
			return rest_ensure_response( array( 'ok' => true ) );
		},
	) );
} );

/** Frontend-tracker laden (klikken + verwijzing van de assistent). */
add_action( 'wp_enqueue_scripts', function () {
	$js = get_theme_file_path( 'assets/js/track.js' );
	if ( ! file_exists( $js ) ) {
		return;
	}
	wp_enqueue_script( 'ekinese-track', get_theme_file_uri( 'assets/js/track.js' ), array(), (string) filemtime( $js ), true );
	wp_localize_script( 'ekinese-track', 'XGTrack', array( 'rest' => esc_url_raw( rest_url( 'ekinese/v1/track' ) ) ) );
} );

/* =====================================================================
   INTEGRATIES — opties + head-injectie
===================================================================== */
function ekinese_analytics_options() {
	return array(
		'xg_ga4_id'          => 'Google Analytics 4 — Measurement ID (G-XXXX)',
		'xg_ga4_property'    => 'GA4 Property ID (voor rapport-data, vereist OAuth)',
		'xg_gsc_verify'      => 'Google Search Console — verificatiecode (meta)',
		'xg_gsc_site'        => 'Search Console — site-URL (sc-domain:… of https://…)',
		'xg_bing_verify'     => 'Bing Webmaster — verificatiecode (meta)',
		'xg_fb_pixel'        => 'Facebook Pixel-ID',
		'xg_fb_domain'       => 'Facebook domeinverificatie (meta)',
		'xg_yt_api_key'      => 'YouTube Data API-key',
		'xg_yt_channel'      => 'YouTube kanaal-ID (UC…)',
	);
}

/** Verificatie-meta's + GA4 + FB-Pixel in de <head>. */
add_action( 'wp_head', function () {
	$gsc  = trim( (string) get_option( 'xg_gsc_verify', '' ) );
	$bing = trim( (string) get_option( 'xg_bing_verify', '' ) );
	$fbd  = trim( (string) get_option( 'xg_fb_domain', '' ) );
	$ga4  = trim( (string) get_option( 'xg_ga4_id', '' ) );
	$px   = trim( (string) get_option( 'xg_fb_pixel', '' ) );
	if ( $gsc ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $gsc ) . '">' . "\n";
	}
	if ( $bing ) {
		echo '<meta name="msvalidate.01" content="' . esc_attr( $bing ) . '">' . "\n";
	}
	if ( $fbd ) {
		echo '<meta name="facebook-domain-verification" content="' . esc_attr( $fbd ) . '">' . "\n";
	}
	// GA4 + Facebook-pixel worden NIET hier geladen: dat gebeurt pas na
	// cookie-toestemming (inc/consent.php). Verificatie-meta's mogen wel altijd.
}, 5 );

/** YouTube-kanaalstatistiek (via API-key), gecachet. */
function ekinese_youtube_stats() {
	$key = trim( (string) get_option( 'xg_yt_api_key', '' ) );
	$ch  = trim( (string) get_option( 'xg_yt_channel', '' ) );
	if ( '' === $key || '' === $ch ) {
		return null;
	}
	$cache = get_transient( 'xg_yt_stats' );
	if ( false !== $cache ) {
		return $cache;
	}
	$resp = wp_remote_get( add_query_arg(
		array( 'part' => 'statistics', 'id' => $ch, 'key' => $key ),
		'https://www.googleapis.com/youtube/v3/channels'
	), array( 'timeout' => 15 ) );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return null;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	$st   = $data['items'][0]['statistics'] ?? null;
	if ( $st ) {
		set_transient( 'xg_yt_stats', $st, 6 * HOUR_IN_SECONDS );
	}
	return $st;
}

/* =====================================================================
   ADMIN — instellingen-pagina
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Analytics & integraties', 'ekinese' ), __( 'Analytics', 'ekinese' ), 'manage_options', 'xg-analytics', 'ekinese_analytics_settings_page' );
} );

function ekinese_analytics_settings_page() {
	if ( isset( $_POST['xg_an_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_an_nonce'] ), 'xg_an' ) ) {
		foreach ( array_keys( ekinese_analytics_options() ) as $opt ) {
			if ( isset( $_POST[ $opt ] ) ) {
				update_option( $opt, sanitize_text_field( wp_unslash( $_POST[ $opt ] ) ) );
			}
		}
		delete_transient( 'xg_yt_stats' );
		echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
	}
	echo '<div class="wrap"><h1>Analytics &amp; integraties</h1>';
	echo '<p>Verificatie-codes en tracking-ID\'s worden direct in de site geïnjecteerd. Voor volledige rapport-data van GA4/Search Console is OAuth nodig — die velden staan klaar voor zodra u de credentials koppelt.</p>';
	echo '<form method="post"><table class="form-table">';
	wp_nonce_field( 'xg_an', 'xg_an_nonce' );
	foreach ( ekinese_analytics_options() as $opt => $label ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><input type="text" name="' . esc_attr( $opt ) . '" value="' . esc_attr( get_option( $opt, '' ) ) . '" class="regular-text"></td></tr>';
	}
	echo '</table>';
	submit_button();
	$yt = ekinese_youtube_stats();
	if ( $yt ) {
		echo '<h2>YouTube</h2><p>Abonnees: <strong>' . esc_html( number_format_i18n( (int) ( $yt['subscriberCount'] ?? 0 ) ) ) . '</strong> · Weergaven: <strong>' . esc_html( number_format_i18n( (int) ( $yt['viewCount'] ?? 0 ) ) ) . '</strong> · Video\'s: <strong>' . esc_html( (int) ( $yt['videoCount'] ?? 0 ) ) . '</strong></p>';
	}
	echo '</form></div>';
}

/* =====================================================================
   DASHBOARD-PANEEL — wat zoeken/klikken bezoekers?
===================================================================== */
function ekinese_analytics_panel() {
	$render = function ( $title, $rows, $empty ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0;font-size:15px">' . esc_html( $title ) . '</h2>';
		if ( ! $rows ) {
			echo '<p style="color:#8c8f94;margin:0">' . esc_html( $empty ) . '</p></div>';
			return;
		}
		echo '<ol style="margin:0;padding-left:18px">';
		foreach ( $rows as $r ) {
			echo '<li style="padding:3px 0">' . esc_html( $r->term ) . ' <span style="color:#646970">— ' . esc_html( $r->n ) . '×</span></li>';
		}
		echo '</ol></div>';
	};
	echo '<h2 style="margin-top:24px">Wat zoeken bezoekers? <span style="color:#646970;font-weight:400;font-size:13px">(laatste 30 dagen)</span></h2>';
	echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:8px">';
	$render( 'Top assistent-vragen', ekinese_top_events( 'assistant', 30, 8 ), 'Nog geen vragen gelogd.' );
	$render( 'Top zoekopdrachten', ekinese_top_events( 'search', 30, 8 ), 'Nog geen zoekopdrachten.' );
	$render( 'Meest geklikt', ekinese_top_events( 'click', 30, 8 ), 'Nog geen kliks gelogd.' );
	echo '</div>';

	$yt = ekinese_youtube_stats();
	if ( $yt ) {
		echo '<p style="margin-top:10px;color:#646970">YouTube: ' . esc_html( number_format_i18n( (int) ( $yt['subscriberCount'] ?? 0 ) ) ) . ' abonnees · ' . esc_html( number_format_i18n( (int) ( $yt['viewCount'] ?? 0 ) ) ) . ' weergaven.</p>';
	}
}

/* =====================================================================
   DAILY TASKS uit analytics (haakt in op de bestaande lijst)
===================================================================== */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$noanswer = ekinese_events_count( 'noanswer', 7 );
	if ( $noanswer > 0 ) {
		$tasks[] = array( 'key' => 'noanswer', 'label' => 'Zoekopdrachten zonder goed antwoord — content maken', 'count' => $noanswer, 'link' => admin_url( 'admin.php?page=xgoud' ) );
	}
	$top = ekinese_top_events( 'assistant', 7, 1 );
	if ( $top && (int) $top[0]->n >= 5 ) {
		$tasks[] = array( 'key' => 'topterm', 'label' => 'Veelgevraagd: “' . $top[0]->term . '” — overweeg een landingspagina', 'count' => (int) $top[0]->n, 'link' => admin_url( 'edit.php?post_type=page' ) );
	}
	return $tasks;
}, 10, 1 );
