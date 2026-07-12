<?php
/**
 * WebinoCRM Task AJAX Handler
 *
 * @deprecated 2.x REST API (`/webinocrm/v1/tasks`) is the source of truth for the dashboard SPA.
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Task_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

	public function __construct() {
		add_action( 'wp_ajax_webino_add_task', array( $this, 'add_task' ) );
		add_action( 'wp_ajax_webino_quick_add_task', array( $this, 'quick_add_task' ) );
		add_action( 'wp_ajax_webino_update_task_status', array( $this, 'update_task_status' ) );
		add_action( 'wp_ajax_webino_delete_task', array( $this, 'delete_task' ) );
		add_action( 'wp_ajax_webino_update_task_assignee', array( $this, 'ajax_update_task_assignee' ) );
		add_action( 'wp_ajax_webino_save_task_content', array( $this, 'save_task_content' ) );
		add_action( 'wp_ajax_webino_add_task_comment', array( $this, 'add_task_comment' ) );
		add_action( 'wp_ajax_webino_manage_checklist', array( $this, 'manage_checklist' ) );
		add_action( 'wp_ajax_crm_log_time', array( $this, 'log_time' ) );
		add_action( 'wp_ajax_webino_quick_edit_task', array( $this, 'quick_edit_task' ) );
		add_action( 'wp_ajax_get_tasks_for_views', array( $this, 'get_tasks_for_views' ) );
		add_action( 'wp_ajax_webino_add_task_link', array( $this, 'add_task_link' ) );
		add_action( 'wp_ajax_webino_remove_task_link', array( $this, 'remove_task_link' ) );
		add_action( 'wp_ajax_webino_search_tasks_for_linking', array( $this, 'search_tasks_for_linking' ) );
		add_action( 'wp_ajax_webino_bulk_edit_tasks', array( $this, 'bulk_edit_tasks' ) );
		add_action( 'wp_ajax_webino_save_task_as_template', array( $this, 'save_task_as_template' ) );
		add_action( 'wp_ajax_webino_get_tasks_calendar', array( $this, 'get_tasks_calendar' ) );
		add_action( 'wp_ajax_webino_get_tasks_gantt', array( $this, 'get_tasks_gantt' ) );
		add_action( 'wp_ajax_webino_create_task', array( $this, 'add_task' ) );
		add_action( 'wp_ajax_webinocrm_get_tasks', array( $this, 'ajax_get_tasks' ) );
	}

	public function add_task() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'create_extended' ) );
	}

	public function quick_add_task() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'create' ) );
	}

	public function update_task_status() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'update_status' ) );
	}

	public function delete_task() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'delete' ) );
	}

	public function save_task_content() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'save_content' ) );
	}

	public function add_task_comment() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'add_comment' ) );
	}

	public function manage_checklist() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'manage_checklist' ) );
	}

	public function log_time() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'log_time' ) );
	}

	public function quick_edit_task() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'quick_edit' ) );
	}

	public function get_tasks_for_views() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'views' ) );
	}

	public function search_tasks_for_linking() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'search_for_linking' ) );
	}

	public function add_task_link() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'add_link' ) );
	}

	public function remove_task_link() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'remove_link' ) );
	}

	public function bulk_edit_tasks() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'bulk_edit' ) );
	}

	public function save_task_as_template() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'save_as_template' ) );
	}

	public function ajax_update_task_assignee() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'update_assignee' ) );
	}

	public function get_tasks_calendar() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'calendar' ) );
	}

	public function get_tasks_gantt() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'gantt' ) );
	}

	public function ajax_get_tasks() {
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'list' ) );
	}
}
