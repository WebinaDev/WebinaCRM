<?php
/**
 * Posts payroll runs to accounting journal entries.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Payroll_Finance_Integration {

	/**
	 * Create and post journal entry for an approved payroll run.
	 *
	 * @param int $run_id Payroll run id.
	 * @return int|WP_Error Journal entry id.
	 */
	public static function create_journal_for_run( $run_id ) {
		global $wpdb;

		$run_id = (int) $run_id;
		$runs   = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$run    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $runs WHERE id = %d", $run_id ) );
		if ( ! $run ) {
			return new WP_Error( 'not_found', __( 'دوره حقوق یافت نشد.', 'webinocrm' ) );
		}
		if ( (int) ( $run->journal_entry_id ?? 0 ) > 0 ) {
			return (int) $run->journal_entry_id;
		}

		$settings = self::get_settings();
		$expense  = (int) ( $settings['salary_expense_account_id'] ?? 0 );
		$payable  = (int) ( $settings['salary_payable_account_id'] ?? 0 );
		if ( $expense <= 0 || $payable <= 0 ) {
			return new WP_Error( 'settings', __( 'حساب‌های GL حقوق در تنظیمات تعریف نشده‌اند.', 'webinocrm' ) );
		}

		$gross      = (float) ( $run->total_gross ?? 0 );
		$deductions = (float) ( $run->total_deductions ?? 0 );
		$net        = (float) ( $run->total_net ?? 0 );
		if ( $net <= 0 && $gross <= 0 ) {
			return new WP_Error( 'empty', __( 'مبلغ حقوق برای ثبت سند کافی نیست.', 'webinocrm' ) );
		}

		if ( ! class_exists( 'WebinoCRM_Accounting_Journal_Entry' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-journal-entry.php';
		}

		$insurance = (int) ( $settings['insurance_payable_account_id'] ?? 0 );
		$tax       = (int) ( $settings['tax_payable_account_id'] ?? 0 );

		$lines = array();
		if ( $gross > 0 ) {
			$lines[] = array(
				'account_id'  => $expense,
				'debit'       => $gross,
				'credit'      => 0,
				'description' => sprintf(
					/* translators: %1$s: year, %2$s: month */
					__( 'هزینه حقوق %1$s/%2$s', 'webinocrm' ),
					(string) $run->period_year,
					(string) $run->period_month
				),
			);
		}
		if ( $net > 0 ) {
			$lines[] = array(
				'account_id'  => $payable,
				'debit'       => 0,
				'credit'      => $net,
				'description' => __( 'بدهی حقوق و دستمزد', 'webinocrm' ),
			);
		}
		if ( $deductions > 0 ) {
			$credit_account = $insurance > 0 ? $insurance : $payable;
			$lines[]        = array(
				'account_id'  => $credit_account,
				'debit'       => 0,
				'credit'      => $deductions,
				'description' => __( 'کسورات حقوق', 'webinocrm' ),
			);
		}
		if ( $tax > 0 && $deductions > 0 ) {
			// Optional split when tax account configured — remainder stays on insurance/payable above.
		}

		$entry_id = WebinoCRM_Accounting_Journal_Entry::create(
			array(
				'voucher_date'   => (string) ( $run->period_end ?? current_time( 'Y-m-d' ) ),
				'description'    => sprintf(
					/* translators: %d: payroll run id */
					__( 'سند حقوق و دستمزد — دوره #%d', 'webinocrm' ),
					$run_id
				),
				'reference_type' => 'hrm_payroll_run',
				'reference_id'   => $run_id,
				'status'         => WebinoCRM_Accounting_Journal_Entry::STATUS_POSTED,
				'lines'          => $lines,
			),
			get_current_user_id()
		);

		if ( ! $entry_id ) {
			return new WP_Error( 'journal', __( 'ثبت سند حسابداری ناموفق بود.', 'webinocrm' ) );
		}

		$wpdb->update(
			$runs,
			array(
				'journal_entry_id' => (int) $entry_id,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $run_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return (int) $entry_id;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function get_settings() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'payroll_settings' );
		$row   = $wpdb->get_row( "SELECT * FROM $table ORDER BY id ASC LIMIT 1", ARRAY_A );
		return is_array( $row ) ? $row : array();
	}
}
