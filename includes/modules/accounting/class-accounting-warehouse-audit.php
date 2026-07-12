<?php
/**
 * Accounting Warehouse Audit (انبارگردانی/شمارش فیزیکی).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse_Audit
 */
class WebinoCRM_Accounting_Warehouse_Audit {

	const TABLE = 'webinocrm_accounting_warehouse_audits';
	const ITEMS_TABLE = 'webinocrm_accounting_warehouse_audit_items';
	const ENTRIES_TABLE = 'webinocrm_accounting_warehouse_audit_entries';

	const STATUS_DRAFT      = 'draft';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_COMPLETED = 'completed';
	const STATUS_POSTED    = 'posted';

	const METHOD_FIFO    = 'fifo';
	const METHOD_LIFO    = 'lifo';
	const METHOD_AVERAGE = 'average';

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
	 * Get entries table name with prefix.
	 *
	 * @return string
	 */
	public static function get_entries_table() {
		global $wpdb;
		return $wpdb->prefix . self::ENTRIES_TABLE;
	}

	/**
	 * Create audit record.
	 *
	 * @param array $data {
	 *     @type int    $warehouse_id       Warehouse ID
	 *     @type int    $fiscal_year_id     Fiscal year ID
	 *     @type string $audit_date         Date (Y-m-d)
	 *     @type string $valuation_method   fifo|lifo|average
	 *     @type string $description        Description
	 *     @type int    $created_by         User ID
	 * }
	 *
	 * @return int|WP_Error Audit ID or error.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		// Validate required fields
		if ( empty( $data['warehouse_id'] ) ) {
			return new WP_Error( 'missing_warehouse', __( 'انبار الزامی است', 'webinocrm' ) );
		}

		// Get next audit number
		$audit_no = self::get_next_audit_number();

		$insert_data = [
			'warehouse_id'     => (int) $data['warehouse_id'],
			'audit_no'         => $audit_no,
			'audit_date'       => ! empty( $data['audit_date'] ) ? sanitize_text_field( $data['audit_date'] ) : current_time( 'Y-m-d' ),
			'fiscal_year_id'   => ! empty( $data['fiscal_year_id'] ) ? (int) $data['fiscal_year_id'] : null,
			'valuation_method' => ! empty( $data['valuation_method'] ) ? sanitize_text_field( $data['valuation_method'] ) : self::METHOD_FIFO,
			'description'      => ! empty( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'status'           => self::STATUS_DRAFT,
			'created_by'       => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
		];

		$result = $wpdb->insert( $table, $insert_data, self::get_formats( $insert_data ) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در ایجاد انبارگردانی', 'webinocrm' ) );
	}

	/**
	 * Get audit by ID with items.
	 *
	 * @param int $id Audit ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();

		$audit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );

		if ( $audit ) {
			$audit->items = self::get_items( $id );
		}

		return $audit;
	}

	/**
	 * Get audit items with product details.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array
	 */
	public static function get_items( $audit_id ) {
		global $wpdb;
		$items_table = self::get_items_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ai.*, p.name as product_name, p.code as product_code, u.symbol as unit_symbol 
				FROM $items_table ai
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ai.product_id = p.id
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON ai.unit_id = u.id
				WHERE ai.audit_id = %d
				ORDER BY p.name ASC",
				(int) $audit_id
			)
		);
	}

	/**
	 * Initialize audit items from current warehouse stock.
	 *
	 * @param int $audit_id    Audit ID.
	 * @param int $warehouse_id Warehouse ID.
	 *
	 * @return int Number of items added.
	 */
	public static function initialize_items( $audit_id, $warehouse_id ) {
		global $wpdb;
		$items_table = self::get_items_table();

		$warehouse_class = 'WebinoCRM_Accounting_Warehouse';
		if ( ! class_exists( $warehouse_class ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
		}

		// Get all current stock for warehouse
		$stock_items = call_user_func( [ $warehouse_class, 'get_warehouse_stock' ], $warehouse_id );

		$count = 0;
		foreach ( $stock_items as $stock ) {
			$result = $wpdb->insert(
				$items_table,
				[
					'audit_id'        => $audit_id,
					'product_id'      => $stock->product_id,
					'unit_id'         => $stock->unit_id,
					'system_quantity' => $stock->quantity,
					'physical_quantity' => 0,
					'variance_quantity' => 0,
				],
				[ '%d', '%d', '%d', '%f', '%f', '%f' ]
			);

			if ( $result ) {
				$count++;
			}
		}

		// Update audit status to in progress
		$audit_table = self::get_table();
		$wpdb->update(
			$audit_table,
			[ 'status' => self::STATUS_IN_PROGRESS ],
			[ 'id' => $audit_id ],
			[ '%s' ],
			[ '%d' ]
		);

		return $count;
	}

	/**
	 * Add or update audit item.
	 *
	 * @param int   $audit_id Audit ID.
	 * @param array $item_data {
	 *     @type int   $product_id Product ID
	 *     @type int   $unit_id Unit ID
	 *     @type float $physical_quantity Counted quantity
	 *     @type float $unit_price Unit price (for variance valuation)
	 *     @type string $location Location
	 * }
	 *
	 * @return int|WP_Error Item ID or error.
	 */
	public static function record_item_count( $audit_id, $item_data ) {
		global $wpdb;
		$items_table = self::get_items_table();

		if ( empty( $item_data['product_id'] ) || $item_data['physical_quantity'] === null ) {
			return new WP_Error( 'missing_fields', __( 'محصول و تعداد الزامی است', 'webinocrm' ) );
		}

		// Check if item exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $items_table WHERE audit_id = %d AND product_id = %d",
				$audit_id,
				$item_data['product_id']
			)
		);

		if ( $existing ) {
			// Update existing
			$variance_qty = $item_data['physical_quantity'] - $existing->system_quantity;
			$variance_amt = $variance_qty * ( ! empty( $item_data['unit_price'] ) ? $item_data['unit_price'] : 0 );

			$wpdb->update(
				$items_table,
				[
					'physical_quantity' => (float) $item_data['physical_quantity'],
					'variance_quantity' => $variance_qty,
					'variance_amount'   => $variance_amt,
					'variance_type'     => $variance_qty > 0 ? 'surplus' : 'shortage',
					'unit_price'        => ! empty( $item_data['unit_price'] ) ? (float) $item_data['unit_price'] : null,
					'location'          => ! empty( $item_data['location'] ) ? sanitize_text_field( $item_data['location'] ) : null,
				],
				[
					'audit_id'   => $audit_id,
					'product_id' => $item_data['product_id'],
				],
				[ '%f', '%f', '%f', '%s', '%f', '%s' ],
				[ '%d', '%d' ]
			);

			return $existing->id;
		} else {
			// Insert new
			$variance_qty = $item_data['physical_quantity'];
			$variance_amt = $variance_qty * ( ! empty( $item_data['unit_price'] ) ? $item_data['unit_price'] : 0 );

			$wpdb->insert(
				$items_table,
				[
					'audit_id'          => $audit_id,
					'product_id'        => (int) $item_data['product_id'],
					'unit_id'           => ! empty( $item_data['unit_id'] ) ? (int) $item_data['unit_id'] : null,
					'system_quantity'   => 0,
					'physical_quantity' => (float) $item_data['physical_quantity'],
					'variance_quantity' => $variance_qty,
					'variance_amount'   => $variance_amt,
					'variance_type'     => $variance_qty > 0 ? 'surplus' : 'shortage',
					'unit_price'        => ! empty( $item_data['unit_price'] ) ? (float) $item_data['unit_price'] : null,
					'location'          => ! empty( $item_data['location'] ) ? sanitize_text_field( $item_data['location'] ) : null,
				],
				[ '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%f', '%s' ]
			);

			return $wpdb->insert_id;
		}
	}

	/**
	 * Complete audit and calculate total variance.
	 *
	 * @param int $id Audit ID.
	 *
	 * @return bool|WP_Error
	 */
	public static function complete( $id ) {
		global $wpdb;
		$table = self::get_table();
		$items_table = self::get_items_table();

		// Calculate total discrepancy
		$total_discrepancy = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(ABS(variance_amount)) FROM $items_table WHERE audit_id = %d",
				$id
			)
		);

		$result = $wpdb->update(
			$table,
			[
				'status'                   => self::STATUS_COMPLETED,
				'total_discrepancy_amount' => (float) $total_discrepancy,
				'updated_at'               => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%f', '%s' ],
			[ '%d' ]
		);

		return (bool) $result;
	}

	/**
	 * Post audit (create journal entry for variances and update stock).
	 *
	 * @param int $id Audit ID.
	 *
	 * @return bool|WP_Error
	 */
	public static function post( $id ) {
		global $wpdb;
		$table = self::get_table();
		$transaction_class = 'WebinoCRM_Accounting_Warehouse_Transaction';
		$journal_class = 'WebinoCRM_Accounting_Journal_Entry';

		if ( ! class_exists( $transaction_class ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-transaction.php';
		}

		$audit = self::get( $id );

		if ( ! $audit ) {
			return new WP_Error( 'not_found', __( 'انبارگردانی یافت نشد', 'webinocrm' ) );
		}

		if ( $audit->status !== self::STATUS_COMPLETED ) {
			return new WP_Error( 'invalid_status', __( 'انبارگردانی باید تکمیل شده باشد', 'webinocrm' ) );
		}

		// Create adjustment transactions for variances
		if ( ! empty( $audit->items ) ) {
			foreach ( $audit->items as $item ) {
				if ( $item->variance_quantity != 0 ) {
					$transaction_result = call_user_func(
						[ $transaction_class, 'create' ],
						[
							'warehouse_id'   => $audit->warehouse_id,
							'product_id'     => $item->product_id,
							'transaction_type' => $item->variance_quantity > 0 ? $transaction_class::TYPE_INBOUND : $transaction_class::TYPE_OUTBOUND,
							'quantity'       => abs( $item->variance_quantity ),
							'unit_id'        => $item->unit_id,
							'document_type'  => $transaction_class::DOCUMENT_AUDIT,
							'document_id'    => $audit->id,
							'reference_no'   => $audit->audit_no,
							'created_by'     => get_current_user_id(),
							'transaction_date' => $audit->audit_date . ' ' . current_time( 'H:i:s' ),
						]
					);

					if ( is_wp_error( $transaction_result ) ) {
						return $transaction_result;
					}
				}
			}
		}

		// Update audit status to posted
		$result = $wpdb->update(
			$table,
			[
				'status'    => self::STATUS_POSTED,
				'posted_by' => get_current_user_id(),
				'posted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return (bool) $result;
	}

	/**
	 * Get next audit number.
	 *
	 * @return string
	 */
	private static function get_next_audit_number() {
		global $wpdb;
		$table = self::get_table();

		$latest = $wpdb->get_var(
			"SELECT MAX(CAST(REPLACE(audit_no, 'AUD-', '') AS UNSIGNED)) FROM $table"
		);

		$next_number = (int) $latest + 1;
		return sprintf( 'AUD-%05d', $next_number );
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
				case 'fiscal_year_id':
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
