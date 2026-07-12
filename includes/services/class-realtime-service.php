<?php
/**
 * Realtime bridge: WS tokens and broadcast to Node sidecar.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Realtime_Service {

	/**
	 * Shared secret for Node server and internal broadcast.
	 */
	public static function get_secret() {
		if ( defined( 'WEBINOCRM_WS_SECRET' ) && WEBINOCRM_WS_SECRET ) {
			return (string) WEBINOCRM_WS_SECRET;
		}
		$opt = get_option( 'webinocrm_ws_secret', '' );
		if ( '' !== $opt ) {
			return (string) $opt;
		}
		return (string) wp_salt( 'auth' );
	}

	/**
	 * @return string
	 */
	public static function get_ws_url() {
		$host = get_option( 'webinocrm_ws_host', '' );
		if ( '' === $host ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$port   = (int) get_option( 'webinocrm_ws_port', 8080 );
		$secure = is_ssl();
		$proto  = $secure ? 'wss' : 'ws';
		if ( $secure && ( 443 === $port || 0 === $port ) ) {
			return "{$proto}://{$host}/ws";
		}
		return "{$proto}://{$host}:{$port}";
	}

	/**
	 * @return string
	 */
	public static function get_broadcast_url() {
		$internal = get_option( 'webinocrm_ws_internal_url', '' );
		if ( '' !== $internal ) {
			return trailingslashit( $internal ) . 'broadcast';
		}
		$port = (int) get_option( 'webinocrm_ws_port', 8080 );
		return "http://127.0.0.1:{$port}/broadcast";
	}

	/**
	 * Short-lived token for WebSocket auth.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function create_ws_token( $user_id ) {
		$user_id = (int) $user_id;
		$exp     = time() + 3600;
		$payload = $user_id . '.' . $exp;
		$sig     = hash_hmac( 'sha256', $payload, self::get_secret() );
		return $payload . '.' . $sig;
	}

	/**
	 * @param array $user_ids Recipient user IDs.
	 * @param array $data     Payload (type, message, ...).
	 */
	public static function broadcast( array $user_ids, array $data ) {
		$user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );
		if ( empty( $user_ids ) ) {
			return;
		}
		wp_remote_post(
			self::get_broadcast_url(),
			array(
				'timeout'  => 2,
				'blocking' => false,
				'headers'  => array(
					'Content-Type' => 'application/json',
					'X-WS-Secret'  => self::get_secret(),
				),
				'body'     => wp_json_encode(
					array(
						'user_ids' => $user_ids,
						'data'     => $data,
					)
				),
			)
		);
	}

	/**
	 * REST: issue WS token for current user.
	 *
	 * @param array $params Request params.
	 * @return array
	 */
	public static function ws_token( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'Unauthorized.', 'webinocrm' ) ), 401 );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'token'  => self::create_ws_token( $user_id ),
				'ws_url' => self::get_ws_url(),
			)
		);
	}

	/**
	 * REST: internal broadcast endpoint (secret header).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_broadcast( $request ) {
		$secret = $request->get_header( 'x-ws-secret' );
		if ( ! is_string( $secret ) || ! hash_equals( self::get_secret(), $secret ) ) {
			return new WP_Error( 'forbidden', __( 'Forbidden.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		$body     = $request->get_json_params();
		$user_ids = isset( $body['user_ids'] ) ? (array) $body['user_ids'] : array();
		$data     = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		self::broadcast( $user_ids, $data );
		return rest_ensure_response( array( 'success' => true ) );
	}
}
