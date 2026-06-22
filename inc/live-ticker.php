<?php
/**
 * XGOUD Live-verkocht-ticker – sociale bewijskracht.
 *
 * Toont rechtsonder kort roterende meldingen van recente verkopen, anoniem
 * (stad + producttype + afgerond bedrag). Gebruikt ECHTE afgeronde afspraken;
 * bij geen data blijft de ticker leeg, tenzij de demo-modus aanstaat (duidelijk
 * representatief, geen misleiding). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recente (anonieme) verkopen voor de ticker.
 *
 * @return array Lijst [ {product, city, amount, ago} ]
 */
function ekinese_live_sales() {
	$cache = get_transient( 'xg_live_sales' );
	if ( false !== $cache ) {
		return $cache;
	}
	$items = array();
	$appts = get_posts( array(
		'post_type'   => 'xg_appointment',
		'numberposts' => 12,
		'post_status' => 'publish',
		'orderby'     => 'modified',
		'order'       => 'DESC',
		'meta_query'  => array( array( 'key' => 'status', 'value' => array( 'completed', 'paid', 'afgerond', 'uitbetaald' ), 'compare' => 'IN' ) ),
	) );
	foreach ( $appts as $a ) {
		$amount = (float) get_post_meta( $a->ID, 'payout', true );
		$items[] = array(
			'product' => get_post_meta( $a->ID, 'product_label', true ) ?: ( get_post_meta( $a->ID, 'service', true ) ?: 'Edelmetaal' ),
			'city'    => get_post_meta( $a->ID, 'city', true ) ?: '',
			'amount'  => $amount ? round( $amount / 10 ) * 10 : 0, // afgerond, privacy
			'ago'     => human_time_diff( get_post_modified_time( 'U', true, $a->ID ) ) ,
		);
	}
	// Demo-modus: representatieve voorbeelden uit catalogus + steden.
	if ( ! $items && get_option( 'xg_live_ticker_demo' ) ) {
		$items = ekinese_live_sales_demo();
	}
	set_transient( 'xg_live_sales', $items, 5 * MINUTE_IN_SECONDS );
	return $items;
}

/** Representatieve voorbeelden (alleen demo). */
function ekinese_live_sales_demo() {
	$products = array( 'Krugerrand 1 oz', 'Gouden ketting', 'Zilverbaar 1 kg', 'Maple Leaf', 'Gouden ring 14k', 'Rolex Datejust', 'Sloopgoud 18k', 'Dukaat' );
	$cities   = array( 'Amsterdam', 'Rotterdam', 'Eindhoven', 'Utrecht', 'Den Haag', 'Tilburg', 'Groningen', 'Breda' );
	$amounts  = array( 320, 540, 760, 1180, 1840, 2950, 480, 95 );
	$out = array();
	for ( $i = 0; $i < 8; $i++ ) {
		$out[] = array(
			'product' => $products[ $i ],
			'city'    => $cities[ $i ],
			'amount'  => $amounts[ $i ],
			'ago'     => ( $i + 1 ) * 7 . ' min',
			'demo'    => true,
		);
	}
	return $out;
}

/** Assets laden (front-end, niet in admin). */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	$sales = ekinese_live_sales();
	if ( ! $sales ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/live-ticker.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-live-ticker', get_theme_file_uri( 'assets/js/live-ticker.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-live-ticker', 'XG_LIVE_SALES', $sales );
	}
} );

/** Instelling: demo-modus aan/uit. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Live-ticker', 'ekinese' ), __( 'Live-ticker', 'ekinese' ), 'manage_options', 'xg-live-ticker', function () {
		if ( isset( $_POST['xg_lt_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_lt_nonce'] ), 'xg_lt' ) ) {
			update_option( 'xg_live_ticker_demo', isset( $_POST['xg_lt_demo'] ) ? 1 : 0 );
			delete_transient( 'xg_live_sales' );
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Live-verkocht-ticker</h1><p>Toont anonieme recente verkopen rechtsonder. Standaard alleen ECHTE afgeronde verkopen.</p><form method="post">';
		wp_nonce_field( 'xg_lt', 'xg_lt_nonce' );
		echo '<p><label><input type="checkbox" name="xg_lt_demo" value="1"' . checked( get_option( 'xg_live_ticker_demo' ), 1, false ) . '> Demo-modus (representatieve voorbeelden tonen zolang er nog geen echte verkopen zijn)</label></p>';
		submit_button();
		echo '</form></div>';
	} );
} );
