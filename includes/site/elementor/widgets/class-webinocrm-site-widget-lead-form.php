<?php
/**
 * Elementor widget: Lead Form
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_LeadForm extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_lead_form';
	}

	public function get_title() {
		return __( 'Lead Form', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
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
				'default' => __( 'درخواست مشاوره رایگان', 'webinocrm' ),
			)
		);
		$this->add_control(
			'description',
			array(
				'label'   => __( 'Description', 'webinocrm' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'پس از ارسال پیام همکاران ما با شما تماس خواهند گرفت', 'webinocrm' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$heading = esc_html( (string) ( $s['heading'] ?? '' ) );
		$desc    = esc_html( (string) ( $s['description'] ?? '' ) );
		$brand   = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$services = (array) ( $brand['services'] ?? array() );
		echo '<div class="webina-widget webina-widget--lead-form">';
		if ( $heading ) {
			echo '<h2 class="webina-widget__heading">' . $heading . '</h2>';
		}
		if ( $desc ) {
			echo '<p class="webina-widget__desc">' . $desc . '</p>';
		}
		echo '<form class="webina-lead-form" data-webina-lead-form method="post" action="#">';
		echo '<select name="webina_service" required><option value="">' . esc_html__( 'در چه زمینه‌ای مشاوره لازم دارید؟', 'webinocrm' ) . '</option>';
		foreach ( $services as $key => $label ) {
			echo '<option value="' . esc_attr( (string) $key ) . '">' . esc_html( (string) $label ) . '</option>';
		}
		echo '</select>';
		echo '<input type="text" name="webina_name" placeholder="' . esc_attr__( 'نام و نام خانوادگی', 'webinocrm' ) . '" required />';
		echo '<input type="tel" name="webina_phone" placeholder="' . esc_attr__( 'شماره همراه', 'webinocrm' ) . '" required dir="ltr" />';
		echo '<textarea name="webina_message" placeholder="' . esc_attr__( 'توضیح کوتاه درباره پروژه (اختیاری)', 'webinocrm' ) . '" rows="3"></textarea>';
		echo '<button type="submit" class="webina-btn webina-btn--accent">' . esc_html__( 'ارسال درخواست', 'webinocrm' ) . '</button>';
		echo '<p class="webina-lead-form__status" role="status" aria-live="polite"></p>';
		echo '</form></div>';
	}
}
