<?php
/**
 * XGOUD prijsprognose (#14) – eenvoudige trend uit de koershistorie.
 *
 * Blok ekinese/price-forecast: berekent een lineaire trend uit
 * ekinese_price_history() en toont een duidelijk indicatieve verwachting
 * ("op basis van de trend van de afgelopen 30 dagen…"). Geen beleggingsadvies –
 * puur informatief. Self-built, geen plugin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'ekinese/price-forecast', array(
		'render_callback' => 'ekinese_render_price_forecast',
		'attributes'      => array(
			'metal' => array( 'type' => 'string', 'default' => 'goud' ),
		),
	) );
} );

/**
 * Lineaire trend (kleinste kwadraten) over de prijsreeks.
 *
 * @param array $series date => price
 * @return array { slope_per_day, last, projected_30, pct_30, direction }
 */
function ekinese_price_trend( $series ) {
	$vals = array_values( $series );
	$n    = count( $vals );
	if ( $n < 2 ) {
		return array( 'slope_per_day' => 0, 'last' => $n ? (float) end( $vals ) : 0, 'projected_30' => 0, 'pct_30' => 0, 'direction' => 'flat' );
	}
	$sx = $sy = $sxy = $sxx = 0;
	foreach ( $vals as $i => $y ) {
		$sx  += $i;
		$sy  += $y;
		$sxy += $i * $y;
		$sxx += $i * $i;
	}
	$denom = ( $n * $sxx - $sx * $sx );
	$slope = $denom != 0 ? ( $n * $sxy - $sx * $sy ) / $denom : 0; // phpcs:ignore
	$last  = (float) end( $vals );
	$proj  = $last + $slope * 30;
	$pct   = $last > 0 ? ( ( $proj - $last ) / $last ) * 100 : 0;
	$dir   = abs( $pct ) < 0.5 ? 'flat' : ( $pct > 0 ? 'up' : 'down' );
	return array(
		'slope_per_day' => round( $slope, 4 ),
		'last'          => round( $last, 2 ),
		'projected_30'  => round( $proj, 2 ),
		'pct_30'        => round( $pct, 1 ),
		'direction'     => $dir,
	);
}

/** Render de prognose-sectie. */
function ekinese_render_price_forecast( $attr = array() ) {
	$metal = isset( $attr['metal'] ) ? sanitize_key( $attr['metal'] ) : 'goud';
	if ( ! function_exists( 'ekinese_price_history' ) ) {
		return '';
	}
	$series = ekinese_price_history( $metal, 30 );
	$t      = ekinese_price_trend( $series );
	$labels = array( 'goud' => 'goud', 'zilver' => 'zilver', 'platina' => 'platina', 'palladium' => 'palladium' );
	$label  = $labels[ $metal ] ?? 'goud';

	$arrow = 'up' === $t['direction'] ? '▲' : ( 'down' === $t['direction'] ? '▼' : '▬' );
	$cls   = 'up' === $t['direction'] ? 'up' : ( 'down' === $t['direction'] ? 'down' : 'flat' );
	$word  = 'up' === $t['direction'] ? 'licht stijgend' : ( 'down' === $t['direction'] ? 'licht dalend' : 'stabiel' );

	ob_start();
	echo '<section class="xg-forecast"><div class="xg-container">';
	echo '<div class="xg-forecast-box xg-forecast-' . esc_attr( $cls ) . '">';
	echo '<div class="xg-forecast-head"><span class="xg-forecast-ico">' . esc_html( $arrow ) . '</span><h3>Trend ' . esc_html( $label ) . '</h3></div>';
	echo '<p class="xg-forecast-txt">Op basis van de trend van de afgelopen 30 dagen is de prijs van ' . esc_html( $label ) . ' <strong>' . esc_html( $word ) . '</strong>. ';
	if ( 'flat' !== $t['direction'] && $t['last'] > 0 ) {
		echo 'Indicatief zou de prijs bij ongewijzigde trend over 30 dagen rond <strong>€ ' . esc_html( number_format_i18n( $t['projected_30'], 2 ) ) . ' / gram</strong> kunnen liggen (' . esc_html( ( $t['pct_30'] > 0 ? '+' : '' ) . number_format_i18n( $t['pct_30'], 1 ) ) . '%).';
	}
	echo '</p>';
	echo '<p class="xg-forecast-disc">Let op: dit is een indicatieve trendweergave, géén beleggingsadvies. Koersen kunnen sterk schommelen.</p>';
	echo '</div></div></section>';
	return ob_get_clean();
}
