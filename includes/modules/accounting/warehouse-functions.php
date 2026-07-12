<?php
/**
 * Warehouse Reporting Functions (توابع گزارش‌دهی انبار).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get warehouse stock report.
 *
 * @param int   $warehouse_id Warehouse ID.
 * @param array $args         Additional filters.
 *
 * @return array
 */
function webinocrm_get_warehouse_stock_report( $warehouse_id, $args = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Stock' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	return WebinoCRM_Accounting_Warehouse_Stock::get_stock_value_report( $warehouse_id, $args );
}

/**
 * Get product movement report for warehouse.
 *
 * @param int   $warehouse_id Warehouse ID.
 * @param int   $product_id   Product ID.
 * @param array $args         Filter arguments.
 *
 * @return array
 */
function webinocrm_get_product_movement_report( $warehouse_id, $product_id, $args = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Stock' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	return WebinoCRM_Accounting_Warehouse_Stock::get_movement_report( $warehouse_id, $product_id, $args );
}

/**
 * Get low stock items in warehouse.
 *
 * @param int $warehouse_id Warehouse ID.
 *
 * @return array
 */
function webinocrm_get_low_stock_items( $warehouse_id ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Stock' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	return WebinoCRM_Accounting_Warehouse_Stock::get_low_stock_items( $warehouse_id );
}

/**
 * Get warehouse stock card for product.
 *
 * @param int $product_id   Product ID.
 * @param int $warehouse_id Warehouse ID.
 *
 * @return object|null
 */
function webinocrm_get_product_stock_card( $product_id, $warehouse_id ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Stock' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	return WebinoCRM_Accounting_Warehouse_Stock::get_product_stock_card( $product_id, $warehouse_id );
}

/**
 * Get all warehouses.
 *
 * @param array $args Filters.
 *
 * @return array
 */
function webinocrm_get_warehouses( $args = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
	}

	return WebinoCRM_Accounting_Warehouse::get_all( $args );
}

/**
 * Get warehouse by ID.
 *
 * @param int $warehouse_id Warehouse ID.
 *
 * @return object|null
 */
function webinocrm_get_warehouse( $warehouse_id ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
	}

	return WebinoCRM_Accounting_Warehouse::get( $warehouse_id );
}

/**
 * Get default warehouse.
 *
 * @return object|null
 */
function webinocrm_get_default_warehouse() {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
	}

	return WebinoCRM_Accounting_Warehouse::get_default();
}

/**
 * Get warehouse stock for product.
 *
 * @param int $warehouse_id Warehouse ID.
 * @param int $product_id   Product ID.
 *
 * @return float|null Stock quantity or null.
 */
function webinocrm_get_warehouse_product_stock( $warehouse_id, $product_id ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Stock' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	$stock = WebinoCRM_Accounting_Warehouse_Stock::get( $warehouse_id, $product_id );

	return $stock ? $stock->quantity : null;
}

/**
 * Get warehouse stock status (in stock, low, out of stock).
 *
 * @param int $warehouse_id Warehouse ID.
 * @param int $product_id   Product ID.
 *
 * @return string 'in_stock', 'low_stock', or 'out_of_stock'
 */
function webinocrm_get_warehouse_stock_status( $warehouse_id, $product_id ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
	}

	$stock = WebinoCRM_Accounting_Warehouse::get_stock( $warehouse_id, $product_id );

	if ( ! $stock || $stock->quantity <= 0 ) {
		return 'out_of_stock';
	}

	// Check if below reorder point
	global $wpdb;
	$product = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT reorder_point FROM {$wpdb->prefix}webinocrm_accounting_products WHERE id = %d",
			$product_id
		)
	);

	if ( $product && $stock->quantity < $product->reorder_point ) {
		return 'low_stock';
	}

	return 'in_stock';
}

/**
 * Create warehouse inbound record.
 *
 * @param int   $warehouse_id Warehouse ID.
 * @param array $data         Inbound data.
 *
 * @return int|WP_Error Inbound ID or error.
 */
function webinocrm_create_inbound( $warehouse_id, $data = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Inbound' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-inbound.php';
	}

	$data['warehouse_id'] = $warehouse_id;

	return WebinoCRM_Accounting_Warehouse_Inbound::create( $data );
}

/**
 * Create warehouse outbound record.
 *
 * @param int   $warehouse_id Warehouse ID.
 * @param array $data         Outbound data.
 *
 * @return int|WP_Error Outbound ID or error.
 */
function webinocrm_create_outbound( $warehouse_id, $data = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Outbound' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-outbound.php';
	}

	$data['warehouse_id'] = $warehouse_id;

	return WebinoCRM_Accounting_Warehouse_Outbound::create( $data );
}

/**
 * Create warehouse audit.
 *
 * @param int   $warehouse_id Warehouse ID.
 * @param array $data         Audit data.
 *
 * @return int|WP_Error Audit ID or error.
 */
function webinocrm_create_warehouse_audit( $warehouse_id, $data = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Audit' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-audit.php';
	}

	$data['warehouse_id'] = $warehouse_id;

	return WebinoCRM_Accounting_Warehouse_Audit::create( $data );
}

/**
 * Get warehouse stock items (with product details).
 *
 * @param int   $warehouse_id Warehouse ID.
 * @param array $args         Filters.
 *
 * @return array
 */
function webinocrm_get_warehouse_stock_items( $warehouse_id, $args = [] ) {
	if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Stock' ) ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	return WebinoCRM_Accounting_Warehouse_Stock::get_all( $warehouse_id, $args );
}
