<?php
/**
 * Elementor widget: Split Service block
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_SplitService extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_split_service';
	}

	public function get_title() {
		return __( 'Split Service', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-columns';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'خدمات سئو سایت', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => __( 'بهینه‌سازی سایت برای موتورهای جستجو، بهترین روش جذب کاربر هدفمند برای کسب‌وکار شماست.', 'webinocrm' ),
		) );
		$this->add_control( 'bullets', array(
			'label'   => __( 'Bullets (comma separated)', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => 'User friendly, Mobile friendly, Google friendly',
		) );
		$this->add_control( 'cta_label', array(
			'label'   => __( 'CTA label', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'سفارش خدمات', 'webinocrm' ),
		) );
		$this->add_control( 'cta_url', array(
			'label'   => __( 'CTA URL', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::URL,
			'default' => array( 'url' => '' ),
		) );
		$this->add_control( 'show_stats', array(
			'label'   => __( 'Show stats', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => '',
		) );
		$this->add_control( 'reverse', array(
			'label'   => __( 'Reverse layout', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$heading = (string) ( $s['heading'] ?? '' );
		$desc    = (string) ( $s['description'] ?? '' );
		$bullets = array_filter( array_map( 'trim', explode( ',', (string) ( $s['bullets'] ?? '' ) ) ) );
		$cta     = (string) ( $s['cta_label'] ?? '' );
		$url     = (string) ( $s['cta_url']['url'] ?? '' );
		if ( '' === $url ) {
			$url = '#';
		}
		$rev = ! empty( $s['reverse'] ) && 'yes' === $s['reverse'];
		$class = 'webina-split-service' . ( $rev ? ' webina-split-service--reverse' : '' );

		echo '<div class="webina-widget webina-widget--split-service"><div class="' . esc_attr( $class ) . '">';
		echo '<div class="webina-split-service__content">';
		echo '<h2>' . esc_html( $heading ) . '</h2>';
		echo '<p>' . esc_html( $desc ) . '</p>';
		if ( $bullets ) {
			echo '<ul class="webina-split-service__bullets">';
			foreach ( $bullets as $b ) {
				echo '<li>' . esc_html( $b ) . '</li>';
			}
			echo '</ul>';
		}
		if ( $cta ) {
			$is_popup = '#' === $url || false !== strpos( $url, 'moshavere' );
			if ( $is_popup ) {
				echo '<button type="button" class="webina-btn webina-btn--accent" data-webina-popup-open>' . esc_html( $cta ) . '</button>';
			} else {
				echo '<a class="webina-btn webina-btn--accent" href="' . esc_url( $url ) . '">' . esc_html( $cta ) . '</a>';
			}
		}
		if ( ! empty( $s['show_stats'] ) && 'yes' === $s['show_stats'] ) {
			$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
			$stats = (array) ( $brand['stats'] ?? array() );
			echo '<div class="webina-split-service__stats">';
			foreach ( $stats as $st ) {
				echo '<div class="webina-split-service__stat">';
				echo '<strong data-count="' . esc_attr( (string) ( $st['v'] ?? '0' ) ) . '" data-suffix="' . esc_attr( (string) ( $st['suffix'] ?? '' ) ) . '">0</strong>';
				echo '<span>' . esc_html( (string) ( $st['l'] ?? '' ) ) . '</span></div>';
			}
			echo '</div>';
		}
		echo '</div><div class="webina-split-service__visual" aria-hidden="true"></div></div></div>';
	}
}
