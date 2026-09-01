<?php
/**
 * Elementor widget: Testimonial
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_Testimonial extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_testimonial';
	}

	public function get_title() {
		return __( 'Testimonial', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-testimonial';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array(
			'label' => __( 'Content', 'webinocrm' ),
		) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'نظرات مشتریان وبینا', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => __( 'ما به شفافیت در اجرای پروژه‌ها اعتقاد داریم. تجربه کسب‌وکارهایی که به ما اعتماد کردند را از زبان خودشان بشنوید.', 'webinocrm' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$heading = esc_html( (string) ( $s['heading'] ?? '' ) );
		$desc    = esc_html( (string) ( $s['description'] ?? '' ) );
		$brand   = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$items   = (array) ( $brand['testimonials'] ?? array() );
		echo '<div class="webina-widget webina-widget--testimonial">';
		if ( $heading ) {
			echo '<h2 class="webina-widget__heading">' . $heading . '</h2>';
		}
		if ( $desc ) {
			echo '<p class="webina-widget__desc">' . $desc . '</p>';
		}
		echo '<div class="webina-testimonials">';
		foreach ( $items as $item ) {
			echo '<blockquote class="webina-testimonial">';
			echo '<p>' . esc_html( (string) ( $item['text'] ?? '' ) ) . '</p>';
			echo '<cite>' . esc_html( (string) ( $item['name'] ?? '' ) ) . ' — ' . esc_html( (string) ( $item['role'] ?? '' ) ) . '</cite>';
			echo '</blockquote>';
		}
		echo '</div></div>';
	}
}
