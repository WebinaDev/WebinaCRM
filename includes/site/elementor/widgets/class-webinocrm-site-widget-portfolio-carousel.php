<?php
/**
 * Elementor widget: Portfolio Carousel
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_PortfolioCarousel extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_portfolio_carousel';
	}

	public function get_title() {
		return __( 'Portfolio Carousel', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => '',
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => '',
		) );
		$this->add_control( 'tall', array(
			'label'   => __( 'Tall cards (UI style)', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$tall = ! empty( $s['tall'] ) && 'yes' === $s['tall'];
		echo '<div class="webina-widget webina-widget--portfolio-carousel">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-widget__desc">' . esc_html( (string) $s['description'] ) . '</p>';
		}

		$items = array();
		if ( class_exists( 'WebinoCRM_Site_Portfolio' ) && post_type_exists( WebinoCRM_Site_Portfolio::POST_TYPE ) ) {
			$q = new WP_Query(
				array(
					'post_type'      => WebinoCRM_Site_Portfolio::POST_TYPE,
					'posts_per_page' => 8,
					'post_status'    => 'publish',
				)
			);
			foreach ( $q->posts as $post ) {
				$items[] = array(
					'title' => get_the_title( $post ),
					'url'   => get_permalink( $post ),
				);
			}
			wp_reset_postdata();
		}
		if ( empty( $items ) ) {
			for ( $i = 1; $i <= 6; $i++ ) {
				$items[] = array(
					'title' => sprintf( /* translators: %d index */ __( 'نمونه کار %d', 'webinocrm' ), $i ),
					'url'   => home_url( '/portfolio/' ),
				);
			}
		}

		echo '<div class="webina-portfolio-carousel"><div class="webina-portfolio-carousel__track">';
		foreach ( $items as $item ) {
			$cls = 'webina-portfolio-carousel__item' . ( $tall ? ' webina-portfolio-carousel__item--tall' : '' );
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( (string) $item['url'] ) . '">';
			echo '<div class="webina-portfolio-carousel__thumb" aria-hidden="true"></div>';
			echo '<strong>' . esc_html( (string) $item['title'] ) . '</strong>';
			echo '</a>';
		}
		echo '</div></div></div>';
	}
}
