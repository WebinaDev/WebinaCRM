<?php
/**
 * WebinoCRM Services & Products AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/services. Kept for legacy hooks.
 * Handles WC subscriptions, products, and task template linking.
 *
 * @package WebinoCRM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Services_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


	public function __construct() {
		add_action( 'wp_ajax_webinocrm_list_subscriptions', [ $this, 'ajax_list_subscriptions' ] );
		add_action( 'wp_ajax_webinocrm_list_products', [ $this, 'ajax_list_products' ] );
		add_action( 'wp_ajax_webinocrm_convert_subscription_to_contract', [ $this, 'ajax_convert_subscription_to_contract' ] );
		add_action( 'wp_ajax_webinocrm_update_product_task_template', [ $this, 'ajax_update_product_task_template' ] );
		add_action( 'wp_ajax_webinocrm_get_task_templates', [ $this, 'ajax_get_task_templates' ] );
	}

	/**
	 * Check nonce and capability.
	 */
	private function verify_request() {
		if ( ! check_ajax_referer( 'webinocrm-ajax-nonce', 'security', false ) ) {
			wp_send_json_error( [ 'message' => __( 'خطای امنیتی. لطفاً صفحه را رفرش کنید.', 'webinocrm' ) ] );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}
	}

	/**
	 * List WooCommerce Subscriptions.
	 */
	public function ajax_list_subscriptions() {
		$this->emit_service( array( 'WebinoCRM_Crm_Services_Module_Service', 'subscriptions' ) );
	}

	/**
	 * List WooCommerce products (subscription, grouped, simple).
	 */
	public function ajax_list_products() {
		$this->emit_service( array( 'WebinoCRM_Crm_Services_Module_Service', 'products' ) );
	}

	/**
	 * Convert a WC subscription to a contract (and create projects for items).
	 */
	public function ajax_convert_subscription_to_contract() {
		$this->emit_service( array( 'WebinoCRM_Crm_Services_Module_Service', 'convert_subscription' ) );
	}

	/**
	 * Update product task template link.
	 */
	public function ajax_update_product_task_template() {
		$this->emit_service( array( 'WebinoCRM_Crm_Services_Module_Service', 'product_task_template' ) );
	}

	/**
	 * Get task templates for dropdown.
	 */
	public function ajax_get_task_templates() {
		$this->emit_service( array( 'WebinoCRM_Crm_Services_Module_Service', 'task_templates' ) );
	}

	/**
	 * Get list of task templates (task_template CPT from Task Template Manager).
	 */
	private function get_task_templates_list() {
		$templates = get_posts( [
			'post_type'      => 'task_template',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		] );

		$list = [];
		foreach ( $templates as $t ) {
			$is_recurring   = (bool) get_post_meta( $t->ID, '_is_recurring', true );
			$recurring_type = get_post_meta( $t->ID, '_recurring_type', true ) ?: '';
			$list[] = [
				'id'            => $t->ID,
				'title'         => $t->post_title,
				'is_recurring'  => $is_recurring,
				'recurring_type' => $recurring_type,
			];
		}
		return $list;
	}

	/**
	 * Get template title by ID.
	 */
	private function get_template_title( $template_id ) {
		$post = get_post( $template_id );
		return $post && $post->post_type === 'task_template' ? $post->post_title : '';
	}

	/**
	 * Create tasks from task_template for a project.
	 */
	private function create_tasks_from_task_template( $project_id, $template_id ) {
		$tasks = get_post_meta( $template_id, '_template_tasks', true );
		if ( ! is_array( $tasks ) || empty( $tasks ) ) {
			return;
		}

		$start = strtotime( date( 'Y-m-d' ) );
		$todo_term = get_term_by( 'slug', 'to-do', 'task_status' );
		if ( ! $todo_term ) {
			$todo_term = get_term_by( 'slug', 'todo', 'task_status' );
		}
		$todo_term_id = $todo_term ? $todo_term->term_id : 0;

		foreach ( $tasks as $task_data ) {
			$title   = $task_data['title'] ?? '';
			$duration = isset( $task_data['duration'] ) ? floatval( $task_data['duration'] ) : 1;
			if ( empty( $title ) ) {
				continue;
			}
			$due_date = date( 'Y-m-d', strtotime( "+{$duration} days", $start ) );
			$task_id = wp_insert_post( [
				'post_title'   => $title,
				'post_type'    => 'task',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			] );
			if ( $task_id && ! is_wp_error( $task_id ) ) {
				update_post_meta( $task_id, '_project_id', $project_id );
				update_post_meta( $task_id, '_start_date', date( 'Y-m-d', $start ) );
				update_post_meta( $task_id, '_due_date', $due_date );
				if ( $todo_term_id ) {
					wp_set_object_terms( $task_id, $todo_term_id, 'task_status' );
				}
				$start = strtotime( $due_date );
			}
		}
	}

	/**
	 * Create tasks from project_template (_default_tasks) for a project.
	 */
	private function create_tasks_from_project_template( $project_id, $template_id ) {
		$template = get_post( $template_id );
		if ( ! $template || $template->post_type !== 'project_template' ) {
			return;
		}
		$default_tasks = get_post_meta( $template_id, '_default_tasks', true );
		if ( empty( $default_tasks ) || ! is_array( $default_tasks ) ) {
			return;
		}
		$todo_term = get_term_by( 'slug', 'to-do', 'task_status' );
		if ( ! $todo_term ) {
			$todo_term = get_term_by( 'slug', 'todo', 'task_status' );
		}
		$todo_term_id = $todo_term ? $todo_term->term_id : 0;
		foreach ( $default_tasks as $task_data ) {
			$title = isset( $task_data['title'] ) ? sanitize_text_field( $task_data['title'] ) : '';
			if ( empty( $title ) ) {
				continue;
			}
			$content = isset( $task_data['content'] ) ? wp_kses_post( $task_data['content'] ) : '';
			$task_id = wp_insert_post( [
				'post_title'   => $title,
				'post_content' => $content,
				'post_type'    => 'task',
				'post_status'  => 'publish',
			], true );
			if ( $task_id && ! is_wp_error( $task_id ) ) {
				update_post_meta( $task_id, '_project_id', $project_id );
				if ( $todo_term_id ) {
					wp_set_object_terms( $task_id, $todo_term_id, 'task_status' );
				}
			}
		}
	}
}
