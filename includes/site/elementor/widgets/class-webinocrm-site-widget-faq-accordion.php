<?php
/**
 * Elementor widget: FAQ Accordion
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_FaqAccordion extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_faq_accordion';
	}

	public function get_title() {
		return __( 'FAQ Accordion', 'webinocrm' );
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
		echo '<div class="webina-widget webina-widget--faq-accordion">';
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
			array( 'q' => 'مدت قرارداد چقدر است؟', 'a' => 'قراردادهای رشد ۶ ماهه با KPI شفاف.' ),
			array( 'q' => 'گزارش‌دهی چگونه است؟', 'a' => 'داشبورد هفتگی و جلسه ماهانه استراتژی.' ),
			array( 'q' => 'آیا پشتیبانی فنی دارید؟', 'a' => 'بله، تیم فنی وبینا پشتیبانی و بهینه‌سازی مستمر ارائه می‌دهد.' ),
		);
		echo '<div class="webina-faq">';
		foreach ( $items as $item ) {
			echo '<details><summary>' . esc_html( $item['q'] ) . '</summary><p>' . esc_html( $item['a'] ) . '</p></details>';
		}
		echo '</div>';
	}
}
