<?php
/**
 * Registers CRM REST resource routes (webinocrm/v1).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route registry.
 */
class WebinoCRM_REST_Registry {

	const NS = WebinoCRM_Dashboard_REST::NS;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ), 10 );
	}

	/**
	 * @return void
	 */
	public static function register() {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-services-loader.php';
		WebinoCRM_Services_Loader::init();
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-rest-routes.php';
		WebinoCRM_REST_Routes::register_routes();
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	public static function enrich_request_params( WP_REST_Request $request, array $params ) {
		return self::enrich_legacy_params( $request, $params );
	}

	/**
	 * Generic ajax-backed route handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function dispatch_ajax( WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'webinocrm_ajax_action' );
		if ( '' === $action ) {
			$action = (string) $request->get_param( '_webinocrm_action' );
		}
		$params = WebinoCRM_REST_Legacy_Invoker::request_params( $request );
		$params = self::enrich_legacy_params( $request, $params );
		$result = WebinoCRM_Service_Action_Map::dispatch( $action, $params );
		return WebinoCRM_REST_Response::from_service( $result );
	}

	/**
	 * Register a route backed by a service callable (preferred).
	 *
	 * @param string|array        $methods  HTTP methods.
	 * @param string              $route    Route path.
	 * @param callable            $callback function( array $params ): array.
	 * @param string              $cap      Route slug for permissions.
	 * @return void
	 */
	/**
	 * Register a route whose handler may stream binary output (e.g. CSV) and exit.
	 *
	 * @param string|array        $methods  HTTP methods.
	 * @param string              $route    Route path.
	 * @param callable            $callback function( array $params ): array|null.
	 * @param string              $cap      Route slug for permissions.
	 * @return void
	 */
	public static function register_stream_route( $methods, $route, $callback, $cap = '' ) {
		$cap_slug = $cap ? $cap : '';
		if ( 'public' === $cap_slug ) {
			$permission = '__return_true';
		} elseif ( '' === $cap_slug ) {
			$permission = array( 'WebinoCRM_REST_Base', 'can_read' );
		} else {
			$permission = self::can_route( $cap_slug );
		}
		register_rest_route(
			self::NS,
			$route,
			array(
				'methods'             => $methods,
				'callback'            => static function ( $request ) use ( $callback ) {
					$params = WebinoCRM_REST_Legacy_Invoker::request_params( $request );
					$params = self::enrich_request_params( $request, $params );
					if ( empty( $params['_wpnonce'] ) ) {
						$header = $request->get_header( 'X-WP-Nonce' );
						if ( $header ) {
							$params['_wpnonce'] = $header;
						}
					}
					$result = call_user_func( $callback, $params );
					if ( is_array( $result ) ) {
						return WebinoCRM_REST_Response::from_service( $result );
					}
					return new WP_REST_Response( null, 200 );
				},
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Register a route backed by a service callable (preferred).
	 *
	 * @param string|array        $methods  HTTP methods.
	 * @param string              $route    Route path.
	 * @param callable            $callback function( array $params ): array.
	 * @param string              $cap      Route slug for permissions.
	 * @return void
	 */
	public static function register_service_route( $methods, $route, $callback, $cap = '' ) {
		$cap_slug = $cap ? $cap : '';
		if ( 'public' === $cap_slug ) {
			$permission = '__return_true';
		} elseif ( '' === $cap_slug ) {
			$permission = array( 'WebinoCRM_REST_Base', 'can_read' );
		} else {
			$permission = self::can_route( $cap_slug );
		}
		register_rest_route(
			self::NS,
			$route,
			array(
				'methods'             => $methods,
				'callback'            => static function ( $request ) use ( $callback ) {
					return WebinoCRM_REST_Controller_Base::run_service( $callback, $request );
				},
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * @param string $route_slug CRM route slug for permission check.
	 * @return callable
	 */
	public static function can_route( $route_slug ) {
		return static function () use ( $route_slug ) {
			return WebinoCRM_REST_Base::can_read() && WebinoCRM_REST_Base::can_access_route( $route_slug );
		};
	}

	/**
	 * @param string|array $methods HTTP method(s).
	 * @param string       $route   Route regex (relative).
	 * @param string       $action  Ajax action name.
	 * @param string       $cap     Route slug for permissions (empty = logged-in read).
	 * @return void
	 */
	public static function register_ajax_route( $methods, $route, $action, $cap = '' ) {
		$cap_slug = $cap ? $cap : '';
		$action   = (string) $action;
		register_rest_route(
			self::NS,
			$route,
			array(
				'methods'             => $methods,
				'callback'            => static function ( $request ) use ( $action ) {
					$request->set_param( 'webinocrm_ajax_action', $action );
					return self::dispatch_ajax( $request );
				},
				'permission_callback' => '' === $cap_slug
					? array( 'WebinoCRM_REST_Base', 'can_read' )
					: self::can_route( $cap_slug ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	public static function enrich_legacy_params( WP_REST_Request $request, array $params ) {
		$url = $request->get_url_params();
		if ( ! empty( $url['release_id'] ) && empty( $params['release_id'] ) ) {
			$params['release_id'] = (string) $url['release_id'];
		}
		if ( ! empty( $url['token'] ) && empty( $params['token'] ) ) {
			$params['token'] = (string) $url['token'];
		}
		if ( empty( $url['id'] ) ) {
			return $params;
		}
		$id     = (string) $url['id'];
		$action = (string) $request->get_param( 'webinocrm_ajax_action' );

		if ( empty( $params['id'] ) ) {
			$params['id'] = $id;
		}
		if ( ! isset( $params['id'] ) && preg_match( '/_(get|delete|save|post|confirm)(_|$)/', $action ) ) {
			$params['id'] = $id;
		}
		$maps = array(
			'project'  => 'project_id',
			'contract' => 'contract_id',
			'ticket'   => 'ticket_id',
			'task'     => 'task_id',
			'lead'     => 'lead_id',
			'invoice'  => 'invoice_id',
			'license'  => 'license_id',
			'campaign' => 'campaign_id',
			'position' => 'position_id',
			'category' => 'category_id',
			'response' => 'response_id',
			'quote'    => 'quote_id',
		);
		foreach ( $maps as $needle => $key ) {
			if ( str_contains( $action, $needle ) && empty( $params[ $key ] ) ) {
				$params[ $key ] = $id;
			}
		}
		return $params;
	}
}
