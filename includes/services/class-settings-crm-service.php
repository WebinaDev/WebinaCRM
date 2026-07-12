<?php
/**
 * Settings Crm Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Settings_Crm_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!self::can_manage_settings()) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $tab = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'settings_tab', '' ) );
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
                    return WebinoCRM_Service_Base::error( $tab_data->get_error_message() );
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
                return WebinoCRM_Service_Base::error( __( 'تب تنظیمات نامعتبر است.', 'webinocrm' ) );
        }
        return WebinoCRM_Service_Base::success( $data );
	}

		public static function save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!self::can_manage_settings()) {
            return WebinoCRM_Service_Base::error( __( 'شما دسترسی لازم برای این عملیات را ندارید.', 'webinocrm' ), 403 );
        }
        $settings_tab = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'settings_tab', '' ) );
        $messages = [
            'sms'              => __('تنظیمات پیامک با موفقیت ذخیره شد.', 'webinocrm'),
            'modirpayamak'     => __('تنظیمات مدیرپیامک ذخیره شد.', 'webinocrm'),
            'payment'          => __('تنظیمات درگاه پرداخت ذخیره شد.', 'webinocrm'),
            'notifications'    => __('تنظیمات اعلانات ذخیره شد.', 'webinocrm'),
            'style'            => __('تنظیمات استایل ذخیره شد.', 'webinocrm'),
            'visitor_tracking' => __('تنظیمات آمار بازدید ذخیره شد.', 'webinocrm'),
        ];
        if (!isset($messages[$settings_tab])) {
            return WebinoCRM_Service_Base::error( __( 'نوع تنظیمات مشخص نیست.', 'webinocrm' ) );
        }
        $result = WebinoCRM_Settings_Handler::save_tab_settings($settings_tab, $params);
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( $result->get_error_message() );
        }
        $response = [
            'message' => $messages[$settings_tab],
            'reload'  => true,
        ];
        if ('visitor_tracking' === $settings_tab) {
            $response['tracking_enabled'] = WebinoCRM_Settings_Handler::is_visitor_tracking_enabled();
        }
        return WebinoCRM_Service_Base::success( $response );
	}

		public static function save_auth( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!self::can_manage_settings()) {
            return WebinoCRM_Service_Base::error( __( 'شما دسترسی لازم برای این عملیات را ندارید.', 'webinocrm' ), 403 );
        }
        $result = WebinoCRM_Settings_Handler::save_tab_settings('authentication', $params);
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( $result->get_error_message() );
        }
        return WebinoCRM_Service_Base::success( ['message' => __('تنظیمات احراز هویت با موفقیت ذخیره شد.', 'webinocrm')] );
	}

		public static function hub_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!self::can_manage_settings_hub()) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $modules = [];
        foreach (self::get_hub_module_keys() as $key) {
            $opt = 'module_' . $key . '_enabled';
            $modules[$key] = !isset($settings[$opt]) || (string) $settings[$opt] === '1';
        }
        return WebinoCRM_Service_Base::success( [ 'hub' => [ 'modules' => $modules ] ] );
	}

		public static function hub_toggle( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!self::can_manage_settings_hub()) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $module_key = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'module_key', '' ) );
        if ( '' === $module_key ) {
            $module_key = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'module_id', '' ) );
        }
        if (!in_array($module_key, self::get_hub_module_keys(), true)) {
            return WebinoCRM_Service_Base::error( __( 'ماژول نامعتبر است.', 'webinocrm' ) );
        }
        $enabled_raw = WebinoCRM_Service_Base::param( $params, 'enabled', '' );
        $enabled = ( $enabled_raw === '1' || $enabled_raw === 'true' || $enabled_raw === true || $enabled_raw === 1 );
        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $settings['module_' . $module_key . '_enabled'] = $enabled ? '1' : '0';
        WebinoCRM_Settings_Handler::update_settings($settings);
        return WebinoCRM_Service_Base::success( [
            'message' => __('تنظیمات ذخیره شد.', 'webinocrm'),
            'enabled' => $enabled,
        ] );
	}

		public static function canned_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( __( 'شما دسترسی لازم برای این عملیات را ندارید.', 'webinocrm' ), 403 );
        }
        $response_id = WebinoCRM_Service_Base::int_param( $params, 'response_id' );
        $title = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'response_title', '' ) );
        $content = wp_kses_post( (string) WebinoCRM_Service_Base::param( $params, 'response_content', '' ) );
        if (empty($title) || empty($content)) {
            return WebinoCRM_Service_Base::error( __( 'لطفاً تمام فیلدهای ضروری را پر کنید.', 'webinocrm' ) );
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
            return WebinoCRM_Service_Base::error( __( 'خطا در ذخیره پاسخ آماده.', 'webinocrm' ) );
        }
        return WebinoCRM_Service_Base::success( [
            'message' => $message,
            'reload' => true
        ] );
	}

		public static function canned_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $response_id = WebinoCRM_Service_Base::int_param( $params, 'response_id' );
        if ($response_id && wp_delete_post($response_id, true)) {
            return WebinoCRM_Service_Base::success( ['message' => 'پاسخ آماده حذف شد.'] );
        } else {
            return WebinoCRM_Service_Base::error( __( 'خطا در حذف پاسخ آماده.', 'webinocrm' ) );
        }
	}

		public static function position_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $position_id = WebinoCRM_Service_Base::int_param( $params, 'position_id' );
        $title = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'position_title', '' ) );
        $permissions = WebinoCRM_Service_Base::array_param( $params, 'position_permissions' );
        if ( ! is_array( $permissions ) ) {
            $permissions = [];
        }
        $permissions = array_map( 'sanitize_text_field', $permissions );
        if (empty($title)) {
            return WebinoCRM_Service_Base::error( __( 'عنوان موقعیت شغلی الزامی است.', 'webinocrm' ) );
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
            return WebinoCRM_Service_Base::error( __( 'خطا در ذخیره موقعیت شغلی.', 'webinocrm' ) );
        }
        // Save permissions
        update_post_meta($result, '_position_permissions', $permissions);
        return WebinoCRM_Service_Base::success( [
            'message' => $message,
            'reload' => true
        ] );
	}

		public static function position_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $position_id = WebinoCRM_Service_Base::int_param( $params, 'position_id' );
        if ($position_id && wp_delete_post($position_id, true)) {
            return WebinoCRM_Service_Base::success( ['message' => 'موقعیت شغلی حذف شد.'] );
        } else {
            return WebinoCRM_Service_Base::error( __( 'خطا در حذف.', 'webinocrm' ) );
        }
	}

		public static function task_category_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $category_id = WebinoCRM_Service_Base::int_param( $params, 'category_id' );
        $name = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'category_name', '' ) );
        $color = sanitize_hex_color( (string) WebinoCRM_Service_Base::param( $params, 'category_color', '#845adf' ) );
        if ( ! $color ) {
            $color = '#845adf';
        }
        if (empty($name)) {
            return WebinoCRM_Service_Base::error( __( 'نام دسته‌بندی الزامی است.', 'webinocrm' ) );
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
            return WebinoCRM_Service_Base::error( __( 'خطا در ذخیره دسته‌بندی.', 'webinocrm' ) );
        }
        // Save color
        update_term_meta($category_id, 'category_color', $color);
        return WebinoCRM_Service_Base::success( [
            'message' => $message,
            'reload' => true
        ] );
	}

		public static function task_category_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $category_id = WebinoCRM_Service_Base::int_param( $params, 'category_id' );
        if ($category_id && wp_delete_term($category_id, 'task_category')) {
            return WebinoCRM_Service_Base::success( ['message' => 'دسته‌بندی حذف شد.'] );
        } else {
            return WebinoCRM_Service_Base::error( __( 'خطا در حذف.', 'webinocrm' ) );
        }
	}


	private static function can_manage_settings() {
return current_user_can('manage_options')
            || (function_exists('webinocrm_current_user_has_role') && webinocrm_current_user_has_role(['system_manager']));
	}

	private static function can_manage_settings_hub() {
return self::can_manage_settings();
	}

	private static function get_hub_module_keys() {
		return array( 'dashboard', 'hrm', 'finance', 'crm', 'pm', 'scm', 'sales', 'mfg', 'docs', 'distribution', 'admin', 'modirpayamak', 'bale_business', 'general', 'projects', 'bots', 'accounting' );
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_settings' => array( __CLASS__, 'get' ),
				'webino_save_settings' => array( __CLASS__, 'save' ),
				'save_auth_settings' => array( __CLASS__, 'save_auth' ),
				'webinocrm_get_settings_hub' => array( __CLASS__, 'hub_get' ),
				'webinocrm_save_settings_hub_toggle' => array( __CLASS__, 'hub_toggle' ),
				'webino_manage_canned_response' => array( __CLASS__, 'canned_save' ),
				'webino_delete_canned_response' => array( __CLASS__, 'canned_delete' ),
				'webino_manage_position' => array( __CLASS__, 'position_save' ),
				'webino_delete_position' => array( __CLASS__, 'position_delete' ),
				'webino_manage_task_category' => array( __CLASS__, 'task_category_save' ),
				'webino_delete_task_category' => array( __CLASS__, 'task_category_delete' ),

			)
		);
	}
}
