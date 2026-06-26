<?php
/**
 * XGOUD daily-streak / login-bonus.
 *
 * Bezoekt een klant zijn Mijn-XGOUD-overzicht op opeenvolgende dagen, dan loopt
 * de "streak" op en krijgt hij dagelijks een kleine bonus (1× per dag), met
 * extra mijlpalen op 7 en 30 dagen. Stimuleert terugkeren.
 *
 * Streak-state wordt op het account-profiel (xg_account) bewaard; voor klanten
 * zonder profiel wordt er een minimaal profiel aangemaakt op e-mail.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'ekinese_reward_rules', function ( $r ) {
	if ( ! isset( $r['streak'] ) ) {
		$r['streak'] = 5; // dagelijkse bonus
	}
	return $r;
} );

/**
 * Registreer een bezoek en werk de streak bij. Geeft de bonus 1× per dag.
 *
 * @return array { streak, best, awarded }
 */
function ekinese_streak_touch( $email ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return array( 'streak' => 0, 'best' => 0, 'awarded' => 0 );
	}
	// Profiel-id ophalen of aanmaken (hergebruik registratie-infra indien aanwezig).
	$id = function_exists( 'ekinese_account_profile_id' ) ? ekinese_account_profile_id( $email ) : 0;
	if ( ! $id && function_exists( 'ekinese_account_upsert' ) ) {
		$id = ekinese_account_upsert( $email );
	}
	if ( ! $id ) {
		return array( 'streak' => 0, 'best' => 0, 'awarded' => 0 );
	}

	$today = current_time( 'Y-m-d' );
	$last  = (string) get_post_meta( $id, 'streak_last', true );
	$streak = (int) get_post_meta( $id, 'streak', true );
	$best   = (int) get_post_meta( $id, 'streak_best', true );
	$awarded = 0;

	if ( $last === $today ) {
		return array( 'streak' => $streak, 'best' => max( $best, $streak ), 'awarded' => 0 );
	}

	$yesterday = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );
	$streak = ( $last === $yesterday ) ? $streak + 1 : 1;
	$best   = max( $best, $streak );

	update_post_meta( $id, 'streak', $streak );
	update_post_meta( $id, 'streak_best', $best );
	update_post_meta( $id, 'streak_last', $today );

	// Dagelijkse bonus + mijlpalen.
	if ( function_exists( 'ekinese_award_points' ) ) {
		$rules = ekinese_reward_rules();
		$pts   = (int) ( $rules['streak'] ?? 5 );
		if ( 7 === $streak ) {
			$pts += 20;
		} elseif ( 30 === $streak ) {
			$pts += 50;
		}
		ekinese_award_points( $email, $pts, 'streak', 'streak#' . $today );
		$awarded = $pts;
	}
	return array( 'streak' => $streak, 'best' => $best, 'awarded' => $awarded );
}

/* In het overzicht meesturen (draait vroeg, vóór achievements op prio 50). */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$s = ekinese_streak_touch( $email );
	$data['streak']         = (int) $s['streak'];
	$data['streak_best']    = (int) $s['best'];
	$data['streak_awarded'] = (int) $s['awarded'];
	return $data;
}, 6, 2 );
