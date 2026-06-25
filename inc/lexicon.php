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

/**
 * Listings (data/listings.csv) → lexicon-entries onder categorie "Locaties".
 *
 * Bron is de listdom-export (steden met rijke SEO-tekst). Provincie = Category
 * wordt een xg_term_cat-term. Upsert per slug. URL-namespace /lexicon/ staat los
 * van /kantoren/, dus geen botsing met de kantorenpagina's.
 *
 * @return int Aantal verwerkte entries.
 */
function ekinese_import_lexicon_listings() {
	$file = get_theme_file_path( 'data/listings.csv' );
	if ( ! file_exists( $file ) || ! ( $fp = fopen( $file, 'r' ) ) ) { // phpcs:ignore
		return 0;
	}
	$header = null;
	$count  = 0;
	while ( ( $row = fgetcsv( $fp, 0, ',' ) ) !== false ) {
		if ( null === $header ) {
			$header    = $row;
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
			continue;
		}
		$r = @array_combine( $header, $row ); // phpcs:ignore
		if ( ! $r || empty( $r['Slug'] ) || empty( $r['Title'] ) ) {
			continue;
		}
		$slug    = sanitize_title( $r['Slug'] );
		$title   = sanitize_text_field( $r['Title'] );
		$excerpt = wp_strip_all_tags( (string) ( $r['Excerpt'] ?? '' ) );

		$existing = get_page_by_path( $slug, OBJECT, 'xg_term' );
		$postarr  = array(
			'post_type'    => 'xg_term',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => wp_kses_post( (string) ( $r['Description'] ?? '' ) ),
			'post_excerpt' => mb_substr( $excerpt, 0, 300 ),
		);
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
		}
		$id = wp_insert_post( $postarr );
		if ( is_wp_error( $id ) ) {
			continue;
		}
		// Provincie als categorie (hiërarchisch), plus overkoepelende term "Locaties".
		$cats = array( 'Locaties' );
		if ( ! empty( $r['Category'] ) ) {
			$cats[] = sanitize_text_field( $r['Category'] );
		}
		wp_set_object_terms( $id, $cats, 'xg_term_cat', false );
		$count++;
	}
	fclose( $fp );
	return $count;
}

/**
 * Verwijdert de stad-/locatie-entries uit het lexicon (alles in de term
 * "Locaties"). De 12 echte vakbegrippen blijven staan. Steden horen alleen
 * onder Kantoren thuis, niet dubbel in het lexicon. Naar prullenbak.
 *
 * @return int Aantal verplaatst.
 */
function ekinese_lexicon_remove_locations() {
	$term = get_term_by( 'name', 'Locaties', 'xg_term_cat' );
	if ( ! $term ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'   => 'xg_term',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'taxonomy' => 'xg_term_cat', 'field' => 'term_id', 'terms' => $term->term_id ),
			),
		)
	);
	$n = 0;
	foreach ( $ids as $id ) {
		if ( wp_trash_post( (int) $id ) ) {
			$n++;
		}
	}
	return $n;
}

function ekinese_lexicon_seed_menu() {
	add_submenu_page( 'edit.php?post_type=xg_term', __( 'Seed', 'ekinese' ), __( 'Basis-seed', 'ekinese' ), 'manage_options', 'xg-lexicon-seed', function () {
		if ( isset( $_POST['xg_lx_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_lx_nonce'] ), 'xg_lx' ) ) {
			if ( isset( $_POST['xg_lx_remove'] ) ) {
				$n = ekinese_lexicon_remove_locations();
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( '%d locatie-entries uit het lexicon verwijderd (naar prullenbak).', $n ) ) . '</p></div>';
			} else {
				ekinese_seed_lexicon();
				echo '<div class="notice notice-success"><p>Basisbegrippen toegevoegd.</p></div>';
			}
		}
		echo '<div class="wrap"><h1>Lexicon seed</h1>';
		echo '<form method="post" style="margin-bottom:24px">';
		wp_nonce_field( 'xg_lx', 'xg_lx_nonce' );
		submit_button( 'Basisbegrippen toevoegen' );
		echo '</form>';
		echo '<form method="post"><h2>Locaties uit lexicon verwijderen</h2><p>Steden horen alleen onder <strong>Kantoren</strong>, niet dubbel in het lexicon. Hiermee verwijder je alle stad-entries (term "Locaties") uit het lexicon — de vakbegrippen blijven staan.</p>';
		wp_nonce_field( 'xg_lx', 'xg_lx_nonce' );
		echo '<input type="hidden" name="xg_lx_remove" value="1">';
		submit_button( 'Locaties uit lexicon verwijderen', 'delete' );
		echo '</form></div>';
	} );
}
add_action( 'admin_menu', 'ekinese_lexicon_seed_menu' );
