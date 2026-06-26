<?php
/**
 * XGOUD boekhouding — Nederlands belastingrecht (uitbreiding van inc/accounting.php).
 *
 *  - Btw-regeling per factuur: regulier (21/9%), margeregeling (gebruikte goederen),
 *    vrijgesteld beleggingsgoud, of btw verlegd.
 *  - Kwartaal-btw-overzicht (aangifte-hulp): verschuldigde btw, voorbelasting,
 *    te betalen — schema-bewust.
 *
 * LET OP: dit is een hulpmiddel/overzicht, geen vervanging van een boekhouder.
 * Beleggingsgoud is btw-vrijgesteld; bij de margeregeling is btw alleen over de
 * winstmarge (verkoop − inkoop) verschuldigd en is de inkoop niet aftrekbaar.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_btw_schemes() {
	return array(
		'regulier'      => 'Regulier (21%)',
		'regulier9'     => 'Regulier laag (9%)',
		'marge'         => 'Margeregeling (gebruikte goederen)',
		'beleggingsgoud'=> 'Beleggingsgoud (vrijgesteld)',
		'verlegd'       => 'Btw verlegd',
	);
}

/* Tweede metabox op de factuur: btw-regeling + inkoopwaarde (voor marge). */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_acc_btw', 'Btw-regeling (NL)', 'ekinese_acc_btw_metabox', 'xg_invoice', 'side', 'default' );
} );

function ekinese_acc_btw_metabox( $post ) {
	wp_nonce_field( 'xg_acc_btw_save', 'xg_acc_btw_nonce' );
	$scheme = get_post_meta( $post->ID, 'vat_scheme', true ) ?: 'regulier';
	$inkoop = get_post_meta( $post->ID, 'inkoopwaarde', true );
	echo '<p><label><strong>Regeling</strong><br><select name="xga_vat_scheme" style="width:100%">';
	foreach ( ekinese_btw_schemes() as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $scheme, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p><label><strong>Inkoopwaarde (€)</strong><br><input type="number" step="0.01" name="xga_inkoopwaarde" value="' . esc_attr( $inkoop ) . '" style="width:100%"></label>';
	echo '<span class="description">Alleen bij margeregeling: de inkoopprijs van dit goed. Btw = 21/121 × (verkoop − inkoop).</span></p>';
}

add_action( 'save_post_xg_invoice', function ( $post_id ) {
	if ( ! isset( $_POST['xg_acc_btw_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_acc_btw_nonce'] ), 'xg_acc_btw_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$scheme = sanitize_key( $_POST['xga_vat_scheme'] ?? 'regulier' );
	update_post_meta( $post_id, 'vat_scheme', array_key_exists( $scheme, ekinese_btw_schemes() ) ? $scheme : 'regulier' );
	update_post_meta( $post_id, 'inkoopwaarde', round( (float) ( $_POST['xga_inkoopwaarde'] ?? 0 ), 2 ) );
}, 11 );

/** Verschuldigde btw van één uitgaande factuur (schema-bewust). */
function ekinese_btw_output( $id ) {
	$scheme = get_post_meta( $id, 'vat_scheme', true ) ?: 'regulier';
	$amount = (float) get_post_meta( $id, 'amount', true );
	$vat    = (float) get_post_meta( $id, 'vat', true );
	switch ( $scheme ) {
		case 'beleggingsgoud':
		case 'verlegd':
			return 0.0;
		case 'marge':
			$marge = $amount - (float) get_post_meta( $id, 'inkoopwaarde', true );
			return $marge > 0 ? round( $marge * 21 / 121, 2 ) : 0.0;
		case 'regulier9':
			return $vat > 0 ? $vat : round( $amount * 0.09, 2 );
		default:
			return $vat > 0 ? $vat : round( $amount * 0.21, 2 );
	}
}

/** Btw-aangifte voor een kwartaal. */
function ekinese_btw_quarter( $year, $q ) {
	$start = sprintf( '%04d-%02d-01', $year, ( $q - 1 ) * 3 + 1 );
	$end   = gmdate( 'Y-m-d', strtotime( $start . ' +3 months' ) );
	$ids = get_posts( array(
		'post_type'   => 'xg_invoice',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			array( 'key' => 'invoice_date', 'value' => array( $start, $end ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
		),
	) );
	$out = 0; $in = 0; $omzet = 0; $marge_omzet = 0; $vrij = 0;
	foreach ( $ids as $id ) {
		$dir    = get_post_meta( $id, 'direction', true );
		$amount = (float) get_post_meta( $id, 'amount', true );
		$scheme = get_post_meta( $id, 'vat_scheme', true ) ?: 'regulier';
		if ( 'outgoing' === $dir ) {
			$omzet += $amount;
			$out   += ekinese_btw_output( $id );
			if ( 'marge' === $scheme ) {
				$marge_omzet += $amount;
			}
			if ( 'beleggingsgoud' === $scheme ) {
				$vrij += $amount;
			}
		} else {
			// Voorbelasting: niet aftrekbaar bij marge-inkoop.
			if ( 'marge' !== $scheme ) {
				$in += (float) get_post_meta( $id, 'vat', true );
			}
		}
	}
	return array(
		'periode'     => sprintf( 'Q%d %d', $q, $year ),
		'omzet'       => $omzet,
		'marge_omzet' => $marge_omzet,
		'vrij'        => $vrij,
		'verschuldigd'=> round( $out, 2 ),
		'voorbelasting'=> round( $in, 2 ),
		'te_betalen'  => round( $out - $in, 2 ),
	);
}

/* Aangifte-pagina onder Boekhouding. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=xg_invoice', 'Btw-aangifte', 'Btw-aangifte', 'manage_options', 'xg-btw', 'ekinese_btw_page' );
} );

function ekinese_btw_page() {
	$year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : (int) current_time( 'Y' );
	$eur  = function ( $n ) { return '€ ' . number_format_i18n( (float) $n, 2 ); };
	echo '<div class="wrap"><h1>Btw-aangifte ' . esc_html( $year ) . '</h1>';
	echo '<p>Kwartaaloverzicht als hulpmiddel voor de btw-aangifte. <strong>Beleggingsgoud</strong> is vrijgesteld; bij de <strong>margeregeling</strong> is btw alleen over de winstmarge verschuldigd. Geen vervanging van je boekhouder.</p>';
	echo '<form method="get"><input type="hidden" name="post_type" value="xg_invoice"><input type="hidden" name="page" value="xg-btw">Jaar: <input type="number" name="jaar" value="' . esc_attr( $year ) . '" style="width:90px"> <button class="button">Toon</button></form>';
	echo '<table class="widefat striped" style="margin-top:14px"><thead><tr><th>Periode</th><th>Omzet</th><th>Waarvan marge</th><th>Vrijgesteld</th><th>Verschuldigde btw</th><th>Voorbelasting</th><th>Te betalen</th></tr></thead><tbody>';
	$tot = array( 'verschuldigd' => 0, 'voorbelasting' => 0, 'te_betalen' => 0 );
	for ( $q = 1; $q <= 4; $q++ ) {
		$r = ekinese_btw_quarter( $year, $q );
		$tot['verschuldigd'] += $r['verschuldigd'];
		$tot['voorbelasting'] += $r['voorbelasting'];
		$tot['te_betalen'] += $r['te_betalen'];
		echo '<tr><td><strong>' . esc_html( $r['periode'] ) . '</strong></td><td>' . esc_html( $eur( $r['omzet'] ) ) . '</td><td>' . esc_html( $eur( $r['marge_omzet'] ) ) . '</td><td>' . esc_html( $eur( $r['vrij'] ) ) . '</td><td>' . esc_html( $eur( $r['verschuldigd'] ) ) . '</td><td>' . esc_html( $eur( $r['voorbelasting'] ) ) . '</td><td><strong>' . esc_html( $eur( $r['te_betalen'] ) ) . '</strong></td></tr>';
	}
	echo '<tr style="background:#faf6ec"><td><strong>Jaar</strong></td><td colspan="3"></td><td><strong>' . esc_html( $eur( $tot['verschuldigd'] ) ) . '</strong></td><td><strong>' . esc_html( $eur( $tot['voorbelasting'] ) ) . '</strong></td><td><strong>' . esc_html( $eur( $tot['te_betalen'] ) ) . '</strong></td></tr>';
	echo '</tbody></table></div>';
}

/* Daily task: btw-aangifte aan het begin van een nieuw kwartaal. */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$day  = (int) current_time( 'j' );
	$mon  = (int) current_time( 'n' );
	if ( $day <= 20 && in_array( $mon, array( 1, 4, 7, 10 ), true ) ) {
		$tasks[] = array( 'key' => 'btw_aangifte', 'label' => 'Btw-aangifte vorig kwartaal indienen', 'count' => 1, 'link' => admin_url( 'edit.php?post_type=xg_invoice&page=xg-btw' ) );
	}
	return $tasks;
} );
