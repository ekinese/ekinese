<?php
/**
 * XG caching — self-built performance/caching module (vervangt FlyingPress).
 *
 * Doet alles wat een caching-plugin doet, zonder plugin en zonder cloud-dienst:
 *  - CSS/JS minify (+ optioneel combine), lokaal gecachet
 *  - alle JS deferren + 3rd-party/zware scripts uitstellen tot interactie
 *  - lazyload afbeeldingen/iframes, lichte YouTube-previews, width/height (CLS)
 *  - fonts: preconnect + display=swap
 *  - links preloaden bij hover (instant.page-stijl)
 *  - lichte HTML-minify
 *  - WordPress-rommel opschonen (emoji's, dashicons, oEmbed, XML-RPC, heartbeat …)
 *  - dagelijkse database-opschoning (revisies/concepten/prullenbak/spam/transients)
 *  - volledige page-caching via Cloudflare-edge (cache-headers + auto-purge via API)
 *
 * Beeldoptimalisatie (WebP/AVIF) staat in inc/perf-images.php.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   OPTIES
===================================================================== */
function xg_perf_defaults() {
	return array(
		'enabled'            => 1,
		// assets
		'minify'             => 1,
		'combine'            => 0,
		'defer_js'           => 1,
		'delay_js'           => 'interaction', // none|idle|interaction
		// media
		'lazyload'           => 1,
		'lazy_skip'          => 2,
		'img_dims'           => 1,
		'lite_yt'            => 1,
		// fonts
		'font_swap'          => 1,
		// navigatie
		'hover_preload'      => 1,
		// html
		'minify_html'        => 1,
		// WordPress-rommel
		'rm_emoji'           => 1,
		'rm_dashicons'       => 1,
		'rm_oembed'          => 1,
		'rm_xmlrpc'          => 1,
		'rm_jquery_migrate'  => 0,
		'limit_revisions'    => 1,
		'throttle_heartbeat' => 1,
		// database
		'db_cron'            => 1,
		'db_freq'            => 'daily',
		// Cloudflare-edge page-cache
		'cf_cache'           => 0,
		'cf_token'           => '',
		'cf_zone'            => '',
		'cf_ttl'             => 14400,
		// beelden (perf-images.php)
		'img_webp'           => 1,
		'img_avif'           => 0,
		'img_quality'        => 75,
	);
}

function xg_perf_options() {
	$o = get_option( 'xg_perf', array() );
	if ( ! is_array( $o ) ) {
		$o = array();
	}
	return array_merge( xg_perf_defaults(), $o );
}

function xg_perf_opt( $key, $default = null ) {
	$o = xg_perf_options();
	return array_key_exists( $key, $o ) ? $o[ $key ] : $default;
}

/** Is de module actief? */
function xg_perf_on() {
	return (bool) xg_perf_opt( 'enabled', 1 );
}

/**
 * Mogen we de HTML van deze request optimaliseren? (front-end, GET, geen
 * admin/REST/feed/preview). Geldt voor zowel ingelogde als anonieme bezoekers —
 * de transforms zijn presentatie-veilig. Page-cache-headers zijn strenger (zie
 * xg_perf_is_cacheable()).
 */
function xg_perf_should_optimize() {
	if ( ! xg_perf_on() ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	// Ingelogde gebruikers (admin-bar/editor) niet optimaliseren — test in incognito.
	if ( is_user_logged_in() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	if ( is_feed() || is_preview() || is_customize_preview() || is_embed() ) {
		return false;
	}
	if ( function_exists( 'is_robots' ) && is_robots() ) {
		return false;
	}
	// Eigen dynamische routes (driver-app, betalen, verify, sw/manifest) overslaan.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	foreach ( array( 'xg_sw=', 'xg_manifest=', '/rit/', '/pay/', 'wp-json' ) as $skip ) {
		if ( false !== strpos( $uri, $skip ) ) {
			return false;
		}
	}
	return (bool) apply_filters( 'xg_perf_should_optimize', true );
}

/** Mag deze (anonieme) request edge-gecachet worden door Cloudflare? */
function xg_perf_is_cacheable() {
	if ( ! xg_perf_should_optimize() ) {
		return false;
	}
	if ( is_user_logged_in() ) {
		return false;
	}
	if ( is_404() || is_search() ) {
		return false;
	}
	// Query-string (behalve nette UTM/preset) → niet cachen, zou varianten geven.
	if ( ! empty( $_GET ) ) {
		$allowed = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' );
		foreach ( array_keys( $_GET ) as $k ) {
			if ( ! in_array( $k, $allowed, true ) ) {
				return false;
			}
		}
	}
	// Sessie-/winkel-cookies → persoonlijke inhoud, niet cachen.
	if ( ! empty( $_COOKIE ) ) {
		foreach ( array_keys( $_COOKIE ) as $ck ) {
			if ( false !== stripos( $ck, 'wordpress_logged_in' ) || false !== stripos( $ck, 'comment_author' ) || false !== stripos( $ck, 'xg_token' ) ) {
				return false;
			}
		}
	}
	return (bool) apply_filters( 'xg_perf_is_cacheable', true );
}

/* =====================================================================
   CACHE-DIR (geminifyde assets)
===================================================================== */
function xg_perf_cache_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'xg-perf';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}
function xg_perf_cache_url() {
	$up = wp_upload_dir();
	return trailingslashit( $up['baseurl'] ) . 'xg-perf';
}

/* =====================================================================
   1) ASSET-MINIFY (CSS/JS) — lokale bestanden, gecachet
===================================================================== */
/** Simpele, veilige CSS-minifier. */
function xg_perf_minify_css( $css ) {
	$css = preg_replace( '#/\*(?!!)[^*]*\*+([^/*][^*]*\*+)*/#', '', $css ); // comments (geen /*! */)
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{};:,>~])\s*/', '$1', $css );
	$css = str_replace( ';}', '}', $css );
	return trim( $css );
}
/** Conservatieve JS-minifier: alleen comments/whitespace aan regeleinden. */
function xg_perf_minify_js( $js ) {
	// Verwijder /* */-blokcommentaren (niet in strings — best effort, conservatief).
	$js = preg_replace( '#/\*[^!][^*]*\*+([^/*][^*]*\*+)*/#', '', $js );
	// Verwijder regel-commentaren // … (alleen als de regel niet in een URL zit).
	$out  = array();
	$lines = explode( "\n", $js );
	foreach ( $lines as $line ) {
		$out[] = rtrim( $line );
	}
	$js = implode( "\n", $out );
	// Collapse meerdere lege regels.
	$js = preg_replace( "/\n\s*\n+/", "\n", $js );
	return trim( $js );
}

/** Lever een gecachet, geminifyd bestand voor een lokaal asset; geef URL terug. */
function xg_perf_minified_url( $src, $type ) {
	$path = xg_perf_local_path( $src );
	if ( ! $path || ! is_readable( $path ) ) {
		return $src;
	}
	$key   = md5( $path . '|' . (string) filemtime( $path ) ) . '.' . $type;
	$file  = xg_perf_cache_dir() . '/' . $key;
	$url   = xg_perf_cache_url() . '/' . $key;
	if ( ! file_exists( $file ) ) {
		$raw = file_get_contents( $path ); // phpcs:ignore
		if ( false === $raw ) {
			return $src;
		}
		$min = 'css' === $type ? xg_perf_minify_css( $raw ) : xg_perf_minify_js( $raw );
		if ( false === file_put_contents( $file, $min ) ) { // phpcs:ignore
			return $src;
		}
	}
	return $url;
}

/** Map een asset-URL naar een lokaal pad (alleen binnen deze site). */
function xg_perf_local_path( $src ) {
	$src = strtok( $src, '?' ); // strip ?ver=
	$home = home_url();
	$base = wp_parse_url( $home );
	$host = isset( $base['host'] ) ? $base['host'] : '';
	if ( 0 === strpos( $src, '//' ) ) {
		$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
	}
	if ( 0 === strpos( $src, 'http' ) ) {
		$p = wp_parse_url( $src );
		if ( empty( $p['host'] ) || $p['host'] !== $host ) {
			return ''; // extern → niet aanraken
		}
		$src = isset( $p['path'] ) ? $p['path'] : '';
	}
	$path = untrailingslashit( ABSPATH ) . '/' . ltrim( $src, '/' );
	return file_exists( $path ) ? $path : '';
}

/** Swap enqueued CSS naar geminifyde versie. */
add_filter( 'style_loader_src', function ( $src ) {
	if ( is_admin() || ! xg_perf_on() || ! xg_perf_opt( 'minify', 1 ) ) {
		return $src;
	}
	$min = xg_perf_minified_url( $src, 'css' );
	return $min ? $min : $src;
}, 20 );

/** Swap enqueued JS naar geminifyde versie. */
add_filter( 'script_loader_src', function ( $src ) {
	if ( is_admin() || ! xg_perf_on() || ! xg_perf_opt( 'minify', 1 ) ) {
		return $src;
	}
	$min = xg_perf_minified_url( $src, 'js' );
	return $min ? $min : $src;
}, 20 );

/* =====================================================================
   2) HTML-BUFFER — lazyload, scripts deferren/uitstellen, fonts, minify
===================================================================== */
add_action( 'template_redirect', function () {
	if ( ! xg_perf_should_optimize() ) {
		return;
	}
	ob_start( 'xg_perf_buffer' );
}, 1 );

function xg_perf_buffer( $html ) {
	if ( '' === trim( (string) $html ) ) {
		return $html;
	}
	// Alleen volledige HTML-documenten bewerken.
	if ( false === stripos( $html, '</html>' ) ) {
		return $html;
	}

	if ( xg_perf_opt( 'lazyload', 1 ) ) {
		$html = xg_perf_lazyload( $html );
	}
	if ( xg_perf_opt( 'lite_yt', 1 ) ) {
		$html = xg_perf_lite_youtube( $html );
	}
	if ( function_exists( 'xg_perf_images_rewrite' ) && ( xg_perf_opt( 'img_webp', 1 ) || xg_perf_opt( 'img_avif', 0 ) || xg_perf_opt( 'img_dims', 1 ) ) ) {
		$html = xg_perf_images_rewrite( $html );
	}
	if ( xg_perf_opt( 'defer_js', 1 ) || 'none' !== xg_perf_opt( 'delay_js', 'interaction' ) ) {
		$html = xg_perf_optimize_scripts( $html );
	}
	if ( xg_perf_opt( 'font_swap', 1 ) ) {
		$html = xg_perf_fonts( $html );
	}
	if ( xg_perf_opt( 'minify_html', 1 ) ) {
		$html = xg_perf_minify_html( $html );
	}
	return $html;
}

/** loading="lazy" + decoding="async" op img/iframe (skip de eerste N img's). */
function xg_perf_lazyload( $html ) {
	$skip = max( 0, (int) xg_perf_opt( 'lazy_skip', 2 ) );
	$seen = 0;
	$html = preg_replace_callback( '#<img\b[^>]*>#i', function ( $m ) use ( &$seen, $skip ) {
		$tag = $m[0];
		if ( preg_match( '/\b(loading|data-no-lazy|data-skip-lazy)\s*=/i', $tag ) ) {
			return $tag;
		}
		$seen++;
		if ( $seen <= $skip ) {
			// Boven de vouw → niet lazy, wel hoge prioriteit.
			if ( ! preg_match( '/\bfetchpriority\s*=/i', $tag ) ) {
				$tag = preg_replace( '/<img\b/i', '<img fetchpriority="high"', $tag, 1 );
			}
			return $tag;
		}
		$add = 'loading="lazy" decoding="async"';
		return preg_replace( '/<img\b/i', '<img ' . $add, $tag, 1 );
	}, $html );

	// iframes (behalve al-lazy en eigen verify/map embeds met data-no-lazy).
	$html = preg_replace_callback( '#<iframe\b[^>]*>#i', function ( $m ) {
		$tag = $m[0];
		if ( preg_match( '/\b(loading|data-no-lazy)\s*=/i', $tag ) ) {
			return $tag;
		}
		return preg_replace( '/<iframe\b/i', '<iframe loading="lazy"', $tag, 1 );
	}, $html );

	return $html;
}

/** Vervang YouTube-iframes door een lichte facade (thumbnail + play). */
function xg_perf_lite_youtube( $html ) {
	return preg_replace_callback( '#<iframe\b[^>]*src=["\']([^"\']*youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_\-]+)[^"\']*)["\'][^>]*></iframe>#i', function ( $m ) {
		$id = $m[2];
		$thumb = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
		return '<div class="xg-lyt" data-yt="' . esc_attr( $id ) . '" style="background-image:url(' . esc_url( $thumb ) . ')">'
			. '<button type="button" class="xg-lyt-play" aria-label="Video afspelen"></button></div>';
	}, $html );
}

/**
 * Scripts: defer alles (behalve uitsluitingen) en stel 3rd-party/zware scripts
 * uit tot de eerste interactie. JSON-LD en al-geoptimaliseerde scripts blijven.
 */
function xg_perf_optimize_scripts( $html ) {
	$defer      = (bool) xg_perf_opt( 'defer_js', 1 );
	$delay_mode = (string) xg_perf_opt( 'delay_js', 'interaction' );
	$delay      = ( 'none' !== $delay_mode );
	// Scripts die NOOIT aangeraakt worden (volgorde-/render-kritisch).
	$skip = apply_filters( 'xg_perf_script_skip', array( 'theme.js', 'jquery.js', 'jquery.min.js', 'jquery-core' ) );
	// 3rd-party patronen die we uitstellen tot interactie.
	$thirdparty = apply_filters( 'xg_perf_delay_patterns', array(
		'googletagmanager', 'google-analytics', 'gtag/js', 'gtag(', 'analytics.js',
		'facebook.net', 'fbevents', 'connect.facebook', 'hotjar', 'clarity.ms',
		'doubleclick', 'googlesyndication', 'youtube.com/iframe_api', 'tiktok',
		'linkedin', 'pinterest', 'snap.licdn', 'twitter', 'bing.com',
	) );

	$has_delayed = false;
	$html = preg_replace_callback( '#<script\b([^>]*)>(.*?)</script>#is', function ( $m ) use ( $defer, $delay, $skip, $thirdparty, &$has_delayed ) {
		$attr = $m[1];
		$body = $m[2];
		// JSON-LD / niet-JS / opt-out laten staan.
		if ( preg_match( '/type\s*=\s*["\']application\/(ld\+json|json)["\']/i', $attr ) ) {
			return $m[0];
		}
		if ( preg_match( '/\bdata-no-optimize\b/i', $attr ) ) {
			return $m[0];
		}
		$is_skip = false;
		foreach ( $skip as $s ) {
			if ( false !== stripos( $attr, $s ) || false !== stripos( $body, $s ) ) {
				$is_skip = true;
				break;
			}
		}
		// Uitstellen tot interactie?
		if ( $delay && ! $is_skip ) {
			$match3p = false;
			foreach ( $thirdparty as $p ) {
				if ( false !== stripos( $attr, $p ) || false !== stripos( $body, $p ) ) {
					$match3p = true;
					break;
				}
			}
			if ( $match3p ) {
				$has_delayed = true;
				$na = preg_replace( '/\stype\s*=\s*["\'][^"\']*["\']/i', '', $attr );
				$na = preg_replace( '/\ssrc\s*=/i', ' data-xgsrc=', $na );
				return '<script type="xg/delayed"' . $na . '>' . $body . '</script>';
			}
		}
		// Anders: deferren als er een src is en nog geen defer/async.
		if ( $defer && ! $is_skip && preg_match( '/\ssrc\s*=/i', $attr ) && ! preg_match( '/\b(defer|async)\b/i', $attr ) ) {
			return '<script' . $attr . ' defer>' . $body . '</script>';
		}
		return $m[0];
	}, $html );

	if ( $has_delayed || $delay ) {
		$loader = xg_perf_delay_loader_js();
		$html   = str_ireplace( '</body>', '<script data-no-optimize>' . $loader . '</script></body>', $html );
	}
	return $html;
}

/** Mini-loader die uitgestelde scripts laadt bij de eerste interactie. */
function xg_perf_delay_loader_js() {
	$events = "['mousemove','mousedown','keydown','touchstart','wheel','scroll']";
	// Fallback-timeout zodat scripts ook zonder interactie uiteindelijk laden.
	return "(function(){var done=false;function run(){if(done)return;done=true;" .
		"var s=document.querySelectorAll('script[type=\"xg/delayed\"]');" .
		"function next(i){if(i>=s.length)return;var o=s[i],n=document.createElement('script');" .
		"for(var a=0;a<o.attributes.length;a++){var at=o.attributes[a];if(at.name==='type')continue;" .
		"if(at.name==='data-xgsrc'){n.src=at.value;}else{n.setAttribute(at.name,at.value);}}" .
		"if(!o.getAttribute('data-xgsrc')){n.text=o.text;}" .
		"o.parentNode.replaceChild(n,o);" .
		"if(n.src){n.onload=n.onerror=function(){next(i+1);};}else{next(i+1);}}" .
		"next(0);" . $events . ".forEach(function(e){window.removeEventListener(e,run);});}" .
		$events . ".forEach(function(e){window.addEventListener(e,run,{passive:true,once:true});});" .
		"setTimeout(run,7000);})();";
}

/** Fonts: preconnect + display=swap op Google Fonts-links. */
function xg_perf_fonts( $html ) {
	if ( false === stripos( $html, 'fonts.googleapis.com' ) ) {
		return $html;
	}
	// display=swap toevoegen waar het ontbreekt.
	$html = preg_replace_callback( '#(<link\b[^>]*href=["\'][^"\']*fonts\.googleapis\.com[^"\']*)(["\'][^>]*>)#i', function ( $m ) {
		$href = $m[1];
		if ( false === stripos( $href, 'display=' ) ) {
			$href .= ( false !== strpos( $href, '?' ) ? '&' : '?' ) . 'display=swap';
		}
		return $href . $m[2];
	}, $html );
	// preconnect (1×) injecteren in de head.
	if ( false === stripos( $html, 'rel="preconnect" href="https://fonts.gstatic.com"' ) ) {
		$pc = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="preconnect" href="https://fonts.googleapis.com">';
		$html = preg_replace( '#</title>#i', '</title>' . $pc, $html, 1 );
	}
	return $html;
}

/** Lichte HTML-minify (behoudt pre/textarea/script/style-inhoud). */
function xg_perf_minify_html( $html ) {
	$parts = preg_split( '#(<(?:pre|textarea|script|style)\b[^>]*>.*?</(?:pre|textarea|script|style)>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$out = '';
	foreach ( $parts as $i => $part ) {
		if ( $i % 2 === 1 ) {
			$out .= $part; // beschermd blok ongemoeid
			continue;
		}
		$part = preg_replace( '/<!--(?!\[if).*?-->/s', '', $part ); // HTML-comments (geen IE-conditionals)
		$part = preg_replace( '/\s{2,}/', ' ', $part );
		$part = preg_replace( '/>\s+</', '><', $part );
		$out .= $part;
	}
	return $out;
}

/* Lite-YouTube + hover-preload assets alleen front-end laden. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! xg_perf_should_optimize() ) {
		return;
	}
	$css = '.xg-lyt{position:relative;width:100%;aspect-ratio:16/9;background-size:cover;background-position:center;cursor:pointer}'
		. '.xg-lyt-play{position:absolute;inset:0;margin:auto;width:68px;height:48px;border:0;border-radius:14px;background:rgba(0,0,0,.7);cursor:pointer}'
		. '.xg-lyt-play::before{content:"";position:absolute;top:50%;left:50%;transform:translate(-40%,-50%);border-style:solid;border-width:11px 0 11px 18px;border-color:transparent transparent transparent #fff}';
	wp_register_style( 'xg-perf', false, array(), null );
	wp_enqueue_style( 'xg-perf' );
	wp_add_inline_style( 'xg-perf', $css );

	$js = '';
	if ( xg_perf_opt( 'lite_yt', 1 ) ) {
		$js .= "document.addEventListener('click',function(e){var f=e.target.closest&&e.target.closest('.xg-lyt');if(!f)return;var id=f.getAttribute('data-yt');var i=document.createElement('iframe');i.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture');i.setAttribute('allowfullscreen','');i.style.cssText='position:absolute;inset:0;width:100%;height:100%;border:0';i.src='https://www.youtube-nocookie.com/embed/'+id+'?autoplay=1';f.innerHTML='';f.appendChild(i);});";
	}
	if ( xg_perf_opt( 'hover_preload', 1 ) ) {
		// instant.page-stijl: prefetch bij hover/touchstart (max 1×/URL).
		$js .= "(function(){var seen={};function pf(u){if(seen[u])return;seen[u]=1;var l=document.createElement('link');l.rel='prefetch';l.href=u;document.head.appendChild(l);}"
			. "var t;document.addEventListener('mouseover',function(e){var a=e.target.closest&&e.target.closest('a');if(!a||!a.href)return;if(a.host!==location.host)return;if(a.href.indexOf('#')>-1)return;t=setTimeout(function(){pf(a.href);},65);},{passive:true});"
			. "document.addEventListener('mouseout',function(){clearTimeout(t);},{passive:true});"
			. "document.addEventListener('touchstart',function(e){var a=e.target.closest&&e.target.closest('a');if(a&&a.href&&a.host===location.host)pf(a.href);},{passive:true});})();";
	}
	if ( $js ) {
		wp_register_script( 'xg-perf', false, array(), null, true );
		wp_enqueue_script( 'xg-perf' );
		wp_add_inline_script( 'xg-perf', $js );
	}
}, 5 );

/* =====================================================================
   3) WORDPRESS-ROMMEL OPSCHONEN
===================================================================== */
add_action( 'init', function () {
	if ( ! xg_perf_on() || is_admin() ) {
		return;
	}
	if ( xg_perf_opt( 'rm_emoji', 1 ) ) {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', function ( $p ) { return is_array( $p ) ? array_diff( $p, array( 'wpemoji' ) ) : $p; } );
		add_filter( 'emoji_svg_url', '__return_false' );
	}
	if ( xg_perf_opt( 'rm_oembed', 1 ) ) {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_action( 'wp_footer', function () { wp_dequeue_script( 'wp-embed' ); }, 1 );
	}
	if ( xg_perf_opt( 'rm_jquery_migrate', 0 ) ) {
		add_action( 'wp_default_scripts', function ( $scripts ) {
			if ( is_admin() ) { return; }
			$h = $scripts->registered['jquery'] ?? null;
			if ( $h && ! empty( $h->deps ) ) {
				$h->deps = array_diff( $h->deps, array( 'jquery-migrate' ) );
			}
		} );
	}
} );

/* Dashicons niet voor niet-ingelogde bezoekers. */
add_action( 'wp_enqueue_scripts', function () {
	if ( xg_perf_on() && xg_perf_opt( 'rm_dashicons', 1 ) && ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
}, 100 );

/* XML-RPC uitschakelen. */
add_filter( 'xmlrpc_enabled', function ( $v ) {
	return ( xg_perf_on() && xg_perf_opt( 'rm_xmlrpc', 1 ) ) ? false : $v;
} );
add_filter( 'wp_headers', function ( $headers ) {
	if ( xg_perf_on() && xg_perf_opt( 'rm_xmlrpc', 1 ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
} );

/* Revisies beperken (max 3). */
add_filter( 'wp_revisions_to_keep', function ( $num, $post ) {
	if ( xg_perf_on() && xg_perf_opt( 'limit_revisions', 1 ) ) {
		return min( (int) $num < 0 ? 3 : $num, 3 );
	}
	return $num;
}, 10, 2 );

/* Heartbeat throttlen (1×/60s) buiten de editor. */
add_filter( 'heartbeat_settings', function ( $s ) {
	if ( xg_perf_on() && xg_perf_opt( 'throttle_heartbeat', 1 ) ) {
		$s['interval'] = 60;
	}
	return $s;
} );

/* =====================================================================
   4) DATABASE-OPSCHONING (dagelijkse cron)
===================================================================== */
add_action( 'xg_perf_db_clean', 'xg_perf_run_db_clean' );

function xg_perf_run_db_clean() {
	if ( ! xg_perf_on() || ! xg_perf_opt( 'db_cron', 1 ) ) {
		return array();
	}
	global $wpdb;
	$report = array();

	// Revisies.
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='revision'" ); // phpcs:ignore
	foreach ( $ids as $id ) { wp_delete_post_revision( (int) $id ); }
	$report['revisies'] = count( $ids );

	// Auto-drafts + verlopen concepten.
	$drafts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status='auto-draft'" ); // phpcs:ignore
	foreach ( $drafts as $id ) { wp_delete_post( (int) $id, true ); }
	$report['concepten'] = count( $drafts );

	// Prullenbak-berichten.
	$trash = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status='trash'" ); // phpcs:ignore
	foreach ( $trash as $id ) { wp_delete_post( (int) $id, true ); }
	$report['prullenbak'] = count( $trash );

	// Spam + prullenbak-reacties.
	$cids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ); // phpcs:ignore
	foreach ( $cids as $cid ) { wp_delete_comment( (int) $cid, true ); }
	$report['reacties'] = count( $cids );

	// Verlopen transients.
	$wpdb->query( "DELETE a, b FROM {$wpdb->options} a LEFT JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12)) WHERE a.option_name LIKE '\_transient\_%' AND a.option_name NOT LIKE '\_transient\_timeout\_%' AND b.option_value < UNIX_TIMESTAMP()" ); // phpcs:ignore
	$report['transients'] = (int) $wpdb->rows_affected;

	// Tabellen optimaliseren.
	$tables = $wpdb->get_col( 'SHOW TABLES', 0 ); // phpcs:ignore
	$opt = 0;
	foreach ( $tables as $t ) {
		if ( 0 === strpos( $t, $wpdb->prefix ) ) {
			$wpdb->query( "OPTIMIZE TABLE `$t`" ); // phpcs:ignore
			$opt++;
		}
	}
	$report['tabellen'] = $opt;

	update_option( 'xg_perf_db_last', array( 'time' => current_time( 'mysql' ), 'report' => $report ), false );
	return $report;
}

/* Cron (her)plannen volgens instelling. */
add_action( 'init', function () {
	$want = xg_perf_on() && xg_perf_opt( 'db_cron', 1 );
	$freq = xg_perf_opt( 'db_freq', 'daily' );
	$next = wp_next_scheduled( 'xg_perf_db_clean' );
	if ( $want && ! $next ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, $freq, 'xg_perf_db_clean' );
	} elseif ( ! $want && $next ) {
		wp_unschedule_event( $next, 'xg_perf_db_clean' );
	}
} );

/* =====================================================================
   5) CLOUDFLARE EDGE PAGE-CACHE (headers + auto-purge)
===================================================================== */
/**
 * Cache-headers: cachebare anonieme pagina's krijgen public/max-age zodat een
 * Cloudflare "Cache Everything"-regel ze aan de edge bewaart; al het andere
 * krijgt no-store. Cloudflare honoreert Cache-Control met Edge Cache TTL via een
 * Cache-Rule (zie admin-uitleg).
 */
add_filter( 'wp_headers', function ( $headers ) {
	if ( ! xg_perf_on() || ! xg_perf_opt( 'cf_cache', 0 ) ) {
		return $headers;
	}
	if ( xg_perf_is_cacheable() ) {
		$ttl = max( 60, (int) xg_perf_opt( 'cf_ttl', 14400 ) );
		$headers['Cache-Control'] = 'public, max-age=' . $ttl . ', s-maxage=' . $ttl;
		$headers['X-XG-Cache']    = 'edge';
	} else {
		$headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
		$headers['X-XG-Cache']    = 'bypass';
	}
	return $headers;
}, 99 );

/** Cloudflare API: zone-id (auto-resolve uit domein als leeg). */
function xg_perf_cf_zone_id() {
	$zone = trim( (string) xg_perf_opt( 'cf_zone', '' ) );
	if ( $zone ) {
		return $zone;
	}
	$token = trim( (string) xg_perf_opt( 'cf_token', '' ) );
	if ( ! $token ) {
		return '';
	}
	$cached = get_transient( 'xg_perf_cf_zone' );
	if ( $cached ) {
		return $cached;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', (string) $host );
	$res  = wp_remote_get( 'https://api.cloudflare.com/client/v4/zones?name=' . rawurlencode( $host ), array(
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
		'timeout' => 12,
	) );
	if ( is_wp_error( $res ) ) {
		return '';
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! empty( $body['result'][0]['id'] ) ) {
		set_transient( 'xg_perf_cf_zone', $body['result'][0]['id'], DAY_IN_SECONDS );
		return $body['result'][0]['id'];
	}
	return '';
}

/** Purge Cloudflare-cache: specifieke URL's of alles. */
function xg_perf_cf_purge( $urls = array() ) {
	if ( ! xg_perf_opt( 'cf_cache', 0 ) ) {
		return false;
	}
	$token = trim( (string) xg_perf_opt( 'cf_token', '' ) );
	$zone  = xg_perf_cf_zone_id();
	if ( ! $token || ! $zone ) {
		return false;
	}
	$payload = empty( $urls ) ? array( 'purge_everything' => true ) : array( 'files' => array_values( array_unique( $urls ) ) );
	$res = wp_remote_post( 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/purge_cache', array(
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $payload ),
		'timeout' => 15,
	) );
	if ( is_wp_error( $res ) ) {
		return false;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	return ! empty( $body['success'] );
}

/** Bij content-wijziging de relevante URL's (+ home) purgen. */
function xg_perf_purge_on_save( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		// Bij depubliceren toch home purgen.
		xg_perf_cf_purge( array( home_url( '/' ) ) );
		return;
	}
	$urls = array( home_url( '/' ), get_permalink( $post_id ) );
	// Archief/feed van het posttype mee.
	$pt = get_post_type_archive_link( $post->post_type );
	if ( $pt ) {
		$urls[] = $pt;
	}
	xg_perf_cf_purge( array_filter( $urls ) );
}
add_action( 'save_post', 'xg_perf_purge_on_save', 20 );
add_action( 'deleted_post', 'xg_perf_purge_on_save', 20 );
add_action( 'comment_post', function () { xg_perf_cf_purge( array( home_url( '/' ) ) ); } );

/* =====================================================================
   6) ADMIN-PAGINA "XG caching"
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'XG caching', 'XG caching', 'manage_options', 'xg-perf', 'xg_perf_admin_page' );
}, 50 );

add_action( 'admin_init', function () {
	if ( ! isset( $_POST['xg_perf_save'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'xg_perf_save' );
	$d   = xg_perf_defaults();
	$new = array();
	foreach ( $d as $k => $dv ) {
		if ( in_array( $k, array( 'cf_token', 'cf_zone' ), true ) ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		} elseif ( in_array( $k, array( 'delay_js', 'db_freq' ), true ) ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : $dv;
		} elseif ( in_array( $k, array( 'lazy_skip', 'cf_ttl', 'img_quality' ), true ) ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? max( 0, (int) $_POST[ $k ] ) : $dv;
		} else {
			$new[ $k ] = isset( $_POST[ $k ] ) ? 1 : 0;
		}
	}
	update_option( 'xg_perf', $new, false );
	delete_transient( 'xg_perf_cf_zone' );
	add_settings_error( 'xg_perf', 'saved', 'Instellingen opgeslagen.', 'success' );
} );

/* Acties: cache leegmaken, db nu opschonen, CF testen. */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || empty( $_GET['xg_perf_action'] ) ) {
		return;
	}
	check_admin_referer( 'xg_perf_action' );
	$action = sanitize_key( $_GET['xg_perf_action'] );
	$msg = '';
	if ( 'clear_assets' === $action ) {
		$n = 0;
		foreach ( (array) glob( xg_perf_cache_dir() . '/*' ) as $f ) {
			if ( is_file( $f ) ) { unlink( $f ); $n++; } // phpcs:ignore
		}
		$msg = $n . ' gecachete asset-bestanden gewist.';
	} elseif ( 'db_now' === $action ) {
		$r = xg_perf_run_db_clean();
		$msg = 'Database opgeschoond: ' . implode( ', ', array_map( function ( $k, $v ) { return "$v $k"; }, array_keys( $r ), $r ) ) . '.';
	} elseif ( 'cf_test' === $action ) {
		$zone = xg_perf_cf_zone_id();
		$msg  = $zone ? 'Cloudflare verbonden — zone-id: ' . esc_html( $zone ) : 'Geen verbinding met Cloudflare (controleer API-token).';
	} elseif ( 'cf_purge' === $action ) {
		$msg = xg_perf_cf_purge() ? 'Cloudflare-cache volledig geleegd.' : 'Purge mislukt (controleer token/zone).';
	}
	set_transient( 'xg_perf_admin_msg', $msg, 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=xg-perf' ) );
	exit;
} );

function xg_perf_admin_page() {
	$o = xg_perf_options();
	$nonce_action = wp_nonce_url( admin_url( 'admin.php?page=xg-perf' ), 'xg_perf_action' );
	$msg = get_transient( 'xg_perf_admin_msg' );
	if ( $msg ) {
		delete_transient( 'xg_perf_admin_msg' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	settings_errors( 'xg_perf' );
	$last = get_option( 'xg_perf_db_last', array() );

	$tog = function ( $key, $label, $desc ) use ( $o ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> ' . esc_html( $desc ) . '</label>';
		echo '</td></tr>';
	};
	?>
	<div class="wrap">
		<h1>XG caching</h1>
		<p>Self-built prestatie- en caching-module (vervangt FlyingPress). Geen plugin, geen externe cloud — alleen Cloudflare voor de edge-cache.</p>

		<p>
			<a class="button" href="<?php echo esc_url( $nonce_action . '&xg_perf_action=clear_assets' ); ?>">Asset-cache leegmaken</a>
			<a class="button" href="<?php echo esc_url( $nonce_action . '&xg_perf_action=db_now' ); ?>">Database nu opschonen</a>
			<a class="button" href="<?php echo esc_url( $nonce_action . '&xg_perf_action=cf_test' ); ?>">Cloudflare testen</a>
			<a class="button" href="<?php echo esc_url( $nonce_action . '&xg_perf_action=cf_purge' ); ?>">Cloudflare-cache legen</a>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'xg_perf_save' ); ?>
			<input type="hidden" name="xg_perf_save" value="1">

			<h2>Algemeen</h2>
			<table class="form-table"><tbody>
				<?php $tog( 'enabled', 'XG caching actief', 'Schakelt de hele module in/uit' ); ?>
			</tbody></table>

			<h2>CSS &amp; JavaScript</h2>
			<table class="form-table"><tbody>
				<?php
				$tog( 'minify', 'CSS/JS minificeren', 'Verkleint lokale bestanden (gecachet)' );
				$tog( 'defer_js', 'Alle JavaScript deferren', 'Niet-kritische scripts blokkeren het renderen niet' );
				?>
				<tr><th scope="row">Scripts uitstellen</th><td>
					<select name="delay_js">
						<option value="none" <?php selected( $o['delay_js'], 'none' ); ?>>Uit</option>
						<option value="interaction" <?php selected( $o['delay_js'], 'interaction' ); ?>>3rd-party laden bij interactie (aanbevolen)</option>
					</select>
					<p class="description">Stelt analytics/chat/ads uit tot de eerste muis-/scroll-/toetsactie (grote INP/LCP-winst).</p>
				</td></tr>
			</tbody></table>

			<h2>Afbeeldingen, media &amp; fonts</h2>
			<table class="form-table"><tbody>
				<?php $tog( 'lazyload', 'Lazyload media', 'Afbeeldingen/iframes onder de vouw laden later' ); ?>
				<tr><th scope="row">Lazyload overslaan (boven de vouw)</th><td>
					<input type="number" name="lazy_skip" min="0" max="10" value="<?php echo esc_attr( $o['lazy_skip'] ); ?>" class="small-text"> eerste afbeeldingen
				</td></tr>
				<?php
				$tog( 'img_dims', 'Afbeeldingen width/height', 'Voegt afmetingen toe om layout-shift (CLS) te voorkomen' );
				$tog( 'lite_yt', 'Lichte YouTube-previews', 'Vervangt zware YouTube-iframes door een klik-om-te-laden facade' );
				$tog( 'font_swap', 'Fonts: swap + preconnect', 'display=swap + preconnect op Google Fonts' );
				$tog( 'hover_preload', 'Links preloaden bij hover', 'Prefetcht een pagina zodra de muis over een link gaat' );
				$tog( 'minify_html', 'HTML minificeren', 'Verwijdert overbodige witruimte/commentaar uit de HTML' );
				?>
			</tbody></table>

			<h2>WordPress opschonen</h2>
			<table class="form-table"><tbody>
				<?php
				$tog( 'rm_emoji', "Emoji's uitschakelen", 'Geen emoji-scripts/-stijlen' );
				$tog( 'rm_dashicons', 'Dashicons verwijderen', 'Admin-icoonfont niet voor bezoekers' );
				$tog( 'rm_oembed', 'oEmbeds uitschakelen', 'Geen automatische externe insluitingen' );
				$tog( 'rm_xmlrpc', 'XML-RPC uitschakelen', 'Blokkeert externe XML-RPC-toegang' );
				$tog( 'rm_jquery_migrate', 'jQuery Migrate uitschakelen', 'Geen oude jQuery-compat (test je site!)' );
				$tog( 'limit_revisions', 'Revisies beperken', 'Max. 3 revisies per bericht' );
				$tog( 'throttle_heartbeat', 'Heartbeat throttlen', 'Achtergrondactiviteit max. 1×/60s' );
				?>
			</tbody></table>

			<h2>Database</h2>
			<table class="form-table"><tbody>
				<?php $tog( 'db_cron', 'Database automatisch opschonen', 'Geplande opschoning (revisies, concepten, prullenbak, spam, transients, OPTIMIZE)' ); ?>
				<tr><th scope="row">Frequentie</th><td>
					<select name="db_freq">
						<option value="daily" <?php selected( $o['db_freq'], 'daily' ); ?>>Dagelijks</option>
						<option value="weekly" <?php selected( $o['db_freq'], 'weekly' ); ?>>Wekelijks</option>
					</select>
					<?php if ( ! empty( $last['time'] ) ) : ?>
						<p class="description">Laatste opschoning: <?php echo esc_html( $last['time'] ); ?></p>
					<?php endif; ?>
				</td></tr>
			</tbody></table>

			<h2>Page-cache (Cloudflare edge)</h2>
			<table class="form-table"><tbody>
				<?php $tog( 'cf_cache', 'Cloudflare edge-cache', 'Stuurt cache-headers + purged automatisch bij wijzigingen' ); ?>
				<tr><th scope="row">Cloudflare API-token</th><td>
					<input type="password" name="cf_token" value="<?php echo esc_attr( $o['cf_token'] ); ?>" class="regular-text" autocomplete="off">
					<p class="description">Token met rechten <em>Zone → Cache Purge</em> en <em>Zone → Zone Read</em>.</p>
				</td></tr>
				<tr><th scope="row">Zone-ID (optioneel)</th><td>
					<input type="text" name="cf_zone" value="<?php echo esc_attr( $o['cf_zone'] ); ?>" class="regular-text">
					<p class="description">Leeg laten = automatisch opzoeken uit je domein.</p>
				</td></tr>
				<tr><th scope="row">Edge-TTL (seconden)</th><td>
					<input type="number" name="cf_ttl" min="60" value="<?php echo esc_attr( $o['cf_ttl'] ); ?>" class="small-text">
				</td></tr>
			</tbody></table>
			<p class="description" style="max-width:720px">
				<strong>Eenmalig in Cloudflare (gratis plan):</strong> maak een <em>Cache Rule</em> →
				als hostnaam = je domein, dan <em>Eligible for cache</em> + <em>Edge TTL: respecteer oorsprong</em>.
				XG caching stuurt dan de juiste <code>Cache-Control</code>-headers en purged automatisch bij elke
				publicatie. Ingelogde gebruikers en pagina's met query/cookies worden nooit gecachet.
			</p>

			<h2>Afbeeldingsoptimalisatie</h2>
			<table class="form-table"><tbody>
				<?php
				$tog( 'img_webp', 'WebP genereren', 'Maakt WebP-versies bij upload (als de server het ondersteunt)' );
				$tog( 'img_avif', 'AVIF genereren', 'Alleen als de server imageavif() heeft (PHP 8.1+ GD)' );
				?>
				<tr><th scope="row">Beeldkwaliteit</th><td>
					<input type="number" name="img_quality" min="40" max="95" value="<?php echo esc_attr( $o['img_quality'] ); ?>" class="small-text"> (40–95)
				</td></tr>
				<?php if ( function_exists( 'xg_perf_images_status' ) ) : $st = xg_perf_images_status(); ?>
				<tr><th scope="row">Serverondersteuning</th><td>
					WebP: <strong><?php echo $st['webp'] ? 'ja' : 'nee'; ?></strong> ·
					AVIF: <strong><?php echo $st['avif'] ? 'ja' : 'nee'; ?></strong><br>
					<?php if ( ! empty( $st['bulk_url'] ) ) : ?>
						<a class="button" href="<?php echo esc_url( $st['bulk_url'] ); ?>">Bestaande afbeeldingen verwerken</a>
						<span class="description"><?php echo esc_html( $st['done'] ); ?> / <?php echo esc_html( $st['total'] ); ?> verwerkt</span>
					<?php endif; ?>
				</td></tr>
				<?php endif; ?>
			</tbody></table>

			<?php submit_button( 'Opslaan' ); ?>
		</form>
	</div>
	<?php
}
