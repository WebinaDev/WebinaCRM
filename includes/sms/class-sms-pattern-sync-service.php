<?php
/**
 * Sync message templates to IPPanel patterns.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers / updates patterns on Edge API.
 */
final class WebinoCRM_Sms_Pattern_Sync_Service {

	const STATUS_PENDING  = 'pending';
	const STATUS_SYNCED   = 'synced';
	const STATUS_FAILED   = 'failed';

	/**
	 * @param string $domain Domain.
	 * @param string $scope Template scope.
	 * @param string $event_key Event key.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function sync_one( $domain, $scope, $event_key ) {
		$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
		if ( ! $tpl || empty( $tpl['body'] ) ) {
			return new WP_Error( 'no_template', __( 'Template not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		return self::sync_body( $domain, $scope, $event_key, (string) $tpl['body'], (string) ( $tpl['pattern_code'] ?? '' ) );
	}

	/**
	 * Bind an existing approved IPPanel pattern code without creating a new one.
	 *
	 * @param string               $domain Domain.
	 * @param string               $scope Scope.
	 * @param string               $event_key Event.
	 * @param string               $code Existing pattern code.
	 * @param array<string,string> $param_map Optional pattern var → site expression map.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function bind_existing( $domain, $scope, $event_key, $code, array $param_map = array() ) {
		$domain    = WebinoCRM_License_Manager::normalize_domain( $domain );
		$scope     = sanitize_key( $scope );
		$event_key = sanitize_key( $event_key );
		$code      = sanitize_text_field( (string) $code );
		if ( '' === $code ) {
			return new WP_Error( 'invalid_code', __( 'Pattern code is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$edge = WebinoCRM_ModirPayamak_Edge_Client::get_pattern( $code );
		if ( empty( $edge['ok'] ) ) {
			self::upsert_registry( $domain, $scope, $event_key, $code, self::STATUS_FAILED, (string) ( $edge['message'] ?? '' ), $param_map );
			return new WP_Error( 'pattern_not_found', $edge['message'] ?? __( 'Pattern not found on IPPanel.', 'webinocrm' ), array( 'status' => 404 ) );
		}

		$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
		$body_from_edge = '';
		if ( is_array( $edge['data'] ?? null ) && ! empty( $edge['data']['pattern_message'] ) ) {
			$body_from_edge = (string) preg_replace( '/%([a-zA-Z0-9_]+)%/', '{$1}', (string) $edge['data']['pattern_message'] );
		}
		if ( $param_map ) {
			$body = WebinoCRM_Sms_Template_Service::format_param_map_preview( $code, $param_map );
		} else {
			$body = trim( $body_from_edge );
			if ( '' === $body ) {
				$body = ( 'order_admin' === $scope )
					? WebinoCRM_Sms_Constants::default_admin_template( $event_key )
					: WebinoCRM_Sms_Constants::default_customer_template( $event_key );
			}
		}
		$enabled = $tpl ? ! empty( $tpl['enabled'] ) : true;
		WebinoCRM_Sms_Template_Service::upsert( $domain, $scope, $event_key, $body, $enabled, $code, $param_map ?: null );
		$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
		if ( ! $tpl ) {
			return new WP_Error( 'no_template', __( 'Template not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}

		$approval = '';
		if ( is_array( $edge['data'] ?? null ) ) {
			$approval = (string) ( $edge['data']['status'] ?? $edge['data']['approval'] ?? $edge['data']['state'] ?? $edge['data']['pattern_status'] ?? '' );
		}
		$synced = true;
		if ( '' !== $approval && preg_match( '/reject|fail|deny|pending|wait/i', $approval ) && ! preg_match( '/approv|active|ok|accept|confirm/i', $approval ) ) {
			$synced = false;
		}
		$status = $synced ? self::STATUS_SYNCED : self::STATUS_PENDING;
		$map_for_registry = $param_map ?: WebinoCRM_Sms_Template_Service::decode_param_map( $tpl['param_map'] ?? null );
		self::upsert_registry( $domain, $scope, $event_key, $code, $status, $synced ? '' : (string) $approval, $map_for_registry );

		return array(
			'ok'           => true,
			'ippanel_code' => $code,
			'sync_status'  => $status,
			'param_map'    => $map_for_registry,
			'edge'         => $edge['data'] ?? null,
		);
	}

	/**
	 * Detach pattern from domain event (registry + template code/map).
	 *
	 * @param string $domain Domain.
	 * @param string $scope Scope.
	 * @param string $event_key Event.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function detach( $domain, $scope, $event_key ) {
		global $wpdb;
		$domain    = WebinoCRM_License_Manager::normalize_domain( $domain );
		$scope     = sanitize_key( $scope );
		$event_key = sanitize_key( $event_key );
		if ( '' === $domain || '' === $scope || '' === $event_key ) {
			return new WP_Error( 'invalid', __( 'Domain, scope and event are required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$table = WebinoCRM_Sms_Install::table( 'pattern_registry' );
		$wpdb->delete(
			$table,
			array(
				'domain'    => $domain,
				'scope'     => $scope,
				'event_key' => $event_key,
			)
		);
		$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
		if ( $tpl ) {
			WebinoCRM_Sms_Template_Service::upsert(
				$domain,
				$scope,
				$event_key,
				(string) $tpl['body'],
				! empty( $tpl['enabled'] ),
				'',
				array()
			);
		}
		return array(
			'ok'       => true,
			'registry' => self::list_registry( $domain ),
		);
	}

	/**
	 * Unique IPPanel codes owned/bound by a domain.
	 *
	 * @param string $domain Domain.
	 * @return array<int,string>
	 */
	public static function list_domain_pattern_codes( $domain ) {
		$codes = array();
		foreach ( self::list_registry( $domain ) as $row ) {
			$code = sanitize_text_field( (string) ( $row['ippanel_code'] ?? '' ) );
			if ( '' !== $code ) {
				$codes[ $code ] = $code;
			}
		}
		return array_values( $codes );
	}

	/**
	 * @param string $domain Domain.
	 * @param string $scope Scope.
	 * @param string $event_key Event.
	 * @param string $body Template body.
	 * @param string $existing_code Existing IPPanel code.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function sync_body( $domain, $scope, $event_key, $body, $existing_code = '' ) {
		if ( ! WebinoCRM_ModirPayamak_Edge_Client::is_configured() ) {
			return new WP_Error( 'not_configured', __( 'ModirPayamak is not enabled.', 'webinocrm' ), array( 'status' => 503 ) );
		}

		$domain    = WebinoCRM_License_Manager::normalize_domain( $domain );
		$scope     = sanitize_key( $scope );
		$event_key = sanitize_key( $event_key );
		$code      = $existing_code ?: self::generate_code( $domain, $scope, $event_key );

		$pattern_message = preg_replace( '/\{([a-zA-Z0-9_]+)\}/', '%$1%', $body );
		$vars            = array();
		if ( preg_match_all( '/\{([a-zA-Z0-9_]+)\}/', $body, $m ) ) {
			foreach ( array_unique( $m[1] ) as $var ) {
				$vars[] = array(
					'name' => $var,
					'type' => 'string',
				);
			}
		}

		$website = 'https://' . $domain;
		$payload = array(
			'title'       => substr( $scope . '_' . $event_key, 0, 80 ),
			'description' => sprintf(
				/* translators: 1: domain, 2: event key */
				__( 'Transactional SMS for %1$s (%2$s)', 'webinocrm' ),
				$domain,
				$event_key
			),
			'message'     => $pattern_message,
			'website'     => $website,
			'is_share'    => false,
			'variable'    => $vars,
			// Legacy keys kept for update_pattern fallbacks.
			'code'        => $code,
			'vars'        => $vars,
		);

		$registry = self::get_registry_row( $domain, $scope, $event_key );
		if ( $registry && ! empty( $registry['ippanel_code'] ) ) {
			$code   = (string) $registry['ippanel_code'];
			$result = WebinoCRM_ModirPayamak_Edge_Client::update_pattern( $code, $payload );
			if ( empty( $result['ok'] ) ) {
				$result = WebinoCRM_ModirPayamak_Edge_Client::create_pattern( $payload );
			}
		} else {
			$result = WebinoCRM_ModirPayamak_Edge_Client::create_pattern( $payload );
		}
		if ( ! empty( $result['ok'] ) && is_array( $result['data'] ?? null ) ) {
			$returned = (string) ( $result['data']['code'] ?? $result['data']['pattern_code'] ?? '' );
			if ( '' !== $returned ) {
				$code = $returned;
			}
		}

		$status = ! empty( $result['ok'] ) ? self::STATUS_SYNCED : self::STATUS_FAILED;
		$error  = ! empty( $result['ok'] ) ? '' : (string) ( $result['message'] ?? __( 'Pattern sync failed.', 'webinocrm' ) );

		self::upsert_registry( $domain, $scope, $event_key, $code, $status, $error );

		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'pattern_sync_failed', $error, array( 'status' => 502 ) );
		}

		WebinoCRM_Sms_Template_Service::upsert( $domain, $scope, $event_key, $body, true, $code );

		return array(
			'ok'            => true,
			'ippanel_code'  => $code,
			'sync_status'   => $status,
			'edge'          => $result['data'],
		);
	}

	/**
	 * @param string $domain Domain.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_registry( $domain ) {
		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'pattern_registry' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE domain = %s ORDER BY scope, event_key", WebinoCRM_License_Manager::normalize_domain( $domain ) ),
			ARRAY_A
		) ?: array();
		foreach ( $rows as &$row ) {
			$row['param_map'] = WebinoCRM_Sms_Template_Service::decode_param_map( $row['param_map'] ?? null );
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Admin: list registry across domains with optional filters.
	 *
	 * @param string $domain Optional domain filter.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_registry_all( $domain = '', $limit = 200, $offset = 0 ) {
		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'pattern_registry' );
		$domain = WebinoCRM_License_Manager::normalize_domain( (string) $domain );
		$limit  = max( 1, min( 500, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		if ( '' !== $domain ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE domain = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$domain,
					$limit,
					$offset
				),
				ARRAY_A
			) ?: array();
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			) ?: array();
		}
		foreach ( $rows as &$row ) {
			$row['param_map'] = WebinoCRM_Sms_Template_Service::decode_param_map( $row['param_map'] ?? null );
		}
		unset( $row );
		return $rows;
	}

	/**
	 * @param string $domain Domain.
	 * @param string $scope Scope.
	 * @param string $event_key Event.
	 * @return array<string,mixed>|null
	 */
	public static function get_registry_row( $domain, $scope, $event_key ) {
		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'pattern_registry' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE domain = %s AND scope = %s AND event_key = %s LIMIT 1",
				WebinoCRM_License_Manager::normalize_domain( $domain ),
				sanitize_key( $scope ),
				sanitize_key( $event_key )
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['param_map'] = WebinoCRM_Sms_Template_Service::decode_param_map( $row['param_map'] ?? null );
		return $row;
	}

	/**
	 * @param string               $domain Domain.
	 * @param string               $scope Scope.
	 * @param string               $event_key Event.
	 * @param string               $code Code.
	 * @param string               $status Status.
	 * @param string               $error Error.
	 * @param array<string,string> $param_map Param map.
	 * @return void
	 */
	private static function upsert_registry( $domain, $scope, $event_key, $code, $status, $error, array $param_map = array() ) {
		global $wpdb;
		$table    = WebinoCRM_Sms_Install::table( 'pattern_registry' );
		$domain   = WebinoCRM_License_Manager::normalize_domain( $domain );
		$existing = self::get_registry_row( $domain, $scope, $event_key );
		$data     = array(
			'ippanel_code' => $code,
			'sync_status'  => $status,
			'last_error'   => $error,
			'submitted_at' => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);
		if ( $param_map || ! $existing ) {
			$data['param_map'] = WebinoCRM_Sms_Template_Service::encode_param_map( $param_map );
		} elseif ( $existing && array_key_exists( 'param_map', $existing ) && empty( $param_map ) ) {
			// Keep existing map when not provided.
		}
		if ( $param_map ) {
			$data['param_map'] = WebinoCRM_Sms_Template_Service::encode_param_map( $param_map );
		}
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			return;
		}
		$wpdb->insert(
			$table,
			array_merge(
				$data,
				array(
					'domain'    => $domain,
					'scope'     => sanitize_key( $scope ),
					'event_key' => sanitize_key( $event_key ),
				)
			)
		);
	}

	/**
	 * @param string $domain Domain.
	 * @param string $scope Scope.
	 * @param string $event_key Event.
	 * @return string
	 */
	private static function generate_code( $domain, $scope, $event_key ) {
		$slug = preg_replace( '/[^a-z0-9_]/', '_', strtolower( substr( md5( $domain . $scope . $event_key ), 0, 8 ) . '_' . $event_key ) );
		return 'wb_' . substr( $slug, 0, 40 );
	}
}
