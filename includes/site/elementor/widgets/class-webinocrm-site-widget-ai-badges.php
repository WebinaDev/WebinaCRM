<?php
/**
 * Elementor widget: AI Badges
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_AiBadges extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_ai_badges';
	}

	public function get_title() {
		return __( 'AI Badges', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-apps';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'خدمات سئو با هوش مصنوعی', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => __( 'دیده‌شدن در گوگل کافی نیست؛ هوش مصنوعی هم باید شما را بشناسد.', 'webinocrm' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$badges = array( 'ChatGPT', 'Claude', 'Gemini', 'Grok', 'DeepSeek', 'Copilot' );
		echo '<div class="webina-widget webina-widget--ai-badges">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-widget__desc">' . esc_html( (string) $s['description'] ) . '</p>';
		}
		echo '<div class="webina-ai-badges">';
		foreach ( $badges as $b ) {
			echo '<span class="webina-ai-badge">' . esc_html( $b ) . '</span>';
		}
		echo '</div></div>';
	}
}
