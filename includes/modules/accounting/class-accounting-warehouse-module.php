<?php
/**
 * Accounting Warehouse Module Manager (مدیریت ماژول انبار).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Warehouse_Module
 */
class WebinoCRM_Accounting_Warehouse_Module {

	/**
	 * Initialize module.
	 */
	public static function init() {
		// Load all warehouse classes
		self::load_classes();

		// Add AJAX handlers
		add_action( 'wp_ajax_webino_get_warehouses', [ __CLASS__, 'ajax_get_warehouses' ] );
		add_action( 'wp_ajax_webino_create_warehouse', [ __CLASS__, 'ajax_create_warehouse' ] );
		add_action( 'wp_ajax_webino_update_warehouse', [ __CLASS__, 'ajax_update_warehouse' ] );
		add_action( 'wp_ajax_webino_delete_warehouse', [ __CLASS__, 'ajax_delete_warehouse' ] );

		add_action( 'wp_ajax_webino_create_inbound', [ __CLASS__, 'ajax_create_inbound' ] );
		add_action( 'wp_ajax_webino_get_inbound', [ __CLASS__, 'ajax_get_inbound' ] );
		add_action( 'wp_ajax_webino_add_inbound_item', [ __CLASS__, 'ajax_add_inbound_item' ] );
		add_action( 'wp_ajax_webino_post_inbound', [ __CLASS__, 'ajax_post_inbound' ] );

		add_action( 'wp_ajax_webino_create_outbound', [ __CLASS__, 'ajax_create_outbound' ] );
		add_action( 'wp_ajax_webino_get_outbound', [ __CLASS__, 'ajax_get_outbound' ] );
		add_action( 'wp_ajax_webino_add_outbound_item', [ __CLASS__, 'ajax_add_outbound_item' ] );
		add_action( 'wp_ajax_webino_post_outbound', [ __CLASS__, 'ajax_post_outbound' ] );

		add_action( 'wp_ajax_webino_create_audit', [ __CLASS__, 'ajax_create_audit' ] );
		add_action( 'wp_ajax_webino_get_audit', [ __CLASS__, 'ajax_get_audit' ] );
		add_action( 'wp_ajax_webino_initialize_audit_items', [ __CLASS__, 'ajax_initialize_audit_items' ] );
		add_action( 'wp_ajax_webino_record_audit_item', [ __CLASS__, 'ajax_record_audit_item' ] );
		add_action( 'wp_ajax_webino_complete_audit', [ __CLASS__, 'ajax_complete_audit' ] );
		add_action( 'wp_ajax_webino_post_audit', [ __CLASS__, 'ajax_post_audit' ] );

		add_action( 'wp_ajax_webino_get_warehouse_stock', [ __CLASS__, 'ajax_get_warehouse_stock' ] );
		add_action( 'wp_ajax_webino_get_stock_report', [ __CLASS__, 'ajax_get_stock_report' ] );
		add_action( 'wp_ajax_webino_get_movement_report', [ __CLASS__, 'ajax_get_movement_report' ] );
		add_action( 'wp_ajax_webino_get_low_stock_items', [ __CLASS__, 'ajax_get_low_stock_items' ] );
	}

	/**
	 * Load all warehouse-related classes.
	 */
	private static function load_classes() {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-transaction.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-inbound.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-outbound.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-audit.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-warehouse-stock.php';
	}

	/**
	 * Get all warehouses.
	 */
	public static function ajax_get_warehouses() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouses = WebinoCRM_Accounting_Warehouse::get_all(
			[
				'only_active' => ! empty( $_POST['only_active'] ),
			]
		);

		wp_send_json_success( [ 'warehouses' => $warehouses ] );
	}

	/**
	 * Create warehouse.
	 */
	public static function ajax_create_warehouse() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$data = json_decode( stripslashes( $_POST['data'] ), true );

		$result = WebinoCRM_Accounting_Warehouse::create( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'warehouse_id' => $result, 'message' => __( 'انبار با موفقیت ایجاد شد', 'webinocrm' ) ] );
	}

	/**
	 * Update warehouse.
	 */
	public static function ajax_update_warehouse() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;
		$data = json_decode( stripslashes( $_POST['data'] ), true );

		$result = WebinoCRM_Accounting_Warehouse::update( $warehouse_id, $data );

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'خطا در به‌روزرسانی انبار', 'webinocrm' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'انبار با موفقیت به‌روزرسانی شد', 'webinocrm' ) ] );
	}

	/**
	 * Delete warehouse.
	 */
	public static function ajax_delete_warehouse() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;

		$result = WebinoCRM_Accounting_Warehouse::delete( $warehouse_id );

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'خطا در حذف انبار', 'webinocrm' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'انبار با موفقیت حذف شد', 'webinocrm' ) ] );
	}

	/**
	 * Create inbound.
	 */
	public static function ajax_create_inbound() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$data = json_decode( stripslashes( $_POST['data'] ), true );
		$data['created_by'] = get_current_user_id();

		$result = WebinoCRM_Accounting_Warehouse_Inbound::create( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'inbound_id' => $result, 'message' => __( 'رسید با موفقیت ایجاد شد', 'webinocrm' ) ] );
	}

	/**
	 * Get inbound.
	 */
	public static function ajax_get_inbound() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$inbound_id = ! empty( $_POST['inbound_id'] ) ? (int) $_POST['inbound_id'] : 0;

		$inbound = WebinoCRM_Accounting_Warehouse_Inbound::get( $inbound_id );

		if ( ! $inbound ) {
			wp_send_json_error( [ 'message' => __( 'رسید یافت نشد', 'webinocrm' ) ] );
		}

		wp_send_json_success( [ 'inbound' => $inbound ] );
	}

	/**
	 * Add inbound item.
	 */
	public static function ajax_add_inbound_item() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$inbound_id = ! empty( $_POST['inbound_id'] ) ? (int) $_POST['inbound_id'] : 0;
		$item_data = json_decode( stripslashes( $_POST['item_data'] ), true );

		$result = WebinoCRM_Accounting_Warehouse_Inbound::add_item( $inbound_id, $item_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'item_id' => $result, 'message' => __( 'سطر با موفقیت اضافه شد', 'webinocrm' ) ] );
	}

	/**
	 * Post inbound.
	 */
	public static function ajax_post_inbound() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$inbound_id = ! empty( $_POST['inbound_id'] ) ? (int) $_POST['inbound_id'] : 0;

		$result = WebinoCRM_Accounting_Warehouse_Inbound::post( $inbound_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'رسید با موفقیت ثبت شد', 'webinocrm' ) ] );
	}

	/**
	 * Create outbound.
	 */
	public static function ajax_create_outbound() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$data = json_decode( stripslashes( $_POST['data'] ), true );
		$data['created_by'] = get_current_user_id();

		$result = WebinoCRM_Accounting_Warehouse_Outbound::create( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'outbound_id' => $result, 'message' => __( 'حواله با موفقیت ایجاد شد', 'webinocrm' ) ] );
	}

	/**
	 * Get outbound.
	 */
	public static function ajax_get_outbound() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$outbound_id = ! empty( $_POST['outbound_id'] ) ? (int) $_POST['outbound_id'] : 0;

		$outbound = WebinoCRM_Accounting_Warehouse_Outbound::get( $outbound_id );

		if ( ! $outbound ) {
			wp_send_json_error( [ 'message' => __( 'حواله یافت نشد', 'webinocrm' ) ] );
		}

		wp_send_json_success( [ 'outbound' => $outbound ] );
	}

	/**
	 * Add outbound item.
	 */
	public static function ajax_add_outbound_item() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$outbound_id = ! empty( $_POST['outbound_id'] ) ? (int) $_POST['outbound_id'] : 0;
		$item_data = json_decode( stripslashes( $_POST['item_data'] ), true );

		$result = WebinoCRM_Accounting_Warehouse_Outbound::add_item( $outbound_id, $item_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'item_id' => $result, 'message' => __( 'سطر با موفقیت اضافه شد', 'webinocrm' ) ] );
	}

	/**
	 * Post outbound.
	 */
	public static function ajax_post_outbound() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$outbound_id = ! empty( $_POST['outbound_id'] ) ? (int) $_POST['outbound_id'] : 0;

		$result = WebinoCRM_Accounting_Warehouse_Outbound::post( $outbound_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'حواله با موفقیت ثبت شد', 'webinocrm' ) ] );
	}

	/**
	 * Create audit.
	 */
	public static function ajax_create_audit() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$data = json_decode( stripslashes( $_POST['data'] ), true );
		$data['created_by'] = get_current_user_id();

		$result = WebinoCRM_Accounting_Warehouse_Audit::create( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'audit_id' => $result, 'message' => __( 'انبارگردانی با موفقیت ایجاد شد', 'webinocrm' ) ] );
	}

	/**
	 * Get audit.
	 */
	public static function ajax_get_audit() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$audit_id = ! empty( $_POST['audit_id'] ) ? (int) $_POST['audit_id'] : 0;

		$audit = WebinoCRM_Accounting_Warehouse_Audit::get( $audit_id );

		if ( ! $audit ) {
			wp_send_json_error( [ 'message' => __( 'انبارگردانی یافت نشد', 'webinocrm' ) ] );
		}

		wp_send_json_success( [ 'audit' => $audit ] );
	}

	/**
	 * Initialize audit items from current stock.
	 */
	public static function ajax_initialize_audit_items() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$audit_id = ! empty( $_POST['audit_id'] ) ? (int) $_POST['audit_id'] : 0;
		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;

		$count = WebinoCRM_Accounting_Warehouse_Audit::initialize_items( $audit_id, $warehouse_id );

		wp_send_json_success( [
			'count' => $count,
			'message' => sprintf( __( '%d کالا برای شمارش آماده شد', 'webinocrm' ), $count ),
		] );
	}

	/**
	 * Record audit item count.
	 */
	public static function ajax_record_audit_item() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$audit_id = ! empty( $_POST['audit_id'] ) ? (int) $_POST['audit_id'] : 0;
		$item_data = json_decode( stripslashes( $_POST['item_data'] ), true );

		$result = WebinoCRM_Accounting_Warehouse_Audit::record_item_count( $audit_id, $item_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'item_id' => $result, 'message' => __( 'شمارش با موفقیت ثبت شد', 'webinocrm' ) ] );
	}

	/**
	 * Complete audit.
	 */
	public static function ajax_complete_audit() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$audit_id = ! empty( $_POST['audit_id'] ) ? (int) $_POST['audit_id'] : 0;

		$result = WebinoCRM_Accounting_Warehouse_Audit::complete( $audit_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'انبارگردانی با موفقیت تکمیل شد', 'webinocrm' ) ] );
	}

	/**
	 * Post audit.
	 */
	public static function ajax_post_audit() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$audit_id = ! empty( $_POST['audit_id'] ) ? (int) $_POST['audit_id'] : 0;

		$result = WebinoCRM_Accounting_Warehouse_Audit::post( $audit_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'انبارگردانی با موفقیت ثبت شد', 'webinocrm' ) ] );
	}

	/**
	 * Get warehouse stock.
	 */
	public static function ajax_get_warehouse_stock() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;

		$stock = WebinoCRM_Accounting_Warehouse_Stock::get_all( $warehouse_id, [
			'only_active' => true,
			'limit' => 100,
		] );

		wp_send_json_success( [ 'stock' => $stock ] );
	}

	/**
	 * Get stock report.
	 */
	public static function ajax_get_stock_report() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;

		$report = WebinoCRM_Accounting_Warehouse_Stock::get_stock_value_report( $warehouse_id, [
			'only_active' => true,
		] );

		wp_send_json_success( [ 'report' => $report ] );
	}

	/**
	 * Get movement report.
	 */
	public static function ajax_get_movement_report() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;
		$product_id = ! empty( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

		$report = WebinoCRM_Accounting_Warehouse_Stock::get_movement_report( $warehouse_id, $product_id, [
			'from_date' => ! empty( $_POST['from_date'] ) ? sanitize_text_field( $_POST['from_date'] ) : null,
			'to_date' => ! empty( $_POST['to_date'] ) ? sanitize_text_field( $_POST['to_date'] ) : null,
		] );

		wp_send_json_success( [ 'report' => $report ] );
	}

	/**
	 * Get low stock items.
	 */
	public static function ajax_get_low_stock_items() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی رد شد', 'webinocrm' ) ], 403 );
		}

		$warehouse_id = ! empty( $_POST['warehouse_id'] ) ? (int) $_POST['warehouse_id'] : 0;

		$items = WebinoCRM_Accounting_Warehouse_Stock::get_low_stock_items( $warehouse_id );

		wp_send_json_success( [ 'items' => $items ] );
	}
}

// Initialize module
WebinoCRM_Accounting_Warehouse_Module::init();
