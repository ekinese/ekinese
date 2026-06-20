<?php
/**
 * Eigene Block-Styles registrieren.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beispiel-Block-Styles, die Redakteure im Editor auswählen können.
 */
function ekinese_register_block_styles() {
	register_block_style(
		'core/group',
		array(
			'name'  => 'card',
			'label' => __( 'Karte', 'ekinese' ),
		)
	);

	register_block_style(
		'core/image',
		array(
			'name'  => 'rounded-lg',
			'label' => __( 'Stark abgerundet', 'ekinese' ),
		)
	);
}
add_action( 'init', 'ekinese_register_block_styles' );
