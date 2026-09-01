<?php
/**
 * Elementor widget: Hero سینمایی
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_HeroCinematic extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_hero_cinematic';
	}

	public function get_title() {
		return __( 'Hero سینمایی', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-star';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content',
			array(
				'label' => __( 'Content', 'webinocrm' ),
			)
		);
		$this->add_control(
			'heading',
			array(
				'label'   => __( 'Heading', 'webinocrm' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'وبینا', 'webinocrm' ),
			)
		);
		$this->add_control(
			'description',
			array(
				'label'   => __( 'Description', 'webinocrm' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$heading = esc_html( (string) ( $s['heading'] ?? '' ) );
		$desc    = esc_html( (string) ( $s['description'] ?? '' ) );
		echo '<div class="webina-widget webina-widget--hero-cinematic">';
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
		$svc = esc_url( home_url( '/services/' ) );
		echo '<div class="webina-vip-hero webina-vip-hero--widget">';
		echo '<div class="webina-vip-hero__actions">';
		echo '<button type="button" class="webina-btn webina-btn--accent" data-webina-popup-open>' . esc_html__( 'مشاوره رایگان', 'webinocrm' ) . '</button>';
		echo '<a class="webina-btn webina-btn--ghost" href="' . $svc . '">' . esc_html__( 'مشاهده خدمات', 'webinocrm' ) . '</a>';
		echo '</div></div>';
	}
}
