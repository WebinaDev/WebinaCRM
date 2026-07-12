<?php
/**
 * Session Management System
 * 
 * Manages user sessions and active devices
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Session_Management {

    /**
     * Initialize Session Management
     */
    public function __construct() {
        add_action('wp_login', [$this, 'create_session'], 10, 2);
        add_action('wp_logout', [$this, 'destroy_session']);
        add_action('wp_ajax_webinocrm_get_active_sessions', [$this, 'ajax_get_active_sessions']);
        add_action('wp_ajax_webinocrm_terminate_session', [$this, 'ajax_terminate_session']);
        add_action('wp_ajax_webinocrm_terminate_all_sessions', [$this, 'ajax_terminate_all_sessions']);
        add_action('init', [$this, 'check_session_validity']);
        add_action('webinocrm_cleanup_sessions', [$this, 'cleanup_old_sessions']);
    }

    /**
     * Create session on login
     */
    public function create_session($user_login, $user) {
        global $wpdb;

        $session_data = [
            'user_id' => $user->ID,
            'session_token' => $this->generate_session_token(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'device_info' => $this->parse_device_info($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'location' => $this->get_location_from_ip($this->get_client_ip()),
            'login_time' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'is_active' => 1
        ];

        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_sessions',
            $session_data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        $session_id = $wpdb->insert_id;

        // Store session ID in cookie
        setcookie('webinocrm_session_id', $session_id, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'user_id' => $user->ID,
            'action_type' => 'user_login',
            'entity_type' => 'session',
            'entity_id' => $session_id,
            'description' => 'ورود به سیستم',
            'metadata' => [
                'ip' => $session_data['ip_address'],
                'device' => $session_data['device_info']
            ]
        ]);

        return $session_id;
    }

    /**
     * Destroy session on logout
     */
    public function destroy_session() {
        global $wpdb;

        $session_id = $_COOKIE['webinocrm_session_id'] ?? 0;

        if ($session_id) {
            $wpdb->update(
                $wpdb->prefix . 'webinocrm_sessions',
                [
                    'is_active' => 0,
                    'logout_time' => current_time('mysql')
                ],
                ['id' => $session_id],
                ['%d', '%s'],
                ['%d']
            );

            // Clear cookie
            setcookie('webinocrm_session_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    /**
     * Check session validity
     */
    public function check_session_validity() {
        if (!is_user_logged_in()) {
            return;
        }

        $session_id = $_COOKIE['webinocrm_session_id'] ?? 0;

        if (!$session_id) {
            return;
        }

        global $wpdb;

        // Update last activity
        $wpdb->update(
            $wpdb->prefix . 'webinocrm_sessions',
            ['last_activity' => current_time('mysql')],
            ['id' => $session_id, 'is_active' => 1],
            ['%s'],
            ['%d', '%d']
        );

        // Check if session has been terminated
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_sessions WHERE id = %d",
            $session_id
        ));

        if (!$session || !$session->is_active) {
            // Force logout
            wp_logout();
            wp_redirect(wp_login_url());
            exit;
        }
    }

    /**
     * Get user's active sessions
     */
    public static function get_user_sessions($user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_sessions
             WHERE user_id = %d
             AND is_active = 1
             ORDER BY last_activity DESC",
            $user_id
        ));

        // Parse device info
        foreach ($sessions as $session) {
            $session->device_info = maybe_unserialize($session->device_info);
            $session->location = maybe_unserialize($session->location);
            $session->is_current = (isset($_COOKIE['webinocrm_session_id']) && $_COOKIE['webinocrm_session_id'] == $session->id);
        }

        return $sessions;
    }

    /**
     * Terminate specific session
     */
    public static function terminate_session($session_id, $user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Verify session belongs to user
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_sessions 
             WHERE id = %d AND user_id = %d",
            $session_id, $user_id
        ));

        if (!$session) {
            return new WP_Error('invalid_session', 'جلسه معتبر نیست');
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'webinocrm_sessions',
            [
                'is_active' => 0,
                'logout_time' => current_time('mysql')
            ],
            ['id' => $session_id],
            ['%d', '%s'],
            ['%d']
        );

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'user_id' => $user_id,
            'action_type' => 'session_terminated',
            'entity_type' => 'session',
            'entity_id' => $session_id,
            'description' => 'پایان جلسه'
        ]);

        return $result;
    }

    /**
     * Terminate all other sessions
     */
    public static function terminate_all_sessions($user_id = 0, $except_current = true) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $where = ['user_id = %d', 'is_active = 1'];
        $where_values = [$user_id];

        if ($except_current && isset($_COOKIE['webinocrm_session_id'])) {
            $where[] = 'id != %d';
            $where_values[] = $_COOKIE['webinocrm_session_id'];
        }

        $where_clause = implode(' AND ', $where);
        $where_clause = $wpdb->prepare($where_clause, $where_values);

        $result = $wpdb->query(
            "UPDATE {$wpdb->prefix}webinocrm_sessions
             SET is_active = 0, logout_time = '" . current_time('mysql') . "'
             WHERE {$where_clause}"
        );

        return $result;
    }

    /**
     * Cleanup old sessions
     */
    public function cleanup_old_sessions() {
        global $wpdb;

        // Delete inactive sessions older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}webinocrm_sessions
             WHERE is_active = 0
             AND logout_time < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Mark active sessions as inactive if last activity > 7 days
        $wpdb->query(
            "UPDATE {$wpdb->prefix}webinocrm_sessions
             SET is_active = 0, logout_time = NOW()
             WHERE is_active = 1
             AND last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    /**
     * Generate session token
     */
    private function generate_session_token() {
        return wp_hash(wp_generate_password(32, false) . time() . wp_rand(), 'auth');
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
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
     * Parse device info from user agent
     */
    private function parse_device_info($user_agent) {
        $device_info = [
            'browser' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => 'Desktop'
        ];

        // Detect browser
        if (strpos($user_agent, 'Chrome') !== false) {
            $device_info['browser'] = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $device_info['browser'] = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $device_info['browser'] = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $device_info['browser'] = 'Edge';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            $device_info['browser'] = 'Internet Explorer';
        }

        // Detect OS
        if (strpos($user_agent, 'Windows') !== false) {
            $device_info['os'] = 'Windows';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            $device_info['os'] = 'Mac OS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $device_info['os'] = 'Linux';
        } elseif (strpos($user_agent, 'Android') !== false) {
            $device_info['os'] = 'Android';
            $device_info['device_type'] = 'Mobile';
        } elseif (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false) {
            $device_info['os'] = 'iOS';
            $device_info['device_type'] = 'Mobile';
        }

        // Detect device type
        if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false) {
            $device_info['device_type'] = 'Mobile';
        } elseif (strpos($user_agent, 'Tablet') !== false || strpos($user_agent, 'iPad') !== false) {
            $device_info['device_type'] = 'Tablet';
        }

        return maybe_serialize($device_info);
    }

    /**
     * Get location from IP (simple implementation)
     */
    private function get_location_from_ip($ip) {
        // For production, you should use a proper IP geolocation service
        // This is a simplified version
        $location = [
            'country' => 'Unknown',
            'city' => 'Unknown'
        ];

        // You can integrate with services like:
        // - ip-api.com
        // - ipstack.com
        // - ipinfo.io

        return maybe_serialize($location);
    }

    /**
     * Get session statistics
     */
    public static function get_session_stats($user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $stats = [
            'active_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}webinocrm_sessions 
                 WHERE user_id = %d AND is_active = 1",
                $user_id
            )),
            'total_logins' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}webinocrm_sessions 
                 WHERE user_id = %d",
                $user_id
            )),
            'last_login' => $wpdb->get_var($wpdb->prepare(
                "SELECT login_time FROM {$wpdb->prefix}webinocrm_sessions 
                 WHERE user_id = %d 
                 ORDER BY login_time DESC 
                 LIMIT 1",
                $user_id
            )),
            'devices_used' => $wpdb->get_results($wpdb->prepare(
                "SELECT device_info, COUNT(*) as count 
                 FROM {$wpdb->prefix}webinocrm_sessions 
                 WHERE user_id = %d 
                 GROUP BY device_info
                 ORDER BY count DESC",
                $user_id
            ))
        ];

        return $stats;
    }

    /**
     * AJAX Handlers
     */
    public function ajax_get_active_sessions() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $sessions = self::get_user_sessions();

        wp_send_json_success(['sessions' => $sessions]);
    }

    public function ajax_terminate_session() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $session_id = intval($_POST['session_id'] ?? 0);

        $result = self::terminate_session($session_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'جلسه پایان یافت']);
    }

    public function ajax_terminate_all_sessions() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $except_current = isset($_POST['except_current']) ? true : false;

        $result = self::terminate_all_sessions(0, $except_current);

        wp_send_json_success([
            'message' => 'تمام جلسات پایان یافت',
            'count' => $result
        ]);
    }
}

