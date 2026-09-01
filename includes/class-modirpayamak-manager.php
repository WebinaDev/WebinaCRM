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
			return;
		}
		self::ensure_domain_numbers_table();
		self::maybe_upgrade_domain_numbers_schema();
		if ( class_exists( 'WebinoCRM_ModirPayamak_Tariffs' ) ) {
			WebinoCRM_ModirPayamak_Tariffs::ensure_table();
		}
	}

	/**
	 * Ensure domain_numbers table exists (upgrades).
	 *
	 * @return void
	 */
	public static function ensure_domain_numbers_table() {
		global $wpdb;
		$table = self::table( 'domain_numbers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) === $table ) {
			return;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-installer.php';
		WebinoCRM_Installer::create_modirpayamak_tables();
	}

	/**
	 * Migrate domain_numbers: multi-number per domain, personal role.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_domain_numbers_schema() {
		global $wpdb;
		$table = self::table( 'domain_numbers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) !== $table ) {
			return;
		}
		$option = 'webinocrm_modirpayamak_domain_numbers_v2';
		if ( '1' === (string) get_option( $option, '' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE $table SET role = 'personal' WHERE role = 'marketing'" );

		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A );
		$has_domain_role = false;
		$has_domain_number = false;
		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $idx ) {
				$name = (string) ( $idx['Key_name'] ?? '' );
				if ( 'domain_role' === $name && (int) ( $idx['Non_unique'] ?? 1 ) === 0 ) {
					$has_domain_role = true;
				}
				if ( 'domain_number' === $name ) {
					$has_domain_number = true;
				}
			}
		}
		if ( $has_domain_role ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table DROP INDEX domain_role" );
		}
		if ( ! $has_domain_number ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD UNIQUE KEY domain_number (domain, number)" );
		}
		// Non-unique helper index for role lookups.
		$has_role_key = false;
		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $idx ) {
				if ( 'domain_role' === (string) ( $idx['Key_name'] ?? '' ) && (int) ( $idx['Non_unique'] ?? 0 ) === 1 ) {
					$has_role_key = true;
				}
			}
		}
		if ( ! $has_role_key && ! $has_domain_role ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD KEY domain_role (domain, role)" );
		}

		update_option( $option, '1', false );
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
	public static function deduct_for_send( $domain, $amount, $message_id = 0, $note = '' ) {
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
			$note !== '' ? $note : __( 'SMS send', 'webinocrm' ),
			true
		);
	}

	/**
	 * Extract message text from Edge send payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public static function extract_message_text( array $payload ) {
		if ( ! empty( $payload['message'] ) && is_string( $payload['message'] ) ) {
			return $payload['message'];
		}
		if ( ! empty( $payload['params']['message'] ) && is_string( $payload['params']['message'] ) ) {
			return $payload['params']['message'];
		}
		// Pattern: concatenate values for part estimate (conservative length).
		if ( ! empty( $payload['params']['code'] ) || ! empty( $payload['code'] ) ) {
			$vals = array();
			if ( ! empty( $payload['params']['values'] ) && is_array( $payload['params']['values'] ) ) {
				$vals = $payload['params']['values'];
			} elseif ( ! empty( $payload['params'] ) && is_array( $payload['params'] ) ) {
				foreach ( $payload['params'] as $k => $v ) {
					if ( in_array( $k, array( 'code', 'recipients', 'values' ), true ) ) {
						continue;
					}
					if ( is_scalar( $v ) ) {
						$vals[] = (string) $v;
					}
				}
			}
			return implode( ' ', array_map( 'strval', $vals ) );
		}
		return '';
	}

	/**
	 * Estimate customer cost (Toman) — legacy recipient-only API kept for callers.
	 *
	 * @param int $recipient_count Recipients.
	 * @return float
	 */
	public static function estimate_customer_cost( $recipient_count ) {
		$n = max( 1, (int) $recipient_count );
		$quote = WebinoCRM_ModirPayamak_Tariffs::quote_send(
			WebinoCRM_ModirPayamak_Edge_Client::default_from(),
			'',
			array_fill( 0, $n, '' )
		);
		return (float) $quote['cost_toman'];
	}

	/**
	 * Full quote for a payload (Toman debit amount).
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return array{cost_toman:float,cost_rial:float,parts:int,line_type:string,encoding:string,recipient_count:int,breakdown:array,note:string}
	 */
	public static function quote_payload( array $payload ) {
		$from = ! empty( $payload['from_number'] ) ? (string) $payload['from_number'] : WebinoCRM_ModirPayamak_Edge_Client::default_from();
		$text = self::extract_message_text( $payload );
		$recipients = self::extract_recipients( $payload );
		if ( ! $recipients && ! empty( $payload['params']['groups'] ) && is_array( $payload['params']['groups'] ) ) {
			foreach ( $payload['params']['groups'] as $g ) {
				if ( is_array( $g ) && ! empty( $g['recipients'] ) && is_array( $g['recipients'] ) ) {
					foreach ( $g['recipients'] as $r ) {
						$recipients[] = (string) $r;
					}
				}
			}
		}
		$edge_type = (string) ( $payload['line_type'] ?? $payload['type'] ?? '' );
		return WebinoCRM_ModirPayamak_Tariffs::quote_send( $from, $text, $recipients, $edge_type );
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

		$quote = self::quote_payload( $payload );
		$cost  = max( 0.0001, (float) $quote['cost_toman'] );

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

		$deduct = self::deduct_for_send( $domain, $cost, $msg_id, (string) ( $quote['note'] ?? '' ) );
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
			'quote'      => $quote,
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
		$domain = (string) ( $account['domain'] ?? '' );
		return array(
			'id'             => (int) ( $account['id'] ?? 0 ),
			'domain'         => $domain,
			'balance'        => (float) ( $account['balance'] ?? 0 ),
			'default_from'   => (string) ( $account['default_from'] ?? '' ),
			'status'         => (string) ( $account['status'] ?? self::STATUS_ACTIVE ),
			'expires_at'     => $account['expires_at'] ?? null,
			'price_per_unit' => self::price_per_unit(),
			'numbers'        => $domain ? self::get_domain_numbers( $domain ) : array(),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all_accounts() {
		global $wpdb;
		$table = self::table( 'accounts' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY domain ASC", ARRAY_A ) ?: array();
		return array_map(
			static function ( $row ) {
				$row['numbers'] = WebinoCRM_ModirPayamak_Manager::get_domain_numbers( (string) ( $row['domain'] ?? '' ) );
				return $row;
			},
			$rows
		);
	}

	/**
	 * @param string $domain Domain.
	 * @param string $status Status.
	 * @return true|WP_Error
	 */
	public static function set_account_status( $domain, $status ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_SUSPENDED ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid SMS account status.', 'webinocrm' ) );
		}
		$account = self::get_or_create_account( $domain );
		if ( empty( $account['id'] ) ) {
			return new WP_Error( 'not_found', __( 'Account not found.', 'webinocrm' ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table( 'accounts' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $account['id'] )
		);
		return true;
	}

	/**
	 * Create SMS accounts for all active licenses missing one.
	 *
	 * @return array{created:int,total:int}
	 */
	public static function ensure_accounts_for_active_licenses() {
		global $wpdb;
		$licenses = $wpdb->prefix . 'webinocrm_licenses';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$domains  = $wpdb->get_col( $wpdb->prepare( "SELECT domain FROM $licenses WHERE status = %s", 'active' ) ) ?: array();
		$created  = 0;
		foreach ( $domains as $domain ) {
			$before = self::get_account_by_domain( (string) $domain );
			$row    = self::get_or_create_account( (string) $domain );
			if ( ! $before && ! empty( $row['id'] ) ) {
				++$created;
			} elseif ( $before && self::STATUS_SUSPENDED === ( $before['status'] ?? '' ) ) {
				self::set_account_status( (string) $domain, self::STATUS_ACTIVE );
			}
		}
		return array(
			'created' => $created,
			'total'   => count( $domains ),
		);
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $page Page.
	 * @param int    $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_ledger( $domain, $page = 1, $limit = 50 ) {
		$account = self::get_account_by_domain( $domain );
		if ( ! $account ) {
			return array();
		}
		global $wpdb;
		$offset = max( 0, ( (int) $page - 1 ) * (int) $limit );
		$table  = self::table( 'ledger' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE account_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				(int) $account['id'],
				(int) $limit,
				$offset
			),
			ARRAY_A
		) ?: array();
	}

	const ROLE_SERVICE   = 'service';
	/** @deprecated Use ROLE_PERSONAL. Kept as alias for older callers. */
	const ROLE_MARKETING = 'personal';
	const ROLE_PERSONAL  = 'personal';

	/**
	 * Normalize line role to service|personal.
	 *
	 * @param string $role Role.
	 * @return string
	 */
	public static function normalize_number_role( $role ) {
		$role = sanitize_key( (string) $role );
		if ( in_array( $role, array( 'personal', 'marketing', 'dedicated' ), true ) ) {
			return self::ROLE_PERSONAL;
		}
		return self::ROLE_SERVICE;
	}

	/**
	 * @param string $domain Domain.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_domain_numbers( $domain ) {
		self::ensure_domain_numbers_table();
		self::maybe_upgrade_domain_numbers_schema();
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		if ( '' === $domain ) {
			return array();
		}
		$table = self::table( 'domain_numbers' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE domain = %s ORDER BY role ASC, is_default DESC, id ASC", $domain ),
			ARRAY_A
		) ?: array();
		foreach ( $rows as &$row ) {
			if ( 'marketing' === ( $row['role'] ?? '' ) ) {
				$row['role'] = self::ROLE_PERSONAL;
			}
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Domains that have a given pool number attached.
	 *
	 * @param string $number Number.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_number_attachments( $number ) {
		self::ensure_domain_numbers_table();
		self::maybe_upgrade_domain_numbers_schema();
		global $wpdb;
		$number = sanitize_text_field( (string) $number );
		if ( '' === $number ) {
			return array();
		}
		$table = self::table( 'domain_numbers' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE number = %s ORDER BY domain ASC", $number ),
			ARRAY_A
		) ?: array();
		foreach ( $rows as &$row ) {
			if ( 'marketing' === ( $row['role'] ?? '' ) ) {
				$row['role'] = self::ROLE_PERSONAL;
			}
		}
		unset( $row );
		return $rows;
	}

	/**
	 * @param string $domain Domain.
	 * @param string $role service|personal.
	 * @return string
	 */
	public static function get_domain_number_for_role( $domain, $role = self::ROLE_SERVICE ) {
		$role = self::normalize_number_role( $role );
		$fallback = '';
		foreach ( self::get_domain_numbers( $domain ) as $row ) {
			if ( $role !== ( $row['role'] ?? '' ) || empty( $row['number'] ) ) {
				continue;
			}
			if ( ! empty( $row['is_default'] ) ) {
				return (string) $row['number'];
			}
			if ( '' === $fallback ) {
				$fallback = (string) $row['number'];
			}
		}
		return $fallback;
	}

	/**
	 * Attach a pool number to a domain (many numbers per domain; unique domain+number).
	 *
	 * @param string $domain Domain.
	 * @param string $number E.164 / line number.
	 * @param string $role service|personal.
	 * @param string $label Optional label.
	 * @param bool   $make_default Make default for role.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function attach_domain_number( $domain, $number, $role = self::ROLE_SERVICE, $label = '', $make_default = true ) {
		self::ensure_domain_numbers_table();
		self::maybe_upgrade_domain_numbers_schema();
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$number = sanitize_text_field( (string) $number );
		$role   = self::normalize_number_role( $role );
		$label  = sanitize_text_field( (string) $label );
		if ( '' === $domain || '' === $number ) {
			return new WP_Error( 'invalid', __( 'Domain and number are required.', 'webinocrm' ) );
		}

		self::get_or_create_account( $domain );
		global $wpdb;
		$table    = self::table( 'domain_numbers' );
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE domain = %s AND number = %s LIMIT 1", $domain, $number ),
			ARRAY_A
		);

		$is_default = $make_default ? 1 : 0;
		if ( $make_default ) {
			$wpdb->update(
				$table,
				array( 'is_default' => 0, 'updated_at' => current_time( 'mysql' ) ),
				array(
					'domain' => $domain,
					'role'   => $role,
				)
			);
		} elseif ( ! $existing ) {
			$has_default = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE domain = %s AND role = %s AND is_default = 1 LIMIT 1",
					$domain,
					$role
				)
			);
			$is_default = $has_default ? 0 : 1;
		}

		$data = array(
			'number'     => $number,
			'role'       => $role,
			'label'      => $label,
			'is_default' => $is_default,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			$id = (int) $existing['id'];
		} else {
			$wpdb->insert(
				$table,
				array_merge(
					$data,
					array(
						'domain'     => $domain,
						'created_at' => current_time( 'mysql' ),
					)
				)
			);
			$id = (int) $wpdb->insert_id;
		}

		if ( self::ROLE_SERVICE === $role && $is_default ) {
			$wpdb->update(
				self::table( 'accounts' ),
				array(
					'default_from' => $number,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'domain' => $domain )
			);
		}
		self::sync_sender_settings_from_attach( $domain );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
		return $row ?: array();
	}

	/**
	 * Detach by domain + number (preferred) or legacy domain + role.
	 *
	 * @param string $domain Domain.
	 * @param string $role_or_number Role or number.
	 * @param string $number Optional number when role is passed.
	 * @return true|WP_Error
	 */
	public static function detach_domain_number( $domain, $role_or_number, $number = '' ) {
		self::ensure_domain_numbers_table();
		self::maybe_upgrade_domain_numbers_schema();
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		global $wpdb;
		$table = self::table( 'domain_numbers' );

		$number = sanitize_text_field( (string) $number );
		$arg    = sanitize_text_field( (string) $role_or_number );

		if ( '' !== $number ) {
			$role = self::normalize_number_role( $arg );
			$wpdb->delete(
				$table,
				array(
					'domain' => $domain,
					'number' => $number,
					'role'   => $role,
				)
			);
		} elseif ( preg_match( '/^\+?\d{5,}$/', $arg ) ) {
			$wpdb->delete(
				$table,
				array(
					'domain' => $domain,
					'number' => $arg,
				)
			);
		} else {
			$role = self::normalize_number_role( $arg );
			// Legacy: detach default (or all) for role — only default row to avoid mass wipe if multi.
			$default = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE domain = %s AND role = %s ORDER BY is_default DESC, id ASC LIMIT 1",
					$domain,
					$role
				),
				ARRAY_A
			);
			if ( $default ) {
				$wpdb->delete( $table, array( 'id' => (int) $default['id'] ) );
			}
		}

		self::sync_sender_settings_from_attach( $domain );
		return true;
	}

	/**
	 * Write attached lines into site/shop SMS settings.
	 *
	 * @param string $domain Domain.
	 * @return void
	 */
	public static function sync_sender_settings_from_attach( $domain ) {
		if ( ! class_exists( 'WebinoCRM_Sms_Settings_Service' ) ) {
			return;
		}
		$service  = self::get_domain_number_for_role( $domain, self::ROLE_SERVICE );
		$personal = self::get_domain_number_for_role( $domain, self::ROLE_PERSONAL );
		foreach ( array( WebinoCRM_Sms_Constants::SCOPE_SITE, WebinoCRM_Sms_Constants::SCOPE_SHOP ) as $scope ) {
			$patch = array();
			if ( '' !== $service ) {
				$patch['sender_line_service'] = $service;
			}
			if ( '' !== $personal ) {
				$patch['sender_line_dedicated'] = $personal;
			}
			if ( $patch ) {
				WebinoCRM_Sms_Settings_Service::save( $domain, $scope, $patch );
			}
		}
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
