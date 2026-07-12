<?php

namespace WebinaBaleBusiness\Api;

use WebinaBaleBusiness\Bot\Router;
use WebinaBaleBusiness\Support\Logger;

class WebhookController {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'webinocrm/v1',
			'/bale/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function webhook_url(): string {
		return rest_url( 'webinocrm/v1/bale/webhook' );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			Logger::log( 'error', 'webhook_invalid_payload', array() );
			return new \WP_REST_Response( array( 'ok' => false, 'error' => 'invalid_payload' ), 400 );
		}

		$update_id = isset( $data['update_id'] ) ? (int) $data['update_id'] : 0;
		if ( $update_id > 0 && $this->is_duplicate( $update_id ) ) {
			Logger::log( 'info', 'webhook_duplicate_update', array( 'update_id' => $update_id ) );
			return new \WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
		}

		try {
			Router::instance()->dispatch( $data );
		} catch ( \Throwable $throwable ) {
			Logger::log(
				'error',
				'webhook_dispatch_exception',
				array(
					'message' => $throwable->getMessage(),
					'file'    => $throwable->getFile(),
					'line'    => $throwable->getLine(),
				)
			);
			return new \WP_REST_Response( array( 'ok' => false, 'error' => 'dispatch_failed' ), 500 );
		}
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function is_duplicate( int $update_id ): bool {
		$key = 'wbb_seen_update_' . $update_id;
		if ( get_transient( $key ) ) {
			return true;
		}
		set_transient( $key, 1, DAY_IN_SECONDS );
		return false;
	}
}
