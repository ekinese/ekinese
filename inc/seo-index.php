<?php
/**
 * XGOUD SEO-indexering: automatische push naar zoekmachines (IndexNow) +
 * verse sitemap, 3× per dag, voor de prijspagina's (goud/zilver/platina/
 * palladium + inkoopprijzen). Zo blijven de prijzen actueel in de
 * zoekresultaten. Self-built, geen plugin.
 *
 * IndexNow (Bing, Yandex, Seznam, …) werkt met een zelf-gegenereerde sleutel.
 * Google's expliciete ping is afgeschaft; daar houden we de sitemap + lastmod
 * vers zodat Google sneller hercrawlt (Search Console API kan later, met auth).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Zelf-gegenereerde IndexNow-sleutel (32 hex). */
function ekinese_indexnow_key() {
	$key = get_option( 'xg_indexnow_key' );
	if ( ! $key ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'xg_indexnow_key', $key );
	}
	return $key;
}

/** Prijspagina-paden die we actief willen laten indexeren (filterbaar). */
function ekinese_price_paths() {
	return apply_filters( 'ekinese_price_paths', array(
		'dagprijzen/',
		'dagprijzen/goudprijs/',
		'dagprijzen/zilverprijs/',
		'dagprijzen/platinaprijs/',
		'dagprijzen/palladiumprijs/',
		'inkoopprijzen/',
	) );
}

/** Volledige URL's van de prijspagina's. */
function ekinese_price_urls() {
	return array_map( function ( $p ) { return home_url( '/' . ltrim( $p, '/' ) ); }, ekinese_price_paths() );
}

/* =====================================================================
   INDEXNOW – sleutelbestand op /{key}.txt
===================================================================== */
function ekinese_indexnow_rewrite() {
	add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?xg_indexnow_key=$matches[1]', 'top' );
}
add_action( 'init', 'ekinese_indexnow_rewrite' );

function ekinese_indexnow_qv( $vars ) {
	$vars[] = 'xg_indexnow_key';
	return $vars;
}
add_filter( 'query_vars', 'ekinese_indexnow_qv' );

function ekinese_indexnow_keyfile() {
	$req = get_query_var( 'xg_indexnow_key' );
	if ( ! $req ) {
		return;
	}
	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo ( $req === ekinese_indexnow_key() ) ? esc_html( $req ) : '';
	exit;
}
add_action( 'template_redirect', 'ekinese_indexnow_keyfile' );

/**
 * URL's bij IndexNow indienen.
 *
 * @param array $urls
 * @return array|WP_Error response-info
 */
function ekinese_indexnow_submit( $urls ) {
	$urls = array_values( array_unique( array_filter( (array) $urls ) ) );
	if ( ! $urls ) {
		return new WP_Error( 'empty', 'geen urls' );
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$body = array(
		'host'        => $host,
		'key'         => ekinese_indexnow_key(),
		'keyLocation' => home_url( '/' . ekinese_indexnow_key() . '.txt' ),
		'urlList'     => $urls,
	);
	$res = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
		'timeout' => 12,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'    => wp_json_encode( $body ),
	) );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	update_option( 'xg_indexnow_last', array( 'time' => current_time( 'mysql' ), 'count' => count( $urls ), 'code' => $code ) );
	return $res;
}

/* =====================================================================
   CRON – 3× per dag (elke 8 uur)
===================================================================== */
function ekinese_seo_index_schedule( $schedules ) {
	$schedules['xg_8h'] = array( 'interval' => 8 * HOUR_IN_SECONDS, 'display' => '3× per dag' );
	return $schedules;
}
add_filter( 'cron_schedules', 'ekinese_seo_index_schedule' );

function ekinese_seo_index_activate() {
	if ( ! wp_next_scheduled( 'xg_seo_index_push' ) ) {
		wp_schedule_event( time() + 600, 'xg_8h', 'xg_seo_index_push' );
	}
}
add_action( 'init', 'ekinese_seo_index_activate' );

function ekinese_seo_index_push() {
	// Sitemap vernieuwen (lastmod up-to-date) + prijspagina's pushen.
	delete_transient( 'xg_sitemap_xml' );
	ekinese_indexnow_submit( ekinese_price_urls() );
}
add_action( 'xg_seo_index_push', 'ekinese_seo_index_push' );

/* =====================================================================
   ADMIN  →  Instellingen → SEO-indexering
===================================================================== */
function ekinese_seo_index_menu() {
	add_submenu_page( 'options-general.php', __( 'SEO-indexering', 'ekinese' ), __( 'SEO-indexering', 'ekinese' ), 'manage_options', 'xg-seo-index', 'ekinese_seo_index_page' );
}
add_action( 'admin_menu', 'ekinese_seo_index_menu' );

function ekinese_seo_index_page() {
	if ( isset( $_POST['xg_idx_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_idx_nonce'] ), 'xg_idx' ) ) {
		$res  = ekinese_indexnow_submit( ekinese_price_urls() );
		$msg  = is_wp_error( $res ) ? $res->get_error_message() : ( 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Nu ingediend:', 'ekinese' ) . ' ' . esc_html( $msg ) . '</p></div>';
	}
	$last = get_option( 'xg_indexnow_last' );
	echo '<div class="wrap"><h1>' . esc_html__( 'SEO-indexering', 'ekinese' ) . '</h1>';
	echo '<p>' . esc_html__( 'Stuurt de prijspagina\'s automatisch 3× per dag naar IndexNow (Bing/Yandex e.a.) en houdt de sitemap vers voor Google.', 'ekinese' ) . '</p>';
	echo '<table class="form-table">';
	echo '<tr><th>IndexNow-sleutel</th><td><code>' . esc_html( ekinese_indexnow_key() ) . '</code><br><small>' . esc_html__( 'Sleutelbestand:', 'ekinese' ) . ' ' . esc_html( home_url( '/' . ekinese_indexnow_key() . '.txt' ) ) . '</small></td></tr>';
	if ( $last ) {
		echo '<tr><th>Laatste push</th><td>' . esc_html( $last['time'] ) . ' — ' . esc_html( (string) $last['count'] ) . ' urls (HTTP ' . esc_html( (string) $last['code'] ) . ')</td></tr>';
	}
	echo '<tr><th>Prijspagina\'s</th><td>' . implode( '<br>', array_map( 'esc_html', ekinese_price_urls() ) ) . '</td></tr>';
	echo '</table>';
	echo '<form method="post">';
	wp_nonce_field( 'xg_idx', 'xg_idx_nonce' );
	submit_button( __( 'Nu indienen', 'ekinese' ) );
	echo '</form></div>';
}

/* =====================================================================
   BLOK  ekinese/metal-prices  – live inkoopprijzen (indexeerbare content)
===================================================================== */
function ekinese_register_price_block() {
	register_block_type( 'ekinese/metal-prices', array(
		'attributes'      => array( 'metal' => array( 'type' => 'string', 'default' => '' ) ),
		'render_callback' => 'ekinese_render_price_block',
	) );
}
add_action( 'init', 'ekinese_register_price_block' );

/**
 * Inkoopprijs per gram fijn metaal = spot × (1 − marge). Zelfde basis als de
 * calculator (ekinese_metal_spot + marge-optie).
 */
function ekinese_render_price_block( $attr ) {
	$labels = array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' );
	$only   = sanitize_key( $attr['metal'] ?? '' );
	$metals = $only && isset( $labels[ $only ] ) ? array( $only => $labels[ $only ] ) : $labels;

	$margins = get_option( 'xg_calc_margins', array() );
	$margin  = isset( $margins['metal'] ) ? (float) $margins['metal'] : 0.08;

	ob_start();
	echo '<section><div class="xg-container"><div class="xg-spec-table"><table><thead><tr><th>Metaal</th><th>Spotprijs (€/g)</th><th>Inkoopprijs (€/g)</th></tr></thead><tbody>';
	foreach ( $metals as $code => $label ) {
		$spot = function_exists( 'ekinese_metal_spot' ) ? ekinese_metal_spot( $code ) : 0;
		$buy  = $spot * ( 1 - $margin );
		printf(
			'<tr><td>%s</td><td>€ %s</td><td><strong>€ %s</strong></td></tr>',
			esc_html( $label ),
			esc_html( number_format_i18n( $spot, 2 ) ),
			esc_html( number_format_i18n( $buy, 2 ) )
		);
	}
	echo '</tbody></table></div><p style="margin-top:12px;color:var(--ink-soft,#6b665c);font-size:13px">Laatst bijgewerkt: ' . esc_html( date_i18n( 'd-m-Y H:i' ) ) . '. Prijzen indicatief, op basis van de actuele spotkoers.</p></div></section>';
	return ob_get_clean();
}
