<?php
/**
 * XGOUD Veilingen — front-end (eBay-stijl) bovenop het bestaande xg_auction-CPT
 * (inc/loyalty.php): overzicht-grid, detailpagina met bod-formulier + aftelklok,
 * goede-doel-aandeel, en een cron die afgelopen veilingen sluit + de winnaar mailt.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Minimale volgende bod voor een veiling. */
function ekinese_auction_min_next( $id ) {
	$cur = (float) ( get_post_meta( $id, 'current_bid', true ) ?: get_post_meta( $id, 'start_price', true ) );
	return $cur + max( 1, round( $cur * 0.02, 2 ) );
}

/** € weergave. */
function ekinese_auction_eur( $v ) {
	return '€ ' . number_format_i18n( (float) $v, 2 );
}

/* =====================================================================
   BLOKKEN
===================================================================== */
function ekinese_register_auction_blocks() {
	register_block_type( 'ekinese/auctions', array(
		'attributes'      => array( 'limit' => array( 'type' => 'number', 'default' => 24 ) ),
		'render_callback' => 'ekinese_render_auctions',
	) );
	register_block_type( 'ekinese/auction-detail', array( 'render_callback' => 'ekinese_render_auction_detail' ) );
}
add_action( 'init', 'ekinese_register_auction_blocks' );

/** Overzicht: lopende veilingen als kaarten. */
function ekinese_render_auctions( $attr ) {
	$posts = get_posts( array(
		'post_type'      => 'xg_auction',
		'post_status'    => 'publish',
		'posts_per_page' => (int) ( $attr['limit'] ?? 24 ),
		'orderby'        => 'meta_value',
		'meta_key'       => 'ends',
		'order'          => 'ASC',
	) );
	ob_start();
	echo '<section><div class="xg-container">';
	echo '<h2 class="xg-section-title">Veilingen</h2>';
	if ( ! $posts ) {
		echo '<p>Er zijn op dit moment geen veilingen. Kom snel terug!</p></div></section>';
		return ob_get_clean();
	}
	echo '<div class="xg-grid-3 xg-auction-grid">';
	foreach ( $posts as $p ) {
		$live  = ekinese_auction_is_live( $p->ID );
		$cur   = get_post_meta( $p->ID, 'current_bid', true ) ?: get_post_meta( $p->ID, 'start_price', true );
		$ends  = get_post_meta( $p->ID, 'ends', true );
		$count = (int) get_post_meta( $p->ID, 'bid_count', true );
		$pct   = (float) get_post_meta( $p->ID, 'charity_pct', true );
		$thumb = get_the_post_thumbnail( $p->ID, 'medium' );
		echo '<a class="xg-c-card xg-auction-card" href="' . esc_url( get_permalink( $p->ID ) ) . '" style="text-decoration:none">';
		if ( $thumb ) {
			echo '<div class="xg-auction-thumb">' . $thumb . '</div>'; // phpcs:ignore
		}
		echo '<span class="xg-auction-status ' . ( $live ? 'live' : 'closed' ) . '">' . ( $live ? 'Live' : 'Gesloten' ) . '</span>';
		echo '<h3>' . esc_html( get_the_title( $p->ID ) ) . '</h3>';
		echo '<p class="xg-auction-bid"><span>Huidig bod</span><strong>' . esc_html( ekinese_auction_eur( $cur ) ) . '</strong></p>';
		echo '<div class="xg-auction-meta"><span>' . esc_html( $count ) . ' biedingen</span>';
		if ( $live && $ends ) {
			echo '<span class="xg-auction-timer" data-ends="' . esc_attr( $ends ) . '"></span>';
		}
		echo '</div>';
		if ( $pct > 0 ) {
			echo '<p class="xg-auction-charity">' . esc_html( rtrim( rtrim( number_format( $pct, 1 ), '0' ), '.' ) ) . '% gaat naar een goed doel</p>';
		}
		echo '</a>';
	}
	echo '</div></div></section>';
	return ob_get_clean();
}

/** Detailpagina van één veiling (bod-formulier + aftelklok). */
function ekinese_render_auction_detail() {
	if ( ! is_singular( 'xg_auction' ) ) {
		return '';
	}
	$id      = get_the_ID();
	$live    = ekinese_auction_is_live( $id );
	$cur     = get_post_meta( $id, 'current_bid', true ) ?: get_post_meta( $id, 'start_price', true );
	$min     = ekinese_auction_min_next( $id );
	$ends    = get_post_meta( $id, 'ends', true );
	$count   = (int) get_post_meta( $id, 'bid_count', true );
	$pct     = (float) get_post_meta( $id, 'charity_pct', true );
	$proj    = get_post_meta( $id, 'charity_project', true );
	$thumb   = get_the_post_thumbnail( $id, 'large' );
	$endpoint = esc_url( rest_url( 'ekinese/v1/auction/' . $id . '/bid' ) );

	ob_start();
	echo '<section class="xg-hero-v2"><div class="xg-container"><div class="xg-grid-2 xg-auction-detail" style="gap:46px;align-items:start">';

	echo '<div class="xg-auction-detail-media">';
	echo $thumb ? $thumb : '<div class="xg-auction-thumb xg-auction-thumb--empty"></div>'; // phpcs:ignore
	$body = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
	if ( trim( wp_strip_all_tags( (string) $body ) ) ) {
		echo '<div class="xg-intro" style="margin-top:22px">' . wp_kses_post( $body ) . '</div>';
	}
	echo '</div>';

	echo '<div class="xg-auction-detail-bid">';
	echo '<div class="hero-kicker">VEILING</div><h1>' . esc_html( get_the_title( $id ) ) . '</h1>';
	echo '<div class="xg-auction-bidbox">';
	echo '<p class="xg-auction-bid"><span>Huidig bod (' . esc_html( $count ) . ' biedingen)</span><strong>' . esc_html( ekinese_auction_eur( $cur ) ) . '</strong></p>';
	if ( $live && $ends ) {
		echo '<p class="xg-auction-timer-lg" data-ends="' . esc_attr( $ends ) . '">—</p>';
	}
	if ( $pct > 0 ) {
		echo '<p class="xg-auction-charity">' . esc_html( rtrim( rtrim( number_format( $pct, 1 ), '0' ), '.' ) ) . '% van de opbrengst gaat naar ' . esc_html( $proj ?: 'een goed doel' ) . '.</p>';
	}
	if ( $live ) {
		echo '<form class="xg-auction-form" data-xg-auction data-endpoint="' . $endpoint . '" data-min="' . esc_attr( $min ) . '">';
		echo '<div class="xg-form-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
		echo '<label>Uw naam<input type="text" name="name" required></label>';
		echo '<label>Uw e-mail<input type="email" name="email" required></label>';
		echo '<label>Uw bod (min. ' . esc_html( ekinese_auction_eur( $min ) ) . ')<input type="number" name="amount" min="' . esc_attr( $min ) . '" step="0.01" required></label>';
		echo '<p class="xg-auction-warn">Let op: een bod is <strong>bindend</strong> en kan niet worden ingetrokken.</p>';
		echo '<button type="submit" class="xg-form-btn">Bod uitbrengen</button>';
		echo '<div class="xg-form-msg" role="status" hidden></div>';
		echo '</form>';
	} else {
		echo '<p class="xg-auction-closed-note">Deze veiling is gesloten.</p>';
	}
	echo '</div></div>';

	echo '</div></div></section>';
	return ob_get_clean();
}

/* =====================================================================
   ASSETS
===================================================================== */
function ekinese_auction_assets() {
	$needs = is_post_type_archive( 'xg_auction' ) || is_singular( 'xg_auction' )
		|| ( is_singular() && ( has_block( 'ekinese/auctions' ) || has_block( 'ekinese/auction-detail' ) ) );
	if ( ! $needs ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/auctions.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-auctions', get_theme_file_uri( 'assets/css/auctions.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/auctions.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-auctions', get_theme_file_uri( 'assets/js/auctions.js' ), array(), (string) filemtime( $js ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_auction_assets' );

/* =====================================================================
   CRON — afgelopen veilingen sluiten + winnaar mailen
===================================================================== */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_auction_close' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'xg_auction_close' );
	}
} );
add_action( 'xg_auction_close', 'ekinese_auctions_close_due' );

function ekinese_auctions_close_due() {
	$posts = get_posts( array(
		'post_type'      => 'xg_auction',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $posts as $id ) {
		$ends = get_post_meta( $id, 'ends', true );
		if ( ! $ends || strtotime( $ends ) > time() ) {
			continue; // nog live.
		}
		if ( get_post_meta( $id, 'status', true ) === 'closed' ) {
			continue; // al afgehandeld.
		}
		update_post_meta( $id, 'status', 'closed' );

		$final = (float) ( get_post_meta( $id, 'current_bid', true ) ?: 0 );
		$pct   = (float) get_post_meta( $id, 'charity_pct', true );
		if ( $pct > 0 && $final > 0 ) {
			update_post_meta( $id, 'charity_amount', round( $final * $pct / 100, 2 ) );
		}
		$winner = get_post_meta( $id, 'winner_email', true );
		$wname  = get_post_meta( $id, 'winner_name', true );
		$title  = get_the_title( $id );
		if ( $winner && is_email( $winner ) ) {
			wp_mail(
				$winner,
				sprintf( __( 'Gefeliciteerd — u heeft de veiling gewonnen: %s', 'ekinese' ), $title ),
				sprintf( "Beste %s,\n\nU heeft de veiling '%s' gewonnen met een bod van %s. U ontvangt zo spoedig mogelijk een pro forma factuur. Na betaling volgt de definitieve factuur.\n\nMet vriendelijke groet,\nXGOUD", $wname ?: '', $title, ekinese_auction_eur( $final ) )
			);
			if ( function_exists( 'ekinese_notify' ) ) {
				ekinese_notify( $winner, 'U heeft de veiling gewonnen!', sprintf( "Gefeliciteerd — u won '%s' met %s. U ontvangt een pro forma factuur.", $title, ekinese_auction_eur( $final ) ), get_permalink( $id ), 'won' );
			}
		}
		$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
		wp_mail( $admin, 'Veiling gesloten: ' . $title, sprintf( "De veiling '%s' is gesloten.\nEindbod: %s\nWinnaar: %s <%s>\n", $title, ekinese_auction_eur( $final ), $wname, $winner ) );
	}
}
