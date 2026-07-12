<?php
/**
 * Settings AJAX Handler
 * Handles all settings-related AJAX requests
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Settings_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        // Get settings (for dashboard React app)
        add_action('wp_ajax_webinocrm_get_settings', [$this, 'get_settings']);
        // Save general settings
        add_action('wp_ajax_webino_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_webino_save_white_label_settings', [$this, 'save_white_label_settings']);
        add_action('wp_ajax_save_auth_settings', [$this, 'save_auth_settings']);
        
        // Save user preferences (language, theme, sidebar, etc.)
        add_action('wp_ajax_webino_save_user_preference', [$this, 'save_user_preference']);
        
        // Manage canned responses
        add_action('wp_ajax_webino_manage_canned_response', [$this, 'manage_canned_response']);
        add_action('wp_ajax_webino_delete_canned_response', [$this, 'delete_canned_response']);
        
        // Manage positions
        add_action('wp_ajax_webino_manage_position', [$this, 'manage_position']);
        add_action('wp_ajax_webino_delete_position', [$this, 'delete_position']);
        
        // Manage task categories
        add_action('wp_ajax_webino_manage_task_category', [$this, 'manage_task_category']);
        add_action('wp_ajax_webino_delete_task_category', [$this, 'delete_task_category']);

        add_action('wp_ajax_webinocrm_get_settings_hub', [$this, 'get_settings_hub']);
        add_action('wp_ajax_webinocrm_save_settings_hub_toggle', [$this, 'save_settings_hub_toggle']);
    }

    /**
     * Allowed module keys for hub toggles.
     */
    private function get_hub_module_keys() {
        return array( 'dashboard', 'hrm', 'finance', 'crm', 'pm', 'scm', 'sales', 'mfg', 'docs', 'distribution', 'admin', 'modirpayamak', 'bale_business', 'general', 'projects', 'bots', 'accounting' );
    }

    private function can_manage_settings() {
        return current_user_can('manage_options')
            || (function_exists('webinocrm_current_user_has_role') && webinocrm_current_user_has_role(['system_manager']));
    }

    private function can_manage_settings_hub() {
        return $this->can_manage_settings();
    }

    /**
     * Get enabled state for all settings hub modules.
     */
    public function get_settings_hub() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!$this->can_manage_settings_hub()) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز.', 'webinocrm')]);
        }

        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $modules = [];
        foreach ($this->get_hub_module_keys() as $key) {
            $opt = 'module_' . $key . '_enabled';
            $modules[$key] = !isset($settings[$opt]) || (string) $settings[$opt] === '1';
        }

        wp_send_json_success(['modules' => $modules]);
    }

    /**
     * Toggle a settings hub module on/off.
     */
    public function save_settings_hub_toggle() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!$this->can_manage_settings_hub()) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز.', 'webinocrm')]);
        }

        $module_key = isset($_POST['module_key']) ? sanitize_key($_POST['module_key']) : '';
        if (!in_array($module_key, $this->get_hub_module_keys(), true)) {
            wp_send_json_error(['message' => __('ماژول نامعتبر است.', 'webinocrm')]);
        }

        $enabled = isset($_POST['enabled']) && ($_POST['enabled'] === '1' || $_POST['enabled'] === 'true');
        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $settings['module_' . $module_key . '_enabled'] = $enabled ? '1' : '0';
        WebinoCRM_Settings_Handler::update_settings($settings);

        wp_send_json_success([
            'message' => __('تنظیمات ذخیره شد.', 'webinocrm'),
            'enabled' => $enabled,
        ]);
    }
    
    /**
     * Get settings by tab (for dashboard React app)
     */
    public function get_settings() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!$this->can_manage_settings()) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز.', 'webinocrm')]);
        }
        $tab = isset($_POST['settings_tab']) ? sanitize_key($_POST['settings_tab']) : '';
        $data = [];
        switch ($tab) {
            case 'authentication':
            case 'style':
            case 'payment':
            case 'sms':
            case 'modirpayamak':
            case 'notifications':
            case 'visitor_tracking':
                $tab_data = WebinoCRM_Settings_Handler::get_tab_settings($tab);
                if (is_wp_error($tab_data)) {
                    wp_send_json_error(['message' => $tab_data->get_error_message()]);
                }
                $data = $tab_data;
                break;
            case 'canned_responses':
                $posts = get_posts(['post_type' => 'canned_response', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title']);
                $data = ['items' => array_map(function ($p) {
                    return ['id' => $p->ID, 'title' => $p->post_title, 'content' => $p->post_content];
                }, $posts)];
                break;
            case 'positions':
                $posts = get_posts(['post_type' => 'position', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title']);
                $data = ['items' => array_map(function ($p) {
                    return ['id' => $p->ID, 'title' => $p->post_title, 'permissions' => (array) get_post_meta($p->ID, '_position_permissions', true)];
                }, $posts)];
                break;
            case 'task_categories':
                $terms = get_terms(['taxonomy' => 'task_category', 'hide_empty' => false]);
                $items = [];
                if (!is_wp_error($terms)) {
                    foreach ($terms as $t) {
                        $items[] = ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'color' => get_term_meta($t->term_id, 'category_color', true) ?: '#845adf'];
                    }
                }
                $data = ['items' => $items];
                break;
            case 'workflow':
            case 'automations':
            case 'forms':
            case 'leads':
                $data = WebinoCRM_Settings_Handler::get_all_settings();
                break;
            default:
                wp_send_json_error(['message' => 'تب تنظیمات نامعتبر است.']);
        }
        wp_send_json_success($data);
    }

    /**
     * Save user preference (language, theme, sidebar, etc.)
     */
    public function save_user_preference() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('لطفاً وارد شوید.', 'webinocrm')]);
        }
        
        $user_id = get_current_user_id();
        $preference = isset($_POST['preference']) ? sanitize_text_field($_POST['preference']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        
        if (empty($preference) || empty($value)) {
            wp_send_json_error(['message' => __('مقدار یا نام ترجیح نامعتبر است.', 'webinocrm')]);
        }
        
        // Allowed preferences
        $allowed_preferences = ['webino_language', 'webino_theme_mode', 'webino_sidebar_state'];
        
        if (!in_array($preference, $allowed_preferences)) {
            wp_send_json_error(['message' => __('نام ترجیح مجاز نیست.', 'webinocrm')]);
        }
        
        // Validate values
        if ($preference === 'webino_language' && !in_array($value, ['fa', 'en'])) {
            wp_send_json_error(['message' => __('مقدار زبان نامعتبر است.', 'webinocrm')]);
        }
        
        if ($preference === 'webino_theme_mode' && !in_array($value, ['light', 'dark'])) {
            wp_send_json_error(['message' => __('مقدار حالت نمایش نامعتبر است.', 'webinocrm')]);
        }
        
        if ($preference === 'webino_sidebar_state' && !in_array($value, ['open', 'closed'])) {
            wp_send_json_error(['message' => __('مقدار وضعیت نوار کناری نامعتبر است.', 'webinocrm')]);
        }
        
        // Save to user meta
        update_user_meta($user_id, $preference, $value);
        
        wp_send_json_success([
            'message' => __('ترجیح با موفقیت ذخیره شد.', 'webinocrm'),
            'preference' => $preference,
            'value' => $value
        ]);
    }

    /**
     * Save General Settings
     */
    public function save_settings() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');

        if (!$this->can_manage_settings()) {
            wp_send_json_error(['message' => __('شما دسترسی لازم برای این عملیات را ندارید.', 'webinocrm')]);
        }

        $settings_tab = isset($_POST['settings_tab']) ? sanitize_key($_POST['settings_tab']) : '';
        $messages = [
            'sms'              => __('تنظیمات پیامک با موفقیت ذخیره شد.', 'webinocrm'),
            'modirpayamak'     => __('تنظیمات مدیرپیامک ذخیره شد.', 'webinocrm'),
            'payment'          => __('تنظیمات درگاه پرداخت ذخیره شد.', 'webinocrm'),
            'notifications'    => __('تنظیمات اعلانات ذخیره شد.', 'webinocrm'),
            'style'            => __('تنظیمات استایل ذخیره شد.', 'webinocrm'),
            'visitor_tracking' => __('تنظیمات آمار بازدید ذخیره شد.', 'webinocrm'),
        ];

        if (!isset($messages[$settings_tab])) {
            wp_send_json_error(['message' => __('نوع تنظیمات مشخص نیست.', 'webinocrm')]);
        }

        $result = WebinoCRM_Settings_Handler::save_tab_settings($settings_tab, $_POST);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $response = [
            'message' => $messages[$settings_tab],
            'reload'  => true,
        ];
        if ('visitor_tracking' === $settings_tab) {
            $response['tracking_enabled'] = WebinoCRM_Settings_Handler::is_visitor_tracking_enabled();
        }

        wp_send_json_success($response);
    }

    /**
     * Save White Label Settings
     */
    public function save_white_label_settings() {
        // Check nonce with better error handling
        $nonce_check = check_ajax_referer('webinocrm-ajax-nonce', 'security', false);
        if (!$nonce_check) {
            webinocrm_debug_log('WebinoCRM: Nonce verification failed. POST data: ' . print_r($_POST, true));
            wp_send_json_error([
                'message' => 'خطا در تأیید امنیتی. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.',
                'error_code' => 'nonce_failed'
            ], 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
        }

        // Debug: Log received data (remove in production)
        webinocrm_debug_log('WebinoCRM White Label Save - POST Data: ' . print_r($_POST, true));

        // Branding settings - always save even if empty
        $company_name = isset($_POST['wl_company_name']) ? sanitize_text_field($_POST['wl_company_name']) : '';
        update_option('webinocrm_wl_company_name', $company_name);
        
        $company_url = isset($_POST['wl_company_url']) ? esc_url_raw($_POST['wl_company_url']) : '';
        update_option('webinocrm_wl_company_url', $company_url);
        
        // Logo settings - always save even if empty
        $company_logo = isset($_POST['wl_company_logo']) ? esc_url_raw($_POST['wl_company_logo']) : '';
        webinocrm_debug_log('WebinoCRM: Saving company_logo = ' . $company_logo);
        update_option('webinocrm_wl_company_logo', $company_logo);
        
        $login_logo = isset($_POST['wl_login_logo']) ? esc_url_raw($_POST['wl_login_logo']) : '';
        webinocrm_debug_log('WebinoCRM: Saving login_logo = ' . $login_logo);
        update_option('webinocrm_wl_login_logo', $login_logo);
        
        $email_logo = isset($_POST['wl_email_logo']) ? esc_url_raw($_POST['wl_email_logo']) : '';
        webinocrm_debug_log('WebinoCRM: Saving email_logo = ' . $email_logo);
        update_option('webinocrm_wl_email_logo', $email_logo);
        
        $company_icon = isset($_POST['wl_company_icon']) ? esc_url_raw($_POST['wl_company_icon']) : '';
        webinocrm_debug_log('WebinoCRM: Saving company_icon = ' . $company_icon);
        update_option('webinocrm_wl_company_icon', $company_icon);

        // Color settings - always save
        $primary_color = isset($_POST['wl_primary_color']) ? sanitize_hex_color($_POST['wl_primary_color']) : '#e03f2b';
        update_option('webinocrm_wl_primary_color', $primary_color);
        
        $secondary_color = isset($_POST['wl_secondary_color']) ? sanitize_hex_color($_POST['wl_secondary_color']) : '#6c757d';
        update_option('webinocrm_wl_secondary_color', $secondary_color);
        
        $accent_color = isset($_POST['wl_accent_color']) ? sanitize_hex_color($_POST['wl_accent_color']) : '#FF5722';
        update_option('webinocrm_wl_accent_color', $accent_color);
        
        $success_color = isset($_POST['wl_success_color']) ? sanitize_hex_color($_POST['wl_success_color']) : '#28a745';
        update_option('webinocrm_wl_success_color', $success_color);
        
        $warning_color = isset($_POST['wl_warning_color']) ? sanitize_hex_color($_POST['wl_warning_color']) : '#ffc107';
        update_option('webinocrm_wl_warning_color', $warning_color);
        
        $danger_color = isset($_POST['wl_danger_color']) ? sanitize_hex_color($_POST['wl_danger_color']) : '#dc3545';
        update_option('webinocrm_wl_danger_color', $danger_color);
        
        $info_color = isset($_POST['wl_info_color']) ? sanitize_hex_color($_POST['wl_info_color']) : '#17a2b8';
        update_option('webinocrm_wl_info_color', $info_color);

        // Text settings - always save even if empty
        $dashboard_title = isset($_POST['wl_dashboard_title']) ? sanitize_text_field($_POST['wl_dashboard_title']) : '';
        update_option('webinocrm_wl_dashboard_title', $dashboard_title);
        
        $welcome_message = isset($_POST['wl_welcome_message']) ? sanitize_textarea_field($_POST['wl_welcome_message']) : '';
        update_option('webinocrm_wl_welcome_message', $welcome_message);
        
        $footer_text = isset($_POST['wl_footer_text']) ? sanitize_text_field($_POST['wl_footer_text']) : '';
        update_option('webinocrm_wl_footer_text', $footer_text);
        
        $copyright_text = isset($_POST['wl_copyright_text']) ? wp_kses_post($_POST['wl_copyright_text']) : '';
        update_option('webinocrm_wl_copyright_text', $copyright_text);

        // Support settings - always save even if empty
        $support_email = isset($_POST['wl_support_email']) ? sanitize_email($_POST['wl_support_email']) : '';
        update_option('webinocrm_wl_support_email', $support_email);
        
        $support_phone = isset($_POST['wl_support_phone']) ? sanitize_text_field($_POST['wl_support_phone']) : '';
        update_option('webinocrm_wl_support_phone', $support_phone);
        
        $support_url = isset($_POST['wl_support_url']) ? esc_url_raw($_POST['wl_support_url']) : '';
        update_option('webinocrm_wl_support_url', $support_url);

        // Font and theme settings (save to general settings)
        $current_settings = WebinoCRM_Settings_Handler::get_all_settings();
        
        if (isset($_POST['font_family'])) {
            $current_settings['font_family'] = sanitize_text_field($_POST['font_family']);
        }
        
        if (isset($_POST['base_font_size'])) {
            $current_settings['base_font_size'] = sanitize_text_field($_POST['base_font_size']);
        }
        
        if (isset($_POST['default_theme'])) {
            $current_settings['default_theme'] = sanitize_text_field($_POST['default_theme']);
        }
        
        WebinoCRM_Settings_Handler::update_settings($current_settings);

        // Advanced settings
        if (isset($_POST['wl_custom_css'])) {
            update_option('webinocrm_wl_custom_css', wp_strip_all_tags($_POST['wl_custom_css']));
        }
        
        if (isset($_POST['wl_custom_js'])) {
            update_option('webinocrm_wl_custom_js', wp_strip_all_tags($_POST['wl_custom_js']));
        }
        
        $hide_branding = isset($_POST['wl_hide_branding']) ? 1 : 0;
        update_option('webinocrm_wl_hide_branding', $hide_branding);
        
        // Verify some values were actually saved
        $saved_company_name = get_option('webinocrm_wl_company_name', '');
        webinocrm_debug_log('WebinoCRM: Verification - Saved company_name = ' . $saved_company_name);
        $saved_company_logo = get_option('webinocrm_wl_company_logo', '');
        webinocrm_debug_log('WebinoCRM: Verification - Saved company_logo = ' . $saved_company_logo);
        
        wp_send_json_success([
            'message' => 'تمامی تنظیمات White Label با موفقیت ذخیره شد.',
            'reload' => true,
            'debug' => [
                'company_name' => $saved_company_name,
                'company_logo' => $saved_company_logo
            ]
        ]);
    }

    /**
     * Manage Canned Response
     */
    public function manage_canned_response() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
        }

        $response_id = isset($_POST['response_id']) ? intval($_POST['response_id']) : 0;
        $title = isset($_POST['response_title']) ? sanitize_text_field($_POST['response_title']) : '';
        $content = isset($_POST['response_content']) ? wp_kses_post($_POST['response_content']) : '';

        if (empty($title) || empty($content)) {
            wp_send_json_error(['message' => 'لطفاً تمام فیلدهای ضروری را پر کنید.']);
        }

        $post_data = [
            'post_type' => 'canned_response',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish'
        ];

        if ($response_id > 0) {
            $post_data['ID'] = $response_id;
            $result = wp_update_post($post_data);
            $message = 'پاسخ آماده با موفقیت بروزرسانی شد.';
        } else {
            $result = wp_insert_post($post_data);
            $message = 'پاسخ آماده جدید با موفقیت ایجاد شد.';
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'خطا در ذخیره پاسخ آماده.']);
        }

        wp_send_json_success([
            'message' => $message,
            'reload' => true
        ]);
    }

    /**
     * Delete Canned Response
     */
    public function delete_canned_response() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $response_id = isset($_POST['response_id']) ? intval($_POST['response_id']) : 0;
        
        if ($response_id && wp_delete_post($response_id, true)) {
            wp_send_json_success(['message' => 'پاسخ آماده حذف شد.']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف پاسخ آماده.']);
        }
    }

    /**
     * Manage Position
     */
    public function manage_position() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $position_id = isset($_POST['position_id']) ? intval($_POST['position_id']) : 0;
        $title = isset($_POST['position_title']) ? sanitize_text_field($_POST['position_title']) : '';
        $permissions = isset($_POST['position_permissions']) ? array_map('sanitize_text_field', $_POST['position_permissions']) : [];

        if (empty($title)) {
            wp_send_json_error(['message' => 'عنوان موقعیت شغلی الزامی است.']);
        }

        $post_data = [
            'post_type' => 'position',
            'post_title' => $title,
            'post_status' => 'publish'
        ];

        if ($position_id > 0) {
            $post_data['ID'] = $position_id;
            $result = wp_update_post($post_data);
            $message = 'موقعیت شغلی بروزرسانی شد.';
        } else {
            $result = wp_insert_post($post_data);
            $message = 'موقعیت شغلی جدید ایجاد شد.';
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'خطا در ذخیره موقعیت شغلی.']);
        }

        // Save permissions
        update_post_meta($result, '_position_permissions', $permissions);

        wp_send_json_success([
            'message' => $message,
            'reload' => true
        ]);
    }

    /**
     * Delete Position
     */
    public function delete_position() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $position_id = isset($_POST['position_id']) ? intval($_POST['position_id']) : 0;
        
        if ($position_id && wp_delete_post($position_id, true)) {
            wp_send_json_success(['message' => 'موقعیت شغلی حذف شد.']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف.']);
        }
    }

    /**
     * Manage Task Category
     */
    public function manage_task_category() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
        $color = isset($_POST['category_color']) ? sanitize_hex_color($_POST['category_color']) : '#845adf';

        if (empty($name)) {
            wp_send_json_error(['message' => 'نام دسته‌بندی الزامی است.']);
        }

        if ($category_id > 0) {
            $result = wp_update_term($category_id, 'task_category', ['name' => $name]);
            $message = 'دسته‌بندی بروزرسانی شد.';
        } else {
            $result = wp_insert_term($name, 'task_category');
            $message = 'دسته‌بندی جدید ایجاد شد.';
            $category_id = $result['term_id'];
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'خطا در ذخیره دسته‌بندی.']);
        }

        // Save color
        update_term_meta($category_id, 'category_color', $color);

        wp_send_json_success([
            'message' => $message,
            'reload' => true
        ]);
    }

    /**
     * Delete Task Category
     */
    public function delete_task_category() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if ($category_id && wp_delete_term($category_id, 'task_category')) {
            wp_send_json_success(['message' => 'دسته‌بندی حذف شد.']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف.']);
        }
    }

    /**
     * Save Authentication Settings
     */
    public function save_auth_settings() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');

        if (!$this->can_manage_settings()) {
            wp_send_json_error(['message' => __('شما دسترسی لازم برای این عملیات را ندارید.', 'webinocrm')]);
        }

        $result = WebinoCRM_Settings_Handler::save_tab_settings('authentication', $_POST);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('تنظیمات احراز هویت با موفقیت ذخیره شد.', 'webinocrm')]);
    }
}

