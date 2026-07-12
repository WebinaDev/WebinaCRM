<?php
/**
 * WebinoCRM Agile/Scrum Handler
 * Manages sprints, backlog, and related agile functionalities.
 * @package WebinoCRM
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Agile_Handler {

    public function __construct() {
        // Actions for sprint management will be added here
        add_action('wp_ajax_webino_get_backlog_tasks', [$this, 'get_backlog_tasks']);
        add_action('wp_ajax_webino_add_task_to_sprint', [$this, 'add_task_to_sprint']);
    }

    /**
     * AJAX handler to fetch tasks for the backlog.
     */
    public function get_backlog_tasks() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if ( ! current_user_can('edit_tasks') ) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if (empty($project_id)) {
            wp_send_json_success(['html' => '<p>لطفاً ابتدا یک پروژه را انتخاب کنید.</p>']);
        }

        $args = [
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_project_id', 'value' => $project_id],
                [
                    'relation' => 'OR',
                    ['key' => '_sprint_id', 'compare' => 'NOT EXISTS'],
                    ['key' => '_sprint_id', 'value' => '0']
                ]
            ],
            'orderby' => 'menu_order date',
            'order' => 'ASC',
        ];

        $tasks_query = new WP_Query($args);
        $html = '';
        if ($tasks_query->have_posts()) {
            while ($tasks_query->have_posts()) {
                $tasks_query->the_post();
                $html .= webino_render_task_card(get_post());
            }
        } else {
            $html = '<p>هیچ تسکی در بک‌لاگ این پروژه یافت نشد.</p>';
        }
        wp_reset_postdata();

        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX handler to assign a task to a sprint.
     */
    public function add_task_to_sprint() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if ( ! current_user_can('edit_tasks') ) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $task_id = intval($_POST['task_id']);
        $sprint_id = intval($_POST['sprint_id']); // 0 for backlog

        if ($task_id > 0) {
            update_post_meta($task_id, '_sprint_id', $sprint_id);
            wp_send_json_success(['message' => 'تسک به اسپرینت منتقل شد.']);
        } else {
            wp_send_json_error(['message' => 'شناسه تسک نامعتبر است.']);
        }
    }
}