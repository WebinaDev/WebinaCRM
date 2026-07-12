<?php
/**
 * WebinoCRM Workflow & Taxonomy AJAX Handler
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Workflow_Ajax_Handler {

    public function __construct() {
        // --- Workflow & Taxonomy Management Actions ---
        add_action('wp_ajax_webino_save_status_order', [$this, 'save_status_order']);
        add_action('wp_ajax_webino_add_new_status', [$this, 'add_new_status']);
        add_action('wp_ajax_webino_delete_status', [$this, 'delete_status']);
        add_action('wp_ajax_webino_manage_position', [$this, 'ajax_manage_position']);
        add_action('wp_ajax_webino_delete_position', [$this, 'ajax_delete_position']);
        add_action('wp_ajax_webino_manage_task_category', [$this, 'ajax_manage_task_category']);
        add_action('wp_ajax_webino_delete_task_category', [$this, 'ajax_delete_task_category']);
    }

    public function save_status_order() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options') || !isset($_POST['order'])) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }
        global $wpdb;
        foreach ($_POST['order'] as $index => $term_id) {
            $wpdb->update($wpdb->terms, ['term_order' => $index + 1], ['term_id' => intval($term_id)]);
        }
        clean_term_cache(array_map('intval', $_POST['order']), 'task_status');
        wp_send_json_success(['message' => 'ترتیب وضعیت‌ها ذخیره شد.']);
    }

    public function add_new_status() {
        check_ajax_referer('webino_add_new_status_nonce', 'security');
        if (!current_user_can('manage_options') || !isset($_POST['name'])) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $name = sanitize_text_field($_POST['name']);
        if (empty($name)) wp_send_json_error(['message' => 'نام وضعیت نمی‌تواند خالی باشد.']);

        $result = wp_insert_term($name, 'task_status');
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'وضعیت جدید اضافه شد.', 'term_id' => $result['term_id'], 'name' => $name, 'slug' => get_term($result['term_id'])->slug]);
    }

    public function delete_status() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options') || !isset($_POST['term_id'])) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $term_id = intval($_POST['term_id']);
        $default_term = get_term_by('slug', 'to-do', 'task_status');
        if (!$default_term || $default_term->term_id == $term_id) {
            wp_send_json_error(['message' => 'امکان حذف وضعیت پیش‌فرض "انجام نشده" وجود ندارد.']);
        }

        // Reassign tasks to the default status before deleting
        $tasks_with_this_status = get_posts(['post_type' => 'task', 'tax_query' => [['taxonomy' => 'task_status','field' => 'term_id','terms' => $term_id]], 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($tasks_with_this_status as $task_id) {
            wp_set_object_terms($task_id, $default_term->term_id, 'task_status');
        }

        $result = wp_delete_term($term_id, 'task_status');
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'وضعیت با موفقیت حذف شد.']);
    }

    public function ajax_manage_task_category() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        }

        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        if (empty($name)) {
            wp_send_json_error(['message' => 'نام دسته‌بندی نمی‌تواند خالی باشد.']);
        }

        if ($term_id > 0) {
            $result = wp_update_term($term_id, 'task_category', ['name' => $name]);
            $message = 'دسته‌بندی با موفقیت ویرایش شد.';
        } else {
            if (term_exists($name, 'task_category')) {
                wp_send_json_error(['message' => 'این دسته‌بندی از قبل وجود دارد.']);
            }
            $result = wp_insert_term($name, 'task_category');
            $message = 'دسته‌بندی جدید با موفقیت اضافه شد.';
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => $message, 'reload' => true]);
    }

    public function ajax_delete_task_category() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        }

        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        if ($term_id === 0) {
            wp_send_json_error(['message' => 'شناسه نامعتبر است.']);
        }

        $result = wp_delete_term($term_id, 'task_category');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'دسته‌بندی با موفقیت حذف شد.', 'reload' => true]);
    }
    
    public function ajax_manage_position() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        }
        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;

        if (empty($name)) {
            wp_send_json_error(['message' => 'نام جایگاه نمی‌تواند خالی باشد.']);
        }
        
        $args = ['parent' => $parent];

        if ($term_id > 0) {
            $result = wp_update_term($term_id, 'organizational_position', ['name' => $name, 'parent' => $parent]);
            $message = 'جایگاه با موفقیت ویرایش شد.';
        } else {
            if (term_exists($name, 'organizational_position', $parent)) {
                wp_send_json_error(['message' => 'این جایگاه در این سطح از قبل وجود دارد.']);
            }
            $result = wp_insert_term($name, 'organizational_position', $args);
            $message = 'جایگاه جدید با موفقیت اضافه شد.';
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['message' => $message, 'reload' => true]);
    }

    public function ajax_delete_position() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        }
        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        if ($term_id === 0) {
            wp_send_json_error(['message' => 'شناسه نامعتبر است.']);
        }
        $result = wp_delete_term($term_id, 'organizational_position');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['message' => 'جایگاه با موفقیت حذف شد.', 'reload' => true]);
    }
}