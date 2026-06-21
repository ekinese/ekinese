<?php
/**
 * XGOUD Ads-beheer.
 *
 * Vanuit het backend advertentiecampagnes beheren voor Google, Bing, Facebook
 * en X (Twitter): kop, tekst, budget, doelgroep, status. API-sleutels staan
 * UITSLUITEND in de WP-opties (admin), nooit in de repo. De koppeling naar de
 * ad-platforms is een scaffold (ekinese_ads_sync()); zodra de sleutels en
 * endpoints er zijn, synchroniseert het echt.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_ads_platforms() {
	return array(
		'google'   => 'Google Ads',
		'bing'     => 'Microsoft (Bing) Ads',
		'facebook' => 'Facebook Ads',
		'x'        => 'X (Twitter) Ads',
	);
}

const XG_AD_FIELDS = array( 'platform', 'headline', 'body', 'url', 'budget', 'audience', 'start', 'end', 'status' );

function ekinese_register_ads() {
	register_post_type( 'xg_ad', array(
		'labels'    => array( 'name' => __( 'Advertenties', 'ekinese' ), 'singular_name' => __( 'Advertentie', 'ekinese' ), 'menu_name' => __( 'Ads', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-megaphone',
		'supports'  => array( 'title' ),
	) );
}
add_action( 'init', 'ekinese_register_ads' );

/* ---- Metabox ---- */
function ekinese_ads_metabox() {
	add_meta_box( 'xg_ad_meta', __( 'Campagne', 'ekinese' ), 'ekinese_ads_metabox_html', 'xg_ad', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_ads_metabox' );

function ekinese_ads_metabox_html( $post ) {
	wp_nonce_field( 'xg_ad_save', 'xg_ad_nonce' );
	echo '<table class="form-table">';
	// Platform
	$pl = get_post_meta( $post->ID, 'platform', true );
	echo '<tr><th>Platform</th><td><select name="xga_platform">';
	foreach ( ekinese_ads_platforms() as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $pl, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></td></tr>';
	$txt = function ( $k, $lbl, $type = 'text' ) use ( $post ) {
		echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="' . esc_attr( $type ) . '" name="xga_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
	};
	$txt( 'headline', 'Kop' );
	echo '<tr><th>Tekst</th><td><textarea name="xga_body" rows="3" class="large-text">' . esc_textarea( get_post_meta( $post->ID, 'body', true ) ) . '</textarea></td></tr>';
	$txt( 'url', 'Bestemmings-URL', 'url' );
	$txt( 'budget', 'Budget (€)', 'number' );
	$txt( 'audience', 'Doelgroep' );
	$txt( 'start', 'Startdatum', 'date' );
	$txt( 'end', 'Einddatum', 'date' );
	$st = get_post_meta( $post->ID, 'status', true ) ?: 'draft';
	echo '<tr><th>Status</th><td><select name="xga_status">';
	foreach ( array( 'draft' => 'Concept', 'active' => 'Actief', 'paused' => 'Gepauzeerd', 'ended' => 'Beëindigd' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '</table>';
	echo '<p class="description">' . esc_html__( 'Bij gekoppelde API-sleutels wordt de campagne gesynchroniseerd met het platform.', 'ekinese' ) . '</p>';
}

function ekinese_ads_save( $post_id ) {
	if ( ! isset( $_POST['xg_ad_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_ad_nonce'] ), 'xg_ad_save' ) ) {
		return;
	}
	foreach ( XG_AD_FIELDS as $f ) {
		if ( isset( $_POST[ 'xga_' . $f ] ) ) {
			$val = wp_unslash( $_POST[ 'xga_' . $f ] );
			$val = ( 'body' === $f ) ? sanitize_textarea_field( $val ) : sanitize_text_field( $val );
			update_post_meta( $post_id, $f, $val );
		}
	}
	ekinese_ads_sync( $post_id );
}
add_action( 'save_post_xg_ad', 'ekinese_ads_save' );

/**
 * Campagne synchroniseren met het platform (scaffold — vereist API-sleutel).
 *
 * @return true|WP_Error
 */
function ekinese_ads_sync( $post_id ) {
	$platform = get_post_meta( $post_id, 'platform', true );
	$token    = get_option( 'xg_ads_' . $platform . '_token' );
	if ( ! $token ) {
		return new WP_Error( 'nokey', sprintf( 'Geen API-sleutel voor %s.', $platform ) );
	}
	/** Echte sync-implementatie haakt hier in (per platform). */
	return apply_filters( 'ekinese_ads_sync', new WP_Error( 'todo', 'Ad-platform endpoint nog niet gekoppeld.' ), $post_id, $platform, $token );
}

/* ---- API-sleutels ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=xg_ad', __( 'API-sleutels', 'ekinese' ), __( 'API-sleutels', 'ekinese' ), 'manage_options', 'xg-ads-keys', function () {
		if ( isset( $_POST['xg_ak_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_ak_nonce'] ), 'xg_ak' ) ) {
			foreach ( array_keys( ekinese_ads_platforms() ) as $p ) {
				if ( isset( $_POST[ 'xg_ads_' . $p ] ) ) {
					update_option( 'xg_ads_' . $p . '_token', sanitize_text_field( wp_unslash( $_POST[ 'xg_ads_' . $p ] ) ) );
				}
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Ads — API-sleutels</h1><p>Sleutels staan alleen hier (database), nooit in de themacode.</p><form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_ak', 'xg_ak_nonce' );
		foreach ( ekinese_ads_platforms() as $p => $lbl ) {
			echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="password" name="xg_ads_' . esc_attr( $p ) . '" value="' . esc_attr( get_option( 'xg_ads_' . $p . '_token', '' ) ) . '" class="regular-text"></td></tr>';
		}
		submit_button();
		echo '</table></form></div>';
	} );
} );
