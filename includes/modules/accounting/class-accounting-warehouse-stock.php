<?php
/**
 * Accounting Warehouse Stock (موجودی پروژه فعلی).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse_Stock
 */
class WebinoCRM_Accounting_Warehouse_Stock {

	const TABLE = 'webinocrm_accounting_warehouse_stock';

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
	 * Get stock for product in warehouse.
	 *
	 * @param int $warehouse_id Warehouse ID.
	 * @param int $product_id   Product ID.
	 *
	 * @return object|null {
	 *     @type int   $id
	 *     @type int   $warehouse_id
	 *     @type int   $product_id
	 *     @type float $quantity
	 *     @type int   $unit_id
	 * }
	 */
	public static function get( $warehouse_id, $product_id ) {
		global $wpdb;
		$table = self::get_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE warehouse_id = %d AND product_id = %d",
				(int) $warehouse_id,
				(int) $product_id
			)
		);
	}

	/**
	 * Get all stock items in warehouse with product details.
	 *
	 * @param int   $warehouse_id Warehouse ID.
	 * @param array $args         Additional arguments {
	 *     @type bool $only_active     Include only active products
	 *     @type bool $only_counted    Include only items with quantity > 0
	 *     @type int  $limit           Result limit
	 *     @type string $order_by      Order by field (default: product_name)
	 * }
	 *
	 * @return array
	 */
	public static function get_all( $warehouse_id, $args = [] ) {
		global $wpdb;
		$table = self::get_table();

		$query = "SELECT ws.*, p.id as product_id, p.name as product_name, p.code as product_code, 
		          p.inventory_controlled, p.reorder_point, u.symbol as unit_symbol 
		          FROM $table ws 
		          LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ws.product_id = p.id 
		          LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON ws.unit_id = u.id 
		          WHERE ws.warehouse_id = %d";

		$params = [ $warehouse_id ];

		if ( ! empty( $args['only_active'] ) ) {
			$query .= ' AND p.is_active = 1';
		}

		if ( ! empty( $args['only_counted'] ) ) {
			$query .= ' AND ws.quantity > 0';
		}

		if ( ! empty( $args['below_reorder'] ) ) {
			$query .= ' AND ws.quantity < p.reorder_point';
		}

		$order_by = ! empty( $args['order_by'] ) ? sanitize_text_field( $args['order_by'] ) : 'p.name';
		$query .= " ORDER BY $order_by ASC";

		if ( ! empty( $args['limit'] ) ) {
			$query .= ' LIMIT %d';
			$params[] = (int) $args['limit'];
		}

		return $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
	}

	/**
	 * Get stock value report for warehouse.
	 *
	 * @param int   $warehouse_id Warehouse ID.
	 * @param array $args         Additional arguments {
	 *     @type string $valuation_method fifo|lifo|average (for future use)
	 *     @type bool   $include_zero     Include zero stock items
	 * }
	 *
	 * @return array {
	 *     @type array $items List of stock items with values
	 *     @type float $total_quantity Total quantity
	 *     @type float $total_value    Total value
	 * }
	 */
	public static function get_stock_value_report( $warehouse_id, $args = [] ) {
		$items = self::get_all( $warehouse_id, $args );

		$total_quantity = 0;
		$total_value    = 0;

		foreach ( $items as &$item ) {
			// Get last purchase price or use existing value
			$item->unit_cost = self::get_last_unit_cost( $item->product_id, $warehouse_id );
			$item->total_value = $item->quantity * ( $item->unit_cost ?: 0 );

			$total_quantity += $item->quantity;
			$total_value += $item->total_value;
		}

		return [
			'items'            => $items,
			'total_quantity'   => $total_quantity,
			'total_value'      => $total_value,
			'warehouse_id'     => $warehouse_id,
		];
	}

	/**
	 * Get stock movement report for product.
	 *
	 * @param int   $warehouse_id Warehouse ID.
	 * @param int   $product_id   Product ID.
	 * @param array $args         Additional arguments {
	 *     @type string $from_date From date (Y-m-d)
	 *     @type string $to_date   To date (Y-m-d)
	 * }
	 *
	 * @return array {
	 *     @type float $opening  Opening balance
	 *     @type array $movements List of movements
	 *     @type float $closing  Closing balance
	 * }
	 */
	public static function get_movement_report( $warehouse_id, $product_id, $args = [] ) {
		global $wpdb;
		$transaction_table = $wpdb->prefix . 'webinocrm_accounting_warehouse_transactions';

		// Get current balance
		$stock = self::get( $warehouse_id, $product_id );
		$current_balance = $stock ? $stock->quantity : 0;

		// Build movement query
		$query = "SELECT * FROM $transaction_table 
		          WHERE warehouse_id = %d AND product_id = %d";

		$params = [ $warehouse_id, $product_id ];

		if ( ! empty( $args['from_date'] ) ) {
			$query .= ' AND transaction_date >= %s';
			$params[] = $args['from_date'] . ' 00:00:00';
		}

		if ( ! empty( $args['to_date'] ) ) {
			$query .= ' AND transaction_date <= %s';
			$params[] = $args['to_date'] . ' 23:59:59';
		}

		$query .= ' ORDER BY transaction_date ASC, id ASC';

		$movements = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

		// Calculate opening balance (before from_date)
		$opening = $current_balance;
		if ( ! empty( $args['from_date'] ) ) {
			$from_query = "SELECT SUM(
			    CASE WHEN transaction_type = 'inbound' THEN quantity ELSE -quantity END
			) as total FROM $transaction_table 
			WHERE warehouse_id = %d AND product_id = %d AND transaction_date < %s";

			$from_params = [ $warehouse_id, $product_id, $args['from_date'] . ' 00:00:00' ];

			$from_total = $wpdb->get_var( $wpdb->prepare( $from_query, ...$from_params ) );
			$opening = ( $from_total ?: 0 );
		}

		// Calculate closing balance
		$closing = $opening;
		foreach ( $movements as $movement ) {
			$closing += ( $movement->transaction_type === 'inbound' ? $movement->quantity : -$movement->quantity );
		}

		return [
			'warehouse_id' => $warehouse_id,
			'product_id'   => $product_id,
			'opening'      => $opening,
			'movements'    => $movements,
			'closing'      => $closing,
		];
	}

	/**
	 * Check if any products are below reorder point.
	 *
	 * @param int $warehouse_id Warehouse ID.
	 *
	 * @return array Array of low-stock items.
	 */
	public static function get_low_stock_items( $warehouse_id ) {
		global $wpdb;
		$table = self::get_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ws.*, p.name as product_name, p.code as product_code, p.reorder_point,
				(p.reorder_point - ws.quantity) as shortage
				FROM $table ws 
				LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ws.product_id = p.id
				WHERE ws.warehouse_id = %d AND p.inventory_controlled = 1 
				AND ws.quantity < p.reorder_point
				ORDER BY shortage DESC",
				(int) $warehouse_id
			)
		);
	}

	/**
	 * Get last purchase price for product.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $warehouse_id Warehouse ID (optional).
	 *
	 * @return float|null
	 */
	private static function get_last_unit_cost( $product_id, $warehouse_id = null ) {
		global $wpdb;

		$query = "SELECT ii.unit_price FROM {$wpdb->prefix}webinocrm_accounting_warehouse_inbound_items ii
		          INNER JOIN {$wpdb->prefix}webinocrm_accounting_warehouse_inbound i 
		          ON ii.inbound_id = i.id
		          WHERE ii.product_id = %d";

		$params = [ $product_id ];

		if ( $warehouse_id ) {
			$query .= ' AND i.warehouse_id = %d';
			$params[] = $warehouse_id;
		}

		$query .= ' ORDER BY i.inbound_date DESC LIMIT 1';

		$result = $wpdb->get_var( $wpdb->prepare( $query, ...$params ) );

		return $result ? (float) $result : null;
	}

	/**
	 * Get product details with current stock.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $warehouse_id Warehouse ID.
	 *
	 * @return object|null
	 */
	public static function get_product_stock_card( $product_id, $warehouse_id ) {
		global $wpdb;
		$table = self::get_table();

		$query = "SELECT ws.*, p.name as product_name, p.code as product_code, 
		          p.purchase_price, p.inventory_controlled, p.reorder_point,
		          u.symbol as unit_symbol, u.name as unit_name
		          FROM $table ws
		          LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ws.product_id = p.id
		          LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON ws.unit_id = u.id
		          WHERE ws.warehouse_id = %d AND ws.product_id = %d";

		$card = $wpdb->get_row( $wpdb->prepare( $query, $warehouse_id, $product_id ) );

		if ( $card ) {
			// Add transaction history
			$card->transactions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}webinocrm_accounting_warehouse_transactions
					WHERE warehouse_id = %d AND product_id = %d
					ORDER BY transaction_date DESC, id DESC LIMIT 50",
					$warehouse_id,
					$product_id
				)
			);
		}

		return $card;
	}
}
