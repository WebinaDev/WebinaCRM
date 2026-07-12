<?php
/**
 * ModirPayamak wallet, accounts, and send orchestration.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-domain SMS wallet backed by IPPanel Edge (master key).
 */
class WebinoCRM_ModirPayamak_Manager {

	const STATUS_ACTIVE   = 'active';
	const STATUS_SUSPENDED = 'suspended';

	const LEDGER_TOPUP  = 'topup';
	const LEDGER_SEND   = 'send';
	const LEDGER_REFUND = 'refund';
	const LEDGER_ADJUST = 'adjust';

	const ORDER_PENDING = 'pending';
	const ORDER_PAID    = 'paid';
	const ORDER_FAILED  = 'failed';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_create_tables' ), 2 );
	}

	/**
	 * @return void
	 */
	public static function maybe_create_tables() {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_modirpayamak_accounts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) !== $table ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-installer.php';
			WebinoCRM_Installer::create_modirpayamak_tables();
			self::seed_default_packages();
		}
	}

	/**
	 * @param string $suffix Table suffix without prefix.
	 * @return string
	 */
	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_modirpayamak_' . $suffix;
	}

	/**
	 * @return float
	 */
	public static function price_per_unit() {
		return max( 1, (float) WebinoCRM_Settings_Handler::get_setting( 'modirpayamak_sms_price_per_unit', 500 ) );
	}

	/**
	 * @param string $domain Domain.
	 * @return array<string,mixed>|null
	 */
	public static function get_account_by_domain( $domain ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		if ( '' === $domain ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( 'accounts' ) . ' WHERE domain = %s LIMIT 1', $domain ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * @param string $domain Domain.
	 * @return array<string,mixed>
	 */
	public static function get_or_create_account( $domain ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$row    = self::get_account_by_domain( $domain );
		if ( $row ) {
			return $row;
		}
		global $wpdb;
		$default_from = WebinoCRM_ModirPayamak_Edge_Client::default_from();
		$wpdb->insert(
			self::table( 'accounts' ),
			array(
				'domain'       => $domain,
				'balance'      => 0,
				'default_from' => $default_from,
				'status'       => self::STATUS_ACTIVE,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			)
		);
		return self::get_account_by_domain( $domain ) ?: array();
	}

	/**
	 * @param int    $account_id Account id.
	 * @param string $type       Ledger type.
	 * @param float  $amount     Positive amount.
	 * @param string $ref_type   Reference type.
	 * @param int    $ref_id     Reference id.
	 * @param string $note       Note.
	 * @param bool   $debit      True to subtract.
	 * @return true|WP_Error
	 */
	public static function ledger_entry( $account_id, $type, $amount, $ref_type = '', $ref_id = 0, $note = '', $debit = false ) {
		global $wpdb;
		$account_id = (int) $account_id;
		$amount     = abs( (float) $amount );
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid amount.', 'webinocrm' ) );
		}

		$accounts = self::table( 'accounts' );
		$ledger   = self::table( 'ledger' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $accounts WHERE id = %d FOR UPDATE", $account_id ), ARRAY_A );
		if ( ! $account ) {
			return new WP_Error( 'not_found', __( 'Account not found.', 'webinocrm' ) );
		}

		$balance = (float) $account['balance'];
		if ( $debit ) {
			if ( $balance < $amount ) {
				return new WP_Error( 'insufficient_balance', __( 'Insufficient SMS credit.', 'webinocrm' ), array( 'status' => 402 ) );
			}
			$balance -= $amount;
		} else {
			$balance += $amount;
		}

		$wpdb->update(
			$accounts,
			array(
				'balance'    => $balance,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $account_id )
		);

		$wpdb->insert(
			$ledger,
			array(
				'account_id'    => $account_id,
				'type'          => sanitize_key( $type ),
				'amount'        => $debit ? -$amount : $amount,
				'balance_after' => $balance,
				'ref_type'      => sanitize_key( $ref_type ),
				'ref_id'        => (int) $ref_id,
				'note'          => sanitize_text_field( $note ),
				'created_at'    => current_time( 'mysql' ),
			)
		);

		return true;
	}

	/**
	 * @param string $domain Domain.
	 * @param float  $amount Amount Toman.
	 * @param int    $order_id Order id.
	 * @return true|WP_Error
	 */
	public static function topup( $domain, $amount, $order_id = 0 ) {
		$account = self::get_or_create_account( $domain );
		return self::ledger_entry(
			(int) $account['id'],
			self::LEDGER_TOPUP,
			$amount,
			'order',
			$order_id,
			__( 'Credit top-up', 'webinocrm' ),
			false
		);
	}

	/**
	 * @param string $domain Domain.
	 * @param float  $amount Amount.
	 * @param int    $message_id Message log id.
	 * @return true|WP_Error
	 */
	public static function deduct_for_send( $domain, $amount, $message_id = 0 ) {
		$account = self::get_or_create_account( $domain );
		if ( self::STATUS_ACTIVE !== ( $account['status'] ?? '' ) ) {
			return new WP_Error( 'suspended', __( 'SMS account is suspended.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		return self::ledger_entry(
			(int) $account['id'],
			self::LEDGER_SEND,
			$amount,
			'message',
			$message_id,
			__( 'SMS send', 'webinocrm' ),
			true
		);
	}

	/**
	 * Estimate customer cost from recipient count.
	 *
	 * @param int $recipient_count Recipients.
	 * @return float
	 */
	public static function estimate_customer_cost( $recipient_count ) {
		return max( 1, (int) $recipient_count ) * self::price_per_unit();
	}

	/**
	 * @param string               $domain Domain.
	 * @param array<string,mixed>  $payload Send payload for Edge API.
	 * @return array{ ok: bool, data?: mixed, message?: string, cost?: float, message_id?: int }|WP_Error
	 */
	public static function customer_send( $domain, array $payload ) {
		if ( ! WebinoCRM_ModirPayamak_Edge_Client::is_configured() ) {
			return new WP_Error( 'not_configured', __( 'ModirPayamak is not enabled.', 'webinocrm' ), array( 'status' => 503 ) );
		}

		$account = self::get_or_create_account( $domain );
		$from    = ! empty( $payload['from_number'] ) ? (string) $payload['from_number'] : ( (string) ( $account['default_from'] ?? '' ) ?: WebinoCRM_ModirPayamak_Edge_Client::default_from() );
		$payload['from_number'] = $from;

		$recipient_count = self::count_recipients( $payload );
		$cost            = self::estimate_customer_cost( $recipient_count );

		if ( (float) $account['balance'] < $cost ) {
			return new WP_Error( 'insufficient_balance', __( 'Insufficient SMS credit. Please top up.', 'webinocrm' ), array( 'status' => 402 ) );
		}

		global $wpdb;
		$msg_id = 0;
		$wpdb->insert(
			self::table( 'messages' ),
			array(
				'domain'        => WebinoCRM_License_Manager::normalize_domain( $domain ),
				'sending_type'  => sanitize_key( (string) ( $payload['sending_type'] ?? 'webservice' ) ),
				'recipients'    => wp_json_encode( self::extract_recipients( $payload ) ),
				'message_body'  => wp_json_encode( $payload ),
				'cost'          => $cost,
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$msg_id = (int) $wpdb->insert_id;

		$deduct = self::deduct_for_send( $domain, $cost, $msg_id );
		if ( is_wp_error( $deduct ) ) {
			$wpdb->update( self::table( 'messages' ), array( 'status' => 'failed' ), array( 'id' => $msg_id ) );
			return $deduct;
		}

		$result = WebinoCRM_ModirPayamak_Edge_Client::send( $payload );
		$status = ! empty( $result['ok'] ) ? 'sent' : 'failed';
		$outbox = null;
		if ( is_array( $result['data'] ) && ! empty( $result['data']['message_outbox_ids'][0] ) ) {
			$outbox = (string) $result['data']['message_outbox_ids'][0];
		}

		$wpdb->update(
			self::table( 'messages' ),
			array(
				'status'            => $status,
				'outbox_id'         => $outbox,
				'provider_response' => substr( (string) ( $result['raw'] ?? '' ), 0, 65000 ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $msg_id )
		);

		if ( empty( $result['ok'] ) ) {
			self::ledger_entry(
				(int) $account['id'],
				self::LEDGER_REFUND,
				$cost,
				'message',
				$msg_id,
				__( 'Refund failed send', 'webinocrm' ),
				false
			);
			return new WP_Error( 'send_failed', $result['message'] ?: __( 'SMS send failed.', 'webinocrm' ), array( 'status' => 502 ) );
		}

		return array(
			'ok'         => true,
			'data'       => $result['data'],
			'cost'       => $cost,
			'message_id' => $msg_id,
			'outbox_id'  => $outbox,
		);
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return int
	 */
	private static function count_recipients( array $payload ) {
		$recipients = self::extract_recipients( $payload );
		if ( $recipients ) {
			return count( $recipients );
		}
		if ( ! empty( $payload['params']['groups'] ) && is_array( $payload['params']['groups'] ) ) {
			$total = 0;
			foreach ( $payload['params']['groups'] as $g ) {
				if ( is_array( $g ) && ! empty( $g['recipients'] ) && is_array( $g['recipients'] ) ) {
					$total += count( $g['recipients'] );
				}
			}
			return max( 1, $total );
		}
		return 1;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return array<int,string>
	 */
	private static function extract_recipients( array $payload ) {
		if ( ! empty( $payload['recipients'] ) && is_array( $payload['recipients'] ) ) {
			return array_values( $payload['recipients'] );
		}
		if ( ! empty( $payload['params']['recipients'] ) && is_array( $payload['params']['recipients'] ) ) {
			return array_values( $payload['params']['recipients'] );
		}
		return array();
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $page Page.
	 * @param int    $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_domain_messages( $domain, $page = 1, $limit = 20 ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$offset = max( 0, ( (int) $page - 1 ) * (int) $limit );
		$table  = self::table( 'messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE domain = %s ORDER BY id DESC LIMIT %d OFFSET %d",
				$domain,
				(int) $limit,
				$offset
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_active_packages() {
		global $wpdb;
		$table = self::table( 'packages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			"SELECT * FROM $table WHERE status = 'active' ORDER BY sort ASC, amount ASC",
			ARRAY_A
		) ?: array();
	}

	/**
	 * @return void
	 */
	public static function seed_default_packages() {
		global $wpdb;
		$table = self::table( 'packages' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		if ( $count > 0 ) {
			return;
		}
		$packages = array(
			array( 'name' => __( 'Starter 50k', 'webinocrm' ), 'amount' => 50000, 'bonus' => 0, 'sort' => 0 ),
			array( 'name' => __( 'Standard 200k', 'webinocrm' ), 'amount' => 200000, 'bonus' => 10000, 'sort' => 1 ),
			array( 'name' => __( 'Pro 500k', 'webinocrm' ), 'amount' => 500000, 'bonus' => 50000, 'sort' => 2 ),
		);
		foreach ( $packages as $p ) {
			$wpdb->insert(
				$table,
				array_merge(
					$p,
					array(
						'status'     => 'active',
						'created_at' => current_time( 'mysql' ),
					)
				)
			);
		}
	}

	/**
	 * @param string $domain Domain must have valid license.
	 * @return true|WP_Error
	 */
	public static function assert_licensed_domain( $domain ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		if ( '' === $domain ) {
			return new WP_Error( 'missing_domain', __( 'Domain is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$check = WebinoCRM_License_Manager::validate_license( $domain, false );
		if ( empty( $check['valid'] ) ) {
			return new WP_Error(
				'license_invalid',
				$check['message'] ?? __( 'License is not active for this domain.', 'webinocrm' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Format account for API response.
	 *
	 * @param array<string,mixed> $account Account row.
	 * @return array<string,mixed>
	 */
	public static function format_account_public( $account ) {
		return array(
			'domain'       => (string) ( $account['domain'] ?? '' ),
			'balance'      => (float) ( $account['balance'] ?? 0 ),
			'default_from' => (string) ( $account['default_from'] ?? '' ),
			'status'       => (string) ( $account['status'] ?? self::STATUS_ACTIVE ),
			'expires_at'   => $account['expires_at'] ?? null,
			'price_per_unit' => self::price_per_unit(),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all_accounts() {
		global $wpdb;
		$table = self::table( 'accounts' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY domain ASC", ARRAY_A ) ?: array();
	}

	/**
	 * @param string $domain Domain.
	 * @param float  $amount Amount (positive credit, negative debit).
	 * @param string $note Note.
	 * @return true|WP_Error
	 */
	public static function adjust_balance( $domain, $amount, $note = '' ) {
		$account = self::get_or_create_account( $domain );
		$amount  = (float) $amount;
		if ( 0.0 === $amount ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid amount.', 'webinocrm' ) );
		}
		return self::ledger_entry(
			(int) $account['id'],
			self::LEDGER_ADJUST,
			abs( $amount ),
			'adjust',
			0,
			$note ?: __( 'Manual adjustment', 'webinocrm' ),
			$amount < 0
		);
	}

	/**
	 * @param int $page Page.
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_orders( $page = 1, $limit = 50 ) {
		global $wpdb;
		$offset = max( 0, ( (int) $page - 1 ) * (int) $limit );
		$table  = self::table( 'orders' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", (int) $limit, $offset ),
			ARRAY_A
		) ?: array();
	}

	/**
	 * @param array<string,mixed> $payload Edge send payload (no wallet).
	 * @return array<string,mixed>|WP_Error
	 */
	public static function admin_send( array $payload ) {
		if ( ! WebinoCRM_ModirPayamak_Edge_Client::is_configured() ) {
			return new WP_Error( 'not_configured', __( 'ModirPayamak is not enabled.', 'webinocrm' ), array( 'status' => 503 ) );
		}
		if ( empty( $payload['from_number'] ) ) {
			$payload['from_number'] = WebinoCRM_ModirPayamak_Edge_Client::default_from();
		}
		$result = WebinoCRM_ModirPayamak_Edge_Client::send( $payload );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'send_failed', $result['message'] ?: __( 'SMS send failed.', 'webinocrm' ) );
		}
		return array( 'ok' => true, 'data' => $result['data'] );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function dashboard_stats() {
		global $wpdb;
		$accounts = self::table( 'accounts' );
		$messages = self::table( 'messages' );
		$orders   = self::table( 'orders' );
		$total_customers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $accounts" );
		$sent_today      = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $messages WHERE status = %s AND DATE(created_at) = CURDATE()",
				'sent'
			)
		);
		$pending_orders  = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $orders WHERE status = %s", self::ORDER_PENDING )
		);
		$credit = WebinoCRM_ModirPayamak_Edge_Client::my_credit();
		return array(
			'total_customers' => $total_customers,
			'sent_today'      => $sent_today,
			'pending_orders'  => $pending_orders,
			'reseller_credit' => is_array( $credit['data'] ) ? $credit['data'] : null,
			'price_per_unit'  => self::price_per_unit(),
			'configured'      => WebinoCRM_ModirPayamak_Edge_Client::is_configured(),
		);
	}
}

WebinoCRM_ModirPayamak_Manager::init();
