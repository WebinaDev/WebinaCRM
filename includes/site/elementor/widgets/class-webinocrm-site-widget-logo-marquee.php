<?php
/**
 * Elementor widget: Logo Marquee
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_LogoMarquee extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_logo_marquee';
	}

	public function get_title() {
		return __( 'Logo Marquee', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-carousel';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array(
			'label' => __( 'Content', 'webinocrm' ),
		) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'برندهایی که به ما اعتماد کردند', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$logos = (array) ( $brand['client_logos'] ?? array( 'Partner', 'Brand', 'Client' ) );
		echo '<div class="webina-widget webina-widget--logo-marquee">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-widget__desc">' . esc_html( (string) $s['description'] ) . '</p>';
		}
		echo '<div class="webina-logo-marquee"><div class="webina-logo-marquee__track">';
		foreach ( $logos as $logo ) {
			echo '<span>' . esc_html( (string) $logo ) . '</span>';
		}
		echo '</div></div></div>';
	}
}
