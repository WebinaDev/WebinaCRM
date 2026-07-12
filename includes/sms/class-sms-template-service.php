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
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A ) ?: array();
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
		return $row ?: null;
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
				isset( $tpl['pattern_code'] ) ? sanitize_text_field( (string) $tpl['pattern_code'] ) : null
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
	 * @return array<string,mixed>
	 */
	public static function upsert( $domain, $scope, $event_key, $body, $enabled = true, $pattern_code = null ) {
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
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			return array_merge( $existing, $data );
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
		return array_merge( $data, array( 'id' => (int) $wpdb->insert_id, 'domain' => $domain, 'scope' => $scope, 'event_key' => $event_key ) );
	}

	/**
	 * Ensure default order templates exist for a domain.
	 *
	 * @param string $domain Domain.
	 * @return void
	 */
	public static function seed_order_defaults( $domain ) {
		foreach ( WebinoCRM_Sms_Constants::order_event_keys() as $event_key ) {
			if ( ! self::get_one( $domain, WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER, $event_key ) ) {
				self::upsert(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER,
					$event_key,
					WebinoCRM_Sms_Constants::default_customer_template( $event_key )
				);
			}
			if ( ! self::get_one( $domain, WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN, $event_key ) ) {
				self::upsert(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN,
					$event_key,
					WebinoCRM_Sms_Constants::default_admin_template( $event_key )
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
		return array(
			'order_id'       => (string) ( $order['id'] ?? '' ),
			'order_number'   => (string) ( $order['number'] ?? $order['id'] ?? '' ),
			'customer_name'  => (string) ( $order['customer_name'] ?? '' ),
			'customer_phone' => (string) ( $order['customer_phone'] ?? '' ),
			'total'          => (string) ( $order['total'] ?? '' ),
			'status'         => (string) ( $order['status'] ?? '' ),
			'status_label'   => (string) ( $order['status_label'] ?? $order['status'] ?? '' ),
			'tracking'       => (string) ( $order['tracking'] ?? '' ),
			'barcode'        => (string) ( $order['barcode'] ?? '' ),
			'site_name'      => (string) ( $order['site_name'] ?? get_bloginfo( 'name' ) ),
			'site_url'       => (string) ( $order['site_url'] ?? 'https://' . $domain ),
			'product_name'     => (string) ( $order['product_name'] ?? '' ),
			'product_url'      => (string) ( $order['product_url'] ?? '' ),
			'qty'              => (string) ( $order['qty'] ?? $order['stock_quantity'] ?? '' ),
			'stock_quantity'   => (string) ( $order['stock_quantity'] ?? $order['qty'] ?? '' ),
			'low_stock_amount' => (string) ( $order['low_stock_amount'] ?? '' ),
		);
	}
}
