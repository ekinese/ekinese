<?php
/**
 * XGOUD Statistieken-dashboard.
 *
 * Bundelt de kerncijfers van alle self-built modules (kantoren, producten,
 * horloges, afspraken, tickets, zendingen, prijsalarmen, goede doelen) in één
 * adminoverzicht + dashboard-widget. Geen plugin, gecachet.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kerncijfers verzamelen (5 min gecachet).
 *
 * @return array
 */
function ekinese_stats() {
	$stats = get_transient( 'xg_stats' );
	if ( false !== $stats ) {
		return $stats;
	}
	$count = function ( $type ) {
		$c = wp_count_posts( $type );
		return $c ? (int) ( $c->publish ?? 0 ) : 0;
	};
	$meta_count = function ( $type, $key, $value ) {
		$q = new WP_Query( array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( array( 'key' => $key, 'value' => $value ) ),
		) );
		return (int) $q->found_posts;
	};

	$stats = array(
		'offices'      => $count( 'xg_office' ),
		'products'     => $count( 'xg_product' ),
		'watches'      => $count( 'xg_watch' ),
		'appointments' => $count( 'xg_appointment' ),
		'tickets_open' => $meta_count( 'xg_ticket', 'status', 'open' ),
		'tickets_all'  => $count( 'xg_ticket' ),
		'pickups'      => $count( 'xg_pickup' ),
		'alerts'       => $meta_count( 'xg_price_alert', 'active', '1' ),
		'subscribers'  => $count( 'xg_subscriber' ),
		'lexicon'      => $count( 'xg_term' ),
		'charity'      => function_exists( 'ekinese_charity_total_since' ) ? ekinese_charity_total_since() : 0,
	);
	set_transient( 'xg_stats', $stats, 5 * MINUTE_IN_SECONDS );
	return $stats;
}

/** Cache legen bij relevante wijzigingen. */
function ekinese_stats_flush( $post_id ) {
	$types = array( 'xg_office', 'xg_product', 'xg_watch', 'xg_appointment', 'xg_ticket', 'xg_pickup', 'xg_price_alert', 'xg_subscriber', 'xg_term' );
	if ( in_array( get_post_type( $post_id ), $types, true ) ) {
		delete_transient( 'xg_stats' );
	}
}
add_action( 'save_post', 'ekinese_stats_flush' );
add_action( 'deleted_post', 'ekinese_stats_flush' );

/* =====================================================================
   RENDER
===================================================================== */
function ekinese_stats_cards( $compact = false ) {
	$s   = ekinese_stats();
	$eur = function ( $n ) {
		return '€ ' . number_format_i18n( (float) $n, 2 );
	};
	$cards = array(
		array( __( 'Kantoren', 'ekinese' ), $s['offices'] ),
		array( __( 'Producten', 'ekinese' ), $s['products'] ),
		array( __( 'Horloges', 'ekinese' ), $s['watches'] ),
		array( __( 'Afspraken', 'ekinese' ), $s['appointments'] ),
		array( __( 'Open tickets', 'ekinese' ), $s['tickets_open'] . ' / ' . $s['tickets_all'] ),
		array( __( 'Zendingen', 'ekinese' ), $s['pickups'] ),
		array( __( 'Actieve prijsalarmen', 'ekinese' ), $s['alerts'] ),
		array( __( 'Nieuwsbrief', 'ekinese' ), $s['subscribers'] ),
		array( __( 'Lexicon-items', 'ekinese' ), $s['lexicon'] ),
		array( __( 'Goede doelen (totaal)', 'ekinese' ), $eur( $s['charity'] ) ),
	);
	echo '<div style="display:grid;grid-template-columns:repeat(' . ( $compact ? 2 : 5 ) . ',1fr);gap:12px;margin-top:12px">';
	foreach ( $cards as $c ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:14px"><div style="font-size:22px;font-weight:700;color:#AE1E1E">' . esc_html( $c[1] ) . '</div><div style="font-size:12px;color:#646970">' . esc_html( $c[0] ) . '</div></div>';
	}
	echo '</div>';
}

/* =====================================================================
   ADMIN – dashboard-widget + eigen pagina
===================================================================== */
function ekinese_stats_widget() {
	wp_add_dashboard_widget( 'xg_stats_widget', __( 'XGOUD – overzicht', 'ekinese' ), function () {
		ekinese_stats_cards( true );
	} );
}
add_action( 'wp_dashboard_setup', 'ekinese_stats_widget' );

function ekinese_stats_menu() {
	add_menu_page( __( 'XGOUD statistieken', 'ekinese' ), __( 'XGOUD stats', 'ekinese' ), 'manage_options', 'xg-stats', 'ekinese_stats_page', 'dashicons-chart-bar', 58 );
}
add_action( 'admin_menu', 'ekinese_stats_menu' );

function ekinese_stats_page() {
	echo '<div class="wrap"><h1>' . esc_html__( 'XGOUD statistieken', 'ekinese' ) . '</h1>';
	echo '<p>' . esc_html__( 'Live kerncijfers van alle modules (5 min gecachet).', 'ekinese' ) . '</p>';
	ekinese_stats_cards( false );
	echo '</div>';
}
