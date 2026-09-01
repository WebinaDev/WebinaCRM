<?php
/**
 * Accounting Reports – Balance sheet, P&L, trial balance (Iranian standard).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Reports
 */
class WebinoCRM_Accounting_Reports {

	/**
	 * Trial balance for a date range.
	 *
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to Y-m-d.
	 * @return array
	 */
	public static function trial_balance( $fiscal_year_id, $date_from = '', $date_to = '' ) {
		return WebinoCRM_Accounting_Ledger::get_trial_balance( $fiscal_year_id, $date_from, $date_to );
	}

	/**
	 * Balance sheet (ترازنامه) – Iranian standard: Assets = Liabilities + Equity.
	 * Groups: 1,2 = Assets; 3,4 = Liabilities; 5 = Equity.
	 *
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $as_of_date Date Y-m-d.
	 * @return array { assets: [...], liabilities: [...], equity: [...] }
	 */
	public static function balance_sheet( $fiscal_year_id, $as_of_date = '' ) {
		global $wpdb;
		$chart_table   = $wpdb->prefix . 'webinocrm_accounting_chart_accounts';
		$entries_table = $wpdb->prefix . 'webinocrm_accounting_journal_entries';
		$lines_table   = $wpdb->prefix . 'webinocrm_accounting_journal_lines';
		$fiscal_year_id = (int) $fiscal_year_id;
		if ( $as_of_date === '' ) {
			$fy = WebinoCRM_Accounting_Fiscal_Year::get( $fiscal_year_id );
			$as_of_date = $fy ? $fy->end_date : current_time( 'Y-m-d' );
		}

		$accounts = WebinoCRM_Accounting_Chart_Of_Accounts::get_tree( $fiscal_year_id );
		$by_code  = array();
		foreach ( $accounts as $a ) {
			$by_code[ $a['code'] ] = $a;
		}

		$sql = "SELECT l.account_id, SUM(l.debit) - SUM(l.credit) AS balance
			FROM $lines_table l
			INNER JOIN $entries_table e ON e.id = l.journal_entry_id AND e.status = 'posted'
			WHERE e.fiscal_year_id = %d AND e.voucher_date <= %s
			GROUP BY l.account_id";
		$balances = $wpdb->get_results( $wpdb->prepare( $sql, $fiscal_year_id, $as_of_date ) );
		$balance_by_id = array();
		foreach ( (array) $balances as $b ) {
			$balance_by_id[ (int) $b->account_id ] = (float) $b->balance;
		}

		$asset_types      = array( 'asset' );
		$liability_types  = array( 'liability' );
		$equity_types    = array( 'equity' );

		$group_assets     = array( '1', '2' ); // دارایی جاری، دارایی غیرجاری
		$group_liabilities = array( '3', '4' ); // بدهی جاری، بدهی غیرجاری
		$group_equity     = array( '5' ); // حقوق صاحبان سهام

		$result = array( 'assets' => array(), 'liabilities' => array(), 'equity' => array() );

		foreach ( $accounts as $a ) {
			$code   = $a['code'];
			$level  = (int) $a['level'];
			$group  = substr( $code, 0, 1 );
			$bal    = isset( $balance_by_id[ (int) $a['id'] ] ) ? $balance_by_id[ (int) $a['id'] ] : 0;
			if ( abs( $bal ) < 0.01 && $level > 1 ) {
				continue;
			}
			$row = array(
				'id'    => (int) $a['id'],
				'code'  => $code,
				'title' => $a['title'],
				'balance' => $bal,
			);
			if ( in_array( $group, $group_assets, true ) || ( isset( $a['account_type'] ) && $a['account_type'] === 'asset' ) ) {
				$result['assets'][] = $row;
			} elseif ( in_array( $group, $group_liabilities, true ) || ( isset( $a['account_type'] ) && $a['account_type'] === 'liability' ) ) {
				$result['liabilities'][] = $row;
			} elseif ( in_array( $group, $group_equity, true ) || ( isset( $a['account_type'] ) && $a['account_type'] === 'equity' ) ) {
				$result['equity'][] = $row;
			}
		}

		return $result;
	}

	/**
	 * Profit and loss (سود و زیان) – Iranian standard: Income (6) - Expense (7).
	 *
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to Y-m-d.
	 * @return array { income: [...], expense: [...], net: float }
	 */
	public static function profit_and_loss( $fiscal_year_id, $date_from = '', $date_to = '' ) {
		global $wpdb;
		$chart_table   = $wpdb->prefix . 'webinocrm_accounting_chart_accounts';
		$entries_table = $wpdb->prefix . 'webinocrm_accounting_journal_entries';
		$lines_table   = $wpdb->prefix . 'webinocrm_accounting_journal_lines';
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

		$sql = "SELECT c.id, c.code, c.title, c.account_type,
			SUM(l.debit) AS debit_total, SUM(l.credit) AS credit_total
			FROM $lines_table l
			INNER JOIN $entries_table e ON e.id = l.journal_entry_id AND e.status = 'posted'
			INNER JOIN $chart_table c ON c.id = l.account_id AND c.fiscal_year_id = e.fiscal_year_id
			WHERE e.fiscal_year_id = %d AND (c.account_type = 'income' OR c.account_type = 'expense') $where_date
			GROUP BY c.id, c.code, c.title, c.account_type
			HAVING debit_total <> 0 OR credit_total <> 0
			ORDER BY c.account_type, c.code";

		$rows = $wpdb->get_results( $prepare ? $wpdb->prepare( $sql, $prepare ) : $sql );
		$income_total  = 0;
		$expense_total = 0;
		$income_items  = array();
		$expense_items = array();

		foreach ( (array) $rows as $r ) {
			$debit  = (float) $r->debit_total;
			$credit = (float) $r->credit_total;
			$row = array(
				'id' => (int) $r->id,
				'code' => $r->code,
				'title' => $r->title,
				'debit_total' => $debit,
				'credit_total' => $credit,
			);
			if ( $r->account_type === 'income' ) {
				$amount = $credit - $debit;
				$row['amount'] = $amount;
				$income_total += $amount;
				$income_items[] = $row;
			} else {
				$amount = $debit - $credit;
				$row['amount'] = $amount;
				$expense_total += $amount;
				$expense_items[] = $row;
			}
		}

		return array(
			'income'  => $income_items,
			'expense' => $expense_items,
			'income_total'  => $income_total,
			'expense_total' => $expense_total,
			'net'     => $income_total - $expense_total,
		);
	}

	/**
	 * Simplified VAT summary from sales invoices in range.
	 *
	 * @param string $date_from From.
	 * @param string $date_to   To.
	 * @return array<string,mixed>
	 */
	public static function vat_summary( $date_from = '', $date_to = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_accounting_invoices';
		$where = "WHERE invoice_type IN ('sales','purchase')";
		$args  = array();
		if ( $date_from ) {
			$where .= ' AND invoice_date >= %s';
			$args[] = $date_from;
		}
		if ( $date_to ) {
			$where .= ' AND invoice_date <= %s';
			$args[] = $date_to;
		}
		$sql = "SELECT invoice_type, COALESCE(SUM(tax_amount),0) AS tax_sum, COALESCE(SUM(grand_total),0) AS total_sum FROM {$table} {$where} GROUP BY invoice_type"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = array( 'sales_vat' => 0.0, 'purchase_vat' => 0.0, 'net_vat' => 0.0, 'rows' => $rows );
		foreach ( (array) $rows as $r ) {
			if ( 'sales' === ( $r['invoice_type'] ?? '' ) ) {
				$out['sales_vat'] = (float) $r['tax_sum'];
			}
			if ( 'purchase' === ( $r['invoice_type'] ?? '' ) ) {
				$out['purchase_vat'] = (float) $r['tax_sum'];
			}
		}
		$out['net_vat'] = $out['sales_vat'] - $out['purchase_vat'];
		return $out;
	}

	/**
	 * Simple AR aging buckets from open sales invoices.
	 *
	 * @return array<string,mixed>
	 */
	public static function aging() {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_accounting_invoices';
		$today = current_time( 'Y-m-d' );
		$rows  = $wpdb->get_results(
			"SELECT id, person_id, invoice_no, invoice_date, grand_total, COALESCE(paid_amount,0) AS paid_amount
			FROM {$table} WHERE invoice_type = 'sales' AND status IN ('confirmed','posted','unpaid','partial')", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$buckets = array( 'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'older' => 0.0 );
		foreach ( (array) $rows as $r ) {
			$due = max( 0, (float) $r['grand_total'] - (float) $r['paid_amount'] );
			if ( $due <= 0 ) {
				continue;
			}
			$days = (int) ( ( strtotime( $today ) - strtotime( (string) $r['invoice_date'] ) ) / DAY_IN_SECONDS );
			if ( $days <= 30 ) {
				$buckets['current'] += $due;
			} elseif ( $days <= 60 ) {
				$buckets['d30'] += $due;
			} elseif ( $days <= 90 ) {
				$buckets['d60'] += $due;
			} elseif ( $days <= 120 ) {
				$buckets['d90'] += $due;
			} else {
				$buckets['older'] += $due;
			}
		}
		return $buckets;
	}

	/**
	 * Margin proxy: sales totals vs purchase totals.
	 *
	 * @param string $date_from From.
	 * @param string $date_to To.
	 * @return array<string,mixed>
	 */
	public static function margin( $date_from = '', $date_to = '' ) {
		$vat = self::vat_summary( $date_from, $date_to );
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_accounting_invoices';
		$sales = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total),0) FROM {$table} WHERE invoice_type='sales'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$purch = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total),0) FROM {$table} WHERE invoice_type='purchase'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$margin = $sales - $purch;
		return array(
			'sales'   => $sales,
			'cogs'    => $purch,
			'margin'  => $margin,
			'pct'     => $sales > 0 ? round( 100 * $margin / $sales, 2 ) : 0,
			'net_vat' => $vat['net_vat'],
		);
	}

	/**
	 * Cash-flow proxy from receipt vouchers.
	 *
	 * @param string $date_from From.
	 * @param string $date_to To.
	 * @return array<string,mixed>
	 */
	public static function cash_flow( $date_from = '', $date_to = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_accounting_receipt_vouchers';
		$in    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE voucher_type IN ('receipt','in')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE voucher_type IN ('payment','out')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'in' => $in, 'out' => $out, 'net' => $in - $out );
	}

	/**
	 * Account turnover (گردش حساب) for one account.
	 *
	 * @param int    $account_id Account id.
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to Y-m-d.
	 * @return array
	 */
	public static function account_turnover( $account_id, $fiscal_year_id, $date_from = '', $date_to = '' ) {
		return WebinoCRM_Accounting_Ledger::get_account_turnover( $account_id, $fiscal_year_id, $date_from, $date_to );
	}
}
