<?php
/**
 * Build Elementor JSON trees for seeded pages.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Elementor_Builder {

	/**
	 * @param array<string,mixed> $node IA node.
	 * @param array<string,mixed> $copy Content copy.
	 * @return array<int,mixed>
	 */
	public static function build( $node, $copy ) {
		$template = (string) ( $copy['template'] ?? 'generic' );
		return WebinoCRM_Site_Page_Stacks::for_template( $template, $node, $copy );
	}

	/**
	 * Home hero (DMROOM-style).
	 *
	 * @param array<string,mixed> $copy Copy.
	 * @return string
	 */
	public static function html_home_hero( $copy ) {
		$lead = (string) ( $copy['excerpt'] ?? 'ما نقشه راه کسب‌وکار را برای افزایش فروش اینترنتی ترسیم می‌کنیم.' );
		$html = '<section class="webina-home-hero">';
		$html .= '<h1 class="webina-home-hero__title">وبینا؛ فـــراتر از <em>نتیجه</em>...</h1>';
		$html .= '<p class="webina-home-hero__lead">' . esc_html( $lead ) . '</p>';
		$html .= '<div class="webina-home-hero__actions">';
		$html .= '<button type="button" class="webina-btn webina-btn--accent" data-webina-popup-open>درخواست مشاوره تخصصی</button>';
		$html .= '<a class="webina-btn webina-btn--ghost" href="' . esc_url( home_url( '/services/' ) ) . '">مشاهده خدمات</a>';
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * About story timeline.
	 *
	 * @return string
	 */
	public static function html_story_timeline() {
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$items = (array) ( $brand['timeline'] ?? array() );
		$html  = '<section><h2 class="webina-section-title">از کجا شروع شد؟</h2><div class="webina-story-timeline">';
		foreach ( $items as $item ) {
			$html .= '<article class="webina-story-timeline__item">';
			$html .= '<div class="webina-story-timeline__year">' . esc_html( (string) ( $item['year'] ?? '' ) ) . '</div>';
			$html .= '<div><h3>' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</h3>';
			$html .= '<p>' . esc_html( (string) ( $item['desc'] ?? '' ) ) . '</p></div></article>';
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Hero HTML block.
	 *
	 * @param array<string,mixed> $copy Copy.
	 * @param bool                $is_home Home hero.
	 * @return string
	 */
	public static function html_hero( $copy, $is_home = false ) {
		if ( $is_home ) {
			return self::html_home_hero( $copy );
		}
		$hero    = (array) ( $copy['hero'] ?? array() );
		$title   = (string) ( $hero['title'] ?? $copy['title'] ?? 'وبینا' );
		$lead    = (string) ( $hero['lead'] ?? $copy['excerpt'] ?? '' );
		$bullets = (array) ( $hero['bullets'] ?? array() );
		$svc     = esc_url( home_url( '/services/' ) );

		$html = '<section class="webina-page-hero webina-page-hero--large">';
		$html .= '<h1 class="webina-page-hero__title">' . esc_html( $title ) . '</h1>';
		$html .= '<p class="webina-page-hero__lead">' . esc_html( $lead ) . '</p>';
		if ( $bullets ) {
			$html .= '<ul class="webina-hero-bullets">';
			foreach ( $bullets as $b ) {
				$html .= '<li>' . esc_html( (string) $b ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '<div class="webina-vip-hero__actions">';
		$html .= '<button type="button" class="webina-btn webina-btn--accent" data-webina-popup-open>درخواست مشاوره</button>';
		$html .= '<a class="webina-btn webina-btn--ghost" href="' . $svc . '">مشاهده خدمات</a>';
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Service hub cards for home.
	 *
	 * @return string
	 */
	public static function html_service_hubs() {
		$cols = WebinoCRM_Site_IA::mega_menu_tree( 'services' );
		$html = '<section class="webina-hub-grid-section"><h2 class="webina-section-title">خدمات وبینا</h2><p class="webina-section-lead">از فناوری تا رشد — یک تیم، یک نقشه راه.</p><div class="webina-hub-grid">';
		foreach ( $cols as $col ) {
			$html .= '<a class="webina-hub-card" href="' . esc_url( (string) ( $col['url'] ?? '#' ) ) . '">';
			$html .= '<span class="webina-hub-card__accent"></span>';
			$html .= '<strong>' . esc_html( (string) ( $col['title'] ?? '' ) ) . '</strong>';
			$html .= '<p>' . esc_html( WebinoCRM_Site_Content::short_blurb( (string) ( $col['title'] ?? '' ) ) ) . '</p>';
			$html .= '</a>';
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Child links grid.
	 *
	 * @param array<string,mixed> $copy Copy.
	 * @return string
	 */
	public static function html_child_grid( $copy ) {
		$links = (array) ( $copy['child_links'] ?? array() );
		if ( empty( $links ) ) {
			return '';
		}
		$html = '<section class="webina-child-grid-section"><h2 class="webina-section-title">زیرمجموعه‌ها</h2><div class="webina-child-grid">';
		foreach ( $links as $link ) {
			$html .= '<a class="webina-child-card" href="' . esc_url( (string) ( $link['url'] ?? '#' ) ) . '">';
			$html .= '<strong>' . esc_html( (string) ( $link['title'] ?? '' ) ) . '</strong>';
			$html .= '<p>' . esc_html( (string) ( $link['excerpt'] ?? '' ) ) . '</p></a>';
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Pain + outcomes section.
	 *
	 * @param array<string,mixed> $copy Copy.
	 * @return string
	 */
	public static function html_pain_outcomes( $copy ) {
		$pains    = (array) ( $copy['pain_points'] ?? array() );
		$outcomes = (array) ( $copy['outcomes'] ?? array() );
		$html     = '<section class="webina-split-section"><div class="webina-split-section__col">';
		$html    .= '<h2 class="webina-section-title">چالش‌های رایج</h2><ul class="webina-check-list">';
		foreach ( $pains as $p ) {
			$html .= '<li>' . esc_html( (string) $p ) . '</li>';
		}
		$html .= '</ul></div><div class="webina-split-section__col">';
		$html .= '<h2 class="webina-section-title">آنچه به دست می‌آورید</h2><ul class="webina-check-list webina-check-list--gold">';
		foreach ( $outcomes as $o ) {
			$html .= '<li>' . esc_html( (string) $o ) . '</li>';
		}
		$html .= '</ul></div></section>';
		return $html;
	}

	/**
	 * FAQ HTML from copy.
	 *
	 * @param array<string,mixed> $copy Copy.
	 * @return string
	 */
	public static function html_faq( $copy ) {
		$items = (array) ( $copy['faq'] ?? array() );
		$html  = '<section class="webina-faq-section"><h2 class="webina-section-title">سوالات متداول</h2><div class="webina-faq">';
		foreach ( $items as $item ) {
			$html .= '<details><summary>' . esc_html( (string) ( $item['q'] ?? '' ) ) . '</summary><p>' . esc_html( (string) ( $item['a'] ?? '' ) ) . '</p></details>';
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Rich body wrapper.
	 *
	 * @param string $body HTML body.
	 * @return string
	 */
	public static function html_rich_body( $body ) {
		$body = trim( $body );
		if ( '' === $body ) {
			$body = '<p>تیم وبینا مسیر رشد دیجیتال شما را از استراتژی تا اجرا طراحی و پیاده‌سازی می‌کند.</p>';
		}
		return '<div class="webina-rich-body">' . $body . '</div>';
	}

	/**
	 * Full-width section with dark hero bg.
	 *
	 * @param array<int,mixed> $widgets Widgets.
	 * @return array<string,mixed>
	 */
	public static function section_full( $widgets ) {
		if ( is_string( $widgets ) ) {
			$widgets = array( self::widget( 'html', array( 'html' => $widgets ) ) );
		}
		return self::section(
			$widgets,
			array(
				'layout'                => 'full_width',
				'background_background' => 'classic',
				'background_color'      => '#231110',
				'padding'               => array(
					'unit'     => 'px',
					'top'      => '0',
					'right'    => '0',
					'bottom'   => '0',
					'left'     => '0',
					'isLinked' => true,
				),
			)
		);
	}

	/**
	 * @param array<int,mixed>    $widgets Column widgets.
	 * @param array<string,mixed> $settings Section settings.
	 * @return array<string,mixed>
	 */
	public static function section( $widgets, $settings = array() ) {
		$defaults = array(
			'layout'                => 'boxed',
			'content_width'         => array( 'unit' => 'px', 'size' => 1200 ),
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '72',
				'right'    => '24',
				'bottom'   => '72',
				'left'     => '24',
				'isLinked' => false,
			),
			'background_background' => 'classic',
			'background_color'      => '',
		);
		return array(
			'id'       => self::id(),
			'elType'   => 'section',
			'isInner'  => false,
			'settings' => array_merge( $defaults, $settings ),
			'elements' => array(
				array(
					'id'       => self::id(),
					'elType'   => 'column',
					'isInner'  => false,
					'settings' => array( '_column_size' => 100 ),
					'elements' => $widgets,
				),
			),
		);
	}

	/**
	 * Full-width white canvas section (zero padding) for homepage HTML clone.
	 *
	 * @param array<int,mixed> $widgets Widgets.
	 * @return array<string,mixed>
	 */
	public static function section_canvas( $widgets ) {
		return self::section(
			$widgets,
			array(
				'layout'                => 'full_width',
				'background_background' => 'classic',
				'background_color'      => '#FFFFFF',
				'padding'               => array(
					'unit'     => 'px',
					'top'      => '0',
					'right'    => '0',
					'bottom'   => '0',
					'left'     => '0',
					'isLinked' => true,
				),
			)
		);
	}

	/**
	 * @param string              $type Widget type.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function widget( $type, $settings = array() ) {
		return array(
			'id'         => self::id(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Header template data.
	 *
	 * @return array<int,mixed>
	 */
	public static function build_header() {
		return array(
			self::section(
				array(
					self::widget( 'html', array( 'html' => WebinoCRM_Site_Chrome::header_html() ) ),
				),
				array(
					'layout'  => 'full_width',
					'padding' => array(
						'unit'     => 'px',
						'top'      => '0',
						'right'    => '0',
						'bottom'   => '0',
						'left'     => '0',
						'isLinked' => true,
					),
				)
			),
		);
	}

	/**
	 * Footer template data.
	 *
	 * @return array<int,mixed>
	 */
	public static function build_footer() {
		return array(
			self::section(
				array(
					self::widget( 'html', array( 'html' => WebinoCRM_Site_Chrome::footer_html() ) ),
				),
				array( 'layout' => 'full_width' )
			),
		);
	}

	/**
	 * 404 template.
	 *
	 * @return array<int,mixed>
	 */
	public static function build_404() {
		return array(
			self::section_full(
				array(
					self::widget(
						'html',
						array(
							'html' => self::html_hero(
								array(
									'title'   => 'صفحه پیدا نشد',
									'excerpt' => 'مسیر مورد نظر وجود ندارد.',
									'hero'    => array( 'title' => 'صفحه پیدا نشد', 'lead' => 'به خانه برگردید یا با ما تماس بگیرید.', 'bullets' => array() ),
								),
								false
							) . '<p class="webina-404-back"><a class="webina-btn webina-btn--gold" href="' . esc_url( home_url( '/' ) ) . '">بازگشت به خانه</a></p>',
						)
					),
				)
			),
		);
	}

	/**
	 * Portfolio archive.
	 *
	 * @return array<int,mixed>
	 */
	public static function build_portfolio_archive() {
		return array(
			self::section_full(
				array(
					self::widget(
						'html',
						array(
							'html' => self::html_hero(
								array(
									'title'   => 'نمونه‌کارها',
									'excerpt' => 'پروژه‌های منتخب وبینا با ROI شفاف.',
									'hero'    => array(
										'title'   => 'نمونه‌کارها',
										'lead'    => 'فیلتر بر اساس خدمت و صنعت',
										'bullets' => array(),
									),
								),
								false
							),
						)
					),
				)
			),
			self::section(
				array(
					self::widget(
						'webina_portfolio_grid',
						array(
							'heading'     => 'پروژه‌ها',
							'show_filter' => 'yes',
						)
					),
				)
			),
		);
	}

	/**
	 * Portfolio single.
	 *
	 * @return array<int,mixed>
	 */
	public static function build_portfolio_single() {
		return array(
			self::section(
				array(
					self::widget(
						'webina_case_study_hero',
						array(
							'heading' => 'جزئیات پروژه',
						)
					),
				),
				array( 'layout' => 'full_width' )
			),
			self::section(
				array(
					self::widget(
						'theme-post-content',
						array()
					),
				)
			),
		);
	}

	/**
	 * @return string
	 */
	private static function id() {
		return dechex( wp_rand( 0, 0xfffffff ) );
	}
}
