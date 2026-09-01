<?php
/**
 * Elementor widget: Timeline
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_Timeline extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_timeline';
	}

	public function get_title() {
		return __( 'Timeline', 'webinocrm' );
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
				'default' => __( 'فرآیند', 'webinocrm' ),
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
		echo '<div class="webina-widget webina-widget--timeline">';
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
			array( 't' => 'تحلیل وضعیت', 'd' => 'ممیزی فنی، بازار و قیف فروش' ),
			array( 't' => 'استراتژی ۹۰ روزه', 'd' => 'KPI و اولویت کانال‌ها' ),
			array( 't' => 'طراحی و اجرا', 'd' => 'تیم فنی + مارکتینگ' ),
			array( 't' => 'بهینه‌سازی', 'd' => 'A/B، سئو و CRO' ),
			array( 't' => 'گزارش ROI', 'd' => 'داشبورد مدیریتی' ),
		);
		echo '<ol class="webina-timeline webina-timeline--rich">';
		foreach ( $items as $i => $step ) {
			echo '<li><span class="webina-timeline__num">' . esc_html( (string) ( $i + 1 ) ) . '</span>';
			echo '<div><strong>' . esc_html( $step['t'] ) . '</strong><p>' . esc_html( $step['d'] ) . '</p></div></li>';
		}
		echo '</ol>';
	}
}
