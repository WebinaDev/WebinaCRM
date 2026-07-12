<?php
/**
 * Accounting Warehouse Outbound (حواله/انبار صدور).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse_Outbound
 */
class WebinoCRM_Accounting_Warehouse_Outbound {

	const TABLE = 'webinocrm_accounting_warehouse_outbound';
	const ITEMS_TABLE = 'webinocrm_accounting_warehouse_outbound_items';

	const STATUS_DRAFT   = 'draft';
	const STATUS_SHIPPED = 'shipped';
	const STATUS_POSTED  = 'posted';

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
	 * Get items table name with prefix.
	 *
	 * @return string
	 */
	public static function get_items_table() {
		global $wpdb;
		return $wpdb->prefix . self::ITEMS_TABLE;
	}

	/**
	 * Create outbound record.
	 *
	 * @param array $data {
	 *     @type int    $warehouse_id            Warehouse ID (source)
	 *     @type string $reference_type          sales_invoice|sales_return|transfer|adjustment
	 *     @type int    $reference_id            Reference ID
	 *     @type int    $destination_warehouse_id Destination warehouse (for transfers)
	 *     @type string $outbound_date           Date (Y-m-d)
	 *     @type string $description             Description
	 *     @type int    $created_by              User ID
	 * }
	 *
	 * @return int|WP_Error Outbound ID or error.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		// Validate required fields
		if ( empty( $data['warehouse_id'] ) ) {
			return new WP_Error( 'missing_warehouse', __( 'انبار الزامی است', 'webinocrm' ) );
		}

		// Get next outbound number
		$outbound_no = self::get_next_outbound_number( $data['warehouse_id'] );

		$insert_data = [
			'warehouse_id'           => (int) $data['warehouse_id'],
			'outbound_no'            => $outbound_no,
			'outbound_date'          => ! empty( $data['outbound_date'] ) ? sanitize_text_field( $data['outbound_date'] ) : current_time( 'Y-m-d' ),
			'reference_type'         => ! empty( $data['reference_type'] ) ? sanitize_text_field( $data['reference_type'] ) : null,
			'reference_id'           => ! empty( $data['reference_id'] ) ? (int) $data['reference_id'] : null,
			'destination_warehouse_id' => ! empty( $data['destination_warehouse_id'] ) ? (int) $data['destination_warehouse_id'] : null,
			'description'            => ! empty( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'status'                 => self::STATUS_DRAFT,
			'created_by'             => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
		];

		$result = $wpdb->insert( $table, $insert_data, self::get_formats( $insert_data ) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در ایجاد حواله', 'webinocrm' ) );
	}

	/**
	 * Get outbound by ID.
	 *
	 * @param int $id Outbound ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();

		$outbound = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );

		if ( $outbound ) {
			$outbound->items = self::get_items( $id );
		}

		return $outbound;
	}

	/**
	 * Get outbound items.
	 *
	 * @param int $outbound_id Outbound ID.
	 * @return array
	 */
	public static function get_items( $outbound_id ) {
		global $wpdb;
		$items_table = self::get_items_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.*, p.name as product_name, p.code as product_code, u.symbol as unit_symbol 
				FROM $items_table oi
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON oi.product_id = p.id
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON oi.unit_id = u.id
				WHERE oi.outbound_id = %d
				ORDER BY oi.sort_order ASC",
				(int) $outbound_id
			)
		);
	}

	/**
	 * Add item to outbound.
	 *
	 * @param int   $outbound_id Outbound ID.
	 * @param array $item_data {
	 *     @type int   $product_id       Product ID
	 *     @type float $quantity_shipped Quantity to ship
	 *     @type int   $unit_id          Unit ID
	 *     @type float $unit_price       Unit price (optional)
	 *     @type string $location        Location (rack, shelf)
	 *     @type string $description     Description
	 * }
	 *
	 * @return int|WP_Error Item ID or error.
	 */
	public static function add_item( $outbound_id, $item_data ) {
		global $wpdb;
		$items_table = self::get_items_table();

		if ( empty( $item_data['product_id'] ) || $item_data['quantity_shipped'] === null ) {
			return new WP_Error( 'missing_fields', __( 'محصول و تعداد الزامی است', 'webinocrm' ) );
		}

		$outbound = self::get( $outbound_id );
		if ( ! $outbound ) {
			return new WP_Error( 'not_found', __( 'حواله یافت نشد', 'webinocrm' ) );
		}

		// Check if sufficient stock exists
		$warehouse_class = 'WebinoCRM_Accounting_Warehouse';
		if ( ! class_exists( $warehouse_class ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
		}

		$stock = call_user_func( [ $warehouse_class, 'get_stock' ], $outbound->warehouse_id, $item_data['product_id'] );

		if ( ! $stock || $stock->quantity < $item_data['quantity_shipped'] ) {
			return new WP_Error( 'insufficient_stock', __( 'موجودی کافی نیست', 'webinocrm' ) );
		}

		$item_data_insert = [
			'outbound_id'      => (int) $outbound_id,
			'product_id'       => (int) $item_data['product_id'],
			'quantity_shipped' => (float) $item_data['quantity_shipped'],
			'unit_id'          => ! empty( $item_data['unit_id'] ) ? (int) $item_data['unit_id'] : null,
			'unit_price'       => ! empty( $item_data['unit_price'] ) ? (float) $item_data['unit_price'] : null,
			'location'         => ! empty( $item_data['location'] ) ? sanitize_text_field( $item_data['location'] ) : null,
			'description'      => ! empty( $item_data['description'] ) ? wp_kses_post( $item_data['description'] ) : null,
			'sort_order'       => ! empty( $item_data['sort_order'] ) ? (int) $item_data['sort_order'] : 0,
		];

		$result = $wpdb->insert( $items_table, $item_data_insert, self::get_item_formats( $item_data_insert ) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در افزودن سطر', 'webinocrm' ) );
	}

	/**
	 * Post outbound (convert draft to shipped and create transactions).
	 *
	 * @param int $id Outbound ID.
	 *
	 * @return bool|WP_Error
	 */
	public static function post( $id ) {
		global $wpdb;
		$table = self::get_table();
		$transaction_class = 'WebinoCRM_Accounting_Warehouse_Transaction';

		if ( ! class_exists( $transaction_class ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-transaction.php';
		}

		$outbound = self::get( $id );

		if ( ! $outbound ) {
			return new WP_Error( 'not_found', __( 'حواله یافت نشد', 'webinocrm' ) );
		}

		if ( $outbound->status !== self::STATUS_DRAFT ) {
			return new WP_Error( 'invalid_status', __( 'فقط حواله‌های پیش‌نویس قابل ثبت هستند', 'webinocrm' ) );
		}

		// Create transactions for each item
		if ( ! empty( $outbound->items ) ) {
			foreach ( $outbound->items as $item ) {
				$transaction_result = call_user_func(
					[ $transaction_class, 'create' ],
					[
						'warehouse_id'   => $outbound->warehouse_id,
						'product_id'     => $item->product_id,
						'transaction_type' => $transaction_class::TYPE_OUTBOUND,
						'quantity'       => $item->quantity_shipped,
						'unit_id'        => $item->unit_id,
						'document_type'  => $outbound->reference_type ?: $transaction_class::DOCUMENT_INVOICE,
						'document_id'    => $outbound->id,
						'reference_no'   => $outbound->outbound_no,
						'created_by'     => get_current_user_id(),
						'transaction_date' => $outbound->outbound_date . ' ' . current_time( 'H:i:s' ),
					]
				);

				if ( is_wp_error( $transaction_result ) ) {
					return $transaction_result;
				}
			}
		}

		// Update status to shipped
		$result = $wpdb->update(
			$table,
			[
				'status'    => self::STATUS_SHIPPED,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return (bool) $result;
	}

	/**
	 * Get next outbound number for warehouse.
	 *
	 * @param int $warehouse_id Warehouse ID.
	 *
	 * @return string
	 */
	private static function get_next_outbound_number( $warehouse_id ) {
		global $wpdb;
		$table = self::get_table();

		$latest = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(REPLACE(outbound_no, 'OUT-', '') AS UNSIGNED)) FROM $table WHERE warehouse_id = %d",
				(int) $warehouse_id
			)
		);

		$next_number = (int) $latest + 1;
		return sprintf( 'OUT-%05d', $next_number );
	}

	/**
	 * Get format array for main table.
	 *
	 * @param array $data Data array.
	 *
	 * @return array
	 */
	private static function get_formats( $data ) {
		$formats = [];
		foreach ( array_keys( $data ) as $key ) {
			switch ( $key ) {
				case 'warehouse_id':
				case 'reference_id':
				case 'destination_warehouse_id':
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

	/**
	 * Get format array for items table.
	 *
	 * @param array $data Data array.
	 *
	 * @return array
	 */
	private static function get_item_formats( $data ) {
		$formats = [];
		foreach ( array_keys( $data ) as $key ) {
			switch ( $key ) {
				case 'outbound_id':
				case 'product_id':
				case 'unit_id':
				case 'sort_order':
					$formats[] = '%d';
					break;
				case 'quantity_shipped':
				case 'unit_price':
					$formats[] = '%f';
					break;
				default:
					$formats[] = '%s';
					break;
			}
		}
		return $formats;
	}
}
