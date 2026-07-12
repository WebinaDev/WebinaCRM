<?php
/**
 * Warehouse REST service (warehouses, stock, inbound/outbound/audit, products alias).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Warehouse_Service {

	/**
	 * @return void
	 */
	private static function ensure_warehouse_classes() {
		if ( class_exists( 'WebinoCRM_Accounting_Warehouse' ) ) {
			return;
		}
		$base = WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/';
		require_once $base . 'class-accounting-warehouse.php';
		require_once $base . 'class-accounting-warehouse-transaction.php';
		require_once $base . 'class-accounting-warehouse-inbound.php';
		require_once $base . 'class-accounting-warehouse-outbound.php';
		require_once $base . 'class-accounting-warehouse-audit.php';
		require_once $base . 'class-accounting-warehouse-stock.php';
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>|true
	 */
	private static function verify_access( array $params ) {
		$allowed = current_user_can( 'manage_options' )
			|| ( function_exists( 'webinocrm_current_user_can_accounting' ) && webinocrm_current_user_can_accounting() );
		if ( ! $allowed ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && WebinoCRM_Service_Base::verify_crm_request() ) {
			return true;
		}
		if ( class_exists( 'WebinoCRM_Accounting_Service' ) ) {
			return WebinoCRM_Accounting_Service::verify_accounting_access( $params );
		}
		return true;
	}

	/**
	 * REST segment dispatcher (scm/* alias).
	 *
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function by_segment( array $params ) {
		$segment = isset( $params['segment'] ) ? sanitize_key( str_replace( '-', '_', (string) $params['segment'] ) ) : '';
		$map     = array(
			'warehouses'        => 'list',
			'warehouse_stock'   => 'stock_list',
			'warehouse_inbound' => 'inbound_list',
			'warehouse_outbound'=> 'outbound_list',
			'warehouse_audit'   => 'audit_list',
			'products'          => 'products',
		);
		$method  = isset( $map[ $segment ] ) ? $map[ $segment ] : $segment;
		if ( ! method_exists( __CLASS__, $method ) ) {
			return WebinoCRM_Service_Base::error( __( 'Unknown warehouse action.', 'webinocrm' ), 404 );
		}
		return call_user_func( array( __CLASS__, $method ), $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$search   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'search', '' ) ) );
		$page     = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) );
		$per_page = max( 1, min( 100, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 50 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$rows  = WebinoCRM_Accounting_Warehouse::get_all(
			array(
				'limit'  => $per_page,
				'offset' => $offset,
			)
		);
		$items = array();
		foreach ( $rows as $row ) {
			$normalized = self::normalize_warehouse( $row );
			if ( $search !== '' ) {
				$haystack = strtolower( $normalized['name'] . ' ' . $normalized['code'] );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}
			$items[] = $normalized;
		}

		global $wpdb;
		$table = WebinoCRM_Accounting_Warehouse::get_table();
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

		return array(
			'success' => true,
			'data'    => array(
				'items' => $items,
				'total' => $total,
			),
		);
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function create( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$data = self::warehouse_payload( $params );
		$data['created_by'] = get_current_user_id();
		$result             = WebinoCRM_Accounting_Warehouse::create( $data );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => (int) $result ) );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function update( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه انبار نامعتبر است.', 'webinocrm' ) );
		}
		$data   = self::warehouse_payload( $params );
		$result = WebinoCRM_Accounting_Warehouse::update( $id, $data );
		if ( ! $result ) {
			return WebinoCRM_Service_Base::error( __( 'خطا در به‌روزرسانی انبار.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success();
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه انبار نامعتبر است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Accounting_Warehouse::delete( $id );
		if ( ! $result ) {
			return WebinoCRM_Service_Base::error( __( 'خطا در حذف انبار.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success();
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function products( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$result = WebinoCRM_Accounting_Service::products_list( $params );
		if ( empty( $result['success'] ) ) {
			return $result;
		}
		$items = isset( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array();
		$total = isset( $result['data']['total'] ) ? (int) $result['data']['total'] : count( $items );
		return array(
			'success' => true,
			'data'    => $items,
			'total'   => $total,
		);
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function stock_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$warehouse_id = WebinoCRM_Service_Base::int_param( $params, 'warehouse_id' );
		$search         = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'search', '' ) ) );
		$low_stock      = (int) WebinoCRM_Service_Base::param( $params, 'low_stock', 0 ) === 1;
		$page           = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) );
		$per_page       = max( 1, min( 100, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 20 ) ) );
		$offset         = ( $page - 1 ) * $per_page;

		global $wpdb;
		$stock_table = WebinoCRM_Accounting_Warehouse_Stock::get_table();
		$wh_table    = WebinoCRM_Accounting_Warehouse::get_table();
		$where       = array( '1=1' );
		$values      = array();

		if ( $warehouse_id > 0 ) {
			$where[]  = 'ws.warehouse_id = %d';
			$values[] = $warehouse_id;
		}
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( p.name LIKE %s OR p.code LIKE %s )';
			$values[] = $like;
			$values[] = $like;
		}
		if ( $low_stock ) {
			$where[] = 'ws.quantity <= p.reorder_point';
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM $stock_table ws
			LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ws.product_id = p.id
			WHERE $where_sql";
		$total     = ! empty( $values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) )
			: (int) $wpdb->get_var( $count_sql );

		$list_sql = "SELECT ws.*, p.name AS product_name, p.code AS product_code, p.reorder_point,
			u.symbol AS unit_symbol, w.name AS warehouse_name
			FROM $stock_table ws
			LEFT JOIN {$wpdb->prefix}webinocrm_accounting_products p ON ws.product_id = p.id
			LEFT JOIN {$wpdb->prefix}webinocrm_accounting_units u ON ws.unit_id = u.id
			LEFT JOIN $wh_table w ON ws.warehouse_id = w.id
			WHERE $where_sql
			ORDER BY p.name ASC
			LIMIT %d OFFSET %d";
		$query_values = array_merge( $values, array( $per_page, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( $list_sql, $query_values ) );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'id'               => (int) $row->id,
				'product_id'       => (int) $row->product_id,
				'product_name'     => (string) ( $row->product_name ?? '' ),
				'product_code'     => (string) ( $row->product_code ?? '' ),
				'warehouse_id'     => (int) $row->warehouse_id,
				'warehouse_name'   => (string) ( $row->warehouse_name ?? '' ),
				'current_quantity' => (float) $row->quantity,
				'unit_name'        => (string) ( $row->unit_symbol ?? '' ),
				'reorder_point'    => (float) ( $row->reorder_point ?? 0 ),
				'last_updated'     => (string) ( $row->updated_at ?? $row->created_at ?? '' ),
			);
		}

		return array(
			'success' => true,
			'data'    => $items,
			'total'   => $total,
		);
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function stock_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$warehouse_id = WebinoCRM_Service_Base::int_param( $params, 'warehouse_id' );
		$product_id   = WebinoCRM_Service_Base::int_param( $params, 'product_id' );
		if ( $warehouse_id <= 0 || $product_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'انبار و کالا الزامی است.', 'webinocrm' ) );
		}
		$row = WebinoCRM_Accounting_Warehouse_Stock::get( $warehouse_id, $product_id );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::success( array( 'quantity' => 0 ) );
		}
		return WebinoCRM_Service_Base::success( $row );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function inbound_list( array $params ) {
		return self::doc_list( 'inbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function inbound_get( array $params ) {
		return self::doc_get( 'inbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function inbound_create( array $params ) {
		return self::doc_create( 'inbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function inbound_post( array $params ) {
		return self::doc_post( 'inbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function outbound_list( array $params ) {
		return self::doc_list( 'outbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function outbound_get( array $params ) {
		return self::doc_get( 'outbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function outbound_create( array $params ) {
		return self::doc_create( 'outbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function outbound_post( array $params ) {
		return self::doc_post( 'outbound', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function audit_list( array $params ) {
		return self::doc_list( 'audit', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function audit_get( array $params ) {
		return self::doc_get( 'audit', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function audit_create( array $params ) {
		return self::doc_create( 'audit', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function audit_record( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$audit_id  = WebinoCRM_Service_Base::int_param( $params, 'audit_id' );
		$item_data = self::decode_json_param( $params, 'item_data' );
		if ( empty( $item_data ) && isset( $params['product_id'] ) ) {
			$item_data = $params;
		}
		$result = WebinoCRM_Accounting_Warehouse_Audit::record_item_count( $audit_id, $item_data );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'item_id' => (int) $result ) );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function audit_complete( array $params ) {
		return self::doc_complete( 'audit', $params );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function audit_post( array $params ) {
		return self::doc_post( 'audit', $params );
	}

	/**
	 * @param string              $type   inbound|outbound|audit.
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	private static function doc_list( $type, array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$config         = self::doc_config( $type );
		$warehouse_id   = WebinoCRM_Service_Base::int_param( $params, 'warehouse_id' );
		$search         = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'search', '' ) ) );
		$page           = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) );
		$per_page       = max( 1, min( 100, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 15 ) ) );
		$offset         = ( $page - 1 ) * $per_page;

		global $wpdb;
		$table  = $config['table'];
		$where  = array( '1=1' );
		$values = array();
		if ( $warehouse_id > 0 ) {
			$where[]  = 'warehouse_id = %d';
			$values[] = $warehouse_id;
		}
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = "( {$config['number_col']} LIKE %s OR description LIKE %s )";
			$values[] = $like;
			$values[] = $like;
		}
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		$total     = ! empty( $values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) )
			: (int) $wpdb->get_var( $count_sql );

		$list_sql = "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $values, array( $per_page, $offset ) ) ) );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::format_doc_row( $type, $row );
		}

		return array(
			'success' => true,
			'data'    => $items,
			'total'   => $total,
		);
	}

	/**
	 * @param string              $type   inbound|outbound|audit.
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	private static function doc_get( $type, array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه نامعتبر است.', 'webinocrm' ) );
		}

		$config = self::doc_config( $type );
		$doc    = call_user_func( array( $config['class'], 'get' ), $id );
		if ( ! $doc ) {
			return WebinoCRM_Service_Base::error( __( 'رکورد یافت نشد.', 'webinocrm' ) );
		}

		$formatted = self::format_doc_row( $type, $doc, true );
		if ( ! empty( $doc->items ) ) {
			$formatted['items'] = array_map(
				static function ( $item ) use ( $type ) {
					return self::format_doc_item( $type, $item );
				},
				$doc->items
			);
		}

		return WebinoCRM_Service_Base::success( $formatted );
	}

	/**
	 * @param string              $type   inbound|outbound|audit.
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	private static function doc_create( $type, array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$config       = self::doc_config( $type );
		$warehouse_id = WebinoCRM_Service_Base::int_param( $params, 'warehouse_id' );
		$data         = array(
			'warehouse_id' => $warehouse_id,
			'description'  => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'notes', WebinoCRM_Service_Base::param( $params, 'description', '' ) ) ) ),
			'created_by'   => get_current_user_id(),
		);
		$reference = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'reference_doc', '' ) ) );
		if ( $reference !== '' ) {
			$data['reference_type'] = 'adjustment';
			$data['description']    = trim( $reference . ( $data['description'] !== '' ? ' — ' . $data['description'] : '' ) );
		}

		$result = call_user_func( array( $config['class'], 'create' ), $data );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}

		$doc_id = (int) $result;
		$items  = self::decode_json_param( $params, 'items' );
		if ( ! empty( $items ) && in_array( $type, array( 'inbound', 'outbound' ), true ) ) {
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$qty_key = 'inbound' === $type ? 'quantity_received' : 'quantity_shipped';
				$qty     = isset( $item['quantity_ordered'] ) ? (float) $item['quantity_ordered'] : ( isset( $item[ $qty_key ] ) ? (float) $item[ $qty_key ] : 0 );
				if ( empty( $item['product_id'] ) || $qty <= 0 ) {
					continue;
				}
				$item_payload = array(
					'product_id' => (int) $item['product_id'],
					$qty_key     => $qty,
					'unit_price' => isset( $item['unit_price'] ) ? (float) $item['unit_price'] : null,
					'location'   => isset( $item['location'] ) ? sanitize_text_field( (string) $item['location'] ) : '',
				);
				call_user_func( array( $config['class'], 'add_item' ), $doc_id, $item_payload );
			}
		}

		return WebinoCRM_Service_Base::success( array( 'id' => $doc_id ) );
	}

	/**
	 * @param string              $type   inbound|outbound|audit.
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	private static function doc_post( $type, array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$config = self::doc_config( $type );
		$id     = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$result = call_user_func( array( $config['class'], 'post' ), $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success();
	}

	/**
	 * @param string              $type   audit only.
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	private static function doc_complete( $type, array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		self::ensure_warehouse_classes();
		$access = self::verify_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}

		$config = self::doc_config( $type );
		$id     = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$result = call_user_func( array( $config['class'], 'complete' ), $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success();
	}

	/**
	 * @param string $type Document type.
	 * @return array<string,mixed>
	 */
	private static function doc_config( $type ) {
		global $wpdb;
		switch ( $type ) {
			case 'outbound':
				return array(
					'class'      => 'WebinoCRM_Accounting_Warehouse_Outbound',
					'table'      => $wpdb->prefix . 'webinocrm_accounting_warehouse_outbound',
					'number_col' => 'outbound_no',
				);
			case 'audit':
				return array(
					'class'      => 'WebinoCRM_Accounting_Warehouse_Audit',
					'table'      => $wpdb->prefix . 'webinocrm_accounting_warehouse_audits',
					'number_col' => 'audit_no',
				);
			case 'inbound':
			default:
				return array(
					'class'      => 'WebinoCRM_Accounting_Warehouse_Inbound',
					'table'      => $wpdb->prefix . 'webinocrm_accounting_warehouse_inbound',
					'number_col' => 'inbound_no',
				);
		}
	}

	/**
	 * @param object $row Warehouse row.
	 * @return array<string,mixed>
	 */
	private static function normalize_warehouse( $row ) {
		return array(
			'id'          => (int) $row->id,
			'name'        => (string) $row->name,
			'code'        => (string) ( $row->code ?? '' ),
			'description' => (string) ( $row->description ?? '' ),
			'location'    => (string) ( $row->location ?? '' ),
			'is_default'  => (bool) $row->is_default,
			'is_active'   => (bool) $row->is_active,
			'created_at'  => (string) ( $row->created_at ?? '' ),
		);
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	private static function warehouse_payload( array $params ) {
		return array(
			'name'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'code'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'code', '' ) ) ),
			'description' => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', '' ) ) ),
			'location'    => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'location', '' ) ) ),
			'is_default'  => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_default', false ) ),
			'is_active'   => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_active', true ) ),
		);
	}

	/**
	 * @param string  $type     Document type.
	 * @param object  $row      DB row.
	 * @param bool    $detailed Include extra fields.
	 * @return array<string,mixed>
	 */
	private static function format_doc_row( $type, $row, $detailed = false ) {
		global $wpdb;
		$summary = self::doc_totals( $type, (int) $row->id );
		$number  = '';
		if ( 'outbound' === $type ) {
			$number = (string) $row->outbound_no;
		} elseif ( 'audit' === $type ) {
			$number = (string) ( $row->audit_no ?? $row->id );
		} else {
			$number = (string) $row->inbound_no;
		}

		$formatted = array(
			'id'             => (int) $row->id,
			'warehouse_id'   => (int) $row->warehouse_id,
			'inbound_no'     => $number,
			'outbound_no'    => $number,
			'audit_no'       => $number,
			'reference_doc'  => (string) ( $row->reference_type ?? '' ),
			'status'         => (string) $row->status,
			'created_date'   => (string) ( $row->created_at ?? $row->inbound_date ?? $row->outbound_date ?? '' ),
			'posted_date'    => (string) ( $row->posted_at ?? '' ),
			'notes'          => (string) ( $row->description ?? '' ),
			'total_quantity' => $summary['quantity'],
			'total_amount'   => $summary['amount'],
		);

		if ( $detailed ) {
			$formatted['description'] = (string) ( $row->description ?? '' );
		}

		return $formatted;
	}

	/**
	 * @param string $type Document type.
	 * @param object $item Item row.
	 * @return array<string,mixed>
	 */
	private static function format_doc_item( $type, $item ) {
		$qty = 'outbound' === $type
			? (float) ( $item->quantity_shipped ?? 0 )
			: (float) ( $item->quantity_received ?? 0 );

		return array(
			'id'                 => (int) $item->id,
			'product_id'         => (int) $item->product_id,
			'product_name'       => (string) ( $item->product_name ?? '' ),
			'quantity_ordered'   => (float) ( $item->quantity_ordered ?? $qty ),
			'quantity_received'  => (float) ( $item->quantity_received ?? $qty ),
			'quantity_shipped'   => (float) ( $item->quantity_shipped ?? $qty ),
			'unit_name'          => (string) ( $item->unit_symbol ?? '' ),
			'unit_price'         => (float) ( $item->unit_price ?? 0 ),
			'location'           => (string) ( $item->location ?? '' ),
		);
	}

	/**
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return array{quantity:float,amount:float}
	 */
	private static function doc_totals( $type, $id ) {
		global $wpdb;
		if ( 'outbound' === $type ) {
			$table = $wpdb->prefix . 'webinocrm_accounting_warehouse_outbound_items';
			$qty   = 'quantity_shipped';
			$fk    = 'outbound_id';
		} elseif ( 'audit' === $type ) {
			return array( 'quantity' => 0.0, 'amount' => 0.0 );
		} else {
			$table = $wpdb->prefix . 'webinocrm_accounting_warehouse_inbound_items';
			$qty   = 'quantity_received';
			$fk    = 'inbound_id';
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM($qty) AS quantity, SUM($qty * COALESCE(unit_price, 0)) AS amount FROM $table WHERE {$fk} = %d",
				$id
			)
		);
		return array(
			'quantity' => (float) ( $row->quantity ?? 0 ),
			'amount'   => (float) ( $row->amount ?? 0 ),
		);
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @param string              $key    Param key.
	 * @return array<int,mixed>
	 */
	private static function decode_json_param( array $params, $key ) {
		$raw = WebinoCRM_Service_Base::param( $params, $key, null );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( wp_unslash( $raw ), true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
