<?php
/**
 * WebinoCRM Log Database
 * Handles insert/read/delete for system_logs and user_logs tables.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Log_Database {

	/**
	 * Get client IP address.
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	/**
	 * Insert system log row.
	 *
	 * @param array $data Keys: log_type, severity, message, context, file, line, user_id.
	 * @return int|false Insert ID or false.
	 */
	public static function log_system_log( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_system_logs';

		$defaults = array(
			'log_type'   => 'debug',
			'severity'   => 'info',
			'message'    => '',
			'context'    => null,
			'file'       => null,
			'line'       => null,
			'user_id'    => get_current_user_id() ?: null,
			'ip_address' => self::get_client_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
		);
		$data = wp_parse_args( $data, $defaults );

		if ( is_array( $data['context'] ) ) {
			$data['context'] = wp_json_encode( $data['context'], JSON_UNESCAPED_UNICODE );
			if ( strlen( $data['context'] ) > 10000 ) {
				$data['context'] = substr( $data['context'], 0, 10000 );
			}
		}

		$result = $wpdb->insert(
			$table,
			array(
				'log_type'   => sanitize_text_field( $data['log_type'] ),
				'severity'   => sanitize_text_field( $data['severity'] ),
				'message'    => wp_kses_post( $data['message'] ),
				'context'    => $data['context'],
				'file'       => ! empty( $data['file'] ) ? sanitize_text_field( $data['file'] ) : null,
				'line'       => ! empty( $data['line'] ) ? (int) $data['line'] : null,
				'user_id'    => $data['user_id'] ? (int) $data['user_id'] : null,
				'ip_address' => $data['ip_address'],
				'user_agent' => $data['user_agent'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Insert user log row.
	 *
	 * @param array $data Keys: user_id, action_type, action_description, target_type, target_id, metadata.
	 * @return int|false Insert ID or false.
	 */
	public static function log_user_log( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_user_logs';

		$defaults = array(
			'user_id'             => get_current_user_id(),
			'action_type'         => 'unknown',
			'action_description'  => '',
			'target_type'         => null,
			'target_id'           => null,
			'metadata'            => null,
			'ip_address'          => self::get_client_ip(),
			'user_agent'          => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
		);
		$data = wp_parse_args( $data, $defaults );

		if ( ! $data['user_id'] ) {
			return false;
		}

		if ( is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'], JSON_UNESCAPED_UNICODE );
			if ( strlen( $data['metadata'] ) > 10000 ) {
				$data['metadata'] = substr( $data['metadata'], 0, 10000 );
			}
		}

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'            => (int) $data['user_id'],
				'action_type'        => sanitize_text_field( $data['action_type'] ),
				'action_description' => sanitize_text_field( $data['action_description'] ),
				'target_type'        => ! empty( $data['target_type'] ) ? sanitize_text_field( $data['target_type'] ) : null,
				'target_id'          => ! empty( $data['target_id'] ) ? (int) $data['target_id'] : null,
				'metadata'           => $data['metadata'],
				'ip_address'         => $data['ip_address'],
				'user_agent'         => $data['user_agent'],
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get system logs with filters.
	 *
	 * @param array $args date_from, date_to, log_type, severity, user_id, search, limit, offset, order_by, order.
	 * @return array of objects.
	 */
	public static function get_system_logs( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_system_logs';

		$defaults = array(
			'log_type'  => '',
			'severity'  => '',
			'user_id'   => '',
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
			'limit'     => 50,
			'offset'    => 0,
			'order_by'  => 'created_at',
			'order'     => 'DESC',
		);
		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );

		if ( ! empty( $args['log_type'] ) ) {
			$where[] = $wpdb->prepare( 'log_type = %s', $args['log_type'] );
		}
		if ( ! empty( $args['severity'] ) ) {
			$where[] = $wpdb->prepare( 'severity = %s', $args['severity'] );
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare( 'message LIKE %s', $search_term );
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( ! $order_by ) {
			$order_by = 'created_at DESC';
		}
		$limit  = (int) $args['limit'];
		$offset = (int) $args['offset'];

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d";
		return $wpdb->get_results( $wpdb->prepare( $query, $limit, $offset ) );
	}

	/**
	 * Get system logs count.
	 */
	public static function get_system_logs_count( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_system_logs';

		$defaults = array(
			'log_type'  => '',
			'severity'  => '',
			'user_id'   => '',
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
		);
		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );

		if ( ! empty( $args['log_type'] ) ) {
			$where[] = $wpdb->prepare( 'log_type = %s', $args['log_type'] );
		}
		if ( ! empty( $args['severity'] ) ) {
			$where[] = $wpdb->prepare( 'severity = %s', $args['severity'] );
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare( 'message LIKE %s', $search_term );
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get user logs with filters.
	 */
	public static function get_user_logs( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_user_logs';

		$defaults = array(
			'user_id'     => '',
			'action_type' => '',
			'target_type' => '',
			'date_from'   => '',
			'date_to'     => '',
			'search'      => '',
			'limit'       => 50,
			'offset'      => 0,
			'order_by'    => 'created_at',
			'order'       => 'DESC',
		);
		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}
		if ( ! empty( $args['action_type'] ) ) {
			$where[] = $wpdb->prepare( 'action_type = %s', $args['action_type'] );
		}
		if ( ! empty( $args['target_type'] ) ) {
			$where[] = $wpdb->prepare( 'target_type = %s', $args['target_type'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare( 'action_description LIKE %s', $search_term );
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( ! $order_by ) {
			$order_by = 'created_at DESC';
		}
		$limit  = (int) $args['limit'];
		$offset = (int) $args['offset'];

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d";
		return $wpdb->get_results( $wpdb->prepare( $query, $limit, $offset ) );
	}

	/**
	 * Get user logs count.
	 */
	public static function get_user_logs_count( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_user_logs';

		$defaults = array(
			'user_id'     => '',
			'action_type' => '',
			'target_type' => '',
			'date_from'   => '',
			'date_to'     => '',
			'search'      => '',
		);
		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}
		if ( ! empty( $args['action_type'] ) ) {
			$where[] = $wpdb->prepare( 'action_type = %s', $args['action_type'] );
		}
		if ( ! empty( $args['target_type'] ) ) {
			$where[] = $wpdb->prepare( 'target_type = %s', $args['target_type'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare( 'action_description LIKE %s', $search_term );
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Delete all system logs.
	 *
	 * @return int|false Number of rows deleted or false.
	 */
	public static function delete_system_logs() {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_system_logs';
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $total === 0 ) {
			return 0;
		}
		$deleted = $wpdb->query( "DELETE FROM {$table}" );
		return $deleted !== false ? $deleted : false;
	}

	/**
	 * Delete old system logs (for cleanup).
	 *
	 * @param int $limit Number of oldest rows to delete.
	 * @return int Rows deleted.
	 */
	public static function delete_old_system_logs( $limit = 500 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_system_logs';
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			return 0;
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY created_at ASC LIMIT %d", $limit ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($placeholders)", $ids ) );
	}
}
