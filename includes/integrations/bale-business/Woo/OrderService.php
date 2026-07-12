<?php

namespace WebinaBaleBusiness\Woo;

class OrderService {

	/**
	 * @return array{order_id:int,payment_url:string}|null
	 */
	public function create_order_for_chat( string $chat_id, string $plan_key = '', int $business_id = 0 ): ?array {
		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_create_order' ) ) {
			return null;
		}

		$settings   = \WebinaBaleBusiness\Core\Plugin::get_settings();
		$product_id = (int) ( $settings['sales_wc_product_id'] ?? 0 );
		if ( $plan_key !== '' && isset( $settings['plan_product_map'][ $plan_key ] ) ) {
			$product_id = \absint( $settings['plan_product_map'][ $plan_key ] );
		}
		if ( $product_id <= 0 ) {
			return null;
		}

		$product = \wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$order = \wc_create_order();
		$order->add_product( $product, 1 );
		$order->update_meta_data( '_webina_bale_source', 1 );
		$order->update_meta_data( '_webina_bale_chat_id', $chat_id );
		$order->update_meta_data( '_webina_bale_lead_stage', 'order_created' );
		$order->update_meta_data( '_webina_bale_plan_key', $plan_key );
		if ( $business_id > 0 ) {
			$order->update_meta_data( '_webina_bale_business_id', $business_id );
		}
		$order->calculate_totals();
		$order->save();

		return array(
			'order_id'    => $order->get_id(),
			'payment_url' => $order->get_checkout_payment_url( true ),
		);
	}
}
