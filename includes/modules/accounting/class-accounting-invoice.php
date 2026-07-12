<?php
/**
 * Accounting Invoice model (فاکتور خرید و فروش).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Invoice
 */
class WebinoCRM_Accounting_Invoice {

	const TABLE = 'webinocrm_accounting_invoices';

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
	 * Get invoice by id.
	 *
	 * @param int $id Invoice id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get next invoice number for a fiscal year and type.
	 *
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param string $invoice_type   proforma|sales|purchase.
	 * @return string
	 */
	public static function get_next_number( $fiscal_year_id, $invoice_type = 'sales' ) {
		global $wpdb;
		$table  = self::get_table();
		$prefix = in_array( $invoice_type, array( 'proforma', 'sales', 'purchase' ), true ) ? $invoice_type : 'sales';
		$pattern = $prefix . '-%';
		$rows    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT invoice_no FROM $table WHERE fiscal_year_id = %d AND invoice_no LIKE %s ORDER BY id DESC LIMIT 1",
				$fiscal_year_id,
				$pattern
			)
		);
		$next = 1;
		if ( ! empty( $rows ) ) {
			$parts = explode( '-', $rows[0] );
			if ( count( $parts ) >= 2 && is_numeric( $parts[1] ) ) {
				$next = (int) $parts[1] + 1;
			}
		}
		return $prefix . '-' . $next;
	}

	/**
	 * List invoices with filters.
	 *
	 * @param array $args fiscal_year_id, person_id, invoice_type, status, date_from, date_to, per_page, page.
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
		if ( ! empty( $args['person_id'] ) ) {
			$where[]  = 'person_id = %d';
			$values[] = (int) $args['person_id'];
		}
		if ( ! empty( $args['invoice_type'] ) && in_array( $args['invoice_type'], array( 'proforma', 'sales', 'purchase' ), true ) ) {
			$where[]  = 'invoice_type = %s';
			$values[] = $args['invoice_type'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'draft', 'confirmed', 'returned' ), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'invoice_date >= %s';
			$values[] = sanitize_text_field( $args['date_from'] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'invoice_date <= %s';
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
	 * Create invoice.
	 *
	 * @param array $data Invoice fields.
	 * @param int   $user_id Created by user id.
	 * @return int|false
	 */
	public static function create( $data, $user_id = 0 ) {
		global $wpdb;
		$table = self::get_table();
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		$fiscal_year_id = isset( $data['fiscal_year_id'] ) ? (int) $data['fiscal_year_id'] : 0;
		$invoice_type   = isset( $data['invoice_type'] ) && in_array( $data['invoice_type'], array( 'proforma', 'sales', 'purchase' ), true ) ? $data['invoice_type'] : 'sales';
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$fiscal_year_id = $active ? (int) $active->id : 0;
		}
		if ( $fiscal_year_id <= 0 ) {
			return false;
		}

		$invoice_no = isset( $data['invoice_no'] ) && $data['invoice_no'] !== '' ? sanitize_text_field( $data['invoice_no'] ) : self::get_next_number( $fiscal_year_id, $invoice_type );
		$person_id  = isset( $data['person_id'] ) ? (int) $data['person_id'] : 0;
		$inv_date   = isset( $data['invoice_date'] ) ? sanitize_text_field( $data['invoice_date'] ) : gmdate( 'Y-m-d' );
		if ( $person_id <= 0 ) {
			return false;
		}

		$insert = array(
			'fiscal_year_id'   => $fiscal_year_id,
			'invoice_no'       => $invoice_no,
			'invoice_type'     => $invoice_type,
			'person_id'        => $person_id,
			'invoice_date'     => $inv_date,
			'due_date'         => isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : null,
			'status'           => isset( $data['status'] ) && in_array( $data['status'], array( 'draft', 'confirmed', 'returned' ), true ) ? $data['status'] : 'draft',
			'seller_id'        => isset( $data['seller_id'] ) && (int) $data['seller_id'] > 0 ? (int) $data['seller_id'] : null,
			'project_id'       => isset( $data['project_id'] ) && (int) $data['project_id'] > 0 ? (int) $data['project_id'] : null,
			'shipping_cost'    => isset( $data['shipping_cost'] ) ? floatval( $data['shipping_cost'] ) : null,
			'extra_additions'  => isset( $data['extra_additions'] ) ? floatval( $data['extra_additions'] ) : null,
			'extra_deductions' => isset( $data['extra_deductions'] ) ? floatval( $data['extra_deductions'] ) : null,
			'reference_type'   => isset( $data['reference_type'] ) ? sanitize_text_field( $data['reference_type'] ) : null,
			'reference_id'     => isset( $data['reference_id'] ) && (int) $data['reference_id'] > 0 ? (int) $data['reference_id'] : null,
			'created_by'       => $user_id,
		);

		$formats = array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%f', '%s', '%d', '%d' );
		$r = $wpdb->insert( $table, $insert, $formats );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update invoice.
	 *
	 * @param int   $id   Invoice id.
	 * @param array $data Fields to update.
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
		if ( ! $row || $row->status !== 'draft' ) {
			return false; // Only draft can be updated.
		}

		$allowed = array( 'invoice_no', 'invoice_type', 'person_id', 'invoice_date', 'due_date', 'seller_id', 'project_id', 'shipping_cost', 'extra_additions', 'extra_deductions', 'reference_type', 'reference_id' );
		$updates = array();
		$formats = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, array( 'person_id', 'seller_id', 'project_id', 'reference_id' ), true ) ) {
				$v = (int) $data[ $key ];
				$updates[ $key ] = $v > 0 ? $v : null;
				$formats[]      = '%d';
			} elseif ( in_array( $key, array( 'shipping_cost', 'extra_additions', 'extra_deductions' ), true ) ) {
				$updates[ $key ] = $data[ $key ] === null || $data[ $key ] === '' ? null : floatval( $data[ $key ] );
				$formats[]       = '%f';
			} elseif ( in_array( $key, array( 'invoice_date', 'due_date' ), true ) ) {
				$updates[ $key ] = $data[ $key ] ? sanitize_text_field( $data[ $key ] ) : null;
				$formats[]       = '%s';
			} else {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[]       = '%s';
			}
		}

		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete invoice (only draft).
	 *
	 * @param int $id Invoice id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row || $row->status !== 'draft' ) {
			return false;
		}
		$lines_table = $wpdb->prefix . 'webinocrm_accounting_invoice_lines';
		$wpdb->delete( $lines_table, array( 'invoice_id' => $id ), array( '%d' ) );
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Confirm (post) a draft invoice. Only draft can be confirmed.
	 * Phase 2: Later can create automatic journal entry here.
	 *
	 * @param int $id Invoice id.
	 * @return bool
	 */
	public static function confirm( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row || $row->status !== 'draft' ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->update(
			$table,
			array( 'status' => 'confirmed' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
