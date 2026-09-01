<?php
/**
 * Elementor widget: Vision / Mission
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_VisionMission extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_vision_mission';
	}

	public function get_title() {
		return __( 'Vision Mission', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-info-box';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'اهداف آژانس وبینا', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		echo '<div class="webina-widget webina-widget--vision-mission">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-widget__desc">' . esc_html( (string) $s['description'] ) . '</p>';
		}
		echo '<div class="webina-vision-mission">';
		echo '<article class="webina-vm-card"><span class="webina-vm-card__label">Vision</span>';
		echo '<h3>چشم انداز وبینا</h3><p>' . esc_html( (string) ( $brand['vision'] ?? '' ) ) . '</p></article>';
		echo '<article class="webina-vm-card"><span class="webina-vm-card__label">Mission</span>';
		echo '<h3>ماموریت وبینا</h3><p>' . esc_html( (string) ( $brand['mission'] ?? '' ) ) . '</p></article>';
		echo '</div></div>';
	}
}
