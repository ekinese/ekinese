<?php
/**
 * XGOUD page-editor tools:
 *  - SEO/GEO-analyzer: live score + checklist naast de editor (titel, meta,
 *    koppen, woorden, interne links, alt-teksten, lokale/GEO-signalen, schema).
 *  - AI-tekstgenerator: prompt → Claude schrijft NL webtekst → direct invoegen.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   METABOXEN (post + page)
===================================================================== */
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'post', 'page' ) as $pt ) {
		add_meta_box( 'xg_seo_analyzer', __( 'SEO / GEO-analyse', 'ekinese' ), 'ekinese_seo_analyzer_box', $pt, 'side', 'default' );
		add_meta_box( 'xg_ai_writer', __( 'AI-tekstgenerator', 'ekinese' ), 'ekinese_ai_writer_box', $pt, 'side', 'default' );
	}
} );

function ekinese_seo_analyzer_box() {
	echo '<div id="xg-seo-analyzer"><p class="description">Analyse wordt geladen…</p></div>';
}

function ekinese_ai_writer_box() {
	if ( ! function_exists( 'ekinese_ai_enabled' ) || ! ekinese_ai_enabled() ) {
		echo '<p class="description">Verbind eerst Claude onder <a href="' . esc_url( admin_url( 'admin.php?page=xg-ai' ) ) . '">XGOUD → AI-assistent</a>.</p>';
		return;
	}
	$nonce = wp_create_nonce( 'xg_ai_gen' );
	echo '<div id="xg-ai-writer" data-nonce="' . esc_attr( $nonce ) . '">';
	echo '<textarea id="xg-ai-prompt" rows="3" style="width:100%" placeholder="Bijv. Schrijf een wervende intro voor de pagina “Goud verkopen in Utrecht” (2 alinea’s, lokaal, met CTA)."></textarea>';
	echo '<p><button type="button" class="button button-primary" id="xg-ai-gen">Genereer tekst</button></p>';
	echo '<div id="xg-ai-gen-out" style="display:none"><textarea id="xg-ai-gen-text" rows="8" style="width:100%"></textarea>';
	echo '<p><button type="button" class="button" id="xg-ai-insert">In editor invoegen</button></p></div>';
	echo '</div>';
}

/* =====================================================================
   AJAX – tekst genereren (Claude)
===================================================================== */
add_action( 'wp_ajax_xg_ai_gen', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'xg_ai_gen', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Niet toegestaan.' ), 403 );
	}
	$prompt = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
	if ( '' === trim( $prompt ) ) {
		wp_send_json_error( array( 'message' => 'Lege prompt.' ), 400 );
	}
	if ( ! function_exists( 'ekinese_admin_ai_call' ) ) {
		wp_send_json_error( array( 'message' => 'AI niet beschikbaar.' ), 400 );
	}
	$system = 'Je bent een Nederlandstalige SEO-copywriter voor XGOUD, een betrouwbare opkoper van goud, zilver, edelmetaal, diamanten en horloges. '
		. 'Schrijf bondige, wervende en correcte webtekst, conversiegericht en lokaal waar relevant (GEO). Gebruik waar passend tussenkoppen. '
		. 'Geen verzonnen feiten, geen keiharde prijzen (altijd indicatief, definitief na taxatie). Lever platte tekst met alinea\'s.';
	$reply = ekinese_admin_ai_call( $system, $prompt );
	if ( is_wp_error( $reply ) ) {
		wp_send_json_error( array( 'message' => $reply->get_error_message() ), 400 );
	}
	wp_send_json_success( array( 'text' => $reply ) );
} );

/* =====================================================================
   SERVER-SCORE (mirror van de JS-analyzer) → kolom in pages/posts
===================================================================== */
/** Bereken SEO- en GEO-score (0–100) voor een post. */
function ekinese_seo_compute_score( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array( 'seo' => 0, 'geo' => 0 );
	}
	$content  = (string) $post->post_content;
	$plain    = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $content ) ) );
	$lc       = strtolower( $plain );
	$words    = $plain ? count( preg_split( '/\s+/', $plain ) ) : 0;
	$title    = get_post_meta( $post->ID, '_xg_seo_title', true ) ?: wp_strip_all_tags( get_the_title( $post ) );
	$meta     = (string) get_post_meta( $post->ID, '_xg_meta_desc', true );
	$kw       = strtolower( trim( (string) get_post_meta( $post->ID, '_xg_focus_keyword', true ) ) );
	$slug     = (string) $post->post_name;

	$cities = array( 'amsterdam', 'rotterdam', 'den haag', 'utrecht', 'eindhoven', 'tilburg', 'groningen', 'breda', 'nijmegen', 'arnhem', 'haarlem', 'zwolle', 'maastricht', 'antwerpen', 'brussel', 'gent', 'nederland', 'belgië', 'belgie' );
	$power  = array( 'gratis', 'direct', 'beste', 'veilig', 'snel', 'eenvoudig', 'vandaag', 'betrouwbaar', 'hoogste', 'expert', 'gegarandeerd', 'eerlijk', 'voordelig', 'exclusief' );

	$h2      = preg_match_all( '/<h2/i', $content ) + preg_match_all( '/"level":2/', $content );
	$subs    = $h2 + preg_match_all( '/<h3/i', $content ) + preg_match_all( '/"level":3/', $content );
	$qheads  = preg_match_all( '/<h[2-4][^>]*>[^<]*\?[^<]*<\/h[2-4]>/i', $content );
	$int     = preg_match_all( '/href="\/[^"]*"/', $content ) + preg_match_all( '#href="https?://[^"]*xgoud#i', $content );
	$ext     = preg_match_all( '#href="https?://(?![^"]*xgoud)[^"]*"#i', $content );
	$imgs    = preg_match_all( '/<img/i', $content );
	$alts    = preg_match_all( '/<img[^>]*alt="[^"]+"/i', $content );
	$kwcount = $kw ? preg_match_all( '/' . preg_quote( $kw, '/' ) . '/i', $lc ) : 0;
	$density = ( $kw && $words ) ? $kwcount / $words * 100 : 0;
	$first10 = substr( $lc, 0, max( 120, (int) ( strlen( $lc ) * 0.1 ) ) );
	$heads   = strtolower( implode( ' ', (array) ( preg_match_all( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/i', $content, $mm ) ? $mm[1] : array() ) ) );
	$geo_loc = false;
	foreach ( $cities as $c ) { if ( false !== strpos( $lc, $c ) ) { $geo_loc = true; break; } }
	$schema  = (bool) preg_match( '#wp:ekinese/(faq|offices|price|seo-index)#', $content ) || (bool) preg_match( '/FAQPage|LocalBusiness/', $content );
	$media   = $imgs > 0 || (bool) preg_match( '/<iframe|wp:video|wp:embed|xg-lyt/', $content );

	// SEO-checks [pass, weight].
	$seo = array();
	if ( $kw ) {
		$seo[] = array( false !== strpos( strtolower( $title ), $kw ), 4 );
		$seo[] = array( false !== strpos( strtolower( $meta ), $kw ), 3 );
		$seo[] = array( false !== strpos( $slug, str_replace( ' ', '-', $kw ) ) || false !== strpos( $slug, strtok( $kw, ' ' ) ), 3 );
		$seo[] = array( false !== strpos( $first10, $kw ), 3 );
		$seo[] = array( $kwcount > 0, 3 );
		$seo[] = array( 0 === strpos( strtolower( trim( $title ) ), $kw ), 1 );
		$seo[] = array( false !== strpos( $heads, $kw ), 2 );
		$seo[] = array( $density >= 0.5 && $density <= 2.5, 2 );
	} else {
		$seo[] = array( false, 8 ); // geen keyword ingesteld
	}
	$seo[] = array( $words >= 600, 3 );
	$seo[] = array( strlen( $title ) >= 30 && strlen( $title ) <= 60, 2 );
	$seo[] = array( $int >= 1, 2 );
	$seo[] = array( $ext >= 1, 1 );
	$seo[] = array( 0 === $imgs || $alts >= $imgs, 2 );
	$seo[] = array( $subs >= 2, 2 );
	$seo[] = array( strlen( $slug ) > 0 && strlen( $slug ) <= 75, 1 );
	$pw = false; foreach ( $power as $p ) { if ( false !== strpos( strtolower( $title ), $p ) ) { $pw = true; break; } }
	$seo[] = array( $pw, 1 );

	// GEO-checks.
	$geo = array(
		array( $geo_loc, 2 ),
		array( $schema, 3 ),
		array( $words >= 300, 2 ),
		array( $qheads >= 1, 2 ),
		array( strlen( $meta ) >= 70 && strlen( $meta ) <= 160, 1 ),
		array( $media, 1 ),
	);

	$calc = function ( $list ) {
		$w = 0; $p = 0;
		foreach ( $list as $c ) { $w += $c[1]; if ( $c[0] ) { $p += $c[1]; } }
		return $w ? (int) round( $p / $w * 100 ) : 0;
	};
	return array( 'seo' => $calc( $seo ), 'geo' => $calc( $geo ) );
}

/** Score opslaan bij elke save. */
add_action( 'save_post', function ( $post_id, $post ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'post', 'page', 'xg_product', 'xg_watch', 'xg_gemstone' ), true ) ) {
		return;
	}
	$s = ekinese_seo_compute_score( $post );
	update_post_meta( $post_id, '_xg_seo_score', $s['seo'] );
	update_post_meta( $post_id, '_xg_geo_score', $s['geo'] );
}, 99, 2 );

/** Kolom SEO/GEO in de lijst van pages & posts. */
function ekinese_seo_score_badge( $score ) {
	$score = (int) $score;
	$color = $score >= 80 ? '#1f9d55' : ( $score >= 50 ? '#dba617' : '#d63638' );
	return '<span style="display:inline-block;min-width:34px;text-align:center;background:' . $color . ';color:#fff;font-weight:700;padding:2px 6px;border-radius:3px">' . $score . '</span>';
}
foreach ( array( 'post', 'page' ) as $pt ) {
	add_filter( "manage_{$pt}_posts_columns", function ( $cols ) {
		$cols['xg_seo_score'] = 'SEO / GEO';
		return $cols;
	} );
	add_action( "manage_{$pt}_posts_custom_column", function ( $col, $post_id ) {
		if ( 'xg_seo_score' !== $col ) {
			return;
		}
		$seo = get_post_meta( $post_id, '_xg_seo_score', true );
		$geo = get_post_meta( $post_id, '_xg_geo_score', true );
		if ( '' === $seo && '' === $geo ) {
			$s   = ekinese_seo_compute_score( $post_id );
			$seo = $s['seo']; $geo = $s['geo'];
		}
		echo ekinese_seo_score_badge( $seo ) . ' &nbsp; ' . ekinese_seo_score_badge( $geo ); // phpcs:ignore
	}, 10, 2 );
}

/* =====================================================================
   ASSETS – alleen op de post/page-editor
===================================================================== */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/seo-editor.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-seo-editor', get_theme_file_uri( 'assets/js/seo-editor.js' ), array( 'wp-data', 'wp-blocks', 'wp-dom-ready' ), (string) filemtime( $js ), true );
	}
} );
