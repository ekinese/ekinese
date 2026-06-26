<?php
/**
 * XGOUD Optimalisaties (AI-ondersteund).
 *
 * Bundelt de statistiek-signalen van de hele site (zwakke SEO/GEO-pagina's,
 * content-gaten, social-engagement, surveys, operatie) tot één overzicht én laat
 * Claude er een geprioriteerde, uitvoerbare optimalisatielijst van maken.
 * Sluit de cyclus: hoe meer data we verzamelen, hoe gerichter de adviezen.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   STATISTIEK: zwakke pagina's + signalen
===================================================================== */
/** Pagina's/posts met een lage SEO- of GEO-score. */
function ekinese_opt_weak_pages( $max_score = 60, $limit = 12 ) {
	$rows = get_posts( array(
		'post_type'      => array( 'page', 'post' ),
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => '_xg_seo_score', 'value' => $max_score, 'compare' => '<', 'type' => 'NUMERIC' ),
			array( 'key' => '_xg_geo_score', 'value' => $max_score, 'compare' => '<', 'type' => 'NUMERIC' ),
		),
		'orderby'        => 'meta_value_num',
		'meta_key'       => '_xg_seo_score',
		'order'          => 'ASC',
	) );
	$out = array();
	foreach ( $rows as $p ) {
		$out[] = array(
			'id'    => $p->ID,
			'title' => get_the_title( $p ),
			'seo'   => (int) get_post_meta( $p->ID, '_xg_seo_score', true ),
			'geo'   => (int) get_post_meta( $p->ID, '_xg_geo_score', true ),
			'kw'    => (string) get_post_meta( $p->ID, '_xg_focus_keyword', true ),
			'edit'  => get_edit_post_link( $p->ID, '' ),
		);
	}
	return $out;
}

/** Verzamelde optimalisatie-statistieken. */
function ekinese_optimizer_stats() {
	global $wpdb;
	// Zwakke / ontbrekende SEO.
	$low_seo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_xg_seo_score' AND CAST(meta_value AS UNSIGNED) < 60" ); // phpcs:ignore
	$low_geo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_xg_geo_score' AND CAST(meta_value AS UNSIGNED) < 60" ); // phpcs:ignore
	// Pagina's/posts zonder focus-keyword.
	$published = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post')" ); // phpcs:ignore
	$with_kw   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_xg_focus_keyword' AND meta_value <> ''" ); // phpcs:ignore
	$no_kw     = max( 0, $published - $with_kw );

	$stats = array(
		'low_seo'    => $low_seo,
		'low_geo'    => $low_geo,
		'no_keyword' => $no_kw,
	);
	if ( function_exists( 'ekinese_si_unanswered_total' ) ) {
		$stats['social_unanswered'] = ekinese_si_unanswered_total();
	}
	if ( post_type_exists( 'xg_moment' ) ) {
		$stats['moments_pending'] = (int) wp_count_posts( 'xg_moment' )->pending;
	}
	if ( post_type_exists( 'xg_survey_response' ) ) {
		$stats['survey_responses_7d'] = count( get_posts( array( 'post_type' => 'xg_survey_response', 'numberposts' => -1, 'fields' => 'ids', 'date_query' => array( array( 'after' => '7 days ago' ) ) ) ) );
	}
	return apply_filters( 'ekinese_optimizer_stats', $stats );
}

/* Zwakke pagina's mee in de AI-context. */
add_filter( 'ekinese_dashboard_ai_context_lines', function ( $lines ) {
	$weak = ekinese_opt_weak_pages( 60, 8 );
	if ( $weak ) {
		$bits = array_map( function ( $w ) {
			return $w['title'] . ' (SEO ' . $w['seo'] . '/GEO ' . $w['geo'] . ( $w['kw'] ? '' : ', geen keyword' ) . ')';
		}, $weak );
		$lines[] = 'Zwakke pagina\'s (score <60): ' . implode( '; ', $bits ) . '.';
	}
	$s = ekinese_optimizer_stats();
	$lines[] = sprintf( 'Optimalisatie-signalen: %d zwakke SEO, %d zwakke GEO, %d zonder focus-keyword.', $s['low_seo'], $s['low_geo'], $s['no_keyword'] );
	return $lines;
} );

/* =====================================================================
   AJAX: optimalisatie-advies genereren
===================================================================== */
add_action( 'wp_ajax_xg_optimize', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'xg_optimize', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Niet toegestaan.' ), 403 );
	}
	if ( ! function_exists( 'ekinese_admin_ai_ask' ) ) {
		wp_send_json_error( array( 'message' => 'AI niet beschikbaar.' ), 400 );
	}
	$instruction = "Maak een GEPRIORITEERDE optimalisatielijst voor XGOUD op basis van de bedrijfsdata hierboven. "
		. "Maximaal 8 punten, gesorteerd op impact. Categorieën waar relevant: SEO/GEO, content, social media, conversie, operatie. "
		. "Geef per punt op één regel: [CATEGORIE] concrete actie — waarom, en (impact: hoog/midden/laag). "
		. "Wees concreet en uitvoerbaar; verzin geen cijfers; gebruik alleen de gegeven data.";
	$reply = ekinese_admin_ai_ask( $instruction );
	if ( is_wp_error( $reply ) ) {
		wp_send_json_error( array( 'message' => $reply->get_error_message() ), 400 );
	}
	update_option( 'xg_opt_last', array( 'time' => current_time( 'mysql' ), 'text' => $reply ), false );
	wp_send_json_success( array( 'reply' => $reply, 'time' => current_time( 'mysql' ) ) );
} );

/* =====================================================================
   ADMIN-PAGINA  "Optimalisaties"
===================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Optimalisaties (AI)', 'Optimalisaties (AI)', 'manage_options', 'xg-optimizer', 'ekinese_optimizer_page' );
}, 2 );

function ekinese_optimizer_page() {
	$s     = ekinese_optimizer_stats();
	$weak  = ekinese_opt_weak_pages( 60, 12 );
	$last  = get_option( 'xg_opt_last', array() );
	$nonce = wp_create_nonce( 'xg_optimize' );
	$ai_on = function_exists( 'ekinese_ai_enabled' ) ? ekinese_ai_enabled() : (bool) get_option( 'xg_anthropic_key' );

	$cards = array(
		array( 'Zwakke SEO-pagina\'s (<60)', $s['low_seo'] ?? 0 ),
		array( 'Zwakke GEO-pagina\'s (<60)', $s['low_geo'] ?? 0 ),
		array( 'Zonder focus-keyword', $s['no_keyword'] ?? 0 ),
	);
	if ( isset( $s['social_unanswered'] ) ) {
		$cards[] = array( 'Onbeantwoorde social-reacties', $s['social_unanswered'] );
	}
	if ( isset( $s['moments_pending'] ) ) {
		$cards[] = array( 'Momenten te modereren', $s['moments_pending'] );
	}
	if ( isset( $s['survey_responses_7d'] ) ) {
		$cards[] = array( 'Survey-reacties (7d)', $s['survey_responses_7d'] );
	}
	?>
	<div class="wrap">
		<h1>Optimalisaties (AI)</h1>
		<p>Eén plek voor alle verbeter-signalen van de site. De AI maakt er een geprioriteerde actielijst van — hoe meer data we verzamelen (SEO, social, surveys, verkopen), hoe gerichter het advies.</p>

		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0">
			<?php foreach ( $cards as $c ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;padding:16px">
					<div style="font-size:26px;font-weight:800;color:<?php echo ( (int) $c[1] > 0 ? '#AE1E1E' : '#1f9d55' ); ?>"><?php echo esc_html( $c[1] ); ?></div>
					<div style="font-size:13px;color:#646970"><?php echo esc_html( $c[0] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
			<div style="background:#fff;border:1px solid #dcdcde;padding:16px">
				<h2 style="margin-top:0;font-size:15px">AI-optimalisatieadvies</h2>
				<?php if ( ! $ai_on ) : ?>
					<p class="description">Verbind eerst Claude onder <a href="<?php echo esc_url( admin_url( 'admin.php?page=xg-ai' ) ); ?>">XGOUD → AI-assistent</a>.</p>
				<?php else : ?>
					<p><button type="button" class="button button-primary" id="xg-opt-run" data-nonce="<?php echo esc_attr( $nonce ); ?>">Genereer optimalisatie-advies</button>
					<span id="xg-opt-spin" style="display:none">⏳ Bezig…</span></p>
					<div id="xg-opt-out" style="white-space:pre-wrap;line-height:1.6"><?php
						if ( ! empty( $last['text'] ) ) {
							echo esc_html( $last['text'] ) . "\n\n";
							echo '<em style="color:#646970">Laatst: ' . esc_html( $last['time'] ) . '</em>';
						} else {
							echo '<span class="description">Nog geen advies gegenereerd.</span>';
						}
					?></div>
				<?php endif; ?>
			</div>

			<div style="background:#fff;border:1px solid #dcdcde;padding:16px">
				<h2 style="margin-top:0;font-size:15px">Zwakste pagina's</h2>
				<?php if ( ! $weak ) : ?>
					<p>🎉 Geen zwakke pagina's. Goed bezig!</p>
				<?php else : ?>
					<table class="widefat striped"><thead><tr><th>Pagina</th><th>SEO</th><th>GEO</th><th></th></tr></thead><tbody>
						<?php foreach ( $weak as $w ) :
							$badge = function ( $v ) { $c = $v >= 80 ? '#1f9d55' : ( $v >= 50 ? '#dba617' : '#d63638' ); return '<span style="background:' . $c . ';color:#fff;padding:1px 6px;border-radius:3px">' . (int) $v . '</span>'; };
							?>
							<tr>
								<td><?php echo esc_html( $w['title'] ); ?><?php echo $w['kw'] ? '' : ' <span style="color:#b32d2e;font-size:11px">geen keyword</span>'; ?></td>
								<td><?php echo $badge( $w['seo'] ); // phpcs:ignore ?></td>
								<td><?php echo $badge( $w['geo'] ); // phpcs:ignore ?></td>
								<td><?php if ( $w['edit'] ) : ?><a href="<?php echo esc_url( $w['edit'] ); ?>">Bewerk</a><?php endif; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script>
	(function(){
		var btn=document.getElementById('xg-opt-run'); if(!btn) return;
		btn.addEventListener('click',function(){
			var out=document.getElementById('xg-opt-out'), spin=document.getElementById('xg-opt-spin');
			btn.disabled=true; spin.style.display='inline';
			var body=new URLSearchParams(); body.append('action','xg_optimize'); body.append('nonce',btn.getAttribute('data-nonce'));
			fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
				.then(function(r){return r.json();})
				.then(function(j){ btn.disabled=false; spin.style.display='none';
					if(j&&j.success){ out.textContent=j.data.reply; } else { out.textContent=(j&&j.data&&j.data.message)||'Mislukt.'; } })
				.catch(function(){ btn.disabled=false; spin.style.display='none'; out.textContent='Netwerkfout.'; });
		});
	})();
	</script>
	<?php
}
