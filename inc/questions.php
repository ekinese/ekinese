<?php
/**
 * XGOUD Product-vragen (Q&A). Klanten stellen een vraag op een productpagina;
 * jij beantwoordt in het admin. Beantwoorde vragen verschijnen publiek op het
 * product; de vrager krijgt een melding + e-mail. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT  xg_question
===================================================================== */
function ekinese_register_questions() {
	register_post_type( 'xg_question', array(
		'labels'    => array( 'name' => __( 'Productvragen', 'ekinese' ), 'singular_name' => __( 'Vraag', 'ekinese' ), 'menu_name' => __( 'Productvragen', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-format-chat',
		'supports'  => array( 'editor' ),
	) );
	foreach ( array( 'product', 'email', 'name', 'answer', 'status' ) as $f ) {
		register_post_meta( 'xg_question', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_questions' );

/** Beantwoorde vragen voor een product. */
function ekinese_product_questions( $product_id ) {
	return get_posts( array(
		'post_type'   => 'xg_question',
		'post_status' => 'publish',
		'numberposts' => 50,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'product', 'value' => (string) $product_id ),
			array( 'key' => 'status', 'value' => 'answered' ),
		),
	) );
}

/** Sectie met Q&A + vraagformulier (op de productpagina). */
function ekinese_render_product_questions( $product_id ) {
	$qs       = ekinese_product_questions( $product_id );
	$endpoint = esc_url( rest_url( 'ekinese/v1/question' ) );
	ob_start();
	echo '<section class="xg-price-section"><div class="xg-container">';
	echo '<h2 class="xg-section-title">Vragen over dit product</h2>';
	if ( $qs ) {
		echo '<div class="xg-qa-list">';
		foreach ( $qs as $q ) {
			echo '<div class="xg-qa">';
			echo '<p class="xg-qa-q"><strong>V:</strong> ' . esc_html( wp_strip_all_tags( $q->post_content ) ) . '</p>';
			echo '<p class="xg-qa-a"><strong>XGOUD:</strong> ' . esc_html( get_post_meta( $q->ID, 'answer', true ) ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	} else {
		echo '<p>Er zijn nog geen vragen. Stel als eerste een vraag — wij beantwoorden deze graag.</p>';
	}
	echo '<form class="xg-qa-form" data-xg-form data-endpoint="' . $endpoint . '">';
	echo '<h3>Stel een vraag</h3>';
	echo '<div class="xg-form-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	echo '<input type="hidden" name="product" value="' . esc_attr( $product_id ) . '">';
	echo '<div class="xg-form-row"><label>Uw naam<input type="text" name="name"></label><label>Uw e-mail<input type="email" name="email" required></label></div>';
	echo '<label>Uw vraag<textarea name="message" rows="3" required></textarea></label>';
	echo '<button type="submit" class="xg-form-btn">Vraag versturen</button>';
	echo '<div class="xg-form-msg" role="status" hidden></div>';
	echo '</form>';
	echo '</div></section>';
	return ob_get_clean();
}

/* =====================================================================
   REST – vraag indienen
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/question', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_question_submit',
	) );
} );

function ekinese_question_submit( WP_REST_Request $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_params();
	}
	if ( ! empty( $p['website'] ) ) { // honeypot
		return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt!' ) );
	}
	$email    = sanitize_email( $p['email'] ?? '' );
	$name     = sanitize_text_field( $p['name'] ?? '' );
	$product  = (int) ( $p['product'] ?? 0 );
	$question = sanitize_textarea_field( $p['message'] ?? '' );
	if ( ! is_email( $email ) || '' === $question || ! $product ) {
		return new WP_Error( 'invalid', 'Vul uw e-mail en uw vraag in.', array( 'status' => 400 ) );
	}
	$id = wp_insert_post( array(
		'post_type'    => 'xg_question',
		'post_status'  => 'publish',
		'post_title'   => 'Vraag: ' . get_the_title( $product ),
		'post_content' => $question,
	) );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'product', (string) $product );
		update_post_meta( $id, 'email', $email );
		update_post_meta( $id, 'name', $name );
		update_post_meta( $id, 'status', 'pending' );
	}
	$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
	wp_mail( $admin, 'Nieuwe productvraag', sprintf( "Nieuwe vraag over '%s' van %s <%s>:\n\n%s\n\nBeantwoord in het admin onder Productvragen.", get_the_title( $product ), $name, $email, $question ) );
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt! Uw vraag is verstuurd. U ontvangt bericht zodra wij hebben geantwoord.' ) );
}

/* =====================================================================
   ADMIN – beantwoorden
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_question_answer', __( 'Antwoord', 'ekinese' ), 'ekinese_question_answer_box', 'xg_question', 'normal', 'high' );
} );

function ekinese_question_answer_box( $post ) {
	wp_nonce_field( 'xg_question_save', 'xg_question_nonce' );
	$product = (int) get_post_meta( $post->ID, 'product', true );
	$answer  = get_post_meta( $post->ID, 'answer', true );
	$status  = get_post_meta( $post->ID, 'status', true );
	echo '<p><strong>Product:</strong> ' . ( $product ? '<a href="' . esc_url( get_edit_post_link( $product ) ) . '">' . esc_html( get_the_title( $product ) ) . '</a>' : '—' ) . '</p>';
	echo '<p><strong>Van:</strong> ' . esc_html( get_post_meta( $post->ID, 'name', true ) ) . ' &lt;' . esc_html( get_post_meta( $post->ID, 'email', true ) ) . '&gt; · Status: ' . esc_html( $status ?: 'pending' ) . '</p>';
	echo '<p><strong>Vraag:</strong><br>' . esc_html( $post->post_content ) . '</p>';
	echo '<p><label><strong>Uw antwoord</strong> (publiceren → klant krijgt melding/e-mail)<br><textarea name="xg_answer" rows="4" style="width:100%">' . esc_textarea( $answer ) . '</textarea></label></p>';
}

function ekinese_question_save( $post_id ) {
	if ( ! isset( $_POST['xg_question_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_question_nonce'] ), 'xg_question_save' ) ) {
		return;
	}
	if ( ! isset( $_POST['xg_answer'] ) ) {
		return;
	}
	$answer = sanitize_textarea_field( wp_unslash( $_POST['xg_answer'] ) );
	$was    = get_post_meta( $post_id, 'status', true );
	update_post_meta( $post_id, 'answer', $answer );
	if ( '' !== trim( $answer ) && 'answered' !== $was ) {
		update_post_meta( $post_id, 'status', 'answered' );
		$email   = get_post_meta( $post_id, 'email', true );
		$product = (int) get_post_meta( $post_id, 'product', true );
		$url     = $product ? get_permalink( $product ) : '';
		if ( is_email( $email ) ) {
			wp_mail( $email, 'Antwoord op uw vraag — XGOUD', sprintf( "Beste,\n\nUw vraag over '%s' is beantwoord:\n\n%s\n\nBekijk het product: %s\n\nXGOUD", get_the_title( $product ), $answer, $url ) );
			if ( function_exists( 'ekinese_notify' ) ) {
				ekinese_notify( $email, 'Uw vraag is beantwoord', sprintf( "Uw vraag over '%s' is beantwoord.", get_the_title( $product ) ), $url, 'info' );
			}
		}
	}
}
add_action( 'save_post_xg_question', 'ekinese_question_save' );
