<?php
/**
 * Elementor widget: Bento Grid
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_BentoGrid extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_bento_grid';
	}

	public function get_title() {
		return __( 'Bento Grid', 'webinocrm' );
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
		echo '<div class="webina-widget webina-widget--bento-grid">';
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
			array(
				't' => 'استراتژی رشد',
				'd' => 'نقشه راه ۹۰ روزه با KPIهای هیئت‌مدیره‌پسند و اولویت‌بندی کانال‌ها.',
				'k' => 'large',
			),
			array(
				't' => 'فناوری و محصول',
				'd' => 'وبسایت، CRM و اتوماسیون در یک معماری یکپارچه.',
				'k' => '',
			),
			array(
				't' => 'سئو و محتوا',
				'd' => 'جذب لید B2B با خوشه‌های موضوعی و Core Web Vitals.',
				'k' => '',
			),
			array(
				't' => 'برندینگ VIP',
				'd' => 'هویت بصری تیره و طلایی برای برندهای لوکس و شرکتی.',
				'k' => 'wide',
			),
		);
		echo '<div class="webina-bento">';
		foreach ( $items as $item ) {
			$class = 'webina-bento__item';
			if ( ! empty( $item['k'] ) ) {
				$class .= ' webina-bento__item--' . sanitize_html_class( $item['k'] );
			}
			echo '<div class="' . esc_attr( $class ) . '">';
			echo '<span class="webina-bento__accent" aria-hidden="true"></span>';
			echo '<strong>' . esc_html( $item['t'] ) . '</strong>';
			echo '<p>' . esc_html( $item['d'] ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}
}
