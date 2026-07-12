<?php
/**
 * Accounting AJAX handler – CRUD for chart, fiscal year, journals, reports.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Ajax_Handler
 */
class WebinoCRM_Accounting_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

	public function __construct() {
			$prefix = 'wp_ajax_webinocrm_accounting_';
			add_action( $prefix . 'fiscal_years', array( $this, 'fiscal_years' ) );
			add_action( $prefix . 'fiscal_year_save', array( $this, 'fiscal_year_save' ) );
			add_action( $prefix . 'fiscal_year_delete', array( $this, 'fiscal_year_delete' ) );
			add_action( $prefix . 'chart_list', array( $this, 'chart_list' ) );
			add_action( $prefix . 'chart_save', array( $this, 'chart_save' ) );
			add_action( $prefix . 'chart_delete', array( $this, 'chart_delete' ) );
			add_action( $prefix . 'journal_list', array( $this, 'journal_list' ) );
			add_action( $prefix . 'journal_get', array( $this, 'journal_get' ) );
			add_action( $prefix . 'journal_save', array( $this, 'journal_save' ) );
			add_action( $prefix . 'journal_post', array( $this, 'journal_post' ) );
			add_action( $prefix . 'journal_delete', array( $this, 'journal_delete' ) );
			add_action( $prefix . 'ledger', array( $this, 'ledger' ) );
			add_action( $prefix . 'report_trial_balance', array( $this, 'report_trial_balance' ) );
			add_action( $prefix . 'report_balance_sheet', array( $this, 'report_balance_sheet' ) );
			add_action( $prefix . 'report_profit_loss', array( $this, 'report_profit_loss' ) );
			add_action( $prefix . 'settings_get', array( $this, 'settings_get' ) );
			add_action( $prefix . 'settings_save', array( $this, 'settings_save' ) );
			add_action( $prefix . 'seed_chart', array( $this, 'seed_chart' ) );
			// Phase 1: Persons
			add_action( $prefix . 'person_categories', array( $this, 'person_categories' ) );
			add_action( $prefix . 'person_category_save', array( $this, 'person_category_save' ) );
			add_action( $prefix . 'person_category_delete', array( $this, 'person_category_delete' ) );
			add_action( $prefix . 'persons_list', array( $this, 'persons_list' ) );
			add_action( $prefix . 'person_get', array( $this, 'person_get' ) );
			add_action( $prefix . 'person_save', array( $this, 'person_save' ) );
			add_action( $prefix . 'person_delete', array( $this, 'person_delete' ) );
			// Phase 1: Products, units, price lists
			add_action( $prefix . 'product_categories', array( $this, 'product_categories' ) );
			add_action( $prefix . 'product_category_save', array( $this, 'product_category_save' ) );
			add_action( $prefix . 'product_category_delete', array( $this, 'product_category_delete' ) );
			add_action( $prefix . 'units_list', array( $this, 'units_list' ) );
			add_action( $prefix . 'unit_save', array( $this, 'unit_save' ) );
			add_action( $prefix . 'unit_delete', array( $this, 'unit_delete' ) );
			add_action( $prefix . 'price_lists', array( $this, 'price_lists' ) );
			add_action( $prefix . 'price_list_get', array( $this, 'price_list_get' ) );
			add_action( $prefix . 'price_list_save', array( $this, 'price_list_save' ) );
			add_action( $prefix . 'price_list_delete', array( $this, 'price_list_delete' ) );
			add_action( $prefix . 'price_list_items', array( $this, 'price_list_items' ) );
			add_action( $prefix . 'price_list_items_save', array( $this, 'price_list_items_save' ) );
			add_action( $prefix . 'products_list', array( $this, 'products_list' ) );
			add_action( $prefix . 'product_get', array( $this, 'product_get' ) );
			add_action( $prefix . 'product_save', array( $this, 'product_save' ) );
			add_action( $prefix . 'product_delete', array( $this, 'product_delete' ) );
			add_action( $prefix . 'user_defaults_get', array( $this, 'user_defaults_get' ) );
			add_action( $prefix . 'user_defaults_save', array( $this, 'user_defaults_save' ) );
			// Phase 2: Invoices
			add_action( $prefix . 'invoice_list', array( $this, 'invoice_list' ) );
			add_action( $prefix . 'invoice_get', array( $this, 'invoice_get' ) );
			add_action( $prefix . 'invoice_save', array( $this, 'invoice_save' ) );
			add_action( $prefix . 'invoice_delete', array( $this, 'invoice_delete' ) );
			add_action( $prefix . 'invoice_next_number', array( $this, 'invoice_next_number' ) );
			add_action( $prefix . 'invoice_confirm', array( $this, 'invoice_confirm' ) );
			// Phase 3: Cash accounts (صندوق/بانک/تنخواه) and Receipt/Payment
			add_action( $prefix . 'cash_accounts_list', array( $this, 'cash_accounts_list' ) );
			add_action( $prefix . 'cash_account_get', array( $this, 'cash_account_get' ) );
			add_action( $prefix . 'cash_account_save', array( $this, 'cash_account_save' ) );
			add_action( $prefix . 'cash_account_delete', array( $this, 'cash_account_delete' ) );
			add_action( $prefix . 'receipt_voucher_list', array( $this, 'receipt_voucher_list' ) );
			add_action( $prefix . 'receipt_voucher_get', array( $this, 'receipt_voucher_get' ) );
			add_action( $prefix . 'receipt_voucher_save', array( $this, 'receipt_voucher_save' ) );
			add_action( $prefix . 'receipt_voucher_post', array( $this, 'receipt_voucher_post' ) );
			add_action( $prefix . 'receipt_voucher_delete', array( $this, 'receipt_voucher_delete' ) );
			add_action( $prefix . 'receipt_voucher_next_number', array( $this, 'receipt_voucher_next_number' ) );
			// Phase 4: Cheques (چک دریافتی و پرداختی)
			add_action( $prefix . 'check_list', array( $this, 'check_list' ) );
			add_action( $prefix . 'check_get', array( $this, 'check_get' ) );
			add_action( $prefix . 'check_save', array( $this, 'check_save' ) );
			add_action( $prefix . 'check_delete', array( $this, 'check_delete' ) );
			add_action( $prefix . 'check_set_status', array( $this, 'check_set_status' ) );
		}

	public function fiscal_years() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'fiscal_years' ) );
	}

	public function fiscal_year_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'fiscal_year_save' ) );
	}

	public function fiscal_year_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'fiscal_year_delete' ) );
	}

	public function chart_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'chart_list' ) );
	}

	public function chart_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'chart_save' ) );
	}

	public function chart_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'chart_delete' ) );
	}

	public function journal_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'journal_list' ) );
	}

	public function journal_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'journal_get' ) );
	}

	public function journal_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'journal_save' ) );
	}

	public function journal_post() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'journal_post' ) );
	}

	public function journal_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'journal_delete' ) );
	}

	public function ledger() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'ledger' ) );
	}

	public function report_trial_balance() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'report_trial_balance' ) );
	}

	public function report_balance_sheet() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'report_balance_sheet' ) );
	}

	public function report_profit_loss() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'report_profit_loss' ) );
	}

	public function settings_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'settings_get' ) );
	}

	public function settings_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'settings_save' ) );
	}

	public function seed_chart() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'seed_chart' ) );
	}

	public function person_categories() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'person_categories' ) );
	}

	public function person_category_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'person_category_save' ) );
	}

	public function person_category_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'person_category_delete' ) );
	}

	public function persons_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'persons_list' ) );
	}

	public function person_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'person_get' ) );
	}

	public function person_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'person_save' ) );
	}

	public function person_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'person_delete' ) );
	}

	public function product_categories() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'product_categories' ) );
	}

	public function product_category_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'product_category_save' ) );
	}

	public function product_category_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'product_category_delete' ) );
	}

	public function units_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'units_list' ) );
	}

	public function unit_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'unit_save' ) );
	}

	public function unit_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'unit_delete' ) );
	}

	public function price_lists() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'price_lists' ) );
	}

	public function price_list_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'price_list_get' ) );
	}

	public function price_list_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'price_list_save' ) );
	}

	public function price_list_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'price_list_delete' ) );
	}

	public function price_list_items() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'price_list_items' ) );
	}

	public function price_list_items_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'price_list_items_save' ) );
	}

	public function products_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'products_list' ) );
	}

	public function product_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'product_get' ) );
	}

	public function product_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'product_save' ) );
	}

	public function product_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'product_delete' ) );
	}

	public function user_defaults_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'user_defaults_get' ) );
	}

	public function user_defaults_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'user_defaults_save' ) );
	}

	public function invoice_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'invoice_list' ) );
	}

	public function invoice_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'invoice_get' ) );
	}

	public function invoice_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'invoice_save' ) );
	}

	public function invoice_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'invoice_delete' ) );
	}

	public function invoice_next_number() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'invoice_next_number' ) );
	}

	public function invoice_confirm() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'invoice_confirm' ) );
	}

	public function cash_accounts_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'cash_accounts_list' ) );
	}

	public function cash_account_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'cash_account_get' ) );
	}

	public function cash_account_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'cash_account_save' ) );
	}

	public function cash_account_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'cash_account_delete' ) );
	}

	public function receipt_voucher_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'receipt_voucher_list' ) );
	}

	public function receipt_voucher_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'receipt_voucher_get' ) );
	}

	public function receipt_voucher_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'receipt_voucher_save' ) );
	}

	public function receipt_voucher_post() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'receipt_voucher_post' ) );
	}

	public function receipt_voucher_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'receipt_voucher_delete' ) );
	}

	public function receipt_voucher_next_number() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'receipt_voucher_next_number' ) );
	}

	public function check_list() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'check_list' ) );
	}

	public function check_get() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'check_get' ) );
	}

	public function check_save() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'check_save' ) );
	}

	public function check_delete() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'check_delete' ) );
	}

	public function check_set_status() {
		$this->emit_service( array( 'WebinoCRM_Accounting_Service', 'check_set_status' ) );
	}

}
