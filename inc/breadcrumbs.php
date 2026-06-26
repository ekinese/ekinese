<?php
/**
 * XGOUD breadcrumbs — zichtbaar kruimelpad + gedeelde trail voor de JSON-LD.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bouwt het kruimelpad als array van array('name','url'); laatste = huidige (url leeg). */
function ekinese_breadcrumb_trail() {
	$home  = home_url( '/' );
	$trail = array( array( 'name' => 'Home', 'url' => $home ) );

	if ( is_front_page() ) {
		return $trail;
	}

	if ( is_singular() ) {
		$id   = get_the_ID();
		$type = get_post_type( $id );

		if ( in_array( $type, array( 'xg_product', 'xg_watch', 'xg_gemstone' ), true ) ) {
			$trail[] = array( 'name' => 'Verkopen', 'url' => home_url( '/verkopen/' ) );
		} elseif ( 'post' === $type ) {
			$blog = get_option( 'page_for_posts' );
			$trail[] = array( 'name' => $blog ? get_the_title( $blog ) : 'Nieuws', 'url' => $blog ? get_permalink( $blog ) : home_url( '/nieuws/' ) );
			$cats = get_the_category( $id );
			if ( $cats ) {
				$trail[] = array( 'name' => $cats[0]->name, 'url' => get_category_link( $cats[0]->term_id ) );
			}
		} elseif ( 'page' === $type ) {
			$ancestors = array_reverse( get_post_ancestors( $id ) );
			foreach ( $ancestors as $aid ) {
				$trail[] = array( 'name' => get_the_title( $aid ), 'url' => get_permalink( $aid ) );
			}
		} else {
			$pt = get_post_type_object( $type );
			if ( $pt && ! empty( $pt->has_archive ) ) {
				$link = get_post_type_archive_link( $type );
				if ( $link ) {
					$trail[] = array( 'name' => $pt->labels->name, 'url' => $link );
				}
			}
		}
		$trail[] = array( 'name' => wp_strip_all_tags( get_the_title( $id ) ), 'url' => '' );
	} elseif ( is_category() || is_tax() || is_tag() ) {
		$trail[] = array( 'name' => single_term_title( '', false ), 'url' => '' );
	} elseif ( is_post_type_archive() ) {
		$trail[] = array( 'name' => post_type_archive_title( '', false ), 'url' => '' );
	} elseif ( is_search() ) {
		$trail[] = array( 'name' => 'Zoeken', 'url' => '' );
	} elseif ( is_404() ) {
		$trail[] = array( 'name' => 'Niet gevonden', 'url' => '' );
	} else {
		$trail[] = array( 'name' => wp_strip_all_tags( wp_get_document_title() ), 'url' => '' );
	}

	return apply_filters( 'ekinese_breadcrumb_trail', $trail );
}

/** Zichtbaar kruimelpad (HTML). */
function ekinese_breadcrumbs_html() {
	$trail = ekinese_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return '';
	}
	$items = array();
	$last  = count( $trail ) - 1;
	foreach ( $trail as $i => $c ) {
		if ( $i === $last || '' === $c['url'] ) {
			$items[] = '<span class="xg-bc-cur" aria-current="page">' . esc_html( $c['name'] ) . '</span>';
		} else {
			$items[] = '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a>';
		}
	}
	return '<nav class="xg-breadcrumbs" aria-label="Kruimelpad"><div class="xg-container">'
		. implode( '<span class="xg-bc-sep">›</span>', $items ) . '</div></nav>';
}

/* Auto-invoegen vóór de inhoud op singular-pagina's (niet op de homepage en
   niet op de full-screen app/driver/checkout-pagina's). */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular() || is_front_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$post = get_post();
	if ( $post ) {
		foreach ( array( 'ekinese/driver-app', 'ekinese/widget', 'ekinese/account', 'ekinese/verify' ) as $b ) {
			if ( has_block( $b, $post ) ) {
				return $content;
			}
		}
	}
	return ekinese_breadcrumbs_html() . $content;
}, 8 );

/* Blok voor handmatige plaatsing. */
add_action( 'init', function () {
	register_block_type( 'ekinese/breadcrumbs', array( 'render_callback' => 'ekinese_breadcrumbs_html' ) );
} );

/* Stijl (compact; light + dark). */
add_action( 'wp_enqueue_scripts', function () {
	$css = '.xg-breadcrumbs{font-size:13px;color:#8a847a;padding:14px 0 0}'
		. '.xg-breadcrumbs a{color:#6b665c;text-decoration:none}.xg-breadcrumbs a:hover{color:#AE1E1E}'
		. '.xg-bc-sep{margin:0 7px;color:#cfc6b4}.xg-bc-cur{color:#2a2620;font-weight:600}'
		. 'html[data-theme="dark"] .xg-breadcrumbs a{color:#b3ad9f}html[data-theme="dark"] .xg-bc-cur{color:#f3efe8}';
	wp_register_style( 'ekinese-breadcrumbs', false, array(), null );
	wp_enqueue_style( 'ekinese-breadcrumbs' );
	wp_add_inline_style( 'ekinese-breadcrumbs', $css );
} );
