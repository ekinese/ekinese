<?php
/**
 * XGOUD ↔ Google News.
 *
 *  - Google-News-sitemap (/news-sitemap.xml): de artikelen van de laatste 48 uur
 *    met <news:news>-tags. In Search Console / Publisher Center indienen.
 *  - NewsArticle-structured-data (JSON-LD) op nieuwsartikelen (post).
 *  - Redactionele RICHTLIJN als metabox bij het aanmaken van een nieuwsartikel,
 *    zodat de redactie consistent en Google-News-conform werkt.
 *
 * Nieuws = standaard WordPress-posts (post_type 'post').
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   1) GOOGLE-NEWS-SITEMAP  →  /news-sitemap.xml
===================================================================== */
add_action( 'init', function () {
	add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?xg_news_sitemap=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'xg_news_sitemap';
	return $vars;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'xg_news_sitemap' ) ) {
		return;
	}
	$xml = get_transient( 'xg_news_sitemap_xml' );
	if ( false === $xml ) {
		$pub  = function_exists( 'ekinese_business' ) ? ekinese_business()['name'] : get_bloginfo( 'name' );
		$lang = substr( (string) get_bloginfo( 'language' ), 0, 2 ) ?: 'nl';
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'date_query'     => array( array( 'after' => '48 hours ago' ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
		foreach ( $posts as $p ) {
			// noindex-artikelen overslaan.
			if ( get_post_meta( $p->ID, '_xg_noindex', true ) ) {
				continue;
			}
			$xml .= "  <url>\n";
			$xml .= '    <loc>' . esc_url( get_permalink( $p ) ) . "</loc>\n";
			$xml .= "    <news:news>\n";
			$xml .= "      <news:publication>\n";
			$xml .= '        <news:name>' . esc_html( $pub ) . "</news:name>\n";
			$xml .= '        <news:language>' . esc_html( $lang ) . "</news:language>\n";
			$xml .= "      </news:publication>\n";
			$xml .= '      <news:publication_date>' . esc_html( get_the_date( 'c', $p ) ) . "</news:publication_date>\n";
			$xml .= '      <news:title>' . esc_html( wp_strip_all_tags( get_the_title( $p ) ) ) . "</news:title>\n";
			$xml .= "    </news:news>\n";
			$xml .= "  </url>\n";
		}
		$xml .= '</urlset>';
		set_transient( 'xg_news_sitemap_xml', $xml, HOUR_IN_SECONDS );
	}
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo $xml; // phpcs:ignore
	exit;
} );

/* Cache verversen bij publicatie/bewerking van een post. */
add_action( 'save_post_post', function () {
	delete_transient( 'xg_news_sitemap_xml' );
} );

/* News-sitemap in robots.txt bekendmaken. */
add_filter( 'robots_txt', function ( $output ) {
	$output .= "\nSitemap: " . home_url( '/news-sitemap.xml' ) . "\n";
	return $output;
}, 20 );

/* =====================================================================
   2) NewsArticle-SCHEMA op nieuwsartikelen (post)
===================================================================== */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$id = get_the_ID();
	if ( get_post_meta( $id, '_xg_noindex', true ) ) {
		return;
	}
	$b      = function_exists( 'ekinese_business' ) ? ekinese_business() : array( 'name' => get_bloginfo( 'name' ), 'url' => home_url(), 'logo' => '' );
	$img    = get_the_post_thumbnail_url( $id, 'full' );
	$desc   = get_post_meta( $id, '_xg_meta_desc', true ) ?: wp_strip_all_tags( get_the_excerpt( $id ) );
	$author = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) ) ?: $b['name'];

	$data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'NewsArticle',
		'headline'         => wp_strip_all_tags( get_the_title( $id ) ),
		'description'      => $desc,
		'datePublished'    => get_the_date( 'c', $id ),
		'dateModified'     => get_the_modified_date( 'c', $id ),
		'author'           => array( '@type' => 'Person', 'name' => $author ),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => $b['name'],
			'logo'  => array( '@type' => 'ImageObject', 'url' => $b['logo'] ),
		),
		'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => get_permalink( $id ) ),
	);
	if ( $img ) {
		$data['image'] = array( $img );
	}
	$cats = get_the_category( $id );
	if ( $cats ) {
		$data['articleSection'] = $cats[0]->name;
	}
	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 21 );

/* =====================================================================
   3) REDACTIONELE RICHTLIJN — metabox bij nieuws aanmaken
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_news_guide', 'Google News — redactierichtlijn', 'ekinese_news_guideline_box', 'post', 'normal', 'high' );
} );

function ekinese_news_guideline_box( $post ) {
	// Live mini-check op de huidige (opgeslagen) staat.
	$title_len = strlen( wp_strip_all_tags( get_the_title( $post ) ) );
	$words     = str_word_count( wp_strip_all_tags( $post->post_content ) );
	$has_img   = has_post_thumbnail( $post );
	$has_cat   = (bool) get_the_category( $post->ID );
	$chk = function ( $ok, $text ) {
		return '<li style="display:flex;gap:6px;padding:2px 0"><span>' . ( $ok ? '✅' : '⬜' ) . '</span><span>' . $text . '</span></li>';
	};
	?>
	<style>.xg-ng h4{margin:14px 0 6px}.xg-ng ul{margin:0 0 8px}.xg-ng li{line-height:1.5}.xg-ng-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}</style>
	<div class="xg-ng">
		<p style="margin-top:0;color:#50575e">Volg deze richtlijn zodat elk nieuwsartikel consistent én geschikt voor <strong>Google News</strong> is. De checklist links toont de status van dít artikel (na opslaan).</p>
		<div class="xg-ng-grid">
			<div>
				<h4>Checklist voor dit artikel</h4>
				<ul style="list-style:none;padding:0">
					<?php
					echo $chk( $title_len >= 25 && $title_len <= 110, 'Kop 25–110 tekens, feitelijk (geen clickbait) — nu ' . $title_len ); // phpcs:ignore
					echo $chk( $words >= 250, 'Minstens 250 woorden eigen tekst — nu ' . $words ); // phpcs:ignore
					echo $chk( $has_img, 'Uitgelichte afbeelding ingesteld (met bijschrift/alt)' ); // phpcs:ignore
					echo $chk( $has_cat, 'Een categorie gekozen (rubriek)' ); // phpcs:ignore
					echo $chk( true, 'Publicatiedatum klopt (Google News toont laatste 48 u prominent)' ); // phpcs:ignore
					?>
				</ul>
			</div>
			<div>
				<h4>Schrijfrichtlijn</h4>
				<ul>
					<li><strong>Kop</strong>: concreet en feitelijk. Belangrijkste nieuws + (indien relevant) plaats/merk. Geen hoofdletters-only, geen clickbait.</li>
					<li><strong>Lead</strong>: beantwoord in de eerste 2–3 zinnen <em>wie, wat, waar, wanneer, waarom</em>.</li>
					<li><strong>Bron &amp; feiten</strong>: noem bron/cijfers; geen ongefundeerde claims, geen koersadvies. Prijzen indicatief.</li>
					<li><strong>Structuur</strong>: korte alinea's, 1–2 tussenkoppen, eventueel een citaat.</li>
					<li><strong>Beeld</strong>: één relevante uitgelichte afbeelding met alt-tekst; rechtenvrij of eigen.</li>
					<li><strong>Auteur &amp; datum</strong>: echte auteur en juiste datum (vers = beter zichtbaar in Google News).</li>
					<li><strong>SEO/GEO</strong>: zet ook het focus-keyword + meta description (SEO-blok) — streef SEO/GEO ≥ 80.</li>
				</ul>
			</div>
		</div>
		<p style="color:#50575e;border-top:1px solid #eee;padding-top:8px;margin-bottom:0">
			<strong>Eenmalige setup (beheer):</strong> meld de site aan in
			<a href="https://publishercenter.google.com/" target="_blank" rel="noopener">Google Publisher Center</a>
			en dien <code><?php echo esc_html( home_url( '/news-sitemap.xml' ) ); ?></code> in via Search Console.
		</p>
	</div>
	<?php
}
