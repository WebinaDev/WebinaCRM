<?php
/**
 * Warehouse & Invoice Integration (یکپارچگی انبار و فاکتور).
 *
 * This class handles automatic generation of warehouse receipts/outbounds
 * when invoices are created or confirmed.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Warehouse_Invoice_Integration
 */
class WebinoCRM_Warehouse_Invoice_Integration {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// When a sales invoice is created/confirmed, create outbound (حواله)
		add_action( 'webinocrm_invoice_created', [ __CLASS__, 'on_invoice_created' ], 10, 2 );
		add_action( 'webinocrm_invoice_confirmed', [ __CLASS__, 'on_sales_invoice_confirmed' ], 10, 1 );
		add_action( 'webinocrm_invoice_updated', [ __CLASS__, 'on_invoice_updated' ], 10, 2 );

		// When a purchase invoice is created, create inbound (رسید)
		// (Note: This would typically be handled by a goods receipt process)
	}

	/**
	 * When invoice is created, optionally create outbound/inbound.
	 *
	 * @param int   $invoice_id Invoice ID.
	 * @param array $invoice_data Invoice data.
	 */
	public static function on_invoice_created( $invoice_id, $invoice_data ) {
		// Optional: Auto-create draft outbound on sales invoice creation
		// For now, we'll skip this to let users manually create outbounds
	}

	/**
	 * When sales invoice is confirmed, create/finalize outbound.
	 *
	 * @param int $invoice_id Invoice ID.
	 */
	public static function on_sales_invoice_confirmed( $invoice_id ) {
		if ( ! class_exists( 'WebinoCRM_Accounting_Invoice' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-invoice.php';
		}

		$invoice = WebinoCRM_Accounting_Invoice::get( $invoice_id );

		if ( ! $invoice || $invoice->invoice_type !== 'sales' ) {
			return;
		}

		// Check if outbound already exists
		$existing_outbound = self::get_outbound_for_invoice( $invoice_id );

		if ( $existing_outbound ) {
			// Outbound already exists, post it if not already posted
			if ( $existing_outbound->status === 'draft' ) {
				self::post_outbound( $existing_outbound->id );
			}
			return;
		}

		// Auto-create outbound from confirmed sales invoice
		self::create_outbound_from_invoice( $invoice );
	}

	/**
	 * When invoice is updated, sync warehouse transactions.
	 *
	 * @param int   $invoice_id Invoice ID.
	 * @param array $old_data   Old invoice data.
	 */
	public static function on_invoice_updated( $invoice_id, $old_data ) {
		// Handle inventory sync when invoice is modified
	}

	/**
	 * Create outbound from sales invoice.
	 *
	 * @param object $invoice Invoice object.
	 *
	 * @return int|WP_Error Outbound ID or error.
	 */
	private static function create_outbound_from_invoice( $invoice ) {
		if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Outbound' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-outbound.php';
		}

		if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
		}

		// Get default warehouse
		$default_warehouse = WebinoCRM_Accounting_Warehouse::get_default();
		$warehouse_id = $default_warehouse ? $default_warehouse->id : null;

		if ( ! $warehouse_id ) {
			return new WP_Error( 'no_warehouse', __( 'انبار پیش‌فرض تعریف نشده', 'webinocrm' ) );
		}

		// Create outbound
		$outbound_id = WebinoCRM_Accounting_Warehouse_Outbound::create( [
			'warehouse_id'   => $warehouse_id,
			'reference_type' => 'sales_invoice',
			'reference_id'   => $invoice->id,
			'outbound_date'  => $invoice->invoice_date,
			'description'    => sprintf(
				__( 'صادره خودکار برای فاکتور فروش #%s', 'webinocrm' ),
				$invoice->invoice_no
			),
			'created_by'     => get_current_user_id(),
		] );

		if ( is_wp_error( $outbound_id ) ) {
			return $outbound_id;
		}

		// Add items from invoice
		if ( ! empty( $invoice->items ) ) {
			foreach ( $invoice->items as $item ) {
				$item_result = WebinoCRM_Accounting_Warehouse_Outbound::add_item( $outbound_id, [
					'product_id'       => $item->product_id,
					'quantity_shipped' => $item->quantity,
					'unit_id'          => $item->unit_id,
					'unit_price'       => $item->unit_price,
					'description'      => $item->description,
				] );

				if ( is_wp_error( $item_result ) ) {
					return $item_result;
				}
			}
		}

		// Auto-post outbound
		$post_result = WebinoCRM_Accounting_Warehouse_Outbound::post( $outbound_id );

		if ( is_wp_error( $post_result ) ) {
			return $post_result;
		}

		return $outbound_id;
	}

	/**
	 * Get outbound for invoice.
	 *
	 * @param int $invoice_id Invoice ID.
	 *
	 * @return object|null
	 */
	private static function get_outbound_for_invoice( $invoice_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_accounting_warehouse_outbound';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE reference_type = 'sales_invoice' AND reference_id = %d LIMIT 1",
				(int) $invoice_id
			)
		);
	}

	/**
	 * Post outbound.
	 *
	 * @param int $outbound_id Outbound ID.
	 *
	 * @return bool|WP_Error
	 */
	private static function post_outbound( $outbound_id ) {
		if ( ! class_exists( 'WebinoCRM_Accounting_Warehouse_Outbound' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-outbound.php';
		}

		return WebinoCRM_Accounting_Warehouse_Outbound::post( $outbound_id );
	}
}

// Initialize integration
WebinoCRM_Warehouse_Invoice_Integration::init();
