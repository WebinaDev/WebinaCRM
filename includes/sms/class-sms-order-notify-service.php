<?php
/**
 * WooCommerce order SMS notifications (triggered from customer dashboard).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends customer + admin SMS for order events (pattern-only).
 * Dashboard event switches (settings.events) are the sole enable gate per role.
 */
final class WebinoCRM_Sms_Order_Notify_Service {

	/**
	 * @param string              $domain Domain.
	 * @param string              $event_key Event key.
	 * @param array<string,mixed> $order Order snapshot.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function notify( $domain, $event_key, array $order, array $options = array() ) {
		$domain    = WebinoCRM_License_Manager::normalize_domain( $domain );
		$event_key = WebinoCRM_Sms_Constants::normalize_event_key( $event_key );
		$order_id  = (int) ( $order['id'] ?? 0 );

		if ( ! WebinoCRM_Sms_Constants::is_valid_event_key( $event_key ) ) {
			return new WP_Error( 'invalid_event', __( 'Unknown order SMS event.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$shop = WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP );
		if ( empty( $shop['enabled'] ) ) {
			self::log_skip( $domain, $order_id, 'system', $event_key, 'disabled' );
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'disabled' );
		}

		$events = WebinoCRM_Sms_Constants::resolve_event_toggles( $shop, $event_key );
		if ( empty( $options['force_customer'] ) ) {
			$events['customer'] = ! empty( $events['customer'] );
		} else {
			$events['customer'] = true;
		}
		if ( empty( $options['force_admin'] ) ) {
			$events['admin'] = ! empty( $events['admin'] );
		} else {
			$events['admin'] = true;
		}
		if ( ! $events['customer'] && ! $events['admin'] ) {
			self::log_skip( $domain, $order_id, 'system', $event_key, 'event_off' );
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'event_off' );
		}

		$known_keys = WebinoCRM_Sms_Constants::resolve_event_keys( $shop );
		if ( ! in_array( $event_key, $known_keys, true ) && empty( $shop['events'][ $event_key ] ) ) {
			$shop['events'][ $event_key ] = $shop['events'][ $event_key ] ?? array( 'customer' => false, 'admin' => false );
		}

		WebinoCRM_Sms_Template_Service::seed_order_defaults( $domain, $known_keys );

		$vars = WebinoCRM_Sms_Template_Service::vars_from_order_snapshot( $order, $domain );
		$from = WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, ! empty( $shop['use_service_line'] ) );

		$results = array(
			'customer' => null,
			'admin'    => array(),
		);

		if ( $events['customer'] ) {
			$phone = WebinoCRM_Sms_Template_Service::normalize_phone( (string) ( $order['customer_phone'] ?? '' ) );
			if ( strlen( $phone ) > 4 ) {
				$results['customer'] = self::normalize_role_result(
					self::send_for_role(
						$domain,
						WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER,
						$event_key,
						$phone,
						$vars,
						$from,
						'customer',
						$order_id
					)
				);
			} else {
				self::log_skip( $domain, $order_id, 'customer', $event_key, 'no_phone' );
				$results['customer'] = array( 'skipped' => true, 'reason' => 'no_phone' );
			}
		}

		if ( $events['admin'] ) {
			$admin_phones = is_array( $shop['admin_phones'] ?? null ) ? $shop['admin_phones'] : array();
			if ( ! empty( $options['admin_phones'] ) && is_array( $options['admin_phones'] ) ) {
				$admin_phones = $options['admin_phones'];
			}
			$sent_any = false;
			foreach ( $admin_phones as $admin_phone ) {
				$phone = WebinoCRM_Sms_Template_Service::normalize_phone( (string) $admin_phone );
				if ( '' === $phone ) {
					continue;
				}
				$sent_any           = true;
				$results['admin'][] = self::normalize_role_result(
					self::send_for_role(
						$domain,
						WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN,
						$event_key,
						$phone,
						$vars,
						$from,
						'admin',
						$order_id
					)
				);
			}
			if ( ! $sent_any ) {
				self::log_skip( $domain, $order_id, 'admin', $event_key, 'no_admin_phone' );
				$results['admin'][] = array( 'skipped' => true, 'reason' => 'no_admin_phone' );
			}
		}

		return array( 'ok' => true, 'results' => $results );
	}

	/**
	 * @param array<string,mixed>|WP_Error $result Role send result.
	 * @return array<string,mixed>
	 */
	private static function normalize_role_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'skipped' => false,
				'reason'  => sanitize_key( (string) $result->get_error_code() ) ?: 'send_failed',
				'message' => $result->get_error_message(),
			);
		}
		return is_array( $result ) ? $result : array( 'ok' => false, 'reason' => 'send_failed' );
	}

	/**
	 * @param string               $domain Domain.
	 * @param string               $scope Template scope.
	 * @param string               $event_key Event.
	 * @param string               $phone Recipient.
	 * @param array<string,string> $vars Vars.
	 * @param string               $from From number.
	 * @param string               $role customer|admin.
	 * @param int                  $order_id Order id.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function send_for_role( $domain, $scope, $event_key, $phone, array $vars, $from, $role, $order_id ) {
		$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
		if ( ! $tpl ) {
			self::log_skip( $domain, $order_id, $role, $event_key, 'template_missing', $phone );
			return array( 'skipped' => true, 'reason' => 'template_missing' );
		}
		if ( empty( $tpl['body'] ) ) {
			self::log_skip( $domain, $order_id, $role, $event_key, 'empty_template', $phone );
			return array( 'skipped' => true, 'reason' => 'empty_template' );
		}

		$registry = WebinoCRM_Sms_Pattern_Sync_Service::get_registry_row( $domain, $scope, $event_key );
		$code     = (string) ( $tpl['pattern_code'] ?? '' );
		if ( $registry && ! empty( $registry['ippanel_code'] ) ) {
			$code = (string) $registry['ippanel_code'];
		}

		$synced = $registry
			&& WebinoCRM_Sms_Pattern_Sync_Service::STATUS_SYNCED === ( $registry['sync_status'] ?? '' )
			&& '' !== $code;

		if ( ! $synced ) {
			self::log_skip( $domain, $order_id, $role, $event_key, 'pattern_missing', $phone );
			return array(
				'skipped' => true,
				'reason'  => 'pattern_missing',
				'message' => __( 'Order SMS requires a synced IPPanel pattern.', 'webinocrm' ),
			);
		}

		$params = WebinoCRM_Sms_Template_Service::pattern_params_from_map(
			WebinoCRM_Sms_Template_Service::decode_param_map( $registry['param_map'] ?? null )
				?: WebinoCRM_Sms_Template_Service::decode_param_map( $tpl['param_map'] ?? null ),
			(string) $tpl['body'],
			$vars
		);
		$result = WebinoCRM_ModirPayamak_Manager::customer_send(
			$domain,
			array(
				'sending_type' => 'pattern',
				'from_number'  => $from,
				'code'         => $code,
				'recipients'   => array( $phone ),
				'params'       => $params,
			)
		);

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$msg_id = is_array( $data ) ? (int) ( $data['message_id'] ?? 0 ) : 0;
			if ( $msg_id > 0 ) {
				self::tag_message_log( $domain, $msg_id, 'order', (string) $order_id, $role, $event_key );
			} else {
				self::log_skip(
					$domain,
					$order_id,
					$role,
					$event_key,
					sanitize_key( (string) $result->get_error_code() ) ?: 'send_failed',
					$phone
				);
			}
			return $result;
		}

		self::tag_message_log( $domain, (int) ( $result['message_id'] ?? 0 ), 'order', (string) $order_id, $role, $event_key );

		return $result;
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $order_id Order id.
	 * @param string $role Role.
	 * @param string $event_key Event.
	 * @param string $reason Reason.
	 * @param string $phone Optional recipient phone.
	 * @return void
	 */
	private static function log_skip( $domain, $order_id, $role, $event_key, $reason, $phone = '' ) {
		if ( $order_id <= 0 ) {
			return;
		}
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'messages' );
		$recipients = array();
		$phone      = WebinoCRM_Sms_Template_Service::normalize_phone( (string) $phone );
		if ( '' !== $phone ) {
			$recipients[] = $phone;
		}
		$wpdb->insert(
			$table,
			array(
				'domain'         => WebinoCRM_License_Manager::normalize_domain( $domain ),
				'sending_type'   => 'pattern',
				'recipients'     => wp_json_encode( $recipients ),
				'message_body'   => wp_json_encode(
					array(
						'event_key' => $event_key,
						'reason'    => $reason,
						'order_id'  => $order_id,
						'role'      => $role,
					)
				),
				'cost'           => 0,
				'status'         => 'skipped',
				'context_type'   => 'order',
				'context_id'     => (string) $order_id,
				'recipient_role' => $role,
				'event_key'      => sanitize_key( $event_key ),
				'created_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $message_id Message row id.
	 * @param string $context_type Context.
	 * @param string $context_id Context id.
	 * @param string $role Role.
	 * @param string $event_key Event key.
	 * @return void
	 */
	private static function tag_message_log( $domain, $message_id, $context_type, $context_id, $role, $event_key = '' ) {
		if ( $message_id <= 0 ) {
			return;
		}
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'messages' );
		$wpdb->update(
			$table,
			array(
				'context_type'   => $context_type,
				'context_id'     => $context_id,
				'recipient_role' => $role,
				'event_key'      => sanitize_key( (string) $event_key ),
			),
			array( 'id' => $message_id )
		);
	}
}
