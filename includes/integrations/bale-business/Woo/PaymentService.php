<?php

namespace WebinaBaleBusiness\Woo;

use WebinaBaleBusiness\Bale\Client;
use WebinaBaleBusiness\Core\Plugin;
use WebinaBaleBusiness\Util\OrderPayload;

class PaymentService {

	private Client $client;

	public function __construct( ?Client $client = null ) {
		$this->client = $client ?? new Client();
	}

	public function send_invoice( string $chat_id, int $order_id ): bool {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return false;
		}
		$payload = OrderPayload::build( $order_id );
		$token   = Plugin::get_provider_token();
		if ( $token === '' ) {
			return false;
		}
		$result = $this->client->send_invoice(
			array(
				'chat_id'        => $chat_id,
				'title'          => 'خرید ربات فروشگاهی وبینا',
				'description'    => 'ثبت سفارش ربات فروشگاهی',
				'payload'        => $payload,
				'provider_token' => $token,
				'currency'       => 'IRR',
				'prices'         => array(
					array(
						'label'  => 'ربات فروشگاهی وبینا',
						'amount' => (int) round( (float) $order->get_total() ),
					),
				),
			)
		);
		return is_array( $result ) && ( $result['ok'] ?? false );
	}

	/**
	 * @param array<string,mixed> $query
	 */
	public function answer_pre_checkout( array $query ): void {
		$payload = (string) ( $query['invoice_payload'] ?? '' );
		$order_id = OrderPayload::verify( $payload );
		$is_valid = $order_id > 0;
		$error    = 'اطلاعات پرداخت معتبر نیست.';
		if ( $is_valid ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
			if ( ! $order ) {
				$is_valid = false;
			} else {
				$expected = (int) round( (float) $order->get_total() );
				$actual   = isset( $query['total_amount'] ) ? (int) $query['total_amount'] : $expected;
				if ( $actual !== $expected ) {
					$is_valid = false;
					$error    = 'مبلغ پرداخت با سفارش مطابقت ندارد.';
				}
			}
		}
		$this->client->answer_pre_checkout_query(
			array(
				'pre_checkout_query_id' => (string) ( $query['id'] ?? '' ),
				'ok'                    => $is_valid,
				'error_message'         => $is_valid ? '' : $error,
			)
		);
	}

	/**
	 * @param array<string,mixed> $successful_payment
	 */
	public function finalize_successful_payment( array $successful_payment ): int {
		$payload  = (string) ( $successful_payment['invoice_payload'] ?? '' );
		$order_id = OrderPayload::verify( $payload );
		if ( $order_id <= 0 ) {
			return 0;
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( $order && ! $order->is_paid() ) {
			$order->payment_complete();
			$order->update_meta_data( '_webina_bale_lead_stage', 'paid' );
			$order->add_order_note( 'پرداخت کیف پول بله با موفقیت تایید شد.' );
			$order->save();
		}
		return $order_id;
	}
}
