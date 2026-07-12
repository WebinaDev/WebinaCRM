<?php

namespace WebinaBaleBusiness\Bale;

use WebinaBaleBusiness\Core\Plugin;
use WebinaBaleBusiness\Support\Logger;

class Client {

	private string $token;

	public function __construct( ?string $token = null ) {
		$this->token = $token ?? Plugin::get_bot_token();
	}

	private function url( string $method ): string {
		return 'https://tapi.bale.ai/bot' . rawurlencode( $this->token ) . '/' . $method;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|null
	 */
	public function call( string $method, array $payload = array() ): ?array {
		$response = wp_remote_post(
			$this->url( $method ),
			array(
				'timeout' => 12,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			Logger::log(
				'error',
				'bale_api_transport',
				array(
					'method'   => $method,
					'message'  => $response->get_error_message(),
					'hasToken' => $this->token !== '',
				)
			);
			return null;
		}
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			Logger::log(
				'error',
				'bale_api_invalid_json',
				array(
					'method' => $method,
					'code'   => $code,
					'body'   => substr( $raw, 0, 400 ),
				)
			);
			return null;
		}
		if ( $code >= 400 || ( isset( $decoded['ok'] ) && ! $decoded['ok'] ) ) {
			Logger::log(
				'error',
				'bale_api_error',
				array(
					'method'      => $method,
					'code'        => $code,
					'error_code'  => $decoded['error_code'] ?? null,
					'description' => $decoded['description'] ?? '',
				)
			);
		}
		return $decoded;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|null
	 */
	public function send_message( array $payload ): ?array {
		return $this->call( 'sendMessage', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|null
	 */
	public function answer_callback_query( array $payload ): ?array {
		return $this->call( 'answerCallbackQuery', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|null
	 */
	public function send_invoice( array $payload ): ?array {
		return $this->call( 'sendInvoice', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|null
	 */
	public function answer_pre_checkout_query( array $payload ): ?array {
		return $this->call( 'answerPreCheckoutQuery', $payload );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function set_webhook( string $url ): ?array {
		return $this->call( 'setWebhook', array( 'url' => $url ) );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function delete_webhook(): ?array {
		return $this->call( 'deleteWebhook', array() );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_webhook_info(): ?array {
		return $this->call( 'getWebhookInfo', array() );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_chat_member( string $chat_id, string $user_id ): ?array {
		return $this->call(
			'getChatMember',
			array(
				'chat_id' => $chat_id,
				'user_id' => $user_id,
			)
		);
	}
}
