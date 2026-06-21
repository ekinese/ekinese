<?php
/**
 * XGOUD Mail-flows – levenscyclus-mails met logica + centraal log.
 *
 * Eén plek voor geautomatiseerde klantmails: welkom bij aanmelding, herinnering
 * vóór een afspraak, en een centraal verzendlog (audit) van álle uitgaande mail.
 * Werkt samen met de bestaande transactionele mails (afspraak/ticket/zending).
 * Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CENTRAAL VERZENDLOG  (alle wp_mail)
===================================================================== */
function ekinese_register_maillog() {
	register_post_type( 'xg_maillog', array(
		'labels'    => array( 'name' => __( 'Mail-log', 'ekinese' ), 'singular_name' => __( 'Mail', 'ekinese' ), 'menu_name' => __( 'Mail-log', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-email-alt',
		'supports'  => array( 'title', 'editor' ),
		'capabilities' => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap' => true,
	) );
}
add_action( 'init', 'ekinese_register_maillog' );

/** Elke uitgaande mail loggen (afgekapt). */
function ekinese_mail_log( $args ) {
	// Niet falen als er iets mis is met $args.
	$to      = is_array( $args['to'] ?? '' ) ? implode( ', ', $args['to'] ) : (string) ( $args['to'] ?? '' );
	$subject = (string) ( $args['subject'] ?? '' );
	$id      = wp_insert_post( array(
		'post_type'    => 'xg_maillog',
		'post_status'  => 'publish',
		'post_title'   => mb_substr( $subject, 0, 120 ) . ' → ' . $to,
		'post_content' => mb_substr( (string) ( $args['message'] ?? '' ), 0, 4000 ),
	), false );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'to', $to );
		update_post_meta( $id, 'sent', current_time( 'mysql' ) );
	}
	// Oud log opschonen (max ~500).
	if ( wp_rand( 1, 25 ) === 1 ) {
		$old = get_posts( array( 'post_type' => 'xg_maillog', 'numberposts' => 100, 'offset' => 500, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( $old as $oid ) {
			wp_delete_post( $oid, true );
		}
	}
	return $args;
}
add_filter( 'wp_mail', 'ekinese_mail_log', 99 );

/* =====================================================================
   WELKOM – bij nieuwsbrief-aanmelding
===================================================================== */
function ekinese_mail_welcome( $post_id ) {
	if ( get_post_type( $post_id ) !== 'xg_subscriber' ) {
		return;
	}
	if ( get_post_meta( $post_id, '_xg_welcomed', true ) ) {
		return;
	}
	$email = get_post_meta( $post_id, 'email', true ) ?: get_the_title( $post_id );
	if ( ! $email || ! is_email( $email ) ) {
		return;
	}
	update_post_meta( $post_id, '_xg_welcomed', '1' );
	wp_mail(
		$email,
		__( 'Welkom bij XGOUD', 'ekinese' ),
		"Bedankt voor uw aanmelding!\n\nU ontvangt voortaan onze actuele dagprijzen en aanbiedingen. Wilt u nu al uw edelmetaal laten taxeren? Maak eenvoudig een afspraak: " . home_url( '/afspraak/' ) . "\n\nMet vriendelijke groet,\nXGOUD"
	);
}
add_action( 'save_post_xg_subscriber', 'ekinese_mail_welcome', 20 );

/* =====================================================================
   HERINNERING – 1 dag vóór de afspraak (dagelijkse cron)
===================================================================== */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_appt_reminders' ) ) {
		wp_schedule_event( time() + 3600, 'daily', 'xg_appt_reminders' );
	}
} );

function ekinese_appt_reminders() {
	$tomorrow = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
	$appts    = get_posts( array(
		'post_type'   => 'xg_appointment',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'date', 'value' => $tomorrow ),
			array( 'key' => '_xg_reminded', 'compare' => 'NOT EXISTS' ),
		),
	) );
	foreach ( $appts as $a ) {
		$email = get_post_meta( $a->ID, 'email', true );
		if ( ! $email || ! is_email( $email ) ) {
			continue;
		}
		$time = get_post_meta( $a->ID, 'time', true );
		wp_mail(
			$email,
			__( 'Herinnering: uw afspraak bij XGOUD morgen', 'ekinese' ),
			sprintf( "Beste klant,\n\nDit is een herinnering aan uw afspraak morgen%s. Wij kijken ernaar uit u te helpen.\n\nTot ziens,\nXGOUD", $time ? ' om ' . $time : '' )
		);
		update_post_meta( $a->ID, '_xg_reminded', '1' );
	}
}
add_action( 'xg_appt_reminders', 'ekinese_appt_reminders' );

/* ---- Mail-log read-only kolom ---- */
function ekinese_maillog_columns( $cols ) {
	$cols['xg_to']   = __( 'Aan', 'ekinese' );
	$cols['xg_sent'] = __( 'Verzonden', 'ekinese' );
	return $cols;
}
add_filter( 'manage_xg_maillog_posts_columns', 'ekinese_maillog_columns' );
function ekinese_maillog_column( $col, $post_id ) {
	if ( 'xg_to' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'to', true ) );
	} elseif ( 'xg_sent' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'sent', true ) );
	}
}
add_action( 'manage_xg_maillog_posts_custom_column', 'ekinese_maillog_column', 10, 2 );
