<?php
/**
 * Accounting Warehouse Transaction (تراکنش موجودی).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse_Transaction
 */
class WebinoCRM_Accounting_Warehouse_Transaction {

	const TABLE = 'webinocrm_accounting_warehouse_transactions';

	const TYPE_INBOUND  = 'inbound';
	const TYPE_OUTBOUND = 'outbound';

	const DOCUMENT_GOODS_RECEIPT = 'goods_receipt';
	const DOCUMENT_INVOICE       = 'invoice';
	const DOCUMENT_TRANSFER      = 'transfer';
	const DOCUMENT_ADJUSTMENT    = 'adjustment';
	const DOCUMENT_AUDIT         = 'audit';

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
	 * Record transaction.
	 *
	 * @param array $data {
	 *     @type int    $warehouse_id   Warehouse ID
	 *     @type int    $product_id     Product ID
	 *     @type string $transaction_type inbound|outbound
	 *     @type float  $quantity       Quantity
	 *     @type int    $unit_id        Unit ID
	 *     @type string $document_type  Document type
	 *     @type int    $document_id    Document ID
	 *     @type string $reference_no   Reference number
	 *     @type string $notes          Notes
	 *     @type int    $created_by     User ID
	 * }
	 *
	 * @return int|WP_Error Transaction ID or error.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		// Validate required fields
		if ( empty( $data['warehouse_id'] ) || empty( $data['product_id'] ) || 
		     empty( $data['transaction_type'] ) || $data['quantity'] === null ) {
			return new WP_Error( 'missing_required', __( 'فیلدهای الزامی انجام نشد', 'webinocrm' ) );
		}

		// Validate transaction type
		if ( ! in_array( $data['transaction_type'], [ self::TYPE_INBOUND, self::TYPE_OUTBOUND ], true ) ) {
			return new WP_Error( 'invalid_type', __( 'نوع تراکنش نامعتبر است', 'webinocrm' ) );
		}

		$insert_data = [
			'warehouse_id'     => (int) $data['warehouse_id'],
			'product_id'       => (int) $data['product_id'],
			'transaction_type' => sanitize_text_field( $data['transaction_type'] ),
			'quantity'         => (float) $data['quantity'],
			'unit_id'          => ! empty( $data['unit_id'] ) ? (int) $data['unit_id'] : null,
			'document_type'    => ! empty( $data['document_type'] ) ? sanitize_text_field( $data['document_type'] ) : null,
			'document_id'      => ! empty( $data['document_id'] ) ? (int) $data['document_id'] : null,
			'reference_no'     => ! empty( $data['reference_no'] ) ? sanitize_text_field( $data['reference_no'] ) : null,
			'notes'            => ! empty( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			'created_by'       => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
			'transaction_date' => ! empty( $data['transaction_date'] ) ? sanitize_text_field( $data['transaction_date'] ) : current_time( 'mysql' ),
		];

		$result = $wpdb->insert( $table, $insert_data, self::get_formats( $insert_data ) );

		if ( $result ) {
			$transaction_id = $wpdb->insert_id;

			// Update warehouse stock
			self::update_stock( $insert_data['warehouse_id'], $insert_data['product_id'], $insert_data['quantity'], $insert_data['transaction_type'], $insert_data['unit_id'] );

			return $transaction_id;
		}

		return new WP_Error( 'db_error', __( 'خطا در ثبت تراکنش', 'webinocrm' ) );
	}

	/**
	 * Get transaction by ID.
	 *
	 * @param int $id Transaction ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get transactions for a product in a warehouse.
	 *
	 * @param int   $warehouse_id Warehouse ID.
	 * @param int   $product_id   Product ID.
	 * @param array $args         Additional arguments.
	 *
	 * @return array
	 */
	public static function get_product_transactions( $warehouse_id, $product_id, $args = [] ) {
		global $wpdb;
		$table = self::get_table();

		$query = "SELECT * FROM $table WHERE warehouse_id = %d AND product_id = %d";
		$params = [ $warehouse_id, $product_id ];

		if ( ! empty( $args['transaction_type'] ) ) {
			$query .= " AND transaction_type = %s";
			$params[] = $args['transaction_type'];
		}

		if ( ! empty( $args['document_type'] ) ) {
			$query .= " AND document_type = %s";
			$params[] = $args['document_type'];
		}

		if ( ! empty( $args['from_date'] ) ) {
			$query .= " AND transaction_date >= %s";
			$params[] = $args['from_date'];
		}

		if ( ! empty( $args['to_date'] ) ) {
			$query .= " AND transaction_date <= %s";
			$params[] = $args['to_date'];
		}

		$query .= ' ORDER BY transaction_date DESC, id DESC';

		if ( ! empty( $args['limit'] ) ) {
			$query .= ' LIMIT %d';
			$params[] = (int) $args['limit'];
		}

		return $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
	}

	/**
	 * Get transactions for a document.
	 *
	 * @param string $document_type Document type.
	 * @param int    $document_id   Document ID.
	 *
	 * @return array
	 */
	public static function get_document_transactions( $document_type, $document_id ) {
		global $wpdb;
		$table = self::get_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE document_type = %s AND document_id = %d ORDER BY id DESC",
				$document_type,
				(int) $document_id
			)
		);
	}

	/**
	 * Update warehouse stock based on transaction.
	 *
	 * @param int    $warehouse_id      Warehouse ID.
	 * @param int    $product_id        Product ID.
	 * @param float  $quantity          Quantity (positive for inbound, negative for outbound).
	 * @param string $transaction_type  inbound|outbound
	 * @param int    $unit_id           Unit ID.
	 *
	 * @return bool
	 */
	private static function update_stock( $warehouse_id, $product_id, $quantity, $transaction_type, $unit_id = null ) {
		global $wpdb;
		$stock_table = $wpdb->prefix . 'webinocrm_accounting_warehouse_stock';

		// Get or create stock record
		$stock = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $stock_table WHERE warehouse_id = %d AND product_id = %d",
				$warehouse_id,
				$product_id
			)
		);

		if ( ! $stock ) {
			// Create new stock record
			$wpdb->insert(
				$stock_table,
				[
					'warehouse_id'  => $warehouse_id,
					'product_id'    => $product_id,
					'quantity'      => $transaction_type === self::TYPE_INBOUND ? $quantity : -$quantity,
					'unit_id'       => $unit_id,
					'updated_at'    => current_time( 'mysql' ),
				],
				[ '%d', '%d', '%f', '%d', '%s' ]
			);
		} else {
			// Update existing stock record
			$new_quantity = $stock->quantity + ( $transaction_type === self::TYPE_INBOUND ? $quantity : -$quantity );

			$wpdb->update(
				$stock_table,
				[
					'quantity'    => $new_quantity,
					'updated_at'  => current_time( 'mysql' ),
				],
				[
					'warehouse_id' => $warehouse_id,
					'product_id'   => $product_id,
				],
				[ '%f', '%s' ],
				[ '%d', '%d' ]
			);
		}

		return true;
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
				case 'warehouse_id':
				case 'product_id':
				case 'unit_id':
				case 'document_id':
				case 'created_by':
					$formats[] = '%d';
					break;
				case 'quantity':
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
