<?php
/**
 * Project template → project/tasks creation (shared by contracts).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers for project_template CPT.
 */
class WebinoCRM_Project_Template_Helper {

	/**
	 * @param int $template_id project_template post ID.
	 * @param int $contract_id Contract post ID.
	 * @return int|WP_Error Project ID or error.
	 */
	public static function create_project_from_template_for_contract( $template_id, $contract_id ) {
		$template = get_post( $template_id );
		if ( ! $template || 'project_template' !== $template->post_type ) {
			return new WP_Error( 'invalid_template', 'قالب پروژه نامعتبر است.' );
		}
		$contract = get_post( $contract_id );
		if ( ! $contract || 'contract' !== $contract->post_type ) {
			return new WP_Error( 'invalid_contract', 'قرارداد نامعتبر است.' );
		}
		$project_id = wp_insert_post(
			array(
				'post_title'  => $template->post_title,
				'post_author' => $contract->post_author,
				'post_status' => 'publish',
				'post_type'   => 'project',
			),
			true
		);
		if ( is_wp_error( $project_id ) ) {
			return $project_id;
		}
		update_post_meta( $project_id, '_contract_id', $contract_id );
		$active_status = get_term_by( 'slug', 'active', 'project_status' );
		if ( $active_status ) {
			wp_set_object_terms( $project_id, $active_status->term_id, 'project_status' );
		}
		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add(
				'پروژه از قالب ایجاد شد',
				array(
					'project_id'   => $project_id,
					'contract_id'  => $contract_id,
					'template_id'  => $template_id,
				),
				'success'
			);
		}
		return $project_id;
	}

	/**
	 * @param int $project_id  Project post ID.
	 * @param int $template_id project_template post ID.
	 * @return void
	 */
	public static function create_tasks_from_template_for_project( $project_id, $template_id ) {
		$default_tasks = get_post_meta( $template_id, '_default_tasks', true );
		if ( empty( $default_tasks ) || ! is_array( $default_tasks ) ) {
			return;
		}
		$default_status_slug = function_exists( 'webino_get_default_task_status_slug' ) ? webino_get_default_task_status_slug() : 'to-do';
		$default_status      = get_term_by( 'slug', $default_status_slug, 'task_status' );
		$default_term_id     = $default_status ? $default_status->term_id : 0;

		foreach ( $default_tasks as $task_data ) {
			$title = isset( $task_data['title'] ) ? sanitize_text_field( $task_data['title'] ) : '';
			if ( '' === $title ) {
				continue;
			}
			$content       = isset( $task_data['content'] ) ? wp_kses_post( $task_data['content'] ) : '';
			$assigned_role = isset( $task_data['assigned_role'] ) ? sanitize_key( $task_data['assigned_role'] ) : '';

			if ( '' !== $assigned_role ) {
				$users_with_role = get_users( array( 'role' => $assigned_role, 'fields' => 'ID' ) );
				if ( empty( $users_with_role ) ) {
					self::insert_template_task( $title, $content, $project_id, 0, $default_term_id );
					continue;
				}
				foreach ( $users_with_role as $user_id ) {
					self::insert_template_task( $title, $content, $project_id, (int) $user_id, $default_term_id );
				}
			} else {
				self::insert_template_task( $title, $content, $project_id, 0, $default_term_id );
			}
		}
		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add(
				'تسک‌ها از قالب ایجاد شدند',
				array(
					'project_id'  => $project_id,
					'template_id' => $template_id,
				),
				'success'
			);
		}
	}

	/**
	 * @param string $title           Task title.
	 * @param string $content         Task content.
	 * @param int    $project_id      Project ID.
	 * @param int    $assigned_to     User ID or 0.
	 * @param int    $default_term_id Status term ID.
	 * @return void
	 */
	private static function insert_template_task( $title, $content, $project_id, $assigned_to, $default_term_id ) {
		$task_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_type'    => 'task',
				'post_status'  => 'publish',
			),
			true
		);
		if ( is_wp_error( $task_id ) ) {
			return;
		}
		update_post_meta( $task_id, '_project_id', $project_id );
		if ( $assigned_to > 0 ) {
			update_post_meta( $task_id, '_assigned_to', $assigned_to );
		}
		if ( $default_term_id ) {
			wp_set_object_terms( $task_id, $default_term_id, 'task_status' );
		}
	}
}
