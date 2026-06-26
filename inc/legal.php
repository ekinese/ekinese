<?php
/**
 * XGOUD juridische bedrijfsgegevens (NL-verplicht): KvK, btw, erkend-opkoper-
 * registratie. Beheerbaar in het admin, getoond in de footer/legal-pagina's en
 * toegevoegd aan de Organization-structured data. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Juridische gegevens (opties met nette defaults). */
function ekinese_legal() {
	return array(
		'legal_name' => get_option( 'xg_legal_name', 'XGOUD B.V.' ),
		'kvk'        => get_option( 'xg_legal_kvk', '' ),
		'btw'        => get_option( 'xg_legal_btw', '' ),
		'opkoper'    => get_option( 'xg_legal_opkoper', '' ),
		'iban'       => get_option( 'xg_legal_iban', '' ),
	);
}

/** Compacte juridische voettekst (KvK/btw/opkoper/18+). */
function ekinese_legal_line() {
	$l     = ekinese_legal();
	$parts = array( esc_html( $l['legal_name'] ) );
	if ( $l['kvk'] ) {
		$parts[] = 'KvK ' . esc_html( $l['kvk'] );
	}
	if ( $l['btw'] ) {
		$parts[] = 'btw ' . esc_html( $l['btw'] );
	}
	if ( $l['opkoper'] ) {
		$parts[] = 'Erkend opkoper ' . esc_html( $l['opkoper'] );
	} else {
		$parts[] = 'Erkend opkoper (Digitaal Opkopersregister)';
	}
	$parts[] = 'Inkoop vanaf 18 jaar, legitimatie verplicht (Wwft)';
	return implode( ' · ', $parts );
}

/* Block ekinese/legal-info — bruikbaar op legal-pagina's. */
add_action( 'init', function () {
	register_block_type( 'ekinese/legal-info', array( 'render_callback' => function () {
		return '<p class="xg-legal-info">' . ekinese_legal_line() . '</p>';
	} ) );
} );

/* Organization structured data met KvK (taxID) + btw (vatID). */
add_action( 'wp_head', function () {
	$l = ekinese_legal();
	if ( ! $l['kvk'] && ! $l['btw'] ) {
		return;
	}
	$data = array_filter( array(
		'@context'  => 'https://schema.org',
		'@type'     => 'Organization',
		'name'      => function_exists( 'ekinese_business' ) ? ekinese_business()['name'] : get_bloginfo( 'name' ),
		'legalName' => $l['legal_name'],
		'url'       => home_url( '/' ),
		'taxID'     => $l['kvk'] ? ( 'KvK ' . $l['kvk'] ) : '',
		'vatID'     => $l['btw'],
	) );
	echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
}, 6 );

/* =====================================================================
   ADMIN — Bedrijfsgegevens
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Bedrijfsgegevens', 'ekinese' ), __( 'Bedrijfsgegevens', 'ekinese' ), 'manage_options', 'xg-legal', function () {
		$fields = array(
			'xg_legal_name'    => 'Statutaire naam (bv. XGOUD B.V.)',
			'xg_legal_kvk'     => 'KvK-nummer',
			'xg_legal_btw'     => 'Btw-nummer (NL…B01)',
			'xg_legal_opkoper' => 'Registratienummer Digitaal Opkopersregister',
			'xg_legal_iban'    => 'IBAN (uitbetaling)',
		);
		if ( isset( $_POST['xg_legal_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_legal_nonce'] ), 'xg_legal' ) ) {
			foreach ( array_keys( $fields ) as $f ) {
				if ( isset( $_POST[ $f ] ) ) {
					update_option( $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
				}
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="wrap"><h1>Bedrijfsgegevens</h1>';
		echo '<p>Wettelijk verplicht voor een Nederlandse webshop: vermeld KvK- en btw-nummer. Als opkoper van edelmetaal is registratie in het Digitaal Opkopersregister verplicht. Deze gegevens verschijnen in de footer en in de structured data.</p>';
		echo '<form method="post"><table class="form-table">';
		wp_nonce_field( 'xg_legal', 'xg_legal_nonce' );
		foreach ( $fields as $f => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><input type="text" name="' . esc_attr( $f ) . '" value="' . esc_attr( get_option( $f, '' ) ) . '" class="regular-text"></td></tr>';
		}
		echo '</table>';
		submit_button();
		echo '<p style="color:#646970">Voorbeeld voettekst: <em>' . ekinese_legal_line() . '</em></p>';
		echo '</form></div>';
	} );
} );
