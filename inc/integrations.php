<?php
/**
 * XGOUD Integraties – Google Tag (GA4) + reCAPTCHA.
 *
 * Sleutels staan UITSLUITEND in de WP-opties (admin), nooit in de repo.
 * Self-built, geen plugin. Instellingen → Integraties.
 *
 *   - Google Tag (gtag.js / GA4): meet-ID in optie xg_gtag_id.
 *   - Google reCAPTCHA v3: site- + secret-key in opties.
 *     ekinese_recaptcha_verify( $token, $action ) voor formuliervalidatie.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   GOOGLE TAG (GA4)
===================================================================== */
/** gtag.js in de <head> – alleen wanneer een meet-ID is ingesteld. */
function ekinese_gtag_head() {
	$id = trim( (string) get_option( 'xg_gtag_id', '' ) );
	if ( '' === $id || is_admin() ) {
		return;
	}
	// Geen tracking in de adminbalk-preview / ingelogde redacteuren mag, maar
	// houden we simpel: respecteer DNT (Do Not Track).
	if ( ! empty( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) {
		return;
	}
	$id = esc_js( $id );
	?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('js',new Date());gtag('config','<?php echo $id; // phpcs:ignore ?>',{anonymize_ip:true});
</script>
	<?php
}
add_action( 'wp_head', 'ekinese_gtag_head', 1 );

/* =====================================================================
   reCAPTCHA v3
===================================================================== */
/** API-script laden (front-end) wanneer een site-key is ingesteld. */
function ekinese_recaptcha_enqueue() {
	$site = trim( (string) get_option( 'xg_recaptcha_site', '' ) );
	if ( '' === $site ) {
		return;
	}
	wp_enqueue_script( 'recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site ), array(), null, true ); // phpcs:ignore
	wp_add_inline_script( 'recaptcha', 'window.XG_RECAPTCHA=' . wp_json_encode( array( 'site' => $site ) ) . ';' );
}
add_action( 'wp_enqueue_scripts', 'ekinese_recaptcha_enqueue' );

/**
 * Server-side verificatie van een reCAPTCHA-token.
 *
 * @param string $token  Token uit grecaptcha.execute().
 * @param string $action Verwachte actie (optioneel).
 * @return bool True wanneer geldig (of wanneer reCAPTCHA niet is geconfigureerd).
 */
function ekinese_recaptcha_verify( $token, $action = '' ) {
	$secret = trim( (string) get_option( 'xg_recaptcha_secret', '' ) );
	if ( '' === $secret ) {
		return true; // Niet geconfigureerd → niet blokkeren.
	}
	if ( '' === (string) $token ) {
		return false;
	}
	$res = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return true; // Bij API-storing liever niet alle inzendingen weigeren.
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $data['success'] ) ) {
		return false;
	}
	if ( $action && isset( $data['action'] ) && $data['action'] !== $action ) {
		return false;
	}
	$threshold = (float) get_option( 'xg_recaptcha_threshold', 0.5 );
	if ( isset( $data['score'] ) && (float) $data['score'] < $threshold ) {
		return false;
	}
	return true;
}

/* =====================================================================
   ADMIN  →  Instellingen → Integraties
===================================================================== */
function ekinese_integrations_menu() {
	add_submenu_page( 'options-general.php', __( 'Integraties', 'ekinese' ), __( 'Integraties', 'ekinese' ), 'manage_options', 'xg-integrations', 'ekinese_integrations_page' );
}
add_action( 'admin_menu', 'ekinese_integrations_menu' );

function ekinese_integrations_page() {
	if ( isset( $_POST['xg_int_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_int_nonce'] ), 'xg_int' ) ) {
		update_option( 'xg_gtag_id', sanitize_text_field( wp_unslash( $_POST['xg_gtag_id'] ?? '' ) ) );
		update_option( 'xg_recaptcha_site', sanitize_text_field( wp_unslash( $_POST['xg_recaptcha_site'] ?? '' ) ) );
		update_option( 'xg_recaptcha_secret', sanitize_text_field( wp_unslash( $_POST['xg_recaptcha_secret'] ?? '' ) ) );
		update_option( 'xg_recaptcha_threshold', (float) ( $_POST['xg_recaptcha_threshold'] ?? 0.5 ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Opgeslagen.', 'ekinese' ) . '</p></div>';
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Integraties', 'ekinese' ) . '</h1>';
	echo '<p>' . esc_html__( 'Sleutels worden alleen hier opgeslagen (database), nooit in de themacode.', 'ekinese' ) . '</p>';
	echo '<form method="post"><table class="form-table">';
	wp_nonce_field( 'xg_int', 'xg_int_nonce' );

	echo '<tr><th colspan="2"><h2>Google Tag (GA4)</h2></th></tr>';
	echo '<tr><th>Meet-ID</th><td><input type="text" name="xg_gtag_id" value="' . esc_attr( get_option( 'xg_gtag_id', '' ) ) . '" class="regular-text" placeholder="G-XXXXXXXXXX"><p class="description">GA4 meet-ID. Leeg = geen tracking. DNT-headers worden gerespecteerd.</p></td></tr>';

	echo '<tr><th colspan="2"><h2>reCAPTCHA v3</h2></th></tr>';
	echo '<tr><th>Site-key</th><td><input type="text" name="xg_recaptcha_site" value="' . esc_attr( get_option( 'xg_recaptcha_site', '' ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Secret-key</th><td><input type="password" name="xg_recaptcha_secret" value="' . esc_attr( get_option( 'xg_recaptcha_secret', '' ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Drempelwaarde</th><td><input type="number" step="0.1" min="0" max="1" name="xg_recaptcha_threshold" value="' . esc_attr( get_option( 'xg_recaptcha_threshold', 0.5 ) ) . '"><p class="description">Minimale score (0–1). Inzendingen daaronder worden geweigerd.</p></td></tr>';

	submit_button();
	echo '</table></form></div>';
}
