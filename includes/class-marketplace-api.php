<?php
/**
 * Marketplace REST API for customer dashboards.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes under webinocrm/v1/marketplace/*
 */
class WebinoCRM_Marketplace_API {

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

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/catalog/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array( 'domain' => $domain_args ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/catalog/modules',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_modules' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'domain'   => $domain_args,
					'category' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/catalog/modules/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_module_detail' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'domain' => $domain_args,
					'slug'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/entitlements',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_entitlements' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array( 'domain' => $domain_args ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/purchase/init',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'purchase_init' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/purchase/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'purchase_verify' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/download-token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'download_token' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/entitlements/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'revoke_entitlement' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/core/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'core_check' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'domain'          => $domain_args,
					'current_version' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/core/download-token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'core_download_token' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/marketplace/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download_package' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function permission_check( $request ) {
		$domain = $request->get_param( 'domain' );
		if ( ! $domain && 'POST' === $request->get_method() ) {
			$body   = $request->get_json_params();
			$domain = is_array( $body ) ? ( $body['domain'] ?? '' ) : '';
		}
		$licensed = WebinoCRM_Marketplace_Manager::assert_licensed_domain( (string) $domain );
		if ( is_wp_error( $licensed ) ) {
			return $licensed;
		}
		if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'marketplace_api_' . $request->get_route(), 120, 60 ) ) {
			return new WP_Error( 'webinocrm_rate_limit', __( 'Too many requests.', 'webinocrm' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * @param WP_Error $error Token error.
	 * @return WP_REST_Response
	 */
	private function download_token_error_response( WP_Error $error ) {
		$payload  = array(
			'ok'      => false,
			'message' => $error->get_error_message(),
		);
		$err_data = $error->get_error_data();
		if ( is_array( $err_data ) && ! empty( $err_data['debug'] ) && is_array( $err_data['debug'] ) ) {
			$payload['debug'] = $err_data['debug'];
		}
		$status = is_array( $err_data ) && isset( $err_data['status'] ) ? (int) $err_data['status'] : 400;
		return new WP_REST_Response( $payload, $status );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_categories( $request ) {
		$categories = WebinoCRM_Marketplace_Manager::get_categories( true );
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'categories' => $categories,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_modules( $request ) {
		$category = (string) $request->get_param( 'category' );
		$domain   = WebinoCRM_License_Manager::normalize_domain( (string) $request->get_param( 'domain' ) );
		$modules  = WebinoCRM_Marketplace_Manager::get_modules( $category, true );
		$modules  = WebinoCRM_Marketplace_Manager::filter_installable_catalog_modules( $modules );
		$owned    = array();
		foreach ( WebinoCRM_Marketplace_Manager::get_entitlements_for_domain( $domain ) as $ent ) {
			$owned[ (string) $ent['module_slug'] ] = true;
		}
		foreach ( $modules as &$mod ) {
			$mod['owned'] = ! empty( $owned[ $mod['slug'] ] ) || ! empty( $mod['is_free'] );
		}
		unset( $mod );
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'modules' => $modules,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_module_detail( $request ) {
		$slug   = sanitize_key( (string) $request['slug'] );
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) $request->get_param( 'domain' ) );
		$module = WebinoCRM_Marketplace_Manager::get_module_catalog_by_slug( $slug );
		if ( ! $module ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => __( 'Module not found.', 'webinocrm' ),
				),
				404
			);
		}
		if ( ! empty( $module['is_core'] ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => __( 'Module not found.', 'webinocrm' ),
				),
				404
			);
		}
		if ( 'active' !== (string) ( $module['status'] ?? '' ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => __( 'Module is not available.', 'webinocrm' ),
				),
				404
			);
		}
		if ( empty( $module['package_available'] ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => __( 'Install package is not available.', 'webinocrm' ),
				),
				404
			);
		}
		$owned = WebinoCRM_Marketplace_Manager::domain_has_entitlement( $domain, (int) $module['id'] );
		$module['owned'] = $owned || ! empty( $module['is_free'] );
		return new WP_REST_Response(
			array(
				'ok'     => true,
				'module' => $module,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_entitlements( $request ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) $request->get_param( 'domain' ) );
		$ents   = WebinoCRM_Marketplace_Manager::get_entitlements_for_domain( $domain );
		$out    = array();
		foreach ( $ents as $ent ) {
			$out[] = array(
				'module_id'   => (int) $ent['module_id'],
				'module_slug' => (string) $ent['module_slug'],
				'module_name' => (string) $ent['module_name'],
				'status'      => (string) $ent['status'],
				'version'     => (string) ( $ent['installed_version'] ?? '' ),
			);
		}
		return new WP_REST_Response(
			array(
				'ok'           => true,
				'entitlements' => $out,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function purchase_init( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$slug   = sanitize_key( (string) ( $body['module_slug'] ?? '' ) );
		$module = WebinoCRM_Marketplace_Manager::get_module_by_slug( $slug );
		if ( ! $module || 'active' !== ( $module['status'] ?? '' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Module not found.', 'webinocrm' ) ), 404 );
		}
		if ( ! empty( $module['is_free'] ) ) {
			WebinoCRM_Marketplace_Manager::grant_entitlement( $domain, (int) $module['id'], null, (string) ( $module['version'] ?? '' ) );
			return new WP_REST_Response(
				array(
					'ok'       => true,
					'is_free'  => true,
					'owned'    => true,
				),
				200
			);
		}
		if ( WebinoCRM_Marketplace_Manager::domain_has_entitlement( $domain, (int) $module['id'] ) ) {
			return new WP_REST_Response(
				array(
					'ok'    => true,
					'owned' => true,
				),
				200
			);
		}
		$amount = (float) ( $module['price'] ?? 0 );
		if ( $amount <= 0 ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Invalid module price.', 'webinocrm' ) ), 400 );
		}
		$order_id = WebinoCRM_Marketplace_Manager::create_order( $domain, (int) $module['id'], $amount );
		if ( ! $order_id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Could not create order.', 'webinocrm' ) ), 500 );
		}
		$merchant = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_merchant', '' );
		if ( empty( $merchant ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Payment gateway is not configured.', 'webinocrm' ) ), 500 );
		}
		$sandbox  = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_sandbox', '0' );
		$callback = (string) ( $body['callback_url'] ?? '' );
		if ( '' === $callback ) {
			$callback = add_query_arg(
				array(
					'domain'      => rawurlencode( $domain ),
					'module_slug' => rawurlencode( $slug ),
					'order_id'    => (string) $order_id,
				),
				'https://' . $domain . '/dashboard/marketplace/payment-callback'
			);
		}
		$zarinpal = new WebinoCRM_Zarinpal_Handler( $merchant, '1' === $sandbox );
		$result   = $zarinpal->request_payment(
			$amount,
			sprintf(
				/* translators: %s: module name */
				__( 'Marketplace module: %s', 'webinocrm' ),
				(string) $module['name']
			),
			$callback
		);
		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $result['message'] ?? __( 'Payment request failed.', 'webinocrm' ),
				),
				500
			);
		}
		WebinoCRM_Marketplace_Manager::set_order_authority( $order_id, (string) $result['authority'] );
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
	public function purchase_verify( $request ) {
		$body      = $request->get_json_params();
		$domain    = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$slug      = sanitize_key( (string) ( $body['module_slug'] ?? '' ) );
		$authority = sanitize_text_field( (string) ( $body['authority'] ?? '' ) );
		$order_id  = isset( $body['order_id'] ) ? (int) $body['order_id'] : 0;
		$status    = sanitize_text_field( (string) ( $body['status'] ?? '' ) );
		if ( 'OK' !== strtoupper( $status ) && 'NOK' === strtoupper( $status ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Payment cancelled.', 'webinocrm' ) ), 200 );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_slug( $slug );
		if ( ! $module ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Module not found.', 'webinocrm' ) ), 404 );
		}
		global $wpdb;
		$orders_table = WebinoCRM_Marketplace_Manager::orders_table();
		if ( $order_id ) {
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $orders_table WHERE id = %d AND domain = %s", $order_id, $domain ), ARRAY_A );
		} else {
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $orders_table WHERE authority = %s AND domain = %s ORDER BY id DESC LIMIT 1",
					$authority,
					$domain
				),
				ARRAY_A
			);
		}
		if ( ! $order ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Order not found.', 'webinocrm' ) ), 404 );
		}
		if ( WebinoCRM_Marketplace_Manager::ORDER_PAID === ( $order['status'] ?? '' ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'owned' => true ), 200 );
		}
		$merchant = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_merchant', '' );
		$sandbox  = WebinoCRM_Settings_Handler::get_setting( 'zarinpal_sandbox', '0' );
		$zarinpal = new WebinoCRM_Zarinpal_Handler( $merchant, '1' === $sandbox );
		$verify   = $zarinpal->verify_payment( $authority ?: (string) $order['authority'], (float) $order['amount'] );
		if ( empty( $verify['success'] ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $verify['message'] ?? __( 'Payment verification failed.', 'webinocrm' ),
				),
				200
			);
		}
		WebinoCRM_Marketplace_Manager::complete_order( (int) $order['id'], (string) ( $verify['ref_id'] ?? '' ) );
		return new WP_REST_Response(
			array(
				'ok'    => true,
				'owned' => true,
				'ref_id' => (string) ( $verify['ref_id'] ?? '' ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function revoke_entitlement( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( is_array( $body ) ? ( $body['domain'] ?? '' ) : '' ) );
		$slug   = sanitize_key( (string) ( is_array( $body ) ? ( $body['module_slug'] ?? '' ) : '' ) );
		if ( '' === $slug ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => __( 'Module slug is required.', 'webinocrm' ),
				),
				400
			);
		}
		$result = WebinoCRM_Marketplace_Manager::revoke_entitlement( $domain, $slug );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $result->get_error_message(),
				),
				$result->get_error_data()['status'] ?? 400
			);
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function core_check( $request ) {
		$current = (string) $request->get_param( 'current_version' );
		$check   = WebinoCRM_Marketplace_Manager::get_core_update_check( $current );
		if ( is_wp_error( $check ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $check->get_error_message(),
				),
				$check->get_error_data()['status'] ?? 503
			);
		}
		return new WP_REST_Response(
			array_merge(
				array( 'ok' => true ),
				$check
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function core_download_token( $request ) {
		$body    = $request->get_json_params();
		$domain  = WebinoCRM_License_Manager::normalize_domain( (string) ( is_array( $body ) ? ( $body['domain'] ?? '' ) : '' ) );
		$version = is_array( $body ) ? sanitize_text_field( (string) ( $body['version'] ?? '' ) ) : '';
		$token   = WebinoCRM_Marketplace_Manager::create_download_token(
			$domain,
			WebinoCRM_Marketplace_Manager::CORE_MODULE_SLUG,
			$version
		);
		if ( is_wp_error( $token ) ) {
			return $this->download_token_error_response( $token );
		}
		return new WP_REST_Response(
			array(
				'ok'           => true,
				'token'        => $token,
				'download_url' => add_query_arg( 'token', rawurlencode( $token ), rest_url( 'webinocrm/v1/marketplace/download' ) ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function download_token( $request ) {
		$body    = $request->get_json_params();
		$domain  = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$slug    = sanitize_key( (string) ( $body['module_slug'] ?? '' ) );
		$version = sanitize_text_field( (string) ( $body['version'] ?? '' ) );
		$token   = WebinoCRM_Marketplace_Manager::create_download_token( $domain, $slug, $version );
		if ( is_wp_error( $token ) ) {
			return $this->download_token_error_response( $token );
		}
		$base = home_url( '/' );
		return new WP_REST_Response(
			array(
				'ok'           => true,
				'token'        => $token,
				'download_url' => add_query_arg( 'token', rawurlencode( $token ), rest_url( 'webinocrm/v1/marketplace/download' ) ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return void|WP_REST_Response
	 */
	public function download_package( $request ) {
		$token = (string) $request->get_param( 'token' );
		$data  = WebinoCRM_Marketplace_Manager::consume_download_token( $token );
		if ( ! $data || empty( $data['path'] ) || ! is_readable( $data['path'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Invalid or expired token.', 'webinocrm' ) ), 403 );
		}
		$path = (string) $data['path'];
		$name = basename( $path );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}
}
