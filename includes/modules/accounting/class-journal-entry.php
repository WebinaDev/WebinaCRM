<?php
/**
 * Journal Entry (voucher) – Iranian standard.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Journal_Entry
 */
class WebinoCRM_Accounting_Journal_Entry {

	const TABLE      = 'webinocrm_accounting_journal_entries';
	const LINES_TABLE = 'webinocrm_accounting_journal_lines';

	const STATUS_DRAFT  = 'draft';
	const STATUS_POSTED = 'posted';

	/**
	 * Get entries table name.
	 *
	 * @return string
	 */
	public static function get_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Get lines table name.
	 *
	 * @return string
	 */
	public static function get_lines_table() {
		global $wpdb;
		return $wpdb->prefix . self::LINES_TABLE;
	}

	/**
	 * Get entry by id.
	 *
	 * @param int $id Entry id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get lines for an entry.
	 *
	 * @param int $journal_entry_id Entry id.
	 * @return array
	 */
	public static function get_lines( $journal_entry_id ) {
		global $wpdb;
		$table = self::get_lines_table();
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE journal_entry_id = %d ORDER BY sort_order ASC, id ASC",
			(int) $journal_entry_id
		), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get next voucher number for fiscal year.
	 *
	 * @param int $fiscal_year_id Fiscal year id.
	 * @return string
	 */
	public static function next_voucher_no( $fiscal_year_id ) {
		global $wpdb;
		$table = self::get_table();
		$max   = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(CAST(voucher_no AS UNSIGNED)) FROM $table WHERE fiscal_year_id = %d",
			(int) $fiscal_year_id
		) );
		return (string) ( (int) $max + 1 );
	}

	/**
	 * Validate that total debit = total credit for lines.
	 *
	 * @param array $lines Array of { account_id, debit, credit, description }.
	 * @return bool
	 */
	public static function validate_balanced( $lines ) {
		$debit  = '0';
		$credit = '0';
		foreach ( $lines as $line ) {
			$d = isset( $line['debit'] ) ? $line['debit'] : 0;
			$c = isset( $line['credit'] ) ? $line['credit'] : 0;
			if ( function_exists( 'bcadd' ) ) {
				$debit  = bcadd( $debit, (string) $d, 8 );
				$credit = bcadd( $credit, (string) $c, 8 );
			} else {
				$debit  = (string) ( (float) $debit + (float) $d );
				$credit = (string) ( (float) $credit + (float) $c );
			}
		}
		if ( function_exists( 'bccomp' ) ) {
			return bccomp( $debit, $credit, 2 ) === 0;
		}
		return abs( (float) $debit - (float) $credit ) < 0.005;
	}

	/**
	 * Create journal entry with lines.
	 *
	 * @param array $data voucher_date, description, reference_type, reference_id, fiscal_year_id, status, lines.
	 * @param int   $created_by User id.
	 * @return int|false Entry id or false.
	 */
	public static function create( $data, $created_by = 0 ) {
		global $wpdb;

		$fiscal_year_id = isset( $data['fiscal_year_id'] ) ? (int) $data['fiscal_year_id'] : 0;
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			if ( ! $active ) {
				return false;
			}
			$fiscal_year_id = (int) $active->id;
		}

		$voucher_date  = isset( $data['voucher_date'] ) ? sanitize_text_field( $data['voucher_date'] ) : current_time( 'Y-m-d' );
		$description   = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
		$reference_type = isset( $data['reference_type'] ) ? sanitize_key( $data['reference_type'] ) : null;
		$reference_id   = isset( $data['reference_id'] ) ? (int) $data['reference_id'] : null;
		$status        = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : self::STATUS_DRAFT;
		$lines         = isset( $data['lines'] ) && is_array( $data['lines'] ) ? $data['lines'] : array();

		if ( ! in_array( $status, array( self::STATUS_DRAFT, self::STATUS_POSTED ), true ) ) {
			$status = self::STATUS_DRAFT;
		}

		if ( ! self::validate_balanced( $lines ) ) {
			return false;
		}

		$voucher_no = isset( $data['voucher_no'] ) ? sanitize_text_field( $data['voucher_no'] ) : self::next_voucher_no( $fiscal_year_id );

		$table = self::get_table();
		$r     = $wpdb->insert(
			$table,
			array(
				'fiscal_year_id'  => $fiscal_year_id,
				'voucher_no'      => $voucher_no,
				'voucher_date'    => $voucher_date,
				'description'    => $description,
				'reference_type' => $reference_type,
				'reference_id'   => $reference_id,
				'created_by'     => $created_by ? (int) $created_by : get_current_user_id(),
				'status'         => $status,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $r ) {
			return false;
		}
		$entry_id = (int) $wpdb->insert_id;

		$lines_table = self::get_lines_table();
		$sort        = 0;
		foreach ( $lines as $line ) {
			$account_id = isset( $line['account_id'] ) ? (int) $line['account_id'] : 0;
			$debit      = isset( $line['debit'] ) ? (float) $line['debit'] : 0;
			$credit     = isset( $line['credit'] ) ? (float) $line['credit'] : 0;
			$desc       = isset( $line['description'] ) ? sanitize_textarea_field( $line['description'] ) : '';
			if ( $account_id <= 0 ) {
				continue;
			}
			$wpdb->insert(
				$lines_table,
				array(
					'journal_entry_id' => $entry_id,
					'account_id'       => $account_id,
					'debit'            => $debit,
					'credit'           => $credit,
					'description'      => $desc,
					'sort_order'       => $sort++,
				),
				array( '%d', '%d', '%f', '%f', '%s', '%d' )
			);
		}

		return $entry_id;
	}

	/**
	 * Update entry (only draft; lines can be replaced).
	 *
	 * @param int   $id   Entry id.
	 * @param array $data Fields and optionally 'lines'.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::get_table();
		$id    = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row || $row->status !== self::STATUS_DRAFT ) {
			return false;
		}

		$updates = array();
		$formats = array();
		foreach ( array( 'voucher_date', 'description', 'reference_type', 'reference_id' ) as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( $key === 'reference_id' ) {
				$updates[ $key ] = (int) $data[ $key ];
				$formats[]       = '%d';
			} else {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[]       = '%s';
			}
		}
		if ( ! empty( $updates ) ) {
			$wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
		}

		if ( isset( $data['lines'] ) && is_array( $data['lines'] ) ) {
			if ( ! self::validate_balanced( $data['lines'] ) ) {
				return false;
			}
			$lines_table = self::get_lines_table();
			$wpdb->delete( $lines_table, array( 'journal_entry_id' => $id ), array( '%d' ) );
			$sort = 0;
			foreach ( $data['lines'] as $line ) {
				$account_id = isset( $line['account_id'] ) ? (int) $line['account_id'] : 0;
				$debit      = isset( $line['debit'] ) ? (float) $line['debit'] : 0;
				$credit     = isset( $line['credit'] ) ? (float) $line['credit'] : 0;
				$desc       = isset( $line['description'] ) ? sanitize_textarea_field( $line['description'] ) : '';
				if ( $account_id <= 0 ) {
					continue;
				}
				$wpdb->insert(
					$lines_table,
					array(
						'journal_entry_id' => $id,
						'account_id'       => $account_id,
						'debit'            => $debit,
						'credit'           => $credit,
						'description'      => $desc,
						'sort_order'       => $sort++,
					),
					array( '%d', '%d', '%f', '%f', '%s', '%d' )
				);
			}
		}
		return true;
	}

	/**
	 * Post a draft entry.
	 *
	 * @param int $id Entry id.
	 * @return bool
	 */
	public static function post( $id ) {
		global $wpdb;
		$table = self::get_table();
		$id    = (int) $id;
		$row   = self::get( $id );
		if ( ! $row || $row->status !== self::STATUS_DRAFT ) {
			return false;
		}
		return (bool) $wpdb->update( $table, array( 'status' => self::STATUS_POSTED ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Delete entry (only draft).
	 *
	 * @param int $id Entry id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		$row = self::get( $id );
		if ( ! $row || $row->status !== self::STATUS_DRAFT ) {
			return false;
		}
		$lines_table = self::get_lines_table();
		$wpdb->delete( $lines_table, array( 'journal_entry_id' => $id ), array( '%d' ) );
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * List entries with optional filters.
	 *
	 * @param array $args fiscal_year_id, status, date_from, date_to, per_page, page.
	 * @return array { items, total }
	 */
	public static function list_entries( $args = array() ) {
		global $wpdb;
		$table = self::get_table();

		$fiscal_year_id = isset( $args['fiscal_year_id'] ) ? (int) $args['fiscal_year_id'] : 0;
		$status        = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$date_from     = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : '';
		$date_to       = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : '';
		$per_page      = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page          = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset        = ( $page - 1 ) * $per_page;

		$where = array( '1=1' );
		$prepare = array();
		if ( $fiscal_year_id > 0 ) {
			$where[] = 'fiscal_year_id = %d';
			$prepare[] = $fiscal_year_id;
		}
		if ( $status !== '' ) {
			$where[] = 'status = %s';
			$prepare[] = $status;
		}
		if ( $date_from !== '' ) {
			$where[] = 'voucher_date >= %s';
			$prepare[] = $date_from;
		}
		if ( $date_to !== '' ) {
			$where[] = 'voucher_date <= %s';
			$prepare[] = $date_to;
		}
		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( $prepare ? $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where_sql", $prepare ) : "SELECT COUNT(*) FROM $table WHERE $where_sql" );
		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY voucher_date DESC, id DESC LIMIT %d OFFSET %d";
		$prepare[] = $per_page;
		$prepare[] = $offset;
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $prepare ) );

		return array( 'items' => $items ?: array(), 'total' => $total );
	}
}
