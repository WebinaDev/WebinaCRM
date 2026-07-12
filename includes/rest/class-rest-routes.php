<?php
/**
 * CRM REST resource routes (webinocrm/v1) mapped to legacy AJAX handlers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route registration.
 */
class WebinoCRM_REST_Routes {

	/**
	 * @return void
	 */
	public static function register_routes() {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/controllers/class-rest-crm-controllers.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/controllers/class-rest-hrm-controllers.php';
		WebinoCRM_REST_Crm_Controllers::register_all();
		self::register_accounting_catch_all();
		self::register_scm_catch_all();
		self::register_auth_routes();
	}

		/* Legacy route_definitions() removed — routes register via WebinoCRM_REST_Crm_Controllers. */

	private static function register_accounting_catch_all() {
		$methods = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		$args    = array(
			'methods'             => $methods,
			'callback'            => array( __CLASS__, 'accounting_dispatch' ),
			'permission_callback' => static function () {
				return WebinoCRM_REST_Base::can_read()
					&& ( ! function_exists( 'webinocrm_current_user_can_accounting' )
					|| webinocrm_current_user_can_accounting() );
			},
		);
		register_rest_route( WebinoCRM_REST_Registry::NS, '/accounting/(?P<segment>[a-z0-9_-]+)', $args );
		register_rest_route( WebinoCRM_REST_Registry::NS, '/finance/(?P<segment>[a-z0-9_-]+)', $args );
	}

	/**
	 * SCM / warehouse REST (alias of accounting warehouse segments).
	 *
	 * @return void
	 */
	private static function register_scm_catch_all() {
		$methods = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		register_rest_route(
			WebinoCRM_REST_Registry::NS,
			'/scm/(?P<segment>[a-z0-9_-]+)',
			array(
				'methods'             => $methods,
				'callback'            => array( __CLASS__, 'scm_dispatch' ),
				'permission_callback' => static function () {
					return WebinoCRM_REST_Base::can_read()
						&& ( ! function_exists( 'webinocrm_current_user_can_accounting' )
						|| webinocrm_current_user_can_accounting() );
				},
			)
		);
	}

	/**
	 * Map SCM URL segments to accounting service segments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function scm_dispatch( WP_REST_Request $request ) {
		$raw = sanitize_key( str_replace( '-', '_', (string) $request->get_param( 'segment' ) ) );
		$map = array(
			'warehouses' => 'warehouses',
			'stock'      => 'warehouse_stock',
			'inbound'    => 'warehouse_inbound',
			'outbound'   => 'warehouse_outbound',
			'audit'      => 'warehouse_audit',
		);
		$segment = isset( $map[ $raw ] ) ? $map[ $raw ] : $raw;
		return WebinoCRM_REST_Controller_Base::run_service(
			array( 'WebinoCRM_Warehouse_Service', 'by_segment' ),
			$request,
			array( 'segment' => $segment )
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function accounting_dispatch( WP_REST_Request $request ) {
		$segment = sanitize_key( str_replace( '-', '_', (string) $request->get_param( 'segment' ) ) );
		return WebinoCRM_REST_Controller_Base::run_service(
			array( 'WebinoCRM_Accounting_Service', 'by_segment' ),
			$request,
			array( 'segment' => $segment )
		);
	}

	/**
	 * Public login AJAX via REST (nopriv allowed).
	 *
	 * @return void
	 */
	private static function register_auth_routes() {
		$routes = array(
			array( '/auth/login/otp/send', 'send_login_otp' ),
			array( '/auth/login/otp/verify', 'verify_login_otp' ),
			array( '/auth/login/password', 'login_password' ),
			array( '/auth/login/email-otp/send', 'send_email_otp' ),
			array( '/auth/login/email-otp/verify', 'verify_email_otp' ),
			array( '/auth/register', 'register' ),
			array( '/auth/set-password', 'set_password' ),
		);
		foreach ( $routes as $pair ) {
			WebinoCRM_REST_Registry::register_service_route(
				'POST',
				$pair[0],
				array( 'WebinoCRM_Auth_Service', $pair[1] ),
				'public'
			);
		}
	}
}
