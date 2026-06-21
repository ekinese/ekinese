<?php
/**
 * XGOUD Lexicon – producten, diensten, begrippen, processen.
 * CPT xg_term + categorie-taxonomie + DefinedTerm-schema + seed.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_register_lexicon() {
	register_post_type( 'xg_term', array(
		'labels'      => array( 'name' => __( 'Lexicon', 'ekinese' ), 'singular_name' => __( 'Begrip', 'ekinese' ), 'menu_name' => __( 'Lexicon', 'ekinese' ) ),
		'public'      => true,
		'has_archive' => true,
		'show_in_rest'=> true,
		'menu_icon'   => 'dashicons-book',
		'supports'    => array( 'title', 'editor' ),
		'rewrite'     => array( 'slug' => 'lexicon' ),
	) );
	register_taxonomy( 'xg_term_cat', 'xg_term', array(
		'labels'       => array( 'name' => __( 'Lexicon-categorieën', 'ekinese' ) ),
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
	) );
}
add_action( 'init', 'ekinese_register_lexicon' );

/** DefinedTerm-schema op een lexiconpagina. */
function ekinese_lexicon_schema() {
	if ( ! is_singular( 'xg_term' ) ) {
		return;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array(
		'@context'    => 'https://schema.org',
		'@type'       => 'DefinedTerm',
		'name'        => get_the_title(),
		'description' => wp_strip_all_tags( get_the_excerpt() ),
		'inDefinedTermSet' => home_url( '/lexicon/' ),
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}
add_action( 'wp_head', 'ekinese_lexicon_schema', 21 );

/** Eenmalige seed met basisbegrippen. */
function ekinese_seed_lexicon() {
	$terms = array(
		array( 'Troy ounce', 'Een internationale gewichtseenheid voor edelmetaal, gelijk aan 31,1035 gram.' ),
		array( 'Fineness / zuiverheid', 'Het gehalte aan zuiver edelmetaal, uitgedrukt in duizendsten (bv. 999,9) of karaat.' ),
		array( 'Karaat (goud)', 'Maat voor de zuiverheid van goud; 24 karaat is zuiver goud, 14 karaat is 585/1000.' ),
		array( 'Spotprijs', 'De actuele internationale marktprijs van een edelmetaal per troy ounce of gram.' ),
		array( 'Rapaport', 'De wekelijks bijgewerkte internationale referentieprijslijst voor diamanten.' ),
		array( 'De 4 C\'s', 'Carat, Color, Clarity en Cut — de vier criteria die de waarde van een diamant bepalen.' ),
		array( 'GIA', 'Gemological Institute of America, toonaangevend instituut voor diamantcertificering.' ),
		array( 'Baar', 'Een gegoten of geslagen staaf edelmetaal met een vast gewicht en zuiverheid.' ),
		array( 'Beleggingsmunt', 'Een munt die voornamelijk om zijn edelmetaalwaarde wordt verhandeld, zoals de Krugerrand.' ),
		array( 'Sloopgoud', 'Gebruikt goud (sieraden) dat wordt ingekocht op basis van het zuivere goudgehalte.' ),
		array( 'Taxatie', 'De deskundige waardebepaling van een object op basis van markt en kwaliteit.' ),
		array( 'Thuisbezoek', 'Service waarbij onze expert bij u langskomt voor taxatie en uitbetaling.' ),
	);
	foreach ( $terms as $t ) {
		if ( get_page_by_path( sanitize_title( $t[0] ), OBJECT, 'xg_term' ) ) {
			continue;
		}
		wp_insert_post( array(
			'post_type'    => 'xg_term',
			'post_status'  => 'publish',
			'post_title'   => $t[0],
			'post_name'    => sanitize_title( $t[0] ),
			'post_content' => $t[1],
			'post_excerpt' => $t[1],
		) );
	}
}

function ekinese_lexicon_seed_menu() {
	add_submenu_page( 'edit.php?post_type=xg_term', __( 'Seed', 'ekinese' ), __( 'Basis-seed', 'ekinese' ), 'manage_options', 'xg-lexicon-seed', function () {
		if ( isset( $_POST['xg_lx_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_lx_nonce'] ), 'xg_lx' ) ) {
			ekinese_seed_lexicon();
			echo '<div class="notice notice-success"><p>Basisbegrippen toegevoegd.</p></div>';
		}
		echo '<div class="wrap"><h1>Lexicon seed</h1><form method="post">';
		wp_nonce_field( 'xg_lx', 'xg_lx_nonce' );
		submit_button( 'Basisbegrippen toevoegen' );
		echo '</form></div>';
	} );
}
add_action( 'admin_menu', 'ekinese_lexicon_seed_menu' );
