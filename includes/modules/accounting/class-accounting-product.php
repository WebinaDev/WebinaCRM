<?php
/**
 * Accounting Product / Goods and services model (کالا و خدمات).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Product
 */
class WebinoCRM_Accounting_Product {

	const TABLE = 'webinocrm_accounting_products';

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
	 * Get product by id.
	 *
	 * @param int $id Product id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get product by code.
	 *
	 * @param string $code Product code.
	 * @param int    $exclude_id Optional id to exclude (for update uniqueness).
	 * @return object|null
	 */
	public static function get_by_code( $code, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::get_table();
		if ( $exclude_id > 0 ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE code = %s AND id != %d",
					$code,
					$exclude_id
				)
			);
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE code = %s", $code ) );
	}

	/**
	 * Get list with optional filters.
	 *
	 * @param array $args category_id, search, is_active, per_page, page, orderby, order.
	 * @return array { items: array, total: int }
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;
		$table = self::get_table();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['category_id'] ) ) {
			$where[]   = 'category_id = %d';
			$values[]  = (int) $args['category_id'];
		}
		if ( isset( $args['is_active'] ) && $args['is_active'] !== '' ) {
			$where[]  = 'is_active = %d';
			$values[] = (int) $args['is_active'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '( name LIKE %s OR code LIKE %s OR barcode LIKE %s )';
			$s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
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
	 * Create product.
	 *
	 * @param array $data All product fields.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$code = isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : '';
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( empty( $code ) || empty( $name ) ) {
			return false;
		}
		if ( self::get_by_code( $code ) ) {
			return false; // duplicate code
		}

		$cat_id = isset( $data['category_id'] ) ? (int) $data['category_id'] : null;
		$sub_id = isset( $data['sub_unit_id'] ) ? (int) $data['sub_unit_id'] : null;
		$insert = array(
			'code'                 => $code,
			'name'                 => $name,
			'category_id'          => $cat_id > 0 ? $cat_id : null,
			'main_unit_id'         => isset( $data['main_unit_id'] ) ? (int) $data['main_unit_id'] : 0,
			'sub_unit_id'          => $sub_id > 0 ? $sub_id : null,
			'sub_unit_ratio'       => isset( $data['sub_unit_ratio'] ) ? floatval( $data['sub_unit_ratio'] ) : 1,
			'purchase_price'       => isset( $data['purchase_price'] ) ? floatval( $data['purchase_price'] ) : null,
			'barcode'              => isset( $data['barcode'] ) ? sanitize_textarea_field( $data['barcode'] ) : null,
			'inventory_controlled' => isset( $data['inventory_controlled'] ) ? (int) $data['inventory_controlled'] : 0,
			'reorder_point'        => isset( $data['reorder_point'] ) ? floatval( $data['reorder_point'] ) : null,
			'tax_rate_sales'       => isset( $data['tax_rate_sales'] ) ? floatval( $data['tax_rate_sales'] ) : null,
			'tax_rate_purchase'    => isset( $data['tax_rate_purchase'] ) ? floatval( $data['tax_rate_purchase'] ) : null,
			'image_url'            => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : null,
			'note'                 => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : null,
			'is_active'            => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
		);

		if ( $insert['main_unit_id'] <= 0 ) {
			$first_unit = $wpdb->get_row( "SELECT id FROM {$wpdb->prefix}webinocrm_accounting_units WHERE is_main = 1 LIMIT 1" );
			$insert['main_unit_id'] = $first_unit ? (int) $first_unit->id : 0;
		}

		$formats = array( '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%s', '%d', '%f', '%f', '%f', '%s', '%s', '%d' );
		$r = $wpdb->insert( $table, $insert, $formats );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update product.
	 *
	 * @param int   $id   Product id.
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

		if ( ! empty( $data['code'] ) ) {
			$existing = self::get_by_code( $data['code'], $id );
			if ( $existing ) {
				return false; // duplicate code
			}
		}

		$allowed = array( 'code', 'name', 'category_id', 'main_unit_id', 'sub_unit_id', 'sub_unit_ratio', 'purchase_price', 'barcode', 'inventory_controlled', 'reorder_point', 'tax_rate_sales', 'tax_rate_purchase', 'image_url', 'note', 'is_active' );
		$updates = array();
		$formats = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, array( 'category_id', 'sub_unit_id' ), true ) ) {
				$val = (int) $data[ $key ];
				$updates[ $key ] = $val > 0 ? $val : null;
				$formats[]       = '%d';
			} elseif ( in_array( $key, array( 'main_unit_id', 'inventory_controlled', 'is_active' ), true ) ) {
				$updates[ $key ] = (int) $data[ $key ];
				$formats[]      = '%d';
			} elseif ( in_array( $key, array( 'sub_unit_ratio', 'purchase_price', 'reorder_point', 'tax_rate_sales', 'tax_rate_purchase' ), true ) ) {
				$updates[ $key ] = $data[ $key ] === null || $data[ $key ] === '' ? null : floatval( $data[ $key ] );
				$formats[]       = '%f';
			} elseif ( $key === 'image_url' ) {
				$updates[ $key ] = esc_url_raw( $data[ $key ] );
				$formats[]      = '%s';
			} elseif ( in_array( $key, array( 'note', 'barcode' ), true ) ) {
				$updates[ $key ] = sanitize_textarea_field( $data[ $key ] );
				$formats[]      = '%s';
			} else {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[]      = '%s';
			}
		}

		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete product.
	 *
	 * @param int $id Product id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$items_table = $wpdb->prefix . 'webinocrm_accounting_price_list_items';
		$wpdb->delete( $items_table, array( 'product_id' => $id ), array( '%d' ) );
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
