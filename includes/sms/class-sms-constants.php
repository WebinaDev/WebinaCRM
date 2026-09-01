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

	/**
	 * Built-in default event keys (sites may add more via event_catalog).
	 *
	 * @return array<int,string>
	 */
	public static function order_event_keys() {
		return array(
			'pending_on_create',
			'pending_on_status',
			'on-hold',
			'processing',
			'packaged',
			'sent-to-warehouse',
			'courier',
			'post',
			'tipax',
			'completed',
			'cancelled',
			'failed',
			'refunded',
			'checkout-draft',
			'cart-abandoned',
			'order-abandoned',
			'user-welcome',
			'stock-low',
			'stock-out',
			'pos-payment-link',
			'return-requested',
			'return-approved',
			'return-rejected',
			'return-parcel-received',
			'return-refund',
			'return-exchange',
		);
	}

	/**
	 * Map legacy / alias event keys to canonical shop toggle keys.
	 *
	 * @param string $event_key Event key.
	 * @return string
	 */
	public static function normalize_event_key( $event_key ) {
		$key = sanitize_key( (string) $event_key );
		if ( '' === $key ) {
			return '';
		}
		if ( 'post-barcode' === $key ) {
			return 'post';
		}

		$aliases = array(
			'pws-packaged'            => 'packaged',
			'pws-packed'              => 'packaged',
			'pws-courier'             => 'courier',
			'pws-peyk'                => 'courier',
			'pws-pik'                 => 'courier',
			'pws-post'                => 'post',
			'pws-postal'              => 'post',
			'pws-tipax'               => 'tipax',
			'pws-sent-to-warehouse'   => 'sent-to-warehouse',
			'pws-sent_to_warehouse'   => 'sent-to-warehouse',
			'pws-warehouse'           => 'sent-to-warehouse',
			'packed'                  => 'packaged',
			'packing'                 => 'packaged',
			'package'                 => 'packaged',
			'packaging'               => 'packaged',
			'peyk'                    => 'courier',
			'pik'                     => 'courier',
			'delivery-courier'        => 'courier',
			'courier-delivery'        => 'courier',
			'courier_delivery'        => 'courier',
			'postal'                  => 'post',
			'post-office'             => 'post',
			'post_office'             => 'post',
			'postage'                 => 'post',
			'tipax-delivery'          => 'tipax',
			'tipax_delivery'          => 'tipax',
			'sent_to_warehouse'       => 'sent-to-warehouse',
			'sent-warehouse'          => 'sent-to-warehouse',
			'sent_warehouse'          => 'sent-to-warehouse',
			'warehouse'               => 'sent-to-warehouse',
			'to-warehouse'            => 'sent-to-warehouse',
			'to_warehouse'            => 'sent-to-warehouse',
		);

		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ];
		}
		if ( str_starts_with( $key, 'pws-' ) ) {
			$stripped = substr( $key, 4 );
			if ( isset( $aliases[ $stripped ] ) ) {
				return $aliases[ $stripped ];
			}
			if ( in_array( $stripped, self::order_event_keys(), true ) ) {
				return $stripped;
			}
		}
		return $key;
	}

	/**
	 * Resolve customer/admin toggles for a canonical event (merges alias keys).
	 *
	 * @param array<string,mixed> $shop Shop settings.
	 * @param string              $event_key Event key.
	 * @return array{customer:bool,admin:bool}
	 */
	public static function resolve_event_toggles( array $shop, $event_key ) {
		$event_key = self::normalize_event_key( $event_key );
		$events    = isset( $shop['events'] ) && is_array( $shop['events'] ) ? $shop['events'] : array();

		if ( array_key_exists( $event_key, $events ) && is_array( $events[ $event_key ] ) ) {
			return array(
				'customer' => true === (bool) ( $events[ $event_key ]['customer'] ?? false ),
				'admin'    => true === (bool) ( $events[ $event_key ]['admin'] ?? false ),
			);
		}

		$customer = false;
		$admin    = false;

		foreach ( $events as $key => $toggle ) {
			if ( ! is_array( $toggle ) ) {
				continue;
			}
			if ( self::normalize_event_key( (string) $key ) !== $event_key ) {
				continue;
			}
			if ( true === (bool) ( $toggle['customer'] ?? false ) ) {
				$customer = true;
			}
			if ( true === (bool) ( $toggle['admin'] ?? false ) ) {
				$admin = true;
			}
		}

		return array(
			'customer' => $customer,
			'admin'    => $admin,
		);
	}

	/**
	 * Whether an event_key is a valid SMS event slug.
	 *
	 * @param string $event_key Event key.
	 * @return bool
	 */
	public static function is_valid_event_key( $event_key ) {
		$event_key = sanitize_key( (string) $event_key );
		return '' !== $event_key && (bool) preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $event_key );
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
			'enabled'               => true,
			'sender_line_service'   => '',
			'sender_line_dedicated' => '',
			'otp_login_enabled'     => true,
			'otp_register_enabled'  => true,
			'otp_expiry_minutes'    => 5,
			'otp_max_attempts'      => 3,
			'otp_length'            => 6,
			'otp_login_template'    => __( 'Code: {code}', 'webinocrm' ),
			'otp_register_template' => __( 'Registration code: {code}', 'webinocrm' ),
			'use_pattern_for_otp'   => false,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_shop_settings() {
		return array(
			'enabled'               => true,
			'admin_phones'          => array(),
			'bot_ids'               => array(),
			'sender_line_service'   => '',
			'sender_line_dedicated' => '',
			'events'                => self::default_shop_event_toggles(),
			'event_catalog'         => array(),
			'use_service_line'      => true,
			'require_pattern'       => true,
			'tracking_urls'         => array(
				'post'    => 'https://tracking.post.ir/?id={code}',
				'tipax'   => 'https://tipaxco.com/tracking?code={code}',
				'courier' => '',
			),
			'newsletter'            => array(
				'enabled'          => false,
				'message_template' => __( 'New update for {product_name}: {site_name}', 'webinocrm' ),
				'pattern_code'     => '',
			),
			'recovery'              => array(
				'cart-abandoned'  => array(
					'delay_hours'  => 24,
					'enabled'      => false,
					'type'         => 'percent',
					'amount'       => 10,
					'expires_days' => 7,
					'usage_limit'  => 1,
				),
				'order-abandoned' => array(
					'delay_hours'  => 24,
					'enabled'      => false,
					'type'         => 'percent',
					'amount'       => 10,
					'expires_days' => 7,
					'usage_limit'  => 1,
				),
				'cancelled'       => array(
					'enabled'      => false,
					'type'         => 'percent',
					'amount'       => 10,
					'expires_days' => 7,
					'usage_limit'  => 1,
				),
				'failed'          => array(
					'enabled'      => false,
					'type'         => 'percent',
					'amount'       => 10,
					'expires_days' => 7,
					'usage_limit'  => 1,
				),
				'user-welcome'    => array(
					'enabled'      => false,
					'type'         => 'percent',
					'amount'       => 10,
					'expires_days' => 7,
					'usage_limit'  => 1,
				),
			),
		);
	}

	/**
	 * Resolve event keys for a domain (catalog + defaults + toggles).
	 *
	 * @param array<string,mixed> $shop Shop settings.
	 * @return array<int,string>
	 */
	public static function resolve_event_keys( array $shop ) {
		$keys = self::order_event_keys();
		if ( ! empty( $shop['event_catalog'] ) && is_array( $shop['event_catalog'] ) ) {
			foreach ( $shop['event_catalog'] as $row ) {
				$key = is_array( $row ) ? sanitize_key( (string) ( $row['key'] ?? '' ) ) : sanitize_key( (string) $row );
				if ( self::is_valid_event_key( $key ) ) {
					$keys[] = $key;
				}
			}
		}
		if ( ! empty( $shop['events'] ) && is_array( $shop['events'] ) ) {
			foreach ( array_keys( $shop['events'] ) as $key ) {
				$key = sanitize_key( (string) $key );
				if ( self::is_valid_event_key( $key ) ) {
					$keys[] = $key;
				}
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * @return array<int,array{key:string,label:string,scope:string}>
	 */
	public static function shortcodes() {
		return array(
			array( 'key' => 'mobile', 'label' => 'شماره موبایل مشتری', 'scope' => 'order' ),
			array( 'key' => 'customer_phone', 'label' => 'شماره تلفن مشتری', 'scope' => 'order' ),
			array( 'key' => 'customer_email', 'label' => 'ایمیل مشتری', 'scope' => 'order' ),
			array( 'key' => 'status', 'label' => 'وضعیت سفارش', 'scope' => 'order' ),
			array( 'key' => 'status_label', 'label' => 'برچسب وضعیت سفارش', 'scope' => 'order' ),
			array( 'key' => 'items', 'label' => 'محصولات سفارش', 'scope' => 'order' ),
			array( 'key' => 'all_items', 'label' => 'محصولات سفارش', 'scope' => 'order' ),
			array( 'key' => 'all_items_full', 'label' => 'محصولات سفارش با نام کامل متغیر', 'scope' => 'order' ),
			array( 'key' => 'items_qty', 'label' => 'محصولات سفارش بهمراه تعداد', 'scope' => 'order' ),
			array( 'key' => 'count_items', 'label' => 'تعداد محصولات سفارش', 'scope' => 'order' ),
			array( 'key' => 'total', 'label' => 'مبلغ سفارش', 'scope' => 'order' ),
			array( 'key' => 'price', 'label' => 'مبلغ سفارش', 'scope' => 'order' ),
			array( 'key' => 'total_amount', 'label' => 'مبلغ سفارش بدون واحد', 'scope' => 'order' ),
			array( 'key' => 'price_amount', 'label' => 'مبلغ سفارش بدون واحد', 'scope' => 'order' ),
			array( 'key' => 'order_id', 'label' => 'شماره سفارش اصلی', 'scope' => 'order' ),
			array( 'key' => 'order_number', 'label' => 'شماره سفارش', 'scope' => 'order' ),
			array( 'key' => 'transaction_id', 'label' => 'شماره تراکنش', 'scope' => 'order' ),
			array( 'key' => 'order_date', 'label' => 'تاریخ سفارش', 'scope' => 'order' ),
			array( 'key' => 'description', 'label' => 'توضیحات مشتری', 'scope' => 'order' ),
			array( 'key' => 'payment_method', 'label' => 'روش پرداخت', 'scope' => 'order' ),
			array( 'key' => 'shipping_method', 'label' => 'روش ارسال', 'scope' => 'order' ),
			array( 'key' => 'payment_url', 'label' => 'لینک پرداخت', 'scope' => 'order' ),
			array( 'key' => 'customer_name', 'label' => 'نام مشتری', 'scope' => 'order' ),
			array( 'key' => 'b_first_name', 'label' => 'نام مشتری', 'scope' => 'order' ),
			array( 'key' => 'b_last_name', 'label' => 'نام خانوادگی مشتری', 'scope' => 'order' ),
			array( 'key' => 'b_company', 'label' => 'نام شرکت', 'scope' => 'order' ),
			array( 'key' => 'b_country', 'label' => 'کشور', 'scope' => 'order' ),
			array( 'key' => 'b_state', 'label' => 'ایالت/استان', 'scope' => 'order' ),
			array( 'key' => 'b_city', 'label' => 'شهر', 'scope' => 'order' ),
			array( 'key' => 'b_address_1', 'label' => 'آدرس 1', 'scope' => 'order' ),
			array( 'key' => 'b_address_2', 'label' => 'آدرس 2', 'scope' => 'order' ),
			array( 'key' => 'b_postcode', 'label' => 'کد پستی', 'scope' => 'order' ),
			array( 'key' => 'sh_first_name', 'label' => 'نام مشتری (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_last_name', 'label' => 'نام خانوادگی مشتری (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_company', 'label' => 'نام شرکت (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_country', 'label' => 'کشور (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_state', 'label' => 'ایالت/استان (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_city', 'label' => 'شهر (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_address_1', 'label' => 'آدرس 1 (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_address_2', 'label' => 'آدرس 2 (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'sh_postcode', 'label' => 'کد پستی (حمل و نقل)', 'scope' => 'order' ),
			array( 'key' => 'billing_address', 'label' => 'آدرس صورتحساب', 'scope' => 'order' ),
			array( 'key' => 'shipping_address', 'label' => 'آدرس حمل و نقل', 'scope' => 'order' ),
			array( 'key' => 'tracking', 'label' => 'کد پیگیری', 'scope' => 'order' ),
			array( 'key' => 'tracking_code', 'label' => 'کد رهگیری', 'scope' => 'order' ),
			array( 'key' => 'tracking_url', 'label' => 'لینک رهگیری', 'scope' => 'order' ),
			array( 'key' => 'barcode', 'label' => 'بارکد پستی', 'scope' => 'order' ),
			array( 'key' => 'coupon', 'label' => 'کد تخفیف اختصاصی', 'scope' => 'order' ),
			array( 'key' => 'coupon_code', 'label' => 'کد تخفیف اختصاصی', 'scope' => 'order' ),
			array( 'key' => 'coupon_amount', 'label' => 'مقدار تخفیف', 'scope' => 'recovery' ),
			array( 'key' => 'coupon_type', 'label' => 'نوع تخفیف', 'scope' => 'recovery' ),
			array( 'key' => 'coupon_type_label', 'label' => 'برچسب نوع تخفیف', 'scope' => 'recovery' ),
			array( 'key' => 'coupon_expires', 'label' => 'تاریخ انقضای کدتخفیف', 'scope' => 'recovery' ),
			array( 'key' => 'coupon_expires_days', 'label' => 'اعتبار کدتخفیف (روز)', 'scope' => 'recovery' ),
			array( 'key' => 'coupon_usage_limit', 'label' => 'حد مصرف کدتخفیف', 'scope' => 'recovery' ),
			array( 'key' => 'coupon_discount_text', 'label' => 'متن تخفیف', 'scope' => 'recovery' ),
			array( 'key' => 'return_item', 'label' => 'نام کالای مرجوعی', 'scope' => 'order' ),
			array( 'key' => 'return_qty', 'label' => 'تعداد مرجوعی', 'scope' => 'order' ),
			array( 'key' => 'return_reason', 'label' => 'دلیل مرجوعی', 'scope' => 'order' ),
			array( 'key' => 'return_status', 'label' => 'وضعیت مرجوعی', 'scope' => 'order' ),
			array( 'key' => 'site_url', 'label' => 'آدرس سایت', 'scope' => 'all' ),
			array( 'key' => 'code', 'label' => 'کد یکبار مصرف', 'scope' => 'otp' ),
			array( 'key' => 'product_name', 'label' => 'نام محصول', 'scope' => 'stock' ),
			array( 'key' => 'product_url', 'label' => 'لینک محصول', 'scope' => 'stock' ),
			array( 'key' => 'qty', 'label' => 'موجودی', 'scope' => 'stock' ),
			array( 'key' => 'stock_quantity', 'label' => 'موجودی انبار', 'scope' => 'stock' ),
			array( 'key' => 'low_stock_amount', 'label' => 'آستانه موجودی کم', 'scope' => 'stock' ),
		);
	}

	/**
	 * @param string $event_key Event key.
	 * @return string
	 */
	public static function default_customer_template( $event_key ) {
		$event_key = self::normalize_event_key( $event_key );
		return self::customer_body_for_event( $event_key );
	}

	/**
	 * @param string $event_key Event key.
	 * @return string
	 */
	public static function default_admin_template( $event_key ) {
		$event_key = self::normalize_event_key( $event_key );
		return self::admin_body_for_event( $event_key );
	}

	/**
	 * @param string $body Template body.
	 * @return bool
	 */
	public static function is_legacy_generic_customer_template( $body ) {
		$body = trim( (string) $body );
		return in_array(
			$body,
			array(
				'Order {order_id}: status updated to {status_label}.',
				__( 'Order {order_id}: status updated to {status_label}.', 'webinocrm' ),
			),
			true
		);
	}

	/**
	 * @param string $body Template body.
	 * @param string $event_key Event key.
	 * @return bool
	 */
	public static function is_legacy_generic_admin_template( $body, $event_key = '' ) {
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return false;
		}
		if ( preg_match( '/^Admin alert — order \{order_number\} \(\{status_label\}\)\. Event:/', $body ) ) {
			return true;
		}
		$event_key = sanitize_key( (string) $event_key );
		if ( '' !== $event_key ) {
			$legacy = sprintf(
				__( 'Admin alert — order {order_number} ({status_label}). Event: %s', 'webinocrm' ),
				$event_key
			);
			if ( $body === $legacy ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $event_key Canonical event key.
	 * @return string
	 */
	private static function customer_body_for_event( $event_key ) {
		$map = array(
			'pending_on_create' => 'سفارش شماره {order_number} ثبت شد و در انتظار پرداخت است. مبلغ: {total}',
			'pending_on_status' => 'سفارش شماره {order_number} در انتظار پرداخت است.',
			'on-hold'           => 'سفارش شماره {order_number} در انتظار بررسی است.',
			'processing'        => 'سفارش شماره {order_number} در حال انجام است.',
			'packaged'          => 'سفارش شماره {order_number} بسته‌بندی شد.',
			'sent-to-warehouse' => 'سفارش شماره {order_number} به انبار ارسال شد.',
			'courier'           => 'سفارش شماره {order_number} تحویل پیک شد.',
			'post'              => 'سفارش شماره {order_number} تحویل پست شد. بارکد: {barcode}',
			'tipax'             => 'سفارش شماره {order_number} تحویل تیپاکس شد.',
			'completed'         => 'سفارش شماره {order_number} تکمیل شد.',
			'cancelled'         => 'سفارش شماره {order_number} لغو شد.',
			'failed'            => 'پرداخت سفارش شماره {order_number} ناموفق بود.',
			'refunded'          => 'مبلغ سفارش شماره {order_number} مسترد شد.',
			'checkout-draft'    => 'پیش‌نویس سفارش شماره {order_number} ثبت شد.',
			'cart-abandoned'    => 'سبد خرید شما در {site_name} هنوز تکمیل نشده است.',
			'order-abandoned'   => 'سفارش شماره {order_number} هنوز پرداخت نشده است. مبلغ: {total}',
			'user-welcome'      => 'به {site_name} خوش آمدید!',
			'stock-low'         => 'موجودی محصول «{product_name}» رو به اتمام است.',
			'stock-out'         => 'محصول «{product_name}» ناموجود شد.',
			'pos-payment-link'  => 'سفارش در {site_name} ثبت شد. برای تکمیل وارد {payment_url} شوید.',
			'return-requested'  => 'درخواست مرجوعی برای سفارش {order_number} — {return_item} ×{return_qty}',
			'return-approved'   => 'مرجوعی سفارش {order_number} تأیید شد. {return_item}',
			'return-rejected'   => 'مرجوعی سفارش {order_number} رد شد. {return_item}',
			'return-parcel-received' => 'مرسوله مرجوعی سفارش {order_number} دریافت شد.',
			'return-refund'     => 'مبلغ مرجوعی سفارش {order_number} مسترد شد. {return_item}',
			'return-exchange'   => 'تعویض کالا برای سفارش {order_number} ثبت شد. {return_item}',
		);
		if ( isset( $map[ $event_key ] ) ) {
			return $map[ $event_key ];
		}
		return 'سفارش شماره {order_number} به‌روزرسانی شد. وضعیت: {status_label}';
	}

	/**
	 * @param string $event_key Canonical event key.
	 * @return string
	 */
	private static function admin_body_for_event( $event_key ) {
		switch ( $event_key ) {
			case 'stock-low':
				return 'موجودی محصول «{product_name}» کم است.';
			case 'stock-out':
				return 'محصول «{product_name}» ناموجود شد.';
			case 'user-welcome':
				return 'کاربر جدید در {site_name} ثبت‌نام کرد: {customer_name}';
			case 'cart-abandoned':
				return 'سبد خرید رها شده — مشتری: {customer_name}';
			case 'pending_on_create':
				return 'سفارش جدید شماره {order_number} از {customer_name} — در انتظار پرداخت. مبلغ: {total}';
			default:
				return 'سفارش شماره {order_number} از {customer_name} — وضعیت: {status_label}. مبلغ: {total}';
		}
	}
}
