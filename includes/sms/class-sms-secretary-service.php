<?php
/**
 * Local SMS secretary rules (keyword auto-reply / forward / membership).
 *
 * Edge has no secretary API; rules are stored and processed against inbox locally.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + inbox processing for domain secretaries.
 */
final class WebinoCRM_Sms_Secretary_Service {

	const TYPE_AUTO_REPLY     = 'auto_reply';
	const TYPE_INBOX_FORWARD  = 'inbox_forward';
	const TYPE_CODE_READER    = 'code_reader';
	const TYPE_MEMBERSHIP     = 'membership';

	/**
	 * @return array<int,string>
	 */
	public static function types() {
		return array(
			self::TYPE_AUTO_REPLY,
			self::TYPE_INBOX_FORWARD,
			self::TYPE_CODE_READER,
			self::TYPE_MEMBERSHIP,
		);
	}

	/**
	 * @param string $domain Domain.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_rules( $domain ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$table  = WebinoCRM_Sms_Install::table( 'secretaries' );
		self::ensure_table();
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE domain = %s ORDER BY id DESC", $domain ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string              $domain Domain.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save_rule( $domain, array $input ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		self::ensure_table();
		$table  = WebinoCRM_Sms_Install::table( 'secretaries' );
		$id     = (int) ( $input['id'] ?? 0 );
		$type   = sanitize_key( (string) ( $input['type'] ?? self::TYPE_AUTO_REPLY ) );
		if ( ! in_array( $type, self::types(), true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid secretary type.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$keywords = self::normalize_keywords( $input['keywords'] ?? '' );
		$row      = array(
			'domain'       => $domain,
			'type'         => $type,
			'name'         => sanitize_text_field( (string) ( $input['name'] ?? $type ) ),
			'keywords'     => $keywords,
			'reply_body'   => sanitize_textarea_field( (string) ( $input['reply_body'] ?? '' ) ),
			'pattern_code' => sanitize_text_field( (string) ( $input['pattern_code'] ?? '' ) ),
			'forward_to'   => sanitize_text_field( (string) ( $input['forward_to'] ?? '' ) ),
			'enabled'      => empty( $input['enabled'] ) && array_key_exists( 'enabled', $input ) ? 0 : 1,
			'updated_at'   => current_time( 'mysql' ),
		);
		if ( $id > 0 ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM $table WHERE id = %d AND domain = %s", $id, $domain )
			);
			if ( ! $exists ) {
				return new WP_Error( 'not_found', __( 'Secretary rule not found.', 'webinocrm' ), array( 'status' => 404 ) );
			}
			$wpdb->update( $table, $row, array( 'id' => $id ) );
			$row['id'] = $id;
			return array( 'ok' => true, 'rule' => $row );
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row );
		$row['id'] = (int) $wpdb->insert_id;
		return array( 'ok' => true, 'rule' => $row );
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $id Rule id.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function delete_rule( $domain, $id ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$id     = (int) $id;
		self::ensure_table();
		$table = WebinoCRM_Sms_Install::table( 'secretaries' );
		$deleted = $wpdb->delete( $table, array( 'id' => $id, 'domain' => $domain ) );
		if ( ! $deleted ) {
			return new WP_Error( 'not_found', __( 'Secretary rule not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		return array( 'ok' => true, 'id' => $id );
	}

	/**
	 * Pull recent inbox and apply enabled rules.
	 *
	 * @param string $domain Domain.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function process_inbox( $domain ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$rules  = array_values(
			array_filter(
				self::list_rules( $domain ),
				static function ( $r ) {
					return ! empty( $r['enabled'] );
				}
			)
		);
		if ( ! $rules ) {
			return array( 'ok' => true, 'processed' => 0, 'matched' => 0 );
		}

		$edge = WebinoCRM_ModirPayamak_Edge_Client::report_inbox( 1, 30 );
		if ( empty( $edge['ok'] ) ) {
			return new WP_Error( 'inbox_failed', __( 'Could not fetch inbox.', 'webinocrm' ), array( 'status' => 502 ) );
		}
		$items = $edge['data'] ?? array();
		if ( isset( $items['data'] ) && is_array( $items['data'] ) ) {
			$items = $items['data'];
		}
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$processed = 0;
		$matched   = 0;
		$actions   = array();
		$seen_key  = 'webinocrm_sms_secretary_seen_' . md5( $domain );
		$seen      = get_transient( $seen_key );
		if ( ! is_array( $seen ) ) {
			$seen = array();
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			++$processed;
			$msg_id = (string) ( $item['id'] ?? $item['message_id'] ?? $item['inbox_id'] ?? '' );
			$text   = (string) ( $item['message'] ?? $item['text'] ?? $item['body'] ?? '' );
			$from   = (string) ( $item['from'] ?? $item['sender'] ?? $item['number'] ?? '' );
			$dedupe = $msg_id !== '' ? $msg_id : md5( $from . '|' . $text );
			if ( isset( $seen[ $dedupe ] ) ) {
				continue;
			}
			$seen[ $dedupe ] = time();

			foreach ( $rules as $rule ) {
				if ( ! self::keywords_match( (string) ( $rule['keywords'] ?? '' ), $text ) ) {
					continue;
				}
				++$matched;
				$action = self::apply_rule( $domain, $rule, $from, $text );
				$actions[] = array(
					'rule_id' => (int) ( $rule['id'] ?? 0 ),
					'type'    => (string) ( $rule['type'] ?? '' ),
					'from'    => $from,
					'result'  => is_wp_error( $action ) ? $action->get_error_message() : $action,
				);
			}
		}

		// Keep last 200 seen ids.
		if ( count( $seen ) > 200 ) {
			asort( $seen );
			$seen = array_slice( $seen, -200, null, true );
		}
		set_transient( $seen_key, $seen, WEEK_IN_SECONDS );

		return array(
			'ok'        => true,
			'processed' => $processed,
			'matched'   => $matched,
			'actions'   => $actions,
		);
	}

	/**
	 * @param string              $domain Domain.
	 * @param array<string,mixed> $rule Rule.
	 * @param string              $from Sender phone.
	 * @param string              $text Inbox text.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function apply_rule( $domain, array $rule, $from, $text ) {
		$type = (string) ( $rule['type'] ?? '' );
		$from = WebinoCRM_Sms_Template_Service::normalize_phone( $from );

		if ( self::TYPE_MEMBERSHIP === $type ) {
			return WebinoCRM_Sms_Newsletter_Service::subscribe( $domain, 0, $from );
		}

		if ( self::TYPE_INBOX_FORWARD === $type || self::TYPE_CODE_READER === $type ) {
			$forward = WebinoCRM_Sms_Template_Service::normalize_phone( (string) ( $rule['forward_to'] ?? '' ) );
			if ( '' === $forward ) {
				return array( 'skipped' => true, 'reason' => 'no_forward' );
			}
			$body = trim( (string) ( $rule['reply_body'] ?? '' ) );
			if ( '' === $body ) {
				$body = sprintf( '[%s] %s: %s', $type, $from, $text );
			} else {
				$body = str_replace(
					array( '{from}', '{message}', '{code}' ),
					array( $from, $text, trim( $text ) ),
					$body
				);
			}
			$line = WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, false );
			return WebinoCRM_ModirPayamak_Manager::customer_send(
				$domain,
				array(
					'sending_type' => 'webservice',
					'from_number'  => $line,
					'message'      => $body,
					'params'       => array( 'recipients' => array( $forward ) ),
				)
			);
		}

		// auto_reply
		if ( '' === $from ) {
			return array( 'skipped' => true, 'reason' => 'no_from' );
		}
		$code = (string) ( $rule['pattern_code'] ?? '' );
		$line = WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, true );
		if ( '' !== $code ) {
			return WebinoCRM_ModirPayamak_Manager::customer_send(
				$domain,
				array(
					'sending_type' => 'pattern',
					'from_number'  => $line,
					'code'         => $code,
					'recipients'   => array( $from ),
					'params'       => array( 'message' => $text ),
				)
			);
		}
		$reply = trim( (string) ( $rule['reply_body'] ?? '' ) );
		if ( '' === $reply ) {
			return array( 'skipped' => true, 'reason' => 'empty_reply' );
		}
		$reply = str_replace( array( '{from}', '{message}' ), array( $from, $text ), $reply );
		return WebinoCRM_ModirPayamak_Manager::customer_send(
			$domain,
			array(
				'sending_type' => 'webservice',
				'from_number'  => WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, false ),
				'message'      => $reply,
				'params'       => array( 'recipients' => array( $from ) ),
			)
		);
	}

	/**
	 * @param mixed $raw Keywords.
	 * @return string
	 */
	private static function normalize_keywords( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}
		$parts = preg_split( '/[\s,;|]+/', (string) $raw );
		$out   = array();
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( '' !== $p ) {
				$out[] = $p;
			}
		}
		return implode( ',', $out );
	}

	/**
	 * @param string $keywords Comma-separated.
	 * @param string $text Message.
	 * @return bool
	 */
	private static function keywords_match( $keywords, $text ) {
		$keywords = trim( $keywords );
		if ( '' === $keywords || '*' === $keywords ) {
			return true;
		}
		$text = mb_strtolower( (string) $text );
		foreach ( explode( ',', $keywords ) as $kw ) {
			$kw = trim( mb_strtolower( $kw ) );
			if ( '' !== $kw && false !== mb_strpos( $text, $kw ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return void
	 */
	public static function ensure_table() {
		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'secretaries' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) === $table ) {
			return;
		}
		WebinoCRM_Sms_Install::create_secretaries_table();
	}
}
