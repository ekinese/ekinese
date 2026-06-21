<?php
/**
 * XGOUD Heatmap – self-built klik-aggregatie (privacyvriendelijk, performant).
 *
 * Geen externe tool. Clicks worden client-side gesampled en gebatcht via
 * sendBeacon naar de REST-API. De server bucket ze in een grof raster
 * (relatief, geen pixels) en bewaart alleen tellingen per pad in één optie.
 * Geen IP's, geen persoonsgegevens. Standaard UIT (adminschakelaar) zodat het
 * nooit ongevraagd schrijflast veroorzaakt.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_HEATMAP_COLS = 20; // rasterbreedte (kolommen, 0–19)
const XG_HEATMAP_ROWS = 40; // rastergebied in 5%-stappen verticaal

function ekinese_heatmap_enabled() {
	return (bool) get_option( 'xg_heatmap_on', false );
}

/* =====================================================================
   FRONT-END – tracker laden (alleen indien ingeschakeld)
===================================================================== */
function ekinese_heatmap_assets() {
	if ( ! ekinese_heatmap_enabled() || is_admin() ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/heatmap.js' );
	if ( ! file_exists( $js ) ) {
		return;
	}
	wp_enqueue_script( 'ekinese-heatmap', get_theme_file_uri( 'assets/js/heatmap.js' ), array(), (string) filemtime( $js ), true );
	wp_localize_script( 'ekinese-heatmap', 'XG_HEATMAP', array(
		'rest'   => esc_url_raw( rest_url( 'ekinese/v1/heatmap' ) ),
		'sample' => (float) get_option( 'xg_heatmap_sample', 0.25 ), // 25% van de bezoekers
		'cols'   => XG_HEATMAP_COLS,
		'rows'   => XG_HEATMAP_ROWS,
	) );
}
add_action( 'wp_enqueue_scripts', 'ekinese_heatmap_assets' );

/* =====================================================================
   REST – batch hits opslaan
===================================================================== */
function ekinese_heatmap_rest() {
	register_rest_route( 'ekinese/v1', '/heatmap', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_heatmap_collect',
	) );
}
add_action( 'rest_api_init', 'ekinese_heatmap_rest' );

/** Sleutel per pad (kort, veilig). */
function ekinese_heatmap_key( $path ) {
	return 'xg_hm_' . md5( $path );
}

function ekinese_heatmap_collect( WP_REST_Request $req ) {
	if ( ! ekinese_heatmap_enabled() ) {
		return new WP_Error( 'off', 'disabled', array( 'status' => 403 ) );
	}
	$path = sanitize_text_field( (string) $req->get_param( 'path' ) );
	$hits = $req->get_param( 'hits' );
	if ( '' === $path || ! is_array( $hits ) || ! $hits ) {
		return new WP_Error( 'invalid', 'no data', array( 'status' => 400 ) );
	}
	$path = '/' . trim( wp_parse_url( $path, PHP_URL_PATH ) ?: $path, '/' );
	$key  = ekinese_heatmap_key( $path );
	$grid = get_option( $key, array() );
	if ( ! is_array( $grid ) ) {
		$grid = array();
	}
	$max = 200; // hard cap per request tegen misbruik
	foreach ( array_slice( $hits, 0, $max ) as $h ) {
		$cx = isset( $h['c'] ) ? (int) $h['c'] : -1;
		$ry = isset( $h['r'] ) ? (int) $h['r'] : -1;
		if ( $cx < 0 || $cx >= XG_HEATMAP_COLS || $ry < 0 || $ry >= XG_HEATMAP_ROWS ) {
			continue;
		}
		$cell          = $cx . ',' . $ry;
		$grid[ $cell ] = ( $grid[ $cell ] ?? 0 ) + 1;
	}
	update_option( $key, $grid, false ); // autoload uit → geen perf-impact
	// Padregister bijhouden voor de adminlijst.
	$paths = get_option( 'xg_heatmap_paths', array() );
	if ( ! in_array( $path, $paths, true ) ) {
		$paths[] = $path;
		update_option( 'xg_heatmap_paths', array_slice( $paths, -200 ), false );
	}
	return rest_ensure_response( array( 'ok' => true ) );
}

/* =====================================================================
   ADMIN – schakelaar + viewer
===================================================================== */
function ekinese_heatmap_menu() {
	add_submenu_page( 'options-general.php', __( 'Heatmap', 'ekinese' ), __( 'Heatmap', 'ekinese' ), 'manage_options', 'xg-heatmap', 'ekinese_heatmap_page' );
}
add_action( 'admin_menu', 'ekinese_heatmap_menu' );

function ekinese_heatmap_page() {
	if ( isset( $_POST['xg_hm_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_hm_nonce'] ), 'xg_hm' ) ) {
		update_option( 'xg_heatmap_on', isset( $_POST['xg_heatmap_on'] ) ? 1 : 0 );
		update_option( 'xg_heatmap_sample', min( 1, max( 0.01, (float) ( $_POST['xg_heatmap_sample'] ?? 0.25 ) ) ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Opgeslagen.', 'ekinese' ) . '</p></div>';
	}
	$paths = get_option( 'xg_heatmap_paths', array() );
	echo '<div class="wrap"><h1>' . esc_html__( 'Heatmap', 'ekinese' ) . '</h1>';
	echo '<form method="post"><table class="form-table">';
	wp_nonce_field( 'xg_hm', 'xg_hm_nonce' );
	echo '<tr><th>Inschakelen</th><td><label><input type="checkbox" name="xg_heatmap_on" value="1"' . checked( ekinese_heatmap_enabled(), true, false ) . '> klikken verzamelen</label></td></tr>';
	echo '<tr><th>Sample-ratio</th><td><input type="number" step="0.05" min="0.01" max="1" name="xg_heatmap_sample" value="' . esc_attr( get_option( 'xg_heatmap_sample', 0.25 ) ) . '"><p class="description">Aandeel bezoekers dat meet (0.25 = 25%). Lager = minder schrijflast.</p></td></tr>';
	submit_button();
	echo '</table></form>';

	if ( $paths ) {
		echo '<h2>' . esc_html__( 'Gemeten pagina\'s', 'ekinese' ) . '</h2><table class="widefat striped"><thead><tr><th>Pad</th><th>Totaal clicks</th><th>Hotspot (kol,rij)</th></tr></thead><tbody>';
		foreach ( $paths as $p ) {
			$grid = get_option( ekinese_heatmap_key( $p ), array() );
			$tot  = is_array( $grid ) ? array_sum( $grid ) : 0;
			$top  = '';
			if ( $grid ) {
				arsort( $grid );
				$top = array_key_first( $grid );
			}
			echo '<tr><td>' . esc_html( $p ) . '</td><td>' . esc_html( (string) $tot ) . '</td><td>' . esc_html( $top ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}
