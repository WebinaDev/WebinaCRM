<?php

namespace WebinaBaleBusiness\Core;

use WebinaBaleBusiness\Admin\ContentTypes;
use WebinaBaleBusiness\Admin\BusinessAdminSync;
use WebinaBaleBusiness\Api\WebhookController;
use WebinaBaleBusiness\Bot\Router;

class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		if ( is_admin() ) {
			// CRM dashboard + REST replace legacy wp-admin screens for Bale.
			ContentTypes::init();
			BusinessAdminSync::init();
			add_action( 'admin_init', array( $this, 'redirect_legacy_wbb_admin_pages' ) );
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		}

		WebhookController::instance()->init();
		Router::instance()->init();
	}

	/**
	 * Redirect old Bale admin slugs to the CRM dashboard route.
	 */
	public function redirect_legacy_wbb_admin_pages(): void {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $page, array( 'wbb-dashboard', 'wbb-settings', 'wbb-diagnostics' ), true ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/dashboard/bale-business' ) );
		exit;
	}

	public function dependency_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( class_exists( '\WooCommerce' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'ربات کسب و کار وبینا برای ثبت سفارش و پرداخت به ووکامرس نیاز دارد.', 'webinocrm' ) . '</p></div>';
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function settings_defaults(): array {
		return array(
			'bot_token'                    => '',
			'provider_token'               => '',
			'channel_id'                   => '',
			'channel_join_url'             => '',
			'membership_required'          => '1',
			'webhook_mode'                 => 'rest',
			'enable_menu_features'         => '1',
			'enable_menu_support'          => '1',
			'enable_menu_profile'          => '1',
			'enable_menu_businesses'       => '1',
			'enable_menu_pricing'          => '1',
			'enable_menu_faq'              => '1',
			'enable_menu_why_bale'         => '1',
			'enable_auto_register_user'    => '1',
			'enable_auto_lead_from_business' => '1',
			'sales_wc_product_id'          => 0,
			'currency_unit'                => 'rial',
			'welcome_text'                 => 'سلام 👋 خوش اومدی به ربات کسب و کار وبینا؛ جایی که خیلی سریع دوباره به مشتری‌هات وصل میشی.',
			'features_intro_text'          => 'اینجا همه قابلیت های ربات فروش وبینا رو می‌بینی؛ هر کدوم مستقیم برای رشد فروش و جذب مشتری طراحی شده.',
			'cta_order_text'               => 'ثبت درخواست فوری ربات',
			'faq_intro_text'               => 'سوالات پرتکرار',
			'contact_sales_text'           => 'برای مشاوره سریع و رزرو نوبت اجرا، همین الان با تیم فروش در ارتباط باش: @webina',
			'membership_gate_text'         => 'برای استفاده از ربات، ابتدا در کانال عضو شوید 👇',
			'membership_error_text'        => 'هنوز عضویت شما تایید نشد. ابتدا عضو کانال شوید و سپس بررسی عضویت را بزنید.',
			'start_hint_text'              => 'از منوی پایین شروع کن و مسیر مناسب کسب‌وکارت رو انتخاب کن 👇',
			'support_intro_text'           => 'هرجا سوال داشتی یا خواستی سریع تصمیم بگیری، تیم پشتیبانی وبینا کنارته.',
			'support_cta_text'             => 'برای اینکه نوبتت عقب نیفته، همین الان یکی از راه های ارتباطی رو انتخاب کن.',
			'support_items'                => array(
				array(
					'emoji'        => '🟣',
					'title'        => 'پشتیبانی بله',
					'description'  => 'ارتباط مستقیم در بله',
					'action_type'  => 'username',
					'action_value' => '@webina_support',
					'sort'         => 10,
				),
				array(
					'emoji'        => '📞',
					'title'        => 'تلفن پشتیبانی',
					'description'  => 'پاسخگویی در ساعات اداری',
					'action_type'  => 'phone',
					'action_value' => '02100000000',
					'sort'         => 20,
				),
			),
			'feature_docs'                 => self::default_feature_docs(),
			'catalog_items'                => self::default_catalog_items(),
			'faq_items'                    => self::default_faq_items(),
			'plan_product_map'             => array(),
			'sales_trust_text'             => 'از طراحی تا اجرا و پشتیبانی، همه چیز با همراهی مستقیم تیم وبینا انجام می شود.',
			'order_success_text'           => 'درخواستت ثبت شد ✅ برای قطعی شدن، پرداخت رو همین حالا انجام بده.',
			'payment_success_text'         => 'پرداختت با موفقیت انجام شد 🎉 خیلی زود برای ادامه کار باهات هماهنگ می‌کنیم.',
			'manual_payment_link_template' => 'برای پرداخت از این لینک استفاده کنید: {payment_url}',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( 'wbb_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::settings_defaults(), $stored );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public static function update_settings( array $settings ): void {
		update_option( 'wbb_settings', $settings );
	}

	public static function get_bot_token(): string {
		$s = self::get_settings();
		return (string) ( $s['bot_token'] ?? '' );
	}

	public static function get_provider_token(): string {
		$s = self::get_settings();
		return (string) ( $s['provider_token'] ?? '' );
	}

	public static function get_webhook_secret(): string {
		return '';
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private static function default_catalog_items(): array {
		return array(
			array(
				'key'         => 'store_navigation',
				'title'       => 'فروشگاه در چت',
				'description' => 'مرور دسته ها، جستجو، نمایش محصول و انتخاب تنوع',
			),
			array(
				'key'         => 'cart_checkout',
				'title'       => 'سبد و تسویه',
				'description' => 'افزودن/حذف محصول، کد تخفیف، ثبت سفارش',
			),
			array(
				'key'         => 'order_updates',
				'title'       => 'پیگیری سفارش',
				'description' => 'نمایش وضعیت سفارش و اعلان تغییر وضعیت',
			),
			array(
				'key'         => 'marketing',
				'title'       => 'بازاریابی',
				'description' => 'پیام گروهی، قالب پیام، شخصی سازی متون',
			),
		);
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private static function default_faq_items(): array {
		return array(
			array(
				'question' => 'این ربات روی چه پلتفرمی نصب می شود؟',
				'answer'   => 'روی وردپرس و ووکامرس و طبق استاندارد رسمی آنها.',
			),
			array(
				'question' => 'پرداخت چطور انجام می شود؟',
				'answer'   => 'هم از کیف پول بله و هم از لینک پرداخت ووکامرس پشتیبانی می شود.',
			),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function default_feature_docs(): array {
		return array(
			array(
				'key'             => 'menu_navigation',
				'title'           => 'شروع سریع خرید با منوی فروش هوشمند',
				'short'           => 'مشتری از همان پیام اول، مسیر خرید را بدون سردرگمی پیدا می کند و سریع تر به سفارش می رسد.',
				'details'         => 'منوی یکپارچه باعث می شود کاربر بین بخش های فروشگاه، جستجو، سبد و پشتیبانی به سرعت جابه جا شود. نتیجه مستقیم این تجربه، کاهش ریزش ابتدای مسیر و افزایش احتمال تبدیل به خرید است.',
				'steps'           => array( 'کاربر با start وارد ربات می شود', 'مسیر مناسب را از منوی اصلی انتخاب می کند', 'بدون اتلاف وقت به مرحله اقدام خرید می رسد' ),
				'limitations'     => array( 'برای تجربه کامل فروش، اتصال هویت کاربر به ووکامرس باید انجام شده باشد' ),
				'related_actions' => array( 'مشاهده فهرست امکانات', 'شروع ثبت درخواست' ),
				'tags'            => array( 'ux', 'navigation', 'router' ),
				'sort'            => 10,
			),
			array(
				'key'             => 'catalog_browse',
				'title'           => 'ویترین دسته بندی شده برای فروش بیشتر',
				'short'           => 'کاربر با دسته و برند، سریع تر محصول مناسب را پیدا می کند و نرخ تصمیم گیری خرید بالاتر می رود.',
				'details'         => 'نمایش ساختارمند کاتالوگ باعث می شود پیشنهادها دقیق تر دیده شوند و کاربر زمان کمتری برای یافتن محصول صرف کند. این موضوع مستقیم به افزایش تعامل روی کارت محصول و رشد سبد خرید کمک می کند.',
				'steps'           => array( 'انتخاب دسته محصول', 'اعمال فیلتر برند در صورت نیاز', 'مرور محصولات صفحه بندی شده و انتخاب مورد مناسب' ),
				'limitations'     => array( 'کیفیت ویترین فروش وابسته به تکمیل عنوان، تصویر و دسته بندی صحیح محصولات است' ),
				'related_actions' => array( 'مشاهده پلن ها و قیمت', 'تماس با فروش' ),
				'tags'            => array( 'catalog', 'category', 'brand', 'pagination' ),
				'sort'            => 20,
			),
			array(
				'key'             => 'product_details_variations',
				'title'           => 'صفحه محصول شفاف برای تصمیم خرید مطمئن',
				'short'           => 'با نمایش جزئیات کامل و تنوع ها، تردید مشتری کم می شود و انتخاب دقیق تری انجام می دهد.',
				'details'         => 'ارائه مشخصات کلیدی، موجودی و تنوع ها در بات، سوال های قبل از خرید را کمتر می کند. وقتی مشتری همان جا تنوع درست را انتخاب می کند، خطای خرید کاهش یافته و رضایت پس از خرید بالا می رود.',
				'steps'           => array( 'ورود به کارت محصول', 'بررسی جزئیات و ویژگی ها', 'انتخاب تنوع دقیق و افزودن به سبد' ),
				'limitations'     => array( 'برای نمایش صحیح، تنوع ها و موجودی باید در ووکامرس دقیق نگهداری شوند' ),
				'related_actions' => array( 'شروع ثبت درخواست' ),
				'tags'            => array( 'product', 'variation', 'stock' ),
				'sort'            => 30,
			),
			array(
				'key'             => 'search_engine',
				'title'           => 'جستجوی سریع برای شکار مشتری آماده خرید',
				'short'           => 'مشتری با یک کلمه، محصول مدنظر را سریع پیدا می کند و مسیر خرید کوتاه تر می شود.',
				'details'         => 'وقتی کاربر دقیقا می داند چه می خواهد، جستجوی سریع مهم ترین اهرم تبدیل است. این بخش او را مستقیم از نیت خرید به صفحه اقدام می رساند و زمان تصمیم را کم می کند.',
				'steps'           => array( 'ثبت کلیدواژه توسط کاربر', 'جستجو در محصولات ووکامرس', 'نمایش نتیجه همراه اقدام خرید' ),
				'limitations'     => array( 'دقت خروجی جستجو به کیفیت نام گذاری و توضیحات محصولات وابسته است' ),
				'related_actions' => array( 'تماس با فروش' ),
				'tags'            => array( 'search', 'discovery' ),
				'sort'            => 40,
			),
			array(
				'key'             => 'wishlist',
				'title'           => 'علاقه مندی برای بازگشت و خرید مجدد',
				'short'           => 'محصولات محبوب کاربر ذخیره می شود تا در زمان مناسب، خرید با یک کلیک تکمیل شود.',
				'details'         => 'Wishlist به شما کمک می کند مشتریان مردد را از دست ندهید. کاربر محصول را ذخیره می کند، بعدا برمی گردد و سریع تر خرید را نهایی می کند؛ یعنی افزایش نرخ بازگشت و فروش تکراری.',
				'steps'           => array( 'ثبت محصول در علاقه مندی', 'نگهداری لیست اختصاصی هر کاربر', 'بازگشت آسان به محصولات ذخیره شده' ),
				'limitations'     => array( 'برای حفظ لیست اختصاصی، اتصال هویت کاربر باید پایدار باشد' ),
				'related_actions' => array( 'مشاهده فهرست امکانات' ),
				'tags'            => array( 'wishlist', 'retention' ),
				'sort'            => 50,
			),
			array(
				'key'             => 'cart_coupon',
				'title'           => 'سبد خرید پویا برای کاهش رهاسازی',
				'short'           => 'کاربر داخل بات سبد را مدیریت می کند و با کوپن، انگیزه خرید فوری می گیرد.',
				'details'         => 'امکان ویرایش سریع سبد و اعمال تخفیف، اصطکاک مرحله خرید را کم می کند. وقتی کاربر بدون خروج از چت قیمت نهایی را ببیند، احتمال تکمیل سفارش بیشتر می شود.',
				'steps'           => array( 'باز کردن سبد خرید', 'ویرایش اقلام در لحظه', 'ثبت کوپن و مشاهده قیمت نهایی' ),
				'limitations'     => array( 'قوانین و اعتبار کوپن مطابق تنظیمات ووکامرس اعمال می شود' ),
				'related_actions' => array( 'مشاهده پلن ها و قیمت' ),
				'tags'            => array( 'cart', 'coupon' ),
				'sort'            => 60,
			),
			array(
				'key'             => 'checkout_address_shipping',
				'title'           => 'تسویه سریع با آدرس و ارسال مطمئن',
				'short'           => 'مشتری با چند انتخاب ساده، آدرس و روش ارسال را تکمیل می کند و سفارش سریع ثبت می شود.',
				'details'         => 'تجربه checkout در چت، مرحله حساس خرید را کوتاه و روان می کند. پشتیبانی از fallback های ارسال کمک می کند حتی در شرایط پیچیده، مسیر ثبت سفارش پایدار بماند و فروش از دست نرود.',
				'steps'           => array( 'انتخاب یا ثبت آدرس', 'انتخاب روش ارسال', 'ثبت نهایی سفارش از روی سبد' ),
				'limitations'     => array( 'در سناریوهای چندپکیج، ادامه تسویه ممکن است به صفحه سایت منتقل شود' ),
				'related_actions' => array( 'تماس با فروش' ),
				'tags'            => array( 'checkout', 'shipping', 'address' ),
				'sort'            => 70,
			),
			array(
				'key'             => 'payments',
				'title'           => 'پرداخت دو مسیره برای نهایی شدن فروش',
				'short'           => 'هم پرداخت کیف پول بله و هم لینک سایت فعال است تا هیچ خریدی به خاطر درگاه از دست نرود.',
				'details'         => 'کاربر بر اساس ترجیح خود مسیر پرداخت را انتخاب می کند. کنترل امنیتی pre-checkout اعتماد ایجاد می کند و مسیر جایگزین لینک سایت باعث می شود در خطای invoice نیز خرید متوقف نشود.',
				'steps'           => array( 'ایجاد سفارش', 'انتخاب پرداخت در بله یا سایت', 'تایید پرداخت و تکمیل نهایی سفارش' ),
				'limitations'     => array( 'برای پرداخت در بله، provider token معتبر و تنظیم درگاه لازم است' ),
				'related_actions' => array( 'شروع ثبت درخواست' ),
				'tags'            => array( 'payment', 'invoice', 'security' ),
				'sort'            => 80,
			),
			array(
				'key'             => 'orders_tracking',
				'title'           => 'رهگیری شفاف سفارش برای اعتماد مشتری',
				'short'           => 'مشتری وضعیت سفارش را لحظه ای می بیند و با اطمینان بیشتری خرید بعدی را انجام می دهد.',
				'details'         => 'دسترسی به جزئیات سفارش و رهگیری مرحله ای، تعداد پرسش های پشتیبانی را کم می کند و حس کنترل خرید به مشتری می دهد. همین شفافیت، اعتماد و تکرار خرید را تقویت می کند.',
				'steps'           => array( 'باز کردن سفارش های من', 'مشاهده جزئیات سفارش', 'رهگیری یا لغو در وضعیت مجاز' ),
				'limitations'     => array( 'لغو فقط در وضعیت های مجاز مانند pending و on-hold انجام می شود' ),
				'related_actions' => array( 'پشتیبانی' ),
				'tags'            => array( 'orders', 'tracking', 'account' ),
				'sort'            => 90,
			),
			array(
				'key'             => 'status_notifications',
				'title'           => 'اعلان هوشمند وضعیت برای حفظ ارتباط فروش',
				'short'           => 'با هر تغییر وضعیت، پیام درست و به موقع ارسال می شود تا مشتری همیشه در جریان بماند.',
				'details'         => 'قالب های پیام قابل شخصی سازی به شما کمک می کند ارتباط برند را در تمام چرخه سفارش حفظ کنید. این پیوستگی اطلاع رسانی، اضطراب مشتری را کم و رضایت نهایی را بیشتر می کند.',
				'steps'           => array( 'تغییر وضعیت سفارش', 'تولید پیام از قالب تعریف شده', 'ارسال خودکار به کاربر در بله' ),
				'limitations'     => array( 'برای سفارش های مهمان فاقد شناسه بله، ارسال مستقیم ممکن نیست' ),
				'related_actions' => array( 'تماس با فروش' ),
				'tags'            => array( 'notifications', 'template' ),
				'sort'            => 100,
			),
			array(
				'key'             => 'broadcast_admin_messaging',
				'title'           => 'بازاریابی مستقیم با پیام انبوه هدفمند',
				'short'           => 'کمپین های پیام رسانی را سریع اجرا کنید و کاربران آماده خرید را دوباره فعال کنید.',
				'details'         => 'ارسال انبوه زمان بندی شده و پیام مستقیم ادمین، کانال فروش شما را همیشه فعال نگه می دارد. می توانید لینک پرداخت، خلاصه سفارش یا پیشنهاد ویژه را دقیق و سریع به مخاطب برسانید.',
				'steps'           => array( 'تعریف پیام یا کمپین', 'ارسال مرحله ای از صف', 'تحلیل نتیجه از لاگ اجرا' ),
				'limitations'     => array( 'کاربر باید قبلا به ربات متصل شده باشد تا پیام را دریافت کند' ),
				'related_actions' => array( 'شروع ثبت درخواست' ),
				'tags'            => array( 'broadcast', 'admin', 'messaging' ),
				'sort'            => 110,
			),
			array(
				'key'             => 'channel_sync_marketing',
				'title'           => 'انتشار خودکار محصول در کانال برای جذب سریع لید',
				'short'           => 'محصولات شما بدون کار دستی در کانال منتشر می شوند و مخاطب مستقیم وارد مسیر خرید می شود.',
				'details'         => 'سینک هوشمند محصول با کانال، بازوی بازاریابی شما را مقیاس پذیر می کند. با کنترل hash و تصویر، به جای انتشار تکراری، پیام بهینه ویرایش می شود تا ظاهر حرفه ای کانال حفظ شود.',
				'steps'           => array( 'به روزرسانی محصول در ووکامرس', 'بررسی نیاز به انتشار یا ویرایش', 'ارسال پیام کانال با CTA خرید' ),
				'limitations'     => array( 'تنظیم صحیح شناسه یا لینک کانال برای کارکرد پایدار الزامی است' ),
				'related_actions' => array( 'مشاهده فهرست امکانات' ),
				'tags'            => array( 'channel', 'marketing', 'sync' ),
				'sort'            => 120,
			),
			array(
				'key'             => 'analytics_diagnostics',
				'title'           => 'داشبورد تصمیم گیری برای رشد فروش',
				'short'           => 'شاخص های کلیدی را لحظه ای ببینید و سریع روی نقاط افت یا فرصت فروش اقدام کنید.',
				'details'         => 'داشبورد و لاگ ها به شما دید عملیاتی می دهند تا کمپین، فلو خرید و عملکرد ربات را بهینه کنید. با تشخیص سریع خطاها، از افت فروش جلوگیری می شود و پایداری تجربه مشتری بالا می رود.',
				'steps'           => array( 'بررسی KPI های اصلی', 'کنترل webhook و سلامت اتصال', 'تحلیل رخدادها و بهبود مسیر فروش' ),
				'limitations'     => array( 'دقت تحلیل وابسته به ثبت منظم رخدادها و تگ گذاری صحیح است' ),
				'related_actions' => array( 'پشتیبانی' ),
				'tags'            => array( 'analytics', 'logs', 'diagnostics' ),
				'sort'            => 130,
			),
			array(
				'key'             => 'hidden_edge_cases',
				'title'           => 'پایداری فروش در سناریوهای حساس',
				'short'           => 'مسیرهای fallback هوشمند کمک می کنند حتی در خطاهای خاص، فروش متوقف نشود.',
				'details'         => 'این لایه محافظتی دقیقا برای زمانی است که شرایط عادی جواب نمی دهد. از fallback حمل تا جلوگیری از پیام تکراری و مسیر جایگزین پرداخت، همه چیز برای حفظ تجربه خرید پایدار و جلوگیری از ریزش مشتری طراحی شده است.',
				'steps'           => array( 'تشخیص شرایط غیرعادی', 'فعال سازی مسیر جایگزین مناسب', 'ثبت رخداد برای بهبود بعدی' ),
				'limitations'     => array( 'برخی سناریوهای پیشرفته به تنظیم دقیق افزونه ها و زیرساخت فروشگاه نیاز دارند' ),
				'related_actions' => array( 'تماس با فروش', 'پشتیبانی' ),
				'tags'            => array( 'edge-case', 'resilience', 'fallback' ),
				'sort'            => 140,
			),
		);
	}
}
