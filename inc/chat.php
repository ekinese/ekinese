<?php
/**
 * XGOUD Website-Chat.
 *
 * - CPT xg_chat: ein Gespräch je Besucher (Nachrichten als JSON), im Backend
 *   durchsuchbar/archiviert.
 * - Foto-/Video-Upload (REST, MIME-/Größen-geprüft).
 * - Regelbasierter Chatbot (NL) – beantwortet Standardfragen, leitet zu
 *   Mensch/„bericht achterlaten" außerhalb der Öffnungszeiten.
 *
 * Dieselbe Bot-Engine (ekinese_bot_reply) nutzt später der Such-Assistent.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT
===================================================================== */
function ekinese_register_chat() {
	register_post_type(
		'xg_chat',
		array(
			'labels'      => array(
				'name'          => __( 'Chats', 'ekinese' ),
				'singular_name' => __( 'Chat', 'ekinese' ),
				'menu_name'     => __( 'Chats', 'ekinese' ),
			),
			'public'      => false,
			'show_ui'     => true,
			'menu_icon'   => 'dashicons-format-chat',
			'supports'    => array( 'title' ),
		)
	);
	foreach ( array( 'messages', 'visitor_name', 'visitor_email', 'status', 'token' ) as $k ) {
		register_post_meta( 'xg_chat', $k, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_chat' );

/* =====================================================================
   ÖFFNUNGSZEITEN
===================================================================== */
/**
 * Sind gerade Geschäftszeiten? (Mo–Fr 09–17:30, Sa 10–16, in CET grob.)
 *
 * @return bool
 */
function ekinese_chat_is_open() {
	$tz   = new DateTimeZone( 'Europe/Amsterdam' );
	$now  = new DateTime( 'now', $tz );
	$dow  = (int) $now->format( 'N' ); // 1=Mo .. 7=So
	$mins = (int) $now->format( 'H' ) * 60 + (int) $now->format( 'i' );
	if ( $dow >= 1 && $dow <= 5 ) {
		return $mins >= 540 && $mins <= 1050; // 09:00–17:30
	}
	if ( 6 === $dow ) {
		return $mins >= 600 && $mins <= 960;  // 10:00–16:00
	}
	return false;
}

/* =====================================================================
   BOT-ENGINE (regelbasiert, NL) – auch vom Such-Assistenten genutzt
===================================================================== */
/**
 * Antwort + Quick-Replies + optionale Vorschläge erzeugen.
 *
 * @param string $text    Nutzereingabe
 * @param string $context 'chat' | 'search'
 * @return array { reply:string, quick:array, suggestions:array }
 */
function ekinese_bot_reply( $text, $context = 'chat' ) {
	$t    = mb_strtolower( trim( $text ) );
	$open = ekinese_chat_is_open();

	$rules = array(
		array( '/\b(goud|goudprijs|koers|gram|verkoop|verkopen)\b/', 'De actuele goudprijs ziet u live bovenaan de pagina. Wilt u een indicatie? Gebruik onze rekentool of noem het gewicht en karaat.', array( 'Goud verkopen', 'Goudprijs vandaag' ) ),
		array( '/\b(afspraak|termijn|plannen|langskomen|thuis)\b/', 'U kunt direct een afspraak maken. Wilt u een bezoek aan huis, een kantoor of de ophaalservice?', array( 'Afspraak maken', 'Bezoek aan huis' ) ),
		array( '/\b(kantoor|vestiging|adres|locatie|waar)\b/', 'Wij hebben 40+ vestigingen. Eindhoven is ons hoofdkantoor (6 dagen open). In welke stad zoekt u?', array( 'Alle kantoren', 'Kantoor Eindhoven' ) ),
		array( '/\b(diamant|diamanten|briljant|rapaport|gia)\b/', 'Voor diamanten taxeren wij volgens de Rapaport-lijst. Heeft u een GIA-, IGI- of HRD-certificaat? Dan kan ik nauwkeuriger inschatten.', array( 'Diamanten verkopen', 'Wat is mijn diamant waard?' ) ),
		array( '/\b(horloge|rolex|omega|patek|cartier)\b/', 'Welk merk en model horloge wilt u verkopen? Met merk, model en staat geef ik u een indicatie.', array( 'Horloge verkopen', 'Rolex verkopen' ) ),
		array( '/\b(zilver|platina|palladium|munt|baar|krugerrand)\b/', 'Ook zilver, platina, palladium en munten kopen wij in. Noem het gewicht en de zuiverheid voor een indicatie.', array( 'Edelmetaal verkopen' ) ),
		array( '/\b(open|tijd|uur|geopend|wanneer)\b/', $open ? 'Wij zijn nu geopend en antwoorden zo snel mogelijk. Ma–vr 09:00–17:30, za 10:00–16:00.' : 'Wij zijn nu gesloten. Ma–vr 09:00–17:30, za 10:00–16:00. Laat gerust een bericht achter, dan reageren wij snel.', array() ),
		array( '/\b(foto|video|sturen|afbeelding|film)\b/', 'Ja, u kunt hier een foto of video sturen via de 📎-knop onderaan. Zo kunnen wij beter inschatten.', array() ),
		array( '/\b(hallo|hoi|goedendag|hey|hi)\b/', 'Hallo! Welkom bij XGOUD. Waarmee kan ik u helpen — goud, diamanten, horloges of een afspraak?', array( 'Goud verkopen', 'Afspraak maken', 'Alle kantoren' ) ),
	);

	foreach ( $rules as $r ) {
		if ( preg_match( $r[0], $t ) ) {
			return array( 'reply' => $r[1], 'quick' => $r[2], 'suggestions' => ekinese_bot_suggestions( $t ) );
		}
	}

	// Fallback.
	$fallback = $open
		? 'Daar help ik u graag mee. Kunt u het iets specifieker omschrijven? Of wilt u dat een medewerker met u meekijkt?'
		: 'Bedankt voor uw bericht! Wij zijn nu gesloten, maar laat uw vraag en e-mail achter — dan reageren wij zo snel mogelijk.';
	return array( 'reply' => $fallback, 'quick' => array( 'Goud verkopen', 'Afspraak maken', 'Alle kantoren' ), 'suggestions' => ekinese_bot_suggestions( $t ) );
}

/**
 * Inhaltliche Vorschläge (Seiten/Produkte) – Grundlage für den Such-Assistenten.
 *
 * @param string $t kleingeschriebener Suchtext
 * @return array Liste [ {label, url} ]
 */
function ekinese_bot_suggestions( $t ) {
	$map = array(
		'goud'     => array( 'Goud verkopen', '/edelmetalen/goud-verkopen/' ),
		'zilver'   => array( 'Zilver verkopen', '/edelmetalen/zilver-verkopen/' ),
		'diamant'  => array( 'Diamanten verkopen', '/edelstenen/diamanten-verkopen/' ),
		'horloge'  => array( 'Horloge verkopen', '/horloges/' ),
		'rolex'    => array( 'Rolex verkopen', '/horloges/rolex-verkopen/' ),
		'kantoor'  => array( 'Alle kantoren', '/kantoren/' ),
		'afspraak' => array( 'Afspraak maken', '/afspraak/' ),
	);
	$out = array();
	foreach ( $map as $key => $s ) {
		if ( false !== mb_strpos( $t, $key ) ) {
			$out[] = array( 'label' => $s[0], 'url' => $s[1] );
		}
	}
	return $out;
}

/* =====================================================================
   REST
===================================================================== */
function ekinese_register_chat_rest() {
	register_rest_route( 'ekinese/v1', '/chat/message', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_rest_chat_message',
	) );
	register_rest_route( 'ekinese/v1', '/chat/upload', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_rest_chat_upload',
	) );
	// Such-Assistent (nutzt dieselbe Bot-Engine).
	register_rest_route( 'ekinese/v1', '/assist', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			$q = (string) ( $r->get_json_params()['q'] ?? '' );
			return ekinese_bot_reply( $q, 'search' );
		},
	) );
}
add_action( 'rest_api_init', 'ekinese_register_chat_rest' );

/**
 * Konversation finden/erzeugen und Token prüfen.
 */
function ekinese_chat_resolve( $id, $token ) {
	if ( $id ) {
		$saved = get_post_meta( $id, 'token', true );
		if ( $saved && hash_equals( $saved, (string) $token ) && 'xg_chat' === get_post_type( $id ) ) {
			return $id;
		}
		return 0;
	}
	$new_token = wp_generate_password( 20, false );
	$id = wp_insert_post( array(
		'post_type'   => 'xg_chat',
		'post_status' => 'publish',
		'post_title'  => 'Chat ' . gmdate( 'Y-m-d H:i' ),
	) );
	update_post_meta( $id, 'token', $new_token );
	update_post_meta( $id, 'status', 'open' );
	update_post_meta( $id, 'messages', wp_json_encode( array() ) );
	return array( 'id' => $id, 'token' => $new_token );
}

function ekinese_rest_chat_message( WP_REST_Request $req ) {
	$d     = $req->get_json_params();
	$text  = trim( (string) ( $d['text'] ?? '' ) );
	$atts  = isset( $d['attachments'] ) && is_array( $d['attachments'] ) ? array_map( 'esc_url_raw', $d['attachments'] ) : array();
	$id    = (int) ( $d['id'] ?? 0 );
	$token = (string) ( $d['token'] ?? '' );

	if ( '' === $text && empty( $atts ) ) {
		return new WP_Error( 'xg_empty', 'Leeg bericht', array( 'status' => 400 ) );
	}

	$resolved = ekinese_chat_resolve( $id, $token );
	if ( is_array( $resolved ) ) {
		$id = $resolved['id']; $token = $resolved['token'];
	} elseif ( $resolved ) {
		$id = $resolved;
	} else {
		return new WP_Error( 'xg_token', 'Ongeldige sessie', array( 'status' => 403 ) );
	}

	$messages = json_decode( (string) get_post_meta( $id, 'messages', true ), true ) ?: array();
	$messages[] = array( 'sender' => 'user', 'text' => sanitize_textarea_field( $text ), 'attachments' => $atts, 'time' => gmdate( 'c' ) );

	// Optional Kontaktdaten erkennen.
	if ( is_email( $text ) ) {
		update_post_meta( $id, 'visitor_email', sanitize_email( $text ) );
	}

	$bot = ekinese_bot_reply( $text );
	$messages[] = array( 'sender' => 'bot', 'text' => $bot['reply'], 'time' => gmdate( 'c' ) );
	update_post_meta( $id, 'messages', wp_json_encode( $messages ) );

	return array(
		'id'    => $id,
		'token' => $token,
		'reply' => $bot['reply'],
		'quick' => $bot['quick'],
		'open'  => ekinese_chat_is_open(),
	);
}

function ekinese_rest_chat_upload( WP_REST_Request $req ) {
	if ( empty( $_FILES['file'] ) ) {
		return new WP_Error( 'xg_nofile', 'Geen bestand', array( 'status' => 400 ) );
	}
	$file = $_FILES['file'];

	// MIME-/Größenprüfung (Bilder + Videos, max 25 MB).
	$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/quicktime', 'video/webm' );
	$check   = wp_check_filetype( $file['name'] );
	if ( ! in_array( $file['type'], $allowed, true ) ) {
		return new WP_Error( 'xg_mime', 'Bestandstype niet toegestaan', array( 'status' => 415 ) );
	}
	if ( (int) $file['size'] > 25 * 1024 * 1024 ) {
		return new WP_Error( 'xg_size', 'Bestand te groot (max 25 MB)', array( 'status' => 413 ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$moved = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => array(
		'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
		'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
	) ) );
	if ( isset( $moved['error'] ) ) {
		return new WP_Error( 'xg_upload', $moved['error'], array( 'status' => 500 ) );
	}
	return array( 'url' => $moved['url'], 'type' => $moved['type'] );
}

/* =====================================================================
   ASSETS
===================================================================== */
function ekinese_enqueue_chat_assets() {
	$css = get_theme_file_path( 'assets/css/chat.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-chat', get_theme_file_uri( 'assets/css/chat.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/chat.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-chat', get_theme_file_uri( 'assets/js/chat.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-chat', 'XG_CHAT', array(
			'rest_message' => esc_url_raw( rest_url( 'ekinese/v1/chat/message' ) ),
			'rest_upload'  => esc_url_raw( rest_url( 'ekinese/v1/chat/upload' ) ),
			'rest_assist'  => esc_url_raw( rest_url( 'ekinese/v1/assist' ) ),
			'open'         => ekinese_chat_is_open(),
		) );
	}

	// Such-Assistent (hängt sich an das Header-Such-Overlay).
	$sa = get_theme_file_path( 'assets/js/search-assist.js' );
	if ( file_exists( $sa ) ) {
		wp_enqueue_script( 'ekinese-search-assist', get_theme_file_uri( 'assets/js/search-assist.js' ), array( 'ekinese-chat' ), (string) filemtime( $sa ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_chat_assets' );

/* =====================================================================
   ADMIN: Transcript anzeigen
===================================================================== */
function ekinese_chat_metabox() {
	add_meta_box( 'xg_chat_log', __( 'Gesprek', 'ekinese' ), 'ekinese_chat_metabox_html', 'xg_chat', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_chat_metabox' );

function ekinese_chat_metabox_html( $post ) {
	$messages = json_decode( (string) get_post_meta( $post->ID, 'messages', true ), true ) ?: array();
	$email    = get_post_meta( $post->ID, 'visitor_email', true );
	if ( $email ) {
		echo '<p><strong>' . esc_html__( 'E-mail bezoeker', 'ekinese' ) . ':</strong> ' . esc_html( $email ) . '</p>';
	}
	echo '<div style="max-height:520px;overflow:auto;border:1px solid #e3ddd0;padding:14px;background:#faf8f2">';
	foreach ( $messages as $m ) {
		$is_user = 'user' === ( $m['sender'] ?? '' );
		$align   = $is_user ? 'right' : 'left';
		$bg      = $is_user ? '#fff' : '#f0e6d2';
		echo '<div style="text-align:' . esc_attr( $align ) . ';margin:8px 0"><span style="display:inline-block;max-width:75%;background:' . esc_attr( $bg ) . ';border:1px solid #e3ddd0;padding:8px 12px;text-align:left">';
		echo esc_html( $m['text'] ?? '' );
		if ( ! empty( $m['attachments'] ) ) {
			foreach ( $m['attachments'] as $url ) {
				echo '<br><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Bijlage', 'ekinese' ) . ' ↗</a>';
			}
		}
		echo '<br><small style="color:#999">' . esc_html( $m['time'] ?? '' ) . '</small></span></div>';
	}
	echo '</div>';
}
