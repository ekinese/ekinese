<?php
/**
 * XGOUD Formulieren – plugin-vrij (geen Contact Form 7 e.d.).
 *
 * - CPT xg_lead: opslag van inzendingen, zichtbaar in het admin-menu.
 * - Blok ekinese/form: een herbruikbaar formulier dat je op elke pagina kunt
 *   plaatsen (bv. "Robijn verkopen"). Onderwerp + velden via blok-attributen.
 * - REST ekinese/v1/lead: verwerkt de inzending (honeypot + optioneel reCAPTCHA),
 *   slaat op als lead en mailt naar het bedrijf.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT  xg_lead  (inzendingen, alleen backend)
===================================================================== */
function ekinese_register_leads() {
	register_post_type(
		'xg_lead',
		array(
			'labels'       => array(
				'name'          => __( 'Aanvragen', 'ekinese' ),
				'singular_name' => __( 'Aanvraag', 'ekinese' ),
				'menu_name'     => __( 'Aanvragen', 'ekinese' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-email-alt',
			'supports'     => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap' => true,
		)
	);
	foreach ( array( 'name', 'email', 'phone', 'subject', 'page' ) as $f ) {
		register_post_meta( 'xg_lead', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_leads' );

/* =====================================================================
   BLOK  ekinese/form
===================================================================== */
function ekinese_register_form_block() {
	register_block_type(
		'ekinese/form',
		array(
			'attributes'      => array(
				'subject'     => array( 'type' => 'string', 'default' => '' ),
				'title'       => array( 'type' => 'string', 'default' => 'Vraag een vrijblijvende indicatie aan' ),
				'intro'       => array( 'type' => 'string', 'default' => 'Laat uw gegevens achter — wij nemen snel contact met u op.' ),
				'button'      => array( 'type' => 'string', 'default' => 'Verstuur aanvraag' ),
				'showPhone'   => array( 'type' => 'boolean', 'default' => true ),
				'showMessage' => array( 'type' => 'boolean', 'default' => true ),
			),
			'render_callback' => 'ekinese_render_form',
		)
	);
}
add_action( 'init', 'ekinese_register_form_block' );

function ekinese_render_form( $attr ) {
	$subject  = ! empty( $attr['subject'] ) ? $attr['subject'] : ( is_singular() ? get_the_title() : 'Aanvraag' );
	$endpoint = esc_url( rest_url( 'ekinese/v1/lead' ) );
	ob_start();
	?>
	<div class="xg-form-wrap">
	<form class="xg-form" data-xg-form data-endpoint="<?php echo $endpoint; // phpcs:ignore ?>">
		<?php if ( ! empty( $attr['title'] ) ) : ?><h3 class="xg-form-title"><?php echo esc_html( $attr['title'] ); ?></h3><?php endif; ?>
		<?php if ( ! empty( $attr['intro'] ) ) : ?><p class="xg-form-intro"><?php echo esc_html( $attr['intro'] ); ?></p><?php endif; ?>
		<input type="hidden" name="subject" value="<?php echo esc_attr( $subject ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_url( is_singular() ? get_permalink() : home_url( '/' ) ); ?>">
		<!-- honeypot (verborgen voor mensen) -->
		<div class="xg-form-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
		<div class="xg-form-row">
			<label>Naam<input type="text" name="name" required></label>
			<label>E-mail<input type="email" name="email" required></label>
		</div>
		<?php if ( ! empty( $attr['showPhone'] ) ) : ?>
		<div class="xg-form-row"><label>Telefoon<input type="tel" name="phone"></label></div>
		<?php endif; ?>
		<?php if ( ! empty( $attr['showMessage'] ) ) : ?>
		<label>Bericht<textarea name="message" rows="4"></textarea></label>
		<?php endif; ?>
		<button type="submit" class="xg-form-btn"><?php echo esc_html( $attr['button'] ); ?></button>
		<div class="xg-form-msg" role="status" hidden></div>
	</form>
	</div>
	<?php
	return ob_get_clean();
}

/** Assets alleen laden waar het blok staat. */
function ekinese_form_assets() {
	if ( is_singular() && ( has_block( 'ekinese/form', get_post() ) || is_singular( 'xg_product' ) ) ) {
		wp_enqueue_script( 'ekinese-forms', get_theme_file_uri( 'assets/js/forms.js' ), array(), filemtime( get_theme_file_path( 'assets/js/forms.js' ) ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_form_assets' );

/* =====================================================================
   REST  ekinese/v1/lead
===================================================================== */
function ekinese_lead_routes() {
	register_rest_route(
		'ekinese/v1',
		'/lead',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'ekinese_lead_submit',
		)
	);
}
add_action( 'rest_api_init', 'ekinese_lead_routes' );

function ekinese_lead_submit( $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_params();
	}

	// Honeypot: bot vulde het verborgen veld → doe alsof het lukte, sla niets op.
	if ( ! empty( $p['website'] ) ) {
		return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt!' ) );
	}

	$name    = sanitize_text_field( $p['name'] ?? '' );
	$email   = sanitize_email( $p['email'] ?? '' );
	$phone   = sanitize_text_field( $p['phone'] ?? '' );
	$message = sanitize_textarea_field( $p['message'] ?? '' );
	$subject = sanitize_text_field( $p['subject'] ?? 'Aanvraag' );
	$page    = esc_url_raw( $p['page'] ?? '' );

	if ( '' === $name || ! is_email( $email ) ) {
		return new WP_Error( 'xg_lead_invalid', 'Vul uw naam en een geldig e-mailadres in.', array( 'status' => 400 ) );
	}

	// Optionele reCAPTCHA (alleen als er een token + key is).
	if ( ! empty( $p['token'] ) && function_exists( 'ekinese_recaptcha_verify' ) ) {
		ekinese_recaptcha_verify( $p['token'], 'lead' );
	}

	$body_lines = array(
		'Onderwerp: ' . $subject,
		'Naam: ' . $name,
		'E-mail: ' . $email,
		'Telefoon: ' . $phone,
		'Pagina: ' . $page,
		'',
		'Bericht:',
		$message,
	);
	$body = implode( "\n", $body_lines );

	// Opslaan als lead.
	$id = wp_insert_post(
		array(
			'post_type'    => 'xg_lead',
			'post_status'  => 'private',
			'post_title'   => $subject . ' — ' . $name,
			'post_content' => $body,
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'name', $name );
		update_post_meta( $id, 'email', $email );
		update_post_meta( $id, 'phone', $phone );
		update_post_meta( $id, 'subject', $subject );
		update_post_meta( $id, 'page', $page );
	}

	// Mailen naar het bedrijf.
	$to      = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );
	wp_mail( $to, 'Nieuwe aanvraag: ' . $subject, $body, $headers );

	return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt! We nemen snel contact met u op.' ) );
}
