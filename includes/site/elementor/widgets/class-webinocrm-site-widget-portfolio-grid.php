<?php
/**
 * Elementor widget: Portfolio Grid
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_PortfolioGrid extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_portfolio_grid';
	}

	public function get_title() {
		return __( 'Portfolio Grid', 'webinocrm' );
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
		$this->add_control( 'show_filter', array(
			'label'   => __( 'Show filter', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$heading = esc_html( (string) ( $s['heading'] ?? '' ) );
		$desc    = esc_html( (string) ( $s['description'] ?? '' ) );
		echo '<div class="webina-widget webina-widget--portfolio-grid">';
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
		if ( ! empty( $s['show_filter'] ) ) {
			echo '<form class="webina-portfolio-filter">';
			echo '<select name="service"><option value="">' . esc_html__( 'همه خدمات', 'webinocrm' ) . '</option>';
			foreach ( get_terms( array( 'taxonomy' => 'portfolio_service', 'hide_empty' => false ) ) as $term ) {
				if ( $term instanceof WP_Term ) {
					echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
				}
			}
			echo '</select>';
			echo '<select name="industry"><option value="">' . esc_html__( 'همه صنایع', 'webinocrm' ) . '</option>';
			foreach ( get_terms( array( 'taxonomy' => 'portfolio_industry', 'hide_empty' => false ) ) as $term ) {
				if ( $term instanceof WP_Term ) {
					echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
				}
			}
			echo '</select></form>';
		}

		$q = new WP_Query(
			array(
				'post_type'      => WebinoCRM_Site_Portfolio::POST_TYPE,
				'posts_per_page' => 6,
				'post_status'    => 'publish',
			)
		);
		echo '<div class="webina-portfolio-grid">';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				echo '<article class="webina-portfolio-card">';
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) );
				}
				echo '<h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
				echo '<p>' . esc_html( get_the_excerpt() ) . '</p></article>';
			}
			wp_reset_postdata();
		}
		echo '</div>';
	}
}
