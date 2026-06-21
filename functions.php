<?php
/**
 * Ekinese – Theme-Funktionen.
 *
 * In einem Block-Theme ist functions.php optional. Wir nutzen sie nur für
 * Dinge, die sich nicht über theme.json / Templates abbilden lassen:
 * Theme-Supports, eigene Pattern-Kategorie, Block-Styles und Asset-Loading.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EKINESE_VERSION', wp_get_theme()->get( 'Version' ) );

require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/patterns.php' );
require_once get_theme_file_path( 'inc/block-styles.php' );
require_once get_theme_file_path( 'inc/taxonomies.php' );
require_once get_theme_file_path( 'inc/offices.php' );
require_once get_theme_file_path( 'inc/scheduling.php' );
require_once get_theme_file_path( 'inc/charity.php' );
require_once get_theme_file_path( 'inc/chat.php' );
require_once get_theme_file_path( 'inc/blueprint-sections.php' );
require_once get_theme_file_path( 'inc/products.php' );
require_once get_theme_file_path( 'inc/diamond.php' );
require_once get_theme_file_path( 'inc/newsletter.php' );
