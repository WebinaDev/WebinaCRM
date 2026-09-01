<?php
/**
 * Elementor widget: Team Carousel
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_TeamCarousel extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_team_carousel';
	}

	public function get_title() {
		return __( 'Team Carousel', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'تیم ما', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => '',
		) );
		$this->add_control( 'show_all_link', array(
			'label'   => __( 'Show all members link', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$team  = (array) ( $brand['team'] ?? array() );
		echo '<div class="webina-widget webina-widget--team-carousel">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-widget__desc">' . esc_html( (string) $s['description'] ) . '</p>';
		}
		echo '<div class="webina-team-carousel"><div class="webina-team-carousel__track">';
		foreach ( $team as $member ) {
			echo '<article class="webina-team-card">';
			echo '<div class="webina-team-card__photo" aria-hidden="true"></div>';
			echo '<div class="webina-team-card__body">';
			echo '<span class="webina-team-card__role">' . esc_html( (string) ( $member['role'] ?? '' ) ) . '</span>';
			echo '<strong>' . esc_html( (string) ( $member['name'] ?? '' ) ) . '</strong>';
			echo '<a href="' . esc_url( (string) ( $member['linkedin'] ?? '#' ) ) . '">LinkedIn</a>';
			echo '</div></article>';
		}
		echo '</div>';
		if ( ! empty( $s['show_all_link'] ) && 'yes' === $s['show_all_link'] ) {
			echo '<div class="webina-team-carousel__more"><a class="webina-btn webina-btn--ghost" href="' . esc_url( home_url( '/pages/team/' ) ) . '">مشاهده همه اعضای تیم</a></div>';
		}
		echo '</div></div>';
	}
}
