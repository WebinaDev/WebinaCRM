<?php
/**
 * License Manager
 *
 * Handles all license management operations
 *
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class WebinoCRM_License_Manager {

    /**
     * @return int
     */
    public static function cache_ttl() {
        return max( 30, (int) apply_filters( 'webinocrm_license_cache_ttl', 300 ) );
    }

    /**
     * Register async webhook handler.
     */
    public static function init() {
        add_action('webinocrm_license_webhook_async', [__CLASS__, 'run_async_webhook'], 10, 2);
    }

    /**
     * @param string $domain Normalized domain.
     * @return void
     */
    public static function invalidate_license_cache($domain) {
        $domain = self::normalize_domain($domain);
        if ($domain) {
            delete_transient('wcrm_lic_chk_' . md5($domain));
        }
    }

    /**
     * Normalize domain (lowercase, trim, strip www).
     *
     * @param string $domain
     * @return string
     */
    public static function normalize_domain( $domain ) {
        $domain = strtolower( trim( (string) $domain ) );
        return preg_replace( '/^www\./', '', $domain );
    }

    /**
     * Get all licenses
     *
     * @return array
     */
    public static function get_all_licenses() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        
        $licenses = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // Calculate remaining percentage and card color for each license
        foreach ($licenses as &$license) {
            $license['remaining_percentage'] = self::calculate_remaining_percentage($license);
            $license['card_color'] = self::get_card_color($license['remaining_percentage'], $license);
            $license['remaining_days'] = self::calculate_remaining_days($license);
        }
        
        return $licenses;
    }

    /**
     * Get license by domain
     *
     * @param string $domain
     * @return array|null
     */
    public static function get_license_by_domain($domain) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        $normalized = self::normalize_domain( $domain );
        
        $license = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE domain = %s",
                $normalized
            ),
            ARRAY_A
        );
        
        if ($license) {
            $license['remaining_percentage'] = self::calculate_remaining_percentage($license);
            $license['card_color'] = self::get_card_color($license['remaining_percentage'], $license);
            $license['remaining_days'] = self::calculate_remaining_days($license);
        }
        
        return $license;
    }

    /**
     * Get license by key (searches encrypted keys)
     *
     * @param string $key
     * @return array|null
     */
    public static function get_license_by_key($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        
        // Get all licenses and check decrypted keys
        $licenses = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        
        foreach ($licenses as $license) {
            try {
                $decrypted = self::decrypt_license_key($license['license_key']);
                if ($decrypted === $key) {
                    $license['remaining_percentage'] = self::calculate_remaining_percentage($license);
                    $license['card_color'] = self::get_card_color($license['remaining_percentage'], $license);
                    $license['remaining_days'] = self::calculate_remaining_days($license);
                    return $license;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return null;
    }

    /**
     * Add new license
     *
     * @param array $data
     * @return int|false License ID on success, false on failure
     */
    public static function add_license($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        
        $defaults = [
            'project_name' => '',
            'domain' => '',
            'license_key' => '',
            'status' => 'inactive',
            'expiry_date' => null,
            'start_date' => current_time('mysql'),
            'logo_url' => '',
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['project_name']) || empty($data['domain'])) {
            return false;
        }
        
        $normalized_domain = self::normalize_domain( $data['domain'] );
        
        // Check if domain already exists
        $existing_domain = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE domain = %s",
                $normalized_domain
            )
        );
        
        if ($existing_domain) {
            return false;
        }
        
        // Domain is the license - use normalized domain as license_key (encrypted)
        $data['domain'] = $normalized_domain;
        $data['license_key'] = self::encrypt_license_key($normalized_domain);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'project_name' => sanitize_text_field($data['project_name']),
                'domain' => sanitize_text_field($data['domain']),
                'license_key' => $data['license_key'],
                'status' => sanitize_text_field($data['status']),
                'expiry_date' => $data['expiry_date'] ? sanitize_text_field($data['expiry_date']) : null,
                'start_date' => $data['start_date'] ? sanitize_text_field($data['start_date']) : null,
                'logo_url' => esc_url_raw($data['logo_url']),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            // Log error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WebinoCRM: License insert failed: ' . $wpdb->last_error);
            }
            return false;
        }

        if ('active' === sanitize_text_field($data['status'])) {
            self::queue_license_webhook($normalized_domain, 'activate');
        }

        if ( class_exists( 'WebinoCRM_ModirPayamak_Manager' ) ) {
            WebinoCRM_ModirPayamak_Manager::get_or_create_account( $normalized_domain );
            if ( 'active' === sanitize_text_field( (string) $data['status'] ) ) {
                WebinoCRM_ModirPayamak_Manager::set_account_status( $normalized_domain, WebinoCRM_ModirPayamak_Manager::STATUS_ACTIVE );
            }
        }

        return $wpdb->insert_id;
    }

    /**
     * Update license
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update_license($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        
        // Get current license data before update (for webhook)
        $current_license = $wpdb->get_row($wpdb->prepare("SELECT status, domain, license_key FROM $table_name WHERE id = %d", $id), ARRAY_A);
        
        $update_data = [];
        $format = [];
        $status_changed = false;
        $new_status = null;
        
        if (isset($data['project_name'])) {
            $update_data['project_name'] = sanitize_text_field($data['project_name']);
            $format[] = '%s';
        }
        
        if (isset($data['domain'])) {
            $update_data['domain'] = sanitize_text_field( self::normalize_domain( $data['domain'] ) );
            $format[] = '%s';
        }
        
        if (isset($data['license_key'])) {
            $update_data['license_key'] = self::encrypt_license_key($data['license_key']);
            $format[] = '%s';
        }
        
        if (isset($data['status'])) {
            $new_status = sanitize_text_field($data['status']);
            $update_data['status'] = $new_status;
            $format[] = '%s';
            
            if ($current_license && $current_license['status'] !== $new_status) {
                $status_changed = true;
            }
        }
        
        if ( array_key_exists( 'expiry_date', $data ) ) {
            $update_data['expiry_date'] = ! empty( $data['expiry_date'] ) ? sanitize_text_field( (string) $data['expiry_date'] ) : null;
            $format[] = '%s';
        }

        if ( array_key_exists( 'start_date', $data ) ) {
            $update_data['start_date'] = ! empty( $data['start_date'] ) ? sanitize_text_field( (string) $data['start_date'] ) : null;
            $format[] = '%s';
        }
        
        if (isset($data['logo_url'])) {
            $update_data['logo_url'] = esc_url_raw($data['logo_url']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        if ($result !== false && isset($current_license['domain'])) {
            self::invalidate_license_cache($current_license['domain']);
        }

        // Queue webhook if status changed (non-blocking).
        if ($result !== false && $status_changed && isset($current_license)) {
            $domain = $current_license['domain'];
            if ($new_status === 'inactive' || $new_status === 'cancelled' || $new_status === 'expired') {
                self::queue_license_webhook($domain, 'deactivate');
                if ( class_exists( 'WebinoCRM_ModirPayamak_Manager' ) ) {
                    WebinoCRM_ModirPayamak_Manager::set_account_status( $domain, WebinoCRM_ModirPayamak_Manager::STATUS_SUSPENDED );
                }
            } elseif ($new_status === 'active') {
                self::queue_license_webhook($domain, 'activate');
                if ( class_exists( 'WebinoCRM_ModirPayamak_Manager' ) ) {
                    WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain );
                    WebinoCRM_ModirPayamak_Manager::set_account_status( $domain, WebinoCRM_ModirPayamak_Manager::STATUS_ACTIVE );
                }
            }
        }
        
        return $result !== false;
    }

    /**
     * @param string $domain Domain.
     * @param string $action_type Action type.
     * @return void
     */
    public static function queue_license_webhook($domain, $action_type) {
        if (!wp_next_scheduled('webinocrm_license_webhook_async', [$domain, $action_type])) {
            wp_schedule_single_event(time() + 1, 'webinocrm_license_webhook_async', [$domain, $action_type]);
        }
    }

    /**
     * Cron/async webhook runner.
     *
     * @param string $domain Domain.
     * @param string $action_type Action.
     * @return void
     */
    public static function run_async_webhook($domain, $action_type) {
        self::send_license_webhook($domain, $action_type);
    }
    
    /**
     * Optional shared secret header for Dashboard inbound webhook.
     *
     * @return array<string, string>
     */
    private static function license_webhook_headers() {
        $secret = defined('WEBINOCRM_LICENSE_WEBHOOK_SECRET') ? WEBINOCRM_LICENSE_WEBHOOK_SECRET : '';
        $secret = apply_filters('webinocrm_license_webhook_secret', $secret);
        $secret = trim((string) $secret);
        if ('' === $secret) {
            return [];
        }
        return ['X-Webino-Webhook-Secret' => $secret];
    }

    /**
     * Send webhook to notify site about license status change
     *
     * @param string $domain Domain is the license
     * @param string $action_type (cancel, deactivate, activate)
     */
    public static function send_license_webhook($domain, $action_type) {
        // Try both possible URLs (http and https)
        $possible_urls = [
            'https://' . $domain,
            'http://' . $domain,
        ];
        
        foreach ($possible_urls as $site_url) {
            $webhook_url = trailingslashit($site_url) . 'wp-admin/admin-ajax.php';
            
            $response = wp_remote_post($webhook_url, [
                'body' => [
                    'action' => 'maneli_license_webhook',
                    'domain' => $domain,
                    'action_type' => $action_type,
                ],
                'headers' => self::license_webhook_headers(),
                'timeout' => 5,
                'sslverify' => true,
            ]);
            
            // If successful, break
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                break;
            }
        }
    }

    /**
     * Delete license
     *
     * @param int $id
     * @return bool
     */
    public static function delete_license($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT domain FROM $table_name WHERE id = %d", $id ), ARRAY_A );
        if ( $row && ! empty( $row['domain'] ) ) {
            self::queue_license_webhook( $row['domain'], 'deactivate' );
        }
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Renew license
     *
     * @param int $id
     * @param string $new_expiry_date
     * @return bool
     */
    public static function renew_license($id, $new_expiry_date) {
        return self::update_license($id, [
            'expiry_date' => $new_expiry_date,
            'status' => 'active'
        ]);
    }

    /**
     * Cancel license
     *
     * @param int $id
     * @return bool
     */
    public static function cancel_license($id) {
        return self::update_license($id, [
            'status' => 'cancelled'
        ]);
    }

    /**
     * Calculate remaining percentage
     *
     * @param array $license
     * @return float
     */
    public static function calculate_remaining_percentage($license) {
        // If no expiry date, it's VIP (unlimited) - return 100
        if (empty($license['expiry_date'])) {
            return 100;
        }
        
        if (empty($license['start_date'])) {
            return 0;
        }
        
        $start = strtotime($license['start_date']);
        $expiry = strtotime($license['expiry_date']);
        $now = current_time('timestamp');
        
        if ($now >= $expiry) {
            return 0;
        }
        
        if ($now <= $start) {
            return 100;
        }
        
        $total_duration = $expiry - $start;
        $remaining_duration = $expiry - $now;
        
        $percentage = ($remaining_duration / $total_duration) * 100;
        
        return round($percentage, 2);
    }

    /**
     * Calculate remaining days
     *
     * @param array $license
     * @return int|null Days remaining, or null for VIP (no expiry).
     */
    public static function calculate_remaining_days($license) {
        if (empty($license['expiry_date'])) {
            return null;
        }
        
        $expiry = strtotime($license['expiry_date']);
        $now = current_time('timestamp');
        
        if ($now >= $expiry) {
            return 0;
        }
        
        $diff = $expiry - $now;
        $days = floor($diff / (60 * 60 * 24));
        
        return max(0, $days);
    }

    /**
     * Get card color based on remaining percentage and expiry date
     *
     * @param float $percentage
     * @param array|null $license
     * @return string
     */
    public static function get_card_color($percentage, $license = null) {
        // If no expiry date, it's VIP - black color
        if ($license && empty($license['expiry_date'])) {
            return 'black';
        }
        
        if ($percentage > 75) {
            return 'green';
        } elseif ($percentage >= 50) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    /**
     * Generate unique license key automatically
     *
     * @return string
     */
    private static function generate_license_key() {
        // Generate a unique license key: WEBINO-XXXX-XXXX-XXXX-XXXX
        $prefix = 'WEBINO';
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $parts[] = strtoupper(wp_generate_password(4, false));
        }
        return $prefix . '-' . implode('-', $parts);
    }

    /**
     * Encrypt license key
     *
     * @param string $key
     * @return string
     */
    private static function encrypt_license_key($key) {
        // Use WordPress salts for encryption
        $key_to_use = AUTH_SALT . AUTH_KEY;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($key, 'aes-256-cbc', $key_to_use, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt license key
     *
     * @param string $encrypted_key
     * @return string|false
     */
    public static function decrypt_license_key($encrypted_key) {
        try {
            $key_to_use = AUTH_SALT . AUTH_KEY;
            $decoded = base64_decode($encrypted_key);
            if ($decoded === false) {
                return false;
            }
            $parts = explode('::', $decoded, 2);
            if (count($parts) !== 2) {
                return false;
            }
            list($encrypted_data, $iv) = $parts;
            return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key_to_use, 0, $iv);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate license by domain only (domain is the license)
     *
     * @param string $domain
     * @return array
     */
    public static function validate_license($domain, $update_expired = false) {
        $domain = self::normalize_domain($domain);
        $cache_key = 'wcrm_lic_chk_' . md5($domain);
        if (!$update_expired) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $license = self::get_license_by_domain($domain);

        if (!$license) {
            $result = [
                'valid' => false,
                'status' => 'not_found',
                'code' => 'not_found',
                'message' => 'لایسنس یافت نشد',
            ];
            set_transient($cache_key, $result, self::cache_ttl());
            return $result;
        }

        if ($license['status'] !== 'active') {
            $result = [
                'valid' => false,
                'status' => $license['status'],
                'message' => 'لایسنس غیرفعال است',
            ];
            set_transient($cache_key, $result, self::cache_ttl());
            return $result;
        }

        if (!empty($license['expiry_date'])) {
            $expiry = strtotime($license['expiry_date']);
            $now = current_time('timestamp');

            if ($now >= $expiry) {
                if ($update_expired) {
                    self::update_license($license['id'], ['status' => 'expired']);
                    self::invalidate_license_cache($domain);
                }

                $result = [
                    'valid' => false,
                    'status' => 'expired',
                    'message' => 'لایسنس منقضی شده است',
                    'expiry_date' => $license['expiry_date'],
                ];
                set_transient($cache_key, $result, self::cache_ttl());
                return $result;
            }
        }

        $result = [
            'valid' => true,
            'status' => 'active',
            'message' => 'لایسنس معتبر است',
            'expiry_date' => $license['expiry_date'],
            'remaining_days' => self::calculate_remaining_days($license),
            'remaining_percentage' => self::calculate_remaining_percentage($license),
        ];
        set_transient($cache_key, $result, self::cache_ttl());
        return $result;
    }

    /**
     * Activate license for domain (allows inactive -> active when not expired).
     *
     * @param string $domain Domain.
     * @return array<string,mixed>
     */
    public static function activate_license_for_domain($domain) {
        $domain = self::normalize_domain($domain);
        $license = self::get_license_by_domain($domain);

        if (!$license) {
            return [
                'ok' => false,
                'status' => 'error',
                'code' => 'not_found',
                'message' => 'لایسنس یافت نشد',
                'http_status' => 404,
            ];
        }

        if (in_array($license['status'], ['cancelled'], true)) {
            return [
                'ok' => false,
                'status' => 'cancelled',
                'code' => 'cancelled',
                'message' => 'لایسنس لغو شده است',
                'http_status' => 200,
            ];
        }

        if (!empty($license['expiry_date'])) {
            $expiry = strtotime($license['expiry_date']);
            if ($expiry && current_time('timestamp') >= $expiry) {
                if ($license['status'] !== 'expired') {
                    self::update_license($license['id'], ['status' => 'expired']);
                }
                self::invalidate_license_cache($domain);
                return [
                    'ok' => false,
                    'status' => 'expired',
                    'code' => 'expired',
                    'message' => 'لایسنس منقضی شده است',
                    'expiry_date' => $license['expiry_date'],
                    'http_status' => 200,
                ];
            }
        }

        if ($license['status'] !== 'active') {
            self::update_license($license['id'], ['status' => 'active']);
            $license['status'] = 'active';
        }

        if ( class_exists( 'WebinoCRM_ModirPayamak_Manager' ) ) {
            WebinoCRM_ModirPayamak_Manager::get_or_create_account( $domain );
            WebinoCRM_ModirPayamak_Manager::set_account_status( $domain, WebinoCRM_ModirPayamak_Manager::STATUS_ACTIVE );
        }

        self::invalidate_license_cache($domain);

        return [
            'ok' => true,
            'status' => 'success',
            'message' => 'لایسنس با موفقیت فعال شد',
            'expiry_date' => $license['expiry_date'],
            'remaining_days' => self::calculate_remaining_days($license),
            'remaining_percentage' => self::calculate_remaining_percentage($license),
            'http_status' => 200,
        ];
    }
}

WebinoCRM_License_Manager::init();

