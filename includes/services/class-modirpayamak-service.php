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
				'webinocrm_modirpayamak_get_packages' => array( __CLASS__, 'packages' ),
				'webinocrm_modirpayamak_save_package' => array( __CLASS__, 'package_save' ),
				'webinocrm_modirpayamak_delete_package' => array( __CLASS__, 'package_delete' ),
				'webinocrm_modirpayamak_get_orders' => array( __CLASS__, 'orders' ),
				'webinocrm_modirpayamak_admin_send' => array( __CLASS__, 'send' ),
				'webinocrm_modirpayamak_get_messages' => array( __CLASS__, 'messages' ),

			)
		);
	}
}
