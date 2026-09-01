<?php
/**
 * Elementor widget: CEO Quote
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_CeoQuote extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_ceo_quote';
	}

	public function get_title() {
		return __( 'CEO Quote', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'سخن مدیر عامل', 'webinocrm' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		echo '<div class="webina-widget webina-widget--ceo-quote">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		echo '<div class="webina-ceo-quote">';
		echo '<div class="webina-ceo-quote__visual" aria-hidden="true"></div>';
		echo '<div><blockquote>' . esc_html( (string) ( $brand['ceo_quote'] ?? '' ) ) . '</blockquote>';
		echo '<cite>' . esc_html( (string) ( $brand['ceo_name'] ?? '' ) );
		echo '<span>' . esc_html( (string) ( $brand['ceo_title'] ?? '' ) ) . '</span></cite></div>';
		echo '</div></div>';
	}
}
