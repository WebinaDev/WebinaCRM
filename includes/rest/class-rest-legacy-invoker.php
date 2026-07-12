<?php
/**
 * Invoke legacy wp_ajax handlers and capture JSON (for REST migration).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs registered AJAX actions without a browser request.
 */
class WebinoCRM_REST_Legacy_Invoker {

	/**
	 * @param string               $action Ajax action name (without wp_ajax_ prefix).
	 * @param array<string,mixed>  $params Request parameters (merged into $_POST).
	 * @return array<string,mixed>|null Decoded JSON or null on failure.
	 */
	/**
	 * Invoke wp_ajax handler directly (bypasses service action map).
	 *
	 * @param string              $action Ajax action name.
	 * @param array<string,mixed> $params Parameters.
	 * @return array<string,mixed>|null
	 */
	public static function invoke_raw( $action, array $params = array() ) {
		$action = sanitize_key( (string) $action );
		if ( '' === $action ) {
			return null;
		}

		$hook = 'wp_ajax_' . $action;
		if ( ! has_action( $hook ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'message' => __( 'Unknown action.', 'webinocrm' ),
				),
			);
		}

		$nonce = wp_create_nonce( 'webinocrm-ajax-nonce' );
		$backup_post    = $_POST;
		$backup_request = $_REQUEST;

		$_POST    = array_merge( $backup_post, $params, array( 'security' => $nonce ) );
		$_REQUEST = array_merge( $backup_request, $_POST );

		ob_start();
		try {
			do_action( $hook );
		} catch ( Throwable $e ) {
			ob_end_clean();
			$_POST    = $backup_post;
			$_REQUEST = $backup_request;
			return array(
				'success' => false,
				'data'    => array( 'message' => $e->getMessage() ),
			);
		}
		$output = ob_get_clean();

		$_POST    = $backup_post;
		$_REQUEST = $backup_request;

		if ( '' === trim( $output ) ) {
			return array( 'success' => true );
		}

		$decoded = json_decode( $output, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}

		return array(
			'success' => true,
			'data'    => $output,
		);
	}

	/**
	 * Map WP_REST_Request params to POST array (snake_case keys preserved).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	public static function request_params( WP_REST_Request $request ) {
		$params = array_merge(
			$request->get_url_params(),
			$request->get_query_params(),
			$request->get_body_params()
		);
		$json = $request->get_json_params();
		if ( is_array( $json ) ) {
			$params = array_merge( $params, $json );
		}
		unset( $params['action'], $params['security'] );
		return $params;
	}

	/**
	 * Prefer service map, then raw wp_ajax (used by REST registry).
	 *
	 * @param string              $action Action.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|null
	 */
	public static function invoke( $action, array $params = array() ) {
		return WebinoCRM_Service_Action_Map::dispatch( $action, $params );
	}
}
