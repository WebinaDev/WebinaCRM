<?php
/**
 * Portfolio CPT for marketing site.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Portfolio {

	const POST_TYPE = 'webina_portfolio';

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 6 );
		add_action( 'pre_get_posts', array( __CLASS__, 'archive_query' ) );
		add_action( 'wp_ajax_webina_portfolio_filter', array( __CLASS__, 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_webina_portfolio_filter', array( __CLASS__, 'ajax_filter' ) );
	}

	/**
	 * Register CPT.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'نمونه‌کار', 'webinocrm' ),
					'singular_name' => __( 'نمونه‌کار', 'webinocrm' ),
					'add_new_item'  => __( 'افزودن نمونه‌کار', 'webinocrm' ),
					'edit_item'     => __( 'ویرایش نمونه‌کار', 'webinocrm' ),
				),
				'public'              => true,
				'has_archive'         => 'portfolio',
				'rewrite'             => array( 'slug' => 'portfolio' ),
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-portfolio',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'show_in_menu'        => true,
			)
		);
	}

	/**
	 * Register taxonomies.
	 */
	public static function register_taxonomies() {
		register_taxonomy(
			'portfolio_service',
			self::POST_TYPE,
			array(
				'label'        => __( 'خدمت', 'webinocrm' ),
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'portfolio-service' ),
			)
		);
		register_taxonomy(
			'portfolio_industry',
			self::POST_TYPE,
			array(
				'label'        => __( 'صنعت', 'webinocrm' ),
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'portfolio-industry' ),
			)
		);
		register_taxonomy(
			'portfolio_tech',
			self::POST_TYPE,
			array(
				'label'        => __( 'فناوری', 'webinocrm' ),
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'portfolio-tech' ),
			)
		);
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function archive_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}

		$tax_query = array();
		$service   = sanitize_key( (string) get_query_var( 'portfolio_service' ) );
		$industry  = sanitize_key( (string) get_query_var( 'portfolio_industry' ) );
		if ( $service ) {
			$tax_query[] = array(
				'taxonomy' => 'portfolio_service',
				'field'    => 'slug',
				'terms'    => $service,
			);
		}
		if ( $industry ) {
			$tax_query[] = array(
				'taxonomy' => 'portfolio_industry',
				'field'    => 'slug',
				'terms'    => $industry,
			);
		}
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * AJAX portfolio filter.
	 */
	public static function ajax_filter() {
		check_ajax_referer( 'webina_portfolio_filter', 'nonce' );

		$service  = sanitize_key( (string) ( $_POST['service'] ?? '' ) );
		$industry = sanitize_key( (string) ( $_POST['industry'] ?? '' ) );

		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 12,
			'post_status'    => 'publish',
		);
		$tax_query = array();
		if ( $service ) {
			$tax_query[] = array(
				'taxonomy' => 'portfolio_service',
				'field'    => 'slug',
				'terms'    => $service,
			);
		}
		if ( $industry ) {
			$tax_query[] = array(
				'taxonomy' => 'portfolio_industry',
				'field'    => 'slug',
				'terms'    => $industry,
			);
		}
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		$q = new WP_Query( $args );
		ob_start();
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				echo '<article class="webina-portfolio-card">';
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) );
				}
				echo '<h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
				echo '<p>' . esc_html( get_the_excerpt() ) . '</p>';
				echo '</article>';
			}
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'موردی یافت نشد.', 'webinocrm' ) . '</p>';
		}
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Seed demo portfolio items.
	 */
	public static function seed_demo() {
		$demos = array(
			array(
				'title'    => 'پلتفرم فروش B2B صنعتی',
				'excerpt'  => 'طراحی سایت + CRM + سئو برای تولیدکننده قطعات.',
				'service'  => 'tech',
				'industry' => 'corporate',
				'tech'     => 'wordpress',
				'featured' => true,
				'metrics'  => array(
					array( 'label' => 'افزایش لید', 'value' => '+142%' ),
					array( 'label' => 'CWV', 'value' => '92' ),
				),
			),
			array(
				'title'    => 'کلینیک زیبایی لوکس',
				'excerpt'  => 'برندینگ، لندینگ و کمپین گوگل ادز.',
				'service'  => 'growth',
				'industry' => 'health',
				'tech'     => 'elementor',
				'featured' => true,
				'metrics'  => array(
					array( 'label' => 'CPA', 'value' => '-38%' ),
					array( 'label' => 'رزرو', 'value' => '+89%' ),
				),
			),
			array(
				'title'    => 'مارکت‌پلیس مد و پوشاک',
				'excerpt'  => 'فروشگاه چندفروشنده با پنل فروشندگان.',
				'service'  => 'tech',
				'industry' => 'retail',
				'tech'     => 'woocommerce',
				'featured' => false,
				'metrics'  => array(
					array( 'label' => 'GMV', 'value' => '+210%' ),
				),
			),
			array(
				'title'    => 'آکادمی آنلاین آموزشی',
				'excerpt'  => 'LMS، فروش دوره و اتوماسیون مارکتینگ.',
				'service'  => 'tech',
				'industry' => 'education',
				'tech'     => 'wordpress',
				'featured' => true,
				'metrics'  => array(
					array( 'label' => 'ثبت‌نام', 'value' => '+65%' ),
				),
			),
		);

		self::ensure_terms();

		foreach ( $demos as $demo ) {
			$existing = WebinoCRM_Site_Seeder::find_by_title( $demo['title'], self::POST_TYPE );
			if ( $existing ) {
				continue;
			}
			$post_id = wp_insert_post(
				array(
					'post_type'    => self::POST_TYPE,
					'post_title'   => $demo['title'],
					'post_excerpt' => $demo['excerpt'],
					'post_content' => '<p>' . esc_html( $demo['excerpt'] ) . '</p><p>چالش: نیاز به رشد فروش دیجیتال با گزارش ROI شفاف.</p><p>راه‌حل: طراحی قیف، پیاده‌سازی فنی و بهینه‌سازی مستمر توسط وبینا.</p>',
					'post_status'  => 'publish',
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			wp_set_object_terms( $post_id, $demo['service'], 'portfolio_service', false );
			wp_set_object_terms( $post_id, $demo['industry'], 'portfolio_industry', false );
			wp_set_object_terms( $post_id, $demo['tech'], 'portfolio_tech', false );
			update_post_meta( $post_id, '_webina_summary', $demo['excerpt'] );
			update_post_meta( $post_id, '_webina_challenge', 'نیاز به رشد فروش دیجیتال با گزارش ROI شفاف.' );
			update_post_meta( $post_id, '_webina_solution', 'طراحی قیف، پیاده‌سازی فنی و بهینه‌سازی مستمر توسط وبینا.' );
			update_post_meta( $post_id, '_webina_results', wp_json_encode( $demo['metrics'] ) );
			update_post_meta( $post_id, '_webina_featured', $demo['featured'] ? '1' : '0' );
			update_post_meta( $post_id, '_webina_live_url', 'https://webina.dev/' );
		}
	}

	/**
	 * Ensure default taxonomy terms.
	 */
	private static function ensure_terms() {
		$terms = array(
			'portfolio_service'  => array( 'tech', 'growth', 'branding', 'strategy', 'support' ),
			'portfolio_industry' => array( 'retail', 'health', 'corporate', 'education', 'hospitality' ),
			'portfolio_tech'     => array( 'wordpress', 'elementor', 'woocommerce', 'pwa' ),
		);
		foreach ( $terms as $tax => $slugs ) {
			foreach ( $slugs as $slug ) {
				if ( ! term_exists( $slug, $tax ) ) {
					wp_insert_term( ucfirst( $slug ), $tax, array( 'slug' => $slug ) );
				}
			}
		}
	}
}
