<?php
/**
 * Cache & Performance Optimizer
 * 
 * Manages caching and performance optimizations
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Cache_Optimizer {

    /**
     * Cache group name
     */
    const CACHE_GROUP = 'webinocrm';

    /**
     * Initialize Cache Optimizer
     */
    public function __construct() {
        add_action('wp_ajax_webinocrm_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('save_post', [$this, 'clear_related_cache'], 10, 1);
        add_action('deleted_post', [$this, 'clear_related_cache'], 10, 1);
        add_action('updated_user_meta', [$this, 'clear_user_cache'], 10, 1);
        
        // Add admin bar menu for quick cache clear
        add_action('admin_bar_menu', [$this, 'add_admin_bar_cache_menu'], 100);
    }

    /**
     * Get cached data or execute callback
     */
    public static function get_or_set($key, $callback, $expiration = 300) {
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return $cached;
        }

        // Try transient if object cache failed
        $cached = get_transient('wcrm_' . $key);
        
        if ($cached !== false) {
            wp_cache_set($key, $cached, self::CACHE_GROUP, $expiration);
            return $cached;
        }

        // Execute callback to get data
        $data = is_callable($callback) ? call_user_func($callback) : null;
        
        if ($data !== null) {
            wp_cache_set($key, $data, self::CACHE_GROUP, $expiration);
            set_transient('wcrm_' . $key, $data, $expiration);
        }

        return $data;
    }

    /**
     * Clear specific cache
     */
    public static function clear($key) {
        wp_cache_delete($key, self::CACHE_GROUP);
        delete_transient('wcrm_' . $key);
    }

    /**
     * Clear all plugin cache
     */
    public static function clear_all() {
        global $wpdb;

        // Clear object cache
        wp_cache_flush();

        // Clear all plugin transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcrm_%' 
             OR option_name LIKE '_transient_timeout_wcrm_%'
             OR option_name LIKE '_transient_webinocrm_%'
             OR option_name LIKE '_transient_timeout_webinocrm_%'"
        );

        return true;
    }

    /**
     * Clear cache for specific entity
     */
    public static function clear_entity_cache($entity_type, $entity_id = 0) {
        $patterns = [
            'webinocrm_' . $entity_type . '_' . $entity_id,
            'webinocrm_' . $entity_type . '_list',
            'webinocrm_js_data_',
            'webinocrm_analytics_',
        ];

        foreach ($patterns as $pattern) {
            self::clear($pattern);
        }

        // Clear related transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_wcrm_' . $entity_type . '%'
            )
        );
    }

    /**
     * Clear related cache on post save
     */
    public function clear_related_cache($post_id) {
        $post_type = get_post_type($post_id);
        
        if (strpos($post_type, 'webino_') === 0) {
            $entity_type = str_replace('webino_', '', $post_type);
            self::clear_entity_cache($entity_type, $post_id);
        }
    }

    /**
     * Clear user cache on meta update
     */
    public function clear_user_cache($meta_id) {
        self::clear('webinocrm_js_data_');
        self::clear('webinocrm_users_list');
    }

    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی کافی ندارید']);
        }

        $type = sanitize_key($_POST['type'] ?? 'all');

        switch ($type) {
            case 'all':
                self::clear_all();
                $message = 'تمام کش‌ها پاک شد';
                break;
                
            case 'entity':
                $entity_type = sanitize_key($_POST['entity_type'] ?? '');
                $entity_id = intval($_POST['entity_id'] ?? 0);
                self::clear_entity_cache($entity_type, $entity_id);
                $message = 'کش این موجودیت پاک شد';
                break;
                
            default:
                self::clear($_POST['key']);
                $message = 'کش پاک شد';
        }

        wp_send_json_success(['message' => $message]);
    }

    /**
     * Add admin bar menu
     */
    public function add_admin_bar_cache_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node([
            'id' => 'webinocrm-cache',
            'title' => '<span class="ab-icon dashicons-before dashicons-update"></span> پاک کردن کش CRM',
            'href' => '#',
            'meta' => [
                'onclick' => 'webinoClearCache(); return false;',
            ]
        ]);
    }

    /**
     * Get cache statistics
     */
    public static function get_stats() {
        global $wpdb;

        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcrm_%'"
        );

        $transient_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcrm_%'"
        );

        return [
            'transient_count' => intval($transient_count),
            'transient_size' => intval($transient_size),
            'formatted_size' => size_format($transient_size)
        ];
    }
}

// Add inline script for admin bar cache clear
add_action('admin_footer', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <script>
    function webinoClearCache() {
        if (confirm('آیا مطمئن هستید که می‌خواهید تمام کش‌های WebinoCRM را پاک کنید؟')) {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'webinocrm_clear_cache',
                    type: 'all',
                    nonce: '<?php echo wp_create_nonce('webinocrm-ajax-nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ خطا: ' + response.data.message);
                    }
                }
            });
        }
    }
    </script>
    <?php
});

