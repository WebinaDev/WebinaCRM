<?php
/**
 * Copyright Part Template
 * 
 * Displays copyright information
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$copyright_text = isset( $component_data['copyright_text'] ) ? $component_data['copyright_text'] : '';
?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo wp_kses_post( $copyright_text );
?>

