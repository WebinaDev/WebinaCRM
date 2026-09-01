<?php
/**
 * Seed all site pages, menus, and demo content.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Seeder {

	/**
	 * @var array<string,int>
	 */
	private static $page_map = array();

	/**
	 * Run full site seed.
	 *
	 * @param bool $force Force re-seed pages.
	 */
	public static function run( $force = false ) {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return;
		}
		if ( ! did_action( 'init' ) ) {
			return;
		}

		global $wp_rewrite;
		if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) ) {
			return;
		}

		self::$page_map = array();

		WebinoCRM_Site_Theme::activate();
		WebinoCRM_Site_Kit::ensure_kit();
		WebinoCRM_Site_Theme_Builder::seed_templates( (bool) $force );

		self::seed_pages( $force );
		self::seed_menus();
		self::set_front_page();
		WebinoCRM_Site_Portfolio::seed_demo();
		self::seed_blog();
		self::seed_resources();
		self::clear_elementor_cache();

		update_option( 'webinocrm_site_seeded_version', WEBINOCRM_SITE_VERSION );
		delete_option( 'webinocrm_site_needs_seed' );
		flush_rewrite_rules();
	}

	/**
	 * Relative path after IA root (e.g. home, services/tech).
	 *
	 * @param string $path Full IA path.
	 * @return string
	 */
	public static function relative_path( $path ) {
		return WebinoCRM_Site_IA::relative_path( $path );
	}

	/**
	 * Store page id under full and relative keys.
	 *
	 * @param string $path Full path.
	 * @param int    $post_id Post ID.
	 */
	private static function map_page( $path, $post_id ) {
		$post_id = (int) $post_id;
		self::$page_map[ $path ] = $post_id;
		$rel = self::relative_path( $path );
		if ( '' !== $rel ) {
			self::$page_map[ $rel ] = $post_id;
		}
		$slug = basename( $path );
		if ( $slug && ! isset( self::$page_map[ $slug ] ) ) {
			self::$page_map[ $slug ] = $post_id;
		}
	}

	/**
	 * @param bool $force Force update existing pages.
	 */
	private static function seed_pages( $force ) {
		$nodes = WebinoCRM_Site_IA::seedable_pages();
		usort(
			$nodes,
			static function ( $a, $b ) {
				$da = substr_count( (string) ( $a['path'] ?? '' ), '/' );
				$db = substr_count( (string) ( $b['path'] ?? '' ), '/' );
				return $da <=> $db;
			}
		);
		foreach ( $nodes as $node ) {
			self::upsert_page( $node, $force );
		}
	}

	/**
	 * @param array<string,mixed> $node IA node.
	 * @param bool                $force Force update.
	 * @return int
	 */
	private static function upsert_page( $node, $force ) {
		$path = trim( (string) ( $node['path'] ?? '' ), '/' );
		if ( '' === $path ) {
			return 0;
		}

		$copy     = WebinoCRM_Site_Content::for_node( $node );
		$existing = self::find_page_by_path( $path );

		$postarr = array(
			'post_title'   => $copy['title'],
			'post_name'    => sanitize_title( (string) ( $node['slug'] ?? basename( $path ) ) ),
			'post_excerpt' => $copy['excerpt'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		);

		if ( $existing ) {
			$post_id = (int) $existing->ID;
			self::map_page( $path, $post_id );

			$needs_elementor = $force || '' === (string) get_post_meta( $post_id, '_elementor_data', true );
			if ( $force ) {
				$postarr['ID'] = $post_id;
				$updated       = wp_update_post( $postarr, true );
				if ( ! is_wp_error( $updated ) && $updated ) {
					$post_id = (int) $updated;
				}
			}

			$parent_rel = self::relative_path( dirname( $path ) );
			if ( $parent_rel && isset( self::$page_map[ $parent_rel ] ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_parent' => self::$page_map[ $parent_rel ],
					)
				);
			} elseif ( isset( self::$page_map[ dirname( $path ) ] ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_parent' => self::$page_map[ dirname( $path ) ],
					)
				);
			}

			if ( $needs_elementor ) {
				self::apply_elementor( $post_id, $node, $copy );
			}
			WebinoCRM_Site_Seo::apply( $post_id, $copy );
			return $post_id;
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;
		self::map_page( $path, $post_id );

		$parent_full = dirname( $path );
		$parent_rel  = self::relative_path( $parent_full );
		if ( $parent_rel && isset( self::$page_map[ $parent_rel ] ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_parent' => self::$page_map[ $parent_rel ],
				)
			);
		} elseif ( isset( self::$page_map[ $parent_full ] ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_parent' => self::$page_map[ $parent_full ],
				)
			);
		}

		self::apply_elementor( $post_id, $node, $copy );
		WebinoCRM_Site_Seo::apply( $post_id, $copy );

		if ( self::is_hidden_landing( $node ) ) {
			update_post_meta( $post_id, '_webina_hidden_landing', '1' );
		}

		return $post_id;
	}

	/**
	 * @param string $path Path.
	 * @return WP_Post|null
	 */
	private static function find_page_by_path( $path ) {
		$rel  = self::relative_path( $path );
		$slug = basename( $path );
		foreach ( array_filter( array( $rel, $path, $slug ) ) as $candidate ) {
			$page = get_page_by_path( $candidate );
			if ( $page ) {
				return $page;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $node Node.
	 * @return bool
	 */
	private static function is_hidden_landing( $node ) {
		return 'landing' === (string) ( $node['section'] ?? '' );
	}

	/**
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $node Node.
	 * @param array<string,mixed> $copy Copy.
	 */
	private static function apply_elementor( $post_id, $node, $copy ) {
		$data = WebinoCRM_Site_Elementor_Builder::build( $node, $copy );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}
	}

	/**
	 * Clear Elementor CSS cache after bulk meta writes.
	 */
	private static function clear_elementor_cache() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		try {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->files_manager ) && is_object( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
				$plugin->files_manager->clear_cache();
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Ignore cache clear failures during seed.
		}
	}

	/**
	 * Create nav menus.
	 */
	private static function seed_menus() {
		$menu_id = wp_create_nav_menu( 'Webina Primary' );
		if ( is_wp_error( $menu_id ) ) {
			$menus = wp_get_nav_menus();
			foreach ( $menus as $menu ) {
				if ( 'Webina Primary' === $menu->name ) {
					$menu_id = (int) $menu->term_id;
					break;
				}
			}
		}
		if ( ! $menu_id || is_wp_error( $menu_id ) ) {
			return;
		}

		$existing_items = wp_get_nav_menu_items( $menu_id );
		if ( is_array( $existing_items ) ) {
			foreach ( $existing_items as $item ) {
				wp_delete_post( (int) $item->ID, true );
			}
		}

		$links = array(
			'home'           => 'خانه',
			'services'       => 'خدمات',
			'solutions'      => 'راهکارها',
			'portfolio'      => 'نمونه‌کار',
			'resources'      => 'منابع',
			'pages/about'    => 'درباره ما',
			'pages/team'     => 'تیم ما',
			'pages/contact'  => 'تماس',
			'pages/careers'  => 'همکاری',
			'landing/free-consultation' => 'مشاوره رایگان',
		);

		$order = 0;
		foreach ( $links as $path => $label ) {
			if ( ! isset( self::$page_map[ $path ] ) ) {
				if ( 'portfolio' === $path ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'    => $label,
							'menu-item-url'      => home_url( '/portfolio/' ),
							'menu-item-status'   => 'publish',
							'menu-item-position' => ++$order,
						)
					);
				}
				continue;
			}
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $label,
					'menu-item-object'    => 'page',
					'menu-item-object-id' => self::$page_map[ $path ],
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => ++$order,
				)
			);
		}

		$locations            = get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = (int) $menu_id;
		$locations['footer']  = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Set static front page.
	 */
	private static function set_front_page() {
		if ( ! empty( self::$page_map['home'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) self::$page_map['home'] );
		}
		if ( ! empty( self::$page_map['resources/blog'] ) ) {
			update_option( 'page_for_posts', (int) self::$page_map['resources/blog'] );
		}
	}

	/**
	 * Seed blog posts.
	 */
	private static function seed_blog() {
		$posts = array(
			array(
				'title'   => 'راهنمای سئو B2B برای مدیران عامل',
				'excerpt' => 'چگونه SEO را به زبان ROI برای هیئت‌مدیره توضیح دهیم.',
			),
			array(
				'title'   => '۵ اشتباه رایج در redesign سایت شرکتی',
				'excerpt' => 'قبل از بازطراحی، این موارد را بررسی کنید.',
			),
			array(
				'title'   => 'اتوماسیون CRM و افزایش نرخ تبدیل',
				'excerpt' => 'پیگیری لیدها در کمتر از ۳۰ دقیقه.',
			),
		);
		foreach ( $posts as $p ) {
			if ( self::find_by_title( $p['title'], 'post' ) ) {
				continue;
			}
			wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_title'   => $p['title'],
					'post_excerpt' => $p['excerpt'],
					'post_content' => '<p>' . esc_html( $p['excerpt'] ) . '</p>',
					'post_status'  => 'publish',
				)
			);
		}
	}

	/**
	 * Find a post by exact title (WP 6.2+ safe replacement for get_page_by_title).
	 *
	 * @param string $title Post title.
	 * @param string $post_type Post type.
	 * @return WP_Post|null
	 */
	public static function find_by_title( $title, $post_type = 'page' ) {
		$title = (string) $title;
		if ( '' === $title ) {
			return null;
		}
		$q = new WP_Query(
			array(
				'post_type'              => $post_type,
				'title'                  => $title,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $q->posts[0] ) && $q->posts[0] instanceof WP_Post ) {
			return $q->posts[0];
		}
		return null;
	}

	/**
	 * Seed academy/downloads CPT-like pages content.
	 */
	private static function seed_resources() {
		if ( ! empty( self::$page_map['resources/faq'] ) ) {
			$faq = array(
				array( 'q' => 'وبینا با چه صنایعی کار می‌کند؟', 'a' => 'B2B، پزشکی لوکس، املاک، آموزش و hospitality.' ),
				array( 'q' => 'حداقل مدت قرارداد چقدر است؟', 'a' => 'قرارداد رشد ۶ ماهه با KPI شفاف.' ),
				array( 'q' => 'گزارش‌دهی چگونه است؟', 'a' => 'داشبورد هفتگی + جلسه ماهانه استراتژی.' ),
			);
			update_post_meta( self::$page_map['resources/faq'], '_webina_faq_items', wp_json_encode( $faq, JSON_UNESCAPED_UNICODE ) );
		}

		if ( ! empty( self::$page_map['resources/academy'] ) ) {
			$videos = array(
				array( 'title' => 'آشنایی با قیف فروش B2B', 'duration' => '12:40' ),
				array( 'title' => 'Core Web Vitals برای مدیران', 'duration' => '08:15' ),
				array( 'title' => 'اتوماسیون CRM وبینا', 'duration' => '15:02' ),
			);
			update_post_meta( self::$page_map['resources/academy'], '_webina_academy_videos', wp_json_encode( $videos, JSON_UNESCAPED_UNICODE ) );
		}

		if ( ! empty( self::$page_map['resources/downloads'] ) ) {
			$files = array(
				array( 'title' => 'چک‌لیست redesign سایت شرکتی', 'type' => 'PDF' ),
				array( 'title' => 'نمونه گزارش ROI ماهانه', 'type' => 'PDF' ),
				array( 'title' => 'راهنمای انتخاب CMS', 'type' => 'PDF' ),
			);
			update_post_meta( self::$page_map['resources/downloads'], '_webina_downloads', wp_json_encode( $files, JSON_UNESCAPED_UNICODE ) );
		}
	}
}
