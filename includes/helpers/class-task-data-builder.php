<?php
/**
 * Builds structured task detail payloads for REST / SPA.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Task detail data assembler.
 */
class WebinoCRM_Task_Data_Builder {

	/**
	 * @param int $task_id Task post ID.
	 * @return array<string,mixed>|null Null if task not found.
	 */
	public static function build_detail( $task_id ) {
		$task_id = (int) $task_id;
		$task    = get_post( $task_id );
		if ( ! $task || 'task' !== $task->post_type ) {
			return null;
		}

		$current_status = WebinoCRM_Term_Helper::get_first_term( $task_id, 'task_status' );
		$priority_term  = WebinoCRM_Term_Helper::get_first_term( $task_id, 'task_priority' );

		$all_statuses = get_terms(
			array(
				'taxonomy'   => 'task_status',
				'hide_empty' => false,
			)
		);
		$statuses = array();
		if ( $all_statuses && ! is_wp_error( $all_statuses ) ) {
			foreach ( $all_statuses as $term ) {
				$statuses[] = array(
					'id'   => (int) $term->term_id,
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}

		$project_id    = (int) get_post_meta( $task_id, '_project_id', true );
		$assigned_to   = (int) get_post_meta( $task_id, '_assigned_to', true );
		$checklist_raw = get_post_meta( $task_id, '_task_checklist', true );
		if ( ! is_array( $checklist_raw ) ) {
			$checklist_raw = array();
		}
		$checklist = array();
		foreach ( $checklist_raw as $item_id => $item ) {
			$checklist[] = array(
				'id'      => (string) $item_id,
				'text'    => isset( $item['text'] ) ? (string) $item['text'] : '',
				'checked' => ! empty( $item['checked'] ),
			);
		}

		$links_raw = get_post_meta( $task_id, '_task_links', true );
		if ( ! is_array( $links_raw ) ) {
			$links_raw = array();
		}
		$link_labels = array(
			'blocks'        => __( 'مسدود می‌کند', 'webinocrm' ),
			'is_blocked_by' => __( 'مسدود شده توسط', 'webinocrm' ),
			'relates_to'    => __( 'مرتبط با', 'webinocrm' ),
		);
		$links = array();
		foreach ( $links_raw as $link ) {
			$peer_id = (int) ( $link['task_id'] ?? 0 );
			$type    = isset( $link['type'] ) ? (string) $link['type'] : 'relates_to';
			$links[] = array(
				'type'       => $type,
				'type_label' => isset( $link_labels[ $type ] ) ? $link_labels[ $type ] : $type,
				'task_id'    => $peer_id,
				'title'      => $peer_id ? get_the_title( $peer_id ) : '',
			);
		}

		$attachment_ids = get_post_meta( $task_id, '_task_attachments', true );
		if ( ! is_array( $attachment_ids ) ) {
			$attachment_ids = array();
		}
		$attachments = array();
		foreach ( $attachment_ids as $att_id ) {
			$att_id = (int) $att_id;
			if ( $att_id <= 0 ) {
				continue;
			}
			$file_path = get_attached_file( $att_id );
			$file_name = $file_path ? basename( $file_path ) : get_the_title( $att_id );
			$file_type = wp_check_filetype( $file_name );
			$file_size = $file_path && file_exists( $file_path ) ? size_format( (int) filesize( $file_path ) ) : '';
			$attachments[] = array(
				'id'   => $att_id,
				'url'  => (string) wp_get_attachment_url( $att_id ),
				'name' => $file_name,
				'type' => isset( $file_type['ext'] ) ? (string) $file_type['ext'] : '',
				'size' => $file_size,
			);
		}

		$comments_out = array();
		$comments     = get_comments(
			array(
				'post_id' => $task_id,
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			)
		);
		foreach ( $comments as $comment ) {
			$comments_out[] = array(
				'id'         => (int) $comment->comment_ID,
				'author'     => (string) $comment->comment_author,
				'content'    => (string) $comment->comment_content,
				'date'       => (string) $comment->comment_date,
				'avatar_url' => (string) get_avatar_url( $comment->user_id, array( 'size' => 48 ) ),
				'time_ago'   => human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) ),
			);
		}

		$activity_log = get_post_meta( $task_id, '_task_activity_log', true );
		if ( ! is_array( $activity_log ) ) {
			$activity_log = array();
		}
		$activity = array();
		foreach ( array_slice( $activity_log, 0, 20 ) as $entry ) {
			$activity[] = array(
				'user_name' => isset( $entry['user_name'] ) ? (string) $entry['user_name'] : '',
				'text'      => isset( $entry['text'] ) ? (string) $entry['text'] : '',
				'time'      => isset( $entry['time'] ) ? (string) $entry['time'] : '',
			);
		}

		$time_logs_raw = get_post_meta( $task_id, '_task_time_logs', true );
		if ( ! is_array( $time_logs_raw ) ) {
			$time_logs_raw = array();
		}
		$time_logs = array();
		$total_hours = 0.0;
		foreach ( $time_logs_raw as $log ) {
			$hours = isset( $log['hours'] ) ? (float) $log['hours'] : 0;
			$total_hours += $hours;
			$time_logs[] = array(
				'user_name'   => isset( $log['user_name'] ) ? (string) $log['user_name'] : '',
				'hours'       => $hours,
				'description' => isset( $log['description'] ) ? (string) $log['description'] : '',
				'date'        => isset( $log['date'] ) ? (string) $log['date'] : '',
			);
		}

		return array(
			'id'             => $task_id,
			'title'          => $task->post_title,
			'content'        => $task->post_content,
			'status_slug'    => $current_status ? $current_status->slug : '',
			'status_name'    => $current_status ? $current_status->name : '',
			'priority_slug'  => $priority_term ? $priority_term->slug : '',
			'priority_name'  => $priority_term ? $priority_term->name : '',
			'project_id'     => $project_id,
			'project_title'  => $project_id ? get_the_title( $project_id ) : '',
			'assigned_to'    => $assigned_to,
			'assigned_name'  => $assigned_to ? get_the_author_meta( 'display_name', $assigned_to ) : '',
			'due_date'       => (string) get_post_meta( $task_id, '_due_date', true ),
			'statuses'       => $statuses,
			'checklist'      => $checklist,
			'comments'       => $comments_out,
			'links'          => $links,
			'attachments'    => $attachments,
			'activity'       => $activity,
			'time_logs'      => $time_logs,
			'total_hours'    => $total_hours,
		);
	}
}
