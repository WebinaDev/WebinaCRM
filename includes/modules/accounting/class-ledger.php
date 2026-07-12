<?php
/**
 * Ledger – General ledger and sub-ledger (Iranian standard).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Ledger
 */
class WebinoCRM_Accounting_Ledger {

	/**
	 * Get ledger (turnover + balance) for an account in a date range.
	 *
	 * @param int    $account_id Account id.
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $date_from Start date Y-m-d.
	 * @param string $date_to End date Y-m-d.
	 * @return array { rows, debit_total, credit_total, balance_debit, balance_credit }
	 */
	public static function get_account_turnover( $account_id, $fiscal_year_id, $date_from = '', $date_to = '' ) {
		global $wpdb;
		$entries_table = $wpdb->prefix . 'webinocrm_accounting_journal_entries';
		$lines_table   = $wpdb->prefix . 'webinocrm_accounting_journal_lines';
		$account_id    = (int) $account_id;
		$fiscal_year_id = (int) $fiscal_year_id;

		$where_date = '';
		$prepare    = array( $account_id, $fiscal_year_id );
		if ( $date_from !== '' ) {
			$where_date .= ' AND e.voucher_date >= %s';
			$prepare[]  = $date_from;
		}
		if ( $date_to !== '' ) {
			$where_date .= ' AND e.voucher_date <= %s';
			$prepare[]  = $date_to;
		}

		$sql = "SELECT e.id AS entry_id, e.voucher_no, e.voucher_date, e.description AS entry_description,
			l.id AS line_id, l.debit, l.credit, l.description AS line_description
			FROM $lines_table l
			INNER JOIN $entries_table e ON e.id = l.journal_entry_id
			WHERE l.account_id = %d AND e.fiscal_year_id = %d AND e.status = 'posted' $where_date
			ORDER BY e.voucher_date ASC, e.id ASC, l.sort_order ASC, l.id ASC";

		$rows = $wpdb->get_results( $prepare ? $wpdb->prepare( $sql, $prepare ) : $sql );

		$debit_total  = 0;
		$credit_total = 0;
		foreach ( (array) $rows as $r ) {
			$debit_total  += (float) $r->debit;
			$credit_total += (float) $r->credit;
		}
		$balance = $debit_total - $credit_total;
		$balance_debit  = $balance >= 0 ? $balance : 0;
		$balance_credit = $balance < 0 ? -$balance : 0;

		return array(
			'rows'           => $rows ?: array(),
			'debit_total'    => $debit_total,
			'credit_total'   => $credit_total,
			'balance_debit'  => $balance_debit,
			'balance_credit' => $balance_credit,
		);
	}

	/**
	 * Get opening balance for an account before a date (in same fiscal year).
	 *
	 * @param int    $account_id Account id.
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $before_date Date Y-m-d (exclusive).
	 * @return float Signed balance (debit - credit).
	 */
	public static function get_opening_balance( $account_id, $fiscal_year_id, $before_date ) {
		global $wpdb;
		$entries_table = $wpdb->prefix . 'webinocrm_accounting_journal_entries';
		$lines_table   = $wpdb->prefix . 'webinocrm_accounting_journal_lines';

		$sql = "SELECT SUM(l.debit) - SUM(l.credit) AS balance
			FROM $lines_table l
			INNER JOIN $entries_table e ON e.id = l.journal_entry_id
			WHERE l.account_id = %d AND e.fiscal_year_id = %d AND e.status = 'posted' AND e.voucher_date < %s";
		$balance = $wpdb->get_var( $wpdb->prepare( $sql, (int) $account_id, (int) $fiscal_year_id, $before_date ) );
		return (float) $balance;
	}

	/**
	 * Get trial balance (all accounts with balances) for a date range.
	 *
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $date_from Start date Y-m-d.
	 * @param string $date_to End date Y-m-d.
	 * @return array List of { account_id, code, title, debit_total, credit_total, balance_debit, balance_credit }
	 */
	public static function get_trial_balance( $fiscal_year_id, $date_from = '', $date_to = '' ) {
		global $wpdb;
		$entries_table = $wpdb->prefix . 'webinocrm_accounting_journal_entries';
		$lines_table   = $wpdb->prefix . 'webinocrm_accounting_journal_lines';
		$chart_table   = $wpdb->prefix . 'webinocrm_accounting_chart_accounts';
		$fiscal_year_id = (int) $fiscal_year_id;

		$where_date = '';
		$prepare    = array( $fiscal_year_id );
		if ( $date_from !== '' ) {
			$where_date .= ' AND e.voucher_date >= %s';
			$prepare[]  = $date_from;
		}
		if ( $date_to !== '' ) {
			$where_date .= ' AND e.voucher_date <= %s';
			$prepare[]  = $date_to;
		}

		$sql = "SELECT c.id AS account_id, c.code, c.title,
			SUM(l.debit) AS debit_total, SUM(l.credit) AS credit_total
			FROM $lines_table l
			INNER JOIN $entries_table e ON e.id = l.journal_entry_id AND e.status = 'posted'
			INNER JOIN $chart_table c ON c.id = l.account_id AND c.fiscal_year_id = e.fiscal_year_id
			WHERE e.fiscal_year_id = %d $where_date
			GROUP BY c.id, c.code, c.title
			HAVING debit_total <> 0 OR credit_total <> 0
			ORDER BY c.code";

		$rows = $wpdb->get_results( $prepare ? $wpdb->prepare( $sql, $prepare ) : $sql );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$debit  = (float) $r->debit_total;
			$credit = (float) $r->credit_total;
			$balance = $debit - $credit;
			$out[] = array(
				'account_id'     => (int) $r->account_id,
				'code'           => $r->code,
				'title'          => $r->title,
				'debit_total'    => $debit,
				'credit_total'   => $credit,
				'balance_debit'  => $balance >= 0 ? $balance : 0,
				'balance_credit' => $balance < 0 ? -$balance : 0,
			);
		}
		return $out;
	}
}
