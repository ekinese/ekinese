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

/** Dashboard met snelkoppelingen, gegroepeerd per domein. */
function ekinese_admin_hub_page() {
	$groups = array(
		'Klanten & verkoop' => array(
			'xg_appointment' => 'Afspraken', 'xg_kyc' => 'KYC / Opkopersregister', 'xg_ticket' => 'Tickets',
			'xg_pickup' => 'Zendingen', 'xg_route' => 'Routes', 'xg_chat' => 'Chats',
		),
		'Catalogus' => array(
			'xg_product' => 'Producten', 'xg_watch' => 'Horloges', 'xg_office' => 'Kantoren', 'xg_term' => 'Lexicon',
		),
		'Voorraad & partners' => array(
			'xg_inventory' => 'Voorraad', 'xg_partner' => 'Zakenpartners', 'xg_invoice' => 'Boekhouding',
		),
		'Loyaliteit & community' => array(
			'xg_reward' => 'Punten', 'xg_lottery' => 'Loterijen', 'xg_holding' => 'Portfolio', 'xg_deal' => 'Marktplaats', 'xg_charity_project' => 'Goede doelen',
		),
		'Marketing' => array(
			'xg_social_post' => 'Social media', 'xg_ad' => 'Ads', 'xg_subscriber' => 'Nieuwsbrief', 'xg_maillog' => 'Mail-log',
		),
		'HR' => array(
			'xg_employee' => 'Medewerkers', 'xg_timeentry' => 'Urenregistratie',
		),
	);
	echo '<div class="wrap"><h1>XGOUD – Overzicht</h1>';
	if ( function_exists( 'ekinese_stats_cards' ) ) {
		ekinese_stats_cards( false );
	}
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;margin-top:24px">';
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
