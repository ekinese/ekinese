<?php
/**
 * XGOUD cookie-consent (AVG/ePrivacy). GA4 en de Facebook-pixel worden PAS
 * geladen nadat de bezoeker toestemming geeft. Zonder toestemming worden er
 * geen tracking-cookies/scripts geplaatst. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Banner + assets alleen op de frontend, en alleen als er iets te tracken valt. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	$ga4 = trim( (string) get_option( 'xg_ga4_id', '' ) );
	$px  = trim( (string) get_option( 'xg_fb_pixel', '' ) );
	if ( '' === $ga4 && '' === $px ) {
		return; // niets te laden → geen banner nodig.
	}
	$css = get_theme_file_path( 'assets/css/consent.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-consent', get_theme_file_uri( 'assets/css/consent.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/consent.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-consent', get_theme_file_uri( 'assets/js/consent.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-consent', 'XGConsent', array(
			'ga4'     => $ga4,
			'pixel'   => $px,
			'privacy' => esc_url( home_url( '/privacy/' ) ),
		) );
	}
} );

/** De banner-markup in de footer. */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$ga4 = trim( (string) get_option( 'xg_ga4_id', '' ) );
	$px  = trim( (string) get_option( 'xg_fb_pixel', '' ) );
	if ( '' === $ga4 && '' === $px ) {
		return;
	}
	?>
	<div class="xg-consent" id="xgConsent" hidden role="dialog" aria-label="Cookie-toestemming">
		<div class="xg-consent-inner">
			<p class="xg-consent-text">Wij gebruiken cookies voor analyse en marketing om de site te verbeteren. U bepaalt zelf wat u toestaat. Lees meer in onze <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">privacyverklaring</a>.</p>
			<div class="xg-consent-btns">
				<button type="button" class="xg-consent-reject" id="xgConsentReject">Alleen noodzakelijk</button>
				<button type="button" class="xg-consent-accept" id="xgConsentAccept">Accepteren</button>
			</div>
		</div>
	</div>
	<?php
} );
