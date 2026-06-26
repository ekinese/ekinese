<?php
/**
 * XGOUD Social Insights.
 *
 * Einheitliches Insights-Dashboard für Instagram, Facebook, YouTube, LinkedIn,
 * TikTok und X/Twitter: Wie laufen Posts, gibt es (unbeantwortete) Kommentare,
 * welche Inhalte kommen an? Die Daten fließen in die Statistiken, die täglichen
 * Aufgaben und den AI-Kontext des Backend-Dashboards.
 *
 * Realität: Jede Plattform hat eine eigene, teils stark eingeschränkte API.
 * YouTube/Facebook/Instagram sind mit Token gut abrufbar; LinkedIn/TikTok/X
 * erfordern App-Review bzw. bezahlte Tiers — diese Konnektoren sind sauber
 * vorbereitet und laufen, sobald ein Token hinterlegt ist (sonst „nicht
 * verbunden"). Jeder Abruf ist defensiv: ein Fehler liefert leere Daten, nie
 * einen Fatal.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plattformen + Konnektor-Callback + benötigte Optionsfelder. */
function ekinese_si_platforms() {
	return array(
		'youtube'   => array( 'label' => 'YouTube',   'fetch' => 'ekinese_si_youtube',   'fields' => array( 'key' => 'API-key', 'channel' => 'Channel-ID' ) ),
		'facebook'  => array( 'label' => 'Facebook',  'fetch' => 'ekinese_si_facebook',  'fields' => array( 'token' => 'Page access token', 'pageid' => 'Page-ID' ) ),
		'instagram' => array( 'label' => 'Instagram', 'fetch' => 'ekinese_si_instagram', 'fields' => array( 'token' => 'Access token', 'userid' => 'IG-Business-User-ID' ) ),
		'linkedin'  => array( 'label' => 'LinkedIn',  'fetch' => 'ekinese_si_linkedin',  'fields' => array( 'token' => 'Access token', 'org' => 'Organisation-URN' ) ),
		'tiktok'    => array( 'label' => 'TikTok',    'fetch' => 'ekinese_si_tiktok',    'fields' => array( 'token' => 'Access token' ) ),
		'twitter'   => array( 'label' => 'X / Twitter', 'fetch' => 'ekinese_si_twitter', 'fields' => array( 'token' => 'Bearer token', 'userid' => 'User-ID' ) ),
	);
}

function ekinese_si_opt( $platform, $field ) {
	return trim( (string) get_option( 'xg_si_' . $platform . '_' . $field, '' ) );
}

/** Defensiver GET → JSON-array (oder leeres array). */
function ekinese_si_get( $url, $headers = array() ) {
	$res = wp_remote_get( $url, array( 'timeout' => 15, 'headers' => $headers ) );
	if ( is_wp_error( $res ) ) {
		return array( '_error' => $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 ) {
		$msg = is_array( $body ) ? ( $body['error']['message'] ?? $body['error'] ?? ( 'HTTP ' . $code ) ) : ( 'HTTP ' . $code );
		return array( '_error' => is_string( $msg ) ? $msg : ( 'HTTP ' . $code ) );
	}
	return is_array( $body ) ? $body : array();
}

/** Lege/normalisierte Struktur. */
function ekinese_si_empty( $extra = array() ) {
	return array_merge( array( 'connected' => false, 'followers' => 0, 'posts' => array(), 'unanswered' => 0, 'error' => '' ), $extra );
}

/* =====================================================================
   KONNEKTOREN
===================================================================== */
function ekinese_si_youtube() {
	$key = ekinese_si_opt( 'youtube', 'key' );
	$ch  = ekinese_si_opt( 'youtube', 'channel' );
	if ( ! $key || ! $ch ) {
		return ekinese_si_empty();
	}
	$out = ekinese_si_empty( array( 'connected' => true ) );
	$c   = ekinese_si_get( 'https://www.googleapis.com/youtube/v3/channels?part=statistics&id=' . rawurlencode( $ch ) . '&key=' . rawurlencode( $key ) );
	if ( ! empty( $c['_error'] ) ) {
		return ekinese_si_empty( array( 'error' => $c['_error'] ) );
	}
	$out['followers'] = (int) ( $c['items'][0]['statistics']['subscriberCount'] ?? 0 );
	$s   = ekinese_si_get( 'https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=' . rawurlencode( $ch ) . '&order=date&type=video&maxResults=5&key=' . rawurlencode( $key ) );
	$ids = array();
	foreach ( (array) ( $s['items'] ?? array() ) as $it ) {
		if ( ! empty( $it['id']['videoId'] ) ) {
			$ids[] = $it['id']['videoId'];
		}
	}
	if ( $ids ) {
		$v = ekinese_si_get( 'https://www.googleapis.com/youtube/v3/videos?part=statistics,snippet&id=' . implode( ',', $ids ) . '&key=' . rawurlencode( $key ) );
		foreach ( (array) ( $v['items'] ?? array() ) as $vid ) {
			$st = $vid['statistics'] ?? array();
			$out['posts'][] = array(
				'title'    => $vid['snippet']['title'] ?? '',
				'date'     => substr( (string) ( $vid['snippet']['publishedAt'] ?? '' ), 0, 10 ),
				'views'    => (int) ( $st['viewCount'] ?? 0 ),
				'likes'    => (int) ( $st['likeCount'] ?? 0 ),
				'comments' => (int) ( $st['commentCount'] ?? 0 ),
				'url'      => 'https://youtu.be/' . ( $vid['id'] ?? '' ),
			);
		}
	}
	// Unbeantwortete Kommentare (best effort; benötigt evtl. OAuth — sonst übersprungen).
	$ct = ekinese_si_get( 'https://www.googleapis.com/youtube/v3/commentThreads?part=snippet&allThreadsRelatedToChannelId=' . rawurlencode( $ch ) . '&order=time&maxResults=20&key=' . rawurlencode( $key ) );
	if ( empty( $ct['_error'] ) ) {
		foreach ( (array) ( $ct['items'] ?? array() ) as $t ) {
			if ( 0 === (int) ( $t['snippet']['totalReplyCount'] ?? 0 ) ) {
				$out['unanswered']++;
			}
		}
	}
	return $out;
}

function ekinese_si_facebook() {
	$token = ekinese_si_opt( 'facebook', 'token' );
	$pid   = ekinese_si_opt( 'facebook', 'pageid' );
	if ( ! $token || ! $pid ) {
		return ekinese_si_empty();
	}
	$base = 'https://graph.facebook.com/v19.0/';
	$out  = ekinese_si_empty( array( 'connected' => true ) );
	$p    = ekinese_si_get( $base . rawurlencode( $pid ) . '?fields=fan_count,followers_count&access_token=' . rawurlencode( $token ) );
	if ( ! empty( $p['_error'] ) ) {
		return ekinese_si_empty( array( 'error' => $p['_error'] ) );
	}
	$out['followers'] = (int) ( $p['followers_count'] ?? $p['fan_count'] ?? 0 );
	$posts = ekinese_si_get( $base . rawurlencode( $pid ) . '/posts?fields=message,created_time,permalink_url,likes.summary(true),comments.summary(true)&limit=5&access_token=' . rawurlencode( $token ) );
	foreach ( (array) ( $posts['data'] ?? array() ) as $po ) {
		$comments = (int) ( $po['comments']['summary']['total_count'] ?? 0 );
		$out['posts'][] = array(
			'title'    => wp_trim_words( (string) ( $po['message'] ?? '(geen tekst)' ), 10 ),
			'date'     => substr( (string) ( $po['created_time'] ?? '' ), 0, 10 ),
			'views'    => 0,
			'likes'    => (int) ( $po['likes']['summary']['total_count'] ?? 0 ),
			'comments' => $comments,
			'url'      => $po['permalink_url'] ?? '',
		);
		$out['unanswered'] += $comments; // grove indicatie (controleer in Meta Business Suite)
	}
	return $out;
}

function ekinese_si_instagram() {
	$token = ekinese_si_opt( 'instagram', 'token' );
	$uid   = ekinese_si_opt( 'instagram', 'userid' );
	if ( ! $token || ! $uid ) {
		return ekinese_si_empty();
	}
	$base = 'https://graph.facebook.com/v19.0/';
	$out  = ekinese_si_empty( array( 'connected' => true ) );
	$p    = ekinese_si_get( $base . rawurlencode( $uid ) . '?fields=followers_count&access_token=' . rawurlencode( $token ) );
	if ( ! empty( $p['_error'] ) ) {
		return ekinese_si_empty( array( 'error' => $p['_error'] ) );
	}
	$out['followers'] = (int) ( $p['followers_count'] ?? 0 );
	$media = ekinese_si_get( $base . rawurlencode( $uid ) . '/media?fields=caption,timestamp,permalink,like_count,comments_count&limit=5&access_token=' . rawurlencode( $token ) );
	foreach ( (array) ( $media['data'] ?? array() ) as $m ) {
		$comments = (int) ( $m['comments_count'] ?? 0 );
		$out['posts'][] = array(
			'title'    => wp_trim_words( (string) ( $m['caption'] ?? '(geen tekst)' ), 10 ),
			'date'     => substr( (string) ( $m['timestamp'] ?? '' ), 0, 10 ),
			'views'    => 0,
			'likes'    => (int) ( $m['like_count'] ?? 0 ),
			'comments' => $comments,
			'url'      => $m['permalink'] ?? '',
		);
		$out['unanswered'] += $comments;
	}
	return $out;
}

function ekinese_si_linkedin() {
	$token = ekinese_si_opt( 'linkedin', 'token' );
	$org   = ekinese_si_opt( 'linkedin', 'org' );
	if ( ! $token || ! $org ) {
		return ekinese_si_empty();
	}
	// LinkedIn Marketing API vereist app-review; basis-volgers ophalen indien toegestaan.
	$h = array( 'Authorization' => 'Bearer ' . $token, 'X-Restli-Protocol-Version' => '2.0.0' );
	$f = ekinese_si_get( 'https://api.linkedin.com/v2/networkSizes/' . rawurlencode( $org ) . '?edgeType=CompanyFollowedByMember', $h );
	$out = ekinese_si_empty( array( 'connected' => true ) );
	if ( ! empty( $f['_error'] ) ) {
		$out['error'] = 'LinkedIn beperkt — app-review vereist (' . $f['_error'] . ').';
	} else {
		$out['followers'] = (int) ( $f['firstDegreeSize'] ?? 0 );
	}
	return $out;
}

function ekinese_si_tiktok() {
	$token = ekinese_si_opt( 'tiktok', 'token' );
	if ( ! $token ) {
		return ekinese_si_empty();
	}
	$h = array( 'Authorization' => 'Bearer ' . $token );
	$f = ekinese_si_get( 'https://open.tiktokapis.com/v2/user/info/?fields=follower_count,likes_count,video_count', $h );
	$out = ekinese_si_empty( array( 'connected' => true ) );
	if ( ! empty( $f['_error'] ) ) {
		$out['error'] = 'TikTok beperkt — app-review/Business-API vereist (' . $f['_error'] . ').';
	} else {
		$out['followers'] = (int) ( $f['data']['user']['follower_count'] ?? 0 );
	}
	return $out;
}

function ekinese_si_twitter() {
	$token = ekinese_si_opt( 'twitter', 'token' );
	$uid   = ekinese_si_opt( 'twitter', 'userid' );
	if ( ! $token || ! $uid ) {
		return ekinese_si_empty();
	}
	$h   = array( 'Authorization' => 'Bearer ' . $token );
	$f   = ekinese_si_get( 'https://api.twitter.com/2/users/' . rawurlencode( $uid ) . '?user.fields=public_metrics', $h );
	$out = ekinese_si_empty( array( 'connected' => true ) );
	if ( ! empty( $f['_error'] ) ) {
		$out['error'] = 'X/Twitter beperkt — betaalde API-tier vereist (' . $f['_error'] . ').';
	} else {
		$out['followers'] = (int) ( $f['data']['public_metrics']['followers_count'] ?? 0 );
	}
	return $out;
}

/* =====================================================================
   AGGREGATIE + CACHE + CRON
===================================================================== */
function ekinese_si_fetch_all( $force = false ) {
	if ( ! $force ) {
		$cache = get_option( 'xg_si_cache', array() );
		if ( ! empty( $cache['data'] ) ) {
			return $cache['data'];
		}
	}
	$data = array();
	foreach ( ekinese_si_platforms() as $key => $cfg ) {
		$data[ $key ] = is_callable( $cfg['fetch'] ) ? call_user_func( $cfg['fetch'] ) : ekinese_si_empty();
	}
	update_option( 'xg_si_cache', array( 'time' => current_time( 'mysql' ), 'data' => $data ), false );
	return $data;
}

add_action( 'xg_si_refresh', function () { ekinese_si_fetch_all( true ); } );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'xg_si_refresh' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'xg_si_refresh' );
	}
} );

/** Totaal onbeantwoorde reacties over alle platforms. */
function ekinese_si_unanswered_total( $data = null ) {
	$data = null === $data ? ekinese_si_fetch_all() : $data;
	$n = 0;
	foreach ( $data as $d ) {
		$n += (int) ( $d['unanswered'] ?? 0 );
	}
	return $n;
}

/* =====================================================================
   STATISTIEK-ANBINDUNG: daily tasks + AI-context
===================================================================== */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$data = ekinese_si_fetch_all();
	foreach ( $data as $key => $d ) {
		if ( ! empty( $d['connected'] ) && (int) ( $d['unanswered'] ?? 0 ) > 0 ) {
			$labels = ekinese_si_platforms();
			$tasks[] = array(
				'key'   => 'si_' . $key,
				'label' => 'Reacties beantwoorden op ' . ( $labels[ $key ]['label'] ?? $key ),
				'count' => (int) $d['unanswered'],
				'link'  => admin_url( 'admin.php?page=xg-social-insights' ),
			);
		}
	}
	return $tasks;
} );

add_filter( 'ekinese_dashboard_ai_context_lines', function ( $lines ) {
	$data   = ekinese_si_fetch_all();
	$labels = ekinese_si_platforms();
	foreach ( $data as $key => $d ) {
		if ( empty( $d['connected'] ) || empty( $d['posts'] ) ) {
			continue;
		}
		// Beste post (op engagement) als signaal voor "wat komt aan".
		$best = null;
		foreach ( $d['posts'] as $p ) {
			$eng = (int) $p['likes'] + (int) $p['comments'] + (int) $p['views'] / 100;
			if ( ! $best || $eng > $best['eng'] ) {
				$best = array( 'eng' => $eng, 'p' => $p );
			}
		}
		if ( $best ) {
			$lines[] = sprintf(
				'%s: %d volgers, beste recente post "%s" (%d likes, %d reacties%s).',
				$labels[ $key ]['label'] ?? $key,
				(int) $d['followers'],
				wp_trim_words( (string) $best['p']['title'], 8 ),
				(int) $best['p']['likes'],
				(int) $best['p']['comments'],
				$best['p']['views'] ? ', ' . (int) $best['p']['views'] . ' weergaven' : ''
			);
		}
	}
	return $lines;
} );

/* =====================================================================
   ADMIN: instellingen + insights-overzicht
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Social insights', 'Social insights', 'manage_options', 'xg-social-insights', 'ekinese_si_page' );
}, 48 );

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['xg_si_save'] ) && check_admin_referer( 'xg_si_save' ) ) {
		foreach ( ekinese_si_platforms() as $key => $cfg ) {
			foreach ( array_keys( $cfg['fields'] ) as $f ) {
				$opt = 'xg_si_' . $key . '_' . $f;
				if ( isset( $_POST[ $opt ] ) ) {
					update_option( $opt, sanitize_text_field( wp_unslash( $_POST[ $opt ] ) ) );
				}
			}
		}
		add_settings_error( 'xg_si', 'saved', 'Opgeslagen.', 'success' );
	}
	if ( isset( $_GET['xg_si_refresh'] ) && check_admin_referer( 'xg_si_refresh' ) ) {
		ekinese_si_fetch_all( true );
		wp_safe_redirect( admin_url( 'admin.php?page=xg-social-insights&refreshed=1' ) );
		exit;
	}
} );

function ekinese_si_page() {
	settings_errors( 'xg_si' );
	if ( isset( $_GET['refreshed'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Insights ververst.</p></div>';
	}
	$data    = ekinese_si_fetch_all();
	$cache   = get_option( 'xg_si_cache', array() );
	$refresh = wp_nonce_url( admin_url( 'admin.php?page=xg-social-insights&xg_si_refresh=1' ), 'xg_si_refresh' );
	echo '<div class="wrap"><h1>Social insights</h1>';
	echo '<p>Inzicht in je social posts en reacties — voedt de dagelijkse taken en de AI-optimalisaties. ';
	echo '<a class="button" href="' . esc_url( $refresh ) . '">Nu verversen</a>';
	if ( ! empty( $cache['time'] ) ) {
		echo ' <span class="description">Laatst: ' . esc_html( $cache['time'] ) . '</span>';
	}
	echo '</p>';

	// Overzicht per platform.
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:16px;margin:16px 0">';
	foreach ( ekinese_si_platforms() as $key => $cfg ) {
		$d = $data[ $key ] ?? ekinese_si_empty();
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px">';
		echo '<h2 style="margin:0 0 8px;font-size:16px">' . esc_html( $cfg['label'] );
		$badge = ! empty( $d['connected'] ) ? '<span style="background:#1f9d55;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px">verbonden</span>' : '<span style="background:#999;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px">niet verbonden</span>';
		echo ' ' . $badge . '</h2>'; // phpcs:ignore
		if ( ! empty( $d['error'] ) ) {
			echo '<p style="color:#b32d2e">' . esc_html( $d['error'] ) . '</p>';
		}
		if ( ! empty( $d['connected'] ) ) {
			echo '<p><strong>' . esc_html( number_format_i18n( (int) $d['followers'] ) ) . '</strong> volgers · <strong>' . esc_html( (int) $d['unanswered'] ) . '</strong> reacties te checken</p>';
			if ( ! empty( $d['posts'] ) ) {
				echo '<table class="widefat striped"><thead><tr><th>Post</th><th>👁</th><th>♥</th><th>💬</th></tr></thead><tbody>';
				foreach ( $d['posts'] as $p ) {
					$t = $p['url'] ? '<a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_trim_words( $p['title'], 7 ) ) . '</a>' : esc_html( wp_trim_words( $p['title'], 7 ) );
					echo '<tr><td>' . $t . '</td><td>' . esc_html( $p['views'] ?: '—' ) . '</td><td>' . esc_html( $p['likes'] ) . '</td><td>' . esc_html( $p['comments'] ) . '</td></tr>'; // phpcs:ignore
				}
				echo '</tbody></table>';
			}
		} else {
			echo '<p class="description">Vul hieronder de gegevens in om te verbinden.</p>';
		}
		echo '</div>';
	}
	echo '</div>';

	// Instellingen.
	echo '<h2>Verbindingen</h2>';
	echo '<form method="post"><input type="hidden" name="xg_si_save" value="1">';
	wp_nonce_field( 'xg_si_save' );
	echo '<table class="form-table"><tbody>';
	foreach ( ekinese_si_platforms() as $key => $cfg ) {
		echo '<tr><th scope="row" style="vertical-align:top">' . esc_html( $cfg['label'] ) . '</th><td>';
		foreach ( $cfg['fields'] as $f => $flabel ) {
			$opt  = 'xg_si_' . $key . '_' . $f;
			$type = ( false !== strpos( $f, 'token' ) || 'key' === $f ) ? 'password' : 'text';
			echo '<p><label style="display:inline-block;min-width:160px">' . esc_html( $flabel ) . '</label> ';
			echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $opt ) . '" value="' . esc_attr( get_option( $opt, '' ) ) . '" class="regular-text" autocomplete="off"></p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form>';
	echo '<p class="description">YouTube/Facebook/Instagram zijn met token goed uit te lezen. LinkedIn, TikTok en X/Twitter zijn door de aanbieder beperkt (app-review of betaalde tier) — ze werken zodra je toegang hebt.</p>';
	echo '</div>';
}
