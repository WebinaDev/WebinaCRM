<?php
/**
 * ERP module registry — sidebar categories, menu items, module toggles.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central ERP module definitions (mirrors client/src/modules/registry.ts).
 */
class WebinoCRM_Erp_Module_Registry {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_modules() {
		return array(
			array(
				'id'                  => 'hrm',
				'settings_key'        => 'hrm',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'مدیریت منابع انسانی', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'hrm-menu',
						'path'     => 'hrm/staff',
						'title'    => __( 'منابع انسانی', 'webinocrm' ),
						'icon'     => 'ri-user-star-line',
						'children' => array(
							array(
								'id'    => 'staff',
								'path'  => 'hrm/staff',
								'title' => __( 'کارکنان', 'webinocrm' ),
								'icon'  => 'ri-user-star-line',
							),
							array(
								'id'    => 'hrm-attendance',
								'path'  => 'hrm/attendance',
								'title' => __( 'حضور و غیاب', 'webinocrm' ),
								'icon'  => 'ri-calendar-check-line',
							),
							array(
								'id'    => 'hrm-leave',
								'path'  => 'hrm/leave',
								'title' => __( 'مرخصی', 'webinocrm' ),
								'icon'  => 'ri-calendar-event-line',
							),
							array(
								'id'    => 'hrm-payroll',
								'path'  => 'hrm/payroll',
								'title' => __( 'حقوق و دستمزد', 'webinocrm' ),
								'icon'  => 'ri-money-dollar-circle-line',
							),
							array(
								'id'    => 'hrm-payroll-decrees',
								'path'  => 'hrm/payroll/decrees',
								'title' => __( 'احکام کارگزینی', 'webinocrm' ),
								'icon'  => 'ri-file-list-3-line',
							),
							array(
								'id'    => 'hrm-my-payroll',
								'path'  => 'hrm/my-payroll',
								'title' => __( 'فیش حقوق من', 'webinocrm' ),
								'icon'  => 'ri-wallet-3-line',
							),
							array(
								'id'    => 'hrm-me',
								'path'  => 'hrm/me',
								'title' => __( 'پورتال من', 'webinocrm' ),
								'icon'  => 'ri-dashboard-line',
							),
							array(
								'id'    => 'hrm-my-time',
								'path'  => 'hrm/my-time',
								'title' => __( 'زمان و حضور', 'webinocrm' ),
								'icon'  => 'ri-time-line',
							),
							array(
								'id'    => 'hrm-my-docs',
								'path'  => 'hrm/my-docs',
								'title' => __( 'مدارک من', 'webinocrm' ),
								'icon'  => 'ri-file-text-line',
							),
							array(
								'id'    => 'hrm-my-insurance',
								'path'  => 'hrm/my-insurance',
								'title' => __( 'بیمه من', 'webinocrm' ),
								'icon'  => 'ri-shield-user-line',
							),
							array(
								'id'    => 'hrm-my-org',
								'path'  => 'hrm/my-org',
								'title' => __( 'سازمان', 'webinocrm' ),
								'icon'  => 'ri-building-line',
							),
							array(
								'id'    => 'hrm-my-profile',
								'path'  => 'hrm/my-profile',
								'title' => __( 'پروفایل من', 'webinocrm' ),
								'icon'  => 'ri-user-settings-line',
							),
							array(
								'id'    => 'hrm-cartable',
								'path'  => 'hrm/cartable',
								'title' => __( 'کارتابل HR', 'webinocrm' ),
								'icon'  => 'ri-inbox-line',
							),
							array(
								'id'    => 'hrm-recruitment',
								'path'  => 'hrm/recruitment',
								'title' => __( 'استخدام', 'webinocrm' ),
								'icon'  => 'ri-user-add-line',
							),
							array(
								'id'    => 'hrm-performance',
								'path'  => 'hrm/performance',
								'title' => __( 'ارزیابی عملکرد', 'webinocrm' ),
								'icon'  => 'ri-bar-chart-box-line',
							),
							array(
								'id'    => 'hrm-training',
								'path'  => 'hrm/training',
								'title' => __( 'آموزش', 'webinocrm' ),
								'icon'  => 'ri-graduation-cap-line',
							),
						),
					),
				),
			),
			array(
				'id'                  => 'finance',
				'settings_key'        => 'finance',
				'legacy_settings'     => array( 'accounting' ),
				'default_enabled'     => true,
				'category'            => __( 'مدیریت مالی و حسابداری', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'finance-menu',
						'path'     => 'finance',
						'title'    => __( 'حسابداری', 'webinocrm' ),
						'icon'     => 'ri-calculator-line',
						'children' => array(
							array( 'id' => 'accounting', 'path' => 'finance', 'title' => __( 'داشبورد حسابداری', 'webinocrm' ), 'icon' => 'ri-calculator-line' ),
							array( 'id' => 'accounting-persons', 'path' => 'finance/persons', 'title' => __( 'اشخاص (طرف‌های حساب)', 'webinocrm' ), 'icon' => 'ri-user-line' ),
							array( 'id' => 'accounting-products', 'path' => 'finance/products', 'title' => __( 'کالا و خدمات', 'webinocrm' ), 'icon' => 'ri-box-3-line' ),
							array( 'id' => 'accounting-invoices', 'path' => 'finance/invoices', 'title' => __( 'فاکتورها', 'webinocrm' ), 'icon' => 'ri-file-list-3-line' ),
							array( 'id' => 'accounting-cash-accounts', 'path' => 'finance/cash-accounts', 'title' => __( 'حساب‌های بانک/صندوق', 'webinocrm' ), 'icon' => 'ri-bank-line' ),
							array( 'id' => 'accounting-receipts', 'path' => 'finance/receipts', 'title' => __( 'رسید و پرداخت', 'webinocrm' ), 'icon' => 'ri-exchange-dollar-line' ),
							array( 'id' => 'accounting-checks', 'path' => 'finance/checks', 'title' => __( 'چک‌ها', 'webinocrm' ), 'icon' => 'ri-bank-card-line' ),
							array( 'id' => 'accounting-chart', 'path' => 'finance/chart', 'title' => __( 'نمودار حساب‌ها', 'webinocrm' ), 'icon' => 'ri-book-open-line' ),
							array( 'id' => 'accounting-journals', 'path' => 'finance/journals', 'title' => __( 'اسناد حسابداری', 'webinocrm' ), 'icon' => 'ri-file-list-3-line' ),
							array( 'id' => 'accounting-ledger', 'path' => 'finance/ledger', 'title' => __( 'دفتر کل / معین', 'webinocrm' ), 'icon' => 'ri-book-2-line' ),
							array( 'id' => 'accounting-reports', 'path' => 'finance/reports', 'title' => __( 'گزارشات مالی', 'webinocrm' ), 'icon' => 'ri-bar-chart-2-line' ),
							array( 'id' => 'accounting-fiscal-year', 'path' => 'finance/fiscal-year', 'title' => __( 'سال مالی', 'webinocrm' ), 'icon' => 'ri-calendar-line' ),
							array( 'id' => 'accounting-settings', 'path' => 'finance/settings', 'title' => __( 'تنظیمات حسابداری', 'webinocrm' ), 'icon' => 'ri-settings-3-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'crm',
				'settings_key'        => 'crm',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'مدیریت ارتباط با مشتری', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'crm-menu',
						'path'     => 'crm/leads',
						'title'    => __( 'CRM', 'webinocrm' ),
						'icon'     => 'ri-user-add-line',
						'children' => array(
							array( 'id' => 'leads', 'path' => 'crm/leads', 'title' => __( 'سرنخ‌ها', 'webinocrm' ), 'icon' => 'ri-user-add-line' ),
							array( 'id' => 'customers', 'path' => 'crm/customers', 'title' => __( 'مشتریان', 'webinocrm' ), 'icon' => 'ri-group-line' ),
							array( 'id' => 'tickets', 'path' => 'crm/tickets', 'title' => __( 'تیکت‌ها', 'webinocrm' ), 'icon' => 'ri-customer-service-2-line' ),
							array( 'id' => 'consultations', 'path' => 'crm/consultations', 'title' => __( 'مشاوره‌ها', 'webinocrm' ), 'icon' => 'ri-discuss-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'pm',
				'settings_key'        => 'pm',
				'legacy_settings'     => array( 'projects' ),
				'default_enabled'     => true,
				'category'            => __( 'مدیریت پروژه', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'pm-menu',
						'path'     => 'pm/projects',
						'title'    => __( 'پروژه', 'webinocrm' ),
						'icon'     => 'ri-folder-2-line',
						'children' => array(
							array( 'id' => 'projects', 'path' => 'pm/projects', 'title' => __( 'پروژه‌ها', 'webinocrm' ), 'icon' => 'ri-folder-2-line' ),
							array( 'id' => 'tasks', 'path' => 'pm/tasks', 'title' => __( 'وظایف', 'webinocrm' ), 'icon' => 'ri-task-line' ),
							array( 'id' => 'chat', 'path' => 'pm/chat', 'title' => __( 'چت تیمی', 'webinocrm' ), 'icon' => 'ri-message-2-line' ),
							array( 'id' => 'time-tracking', 'path' => 'pm/time-tracking', 'title' => __( 'زمان‌سنجی', 'webinocrm' ), 'icon' => 'ri-time-line' ),
							array( 'id' => 'appointments', 'path' => 'pm/appointments', 'title' => __( 'قرار ملاقات‌ها', 'webinocrm' ), 'icon' => 'ri-calendar-check-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'scm',
				'settings_key'        => 'scm',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'زنجیره تأمین و انبار', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'scm-menu',
						'path'     => 'scm/warehouses',
						'title'    => __( 'انبار', 'webinocrm' ),
						'icon'     => 'ri-building-4-line',
						'children' => array(
							array( 'id' => 'accounting-warehouses', 'path' => 'scm/warehouses', 'title' => __( 'انبارها', 'webinocrm' ), 'icon' => 'ri-building-4-line' ),
							array( 'id' => 'accounting-warehouse-stock', 'path' => 'scm/stock', 'title' => __( 'موجودی انبار', 'webinocrm' ), 'icon' => 'ri-stack-line' ),
							array( 'id' => 'accounting-warehouse-inbound', 'path' => 'scm/inbound', 'title' => __( 'ورود به انبار', 'webinocrm' ), 'icon' => 'ri-inbox-archive-line' ),
							array( 'id' => 'accounting-warehouse-outbound', 'path' => 'scm/outbound', 'title' => __( 'خروج از انبار', 'webinocrm' ), 'icon' => 'ri-inbox-unarchive-line' ),
							array( 'id' => 'accounting-warehouse-audit', 'path' => 'scm/audit', 'title' => __( 'انبارگردانی', 'webinocrm' ), 'icon' => 'ri-file-search-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'sales',
				'settings_key'        => 'sales',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'فروش و بازاریابی', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'sales-menu',
						'path'     => 'sales/invoices',
						'title'    => __( 'فروش', 'webinocrm' ),
						'icon'     => 'ri-file-list-3-line',
						'children' => array(
							array( 'id' => 'invoices', 'path' => 'sales/invoices', 'title' => __( 'پیش‌فاکتورها', 'webinocrm' ), 'icon' => 'ri-file-list-3-line' ),
							array( 'id' => 'services', 'path' => 'sales/catalog', 'title' => __( 'خدمات و محصولات', 'webinocrm' ), 'icon' => 'ri-service-line' ),
							array( 'id' => 'campaigns', 'path' => 'sales/campaigns', 'title' => __( 'کمپین‌ها', 'webinocrm' ), 'icon' => 'ri-megaphone-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'mfg',
				'settings_key'        => 'mfg',
				'legacy_settings'     => array(),
				'default_enabled'     => false,
				'category'            => __( 'مدیریت تولید', 'webinocrm' ),
				'items'               => array(
					array( 'id' => 'mfg-overview', 'path' => 'mfg', 'title' => __( 'نمای کلی تولید', 'webinocrm' ), 'icon' => 'ri-settings-5-line' ),
				),
			),
			array(
				'id'                  => 'docs',
				'settings_key'        => 'docs',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'اسناد و قراردادها', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'docs-menu',
						'path'     => 'docs/contracts',
						'title'    => __( 'اسناد', 'webinocrm' ),
						'icon'     => 'ri-file-text-line',
						'children' => array(
							array( 'id' => 'contracts', 'path' => 'docs/contracts', 'title' => __( 'قراردادها', 'webinocrm' ), 'icon' => 'ri-file-text-line' ),
							array( 'id' => 'documents', 'path' => 'docs/files', 'title' => __( 'اسناد', 'webinocrm' ), 'icon' => 'ri-folder-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'distribution',
				'settings_key'        => 'distribution',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'توزیع، بازارچه و لایسنس', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'distribution-menu',
						'path'     => 'admin/marketplace/products',
						'title'    => __( 'بازارچه', 'webinocrm' ),
						'icon'     => 'ri-store-2-line',
						'children' => array(
							array( 'id' => 'marketplace-products', 'path' => 'admin/marketplace/products', 'title' => __( 'بازارچه / محصولات', 'webinocrm' ), 'icon' => 'ri-store-2-line' ),
							array( 'id' => 'marketplace-gitea', 'path' => 'admin/marketplace/gitea', 'title' => __( 'سرور پکیج (Gitea)', 'webinocrm' ), 'icon' => 'ri-git-branch-line' ),
							array( 'id' => 'marketplace-categories', 'path' => 'admin/marketplace/categories', 'title' => __( 'دسته‌های بازارچه', 'webinocrm' ), 'icon' => 'ri-folder-line' ),
							array( 'id' => 'marketplace-orders', 'path' => 'admin/marketplace/orders', 'title' => __( 'سفارش‌های بازارچه', 'webinocrm' ), 'icon' => 'ri-store-2-line' ),
							array( 'id' => 'licenses', 'path' => 'admin/licenses', 'title' => __( 'لایسنس‌های مارکت‌پلیس', 'webinocrm' ), 'icon' => 'ri-key-2-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'ai_content',
				'settings_key'        => 'ai_content',
				'legacy_settings'     => array(),
				'default_enabled'     => true,
				'category'            => __( 'هوش مصنوعی محتوا', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'ai-content-menu',
						'path'     => 'ai-content',
						'title'    => __( 'هوش مصنوعی محتوا', 'webinocrm' ),
						'icon'     => 'ri-sparkling-line',
						'children' => array(
							array( 'id' => 'ai-content', 'path' => 'ai-content', 'title' => __( 'نمای کلی', 'webinocrm' ), 'icon' => 'ri-dashboard-line' ),
							array( 'id' => 'ai-content-jobs', 'path' => 'ai-content/jobs', 'title' => __( 'کارها', 'webinocrm' ), 'icon' => 'ri-list-check-2' ),
							array( 'id' => 'ai-content-calendar', 'path' => 'ai-content/calendar', 'title' => __( 'تقویم', 'webinocrm' ), 'icon' => 'ri-calendar-line' ),
							array( 'id' => 'ai-content-products', 'path' => 'ai-content/products', 'title' => __( 'محصولات', 'webinocrm' ), 'icon' => 'ri-shopping-bag-line' ),
							array( 'id' => 'ai-content-titles', 'path' => 'ai-content/titles', 'title' => __( 'عناوین', 'webinocrm' ), 'icon' => 'ri-text' ),
							array( 'id' => 'ai-content-pages', 'path' => 'ai-content/pages', 'title' => __( 'صفحات', 'webinocrm' ), 'icon' => 'ri-file-list-3-line' ),
							array( 'id' => 'ai-content-taxonomies', 'path' => 'ai-content/taxonomies', 'title' => __( 'دسته‌ها', 'webinocrm' ), 'icon' => 'ri-folder-line' ),
							array( 'id' => 'ai-content-attributes', 'path' => 'ai-content/attributes', 'title' => __( 'ویژگی‌ها', 'webinocrm' ), 'icon' => 'ri-price-tag-3-line' ),
							array( 'id' => 'ai-content-cms-pages', 'path' => 'pages', 'title' => __( 'ویرایش صفحات', 'webinocrm' ), 'icon' => 'ri-pages-line' ),
							array( 'id' => 'ai-content-settings', 'path' => 'ai-content/settings', 'title' => __( 'تنظیمات', 'webinocrm' ), 'icon' => 'ri-settings-3-line' ),
						),
					),
				),
			),
			array(
				'id'                  => 'admin',
				'settings_key'        => 'admin',
				'legacy_settings'     => array( 'general', 'bots' ),
				'default_enabled'     => true,
				'category'            => __( 'سیستم و تنظیمات', 'webinocrm' ),
				'items'               => array(
					array(
						'id'       => 'admin-menu',
						'path'     => 'admin/settings',
						'title'    => __( 'سیستم', 'webinocrm' ),
						'icon'     => 'ri-settings-3-line',
						'children' => array(
							array( 'id' => 'logs', 'path' => 'admin/logs', 'title' => __( 'لاگ‌ها', 'webinocrm' ), 'icon' => 'ri-file-list-2-line' ),
							array( 'id' => 'visitor-statistics', 'path' => 'admin/analytics/visitors', 'title' => __( 'آمار بازدید', 'webinocrm' ), 'icon' => 'ri-line-chart-line' ),
							array( 'id' => 'settings', 'path' => 'admin/settings', 'title' => __( 'تنظیمات عمومی', 'webinocrm' ), 'icon' => 'ri-settings-3-line' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Module enablement map without translated strings (safe before init).
	 *
	 * @return array<int, array{settings_key:string,legacy_settings:array<int,string>,default_enabled:bool}>
	 */
	public static function module_settings_map() {
		return array(
			array( 'settings_key' => 'hrm', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'finance', 'legacy_settings' => array( 'accounting' ), 'default_enabled' => true ),
			array( 'settings_key' => 'crm', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'pm', 'legacy_settings' => array( 'projects' ), 'default_enabled' => true ),
			array( 'settings_key' => 'scm', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'sales', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'mfg', 'legacy_settings' => array(), 'default_enabled' => false ),
			array( 'settings_key' => 'docs', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'distribution', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'ai_content', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'admin', 'legacy_settings' => array( 'general', 'bots' ), 'default_enabled' => true ),
			array( 'settings_key' => 'modirpayamak', 'legacy_settings' => array(), 'default_enabled' => true ),
			array( 'settings_key' => 'bale_business', 'legacy_settings' => array(), 'default_enabled' => true ),
		);
	}

	/**
	 * Whether an ERP module is enabled in settings.
	 *
	 * @param string $module_key settings_key from registry.
	 * @return bool
	 */
	public static function is_module_enabled( $module_key ) {
		$settings = class_exists( 'WebinoCRM_Settings_Handler' ) ? WebinoCRM_Settings_Handler::get_all_settings() : array();

		foreach ( self::module_settings_map() as $module ) {
			$keys = array_merge( array( $module['settings_key'] ), (array) $module['legacy_settings'] );
			if ( ! in_array( $module_key, $keys, true ) ) {
				continue;
			}
			$has_setting = false;
			foreach ( $keys as $key ) {
				$opt = 'module_' . $key . '_enabled';
				if ( ! isset( $settings[ $opt ] ) ) {
					continue;
				}
				$has_setting = true;
				if ( (string) $settings[ $opt ] === '1' ) {
					return true;
				}
			}
			if ( $has_setting ) {
				return false;
			}
			return (bool) $module['default_enabled'];
		}

		$opt = 'module_' . $module_key . '_enabled';
		if ( isset( $settings[ $opt ] ) ) {
			return (string) $settings[ $opt ] === '1';
		}
		return true;
	}

	/**
	 * Whether a registry menu item should be omitted from the sidebar.
	 *
	 * @param array<string,mixed> $item Registry item.
	 * @return bool
	 */
	private static function should_skip_menu_item( array $item ) {
		if ( 'visitor-statistics' === ( $item['id'] ?? '' ) ) {
			if ( ! class_exists( 'WebinoCRM_Settings_Handler' ) || ! WebinoCRM_Settings_Handler::is_visitor_tracking_enabled() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert a registry menu definition to a sidebar row (flat link or type: menu).
	 *
	 * @param string              $dashboard_url Base dashboard URL.
	 * @param array<string,mixed> $item          Registry item (optional children).
	 * @return array<string,mixed>|null
	 */
	private static function menu_item_def_to_row( $dashboard_url, array $item ) {
		$dashboard_url = untrailingslashit( $dashboard_url );

		if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
			$children = array();
			foreach ( $item['children'] as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$child_row = self::menu_item_def_to_row( $dashboard_url, $child );
				if ( null !== $child_row ) {
					$children[] = $child_row;
				}
			}
			if ( empty( $children ) ) {
				return null;
			}
			return array(
				'type'     => 'menu',
				'id'       => $item['id'],
				'title'    => $item['title'],
				'url'      => $dashboard_url . '/' . ltrim( $item['path'], '/' ),
				'icon'     => $item['icon'],
				'children' => $children,
			);
		}

		if ( self::should_skip_menu_item( $item ) ) {
			return null;
		}

		return array(
			'id'    => $item['id'],
			'title' => $item['title'],
			'url'   => $dashboard_url . '/' . ltrim( $item['path'], '/' ),
			'icon'  => $item['icon'],
		);
	}

	/**
	 * Append ERP module menu blocks for manager role.
	 *
	 * @param string $dashboard_url Base dashboard URL.
	 * @param array  $items         Existing items.
	 * @return array
	 */
	public static function append_manager_module_menus( $dashboard_url, $items ) {
		$dashboard_url = untrailingslashit( $dashboard_url );

		foreach ( self::get_modules() as $module ) {
			if ( ! self::is_module_enabled( $module['settings_key'] ) ) {
				continue;
			}

			$items[] = array(
				'type'  => 'category',
				'id'    => 'cat-' . $module['id'],
				'title' => $module['category'],
			);

			foreach ( $module['items'] as $item ) {
				$row = self::menu_item_def_to_row( $dashboard_url, $item );
				if ( null !== $row ) {
					$items[] = $row;
				}
			}

			$items = self::append_submodule_menus( $dashboard_url, $items, $module['id'] );
		}

		return $items;
	}

	/**
	 * Sellable sub-modules (independent toggles under a parent ERP module).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_submodules() {
		return array(
			array(
				'id'               => 'modirpayamak',
				'settings_key'     => 'modirpayamak',
				'parent_module'    => 'sales',
				'marketplace_slug' => 'modirpayamak-module',
				'default_enabled'  => true,
				'menu'             => array(
					'id'       => 'modirpayamak',
					'path'     => 'admin/integrations/modirpayamak',
					'title'    => __( 'مدیرپیامک', 'webinocrm' ),
					'icon'     => 'ri-message-2-line',
					'children' => self::get_modirpayamak_menu_children(),
				),
			),
			array(
				'id'               => 'bale-business',
				'settings_key'     => 'bale_business',
				'parent_module'    => 'sales',
				'marketplace_slug' => 'bale-business-module',
				'default_enabled'  => true,
				'menu'             => array(
					'id'    => 'bale-business',
					'path'  => 'admin/integrations/bale',
					'title' => __( 'ربات کسب‌وکار', 'webinocrm' ),
					'icon'  => 'ri-robot-2-line',
				),
			),
		);
	}

	/**
	 * ModirPayamak nested sidebar items (URLs unchanged).
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_modirpayamak_menu_children() {
		return array(
			array( 'id' => 'modirpayamak-send', 'path' => 'admin/integrations/modirpayamak/send', 'title' => __( 'ارسال پیامک', 'webinocrm' ), 'icon' => 'ri-send-plane-line' ),
			array( 'id' => 'modirpayamak-reports', 'path' => 'admin/integrations/modirpayamak/reports', 'title' => __( 'گزارش ارسال', 'webinocrm' ), 'icon' => 'ri-file-list-line' ),
			array( 'id' => 'modirpayamak-customers', 'path' => 'admin/integrations/modirpayamak/customers', 'title' => __( 'مشتریان پیامک', 'webinocrm' ), 'icon' => 'ri-group-line' ),
			array( 'id' => 'modirpayamak-packages', 'path' => 'admin/integrations/modirpayamak/packages', 'title' => __( 'بسته شارژ', 'webinocrm' ), 'icon' => 'ri-wallet-line' ),
			array( 'id' => 'modirpayamak-tariffs', 'path' => 'admin/integrations/modirpayamak/tariffs', 'title' => __( 'تعرفه‌ها', 'webinocrm' ), 'icon' => 'ri-price-tag-3-line' ),
			array( 'id' => 'modirpayamak-orders', 'path' => 'admin/integrations/modirpayamak/orders', 'title' => __( 'سفارش شارژ', 'webinocrm' ), 'icon' => 'ri-shopping-cart-line' ),
			array( 'id' => 'modirpayamak-patterns', 'path' => 'admin/integrations/modirpayamak/patterns', 'title' => __( 'پترن‌ها', 'webinocrm' ), 'icon' => 'ri-code-box-line' ),
			array( 'id' => 'modirpayamak-phonebooks', 'path' => 'admin/integrations/modirpayamak/phonebooks', 'title' => __( 'دفترچه', 'webinocrm' ), 'icon' => 'ri-contacts-book-line' ),
			array( 'id' => 'modirpayamak-numbers', 'path' => 'admin/integrations/modirpayamak/numbers', 'title' => __( 'خطوط', 'webinocrm' ), 'icon' => 'ri-phone-line' ),
			array( 'id' => 'modirpayamak-users', 'path' => 'admin/integrations/modirpayamak/users', 'title' => __( 'کاربران IPPanel', 'webinocrm' ), 'icon' => 'ri-user-settings-line' ),
			array( 'id' => 'modirpayamak-tickets', 'path' => 'admin/integrations/modirpayamak/tickets', 'title' => __( 'تیکت‌ها', 'webinocrm' ), 'icon' => 'ri-customer-service-line' ),
			array( 'id' => 'modirpayamak-drafts', 'path' => 'admin/integrations/modirpayamak/drafts', 'title' => __( 'پیش‌نویس', 'webinocrm' ), 'icon' => 'ri-draft-line' ),
			array( 'id' => 'modirpayamak-settings', 'path' => 'admin/integrations/modirpayamak/settings', 'title' => __( 'تنظیمات مدیرپیامک', 'webinocrm' ), 'icon' => 'ri-settings-3-line' ),
		);
	}

	/**
	 * Whether a sellable sub-module is enabled (parent module must also be on).
	 *
	 * @param string $settings_key Submodule settings key.
	 * @return bool
	 */
	public static function is_submodule_enabled( $settings_key ) {
		$sub = null;
		foreach ( self::get_submodules() as $candidate ) {
			if ( (string) $candidate['settings_key'] === (string) $settings_key ) {
				$sub = $candidate;
				break;
			}
		}
		if ( ! $sub ) {
			return true;
		}
		if ( ! empty( $sub['parent_module'] ) && ! self::is_module_enabled( $sub['parent_module'] ) ) {
			return false;
		}
		$settings = class_exists( 'WebinoCRM_Settings_Handler' ) ? WebinoCRM_Settings_Handler::get_all_settings() : array();
		$opt      = 'module_' . $settings_key . '_enabled';
		if ( isset( $settings[ $opt ] ) ) {
			return (string) $settings[ $opt ] === '1';
		}
		return (bool) ( $sub['default_enabled'] ?? true );
	}

	/**
	 * Map a sidebar menu id to its sub-module settings key (empty if none).
	 *
	 * @param string $menu_id Menu item id.
	 * @return string
	 */
	public static function submodule_key_for_menu_id( $menu_id ) {
		$menu_id = (string) $menu_id;
		if ( 'bale-business' === $menu_id ) {
			return 'bale_business';
		}
		if ( 'modirpayamak' === $menu_id || 0 === strpos( $menu_id, 'modirpayamak-' ) ) {
			return 'modirpayamak';
		}
		return '';
	}

	/**
	 * Menu ids gated by the distribution ERP module.
	 *
	 * @return array<int, string>
	 */
	public static function distribution_menu_ids() {
		return array(
			'marketplace-products',
			'marketplace-gitea',
			'marketplace-categories',
			'marketplace-orders',
			'licenses',
		);
	}

	/**
	 * Append enabled sub-module menus under a parent ERP module block.
	 *
	 * @param string $dashboard_url Base dashboard URL.
	 * @param array  $items         Menu rows for current module.
	 * @param string $parent_module_id Parent module id.
	 * @return array
	 */
	public static function append_submodule_menus( $dashboard_url, $items, $parent_module_id ) {
		$dashboard_url = untrailingslashit( $dashboard_url );
		foreach ( self::get_submodules() as $sub ) {
			if ( (string) $sub['parent_module'] !== (string) $parent_module_id ) {
				continue;
			}
			if ( ! self::is_submodule_enabled( $sub['settings_key'] ) ) {
				continue;
			}
			$menu = $sub['menu'];
			$row  = self::menu_item_def_to_row( $dashboard_url, $menu );
			if ( null !== $row ) {
				$items[] = $row;
			}
		}
		return $items;
	}

	/**
	 * Apply sub-module entitlement (marketplace grant/revoke hook).
	 *
	 * @param string $marketplace_slug Product slug from registry.
	 * @param bool   $enabled          Whether entitlement is active.
	 * @return bool
	 */
	public static function apply_submodule_entitlement( $marketplace_slug, $enabled ) {
		foreach ( self::get_submodules() as $sub ) {
			if ( empty( $sub['marketplace_slug'] ) || (string) $sub['marketplace_slug'] !== (string) $marketplace_slug ) {
				continue;
			}
			if ( ! class_exists( 'WebinoCRM_Settings_Handler' ) ) {
				return false;
			}
			$settings = WebinoCRM_Settings_Handler::get_all_settings();
			$key      = 'module_' . $sub['settings_key'] . '_enabled';
			$settings[ $key ] = $enabled ? '1' : '0';
			WebinoCRM_Settings_Handler::update_settings( $settings );
			/**
			 * Fires when a sellable sub-module entitlement changes.
			 *
			 * @param string $settings_key Submodule settings key.
			 * @param bool   $enabled    New enabled state.
			 * @param string $marketplace_slug Product slug.
			 */
			do_action( 'webinocrm_submodule_entitlement_changed', $sub['settings_key'], $enabled, $marketplace_slug );
			return true;
		}
		return false;
	}

	/**
	 * All ERP menu item ids from the module registry tree.
	 *
	 * @return array<int, string>
	 */
	public static function all_menu_ids() {
		$ids = array();
		self::collect_menu_ids_from_modules( self::get_modules(), $ids );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $modules Module rows.
	 * @param array<int, string>             $ids     Collected ids (by reference).
	 * @return void
	 */
	private static function collect_menu_ids_from_modules( array $modules, array &$ids ) {
		foreach ( $modules as $module ) {
			if ( ! is_array( $module ) || empty( $module['items'] ) || ! is_array( $module['items'] ) ) {
				continue;
			}
			self::collect_menu_ids_from_items( $module['items'], $ids );
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $items Menu rows.
	 * @param array<int, string>             $ids   Collected ids (by reference).
	 * @return void
	 */
	private static function collect_menu_ids_from_items( array $items, array &$ids ) {
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['id'] ) ) {
				$ids[] = sanitize_key( (string) $item['id'] );
			}
			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				self::collect_menu_ids_from_items( $item['children'], $ids );
			}
		}
	}

	/**
	 * Menu item ids allowed per role (subset of full manager menu).
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function role_menu_ids() {
		return array(
			'sales_consultant' => array( 'dashboard', 'leads', 'contracts', 'customers' ),
			'finance_manager'  => array(
				'dashboard',
				'reports',
				'docs/contracts',
				'sales/invoices',
				'accounting',
				'accounting-persons',
				'accounting-products',
				'accounting-invoices',
				'accounting-cash-accounts',
				'accounting-receipts',
				'accounting-checks',
				'accounting-chart',
				'accounting-journals',
				'accounting-ledger',
				'accounting-reports',
				'accounting-fiscal-year',
				'accounting-settings',
				'accounting-warehouses',
				'accounting-warehouse-stock',
				'accounting-warehouse-inbound',
				'accounting-warehouse-outbound',
				'accounting-warehouse-audit',
			),
			'team_member'      => array(
				'dashboard',
				'projects',
				'tasks',
				'chat',
				'documents',
				'time-tracking',
				'tickets',
				'hrm-me',
				'hrm-my-payroll',
				'hrm-my-time',
				'hrm-my-docs',
				'hrm-my-insurance',
				'hrm-my-org',
				'hrm-my-profile',
			),
			'client'           => array( 'dashboard', 'projects', 'contracts', 'invoices', 'tickets' ),
			'customer'         => array( 'dashboard', 'projects', 'contracts', 'invoices', 'tickets' ),
		);
	}

	/**
	 * Build filtered menu for a role using manager menu as source.
	 *
	 * @param string $user_role Role slug.
	 * @param array  $source    Full manager menu.
	 * @return array
	 */
	public static function filter_menu_for_role( $user_role, $source ) {
		$allowed = isset( self::role_menu_ids()[ $user_role ] ) ? self::role_menu_ids()[ $user_role ] : array();
		if ( empty( $allowed ) ) {
			return array();
		}

		$allowed_set = array_flip( $allowed );
		$out         = array();
		$last_cat    = null;

		foreach ( $source as $row ) {
			if ( isset( $row['type'] ) && 'category' === $row['type'] ) {
				$last_cat = $row;
				continue;
			}
			$filtered = self::filter_menu_row_for_role( $row, $allowed_set );
			if ( null === $filtered ) {
				continue;
			}
			if ( $last_cat ) {
				$out[]    = $last_cat;
				$last_cat = null;
			}
			$out[] = $filtered;
		}

		return $out;
	}

	/**
	 * Filter a menu row (including nested menu groups) by allowed ids.
	 *
	 * @param array<string,mixed>   $row         Menu row.
	 * @param array<string,int>     $allowed_set Allowed menu ids (flipped).
	 * @return array<string,mixed>|null
	 */
	private static function filter_menu_row_for_role( array $row, array $allowed_set ) {
		if ( isset( $row['type'] ) && 'menu' === $row['type'] && ! empty( $row['children'] ) && is_array( $row['children'] ) ) {
			$kids = array();
			foreach ( $row['children'] as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$fc = self::filter_menu_row_for_role( $child, $allowed_set );
				if ( null !== $fc ) {
					$kids[] = $fc;
				}
			}
			if ( empty( $kids ) ) {
				return null;
			}
			$row['children'] = $kids;
			return $row;
		}
		if ( ! isset( $row['id'] ) || ! isset( $allowed_set[ (string) $row['id'] ] ) ) {
			return null;
		}
		return $row;
	}
}
