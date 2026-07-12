<?php
/**
 * Activity Timeline Handler
 * 
 * Tracks and displays comprehensive activity timeline for all entities
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Activity_Timeline {

    /**
     * Initialize Activity Timeline
     */
    public function __construct() {
        add_action('init', [$this, 'register_activity_table']);
        add_action('wp_ajax_webinocrm_get_timeline', [$this, 'ajax_get_timeline']);
        add_action('wp_ajax_webinocrm_get_entity_timeline', [$this, 'ajax_get_entity_timeline']);
        
        // Hook into various WordPress actions to log activities
        $this->setup_activity_hooks();
    }

    /**
     * Setup hooks to track activities
     */
    private function setup_activity_hooks() {
        // Post actions
        add_action('save_post', [$this, 'log_post_activity'], 10, 3);
        add_action('delete_post', [$this, 'log_post_delete'], 10, 2);
        add_action('transition_post_status', [$this, 'log_status_change'], 10, 3);
        
        // Comment/Note actions
        add_action('wp_insert_comment', [$this, 'log_comment_activity'], 10, 2);
        
        // Meta changes
        add_action('updated_post_meta', [$this, 'log_meta_update'], 10, 4);
        
        // User actions
        add_action('wp_login', [$this, 'log_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'log_user_logout']);
        
        // File uploads
        add_action('add_attachment', [$this, 'log_file_upload']);
        
        // Custom CRM actions
        add_action('webinocrm_lead_converted', [$this, 'log_lead_conversion'], 10, 2);
        add_action('webinocrm_task_completed', [$this, 'log_task_completion'], 10, 2);
        add_action('webinocrm_email_sent', [$this, 'log_email_sent'], 10, 2);
        add_action('webinocrm_sms_sent', [$this, 'log_sms_sent'], 10, 2);
    }

    /**
     * Log activity
     */
    public static function log($args) {
        global $wpdb;

        $defaults = [
            'user_id' => get_current_user_id(),
            'action_type' => 'unknown',
            'entity_type' => '',
            'entity_id' => 0,
            'description' => '',
            'metadata' => [],
            'ip_address' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        ];

        $data = wp_parse_args($args, $defaults);
        $data['metadata'] = maybe_serialize($data['metadata']);

        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_activities',
            $data,
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Get activities with filters
     */
    public static function get_activities($args = []) {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'entity_type' => null,
            'entity_id' => null,
            'action_types' => [],
            'date_from' => null,
            'date_to' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['entity_type']) {
            $where[] = 'entity_type = %s';
            $where_values[] = $args['entity_type'];
        }

        if ($args['entity_id']) {
            $where[] = 'entity_id = %d';
            $where_values[] = $args['entity_id'];
        }

        if (!empty($args['action_types'])) {
            $placeholders = implode(',', array_fill(0, count($args['action_types']), '%s'));
            $where[] = "action_type IN ($placeholders)";
            $where_values = array_merge($where_values, $args['action_types']);
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }

        // Build query
        $query = "SELECT a.*, u.display_name as user_name, u.user_email
                  FROM {$wpdb->prefix}webinocrm_activities a
                  LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY {$args['orderby']} {$args['order']}
                  LIMIT %d OFFSET %d";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset'])
        );

        // Unserialize metadata
        foreach ($results as $activity) {
            $activity->metadata = maybe_unserialize($activity->metadata);
        }

        return $results;
    }

    /**
     * Log post activity
     */
    public function log_post_activity($post_id, $post, $update) {
        // Only log WebinoCRM post types
        $crm_types = ['lead', 'project', 'contract', 'task', 'ticket'];
        
        if (!in_array($post->post_type, $crm_types)) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $action = $update ? 'updated' : 'created';
        
        self::log([
            'action_type' => $action,
            'entity_type' => $post->post_type,
            'entity_id' => $post_id,
            'description' => sprintf(
                '%s %s: %s',
                $action === 'created' ? 'ایجاد' : 'ویرایش',
                $this->get_entity_label($post->post_type),
                $post->post_title
            ),
            'metadata' => [
                'post_title' => $post->post_title,
                'post_status' => $post->post_status
            ]
        ]);
    }

    /**
     * Log post deletion
     */
    public function log_post_delete($post_id, $post) {
        $crm_types = ['lead', 'project', 'contract', 'task', 'ticket'];
        
        if (!in_array($post->post_type, $crm_types)) {
            return;
        }

        self::log([
            'action_type' => 'deleted',
            'entity_type' => $post->post_type,
            'entity_id' => $post_id,
            'description' => sprintf(
                'حذف %s: %s',
                $this->get_entity_label($post->post_type),
                $post->post_title
            ),
            'metadata' => [
                'post_title' => $post->post_title
            ]
        ]);
    }

    /**
     * Log status changes
     */
    public function log_status_change($new_status, $old_status, $post) {
        if ($new_status === $old_status) {
            return;
        }

        $crm_types = ['lead', 'project', 'contract', 'task', 'ticket'];
        
        if (!in_array($post->post_type, $crm_types)) {
            return;
        }

        self::log([
            'action_type' => 'status_changed',
            'entity_type' => $post->post_type,
            'entity_id' => $post->ID,
            'description' => sprintf(
                'تغییر وضعیت %s از "%s" به "%s"',
                $post->post_title,
                $this->translate_status($old_status),
                $this->translate_status($new_status)
            ),
            'metadata' => [
                'old_status' => $old_status,
                'new_status' => $new_status
            ]
        ]);
    }

    /**
     * Log comment/note activity
     */
    public function log_comment_activity($comment_id, $comment) {
        if (is_object($comment)) {
            $comment = (array) $comment;
        }

        self::log([
            'action_type' => 'comment_added',
            'entity_type' => 'comment',
            'entity_id' => $comment_id,
            'description' => 'افزودن یادداشت جدید',
            'metadata' => [
                'comment_content' => wp_trim_words($comment['comment_content'], 20),
                'post_id' => $comment['comment_post_ID']
            ]
        ]);
    }

    /**
     * Log meta updates
     */
    public function log_meta_update($meta_id, $object_id, $meta_key, $meta_value) {
        // Only log important meta keys
        $tracked_keys = ['_lead_status', '_project_status', '_task_assignee', '_contract_value'];
        
        if (!in_array($meta_key, $tracked_keys)) {
            return;
        }

        $post = get_post($object_id);
        if (!$post) {
            return;
        }

        self::log([
            'action_type' => 'meta_updated',
            'entity_type' => $post->post_type,
            'entity_id' => $object_id,
            'description' => sprintf(
                'بروزرسانی فیلد %s',
                $this->translate_meta_key($meta_key)
            ),
            'metadata' => [
                'meta_key' => $meta_key,
                'meta_value' => $meta_value
            ]
        ]);
    }

    /**
     * Log user login
     */
    public function log_user_login($user_login, $user) {
        self::log([
            'user_id' => $user->ID,
            'action_type' => 'login',
            'entity_type' => 'user',
            'entity_id' => $user->ID,
            'description' => 'ورود به سیستم'
        ]);
    }

    /**
     * Log user logout
     */
    public function log_user_logout() {
        self::log([
            'action_type' => 'logout',
            'entity_type' => 'user',
            'entity_id' => get_current_user_id(),
            'description' => 'خروج از سیستم'
        ]);
    }

    /**
     * Log file upload
     */
    public function log_file_upload($attachment_id) {
        $file = get_post($attachment_id);
        
        self::log([
            'action_type' => 'file_uploaded',
            'entity_type' => 'attachment',
            'entity_id' => $attachment_id,
            'description' => sprintf('آپلود فایل: %s', $file->post_title),
            'metadata' => [
                'file_name' => $file->post_title,
                'file_type' => get_post_mime_type($attachment_id)
            ]
        ]);
    }

    /**
     * Log lead conversion
     */
    public function log_lead_conversion($lead_id, $project_id) {
        self::log([
            'action_type' => 'lead_converted',
            'entity_type' => 'lead',
            'entity_id' => $lead_id,
            'description' => 'تبدیل لید به پروژه',
            'metadata' => [
                'project_id' => $project_id
            ]
        ]);
    }

    /**
     * Log task completion
     */
    public function log_task_completion($task_id, $user_id) {
        $task = get_post($task_id);
        
        self::log([
            'action_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => $task_id,
            'description' => sprintf('تکمیل تسک: %s', $task->post_title),
            'metadata' => [
                'task_title' => $task->post_title
            ]
        ]);
    }

    /**
     * Log email sent
     */
    public function log_email_sent($recipient, $subject) {
        self::log([
            'action_type' => 'email_sent',
            'entity_type' => 'email',
            'entity_id' => 0,
            'description' => sprintf('ارسال ایمیل به %s', $recipient),
            'metadata' => [
                'recipient' => $recipient,
                'subject' => $subject
            ]
        ]);
    }

    /**
     * Log SMS sent
     */
    public function log_sms_sent($phone, $message) {
        self::log([
            'action_type' => 'sms_sent',
            'entity_type' => 'sms',
            'entity_id' => 0,
            'description' => sprintf('ارسال پیامک به %s', $phone),
            'metadata' => [
                'phone' => $phone,
                'message' => wp_trim_words($message, 10)
            ]
        ]);
    }

    /**
     * AJAX: Get timeline
     */
    public function ajax_get_timeline() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        $filters = $_POST['filters'] ?? [];

        $args = [
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];

        if (!empty($filters['user_id'])) {
            $args['user_id'] = intval($filters['user_id']);
        }

        if (!empty($filters['entity_type'])) {
            $args['entity_type'] = sanitize_key($filters['entity_type']);
        }

        if (!empty($filters['action_types'])) {
            $args['action_types'] = array_map('sanitize_key', $filters['action_types']);
        }

        $activities = self::get_activities($args);

        wp_send_json_success([
            'activities' => $activities,
            'page' => $page,
            'has_more' => count($activities) === $per_page
        ]);
    }

    /**
     * AJAX: Get entity timeline
     */
    public function ajax_get_entity_timeline() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $entity_type = sanitize_key($_POST['entity_type'] ?? '');
        $entity_id = intval($_POST['entity_id'] ?? 0);

        if (!$entity_type || !$entity_id) {
            wp_send_json_error(['message' => 'پارامترهای نامعتبر']);
        }

        $activities = self::get_activities([
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'limit' => 100
        ]);

        wp_send_json_success(['activities' => $activities]);
    }

    /**
     * Helper: Get client IP
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Helper: Get entity label
     */
    private function get_entity_label($post_type) {
        $labels = [
            'lead' => 'لید',
            'project' => 'پروژه',
            'contract' => 'قرارداد',
            'task' => 'تسک',
            'ticket' => 'تیکت'
        ];

        return $labels[$post_type] ?? $post_type;
    }

    /**
     * Helper: Translate status
     */
    private function translate_status($status) {
        $statuses = [
            'publish' => 'منتشر شده',
            'draft' => 'پیش‌نویس',
            'pending' => 'در انتظار',
            'completed' => 'تکمیل شده',
            'in_progress' => 'در حال انجام'
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Helper: Translate meta key
     */
    private function translate_meta_key($meta_key) {
        $keys = [
            '_lead_status' => 'وضعیت لید',
            '_project_status' => 'وضعیت پروژه',
            '_task_assignee' => 'مسئول تسک',
            '_contract_value' => 'مبلغ قرارداد'
        ];

        return $keys[$meta_key] ?? $meta_key;
    }

    /**
     * Register activity table on init
     */
    public function register_activity_table() {
        // Table will be created during plugin activation
    }
}

