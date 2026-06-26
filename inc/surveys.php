<?php
/**
 * XGOUD Surveys — enquêtes/vragenlijsten.
 *
 * Beheerder maakt een survey met vragen (enkel/meervoudig/rating/tekst). Klanten
 * vullen 'm in (op een deelbare /vragenlijst/-pagina of via een blok) en krijgen
 * spaarpunten. Resultaten worden geaggregeerd in het backend, gaan in de KPI's,
 * dagelijkse taken en de AI-context — zodat de site beter te sturen is.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CPT's
===================================================================== */
add_action( 'init', function () {
	register_post_type( 'xg_survey', array(
		'labels'       => array( 'name' => "Vragenlijsten", 'singular_name' => 'Vragenlijst', 'menu_name' => 'Vragenlijsten', 'add_new_item' => 'Nieuwe vragenlijst' ),
		'public'       => true,
		'has_archive'  => false,
		'show_in_menu' => 'xgoud',
		'menu_icon'    => 'dashicons-feedback',
		'rewrite'      => array( 'slug' => 'vragenlijst' ),
		'supports'     => array( 'title', 'editor' ),
	) );
	// Reacties (intern, niet publiek).
	register_post_type( 'xg_survey_response', array(
		'labels'       => array( 'name' => 'Survey-reacties', 'singular_name' => 'Reactie' ),
		'public'       => false,
		'show_ui'      => false,
	) );
} );

/** Puntenwaarde voor een ingevulde vragenlijst (per survey instelbaar). */
function ekinese_survey_points( $survey_id ) {
	$p = (int) get_post_meta( $survey_id, 'points', true );
	if ( $p > 0 ) {
		return $p;
	}
	$rules = function_exists( 'ekinese_reward_rules' ) ? ekinese_reward_rules() : array();
	return isset( $rules['survey'] ) ? (int) $rules['survey'] : 30;
}
add_filter( 'ekinese_reward_rules', function ( $r ) {
	if ( ! isset( $r['survey'] ) ) {
		$r['survey'] = 30;
	}
	return $r;
} );

/** Vragen van een survey (genormaliseerd). */
function ekinese_survey_questions( $survey_id ) {
	$raw = json_decode( (string) get_post_meta( $survey_id, 'questions', true ), true );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $i => $q ) {
		if ( empty( $q['text'] ) ) {
			continue;
		}
		$type = in_array( ( $q['type'] ?? 'single' ), array( 'single', 'multi', 'rating', 'text' ), true ) ? $q['type'] : 'single';
		$out[] = array(
			'id'       => 'q' . $i,
			'text'     => sanitize_text_field( $q['text'] ),
			'type'     => $type,
			'required' => ! empty( $q['required'] ),
			'options'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $q['options'] ?? array() ) ) ) ),
		);
	}
	return $out;
}

function ekinese_survey_is_open( $survey_id ) {
	return 'closed' !== get_post_meta( $survey_id, 'survey_status', true ) && 'publish' === get_post_status( $survey_id );
}

/* =====================================================================
   REST
===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ekinese/v1', '/surveys/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_survey_get',
	) );
	register_rest_route( 'ekinese/v1', '/surveys/submit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'ekinese_survey_submit',
	) );
} );

function ekinese_survey_get( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( get_post_type( $id ) !== 'xg_survey' ) {
		return new WP_Error( 'notfound', 'Onbekende vragenlijst.', array( 'status' => 404 ) );
	}
	return rest_ensure_response( array(
		'id'        => $id,
		'title'     => get_the_title( $id ),
		'intro'     => wp_strip_all_tags( get_post_field( 'post_content', $id ) ),
		'open'      => ekinese_survey_is_open( $id ),
		'points'    => ekinese_survey_points( $id ),
		'questions' => ekinese_survey_questions( $id ),
	) );
}

function ekinese_survey_submit( WP_REST_Request $req ) {
	if ( ! empty( $req->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'ok' => true ) ); // honeypot
	}
	$id    = (int) $req->get_param( 'survey' );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$token = (string) $req->get_param( 'token' );
	if ( $token && function_exists( 'ekinese_account_verify_token' ) ) {
		$te = ekinese_account_verify_token( $token );
		if ( $te ) {
			$email = $te;
		}
	}
	if ( get_post_type( $id ) !== 'xg_survey' || ! ekinese_survey_is_open( $id ) ) {
		return new WP_Error( 'closed', 'Deze vragenlijst is gesloten.', array( 'status' => 400 ) );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'email', 'Geldig e-mailadres vereist voor de punten.', array( 'status' => 400 ) );
	}
	// Eén reactie per e-mail per survey.
	$dupe = get_posts( array(
		'post_type'   => 'xg_survey_response',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'AND',
			array( 'key' => 'survey', 'value' => $id ),
			array( 'key' => 'email', 'value' => $email ),
		),
	) );
	if ( $dupe ) {
		return new WP_Error( 'dupe', 'Je hebt deze vragenlijst al ingevuld.', array( 'status' => 409 ) );
	}

	$questions = ekinese_survey_questions( $id );
	$answers   = $req->get_param( 'answers' );
	$answers   = is_array( $answers ) ? $answers : json_decode( (string) $answers, true );
	$answers   = is_array( $answers ) ? $answers : array();
	$clean     = array();
	foreach ( $questions as $q ) {
		$val = $answers[ $q['id'] ] ?? '';
		if ( 'multi' === $q['type'] ) {
			$val = array_values( array_filter( array_map( 'sanitize_text_field', (array) $val ) ) );
		} elseif ( 'rating' === $q['type'] ) {
			$val = max( 0, min( 5, (int) $val ) );
		} else {
			$val = sanitize_text_field( (string) $val );
		}
		if ( $q['required'] && ( '' === $val || array() === $val || 0 === $val ) ) {
			return new WP_Error( 'required', 'Beantwoord alle verplichte vragen.', array( 'status' => 400 ) );
		}
		$clean[ $q['id'] ] = $val;
	}

	$rid = wp_insert_post( array(
		'post_type'   => 'xg_survey_response',
		'post_status' => 'publish',
		'post_title'  => 'Reactie · ' . get_the_title( $id ) . ' · ' . gmdate( 'Y-m-d H:i' ),
	), true );
	if ( is_wp_error( $rid ) ) {
		return new WP_Error( 'save', 'Opslaan mislukt.', array( 'status' => 500 ) );
	}
	update_post_meta( $rid, 'survey', $id );
	update_post_meta( $rid, 'email', $email );
	update_post_meta( $rid, 'answers', wp_json_encode( $clean ) );

	$pts = ekinese_survey_points( $id );
	if ( function_exists( 'ekinese_award_points' ) ) {
		ekinese_award_points( $email, $pts, 'survey', 'survey#' . $id );
	}
	return rest_ensure_response( array( 'ok' => true, 'awarded' => $pts, 'message' => 'Bedankt voor het invullen! Je ontvangt ' . $pts . ' spaarpunten.' ) );
}

/* =====================================================================
   FRONT-END  (blok + single-template)
===================================================================== */
add_action( 'init', function () {
	register_block_type( 'ekinese/survey', array(
		'attributes'      => array( 'id' => array( 'type' => 'number', 'default' => 0 ) ),
		'render_callback' => 'ekinese_render_survey',
	) );
} );

function ekinese_render_survey( $attrs ) {
	$id = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
	if ( ! $id && is_singular( 'xg_survey' ) ) {
		$id = get_the_ID();
	}
	if ( get_post_type( $id ) !== 'xg_survey' ) {
		return '';
	}
	$rest  = esc_url_raw( rest_url( 'ekinese/v1/surveys/' . $id ) );
	$post  = esc_url_raw( rest_url( 'ekinese/v1/surveys/submit' ) );
	$share = esc_url_raw( rest_url( 'ekinese/v1/reward/share' ) );
	$url   = esc_url( get_permalink( $id ) );
	return '<section class="xg-survey" data-id="' . esc_attr( $id ) . '" data-rest="' . esc_attr( $rest )
		. '" data-submit="' . esc_attr( $post ) . '" data-share="' . esc_attr( $share ) . '" data-url="' . esc_attr( $url ) . '">'
		. '<div class="xg-container"><div class="xg-survey-box">Vragenlijst laden…</div></div></section>';
}

/* Survey-pagina (single) automatisch het formulier tonen. */
add_filter( 'the_content', function ( $content ) {
	if ( is_singular( 'xg_survey' ) && in_the_loop() && is_main_query() ) {
		return $content . ekinese_render_survey( array( 'id' => get_the_ID() ) );
	}
	return $content;
} );

add_action( 'wp_enqueue_scripts', function () {
	$present = is_singular( 'xg_survey' );
	if ( ! $present && is_singular() ) {
		$p = get_post();
		$present = $p && has_block( 'ekinese/survey', $p );
	}
	if ( ! $present ) {
		return;
	}
	$css = get_theme_file_path( 'assets/css/surveys.css' );
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'ekinese-surveys', get_theme_file_uri( 'assets/css/surveys.css' ), array(), (string) filemtime( $css ) );
	}
	$js = get_theme_file_path( 'assets/js/surveys.js' );
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'ekinese-surveys', get_theme_file_uri( 'assets/js/surveys.js' ), array(), (string) filemtime( $js ), true );
	}
} );

/* =====================================================================
   ADMIN: vragen-editor + instellingen (metabox)
===================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'xg_survey_builder', 'Vragen & instellingen', 'ekinese_survey_metabox', 'xg_survey', 'normal', 'high' );
	add_meta_box( 'xg_survey_results', 'Resultaten', 'ekinese_survey_results_metabox', 'xg_survey', 'side', 'default' );
} );

function ekinese_survey_metabox( $post ) {
	wp_nonce_field( 'xg_survey_save', 'xg_survey_nonce' );
	$questions = get_post_meta( $post->ID, 'questions', true );
	$status    = get_post_meta( $post->ID, 'survey_status', true ) ?: 'open';
	$points    = (int) get_post_meta( $post->ID, 'points', true );
	?>
	<p>
		<label><strong>Status</strong>
			<select name="xg_survey_status">
				<option value="open" <?php selected( $status, 'open' ); ?>>Open (kan ingevuld worden)</option>
				<option value="closed" <?php selected( $status, 'closed' ); ?>>Gesloten</option>
			</select>
		</label>
		&nbsp;&nbsp;
		<label><strong>Punten per invulling</strong>
			<input type="number" name="xg_survey_points" min="0" value="<?php echo esc_attr( $points ); ?>" class="small-text" placeholder="30">
		</label>
	</p>
	<div id="xg-survey-builder" data-questions="<?php echo esc_attr( $questions ); ?>"></div>
	<textarea name="xg_survey_questions" id="xg-survey-questions" style="display:none"><?php echo esc_textarea( $questions ); ?></textarea>
	<p class="description">Typen: <strong>Enkele keuze</strong> (radio), <strong>Meerkeuze</strong> (checkbox), <strong>Rating</strong> (1–5 sterren), <strong>Tekst</strong>.</p>
	<?php
}

function ekinese_survey_results_metabox( $post ) {
	$agg = ekinese_survey_aggregate( $post->ID );
	echo '<p><strong>' . esc_html( $agg['count'] ) . '</strong> reactie(s)</p>';
	if ( $agg['count'] ) {
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=xg-survey-results&survey=' . $post->ID ) ) . '">Volledige resultaten →</a></p>';
	}
}

add_action( 'save_post_xg_survey', function ( $post_id ) {
	if ( ! isset( $_POST['xg_survey_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['xg_survey_nonce'] ), 'xg_survey_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'survey_status', isset( $_POST['xg_survey_status'] ) && 'closed' === $_POST['xg_survey_status'] ? 'closed' : 'open' );
	update_post_meta( $post_id, 'points', isset( $_POST['xg_survey_points'] ) ? max( 0, (int) $_POST['xg_survey_points'] ) : 0 );
	// Vragen-JSON: valideren/normaliseren voor opslag.
	$raw = isset( $_POST['xg_survey_questions'] ) ? wp_unslash( $_POST['xg_survey_questions'] ) : '[]';
	$arr = json_decode( (string) $raw, true );
	$norm = array();
	if ( is_array( $arr ) ) {
		foreach ( $arr as $q ) {
			if ( empty( $q['text'] ) ) {
				continue;
			}
			$norm[] = array(
				'text'     => sanitize_text_field( $q['text'] ),
				'type'     => in_array( ( $q['type'] ?? 'single' ), array( 'single', 'multi', 'rating', 'text' ), true ) ? $q['type'] : 'single',
				'required' => ! empty( $q['required'] ),
				'options'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $q['options'] ?? array() ) ) ) ),
			);
		}
	}
	update_post_meta( $post_id, 'questions', wp_json_encode( $norm ) );
} );

/* Builder-JS in de editor laden. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post;
	if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && $post && 'xg_survey' === $post->post_type ) {
		$js = get_theme_file_path( 'assets/js/admin-surveys.js' );
		if ( file_exists( $js ) ) {
			wp_enqueue_script( 'ekinese-admin-surveys', get_theme_file_uri( 'assets/js/admin-surveys.js' ), array(), (string) filemtime( $js ), true );
		}
	}
} );

/* =====================================================================
   AGGREGATIE + RESULTATEN-PAGINA
===================================================================== */
function ekinese_survey_aggregate( $survey_id ) {
	$responses = get_posts( array(
		'post_type'   => 'xg_survey_response',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => 'survey',
		'meta_value'  => $survey_id,
	) );
	$questions = ekinese_survey_questions( $survey_id );
	$tally     = array();
	foreach ( $questions as $q ) {
		$tally[ $q['id'] ] = array( 'q' => $q, 'counts' => array(), 'texts' => array(), 'rating_sum' => 0, 'rating_n' => 0 );
	}
	foreach ( $responses as $rid ) {
		$ans = json_decode( (string) get_post_meta( $rid, 'answers', true ), true );
		if ( ! is_array( $ans ) ) {
			continue;
		}
		foreach ( $questions as $q ) {
			$v = $ans[ $q['id'] ] ?? null;
			if ( null === $v ) {
				continue;
			}
			if ( 'multi' === $q['type'] ) {
				foreach ( (array) $v as $opt ) {
					$tally[ $q['id'] ]['counts'][ $opt ] = ( $tally[ $q['id'] ]['counts'][ $opt ] ?? 0 ) + 1;
				}
			} elseif ( 'rating' === $q['type'] ) {
				$tally[ $q['id'] ]['rating_sum'] += (int) $v;
				$tally[ $q['id'] ]['rating_n']   += 1;
			} elseif ( 'text' === $q['type'] ) {
				if ( '' !== $v ) {
					$tally[ $q['id'] ]['texts'][] = $v;
				}
			} else {
				$tally[ $q['id'] ]['counts'][ $v ] = ( $tally[ $q['id'] ]['counts'][ $v ] ?? 0 ) + 1;
			}
		}
	}
	return array( 'count' => count( $responses ), 'tally' => $tally );
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'xgoud', 'Survey-resultaten', 'Survey-resultaten', 'manage_options', 'xg-survey-results', 'ekinese_survey_results_page' );
}, 51 );

function ekinese_survey_results_page() {
	$surveys = get_posts( array( 'post_type' => 'xg_survey', 'numberposts' => -1, 'post_status' => array( 'publish', 'draft' ) ) );
	$sel     = isset( $_GET['survey'] ) ? (int) $_GET['survey'] : ( $surveys ? $surveys[0]->ID : 0 );
	echo '<div class="wrap"><h1>Survey-resultaten</h1>';
	echo '<form method="get"><input type="hidden" name="page" value="xg-survey-results"><select name="survey" onchange="this.form.submit()">';
	foreach ( $surveys as $s ) {
		echo '<option value="' . esc_attr( $s->ID ) . '" ' . selected( $sel, $s->ID, false ) . '>' . esc_html( get_the_title( $s ) ) . '</option>';
	}
	echo '</select></form>';
	if ( ! $sel ) {
		echo '<p>Nog geen vragenlijsten.</p></div>';
		return;
	}
	$agg = ekinese_survey_aggregate( $sel );
	echo '<p><strong>' . esc_html( $agg['count'] ) . '</strong> reactie(s) · status: ' . ( ekinese_survey_is_open( $sel ) ? 'open' : 'gesloten' ) . '</p>';
	foreach ( $agg['tally'] as $t ) {
		$q = $t['q'];
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;margin:0 0 12px">';
		echo '<h3 style="margin:0 0 10px">' . esc_html( $q['text'] ) . ' <span style="color:#646970;font-weight:400">(' . esc_html( $q['type'] ) . ')</span></h3>';
		if ( 'rating' === $q['type'] ) {
			$avg = $t['rating_n'] ? round( $t['rating_sum'] / $t['rating_n'], 2 ) : 0;
			echo '<p>Gemiddelde score: <strong>' . esc_html( $avg ) . '</strong> / 5 (' . esc_html( $t['rating_n'] ) . ' stemmen)</p>';
		} elseif ( 'text' === $q['type'] ) {
			if ( $t['texts'] ) {
				echo '<ul style="margin:0">';
				foreach ( array_slice( $t['texts'], 0, 50 ) as $tx ) {
					echo '<li>' . esc_html( $tx ) . '</li>';
				}
				echo '</ul>';
			} else {
				echo '<p>Geen antwoorden.</p>';
			}
		} else {
			$total = array_sum( $t['counts'] );
			arsort( $t['counts'] );
			foreach ( $t['counts'] as $opt => $n ) {
				$pct = $total ? round( $n / $total * 100 ) : 0;
				echo '<div style="margin:4px 0"><div style="display:flex;justify-content:space-between"><span>' . esc_html( $opt ) . '</span><span>' . esc_html( $n ) . ' (' . esc_html( $pct ) . '%)</span></div>';
				echo '<div style="background:#eee;height:8px"><div style="width:' . esc_attr( $pct ) . '%;height:8px;background:#AE1E1E"></div></div></div>';
			}
			if ( ! $total ) {
				echo '<p>Geen antwoorden.</p>';
			}
		}
		echo '</div>';
	}
	echo '</div>';
}

/* =====================================================================
   STATISTIEKEN: KPI, dagelijkse taken, AI-context, account
===================================================================== */
add_filter( 'ekinese_daily_tasks_extra', function ( $tasks ) {
	$open = count( get_posts( array(
		'post_type'   => 'xg_survey',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'survey_status', 'value' => 'closed', 'compare' => '!=' ) ),
	) ) );
	// Nieuwe reacties laatste 7 dagen.
	$recent = count( get_posts( array(
		'post_type'     => 'xg_survey_response',
		'numberposts'   => -1,
		'fields'        => 'ids',
		'date_query'    => array( array( 'after' => '7 days ago' ) ),
	) ) );
	if ( $recent > 0 ) {
		$tasks[] = array( 'key' => 'survey_resp', 'label' => 'Nieuwe survey-reacties bekijken (7d)', 'count' => $recent, 'link' => admin_url( 'admin.php?page=xg-survey-results' ) );
	}
	if ( 0 === $open ) {
		$tasks[] = array( 'key' => 'survey_none', 'label' => 'Geen open vragenlijst — maak er een voor inzicht', 'count' => 1, 'link' => admin_url( 'post-new.php?post_type=xg_survey' ) );
	}
	return $tasks;
} );

add_filter( 'ekinese_dashboard_ai_context_lines', function ( $lines ) {
	$surveys = get_posts( array( 'post_type' => 'xg_survey', 'numberposts' => 5, 'post_status' => 'publish' ) );
	foreach ( $surveys as $s ) {
		$agg = ekinese_survey_aggregate( $s->ID );
		if ( ! $agg['count'] ) {
			continue;
		}
		$bits = array();
		foreach ( $agg['tally'] as $t ) {
			if ( 'rating' === $t['q']['type'] && $t['rating_n'] ) {
				$bits[] = $t['q']['text'] . ': ' . round( $t['rating_sum'] / $t['rating_n'], 1 ) . '/5';
			} elseif ( ! empty( $t['counts'] ) ) {
				arsort( $t['counts'] );
				$top = array_key_first( $t['counts'] );
				$bits[] = $t['q']['text'] . ' → "' . $top . '"';
			}
		}
		if ( $bits ) {
			$lines[] = 'Vragenlijst "' . get_the_title( $s ) . '" (' . $agg['count'] . ' reacties): ' . implode( '; ', array_slice( $bits, 0, 4 ) ) . '.';
		}
	}
	return $lines;
} );

/* Open vragenlijsten in Mijn XGOUD (voor een prompt-widget). */
add_filter( 'ekinese_account_data', function ( $data, $email ) {
	$open = get_posts( array(
		'post_type'   => 'xg_survey',
		'post_status' => 'publish',
		'numberposts' => 5,
		'meta_query'  => array( array( 'key' => 'survey_status', 'value' => 'closed', 'compare' => '!=' ) ),
	) );
	$done = get_posts( array(
		'post_type'   => 'xg_survey_response',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => 'email',
		'meta_value'  => $email,
	) );
	$done_surveys = array();
	foreach ( $done as $rid ) {
		$done_surveys[] = (int) get_post_meta( $rid, 'survey', true );
	}
	$todo = array();
	foreach ( $open as $s ) {
		if ( ! in_array( $s->ID, $done_surveys, true ) ) {
			$todo[] = array( 'id' => $s->ID, 'title' => get_the_title( $s ), 'url' => get_permalink( $s ), 'points' => ekinese_survey_points( $s->ID ) );
		}
	}
	$data['surveys']      = $todo;
	$data['surveys_done'] = count( $done );
	return $data;
}, 17, 2 );
