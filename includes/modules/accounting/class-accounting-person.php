<?php
/**
 * Accounting Person / counterparty model (طرف‌های حساب).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Person
 */
class WebinoCRM_Accounting_Person {

	const TABLE = 'webinocrm_accounting_persons';

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
	 * Get person by id.
	 *
	 * @param int $id Person id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get list with optional filters.
	 *
	 * @param array $args category_id, person_type (customer|supplier|both), search, is_active, per_page, page.
	 * @return array { items: array, total: int }
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;
		$table = self::get_table();

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['category_id'] ) ) {
			$where[] = 'category_id = %d';
			$values[] = (int) $args['category_id'];
		}
		if ( ! empty( $args['person_type'] ) && in_array( $args['person_type'], array( 'customer', 'supplier', 'both' ), true ) ) {
			$where[] = 'person_type = %s';
			$values[] = $args['person_type'];
		}
		if ( isset( $args['is_active'] ) && $args['is_active'] !== '' ) {
			$where[] = 'is_active = %d';
			$values[] = (int) $args['is_active'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = '( name LIKE %s OR code LIKE %s OR mobile LIKE %s OR phone LIKE %s OR national_id LIKE %s OR economic_code LIKE %s )';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
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
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
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
	 * Create person.
	 *
	 * @param array $data All person fields.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$name       = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( empty( $name ) ) {
			return false;
		}

		$cat_id  = isset( $data['category_id'] ) ? (int) $data['category_id'] : null;
		$grp_id  = isset( $data['group_id'] ) ? (int) $data['group_id'] : null;
		$pl_id   = isset( $data['default_price_list_id'] ) ? (int) $data['default_price_list_id'] : null;
		$insert = array(
			'name'                   => $name,
			'code'                   => isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : null,
			'category_id'            => $cat_id > 0 ? $cat_id : null,
			'credit_limit'           => isset( $data['credit_limit'] ) ? floatval( $data['credit_limit'] ) : null,
			'national_id'            => isset( $data['national_id'] ) ? sanitize_text_field( $data['national_id'] ) : null,
			'economic_code'          => isset( $data['economic_code'] ) ? sanitize_text_field( $data['economic_code'] ) : null,
			'registration_no'        => isset( $data['registration_no'] ) ? sanitize_text_field( $data['registration_no'] ) : null,
			'phone'                  => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
			'mobile'                 => isset( $data['mobile'] ) ? sanitize_text_field( $data['mobile'] ) : null,
			'extra_phones'           => isset( $data['extra_phones'] ) ? sanitize_textarea_field( $data['extra_phones'] ) : null,
			'address'                => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null,
			'person_type'            => isset( $data['person_type'] ) && in_array( $data['person_type'], array( 'customer', 'supplier', 'both' ), true ) ? $data['person_type'] : 'both',
			'group_id'               => $grp_id > 0 ? $grp_id : null,
			'image_url'              => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : null,
			'note'                   => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : null,
			'default_price_list_id'  => $pl_id > 0 ? $pl_id : null,
			'is_active'              => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'created_by'             => get_current_user_id() ? get_current_user_id() : null,
		);

		$formats = array( '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d' );

		$r = $wpdb->insert( $table, $insert, $formats );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update person.
	 *
	 * @param int   $id   Person id.
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

		$allowed = array( 'name', 'code', 'category_id', 'credit_limit', 'national_id', 'economic_code', 'registration_no', 'phone', 'mobile', 'extra_phones', 'address', 'person_type', 'group_id', 'image_url', 'note', 'default_price_list_id', 'is_active' );
		$updates = array();
		$formats = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, array( 'category_id', 'group_id', 'default_price_list_id' ), true ) ) {
				$val = (int) $data[ $key ];
				$updates[ $key ] = $val > 0 ? $val : null;
				$formats[]       = '%d';
			} elseif ( $key === 'is_active' ) {
				$updates[ $key ] = (int) $data[ $key ];
				$formats[]       = '%d';
			} elseif ( in_array( $key, array( 'credit_limit' ), true ) ) {
				$updates[ $key ] = $data[ $key ] === null || $data[ $key ] === '' ? null : floatval( $data[ $key ] );
				$formats[] = '%f';
			} elseif ( in_array( $key, array( 'address', 'note', 'extra_phones' ), true ) ) {
				$updates[ $key ] = sanitize_textarea_field( $data[ $key ] );
				$formats[] = '%s';
			} elseif ( $key === 'image_url' ) {
				$updates[ $key ] = esc_url_raw( $data[ $key ] );
				$formats[] = '%s';
			} elseif ( $key === 'person_type' ) {
				$updates[ $key ] = in_array( $data[ $key ], array( 'customer', 'supplier', 'both' ), true ) ? $data[ $key ] : 'both';
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
	 * Delete person.
	 *
	 * @param int $id Person id.
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
