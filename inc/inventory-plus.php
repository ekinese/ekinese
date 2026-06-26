<?php
/**
 * XGOUD voorraad-uitbreiding (bovenop inc/inventory.php).
 *
 *  - Extra velden: locatie, categorie, SKU, leverancier, notitie.
 *  - Actuele marktwaarde (live spot × fijn gewicht) + ongerealiseerde marge.
 *  - Aging/ladenhüter-detectie + voorraadoverzicht (inkoopwaarde, actuele waarde,
 *    marge, per categorie/locatie).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_inv_categories() {
	return array( 'baar' => 'Baar', 'munt' => 'Munt', 'sieraad' => 'Sieraad', 'horloge' => 'Horloge', 'diamant' => 'Diamant/edelsteen', 'sloop' => 'Sloop', 'overig' => 'Overig' );
}

/** Actuele marktwaarde van een voorraad-item (metaal: fijn gewicht × spot). */
function ekinese_inventory_value( $id ) {
	$override = (float) get_post_meta( $id, 'current_value', true );
	if ( $override > 0 ) {
		return $override;
	}
	$metal = sanitize_key( (string) get_post_meta( $id, 'metal', true ) );
	$fine  = (float) get_post_meta( $id, 'fine_weight', true );
	if ( $metal && $fine > 0 && function_exists( 'ekinese_metal_spot' ) ) {
		return round( $fine * (float) ekinese_metal_spot( $metal ), 2 );
	}
	return (float) get_post_meta( $id, 'purchase_price', true );
}

/** Dagen op voorraad. */
function ekinese_inventory_age_days( $id ) {
	$pd = get_post_meta( $id, 'purchase_date', true );
	if ( ! $pd ) {
		return 0;
	}
	return (int) max( 0, floor( ( current_time( 'timestamp' ) - strtotime( $pd ) ) / DAY_IN_SECONDS ) );
}

function ekinese_inventory_in_stock( $id ) {
	$status = (string) get_post_meta( $id, 'status', true );
	$sold   = get_post_meta( $id, 'sale_date', true );
	return ! $sold && ! in_array( $status, array( 'sold', 'verkocht', 'uit' ), true );
}

/* =====================================================================
   EXTRA METABOX
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_inv_extra', 'Voorraad — locatie & categorie', 'ekinese_inv_extra_metabox', 'xg_inventory', 'side', 'default' );
} );

function ekinese_inv_extra_metabox( $post ) {
	wp_nonce_field( 'xg_inv_extra_save', 'xg_inv_extra_nonce' );
	$cat = get_post_meta( $post->ID, 'category', true );
	echo '<p><label><strong>Categorie</strong><br><select name="xgi_category" style="width:100%">';
	foreach ( ekinese_inv_categories() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cat, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label></p>';
	$f = function ( $k, $lbl ) use ( $post ) {
		echo '<p><label><strong>' . esc_html( $lbl ) . '</strong><br><input type="text" name="xgi_' . esc_attr( $k ) . '" value="' . esc_attr( get_post_meta( $post->ID, $k, true ) ) . '" style="width:100%"></label></p>';
	};
	$f( 'location', 'Locatie (kluis/kantoor)' );
	$f( 'sku', 'SKU / lotnummer' );
	$f( 'supplier', 'Leverancier' );
	$f( 'current_value', 'Handmatige waarde € (leeg = live)' );
	$f( 'note', 'Notitie' );
	echo '<p class="description">Actuele waarde nu: <strong>€ ' . esc_html( number_format_i18n( ekinese_inventory_value( $post->ID ), 2 ) ) . '</strong> · ' . esc_html( ekinese_inventory_age_days( $post->ID ) ) . ' dagen op voorraad.</p>';
}

add_action( 'save_post_xg_inventory', function ( $post_id ) {
	if ( ! isset( $_POST['xg_inv_extra_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_inv_extra_nonce'] ), 'xg_inv_extra_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	update_post_meta( $post_id, 'category', sanitize_key( $_POST['xgi_category'] ?? 'overig' ) );
	foreach ( array( 'location', 'sku', 'supplier', 'note' ) as $f ) {
		update_post_meta( $post_id, $f, sanitize_text_field( wp_unslash( $_POST[ 'xgi_' . $f ] ?? '' ) ) );
	}
	update_post_meta( $post_id, 'current_value', round( (float) ( $_POST['xgi_current_value'] ?? 0 ), 2 ) );
}, 11 );

/* =====================================================================
   LIJSTKOLOMMEN
===================================================================== */
add_filter( 'manage_xg_inventory_posts_columns', function ( $c ) {
	$c['xg_cat']   = 'Categorie';
	$c['xg_loc']   = 'Locatie';
	$c['xg_val']   = 'Actuele waarde';
	$c['xg_age']   = 'Dagen';
	return $c;
} );
add_action( 'manage_xg_inventory_posts_custom_column', function ( $col, $id ) {
	if ( 'xg_cat' === $col ) { $cats = ekinese_inv_categories(); echo esc_html( $cats[ get_post_meta( $id, 'category', true ) ] ?? '—' ); }
	elseif ( 'xg_loc' === $col ) { echo esc_html( get_post_meta( $id, 'location', true ) ?: '—' ); }
	elseif ( 'xg_val' === $col ) { echo '€ ' . esc_html( number_format_i18n( ekinese_inventory_value( $id ), 2 ) ); }
	elseif ( 'xg_age' === $col ) {
		$d = ekinese_inventory_age_days( $id );
		echo ekinese_inventory_in_stock( $id ) ? '<strong' . ( $d > 120 ? ' style="color:#d63638"' : '' ) . '>' . esc_html( $d ) . '</strong>' : '—'; // phpcs:ignore
	}
}, 10, 2 );

/* =====================================================================
   VOORRAADOVERZICHT
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=xg_inventory', 'Voorraadoverzicht', 'Overzicht', 'manage_options', 'xg-inventory-overview', 'ekinese_inventory_overview_page' );
} );

function ekinese_inventory_overview_page() {
	$ids = get_posts( array( 'post_type' => 'xg_inventory', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	$eur = function ( $n ) { return '€ ' . number_format_i18n( (float) $n, 2 ); };
	$inkoop = 0; $waarde = 0; $count = 0; $aging = array(); $bycat = array(); $byloc = array();
	foreach ( $ids as $id ) {
		if ( ! ekinese_inventory_in_stock( $id ) ) {
			continue;
		}
		$count++;
		$p = (float) get_post_meta( $id, 'purchase_price', true );
		$v = ekinese_inventory_value( $id );
		$inkoop += $p; $waarde += $v;
		$cat = get_post_meta( $id, 'category', true ) ?: 'overig';
		$loc = get_post_meta( $id, 'location', true ) ?: '—';
		$bycat[ $cat ] = ( $bycat[ $cat ] ?? 0 ) + $v;
		$byloc[ $loc ] = ( $byloc[ $loc ] ?? 0 ) + $v;
		$age = ekinese_inventory_age_days( $id );
		if ( $age > 120 ) {
			$aging[] = array( 'id' => $id, 'title' => get_the_title( $id ), 'age' => $age, 'val' => $v );
		}
	}
	$marge = $waarde - $inkoop;
	$cats = ekinese_inv_categories();
	echo '<div class="wrap"><h1>Voorraadoverzicht</h1>';
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:14px 0">';
	foreach ( array( array( 'Items op voorraad', $count ), array( 'Inkoopwaarde', $eur( $inkoop ) ), array( 'Actuele marktwaarde', $eur( $waarde ) ), array( 'Ongerealiseerde marge', $eur( $marge ) ) ) as $k ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><div style="font-size:24px;font-weight:800;color:#AE1E1E">' . esc_html( $k[1] ) . '</div><div style="font-size:13px;color:#646970">' . esc_html( $k[0] ) . '</div></div>';
	}
	echo '</div>';
	echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">';
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0">Waarde per categorie</h2><table class="widefat striped"><tbody>';
	arsort( $bycat );
	foreach ( $bycat as $c => $v ) {
		echo '<tr><td>' . esc_html( $cats[ $c ] ?? $c ) . '</td><td>' . esc_html( $eur( $v ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
	echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0">Ladenhüter (&gt;120 dagen)</h2>';
	if ( $aging ) {
		usort( $aging, function ( $a, $b ) { return $b['age'] - $a['age']; } );
		echo '<table class="widefat striped"><tbody>';
		foreach ( array_slice( $aging, 0, 20 ) as $a ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $a['id'] ) ) . '">' . esc_html( wp_trim_words( $a['title'], 6 ) ) . '</a></td><td>' . esc_html( $a['age'] ) . ' d</td><td>' . esc_html( $eur( $a['val'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>Geen langzaam lopende voorraad.</p>';
	}
	echo '</div></div></div>';
}

/* Daily task: ladenhüter. */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$ids = get_posts( array( 'post_type' => 'xg_inventory', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	$n = 0;
	foreach ( $ids as $id ) {
		if ( ekinese_inventory_in_stock( $id ) && ekinese_inventory_age_days( $id ) > 120 ) {
			$n++;
		}
	}
	if ( $n > 0 ) {
		$tasks[] = array( 'key' => 'inv_aging', 'label' => 'Langzaam lopende voorraad doorlichten', 'count' => $n, 'link' => admin_url( 'edit.php?post_type=xg_inventory&page=xg-inventory-overview' ) );
	}
	return $tasks;
} );
