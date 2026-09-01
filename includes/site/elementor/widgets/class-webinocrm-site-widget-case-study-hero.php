<?php
/**
 * Elementor widget: Case Study Hero
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_CaseStudyHero extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_case_study_hero';
	}

	public function get_title() {
		return __( 'Case Study Hero', 'webinocrm' );
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
		echo '<div class="webina-widget webina-widget--case-study-hero">';
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
		if ( is_singular( WebinoCRM_Site_Portfolio::POST_TYPE ) ) {
			$metrics = json_decode( (string) get_post_meta( get_the_ID(), '_webina_results', true ), true );
			if ( is_array( $metrics ) ) {
				echo '<div class="webina-stats">';
				foreach ( $metrics as $m ) {
					echo '<div class="webina-stats__item"><strong>' . esc_html( (string) ( $m['value'] ?? '' ) ) . '</strong><span>' . esc_html( (string) ( $m['label'] ?? '' ) ) . '</span></div>';
				}
				echo '</div>';
			}
		}
	}
}
