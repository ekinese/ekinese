<?php
/**
 * XGOUD AI-verkoopassistent — één Claude-brein voor de zoekbalk (nav), de chat
 * en (optioneel) elders. Met tool-use: de assistent kan live prijzen tonen,
 * een koersgrafiek tekenen, de juiste verkoop-routes voorstellen en een afspraak
 * starten. Antwoordt in de taal van de gebruiker (meegegeven door de browser).
 *
 * Vereist een Anthropic API-key (optie xg_anthropic_key); zonder key valt de
 * assistent terug op een nette melding. Self-built (wp_remote_post), geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Toolset die Claude mag aanroepen. */
function ekinese_assistant_tools() {
	return array(
		array(
			'name'         => 'get_metal_price',
			'description'  => 'Geef de actuele indicatieve dagprijs (per gram, kilo en troy ounce) van een edelmetaal.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array( 'metal' => array( 'type' => 'string', 'enum' => array( 'goud', 'zilver', 'platina', 'palladium' ) ) ),
				'required'   => array( 'metal' ),
			),
		),
		array(
			'name'         => 'show_price_chart',
			'description'  => 'Toon een koersgrafiek van een edelmetaal aan de gebruiker (laatste 30 dagen).',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array( 'metal' => array( 'type' => 'string', 'enum' => array( 'goud', 'zilver', 'platina', 'palladium' ) ) ),
				'required'   => array( 'metal' ),
			),
		),
		array(
			'name'         => 'suggest_sell_routes',
			'description'  => 'Stel de beste verkoop-opties/links voor op basis van wat de gebruiker wil verkopen (bijv. ring, munt, horloge, diamant).',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array( 'item' => array( 'type' => 'string', 'description' => 'Wat de gebruiker wil verkopen, vrij tekstveld.' ) ),
				'required'   => array( 'item' ),
			),
		),
		array(
			'name'         => 'start_appointment',
			'description'  => 'Start een (gratis) taxatie-afspraak. Geef een afspraaklink terug; sla optioneel naam/e-mail op zodat XGOUD contact kan opnemen.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'name'    => array( 'type' => 'string' ),
					'email'   => array( 'type' => 'string' ),
					'service' => array( 'type' => 'string', 'description' => 'kantoorbezoek | thuisbezoek | ophaalservice' ),
				),
			),
		),
	);
}

/** Voert één tool uit. Geeft [tekst-voor-claude, kaart-voor-ui] terug. */
function ekinese_assistant_run_tool( $name, $input ) {
	$eur = function ( $v, $d = 2 ) { return '€ ' . number_format( (float) $v, $d, ',', '.' ); };
	switch ( $name ) {
		case 'get_metal_price':
			$metal = sanitize_key( $input['metal'] ?? 'goud' );
			$spot  = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $metal ) : 0;
			$oz    = $spot * 31.1035;
			$txt   = sprintf( '%s: %s per gram, %s per kilo, %s per troy ounce (indicatief).', ucfirst( $metal ), $eur( $spot ), $eur( $spot * 1000, 0 ), $eur( $oz ) );
			$card  = array( 'type' => 'price', 'metal' => ucfirst( $metal ), 'gram' => $eur( $spot ), 'kilo' => $eur( $spot * 1000, 0 ), 'ounce' => $eur( $oz ) );
			return array( $txt, $card );

		case 'show_price_chart':
			$metal = sanitize_key( $input['metal'] ?? 'goud' );
			$html  = '';
			if ( function_exists( 'ekinese_render_price_chart' ) ) {
				$html = ekinese_render_price_chart( array( 'metal' => $metal, 'days' => 30 ) );
			} elseif ( function_exists( 'ekinese_price_chart_svg' ) ) {
				$html = ekinese_price_chart_svg( $metal, 30 );
			}
			return array( 'De koersgrafiek van ' . $metal . ' wordt aan de gebruiker getoond.', array( 'type' => 'chart', 'metal' => ucfirst( $metal ), 'html' => $html ) );

		case 'suggest_sell_routes':
			$item  = strtolower( (string) ( $input['item'] ?? '' ) );
			$links = array( array( 'label' => 'Gratis taxatie & afspraak', 'url' => home_url( '/afspraak/' ) ) );
			$map   = array(
				'goud'    => array( 'goud', 'ring', 'ketting', 'armband', 'sieraad', 'sieraden', 'munt', 'kruger', 'baar' ),
				'zilver'  => array( 'zilver', 'silver' ),
				'platina' => array( 'platina', 'platinum' ),
				'edelstenen' => array( 'diamant', 'diamond', 'saffier', 'robijn', 'smaragd', 'edelsteen', 'steen' ),
				'horloges'   => array( 'horloge', 'rolex', 'omega', 'watch', 'uurwerk' ),
			);
			$matched = '';
			foreach ( $map as $group => $words ) {
				foreach ( $words as $w ) {
					if ( false !== strpos( $item, $w ) ) { $matched = $group; break 2; }
				}
			}
			$routes = array(
				'goud'       => array( 'Goud verkopen', '/verkopen/edelmetalen/goud/' ),
				'zilver'     => array( 'Zilver verkopen', '/verkopen/edelmetalen/zilver/' ),
				'platina'    => array( 'Platina verkopen', '/verkopen/edelmetalen/platina/' ),
				'edelstenen' => array( 'Diamanten & edelstenen verkopen', '/verkopen/edelstenen/' ),
				'horloges'   => array( 'Luxe horloge verkopen', '/verkopen/horloges/' ),
			);
			if ( $matched && isset( $routes[ $matched ] ) ) {
				array_unshift( $links, array( 'label' => $routes[ $matched ][0], 'url' => home_url( $routes[ $matched ][1] ) ) );
			}
			$links[] = array( 'label' => 'Hoe werkt het?', 'url' => home_url( '/service/hoe-werkt-het/' ) );
			$txt = 'Relevante opties voorgesteld: ' . implode( ', ', wp_list_pluck( $links, 'label' ) ) . '.';
			return array( $txt, array( 'type' => 'links', 'items' => $links ) );

		case 'start_appointment':
			$name    = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
			$email   = sanitize_email( (string) ( $input['email'] ?? '' ) );
			$service = sanitize_text_field( (string) ( $input['service'] ?? '' ) );
			if ( is_email( $email ) ) {
				// Lead vastleggen + admin informeren (geen harde boeking; veilig).
				if ( post_type_exists( 'xg_lead' ) ) {
					$lid = wp_insert_post( array( 'post_type' => 'xg_lead', 'post_status' => 'publish', 'post_title' => 'Assistent-lead: ' . ( $name ?: $email ) ) );
					if ( $lid && ! is_wp_error( $lid ) ) {
						update_post_meta( $lid, 'email', $email );
						update_post_meta( $lid, 'name', $name );
						update_post_meta( $lid, 'service', $service );
						update_post_meta( $lid, 'source', 'assistant' );
					}
				}
				$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
				wp_mail( $admin, 'Assistent: afspraakverzoek', sprintf( "Naam: %s\nE-mail: %s\nService: %s", $name, $email, $service ) );
			}
			$url = add_query_arg( array_filter( array( 'service' => $service ?: null ) ), home_url( '/afspraak/' ) );
			return array( 'Afspraaklink gedeeld met de gebruiker' . ( is_email( $email ) ? ' en gegevens vastgelegd.' : '.' ), array( 'type' => 'appointment', 'url' => $url, 'label' => 'Plan uw gratis taxatie' ) );
	}
	return array( '', null );
}

/** System-prompt voor de verkoopassistent (taalbewust). */
function ekinese_assistant_system( $lang ) {
	$base = function_exists( 'ekinese_ai_system_prompt' ) ? ekinese_ai_system_prompt() : 'Je bent de assistent van XGOUD.';
	return $base . "\n\n"
		. "Je bent een proactieve, behulpzame VERKOOPASSISTENT. Help de bezoeker concreet verder: stel zo nodig één korte vervolgvraag, en gebruik je tools om live prijzen te tonen (get_metal_price), een koersgrafiek te laten zien (show_price_chart), de beste verkoop-opties voor te stellen (suggest_sell_routes) en een afspraak te starten (start_appointment). "
		. "Wees concreet en kort. Eindig met een duidelijke volgende stap. "
		. "Antwoord ALTIJD in de taal met code '" . $lang . "' (de taal van de gebruiker). Geef geen hard bod; prijzen zijn indicatief tot na taxatie.";
}

/** Tool-use loop tegen de Anthropic Messages-API. Geeft [reply, cards]. */
function ekinese_assistant_chat( $history, $lang ) {
	$key = trim( (string) get_option( 'xg_anthropic_key', '' ) );
	if ( '' === $key ) {
		return array( '', array(), 'nokey' );
	}
	$model    = function_exists( 'ekinese_ai_model' ) ? ekinese_ai_model() : 'claude-haiku-4-5';
	$tools    = ekinese_assistant_tools();
	$system   = ekinese_assistant_system( $lang );
	$messages = $history; // [{role, content}], content = string of array
	$cards    = array();

	for ( $i = 0; $i < 4; $i++ ) {
		$resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 45,
			'headers' => array( 'content-type' => 'application/json', 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ),
			'body'    => wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => 1024,
				'system'     => $system,
				'tools'      => $tools,
				'messages'   => $messages,
			) ),
		) );
		if ( is_wp_error( $resp ) ) {
			return array( '', $cards, 'error' );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return array( '', $cards, 'error' );
		}
		$data    = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$content = isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array();
		$stop    = $data['stop_reason'] ?? '';

		// Tekst verzamelen.
		$text = '';
		foreach ( $content as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}

		if ( 'tool_use' !== $stop ) {
			return array( trim( $text ), $cards, 'ok' );
		}

		// Assistent-bericht (met tool_use) terugzetten + tool_results bouwen.
		$messages[]   = array( 'role' => 'assistant', 'content' => $content );
		$tool_results = array();
		foreach ( $content as $block ) {
			if ( isset( $block['type'] ) && 'tool_use' === $block['type'] ) {
				list( $rtxt, $card ) = ekinese_assistant_run_tool( $block['name'], (array) ( $block['input'] ?? array() ) );
				if ( $card ) {
					$cards[] = $card;
				}
				$tool_results[] = array(
					'type'        => 'tool_result',
					'tool_use_id' => $block['id'],
					'content'     => $rtxt,
				);
			}
		}
		$messages[] = array( 'role' => 'user', 'content' => $tool_results );
	}
	return array( __( 'Ik heb een paar opties voor u opgehaald. Hoe kan ik u verder helpen?', 'ekinese' ), $cards, 'ok' );
}

/* =====================================================================
   REST  /assistant
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/assistant', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_rest_assistant',
	) );
} );

function ekinese_rest_assistant( WP_REST_Request $req ) {
	$p    = $req->get_json_params();
	$msg  = trim( (string) ( $p['message'] ?? '' ) );
	$lang = preg_replace( '/[^a-z]/', '', strtolower( substr( (string) ( $p['lang'] ?? 'nl' ), 0, 2 ) ) ) ?: 'nl';
	if ( '' === $msg ) {
		return new WP_Error( 'empty', 'Lege vraag.', array( 'status' => 400 ) );
	}
	// Korte geschiedenis (max 6 beurten), tekst-only, om de context te behouden.
	$history = array();
	if ( ! empty( $p['history'] ) && is_array( $p['history'] ) ) {
		foreach ( array_slice( $p['history'], -6 ) as $h ) {
			$role = ( ( $h['role'] ?? '' ) === 'assistant' ) ? 'assistant' : 'user';
			$txt  = sanitize_textarea_field( (string) ( $h['content'] ?? $h['text'] ?? '' ) );
			if ( '' !== $txt ) {
				$history[] = array( 'role' => $role, 'content' => $txt );
			}
		}
	}
	$history[] = array( 'role' => 'user', 'content' => sanitize_textarea_field( $msg ) );

	list( $reply, $cards, $status ) = ekinese_assistant_chat( $history, $lang );

	// Interne analytics: wat vragen bezoekers? (geen antwoord apart geteld).
	if ( function_exists( 'ekinese_track' ) ) {
		ekinese_track( 'assistant', $msg, (string) ( $p['url'] ?? '' ) );
		if ( 'ok' !== $status || '' === trim( (string) $reply ) ) {
			ekinese_track( 'noanswer', $msg );
		}
	}

	if ( 'nokey' === $status ) {
		// Nette fallback zonder key: regel-bot (indien aanwezig).
		$reply = function_exists( 'ekinese_bot_reply' ) ? ( ekinese_bot_reply( $msg, 'search', $lang )['reply'] ?? '' ) : '';
		if ( '' === $reply ) {
			$reply = 'De AI-assistent is nog niet geactiveerd. Bel ons of plan een gratis taxatie via /afspraak/.';
		}
	} elseif ( 'error' === $status && '' === $reply ) {
		$reply = 'Er ging iets mis bij het ophalen van het antwoord. Probeer het zo opnieuw of plan een afspraak.';
	}
	return rest_ensure_response( array( 'reply' => $reply, 'cards' => $cards, 'lang' => $lang ) );
}

/* =====================================================================
   ASSETS — assistent in de nav-zoekbalk (overal geladen)
===================================================================== */
function ekinese_assistant_assets() {
	$css = get_theme_file_path( 'assets/css/assistant.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-assistant', get_theme_file_uri( 'assets/css/assistant.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/assistant.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-assistant', get_theme_file_uri( 'assets/js/assistant.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-assistant', 'XGAssistant', array(
			'rest'    => esc_url_raw( rest_url( 'ekinese/v1/assistant' ) ),
			'enabled' => ekinese_ai_enabled() ? 1 : 0,
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_assistant_assets' );
