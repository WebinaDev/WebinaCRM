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
			array( 'GET', '/modirpayamak/patterns/registry', 'get_pattern_registry' ),
			array( 'POST', '/modirpayamak/orders/notify', 'order_notify' ),
			array( 'POST', '/modirpayamak/auth/send-otp', 'auth_send_otp' ),
			array( 'POST', '/modirpayamak/auth/verify-otp', 'auth_verify_otp' ),
			array( 'POST', '/modirpayamak/newsletter/subscribe', 'newsletter_subscribe' ),
			array( 'GET', '/modirpayamak/newsletter/subscribers', 'newsletter_subscribers' ),
			array( 'POST', '/modirpayamak/newsletter/send', 'newsletter_send' ),
			array( 'GET', '/modirpayamak/reports/inbox', 'reports_inbox' ),
			array( 'POST', '/modirpayamak/send/cancel-scheduled', 'cancel_scheduled' ),
			array( 'GET', '/modirpayamak/drafts', 'list_drafts' ),
			array( 'GET', '/modirpayamak/tickets', 'list_tickets' ),
			array( 'GET', '/modirpayamak/phonebooks/edge', 'list_edge_phonebooks' ),
			array( 'POST', '/modirpayamak/phonebooks/edge', 'create_edge_phonebook' ),
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
		$domain = $this->domain_from_request( $request );
		WebinoCRM_Sms_Template_Service::seed_order_defaults( $domain );
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'settings'   => WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP ),
				'event_keys' => WebinoCRM_Sms_Constants::order_event_keys(),
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
		$body       = $request->get_json_params();
		$domain     = WebinoCRM_License_Manager::normalize_domain( (string) ( $body['domain'] ?? '' ) );
		$scope      = sanitize_key( (string) ( $body['scope'] ?? '' ) );
		$event_key  = sanitize_key( (string) ( $body['event_key'] ?? '' ) );
		$result     = WebinoCRM_Sms_Pattern_Sync_Service::sync_one( $domain, $scope, $event_key );
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
		$result     = WebinoCRM_Sms_Order_Notify_Service::notify( $domain, $event_key, $order );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
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
	public function create_pattern( $request ) {
		$body = $request->get_json_params();
		unset( $body['domain'] );
		$edge = WebinoCRM_ModirPayamak_Edge_Client::create_pattern( is_array( $body ) ? $body : array() );
		return new WP_REST_Response( array( 'ok' => ! empty( $edge['ok'] ), 'data' => $edge['data'], 'meta' => $edge['meta'] ), 200 );
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
