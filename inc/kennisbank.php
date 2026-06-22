<?php
/**
 * XGOUD kennisbank (#16) – hub bovenop het bestaande lexicon.
 *
 * Blok ekinese/kennisbank-hub toont kernartikelen + een overzicht van
 * lexiconbegrippen (CPT xg_term, inc/lexicon.php), gegroepeerd per categorie.
 * Voegt Article-schema toe op de hubpagina. De losse artikelen zijn patterns.
 * Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'ekinese/kennisbank-hub', array( 'render_callback' => 'ekinese_render_kennisbank' ) );
} );

/** Kernartikelen van de kennisbank (titel + korte omschrijving + link). */
function ekinese_kennisbank_articles() {
	return apply_filters( 'ekinese_kennisbank_articles', array(
		array( 'title' => 'Echt goud herkennen', 'desc' => 'Stempels, magneettest en zuurtest – zo controleert u of goud echt is.', 'url' => '/kennisbank/echt-goud-herkennen/' ),
		array( 'title' => '14 vs 18 karaat', 'desc' => 'Het verschil in goudgehalte en wat dat betekent voor de waarde.', 'url' => '/kennisbank/14-vs-18-karaat/' ),
		array( 'title' => 'Wat is sloopgoud waard?', 'desc' => 'Hoe oude sieraden en restgoud worden gewogen en getaxeerd.', 'url' => '/kennisbank/sloopgoud-waarde/' ),
		array( 'title' => 'Goudprijs per gram begrijpen', 'desc' => 'Spotprijs, koers en marge helder uitgelegd.', 'url' => '/kennisbank/goudprijs-per-gram/' ),
		array( 'title' => 'Zilver verkopen', 'desc' => 'Munten, baren en bestek – waar u op moet letten bij zilver.', 'url' => '/kennisbank/zilver-verkopen/' ),
		array( 'title' => 'Diamanten taxeren (de 4 C\'s)', 'desc' => 'Carat, color, clarity en cut bepalen samen de waarde.', 'url' => '/kennisbank/diamanten-4c/' ),
	) );
}

/** Render de kennisbank-hub. */
function ekinese_render_kennisbank() {
	ob_start();
	echo '<section class="xg-kb"><div class="xg-container">';
	echo '<p class="xg-eyebrow">Kennisbank</p>';
	echo '<h1>Alles over edelmetaal verkopen</h1>';
	echo '<p class="xg-intro">Praktische uitleg en achtergrond, zodat u goed voorbereid en met een gerust gevoel verkoopt.</p>';

	// Kernartikelen.
	echo '<div class="xg-kb-grid">';
	foreach ( ekinese_kennisbank_articles() as $a ) {
		echo '<a class="xg-kb-card" href="' . esc_url( $a['url'] ) . '"><h3>' . esc_html( $a['title'] ) . '</h3><p>' . esc_html( $a['desc'] ) . '</p><span class="xg-kb-more">Lees meer &rsaquo;</span></a>';
	}
	echo '</div>';

	// Lexicon-begrippen per categorie.
	$cats = get_terms( array( 'taxonomy' => 'xg_term_cat', 'hide_empty' => true ) );
	if ( $cats && ! is_wp_error( $cats ) ) {
		echo '<div class="xg-kb-lexicon"><h2>Begrippenlijst</h2>';
		foreach ( $cats as $cat ) {
			$terms = get_posts( array(
				'post_type'      => 'xg_term',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( array( 'taxonomy' => 'xg_term_cat', 'field' => 'term_id', 'terms' => $cat->term_id ) ),
			) );
			if ( ! $terms ) {
				continue;
			}
			echo '<div class="xg-kb-catgroup"><h4>' . esc_html( $cat->name ) . '</h4><ul class="xg-kb-terms">';
			foreach ( $terms as $t ) {
				echo '<li><a href="' . esc_url( get_permalink( $t ) ) . '">' . esc_html( $t->post_title ) . '</a></li>';
			}
			echo '</ul></div>';
		}
		echo '</div>';
	}

	echo '</div></section>';
	return ob_get_clean();
}

/** Article-schema op de kennisbank-hub en op artikel-pagina's onder /kennisbank/. */
add_action( 'wp_head', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$is_hub     = has_block( 'ekinese/kennisbank-hub', $post );
	$is_article = ( false !== strpos( get_permalink( $post ), '/kennisbank/' ) );
	if ( ! $is_hub && ! $is_article ) {
		return;
	}
	$schema = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => get_the_title( $post ),
		'description'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
		'datePublished' => get_the_date( 'c', $post ),
		'dateModified'  => get_the_modified_date( 'c', $post ),
		'author'        => array( '@type' => 'Organization', 'name' => 'XGOUD' ),
		'publisher'     => array( '@type' => 'Organization', 'name' => 'XGOUD' ),
		'mainEntityOfPage' => get_permalink( $post ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 23 );
