<?php
/**
 * Central Webina brand copy for public site (editable placeholders).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string,mixed>
 */
function webinocrm_site_brand() {
	static $brand = null;
	if ( null !== $brand ) {
		return $brand;
	}

	$brand = array(
		'name'        => 'وبینا',
		'name_en'     => 'Webina',
		'tagline'     => 'فراتر از نتیجه',
		'tagline_en'  => 'Beyond the Result',
		'domain'      => 'webina.dev',
		'phone'       => '021-91000000',
		'phone_tel'   => '+982191000000',
		'phone_display' => '۰۰۰ ۰۰۰ ۹۱ - ۰۲۱',
		'email'       => 'info@webina.dev',
		'address'     => 'تهران، ایران',
		'hours'       => 'شنبه تا چهارشنبه ۹ تا ۱۸',
		'ceo_name'    => 'تیم مدیریت وبینا',
		'ceo_title'   => 'مدیرعامل آژانس وبینا',
		'ceo_quote'   => 'در سال‌های گذشته به کمک تیمی متخصص و به‌روز توانسته‌ایم پروژه‌های متعددی را با موفقیت به پایان برسانیم. موفقیت ترکیبی از نبوغ فنی و همکاری تیمی است؛ ما آماده‌ایم شما را برای رسیدن به اهداف بزرگ‌تر یاری کنیم.',
		'vision'      => 'وبینا با هدف ارتقاء تجربه کاربری از طریق طراحی و بهینه‌سازی وب‌سایت‌ها، افزایش درآمد کسب‌وکارها و تربیت نیروهای متخصص به فعالیت خود ادامه می‌دهد.',
		'mission'     => 'مأموریت ما ارائه خدمات نوآورانه برای بهبود تجربه کاربری، افزایش فرصت‌های درآمدی و همراهی پایدار کسب‌وکارها در مسیر رشد دیجیتال است.',
		'services'    => array(
			'seo'       => 'خدمات SEO',
			'webdesign' => 'طراحی وب‌سایت',
			'uiux'      => 'طراحی رابط کاربری',
			'growth'    => 'رشد و بازاریابی',
			'crm'       => 'CRM و اتوماسیون',
			'strategy'  => 'استراتژی دیجیتال',
			'other'     => 'سایر خدمات',
		),
		'social'      => array(
			'instagram' => '#',
			'linkedin'  => '#',
			'telegram'  => '#',
			'whatsapp'  => '#',
			'youtube'   => '#',
		),
		'team'        => array(
			array(
				'name'   => 'مدیرعامل وبینا',
				'role'   => 'Chief Executive Officer',
				'role_fa'=> 'مدیرعامل',
				'linkedin' => '#',
			),
			array(
				'name'   => 'مدیر عملیات',
				'role'   => 'Director of Operations',
				'role_fa'=> 'مدیر امور اجرایی',
				'linkedin' => '#',
			),
			array(
				'name'   => 'مدیر فنی',
				'role'   => 'CTO',
				'role_fa'=> 'مدیر فنی',
				'linkedin' => '#',
			),
			array(
				'name'   => 'مدیر سئو',
				'role'   => 'SEO Team Lead',
				'role_fa'=> 'مدیر سئو',
				'linkedin' => '#',
			),
			array(
				'name'   => 'طراح محصول',
				'role'   => 'Product Designer',
				'role_fa'=> 'طراح محصول',
				'linkedin' => '#',
			),
			array(
				'name'   => 'کارشناس سئو',
				'role'   => 'SEO Specialist',
				'role_fa'=> 'کارشناس سئو',
				'linkedin' => '#',
			),
		),
		'testimonials' => array(
			array(
				'role' => 'مدیرعامل — صنعت B2B',
				'name' => 'مشتری وبینا',
				'text' => 'استراتژی هدفمند و اجرای دقیق تیم وبینا به ما کمک کرد مشتریان بالقوه بیشتری جذب کنیم و درآمد سازمان را نسبت به دوره گذشته به‌طور محسوس افزایش دهیم.',
			),
			array(
				'role' => 'مدیر بازاریابی — آموزش آنلاین',
				'name' => 'مشتری وبینا',
				'text' => 'گزارش‌های شفاف و بهینه‌سازی مستمر، تصمیم‌گیری هیئت‌مدیره را آسان کرد. همکاری با وبینا سرمایه‌گذاری بلندمدت و هوشمندانه بوده است.',
			),
			array(
				'role' => 'مدیر دیجیتال — فروشگاه آنلاین',
				'name' => 'مشتری وبینا',
				'text' => 'با اجرای تکنیک‌های پیشنهادی، شاهد رشد مشتریان جدید و افزایش فروش ماهانه بودیم. طراحی و سئو در یک تیم واحد پاسخگو بود.',
			),
			array(
				'role' => 'مدیرعامل — خدمات تخصصی',
				'name' => 'مشتری وبینا',
				'text' => 'تجربه‌ای حرفه‌ای و سازنده. تیم مجرب وبینا با دقت و تعهد ما را در دستیابی به رتبه‌های بهتر و تجربه کاربری قوی‌تر یاری کردند.',
			),
		),
		'client_logos' => array(
			'برند آلفا', 'برند بتا', 'برند گاما', 'برند دلتا', 'برند اپسیلون',
			'برند زتا', 'برند اتا', 'برند تتا', 'برند یوتا', 'برند کاپا',
			'برند لامبدا', 'برند مو',
		),
		'stats' => array(
			array( 'v' => '60', 'suffix' => '%', 'l' => 'افزایش نرخ کلیک' ),
			array( 'v' => '45', 'suffix' => '%', 'l' => 'نرخ مشارکت کاربران' ),
			array( 'v' => '35', 'suffix' => '%', 'l' => 'نرخ بازگشت کاربران' ),
		),
		/**
		 * Named media slots for homepage layers.
		 * Put files under assets/site/media/home/ or override URLs here.
		 * Empty = procedural SVG/CSS scene (Webina-branded).
		 */
		'media' => array(
			'hero'         => '',
			'seo_device_lg' => '',
			'seo_device_sm' => '',
			'ports'        => array( '', '', '', '', '', '', '', '' ),
			'ui_ports'     => array( '', '', '', '', '', '', '' ),
			'goals_team'   => '',
			'ceo'          => '',
			'team'         => array( '', '', '', '', '', '' ),
			'lead_people'  => '',
			'footer_back'  => '',
			'logos'        => array(),
		),
		'timeline' => array(
			array( 'year' => '۱۳۹۹', 'title' => 'همه چیز داشت شروع می‌شد', 'desc' => 'تولد آژانس وبینا با تمرکز بر خدمات دیجیتال و رشد کسب‌وکار.' ),
			array( 'year' => '۱۴۰۰', 'title' => 'قدم‌های اول', 'desc' => 'گسترش تیم و توسعه تنوع خدمات سئو، طراحی و استراتژی.' ),
			array( 'year' => '۱۴۰۱', 'title' => 'گسترش تیم و پروژه‌ها', 'desc' => 'اجرای ده‌ها پروژه طراحی و سئو در صنایع مختلف.' ),
			array( 'year' => '۱۴۰۳', 'title' => 'توسعه آژانس', 'desc' => 'گسترش خدمات CRM، اتوماسیون و راهکارهای سازمانی.' ),
		),
	);

	/**
	 * Filter brand data for site chrome/widgets.
	 *
	 * @param array<string,mixed> $brand Brand.
	 */
	return apply_filters( 'webinocrm_site_brand', $brand );
}
