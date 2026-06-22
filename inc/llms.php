<?php
/**
 * XGOUD /llms.txt (#17) – gestructureerde site-samenvatting voor AI-crawlers.
 *
 * Serveert een Markdown-bestand op /llms.txt (zoals de IndexNow-keyfile via
 * rewrite) met een beknopte, machine-leesbare beschrijving van het bedrijf,
 * diensten, actuele prijzen en belangrijke pagina's. Gecachet. Geen plugin.
 *
 * @see https://llmstxt.org/
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?xg_llms=1', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'xg_llms';
	return $vars;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'xg_llms' ) ) {
		return;
	}
	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo ekinese_llms_body(); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

/** Bouwt (en cachet) de llms.txt-inhoud. */
function ekinese_llms_body() {
	$cached = get_transient( 'xg_llms_txt' );
	if ( false !== $cached ) {
		return $cached;
	}
	$b    = function_exists( 'ekinese_business' ) ? ekinese_business() : array( 'name' => 'XGOUD' );
	$home = home_url( '/' );
	$lines = array();
	$lines[] = '# ' . ( $b['name'] ?? 'XGOUD' );
	$lines[] = '';
	$lines[] = '> XGOUD koopt edelmetaal, sieraden, diamanten, edelstenen en horloges in tegen een eerlijke, transparante dagprijs. Gratis en verzekerde taxatie, directe uitbetaling, en een vast deel van elke marge gaat naar een goed doel.';
	$lines[] = '';
	$lines[] = '## Diensten';
	$lines[] = '- Goud verkopen (munten, baren, sieraden, sloopgoud)';
	$lines[] = '- Zilver, platina en palladium verkopen';
	$lines[] = '- Diamanten en edelstenen verkopen';
	$lines[] = '- Horloges verkopen (o.a. Rolex)';
	$lines[] = '- Gratis taxatie op afspraak, thuisbezoek en verzekerde ophaalservice';
	$lines[] = '';

	// Actuele dagprijzen (indien beschikbaar).
	if ( function_exists( 'ekinese_metal_spot' ) ) {
		$lines[] = '## Actuele dagprijzen (indicatief, per gram)';
		foreach ( array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' ) as $k => $label ) {
			$spot = (float) ekinese_metal_spot( $k );
			if ( $spot > 0 ) {
				$lines[] = '- ' . $label . ': € ' . number_format( $spot, 2, ',', '.' );
			}
		}
		$lines[] = '';
	}

	$lines[] = '## Belangrijke pagina\'s';
	$lines[] = '- [Home](' . $home . ')';
	$lines[] = '- [Afspraak maken / waarde berekenen](' . home_url( '/afspraak/' ) . ')';
	$lines[] = '- [Dagprijzen](' . home_url( '/dagprijzen/' ) . ')';
	$lines[] = '- [Onze kantoren](' . home_url( '/kantoren/' ) . ')';
	$lines[] = '- [Over ons](' . home_url( '/over-ons/' ) . ')';
	$lines[] = '';

	// Steden (stad-landingpages, self-canonical) toevoegen.
	$cities = get_posts( array( 'post_type' => 'xg_city', 'posts_per_page' => 60, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );
	if ( $cities ) {
		$lines[] = '## Steden';
		foreach ( $cities as $c ) {
			$lines[] = '- [Goud verkopen in ' . $c->post_title . '](' . get_permalink( $c ) . ')';
		}
		$lines[] = '';
	}

	$lines[] = '## Contact';
	$lines[] = '- Telefoon: ' . ( $b['telephone'] ?? '' );
	$lines[] = '- E-mail: ' . ( $b['email'] ?? '' );
	$lines[] = '';
	$lines[] = '> Werkwijze: uitsluitend op afspraak. Wij rekenen met de actuele spotprijs en tonen de marge vooraf. Erkend en geregistreerd (Wwft/KYC). Minimumleeftijd 18 jaar.';

	$body = implode( "\n", $lines ) . "\n";
	set_transient( 'xg_llms_txt', $body, 6 * HOUR_IN_SECONDS );
	return $body;
}

/** Cache legen wanneer relevante content wijzigt. */
add_action( 'save_post_xg_city', function () {
	delete_transient( 'xg_llms_txt' );
} );
