<?php
/**
 * XGOUD admin-menu – bundelt alle modules onder één overzichtelijk hoofdmenu,
 * zodat de ~30 databases het WP-menu niet overspoelen. Alle xg_-CPT's komen
 * onder "XGOUD" te staan, gegroepeerd. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Alle eigen CPT's onder het XGOUD-hoofdmenu hangen. */
function ekinese_nest_cpts( $args, $post_type ) {
	if ( 0 === strpos( $post_type, 'xg_' ) && ! empty( $args['show_ui'] ) ) {
		$args['show_in_menu'] = 'xgoud';
	}
	return $args;
}
add_filter( 'register_post_type_args', 'ekinese_nest_cpts', 20, 2 );

/** Hoofdmenu "XGOUD" + dashboard. */
function ekinese_admin_hub() {
	add_menu_page(
		__( 'XGOUD', 'ekinese' ),
		__( 'XGOUD', 'ekinese' ),
		'edit_posts',
		'xgoud',
		'ekinese_admin_hub_page',
		'dashicons-bank',
		3
	);
	add_submenu_page( 'xgoud', __( 'Overzicht', 'ekinese' ), __( 'Overzicht', 'ekinese' ), 'edit_posts', 'xgoud', 'ekinese_admin_hub_page' );
}
add_action( 'admin_menu', 'ekinese_admin_hub', 9 );

/**
 * Groepeer het (lange) XGOUD-submenu met sectiekoppen, zodat het overzichtelijk
 * blijft. Draait laat zodat alle submenu-items al geregistreerd zijn.
 */
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['xgoud'] ) ) {
		return;
	}
	$groups = array(
		'Operatie & klant'      => array( 'xgoud', 'xg-optimizer', 'xg_appointment', 'xg_lead', 'xg_ticket', 'xg_chat', 'xg_question', 'xg_pickup', 'xg_kyc', 'xg_account' ),
		'Fleet'                 => array( 'xg_driver', 'xg_shift', 'xg_expense', 'xg_route', 'xg-fleet-stats', 'xg-plan-route', 'xg-ors' ),
		'Catalogus'             => array( 'xg_product', 'xg_watch', 'xg_gemstone', 'xg_office', 'xg_term', 'xg_inventory' ),
		'Veilingen & community' => array( 'xg_auction', 'xg_market', 'xg_moment', 'xg_deal', 'xg_reward', 'xg_lottery', 'xg_holding', 'xg_wishlist', 'xg_charity', 'xg_notification', 'xg_agent', 'xg_batch' ),
		'Marketing & content'   => array( 'xg_social', 'xg_ad', 'xg_subscriber', 'xg_maillog', 'reviews', 'stad', 'ticker', 'contact', 'vergelijk', 'news', 'xg_survey', 'xg-survey', 'xg-social-insights' ),
		'Financieel & Treuhand' => array( 'xg_escrow', 'xg-pay', 'xg_invoice', 'xg-accounting' ),
		'B2B & HR'              => array( 'xg_partner', 'xg_employee', 'xg_timeentry' ),
		'Instellingen'          => array( 'xg-analytics', 'xg-spot', 'xg-legal', 'xg-smtp', 'xg-ai', 'xg-integrations', 'xg-perf', 'xg-login', 'xg-install' ),
	);
	$buckets = array(); foreach ( $groups as $g => $f ) { $buckets[ $g ] = array(); }
	$buckets['Overig'] = array();
	foreach ( $submenu['xgoud'] as $item ) {
		$slug = isset( $item[2] ) ? (string) $item[2] : '';
		$placed = false;
		foreach ( $groups as $g => $frags ) {
			foreach ( $frags as $fr ) {
				if ( '' !== $fr && false !== strpos( $slug, $fr ) ) { $buckets[ $g ][] = $item; $placed = true; break 2; }
			}
		}
		if ( ! $placed ) { $buckets['Overig'][] = $item; }
	}
	$new = array();
	foreach ( $buckets as $g => $list ) {
		if ( ! $list ) { continue; }
		$new[] = array( '<span class="xg-msep">' . esc_html( $g ) . '</span>', 'read', 'xgoud' );
		foreach ( $list as $it ) { $new[] = $it; }
	}
	$submenu['xgoud'] = array_values( $new );
}, 9999 );

add_action( 'admin_head', function () {
	echo '<style>#adminmenu .wp-submenu a:has(.xg-msep){pointer-events:none;cursor:default;padding-top:12px}#adminmenu .xg-msep{display:block;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#9aa0a6;border-top:1px solid #3a3f44;padding-top:6px;margin-top:2px}</style>';
} );

/** Dashboard met snelkoppelingen, gegroepeerd per domein. */
function ekinese_admin_hub_page() {
	$groups = array(
		'Klanten & verkoop' => array(
			'xg_appointment' => 'Afspraken', 'xg_kyc' => 'KYC / Opkopersregister', 'xg_ticket' => 'Tickets',
			'xg_question' => 'Productvragen', 'xg_pickup' => 'Zendingen', 'xg_route' => 'Routes', 'xg_chat' => 'Chats',
		),
		'AI & meldingen' => array(
			'xg_agent' => 'AI-agents', 'xg_notification' => 'Meldingen',
		),
		'Catalogus' => array(
			'xg_product' => 'Producten', 'xg_watch' => 'Horloges', 'xg_office' => 'Kantoren', 'xg_term' => 'Lexicon',
		),
		'Voorraad & partners' => array(
			'xg_inventory' => 'Voorraad', 'xg_partner' => 'Zakenpartners', 'xg_invoice' => 'Boekhouding',
		),
		'Loyaliteit & community' => array(
			'xg_reward' => 'Punten', 'xg_lottery' => 'Loterijen', 'xg_holding' => 'Portfolio', 'xg_market' => 'Marktplaats', 'xg_deal' => 'Deals (matching)', 'xg_charity_project' => 'Goede doelen',
		),
		'Marketing' => array(
			'xg_social_post' => 'Social media', 'xg_ad' => 'Ads', 'xg_subscriber' => 'Nieuwsbrief', 'xg_maillog' => 'Mail-log',
		),
		'HR' => array(
			'xg_employee' => 'Medewerkers', 'xg_timeentry' => 'Urenregistratie',
		),
	);
	echo '<div class="wrap"><h1>XGOUD – Overzicht</h1>';
	// Operationeel dashboard (prijzen, KPI's, dagelijkse taken, AI & agents).
	if ( function_exists( 'ekinese_dashboard_panels' ) ) {
		ekinese_dashboard_panels();
	}
	echo '<h2 style="margin-top:24px">Statistieken</h2>';
	if ( function_exists( 'ekinese_stats_cards' ) ) {
		ekinese_stats_cards( false );
	}
	echo '<h2 style="margin-top:24px">Beheer per onderdeel</h2>';
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;margin-top:12px">';
	foreach ( $groups as $title => $items ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0;font-size:14px">' . esc_html( $title ) . '</h2><ul style="margin:0">';
		foreach ( $items as $pt => $label ) {
			if ( post_type_exists( $pt ) ) {
				echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=' . $pt ) ) . '">' . esc_html( $label ) . '</a></li>';
			}
		}
		echo '</ul></div>';
	}
	echo '</div>';
	echo '<p style="margin-top:24px"><a class="button button-primary" href="' . esc_url( admin_url( 'tools.php?page=xg-install' ) ) . '">Installatie / setup</a> ';
	echo '<a class="button" href="' . esc_url( admin_url( 'options-general.php?page=xg-integrations' ) ) . '">Integraties (API-sleutels)</a></p>';
	echo '</div>';
}
