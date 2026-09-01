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
		if ( array_key_exists( 'otp_max_attempts', $input ) ) {
			$out['otp_max_attempts'] = max( 1, min( 20, (int) $input['otp_max_attempts'] ) );
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
		foreach ( array( 'enabled', 'use_service_line', 'require_pattern' ) as $k ) {
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
		if ( isset( $input['bot_ids'] ) ) {
			$raw = is_array( $input['bot_ids'] ) ? $input['bot_ids'] : preg_split( '/[\s,;]+/', (string) $input['bot_ids'] );
			$ids = array();
			foreach ( $raw as $id ) {
				$id = sanitize_text_field( (string) $id );
				if ( '' === $id ) {
					continue;
				}
				$ids[] = $id;
				if ( count( $ids ) >= 5 ) {
					break;
				}
			}
			$out['bot_ids'] = $ids;
		}
		if ( isset( $input['newsletter'] ) && is_array( $input['newsletter'] ) ) {
			$nl = is_array( $out['newsletter'] ?? null ) ? $out['newsletter'] : array();
			if ( array_key_exists( 'enabled', $input['newsletter'] ) ) {
				$nl['enabled'] = ! empty( $input['newsletter']['enabled'] );
			}
			if ( array_key_exists( 'message_template', $input['newsletter'] ) ) {
				$nl['message_template'] = sanitize_textarea_field( (string) $input['newsletter']['message_template'] );
			}
			if ( array_key_exists( 'pattern_code', $input['newsletter'] ) ) {
				$nl['pattern_code'] = sanitize_text_field( (string) $input['newsletter']['pattern_code'] );
			}
			$out['newsletter'] = $nl;
		}
		if ( isset( $input['recovery'] ) && is_array( $input['recovery'] ) ) {
			$defaults = WebinoCRM_Sms_Constants::default_shop_settings()['recovery'] ?? array();
			$rec_out  = is_array( $out['recovery'] ?? null ) ? $out['recovery'] : $defaults;
			foreach ( $input['recovery'] as $ek => $row ) {
				$ek = sanitize_key( (string) $ek );
				if ( '' === $ek || ! is_array( $row ) ) {
					continue;
				}
				$base = is_array( $rec_out[ $ek ] ?? null ) ? $rec_out[ $ek ] : ( $defaults[ $ek ] ?? array() );
				$next = array(
					'enabled'      => ! empty( $row['enabled'] ),
					'type'         => in_array( (string) ( $row['type'] ?? 'percent' ), array( 'percent', 'fixed_cart' ), true )
						? (string) $row['type']
						: 'percent',
					'amount'       => isset( $row['amount'] ) ? max( 0, (float) $row['amount'] ) : (float) ( $base['amount'] ?? 0 ),
					'expires_days' => isset( $row['expires_days'] ) ? max( 1, min( 365, (int) $row['expires_days'] ) ) : (int) ( $base['expires_days'] ?? 7 ),
					'usage_limit'  => isset( $row['usage_limit'] ) ? max( 1, min( 100, (int) $row['usage_limit'] ) ) : (int) ( $base['usage_limit'] ?? 1 ),
				);
				if ( isset( $row['delay_hours'] ) || isset( $base['delay_hours'] ) ) {
					$next['delay_hours'] = isset( $row['delay_hours'] )
						? max( 1, min( 720, (int) $row['delay_hours'] ) )
						: (int) ( $base['delay_hours'] ?? 24 );
				}
				$rec_out[ $ek ] = $next;
			}
			$out['recovery'] = $rec_out;
		}
		if ( isset( $input['event_catalog'] ) && is_array( $input['event_catalog'] ) ) {
			$catalog = array();
			foreach ( $input['event_catalog'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$key = sanitize_key( (string) ( $row['key'] ?? '' ) );
				if ( ! WebinoCRM_Sms_Constants::is_valid_event_key( $key ) ) {
					continue;
				}
				$catalog[] = array(
					'key'   => $key,
					'label' => sanitize_text_field( (string) ( $row['label'] ?? $key ) ),
					'kind'  => in_array( (string) ( $row['kind'] ?? '' ), array( 'status', 'extra' ), true )
						? (string) $row['kind']
						: 'status',
				);
			}
			$out['event_catalog'] = $catalog;
		}
		if ( isset( $input['events'] ) && is_array( $input['events'] ) ) {
			$events = is_array( $out['events'] ?? null ) ? $out['events'] : array();
			foreach ( $input['events'] as $key => $toggle ) {
				$key = sanitize_key( (string) $key );
				if ( ! WebinoCRM_Sms_Constants::is_valid_event_key( $key ) || ! is_array( $toggle ) ) {
					continue;
				}
				$events[ $key ] = array(
					'customer' => ! empty( $toggle['customer'] ),
					'admin'    => ! empty( $toggle['admin'] ),
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
		$role     = $use_service
			? WebinoCRM_ModirPayamak_Manager::ROLE_SERVICE
			: WebinoCRM_ModirPayamak_Manager::ROLE_PERSONAL;
		$attached = WebinoCRM_ModirPayamak_Manager::get_domain_number_for_role( $domain, $role );
		if ( '' !== $attached ) {
			return $attached;
		}
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
