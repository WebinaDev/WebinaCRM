<?php
/**
 * WebinoCRM SMS AJAX Handler
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load settings handler and logger
require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-settings-handler.php';
require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-logger.php';

class WebinoCRM_SMS_Ajax_Handler {

    use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        add_action('wp_ajax_webino_send_custom_sms', [$this, 'send_custom_sms']);
    }

    /**
     * AJAX handler for sending a custom SMS from the user management page.
     */
    public function send_custom_sms() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'sms' ) );
    }

    /**
     * AJAX handler for saving settings.
     */
    public function save_settings() {
        // Log the start of settings save operation
        webinocrm_debug_log('WebinoCRM: Starting SMS settings save operation');
        
        // Test logging
        if (class_exists('WebinoCRM_Logger')) {
            WebinoCRM_Logger::add('تست لاگ پیامک', [
                'content' => 'تست لاگ‌گذاری برای تنظیمات پیامک',
                'type' => 'log',
                'details' => [
                    'user_id' => get_current_user_id(),
                    'timestamp' => current_time('mysql')
                ]
            ]);
        }
        
        try {
            // Check nonce
            if (!check_ajax_referer('webinocrm-ajax-nonce', 'security')) {
                webinocrm_debug_log('WebinoCRM: Invalid nonce in SMS settings save');
                wp_send_json_error(['message' => 'درخواست نامعتبر. لطفاً صفحه را رفرش کنید.']);
            }
            
            // Check user permissions
            if (!current_user_can('manage_options')) {
                webinocrm_debug_log('WebinoCRM: User does not have permission to save SMS settings. User ID: ' . get_current_user_id());
                wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
            }

            // Validate POST data
            if (!isset($_POST['webino_settings']) || !is_array($_POST['webino_settings'])) {
                webinocrm_debug_log('WebinoCRM: Invalid POST data for SMS settings. Data: ' . print_r($_POST, true));
                wp_send_json_error(['message' => 'داده‌های تنظیمات نامعتبر است.']);
            }

            $settings = $_POST['webino_settings'];
            webinocrm_debug_log('WebinoCRM: Received SMS settings data: ' . print_r($settings, true));
            
            // Get existing settings
            $existing_settings = WebinoCRM_Settings_Handler::get_all_settings();
            webinocrm_debug_log('WebinoCRM: Existing settings before merge: ' . print_r($existing_settings, true));
            
            // Sanitize new settings
            $sanitized_settings = [];
            foreach ($settings as $key => $value) {
                if (is_array($value)) {
                    $sanitized_settings[$key] = array_map('sanitize_text_field', $value);
                } else {
                    // Use sanitize_textarea_field for textarea fields
                    if (strpos($key, 'template') !== false || strpos($key, 'msg') !== false || strpos($key, 'pattern') !== false) {
                        $sanitized_settings[$key] = sanitize_textarea_field($value);
                    } else {
                        $sanitized_settings[$key] = sanitize_text_field($value);
                    }
                }
                webinocrm_debug_log("WebinoCRM: Sanitized setting '{$key}': '{$sanitized_settings[$key]}'");
            }

            // Merge with existing settings
            $merged_settings = array_merge($existing_settings, $sanitized_settings);
            webinocrm_debug_log('WebinoCRM: Merged settings: ' . print_r($merged_settings, true));

            // Save settings using the settings handler
            $result = WebinoCRM_Settings_Handler::update_settings($merged_settings);
            webinocrm_debug_log('WebinoCRM: Settings save result: ' . ($result ? 'SUCCESS' : 'FAILED'));

            if ($result) {
                // Log successful save
                webinocrm_debug_log('WebinoCRM: SMS settings saved successfully');
                
                // Add to system logs
                if (class_exists('WebinoCRM_Logger')) {
                    WebinoCRM_Logger::add('تنظیمات پیامک', [
                        'content' => 'تنظیمات پیامک با موفقیت ذخیره شد.',
                        'type' => 'log',
                        'details' => [
                            'sms_service' => $merged_settings['sms_service'] ?? 'not_set',
                            'lead_auto_sms_enabled' => $merged_settings['lead_auto_sms_enabled'] ?? '0',
                            'user_id' => get_current_user_id()
                        ]
                    ]);
                }
                
                wp_send_json_success(['message' => 'تنظیمات با موفقیت ذخیره شد.']);
            } else {
                webinocrm_integration_log('SMS settings save failed: update_settings returned false');
                wp_send_json_error(['message' => 'خطا در ذخیره تنظیمات. لطفاً دوباره تلاش کنید.']);
            }
        } catch (Exception $e) {
            webinocrm_integration_log('SMS settings save exception: ' . $e->getMessage());
            webinocrm_debug_log('WebinoCRM Settings Error Stack Trace: ' . $e->getTraceAsString());
            
            // Add error to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('خطای تنظیمات پیامک', [
                    'content' => 'خطا در ذخیره تنظیمات پیامک: ' . $e->getMessage(),
                    'type' => 'error',
                    'details' => [
                        'user_id' => get_current_user_id(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine()
                    ]
                ]);
            }
            
            wp_send_json_error(['message' => 'یک خطای سیستمی در هنگام ذخیره تنظیمات رخ داد.']);
        }
    }
}