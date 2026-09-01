<?php
/**
 * Elementor widget: Trust Strip
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_TrustStrip extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_trust_strip';
	}

	public function get_title() {
		return __( 'Trust Strip', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-star';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array(
			'label' => __( 'Content', 'webinocrm' ),
		) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'وبینا', 'webinocrm' ),
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
		$heading = esc_html( (string) ( $s['heading'] ?? '' ) );
		$desc    = esc_html( (string) ( $s['description'] ?? '' ) );
		echo '<div class="webina-widget webina-widget--trust-strip">';
		if ( $heading ) {
			echo '<h2 class="webina-widget__heading">' . $heading . '</h2>';
		}
		if ( $desc ) {
			echo '<p class="webina-widget__desc">' . $desc . '</p>';
		}
		$this->render_widget_body( $s );
		echo '</div>';
	}

	protected function render_widget_body( array $s ) {
		$items = array(
			'Google Partner',
			'Elementor Expert',
			'WooCommerce',
			'CRM یکپارچه',
			'پشتیبانی VIP',
			'KPI شفاف',
		);
		echo '<div class="webina-trust-marquee">';
		foreach ( $items as $item ) {
			echo '<span class="webina-trust-marquee__item">' . esc_html( $item ) . '</span>';
		}
		echo '</div>';
	}
}
