<?php
/**
 * WebinoCRM Dashboard AJAX Handler (stats for SPA dashboard)
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Dashboard_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

	public function __construct() {
		add_action( 'wp_ajax_webinocrm_get_dashboard_stats', [ $this, 'get_dashboard_stats' ] );
		add_action( 'wp_ajax_webinocrm_get_dashboard_full', [ $this, 'get_dashboard_full' ] );
		add_action( 'wp_ajax_webinocrm_get_team_member_stats', [ $this, 'get_team_member_stats' ] );
		add_action( 'wp_ajax_webinocrm_get_client_stats', [ $this, 'get_client_stats' ] );
		add_action( 'wp_ajax_webinocrm_get_logs', [ $this, 'get_logs' ] );
		add_action( 'wp_ajax_webinocrm_get_reports', [ $this, 'get_reports' ] );
		// System & user logs (DB tables)
		add_action( 'wp_ajax_webinocrm_get_system_logs', [ $this, 'get_system_logs' ] );
		add_action( 'wp_ajax_webinocrm_get_user_logs', [ $this, 'get_user_logs' ] );
		add_action( 'wp_ajax_webinocrm_log_console', [ $this, 'log_console' ] );
		add_action( 'wp_ajax_webinocrm_log_user_action', [ $this, 'log_user_action' ] );
		add_action( 'wp_ajax_webinocrm_delete_system_logs', [ $this, 'delete_system_logs' ] );
		add_action( 'wp_ajax_webinocrm_export_reports_csv', [ $this, 'export_reports_csv' ] );
		// Visitor statistics: track (nopriv + logged-in), get stats (admin only)
		add_action( 'wp_ajax_webinocrm_track_visit', [ $this, 'track_visit' ] );
		add_action( 'wp_ajax_nopriv_webinocrm_track_visit', [ $this, 'track_visit' ] );
		add_action( 'wp_ajax_webinocrm_get_visitor_stats', [ $this, 'get_visitor_stats' ] );
		add_action( 'wp_ajax_webinocrm_save_visitor_settings', [ $this, 'save_visitor_settings' ] );
	}

	/**
	 * Get logs (events or system) for React dashboard.
	 */
	public function get_logs() {
		$this->emit_service( array( 'WebinoCRM_Logs_Service', 'list' ) );
	}

	/**
	 * Return dashboard stats for system manager (for SPA dashboard widgets).
	 */
	public function get_dashboard_stats() {
		$this->emit_service( array( 'WebinoCRM_Dashboard_Service', 'summary' ) );
	}

	/**
	 * Return full dashboard data for system manager (stats, team, running projects, etc.).
	 */
	public function get_dashboard_full() {
		$this->emit_service( array( 'WebinoCRM_Dashboard_Service', 'full' ) );
	}

	/**
	 * Return dashboard stats for team member.
	 */
	public function get_team_member_stats() {
		$this->emit_service( array( 'WebinoCRM_Dashboard_Service', 'team_stats' ) );
	}

	/**
	 * Return dashboard stats for client/customer.
	 */
	public function get_client_stats() {
		$this->emit_service( array( 'WebinoCRM_Dashboard_Service', 'client_stats' ) );
	}

	/**
	 * Get reports data for React (tab: overview, finance, tasks, tickets, agile).
	 */
	public function get_reports() {
		$this->emit_service( array( 'WebinoCRM_Reports_Service', 'get' ) );
	}

	/**
	 * Export reports as CSV (contracts or tasks).
	 */
	public function export_reports_csv() {
		$this->emit_stream( array( 'WebinoCRM_Reports_Service', 'export' ) );
	}

	/**
	 * Get system logs (from webinocrm_system_logs table).
	 */
	public function get_system_logs() {
		$this->emit_service( array( 'WebinoCRM_Logs_Service', 'system' ) );
	}

	/**
	 * Get user logs (from webinocrm_user_logs table).
	 */
	public function get_user_logs() {
		if ( ! check_ajax_referer( 'webinocrm-ajax-nonce', 'security', false ) ) {
			wp_send_json_error( [ 'message' => __( 'درخواست نامعتبر.', 'webinocrm' ) ] );
		}
		if ( ! webinocrm_user_can_view_logs() ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}
		$args = [
			'date_from'   => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
			'date_to'     => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
			'user_id'     => isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : '',
			'action_type' => isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '',
			'target_type' => isset( $_POST['target_type'] ) ? sanitize_text_field( wp_unslash( $_POST['target_type'] ) ) : '',
			'search'      => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'limit'       => isset( $_POST['limit'] ) ? min( 100, max( 1, (int) $_POST['limit'] ) ) : 50,
			'offset'      => isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0,
		];
		$logs  = WebinoCRM_Logger::get_user_logs( $args );
		$total = WebinoCRM_Logger::get_user_logs_count( $args );
		wp_send_json_success( [ 'logs' => $logs, 'total' => $total ] );
	}

	/**
	 * Log console message from frontend.
	 */
	public function log_console() {
		if ( ! check_ajax_referer( 'webinocrm-ajax-nonce', 'security', false ) ) {
			wp_send_json_error( [ 'message' => __( 'درخواست نامعتبر.', 'webinocrm' ) ] );
		}
		$log_type = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : 'console';
		$severity = isset( $_POST['severity'] ) ? sanitize_text_field( wp_unslash( $_POST['severity'] ) ) : 'info';
		$message  = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
		$context  = [];
		if ( ! empty( $_POST['context'] ) && is_string( $_POST['context'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['context'] ), true );
			if ( is_array( $decoded ) ) {
				$context = $decoded;
			}
		}
		$id = WebinoCRM_Logger::log_console( $message, $log_type, $severity, $context );
		wp_send_json_success( [ 'id' => $id ] );
	}

	/**
	 * Log user action from frontend (button_click, form_submit, ajax_call, page_view).
	 */
	public function log_user_action() {
		if ( ! check_ajax_referer( 'webinocrm-ajax-nonce', 'security', false ) ) {
			wp_send_json_error( [ 'message' => __( 'درخواست نامعتبر.', 'webinocrm' ) ] );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_success( [ 'id' => null ] );
			return;
		}
		$action_type        = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'button_click';
		$action_description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$target_type        = isset( $_POST['target_type'] ) ? sanitize_text_field( wp_unslash( $_POST['target_type'] ) ) : null;
		$target_id          = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : null;
		$metadata           = [];
		if ( ! empty( $_POST['metadata'] ) && is_string( $_POST['metadata'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['metadata'] ), true );
			if ( is_array( $decoded ) ) {
				$metadata = $decoded;
			}
		}
		$id = WebinoCRM_Logger::log_user_action( $action_type, $action_description, $target_type, $target_id, $metadata );
		wp_send_json_success( [ 'id' => $id ] );
	}

	/**
	 * Delete all system logs (manage_options only).
	 */
	public function delete_system_logs() {
		$this->emit_service( array( 'WebinoCRM_Logs_Service', 'delete_system' ) );
	}

	/**
	 * Track a visit (frontend or server-side). No auth required; rate-limited by IP inside class.
	 */
	public function track_visit() {
		$this->emit_service( array( 'WebinoCRM_Visitor_Service', 'track' ) );
	}

	/**
	 * Get visitor statistics for dashboard (manage_options only).
	 */
	public function get_visitor_stats() {
		$this->emit_service( array( 'WebinoCRM_Visitor_Service', 'get' ) );
	}

	/**
	 * Save visitor statistics toggle (system manager).
	 */
	public function save_visitor_settings() {
		$this->emit_service( array( 'WebinoCRM_Visitor_Service', 'save_settings' ) );
	}
}
