<?php
/**
 * XGOUD taxatie-certificaat (#25).
 *
 * Na een afgeronde afspraak kan de klant een deelbaar, printbaar certificaat
 * (HTML → print/PDF) openen met de taxatiedetails. Beveiligd met een
 * HMAC-token (zoals de account-links). Link komt in de bevestigingsmail en in
 * het account. Model gebaseerd op ekinese_render_certificate (inc/charity.php).
 * Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** HMAC-token voor een afspraak-certificaat. */
function ekinese_cert_token( $appointment_id ) {
	return substr( hash_hmac( 'sha256', 'cert|' . (int) $appointment_id, wp_salt( 'auth' ) ), 0, 32 );
}

/** Deelbare certificaat-URL. */
function ekinese_cert_url( $appointment_id ) {
	return add_query_arg(
		array( 'id' => (int) $appointment_id, 'token' => ekinese_cert_token( $appointment_id ) ),
		home_url( '/taxatie-certificaat/' )
	);
}

/* Rewrite /taxatie-certificaat/ → virtuele render. */
add_action( 'init', function () {
	add_rewrite_rule( '^taxatie-certificaat/?$', 'index.php?xg_cert=1', 'top' );
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'xg_cert';
	return $v;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'xg_cert' ) ) {
		return;
	}
	$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $id || get_post_type( $id ) !== 'xg_appointment' || ! hash_equals( ekinese_cert_token( $id ), $token ) ) {
		status_header( 403 );
		echo 'Ongeldige of verlopen link.';
		exit;
	}
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo ekinese_appraisal_cert_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

/** Volledige, printbare HTML-pagina van het taxatie-certificaat. */
function ekinese_appraisal_cert_html( $id ) {
	$first  = get_post_meta( $id, 'first', true );
	$last   = get_post_meta( $id, 'last', true );
	$payout = (float) get_post_meta( $id, 'payout_total', true );
	$charity = (float) get_post_meta( $id, 'charity_total', true );
	$date   = get_post_meta( $id, 'date', true ) ?: get_the_date( 'Y-m-d', $id );
	$service = get_post_meta( $id, 'service', true );
	$ref     = 'XG-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );
	$products_raw = get_post_meta( $id, 'products', true );
	$products = $products_raw ? json_decode( $products_raw, true ) : array();

	$rows = '';
	if ( is_array( $products ) ) {
		foreach ( $products as $p ) {
			$label = is_array( $p ) ? ( $p['label'] ?? ( $p['typeLabel'] ?? 'Object' ) ) : (string) $p;
			$spec  = is_array( $p ) ? ( $p['spec'] ?? '' ) : '';
			$rows .= '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( $spec ) . '</td></tr>';
		}
	}
	if ( '' === $rows ) {
		$rows = '<tr><td colspan="2">Getaxeerde objecten</td></tr>';
	}

	$eur = function ( $n ) {
		return '€ ' . number_format( (float) $n, 2, ',', '.' );
	};

	ob_start();
	?><!DOCTYPE html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Taxatie-certificaat <?php echo esc_html( $ref ); ?> – XGOUD</title>
<style>
:root{--red:#AE1E1E;--gold:#D0AC4B;--ink:#161412}
*{box-sizing:border-box}body{font-family:Georgia,'Times New Roman',serif;margin:0;background:#f3f1ec;color:var(--ink);padding:30px}
.cert{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e3ddd0;padding:48px 56px;box-shadow:0 12px 40px rgba(0,0,0,.08)}
.brand{font-family:Arial,sans-serif;font-weight:800;letter-spacing:1px;font-size:24px;color:var(--red)}
.brand span{color:var(--gold)}
.kicker{margin-top:24px;text-transform:uppercase;letter-spacing:3px;font-size:12px;color:#9a7d2e;font-family:Arial,sans-serif}
h1{font-size:30px;margin:6px 0 18px}
.meta{display:flex;justify-content:space-between;font-family:Arial,sans-serif;font-size:13px;color:#57534b;border-top:1px solid #e3ddd0;border-bottom:1px solid #e3ddd0;padding:12px 0;margin:18px 0}
table{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;margin:14px 0}
th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #ececec}
th{color:#9a948a;text-transform:uppercase;font-size:11px;letter-spacing:.5px}
.totals{font-family:Arial,sans-serif;margin-top:14px}
.totals div{display:flex;justify-content:space-between;padding:6px 0}
.totals .big{font-size:20px;font-weight:800;border-top:2px solid var(--ink);margin-top:6px;padding-top:10px}
.charity{margin-top:18px;background:#fbf7ea;border-left:4px solid var(--gold);padding:14px 16px;font-family:Arial,sans-serif;font-size:14px}
.foot{display:flex;justify-content:space-between;margin-top:28px;font-family:Arial,sans-serif;font-size:12px;color:#8a847a}
.print{display:inline-block;margin:0 auto 24px;font-family:Arial,sans-serif}
@media print{body{background:#fff;padding:0}.cert{box-shadow:none;border:none}.noprint{display:none}}
button{font-family:Arial,sans-serif;background:var(--red);color:#fff;border:none;padding:10px 20px;font-weight:700;cursor:pointer}
</style></head>
<body>
<div class="noprint" style="max-width:760px;margin:0 auto 16px;text-align:right"><button onclick="window.print()">Afdrukken / opslaan als PDF</button></div>
<div class="cert">
	<div class="brand">X<span>GOUD</span></div>
	<div class="kicker">Taxatie-certificaat</div>
	<h1><?php echo esc_html( trim( $first . ' ' . $last ) ?: 'Klant' ); ?></h1>
	<div class="meta"><span>Referentie: <strong><?php echo esc_html( $ref ); ?></strong></span><span>Datum: <?php echo esc_html( $date ); ?></span><span><?php echo esc_html( $service ?: 'Taxatie' ); ?></span></div>
	<table><thead><tr><th>Object</th><th>Specificatie</th></tr></thead><tbody><?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
	<div class="totals">
		<div class="big"><span>Uitbetaald bedrag</span><span><?php echo esc_html( $eur( $payout ) ); ?></span></div>
	</div>
	<?php if ( $charity > 0 ) : ?>
	<div class="charity">Met deze verkoop heeft u <strong><?php echo esc_html( $eur( $charity ) ); ?></strong> bijgedragen aan een goed doel. Hartelijk dank.</div>
	<?php endif; ?>
	<div class="foot"><span>XGOUD &middot; xgoud.nl</span><span>Dit certificaat bevestigt een uitgevoerde taxatie/transactie.</span></div>
</div>
</body></html>
	<?php
	return ob_get_clean();
}

/** Certificaat-link toevoegen aan het account-overzicht (afspraken). */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	if ( empty( $data['appointments'] ) || ! is_array( $data['appointments'] ) ) {
		return $data;
	}
	// Voeg een certificaat-URL toe aan afgeronde afspraken.
	$done = array( 'completed', 'paid', 'afgerond', 'uitbetaald' );
	foreach ( $data['appointments'] as &$a ) {
		if ( isset( $a['_id'] ) && in_array( strtolower( (string) ( $a['status'] ?? '' ) ), $done, true ) ) {
			$a['certificate'] = ekinese_cert_url( (int) $a['_id'] );
		}
	}
	unset( $a );
	return $data;
}, 20, 2 );

/* =====================================================================
   E-MAIL het certificaat naar de klant zodra de afspraak is afgerond (#5)
===================================================================== */
function ekinese_cert_email_on_complete( $post_id ) {
	if ( get_post_type( $post_id ) !== 'xg_appointment' ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	if ( get_post_meta( $post_id, 'status', true ) !== 'completed' ) {
		return;
	}
	if ( get_post_meta( $post_id, '_cert_emailed', true ) ) {
		return; // al verstuurd — niet opnieuw.
	}
	$email = get_post_meta( $post_id, 'email', true );
	if ( ! is_email( $email ) ) {
		return;
	}
	$first   = get_post_meta( $post_id, 'first', true );
	$charity = (float) get_post_meta( $post_id, 'charity_total', true );
	$url     = ekinese_cert_url( $post_id );

	$body  = 'Beste ' . trim( $first ) . ",\n\n";
	$body .= "Bedankt voor uw verkoop bij XGOUD.\n";
	if ( $charity > 0 ) {
		$body .= 'Met deze verkoop heeft u € ' . number_format( $charity, 2, ',', '.' ) . " bijgedragen aan een goed doel — hartelijk dank voor uw steun!\n";
	}
	$body .= "\nUw persoonlijke certificaat kunt u hier bekijken, opslaan en delen:\n" . $url . "\n\n";
	$body .= "Met vriendelijke groet,\nXGOUD";

	wp_mail( $email, 'Bedankt voor uw steun — uw XGOUD-certificaat', $body );
	update_post_meta( $post_id, '_cert_emailed', '1' );
}
add_action( 'save_post_xg_appointment', 'ekinese_cert_email_on_complete', 30 );
