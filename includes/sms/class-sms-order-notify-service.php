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
 * Sends customer + admin SMS for order events.
 */
final class WebinoCRM_Sms_Order_Notify_Service {

	/**
	 * @param string              $domain Domain.
	 * @param string              $event_key Event key.
	 * @param array<string,mixed> $order Order snapshot.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function notify( $domain, $event_key, array $order ) {
		$domain    = WebinoCRM_License_Manager::normalize_domain( $domain );
		$event_key = sanitize_key( $event_key );

		if ( ! in_array( $event_key, WebinoCRM_Sms_Constants::order_event_keys(), true ) ) {
			return new WP_Error( 'invalid_event', __( 'Unknown order SMS event.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$shop = WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SHOP );
		if ( empty( $shop['enabled'] ) ) {
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'disabled' );
		}

		WebinoCRM_Sms_Template_Service::seed_order_defaults( $domain );

		$events = $shop['events'][ $event_key ] ?? array( 'customer' => false, 'admin' => false );
		$vars   = WebinoCRM_Sms_Template_Service::vars_from_order_snapshot( $order, $domain );
		$from   = WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, ! empty( $shop['use_service_line'] ) );

		$results = array(
			'customer' => null,
			'admin'    => null,
		);

		if ( ! empty( $events['customer'] ) ) {
			$phone = WebinoCRM_Sms_Template_Service::normalize_phone( (string) ( $order['customer_phone'] ?? '' ) );
			if ( strlen( $phone ) > 4 ) {
				$results['customer'] = self::send_for_role(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_CUSTOMER,
					$event_key,
					$phone,
					$vars,
					$from,
					'customer',
					(int) ( $order['id'] ?? 0 )
				);
			}
		}

		if ( ! empty( $events['admin'] ) && ! empty( $shop['admin_phones'] ) && is_array( $shop['admin_phones'] ) ) {
			foreach ( $shop['admin_phones'] as $admin_phone ) {
				$phone = WebinoCRM_Sms_Template_Service::normalize_phone( (string) $admin_phone );
				if ( '' === $phone ) {
					continue;
				}
				$results['admin'] = self::send_for_role(
					$domain,
					WebinoCRM_Sms_Constants::TPL_ORDER_ADMIN,
					$event_key,
					$phone,
					$vars,
					$from,
					'admin',
					(int) ( $order['id'] ?? 0 )
				);
			}
		}

		return array( 'ok' => true, 'results' => $results );
	}

	/**
	 * @param string              $domain Domain.
	 * @param string              $scope Template scope.
	 * @param string              $event_key Event.
	 * @param string              $phone Recipient.
	 * @param array<string,string> $vars Vars.
	 * @param string              $from From number.
	 * @param string              $role customer|admin.
	 * @param int                 $order_id Order id.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function send_for_role( $domain, $scope, $event_key, $phone, array $vars, $from, $role, $order_id ) {
		$tpl = WebinoCRM_Sms_Template_Service::get_one( $domain, $scope, $event_key );
		if ( ! $tpl || empty( $tpl['enabled'] ) ) {
			return array( 'skipped' => true, 'reason' => 'template_disabled' );
		}

		$registry = WebinoCRM_Sms_Pattern_Sync_Service::get_registry_row( $domain, $scope, $event_key );
		$code     = (string) ( $tpl['pattern_code'] ?? '' );
		if ( $registry && WebinoCRM_Sms_Pattern_Sync_Service::STATUS_SYNCED === ( $registry['sync_status'] ?? '' ) && ! empty( $registry['ippanel_code'] ) ) {
			$code = (string) $registry['ippanel_code'];
		}

		if ( '' !== $code && $registry && WebinoCRM_Sms_Pattern_Sync_Service::STATUS_SYNCED === ( $registry['sync_status'] ?? '' ) ) {
			$params = WebinoCRM_Sms_Template_Service::pattern_params_from_template( (string) $tpl['body'], $vars );
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
		} else {
			$message = WebinoCRM_Sms_Template_Service::render( (string) $tpl['body'], $vars );
			$result  = WebinoCRM_ModirPayamak_Manager::customer_send(
				$domain,
				array(
					'sending_type' => 'webservice',
					'from_number'  => $from,
					'message'      => $message,
					'params'       => array( 'recipients' => array( $phone ) ),
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::tag_message_log( $domain, (int) ( $result['message_id'] ?? 0 ), 'order', (string) $order_id, $role );

		return $result;
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $message_id Message row id.
	 * @param string $context_type Context.
	 * @param string $context_id Context id.
	 * @param string $role Role.
	 * @return void
	 */
	private static function tag_message_log( $domain, $message_id, $context_type, $context_id, $role ) {
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
			),
			array( 'id' => $message_id )
		);
	}
}
