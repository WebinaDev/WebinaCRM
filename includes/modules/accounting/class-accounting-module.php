<?php
/**
 * Accounting Module bootstrap – loads classes and registers AJAX.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Module
 */
class WebinoCRM_Accounting_Module {

	/**
	 * Whether the module is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'WebinoCRM_Sidebar_Menu_Builder' ) ) {
			return true;
		}
		return WebinoCRM_Sidebar_Menu_Builder::is_module_enabled( 'accounting' );
	}

	/**
	 * Load accounting module (classes + AJAX handler).
	 */
	public static function load() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$dir = WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/';
		require_once $dir . 'class-fiscal-year.php';
		require_once $dir . 'class-chart-of-accounts.php';
		require_once $dir . 'class-journal-entry.php';
		require_once $dir . 'class-ledger.php';
		require_once $dir . 'class-accounting-reports.php';
		require_once $dir . 'class-chart-seeder.php';
		require_once $dir . 'class-accounting-person-category.php';
		require_once $dir . 'class-accounting-person.php';
		require_once $dir . 'class-accounting-product-category.php';
		require_once $dir . 'class-accounting-unit.php';
		require_once $dir . 'class-accounting-price-list.php';
		require_once $dir . 'class-accounting-product.php';
		require_once $dir . 'class-accounting-user-defaults.php';
		require_once $dir . 'class-accounting-invoice.php';
		require_once $dir . 'class-accounting-invoice-line.php';
		require_once $dir . 'class-accounting-cash-account.php';
		require_once $dir . 'class-accounting-receipt-voucher.php';
		require_once $dir . 'class-accounting-check.php';

		// Phase 5: Warehouse (انبار و موجودی)
		require_once $dir . 'class-accounting-warehouse.php';
		require_once $dir . 'class-accounting-warehouse-transaction.php';
		require_once $dir . 'class-accounting-warehouse-inbound.php';
		require_once $dir . 'class-accounting-warehouse-outbound.php';
		require_once $dir . 'class-accounting-warehouse-audit.php';
		require_once $dir . 'class-accounting-warehouse-stock.php';
		require_once $dir . 'class-accounting-warehouse-module.php';
		require_once $dir . 'class-warehouse-invoice-integration.php';
		require_once $dir . 'warehouse-functions.php'; // Helper functions

		require_once $dir . 'class-accounting-ext-schema.php';
		require_once $dir . 'class-accounting-ext-compat.php';
		WebinoCRM_Accounting_Ext_Schema::ensure();
		require_once $dir . 'moadian/class-accounting-moadian-client.php';
		require_once $dir . 'moadian/class-accounting-moadian.php';
		require_once $dir . 'hesabfa/class-accounting-hesabfa-client.php';
		require_once $dir . 'hesabfa/class-accounting-hesabfa-sync.php';
		require_once $dir . 'hesabfa/class-accounting-hesabfa-migrate.php';
		require_once $dir . 'hesabfa/class-accounting-hesabfa-webhook.php';
		require_once $dir . 'hesabfa/class-accounting-hesabfa.php';
		if ( class_exists( 'WebinoCRM_Accounting_Hesabfa', false ) ) {
			WebinoCRM_Accounting_Hesabfa::init();
		}

		require_once $dir . 'ajax/class-accounting-ajax-handler.php';

		new WebinoCRM_Accounting_Ajax_Handler();
	}
}
