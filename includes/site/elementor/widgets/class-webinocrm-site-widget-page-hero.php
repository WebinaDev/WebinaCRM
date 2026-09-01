<?php
/**
 * Elementor widget: Page Hero
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_PageHero extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_page_hero';
	}

	public function get_title() {
		return __( 'Page Hero', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'عنوان صفحه', 'webinocrm' ),
		) );
		$this->add_control( 'en_label', array(
			'label'   => __( 'English label', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => 'PAGE',
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
		echo '<div class="webina-widget webina-widget--page-hero">';
		echo '<section class="webina-page-hero">';
		if ( ! empty( $s['en_label'] ) ) {
			echo '<p class="webina-page-hero__en">' . esc_html( (string) $s['en_label'] ) . '</p>';
		}
		echo '<h1 class="webina-page-hero__title">' . esc_html( (string) ( $s['heading'] ?? '' ) ) . '</h1>';
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-page-hero__lead">' . esc_html( (string) $s['description'] ) . '</p>';
		}
		echo '</section></div>';
	}
}
