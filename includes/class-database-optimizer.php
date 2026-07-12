<?php
/**
 * Database Optimizer
 * 
 * Optimizes database queries and adds indexes
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Database_Optimizer {

    /**
     * Initialize Database Optimizer
     */
    public function __construct() {
        add_action('wp_ajax_webinocrm_optimize_database', [$this, 'ajax_optimize_database']);
        add_action('webinocrm_weekly_db_optimize', [$this, 'optimize_tables']);
    }

    /**
     * Add database indexes for better performance
     */
    public static function add_indexes() {
        global $wpdb;

        // Postmeta indexes for frequently queried meta keys
        $meta_keys_to_index = [
            '_lead_status',
            '_lead_assigned_to',
            '_project_status',
            '_project_manager',
            '_task_status',
            '_task_assignee',
            '_task_project',
            '_contract_customer',
            '_contract_value'
        ];

        foreach ($meta_keys_to_index as $meta_key) {
            $index_name = 'wcrm_' . substr(md5($meta_key), 0, 10);
            
            // Check if index exists
            $index_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE table_schema = DATABASE() 
                     AND table_name = '{$wpdb->postmeta}' 
                     AND index_name = %s",
                    $index_name
                )
            );

            if (!$index_exists) {
                $wpdb->query(
                    "ALTER TABLE {$wpdb->postmeta} 
                     ADD INDEX {$index_name} (meta_key(191), meta_value(50))"
                );
            }
        }

        return true;
    }

    /**
     * Optimize database tables
     */
    public static function optimize_tables() {
        global $wpdb;

        $tables = [
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->users,
            $wpdb->usermeta,
            $wpdb->prefix . 'webinocrm_activities',
            $wpdb->prefix . 'webinocrm_notifications',
            $wpdb->prefix . 'webinocrm_time_entries',
            $wpdb->prefix . 'webinocrm_documents',
            $wpdb->prefix . 'webinocrm_chat_messages',
            $wpdb->prefix . 'webinocrm_sessions',
        ];

        foreach ($tables as $table) {
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            
            if ($table_exists) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
        }

        return true;
    }

    /**
     * Clean up old data
     */
    public static function cleanup_old_data() {
        global $wpdb;

        $days_to_keep = apply_filters('webinocrm_data_retention_days', 90);

        // Clean old activities (keep last 90 days)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}webinocrm_activities 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );

        // Clean old notifications (keep last 30 days)
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}webinocrm_notifications 
             WHERE is_read = 1 
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Clean expired transients
        delete_expired_transients();

        // Clean post revisions (keep last 5)
        $wpdb->query(
            "DELETE FROM {$wpdb->posts} 
             WHERE post_type = 'revision' 
             AND post_date < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Clean orphaned postmeta
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm 
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );

        // Clean orphaned term relationships
        $wpdb->query(
            "DELETE tr FROM {$wpdb->term_relationships} tr 
             LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
             WHERE p.ID IS NULL"
        );

        return true;
    }

    /**
     * Get database statistics
     */
    public static function get_stats() {
        global $wpdb;

        $stats = [];

        // Table sizes
        $tables_query = "SELECT 
            table_name AS 'table',
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name LIKE '{$wpdb->prefix}%'
            ORDER BY (data_length + index_length) DESC";

        $stats['tables'] = $wpdb->get_results($tables_query, ARRAY_A);

        // Total database size
        $stats['total_size'] = $wpdb->get_var(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
             FROM information_schema.TABLES 
             WHERE table_schema = DATABASE() 
             AND table_name LIKE '{$wpdb->prefix}%'"
        );

        // Post counts
        $stats['posts'] = [
            'leads' => wp_count_posts('lead')->publish,
            'projects' => wp_count_posts('project')->publish,
            'tasks' => wp_count_posts('task')->publish,
            'contracts' => wp_count_posts('contract')->publish,
            'tickets' => wp_count_posts('ticket')->publish,
        ];

        // Orphaned meta count
        $stats['orphaned_postmeta'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );

        // Revision count
        $stats['revisions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        // Transient count
        $stats['transients'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_%'"
        );

        return $stats;
    }

    /**
     * AJAX: Optimize database
     */
    public function ajax_optimize_database() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی کافی ندارید']);
        }

        $action = sanitize_key($_POST['db_action'] ?? 'optimize');

        switch ($action) {
            case 'optimize':
                self::optimize_tables();
                $message = 'جداول بهینه‌سازی شدند';
                break;
                
            case 'cleanup':
                self::cleanup_old_data();
                $message = 'داده‌های قدیمی پاک شدند';
                break;
                
            case 'add_indexes':
                self::add_indexes();
                $message = 'ایندکس‌ها اضافه شدند';
                break;
                
            default:
                wp_send_json_error(['message' => 'عملیات نامعتبر']);
        }

        wp_send_json_success([
            'message' => $message,
            'stats' => self::get_stats()
        ]);
    }
}

