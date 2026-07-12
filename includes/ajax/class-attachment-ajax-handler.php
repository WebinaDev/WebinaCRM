<?php
/**
 * Attachment AJAX Handler
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Attachment_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

	public function __construct() {
		add_action( 'wp_ajax_webino_upload_task_attachment', array( $this, 'upload_task_attachment' ) );
		add_action( 'wp_ajax_webino_delete_task_attachment', array( $this, 'delete_task_attachment' ) );
		add_action( 'wp_ajax_webino_get_task_attachments', array( $this, 'get_task_attachments' ) );
	}

	public function upload_task_attachment() {
		$this->verify_ajax_or_die();
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'upload_attachment' ) );
	}

	public function delete_task_attachment() {
		$this->verify_ajax_or_die();
		$this->emit_service( array( 'WebinoCRM_Tasks_Service', 'delete_attachment' ) );
	}

	public function get_task_attachments() {
		$this->verify_ajax_or_die();
		$task_id = isset( $_POST['task_id'] ) ? (int) $_POST['task_id'] : 0;
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'شناسه وظیفه نامعتبر است.', 'webinocrm' ) ) );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-task-data-builder.php';
		$task = WebinoCRM_Task_Data_Builder::build_detail( $task_id );
		if ( null === $task ) {
			wp_send_json_error( array( 'message' => __( 'وظیفه یافت نشد.', 'webinocrm' ) ) );
		}
		wp_send_json_success( array( 'attachments' => $task['attachments'] ) );
	}
}
