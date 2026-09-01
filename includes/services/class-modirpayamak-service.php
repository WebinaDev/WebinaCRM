<?php
/**
 * Modirpayamak Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Modirpayamak_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function dashboard( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		return WebinoCRM_Service_Base::success( array( 'stats' => WebinoCRM_ModirPayamak_Manager::dashboard_stats() ) );
	}

		public static function proxy( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$method = strtoupper( sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'method', 'GET' ) ) ) );
		$path   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'path', '' ) ) );
		$body   = array();
		$raw_body = WebinoCRM_Service_Base::param( $params, 'body', '' );
		if ( is_array( $raw_body ) ) {
			$body = $raw_body;
		} elseif ( is_string( $raw_body ) && '' !== $raw_body ) {
			$decoded = json_decode( wp_unslash( $raw_body ), true );
			if ( is_array( $decoded ) ) {
				$body = $decoded;
			}
		}
		$query = array();
		$raw_query = WebinoCRM_Service_Base::param( $params, 'query', '' );
		if ( is_array( $raw_query ) ) {
			$query = $raw_query;
		} elseif ( is_string( $raw_query ) && '' !== $raw_query ) {
			$decoded = json_decode( wp_unslash( $raw_query ), true );
			if ( is_array( $decoded ) ) {
				$query = $decoded;
			}
		}
		if ( '' === $path ) {
		return WebinoCRM_Service_Base::error( __( 'Path is required.', 'webinocrm' ) );
		}
		$result = WebinoCRM_ModirPayamak_Edge_Client::proxy( $method, $path, $body, $query );
		return WebinoCRM_Service_Base::success( $result );
	}

		public static function customers( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		return WebinoCRM_Service_Base::success( array( 'accounts' => WebinoCRM_ModirPayamak_Manager::get_all_accounts() ) );
	}

		public static function adjust_balance( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$amount = (float) WebinoCRM_Service_Base::param( $params, 'amount', 0 );
		$note   = sanitize_text_field(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'note', '' ) )
		);
		if ( '' === $domain ) {
		return WebinoCRM_Service_Base::error( __( 'Domain is required.', 'webinocrm' ) );
		}
		$result = WebinoCRM_ModirPayamak_Manager::adjust_balance( $domain, $amount, $note );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array(
				'message' => __( 'Balance updated.', 'webinocrm' ),
				'account' => WebinoCRM_ModirPayamak_Manager::format_account_public(
					WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain )
				),
			) );
	}

		public static function customer_ledger( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$page  = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) );
		$limit = min( 100, max( 1, (int) WebinoCRM_Service_Base::param( $params, 'limit', 50 ) ) );
		if ( '' === $domain ) {
			return WebinoCRM_Service_Base::error( __( 'Domain is required.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'ledger'  => WebinoCRM_ModirPayamak_Manager::get_ledger( $domain, $page, $limit ),
				'account' => WebinoCRM_ModirPayamak_Manager::format_account_public(
					WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain )
				),
			)
		);
	}

		public static function ensure_customers_from_licenses( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$result = WebinoCRM_ModirPayamak_Manager::ensure_accounts_for_active_licenses();
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'SMS customers synced from active licenses.', 'webinocrm' ),
				'created'  => (int) ( $result['created'] ?? 0 ),
				'total'    => (int) ( $result['total'] ?? 0 ),
				'accounts' => WebinoCRM_ModirPayamak_Manager::get_all_accounts(),
			)
		);
	}

		public static function attach_number( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$number = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'number', '' ) ) );
		$role   = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'role', 'service' ) );
		$label  = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'label', '' ) ) );
		$result = WebinoCRM_ModirPayamak_Manager::attach_domain_number( $domain, $number, $role, $label );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'      => __( 'Number attached.', 'webinocrm' ),
				'number'       => $result,
				'attachments'  => WebinoCRM_ModirPayamak_Manager::get_number_attachments( $number ),
				'account'      => WebinoCRM_ModirPayamak_Manager::format_account_public(
					WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain )
				),
			)
		);
	}

		public static function detach_number( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$role   = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'role', 'service' ) );
		$number = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'number', '' ) ) );
		$result = WebinoCRM_ModirPayamak_Manager::detach_domain_number( $domain, $role, $number );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'Number detached.', 'webinocrm' ),
				'account' => WebinoCRM_ModirPayamak_Manager::format_account_public(
					WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain )
				),
			)
		);
	}

		public static function attach_pattern( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain    = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$scope     = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'scope', 'order_customer' ) );
		$event_key = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'event_key', '' ) );
		$code      = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'pattern_code', '' ) ) );
		$param_map = WebinoCRM_Sms_Template_Service::decode_param_map( WebinoCRM_Service_Base::param( $params, 'param_map', array() ) );
		if ( '' === $domain || '' === $event_key || '' === $code ) {
			return WebinoCRM_Service_Base::error( __( 'Domain, event and pattern code are required.', 'webinocrm' ) );
		}
		$allowed_scopes = array( 'order_customer', 'order_admin', 'site' );
		if ( ! in_array( $scope, $allowed_scopes, true ) ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid pattern scope.', 'webinocrm' ) );
		}
		WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain );
		$result = WebinoCRM_Sms_Pattern_Sync_Service::bind_existing( $domain, $scope, $event_key, $code, $param_map );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'Pattern attached to shop.', 'webinocrm' ),
				'result'   => $result,
				'registry' => WebinoCRM_Sms_Pattern_Sync_Service::list_registry( $domain ),
			)
		);
	}

		public static function detach_pattern( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain    = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$scope     = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'scope', 'order_customer' ) );
		$event_key = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'event_key', '' ) );
		$result    = WebinoCRM_Sms_Pattern_Sync_Service::detach( $domain, $scope, $event_key );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'Pattern detached.', 'webinocrm' ),
				'registry' => $result['registry'] ?? array(),
			)
		);
	}

		public static function domain_pattern_registry( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$limit  = min( 500, max( 1, (int) WebinoCRM_Service_Base::param( $params, 'limit', 200 ) ) );
		$offset = max( 0, (int) WebinoCRM_Service_Base::param( $params, 'offset', 0 ) );
		if ( '' === $domain ) {
			return WebinoCRM_Service_Base::success(
				array(
					'registry' => WebinoCRM_Sms_Pattern_Sync_Service::list_registry_all( '', $limit, $offset ),
					'events'   => WebinoCRM_Sms_Constants::order_event_keys(),
				)
			);
		}
		return WebinoCRM_Service_Base::success(
			array(
				'registry' => WebinoCRM_Sms_Pattern_Sync_Service::list_registry( $domain ),
				'events'   => WebinoCRM_Sms_Constants::order_event_keys(),
			)
		);
	}

	public static function domain_secretaries( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		if ( '' === $domain ) {
			return WebinoCRM_Service_Base::error( __( 'Domain is required.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'secretaries' => WebinoCRM_Sms_Secretary_Service::list_rules( $domain ),
			)
		);
	}

	public static function save_domain_secretary( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$result = WebinoCRM_Sms_Secretary_Service::save_rule( $domain, $params );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( $result );
	}

	public static function delete_domain_secretary( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$id     = (int) WebinoCRM_Service_Base::param( $params, 'id', 0 );
		$result = WebinoCRM_Sms_Secretary_Service::delete_rule( $domain, $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( $result );
	}

		public static function packages( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'packages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY sort ASC, amount ASC", ARRAY_A );
		return WebinoCRM_Service_Base::success( array( 'packages' => $rows ?: array() ) );
	}

		public static function package_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		global $wpdb;
		$id     = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$name   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) );
		$amount = (float) WebinoCRM_Service_Base::param( $params, 'amount', 0 );
		$bonus  = (float) WebinoCRM_Service_Base::param( $params, 'bonus', 0 );
		$sort   = WebinoCRM_Service_Base::int_param( $params, 'sort' );
		$status = sanitize_key( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', 'active' ) ) );
		if ( '' === $name || $amount <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'Name and amount are required.', 'webinocrm' ) );
		}
		$table = WebinoCRM_ModirPayamak_Manager::table( 'packages' );
		$data  = array(
			'name'   => $name,
			'amount' => $amount,
			'bonus'  => $bonus,
			'sort'   => $sort,
			'status' => in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'active',
		);
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $id, 'message' => __( 'Saved.', 'webinocrm' ) ) );
	}

		public static function package_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		global $wpdb;
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( ! $id ) {
		return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		$wpdb->delete( WebinoCRM_ModirPayamak_Manager::table( 'packages' ), array( 'id' => $id ) );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Deleted.', 'webinocrm' ) ) );
	}

	public static function tariffs( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		return WebinoCRM_Service_Base::success(
			array(
				'tariffs'         => WebinoCRM_ModirPayamak_Tariffs::list_all( false ),
				'tax_percent'     => WebinoCRM_ModirPayamak_Tariffs::tax_percent(),
				'surcharge_rial'  => WebinoCRM_ModirPayamak_Tariffs::surcharge_rial(),
			)
		);
	}

	public static function tariff_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$id = WebinoCRM_ModirPayamak_Tariffs::save(
			array(
				'id'        => WebinoCRM_Service_Base::int_param( $params, 'id' ),
				'line_type' => WebinoCRM_Service_Base::param( $params, 'line_type', '' ),
				'operator'  => WebinoCRM_Service_Base::param( $params, 'operator', 'other' ),
				'rate_fa'   => WebinoCRM_Service_Base::param( $params, 'rate_fa', 0 ),
				'rate_la'   => WebinoCRM_Service_Base::param( $params, 'rate_la', 0 ),
				'sort'      => WebinoCRM_Service_Base::int_param( $params, 'sort' ),
				'status'    => WebinoCRM_Service_Base::param( $params, 'status', 'active' ),
			)
		);
		if ( is_wp_error( $id ) ) {
			return WebinoCRM_Service_Base::error( $id->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $id, 'message' => __( 'Saved.', 'webinocrm' ) ) );
	}

	public static function tariff_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$result = WebinoCRM_ModirPayamak_Tariffs::delete( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Deleted.', 'webinocrm' ) ) );
	}

		public static function orders( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$page  = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) );
		$limit = min( 100, max( 1, (int) WebinoCRM_Service_Base::param( $params, 'limit', 50 ) ) );
		return WebinoCRM_Service_Base::success( array( 'orders' => WebinoCRM_ModirPayamak_Manager::get_orders( $page, $limit ) ) );
	}

		public static function send( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$payload = array();
		$raw_payload = WebinoCRM_Service_Base::param( $params, 'payload', '' );
		if ( is_array( $raw_payload ) ) {
			$payload = $raw_payload;
		} elseif ( is_string( $raw_payload ) && '' !== $raw_payload ) {
			$decoded = json_decode( wp_unslash( $raw_payload ), true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}
		$result = WebinoCRM_ModirPayamak_Manager::admin_send( $payload );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( $result );
	}

		public static function messages( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::guard();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$domain = WebinoCRM_License_Manager::normalize_domain(
			wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
		);
		$page   = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) );
		$limit  = min( 50, max( 1, (int) WebinoCRM_Service_Base::param( $params, 'limit', 20 ) ) );
		if ( '' === $domain ) {
			global $wpdb;
			$table = WebinoCRM_ModirPayamak_Manager::table( 'messages' );
			$offset = max( 0, ( $page - 1 ) * $limit );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ),
				ARRAY_A
			);
			return WebinoCRM_Service_Base::success( array( 'messages' => $rows ?: array() ) );
		}
		return WebinoCRM_Service_Base::success( array(
				'messages' => WebinoCRM_ModirPayamak_Manager::get_domain_messages( $domain, $page, $limit ),
			) );
	}


	/**
	 * @return true|array<string,mixed>
	 */
	private static function guard() {
		if ( ! function_exists( 'webinocrm_user_can_manage_modirpayamak' ) || ! webinocrm_user_can_manage_modirpayamak() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return true;
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_modirpayamak_dashboard' => array( __CLASS__, 'dashboard' ),
				'webinocrm_modirpayamak_proxy' => array( __CLASS__, 'proxy' ),
				'webinocrm_modirpayamak_get_customers' => array( __CLASS__, 'customers' ),
				'webinocrm_modirpayamak_adjust_balance' => array( __CLASS__, 'adjust_balance' ),
				'webinocrm_modirpayamak_customer_ledger' => array( __CLASS__, 'customer_ledger' ),
				'webinocrm_modirpayamak_ensure_customers' => array( __CLASS__, 'ensure_customers_from_licenses' ),
				'webinocrm_modirpayamak_attach_number' => array( __CLASS__, 'attach_number' ),
				'webinocrm_modirpayamak_detach_number' => array( __CLASS__, 'detach_number' ),
				'webinocrm_modirpayamak_attach_pattern' => array( __CLASS__, 'attach_pattern' ),
				'webinocrm_modirpayamak_detach_pattern' => array( __CLASS__, 'detach_pattern' ),
				'webinocrm_modirpayamak_domain_pattern_registry' => array( __CLASS__, 'domain_pattern_registry' ),
				'webinocrm_modirpayamak_domain_secretaries' => array( __CLASS__, 'domain_secretaries' ),
				'webinocrm_modirpayamak_save_domain_secretary' => array( __CLASS__, 'save_domain_secretary' ),
				'webinocrm_modirpayamak_delete_domain_secretary' => array( __CLASS__, 'delete_domain_secretary' ),
				'webinocrm_modirpayamak_get_packages' => array( __CLASS__, 'packages' ),
				'webinocrm_modirpayamak_save_package' => array( __CLASS__, 'package_save' ),
				'webinocrm_modirpayamak_delete_package' => array( __CLASS__, 'package_delete' ),
				'webinocrm_modirpayamak_get_tariffs' => array( __CLASS__, 'tariffs' ),
				'webinocrm_modirpayamak_save_tariff' => array( __CLASS__, 'tariff_save' ),
				'webinocrm_modirpayamak_delete_tariff' => array( __CLASS__, 'tariff_delete' ),
				'webinocrm_modirpayamak_get_orders' => array( __CLASS__, 'orders' ),
				'webinocrm_modirpayamak_admin_send' => array( __CLASS__, 'send' ),
				'webinocrm_modirpayamak_get_messages' => array( __CLASS__, 'messages' ),

			)
		);
	}
}
