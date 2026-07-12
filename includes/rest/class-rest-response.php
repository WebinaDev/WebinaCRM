<?php
/**
 * Normalized REST responses (AjaxResponse-compatible during migration).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST response helpers.
 */
class WebinoCRM_REST_Response {

	/**
	 * @param mixed $data Payload.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function success( $data = null, $status = 200 ) {
		$body = array( 'success' => true );
		if ( null !== $data ) {
			$body['data'] = $data;
		}
		return new WP_REST_Response( $body, $status );
	}

	/**
	 * @param string $message Error message.
	 * @param int    $status  HTTP status.
	 * @param string $code    Error code.
	 * @return WP_Error
	 */
	public static function error( $message, $status = 400, $code = 'webinocrm_error' ) {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'  => $status,
				'success' => false,
				'message' => $message,
			)
		);
	}

	/**
	 * Convert legacy invoker result to REST response.
	 *
	 * @param array<string,mixed>|null $result Parsed JSON from ajax handler.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function from_ajax( $result ) {
		if ( ! is_array( $result ) ) {
			return self::error( __( 'Invalid server response.', 'webinocrm' ), 500 );
		}
		if ( ! empty( $result['success'] ) ) {
			$data = isset( $result['data'] ) ? $result['data'] : $result;
			$response = self::success( $data );
			if ( isset( $result['total'] ) ) {
				$response->data['total'] = $result['total'];
			}
			return $response;
		}
		$msg = '';
		if ( isset( $result['data']['message'] ) ) {
			if ( is_string( $result['data']['message'] ) ) {
				$msg = $result['data']['message'];
			} elseif ( is_array( $result['data']['message'] ) && isset( $result['data']['message']['message'] ) ) {
				$msg = (string) $result['data']['message']['message'];
			}
		} elseif ( isset( $result['message'] ) && is_string( $result['message'] ) ) {
			$msg = $result['message'];
		}
		if ( '' === $msg ) {
			$msg = __( 'Request failed.', 'webinocrm' );
		}
		$status = 400;
		if ( isset( $result['data']['status'] ) && is_numeric( $result['data']['status'] ) ) {
			$status = (int) $result['data']['status'];
		}
		return self::error( $msg, $status );
	}

	/**
	 * Convert service layer result to REST response.
	 *
	 * @param array<string,mixed>|WP_Error|null $result Service output.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function from_service( $result ) {
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		return self::from_ajax( is_array( $result ) ? $result : null );
	}
}
