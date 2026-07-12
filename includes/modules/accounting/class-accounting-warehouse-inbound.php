<?php
/**
 * Accounting Warehouse Inbound (رسید کالا/انبار).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse_Inbound
 */
class WebinoCRM_Accounting_Warehouse_Inbound {

	const TABLE = 'webinocrm_accounting_warehouse_inbound';
	const ITEMS_TABLE = 'webinocrm_accounting_warehouse_inbound_items';

	const STATUS_DRAFT    = 'draft';
	const STATUS_RECEIVED = 'received';
	const STATUS_POSTED   = 'posted';

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
	 * Create inbound record.
	 *
	 * @param array $data {
	 *     @type int    $warehouse_id    Warehouse ID
	 *     @type string $reference_type  purchase_invoice|purchase_return|transfer|adjustment
	 *     @type int    $reference_id    Reference ID
	 *     @type string $inbound_date    Date (Y-m-d)
	 *     @type string $description     Description
	 *     @type int    $created_by      User ID
	 * }
	 *
	 * @return int|WP_Error Inbound ID or error.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		// Validate required fields
		if ( empty( $data['warehouse_id'] ) ) {
			return new WP_Error( 'missing_warehouse', __( 'انبار الزامی است', 'webinocrm' ) );
		}

		// Get next inbound number
		$inbound_no = self::get_next_inbound_number( $data['warehouse_id'] );

		$insert_data = [
			'warehouse_id'  => (int) $data['warehouse_id'],
			'inbound_no'    => $inbound_no,
			'inbound_date'  => ! empty( $data['inbound_date'] ) ? sanitize_text_field( $data['inbound_date'] ) : current_time( 'Y-m-d' ),
			'reference_type' => ! empty( $data['reference_type'] ) ? sanitize_text_field( $data['reference_type'] ) : null,
			'reference_id'  => ! empty( $data['reference_id'] ) ? (int) $data['reference_id'] : null,
			'description'   => ! empty( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'status'        => self::STATUS_DRAFT,
			'created_by'    => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
		];

		$result = $wpdb->insert( $table, $insert_data, self::get_formats( $insert_data ) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در ایجاد رسید', 'webinocrm' ) );
	}

	/**
	 * Get inbound by ID.
	 *
	 * @param int $id Inbound ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();

		$inbound = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );

		if ( $inbound ) {
			$inbound->items = self::get_items( $id );
		}

		return $inbound;
	}

	/**
	 * Get inbound items.
	 *
	 * @param int $inbound_id Inbound ID.
	 * @return array
	 */
	public static function get_items( $inbound_id ) {
		global $wpdb;
		$items_table = self::get_items_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ii.*, p.name as product_name, p.code as product_code, u.symbol as unit_symbol 
				FROM $items_table ii
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ii.product_id = p.id
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON ii.unit_id = u.id
				WHERE ii.inbound_id = %d
				ORDER BY ii.sort_order ASC",
				(int) $inbound_id
			)
		);
	}

	/**
	 * Add item to inbound.
	 *
	 * @param int   $inbound_id Inbound ID.
	 * @param array $item_data {
	 *     @type int   $product_id       Product ID
	 *     @type float $quantity_received Quantity received
	 *     @type int   $unit_id          Unit ID
	 *     @type float $unit_price       Unit price (optional)
	 *     @type string $location        Location (rack, shelf)
	 *     @type string $description     Description
	 * }
	 *
	 * @return int|WP_Error Item ID or error.
	 */
	public static function add_item( $inbound_id, $item_data ) {
		global $wpdb;
		$items_table = self::get_items_table();

		if ( empty( $item_data['product_id'] ) || $item_data['quantity_received'] === null ) {
			return new WP_Error( 'missing_fields', __( 'محصول و تعداد الزامی است', 'webinocrm' ) );
		}

		$inbound = self::get( $inbound_id );
		if ( ! $inbound ) {
			return new WP_Error( 'not_found', __( 'رسید یافت نشد', 'webinocrm' ) );
		}

		$item_data_insert = [
			'inbound_id'        => (int) $inbound_id,
			'product_id'        => (int) $item_data['product_id'],
			'quantity_received' => (float) $item_data['quantity_received'],
			'unit_id'           => ! empty( $item_data['unit_id'] ) ? (int) $item_data['unit_id'] : null,
			'unit_price'        => ! empty( $item_data['unit_price'] ) ? (float) $item_data['unit_price'] : null,
			'location'          => ! empty( $item_data['location'] ) ? sanitize_text_field( $item_data['location'] ) : null,
			'description'       => ! empty( $item_data['description'] ) ? wp_kses_post( $item_data['description'] ) : null,
			'sort_order'        => ! empty( $item_data['sort_order'] ) ? (int) $item_data['sort_order'] : 0,
		];

		$result = $wpdb->insert( $items_table, $item_data_insert, self::get_item_formats( $item_data_insert ) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در افزودن سطر', 'webinocrm' ) );
	}

	/**
	 * Post inbound (convert draft to received and create transactions).
	 *
	 * @param int $id Inbound ID.
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

		$inbound = self::get( $id );

		if ( ! $inbound ) {
			return new WP_Error( 'not_found', __( 'رسید یافت نشد', 'webinocrm' ) );
		}

		if ( $inbound->status !== self::STATUS_DRAFT ) {
			return new WP_Error( 'invalid_status', __( 'فقط رسیدهای پیش‌نویس قابل ثبت هستند', 'webinocrm' ) );
		}

		// Create transactions for each item
		if ( ! empty( $inbound->items ) ) {
			foreach ( $inbound->items as $item ) {
				$transaction_result = call_user_func(
					[ $transaction_class, 'create' ],
					[
						'warehouse_id'   => $inbound->warehouse_id,
						'product_id'     => $item->product_id,
						'transaction_type' => $transaction_class::TYPE_INBOUND,
						'quantity'       => $item->quantity_received,
						'unit_id'        => $item->unit_id,
						'document_type'  => $inbound->reference_type ?: $transaction_class::DOCUMENT_GOODS_RECEIPT,
						'document_id'    => $inbound->id,
						'reference_no'   => $inbound->inbound_no,
						'created_by'     => get_current_user_id(),
						'transaction_date' => $inbound->inbound_date . ' ' . current_time( 'H:i:s' ),
					]
				);

				if ( is_wp_error( $transaction_result ) ) {
					return $transaction_result;
				}
			}
		}

		// Update status to received
		$result = $wpdb->update(
			$table,
			[
				'status'    => self::STATUS_RECEIVED,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return (bool) $result;
	}

	/**
	 * Get next inbound number for warehouse.
	 *
	 * @param int $warehouse_id Warehouse ID.
	 *
	 * @return string
	 */
	private static function get_next_inbound_number( $warehouse_id ) {
		global $wpdb;
		$table = self::get_table();

		$latest = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(REPLACE(inbound_no, 'REC-', '') AS UNSIGNED)) FROM $table WHERE warehouse_id = %d",
				(int) $warehouse_id
			)
		);

		$next_number = (int) $latest + 1;
		return sprintf( 'REC-%05d', $next_number );
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
				case 'inbound_id':
				case 'product_id':
				case 'unit_id':
				case 'sort_order':
					$formats[] = '%d';
					break;
				case 'quantity_received':
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
