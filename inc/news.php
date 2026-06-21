<?php
/**
 * XGOUD News-portaal – geaggregeerd nieuws + eigen berichten.
 *
 * Haalt automatisch nieuws van bekende bronnen (RSS) op, gecategoriseerd op
 * thema (edelmetalen, edelstenen, horloges) en taal (NL/EN/FR/ES). Daarnaast
 * verschijnen XGOUD's eigen berichten. Gebruikt WordPress' ingebouwde SimplePie
 * (fetch_feed) — geen plugin. Resultaten gecachet voor performance.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Standaard-feeds (in admin aanpasbaar). [url, thema, taal]. */
function ekinese_news_feeds() {
	$default = array(
		array( 'https://www.kitco.com/rss/KitcoNews.xml', 'edelmetalen', 'en' ),
		array( 'https://www.bullionvault.com/gold-news/feed', 'edelmetalen', 'en' ),
		array( 'https://www.monochrome-watches.com/feed/', 'horloges', 'en' ),
		array( 'https://www.gemsociety.org/feed/', 'edelstenen', 'en' ),
	);
	$saved = get_option( 'xg_news_feeds' );
	return is_array( $saved ) && $saved ? $saved : $default;
}

/** Thema's. */
function ekinese_news_topics() {
	return array(
		'edelmetalen' => __( 'Edelmetalen', 'ekinese' ),
		'edelstenen'  => __( 'Edelstenen', 'ekinese' ),
		'horloges'    => __( 'Horloges', 'ekinese' ),
	);
}

/**
 * Geaggregeerde nieuwsitems (gecachet, 1 uur).
 *
 * @param string $topic optioneel filter.
 * @return array
 */
function ekinese_news_items( $topic = '' ) {
	$cache = get_transient( 'xg_news_items' );
	if ( false === $cache ) {
		require_once ABSPATH . WPINC . '/feed.php';
		$cache = array();
		foreach ( ekinese_news_feeds() as $f ) {
			list( $url, $theme, $lang ) = array_pad( $f, 3, '' );
			$feed = fetch_feed( $url );
			if ( is_wp_error( $feed ) ) {
				continue;
			}
			$max = $feed->get_item_quantity( 6 );
			foreach ( $feed->get_items( 0, $max ) as $item ) {
				$cache[] = array(
					'title'  => wp_strip_all_tags( $item->get_title() ),
					'url'    => esc_url_raw( $item->get_permalink() ),
					'date'   => $item->get_date( 'U' ),
					'source' => wp_strip_all_tags( $feed->get_title() ),
					'topic'  => $theme,
					'lang'   => $lang,
					'own'    => false,
				);
			}
		}
		// Eigen XGOUD-berichten meenemen.
		foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => 8, 'post_status' => 'publish' ) ) as $p ) {
			$cache[] = array(
				'title'  => get_the_title( $p ),
				'url'    => get_permalink( $p ),
				'date'   => get_post_time( 'U', false, $p ),
				'source' => 'XGOUD',
				'topic'  => 'edelmetalen',
				'lang'   => 'nl',
				'own'    => true,
			);
		}
		usort( $cache, function ( $a, $b ) { return (int) $b['date'] - (int) $a['date']; } );
		set_transient( 'xg_news_items', $cache, HOUR_IN_SECONDS );
	}
	if ( $topic ) {
		$cache = array_values( array_filter( $cache, function ( $i ) use ( $topic ) { return $i['topic'] === $topic; } ) );
	}
	return $cache;
}

/* =====================================================================
   BLOK  ekinese/news  – portaal (eigen + web, gecategoriseerd)
===================================================================== */
function ekinese_register_news_block() {
	register_block_type( 'ekinese/news', array(
		'attributes'      => array( 'topic' => array( 'type' => 'string', 'default' => '' ), 'limit' => array( 'type' => 'number', 'default' => 18 ) ),
		'render_callback' => 'ekinese_render_news',
	) );
}
add_action( 'init', 'ekinese_register_news_block' );

function ekinese_render_news( $attr ) {
	$items = ekinese_news_items( sanitize_key( $attr['topic'] ?? '' ) );
	$items = array_slice( $items, 0, (int) ( $attr['limit'] ?? 18 ) );
	if ( ! $items ) {
		return '';
	}
	$topics = ekinese_news_topics();
	ob_start();
	echo '<section><div class="xg-container">';
	echo '<div class="xg-news-grid xg-grid-3">';
	foreach ( $items as $i ) {
		printf(
			'<a class="xg-c-card xg-news-card" href="%s" target="_blank" rel="noopener" style="text-decoration:none"><span class="xg-news-meta">%s · %s%s</span><h3>%s</h3></a>',
			esc_url( $i['url'] ),
			esc_html( $topics[ $i['topic'] ] ?? $i['topic'] ),
			esc_html( $i['source'] ),
			$i['own'] ? ' · <strong>XGOUD</strong>' : '',
			esc_html( $i['title'] )
		);
	}
	echo '</div></div></section>';
	return ob_get_clean();
}

/* =====================================================================
   CRON – feeds verversen
===================================================================== */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_news_refresh' ) ) {
		wp_schedule_event( time() + 900, 'hourly', 'xg_news_refresh' );
	}
} );
add_action( 'xg_news_refresh', function () { delete_transient( 'xg_news_items' ); ekinese_news_items(); } );

/* =====================================================================
   ADMIN – feeds beheren
===================================================================== */
add_action( 'admin_menu', function () {
	add_menu_page( __( 'Nieuwsportaal', 'ekinese' ), __( 'Nieuwsportaal', 'ekinese' ), 'manage_options', 'xg-news', function () {
		if ( isset( $_POST['xg_news_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_news_nonce'] ), 'xg_news' ) ) {
			$lines = preg_split( '/\r?\n/', (string) wp_unslash( $_POST['xg_news_feeds'] ?? '' ) );
			$feeds = array();
			foreach ( $lines as $line ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				if ( ! empty( $parts[0] ) && filter_var( $parts[0], FILTER_VALIDATE_URL ) ) {
					$feeds[] = array( esc_url_raw( $parts[0] ), sanitize_key( $parts[1] ?? 'edelmetalen' ), sanitize_key( $parts[2] ?? 'en' ) );
				}
			}
			update_option( 'xg_news_feeds', $feeds );
			delete_transient( 'xg_news_items' );
			echo '<div class="notice notice-success"><p>Feeds opgeslagen.</p></div>';
		}
		$lines = array_map( function ( $f ) { return implode( ' | ', $f ); }, ekinese_news_feeds() );
		echo '<div class="wrap"><h1>Nieuwsportaal — feeds</h1><p>Eén feed per regel: <code>RSS-URL | thema | taal</code> (thema: edelmetalen/edelstenen/horloges, taal: nl/en/fr/es).</p>';
		echo '<form method="post"><textarea name="xg_news_feeds" rows="10" class="large-text code">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		wp_nonce_field( 'xg_news', 'xg_news_nonce' );
		submit_button();
		echo '</form></div>';
	}, 'dashicons-rss', 59 );
} );
