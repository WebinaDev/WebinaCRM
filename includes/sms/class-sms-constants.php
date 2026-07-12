<?php
/**
 * SMS module constants (order events, scopes, defaults).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event keys and default configuration for per-domain SMS.
 */
final class WebinoCRM_Sms_Constants {

	const SCOPE_SITE = 'site';
	const SCOPE_SHOP = 'shop';

	const TPL_ORDER_CUSTOMER = 'order_customer';
	const TPL_ORDER_ADMIN    = 'order_admin';
	const TPL_SITE_OTP       = 'site_otp';
	const TPL_NEWSLETTER     = 'newsletter';

	/** @var array<int,string> */
	public static function order_event_keys() {
		return array(
			'pending_on_create',
			'pending_on_status',
			'processing',
			'sent-to-warehouse',
			'packaged',
			'courier',
			'post',
			'tipax',
			'on-hold',
			'completed',
			'cancelled',
			'refunded',
			'failed',
			'checkout-draft',
			'post-barcode',
			'stock-low',
			'stock-out',
		);
	}

	/**
	 * @return array<string,array{customer:bool,admin:bool}>
	 */
	public static function default_shop_event_toggles() {
		$out = array();
		foreach ( self::order_event_keys() as $key ) {
			$out[ $key ] = array(
				'customer' => false,
				'admin'    => false,
			);
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_site_settings() {
		return array(
			'enabled'                 => true,
			'sender_line_service'     => '',
			'sender_line_dedicated'   => '',
			'otp_login_enabled'       => true,
			'otp_register_enabled'    => true,
			'otp_expiry_minutes'      => 5,
			'otp_max_attempts'        => 3,
			'otp_length'              => 6,
			'otp_login_template'      => __( 'Code: {code}', 'webinocrm' ),
			'otp_register_template'   => __( 'Registration code: {code}', 'webinocrm' ),
			'use_pattern_for_otp'     => false,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_shop_settings() {
		return array(
			'enabled'               => true,
			'admin_phones'          => array(),
			'sender_line_service'   => '',
			'sender_line_dedicated' => '',
			'events'                => self::default_shop_event_toggles(),
			'use_service_line'      => true,
		);
	}

	/**
	 * @return array<int,array{key:string,label:string,scope:string}>
	 */
	public static function shortcodes() {
		return array(
			array( 'key' => 'order_id', 'label' => __( 'Order ID', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'order_number', 'label' => __( 'Order number', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'customer_name', 'label' => __( 'Customer name', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'customer_phone', 'label' => __( 'Customer phone', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'total', 'label' => __( 'Order total', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'status', 'label' => __( 'Order status', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'status_label', 'label' => __( 'Status label', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'tracking', 'label' => __( 'Tracking code', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'barcode', 'label' => __( 'Post barcode', 'webinocrm' ), 'scope' => 'order' ),
			array( 'key' => 'site_name', 'label' => __( 'Site name', 'webinocrm' ), 'scope' => 'all' ),
			array( 'key' => 'site_url', 'label' => __( 'Site URL', 'webinocrm' ), 'scope' => 'all' ),
			array( 'key' => 'code', 'label' => __( 'OTP code', 'webinocrm' ), 'scope' => 'otp' ),
			array( 'key' => 'product_name', 'label' => __( 'Product name', 'webinocrm' ), 'scope' => 'stock' ),
			array( 'key' => 'product_url', 'label' => __( 'Product URL', 'webinocrm' ), 'scope' => 'stock' ),
			array( 'key' => 'qty', 'label' => __( 'Stock quantity', 'webinocrm' ), 'scope' => 'stock' ),
			array( 'key' => 'stock_quantity', 'label' => __( 'Stock quantity', 'webinocrm' ), 'scope' => 'stock' ),
			array( 'key' => 'low_stock_amount', 'label' => __( 'Low stock threshold', 'webinocrm' ), 'scope' => 'stock' ),
		);
	}

	/**
	 * @param string $event_key Event key.
	 * @return string
	 */
	public static function default_customer_template( $event_key ) {
		return sprintf(
			/* translators: %s: order id placeholder */
			__( 'Order {order_id}: status updated to {status_label}.', 'webinocrm' ),
			$event_key
		);
	}

	/**
	 * @param string $event_key Event key.
	 * @return string
	 */
	public static function default_admin_template( $event_key ) {
		return sprintf(
			__( 'Admin alert — order {order_number} ({status_label}). Event: %s', 'webinocrm' ),
			$event_key
		);
	}
}
