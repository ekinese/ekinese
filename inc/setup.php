<?php
/**
 * Theme-Setup: Supports und Asset-Loading.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme-Supports registrieren.
 */
function ekinese_setup() {
	// Übersetzungen.
	load_theme_textdomain( 'ekinese', get_template_directory() . '/languages' );

	// Standard-Block-Theme-Supports.
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// Zusätzliche Editor-Stylesheets laden (für Front- und Editor-Konsistenz).
	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'ekinese_setup' );

/**
 * Frontend-Assets laden.
 */
function ekinese_enqueue_assets() {
	wp_enqueue_style(
		'ekinese-style',
		get_stylesheet_uri(),
		array(),
		EKINESE_VERSION
	);

	$main_css = get_theme_file_path( 'assets/css/main.css' );
	if ( file_exists( $main_css ) ) {
		wp_enqueue_style(
			'ekinese-main',
			get_theme_file_uri( 'assets/css/main.css' ),
			array(),
			(string) filemtime( $main_css )
		);
	}

	// Header & Footer System (eigenständige Komponente: Ticker, Mega-Menu, Suche).
	$hf_css = get_theme_file_path( 'assets/css/header-footer.css' );
	if ( file_exists( $hf_css ) ) {
		wp_enqueue_style(
			'ekinese-header-footer',
			get_theme_file_uri( 'assets/css/header-footer.css' ),
			array(),
			(string) filemtime( $hf_css )
		);
	}

	$hf_js = get_theme_file_path( 'assets/js/header-footer.js' );
	if ( file_exists( $hf_js ) ) {
		wp_enqueue_script(
			'ekinese-header-footer',
			get_theme_file_uri( 'assets/js/header-footer.js' ),
			array(),
			(string) filemtime( $hf_js ),
			true // im Footer laden
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ekinese_enqueue_assets' );
