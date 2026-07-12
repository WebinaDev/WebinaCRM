<?php
/**
 * Default Iranian chart of accounts (کدینگ استاندارد ایران).
 * Groups 1–7 per Iranian accounting standards.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the default chart structure: array of items with code, title, level, parent_code, account_type, sort_order.
 * parent_code '' means root (group). Level 1 = group, 2 = class (کل), 3 = ledger (معین).
 */
function webinocrm_accounting_get_default_chart_iran() {
	return array(
		// 1 = دارایی‌های جاری
		array( 'code' => '1',   'title' => 'دارایی‌های جاری',           'level' => 1, 'parent_code' => '', 'account_type' => 'asset', 'sort_order' => 1 ),
		array( 'code' => '111', 'title' => 'موجودی نقد و بانک',         'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'sort_order' => 10 ),
		array( 'code' => '1111', 'title' => 'صندوق',                   'level' => 3, 'parent_code' => '111', 'account_type' => 'asset', 'sort_order' => 1 ),
		array( 'code' => '1112', 'title' => 'بانک‌ها',                 'level' => 3, 'parent_code' => '111', 'account_type' => 'asset', 'sort_order' => 2 ),
		array( 'code' => '112', 'title' => 'حساب‌های receivable',       'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'sort_order' => 20 ),
		array( 'code' => '1121', 'title' => 'حساب‌های دریافتنی تجاری', 'level' => 3, 'parent_code' => '112', 'account_type' => 'asset', 'sort_order' => 1 ),
		array( 'code' => '113', 'title' => 'اسناد دریافتنی',          'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'sort_order' => 30 ),
		array( 'code' => '114', 'title' => 'پیش‌پرداخت‌ها',            'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'sort_order' => 40 ),
		array( 'code' => '115', 'title' => 'موجودی کالا',              'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'sort_order' => 50 ),
		// 2 = دارایی‌های غیرجاری
		array( 'code' => '2',   'title' => 'دارایی‌های غیرجاری',       'level' => 1, 'parent_code' => '', 'account_type' => 'asset', 'sort_order' => 2 ),
		array( 'code' => '211', 'title' => 'دارایی‌های ثابت مشهود',   'level' => 2, 'parent_code' => '2', 'account_type' => 'asset', 'sort_order' => 10 ),
		array( 'code' => '212', 'title' => 'استهلاک انباشته',          'level' => 2, 'parent_code' => '2', 'account_type' => 'asset', 'sort_order' => 20 ),
		// 3 = بدهی‌های جاری
		array( 'code' => '3',   'title' => 'بدهی‌های جاری',           'level' => 1, 'parent_code' => '', 'account_type' => 'liability', 'sort_order' => 3 ),
		array( 'code' => '311', 'title' => 'حساب‌های payable',         'level' => 2, 'parent_code' => '3', 'account_type' => 'liability', 'sort_order' => 10 ),
		array( 'code' => '3111', 'title' => 'حساب‌های پرداختنی تجاری', 'level' => 3, 'parent_code' => '311', 'account_type' => 'liability', 'sort_order' => 1 ),
		array( 'code' => '312', 'title' => 'اسناد پرداختنی',          'level' => 2, 'parent_code' => '3', 'account_type' => 'liability', 'sort_order' => 20 ),
		array( 'code' => '313', 'title' => 'پیش‌دریافت‌ها',            'level' => 2, 'parent_code' => '3', 'account_type' => 'liability', 'sort_order' => 30 ),
		// 4 = بدهی‌های غیرجاری
		array( 'code' => '4',   'title' => 'بدهی‌های غیرجاری',         'level' => 1, 'parent_code' => '', 'account_type' => 'liability', 'sort_order' => 4 ),
		array( 'code' => '411', 'title' => 'وام و تسهیلات بلندمدت',   'level' => 2, 'parent_code' => '4', 'account_type' => 'liability', 'sort_order' => 10 ),
		// 5 = حقوق صاحبان سهام
		array( 'code' => '5',   'title' => 'حقوق صاحبان سهام',         'level' => 1, 'parent_code' => '', 'account_type' => 'equity', 'sort_order' => 5 ),
		array( 'code' => '511', 'title' => 'سرمایه',                  'level' => 2, 'parent_code' => '5', 'account_type' => 'equity', 'sort_order' => 10 ),
		array( 'code' => '512', 'title' => 'سود انباشته',             'level' => 2, 'parent_code' => '5', 'account_type' => 'equity', 'sort_order' => 20 ),
		// 6 = درآمدها
		array( 'code' => '6',   'title' => 'درآمدها',                 'level' => 1, 'parent_code' => '', 'account_type' => 'income', 'sort_order' => 6 ),
		array( 'code' => '611', 'title' => 'درآمدهای عملیاتی',        'level' => 2, 'parent_code' => '6', 'account_type' => 'income', 'sort_order' => 10 ),
		array( 'code' => '6111', 'title' => 'فروش کالا و خدمات',      'level' => 3, 'parent_code' => '611', 'account_type' => 'income', 'sort_order' => 1 ),
		array( 'code' => '612', 'title' => 'درآمدهای غیرعملیاتی',     'level' => 2, 'parent_code' => '6', 'account_type' => 'income', 'sort_order' => 20 ),
		// 7 = هزینه‌ها
		array( 'code' => '7',   'title' => 'هزینه‌ها',                'level' => 1, 'parent_code' => '', 'account_type' => 'expense', 'sort_order' => 7 ),
		array( 'code' => '711', 'title' => 'بهای تمام‌شده کالا و خدمات', 'level' => 2, 'parent_code' => '7', 'account_type' => 'expense', 'sort_order' => 10 ),
		array( 'code' => '712', 'title' => 'هزینه‌های عملیاتی',       'level' => 2, 'parent_code' => '7', 'account_type' => 'expense', 'sort_order' => 20 ),
		array( 'code' => '7121', 'title' => 'هزینه‌های اداری',        'level' => 3, 'parent_code' => '712', 'account_type' => 'expense', 'sort_order' => 1 ),
		array( 'code' => '7122', 'title' => 'هزینه‌های فروش',        'level' => 3, 'parent_code' => '712', 'account_type' => 'expense', 'sort_order' => 2 ),
		array( 'code' => '713', 'title' => 'هزینه‌های غیرعملیاتی',    'level' => 2, 'parent_code' => '7', 'account_type' => 'expense', 'sort_order' => 30 ),
	);
}
