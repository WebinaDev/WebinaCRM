<?php

namespace WebinaBaleBusiness\Bot;

use WebinaBaleBusiness\Analytics\EventLogger;
use WebinaBaleBusiness\Automation\Engine as AutomationEngine;
use WebinaBaleBusiness\Woo\PaymentService;

class Router {

	private static ?self $instance = null;
	private SalesFlow $flow;
	private PaymentService $payments;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->flow     = new SalesFlow();
		$this->payments = new PaymentService();
	}

	public function init(): void {}

	/**
	 * @param array<string,mixed> $update
	 */
	public function dispatch( array $update ): void {
		if ( isset( $update['message'] ) && is_array( $update['message'] ) ) {
			$this->on_message( $update['message'] );
			return;
		}
		if ( isset( $update['callback_query'] ) && is_array( $update['callback_query'] ) ) {
			$this->on_callback( $update['callback_query'] );
			return;
		}
		if ( isset( $update['pre_checkout_query'] ) && is_array( $update['pre_checkout_query'] ) ) {
			$this->payments->answer_pre_checkout( $update['pre_checkout_query'] );
			return;
		}
	}

	/**
	 * @param array<string,mixed> $message
	 */
	private function on_message( array $message ): void {
		$chat_id = (string) ( $message['chat']['id'] ?? '' );
		if ( $chat_id === '' ) {
			return;
		}

		if ( isset( $message['successful_payment'] ) && is_array( $message['successful_payment'] ) ) {
			$order_id = $this->payments->finalize_successful_payment( $message['successful_payment'] );
			EventLogger::log( $chat_id, 'payment_success', array( 'order_id' => $order_id ) );
			$this->flow->on_payment_success( $chat_id, $order_id );
			return;
		}

		$text = trim( (string) ( $message['text'] ?? '' ) );
		if ( $text !== '' ) {
			AutomationEngine::ingest_event( $chat_id, 'reply', array( 'text' => mb_substr( $text, 0, 180 ) ) );
		}
		if ( $text === '/start' ) {
			$this->flow->start( $chat_id );
			return;
		}
		if ( $text !== '' && $this->flow->handle_text_action( $chat_id, $text ) ) {
			return;
		}
		$this->flow->handle_user_message( $chat_id, $message );
	}

	/**
	 * @param array<string,mixed> $callback
	 */
	private function on_callback( array $callback ): void {
		$chat_id = (string) ( $callback['message']['chat']['id'] ?? '' );
		$data    = (string) ( $callback['data'] ?? '' );
		$id      = (string) ( $callback['id'] ?? '' );
		if ( $chat_id !== '' && $data !== '' ) {
			$this->flow->handle_callback( $chat_id, $data, $id );
		}
	}
}
