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
					'var'  => $var,
					'type' => 'string',
				);
			}
		}

		$payload = array(
			'code'    => $code,
			'message' => $pattern_message,
			'vars'    => $vars,
		);

		$registry = self::get_registry_row( $domain, $scope, $event_key );
		if ( $registry && ! empty( $registry['ippanel_code'] ) ) {
			$result = WebinoCRM_ModirPayamak_Edge_Client::update_pattern( (string) $registry['ippanel_code'], $payload );
		} else {
			$result = WebinoCRM_ModirPayamak_Edge_Client::create_pattern( $payload );
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
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE domain = %s ORDER BY scope, event_key", WebinoCRM_License_Manager::normalize_domain( $domain ) ),
			ARRAY_A
		) ?: array();
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
		return $row ?: null;
	}

	/**
	 * @param string $domain Domain.
	 * @param string $scope Scope.
	 * @param string $event_key Event.
	 * @param string $code Code.
	 * @param string $status Status.
	 * @param string $error Error.
	 * @return void
	 */
	private static function upsert_registry( $domain, $scope, $event_key, $code, $status, $error ) {
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
