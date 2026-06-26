<?php
/**
 * XG caching — beeldoptimalisatie (onderdeel van inc/perf.php).
 *
 * Self-built WebP/AVIF (vervangt de FlyingPress-beeldcloud), zonder externe
 * dienst. Genereert moderne formaten bij upload (en in bulk voor bestaande
 * media) als de server het ondersteunt, en serveert ze via <picture> op basis
 * van de Accept-header. Voegt ook width/height toe (CLS) aan <img> die ze missen.
 *
 * Beperking (eerlijk): WebP vereist GD/Imagick-WebP op de server; AVIF vereist
 * PHP 8.1+ met GD-AVIF (vaak niet beschikbaar bij gedeelde hosting). Waar de
 * server het niet kan, blijft het origineel staan — geen kwaliteitsverlies.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Wat kan de server? */
function xg_perf_images_status() {
	$webp = function_exists( 'imagewebp' ) || ( class_exists( 'Imagick' ) && ! empty( Imagick::queryFormats( 'WEBP' ) ) );
	$avif = function_exists( 'imageavif' );
	$total = (int) wp_count_posts( 'attachment' )->inherit;
	$done  = (int) get_option( 'xg_perf_img_done', 0 );
	return array(
		'webp'     => $webp,
		'avif'     => $avif,
		'total'    => $total,
		'done'     => $done,
		'bulk_url' => ( $webp || $avif ) ? wp_nonce_url( admin_url( 'admin.php?page=xg-perf&xg_perf_action=img_bulk' ), 'xg_perf_action' ) : '',
	);
}

/** Genereer moderne varianten voor één bestandspad. */
function xg_perf_make_variants( $file ) {
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return array();
	}
	$q    = (int) ( function_exists( 'xg_perf_opt' ) ? xg_perf_opt( 'img_quality', 75 ) : 75 );
	$q    = max( 40, min( 95, $q ) );
	$made = array();

	$src = null;
	if ( function_exists( 'imagecreatefromstring' ) ) {
		$data = file_get_contents( $file ); // phpcs:ignore
		$src  = $data ? @imagecreatefromstring( $data ) : null; // phpcs:ignore
	}
	if ( ! $src ) {
		return array();
	}
	// PNG-transparantie behouden.
	if ( 'png' === $ext ) {
		imagepalettetotruecolor( $src );
		imagealphablending( $src, true );
		imagesavealpha( $src, true );
	}

	if ( function_exists( 'xg_perf_opt' ) && xg_perf_opt( 'img_webp', 1 ) && function_exists( 'imagewebp' ) ) {
		$dest = $file . '.webp';
		if ( ! file_exists( $dest ) && @imagewebp( $src, $dest, $q ) ) { // phpcs:ignore
			$made['webp'] = $dest;
		}
	}
	if ( function_exists( 'xg_perf_opt' ) && xg_perf_opt( 'img_avif', 0 ) && function_exists( 'imageavif' ) ) {
		$dest = $file . '.avif';
		if ( ! file_exists( $dest ) && @imageavif( $src, $dest, $q ) ) { // phpcs:ignore
			$made['avif'] = $dest;
		}
	}
	imagedestroy( $src );
	return $made;
}

/** Bij het genereren van attachment-metadata: alle maten meenemen. */
add_filter( 'wp_generate_attachment_metadata', function ( $meta, $attachment_id ) {
	if ( ! function_exists( 'xg_perf_opt' ) || ! xg_perf_opt( 'enabled', 1 ) ) {
		return $meta;
	}
	if ( ! xg_perf_opt( 'img_webp', 1 ) && ! xg_perf_opt( 'img_avif', 0 ) ) {
		return $meta;
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) {
		return $meta;
	}
	xg_perf_make_variants( $file );
	if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		$dir = trailingslashit( dirname( $file ) );
		foreach ( $meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				xg_perf_make_variants( $dir . $size['file'] );
			}
		}
	}
	return $meta;
}, 20, 2 );

/**
 * HTML-buffer: width/height toevoegen + <img> wrappen in <picture> met
 * moderne bron als die bestaat én de browser het accepteert.
 */
function xg_perf_images_rewrite( $html ) {
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
	$can_avif = ( false !== strpos( $accept, 'image/avif' ) ) && xg_perf_opt( 'img_avif', 0 );
	$can_webp = ( false !== strpos( $accept, 'image/webp' ) ) && xg_perf_opt( 'img_webp', 1 );
	$add_dims = (bool) xg_perf_opt( 'img_dims', 1 );

	return preg_replace_callback( '#<img\b[^>]*>#i', function ( $m ) use ( $can_avif, $can_webp, $add_dims ) {
		$tag = $m[0];
		// src eruit halen.
		if ( ! preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $tag, $sm ) ) {
			return $tag;
		}
		$src  = $sm[1];
		$path = xg_perf_local_path( $src );

		// width/height aanvullen (CLS) als ze ontbreken en het bestand lokaal is.
		if ( $add_dims && $path && ! preg_match( '/\swidth=/i', $tag ) && ! preg_match( '/\sheight=/i', $tag ) ) {
			$dim = xg_perf_img_dims( $path );
			if ( $dim ) {
				$tag = preg_replace( '/<img\b/i', '<img width="' . $dim[0] . '" height="' . $dim[1] . '"', $tag, 1 );
			}
		}

		// Geen lokale bron, of geen moderne variant gewenst → klaar.
		if ( ! $path || ( ! $can_avif && ! $can_webp ) ) {
			return $tag;
		}
		// data-uri / svg overslaan.
		if ( preg_match( '/\.(svg|gif)(\?|$)/i', $src ) ) {
			return $tag;
		}
		$sources = '';
		if ( $can_avif && file_exists( $path . '.avif' ) ) {
			$sources .= '<source type="image/avif" srcset="' . esc_attr( $src . '.avif' ) . '">';
		}
		if ( $can_webp && file_exists( $path . '.webp' ) ) {
			$sources .= '<source type="image/webp" srcset="' . esc_attr( $src . '.webp' ) . '">';
		}
		if ( '' === $sources ) {
			return $tag;
		}
		return '<picture>' . $sources . $tag . '</picture>';
	}, $html );
}

/** Afmetingen van een lokaal beeld (gecachet per pad+mtime). */
function xg_perf_img_dims( $path ) {
	$key = 'xgdim_' . md5( $path . '|' . (string) @filemtime( $path ) ); // phpcs:ignore
	$c   = get_transient( $key );
	if ( false !== $c ) {
		return $c ? $c : null;
	}
	$size = @getimagesize( $path ); // phpcs:ignore
	$val  = ( $size && ! empty( $size[0] ) && ! empty( $size[1] ) ) ? array( (int) $size[0], (int) $size[1] ) : array();
	set_transient( $key, $val, WEEK_IN_SECONDS );
	return $val ? $val : null;
}

/* Bulk: bestaande media in batches verwerken (vanaf de admin-knop). */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || empty( $_GET['xg_perf_action'] ) || 'img_bulk' !== $_GET['xg_perf_action'] ) {
		return;
	}
	check_admin_referer( 'xg_perf_action' );
	$batch = get_posts( array(
		'post_type'      => 'attachment',
		'post_mime_type' => array( 'image/jpeg', 'image/png' ),
		'post_status'    => 'inherit',
		'posts_per_page' => 25,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore
			array( 'key' => '_xg_perf_done', 'compare' => 'NOT EXISTS' ),
		),
	) );
	$n = 0;
	foreach ( $batch as $id ) {
		$file = get_attached_file( $id );
		if ( $file ) {
			xg_perf_make_variants( $file );
			$meta = wp_get_attachment_metadata( $id );
			if ( ! empty( $meta['sizes'] ) ) {
				$dir = trailingslashit( dirname( $file ) );
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						xg_perf_make_variants( $dir . $size['file'] );
					}
				}
			}
		}
		update_post_meta( $id, '_xg_perf_done', 1 );
		$n++;
	}
	update_option( 'xg_perf_img_done', (int) get_option( 'xg_perf_img_done', 0 ) + $n, false );
	$more = count( $batch ) >= 25;
	set_transient( 'xg_perf_admin_msg', $n . ' afbeeldingen verwerkt.' . ( $more ? ' Klik nogmaals voor de volgende batch.' : ' Klaar.' ), 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=xg-perf' ) );
	exit;
} );
