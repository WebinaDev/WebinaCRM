<?php
/**
 * Long-scroll Elementor section stacks per page template.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Page_Stacks {

	/**
	 * @param string              $template Template key.
	 * @param array<string,mixed> $node IA node.
	 * @param array<string,mixed> $copy Content copy.
	 * @return array<int,mixed>
	 */
	public static function for_template( $template, $node, $copy ) {
		switch ( $template ) {
			case 'home':
				return self::home( $copy );
			case 'service_hub':
			case 'solution_hub':
				return self::hub( $node, $copy );
			case 'service_group':
			case 'service_leaf':
			case 'solution_niche':
			case 'solution_leaf':
				return self::leaf( $node, $copy );
			case 'static':
				return self::static_page( $node, $copy );
			case 'landing':
				return self::landing( $copy );
			case 'resource':
			case 'legal':
				return self::resource( $copy );
			default:
				return self::generic( $copy );
		}
	}

	/**
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function home( $copy ) {
		$b = WebinoCRM_Site_Elementor_Builder::class;
		$html = class_exists( 'WebinoCRM_Site_Home_Clone' )
			? WebinoCRM_Site_Home_Clone::render( $copy )
			: '';
		return array(
			$b::section_canvas(
				array(
					$b::widget( 'html', array( 'html' => $html ) ),
				)
			),
		);
	}

	/**
	 * @param array<string,mixed> $node Node.
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function hub( $node, $copy ) {
		$b     = WebinoCRM_Site_Elementor_Builder::class;
		$title = (string) ( $copy['title'] ?? '' );
		$slug  = (string) ( $node['slug'] ?? '' );
		$en    = strtoupper( preg_replace( '/[^a-z0-9]+/i', ' ', $slug ) );
		return array(
			$b::section( array( $b::widget( 'webina_client_marquee', array() ) ), array( 'padding' => self::pad( 20, 20 ) ) ),
			$b::section(
				array(
					$b::widget(
						'webina_page_hero',
						array(
							'heading'     => $title,
							'en_label'    => $en ?: 'SERVICES',
							'description' => (string) ( $copy['excerpt'] ?? '' ),
						)
					),
				),
				array( 'padding' => self::pad( 0, 24 ) )
			),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_child_grid( $copy ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_ai_badges', array() ) ) ),
			$b::section( array( $b::widget( 'webina_portfolio_carousel', array( 'heading' => 'نمونه‌کارهای مرتبط' ) ) ) ),
			$b::section( array( $b::widget( 'webina_stats_strip', array( 'heading' => 'نتایج در پروژه‌های مشابه' ) ) ) ),
			$b::section( array( $b::widget( 'webina_timeline', array( 'heading' => 'مسیر اجرا' ) ) ) ),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_rich_body( (string) ( $copy['body'] ?? '' ) ) ) ) ) ),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_faq( $copy ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_lead_form', array( 'heading' => 'سفارش ' . $title ) ) ) ),
			$b::section_full(
				array(
					$b::widget(
						'webina_cta_fullbleed',
						array(
							'heading'     => (string) ( $copy['cta_label'] ?? 'مشاوره رایگان' ),
							'description' => 'برای ' . $title . ' با ما در ارتباط باشید.',
						)
					),
				)
			),
		);
	}

	/**
	 * @param array<string,mixed> $node Node.
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function leaf( $node, $copy ) {
		$b     = WebinoCRM_Site_Elementor_Builder::class;
		$title = (string) ( $copy['title'] ?? '' );
		return array(
			$b::section(
				array(
					$b::widget(
						'webina_page_hero',
						array(
							'heading'     => $title,
							'en_label'    => 'SERVICE',
							'description' => (string) ( $copy['excerpt'] ?? '' ),
						)
					),
				),
				array( 'padding' => self::pad( 0, 24 ) )
			),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_pain_outcomes( $copy ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_bento_grid', array( 'heading' => 'ویژگی‌های اجرایی', 'description' => 'آنچه در همکاری با وبینا دریافت می‌کنید.' ) ) ) ),
			$b::section( array( $b::widget( 'webina_timeline', array( 'heading' => 'فرآیند پروژه' ) ) ) ),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_rich_body( (string) ( $copy['body'] ?? '' ) ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_pricing_table', array( 'heading' => 'پکیج‌های پیشنهادی' ) ) ) ),
			$b::section( array( $b::widget( 'webina_portfolio_carousel', array( 'heading' => 'نمونه‌کارهای مرتبط' ) ) ) ),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_faq( $copy ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_lead_form', array( 'heading' => 'درخواست مشاوره' ) ) ) ),
			$b::section_full(
				array(
					$b::widget(
						'webina_cta_fullbleed',
						array(
							'heading'     => (string) ( $copy['cta_label'] ?? 'مشاوره رایگان' ),
							'description' => 'تیم وبینا آماده شروع پروژه شماست.',
						)
					),
				)
			),
		);
	}

	/**
	 * @param array<string,mixed> $node Node.
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function static_page( $node, $copy ) {
		$b    = WebinoCRM_Site_Elementor_Builder::class;
		$slug = (string) ( $node['slug'] ?? '' );
		$title = (string) ( $copy['title'] ?? '' );

		if ( 'about' === $slug ) {
			return array(
				$b::section(
					array(
						$b::widget(
							'webina_page_hero',
							array(
								'heading'     => 'داستان وبینا',
								'en_label'    => 'ABOUT US',
								'description' => (string) ( $copy['excerpt'] ?? '' ),
							)
						),
					),
					array( 'padding' => self::pad( 0, 24 ) )
				),
				$b::section( array( $b::widget( 'html', array( 'html' => $b::html_rich_body( (string) ( $copy['body'] ?? '' ) ) ) ) ) ),
				$b::section( array( $b::widget( 'webina_logo_marquee', array( 'heading' => 'برندهایی که به ما اعتماد کردند' ) ) ) ),
				$b::section( array( $b::widget( 'webina_vision_mission', array() ) ) ),
				$b::section( array( $b::widget( 'webina_ceo_quote', array() ) ) ),
				$b::section( array( $b::widget( 'webina_team_carousel', array() ) ) ),
				$b::section( array( $b::widget( 'html', array( 'html' => $b::html_story_timeline() ) ) ) ),
				$b::section( array( $b::widget( 'webina_stats_strip', array( 'heading' => 'تعداد پروژه‌های اجرا شده' ) ) ) ),
				$b::section( array( $b::widget( 'webina_lead_form', array() ) ) ),
			);
		}

		if ( 'team' === $slug ) {
			return array(
				$b::section(
					array(
						$b::widget(
							'webina_page_hero',
							array(
								'heading'     => 'اعضای تیم وبینا',
								'en_label'    => 'OUR TEAM',
								'description' => 'آشنایی با اعضای تیم آژانس توسعه کسب‌وکار وبینا',
							)
						),
					),
					array( 'padding' => self::pad( 0, 24 ) )
				),
				$b::section( array( $b::widget( 'webina_ceo_quote', array() ) ) ),
				$b::section( array( $b::widget( 'webina_team_carousel', array( 'heading' => 'اعضای تیم وبینا', 'show_all_link' => '' ) ) ) ),
				$b::section(
					array(
						$b::widget(
							'html',
							array(
								'html' => '<section class="webina-rich-body"><h2>اهداف تیم ما</h2><p>هماهنگی و تفکر گروهی، دو عنصر اساسی موفقیت در تیم ماست. باور داریم هر عضو نقشی حیاتی در دستیابی به اهداف سازمان دارد.</p></section>',
							)
						),
					)
				),
				$b::section( array( $b::widget( 'webina_lead_form', array() ) ) ),
			);
		}

		if ( 'contact' === $slug ) {
			return array(
				$b::section(
					array(
						$b::widget(
							'webina_page_hero',
							array(
								'heading'     => 'تماس با ما',
								'en_label'    => 'CONTACT US',
								'description' => (string) ( $copy['excerpt'] ?? '' ),
							)
						),
					),
					array( 'padding' => self::pad( 0, 24 ) )
				),
				$b::section( array( $b::widget( 'webina_contact_cards', array() ) ) ),
				$b::section( array( $b::widget( 'webina_lead_form', array( 'heading' => 'درخواست مشاوره رایگان' ) ) ) ),
			);
		}

		if ( 'careers' === $slug ) {
			return array(
				$b::section(
					array(
						$b::widget(
							'webina_page_hero',
							array(
								'heading'     => 'همکاری با ما',
								'en_label'    => 'WORK WITH US',
								'description' => 'شما هم می‌توانید عضوی از تیم وبینا باشید.',
							)
						),
					),
					array( 'padding' => self::pad( 0, 24 ) )
				),
				$b::section( array( $b::widget( 'webina_careers_form', array() ) ) ),
			);
		}

		return array(
			$b::section(
				array(
					$b::widget(
						'webina_page_hero',
						array(
							'heading'     => $title,
							'en_label'    => 'PAGE',
							'description' => (string) ( $copy['excerpt'] ?? '' ),
						)
					),
				),
				array( 'padding' => self::pad( 0, 24 ) )
			),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_rich_body( (string) ( $copy['body'] ?? '' ) ) ) ) ) ),
			$b::section_full( array( $b::widget( 'webina_cta_fullbleed', array( 'heading' => 'همکاری با وبینا', 'description' => 'برای جلسه معارفه درخواست دهید.' ) ) ) ),
		);
	}

	/**
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function landing( $copy ) {
		$b = WebinoCRM_Site_Elementor_Builder::class;
		return array(
			$b::section(
				array(
					$b::widget(
						'webina_page_hero',
						array(
							'heading'     => (string) ( $copy['title'] ?? 'مشاوره رایگان' ),
							'en_label'    => 'CONSULTATION',
							'description' => (string) ( $copy['excerpt'] ?? '' ),
						)
					),
				),
				array( 'padding' => self::pad( 0, 24 ) )
			),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_pain_outcomes( $copy ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_stats_strip', array( 'heading' => 'چرا مدیران به وبینا اعتماد می‌کنند' ) ) ) ),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_faq( $copy ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_lead_form', array( 'heading' => 'رزرو مشاوره رایگان' ) ) ) ),
		);
	}

	/**
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function resource( $copy ) {
		$b = WebinoCRM_Site_Elementor_Builder::class;
		return array(
			$b::section(
				array(
					$b::widget(
						'webina_page_hero',
						array(
							'heading'     => (string) ( $copy['title'] ?? 'منابع' ),
							'en_label'    => 'RESOURCES',
							'description' => (string) ( $copy['excerpt'] ?? '' ),
						)
					),
				),
				array( 'padding' => self::pad( 0, 24 ) )
			),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_rich_body( (string) ( $copy['body'] ?? '' ) ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_blog_cards', array() ) ) ),
			$b::section_full( array( $b::widget( 'webina_cta_fullbleed', array( 'heading' => 'مشاوره رایگان', 'description' => 'سوال دارید؟ با ما در ارتباط باشید.' ) ) ) ),
		);
	}

	/**
	 * @param array<string,mixed> $copy Copy.
	 * @return array<int,mixed>
	 */
	private static function generic( $copy ) {
		$b = WebinoCRM_Site_Elementor_Builder::class;
		return array(
			$b::section(
				array(
					$b::widget(
						'webina_page_hero',
						array(
							'heading'     => (string) ( $copy['title'] ?? 'وبینا' ),
							'en_label'    => 'WEBINA',
							'description' => (string) ( $copy['excerpt'] ?? '' ),
						)
					),
				),
				array( 'padding' => self::pad( 0, 24 ) )
			),
			$b::section( array( $b::widget( 'html', array( 'html' => $b::html_rich_body( (string) ( $copy['body'] ?? '' ) ) ) ) ) ),
			$b::section( array( $b::widget( 'webina_stats_strip', array() ) ) ),
			$b::section_full( array( $b::widget( 'webina_cta_fullbleed', array( 'heading' => (string) ( $copy['cta_label'] ?? 'مشاوره' ) ) ) ) ),
		);
	}

	/**
	 * @param int $top Top padding.
	 * @param int $bottom Bottom padding.
	 * @return array<string,mixed>
	 */
	private static function pad( $top, $bottom ) {
		return array(
			'unit'     => 'px',
			'top'      => (string) $top,
			'right'    => '24',
			'bottom'   => (string) $bottom,
			'left'     => '24',
			'isLinked' => false,
		);
	}
}
