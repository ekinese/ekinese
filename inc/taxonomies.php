<?php
/**
 * Verkopen-Landingpages: 4-stufige Kategorie-Taxonomie.
 *
 * Struktur (4 Ebenen über parent/child):
 *   1. Hauptkategorie   – z. B. Edelmetalen, Edelstenen, Horloges
 *   2. Produktgruppe    – z. B. Goud, Diamanten, Heren/Dames
 *   3. Untertyp         – z. B. Baren, Losse diamanten, Horloge-Merk
 *   4. Produkt          – z. B. konkretes Produkt/Modell
 *
 * Jede Ebene ist ein normaler Term dieser Taxonomie, verschachtelt über
 * "parent". Die Inhalte je Term (Hero, Text etc.) hängen NICHT an dieser
 * Datei, sondern an Term-Meta – Felder können sich ändern, ohne dass die
 * Struktur hier angepasst werden muss.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomie "xg_verkoop_cat" registrieren.
 */
function ekinese_register_verkoop_taxonomy() {
	register_taxonomy(
		'xg_verkoop_cat',
		array( 'page' ),
		array(
			// Hinweis: Der öffentliche URL-Namespace "/verkopen/" wird jetzt vom
			// hierarchischen WP-Seitenbaum (inc/installer.php) + dem Produkt-Rewrite
			// (inc/verkopen.php) belegt. Diese (ungenutzte) Taxonomie wird daher
			// nicht-öffentlich/ohne Rewrite gehalten, um Slug-Kollisionen zu vermeiden.
			'hierarchical'      => true,
			'public'            => false,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
			'labels'            => array(
				'name'          => __( 'Verkoop-Kategorien', 'ekinese' ),
				'singular_name' => __( 'Verkoop-Kategorie', 'ekinese' ),
				'parent_item'   => __( 'Übergeordnete Kategorie', 'ekinese' ),
				'add_new_item'  => __( 'Neue Verkoop-Kategorie', 'ekinese' ),
			),
		)
	);
}
add_action( 'init', 'ekinese_register_verkoop_taxonomy' );

/**
 * Term-Meta-Felder registrieren, damit sie im Editor (REST) verfügbar sind.
 * Welche Felder tatsächlich genutzt/angezeigt werden, ist Inhaltssache und
 * kann sich ändern – die Registrierung hier betrifft nur die Datenhaltung.
 */
function ekinese_register_verkoop_term_meta() {
	$fields = array( 'hero_title', 'hero_text', 'cta_text', 'cta_link', 'body_content' );

	foreach ( $fields as $field ) {
		register_term_meta(
			'xg_verkoop_cat',
			$field,
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}
}
add_action( 'init', 'ekinese_register_verkoop_term_meta' );
