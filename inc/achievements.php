<?php
/**
 * XGOUD Achievements / Badges.
 *
 * Berekent (cosmetische) prestatie-badges uit de bestaande accountdata en voegt
 * ze toe aan het Mijn-XGOUD-overzicht. Doel: gamification — de gebruiker ziet
 * voortgang en wil meer badges verzamelen. Geen extra punten (puur erkenning);
 * de punten lopen via de bestaande acties.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Badges afleiden uit de reeds verzamelde accountdata. Draait LAAT (hoge
 * prioriteit) zodat alle andere modules hun velden al hebben toegevoegd.
 */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$appts   = is_array( $data['appointments'] ?? null ) ? $data['appointments'] : array();
	$done    = 0;
	foreach ( $appts as $a ) {
		$st = strtolower( (string) ( $a['status'] ?? '' ) );
		if ( in_array( $st, array( 'completed', 'paid', 'afgerond', 'uitbetaald' ), true ) ) {
			$done++;
		}
	}
	$pf       = $data['portfolio'] ?? array();
	$pf_items = is_array( $pf['items'] ?? null ) ? count( $pf['items'] ) : 0;
	$charity  = 0;
	foreach ( (array) ( $data['year_review'] ?? array() ) as $y ) {
		$charity += (float) ( $y['charity'] ?? 0 );
	}
	$moments  = is_array( $data['moments'] ?? null ) ? count( $data['moments'] ) : 0;
	$surveys_done = ! empty( $data['surveys_done'] ) ? (int) $data['surveys_done'] : 0;
	$referrals = (int) ( $data['referral_count'] ?? 0 );
	$escrow   = is_array( $data['escrow'] ?? null ) ? count( $data['escrow'] ) : 0;
	$points   = (int) ( $data['points'] ?? 0 );

	// [id, label, icon, behaald?, omschrijving]
	$defs = array(
		array( 'starter',   'Welkom',          '👋', true,                'Account aangemaakt' ),
		array( 'first_sale','Eerste verkoop',  '🤝', $done >= 1,          'Eerste afspraak afgerond' ),
		array( 'loyal',     'Trouwe klant',    '💎', $done >= 5,          '5 verkopen afgerond' ),
		array( 'investor',  'Portfolio-bouwer','📊', $pf_items >= 3,      '3+ posities in portfolio' ),
		array( 'sharer',    'Verhalenverteller','📸', $moments >= 1,      'Een moment gedeeld' ),
		array( 'voice',     'Meedenker',       '🗳', $surveys_done >= 1,  'Een vragenlijst ingevuld' ),
		array( 'hero',      'Goede-doelen-held','❤️', $charity > 0,       'Bijgedragen aan een goed doel' ),
		array( 'connector', 'Ambassadeur',     '📣', $referrals >= 1,     'Een vriend uitgenodigd' ),
		array( 'trusted',   'Treuhand-gebruiker','⚖️', $escrow >= 1,      'Treuhandservice gebruikt' ),
		array( 'collector', 'Puntenspaarder',  '⭐', $points >= 500,      '500+ spaarpunten' ),
		array( 'streaker',  'Op dreef',        '🔥', (int) ( $data['streak_best'] ?? 0 ) >= 7, '7 dagen op rij ingelogd' ),
	);

	$badges = array();
	$earned = 0;
	foreach ( $defs as $d ) {
		if ( $d[3] ) {
			$earned++;
		}
		$badges[] = array(
			'id'      => $d[0],
			'label'   => $d[1],
			'icon'    => $d[2],
			'earned'  => (bool) $d[3],
			'desc'    => $d[4],
		);
	}
	$data['badges']        = $badges;
	$data['badges_earned'] = $earned;
	$data['badges_total']  = count( $defs );

	// Profiel-volledigheid (voor de voortgangsbalk).
	$profile = $data['profile'] ?? array();
	$checks  = array(
		! empty( $profile['name'] ),
		! empty( $profile['phone'] ),
		$pf_items >= 1,
		! empty( $data['alerts'] ),
		$done >= 1,
	);
	$data['profile_completeness'] = (int) round( count( array_filter( $checks ) ) / max( 1, count( $checks ) ) * 100 );
	return $data;
}, 50, 2 );
