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
   DEMO-VEILINGEN — idempotent, zodat /veilingen/ niet leeg is om te testen.
   Verwijderbaar zodra er echte veilingen zijn (titels hieronder).
===================================================================== */
function ekinese_seed_dummy_auctions() {
	$now  = current_time( 'timestamp' );
	$ends = function ( $days ) use ( $now ) {
		return date( 'Y-m-d H:i:s', $now + (int) $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime
	};
	$items = array(
		array( 'Gouden Krugerrand 1 oz (2024)', 1800, 1875, 6, 10, 'Stichting Leergeld', 2,
			'Een prachtige gouden Krugerrand van 1 troy ounce, jaargang 2024. Geleverd met certificaat.' ),
		array( 'Zilveren Maple Leaf — tube (25 stuks)', 600, 642, 4, 5, 'Het Vergeten Kind', 3,
			'Volle tube van 25 zilveren Maple Leafs, 1 oz per munt, 999/1000.' ),
		array( 'Antiek gouden zakhorloge (14k)', 950, 1010, 8, 15, 'KWF Kankerbestrijding', 1,
			'Authentiek gouden zakhorloge, 14 karaat, begin 20e eeuw. Werkend uurwerk.' ),
		array( 'Diamanten solitairring 0,75 ct', 1400, 1400, 0, 10, 'Stichting Leergeld', 5,
			'Witgouden solitairring met een geslepen diamant van 0,75 ct, kleur G, helderheid VS1.' ),
	);
	$made = 0;
	foreach ( $items as $it ) {
		list( $title, $start, $cur, $bids, $pct, $proj, $days, $desc ) = $it;
		$name = sanitize_title( $title );
		$exist = get_posts( array( 'post_type' => 'xg_auction', 'name' => $name, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( ! empty( $exist ) ) {
			continue; // al aanwezig — niet overschrijven (mogelijk handmatig aangepast).
		}
		$id = wp_insert_post( array(
			'post_type'    => 'xg_auction',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $name,
			'post_content' => $desc,
		) );
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		update_post_meta( $id, 'start_price', $start );
		update_post_meta( $id, 'current_bid', $cur );
		update_post_meta( $id, 'bid_count', $bids );
		update_post_meta( $id, 'ends', $ends( $days ) );
		update_post_meta( $id, 'charity_pct', $pct );
		update_post_meta( $id, 'charity_project', $proj );
		update_post_meta( $id, 'status', 'live' );
		update_post_meta( $id, '_xg_dummy', '1' );
		$made++;
	}
	return $made;
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
		echo '<a class="xg-c-card xg-auction-card" href="' . esc_url( get_permalink( $p->ID ) ) . '" style="text-decoration:none" data-auction-id="' . esc_attr( $p->ID ) . '">';
		if ( $thumb ) {
			echo '<div class="xg-auction-thumb">' . $thumb . '</div>'; // phpcs:ignore
		}
		echo '<span class="xg-auction-status ' . ( $live ? 'live' : 'closed' ) . '">' . ( $live ? 'Live' : 'Gesloten' ) . '</span>';
		echo '<h3>' . esc_html( get_the_title( $p->ID ) ) . '</h3>';
		echo '<p class="xg-auction-bid"><span>Huidig bod</span><strong class="xg-auction-bid-val">' . esc_html( ekinese_auction_eur( $cur ) ) . '</strong></p>';
		echo '<div class="xg-auction-meta"><span><span class="xg-auction-count">' . esc_html( $count ) . '</span> biedingen</span>';
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
	echo '<div class="xg-auction-bidbox" data-auction-id="' . esc_attr( $id ) . '">';
	echo '<p class="xg-auction-bid"><span>Huidig bod (<span class="xg-auction-count">' . esc_html( $count ) . '</span> biedingen)</span><strong class="xg-auction-bid-val">' . esc_html( ekinese_auction_eur( $cur ) ) . '</strong></p>';
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
		wp_localize_script( 'ekinese-auctions', 'XGAuction', array(
			'rest' => esc_url_raw( rest_url( 'ekinese/v1/auction/' ) ),
		) );
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
			// Pro forma factuur aanmaken (charity wordt PAS na betaling vrijgegeven).
			if ( ! get_post_meta( $id, 'proforma_number', true ) ) {
				update_post_meta( $id, 'proforma_number', 'PF-' . gmdate( 'Y' ) . '-' . $id );
				update_post_meta( $id, 'proforma_date', gmdate( 'Y-m-d' ) );
			}
			// Spaarpunten voor de aankoop via de veiling.
			$pts = 0;
			if ( function_exists( 'ekinese_award_points' ) && function_exists( 'ekinese_reward_rules' ) && $final > 0 ) {
				$rules = ekinese_reward_rules();
				$pts   = (int) ( $rules['auction'] ?? $rules['deal'] ?? 0 ) + (int) floor( $final / 100 ) * (int) ( $rules['deal_per_100eur'] ?? 0 );
				ekinese_award_points( $winner, $pts, 'auction', 'auction#' . $id );
			}
			$pts_txt = $pts > 0 ? sprintf( ' U ontving hiervoor %d spaarpunten.', $pts ) : '';
			wp_mail(
				$winner,
				sprintf( __( 'Gefeliciteerd — u heeft de veiling gewonnen: %s', 'ekinese' ), $title ),
				sprintf( "Beste %s,\n\nU heeft de veiling '%s' gewonnen met een bod van %s.%s U ontvangt zo spoedig mogelijk een pro forma factuur. Na betaling volgt de definitieve factuur.\n\nMet vriendelijke groet,\nXGOUD", $wname ?: '', $title, ekinese_auction_eur( $final ), $pts_txt )
			);
			if ( function_exists( 'ekinese_notify' ) ) {
				ekinese_notify( $winner, 'U heeft de veiling gewonnen!', sprintf( "Gefeliciteerd — u won '%s' met %s.%s U ontvangt een pro forma factuur.", $title, ekinese_auction_eur( $final ), $pts_txt ), get_permalink( $id ), 'won' );
			}
		}
		$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
		wp_mail( $admin, 'Veiling gesloten: ' . $title, sprintf( "De veiling '%s' is gesloten.\nEindbod: %s\nWinnaar: %s <%s>\n", $title, ekinese_auction_eur( $final ), $wname, $winner ) );
	}
}

/* =====================================================================
   CHARITY-VRIJGAVE: afgerekende veilingen tellen mee in het projecttotaal
===================================================================== */
add_filter( 'ekinese_charity_accrued_extra', function ( $extra, $project_id, $title ) {
	$ids = get_posts( array(
		'post_type'   => 'xg_auction',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'charity_project', 'value' => $title ),
			array( 'key' => 'charity_released', 'value' => '1' ),
		),
	) );
	$sum = 0;
	foreach ( $ids as $aid ) {
		$sum += (float) get_post_meta( $aid, 'charity_amount', true );
	}
	return $extra + $sum;
}, 10, 3 );

/* =====================================================================
   ADMIN: facturatie & betaling per veiling
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_auction_billing', __( 'Facturatie & betaling', 'ekinese' ), 'ekinese_auction_billing_box', 'xg_auction', 'side', 'default' );
} );

function ekinese_auction_billing_box( $post ) {
	wp_nonce_field( 'xg_auction_billing', 'xg_auction_billing_nonce' );
	$pf   = get_post_meta( $post->ID, 'proforma_number', true );
	$inv  = get_post_meta( $post->ID, 'invoice_number', true );
	$paid = get_post_meta( $post->ID, 'paid', true );
	$ca   = (float) get_post_meta( $post->ID, 'charity_amount', true );
	$rel  = get_post_meta( $post->ID, 'charity_released', true );
	$status = get_post_meta( $post->ID, 'status', true );
	$live   = ekinese_auction_is_live( $post->ID );
	if ( 'closed' === $status ) {
		echo '<p><strong>' . esc_html__( 'Veiling gesloten.', 'ekinese' ) . '</strong></p>';
	} elseif ( $live ) {
		echo '<p><label><input type="checkbox" name="xg_auction_close_now" value="1"> ' . esc_html__( 'Veiling nu sluiten', 'ekinese' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Sluit de veiling direct, wijst de winnaar toe en stuurt de pro forma factuur (na “Bijwerken”).', 'ekinese' ) . '</p>';
	}
	echo '<p>Pro forma: <strong>' . esc_html( $pf ?: '—' ) . '</strong></p>';
	echo '<p>Factuur: <strong>' . esc_html( $inv ?: '—' ) . '</strong></p>';
	echo '<p><label><input type="checkbox" name="xg_auction_paid" value="1" ' . checked( $paid, '1', false ) . '> ' . esc_html__( 'Betaling ontvangen', 'ekinese' ) . '</label></p>';
	echo '<p class="description">' . esc_html__( 'Bij “Betaling ontvangen” wordt de definitieve factuur aangemaakt en het goede-doel-aandeel', 'ekinese' ) . ' ' . ( $ca > 0 ? '(' . esc_html( ekinese_auction_eur( $ca ) ) . ') ' : '' ) . esc_html__( 'vrijgegeven voor het project.', 'ekinese' ) . ( $rel ? ' <strong>Vrijgegeven.</strong>' : '' ) . '</p>';
}

function ekinese_auction_billing_save( $post_id ) {
	if ( ! isset( $_POST['xg_auction_billing_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_auction_billing_nonce'] ), 'xg_auction_billing' ) ) {
		return;
	}
	// Veiling direct sluiten (zet einde op nu en draai de sluit-routine).
	if ( ! empty( $_POST['xg_auction_close_now'] ) && get_post_meta( $post_id, 'status', true ) !== 'closed' ) {
		update_post_meta( $post_id, 'ends', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 ) ); // phpcs:ignore WordPress.DateTime
		if ( function_exists( 'ekinese_auctions_close_due' ) ) {
			ekinese_auctions_close_due();
		}
	}
	$paid = ! empty( $_POST['xg_auction_paid'] );
	$was  = get_post_meta( $post_id, 'paid', true ) === '1';
	update_post_meta( $post_id, 'paid', $paid ? '1' : '' );
	if ( ! $paid || $was ) {
		return; // niets nieuws.
	}
	// Definitieve factuur + charity vrijgeven (gebeurt PAS hier, na betaling).
	if ( ! get_post_meta( $post_id, 'invoice_number', true ) ) {
		update_post_meta( $post_id, 'invoice_number', 'INV-' . gmdate( 'Y' ) . '-' . $post_id );
		update_post_meta( $post_id, 'invoice_date', gmdate( 'Y-m-d' ) );
	}
	update_post_meta( $post_id, 'charity_released', '1' );

	// Auto: gewonnen + betaalde veiling toevoegen aan het portfolio van de koper.
	$winner_email = get_post_meta( $post_id, 'winner_email', true );
	if ( $winner_email && is_email( $winner_email ) && ! get_post_meta( $post_id, '_holding_id', true ) ) {
		$hid = wp_insert_post( array(
			'post_type'   => 'xg_holding',
			'post_status' => 'publish',
			'post_title'  => get_the_title( $post_id ),
		) );
		if ( $hid && ! is_wp_error( $hid ) ) {
			update_post_meta( $hid, 'email', $winner_email );
			update_post_meta( $hid, 'metal', get_post_meta( $post_id, 'metal', true ) );
			update_post_meta( $hid, 'fine_weight', get_post_meta( $post_id, 'fine_weight', true ) );
			update_post_meta( $hid, 'qty', 1 );
			update_post_meta( $hid, 'purchase_price', (float) get_post_meta( $post_id, 'current_bid', true ) );
			update_post_meta( $hid, 'purchase_date', gmdate( 'Y-m-d' ) );
			update_post_meta( $post_id, '_holding_id', $hid );
		}
	}

	$winner = get_post_meta( $post_id, 'winner_email', true );
	$title  = get_the_title( $post_id );
	$inv    = get_post_meta( $post_id, 'invoice_number', true );
	$ca     = (float) get_post_meta( $post_id, 'charity_amount', true );
	$proj   = get_post_meta( $post_id, 'charity_project', true );
	if ( $winner && is_email( $winner ) ) {
		$ctxt = $ca > 0 ? sprintf( ' Hiermee is %s vrijgegeven voor %s.', ekinese_auction_eur( $ca ), $proj ?: 'een goed doel' ) : '';
		wp_mail( $winner, 'Uw factuur — ' . $title, sprintf( "Beste klant,\n\nWij hebben uw betaling voor '%s' ontvangen. Uw definitieve factuur is %s.%s\n\nMet vriendelijke groet,\nXGOUD", $title, $inv, $ctxt ) );
		if ( function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $winner, 'Betaling ontvangen — factuur beschikbaar', sprintf( "Wij ontvingen uw betaling voor '%s'. Factuur %s.", $title, $inv ), get_permalink( $post_id ), 'invoice' );
		}
	}
}
add_action( 'save_post_xg_auction', 'ekinese_auction_billing_save' );

/* =====================================================================
   ACCOUNT: gewonnen veilingen + facturatiestatus in "Mijn XGOUD"
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$ids = get_posts( array(
		'post_type'   => 'xg_auction',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => 'winner_email',
		'meta_value'  => sanitize_email( $email ),
	) );
	$rows = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, 'status', true ) !== 'closed' ) {
			continue;
		}
		$rows[] = array(
			'title'    => get_the_title( $id ),
			'amount'   => ekinese_auction_eur( get_post_meta( $id, 'current_bid', true ) ),
			'proforma' => get_post_meta( $id, 'proforma_number', true ) ?: '—',
			'invoice'  => get_post_meta( $id, 'invoice_number', true ) ?: '—',
			'paid'     => get_post_meta( $id, 'paid', true ) === '1' ? 'Ja' : 'Nee',
		);
	}
	if ( $rows ) {
		$data['auctions'] = $rows;
	}
	return $data;
}, 10, 2 );
