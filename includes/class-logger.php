<?php
/**
 * WebinoCRM Logger
 * Supports both legacy CPT logs (add) and new system/user log tables.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Logger {

	/**
	 * Get logging options from settings.
	 *
	 * @return array
	 */
	private static function get_log_options() {
		$settings = WebinoCRM_Settings_Handler::get_all_settings();
		$defaults = array(
			'enable_logging_system'   => true,
			'log_system_errors'       => true,
			'log_system_debug'        => false,
			'log_console_messages'    => true,
			'log_button_errors'       => true,
			'enable_user_logging'     => true,
			'log_button_clicks'       => true,
			'log_form_submissions'    => true,
			'log_ajax_calls'          => true,
			'log_page_views'          => false,
			'log_max_file_size'       => 5000,
			'log_retention_days'      => 90,
			'enable_auto_log_cleanup' => true,
		);
		return wp_parse_args( $settings, $defaults );
	}

	private static function is_option_enabled( $key, $default = false ) {
		$opts = self::get_log_options();
		if ( ! isset( $opts[ $key ] ) ) {
			return $default;
		}
		$v = $opts[ $key ];
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_numeric( $v ) ) {
			return (int) $v === 1;
		}
		$v = strtolower( trim( (string) $v ) );
		return in_array( $v, array( '1', 'true', 'yes', 'on' ), true );
	}

	private static function is_logging_enabled() {
		return self::is_option_enabled( 'enable_logging_system', true );
	}

	private static function is_log_type_enabled( $log_type ) {
		if ( ! self::is_logging_enabled() ) {
			return false;
		}
		switch ( $log_type ) {
			case 'error':
				return self::is_option_enabled( 'log_system_errors', true );
			case 'debug':
				return self::is_option_enabled( 'log_system_debug', false );
			case 'console':
				return self::is_option_enabled( 'log_console_messages', true );
			case 'button_error':
				return self::is_option_enabled( 'log_button_errors', true );
			default:
				return true;
		}
	}

	private static function is_user_logging_enabled() {
		return self::is_option_enabled( 'enable_user_logging', true );
	}

	private static function is_user_action_enabled( $action_type ) {
		if ( ! self::is_user_logging_enabled() ) {
			return false;
		}
		switch ( $action_type ) {
			case 'button_click':
				return self::is_option_enabled( 'log_button_clicks', true );
			case 'form_submit':
				return self::is_option_enabled( 'log_form_submissions', true );
			case 'ajax_call':
				return self::is_option_enabled( 'log_ajax_calls', true );
			case 'page_view':
				return self::is_option_enabled( 'log_page_views', false );
			default:
				return true;
		}
	}

	private static function truncate_message( $message ) {
		$opts   = self::get_log_options();
		$max    = isset( $opts['log_max_file_size'] ) ? max( 0, (int) $opts['log_max_file_size'] ) : 5000;
		$str    = (string) $message;
		if ( $max > 0 && strlen( $str ) > $max ) {
			return substr( $str, 0, $max ) . '... [truncated]';
		}
		return $str;
	}

	/**
	 * Log system log (error, debug, console, button_error).
	 *
	 * @param string $message   Message text.
	 * @param string $log_type  error|debug|console|button_error.
	 * @param string $severity  info|warning|error|critical.
	 * @param array  $context   Optional context array (stored as JSON).
	 * @param string|null $file File path.
	 * @param int|null    $line Line number.
	 * @return int|false Insert ID or false.
	 */
	public static function log_system( $message, $log_type = 'debug', $severity = 'info', $context = array(), $file = null, $line = null ) {
		if ( ! self::is_log_type_enabled( $log_type ) ) {
			return false;
		}
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
		if ( ! $file && isset( $backtrace[0]['file'] ) ) {
			$file = $backtrace[0]['file'];
		}
		if ( $line === null && isset( $backtrace[0]['line'] ) ) {
			$line = $backtrace[0]['line'];
		}
		$message = self::truncate_message( $message );
		$user_id = get_current_user_id() ?: null;

		return WebinoCRM_Log_Database::log_system_log( array(
			'log_type' => $log_type,
			'severity' => $severity,
			'message'  => $message,
			'context'  => $context,
			'file'     => $file,
			'line'     => $line,
			'user_id'  => $user_id,
		) );
	}

	/**
	 * Log user action (button_click, form_submit, ajax_call, page_view).
	 */
	public static function log_user_action( $action_type, $action_description, $target_type = null, $target_id = null, $metadata = array() ) {
		if ( ! self::is_user_action_enabled( $action_type ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$action_description = self::truncate_message( $action_description );

		return WebinoCRM_Log_Database::log_user_log( array(
			'user_id'            => $user_id,
			'action_type'        => $action_type,
			'action_description' => $action_description,
			'target_type'        => $target_type,
			'target_id'          => $target_id,
			'metadata'           => $metadata,
		) );
	}

	public static function log_error( $message, $context = array(), $file = null, $line = null ) {
		return self::log_system( $message, 'system_error', 'error', $context, $file, $line );
	}

	public static function log_debug( $message, $context = array(), $file = null, $line = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return self::log_system( $message, 'debug', 'info', $context, $file, $line );
		}
		return false;
	}

	public static function log_console( $message, $log_type = 'console', $severity = 'info', $context = array() ) {
		return self::log_system( $message, $log_type, $severity, $context );
	}

	public static function log_button_error( $button_id, $error_message, $context = array() ) {
		$context['button_id'] = $button_id;
		return self::log_system(
			sprintf( 'Button error: %s - %s', $button_id, $error_message ),
			'button_error',
			'error',
			$context
		);
	}

	/**
	 * Get system logs (delegates to Log_Database).
	 */
	public static function get_system_logs( $args = array() ) {
		return WebinoCRM_Log_Database::get_system_logs( $args );
	}

	public static function get_system_logs_count( $args = array() ) {
		return WebinoCRM_Log_Database::get_system_logs_count( $args );
	}

	public static function get_user_logs( $args = array() ) {
		return WebinoCRM_Log_Database::get_user_logs( $args );
	}

	public static function get_user_logs_count( $args = array() ) {
		return WebinoCRM_Log_Database::get_user_logs_count( $args );
	}

	/**
	 * Delete all system logs.
	 */
	public static function delete_all_system_logs() {
		return WebinoCRM_Log_Database::delete_system_logs();
	}

	/**
	 * Cleanup old logs by retention (call from cron or manually).
	 */
	public static function cleanup_old_logs() {
		$opts = self::get_log_options();
		if ( ! self::is_option_enabled( 'enable_auto_log_cleanup', false ) ) {
			return 0;
		}
		$retention = isset( $opts['log_retention_days'] ) ? max( 7, (int) $opts['log_retention_days'] ) : 90;
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_system_logs';
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
		$count = $deleted ? $deleted : 0;
		$table_u = $wpdb->prefix . 'webinocrm_user_logs';
		$deleted_u = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_u} WHERE created_at < %s", $cutoff ) );
		$count += $deleted_u ? $deleted_u : 0;
		return $count;
	}

	// --- Legacy: CPT-based log (for backward compatibility) ---

	/**
	 * Adds a new log entry (legacy CPT).
	 *
	 * @param string $title The main message/title of the log.
	 * @param array  $args  Optional: content, type (log|notification|system_error), user_id, object_id.
	 */
	public static function add( $title, $args = array() ) {
		$defaults = array(
			'content'   => '',
			'type'      => 'log',
			'user_id'   => get_current_user_id(),
			'object_id' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$author_id = ( $args['type'] === 'system_error' ) ? 1 : $args['user_id'];

		$log_id = wp_insert_post( array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $args['content'] ),
			'post_type'    => 'crm_log',
			'post_status'  => 'publish',
			'post_author'  => $author_id,
		) );

		if ( ! is_wp_error( $log_id ) ) {
			update_post_meta( $log_id, '_log_type', sanitize_key( $args['type'] ) );
			update_post_meta( $log_id, '_related_object_id', (int) $args['object_id'] );
			if ( $args['type'] === 'notification' ) {
				update_post_meta( $log_id, '_is_read', '0' );
			}
		}
	}
}
