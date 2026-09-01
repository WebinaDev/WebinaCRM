<?php
/**
 * Elementor widget: Client Marquee
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_ClientMarquee extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_client_marquee';
	}

	public function get_title() {
		return __( 'Client Marquee', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-carousel';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$logos = (array) ( $brand['client_logos'] ?? array( 'Partner A', 'Partner B', 'Partner C', 'Partner D' ) );
		echo '<div class="webina-widget webina-widget--client-marquee"><div class="webina-client-marquee"><div class="webina-client-marquee__track">';
		foreach ( $logos as $logo ) {
			echo '<span class="webina-client-marquee__item">' . esc_html( (string) $logo ) . '</span>';
		}
		echo '</div></div></div>';
	}
}
