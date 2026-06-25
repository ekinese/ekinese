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
