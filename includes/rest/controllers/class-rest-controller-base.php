<?php
/**
 * Base REST controller helpers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared REST controller utilities.
 */
abstract class WebinoCRM_REST_Controller_Base {

	/**
	 * Run a service callable and return REST response.
	 *
	 * @param callable            $callable function( array $params ): array.
	 * @param WP_REST_Request     $request  Request.
	 * @param array<string,mixed> $extra    Extra params merged in.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function run_service( $callable, WP_REST_Request $request, array $extra = array() ) {
		$params = array_merge(
			WebinoCRM_REST_Legacy_Invoker::request_params( $request ),
			$extra
		);
		$params = WebinoCRM_REST_Registry::enrich_request_params( $request, $params );
		if ( empty( $params['_wpnonce'] ) ) {
			$header = $request->get_header( 'X-WP-Nonce' );
			if ( $header ) {
				$params['_wpnonce'] = $header;
			}
		}
		$result = WebinoCRM_Dashboard_Locale::with_user_locale(
			static function () use ( $callable, $params ) {
				return call_user_func( $callable, $params );
			}
		);
		return WebinoCRM_REST_Response::from_service( $result );
	}

	/**
	 * @param string $cap Route capability slug.
	 * @return callable
	 */
	protected static function can( $cap = '' ) {
		return '' === $cap
			? array( 'WebinoCRM_REST_Base', 'can_read' )
			: WebinoCRM_REST_Registry::can_route( $cap );
	}
}
