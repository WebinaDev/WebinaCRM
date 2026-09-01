<?php
/**
 * Elementor widget: Blog Cards
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_BlogCards extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_blog_cards';
	}

	public function get_title() {
		return __( 'Blog Cards', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'مقالات', 'webinocrm' ),
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
		echo '<div class="webina-widget webina-widget--blog-cards">';
		echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading" style="margin:0;text-align:right">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		echo '<a class="webina-btn webina-btn--ghost" href="' . esc_url( home_url( '/resources/blog/' ) ) . '">مشاهده همه</a></div>';

		$posts = get_posts(
			array(
				'numberposts' => 3,
				'post_status' => 'publish',
			)
		);

		$fallback = array(
			array( 'title' => 'تفاوت سئو و تبلیغات', 'excerpt' => 'انتخاب بین سئو و تبلیغات یکی از چالش‌های مدیران کسب‌وکار است.', 'date' => '' ),
			array( 'title' => 'هزینه خدمات سئو چقدر است؟', 'excerpt' => 'تعیین قیمت سئو یکی از تصمیمات مهم برای صاحبان کسب‌وکار است.', 'date' => '' ),
			array( 'title' => 'سئو تضمینی: واقعیت یا ادعا؟', 'excerpt' => 'به‌عنوان صاحب کسب‌وکار طبیعی است که بخواهید در میدان رقابت بمانید.', 'date' => '' ),
		);

		echo '<div class="webina-blog-cards">';
		if ( $posts ) {
			foreach ( $posts as $post ) {
				echo '<a class="webina-blog-card" href="' . esc_url( get_permalink( $post ) ) . '">';
				echo '<div class="webina-blog-card__thumb" aria-hidden="true"></div><div class="webina-blog-card__body">';
				echo '<div class="webina-blog-card__meta">' . esc_html( get_the_date( '', $post ) ) . '</div>';
				echo '<h3>' . esc_html( get_the_title( $post ) ) . '</h3>';
				echo '<p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 18 ) ) . '</p>';
				echo '</div></a>';
			}
		} else {
			foreach ( $fallback as $item ) {
				echo '<a class="webina-blog-card" href="' . esc_url( home_url( '/resources/blog/' ) ) . '">';
				echo '<div class="webina-blog-card__thumb" aria-hidden="true"></div><div class="webina-blog-card__body">';
				echo '<div class="webina-blog-card__meta">مقاله</div>';
				echo '<h3>' . esc_html( $item['title'] ) . '</h3>';
				echo '<p>' . esc_html( $item['excerpt'] ) . '</p>';
				echo '</div></a>';
			}
		}
		echo '</div></div>';
	}
}
