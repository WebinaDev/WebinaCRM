<?php
/**
 * ModirPayamak customer REST API (Dashboard sites).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes under webinocrm/v1/modirpayamak/*
 */
class WebinoCRM_ModirPayamak_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public function register_routes() {
		$domain_args = array(
			'required'          => true,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		$routes = array(
			array( 'GET', '/modirpayamak/account', 'get_account' ),
			array( 'GET', '/modirpayamak/packages', 'get_packages' ),
			array( 'POST', '/modirpayamak/topup/init', 'topup_init' ),
			array( 'POST', '/modirpayamak/topup/verify', 'topup_verify' ),
			array( 'POST', '/modirpayamak/send', 'send_message' ),
			array( 'POST', '/modirpayamak/send/peer-to-peer', 'send_peer_to_peer' ),
			array( 'POST', '/modirpayamak/send/calculate-price', 'calculate_price' ),
			array( 'GET', '/modirpayamak/reports/outbox', 'reports_outbox' ),
			array( 'GET', '/modirpayamak/reports/messages', 'reports_messages' ),
			array( 'GET', '/modirpayamak/patterns', 'list_patterns' ),
			array( 'GET', '/modirpayamak/numbers', 'list_numbers' ),
			array( 'GET', '/modirpayamak/phonebooks', 'list_phonebooks' ),
			array( 'POST', '/modirpayamak/phonebooks', 'create_phonebook' ),
			array( 'GET', '/modirpayamak/phonebooks/(?P<id>\d+)/contacts', 'list_contacts' ),
			array( 'POST', '/modirpayamak/phonebooks/(?P<id>\d+)/contacts', 'create_contact' ),
		);

		foreach ( $routes as $r ) {
			register_rest_route(
				'webinocrm/v1',
				$r[1],
				array(
					'methods'             => $r[0],
					'callback'            => array( $this, $r[2] ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => 'GET' === $r[0] ? array( 'domain' => $domain_args ) : array(),
				)
			);
		}

		register_rest_route(
			'webinocrm/v1',
			'/modirpayamak/reports/outbox/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'report_outbox_detail' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array( 'domain' => $domain_args ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function permission_check( $request ) {
		$domain = $request->get_param( 'domain' );
		if ( ! $domain && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$body   = $request->get_json_params();
			$domain = is_array( $body ) ? ( $body['domain'] ?? '' ) : '';
		}
		$licensed = WebinoCRM_ModirPayamak_Manager::assert_licensed_domain( (string) $domain );
		if ( is_wp_error( $licensed ) ) {
			return $licensed;
		}
		if ( ! WebinoCRM_ModirPayamak_Edge_Client::is_configured() ) {
			return new WP_Error( 'not_configured', __( 'ModirPayamak is not enabled on CRM.', 'webinocrm' ), array( 'status' => 503 ) );
		}
		if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'modirpayamak_api_' . $request->get_route(), 120, 60 ) ) {
			return new WP_Error( 'webinocrm_rate_limit', __( 'Too many requests.', 'webinocrm' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function domain_from_request( $request ) {
		$domain = $request->get_param( 'domain' );
		if ( ! $domain ) {
			$body = $request->get_json_params();
			if ( is_array( $body ) && ! empty( $body['domain'] ) ) {
				$domain = $body['domain'];
			}
		}
		return WebinoCRM_License_Manager::normalize_domain( (string) $domain );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_account( $request ) {
		$domain  = $this->domain_from_request( $request );
		$account = WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain );
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'account' => WebinoCRM_ModirPayamak_Manager::format_account_public( $account ),
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public function get_packages() {
		$packages = WebinoCRM_ModirPayamak_Manager::get_active_packages();
		return new WP_REST_Response( array( 'ok' => true, 'packages' => $packages ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function topup_init( $request ) {
		$body       = $request->get_json_params();
		$domain     = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$package_id = isset( $body['package_id'] ) ? (int) $body['package_id'] : 0;
		global $wpdb;
		$package = $package_id ? $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . WebinoCRM_ModirPayamak_Manager::table( 'packages' ) . ' WHERE id = %d AND status = %s', $package_id, 'active' ),
			ARRAY_A
		) : null;
		if ( ! $package ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Package not found.', 'webinocrm' ) ), 404 );
		}
		$amount        = (float) $package['amount'];
		$credit_amount = $amount + (float) ( $package['bonus'] ?? 0 );
		$order_id      = $wpdb->insert(
			WebinoCRM_ModirPayamak_Manager::table( 'orders' ),
			array(
				'domain'         => $domain,
				'package_id'     => $package_id,
				'amount'         => $amount,
				'credit_amount'  => $credit_amount,
				'status'         => WebinoCRM_ModirPayamak_Manager::ORDER_PENDING,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			)
		);
		if ( ! $order_id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Could not create order.', 'webinocrm' ) ), 500 );
		}
		$order_id = (int) $wpdb->insert_id;
		$merchant = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_merchant', '' );
		if ( empty( $merchant ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Payment gateway is not configured.', 'webinocrm' ) ), 500 );
		}
		$sandbox  = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_sandbox', '0' );
		$callback = (string) ( $body['callback_url'] ?? '' );
		if ( '' === $callback ) {
			$callback = add_query_arg(
				array( 'order_id' => (string) $order_id ),
				'https://' . $domain . '/dashboard/marketing/sms/payment-callback'
			);
		}
		$zarinpal = new WebinoCRM_Zarinpal_Handler( $merchant, '1' === $sandbox );
		$result   = $zarinpal->request_payment(
			$amount,
			sprintf( __( 'SMS credit: %s', 'webinocrm' ), (string) $package['name'] ),
			$callback
		);
		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => $result['message'] ?? __( 'Payment request failed.', 'webinocrm' ) ),
				500
			);
		}
		$wpdb->update(
			WebinoCRM_ModirPayamak_Manager::table( 'orders' ),
			array( 'authority' => (string) $result['authority'], 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $order_id )
		);
		return new WP_REST_Response(
			array(
				'ok'          => true,
				'payment_url' => (string) $result['payment_url'],
				'authority'   => (string) $result['authority'],
				'order_id'    => $order_id,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function topup_verify( $request ) {
		$body      = $request->get_json_params();
		$domain    = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$authority = sanitize_text_field( (string) ( $body['authority'] ?? '' ) );
		$order_id  = isset( $body['order_id'] ) ? (int) $body['order_id'] : 0;
		$status    = sanitize_text_field( (string) ( $body['status'] ?? '' ) );
		if ( 'OK' !== strtoupper( $status ) && 'NOK' === strtoupper( $status ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Payment cancelled.', 'webinocrm' ) ), 200 );
		}
		global $wpdb;
		$orders_table = WebinoCRM_ModirPayamak_Manager::table( 'orders' );
		if ( $order_id ) {
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $orders_table WHERE id = %d AND domain = %s", $order_id, $domain ), ARRAY_A );
		} else {
			$order = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM $orders_table WHERE authority = %s AND domain = %s ORDER BY id DESC LIMIT 1", $authority, $domain ),
				ARRAY_A
			);
		}
		if ( ! $order ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Order not found.', 'webinocrm' ) ), 404 );
		}
		if ( WebinoCRM_ModirPayamak_Manager::ORDER_PAID === ( $order['status'] ?? '' ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'credited' => true ), 200 );
		}
		$merchant = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_merchant', '' );
		$sandbox  = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_sandbox', '0' );
		$zarinpal = new WebinoCRM_Zarinpal_Handler( $merchant, '1' === $sandbox );
		$verify   = $zarinpal->verify_payment( $authority ?: (string) $order['authority'], (float) $order['amount'] );
		if ( empty( $verify['success'] ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => $verify['message'] ?? __( 'Verification failed.', 'webinocrm' ) ),
				200
			);
		}
		$wpdb->update(
			$orders_table,
			array(
				'status'     => WebinoCRM_ModirPayamak_Manager::ORDER_PAID,
				'ref_id'     => (string) ( $verify['ref_id'] ?? '' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $order['id'] )
		);
		WebinoCRM_ModirPayamak_Manager::topup( $domain, (float) $order['credit_amount'], (int) $order['id'] );
		return new WP_REST_Response( array( 'ok' => true, 'credited' => true ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_message( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		unset( $body['domain'] );

		if ( empty( $body['sending_type'] ) && ! empty( $body['phone'] ) && ! empty( $body['message'] ) ) {
			$phone       = self::normalize_phone_e164( (string) $body['phone'] );
			$from_number = ! empty( $body['from_number'] ) ? (string) $body['from_number'] : '';
			$body        = array(
				'sending_type' => 'webservice',
				'message'      => (string) $body['message'],
				'params'       => array( 'recipients' => array( $phone ) ),
			);
			if ( '' !== $from_number ) {
				$body['from_number'] = $from_number;
			}
		}

		if ( empty( $body['sending_type'] ) ) {
			$body['sending_type'] = 'webservice';
		}

		$result = WebinoCRM_ModirPayamak_Manager::customer_send( $domain, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array_merge( array( 'ok' => true ), $result ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_peer_to_peer( $request ) {
		$body = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		unset( $body['domain'] );
		$body['sending_type'] = 'peer_to_peer';
		$result = WebinoCRM_ModirPayamak_Manager::customer_send( $domain, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array_merge( array( 'ok' => true ), $result ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function calculate_price( $request ) {
		$body   = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		unset( $body['domain'] );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::calculate_price( $body );
		$quote = WebinoCRM_ModirPayamak_Manager::quote_payload( $body );
		return new WP_REST_Response(
			array(
				'ok'             => ! empty( $edge['ok'] ) || true,
				'edge'           => $edge['data'] ?? null,
				'customer_cost'  => (float) $quote['cost_toman'],
				'cost_rial'      => (float) $quote['cost_rial'],
				'parts'          => (int) $quote['parts'],
				'line_type'      => (string) $quote['line_type'],
				'encoding'       => (string) $quote['encoding'],
				'quote'          => $quote,
				'price_per_unit' => WebinoCRM_ModirPayamak_Manager::price_per_unit(),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reports_outbox( $request ) {
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$limit = min( 50, max( 1, (int) $request->get_param( 'limit' ) ) );
		$edge  = WebinoCRM_ModirPayamak_Edge_Client::report_outbox( $page, $limit );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reports_messages( $request ) {
		$domain = $this->domain_from_request( $request );
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$limit  = min( 50, max( 1, (int) $request->get_param( 'limit' ) ) );
		$rows   = WebinoCRM_ModirPayamak_Manager::get_domain_messages( $domain, $page, $limit );
		return new WP_REST_Response( array( 'ok' => true, 'messages' => $rows ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function report_outbox_detail( $request ) {
		$id   = (string) $request['id'];
		$edge = WebinoCRM_ModirPayamak_Edge_Client::report_outbox_by_id( $id );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_patterns( $request ) {
		$domain = $this->domain_from_request( $request );
		$owned  = WebinoCRM_Sms_Pattern_Sync_Service::list_domain_pattern_codes( $domain );
		$owned_set = array_fill_keys( $owned, true );

		// Prefer fetching owned codes individually so sites never see the full reseller pool.
		$items = array();
		foreach ( $owned as $code ) {
			$edge = WebinoCRM_ModirPayamak_Edge_Client::get_pattern( $code );
			if ( ! empty( $edge['ok'] ) && is_array( $edge['data'] ?? null ) ) {
				$row = $edge['data'];
				if ( empty( $row['pattern_code'] ) && empty( $row['code'] ) ) {
					$row['pattern_code'] = $code;
					$row['code']         = $code;
				}
				$items[] = $row;
			} else {
				$items[] = array(
					'pattern_code'    => $code,
					'code'            => $code,
					'pattern_message' => '',
					'sync_local_only' => true,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'ok'   => true,
				'data' => array(
					'data'  => $items,
					'items' => $items,
				),
				'meta' => array(
					'total'  => count( $items ),
					'domain' => $domain,
					'owned'  => array_keys( $owned_set ),
				),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_numbers( $request ) {
		$domain  = $this->domain_from_request( $request );
		$numbers = WebinoCRM_ModirPayamak_Manager::get_domain_numbers( $domain );
		$data    = array_map(
			static function ( $row ) {
				$role = (string) ( $row['role'] ?? '' );
				if ( 'marketing' === $role ) {
					$role = 'personal';
				}
				return array(
					'id'         => (int) ( $row['id'] ?? 0 ),
					'number'     => (string) ( $row['number'] ?? '' ),
					'role'       => $role,
					'label'      => (string) ( $row['label'] ?? '' ),
					'type'       => $role,
					'is_default' => ! empty( $row['is_default'] ),
				);
			},
			$numbers
		);
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'data'    => $data,
				'numbers' => $data,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_phonebooks( $request ) {
		global $wpdb;
		$domain = $this->domain_from_request( $request );
		$table  = WebinoCRM_ModirPayamak_Manager::table( 'phonebooks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE domain = %s ORDER BY name ASC", $domain ), ARRAY_A );
		return new WP_REST_Response( array( 'ok' => true, 'phonebooks' => $rows ?: array() ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_phonebook( $request ) {
		global $wpdb;
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$name   = sanitize_text_field( (string) ( $body['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Name is required.', 'webinocrm' ) ), 400 );
		}
		$wpdb->insert(
			WebinoCRM_ModirPayamak_Manager::table( 'phonebooks' ),
			array(
				'domain'     => $domain,
				'name'       => $name,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
		return new WP_REST_Response( array( 'ok' => true, 'id' => (int) $wpdb->insert_id ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_contacts( $request ) {
		global $wpdb;
		$pb_id = (int) $request['id'];
		$table = WebinoCRM_ModirPayamak_Manager::table( 'contacts' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE phonebook_id = %d ORDER BY id DESC", $pb_id ), ARRAY_A );
		return new WP_REST_Response( array( 'ok' => true, 'contacts' => $rows ?: array() ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_contact( $request ) {
		global $wpdb;
		$body   = $request->get_json_params();
		$pb_id  = (int) $request['id'];
		$phone  = self::normalize_phone_e164( (string) ( $body['phone'] ?? '' ) );
		$name   = sanitize_text_field( (string) ( $body['name'] ?? '' ) );
		$email  = sanitize_email( (string) ( $body['email'] ?? '' ) );
		if ( '' === $phone ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Phone is required.', 'webinocrm' ) ), 400 );
		}
		$wpdb->insert(
			WebinoCRM_ModirPayamak_Manager::table( 'contacts' ),
			array(
				'phonebook_id' => $pb_id,
				'name'         => $name,
				'phone'        => $phone,
				'email'        => $email,
				'created_at'   => current_time( 'mysql' ),
			)
		);
		return new WP_REST_Response( array( 'ok' => true, 'id' => (int) $wpdb->insert_id ), 200 );
	}

	/**
	 * @param string $phone Phone.
	 * @return string
	 */
	private static function normalize_phone_e164( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( str_starts_with( $digits, '98' ) ) {
			return '+' . $digits;
		}
		if ( str_starts_with( $digits, '0' ) ) {
			return '+98' . substr( $digits, 1 );
		}
		if ( str_starts_with( $digits, '9' ) && 10 === strlen( $digits ) ) {
			return '+98' . $digits;
		}
		return '+' . $digits;
	}
}
