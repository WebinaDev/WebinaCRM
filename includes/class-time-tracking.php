<?php
/**
 * Time Tracking System
 * 
 * Comprehensive time tracking for tasks and projects
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Time_Tracking {

    /**
     * Initialize Time Tracking
     */
    public function __construct() {
        // REST + SPA via WebinoCRM_Time_Tracking_Service; legacy wp_ajax hooks removed.
    }

    /**
     * Start timer
     */
    public static function start_timer($args) {
        global $wpdb;

        $defaults = [
            'user_id' => get_current_user_id(),
            'entity_type' => 'task',
            'entity_id' => 0,
            'description' => '',
            'is_billable' => 1,
            'hourly_rate' => 0
        ];

        $data = wp_parse_args($args, $defaults);

        // Check if user already has an active timer
        $active_timer = self::get_active_timer($data['user_id']);
        if ($active_timer) {
            return new WP_Error('timer_active', 'شما یک تایمر فعال دارید. لطفاً ابتدا آن را متوقف کنید.');
        }

        // Insert timer entry
        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_time_entries',
            [
                'user_id' => $data['user_id'],
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'description' => $data['description'],
                'start_time' => current_time('mysql'),
                'status' => 'running',
                'is_billable' => $data['is_billable'],
                'hourly_rate' => $data['hourly_rate']
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%f']
        );

        $timer_id = $wpdb->insert_id;

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'user_id' => $data['user_id'],
            'action_type' => 'timer_started',
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'description' => 'شروع زمان‌سنجی'
        ]);

        return $timer_id;
    }

    /**
     * Stop timer
     */
    public static function stop_timer($timer_id) {
        global $wpdb;

        $timer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_time_entries WHERE id = %d",
            $timer_id
        ));

        if (!$timer || $timer->status === 'stopped') {
            return new WP_Error('invalid_timer', 'تایمر معتبر نیست یا قبلاً متوقف شده');
        }

        $end_time = current_time('mysql');
        $start = strtotime($timer->start_time);
        
        // Calculate paused duration
        $paused_duration = $timer->paused_duration ?: 0;
        
        $end = strtotime($end_time);
        $total_seconds = $end - $start - $paused_duration;
        $duration_minutes = round($total_seconds / 60, 2);

        // Calculate cost if billable
        $cost = 0;
        if ($timer->is_billable && $timer->hourly_rate > 0) {
            $cost = ($duration_minutes / 60) * $timer->hourly_rate;
        }

        $wpdb->update(
            $wpdb->prefix . 'webinocrm_time_entries',
            [
                'end_time' => $end_time,
                'duration_minutes' => $duration_minutes,
                'cost' => $cost,
                'status' => 'stopped'
            ],
            ['id' => $timer_id],
            ['%s', '%f', '%f', '%s'],
            ['%d']
        );

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'user_id' => $timer->user_id,
            'action_type' => 'timer_stopped',
            'entity_type' => $timer->entity_type,
            'entity_id' => $timer->entity_id,
            'description' => sprintf('توقف زمان‌سنجی (%.2f دقیقه)', $duration_minutes),
            'metadata' => [
                'duration_minutes' => $duration_minutes,
                'cost' => $cost
            ]
        ]);

        return [
            'duration_minutes' => $duration_minutes,
            'cost' => $cost
        ];
    }

    /**
     * Pause timer
     */
    public static function pause_timer($timer_id) {
        global $wpdb;

        $timer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_time_entries WHERE id = %d",
            $timer_id
        ));

        if (!$timer || $timer->status !== 'running') {
            return new WP_Error('invalid_timer', 'تایمر معتبر نیست یا در حال اجرا نیست');
        }

        $wpdb->update(
            $wpdb->prefix . 'webinocrm_time_entries',
            [
                'status' => 'paused',
                'paused_at' => current_time('mysql')
            ],
            ['id' => $timer_id],
            ['%s', '%s'],
            ['%d']
        );

        return true;
    }

    /**
     * Resume timer
     */
    public static function resume_timer($timer_id) {
        global $wpdb;

        $timer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_time_entries WHERE id = %d",
            $timer_id
        ));

        if (!$timer || $timer->status !== 'paused') {
            return new WP_Error('invalid_timer', 'تایمر معتبر نیست یا در حالت توقف نیست');
        }

        // Calculate pause duration
        $pause_duration = strtotime(current_time('mysql')) - strtotime($timer->paused_at);
        $total_paused = ($timer->paused_duration ?: 0) + $pause_duration;

        $wpdb->update(
            $wpdb->prefix . 'webinocrm_time_entries',
            [
                'status' => 'running',
                'paused_duration' => $total_paused,
                'paused_at' => null
            ],
            ['id' => $timer_id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        return true;
    }

    /**
     * Add manual time entry
     */
    public static function add_manual_entry($args) {
        global $wpdb;

        $defaults = [
            'user_id' => get_current_user_id(),
            'entity_type' => 'task',
            'entity_id' => 0,
            'description' => '',
            'date' => current_time('Y-m-d'),
            'duration_minutes' => 0,
            'is_billable' => 1,
            'hourly_rate' => 0
        ];

        $data = wp_parse_args($args, $defaults);

        // Calculate cost
        $cost = 0;
        if ($data['is_billable'] && $data['hourly_rate'] > 0) {
            $cost = ($data['duration_minutes'] / 60) * $data['hourly_rate'];
        }

        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_time_entries',
            [
                'user_id' => $data['user_id'],
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'description' => $data['description'],
                'start_time' => $data['date'] . ' 00:00:00',
                'end_time' => $data['date'] . ' 00:00:00',
                'duration_minutes' => $data['duration_minutes'],
                'cost' => $cost,
                'status' => 'stopped',
                'is_billable' => $data['is_billable'],
                'hourly_rate' => $data['hourly_rate'],
                'is_manual' => 1
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%f', '%d']
        );

        return $wpdb->insert_id;
    }

    /**
     * Get active timer for user
     */
    public static function get_active_timer($user_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_time_entries 
             WHERE user_id = %d AND status IN ('running', 'paused')
             ORDER BY start_time DESC
             LIMIT 1",
            $user_id
        ));
    }

    /**
     * Get time entries
     */
    public static function get_time_entries($args = []) {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'entity_type' => null,
            'entity_id' => null,
            'date_from' => null,
            'date_to' => null,
            'is_billable' => null,
            'status' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'start_time',
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

        if ($args['date_from']) {
            $where[] = 'start_time >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'start_time <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        if ($args['is_billable'] !== null) {
            $where[] = 'is_billable = %d';
            $where_values[] = $args['is_billable'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }

        $query = "SELECT e.*, u.display_name as user_name
                  FROM {$wpdb->prefix}webinocrm_time_entries e
                  LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY {$args['orderby']} {$args['order']}
                  LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset'])
        );
    }

    /**
     * Get time report
     */
    public static function get_time_report($args = []) {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'entity_type' => null,
            'entity_id' => null,
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
            'group_by' => 'day' // day, week, month, user, entity
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ["status = 'stopped'"];
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

        if ($args['date_from']) {
            $where[] = 'start_time >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'start_time <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }

        // Group by clause
        $group_by = '';
        $select_group = '';
        
        switch ($args['group_by']) {
            case 'day':
                $group_by = 'DATE(start_time)';
                $select_group = 'DATE(start_time) as group_label';
                break;
            case 'week':
                $group_by = 'YEARWEEK(start_time)';
                $select_group = 'YEARWEEK(start_time) as group_label';
                break;
            case 'month':
                $group_by = 'DATE_FORMAT(start_time, "%Y-%m")';
                $select_group = 'DATE_FORMAT(start_time, "%Y-%m") as group_label';
                break;
            case 'user':
                $group_by = 'user_id';
                $select_group = 'user_id as group_label, u.display_name';
                break;
            case 'entity':
                $group_by = 'entity_type, entity_id';
                $select_group = 'entity_type, entity_id, entity_type as group_label';
                break;
        }

        $query = "SELECT 
                    {$select_group},
                    COUNT(*) as entry_count,
                    SUM(duration_minutes) as total_minutes,
                    SUM(cost) as total_cost,
                    SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END) as billable_minutes,
                    SUM(CASE WHEN is_billable = 0 THEN duration_minutes ELSE 0 END) as non_billable_minutes
                  FROM {$wpdb->prefix}webinocrm_time_entries e
                  LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                  WHERE {$where_clause}
                  GROUP BY {$group_by}
                  ORDER BY start_time DESC";

        return $wpdb->get_results($query);
    }

    /**
     * Delete time entry
     */
    public static function delete_entry($entry_id) {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . 'webinocrm_time_entries',
            ['id' => $entry_id],
            ['%d']
        );
    }

    /**
     * AJAX Handlers
     */
    public function ajax_start_timer() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $data = [
            'entity_type' => sanitize_key($_POST['entity_type'] ?? 'task'),
            'entity_id' => intval($_POST['entity_id'] ?? 0),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'is_billable' => isset($_POST['is_billable']) ? 1 : 0,
            'hourly_rate' => floatval($_POST['hourly_rate'] ?? 0)
        ];

        $result = self::start_timer($data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'تایمر با موفقیت شروع شد',
            'timer_id' => $result
        ]);
    }

    public function ajax_stop_timer() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $timer_id = intval($_POST['timer_id'] ?? 0);

        $result = self::stop_timer($timer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(array_merge(['message' => 'تایمر متوقف شد'], $result));
    }

    public function ajax_pause_timer() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $timer_id = intval($_POST['timer_id'] ?? 0);

        $result = self::pause_timer($timer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'تایمر متوقف موقت شد']);
    }

    public function ajax_resume_timer() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $timer_id = intval($_POST['timer_id'] ?? 0);

        $result = self::resume_timer($timer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'تایمر از سر گرفته شد']);
    }

    public function ajax_add_manual_time() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $data = [
            'entity_type' => sanitize_key($_POST['entity_type'] ?? 'task'),
            'entity_id' => intval($_POST['entity_id'] ?? 0),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'date' => sanitize_text_field($_POST['date'] ?? current_time('Y-m-d')),
            'duration_minutes' => floatval($_POST['duration_minutes'] ?? 0),
            'is_billable' => isset($_POST['is_billable']) ? 1 : 0,
            'hourly_rate' => floatval($_POST['hourly_rate'] ?? 0)
        ];

        $result = self::add_manual_entry($data);

        wp_send_json_success([
            'message' => 'زمان با موفقیت ثبت شد',
            'entry_id' => $result
        ]);
    }

    public function ajax_get_time_entries() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $args = [
            'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : null,
            'entity_type' => isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : null,
            'entity_id' => isset($_POST['entity_id']) ? intval($_POST['entity_id']) : null,
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null,
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null,
            'limit' => intval($_POST['limit'] ?? 100)
        ];

        $entries = self::get_time_entries($args);

        wp_send_json_success(['entries' => $entries]);
    }

    public function ajax_delete_time_entry() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $entry_id = intval($_POST['entry_id'] ?? 0);

        $result = self::delete_entry($entry_id);

        if ($result) {
            wp_send_json_success(['message' => 'ورودی حذف شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف ورودی']);
        }
    }

    public function ajax_get_time_report() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $args = [
            'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : null,
            'entity_type' => isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : null,
            'entity_id' => isset($_POST['entity_id']) ? intval($_POST['entity_id']) : null,
            'date_from' => sanitize_text_field($_POST['date_from'] ?? date('Y-m-01')),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? date('Y-m-t')),
            'group_by' => sanitize_key($_POST['group_by'] ?? 'day')
        ];

        $report = self::get_time_report($args);

        wp_send_json_success(['report' => $report]);
    }

    public function ajax_get_active_timer() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $user_id = get_current_user_id();
        $timer = self::get_active_timer($user_id);

        wp_send_json_success(['timer' => $timer]);
    }
}

