<?php
/**
 * XGOUD PWA — de hele site als installeerbare app. Manifest + service-worker via
 * lichte query-endpoints (geen rewrite-flush nodig). De service-worker cachet
 * ALLEEN statische assets (network-first met cache-fallback) en raakt nooit
 * REST/admin/gepersonaliseerde HTML aan, zodat prijzen/account altijd vers zijn.
 *
 * Startscherm = /app/ (het widget: live prijzen, profiel, portfolio, afspraken).
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ekinese_pwa_icon() {
	return get_theme_file_uri( 'assets/icons/xgoud-icon.svg' );
}

/* Manifest + service-worker als query-endpoints. */
add_action( 'template_redirect', function () {
	if ( isset( $_GET['xg_manifest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array(
			'name'             => 'XGOUD',
			'short_name'       => 'XGOUD',
			'description'      => 'Verkoop edelmetaal tegen de beste dagprijs — live prijzen, uw portfolio en afspraken.',
			'start_url'        => '/app/',
			'scope'            => '/',
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#161412',
			'theme_color'      => '#161412',
			'lang'             => 'nl',
			'icons'            => array(
				array( 'src' => ekinese_pwa_icon(), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable' ),
			),
			'shortcuts'        => array(
				array( 'name' => 'Mijn XGOUD', 'url' => '/app/' ),
				array( 'name' => 'Afspraak', 'url' => '/afspraak/' ),
				array( 'name' => 'Veilingen', 'url' => '/veilingen/' ),
			),
		) );
		exit;
	}
	if ( isset( $_GET['xg_sw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		echo ekinese_pwa_sw_js(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
} );

/** De service-worker-broncode (network-first voor statische assets + push). */
function ekinese_pwa_sw_js() {
	$icon = ekinese_pwa_icon();
	$push = <<<JS

self.addEventListener('push', e => {
  let d = {};
  try { d = e.data.json(); } catch (_) { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'XGOUD', {
    body: d.body || '', icon: '$icon', badge: '$icon', data: { url: d.url || '/app/' }
  }));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/app/';
  e.waitUntil(self.clients.matchAll({ type: 'window' }).then(ws => {
    for (const w of ws) { if (w.url.indexOf(url) !== -1 && 'focus' in w) return w.focus(); }
    return self.clients.openWindow(url);
  }));
});
JS;
	return <<<JS
const CACHE = 'xg-shell-v4';
self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  // Nooit cachen: REST/admin/login/gepersonaliseerd (prijzen/account moeten vers).
  if (url.pathname.startsWith('/wp-json') || url.pathname.startsWith('/wp-admin') ||
      url.pathname.startsWith('/wp-login') || url.search.includes('token=') || url.search.includes('xg_sw') || url.search.includes('xg_manifest')) return;
  const isAsset = ['style', 'script', 'image', 'font'].includes(req.destination);
  if (!isAsset) return; // HTML niet cachen → altijd actuele inhoud
  e.respondWith(
    fetch(req).then(res => { const c = res.clone(); caches.open(CACHE).then(ch => ch.put(req, c)); return res; })
      .catch(() => caches.match(req))
  );
});
JS . $push;
}

/* Manifest-link + theme-color + apple-meta in de head. */
add_action( 'wp_head', function () {
	$icon = esc_url( ekinese_pwa_icon() );
	echo '<link rel="manifest" href="' . esc_url( home_url( '/?xg_manifest=1' ) ) . '">' . "\n";
	echo '<meta name="theme-color" content="#161412">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . $icon . '">' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
}, 2 );

/* Service-worker registreren + install-prompt. */
add_action( 'wp_enqueue_scripts', function () {
	$js = get_theme_file_path( 'assets/js/pwa.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-pwa', get_theme_file_uri( 'assets/js/pwa.js' ), array(), (string) filemtime( $js ), true );
		wp_localize_script( 'ekinese-pwa', 'XGPWA', array( 'sw' => esc_url_raw( home_url( '/?xg_sw=1' ) ) ) );
	}
} );
