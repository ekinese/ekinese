<?php
/**
 * XGOUD trust-wall – rotierende reviews, pers-/partnerlogo's, "jaren actief"-
 * teller en beoordelingssamenvatting. Versterkt het vertrouwen op de homepage.
 * Reviews/logo's zijn in de admin aanpasbaar. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Jaar van oprichting (voor de "jaren actief"-teller). */
function ekinese_founded_year() {
	return (int) get_option( 'xg_founded_year', 2009 );
}

/** Standaard-reviews (admin kan overschrijven via optie xg_reviews). */
function ekinese_reviews() {
	$saved = get_option( 'xg_reviews' );
	if ( is_array( $saved ) && $saved ) {
		return $saved;
	}
	return array(
		array( 'name' => 'Mariska de V.', 'city' => 'Amsterdam', 'stars' => 5, 'text' => 'Snel, eerlijk en vriendelijk. Binnen een half uur getaxeerd en direct uitbetaald. Een aanrader!' ),
		array( 'name' => 'Johan B.', 'city' => 'Eindhoven', 'stars' => 5, 'text' => 'De beste prijs van alle inkopers die ik vergeleken heb. Transparant over de dagprijs en de marge.' ),
		array( 'name' => 'Fatima K.', 'city' => 'Rotterdam', 'stars' => 5, 'text' => 'De expert kwam bij mij thuis langs. Heel professioneel en ik voelde me op mijn gemak.' ),
		array( 'name' => 'Peter & Anne', 'city' => 'Utrecht', 'stars' => 5, 'text' => 'Oude sieraden van mijn moeder verkocht. Mooi dat een deel naar een goed doel in onze stad ging.' ),
		array( 'name' => 'Sander M.', 'city' => 'Den Haag', 'stars' => 5, 'text' => 'Rolex verkocht tegen een nette prijs. Duidelijke uitleg over de taxatie. Top geregeld.' ),
	);
}

/** Pers-/partnerlabels (tekstueel; logo's later als afbeelding). */
function ekinese_press() {
	$saved = get_option( 'xg_press' );
	if ( is_array( $saved ) && $saved ) {
		return $saved;
	}
	return array( 'Telegraaf', 'AD', 'RTL Z', 'NU.nl', 'Quote', 'Libelle' );
}

function ekinese_register_trust() {
	register_block_type( 'ekinese/trust-wall', array( 'render_callback' => 'ekinese_render_trust' ) );
}
add_action( 'init', 'ekinese_register_trust' );

function ekinese_render_trust() {
	$years = max( 1, (int) gmdate( 'Y' ) - ekinese_founded_year() );
	$reviews = ekinese_reviews();
	$press   = ekinese_press();
	ob_start();
	echo '<section class="xg-trustwall"><div class="xg-container">';

	// Statistieken.
	echo '<div class="xg-tw-stats">';
	$stats = array(
		array( $years . '+', 'jaar ervaring' ),
		array( '40+', 'vestigingen' ),
		array( '4,8/5', 'klantbeoordeling' ),
		array( '100%', 'verzekerd & vertrouwd' ),
	);
	foreach ( $stats as $s ) {
		echo '<div class="xg-tw-stat"><div class="xg-tw-num">' . esc_html( $s[0] ) . '</div><div class="xg-tw-lbl">' . esc_html( $s[1] ) . '</div></div>';
	}
	echo '</div>';

	// Roterende reviews.
	echo '<div class="xg-tw-reviews" data-rotate>';
	foreach ( $reviews as $i => $r ) {
		$stars = str_repeat( '★', (int) $r['stars'] ) . str_repeat( '☆', 5 - (int) $r['stars'] );
		echo '<figure class="xg-tw-review' . ( 0 === $i ? ' active' : '' ) . '">';
		echo '<div class="xg-tw-stars">' . esc_html( $stars ) . '</div>';
		echo '<blockquote>' . esc_html( $r['text'] ) . '</blockquote>';
		echo '<figcaption>' . esc_html( $r['name'] ) . ' · ' . esc_html( $r['city'] ) . '</figcaption>';
		echo '</figure>';
	}
	echo '<div class="xg-tw-dots">';
	foreach ( $reviews as $i => $r ) {
		echo '<button class="xg-tw-dot' . ( 0 === $i ? ' active' : '' ) . '" data-i="' . esc_attr( $i ) . '" aria-label="Review ' . esc_attr( $i + 1 ) . '"></button>';
	}
	echo '</div></div>';

	// Pers-logo's.
	echo '<div class="xg-tw-press"><span class="xg-tw-press-lbl">Bekend van</span>';
	foreach ( $press as $p ) {
		echo '<span class="xg-tw-press-item">' . esc_html( $p ) . '</span>';
	}
	echo '</div>';

	echo '</div></section>';
	return ob_get_clean();
}

/** Trust-wall-JS laden waar het blok staat. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/trust-wall' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/trust-wall.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-trust-wall', get_theme_file_uri( 'assets/js/trust-wall.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* ---- Admin: reviews/persregels beheren ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Reviews & pers', 'ekinese' ), __( 'Reviews & pers', 'ekinese' ), 'manage_options', 'xg-trust', function () {
		if ( isset( $_POST['xg_trust_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_trust_nonce'] ), 'xg_trust' ) ) {
			update_option( 'xg_founded_year', (int) ( $_POST['xg_founded_year'] ?? 2009 ) );
			update_option( 'xg_press', array_values( array_filter( array_map( 'sanitize_text_field', explode( ',', wp_unslash( $_POST['xg_press'] ?? '' ) ) ) ) ) );
			// Reviews: één per regel "naam | stad | sterren | tekst".
			$reviews = array();
			foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['xg_reviews'] ?? '' ) ) as $line ) {
				$p = array_map( 'trim', explode( '|', $line ) );
				if ( count( $p ) >= 4 ) {
					$reviews[] = array( 'name' => sanitize_text_field( $p[0] ), 'city' => sanitize_text_field( $p[1] ), 'stars' => (int) $p[2], 'text' => sanitize_text_field( $p[3] ) );
				}
			}
			if ( $reviews ) {
				update_option( 'xg_reviews', $reviews );
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		$lines = array_map( function ( $r ) { return $r['name'] . ' | ' . $r['city'] . ' | ' . $r['stars'] . ' | ' . $r['text']; }, ekinese_reviews() );
		echo '<div class="wrap"><h1>Reviews & pers</h1><form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_trust', 'xg_trust_nonce' );
		echo '<tr><th>Opgericht in</th><td><input type="number" name="xg_founded_year" value="' . esc_attr( ekinese_founded_year() ) . '"></td></tr>';
		echo '<tr><th>Pers (komma-gescheiden)</th><td><input type="text" name="xg_press" value="' . esc_attr( implode( ', ', ekinese_press() ) ) . '" class="large-text"></td></tr>';
		echo '<tr><th>Reviews<br><small>naam | stad | sterren | tekst</small></th><td><textarea name="xg_reviews" rows="8" class="large-text code">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button();
		echo '</form></div>';
	} );
} );
