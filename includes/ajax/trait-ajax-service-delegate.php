<?php
/**
 * Thin AJAX → service delegation for CRM handlers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers to route wp_ajax callbacks through the service layer.
 */
trait WebinoCRM_Ajax_Service_Delegate {

	/**
	 * @param callable            $service Callable( array $params ): array.
	 * @param array<string,mixed> $params  Request params.
	 * @return void
	 */
	protected function emit_service( $service, array $params = array() ) {
		if ( empty( $params ) ) {
			$params = WebinoCRM_Service_Base::post_params();
		}
		WebinoCRM_Service_Base::emit_json( call_user_func( $service, $params ) );
	}

	/**
	 * Stream/download handlers (CSV, etc.) — service may exit without returning JSON.
	 *
	 * @param callable            $service Callable( array $params ): array|null.
	 * @param array<string,mixed> $params  Request params (defaults to $_GET).
	 * @return void
	 */
	protected function emit_stream( $service, array $params = array() ) {
		if ( empty( $params ) ) {
			$params = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( $_GET as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$params[ $key ] = wp_unslash( (string) $value );
				}
			}
		}
		$result = call_user_func( $service, $params );
		if ( is_array( $result ) ) {
			WebinoCRM_Service_Base::emit_json( $result );
		}
	}

	/**
	 * @return void
	 */
	protected function verify_ajax_or_die() {
		if ( ! WebinoCRM_Service_Base::verify_ajax_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'خطای امنیتی.', 'webinocrm' ) ) );
		}
	}
}
