<?php
/**
 * WebSocket Handler for Real-time Notifications
 * 
 * This class manages WebSocket connections for real-time push notifications
 * using Ratchet WebSocket library integration
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_WebSocket_Handler {

    /**
     * Initialize WebSocket handler
     */
    public function __construct() {
        add_action('init', [$this, 'init_websocket_support']);
    }

    /**
     * Initialize WebSocket support
     */
    public function init_websocket_support() {
        // Register WebSocket endpoint
        add_rewrite_rule('^webino-ws/?', 'index.php?webino_ws=1', 'top');
        add_filter('query_vars', function($vars) {
            $vars[] = 'webino_ws';
            return $vars;
        });
    }

    /**
     * Enqueue WebSocket client scripts
     */
    public function enqueue_websocket_scripts() {
        wp_enqueue_script(
            'webinocrm-websocket',
            WEBINOCRM_PLUGIN_URL . 'assets/js/websocket-client.js',
            ['jquery'],
            WEBINOCRM_VERSION,
            true
        );

        wp_localize_script('webinocrm-websocket', 'webinoWS', [
            'url' => $this->get_websocket_url(),
            'secure' => is_ssl(),
            'user_id' => get_current_user_id(),
            'token' => $this->generate_user_token()
        ]);
    }

    /**
     * Get WebSocket server URL
     */
    private function get_websocket_url() {
        $ws_port = get_option('webinocrm_ws_port', 8080);
        $ws_host = get_option('webinocrm_ws_host', 'localhost');
        $protocol = is_ssl() ? 'wss' : 'ws';
        
        return "{$protocol}://{$ws_host}:{$ws_port}";
    }

    /**
     * Generate authentication token for user
     */
    private function generate_user_token() {
        $user_id = get_current_user_id();
        if (!$user_id) return '';
        
        return wp_hash($user_id . time() . wp_salt(), 'auth');
    }

    /**
     * Broadcast notification to users
     */
    public static function broadcast_notification($user_ids, $data) {
        global $wpdb;
        
        // Store notification in database for offline users
        foreach ((array)$user_ids as $user_id) {
            $wpdb->insert(
                $wpdb->prefix . 'webinocrm_notifications',
                [
                    'user_id' => $user_id,
                    'type' => $data['type'] ?? 'info',
                    'title' => $data['title'] ?? '',
                    'message' => $data['message'] ?? '',
                    'data' => maybe_serialize($data),
                    'is_read' => 0,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
            );
        }

        if ( class_exists( 'WebinoCRM_Realtime_Service' ) ) {
            WebinoCRM_Realtime_Service::broadcast( (array) $user_ids, (array) $data );
        }

        return true;
    }

    /**
     * Get user notifications
     */
    public static function get_user_notifications($user_id, $unread_only = false) {
        global $wpdb;
        
        $where = $wpdb->prepare("user_id = %d", $user_id);
        if ($unread_only) {
            $where .= " AND is_read = 0";
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}webinocrm_notifications 
             WHERE {$where} 
             ORDER BY created_at DESC 
             LIMIT 50"
        );
    }

    /**
     * Mark notification as read
     */
    public static function mark_as_read($notification_id, $user_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'webinocrm_notifications',
            ['is_read' => 1],
            ['id' => $notification_id, 'user_id' => $user_id],
            ['%d'],
            ['%d', '%d']
        );
    }
}

