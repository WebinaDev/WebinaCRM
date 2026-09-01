<?php
/**
 * Elementor widget: Stats Strip
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_StatsStrip extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_stats_strip';
	}

	public function get_title() {
		return __( 'Stats Strip', 'webinocrm' );
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
		echo '<div class="webina-widget webina-widget--stats-strip">';
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
		$stats = array(
			array( 'v' => '142', 'suffix' => '%', 'l' => 'رشد لید واجد شرایط', 'count' => true ),
			array( 'v' => '400', 'suffix' => '+', 'l' => 'پروژه اجراشده', 'count' => true ),
			array( 'v' => '<30m', 'l' => 'پاسخ پشتیبانی', 'count' => false ),
			array( 'v' => '6', 'suffix' => ' ماه', 'l' => 'قرارداد رشد با KPI', 'count' => true ),
		);
		echo '<div class="webina-stats">';
		foreach ( $stats as $st ) {
			echo '<div class="webina-stats__item">';
			if ( ! empty( $st['count'] ) ) {
				echo '<strong data-count="' . esc_attr( $st['v'] ) . '" data-suffix="' . esc_attr( (string) ( $st['suffix'] ?? '' ) ) . '">0</strong>';
			} else {
				echo '<strong>' . esc_html( $st['v'] ) . '</strong>';
			}
			echo '<span>' . esc_html( $st['l'] ) . '</span></div>';
		}
		echo '</div>';
	}
}
