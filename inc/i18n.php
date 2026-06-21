<?php
/**
 * XGOUD server-side meertaligheid (SEO-proof) + hreflang.
 *
 * Kern: vertaling gebeurt op de SERVER, niet in de browser. Onder eigen
 * URL-prefixes (/en/, /fr/, …) rendert WordPress kant-en-klare, vertaalde HTML
 * met hreflang-alternates. Zo indexeert Google elke taal als aparte pagina —
 * in tegenstelling tot client-side JS-vertaling (onzichtbaar voor crawlers).
 *
 * Het woordenboek (data/i18n.json) wordt gedeeld met de JS-laag, die alleen nog
 * dynamisch ingeladen widgets (calculator/chat) vertaalt naar dezelfde taal.
 *
 * Standaardtaal = NL (geen prefix). Geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ondersteunde talen: code => [native label]. NL is default (zonder prefix). */
function xg_languages() {
	return array(
		'nl' => 'Nederlands',
		'de' => 'Deutsch',
		'en' => 'English',
		'fr' => 'Français',
		'es' => 'Español',
		'it' => 'Italiano',
		'tr' => 'Türkçe',
		'pl' => 'Polski',
	);
}

/** Niet-standaard talen (de met prefix). */
function xg_secondary_langs() {
	return array( 'de', 'en', 'fr', 'es', 'it', 'tr', 'pl' );
}

/** Woordenboek (gecachet). NL-bron => [ lang => vertaling ]. */
function xg_dictionary() {
	static $dict = null;
	if ( null !== $dict ) {
		return $dict;
	}
	$cached = wp_cache_get( 'i18n_dict', 'xg' );
	if ( false !== $cached ) {
		$dict = $cached;
		return $dict;
	}
	$file = get_theme_file_path( 'data/i18n.json' );
	$dict = array();
	if ( file_exists( $file ) ) {
		$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore
		if ( isset( $json['dict'] ) && is_array( $json['dict'] ) ) {
			$dict = $json['dict'];
		}
	}
	wp_cache_set( 'i18n_dict', $dict, 'xg' );
	return $dict;
}

/* =====================================================================
   URL-ROUTING  (prefix → query var xg_lang)
===================================================================== */
function xg_i18n_rewrites() {
	$re = implode( '|', xg_secondary_langs() );
	add_rewrite_tag( '%xg_lang%', '(' . $re . ')' );
	// Homepage in een taal.
	add_rewrite_rule( '^(' . $re . ')/?$', 'index.php?xg_lang=$matches[1]', 'top' );
	// Pagina's (hiërarchisch pad) in een taal.
	add_rewrite_rule( '^(' . $re . ')/(.+?)/?$', 'index.php?xg_lang=$matches[1]&pagename=$matches[2]', 'top' );
}
add_action( 'init', 'xg_i18n_rewrites' );

function xg_i18n_query_var( $vars ) {
	$vars[] = 'xg_lang';
	return $vars;
}
add_filter( 'query_vars', 'xg_i18n_query_var' );

/** Eenmalig rewrite-rules flushen na deploy. */
function xg_i18n_maybe_flush() {
	if ( get_option( 'xg_i18n_rules_v' ) !== '1' ) {
		xg_i18n_rewrites();
		flush_rewrite_rules( false );
		update_option( 'xg_i18n_rules_v', '1' );
	}
}
add_action( 'init', 'xg_i18n_maybe_flush', 99 );

/** Huidige taal. */
function xg_current_lang() {
	$l = get_query_var( 'xg_lang' );
	if ( $l && array_key_exists( $l, xg_languages() ) ) {
		return $l;
	}
	return 'nl';
}

/** Vertaal één string naar de huidige (of opgegeven) taal. */
function xg_t( $s, $lang = null ) {
	$lang = $lang ?: xg_current_lang();
	if ( 'nl' === $lang ) {
		return $s;
	}
	$dict = xg_dictionary();
	return ( isset( $dict[ $s ][ $lang ] ) && '' !== $dict[ $s ][ $lang ] ) ? $dict[ $s ][ $lang ] : $s;
}

/* =====================================================================
   <html lang> + hreflang
===================================================================== */
function xg_i18n_html_lang( $output ) {
	$lang = xg_current_lang();
	return 'lang="' . esc_attr( $lang ) . '"';
}
add_filter( 'language_attributes', 'xg_i18n_html_lang' );

/** Pad van de huidige pagina zonder taalprefix. */
function xg_i18n_current_path() {
	if ( is_front_page() || is_home() ) {
		return '';
	}
	if ( is_singular() ) {
		$path = wp_parse_url( get_permalink(), PHP_URL_PATH );
	} else {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
	}
	$path = trim( (string) $path, '/' );
	// Bestaande taalprefix eraf halen.
	$re = implode( '|', xg_secondary_langs() );
	$path = preg_replace( '#^(' . $re . ')/#', '', $path );
	return $path;
}

function xg_i18n_url_for( $lang, $path ) {
	$path = trim( $path, '/' );
	$base = ( 'nl' === $lang ) ? '' : $lang . '/';
	return home_url( '/' . $base . ( $path ? $path . '/' : '' ) );
}

function xg_i18n_hreflang() {
	$path = xg_i18n_current_path();
	foreach ( array_keys( xg_languages() ) as $code ) {
		printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $code ), esc_url( xg_i18n_url_for( $code, $path ) ) );
	}
	printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( xg_i18n_url_for( 'nl', $path ) ) );
}
add_action( 'wp_head', 'xg_i18n_hreflang', 3 );

/* =====================================================================
   SERVER-SIDE VERTALING van de gerenderde HTML (alleen tekst tussen tags,
   nooit attributen/scripts) – dus volledig indexeerbaar.
===================================================================== */
function xg_i18n_translate_html( $html ) {
	$lang = xg_current_lang();
	if ( 'nl' === $lang ) {
		return $html;
	}
	$dict = xg_dictionary();
	if ( ! $dict ) {
		return $html;
	}
	return preg_replace_callback(
		'/>([^<>]+)</',
		function ( $m ) use ( $dict, $lang ) {
			$raw = $m[1];
			$key = trim( $raw );
			if ( '' === $key || ! isset( $dict[ $key ][ $lang ] ) || '' === $dict[ $key ][ $lang ] ) {
				return $m[0];
			}
			return '>' . str_replace( $key, $dict[ $key ][ $lang ], $raw ) . '<';
		},
		$html
	);
}

function xg_i18n_start_buffer() {
	if ( is_admin() || 'nl' === xg_current_lang() ) {
		return;
	}
	ob_start( 'xg_i18n_translate_html' );
}
add_action( 'template_redirect', 'xg_i18n_start_buffer', 1 );

/* =====================================================================
   SITEMAP – taalvarianten toevoegen (haakt op bestaande transient-sitemap).
===================================================================== */
add_filter( 'ekinese_sitemap_urls', function ( $urls ) {
	$extra = array();
	foreach ( $urls as $u ) {
		$path = trim( (string) wp_parse_url( $u, PHP_URL_PATH ), '/' );
		foreach ( xg_secondary_langs() as $code ) {
			$extra[] = xg_i18n_url_for( $code, $path );
		}
	}
	return array_merge( $urls, $extra );
} );
