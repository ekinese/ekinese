<?php
/**
 * XGOUD funnel — één centrale inbox voor álle communicatie.
 *
 * Bundelt tickets, productvragen, chats, nieuwe afspraken/leads en treuhand-
 * aanvragen op één plek, met een deadline (SLA) per soort. Toont wat het eerst
 * beantwoord moet worden, markeert wat over tijd is en filtert op werkgebied.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bronnen in de funnel. sla = uren tot deadline; open = hoe "onbeantwoord"
 * wordt herkend; area = werkgebied (voor de rolfilter).
 */
function ekinese_funnel_sources() {
	return apply_filters( 'ekinese_funnel_sources', array(
		'ticket'      => array( 'pt' => 'xg_ticket',      'label' => 'Ticket',        'sla' => 24, 'area' => 'operatie',  'open' => array( 'status' => array( 'open', '', 'new', 'nieuw' ) ) ),
		'question'    => array( 'pt' => 'xg_question',    'label' => 'Productvraag',   'sla' => 24, 'area' => 'operatie',  'open' => array( 'status' => array( 'pending', '', 'open' ) ) ),
		'chat'        => array( 'pt' => 'xg_chat',        'label' => 'Chat',           'sla' => 4,  'area' => 'operatie',  'open' => array( 'status' => array( 'open', '', 'new' ) ) ),
		'appointment' => array( 'pt' => 'xg_appointment', 'label' => 'Nieuwe afspraak','sla' => 12, 'area' => 'operatie',  'open' => array( 'status' => array( '', 'new', 'nieuw', 'pending', 'aangevraagd' ) ) ),
		'escrow'      => array( 'pt' => 'xg_escrow',      'label' => 'Treuhand-aanvraag','sla' => 48, 'area' => 'operatie', 'open' => array( 'status' => array( 'nieuw' ) ) ),
	) );
}

/** Verzamel open funnel-items, gesorteerd op deadline (urgentst eerst). */
function ekinese_funnel_items( $area = '' ) {
	$now   = current_time( 'timestamp' );
	$items = array();
	foreach ( ekinese_funnel_sources() as $key => $src ) {
		if ( ! post_type_exists( $src['pt'] ) ) {
			continue;
		}
		if ( $area && $src['area'] !== $area ) {
			continue;
		}
		$status_vals = $src['open']['status'] ?? array();
		$posts = get_posts( array(
			'post_type'      => $src['pt'],
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => 100,
			'meta_query'     => $status_vals ? array(
				'relation' => 'OR',
				array( 'key' => 'status', 'value' => array_filter( $status_vals, 'strlen' ), 'compare' => 'IN' ),
				array( 'key' => 'status', 'compare' => 'NOT EXISTS' ),
			) : array(),
		) );
		foreach ( $posts as $p ) {
			$created  = get_post_time( 'U', true, $p );
			$deadline = $created + (int) $src['sla'] * HOUR_IN_SECONDS;
			$overdue  = $now > $deadline;
			$items[]  = array(
				'source'   => $src['label'],
				'area'     => $src['area'],
				'title'    => wp_strip_all_tags( get_the_title( $p ) ) ?: ( '#' . $p->ID ),
				'who'      => (string) ( get_post_meta( $p->ID, 'email', true ) ?: get_post_meta( $p->ID, 'name', true ) ),
				'created'  => $created,
				'deadline' => $deadline,
				'overdue'  => $overdue,
				'hours_left' => round( ( $deadline - $now ) / HOUR_IN_SECONDS, 1 ),
				'link'     => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
			);
		}
	}
	usort( $items, function ( $a, $b ) { return $a['deadline'] - $b['deadline']; } );
	return $items;
}

/** Aantal items over de deadline. */
function ekinese_funnel_overdue_count() {
	$n = 0;
	foreach ( ekinese_funnel_items() as $i ) {
		if ( $i['overdue'] ) {
			$n++;
		}
	}
	return $n;
}

/* Daily task + AI-context. */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$over = ekinese_funnel_overdue_count();
	if ( $over > 0 ) {
		$tasks[] = array( 'key' => 'funnel_overdue', 'label' => 'Berichten over de deadline beantwoorden', 'count' => $over, 'link' => admin_url( 'admin.php?page=xg-funnel' ) );
	}
	return $tasks;
} );
add_filter( 'ekinese_dashboard_ai_context_lines', function ( $lines ) {
	$all  = ekinese_funnel_items();
	$over = 0;
	foreach ( $all as $i ) {
		if ( $i['overdue'] ) {
			$over++;
		}
	}
	if ( $all ) {
		$lines[] = sprintf( 'Funnel/inbox: %d open berichten, waarvan %d over de deadline.', count( $all ), $over );
	}
	return $lines;
} );

/* =====================================================================
   ADMIN-PAGINA  "Inbox"
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Inbox / Funnel', 'Inbox', 'edit_posts', 'xg-funnel', 'ekinese_funnel_page' );
}, 1 );

function ekinese_funnel_page() {
	$my   = function_exists( 'ekinese_current_area' ) ? ekinese_current_area() : '';
	$filt = isset( $_GET['area'] ) ? sanitize_key( $_GET['area'] ) : '';
	$items = ekinese_funnel_items( $filt );
	$areas = function_exists( 'ekinese_role_areas' ) ? ekinese_role_areas() : array();
	$over  = 0;
	foreach ( $items as $i ) {
		if ( $i['overdue'] ) {
			$over++;
		}
	}
	echo '<div class="wrap"><h1>Inbox / Funnel</h1>';
	echo '<p>Alle openstaande communicatie op één plek, gesorteerd op deadline. ' . (int) $over . ' over tijd.</p>';

	// Filter op werkgebied.
	echo '<p>Filter: <a href="' . esc_url( admin_url( 'admin.php?page=xg-funnel' ) ) . '" class="button' . ( '' === $filt ? ' button-primary' : '' ) . '">Alles</a> ';
	foreach ( $areas as $k => $a ) {
		$url = admin_url( 'admin.php?page=xg-funnel&area=' . $k );
		echo '<a href="' . esc_url( $url ) . '" class="button' . ( $filt === $k ? ' button-primary' : '' ) . '">' . esc_html( $a['label'] ) . ( $k === $my ? ' ★' : '' ) . '</a> ';
	}
	echo '</p>';

	if ( ! $items ) {
		echo '<p>🎉 Inbox leeg — niets te beantwoorden.</p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>Soort</th><th>Onderwerp</th><th>Van</th><th>Binnen</th><th>Deadline</th><th>Werkgebied</th><th></th></tr></thead><tbody>';
	foreach ( $items as $i ) {
		$rowbg = $i['overdue'] ? 'background:#fdecec' : ( $i['hours_left'] <= 4 ? 'background:#fff9ec' : '' );
		$dl    = $i['overdue']
			? '<strong style="color:#b32d2e">' . esc_html( abs( $i['hours_left'] ) ) . ' u te laat</strong>'
			: 'over ' . esc_html( $i['hours_left'] ) . ' u';
		echo '<tr style="' . esc_attr( $rowbg ) . '">';
		echo '<td>' . esc_html( $i['source'] ) . '</td>';
		echo '<td>' . esc_html( wp_trim_words( $i['title'], 9 ) ) . '</td>';
		echo '<td>' . esc_html( $i['who'] ) . '</td>';
		echo '<td>' . esc_html( human_time_diff( $i['created'], current_time( 'timestamp' ) ) ) . ' geleden</td>';
		echo '<td>' . $dl . '</td>'; // phpcs:ignore
		echo '<td>' . ( function_exists( 'ekinese_area_badge' ) ? ekinese_area_badge( $i['area'] ) : esc_html( $i['area'] ) ) . '</td>'; // phpcs:ignore
		echo '<td><a class="button button-small" href="' . esc_url( $i['link'] ) . '">Behandelen</a></td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}
