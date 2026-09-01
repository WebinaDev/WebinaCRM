<?php
/**
 * Persian marketing copy generator for seeded pages.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Content {

	/**
	 * @param array<string,mixed> $node IA node.
	 * @return array<string,mixed>
	 */
	public static function for_node( $node ) {
		$title    = (string) ( $node['title'] ?? '' );
		$section  = (string) ( $node['section'] ?? '' );
		$slug     = (string) ( $node['slug'] ?? '' );
		$template = self::detect_template( $node );
		$headline = self::clean_title( $title );
		$excerpt  = self::excerpt_for( $headline, $section, $template );
		$blocks   = self::blocks_for( $headline, $section, $template, $node );

		return array_merge(
			array(
				'title'       => $headline,
				'excerpt'     => $excerpt,
				'body'        => self::body_html_from_blocks( $blocks ),
				'template'    => $template,
				'seo_title'   => $headline . ' | وبینا',
				'seo_desc'    => $excerpt,
				'focus_kw'    => $headline,
				'cta_label'   => 'درخواست مشاوره رایگان',
				'cta_url'     => home_url( '/landing/free-consultation/' ),
				'hero'        => array(
					'title'   => $headline,
					'lead'    => $excerpt,
					'bullets' => $blocks['hero_bullets'],
				),
			),
			$blocks
		);
	}

	/**
	 * Public title cleaner for IA helpers.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	public static function clean_title_public( $title ) {
		return self::clean_title( $title );
	}

	/**
	 * One-line blurb for cards.
	 *
	 * @param string $headline Headline.
	 * @return string
	 */
	public static function short_blurb( $headline ) {
		return "اجرای {$headline} با تیم اختصاصی وبینا، گزارش ROI و پشتیبانی مستمر.";
	}

	/**
	 * @param array<string,mixed> $node Node.
	 * @return string
	 */
	private static function detect_template( $node ) {
		$slug    = (string) ( $node['slug'] ?? '' );
		$section = (string) ( $node['section'] ?? '' );
		$depth   = WebinoCRM_Site_IA::section_depth( $node );

		if ( 'home' === $slug ) {
			return 'home';
		}
		if ( in_array( $slug, array( 'about', 'contact', 'careers', 'team' ), true ) ) {
			return 'static';
		}
		if ( in_array( $slug, array( 'free-consultation', 'proposal', 'pricing' ), true ) ) {
			return 'landing';
		}
		if ( in_array( $slug, array( 'terms', 'privacy', 'trust-seal' ), true ) ) {
			return 'legal';
		}
		if ( in_array( $slug, array( 'faq', 'blog', 'academy', 'downloads' ), true ) ) {
			return 'resource';
		}
		if ( 'services' === $section ) {
			if ( $depth <= 2 ) {
				return 'service_hub';
			}
			if ( 3 === $depth ) {
				return 'service_group';
			}
			return 'service_leaf';
		}
		if ( 'solutions' === $section ) {
			if ( $depth <= 2 ) {
				return 'solution_hub';
			}
			if ( 3 === $depth ) {
				return 'solution_niche';
			}
			return 'solution_leaf';
		}
		return 'generic';
	}

	/**
	 * @param string $title Raw title.
	 * @return string
	 */
	private static function clean_title( $title ) {
		$title = preg_replace( '/\s*\([^)]*\)\s*/u', '', $title );
		$title = preg_replace( '/\s*(Home|Services|Solutions|Pages|Portfolio|Resources|Landing|Legal|Tech|Growth|Branding|Strategy|Support|SEO|Ads|Social|AI|UI UX|FAQ|Retail|Health|Corporate|Education|Hospitality)\s*/ui', ' ', $title );
		return trim( preg_replace( '/\s+/u', ' ', $title ) );
	}

	/**
	 * @param string              $headline Headline.
	 * @param string              $section Section key.
	 * @param string              $template Template key.
	 * @param array<string,mixed> $node Node.
	 * @return array<string,mixed>
	 */
	private static function blocks_for( $headline, $section, $template, $node ) {
		$stats = array(
			array( 'v' => '+142%', 'l' => 'رشد لید واجد شرایط' ),
			array( 'v' => '400+', 'l' => 'پروژه اجراشده' ),
			array( 'v' => '<30m', 'l' => 'پاسخ پشتیبانی' ),
			array( 'v' => '6 ماه', 'l' => 'قرارداد رشد با KPI' ),
		);

		$pain_points = array(
			"{$headline} بدون استراتژی واحد، هزینه تیم‌های پراکنده را بالا می‌برد.",
			'گزارش‌دهی شفاف برای هیئت‌مدیره وجود ندارد و ROI دیجیتال مبهم می‌ماند.',
			'قیف فروش از لید تا قرارداد شکسته است و CRM به‌درستی سیم‌کشی نشده.',
		);

		$outcomes = array(
			"نقشه راه {$headline} با KPI قابل اندازه‌گیری",
			'افزایش نرخ تبدیل و کیفیت لیدها',
			'یکپارچگی وب، CRM و اتوماسیون در یک تیم',
			'گزارش هفتگی و جلسه استراتژی ماهانه',
		);

		$process_steps = array(
			array( 't' => 'تحلیل وضعیت', 'd' => 'ممیزی فنی، بازار و قیف فروش فعلی' ),
			array( 't' => 'استراتژی ۹۰ روزه', 'd' => 'اولویت‌بندی کانال‌ها و بودجه' ),
			array( 't' => 'طراحی و اجرا', 'd' => 'تیم فنی + مارکتینگ وبینا' ),
			array( 't' => 'بهینه‌سازی', 'd' => 'A/B، سئو، CRO و اتوماسیون' ),
			array( 't' => 'گزارش ROI', 'd' => 'داشبورد مدیریتی برای مدیرعامل' ),
		);

		$features = array(
			array( 't' => 'مدیر حساب اختصاصی', 'd' => 'یک نقطه تماس برای تصمیم‌گیری سریع' ),
			array( 't' => 'تیم چندرشته‌ای', 'd' => 'فنی، سئو، طراحی و استراتژی' ),
			array( 't' => 'قرارداد KPI', 'd' => 'شاخص‌های شفاف از روز اول' ),
			array( 't' => 'پشتیبانی VIP', 'd' => 'پاسخ زیر ۳۰ دقیقه در ساعات کاری' ),
		);

		$faq = array(
			array( 'q' => "مدت اجرای {$headline} چقدر است؟", 'a' => 'بسته به مقیاس، از ۴ هفته برای لندینگ تا ۳–۶ ماه برای پروژه‌های سازمانی.' ),
			array( 'q' => 'گزارش‌دهی چگونه است؟', 'a' => 'داشبورد هفتگی + جلسه ماهانه استراتژی با مدیر حساب.' ),
			array( 'q' => 'آیا پشتیبانی فنی دارید؟', 'a' => 'بله؛ نگهداری، امنیت و بهینه‌سازی مستمر در قراردادهای وبینا.' ),
			array( 'q' => 'حداقل بودجه چقدر است؟', 'a' => 'پس از جلسه مشاوره، پکیج متناسب با اهداف و صنعت پیشنهاد می‌شود.' ),
		);

		$hero_bullets = array(
			'استراتژی رشد B2B',
			'گزارش ROI شفاف',
			'تیم اجرای یکپارچه',
		);

		if ( 'home' === $template ) {
			$hero_bullets = array( 'طراحی و فناوری', 'SEO و رشد', 'CRM و اتوماسیون' );
		}

		return array(
			'hero_bullets'  => $hero_bullets,
			'pain_points'   => $pain_points,
			'outcomes'      => $outcomes,
			'process_steps' => $process_steps,
			'features'      => $features,
			'faq'           => $faq,
			'stats'         => $stats,
			'child_links'   => WebinoCRM_Site_IA::child_links( $node, 12 ),
		);
	}

	/**
	 * @param string $headline Headline.
	 * @param string $section Section key.
	 * @param string $template Template key.
	 * @return string
	 */
	private static function excerpt_for( $headline, $section, $template ) {
		$map = array(
			'home'           => 'وبینا؛ فراتر از نتیجه — نقشه راه رشد دیجیتال، طراحی، SEO و اتوماسیون برای کسب‌وکارهایی که نتیجه می‌خواهند.',
			'static'         => 'تیم وبینا همراه راهبردی شما در مسیر رشد پایدار، شفافیت و اجرای حرفه‌ای.',
			'landing'        => 'فرم کوتاه را پر کنید؛ کارشناس وبینا در کمتر از ۳۰ دقیقه با شما تماس می‌گیرد.',
			'legal'          => 'مستندات حقوقی و شفافیت اطلاعات کاربران در وب‌سایت وبینا.',
			'service_hub'    => "خدمات {$headline} وبینا — طراحی، اجرا و بهینه‌سازی با KPI شفاف.",
			'service_group'  => "{$headline} با رویکرد ROI محور، گزارش‌دهی هفتگی و تیم اختصاصی.",
			'service_leaf'   => "{$headline}؛ از استراتژی تا اجرا و پشتیبانی VIP توسط وبینا.",
			'solution_hub'   => "راهکار {$headline} برای صنایع پرحاشیه — قیف فروش، CRM و برندینگ.",
			'solution_niche' => "تخصص وبینا در {$headline} — کمپین، محتوا و زیرساخت فروش.",
			'solution_leaf'  => "پیاده‌سازی {$headline} با تیم فنی و مارکتینگ وبینا.",
			'resource'       => 'منابع آموزشی و دانش تخصصی وبینا برای رشد کسب‌وکار شما.',
		);
		return $map[ $template ] ?? "خدمات {$headline} توسط آژانس توسعه کسب‌وکار وبینا.";
	}

	/**
	 * @param array<string,mixed> $blocks Blocks.
	 * @return string
	 */
	private static function body_html_from_blocks( $blocks ) {
		$html = '';
		if ( ! empty( $blocks['outcomes'] ) ) {
			$html .= '<h2>دستاوردهای کلیدی</h2><ul>';
			foreach ( $blocks['outcomes'] as $item ) {
				$html .= '<li>' . esc_html( (string) $item ) . '</li>';
			}
			$html .= '</ul>';
		}
		if ( ! empty( $blocks['features'] ) ) {
			$html .= '<h2>چرا وبینا؟</h2><ul>';
			foreach ( $blocks['features'] as $f ) {
				$html .= '<li><strong>' . esc_html( (string) ( $f['t'] ?? '' ) ) . '</strong> — ' . esc_html( (string) ( $f['d'] ?? '' ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '<p><strong>آماده شروع هستید؟</strong> همین امروز جلسه مشاوره رایگان رزرو کنید.</p>';
		return $html;
	}
}
