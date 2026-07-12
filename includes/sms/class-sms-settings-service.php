<?php
/**
 * Per-domain SMS settings (site / shop).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for domain_settings JSON blobs.
 */
final class WebinoCRM_Sms_Settings_Service {

	/**
	 * @param string $domain Domain.
	 * @param string $scope site|shop.
	 * @return array<string,mixed>
	 */
	public static function get( $domain, $scope ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$scope  = sanitize_key( $scope );
		$defaults = WebinoCRM_Sms_Constants::SCOPE_SITE === $scope
			? WebinoCRM_Sms_Constants::default_site_settings()
			: WebinoCRM_Sms_Constants::default_shop_settings();

		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'domain_settings' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT settings_json FROM $table WHERE domain = %s AND scope = %s LIMIT 1", $domain, $scope ),
			ARRAY_A
		);
		if ( ! $row || empty( $row['settings_json'] ) ) {
			return $defaults;
		}
		$decoded = json_decode( (string) $row['settings_json'], true );
		if ( ! is_array( $decoded ) ) {
			return $defaults;
		}
		return self::merge_deep( $defaults, $decoded );
	}

	/**
	 * @param string              $domain Domain.
	 * @param string              $scope site|shop.
	 * @param array<string,mixed> $input Partial settings.
	 * @return array<string,mixed>
	 */
	public static function save( $domain, $scope, array $input ) {
		$domain   = WebinoCRM_License_Manager::normalize_domain( $domain );
		$scope    = sanitize_key( $scope );
		$current  = self::get( $domain, $scope );
		$sanitized = WebinoCRM_Sms_Constants::SCOPE_SITE === $scope
			? self::sanitize_site( $input, $current )
			: self::sanitize_shop( $input, $current );

		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'domain_settings' );
		$json  = wp_json_encode( $sanitized );
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM $table WHERE domain = %s AND scope = %s", $domain, $scope )
		);
		if ( $exists ) {
			$wpdb->update(
				$table,
				array( 'settings_json' => $json, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $exists )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'domain'        => $domain,
					'scope'         => $scope,
					'settings_json' => $json,
					'updated_at'    => current_time( 'mysql' ),
				)
			);
		}
		return $sanitized;
	}

	/**
	 * @param array<string,mixed> $input Input.
	 * @param array<string,mixed> $current Current.
	 * @return array<string,mixed>
	 */
	private static function sanitize_site( array $input, array $current ) {
		$out = $current;
		foreach ( array( 'enabled', 'otp_login_enabled', 'otp_register_enabled', 'use_pattern_for_otp' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = ! empty( $input[ $k ] );
			}
		}
		foreach ( array( 'sender_line_service', 'sender_line_dedicated', 'otp_login_template', 'otp_register_template' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = sanitize_textarea_field( (string) $input[ $k ] );
			}
		}
		foreach ( array( 'otp_expiry_minutes', 'otp_length' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = max( 1, min( 15, (int) $input[ $k ] ) );
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $input Input.
	 * @param array<string,mixed> $current Current.
	 * @return array<string,mixed>
	 */
	private static function sanitize_shop( array $input, array $current ) {
		$out = $current;
		foreach ( array( 'enabled', 'use_service_line' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = ! empty( $input[ $k ] );
			}
		}
		foreach ( array( 'sender_line_service', 'sender_line_dedicated' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = sanitize_text_field( (string) $input[ $k ] );
			}
		}
		if ( isset( $input['admin_phones'] ) ) {
			$phones = is_array( $input['admin_phones'] ) ? $input['admin_phones'] : preg_split( '/[\s,;]+/', (string) $input['admin_phones'] );
			$out['admin_phones'] = array_values(
				array_filter(
					array_map(
						static function ( $p ) {
							return WebinoCRM_Sms_Template_Service::normalize_phone( (string) $p );
						},
						$phones
					)
				)
			);
		}
		if ( isset( $input['events'] ) && is_array( $input['events'] ) ) {
			$events = $out['events'];
			foreach ( WebinoCRM_Sms_Constants::order_event_keys() as $key ) {
				if ( ! isset( $input['events'][ $key ] ) || ! is_array( $input['events'][ $key ] ) ) {
					continue;
				}
				$events[ $key ] = array(
					'customer' => ! empty( $input['events'][ $key ]['customer'] ),
					'admin'    => ! empty( $input['events'][ $key ]['admin'] ),
				);
			}
			$out['events'] = $events;
		}
		return $out;
	}

	/**
	 * @param string $domain Domain.
	 * @return string
	 */
	public static function resolve_from_number( $domain, $use_service = true ) {
		$shop = self::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP );
		$site = self::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SITE );
		$line = $use_service
			? ( (string) ( $shop['sender_line_service'] ?? '' ) ?: (string) ( $site['sender_line_service'] ?? '' ) )
			: ( (string) ( $shop['sender_line_dedicated'] ?? '' ) ?: (string) ( $site['sender_line_dedicated'] ?? '' ) );
		if ( '' !== $line ) {
			return $line;
		}
		$account = WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain );
		return (string) ( $account['default_from'] ?? WebinoCRM_ModirPayamak_Edge_Client::default_from() );
	}

	/**
	 * @param array<string,mixed> $base Base.
	 * @param array<string,mixed> $over Overlay.
	 * @return array<string,mixed>
	 */
	private static function merge_deep( array $base, array $over ) {
		foreach ( $over as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) ) {
				$base[ $k ] = self::merge_deep( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}
}
