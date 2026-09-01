<?php
/**
 * Elementor widget: Pricing Table
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_PricingTable extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_pricing_table';
	}

	public function get_title() {
		return __( 'Pricing Table', 'webinocrm' );
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
		echo '<div class="webina-widget webina-widget--pricing-table">';
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
		echo '<div class="webina-bento"><div class="webina-bento__item"><strong>پکیج رشد</strong><p>از ۳۰ میلیون / ماه</p></div><div class="webina-bento__item"><strong>پروژه اختصاصی</strong><p>برآورد پس از جلسه</p></div></div>';
	}
}
