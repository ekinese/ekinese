<?php
/**
 * XGOUD "Momenten" — Share your moments.
 *
 * Een Instagram-achtige UGC-wall: klanten delen een foto + korte tekst over hun
 * ervaring/aankoop. Inzendingen komen als 'pending' binnen en verschijnen pas op
 * de homepage na goedkeuring in het backend (anti-spam). Bij goedkeuring krijgt
 * de klant spaarpunten.
 *
 * Privacy/regel: NUL onderlinge communicatie. Alleen voornaam + (optioneel) stad,
 * geen contactgegevens, geen reacties tussen gebruikers. Een anonieme ❤-teller
 * is puur cosmetisch (geen identiteit, geen data-uitwisseling).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT  xg_moment
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_moment', array(
		'labels'       => array(
			'name'          => 'Momenten',
			'singular_name' => 'Moment',
			'menu_name'     => 'Momenten',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'xgoud',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'menu_icon'    => 'dashicons-format-gallery',
	) );
} );

/** Puntenwaarde voor een goedgekeurd moment. */
function ekinese_moment_points() {
	$rules = function_exists( 'ekinese_reward_rules' ) ? ekinese_reward_rules() : array();
	return isset( $rules['moment'] ) ? (int) $rules['moment'] : 20;
}
add_filter( 'ekinese_reward_rules', function ( $r ) {
	if ( ! isset( $r['moment'] ) ) {
		$r['moment'] = 20;
	}
	return $r;
} );

/** Toegestane typen. */
function ekinese_moment_types() {
	return array(
		'ervaring' => 'Mijn ervaring',
		'aankoop'  => 'Mijn aankoop',
		'anders'   => 'Iets anders',
	);
}

/* =====================================================================
   REST
===================================================================== */
add_action( 'rest_api_init', function () {
	// Publieke feed (goedgekeurde momenten) voor de wall.
	register_rest_route( 'ekinese/v1', '/moments', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_moments_feed',
	) );
	// Inzenden (foto + tekst). E-mail vereist; komt als 'pending' binnen.
	register_rest_route( 'ekinese/v1', '/moments/submit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_moments_submit',
	) );
	// Anonieme ❤ (cosmetisch, dedup gebeurt client-side via localStorage).
	register_rest_route( 'ekinese/v1', '/moments/like', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_moments_like',
	) );
} );

/** Eén moment → publieke (veilige) representatie. */
function ekinese_moment_public( $post ) {
	$thumb = get_the_post_thumbnail_url( $post, 'large' );
	if ( ! $thumb ) {
		return null;
	}
	$types = ekinese_moment_types();
	$type  = (string) get_post_meta( $post->ID, 'type', true );
	return array(
		'id'    => $post->ID,
		'name'  => (string) ( get_post_meta( $post->ID, 'display_name', true ) ?: 'Klant' ),
		'city'  => (string) get_post_meta( $post->ID, 'city', true ),
		'text'  => wp_strip_all_tags( $post->post_content ),
		'type'  => isset( $types[ $type ] ) ? $types[ $type ] : '',
		'photo' => $thumb,
		'likes' => (int) get_post_meta( $post->ID, 'likes', true ),
		'date'  => get_the_date( 'j M Y', $post ),
	);
}

function ekinese_moments_feed( WP_REST_Request $req ) {
	$page = max( 1, (int) $req->get_param( 'page' ) );
	$per  = min( 24, max( 1, (int) ( $req->get_param( 'per' ) ?: 12 ) ) );
	$q    = new WP_Query( array(
		'post_type'      => 'xg_moment',
		'post_status'    => 'publish',
		'posts_per_page' => $per,
		'paged'          => $page,
		'meta_key'       => '_thumbnail_id',
	) );
	$out = array();
	foreach ( $q->posts as $p ) {
		$pub = ekinese_moment_public( $p );
		if ( $pub ) {
			$out[] = $pub;
		}
	}
	return rest_ensure_response( array(
		'moments' => $out,
		'page'    => $page,
		'pages'   => (int) $q->max_num_pages,
	) );
}

function ekinese_moments_submit( WP_REST_Request $req ) {
	if ( ! empty( $req->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'ok' => true ) ); // honeypot
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$text  = sanitize_textarea_field( (string) $req->get_param( 'text' ) );
	$type  = sanitize_key( (string) $req->get_param( 'type' ) );
	$city  = sanitize_text_field( (string) $req->get_param( 'city' ) );
	$token = (string) $req->get_param( 'token' );

	// E-mail via account-token (indien aanwezig) verifiëren, anders het veld.
	if ( $token && function_exists( 'ekinese_account_verify_token' ) ) {
		$tok_email = ekinese_account_verify_token( $token );
		if ( $tok_email ) {
			$email = $tok_email;
		}
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid', 'Geldig e-mailadres vereist.', array( 'status' => 400 ) );
	}
	if ( '' === $text ) {
		return new WP_Error( 'invalid', 'Schrijf kort iets bij je moment.', array( 'status' => 400 ) );
	}
	if ( ! array_key_exists( $type, ekinese_moment_types() ) ) {
		$type = 'ervaring';
	}
	// Geen contactgegevens (geen onderlinge communicatie).
	if ( function_exists( 'ekinese_market_has_contact' ) && ekinese_market_has_contact( $text . ' ' . $city ) ) {
		return new WP_Error( 'contact', 'Contactgegevens (e-mail, telefoon of links) zijn niet toegestaan.', array( 'status' => 400 ) );
	}
	// Rate-limit: max 3 inzendingen per e-mail per dag.
	$flag = 'xg_moment_' . md5( $email . gmdate( 'Ymd' ) );
	$cnt  = (int) get_transient( $flag );
	if ( $cnt >= 3 ) {
		return new WP_Error( 'limit', 'Je hebt vandaag het maximum aan momenten bereikt.', array( 'status' => 429 ) );
	}

	// Voornaam afleiden (geen volledige identiteit tonen).
	$display = (string) $req->get_param( 'name' );
	$display = $display ? sanitize_text_field( $display ) : trim( (string) strtok( $email, '@' ) );
	$display = ucfirst( preg_replace( '/[^\p{L}\- ]/u', '', $display ) );
	$first   = trim( (string) strtok( $display, ' ' ) );

	$post_id = wp_insert_post( array(
		'post_type'    => 'xg_moment',
		'post_status'  => 'pending', // wacht op moderatie
		'post_title'   => 'Moment · ' . $first . ' · ' . gmdate( 'Y-m-d H:i' ),
		'post_content' => $text,
	), true );
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save', 'Opslaan mislukt.', array( 'status' => 500 ) );
	}
	update_post_meta( $post_id, 'email', $email );
	update_post_meta( $post_id, 'display_name', $first );
	update_post_meta( $post_id, 'type', $type );
	if ( $city ) {
		update_post_meta( $post_id, 'city', $city );
	}

	// Foto verwerken (1 stuk; hergebruik de veilige market-uploadlogica indien er
	// meerdere binnenkomen — we pakken de eerste).
	$photo_id = 0;
	if ( ! empty( $_FILES['photo'] ) && function_exists( 'ekinese_moments_handle_photo' ) ) {
		$photo_id = ekinese_moments_handle_photo( $post_id );
	}
	if ( ! $photo_id ) {
		wp_delete_post( $post_id, true );
		return new WP_Error( 'photo', 'Voeg een foto toe — een moment is een beeld.', array( 'status' => 400 ) );
	}
	set_post_thumbnail( $post_id, $photo_id );

	set_transient( $flag, $cnt + 1, DAY_IN_SECONDS );

	// Beheerder seinen (interne notificatie naar het admin-adres).
	if ( function_exists( 'ekinese_notify' ) ) {
		$admin = function_exists( 'ekinese_business' ) ? ekinese_business()['email'] : get_option( 'admin_email' );
		ekinese_notify( $admin, 'Nieuw moment ter moderatie', 'Een klant deelde een moment. Beoordeel het in Momenten.', admin_url( 'edit.php?post_status=pending&post_type=xg_moment' ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => 'Bedankt! Je moment wordt beoordeeld en verschijnt daarna op de homepage.' ) );
}

/** Verwerk één geüploade foto → attachment-ID. */
function ekinese_moments_handle_photo( $post_id ) {
	$key = ! empty( $_FILES['photo'] ) ? 'photo' : '';
	if ( ! $key || empty( $_FILES[ $key ]['name'] ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$allowed = array( 'image/jpeg', 'image/png', 'image/webp' );
	$f       = $_FILES[ $key ]; // phpcs:ignore WordPress.Security
	$name    = sanitize_file_name( (string) $f['name'] );
	$ft      = wp_check_filetype( $name );
	if ( ! in_array( (string) $ft['type'], $allowed, true ) ) {
		return 0;
	}
	$moved = wp_handle_upload( array(
		'name'     => $name,
		'type'     => $f['type'],
		'tmp_name' => $f['tmp_name'],
		'error'    => $f['error'],
		'size'     => $f['size'],
	), array( 'test_form' => false ) );
	if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
		return 0;
	}
	$att_id = wp_insert_attachment( array(
		'post_mime_type' => $moved['type'],
		'post_title'     => sanitize_text_field( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
		'post_status'    => 'inherit',
		'post_parent'    => $post_id,
	), $moved['file'], $post_id );
	if ( ! $att_id || is_wp_error( $att_id ) ) {
		return 0;
	}
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $moved['file'] ) );
	return (int) $att_id;
}

/** Anonieme ❤ (cosmetisch). */
function ekinese_moments_like( WP_REST_Request $req ) {
	$id = (int) $req->get_param( 'id' );
	if ( get_post_type( $id ) !== 'xg_moment' || get_post_status( $id ) !== 'publish' ) {
		return new WP_Error( 'invalid', 'Onbekend moment.', array( 'status' => 400 ) );
	}
	$likes = (int) get_post_meta( $id, 'likes', true ) + 1;
	update_post_meta( $id, 'likes', $likes );
	return rest_ensure_response( array( 'ok' => true, 'likes' => $likes ) );
}

/* =====================================================================
   PUNTEN bij goedkeuring (status → publish)
===================================================================== */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( ! $post || 'xg_moment' !== $post->post_type ) {
		return;
	}
	if ( 'publish' === $new && 'publish' !== $old ) {
		if ( get_post_meta( $post->ID, 'points_awarded', true ) ) {
			return;
		}
		$email = (string) get_post_meta( $post->ID, 'email', true );
		if ( $email && function_exists( 'ekinese_award_points' ) ) {
			ekinese_award_points( $email, ekinese_moment_points(), 'moment', 'moment#' . $post->ID );
			update_post_meta( $post->ID, 'points_awarded', 1 );
			if ( function_exists( 'ekinese_notify' ) ) {
				ekinese_notify( $email, 'Je moment staat online! 🎉', 'Bedankt voor het delen — je ontvangt ' . ekinese_moment_points() . ' spaarpunten.' );
			}
		}
	}
}, 10, 3 );

/* =====================================================================
   BLOK  ekinese/moments  (publieke wall)
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/moments', array(
		'attributes'      => array(
			'title' => array( 'type' => 'string', 'default' => 'Gedeeld door onze klanten' ),
			'limit' => array( 'type' => 'number', 'default' => 8 ),
		),
		'render_callback' => 'ekinese_render_moments',
	) );
} );

function ekinese_render_moments( $attrs ) {
	$title = isset( $attrs['title'] ) ? $attrs['title'] : 'Gedeeld door onze klanten';
	$limit = isset( $attrs['limit'] ) ? (int) $attrs['limit'] : 8;
	$rest  = esc_url_raw( rest_url( 'ekinese/v1/moments' ) );
	$like  = esc_url_raw( rest_url( 'ekinese/v1/moments/like' ) );
	return '<section class="xg-moments" data-rest="' . esc_attr( $rest ) . '" data-like="' . esc_attr( $like ) . '" data-limit="' . esc_attr( $limit ) . '">'
		. '<div class="xg-container"><div class="xg-moments-head"><h2>' . esc_html( $title ) . '</h2>'
		. '<a class="xg-moments-cta" href="/momenten/">Deel jouw moment →</a></div>'
		. '<div class="xg-moments-grid" aria-live="polite"></div>'
		. '<div class="xg-moments-more"></div></div></section>';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() && ! is_front_page() && ! is_home() ) {
		return;
	}
	if ( ! ekinese_moments_present() ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/moments.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-moments', get_theme_file_uri( 'assets/css/moments.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/moments.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-moments', get_theme_file_uri( 'assets/js/moments.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/** Staat er een moments-blok of het inzendformulier op deze pagina? */
function ekinese_moments_present() {
	if ( is_front_page() || is_home() ) {
		return true; // homepage toont de wall
	}
	$post = get_post();
	if ( ! $post ) {
		return false;
	}
	return has_block( 'ekinese/moments', $post ) || has_block( 'ekinese/moment-form', $post )
		|| false !== strpos( (string) $post->post_content, 'xg-moment-form' );
}

/* =====================================================================
   BLOK  ekinese/moment-form  (inzending; werkt met account-token)
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/moment-form', array( 'render_callback' => 'ekinese_render_moment_form' ) );
} );

function ekinese_render_moment_form() {
	$rest  = esc_url_raw( rest_url( 'ekinese/v1/moments/submit' ) );
	$types = ekinese_moment_types();
	$opts  = '';
	foreach ( $types as $k => $label ) {
		$opts .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
	}
	return '<section class="xg-moment-form" data-rest="' . esc_attr( $rest ) . '">'
		. '<div class="xg-container"><h2>Deel jouw moment</h2>'
		. '<p class="xg-mf-intro">Een foto van je aankoop of ervaring + een korte tekst. Na goedkeuring verschijnt het op onze homepage en ontvang je spaarpunten.</p>'
		. '<form class="xg-mf-form" enctype="multipart/form-data">'
		. '<input type="text" name="website" class="xg-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
		. '<div class="xg-mf-row"><input type="email" name="email" placeholder="Je e-mailadres" required></div>'
		. '<div class="xg-mf-row"><input type="text" name="name" placeholder="Je voornaam (optioneel)"><input type="text" name="city" placeholder="Plaats (optioneel)"></div>'
		. '<div class="xg-mf-row"><select name="type">' . $opts . '</select></div>'
		. '<textarea name="text" rows="3" placeholder="Vertel kort over je moment…" required></textarea>'
		. '<label class="xg-mf-file"><span>Kies een foto</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>'
		. '<button type="submit">Plaats mijn moment</button><span class="xg-mf-msg" role="status"></span>'
		. '</form></div></section>';
}

/* =====================================================================
   ACCOUNT-INTEGRATIE  (eigen momenten in Mijn XGOUD)
===================================================================== */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$posts = get_posts( array(
		'post_type'      => 'xg_moment',
		'post_status'    => array( 'pending', 'publish', 'draft' ),
		'posts_per_page' => 20,
		'meta_key'       => 'email',
		'meta_value'     => $email,
	) );
	$status_label = array( 'pending' => 'In behandeling', 'publish' => 'Online', 'draft' => 'Afgewezen' );
	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'     => $p->ID,
			'text'   => wp_trim_words( wp_strip_all_tags( $p->post_content ), 14 ),
			'photo'  => get_the_post_thumbnail_url( $p, 'thumbnail' ),
			'status' => $status_label[ $p->post_status ] ?? $p->post_status,
			'likes'  => (int) get_post_meta( $p->ID, 'likes', true ),
		);
	}
	$data['moments']        = $out;
	$data['moments_submit'] = esc_url_raw( rest_url( 'ekinese/v1/moments/submit' ) );
	return $data;
}, 16, 2 );

/* =====================================================================
   ADMIN: moderatie-kolommen, snelle goedkeuring, KPI + daily task
===================================================================== */
add_filter( 'manage_xg_moment_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['xg_photo'] = 'Foto';
			$new['xg_type']  = 'Type';
			$new['xg_name']  = 'Naam';
		}
	}
	return $new;
} );
add_action( 'manage_xg_moment_posts_custom_column', function ( $col, $post_id ) {
	if ( 'xg_photo' === $col ) {
		$t = get_the_post_thumbnail( $post_id, array( 60, 60 ) );
		echo $t ? $t : '—'; // phpcs:ignore
	} elseif ( 'xg_type' === $col ) {
		$types = ekinese_moment_types();
		$t     = (string) get_post_meta( $post_id, 'type', true );
		echo esc_html( $types[ $t ] ?? $t );
	} elseif ( 'xg_name' === $col ) {
		echo esc_html( (string) get_post_meta( $post_id, 'display_name', true ) );
	}
}, 10, 2 );

/** KPI + daily task: momenten in moderatie. */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$pending = (int) wp_count_posts( 'xg_moment' )->pending;
	if ( $pending > 0 ) {
		$tasks[] = array(
			'key'   => 'moments',
			'label' => 'Momenten modereren (homepage-wall)',
			'count' => $pending,
			'link'  => admin_url( 'edit.php?post_status=pending&post_type=xg_moment' ),
		);
	}
	return $tasks;
} );
