<?php
/**
 * XGOUD foto-taxatie (#11) – Claude vision herkent het object op een foto.
 *
 * Blok ekinese/photo-appraisal: de klant uploadt een foto; Claude (vision)
 * beschrijft wat het waarschijnlijk is (type sieraad/munt/horloge, vermoedelijk
 * metaal/karaat) en geeft een indicatieve richting. Werkt zodra de Anthropic-key
 * is ingevuld (gedeeld met de AI-assistent, inc/ai-assistant.php); zonder key
 * toont het blok netjes "binnenkort beschikbaar" + verwijst naar de rekenaar.
 *
 * Self-built (directe Messages-API met image-content), geen plugin/SDK.
 * Beelden worden NIET permanent opgeslagen — alleen doorgestuurd voor analyse.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'ekinese/photo-appraisal', array( 'render_callback' => 'ekinese_render_photo_appraisal' ) );
} );

/** Toegestane afbeeldingstypes (zoals chat-upload). */
function ekinese_photo_appraisal_mimes() {
	return array( 'image/jpeg', 'image/png', 'image/webp' );
}

/** Render het foto-taxatie-blok. */
function ekinese_render_photo_appraisal() {
	$enabled = function_exists( 'ekinese_ai_enabled' ) && ekinese_ai_enabled();
	$rest    = esc_url_raw( rest_url( 'ekinese/v1/photo-appraisal' ) );
	ob_start();
	echo '<section class="xg-photo"><div class="xg-container"><div class="xg-photo-box" data-rest="' . esc_attr( $rest ) . '">';
	echo '<h3>Foto-taxatie</h3>';
	if ( $enabled ) {
		echo '<p>Upload een duidelijke foto van uw sieraad, munt of horloge. Onze AI geeft direct een eerste, indicatieve inschatting. Voor de definitieve prijs taxeert onze expert gratis.</p>';
		echo '<label class="xg-photo-drop"><input type="file" class="xg-photo-file" accept="image/jpeg,image/png,image/webp"><span>Kies of sleep een foto hierheen</span></label>';
		echo '<button type="button" class="xg-photo-btn" disabled>Analyseer foto</button>';
		echo '<div class="xg-photo-result" hidden></div>';
	} else {
		echo '<p class="xg-photo-soon">Foto-taxatie is <strong>binnenkort beschikbaar</strong>. Gebruik nu onze rekentool voor een directe indicatie, of maak een gratis afspraak.</p>';
		echo '<p><a class="xg-btn-gold" href="/afspraak/">Bereken uw waarde</a></p>';
	}
	echo '</div></div></section>';
	return ob_get_clean();
}

/** Assets laden waar het blok staat (alleen wanneer actief). */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/photo-appraisal' ) ) {
		return;
	}
	if ( ! ( function_exists( 'ekinese_ai_enabled' ) && ekinese_ai_enabled() ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/photo-appraisal.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-photo-appraisal', get_theme_file_uri( 'assets/js/photo-appraisal.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* =====================================================================
   REST – foto → Claude vision → indicatie
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/photo-appraisal', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_photo_appraisal_rest',
	) );
} );

/**
 * Analyseer een geüploade foto met Claude vision.
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response|WP_Error
 */
function ekinese_photo_appraisal_rest( WP_REST_Request $req ) {
	$key = trim( (string) get_option( 'xg_anthropic_key', '' ) );
	if ( '' === $key ) {
		return new WP_Error( 'disabled', __( 'Foto-taxatie is nog niet beschikbaar.', 'ekinese' ), array( 'status' => 503 ) );
	}
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'photo_appraisal' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$files = $req->get_file_params();
	if ( empty( $files['photo'] ) ) {
		return new WP_Error( 'nofile', __( 'Geen foto ontvangen.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$file = $files['photo'];
	if ( ! in_array( $file['type'], ekinese_photo_appraisal_mimes(), true ) ) {
		return new WP_Error( 'mime', __( 'Alleen JPG, PNG of WebP toegestaan.', 'ekinese' ), array( 'status' => 415 ) );
	}
	if ( ! empty( $file['size'] ) && $file['size'] > 8 * MB_IN_BYTES ) {
		return new WP_Error( 'size', __( 'De foto is te groot (max 8 MB).', 'ekinese' ), array( 'status' => 413 ) );
	}
	$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $raw ) {
		return new WP_Error( 'read', __( 'Kon de foto niet lezen.', 'ekinese' ), array( 'status' => 500 ) );
	}
	$b64 = base64_encode( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

	$prompt = 'Bekijk deze foto van een mogelijk te verkopen object (sieraad, munt, baar of horloge). Beschrijf kort en in het Nederlands: 1) wat het waarschijnlijk is, 2) vermoedelijk metaal/materiaal en eventueel karaat/gehalte indien zichtbaar, 3) waar de klant op moet letten (stempel, gewicht). Geef GEEN concrete prijs of bod; benadruk dat een gratis taxatie de waarde bepaalt. Maximaal ~100 woorden.';

	$resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 30,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
		),
		'body'    => wp_json_encode( array(
			'model'      => function_exists( 'ekinese_ai_model' ) ? ekinese_ai_model() : 'claude-haiku-4-5',
			'max_tokens' => 400,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'image', 'source' => array( 'type' => 'base64', 'media_type' => $file['type'], 'data' => $b64 ) ),
						array( 'type' => 'text', 'text' => $prompt ),
					),
				),
			),
		) ),
	) );

	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return new WP_Error( 'ai', __( 'De analyse is even niet beschikbaar. Probeer het later opnieuw.', 'ekinese' ), array( 'status' => 502 ) );
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	$text = isset( $data['content'][0]['text'] ) ? trim( (string) $data['content'][0]['text'] ) : '';
	if ( '' === $text ) {
		return new WP_Error( 'empty', __( 'Geen analyse ontvangen.', 'ekinese' ), array( 'status' => 502 ) );
	}
	// De foto wordt niet opgeslagen.
	return rest_ensure_response( array( 'ok' => true, 'analysis' => $text ) );
}
