<?php
/**
 * XGOUD vergelijker – transparante tabel XGOUD vs. doorsnee inkoper.
 *
 * Toont op een eerlijke, verifieerbare manier waarom XGOUD beter uitpakt
 * (uitbetaling, marge, verzekering, goed doel, transparantie). Beheerbaar in
 * admin (option xg_compare_rows). Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Standaard-vergelijkingsregels (admin kan overschrijven via xg_compare_rows). */
function ekinese_compare_rows() {
	$saved = get_option( 'xg_compare_rows' );
	if ( is_array( $saved ) && $saved ) {
		return $saved;
	}
	// [ label, xgoud, anderen ] – xgoud=true => groen vinkje, string => tekst.
	return array(
		array( 'label' => 'Uitbetaling op basis van actuele dagprijs', 'xgoud' => true, 'other' => 'Vaak vaste, lagere inkoopprijs' ),
		array( 'label' => 'Transparante marge vooraf zichtbaar', 'xgoud' => true, 'other' => false ),
		array( 'label' => 'Direct uitbetaald (contant of overboeking)', 'xgoud' => true, 'other' => 'Soms pas na enkele dagen' ),
		array( 'label' => 'Gratis & verzekerde taxatie', 'xgoud' => true, 'other' => 'Niet altijd verzekerd' ),
		array( 'label' => 'Thuisbezoek & ophaalservice', 'xgoud' => true, 'other' => false ),
		array( 'label' => 'Vast deel van elke marge naar een goed doel', 'xgoud' => true, 'other' => false ),
		array( 'label' => 'Beste-prijsgarantie', 'xgoud' => true, 'other' => false ),
		array( 'label' => 'Erkend & geregistreerd (Wwft/KYC)', 'xgoud' => true, 'other' => 'Wisselend' ),
	);
}

add_action( 'init', function () {
	register_block_type( 'ekinese/compare-table', array( 'render_callback' => 'ekinese_render_compare' ) );
} );

/** Render de vergelijkingstabel + Product-achtige schema-context. */
function ekinese_render_compare() {
	$rows = ekinese_compare_rows();
	$yes  = '<span class="xg-cmp-yes" aria-label="Ja">✓</span>';
	$no   = '<span class="xg-cmp-no" aria-label="Nee">—</span>';
	ob_start();
	echo '<div class="xg-compare"><div class="xg-table-scroll"><table class="xg-cmp-table">';
	echo '<thead><tr><th class="xg-cmp-feat">Wat u mag verwachten</th><th class="xg-cmp-us">XGOUD</th><th class="xg-cmp-them">Doorsnee inkoper</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$us   = ( true === $r['xgoud'] ) ? $yes : ( $r['xgoud'] ? $no : $yes . ' ' . esc_html( $r['xgoud'] ) );
		$them = ( true === $r['other'] ) ? $yes : ( false === $r['other'] || '' === $r['other'] ? $no : esc_html( $r['other'] ) );
		echo '<tr>';
		echo '<td class="xg-cmp-feat">' . esc_html( $r['label'] ) . '</td>';
		echo '<td class="xg-cmp-us">' . wp_kses_post( $us ) . '</td>';
		echo '<td class="xg-cmp-them">' . wp_kses_post( $them ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
	echo '<p class="xg-cmp-note">Vergelijking ter illustratie op basis van gangbare marktpraktijk. Bij XGOUD ziet u de marge altijd vooraf en rekenen we met de actuele spotprijs.</p>';
	echo '<div class="xg-cmp-cta"><a class="xg-btn-gold" href="/afspraak/">Bereken uw waarde bij XGOUD</a></div>';
	echo '</div>';
	return ob_get_clean();
}

/** Assets laden waar het blok staat. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/compare-table' ) ) {
		return;
	}
	// Geen eigen JS nodig; styling zit in blueprint.css.
} );

/* ---- Admin: vergelijkingsregels beheren ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', __( 'Vergelijker', 'ekinese' ), __( 'Vergelijker', 'ekinese' ), 'manage_options', 'xg-compare', function () {
		if ( isset( $_POST['xg_cmp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['xg_cmp_nonce'] ), 'xg_cmp' ) ) {
			$rows = array();
			foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['xg_compare'] ?? '' ) ) as $line ) {
				$p = array_map( 'trim', explode( '|', $line ) );
				if ( '' === $p[0] ) {
					continue;
				}
				$rows[] = array(
					'label' => sanitize_text_field( $p[0] ),
					'xgoud' => ekinese_compare_cell( $p[1] ?? 'ja' ),
					'other' => ekinese_compare_cell( $p[2] ?? 'nee' ),
				);
			}
			if ( $rows ) {
				update_option( 'xg_compare_rows', $rows );
			}
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		$lines = array_map( function ( $r ) {
			$us   = true === $r['xgoud'] ? 'ja' : ( $r['xgoud'] ? $r['xgoud'] : 'nee' );
			$them = true === $r['other'] ? 'ja' : ( $r['other'] ? $r['other'] : 'nee' );
			return $r['label'] . ' | ' . $us . ' | ' . $them;
		}, ekinese_compare_rows() );
		echo '<div class="wrap"><h1>Vergelijker</h1><p>Eén regel per kenmerk: <code>kenmerk | XGOUD | anderen</code>. Gebruik <code>ja</code>/<code>nee</code> of vrije tekst.</p><form method="post">';
		wp_nonce_field( 'xg_cmp', 'xg_cmp_nonce' );
		echo '<textarea name="xg_compare" rows="12" class="large-text code">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		submit_button();
		echo '</form></div>';
	} );
} );

/** Cel-waarde normaliseren: ja=true, nee=false, anders tekst. */
function ekinese_compare_cell( $v ) {
	$v = trim( (string) $v );
	$low = strtolower( $v );
	if ( in_array( $low, array( 'ja', 'yes', 'true', '1', '✓' ), true ) ) {
		return true;
	}
	if ( in_array( $low, array( 'nee', 'no', 'false', '0', '-', '—', '' ), true ) ) {
		return false;
	}
	return sanitize_text_field( $v );
}
