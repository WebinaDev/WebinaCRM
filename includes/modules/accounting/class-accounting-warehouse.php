<?php
/**
 * Accounting Warehouse (انبار).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse
 */
class WebinoCRM_Accounting_Warehouse {

	const TABLE = 'webinocrm_accounting_warehouses';

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
	 * Create or update warehouse.
	 *
	 * @param array $data {
	 *     @type string $name          اسم انبار
	 *     @type string $code          کد انبار
	 *     @type string $description   توضیحات
	 *     @type string $location      موقعیت
	 *     @type bool   $is_default    آیا پیش‌فرض است
	 *     @type bool   $is_active     فعال یا غیرفعال
	 *     @type int    $sort_order    ترتیب نمایش
	 *     @type int    $created_by    کاربر ایجادکننده
	 * }
	 *
	 * @return int|WP_Error Warehouse ID or error.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		// Validate required fields
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'نام انبار الزامی است', 'webinocrm' ) );
		}

		// If setting as default, unset other defaults
		if ( ! empty( $data['is_default'] ) && $data['is_default'] ) {
			$wpdb->update( $table, [ 'is_default' => 0 ], [] );
		}

		$insert_data = [
			'name'        => sanitize_text_field( $data['name'] ),
			'code'        => isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : null,
			'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'location'    => isset( $data['location'] ) ? wp_kses_post( $data['location'] ) : null,
			'is_default'  => isset( $data['is_default'] ) ? (int) $data['is_default'] : 0,
			'is_active'   => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'sort_order'  => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'created_by'  => isset( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
		];

		$result = $wpdb->insert( $table, $insert_data, self::get_formats( $insert_data ) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در ایجاد انبار', 'webinocrm' ) );
	}

	/**
	 * Get warehouse by ID.
	 *
	 * @param int $id Warehouse ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get all warehouses.
	 *
	 * @param array $args {
	 *     @type bool $only_active Only active warehouses
	 *     @type int  $limit       Limit results
	 *     @type int  $offset      Offset results
	 * }
	 *
	 * @return array
	 */
	public static function get_all( $args = [] ) {
		global $wpdb;
		$table = self::get_table();

		$query = "SELECT * FROM $table WHERE 1=1";

		if ( ! empty( $args['only_active'] ) ) {
			$query .= ' AND is_active = 1';
		}

		$query .= ' ORDER BY sort_order ASC, name ASC';

		if ( ! empty( $args['limit'] ) ) {
			$query .= $wpdb->prepare( ' LIMIT %d', (int) $args['limit'] );
			if ( ! empty( $args['offset'] ) ) {
				$query .= $wpdb->prepare( ' OFFSET %d', (int) $args['offset'] );
			}
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get default warehouse.
	 *
	 * @return object|null
	 */
	public static function get_default() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( "SELECT * FROM $table WHERE is_default = 1 AND is_active = 1 LIMIT 1" );
	}

	/**
	 * Update warehouse.
	 *
	 * @param int   $id   Warehouse ID.
	 * @param array $data Update data.
	 *
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::get_table();

		// If setting as default, unset other defaults
		if ( ! empty( $data['is_default'] ) && $data['is_default'] ) {
			$wpdb->update( $table, [ 'is_default' => 0 ], [], [ '%d' ], [] );
		}

		$update_data = [];

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['code'] ) ) {
			$update_data['code'] = sanitize_text_field( $data['code'] );
		}
		if ( isset( $data['description'] ) ) {
			$update_data['description'] = wp_kses_post( $data['description'] );
		}
		if ( isset( $data['location'] ) ) {
			$update_data['location'] = wp_kses_post( $data['location'] );
		}
		if ( isset( $data['is_default'] ) ) {
			$update_data['is_default'] = (int) $data['is_default'];
		}
		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = (int) $data['is_active'];
		}
		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = (int) $data['sort_order'];
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		return (bool) $wpdb->update( $table, $update_data, [ 'id' => $id ], self::get_formats( $update_data ), [ '%d' ] );
	}

	/**
	 * Delete warehouse (soft delete).
	 *
	 * @param int $id Warehouse ID.
	 *
	 * @return bool
	 */
	public static function delete( $id ) {
		return self::update( $id, [ 'is_active' => 0 ] );
	}

	/**
	 * Get warehouse's current stock for a product.
	 *
	 * @param int $warehouse_id Warehouse ID.
	 * @param int $product_id   Product ID.
	 *
	 * @return object|null {
	 *     @type int     $warehouse_id
	 *     @type int     $product_id
	 *     @type float   $quantity
	 *     @type int     $unit_id
	 * }
	 */
	public static function get_stock( $warehouse_id, $product_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_accounting_warehouse_stock';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE warehouse_id = %d AND product_id = %d",
				(int) $warehouse_id,
				(int) $product_id
			)
		);
	}

	/**
	 * Get all stock items in warehouse.
	 *
	 * @param int   $warehouse_id Warehouse ID.
	 * @param array $args         Additional arguments.
	 *
	 * @return array
	 */
	public static function get_warehouse_stock( $warehouse_id, $args = [] ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'webinocrm_accounting_warehouse_stock';
		$query  = "SELECT ws.*, p.name as product_name, p.code as product_code, u.symbol as unit_symbol ";
		$query .= "FROM $table ws ";
		$query .= "LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ws.product_id = p.id ";
		$query .= "LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON ws.unit_id = u.id ";
		$query .= "WHERE ws.warehouse_id = %d ";

		if ( ! empty( $args['only_counted'] ) ) {
			$query .= "AND ws.quantity > 0 ";
		}

		$query .= "ORDER BY p.name ASC";

		if ( ! empty( $args['limit'] ) ) {
			$query .= $wpdb->prepare( ' LIMIT %d', (int) $args['limit'] );
		}

		return $wpdb->get_results( $wpdb->prepare( $query, (int) $warehouse_id ) );
	}

	/**
	 * Get format array for all fields.
	 *
	 * @param array $data Data array.
	 *
	 * @return array
	 */
	private static function get_formats( $data ) {
		$formats = [];
		foreach ( array_keys( $data ) as $key ) {
			switch ( $key ) {
				case 'is_default':
				case 'is_active':
				case 'sort_order':
				case 'created_by':
					$formats[] = '%d';
					break;
				default:
					$formats[] = '%s';
					break;
			}
		}
		return $formats;
	}
}
