<?php
/**
 * SMS message templates per domain.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template CRUD and shortcode rendering.
 */
final class WebinoCRM_Sms_Template_Service {

	/**
	 * @param string $domain Domain.
	 * @param string $scope Optional filter.
	 * @param string $event_key Optional filter.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list( $domain, $scope = '', $event_key = '' ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$table  = WebinoCRM_Sms_Install::table( 'message_templates' );
		$sql    = "SELECT * FROM $table WHERE domain = %s";
		$args   = array( $domain );
		if ( '' !== $scope ) {
			$sql   .= ' AND scope = %s';
			$args[] = sanitize_key( $scope );
		}
		if ( '' !== $event_key ) {
			$sql   .= ' AND event_key = %s';
			$args[] = sanitize_key( $event_key );
		}
		$sql .= ' ORDER BY scope ASC, event_key ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A ) ?: array();
		return array_map( array( __CLASS__, 'normalize_template_row' ), $rows );
	}

	/**
	 * @param string $domain Domain.
	 * @param string $scope Template scope.
	 * @param string $event_key Event key.
	 * @return array<string,mixed>|null
	 */
	public static function get_one( $domain, $scope, $event_key ) {
		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'message_templates' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE domain = %s AND scope = %s AND event_key = %s LIMIT 1",
				WebinoCRM_License_Manager::normalize_domain( $domain ),
				sanitize_key( $scope ),
				sanitize_key( $event_key )
			),
			ARRAY_A
		);
		return $row ? self::normalize_template_row( $row ) : null;
	}

	/**
	 * Normalize template row param_map JSON → array.
	 *
	 * @param array<string,mixed>|null $row Row.
	 * @return array<string,mixed>|null
	 */
	public static function normalize_template_row( $row ) {
		if ( ! is_array( $row ) ) {
			return $row;
		}
		$row['param_map'] = self::decode_param_map( $row['param_map'] ?? null );
		return $row;
	}

	/**
	 * @param string              $domain Domain.
	 * @param array<int,array<string,mixed>> $templates Rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function save_bulk( $domain, array $templates ) {
		$saved = array();
		foreach ( $templates as $tpl ) {
			if ( ! is_array( $tpl ) ) {
				continue;
			}
			$scope     = sanitize_key( (string) ( $tpl['scope'] ?? '' ) );
			$event_key = sanitize_key( (string) ( $tpl['event_key'] ?? '' ) );
			$body      = sanitize_textarea_field( (string) ( $tpl['body'] ?? '' ) );
			if ( '' === $scope || '' === $event_key ) {
				continue;
			}
			$saved[] = self::upsert(
				$domain,
				$scope,
				$event_key,
				$body,
				! empty( $tpl['enabled'] ),
				isset( $tpl['pattern_code'] ) ? sanitize_text_field( (string) $tpl['pattern_code'] ) : null,
				isset( $tpl['param_map'] ) ? $tpl['param_map'] : null
			);
		}
		return $saved;
	}

	/**
	 * @param string      $domain Domain.
	 * @param string      $scope Scope.
	 * @param string      $event_key Event.
	 * @param string      $body Body.
	 * @param bool        $enabled Enabled.
	 * @param string|null $pattern_code Pattern code.
	 * @param mixed       $param_map Param map array|string|null (null = leave unchanged).
	 * @return array<string,mixed>
	 */
	public static function upsert( $domain, $scope, $event_key, $body, $enabled = true, $pattern_code = null, $param_map = null ) {
		global $wpdb;
		$domain    = WebinoCRM_License_Manager::normalize_domain( $domain );
		$scope     = sanitize_key( $scope );
		$event_key = sanitize_key( $event_key );
		$table     = WebinoCRM_Sms_Install::table( 'message_templates' );
		$existing  = self::get_one( $domain, $scope, $event_key );
		$data      = array(
			'body'         => $body,
			'enabled'      => $enabled ? 1 : 0,
			'pattern_code' => $pattern_code,
			'updated_at'   => current_time( 'mysql' ),
		);
		if ( null !== $param_map ) {
			$data['param_map'] = self::encode_param_map( $param_map );
		}
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			$out = array_merge( $existing, $data );
			$out['param_map'] = self::decode_param_map( $out['param_map'] ?? null );
			return $out;
		}
		if ( ! isset( $data['param_map'] ) ) {
			$data['param_map'] = null;
		}
		$wpdb->insert(
			$table,
			array_merge(
				$data,
				array(
					'domain'    => $domain,
					'scope'     => $scope,
					'event_key' => $event_key,
				)
			)
		);
		$out = array_merge( $data, array( 'id' => (int) $wpdb->insert_id, 'domain' => $domain, 'scope' => $scope, 'event_key' => $event_key ) );
		$out['param_map'] = self::decode_param_map( $out['param_map'] ?? null );
		return $out;
	}

	/**
	 * @param mixed $raw Raw.
	 * @return array<string,string>
	 */
	public static function decode_param_map( $raw ) {
		if ( is_array( $raw ) ) {
			$out = array();
			foreach ( $raw as $k => $v ) {
				$k = sanitize_key( (string) $k );
				if ( '' === $k ) {
					continue;
				}
				$out[ $k ] = is_string( $v ) ? $v : (string) $v;
			}
			return $out;
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? self::decode_param_map( $decoded ) : array();
	}

	/**
	 * @param mixed $map Map.
	 * @return string|null
	 */
	public static function encode_param_map( $map ) {
		$clean = self::decode_param_map( $map );
		if ( ! $clean ) {
			return null;
		}
		return wp_json_encode( $clean );
	}

	/**
	 * Human-readable pcode binding preview.
	 *
	 * @param string               $code Code.
	 * @param array<string,string> $param_map Map.
	 * @return string
	 */
	public static function format_param_map_preview( $code, array $param_map ) {
		$lines = array( 'pcode:' . sanitize_text_field( (string) $code ) );
		foreach ( $param_map as $pattern_var => $site_expr ) {
			$lines[] = $pattern_var . ':' . $site_expr;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Build Edge pattern params from stored param_map (preferred) or template body.
	 *
	 * @param array<string,string> $param_map Map patternVar => "{site_var} …".
	 * @param string               $template Fallback template.
	 * @param array<string,string> $vars Vars.
	 * @return array<string,string>
	 */
	public static function pattern_params_from_map( array $param_map, $template, array $vars ) {
		if ( $param_map ) {
			$params = array();
			foreach ( $param_map as $pattern_var => $expr ) {
				$params[ $pattern_var ] = self::render( (string) $expr, $vars );
			}
			return $params;
		}
		return self::pattern_params_from_template( $template, $vars );
	}

	/**
	 * Ensure default order templates exist for a domain.
	 *
	 * @param string        $domain Domain.
	 * @param array<int,string>|null $event_keys Optional event keys (defaults + catalog).
	 * @return void
	 */
	public static function seed_order_defaults( $domain, $event_keys = null ) {
		if ( ! is_array( $event_keys ) || ! $event_keys ) {
			$shop       = WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP );
			$event_keys = WebinoCRM_Sms_Constants::resolve_event_keys( $shop );
		}
		foreach ( $event_keys as $event_key ) {
			$event_key = sanitize_key( (string) $event_key );
			if ( ! WebinoCRM_Sms_Constants::is_valid_event_key( $event_key ) ) {
				continue;
			}
			$canonical = WebinoCRM_Sms_Constants::normalize_event_key( $event_key );
			$customer_default = WebinoCRM_Sms_Constants::default_customer_template( $canonical );
			$admin_default    = WebinoCRM_Sms_Constants::default_admin_template( $canonical );
			$customer_row     = self::get_one( $domain, WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER, $event_key );
			if ( ! $customer_row ) {
				self::upsert(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER,
					$event_key,
					$customer_default
				);
			} elseif ( WebinoCRM_Sms_Constants::is_legacy_generic_customer_template( (string) ( $customer_row['body'] ?? '' ) ) ) {
				self::upsert(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER,
					$event_key,
					$customer_default
				);
			}
			$admin_row = self::get_one( $domain, WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN, $event_key );
			if ( ! $admin_row ) {
				self::upsert(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN,
					$event_key,
					$admin_default
				);
			} elseif ( WebinoCRM_Sms_Constants::is_legacy_generic_admin_template( (string) ( $admin_row['body'] ?? '' ), $event_key ) ) {
				self::upsert(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN,
					$event_key,
					$admin_default
				);
			}
		}
	}

	/**
	 * @param string              $template Template with {tags}.
	 * @param array<string,string> $vars Variables.
	 * @return string
	 */
	public static function render( $template, array $vars ) {
		$out = (string) $template;
		foreach ( $vars as $key => $value ) {
			$out = str_replace( '{' . $key . '}', (string) $value, $out );
		}
		return $out;
	}

	/**
	 * @param string              $template Template.
	 * @param array<string,string> $vars Vars.
	 * @return array<string,string>
	 */
	public static function pattern_params_from_template( $template, array $vars ) {
		if ( ! preg_match_all( '/\{([a-zA-Z0-9_]+)\}/', (string) $template, $m ) ) {
			return array();
		}
		// Common IPPanel aliases used by merchant-approved patterns.
		if ( ! isset( $vars['name'] ) && isset( $vars['customer_name'] ) ) {
			$vars['name'] = $vars['customer_name'];
		}
		if ( ! isset( $vars['orderid'] ) ) {
			$vars['orderid'] = (string) ( $vars['order_number'] ?? $vars['order_id'] ?? '' );
		}
		if ( ! isset( $vars['product_title'] ) && isset( $vars['product_name'] ) ) {
			$vars['product_title'] = $vars['product_name'];
		}
		if ( ! isset( $vars['post_tracking_code'] ) ) {
			$vars['post_tracking_code'] = (string) ( $vars['tracking_code'] ?? $vars['tracking'] ?? $vars['barcode'] ?? '' );
		}
		if ( ! isset( $vars['post_tracking_url'] ) ) {
			if ( isset( $vars['tracking_url'] ) && '' !== (string) $vars['tracking_url'] ) {
				$vars['post_tracking_url'] = (string) $vars['tracking_url'];
			} elseif ( isset( $vars['tracking'] ) && '' !== $vars['tracking'] ) {
				$vars['post_tracking_url'] = 'https://tracking.post.ir/?id=' . rawurlencode( $vars['tracking'] );
			}
		}
		$params = array();
		foreach ( array_unique( $m[1] ) as $key ) {
			if ( isset( $vars[ $key ] ) ) {
				$params[ $key ] = (string) $vars[ $key ];
			}
		}
		return $params;
	}

	/**
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( str_starts_with( $digits, '98' ) ) {
			return '+' . $digits;
		}
		if ( str_starts_with( $digits, '0' ) ) {
			return '+98' . substr( $digits, 1 );
		}
		if ( str_starts_with( $digits, '9' ) && 10 === strlen( $digits ) ) {
			return '+98' . $digits;
		}
		return '+' . $digits;
	}

	/**
	 * @param array<string,mixed> $order Order snapshot from dashboard.
	 * @param string              $domain Domain.
	 * @return array<string,string>
	 */
	public static function vars_from_order_snapshot( array $order, $domain ) {
		$phone = (string) ( $order['customer_phone'] ?? $order['mobile'] ?? '' );
		$total = self::sanitize_sms_price( (string) ( $order['total'] ?? $order['price'] ?? '' ) );
		$items = (string) ( $order['items'] ?? $order['all_items'] ?? '' );
		$qty   = (string) ( $order['items_qty'] ?? $order['count_items'] ?? '' );
		$amount = (string) ( $order['total_amount'] ?? $order['price_amount'] ?? '' );
		if ( '' === $amount ) {
			$amount = self::sms_amount_only( $total );
		} else {
			$amount = self::sanitize_sms_price( $amount );
			$amount = self::sms_amount_only( $amount );
		}

		$tracking_code = (string) ( $order['tracking_code'] ?? $order['tracking'] ?? $order['barcode'] ?? '' );
		$tracking_url  = (string) ( $order['tracking_url'] ?? '' );

		$vars = array(
			'order_id'          => (string) ( $order['id'] ?? '' ),
			'order_number'      => (string) ( $order['number'] ?? $order['id'] ?? '' ),
			'customer_name'     => (string) ( $order['customer_name'] ?? '' ),
			'customer_phone'    => $phone,
			'mobile'            => $phone,
			'customer_email'    => (string) ( $order['customer_email'] ?? '' ),
			'total'             => $total,
			'price'             => $total,
			'total_amount'      => $amount,
			'price_amount'      => $amount,
			'status'            => (string) ( $order['status'] ?? '' ),
			'status_label'      => (string) ( $order['status_label'] ?? $order['status'] ?? '' ),
			'status_slug'       => (string) ( $order['status_slug'] ?? '' ),
			'tracking'          => $tracking_code,
			'tracking_code'     => $tracking_code,
			'tracking_url'      => $tracking_url,
			'barcode'           => (string) ( $order['barcode'] ?? $tracking_code ),
			'items'             => $items,
			'all_items'         => (string) ( $order['all_items'] ?? $items ),
			'all_items_full'    => (string) ( $order['all_items_full'] ?? $order['all_items'] ?? $items ),
			'items_qty'         => $qty,
			'count_items'       => (string) ( $order['count_items'] ?? $qty ),
			'payment_method'    => (string) ( $order['payment_method'] ?? '' ),
			'payment_url'       => (string) ( $order['payment_url'] ?? '' ),
			'shipping_method'   => (string) ( $order['shipping_method'] ?? '' ),
			'transaction_id'    => (string) ( $order['transaction_id'] ?? '' ),
			'description'       => (string) ( $order['description'] ?? '' ),
			'billing_address'   => (string) ( $order['billing_address'] ?? '' ),
			'shipping_address'  => (string) ( $order['shipping_address'] ?? '' ),
			'b_first_name'      => (string) ( $order['b_first_name'] ?? '' ),
			'b_last_name'       => (string) ( $order['b_last_name'] ?? '' ),
			'b_company'         => (string) ( $order['b_company'] ?? '' ),
			'b_country'         => (string) ( $order['b_country'] ?? '' ),
			'b_state'           => (string) ( $order['b_state'] ?? '' ),
			'b_city'            => (string) ( $order['b_city'] ?? '' ),
			'b_address_1'       => (string) ( $order['b_address_1'] ?? '' ),
			'b_address_2'       => (string) ( $order['b_address_2'] ?? '' ),
			'b_postcode'        => (string) ( $order['b_postcode'] ?? '' ),
			'sh_first_name'     => (string) ( $order['sh_first_name'] ?? '' ),
			'sh_last_name'      => (string) ( $order['sh_last_name'] ?? '' ),
			'sh_company'        => (string) ( $order['sh_company'] ?? '' ),
			'sh_country'        => (string) ( $order['sh_country'] ?? '' ),
			'sh_state'          => (string) ( $order['sh_state'] ?? '' ),
			'sh_city'           => (string) ( $order['sh_city'] ?? '' ),
			'sh_address_1'      => (string) ( $order['sh_address_1'] ?? '' ),
			'sh_address_2'      => (string) ( $order['sh_address_2'] ?? '' ),
			'sh_postcode'       => (string) ( $order['sh_postcode'] ?? '' ),
			'order_date'        => (string) ( $order['order_date'] ?? '' ),
			'site_name'         => (string) ( $order['site_name'] ?? get_bloginfo( 'name' ) ),
			'site_url'          => (string) ( $order['site_url'] ?? 'https://' . $domain ),
			'product_name'      => (string) ( $order['product_name'] ?? '' ),
			'product_url'       => (string) ( $order['product_url'] ?? '' ),
			'qty'               => (string) ( $order['qty'] ?? $order['stock_quantity'] ?? '' ),
			'stock_quantity'    => (string) ( $order['stock_quantity'] ?? $order['qty'] ?? '' ),
			'low_stock_amount'  => (string) ( $order['low_stock_amount'] ?? '' ),
			'coupon'            => (string) ( $order['coupon'] ?? $order['coupon_code'] ?? '' ),
			'coupon_code'       => (string) ( $order['coupon_code'] ?? $order['coupon'] ?? '' ),
		);

		foreach ( $order as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || isset( $vars[ $key ] ) ) {
				continue;
			}
			if ( is_bool( $value ) ) {
				$vars[ $key ] = $value ? '1' : '';
				continue;
			}
			if ( is_int( $value ) || is_float( $value ) || is_string( $value ) ) {
				$vars[ $key ] = (string) $value;
			}
		}

		return $vars;
	}

	/**
	 * Strip leftover WooCommerce price HTML for SMS pattern vars.
	 *
	 * @param string $value Raw total.
	 * @return string
	 */
	public static function sanitize_sms_price( $value ) {
		$s = wp_strip_all_tags( (string) $value );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( array( '&nbsp;', "\xC2\xA0" ), ' ', $s );
		$s = trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
		if ( '' === $s ) {
			return $s;
		}
		$toman = 'تومان';
		$s     = preg_replace( '/\b(IRT|TOMAN|IRR)\b/iu', '', $s );
		$s     = str_replace( array( '﷼', '$', '€', '£' ), '', (string) $s );
		$s     = trim( (string) preg_replace( '/\s+/u', ' ', (string) $s ) );
		if ( preg_match( '/^(.+?)\s+' . preg_quote( $toman, '/' ) . '\s*$/u', $s, $m ) ) {
			return trim( $m[1] ) . ' ' . $toman;
		}
		if ( preg_match( '/^' . preg_quote( $toman, '/' ) . '\s+(.+)$/u', $s, $m ) ) {
			return trim( $m[1] ) . ' ' . $toman;
		}
		if ( preg_match( '/^(.+?)\s+ریال\s*$/u', $s, $m ) ) {
			return trim( $m[1] ) . ' ' . $toman;
		}
		if ( ! preg_match( '/تومان/u', $s ) && ! preg_match( '/ریال/u', $s ) ) {
			return $s . ' ' . $toman;
		}
		return $s;
	}

	/**
	 * @param string $value Sanitized price text.
	 * @return string
	 */
	public static function sms_amount_only( $value ) {
		$s = trim( (string) $value );
		$s = preg_replace( '/\s+تومان\s*$/u', '', $s );
		$s = preg_replace( '/\s+ریال\s*$/u', '', (string) $s );
		return trim( (string) $s );
	}
}
