<?php
/**
 * Base class for Webina Elementor widgets.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WebinoCRM_Site_Widget_Base extends \Elementor\Widget_Base {

	/**
	 * @return array<int,string>
	 */
	public function get_categories() {
		return array( 'webina-vip' );
	}

	/**
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( 'webina', 'vip' );
	}

	/**
	 * Kit-aware CSS variables wrapper.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	protected function render_widget_body( array $settings ) {
		// Override in child widgets.
	}

	/**
	 * @return array<string,string>
	 */
	protected function kit_palette() {
		if ( class_exists( 'WebinoCRM_Site_Kit' ) ) {
			return WebinoCRM_Site_Kit::palette();
		}
		return array();
	}
}
