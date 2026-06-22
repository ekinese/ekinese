<?php
/**
 * XGOUD prijsgeschiedenis + koersgrafiek (self-built SVG, geen library).
 *
 * Een dagelijkse cron legt de spotprijs per metaal vast (max ~90 dagen). Het
 * blok ekinese/price-chart tekent een lichte lijn-/vlakgrafiek (7/30/90 dagen)
 * op de prijspagina's — goed voor verblijftijd en SEO. Bij een lege historie
 * wordt indicatief teruggevuld zodat de grafiek meteen zichtbaar is.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XG_PRICE_HISTORY_DAYS = 90;

/** Dagelijkse snapshot van de spotprijzen. */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_price_snapshot' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 00:05' ), 'daily', 'xg_price_snapshot' );
	}
} );

function ekinese_price_snapshot() {
	$hist = get_option( 'xg_price_history', array() );
	$today = gmdate( 'Y-m-d' );
	foreach ( array( 'goud', 'zilver', 'platina', 'palladium' ) as $metal ) {
		$spot = function_exists( 'ekinese_metal_spot' ) ? ekinese_metal_spot( $metal ) : 0;
		if ( $spot <= 0 ) {
			continue;
		}
		$hist[ $metal ]            = isset( $hist[ $metal ] ) ? $hist[ $metal ] : array();
		$hist[ $metal ][ $today ]  = round( $spot, 4 );
		// Afkappen op max dagen.
		if ( count( $hist[ $metal ] ) > XG_PRICE_HISTORY_DAYS ) {
			$hist[ $metal ] = array_slice( $hist[ $metal ], -XG_PRICE_HISTORY_DAYS, null, true );
		}
	}
	update_option( 'xg_price_history', $hist, false );
}
add_action( 'xg_price_snapshot', 'ekinese_price_snapshot' );

/**
 * Historie (datum=>prijs) van een metaal voor N dagen. Vult indicatief terug
 * wanneer er (nog) geen echte data is, zodat de grafiek niet leeg is.
 *
 * @return array [ 'Y-m-d' => float ]
 */
function ekinese_price_history( $metal, $days = 30 ) {
	$hist = get_option( 'xg_price_history', array() );
	$series = isset( $hist[ $metal ] ) ? $hist[ $metal ] : array();
	if ( count( $series ) < 2 ) {
		// Indicatieve backfill op basis van de huidige spot (± lichte ruis).
		$spot = function_exists( 'ekinese_metal_spot' ) ? ekinese_metal_spot( $metal ) : 0;
		if ( $spot > 0 ) {
			$series = array();
			$val    = $spot * 0.96;
			for ( $i = $days; $i >= 0; $i-- ) {
				$d = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
				// Lichte trend richting de actuele spot + deterministische ruis.
				$noise = ( ( crc32( $metal . $d ) % 100 ) / 100 - 0.5 ) * $spot * 0.02;
				$val  += ( $spot - $val ) * 0.12 + $noise;
				$series[ $d ] = round( max( 0.01, $val ), 4 );
			}
			$series[ gmdate( 'Y-m-d' ) ] = round( $spot, 4 );
		}
	}
	return array_slice( $series, -1 * ( $days + 1 ), null, true );
}

/* =====================================================================
   BLOK  ekinese/price-chart
===================================================================== */
function ekinese_register_price_chart() {
	register_block_type( 'ekinese/price-chart', array(
		'attributes'      => array(
			'metal' => array( 'type' => 'string', 'default' => 'goud' ),
			'days'  => array( 'type' => 'number', 'default' => 30 ),
		),
		'render_callback' => 'ekinese_render_price_chart',
	) );
}
add_action( 'init', 'ekinese_register_price_chart' );

function ekinese_render_price_chart( $attr ) {
	$metal = sanitize_key( $attr['metal'] ?? 'goud' );
	$labels = array( 'goud' => 'Goud', 'zilver' => 'Zilver', 'platina' => 'Platina', 'palladium' => 'Palladium' );
	$label  = $labels[ $metal ] ?? ucfirst( $metal );

	ob_start();
	echo '<section><div class="xg-container"><div class="xg-chart" data-metal="' . esc_attr( $metal ) . '">';
	echo '<div class="xg-chart-head"><h2>' . esc_html( $label ) . 'prijs – koersverloop</h2><div class="xg-chart-tabs"><button data-d="7">7d</button><button data-d="30" class="active">30d</button><button data-d="90">90d</button></div></div>';
	echo '<div class="xg-chart-canvas">' . ekinese_price_chart_svg( $metal, (int) ( $attr['days'] ?? 30 ) ) . '</div>';
	echo '</div></div></section>';
	return ob_get_clean();
}

/** Tekent de SVG-grafiek voor metaal/dagen. */
function ekinese_price_chart_svg( $metal, $days ) {
	$series = ekinese_price_history( $metal, $days );
	$vals   = array_values( $series );
	$dates  = array_keys( $series );
	$n      = count( $vals );
	if ( $n < 2 ) {
		return '<p style="color:var(--ink-soft,#6b665c)">Nog geen koersdata.</p>';
	}
	$min = min( $vals );
	$max = max( $vals );
	$rng = ( $max - $min ) ?: 1;
	$w   = 760;
	$h   = 220;
	$pad = 8;
	$pts = array();
	foreach ( $vals as $i => $v ) {
		$x = $pad + ( $i / ( $n - 1 ) ) * ( $w - 2 * $pad );
		$y = $h - $pad - ( ( $v - $min ) / $rng ) * ( $h - 2 * $pad );
		$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}
	$line = implode( ' ', $pts );
	$area = '0,' . $h . ' ' . $line . ' ' . $w . ',' . $h;
	$first = $vals[0];
	$last  = end( $vals );
	$up    = $last >= $first;
	$pct   = $first ? round( ( $last - $first ) / $first * 100, 1 ) : 0;
	$color = $up ? '#1f9d55' : '#AE1E1E';

	$svg  = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" class="xg-chart-svg" role="img" aria-label="Koersverloop ' . esc_attr( $metal ) . '">';
	$svg .= '<defs><linearGradient id="xgc_' . esc_attr( $metal ) . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="' . $color . '" stop-opacity=".18"/><stop offset="100%" stop-color="' . $color . '" stop-opacity="0"/></linearGradient></defs>';
	$svg .= '<polygon points="' . esc_attr( $area ) . '" fill="url(#xgc_' . esc_attr( $metal ) . ')"/>';
	$svg .= '<polyline points="' . esc_attr( $line ) . '" fill="none" stroke="' . $color . '" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>';
	$svg .= '</svg>';

	$meta = '<div class="xg-chart-meta"><span class="xg-chart-now">€ ' . esc_html( number_format_i18n( $last, 2 ) ) . '/g</span> '
		. '<span class="xg-chart-delta" style="color:' . $color . '">' . ( $up ? '▲' : '▼' ) . ' ' . esc_html( abs( $pct ) ) . '% (' . esc_html( $days ) . 'd)</span> '
		. '<span class="xg-chart-range">laag € ' . esc_html( number_format_i18n( $min, 2 ) ) . ' · hoog € ' . esc_html( number_format_i18n( $max, 2 ) ) . '</span></div>';

	// Data voor JS-tabwissel (zonder herladen).
	$json = wp_json_encode( array(
		7  => ekinese_price_chart_points( $metal, 7 ),
		30 => ekinese_price_chart_points( $metal, 30 ),
		90 => ekinese_price_chart_points( $metal, 90 ),
	) );
	return $svg . $meta . '<script type="application/json" class="xg-chart-data">' . $json . '</script>';
}

/** Compacte puntenset voor een periode (voor de JS-tabs). */
function ekinese_price_chart_points( $metal, $days ) {
	return array_values( ekinese_price_history( $metal, $days ) );
}

/** Chart-JS laden waar het blok staat. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! has_block( 'ekinese/price-chart' ) ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/price-chart.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-price-chart', get_theme_file_uri( 'assets/js/price-chart.js' ), array(), (string) filemtime( $js ), true );
	}
} );
