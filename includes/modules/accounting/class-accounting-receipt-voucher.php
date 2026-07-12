<?php
/**
 * Accounting Receipt / Payment / Transfer voucher (رسید دریافت، رسید پرداخت، انتقال).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Receipt_Voucher
 */
class WebinoCRM_Accounting_Receipt_Voucher {

	const TABLE = 'webinocrm_accounting_receipt_vouchers';

	const TYPE_RECEIPT  = 'receipt';
	const TYPE_PAYMENT  = 'payment';
	const TYPE_TRANSFER = 'transfer';

	const STATUS_DRAFT  = 'draft';
	const STATUS_POSTED = 'posted';

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
	 * @param int $id Voucher id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get next voucher number for fiscal year.
	 *
	 * @param int $fiscal_year_id Fiscal year id.
	 * @return string
	 */
	public static function get_next_number( $fiscal_year_id ) {
		global $wpdb;
		$table = self::get_table();
		$max = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(CAST(voucher_no AS UNSIGNED)) FROM $table WHERE fiscal_year_id = %d",
			(int) $fiscal_year_id
		) );
		return (string) ( (int) $max + 1 );
	}

	/**
	 * List vouchers with filters.
	 *
	 * @param array $args fiscal_year_id, type, cash_account_id, person_id, status, date_from, date_to, per_page, page.
	 * @return array { items: array, total: int }
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;
		$table = self::get_table();
		$where  = array( '1=1' );
		$values = array();
		if ( ! empty( $args['fiscal_year_id'] ) ) {
			$where[]  = 'fiscal_year_id = %d';
			$values[] = (int) $args['fiscal_year_id'];
		}
		if ( ! empty( $args['type'] ) && in_array( $args['type'], array( self::TYPE_RECEIPT, self::TYPE_PAYMENT, self::TYPE_TRANSFER ), true ) ) {
			$where[]  = 'type = %s';
			$values[] = $args['type'];
		}
		if ( ! empty( $args['cash_account_id'] ) ) {
			$where[]  = '( cash_account_id = %d OR transfer_to_cash_account_id = %d )';
			$values[] = (int) $args['cash_account_id'];
			$values[] = (int) $args['cash_account_id'];
		}
		if ( ! empty( $args['person_id'] ) ) {
			$where[]  = 'person_id = %d';
			$values[] = (int) $args['person_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( self::STATUS_DRAFT, self::STATUS_POSTED ), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'voucher_date >= %s';
			$values[] = sanitize_text_field( $args['date_from'] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'voucher_date <= %s';
			$values[] = sanitize_text_field( $args['date_to'] );
		}
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values );
		}
		$total = (int) $wpdb->get_var( $count_sql );
		$order   = isset( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$orderby = isset( $args['orderby'] ) ? sanitize_sql_orderby( $args['orderby'] ) : 'id';
		if ( ! $orderby ) {
			$orderby = 'id';
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
	 * Create receipt/payment/transfer voucher.
	 *
	 * @param array $data All fields.
	 * @param int   $created_by User id.
	 * @return int|false
	 */
	public static function create( $data, $created_by = 0 ) {
		global $wpdb;
		$table = self::get_table();
		$created_by = $created_by ? (int) $created_by : get_current_user_id();
		$fiscal_year_id = isset( $data['fiscal_year_id'] ) ? (int) $data['fiscal_year_id'] : 0;
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$fiscal_year_id = $active ? (int) $active->id : 0;
		}
		if ( $fiscal_year_id <= 0 ) {
			return false;
		}
		$type = isset( $data['type'] ) && in_array( $data['type'], array( self::TYPE_RECEIPT, self::TYPE_PAYMENT, self::TYPE_TRANSFER ), true ) ? $data['type'] : self::TYPE_RECEIPT;
		$voucher_no = isset( $data['voucher_no'] ) && $data['voucher_no'] !== '' ? sanitize_text_field( $data['voucher_no'] ) : self::get_next_number( $fiscal_year_id );
		$cash_account_id = isset( $data['cash_account_id'] ) ? (int) $data['cash_account_id'] : 0;
		if ( $cash_account_id <= 0 ) {
			return false;
		}
		$amount = isset( $data['amount'] ) ? floatval( $data['amount'] ) : 0;
		$voucher_date = isset( $data['voucher_date'] ) ? sanitize_text_field( $data['voucher_date'] ) : gmdate( 'Y-m-d' );
		$insert = array(
			'fiscal_year_id'               => $fiscal_year_id,
			'voucher_no'                  => $voucher_no,
			'voucher_date'                 => $voucher_date,
			'type'                         => $type,
			'cash_account_id'              => $cash_account_id,
			'transfer_to_cash_account_id'  => ( $type === self::TYPE_TRANSFER && ! empty( $data['transfer_to_cash_account_id'] ) ) ? (int) $data['transfer_to_cash_account_id'] : null,
			'person_id'                    => ( $type !== self::TYPE_TRANSFER && ! empty( $data['person_id'] ) ) ? (int) $data['person_id'] : null,
			'amount'                       => $amount,
			'description'                  => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
			'invoice_id'                   => isset( $data['invoice_id'] ) && (int) $data['invoice_id'] > 0 ? (int) $data['invoice_id'] : null,
			'project_id'                   => isset( $data['project_id'] ) && (int) $data['project_id'] > 0 ? (int) $data['project_id'] : null,
			'bank_fee'                     => isset( $data['bank_fee'] ) ? floatval( $data['bank_fee'] ) : null,
			'created_by'                   => $created_by,
			'status'                       => self::STATUS_DRAFT,
		);
		$formats = array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%d', '%d', '%f', '%d', '%s' );
		$r = $wpdb->insert( $table, $insert, $formats );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update voucher (draft only).
	 *
	 * @param int   $id   Voucher id.
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
		if ( ! $row || $row->status !== self::STATUS_DRAFT ) {
			return false;
		}
		$allowed = array( 'voucher_no', 'voucher_date', 'type', 'cash_account_id', 'transfer_to_cash_account_id', 'person_id', 'amount', 'description', 'invoice_id', 'project_id', 'bank_fee' );
		$updates = array();
		$formats = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, array( 'cash_account_id', 'transfer_to_cash_account_id', 'person_id', 'invoice_id', 'project_id' ), true ) ) {
				$v = (int) $data[ $key ];
				$updates[ $key ] = $v > 0 ? $v : null;
				$formats[] = '%d';
			} elseif ( in_array( $key, array( 'amount', 'bank_fee' ), true ) ) {
				$updates[ $key ] = $data[ $key ] === null || $data[ $key ] === '' ? null : floatval( $data[ $key ] );
				$formats[] = '%f';
			} elseif ( $key === 'type' ) {
				$updates[ $key ] = in_array( $data[ $key ], array( self::TYPE_RECEIPT, self::TYPE_PAYMENT, self::TYPE_TRANSFER ), true ) ? $data[ $key ] : $row->type;
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
	 * Post (finalize) voucher. Optionally create journal entry later.
	 *
	 * @param int $id Voucher id.
	 * @return bool
	 */
	public static function post( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row || $row->status !== self::STATUS_DRAFT ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->update(
			$table,
			array( 'status' => self::STATUS_POSTED ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete voucher (draft only).
	 *
	 * @param int $id Voucher id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row || $row->status !== self::STATUS_DRAFT ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
