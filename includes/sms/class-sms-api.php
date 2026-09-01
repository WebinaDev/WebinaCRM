<?php
/**
 * SMS module REST API (settings, templates, orders, auth, newsletter, Edge extras).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes under webinocrm/v1/modirpayamak/*
 */
class WebinoCRM_Sms_API {

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
			array( 'GET', '/modirpayamak/settings/site', 'get_site_settings' ),
			array( 'PUT', '/modirpayamak/settings/site', 'put_site_settings' ),
			array( 'POST', '/modirpayamak/settings/site', 'put_site_settings' ),
			array( 'GET', '/modirpayamak/settings/shop', 'get_shop_settings' ),
			array( 'PUT', '/modirpayamak/settings/shop', 'put_shop_settings' ),
			array( 'POST', '/modirpayamak/settings/shop', 'put_shop_settings' ),
			array( 'GET', '/modirpayamak/templates', 'get_templates' ),
			array( 'PUT', '/modirpayamak/templates', 'put_templates' ),
			array( 'POST', '/modirpayamak/templates', 'put_templates' ),
			array( 'GET', '/modirpayamak/templates/shortcodes', 'get_shortcodes' ),
			array( 'POST', '/modirpayamak/patterns/sync', 'sync_pattern' ),
			array( 'POST', '/modirpayamak/patterns/detach', 'detach_pattern' ),
			array( 'GET', '/modirpayamak/patterns/registry', 'get_pattern_registry' ),
			array( 'POST', '/modirpayamak/patterns', 'create_pattern' ),
			array( 'POST', '/modirpayamak/orders/notify', 'order_notify' ),
			array( 'GET', '/modirpayamak/orders/messages', 'order_messages' ),
			array( 'POST', '/modirpayamak/orders/test-notify', 'order_test_notify' ),
			array( 'POST', '/modirpayamak/auth/send-otp', 'auth_send_otp' ),
			array( 'POST', '/modirpayamak/auth/verify-otp', 'auth_verify_otp' ),
			array( 'POST', '/modirpayamak/newsletter/subscribe', 'newsletter_subscribe' ),
			array( 'GET', '/modirpayamak/newsletter/subscribers', 'newsletter_subscribers' ),
			array( 'DELETE', '/modirpayamak/newsletter/subscribers', 'newsletter_unsubscribe' ),
			array( 'POST', '/modirpayamak/newsletter/unsubscribe', 'newsletter_unsubscribe' ),
			array( 'POST', '/modirpayamak/newsletter/send', 'newsletter_send' ),
			array( 'GET', '/modirpayamak/reports/inbox', 'reports_inbox' ),
			array( 'GET', '/modirpayamak/reports/bulk-stats', 'reports_bulk_stats' ),
			array( 'GET', '/modirpayamak/reports/bulk-recipients', 'reports_bulk_recipients' ),
			array( 'POST', '/modirpayamak/send/cancel-scheduled', 'cancel_scheduled' ),
			array( 'GET', '/modirpayamak/drafts', 'list_drafts' ),
			array( 'POST', '/modirpayamak/drafts', 'create_draft' ),
			array( 'DELETE', '/modirpayamak/drafts', 'delete_draft' ),
			array( 'POST', '/modirpayamak/drafts/delete', 'delete_draft' ),
			array( 'GET', '/modirpayamak/tickets', 'list_tickets' ),
			array( 'GET', '/modirpayamak/phonebooks/edge', 'list_edge_phonebooks' ),
			array( 'POST', '/modirpayamak/phonebooks/edge', 'create_edge_phonebook' ),
			array( 'GET', '/modirpayamak/ledger', 'get_ledger' ),
			array( 'GET', '/modirpayamak/secretaries', 'list_secretaries' ),
			array( 'POST', '/modirpayamak/secretaries', 'save_secretary' ),
			array( 'DELETE', '/modirpayamak/secretaries', 'delete_secretary' ),
			array( 'POST', '/modirpayamak/secretaries/delete', 'delete_secretary' ),
			array( 'POST', '/modirpayamak/secretaries/process', 'process_secretaries' ),
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
			'/modirpayamak/patterns/(?P<code>[a-zA-Z0-9_%-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_pattern' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_pattern' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_pattern' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_pattern' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/modirpayamak/phonebooks/edge/(?P<id>\d+)/numbers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_edge_phonebook_numbers' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'store_edge_phonebook_number' ),
					'permission_callback' => array( $this, 'permission_check' ),
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
		if ( ! $domain && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
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
		if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'modirpayamak_sms_' . $request->get_route(), 120, 60 ) ) {
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
	public function get_site_settings( $request ) {
		$domain = $this->domain_from_request( $request );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'settings' => WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SITE ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function put_site_settings( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$input  = is_array( $body['settings'] ?? null ) ? $body['settings'] : $body;
		unset( $input['domain'] );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'settings' => WebinoCRM_Sms_Settings_Service::save( $domain, WebinoCRM_Sms_Constants::SCOPE_SITE, $input ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_shop_settings( $request ) {
		$domain   = $this->domain_from_request( $request );
		$settings = WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP );
		$keys     = WebinoCRM_Sms_Constants::resolve_event_keys( $settings );
		WebinoCRM_Sms_Template_Service::seed_order_defaults( $domain, $keys );
		return new WP_REST_Response(
			array(
				'ok'            => true,
				'settings'      => $settings,
				'event_keys'    => $keys,
				'event_catalog' => is_array( $settings['event_catalog'] ?? null ) ? $settings['event_catalog'] : array(),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function put_shop_settings( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$input  = is_array( $body['settings'] ?? null ) ? $body['settings'] : $body;
		unset( $input['domain'] );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'settings' => WebinoCRM_Sms_Settings_Service::save( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP, $input ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_templates( $request ) {
		$domain = $this->domain_from_request( $request );
		WebinoCRM_Sms_Template_Service::seed_order_defaults( $domain );
		return new WP_REST_Response(
			array(
				'ok'        => true,
				'templates' => WebinoCRM_Sms_Template_Service::list(
					$domain,
					sanitize_key( (string) $request->get_param( 'scope' ) ),
					sanitize_key( (string) $request->get_param( 'event_key' ) )
				),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function put_templates( $request ) {
		$body       = $request->get_json_params();
		$domain     = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$templates  = is_array( $body['templates'] ?? null ) ? $body['templates'] : array();
		$saved      = WebinoCRM_Sms_Template_Service::save_bulk( $domain, $templates );
		return new WP_REST_Response( array( 'ok' => true, 'templates' => $saved ), 200 );
	}

	/**
	 * @return WP_REST_Response
	 */
	public function get_shortcodes() {
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'shortcodes' => WebinoCRM_Sms_Constants::shortcodes(),
				'event_keys' => WebinoCRM_Sms_Constants::order_event_keys(),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_pattern( $request ) {
		$body      = $request->get_json_params();
		$domain    = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$scope     = sanitize_key( (string) ( $body['scope'] ?? '' ) );
		$event_key = sanitize_key( (string) ( $body['event_key'] ?? '' ) );
		$bind_code = sanitize_text_field( (string) ( $body['pattern_code'] ?? $body['ippanel_code'] ?? '' ) );
		$param_map = WebinoCRM_Sms_Template_Service::decode_param_map( $body['param_map'] ?? array() );
		if ( '' !== $bind_code && ! empty( $body['bind_only'] ) ) {
			$result = WebinoCRM_Sms_Pattern_Sync_Service::bind_existing( $domain, $scope, $event_key, $bind_code, $param_map );
		} else {
			if ( '' !== $bind_code || $param_map ) {
				$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
				$body_tpl = $tpl ? (string) $tpl['body'] : '';
				if ( $param_map && '' !== $bind_code ) {
					$body_tpl = WebinoCRM_Sms_Template_Service::format_param_map_preview( $bind_code, $param_map );
				}
				if ( $tpl || $body_tpl ) {
					WebinoCRM_Sms_Template_Service::upsert(
						$domain,
						$scope,
						$event_key,
						$body_tpl ?: ( $tpl ? (string) $tpl['body'] : '' ),
						$tpl ? ! empty( $tpl['enabled'] ) : true,
						$bind_code !== '' ? $bind_code : ( $tpl['pattern_code'] ?? null ),
						$param_map ?: null
					);
				}
			}
			$result = WebinoCRM_Sms_Pattern_Sync_Service::sync_one( $domain, $scope, $event_key );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function detach_pattern( $request ) {
		$body      = $request->get_json_params();
		$domain    = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? $this->domain_from_request( $request ) ) );
		$scope     = sanitize_key( (string) ( $body['scope'] ?? '' ) );
		$event_key = sanitize_key( (string) ( $body['event_key'] ?? '' ) );
		$result    = WebinoCRM_Sms_Pattern_Sync_Service::detach( $domain, $scope, $event_key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_pattern_registry( $request ) {
		$domain = $this->domain_from_request( $request );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'registry' => WebinoCRM_Sms_Pattern_Sync_Service::list_registry( $domain ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function order_notify( $request ) {
		$body       = $request->get_json_params();
		$domain     = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$event_key  = sanitize_key( (string) ( $body['event_key'] ?? '' ) );
		$order      = is_array( $body['order'] ?? null ) ? $body['order'] : array();
		$options    = array(
			'force_customer' => ! empty( $body['force_customer'] ),
			'force_admin'    => ! empty( $body['force_admin'] ),
		);
		$result     = WebinoCRM_Sms_Order_Notify_Service::notify( $domain, $event_key, $order, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * SMS history for a WooCommerce order (context_type=order).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function order_messages( $request ) {
		$domain   = WebinoCRM_License_Manager::normalize_domain( (string) $request->get_param( 'domain' ) );
		$order_id = (int) $request->get_param( 'order_id' );
		$result   = WebinoCRM_Sms_Order_Messages_Service::list_for_order( $domain, $order_id );
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Force-send a test notify for one role (customer or admin) using sample/order snapshot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function order_test_notify( $request ) {
		$body      = $request->get_json_params();
		$domain    = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$event_key = sanitize_key( (string) ( $body['event_key'] ?? 'processing' ) );
		$role      = sanitize_key( (string) ( $body['role'] ?? 'customer' ) );
		$order     = is_array( $body['order'] ?? null ) ? $body['order'] : array();
		$test_phone = WebinoCRM_Sms_Template_Service::normalize_phone( (string) ( $body['phone'] ?? '' ) );
		if ( empty( $order ) ) {
			$order = array(
				'id'             => 0,
				'number'         => 'TEST',
				'customer_name'  => 'Test Customer',
				'customer_phone' => $test_phone,
				'total'          => '0',
				'status'         => 'processing',
				'status_label'   => 'Processing',
				'site_name'      => get_bloginfo( 'name' ),
				'site_url'       => 'https://' . $domain,
			);
		} elseif ( '' !== $test_phone ) {
			$order['customer_phone'] = $test_phone;
		}
		$options = array(
			'force_customer' => 'admin' !== $role,
			'force_admin'    => 'admin' === $role,
		);
		if ( 'admin' === $role && '' !== $test_phone ) {
			$options['admin_phones'] = array( $test_phone );
		}
		$result = WebinoCRM_Sms_Order_Notify_Service::notify( $domain, $event_key, $order, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array_merge( is_array( $result ) ? $result : array(), array( 'ok' => true, 'test' => true ) ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function auth_send_otp( $request ) {
		$body    = $request->get_json_params();
		$domain  = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$phone   = (string) ( $body['phone'] ?? '' );
		$purpose = sanitize_key( (string) ( $body['purpose'] ?? WebinoCRM_Sms_Auth_Service::PURPOSE_LOGIN ) );
		$result  = WebinoCRM_Sms_Auth_Service::send_otp( $domain, $phone, $purpose );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function auth_verify_otp( $request ) {
		$body    = $request->get_json_params();
		$domain  = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$phone   = (string) ( $body['phone'] ?? '' );
		$code    = (string) ( $body['code'] ?? '' );
		$purpose = sanitize_key( (string) ( $body['purpose'] ?? WebinoCRM_Sms_Auth_Service::PURPOSE_LOGIN ) );
		$result  = WebinoCRM_Sms_Auth_Service::verify_otp( $domain, $phone, $code, $purpose );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function newsletter_subscribe( $request ) {
		$body       = $request->get_json_params();
		$domain     = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$product_id = (int) ( $body['product_id'] ?? 0 );
		$phone      = (string) ( $body['phone'] ?? '' );
		$result     = WebinoCRM_Sms_Newsletter_Service::subscribe( $domain, $product_id, $phone );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function newsletter_subscribers( $request ) {
		$domain     = $this->domain_from_request( $request );
		$product_id = (int) $request->get_param( 'product_id' );
		$page       = max( 1, (int) $request->get_param( 'page' ) );
		$limit      = min( 100, max( 1, (int) $request->get_param( 'limit' ) ) );
		return new WP_REST_Response(
			array(
				'ok'          => true,
				'subscribers' => WebinoCRM_Sms_Newsletter_Service::list_subscribers( $domain, $product_id, $page, $limit ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function newsletter_unsubscribe( $request ) {
		$body   = $request->get_json_params();
		$domain = $this->domain_from_request( $request );
		if ( ! $domain && is_array( $body ) ) {
			$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		}
		$id = (int) ( $request->get_param( 'id' ) ?: ( is_array( $body ) ? ( $body['id'] ?? 0 ) : 0 ) );
		$result = WebinoCRM_Sms_Newsletter_Service::unsubscribe( $domain, $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function newsletter_send( $request ) {
		$body       = $request->get_json_params();
		$domain     = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$product_id = (int) ( $body['product_id'] ?? 0 );
		$message    = (string) ( $body['message'] ?? '' );
		$vars       = is_array( $body['vars'] ?? null ) ? $body['vars'] : array();
		$result     = WebinoCRM_Sms_Newsletter_Service::send_campaign( $domain, $product_id, $message, $vars );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reports_inbox( $request ) {
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$limit = min( 50, max( 1, (int) $request->get_param( 'limit' ) ) );
		$edge  = WebinoCRM_ModirPayamak_Edge_Client::report_inbox( $page, $limit );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reports_bulk_stats( $request ) {
		$outbox_id = (string) ( $request->get_param( 'bulk_id' ) ?: $request->get_param( 'outbox_id' ) );
		$edge      = WebinoCRM_ModirPayamak_Edge_Client::report_bulk_stats( $outbox_id );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reports_bulk_recipients( $request ) {
		$outbox_id = (string) ( $request->get_param( 'bulk_id' ) ?: $request->get_param( 'outbox_id' ) );
		$edge      = WebinoCRM_ModirPayamak_Edge_Client::report_bulk_recipients(
			$outbox_id,
			array(
				'page'  => max( 1, (int) $request->get_param( 'page' ) ),
				'limit' => min( 100, max( 1, (int) $request->get_param( 'limit' ) ) ),
			)
		);
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function cancel_scheduled( $request ) {
		$body      = $request->get_json_params();
		$outbox_id = (string) ( $body['messages_outbox_id'] ?? $body['outbox_id'] ?? '' );
		$edge      = WebinoCRM_ModirPayamak_Edge_Client::cancel_scheduled( $outbox_id );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_drafts( $request ) {
		$edge = WebinoCRM_ModirPayamak_Edge_Client::list_drafts(
			array(
				'page'     => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) ),
			)
		);
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_draft( $request ) {
		$body = $request->get_json_params();
		unset( $body['domain'] );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::create_draft( is_array( $body ) ? $body : array() );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_draft( $request ) {
		$body = $request->get_json_params();
		$id   = (int) ( $request->get_param( 'id' ) ?: ( is_array( $body ) ? ( $body['id'] ?? 0 ) : 0 ) );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::delete_draft( $id );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * Site-facing wallet ledger for the licensed domain.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_ledger( $request ) {
		$domain  = $this->domain_from_request( $request );
		$page    = max( 1, (int) $request->get_param( 'page' ) );
		$limit   = min( 100, max( 1, (int) $request->get_param( 'limit' ) ) );
		$account = WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain );
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'account' => WebinoCRM_ModirPayamak_Manager::format_account_public( $account ),
				'ledger'  => WebinoCRM_ModirPayamak_Manager::get_ledger( $domain, $page, $limit ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_secretaries( $request ) {
		$domain = $this->domain_from_request( $request );
		return new WP_REST_Response(
			array(
				'ok'          => true,
				'secretaries' => WebinoCRM_Sms_Secretary_Service::list_rules( $domain ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_secretary( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$result = WebinoCRM_Sms_Secretary_Service::save_rule( $domain, is_array( $body ) ? $body : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_secretary( $request ) {
		$body   = $request->get_json_params();
		$domain = $this->domain_from_request( $request );
		if ( ! $domain && is_array( $body ) ) {
			$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		}
		$id     = (int) ( $request->get_param( 'id' ) ?: ( is_array( $body ) ? ( $body['id'] ?? 0 ) : 0 ) );
		$result = WebinoCRM_Sms_Secretary_Service::delete_rule( $domain, $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function process_secretaries( $request ) {
		$body   = $request->get_json_params();
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? $this->domain_from_request( $request ) ) );
		$result = WebinoCRM_Sms_Secretary_Service::process_inbox( $domain );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_tickets( $request ) {
		$edge = WebinoCRM_ModirPayamak_Edge_Client::list_tickets(
			array(
				'page'     => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) ),
			)
		);
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_edge_phonebooks( $request ) {
		$edge = WebinoCRM_ModirPayamak_Edge_Client::list_phonebooks();
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_edge_phonebook( $request ) {
		$body = $request->get_json_params();
		unset( $body['domain'] );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::create_phonebook( is_array( $body ) ? $body : array() );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_edge_phonebook_numbers( $request ) {
		$id   = (int) $request['id'];
		$edge = WebinoCRM_ModirPayamak_Edge_Client::list_phonebook_numbers( $id );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function store_edge_phonebook_number( $request ) {
		$id   = (int) $request['id'];
		$body = $request->get_json_params();
		unset( $body['domain'] );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::store_phonebook_number( $id, is_array( $body ) ? $body : array() );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_pattern( $request ) {
		$code = (string) $request['code'];
		$edge = WebinoCRM_ModirPayamak_Edge_Client::get_pattern( $code );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_pattern( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? $this->domain_from_request( $request ) ) );
		$scope  = sanitize_key( (string) ( $body['scope'] ?? '' ) );
		$event  = sanitize_key( (string) ( $body['event_key'] ?? '' ) );
		unset( $body['domain'], $body['scope'], $body['event_key'], $body['param_map'] );
		$body['is_share'] = false;
		$edge = WebinoCRM_ModirPayamak_Edge_Client::create_pattern( $body );
		$status = ! empty( $edge['ok'] ) ? 200 : 502;
		$code   = '';
		if ( ! empty( $edge['ok'] ) && is_array( $edge['data'] ?? null ) ) {
			$code = (string) ( $edge['data']['code'] ?? $edge['data']['pattern_code'] ?? '' );
		}
		if ( '' !== $code && '' !== $domain && '' !== $scope && '' !== $event ) {
			WebinoCRM_Sms_Pattern_Sync_Service::bind_existing( $domain, $scope, $event, $code );
		} elseif ( '' !== $code && '' !== $domain ) {
			// Record ownership without event: temporary registry row under site/otp placeholder not used —
			// store via a lightweight upsert on a dedicated "owned" event if provided later.
		}
		return new WP_REST_Response(
			array(
				'ok'      => ! empty( $edge['ok'] ),
				'data'    => $edge['data'] ?? null,
				'meta'    => $edge['meta'] ?? null,
				'message' => $edge['message'] ?? '',
			),
			$status
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_pattern( $request ) {
		$code = (string) $request['code'];
		$body = $request->get_json_params();
		unset( $body['domain'] );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::update_pattern( $code, is_array( $body ) ? $body : array() );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_pattern( $request ) {
		$code = (string) $request['code'];
		$edge = WebinoCRM_ModirPayamak_Edge_Client::delete_pattern( $code );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
	}
}
