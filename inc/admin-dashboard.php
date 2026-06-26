<?php
/**
 * XGOUD operationeel dashboard (admin) — alles in één oogopslag:
 *  - nieuwe afspraken, nieuwe verkopen, nieuwe marktplaats-producten;
 *  - de prijzen van de 4 metalen (999) per gram;
 *  - een dagelijkse takenlijst die zich automatisch genereert uit de statistieken;
 *  - een Claude-AI-paneel + zelf te maken "agents" (opgeslagen instructies die
 *    Claude op de bedrijfsdata draait, eenmalig of dagelijks via cron).
 *
 * Bouwt voort op ekinese_stats(), ekinese_metal_spot() en de bestaande
 * Anthropic-integratie (optie xg_anthropic_key, model via ekinese_ai_model()).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   DATA-HELPERS
===================================================================== */

/** Prijzen van de 4 metalen (999) per gram + per kilo. */
function ekinese_dashboard_metal_prices() {
	$metals = array( 'goud' => 'Goud 999', 'zilver' => 'Zilver 999', 'platina' => 'Platina 999', 'palladium' => 'Palladium 999' );
	$out    = array();
	foreach ( $metals as $code => $label ) {
		$spot         = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $code ) : 0;
		$out[ $code ] = array( 'label' => $label, 'gram' => $spot, 'kilo' => $spot * 1000 );
	}
	return $out;
}

/** Recente posts van een type (optioneel met meta-filter). */
function ekinese_dashboard_recent( $type, $limit = 5, $meta = array() ) {
	if ( ! post_type_exists( $type ) ) {
		return array();
	}
	$args = array( 'post_type' => $type, 'posts_per_page' => $limit, 'post_status' => array( 'publish', 'draft' ), 'orderby' => 'date', 'order' => 'DESC' );
	if ( $meta ) {
		$args['meta_query'] = $meta;
	}
	return get_posts( $args );
}

/** Aantal posts van een type met meta-voorwaarde. */
function ekinese_dashboard_count( $type, $meta = array(), $statuses = array( 'publish' ) ) {
	if ( ! post_type_exists( $type ) ) {
		return 0;
	}
	$q = new WP_Query( array(
		'post_type'      => $type,
		'post_status'    => $statuses,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => $meta ? $meta : array(),
	) );
	return (int) $q->found_posts;
}

/** Aantal posts aangemaakt sinds X dagen. */
function ekinese_dashboard_since( $type, $days = 7, $statuses = array( 'publish', 'draft' ) ) {
	if ( ! post_type_exists( $type ) ) {
		return 0;
	}
	$q = new WP_Query( array(
		'post_type'      => $type,
		'post_status'    => $statuses,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'date_query'     => array( array( 'after' => $days . ' days ago' ) ),
	) );
	return (int) $q->found_posts;
}

/**
 * Genereert de dagelijkse takenlijst automatisch uit de statistieken.
 * Elke taak: key, label, count, link. Alleen taken met werk (count>0) of vaste
 * dagelijkse controles.
 */
function ekinese_daily_tasks() {
	$tasks = array();
	$add   = function ( $key, $label, $count, $link ) use ( &$tasks ) {
		if ( $count > 0 ) {
			$tasks[] = array( 'key' => $key, 'label' => $label, 'count' => $count, 'link' => $link );
		}
	};

	// Nieuwe afspraken te bevestigen (status leeg/new/pending of < 2 dagen oud).
	$new_appt = ekinese_dashboard_count( 'xg_appointment', array(
		'relation' => 'OR',
		array( 'key' => 'status', 'value' => array( '', 'new', 'nieuw', 'pending', 'aangevraagd' ), 'compare' => 'IN' ),
		array( 'key' => 'status', 'compare' => 'NOT EXISTS' ),
	) );
	$add( 'appt', 'Nieuwe afspraken bevestigen', $new_appt, admin_url( 'edit.php?post_type=xg_appointment' ) );

	// Open tickets.
	$add( 'tickets', 'Open tickets beantwoorden', ekinese_dashboard_count( 'xg_ticket', array( array( 'key' => 'status', 'value' => 'open' ) ) ), admin_url( 'edit.php?post_type=xg_ticket' ) );

	// Onbeantwoorde productvragen.
	$add( 'questions', 'Productvragen beantwoorden', ekinese_dashboard_count( 'xg_question', array( array( 'key' => 'status', 'value' => 'pending' ) ) ), admin_url( 'edit.php?post_type=xg_question' ) );

	// Veilingen die vandaag sluiten (live + ends <= einde dag).
	if ( post_type_exists( 'xg_auction' ) ) {
		$ending = get_posts( array(
			'post_type' => 'xg_auction', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
			'meta_query' => array( array( 'key' => 'status', 'value' => 'closed', 'compare' => '!=' ) ),
		) );
		$today_end = strtotime( 'today 23:59:59', current_time( 'timestamp' ) );
		$cnt = 0;
		foreach ( $ending as $aid ) {
			$e = strtotime( (string) get_post_meta( $aid, 'ends', true ) );
			if ( $e && $e <= $today_end ) {
				$cnt++;
			}
		}
		$add( 'auctions_end', 'Veilingen sluiten vandaag (winnaar/proforma)', $cnt, admin_url( 'edit.php?post_type=xg_auction' ) );

		// Gesloten + onbetaald → factureren.
		$unpaid = ekinese_dashboard_count( 'xg_auction', array(
			'relation' => 'AND',
			array( 'key' => 'status', 'value' => 'closed' ),
			array( 'key' => 'paid', 'value' => '1', 'compare' => '!=' ),
		) );
		$add( 'auctions_unpaid', 'Gewonnen veilingen factureren / betaling controleren', $unpaid, admin_url( 'edit.php?post_type=xg_auction' ) );
	}

	// Marktplaats: overlappingen + nieuwe advertenties modereren.
	if ( post_type_exists( 'xg_market' ) ) {
		$add( 'mp_overlap', 'Mogelijke marktplaats-overlappingen controleren', ekinese_dashboard_count( 'xg_market', array( array( 'key' => '_overlap', 'value' => '1' ) ), array( 'publish', 'draft' ) ), admin_url( 'edit.php?post_type=xg_market' ) );
		$add( 'mp_new', 'Nieuwe marktplaats-advertenties (laatste 2 dagen)', ekinese_dashboard_since( 'xg_market', 2, array( 'publish' ) ), admin_url( 'edit.php?post_type=xg_market' ) );
	}

	// KYC te controleren.
	$add( 'kyc', 'KYC / opkopersregister controleren', ekinese_dashboard_count( 'xg_kyc', array( array( 'key' => 'status', 'value' => 'open' ) ) ), admin_url( 'edit.php?post_type=xg_kyc' ) );

	// Analytics-gedreven taken (interne zoekdata + gekoppelde bronnen).
	$tasks = apply_filters( 'ekinese_daily_tasks_extra', $tasks );

	return $tasks;
}

/* =====================================================================
   INBOX — chats, tickets, productvragen op het dashboard
===================================================================== */
function ekinese_dashboard_inbox() {
	echo '<h2 style="margin-top:24px">Inbox</h2><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px">';

	// Open chats (laatste bericht als snippet).
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h3 style="margin-top:0;font-size:14px">Chats <a style="float:right;font-weight:400" href="' . esc_url( admin_url( 'edit.php?post_type=xg_chat' ) ) . '">alle</a></h3>';
	$chats = post_type_exists( 'xg_chat' ) ? get_posts( array( 'post_type' => 'xg_chat', 'numberposts' => 5, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => 'status', 'value' => 'closed', 'compare' => '!=' ) ) ) ) : array();
	if ( ! $chats ) {
		echo '<p style="color:#8c8f94;margin:0">Geen open chats.</p>';
	} else {
		foreach ( $chats as $c ) {
			$msgs = json_decode( (string) get_post_meta( $c->ID, 'messages', true ), true ) ?: array();
			$last = end( $msgs );
			$snip = $last ? wp_trim_words( (string) ( $last['text'] ?? '' ), 12 ) : '—';
			echo '<div style="padding:6px 0;border-bottom:1px solid #f0f0f1"><a href="' . esc_url( get_edit_post_link( $c->ID ) ) . '"><strong>' . esc_html( get_post_meta( $c->ID, 'visitor_email', true ) ?: get_the_title( $c->ID ) ) . '</strong></a><br><span style="color:#646970;font-size:12px">' . esc_html( $snip ) . '</span></div>';
		}
	}
	echo '</div>';

	// Open tickets.
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h3 style="margin-top:0;font-size:14px">Open tickets <a style="float:right;font-weight:400" href="' . esc_url( admin_url( 'edit.php?post_type=xg_ticket' ) ) . '">alle</a></h3>';
	$tickets = post_type_exists( 'xg_ticket' ) ? get_posts( array( 'post_type' => 'xg_ticket', 'numberposts' => 6, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => 'status', 'value' => 'open' ) ) ) ) : array();
	if ( ! $tickets ) {
		echo '<p style="color:#8c8f94;margin:0">Geen open tickets.</p>';
	} else {
		foreach ( $tickets as $t ) {
			echo '<div style="padding:6px 0;border-bottom:1px solid #f0f0f1"><a href="' . esc_url( get_edit_post_link( $t->ID ) ) . '"><strong>' . esc_html( get_post_meta( $t->ID, 'reference', true ) ) . '</strong> ' . esc_html( get_post_meta( $t->ID, 'subject', true ) ) . '</a><br><span style="color:#646970;font-size:12px">' . esc_html( get_post_meta( $t->ID, 'email', true ) ) . '</span></div>';
		}
	}
	echo '</div>';

	// Onbeantwoorde productvragen.
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h3 style="margin-top:0;font-size:14px">Productvragen <a style="float:right;font-weight:400" href="' . esc_url( admin_url( 'edit.php?post_type=xg_question' ) ) . '">alle</a></h3>';
	$qs = post_type_exists( 'xg_question' ) ? get_posts( array( 'post_type' => 'xg_question', 'numberposts' => 6, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => 'status', 'value' => 'pending' ) ) ) ) : array();
	if ( ! $qs ) {
		echo '<p style="color:#8c8f94;margin:0">Niets openstaand.</p>';
	} else {
		foreach ( $qs as $q ) {
			echo '<div style="padding:6px 0;border-bottom:1px solid #f0f0f1"><a href="' . esc_url( get_edit_post_link( $q->ID ) ) . '">' . esc_html( wp_trim_words( $q->post_content, 12 ) ) . '</a></div>';
		}
	}
	echo '</div></div>';
}

/* =====================================================================
   CLAUDE-AI — vraag stellen + agents draaien
===================================================================== */

/** Beknopte bedrijfssnapshot voor de AI-context. */
function ekinese_dashboard_ai_context() {
	$s   = function_exists( 'ekinese_stats' ) ? ekinese_stats() : array();
	$pr  = ekinese_dashboard_metal_prices();
	$lines = array();
	$lines[] = 'Datum: ' . date_i18n( 'Y-m-d H:i' );
	$lines[] = 'Prijzen (per gram): ' . implode( ', ', array_map( function ( $m ) { return $m['label'] . ' € ' . number_format( $m['gram'], 2 ); }, $pr ) );
	if ( $s ) {
		$lines[] = sprintf( 'Afspraken: %d, open tickets: %s, producten: %d, kantoren: %d, nieuwsbrief: %d.', $s['appointments'] ?? 0, ( $s['tickets_open'] ?? 0 ) . '/' . ( $s['tickets_all'] ?? 0 ), $s['products'] ?? 0, $s['offices'] ?? 0, $s['subscribers'] ?? 0 );
		$lines[] = 'Goede doelen totaal: € ' . number_format( (float) ( $s['charity'] ?? 0 ), 2 );
	}
	$tasks = ekinese_daily_tasks();
	if ( $tasks ) {
		$lines[] = 'Openstaande taken: ' . implode( '; ', array_map( function ( $t ) { return $t['label'] . ' (' . $t['count'] . ')'; }, $tasks ) );
	}
	/** Andere modules (surveys, social-insights, …) kunnen context-regels toevoegen. */
	$lines = apply_filters( 'ekinese_dashboard_ai_context_lines', $lines );
	return implode( "\n", (array) $lines );
}

/** Directe call naar de Anthropic Messages-API (hergebruikt key + model). */
function ekinese_admin_ai_call( $system, $user ) {
	$key = trim( (string) get_option( 'xg_anthropic_key', '' ) );
	if ( '' === $key ) {
		return new WP_Error( 'nokey', __( 'Geen Anthropic API-key ingesteld (XGOUD → Integraties).', 'ekinese' ) );
	}
	$model = function_exists( 'ekinese_ai_model' ) ? ekinese_ai_model() : 'claude-haiku-4-5';
	$resp  = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 45,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
		),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'max_tokens' => 1200,
			'system'     => $system,
			'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
		) ),
	) );
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( 200 !== (int) $code ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
		return new WP_Error( 'api', $msg );
	}
	$text = '';
	if ( ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
		foreach ( $body['content'] as $part ) {
			if ( isset( $part['type'], $part['text'] ) && 'text' === $part['type'] ) {
				$text .= $part['text'];
			}
		}
	}
	return $text !== '' ? $text : __( '(Geen antwoord)', 'ekinese' );
}

/** Stelt een vraag/agent-instructie aan Claude met de bedrijfscontext erbij. */
function ekinese_admin_ai_ask( $instruction ) {
	$system = "Je bent de operationele AI-assistent van XGOUD (inkoop van goud, zilver en edelmetaal in Nederland). "
		. "Je helpt het management met beknopte, concrete adviezen, samenvattingen en actielijsten op basis van de actuele bedrijfsdata. "
		. "Antwoord in het Nederlands, kort en praktisch. Verzin geen cijfers; gebruik alleen de gegeven data.\n\n"
		. "ACTUELE BEDRIJFSDATA:\n" . ekinese_dashboard_ai_context();
	return ekinese_admin_ai_call( $system, (string) $instruction );
}

/* ---- AJAX: vraag stellen / agent draaien ---- */
add_action( 'wp_ajax_xg_admin_ai', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'xg_admin_ai', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Niet toegestaan.' ), 403 );
	}
	$agent_id    = (int) ( $_POST['agent'] ?? 0 );
	$instruction = sanitize_textarea_field( wp_unslash( $_POST['q'] ?? '' ) );
	if ( $agent_id && post_type_exists( 'xg_agent' ) ) {
		$instruction = get_post_field( 'post_content', $agent_id );
	}
	if ( '' === trim( (string) $instruction ) ) {
		wp_send_json_error( array( 'message' => 'Lege opdracht.' ), 400 );
	}
	$reply = ekinese_admin_ai_ask( $instruction );
	if ( is_wp_error( $reply ) ) {
		wp_send_json_error( array( 'message' => $reply->get_error_message() ), 400 );
	}
	if ( $agent_id ) {
		update_post_meta( $agent_id, 'last_output', $reply );
		update_post_meta( $agent_id, 'last_run', current_time( 'mysql' ) );
	}
	wp_send_json_success( array( 'reply' => $reply ) );
} );

/* =====================================================================
   CPT  xg_agent  — zelf te maken AI-agents
===================================================================== */
function ekinese_register_agent_cpt() {
	register_post_type( 'xg_agent', array(
		'labels'    => array( 'name' => __( 'AI-agents', 'ekinese' ), 'singular_name' => __( 'AI-agent', 'ekinese' ), 'menu_name' => __( 'AI-agents', 'ekinese' ), 'add_new_item' => __( 'Nieuwe agent', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-superhero',
		'supports'  => array( 'title', 'editor' ),
	) );
	register_post_meta( 'xg_agent', 'schedule', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	register_post_meta( 'xg_agent', 'last_output', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	register_post_meta( 'xg_agent', 'last_run', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
}
add_action( 'init', 'ekinese_register_agent_cpt' );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_agent_meta', __( 'Agent-instellingen', 'ekinese' ), function ( $post ) {
		wp_nonce_field( 'xg_agent_save', 'xg_agent_nonce' );
		$sched = get_post_meta( $post->ID, 'schedule', true );
		echo '<p class="description">' . esc_html__( 'Schrijf in de editor hierboven de instructie/rol van deze agent (bijv. "Vat de openstaande taken samen en stel prioriteiten voor vandaag").', 'ekinese' ) . '</p>';
		echo '<p><label><input type="checkbox" name="xg_agent_schedule" value="daily" ' . checked( $sched, 'daily', false ) . '> ' . esc_html__( 'Dagelijks automatisch draaien', 'ekinese' ) . '</label></p>';
		$out = get_post_meta( $post->ID, 'last_output', true );
		if ( $out ) {
			echo '<p><strong>' . esc_html__( 'Laatste resultaat', 'ekinese' ) . '</strong> (' . esc_html( get_post_meta( $post->ID, 'last_run', true ) ) . '):</p>';
			echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px;max-height:200px;overflow:auto;white-space:pre-wrap">' . esc_html( $out ) . '</div>';
		}
	}, 'xg_agent', 'side', 'default' );
} );

add_action( 'save_post_xg_agent', function ( $post_id ) {
	if ( ! isset( $_POST['xg_agent_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_agent_nonce'] ), 'xg_agent_save' ) ) {
		return;
	}
	update_post_meta( $post_id, 'schedule', ! empty( $_POST['xg_agent_schedule'] ) ? 'daily' : '' );
} );

/* ---- Cron: dagelijkse agents draaien ---- */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_agent_daily' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 7:00' ), 'daily', 'xg_agent_daily' );
	}
} );
add_action( 'xg_agent_daily', function () {
	if ( ! post_type_exists( 'xg_agent' ) ) {
		return;
	}
	$agents = get_posts( array( 'post_type' => 'xg_agent', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'schedule', 'value' => 'daily' ) ) ) );
	foreach ( $agents as $aid ) {
		$reply = ekinese_admin_ai_ask( get_post_field( 'post_content', $aid ) );
		if ( ! is_wp_error( $reply ) ) {
			update_post_meta( $aid, 'last_output', $reply );
			update_post_meta( $aid, 'last_run', current_time( 'mysql' ) );
		}
	}
} );

/* =====================================================================
   RENDER — paneel bovenaan het XGOUD-overzicht
===================================================================== */
function ekinese_dashboard_panels() {
	// Takenlijst opslaan (afvinken) — per dag in een optie.
	$day_key = 'xg_tasks_done_' . gmdate( 'Ymd' );
	if ( isset( $_POST['xg_tasks_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_tasks_nonce'] ), 'xg_tasks' ) ) {
		$done = array_map( 'sanitize_key', (array) ( $_POST['xg_task'] ?? array() ) );
		update_option( $day_key, $done, false );
	}
	$done = (array) get_option( $day_key, array() );

	$eur = function ( $n, $d = 2 ) { return '€ ' . number_format_i18n( (float) $n, $d ); };

	/* --- Metaalprijzen (999) --- */
	echo '<h2 style="margin-top:18px">Metaalprijzen (999)</h2>';
	echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">';
	foreach ( ekinese_dashboard_metal_prices() as $m ) {
		echo '<div style="background:#161412;color:#fff;padding:16px"><div style="font-size:12px;color:#b3ad9f">' . esc_html( $m['label'] ) . '</div>'
			. '<div style="font-size:24px;font-weight:800">' . esc_html( $eur( $m['gram'] ) ) . '<span style="font-size:12px;font-weight:400;color:#cfc6b4"> /g</span></div>'
			. '<div style="font-size:12px;color:#b3ad9f">' . esc_html( $eur( $m['kilo'], 0 ) ) . ' /kg</div></div>';
	}
	echo '</div>';

	/* --- KPI: nieuw vandaag/deze week --- */
	$new_appt  = ekinese_dashboard_since( 'xg_appointment', 7 );
	$new_sales = ekinese_dashboard_count( 'xg_appointment', array( array( 'key' => 'status', 'value' => array( 'completed', 'paid', 'afgerond', 'uitbetaald' ), 'compare' => 'IN' ) ) )
		+ ekinese_dashboard_count( 'xg_auction', array( array( 'key' => 'paid', 'value' => '1' ) ) )
		+ ekinese_dashboard_count( 'xg_market', array( array( 'key' => 'status', 'value' => 'sold' ) ), array( 'publish', 'draft' ) );
	$new_market = ekinese_dashboard_since( 'xg_market', 7, array( 'publish' ) );
	$kpis = array(
		array( 'Nieuwe afspraken (7d)', $new_appt, admin_url( 'edit.php?post_type=xg_appointment' ) ),
		array( 'Verkopen (afgerond/betaald)', $new_sales, admin_url( 'edit.php?post_type=xg_appointment' ) ),
		array( 'Nieuwe marktplaats-producten (7d)', $new_market, admin_url( 'edit.php?post_type=xg_market' ) ),
	);
	if ( post_type_exists( 'xg_moment' ) ) {
		$mc = wp_count_posts( 'xg_moment' );
		$kpis[] = array(
			'Momenten (online · ' . (int) $mc->pending . ' wachtend)',
			(int) $mc->publish,
			admin_url( 'edit.php?post_type=xg_moment' ),
		);
	}
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px">';
	foreach ( $kpis as $k ) {
		echo '<a href="' . esc_url( $k[2] ) . '" style="text-decoration:none;background:#fff;border:1px solid #dcdcde;padding:16px"><div style="font-size:28px;font-weight:800;color:#AE1E1E">' . esc_html( $k[1] ) . '</div><div style="font-size:13px;color:#646970">' . esc_html( $k[0] ) . '</div></a>';
	}
	echo '</div>';

	/* --- Twee kolommen: taken + recente activiteit --- */
	echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px">';

	/* Dagelijkse takenlijst */
	$tasks = ekinese_daily_tasks();
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px">';
	echo '<h2 style="margin-top:0;font-size:15px">Dagelijkse taken <span style="color:#646970;font-weight:400">(' . esc_html( date_i18n( 'd-m-Y' ) ) . ')</span></h2>';
	if ( ! $tasks ) {
		echo '<p>🎉 Geen openstaande taken. Alles is bij.</p>';
	} else {
		echo '<form method="post"><ul style="margin:0;list-style:none">';
		foreach ( $tasks as $t ) {
			$checked = in_array( $t['key'], $done, true );
			echo '<li style="padding:6px 0;border-bottom:1px solid #f0f0f1;display:flex;align-items:center;gap:8px">';
			echo '<input type="checkbox" name="xg_task[]" value="' . esc_attr( $t['key'] ) . '" ' . checked( $checked, true, false ) . '>';
			echo '<span style="flex:1' . ( $checked ? ';text-decoration:line-through;color:#8c8f94' : '' ) . '">' . esc_html( $t['label'] ) . ' <strong>(' . esc_html( $t['count'] ) . ')</strong></span>';
			echo '<a href="' . esc_url( $t['link'] ) . '" class="button button-small">Open</a></li>';
		}
		echo '</ul>';
		wp_nonce_field( 'xg_tasks', 'xg_tasks_nonce' );
		echo '<p style="margin:10px 0 0"><button class="button button-primary">Voortgang opslaan</button></p></form>';
	}
	echo '</div>';

	/* Recente activiteit */
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px">';
	echo '<h2 style="margin-top:0;font-size:15px">Recente activiteit</h2>';
	$render_list = function ( $title, $posts, $fmt ) {
		echo '<h3 style="font-size:13px;margin:10px 0 4px">' . esc_html( $title ) . '</h3>';
		if ( ! $posts ) {
			echo '<p style="color:#8c8f94;margin:0">—</p>';
			return;
		}
		echo '<ul style="margin:0">';
		foreach ( $posts as $p ) {
			echo '<li><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( $fmt( $p ) ) . '</a></li>';
		}
		echo '</ul>';
	};
	$render_list( 'Laatste afspraken', ekinese_dashboard_recent( 'xg_appointment', 4 ), function ( $p ) {
		return ( get_post_meta( $p->ID, 'date', true ) ?: get_the_date( 'd-m', $p ) ) . ' · ' . ( get_post_meta( $p->ID, 'service', true ) ?: $p->post_title ) . ' (' . ( get_post_meta( $p->ID, 'status', true ) ?: 'nieuw' ) . ')';
	} );
	$render_list( 'Laatste marktplaats-advertenties', ekinese_dashboard_recent( 'xg_market', 4 ), function ( $p ) {
		return $p->post_title . ' — € ' . number_format_i18n( (float) get_post_meta( $p->ID, 'price', true ), 0 );
	} );
	$render_list( 'Laatste veilingen', ekinese_dashboard_recent( 'xg_auction', 4 ), function ( $p ) {
		return $p->post_title . ' — ' . ( get_post_meta( $p->ID, 'status', true ) ?: 'live' );
	} );
	echo '</div>';

	echo '</div>'; // grid

	/* --- Inbox: chats, tickets, productvragen op één plek --- */
	ekinese_dashboard_inbox();

	/* --- Chauffeurs: live positie + ETA + onkosten --- */
	if ( function_exists( 'ekinese_fleet_panel' ) ) {
		ekinese_fleet_panel();
	}

	/* --- Bezoekers-inzichten (interne analytics) --- */
	if ( function_exists( 'ekinese_analytics_panel' ) ) {
		ekinese_analytics_panel();
	}

	/* --- AI & Agents --- */
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-top:18px">';
	echo '<h2 style="margin-top:0;font-size:15px">🤖 XGOUD AI-assistent &amp; agents</h2>';
	if ( ! function_exists( 'ekinese_ai_enabled' ) || ! ekinese_ai_enabled() ) {
		echo '<p>Verbind eerst Claude: vul uw Anthropic API-key in onder <a href="' . esc_url( admin_url( 'options-general.php?page=xg-integrations' ) ) . '">XGOUD → Integraties</a>. Daarna kunt u hier vragen stellen en agents draaien.</p>';
	} else {
		$nonce = wp_create_nonce( 'xg_admin_ai' );
		echo '<p>Stel een vraag over uw bedrijfsdata (Claude krijgt de actuele cijfers + taken mee):</p>';
		echo '<div id="xg-ai" data-nonce="' . esc_attr( $nonce ) . '">';
		echo '<textarea id="xg-ai-q" rows="2" style="width:100%" placeholder="Bijv. Vat de openstaande taken samen en geef mij de top 3 prioriteiten voor vandaag."></textarea>';
		echo '<p><button class="button button-primary" id="xg-ai-ask">Vraag aan Claude</button> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=xg_agent' ) ) . '">+ Nieuwe agent</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=xg_agent' ) ) . '">Alle agents</a></p>';
		echo '<div id="xg-ai-out" style="display:none;background:#f6f7f7;border:1px solid #dcdcde;padding:12px;white-space:pre-wrap;margin-top:8px"></div>';

		// Bestaande agents met "Run nu".
		$agents = get_posts( array( 'post_type' => 'xg_agent', 'post_status' => 'publish', 'numberposts' => 10 ) );
		if ( $agents ) {
			echo '<h3 style="font-size:13px;margin:16px 0 6px">Uw agents</h3><ul style="margin:0">';
			foreach ( $agents as $a ) {
				$sched = get_post_meta( $a->ID, 'schedule', true ) === 'daily' ? ' <span style="color:#2271b1">· dagelijks</span>' : '';
				echo '<li style="padding:4px 0"><button class="button button-small xg-ai-run" data-agent="' . esc_attr( $a->ID ) . '">Run</button> <strong>' . esc_html( $a->post_title ) . '</strong>' . $sched . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
	echo '</div>';
}

/** Kleine JS voor het AI-paneel (alleen op de XGOUD-overzichtspagina). */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_xgoud' !== $hook ) {
		return;
	}
	// Leaflet voor de live chauffeurskaart.
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	$js = get_theme_file_path( 'assets/js/admin-dashboard.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'xg-admin-dashboard', get_theme_file_uri( 'assets/js/admin-dashboard.js' ), array( 'leaflet' ), (string) filemtime( $js ), true );
	}
} );
