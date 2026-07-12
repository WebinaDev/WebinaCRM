<?php
/**
 * Accounting Check – چک دریافتی و پرداختی.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Check
 */
class WebinoCRM_Accounting_Check {

	const TABLE = 'webinocrm_accounting_checks';

	const TYPE_RECEIVABLE = 'receivable';
	const TYPE_PAYABLE    = 'payable';

	const STATUS_IN_SAFE   = 'in_safe';   // در صندوق
	const STATUS_COLLECTED = 'collected'; // وصول (دریافتی)
	const STATUS_RETURNED  = 'returned';  // برگشتی
	const STATUS_SPENT     = 'spent';     // خرج‌شده (پرداختی)

	/**
	 * Get table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Get by id.
	 *
	 * @param int $id Check id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * List checks with filters.
	 *
	 * @param array $args type, person_id, cash_account_id, status, date_from, date_to, due_from, due_to, per_page, page.
	 * @return array { items: array, total: int }
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;
		$table = self::get_table();
		$where  = array( '1=1' );
		$values = array();
		if ( ! empty( $args['type'] ) && in_array( $args['type'], array( self::TYPE_RECEIVABLE, self::TYPE_PAYABLE ), true ) ) {
			$where[]  = 'type = %s';
			$values[] = $args['type'];
		}
		if ( ! empty( $args['person_id'] ) ) {
			$where[]  = 'person_id = %d';
			$values[] = (int) $args['person_id'];
		}
		if ( ! empty( $args['cash_account_id'] ) ) {
			$where[]  = 'cash_account_id = %d';
			$values[] = (int) $args['cash_account_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( self::STATUS_IN_SAFE, self::STATUS_COLLECTED, self::STATUS_RETURNED, self::STATUS_SPENT ), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['due_from'] ) ) {
			$where[]  = 'due_date >= %s';
			$values[] = sanitize_text_field( $args['due_from'] );
		}
		if ( ! empty( $args['due_to'] ) ) {
			$where[]  = 'due_date <= %s';
			$values[] = sanitize_text_field( $args['due_to'] );
		}
		if ( ! empty( $args['check_no'] ) ) {
			$where[]  = 'check_no LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['check_no'] ) . '%';
		}
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values );
		}
		$total = (int) $wpdb->get_var( $count_sql );
		$order   = isset( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$orderby = isset( $args['orderby'] ) ? sanitize_sql_orderby( $args['orderby'] ) : 'due_date';
		if ( ! $orderby ) {
			$orderby = 'due_date';
		}
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		if ( ! empty( $values ) ) {
			$items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $values, array( $per_page, $offset ) ) ) );
		} else {
			$items = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) );
		}
		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * Create check.
	 *
	 * @param array $data type, check_no, check_date, amount, cash_account_id, person_id, due_date, status, receipt_voucher_id, description.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();
		$type = isset( $data['type'] ) && in_array( $data['type'], array( self::TYPE_RECEIVABLE, self::TYPE_PAYABLE ), true ) ? $data['type'] : self::TYPE_RECEIVABLE;
		$check_no = isset( $data['check_no'] ) ? sanitize_text_field( $data['check_no'] ) : '';
		if ( empty( $check_no ) ) {
			return false;
		}
		$cash_account_id = isset( $data['cash_account_id'] ) ? (int) $data['cash_account_id'] : 0;
		$person_id       = isset( $data['person_id'] ) ? (int) $data['person_id'] : 0;
		$due_date        = isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : '';
		if ( $cash_account_id <= 0 || $person_id <= 0 || empty( $due_date ) ) {
			return false;
		}
		$amount = isset( $data['amount'] ) ? floatval( $data['amount'] ) : 0;
		if ( $amount <= 0 ) {
			return false;
		}
		$status = isset( $data['status'] ) && in_array( $data['status'], array( self::STATUS_IN_SAFE, self::STATUS_COLLECTED, self::STATUS_RETURNED, self::STATUS_SPENT ), true ) ? $data['status'] : self::STATUS_IN_SAFE;
		$insert = array(
			'type'                 => $type,
			'check_no'             => $check_no,
			'check_date'           => isset( $data['check_date'] ) ? sanitize_text_field( $data['check_date'] ) : null,
			'amount'               => $amount,
			'cash_account_id'      => $cash_account_id,
			'person_id'            => $person_id,
			'due_date'             => $due_date,
			'status'               => $status,
			'receipt_voucher_id'   => isset( $data['receipt_voucher_id'] ) && (int) $data['receipt_voucher_id'] > 0 ? (int) $data['receipt_voucher_id'] : null,
			'description'          => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
			'created_by'           => get_current_user_id() ? get_current_user_id() : 0,
		);
		$formats = array( '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%d' );
		$r = $wpdb->insert( $table, $insert, $formats );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update check (only status and description when allowed).
	 *
	 * @param int   $id   Check id.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::get_table();
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}
		$allowed = array( 'check_no', 'check_date', 'amount', 'cash_account_id', 'person_id', 'due_date', 'description' );
		$updates = array();
		$formats = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, array( 'cash_account_id', 'person_id' ), true ) ) {
				$v = (int) $data[ $key ];
				if ( $v <= 0 ) {
					return false;
				}
				$updates[ $key ] = $v;
				$formats[] = '%d';
			} elseif ( $key === 'amount' ) {
				$updates[ $key ] = floatval( $data[ $key ] );
				$formats[] = '%f';
			} elseif ( in_array( $key, array( 'check_date', 'due_date' ), true ) ) {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[] = '%s';
			} elseif ( $key === 'description' ) {
				$updates[ $key ] = sanitize_textarea_field( $data[ $key ] );
				$formats[] = '%s';
			} else {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[] = '%s';
			}
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Set status: وصول (collected) for receivable, خرج‌شده (spent) for payable, برگشتی (returned).
	 *
	 * @param int    $id     Check id.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}
		$valid = array( self::STATUS_IN_SAFE, self::STATUS_COLLECTED, self::STATUS_RETURNED, self::STATUS_SPENT );
		if ( ! in_array( $status, $valid, true ) ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete check (only if not linked to posted voucher or journal).
	 *
	 * @param int $id Check id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
