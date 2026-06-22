<?php
/**
 * XGOUD jaaroverzicht – deelbare "year in review" voor klanten.
 *
 * Telt per kalenderjaar de afgeronde verkopen, totale uitbetaling en de
 * bijdrage aan goede doelen van een klant. Toegevoegd aan /account/data via
 * de ekinese_account_data-filter; gerenderd in assets/js/account.js als kaart.
 * Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jaaroverzicht per kalenderjaar voor een e-mailadres.
 *
 * @param string $email Klant-e-mail.
 * @return array year => { count, payout, charity }
 */
function ekinese_year_review_for( $email ) {
	$email = sanitize_email( $email );
	if ( ! $email ) {
		return array();
	}
	$appts = get_posts( array(
		'post_type'   => 'xg_appointment',
		'numberposts' => -1,
		'post_status' => 'publish',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'email', 'value' => $email ),
			array( 'key' => 'status', 'value' => array( 'completed', 'paid', 'afgerond', 'uitbetaald' ), 'compare' => 'IN' ),
		),
	) );
	$years = array();
	foreach ( $appts as $a ) {
		$year = (int) get_post_time( 'Y', true, $a->ID );
		if ( ! isset( $years[ $year ] ) ) {
			$years[ $year ] = array( 'count' => 0, 'payout' => 0.0, 'charity' => 0.0 );
		}
		$years[ $year ]['count']++;
		$years[ $year ]['payout']  += (float) get_post_meta( $a->ID, 'payout_total', true );
		$years[ $year ]['charity'] += (float) get_post_meta( $a->ID, 'charity_total', true );
	}
	krsort( $years );
	// Afronden voor weergave.
	foreach ( $years as $y => $v ) {
		$years[ $y ]['payout']  = round( $v['payout'], 2 );
		$years[ $y ]['charity'] = round( $v['charity'], 2 );
	}
	return $years;
}

add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$review = ekinese_year_review_for( $email );
	if ( $review ) {
		$data['year_review'] = $review;
	}
	return $data;
}, 10, 2 );
