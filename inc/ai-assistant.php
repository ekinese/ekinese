<?php
/**
 * XGOUD "Vraag het de expert" (#12) – Claude-assistent.
 *
 * Wanneer de regel-bot (inc/chat.php) geen treffer heeft én er een Anthropic
 * API-key is geconfigureerd (optie xg_anthropic_key), beantwoordt Claude de
 * vraag met een system-prompt gevoed met site-data (diensten, prijzen, FAQ).
 * Zonder key blijft de regel-bot actief — nette fallback, geen storing.
 *
 * Model standaard: claude-haiku-4-5. Key staat ALLEEN in WP-opties, nooit in de
 * repo. Self-built (directe HTTP-call via wp_remote_post), geen plugin/SDK.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Geconfigureerd Claude-model (default haiku). */
function ekinese_ai_model() {
	$m = trim( (string) get_option( 'xg_anthropic_model', '' ) );
	return $m !== '' ? $m : 'claude-haiku-4-5';
}

/** Is de assistent actief? (key aanwezig) */
function ekinese_ai_enabled() {
	return '' !== trim( (string) get_option( 'xg_anthropic_key', '' ) );
}

/** System-prompt met actuele site-context. */
function ekinese_ai_system_prompt() {
	$b     = function_exists( 'ekinese_business' ) ? ekinese_business() : array( 'name' => 'XGOUD' );
	$lines = array();
	$lines[] = 'Je bent de digitale assistent van ' . ( $b['name'] ?? 'XGOUD' ) . ', een betrouwbare opkoper van edelmetaal, sieraden, diamanten, edelstenen en horloges in Nederland en België.';
	$lines[] = 'Beantwoord vragen kort, vriendelijk en correct. Stuur klanten naar een gratis taxatie/afspraak waar passend. Geef nooit een keihard bod; prijzen zijn altijd indicatief op basis van de actuele dagprijs en pas definitief na taxatie.';
	$lines[] = 'Werkwijze: uitsluitend op afspraak; kantoorbezoek, thuisbezoek of verzekerde ophaalservice; directe uitbetaling; gratis taxatie; een vast deel van elke marge gaat naar een goed doel; minimumleeftijd 18 jaar; legitimatie verplicht (Wwft/KYC).';

	if ( function_exists( 'ekinese_metal_spot' ) ) {
		$spots = array();
		foreach ( array( 'goud', 'zilver', 'platina', 'palladium' ) as $m ) {
			$s = (float) ekinese_metal_spot( $m );
			if ( $s > 0 ) {
				$spots[] = ucfirst( $m ) . ' € ' . number_format( $s, 2, ',', '.' ) . '/g';
			}
		}
		if ( $spots ) {
			$lines[] = 'Actuele indicatieve dagprijzen: ' . implode( ', ', $spots ) . '.';
		}
	}
	$lines[] = 'Contact: tel ' . ( $b['telephone'] ?? '' ) . ', e-mail ' . ( $b['email'] ?? '' ) . '.';
	$lines[] = 'Belangrijk: geef geen beleggingsadvies. Antwoord in dezelfde taal als de vraag. Houd antwoorden onder ~120 woorden.';
	return implode( "\n", $lines );
}

/**
 * Vraag Claude om een antwoord. Geeft '' terug bij geen key of fout
 * (de regel-bot neemt het dan over).
 *
 * @param string $text Klantvraag.
 * @param string $lang Taalcode (nl/en/de/fr…).
 * @return string Antwoord of ''.
 */
function ekinese_ai_assistant_reply( $text, $lang = 'nl' ) {
	$key = trim( (string) get_option( 'xg_anthropic_key', '' ) );
	if ( '' === $key || '' === trim( (string) $text ) ) {
		return '';
	}
	// Korte cache op identieke vraag (privacy: alleen hash, 10 min).
	$ck     = 'xg_ai_' . md5( $lang . '|' . mb_strtolower( trim( $text ) ) );
	$cached = get_transient( $ck );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 20,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
		),
		'body'    => wp_json_encode( array(
			'model'      => ekinese_ai_model(),
			'max_tokens' => 400,
			'system'     => ekinese_ai_system_prompt(),
			'messages'   => array(
				array( 'role' => 'user', 'content' => (string) $text ),
			),
		) ),
	) );

	if ( is_wp_error( $resp ) ) {
		return '';
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) {
		return '';
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	$out  = '';
	if ( isset( $data['content'][0]['text'] ) ) {
		$out = trim( (string) $data['content'][0]['text'] );
	}
	if ( '' !== $out ) {
		set_transient( $ck, $out, 10 * MINUTE_IN_SECONDS );
	}
	return $out;
}

/* =====================================================================
   Admin – API-key + model onder XGOUD-menu (Integraties)
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'AI-assistent', 'ekinese' ), __( 'AI-assistent', 'ekinese' ), 'manage_options', 'xg-ai', function () {
		if ( isset( $_POST['xg_ai_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_ai_nonce'] ), 'xg_ai' ) ) {
			$new_key = sanitize_text_field( wp_unslash( $_POST['xg_anthropic_key'] ?? '' ) );
			if ( '' !== $new_key ) { // Leeg = bestaande key behouden.
				update_option( 'xg_anthropic_key', $new_key );
			}
			update_option( 'xg_anthropic_model', sanitize_text_field( wp_unslash( $_POST['xg_anthropic_model'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		$key   = (string) get_option( 'xg_anthropic_key', '' );
		$mask  = $key ? str_repeat( '•', 8 ) . substr( $key, -4 ) : '';
		$model = esc_attr( ekinese_ai_model() );
		echo '<div class="wrap"><h1>AI-assistent (Claude)</h1>';
		echo '<p>Wanneer een key is ingevuld, beantwoordt Claude vragen die de regel-bot niet herkent. Zonder key blijft de regel-bot actief. De key wordt <strong>uitsluitend</strong> hier opgeslagen, nooit in de repository.</p>';
		echo '<p>Status: ' . ( ekinese_ai_enabled() ? '<strong style="color:#1f9d55">actief</strong> (' . esc_html( $mask ) . ')' : '<strong style="color:#c0392b">uit</strong> (geen key)' ) . '</p>';
		echo '<form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_ai', 'xg_ai_nonce' );
		echo '<tr><th>Anthropic API-key</th><td><input type="password" name="xg_anthropic_key" value="" class="regular-text" placeholder="' . ( $key ? esc_attr( $mask ) : 'sk-ant-…' ) . '" autocomplete="off"><p class="description">Laat leeg om de bestaande key te behouden.</p></td></tr>';
		echo '<tr><th>Model</th><td><input type="text" name="xg_anthropic_model" value="' . $model . '" class="regular-text"><p class="description">Standaard <code>claude-haiku-4-5</code>.</p></td></tr>';
		echo '</table>';
		submit_button();
		echo '</form></div>';
	} );
} );
