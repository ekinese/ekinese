<?php
/**
 * Eigene Pattern-Kategorie registrieren.
 *
 * Pattern-Dateien selbst liegen im Ordner /patterns und werden von WordPress
 * automatisch geladen (ab WP 6.0). Hier definieren wir nur die Kategorie,
 * unter der sie im Inserter erscheinen.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pattern-Kategorie "Ekinese" anlegen.
 */
function ekinese_register_pattern_categories() {
	register_block_pattern_category(
		'ekinese',
		array(
			'label'       => __( 'Ekinese', 'ekinese' ),
			'description' => __( 'Layout-Blaupausen und Bausteine des Ekinese-Themes.', 'ekinese' ),
		)
	);
}
add_action( 'init', 'ekinese_register_pattern_categories' );
