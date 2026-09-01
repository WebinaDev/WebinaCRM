<?php
/**
 * Order-scoped SMS message history from CRM log.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists and refreshes SMS rows tagged with order context.
 */
final class WebinoCRM_Sms_Order_Messages_Service {

	/**
	 * @param string $domain   Licensed domain.
	 * @param int    $order_id WooCommerce order id.
	 * @return array{ok:bool,items:array<int,array<string,mixed>>}
	 */
	public static function list_for_order( $domain, $order_id ) {
		$domain   = WebinoCRM_License_Manager::normalize_domain( $domain );
		$order_id = (int) $order_id;
		if ( '' === $domain || $order_id <= 0 ) {
			return array( 'ok' => false, 'items' => array() );
		}

		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE domain = %s AND context_type = %s AND context_id = %s ORDER BY id ASC",
				$domain,
				'order',
				(string) $order_id
			),
			ARRAY_A
		) ?: array();

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::map_row( $row );
		}

		return array( 'ok' => true, 'items' => $items );
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private static function map_row( array $row ) {
		$status    = sanitize_key( (string) ( $row['status'] ?? '' ) );
		$outbox_id = (string) ( $row['outbox_id'] ?? '' );
		$event_key = (string) ( $row['event_key'] ?? '' );
		$phone     = self::extract_phone( $row );

		if ( '' === $event_key ) {
			$event_key = self::extract_event_key( $row );
		}

		if ( '' !== $outbox_id && in_array( $status, array( 'sent', 'pending' ), true ) ) {
			$status = self::refresh_outbox_status( $status, $outbox_id );
		}

		$created = (string) ( $row['created_at'] ?? '' );
		$time    = $created && function_exists( 'mysql2date' )
			? (string) mysql2date( 'c', $created, false )
			: ( $created ? gmdate( 'c', (int) strtotime( $created ) ) : gmdate( 'c' ) );

		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'status'         => $status,
			'outbox_id'      => $outbox_id,
			'recipient_role' => (string) ( $row['recipient_role'] ?? '' ),
			'event_key'      => $event_key,
			'phone'          => $phone,
			'created_at'     => $created,
			'time'           => $time,
		);
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return string
	 */
	private static function extract_phone( array $row ) {
		$raw = (string) ( $row['recipients'] ?? '' );
		if ( '' === $raw ) {
			return '';
		}
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && ! empty( $decoded[0] ) ) {
			return (string) $decoded[0];
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return string
	 */
	private static function extract_event_key( array $row ) {
		$body = (string) ( $row['message_body'] ?? '' );
		if ( '' === $body ) {
			return '';
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		if ( ! empty( $decoded['event_key'] ) ) {
			return sanitize_key( (string) $decoded['event_key'] );
		}
		if ( ! empty( $decoded['reason'] ) && ! empty( $decoded['event_key'] ) ) {
			return sanitize_key( (string) $decoded['event_key'] );
		}
		return '';
	}

	/**
	 * @param string $status    Current DB status.
	 * @param string $outbox_id Edge outbox id.
	 * @return string
	 */
	private static function refresh_outbox_status( $status, $outbox_id ) {
		$edge = WebinoCRM_ModirPayamak_Edge_Client::report_outbox_by_id( $outbox_id );
		if ( empty( $edge['ok'] ) || ! is_array( $edge['data'] ?? null ) ) {
			$bulk = WebinoCRM_ModirPayamak_Edge_Client::report_bulk_stats( $outbox_id );
			if ( ! empty( $bulk['ok'] ) && is_array( $bulk['data'] ?? null ) ) {
				return self::status_from_edge_payload( $status, $bulk['data'] );
			}
			return $status;
		}
		return self::status_from_edge_payload( $status, $edge['data'] );
	}

	/**
	 * @param string              $fallback Fallback status.
	 * @param array<string,mixed> $data     Edge payload.
	 * @return string
	 */
	private static function status_from_edge_payload( $fallback, array $data ) {
		$candidates = array(
			$data['status'] ?? '',
			$data['state'] ?? '',
			$data['delivery_status'] ?? '',
			$data['message_status'] ?? '',
		);
		if ( isset( $data['stats'] ) && is_array( $data['stats'] ) ) {
			$stats = $data['stats'];
			if ( ! empty( $stats['delivered'] ) && empty( $stats['failed'] ) ) {
				return 'delivered';
			}
			if ( ! empty( $stats['failed'] ) ) {
				return 'failed';
			}
		}
		foreach ( $candidates as $raw ) {
			$s = strtolower( sanitize_key( (string) $raw ) );
			if ( '' === $s ) {
				continue;
			}
			if ( str_contains( $s, 'deliver' ) || in_array( $s, array( 'done', 'success', 'completed' ), true ) ) {
				return 'delivered';
			}
			if ( str_contains( $s, 'fail' ) || in_array( $s, array( 'error', 'rejected', 'undelivered' ), true ) ) {
				return 'failed';
			}
			if ( str_contains( $s, 'sent' ) || str_contains( $s, 'queue' ) || 'pending' === $s ) {
				return 'sent';
			}
		}
		return $fallback;
	}
}
