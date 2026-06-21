<?php
/**
 * XGOUD Ticketsysteem (xg_ticket).
 *
 * Self-built support-/vragen-systeem: klant stuurt een ticket (REST), krijgt
 * een referentienummer + bevestigingsmail, kan de status volgen via een token.
 * Beheer in de WP-admin. Geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** CPT. */
function ekinese_register_ticket_cpt() {
	register_post_type( 'xg_ticket', array(
		'labels'       => array(
			'name'          => __( 'Tickets', 'ekinese' ),
			'singular_name' => __( 'Ticket', 'ekinese' ),
			'menu_name'     => __( 'Tickets', 'ekinese' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_rest' => false,
		'menu_icon'    => 'dashicons-tickets-alt',
		'supports'     => array( 'title', 'editor' ),
	) );
}
add_action( 'init', 'ekinese_register_ticket_cpt' );

/** Statuslabels. */
function ekinese_ticket_statuses() {
	return array(
		'open'        => __( 'Open', 'ekinese' ),
		'in_progress' => __( 'In behandeling', 'ekinese' ),
		'waiting'     => __( 'Wacht op klant', 'ekinese' ),
		'resolved'    => __( 'Opgelost', 'ekinese' ),
		'closed'      => __( 'Gesloten', 'ekinese' ),
	);
}

/** Uniek referentienummer: XG-TK-JJJJMM-XXXX. */
function ekinese_ticket_reference() {
	return sprintf( 'XG-TK-%s-%04d', gmdate( 'Ym' ), wp_rand( 0, 9999 ) );
}

/* =====================================================================
   REST  – nieuw ticket + status opvragen
===================================================================== */
function ekinese_tickets_rest() {
	register_rest_route( 'ekinese/v1', '/ticket', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_ticket_create',
	) );
	register_rest_route( 'ekinese/v1', '/ticket/(?P<token>[a-f0-9]{32})', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_ticket_status',
	) );
}
add_action( 'rest_api_init', 'ekinese_tickets_rest' );

function ekinese_ticket_create( WP_REST_Request $req ) {
	// Anti-spam: optionele reCAPTCHA-verificatie.
	if ( function_exists( 'ekinese_recaptcha_verify' ) && ! ekinese_recaptcha_verify( $req->get_param( 'recaptcha' ), 'ticket' ) ) {
		return new WP_Error( 'recaptcha', __( 'Verificatie mislukt.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$name    = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$email   = sanitize_email( (string) $req->get_param( 'email' ) );
	$subject = sanitize_text_field( (string) $req->get_param( 'subject' ) );
	$message = sanitize_textarea_field( (string) $req->get_param( 'message' ) );

	if ( ! $email || ! is_email( $email ) || '' === $message ) {
		return new WP_Error( 'invalid', __( 'Vul een geldig e-mailadres en bericht in.', 'ekinese' ), array( 'status' => 400 ) );
	}
	$ref   = ekinese_ticket_reference();
	$token = wp_generate_password( 32, false, false );

	$id = wp_insert_post( array(
		'post_type'    => 'xg_ticket',
		'post_status'  => 'publish',
		'post_title'   => $ref . ' – ' . ( $subject ?: __( 'Vraag', 'ekinese' ) ),
		'post_content' => $message,
	) );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', __( 'Opslaan mislukt.', 'ekinese' ), array( 'status' => 500 ) );
	}
	update_post_meta( $id, 'reference', $ref );
	update_post_meta( $id, 'token', $token );
	update_post_meta( $id, 'name', $name );
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'subject', $subject );
	update_post_meta( $id, 'status', 'open' );
	update_post_meta( $id, 'created', current_time( 'mysql' ) );

	// Bevestigingsmail naar klant.
	$track = home_url( '/ticket/?token=' . $token );
	wp_mail(
		$email,
		sprintf( __( 'Uw ticket %s is ontvangen', 'ekinese' ), $ref ),
		sprintf(
			"Beste %s,\n\nWe hebben uw vraag ontvangen onder referentie %s.\nU kunt de status volgen via:\n%s\n\nMet vriendelijke groet,\nXGOUD",
			$name ?: 'klant',
			$ref,
			$track
		)
	);
	// Interne notificatie.
	wp_mail( get_option( 'admin_email' ), 'Nieuw ticket: ' . $ref, $message . "\n\n" . $email );

	return rest_ensure_response( array( 'ok' => true, 'reference' => $ref, 'token' => $token, 'track' => $track ) );
}

function ekinese_ticket_status( WP_REST_Request $req ) {
	$token = sanitize_text_field( (string) $req['token'] );
	$q     = get_posts( array(
		'post_type'   => 'xg_ticket',
		'meta_key'    => 'token',
		'meta_value'  => $token,
		'numberposts' => 1,
		'post_status' => 'publish',
	) );
	if ( ! $q ) {
		return new WP_Error( 'notfound', __( 'Ticket niet gevonden.', 'ekinese' ), array( 'status' => 404 ) );
	}
	$id     = $q[0]->ID;
	$status = get_post_meta( $id, 'status', true ) ?: 'open';
	$labels = ekinese_ticket_statuses();
	return rest_ensure_response( array(
		'reference' => get_post_meta( $id, 'reference', true ),
		'subject'   => get_post_meta( $id, 'subject', true ),
		'status'    => $status,
		'status_label' => $labels[ $status ] ?? $status,
		'created'   => get_post_meta( $id, 'created', true ),
	) );
}

/* =====================================================================
   ADMIN – statusveld + kolom
===================================================================== */
function ekinese_ticket_metabox() {
	add_meta_box( 'xg_ticket_meta', __( 'Ticketgegevens', 'ekinese' ), 'ekinese_ticket_metabox_html', 'xg_ticket', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_ticket_metabox' );

function ekinese_ticket_metabox_html( $post ) {
	wp_nonce_field( 'xg_ticket_save', 'xg_ticket_nonce' );
	$status = get_post_meta( $post->ID, 'status', true ) ?: 'open';
	echo '<p><strong>Referentie:</strong> ' . esc_html( get_post_meta( $post->ID, 'reference', true ) ) . '</p>';
	echo '<p><strong>Naam:</strong> ' . esc_html( get_post_meta( $post->ID, 'name', true ) ) . '</p>';
	echo '<p><strong>E-mail:</strong> ' . esc_html( get_post_meta( $post->ID, 'email', true ) ) . '</p>';
	echo '<p><label><strong>Status</strong><br><select name="xg_ticket_status" style="width:100%">';
	foreach ( ekinese_ticket_statuses() as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></label></p>';
}

function ekinese_ticket_save( $post_id ) {
	if ( ! isset( $_POST['xg_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_ticket_nonce'] ), 'xg_ticket_save' ) ) {
		return;
	}
	if ( isset( $_POST['xg_ticket_status'] ) ) {
		$new = sanitize_key( $_POST['xg_ticket_status'] );
		$old = get_post_meta( $post_id, 'status', true );
		update_post_meta( $post_id, 'status', $new );
		// Klant mailen bij statuswijziging naar opgelost.
		if ( $new !== $old && in_array( $new, array( 'resolved', 'waiting' ), true ) ) {
			$email = get_post_meta( $post_id, 'email', true );
			$ref   = get_post_meta( $post_id, 'reference', true );
			if ( $email && is_email( $email ) ) {
				$labels = ekinese_ticket_statuses();
				wp_mail( $email, sprintf( 'Update ticket %s', $ref ), sprintf( "De status van uw ticket %s is gewijzigd naar: %s.\n\nMet vriendelijke groet,\nXGOUD", $ref, $labels[ $new ] ?? $new ) );
			}
		}
	}
}
add_action( 'save_post_xg_ticket', 'ekinese_ticket_save' );

/** Statuskolom in de lijst. */
function ekinese_ticket_columns( $cols ) {
	$cols['xg_status'] = __( 'Status', 'ekinese' );
	return $cols;
}
add_filter( 'manage_xg_ticket_posts_columns', 'ekinese_ticket_columns' );

function ekinese_ticket_column( $col, $post_id ) {
	if ( 'xg_status' === $col ) {
		$labels = ekinese_ticket_statuses();
		$s      = get_post_meta( $post_id, 'status', true ) ?: 'open';
		echo esc_html( $labels[ $s ] ?? $s );
	}
}
add_action( 'manage_xg_ticket_posts_custom_column', 'ekinese_ticket_column', 10, 2 );
