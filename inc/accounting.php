<?php
/**
 * XGOUD Boekhouding – facturen in/uit, scan & automatische categorisatie.
 *
 * Alle facturen (inkomend = kosten, uitgaand = omzet) komen in de boekhouding.
 * Bij upload wordt op basis van trefwoorden automatisch een categorie voorgesteld.
 * Statistieken tonen kosten per categorie en waar optimalisatie mogelijk is.
 * Self-built, geen plugin. (OCR/echte scanherkenning kan later via een dienst.)
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_ACC_FIELDS = array( 'direction', 'party', 'amount', 'vat', 'category', 'invoice_date', 'due_date', 'status', 'invoice_no', 'attachment' );

/** Categorieën + trefwoorden voor automatische herkenning. */
function ekinese_acc_categories() {
	return apply_filters( 'ekinese_acc_categories', array(
		'inkoop'       => array( 'label' => 'Inkoop edelmetaal', 'kw' => array( 'goud', 'zilver', 'platina', 'inkoop', 'metaal' ) ),
		'huur'         => array( 'label' => 'Huur & pand', 'kw' => array( 'huur', 'pand', 'kantoor', 'vastgoed' ) ),
		'marketing'    => array( 'label' => 'Marketing & ads', 'kw' => array( 'google', 'facebook', 'ads', 'advertentie', 'marketing', 'seo' ) ),
		'salaris'      => array( 'label' => 'Salaris & personeel', 'kw' => array( 'salaris', 'loon', 'personeel', 'hr' ) ),
		'transport'    => array( 'label' => 'Transport & verzending', 'kw' => array( 'transport', 'koerier', 'verzend', 'post', 'dhl', 'fedex' ) ),
		'verzekering'  => array( 'label' => 'Verzekering', 'kw' => array( 'verzeker', 'polis', 'assurantie' ) ),
		'software'     => array( 'label' => 'Software & IT', 'kw' => array( 'software', 'hosting', 'cloudflare', 'redis', 'licentie', 'saas' ) ),
		'overig'       => array( 'label' => 'Overig', 'kw' => array() ),
	) );
}

/** Categorie raden uit vrije tekst. */
function ekinese_acc_guess_category( $text ) {
	$text = mb_strtolower( $text );
	foreach ( ekinese_acc_categories() as $key => $cat ) {
		foreach ( $cat['kw'] as $kw ) {
			if ( false !== mb_strpos( $text, $kw ) ) {
				return $key;
			}
		}
	}
	return 'overig';
}

function ekinese_register_accounting() {
	register_post_type( 'xg_invoice', array(
		'labels'    => array( 'name' => __( 'Boekhouding', 'ekinese' ), 'singular_name' => __( 'Factuur', 'ekinese' ), 'menu_name' => __( 'Boekhouding', 'ekinese' ) ),
		'public'    => false,
		'show_ui'   => true,
		'menu_icon' => 'dashicons-media-spreadsheet',
		'supports'  => array( 'title', 'editor' ),
	) );
}
add_action( 'init', 'ekinese_register_accounting' );

/* ---- Admin metabox ---- */
function ekinese_accounting_metabox() {
	add_meta_box( 'xg_acc_meta', __( 'Factuurgegevens', 'ekinese' ), 'ekinese_accounting_metabox_html', 'xg_invoice', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ekinese_accounting_metabox' );

function ekinese_accounting_metabox_html( $post ) {
	wp_nonce_field( 'xg_acc_save', 'xg_acc_nonce' );
	echo '<table class="form-table">';
	// Richting
	$dir = get_post_meta( $post->ID, 'direction', true ) ?: 'incoming';
	echo '<tr><th>Type</th><td><select name="xga_direction">';
	foreach ( array( 'incoming' => 'Inkomend (kosten)', 'outgoing' => 'Uitgaand (omzet)' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $dir, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></td></tr>';
	$txt = function ( $k, $lbl, $type = 'text' ) use ( $post ) {
		echo '<tr><th>' . esc_html( $lbl ) . '</th><td><input type="' . esc_attr( $type ) . '" name="xga_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" class="regular-text"></td></tr>';
	};
	$txt( 'party', 'Tegenpartij' );
	$txt( 'invoice_no', 'Factuurnummer' );
	$txt( 'amount', 'Bedrag excl. btw (€)', 'number' );
	$txt( 'vat', 'Btw (€)', 'number' );
	$txt( 'invoice_date', 'Factuurdatum', 'date' );
	$txt( 'due_date', 'Vervaldatum', 'date' );
	// Categorie
	$cat = get_post_meta( $post->ID, 'category', true );
	echo '<tr><th>Categorie</th><td><select name="xga_category">';
	foreach ( ekinese_acc_categories() as $k => $c ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $cat, $k, false ) . '>' . esc_html( $c['label'] ) . '</option>';
	}
	echo '</select> <small>(automatisch voorgesteld op basis van de tekst)</small></td></tr>';
	// Status
	$st = get_post_meta( $post->ID, 'status', true ) ?: 'unpaid';
	echo '<tr><th>Status</th><td><select name="xga_status">';
	foreach ( array( 'unpaid' => 'Open', 'paid' => 'Betaald' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></td></tr>';
	// Scan/bijlage
	$att = get_post_meta( $post->ID, 'attachment', true );
	echo '<tr><th>Scan/bijlage</th><td>';
	if ( $att ) {
		echo '<a href="' . esc_url( wp_get_attachment_url( $att ) ) . '" target="_blank">' . esc_html__( 'Bekijk bijlage', 'ekinese' ) . '</a><br>';
	}
	echo '<input type="file" name="xga_scan" accept="image/*,application/pdf"></td></tr>';
	echo '</table>';
}

function ekinese_accounting_save( $post_id ) {
	if ( ! isset( $_POST['xg_acc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_acc_nonce'] ), 'xg_acc_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	foreach ( XG_ACC_FIELDS as $f ) {
		if ( 'attachment' === $f ) {
			continue;
		}
		if ( isset( $_POST[ 'xga_' . $f ] ) ) {
			update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xga_' . $f ] ) ) );
		}
	}
	// Upload scan.
	if ( ! empty( $_FILES['xga_scan']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload( 'xga_scan', $post_id );
		if ( ! is_wp_error( $att ) ) {
			update_post_meta( $post_id, 'attachment', $att );
		}
	}
	// Automatische categorie wanneer nog leeg.
	if ( ! get_post_meta( $post_id, 'category', true ) || empty( $_POST['xga_category'] ) ) {
		$text = get_the_title( $post_id ) . ' ' . get_post_meta( $post_id, 'party', true ) . ' ' . get_post_field( 'post_content', $post_id );
		update_post_meta( $post_id, 'category', ekinese_acc_guess_category( $text ) );
	}
}
add_action( 'save_post_xg_invoice', 'ekinese_accounting_save' );

/**
 * Boekhoud-overzicht: omzet, kosten, resultaat + kosten per categorie.
 *
 * @return array
 */
function ekinese_accounting_summary() {
	$items  = get_posts( array( 'post_type' => 'xg_invoice', 'numberposts' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) );
	$income = 0.0;
	$cost   = 0.0;
	$bycat  = array();
	foreach ( $items as $id ) {
		$amount = (float) get_post_meta( $id, 'amount', true ) + (float) get_post_meta( $id, 'vat', true );
		if ( get_post_meta( $id, 'direction', true ) === 'outgoing' ) {
			$income += $amount;
		} else {
			$cost += $amount;
			$cat            = get_post_meta( $id, 'category', true ) ?: 'overig';
			$bycat[ $cat ]  = ( $bycat[ $cat ] ?? 0 ) + $amount;
		}
	}
	arsort( $bycat );
	return array( 'income' => $income, 'cost' => $cost, 'result' => $income - $cost, 'by_category' => $bycat );
}

/* ---- Overzichtspagina ---- */
function ekinese_accounting_menu() {
	add_submenu_page( 'edit.php?post_type=xg_invoice', __( 'Overzicht', 'ekinese' ), __( 'Overzicht', 'ekinese' ), 'manage_options', 'xg-accounting', function () {
		$s = ekinese_accounting_summary();
		$eur = function ( $n ) { return '€ ' . number_format_i18n( (float) $n, 2 ); };
		echo '<div class="wrap"><h1>' . esc_html__( 'Boekhouding – overzicht', 'ekinese' ) . '</h1>';
		echo '<p><strong>Omzet:</strong> ' . esc_html( $eur( $s['income'] ) ) . ' &nbsp; <strong>Kosten:</strong> ' . esc_html( $eur( $s['cost'] ) ) . ' &nbsp; <strong>Resultaat:</strong> ' . esc_html( $eur( $s['result'] ) ) . '</p>';
		echo '<h2>' . esc_html__( 'Kosten per categorie', 'ekinese' ) . '</h2><table class="widefat striped"><tbody>';
		$cats = ekinese_acc_categories();
		foreach ( $s['by_category'] as $cat => $amount ) {
			echo '<tr><td>' . esc_html( $cats[ $cat ]['label'] ?? $cat ) . '</td><td>' . esc_html( $eur( $amount ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	} );
}
add_action( 'admin_menu', 'ekinese_accounting_menu' );
