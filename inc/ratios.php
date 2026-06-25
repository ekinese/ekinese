<?php
/**
 * XGOUD Ratio's – de verhouding tussen twee edelmetaalprijzen (bv. de bekende
 * goud/zilver-ratio). Blok ekinese/metal-ratio toont de actuele ratio + een
 * verloop-grafiek. Eigen pagina's per metaalpaar (zie inc/installer.php).
 *
 * Bron: ekinese_metal_spot() (actueel) + ekinese_price_history() (verloop),
 * met dezelfde slugs als de price-chart: goud/zilver/platina/palladium.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Labels per metaal-slug. */
function ekinese_ratio_labels() {
	return array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' );
}

/** De zes zinvolle metaalparen (hoogste waarde eerst), met slug + titel. */
function ekinese_ratio_pairs() {
	return array(
		'goud-zilver'         => array( 'goud', 'zilver' ),
		'goud-platina'        => array( 'goud', 'platina' ),
		'goud-palladium'      => array( 'goud', 'palladium' ),
		'platina-zilver'      => array( 'platina', 'zilver' ),
		'palladium-zilver'    => array( 'palladium', 'zilver' ),
		'platina-palladium'   => array( 'platina', 'palladium' ),
	);
}

/** Ratio-reeks (datum → A/B) over de opgegeven dagen. */
function ekinese_metal_ratio_series( $a, $b, $days = 90 ) {
	if ( ! function_exists( 'ekinese_price_history' ) ) {
		return array();
	}
	$ha  = ekinese_price_history( $a, $days );
	$hb  = ekinese_price_history( $b, $days );
	$out = array();
	foreach ( $ha as $date => $va ) {
		if ( isset( $hb[ $date ] ) && $hb[ $date ] > 0 && $va > 0 ) {
			$out[ $date ] = $va / $hb[ $date ];
		}
	}
	return $out;
}

/** Inline block-markup voor een ratio-pagina (gebruikt door de installer). */
function ekinese_ratio_page_markup( $a, $b ) {
	return '<!-- wp:group {"className":"xg-blueprint"} --><div class="wp-block-group xg-blueprint">'
		. '<!-- wp:ekinese/metal-ratio {"a":"' . esc_attr( $a ) . '","b":"' . esc_attr( $b ) . '"} /-->'
		. '</div><!-- /wp:group -->';
}

/* =====================================================================
   BLOK  ekinese/metal-ratio
===================================================================== */
function ekinese_register_metal_ratio() {
	register_block_type(
		'ekinese/metal-ratio',
		array(
			'attributes'      => array(
				'a'    => array( 'type' => 'string', 'default' => 'goud' ),
				'b'    => array( 'type' => 'string', 'default' => 'zilver' ),
				'days' => array( 'type' => 'number', 'default' => 90 ),
			),
			'render_callback' => 'ekinese_render_metal_ratio',
		)
	);
}
add_action( 'init', 'ekinese_register_metal_ratio' );

function ekinese_render_metal_ratio( $attr ) {
	$a      = sanitize_key( $attr['a'] ?? 'goud' );
	$b      = sanitize_key( $attr['b'] ?? 'zilver' );
	$days   = (int) ( $attr['days'] ?? 90 );
	$labels = ekinese_ratio_labels();
	$la     = $labels[ $a ] ?? ucfirst( $a );
	$lb     = $labels[ $b ] ?? ucfirst( $b );

	$sa    = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $a ) : 0;
	$sb    = function_exists( 'ekinese_metal_spot' ) ? (float) ekinese_metal_spot( $b ) : 0;
	$ratio = $sb > 0 ? $sa / $sb : 0;

	$series = ekinese_metal_ratio_series( $a, $b, $days );

	ob_start();
	echo '<section><div class="xg-container"><div class="xg-chart xg-ratio" data-ratio="' . esc_attr( $a . '-' . $b ) . '">';
	echo '<div class="xg-chart-head"><h2>' . esc_html( $la ) . '/' . esc_html( $lb ) . '-ratio</h2></div>';
	echo '<p class="xg-ratio-now"><strong>' . esc_html( number_format_i18n( $ratio, 2 ) ) . '</strong> <span>g ' . esc_html( strtolower( $lb ) ) . ' = 1 g ' . esc_html( strtolower( $la ) ) . '</span></p>';
	echo '<div class="xg-chart-canvas">' . ekinese_ratio_svg( $series, $a . '-' . $b ) . '</div>'; // phpcs:ignore
	echo '<p class="xg-intro" style="margin-top:18px">De ' . esc_html( strtolower( $la ) ) . '/' . esc_html( strtolower( $lb ) ) . '-ratio geeft aan hoeveel gram ' . esc_html( strtolower( $lb ) ) . ' gelijkstaat aan één gram ' . esc_html( strtolower( $la ) ) . '. Een hoge ratio betekent dat ' . esc_html( strtolower( $lb ) ) . ' relatief goedkoop is ten opzichte van ' . esc_html( strtolower( $la ) ) . '; een lage ratio het omgekeerde.</p>';
	echo '</div></div></section>';
	return ob_get_clean();
}

/** SVG-lijngrafiek voor een ratio-reeks (waarden = verhoudingsgetallen). */
function ekinese_ratio_svg( $series, $key ) {
	$vals = array_values( $series );
	$n    = count( $vals );
	if ( $n < 2 ) {
		return '<p style="color:var(--ink-soft,#6b665c)">Nog niet genoeg koersdata voor een verloop.</p>';
	}
	$min = min( $vals );
	$max = max( $vals );
	$rng = ( $max - $min ) ?: 1;
	$w   = 760;
	$h   = 220;
	$pad = 8;
	$pts = array();
	foreach ( $vals as $i => $v ) {
		$x     = $pad + ( $i / ( $n - 1 ) ) * ( $w - 2 * $pad );
		$y     = $h - $pad - ( ( $v - $min ) / $rng ) * ( $h - 2 * $pad );
		$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}
	$line  = implode( ' ', $pts );
	$area  = '0,' . $h . ' ' . $line . ' ' . $w . ',' . $h;
	$up    = end( $vals ) >= $vals[0];
	$color = $up ? '#1f9d55' : '#AE1E1E';
	$id    = 'xgr_' . preg_replace( '/[^a-z0-9]/', '', $key );

	$svg  = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" class="xg-chart-svg" role="img" aria-label="Ratio-verloop">';
	$svg .= '<defs><linearGradient id="' . esc_attr( $id ) . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="' . $color . '" stop-opacity=".18"/><stop offset="100%" stop-color="' . $color . '" stop-opacity="0"/></linearGradient></defs>';
	$svg .= '<polygon points="' . esc_attr( $area ) . '" fill="url(#' . esc_attr( $id ) . ')"/>';
	$svg .= '<polyline points="' . esc_attr( $line ) . '" fill="none" stroke="' . $color . '" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>';
	$svg .= '</svg>';
	return $svg;
}
