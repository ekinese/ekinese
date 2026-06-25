<?php
/**
 * XGOUD Marktplaats — gebruikers plaatsen eigen producten (foto's, beschrijving,
 * prijs, looptijd). Bij het plaatsen kiest de verkoper: hier op de Marktplaats
 * verkopen (verwijderbaar) OF via een XGOUD-veiling (eenmaal geplaatst = blijft).
 *
 * Strikte regels:
 *  - GEEN communicatie tussen gebruikers. Nooit. Contactgegevens (e-mail/telefoon/
 *    URL's) worden geweigerd in titel/beschrijving. Interesse loopt uitsluitend via
 *    XGOUD; verkoper- en kopergegevens worden nooit aan elkaar getoond.
 *  - Punten voor plaatsen en verkopen. Een vast deel van de opbrengst gaat naar een
 *    goed doel (charity), net als bij veilingen.
 *  - Het systeem signaleert mogelijke overlappingen (dubbele advertenties) en meldt
 *    dat in het admin.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT  xg_market
===================================================================== */
function ekinese_register_market() {
	register_post_type( 'xg_market', array(
		'labels'       => array( 'name' => __( 'Marktplaats', 'ekinese' ), 'singular_name' => __( 'Advertentie', 'ekinese' ), 'menu_name' => __( 'Marktplaats', 'ekinese' ) ),
		'public'       => true,
		'has_archive'  => false,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-store',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'rewrite'      => array( 'slug' => 'marktplaats-item' ),
	) );
	foreach ( array( 'email', 'price', 'category', 'metal', 'condition', 'gallery', 'status', 'charity_pct', 'charity_amount', 'charity_released', 'expires', 'token', 'norm_title', 'paid' ) as $f ) {
		register_post_meta( 'xg_market', $f, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}
}
add_action( 'init', 'ekinese_register_market' );

function ekinese_market_categories() {
	return array(
		'munten'    => 'Munten',
		'baren'     => 'Baren',
		'sieraden'  => 'Sieraden',
		'horloges'  => 'Horloges',
		'edelstenen'=> 'Edelstenen',
		'overig'    => 'Overig',
	);
}

function ekinese_market_eur( $v ) {
	return '€ ' . number_format_i18n( (float) $v, 2 );
}

/**
 * Detecteert contactgegevens (e-mail/telefoon/URL) — verboden i.v.m. de
 * "geen communicatie tussen gebruikers"-regel.
 */
function ekinese_market_has_contact( $text ) {
	$text = (string) $text;
	if ( preg_match( '/[\w.+-]+@[\w-]+\.[\w.-]+/', $text ) ) {
		return true; // e-mail
	}
	if ( preg_match( '#\b(?:https?://|www\.)\S+#i', $text ) ) {
		return true; // url
	}
	// Telefoonnummer: 8+ cijfers (met scheidingstekens).
	$digits = preg_replace( '/\D+/', '', $text );
	if ( strlen( $digits ) >= 8 && preg_match( '/(\+?\d[\d\s().\/-]{7,}\d)/', $text ) ) {
		return true;
	}
	return false;
}

/**
 * Demo-advertenties (idempotent) zodat de Marktplaats niet leeg is om te testen.
 *
 * @return int
 */
function ekinese_seed_dummy_market() {
	$items = array(
		array( 'Gouden dukaat 1991', 'munten', 285, 'Zeer goed', 'Originele gouden dukaat uit 1991, 98/100 gradering.' ),
		array( 'Zilveren armband 925', 'sieraden', 75, 'Gebruikt', 'Massieve zilveren schakelarmband, 925/1000, 28 gram.' ),
		array( 'Krugerrand 1 oz 2019', 'munten', 1850, 'Nieuw', 'Gouden Krugerrand 1 troy ounce, jaargang 2019, met capsule.' ),
		array( 'Omega Seamaster (vintage)', 'horloges', 1450, 'Gebruikt', 'Vintage Omega Seamaster, automatisch uurwerk, recent onderhouden.' ),
	);
	$made = 0;
	foreach ( $items as $it ) {
		$name  = sanitize_title( $it[0] );
		$exist = get_posts( array( 'post_type' => 'xg_market', 'name' => $name, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( ! empty( $exist ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'xg_market', 'post_status' => 'publish', 'post_title' => $it[0], 'post_name' => $name, 'post_content' => $it[4] ) );
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		update_post_meta( $id, 'email', 'demo@xgoud.nl' );
		update_post_meta( $id, 'price', $it[2] );
		update_post_meta( $id, 'category', $it[1] );
		update_post_meta( $id, 'condition', $it[3] );
		update_post_meta( $id, 'status', 'active' );
		update_post_meta( $id, 'charity_pct', 5 );
		update_post_meta( $id, 'expires', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 14 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime
		update_post_meta( $id, 'norm_title', ekinese_market_normalize( $it[0] ) );
		update_post_meta( $id, '_xg_dummy', '1' );
		$made++;
	}
	return $made;
}

/* =====================================================================
   OVERZICHT-BLOK  ekinese/marketplace
===================================================================== */
function ekinese_register_market_blocks() {
	register_block_type( 'ekinese/marketplace', array(
		'attributes'      => array( 'limit' => array( 'type' => 'number', 'default' => 24 ) ),
		'render_callback' => 'ekinese_render_marketplace',
	) );
	register_block_type( 'ekinese/market-submit', array( 'render_callback' => 'ekinese_render_market_submit' ) );
	register_block_type( 'ekinese/market-detail', array( 'render_callback' => 'ekinese_render_market_detail' ) );
}
add_action( 'init', 'ekinese_register_market_blocks' );

function ekinese_render_marketplace( $attr ) {
	$cats  = ekinese_market_categories();
	$posts = get_posts( array(
		'post_type'      => 'xg_market',
		'post_status'    => 'publish',
		'posts_per_page' => (int) ( $attr['limit'] ?? 24 ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( array( 'key' => 'status', 'value' => 'active' ) ),
	) );
	ob_start();
	echo '<section class="xg-mp"><div class="xg-container">';
	echo '<div class="xg-mp-head"><h2 class="xg-section-title">Marktplaats</h2>';
	echo '<a class="xg-btn-gold xg-mp-place" href="' . esc_url( home_url( '/marktplaats/plaatsen/' ) ) . '">Plaats een advertentie</a></div>';
	echo '<p class="xg-intro">Koop en verkoop edelmetaal, munten, sieraden en horloges. Veilig via XGOUD — wij regelen de transactie, er is geen onderling contact.</p>';

	// Categorie-filter (client-side).
	echo '<div class="xg-mp-filter"><button type="button" class="xg-mp-fbtn active" data-cat="">Alle</button>';
	foreach ( $cats as $k => $lbl ) {
		echo '<button type="button" class="xg-mp-fbtn" data-cat="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</button>';
	}
	echo '</div>';

	if ( ! $posts ) {
		echo '<p class="xg-mp-empty">Er zijn nog geen advertenties. Wees de eerste en plaats er een!</p></div></section>';
		return ob_get_clean();
	}
	echo '<div class="xg-mp-grid xg-grid-4">';
	foreach ( $posts as $p ) {
		$price = get_post_meta( $p->ID, 'price', true );
		$cat   = get_post_meta( $p->ID, 'category', true );
		$pct   = (float) get_post_meta( $p->ID, 'charity_pct', true );
		$thumb = get_the_post_thumbnail( $p->ID, 'medium' );
		echo '<a class="xg-c-card xg-mp-card" data-cat="' . esc_attr( $cat ) . '" href="' . esc_url( get_permalink( $p->ID ) ) . '" style="text-decoration:none">';
		echo '<div class="xg-mp-thumb">' . ( $thumb ? $thumb : '<span class="xg-mp-noimg">Geen foto</span>' ) . '</div>'; // phpcs:ignore
		echo '<h3>' . esc_html( get_the_title( $p->ID ) ) . '</h3>';
		echo '<p class="xg-mp-price">' . esc_html( ekinese_market_eur( $price ) ) . '</p>';
		if ( $pct > 0 ) {
			echo '<span class="xg-mp-charity">' . esc_html( rtrim( rtrim( number_format( $pct, 1 ), '0' ), '.' ) ) . '% naar goed doel</span>';
		}
		echo '</a>';
	}
	echo '</div></div></section>';
	return ob_get_clean();
}

/** Detailpagina van één advertentie (geen verkopergegevens). */
function ekinese_render_market_detail() {
	if ( ! is_singular( 'xg_market' ) ) {
		return '';
	}
	$id     = get_the_ID();
	$status = get_post_meta( $id, 'status', true );
	$price  = get_post_meta( $id, 'price', true );
	$pct    = (float) get_post_meta( $id, 'charity_pct', true );
	$cats   = ekinese_market_categories();
	$cat    = get_post_meta( $id, 'category', true );
	$cond   = get_post_meta( $id, 'condition', true );
	$gallery = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, 'gallery', true ) ) ) );
	$endpoint = esc_url( rest_url( 'ekinese/v1/market/interest' ) );

	ob_start();
	echo '<section class="xg-hero-v2"><div class="xg-container"><div class="xg-grid-2 xg-mp-detail" style="gap:46px;align-items:start">';

	echo '<div class="xg-mp-detail-media">';
	echo get_the_post_thumbnail( $id, 'large', array( 'class' => 'xg-mp-main-img' ) ); // phpcs:ignore
	if ( $gallery ) {
		echo '<div class="xg-mp-gallery">';
		foreach ( $gallery as $aid ) {
			$img = wp_get_attachment_image( $aid, 'medium' );
			if ( $img ) {
				echo '<span class="xg-mp-gthumb">' . $img . '</span>'; // phpcs:ignore
			}
		}
		echo '</div>';
	}
	$body = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
	if ( trim( wp_strip_all_tags( (string) $body ) ) ) {
		echo '<div class="xg-intro" style="margin-top:22px">' . wp_kses_post( $body ) . '</div>';
	}
	echo '</div>';

	echo '<div class="xg-mp-detail-buy">';
	echo '<div class="hero-kicker">MARKTPLAATS</div><h1>' . esc_html( get_the_title( $id ) ) . '</h1>';
	echo '<div class="xg-mp-buybox">';
	echo '<p class="xg-mp-price-lg">' . esc_html( ekinese_market_eur( $price ) ) . '</p>';
	echo '<ul class="xg-mp-specs">';
	if ( $cat ) { echo '<li><span>Categorie</span><strong>' . esc_html( $cats[ $cat ] ?? $cat ) . '</strong></li>'; }
	if ( $cond ) { echo '<li><span>Staat</span><strong>' . esc_html( $cond ) . '</strong></li>'; }
	echo '</ul>';
	if ( $pct > 0 ) {
		echo '<p class="xg-mp-charity">' . esc_html( rtrim( rtrim( number_format( $pct, 1 ), '0' ), '.' ) ) . '% van de opbrengst gaat naar een goed doel.</p>';
	}
	if ( 'active' === $status ) {
		echo '<form class="xg-mp-interest" data-xg-form data-endpoint="' . $endpoint . '">';
		echo '<div class="xg-form-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
		echo '<input type="hidden" name="listing" value="' . esc_attr( $id ) . '">';
		echo '<p class="xg-mp-note">Koop veilig via XGOUD. Wij verwerken de betaling en verzending — geen onderling contact.</p>';
		echo '<label>Uw naam<input type="text" name="name"></label>';
		echo '<label>Uw e-mail<input type="email" name="email" required></label>';
		echo '<button type="submit" class="xg-form-btn">Reserveer via XGOUD</button>';
		echo '<div class="xg-form-msg" role="status" hidden></div>';
		echo '</form>';
	} else {
		echo '<p class="xg-mp-closed">Deze advertentie is niet meer beschikbaar.</p>';
	}
	echo '</div></div>';

	echo '</div></div></section>';
	return ob_get_clean();
}

/** Plaats-formulier (alleen ingelogd via account-token). */
function ekinese_render_market_submit() {
	$cats     = ekinese_market_categories();
	$endpoint = esc_url( rest_url( 'ekinese/v1/market/submit' ) );
	ob_start();
	echo '<section class="xg-mp-submit"><div class="xg-container">';
	echo '<h2 class="xg-section-title">Plaats een advertentie</h2>';
	echo '<p class="xg-intro">Verkoop uw product op de Marktplaats óf laat het veilen via XGOUD. Let op: vermeld <strong>geen</strong> contactgegevens — alle communicatie loopt via XGOUD.</p>';
	echo '<form class="xg-mp-form" data-xg-market-submit data-endpoint="' . $endpoint . '" enctype="multipart/form-data">';
	echo '<div class="xg-form-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	echo '<div class="xg-mp-form-grid">';
	echo '<label>Uw e-mail (privé, niet zichtbaar)<input type="email" name="email" required></label>';
	echo '<label>Titel<input type="text" name="title" maxlength="90" required></label>';
	echo '<label>Categorie<select name="category">';
	foreach ( $cats as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>'; }
	echo '</select></label>';
	echo '<label>Staat<input type="text" name="condition" placeholder="bijv. Nieuw / Gebruikt" maxlength="40"></label>';
	echo '<label>Prijs (€)<input type="number" name="price" min="1" step="0.01" required></label>';
	echo '<label>Looptijd<select name="duration"><option value="7">7 dagen</option><option value="14">14 dagen</option><option value="30">30 dagen</option></select></label>';
	echo '</div>';
	echo '<label>Beschrijving (geen contactgegevens)<textarea name="description" rows="5" required></textarea></label>';
	echo '<label class="xg-mp-file">Foto\'s (max. 5)<input type="file" name="photos[]" accept="image/*" multiple></label>';
	echo '<fieldset class="xg-mp-mode"><legend>Hoe wilt u verkopen?</legend>';
	echo '<label class="xg-mp-radio"><input type="radio" name="mode" value="market" checked> <span><strong>Op de Marktplaats</strong> — vaste prijs. U kunt de advertentie altijd verwijderen.</span></label>';
	echo '<label class="xg-mp-radio"><input type="radio" name="mode" value="auction"> <span><strong>Via een XGOUD-veiling</strong> — hoogste bod wint. Let op: een veiling kan <strong>niet</strong> worden teruggetrokken.</span></label>';
	echo '</fieldset>';
	echo '<button type="submit" class="xg-form-btn">Plaatsen</button>';
	echo '<div class="xg-form-msg" role="status" hidden></div>';
	echo '</form></div></section>';
	return ob_get_clean();
}

/* =====================================================================
   ASSETS
===================================================================== */
function ekinese_market_assets() {
	$needs = is_singular( 'xg_market' )
		|| ( is_singular() && ( has_block( 'ekinese/marketplace' ) || has_block( 'ekinese/market-submit' ) || has_block( 'ekinese/market-detail' ) ) );
	if ( ! $needs ) {
		return;
	}
	$js = get_theme_file_path( 'assets/js/market.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-market', get_theme_file_uri( 'assets/js/market.js' ), array(), (string) filemtime( $js ), true );
	}
	// Het interesse-formulier hergebruikt de generieke forms.js.
	$fjs = get_theme_file_path( 'assets/js/forms.js' );
	if ( file_exists( $fjs ) ) {
		wp_enqueue_script( 'ekinese-forms', get_theme_file_uri( 'assets/js/forms.js' ), array(), (string) filemtime( $fjs ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_market_assets' );

/* =====================================================================
   REST – plaatsen / verwijderen / interesse
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/market/submit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_market_submit',
	) );
	register_rest_route( 'ekinese/v1', '/market/remove', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_market_remove',
	) );
	register_rest_route( 'ekinese/v1', '/market/interest', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_market_interest',
	) );
} );

/** Verwerkt geüploade foto's → attachment-ID's (geen WP-login vereist). */
function ekinese_market_handle_photos( $post_id ) {
	if ( empty( $_FILES['photos'] ) || empty( $_FILES['photos']['name'][0] ) ) {
		return array();
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
	$ids     = array();
	$files   = $_FILES['photos']; // phpcs:ignore WordPress.Security
	$count   = min( 5, count( (array) $files['name'] ) );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) || ! empty( $files['error'][ $i ] ) ) {
			continue;
		}
		$ft = wp_check_filetype( sanitize_file_name( $files['name'][ $i ] ) );
		if ( ! in_array( (string) $ft['type'], $allowed, true ) ) {
			continue;
		}
		$single = array(
			'name'     => sanitize_file_name( $files['name'][ $i ] ),
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);
		$moved = wp_handle_upload( $single, array( 'test_form' => false ) );
		if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
			continue;
		}
		$att_id = wp_insert_attachment( array(
			'post_mime_type' => $moved['type'],
			'post_title'     => sanitize_text_field( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		), $moved['file'], $post_id );
		if ( $att_id && ! is_wp_error( $att_id ) ) {
			wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $moved['file'] ) );
			$ids[] = (int) $att_id;
		}
	}
	return $ids;
}

function ekinese_market_submit( WP_REST_Request $req ) {
	if ( ! empty( $req->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'ok' => true ) ); // honeypot
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$title = sanitize_text_field( (string) $req->get_param( 'title' ) );
	$desc  = sanitize_textarea_field( (string) $req->get_param( 'description' ) );
	$price = (float) $req->get_param( 'price' );
	$cat   = sanitize_key( (string) $req->get_param( 'category' ) );
	$cond  = sanitize_text_field( (string) $req->get_param( 'condition' ) );
	$dur   = max( 1, min( 30, (int) $req->get_param( 'duration' ) ) );
	$mode  = 'auction' === $req->get_param( 'mode' ) ? 'auction' : 'market';

	if ( ! is_email( $email ) || '' === $title || '' === $desc || $price <= 0 ) {
		return new WP_Error( 'invalid', 'Vul alle verplichte velden in.', array( 'status' => 400 ) );
	}
	// Geen contactgegevens (geen onderlinge communicatie).
	if ( ekinese_market_has_contact( $title . ' ' . $desc ) ) {
		return new WP_Error( 'contact', 'Contactgegevens (e-mail, telefoon of links) zijn niet toegestaan. Alle communicatie loopt via XGOUD.', array( 'status' => 400 ) );
	}

	$charity_pct = (float) get_option( 'xg_market_charity_pct', 5 );
	$admin       = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );

	if ( 'auction' === $mode ) {
		// Via XGOUD-veiling: eenmaal geplaatst blijft het in de veiling.
		$aid = wp_insert_post( array(
			'post_type'    => 'xg_auction',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $desc,
		) );
		if ( is_wp_error( $aid ) || ! $aid ) {
			return new WP_Error( 'save', 'Plaatsen mislukt.', array( 'status' => 500 ) );
		}
		$photos = ekinese_market_handle_photos( $aid );
		if ( $photos ) {
			set_post_thumbnail( $aid, $photos[0] );
		}
		update_post_meta( $aid, 'start_price', $price );
		update_post_meta( $aid, 'current_bid', '' );
		update_post_meta( $aid, 'bid_count', 0 );
		update_post_meta( $aid, 'ends', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $dur * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime
		update_post_meta( $aid, 'charity_pct', $charity_pct );
		update_post_meta( $aid, 'status', 'live' );
		update_post_meta( $aid, 'seller_email', $email ); // privé
		update_post_meta( $aid, '_user_submitted', '1' );

		if ( function_exists( 'ekinese_award_points' ) ) {
			ekinese_award_points( $email, 30, 'market_auction', 'auction#' . $aid );
		}
		if ( function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $email, 'Uw veiling staat live', sprintf( "Uw product '%s' wordt geveild via XGOUD. Een veiling kan niet worden teruggetrokken.", $title ), get_permalink( $aid ), 'info' );
		}
		wp_mail( $admin, 'Nieuwe gebruikersveiling: ' . $title, "Een gebruiker plaatste een veiling.\n" . $email );
		return rest_ensure_response( array( 'ok' => true, 'mode' => 'auction', 'url' => get_permalink( $aid ), 'message' => 'Uw veiling staat live. Een veiling kan niet worden teruggetrokken.' ) );
	}

	// Reguliere marktplaats-advertentie.
	$id = wp_insert_post( array(
		'post_type'    => 'xg_market',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $desc,
	) );
	if ( is_wp_error( $id ) || ! $id ) {
		return new WP_Error( 'save', 'Plaatsen mislukt.', array( 'status' => 500 ) );
	}
	$photos = ekinese_market_handle_photos( $id );
	if ( $photos ) {
		set_post_thumbnail( $id, $photos[0] );
		update_post_meta( $id, 'gallery', implode( ',', array_slice( $photos, 1 ) ) );
	}
	$norm = ekinese_market_normalize( $title );
	update_post_meta( $id, 'email', $email );
	update_post_meta( $id, 'price', $price );
	update_post_meta( $id, 'category', $cat );
	update_post_meta( $id, 'condition', $cond );
	update_post_meta( $id, 'status', 'active' );
	update_post_meta( $id, 'charity_pct', $charity_pct );
	update_post_meta( $id, 'expires', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $dur * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime
	update_post_meta( $id, 'token', wp_generate_password( 20, false, false ) );
	update_post_meta( $id, 'norm_title', $norm );

	if ( function_exists( 'ekinese_award_points' ) ) {
		ekinese_award_points( $email, 15, 'market_listing', 'market#' . $id );
	}
	ekinese_market_check_overlap( $id, $norm, $email );

	return rest_ensure_response( array( 'ok' => true, 'mode' => 'market', 'url' => get_permalink( $id ), 'message' => 'Uw advertentie staat online. U kunt deze altijd verwijderen vanuit Mijn XGOUD.' ) );
}

function ekinese_market_normalize( $title ) {
	return trim( preg_replace( '/\s+/', ' ', strtolower( preg_replace( '/[^a-z0-9 ]/i', '', remove_accents( (string) $title ) ) ) ) );
}

/** Signaleer mogelijke overlappingen (dubbele advertenties) in het admin. */
function ekinese_market_check_overlap( $id, $norm, $email ) {
	if ( '' === $norm ) {
		return;
	}
	$dups = get_posts( array(
		'post_type'   => 'xg_market',
		'post_status' => 'publish',
		'numberposts' => 5,
		'exclude'     => array( $id ),
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'norm_title', 'value' => $norm ),
			array( 'key' => 'status', 'value' => 'active' ),
		),
	) );
	if ( ! $dups ) {
		return;
	}
	update_post_meta( $id, '_overlap', '1' );
	$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
	$link  = admin_url( 'edit.php?post_type=xg_market' );
	wp_mail( $admin, 'Mogelijke overlap op de Marktplaats', sprintf( "Advertentie '%s' (#%d) lijkt sterk op %d bestaande advertentie(s). Controleer in het admin:\n%s", get_the_title( $id ), $id, count( $dups ), $link ) );
	if ( function_exists( 'ekinese_notify' ) ) {
		ekinese_notify( $admin, 'Mogelijke overlap op de Marktplaats', sprintf( "'%s' lijkt op %d bestaande advertentie(s).", get_the_title( $id ), count( $dups ) ), $link, 'warning' );
	}
}

/** Verwijderen — alleen door de eigenaar (account-token), alleen marktplaats. */
function ekinese_market_remove( WP_REST_Request $req ) {
	$email = function_exists( 'ekinese_account_verify_token' ) ? ekinese_account_verify_token( (string) $req->get_param( 'token' ) ) : '';
	$id    = (int) $req->get_param( 'id' );
	if ( ! $email || get_post_type( $id ) !== 'xg_market' ) {
		return new WP_Error( 'auth', 'Niet toegestaan.', array( 'status' => 403 ) );
	}
	if ( get_post_meta( $id, 'email', true ) !== $email ) {
		return new WP_Error( 'auth', 'Dit is niet uw advertentie.', array( 'status' => 403 ) );
	}
	update_post_meta( $id, 'status', 'removed' );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
	return rest_ensure_response( array( 'ok' => true ) );
}

/** Interesse — loopt uitsluitend via XGOUD; nooit verkopergegevens delen. */
function ekinese_market_interest( WP_REST_Request $req ) {
	if ( ! empty( $req->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	$id    = (int) $req->get_param( 'listing' );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
	if ( get_post_type( $id ) !== 'xg_market' || ! is_email( $email ) ) {
		return new WP_Error( 'invalid', 'Controleer uw gegevens.', array( 'status' => 400 ) );
	}
	$title  = get_the_title( $id );
	$seller = get_post_meta( $id, 'email', true );
	$admin  = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
	// XGOUD bemiddelt: koper en verkoper krijgen elk apart bericht, nooit elkaars gegevens.
	wp_mail( $admin, 'Marktplaats: interesse in ' . $title, sprintf( "Koper: %s <%s>\nVerkoper: %s\nAdvertentie: %s (#%d)\nXGOUD verwerkt de transactie.", $name, $email, $seller, $title, $id ) );
	wp_mail( $email, 'Uw reservering bij XGOUD', sprintf( "Bedankt voor uw interesse in '%s'. XGOUD neemt contact met u op om de aankoop en verzending te regelen.", $title ) );
	if ( function_exists( 'ekinese_notify' ) && is_email( $seller ) ) {
		ekinese_notify( $seller, 'Interesse in uw advertentie', sprintf( "Er is interesse in '%s'. XGOUD regelt de verkoop en neemt contact met u op.", $title ), get_permalink( $id ), 'info' );
	}
	if ( function_exists( 'ekinese_award_points' ) ) {
		ekinese_award_points( $email, 5, 'market_interest', 'market#' . $id );
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt! XGOUD neemt contact met u op om de aankoop veilig te regelen.' ) );
}

/* =====================================================================
   ADMIN – status, charity, verkocht
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_market_meta', __( 'Advertentie & charity', 'ekinese' ), 'ekinese_market_metabox', 'xg_market', 'side', 'high' );
} );

function ekinese_market_metabox( $post ) {
	wp_nonce_field( 'xg_market_save', 'xg_market_nonce' );
	$status = get_post_meta( $post->ID, 'status', true ) ?: 'active';
	$paid   = get_post_meta( $post->ID, 'paid', true );
	$pct    = get_post_meta( $post->ID, 'charity_pct', true );
	echo '<p><strong>Verkoper:</strong> ' . esc_html( get_post_meta( $post->ID, 'email', true ) ) . '</p>';
	echo '<p><strong>Prijs:</strong> ' . esc_html( ekinese_market_eur( get_post_meta( $post->ID, 'price', true ) ) ) . '</p>';
	if ( get_post_meta( $post->ID, '_overlap', true ) ) {
		echo '<p style="color:#b32d2e"><strong>⚠ Mogelijke overlap met een bestaande advertentie.</strong></p>';
	}
	echo '<p><label>Status<br><select name="xg_market_status">';
	foreach ( array( 'active' => 'Actief', 'sold' => 'Verkocht', 'removed' => 'Verwijderd' ) as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p><label>Charity %<br><input type="number" name="xg_market_charity_pct" step="0.5" min="0" max="100" value="' . esc_attr( $pct ) . '" class="small-text"></label></p>';
	echo '<p><label><input type="checkbox" name="xg_market_paid" value="1" ' . checked( $paid, '1', false ) . '> Betaling ontvangen → charity vrijgeven</label></p>';
}

function ekinese_market_save( $post_id ) {
	if ( ! isset( $_POST['xg_market_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_market_nonce'] ), 'xg_market_save' ) ) {
		return;
	}
	if ( isset( $_POST['xg_market_status'] ) ) {
		update_post_meta( $post_id, 'status', sanitize_key( wp_unslash( $_POST['xg_market_status'] ) ) );
	}
	if ( isset( $_POST['xg_market_charity_pct'] ) ) {
		update_post_meta( $post_id, 'charity_pct', (float) $_POST['xg_market_charity_pct'] );
	}
	$paid = ! empty( $_POST['xg_market_paid'] );
	$was  = get_post_meta( $post_id, 'paid', true ) === '1';
	update_post_meta( $post_id, 'paid', $paid ? '1' : '' );

	// Verkocht + betaald → charity vrijgeven + punten voor de verkoper.
	if ( $paid && ! $was && get_post_meta( $post_id, 'status', true ) === 'sold' ) {
		$price = (float) get_post_meta( $post_id, 'price', true );
		$pct   = (float) get_post_meta( $post_id, 'charity_pct', true );
		if ( $pct > 0 && $price > 0 ) {
			update_post_meta( $post_id, 'charity_amount', round( $price * $pct / 100, 2 ) );
		}
		update_post_meta( $post_id, 'charity_released', '1' );
		$seller = get_post_meta( $post_id, 'email', true );
		if ( is_email( $seller ) && function_exists( 'ekinese_award_points' ) ) {
			ekinese_award_points( $seller, 50 + (int) floor( $price / 100 ) * 5, 'market_sold', 'market#' . $post_id );
		}
		if ( is_email( $seller ) && function_exists( 'ekinese_notify' ) ) {
			ekinese_notify( $seller, 'Uw product is verkocht', sprintf( "'%s' is verkocht via de Marktplaats. U ontvangt de uitbetaling van XGOUD.", get_the_title( $post_id ) ), '', 'won' );
		}
	}
}
add_action( 'save_post_xg_market', 'ekinese_market_save', 20 );

/* Charity: verkochte + vrijgegeven advertenties tellen mee in het projecttotaal. */
add_filter( 'ekinese_charity_accrued_extra', function ( $extra, $project_id, $title ) {
	$ids = get_posts( array(
		'post_type'   => 'xg_market',
		'post_status' => array( 'publish', 'draft' ),
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'charity_released', 'value' => '1' ),
		),
	) );
	$sum = 0;
	foreach ( $ids as $mid ) {
		$sum += (float) get_post_meta( $mid, 'charity_amount', true );
	}
	return $extra + $sum;
}, 10, 3 );

/* =====================================================================
   ACCOUNT – "Mijn advertenties" (eigen listings, verwijderbaar)
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$ids = get_posts( array(
		'post_type'   => 'xg_market',
		'post_status' => array( 'publish', 'draft' ),
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'email', 'value' => sanitize_email( $email ) ) ),
	) );
	$rows = array();
	$labels = array( 'active' => 'Actief', 'sold' => 'Verkocht', 'removed' => 'Verwijderd' );
	foreach ( $ids as $id ) {
		$st = get_post_meta( $id, 'status', true ) ?: 'active';
		$rows[] = array(
			'id'     => $id,
			'title'  => get_the_title( $id ),
			'price'  => ekinese_market_eur( get_post_meta( $id, 'price', true ) ),
			'status' => $labels[ $st ] ?? $st,
			'removable' => ( 'active' === $st ) ? 1 : 0,
		);
	}
	if ( $rows ) {
		$data['listings'] = $rows;
	}
	return $data;
}, 13, 2 );
