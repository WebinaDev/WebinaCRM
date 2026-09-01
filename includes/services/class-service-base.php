<?php
/**
 * Base helpers for CRM service layer (no wp_send_json).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service result helpers and AJAX emission for thin handlers.
 */
class WebinoCRM_Service_Base {

	/**
	 * @param mixed $data Payload.
	 * @return array<string,mixed>
	 */
	public static function success( $data = null ) {
		$out = array( 'success' => true );
		if ( null !== $data ) {
			$out['data'] = $data;
		}
		return $out;
	}

	/**
	 * @param string $message Error message.
	 * @param int    $status  Optional HTTP-ish code for logging.
	 * @return array<string,mixed>
	 */
	public static function error( $message, $status = 400 ) {
		if ( is_array( $message ) && isset( $message['message'] ) ) {
			$message = (string) $message['message'];
		} elseif ( ! is_string( $message ) ) {
			$message = __( 'Request failed.', 'webinocrm' );
		}
		return array(
			'success' => false,
			'data'    => array(
				'message' => $message,
				'status'  => $status,
			),
		);
	}

	/**
	 * POST params for admin-ajax requests.
	 *
	 * @return array<string,mixed>
	 */
	public static function post_params() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$params = array();
		foreach ( $_POST as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$params[ $key ] = wp_unslash( (string) $value );
			}
		}
		return $params;
	}

	/**
	 * Emit JSON for thin AJAX handlers.
	 *
	 * @param array<string,mixed> $result Service result.
	 * @return void
	 */
	public static function emit_json( array $result ) {
		if ( ! empty( $result['success'] ) ) {
			$data = isset( $result['data'] ) ? $result['data'] : null;
			wp_send_json_success( $data );
		}
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		if ( empty( $data['message'] ) && ! empty( $result['message'] ) ) {
			$data['message'] = $result['message'];
		}
		wp_send_json_error( $data );
	}

	/**
	 * Verify CRM AJAX nonce (for thin handlers only).
	 *
	 * @return bool
	 */
	public static function verify_ajax_nonce() {
		return (bool) check_ajax_referer( 'webinocrm-ajax-nonce', 'security', false );
	}

	/**
	 * Verify REST (wp_rest nonce) or legacy AJAX nonce.
	 *
	 * @return bool
	 */
	public static function verify_crm_request() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		// REST routes already passed permission_callback (logged-in + route cap).
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		$candidates = array();
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_WP_NONCE'] ) );
		}
		foreach ( $candidates as $nonce ) {
			if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return true;
			}
		}
		return self::verify_ajax_nonce();
	}

	/**
	 * @param string               $action Legacy wp_ajax action name.
	 * @param array<string,mixed>  $params Request params.
	 * @return array<string,mixed>
	 */
	public static function delegate_legacy( $action, array $params = array() ) {
		$result = WebinoCRM_REST_Legacy_Invoker::invoke_raw( $action, $params );
		if ( is_array( $result ) ) {
			return $result;
		}
		return self::error( __( 'Invalid server response.', 'webinocrm' ), 500 );
	}

	/**
	 * Load CRM dependencies for service operations.
	 *
	 * @return void
	 */
	public static function ensure_dependencies() {
		if ( ! defined( 'WEBINOCRM_PLUGIN_DIR' ) ) {
			return;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-error-codes.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-logger.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/webino-functions.php';
		if ( file_exists( WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php';
		}
	}

	/**
	 * @param string              $code  WebinoCRM_Error_Codes constant.
	 * @param array<string,mixed> $extra Optional override message in extra['message'].
	 * @return array<string,mixed>
	 */
	public static function coded_error( $code, array $extra = array() ) {
		$message = class_exists( 'WebinoCRM_Error_Codes' )
			? WebinoCRM_Error_Codes::get_message( $code )
			: __( 'Request failed.', 'webinocrm' );
		if ( ! empty( $extra['message'] ) && is_string( $extra['message'] ) ) {
			$message = $extra['message'];
		}
		return self::error( $message, 400 );
	}

	/**
	 * @param array<string,mixed> $params  Params.
	 * @param string              $key     Key.
	 * @param mixed               $default Default.
	 * @return mixed
	 */
	public static function param( array $params, $key, $default = '' ) {
		if ( array_key_exists( $key, $params ) ) {
			return $params[ $key ];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) ) {
			return wp_unslash( $_POST[ $key ] );
		}
		return $default;
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string              $key    Key.
	 * @return int
	 */
	public static function int_param( array $params, $key ) {
		return absint( self::param( $params, $key, 0 ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string              $key    Key.
	 * @return array<int,mixed>|null
	 */
	public static function array_param( array $params, $key ) {
		if ( isset( $params[ $key ] ) && is_array( $params[ $key ] ) ) {
			return $params[ $key ];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
			return wp_unslash( $_POST[ $key ] );
		}
		return null;
	}

	/**
	 * @param string $raw Date string (Gregorian Y-m-d or Jalali).
	 * @return string Gregorian Y-m-d, or empty string if invalid / zero-date.
	 */
	public static function normalize_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || '0000-00-00' === $raw || 0 === strpos( $raw, '0000-00-00' ) ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{1,2}-\d{1,2}$/', $raw ) ) {
			$parts = explode( '-', $raw );
			$year  = (int) $parts[0];
			// Jalali years commonly fall in 1000–1500; convert instead of storing as Gregorian.
			if ( $year >= 1000 && $year <= 1500 && function_exists( 'webino_jalali_to_gregorian' ) ) {
				$converted = webino_jalali_to_gregorian( $raw, true );
				if ( $converted ) {
					return $converted;
				}
				return '';
			}
			if ( $year < 1600 ) {
				return '';
			}
			return sprintf( '%04d-%02d-%02d', $year, (int) $parts[1], (int) $parts[2] );
		}
		if ( function_exists( 'webino_jalali_to_gregorian' ) ) {
			$converted = webino_jalali_to_gregorian( $raw, true );
			if ( $converted ) {
				return $converted;
			}
		}
		if ( function_exists( 'jalali_to_gregorian' ) ) {
			$date_parts = explode( '/', $raw );
			if ( count( $date_parts ) === 3 ) {
				$g = jalali_to_gregorian( $date_parts[0], $date_parts[1], $date_parts[2] );
				return sprintf( '%04d-%02d-%02d', $g[0], $g[1], $g[2] );
			}
		}
		return $raw;
	}
}
