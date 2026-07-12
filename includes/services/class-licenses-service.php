<?php
/**
 * Licenses Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Licenses_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!webinocrm_user_can_manage_licenses()) {
            return WebinoCRM_Service_Base::error( ['message' => __('دسترسی غیرمجاز.', 'webinocrm')] );
        }
        $licenses = WebinoCRM_License_Manager::get_all_licenses();
        return WebinoCRM_Service_Base::success( [
            'licenses' => $licenses
        ] );
	}

		public static function create( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
// Check nonce
        if (!webinocrm_user_can_manage_licenses()) {
            return WebinoCRM_Service_Base::error( ['message' => __('دسترسی غیرمجاز.', 'webinocrm')] );
        }
        $project_name = isset($_POST['project_name']) ? sanitize_text_field($_POST['project_name']) : '';
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        $expiry_date = isset($_POST['expiry_date']) && !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : current_time('mysql');
        $logo_url = isset($_POST['logo_url']) ? esc_url_raw($_POST['logo_url']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
        if (empty($project_name) || empty($domain)) {
            return WebinoCRM_Service_Base::error( ['message' => 'لطفاً تمام فیلدهای الزامی را پر کنید'] );
        }
        $domain = WebinoCRM_License_Manager::normalize_domain($domain);
        // Validate domain format (more lenient)
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $domain)) {
            return WebinoCRM_Service_Base::error( ['message' => 'فرمت دامنه نامعتبر است. مثال: example.com'] );
        }
        // Domain is the license - no license_key needed
        $data = [
            'project_name' => $project_name,
            'domain' => $domain,
            'expiry_date' => $expiry_date,
            'start_date' => $start_date,
            'logo_url' => $logo_url,
            'status' => $status
        ];
        $license_id = WebinoCRM_License_Manager::add_license($data);
        if ($license_id === false) {
            // More detailed error message
            global $wpdb;
            $table_name = $wpdb->prefix . 'webinocrm_licenses';
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE domain = %s", $domain));
            if ($existing) {
                return WebinoCRM_Service_Base::error( ['message' => 'این دامنه قبلاً ثبت شده است.'] );
            } else {
                return WebinoCRM_Service_Base::error( ['message' => 'خطا در افزودن لایسنس. لطفاً دوباره تلاش کنید.'] );
            }
        }
        $license = WebinoCRM_License_Manager::get_all_licenses();
        $new_license = null;
        foreach ($license as $l) {
            if ($l['id'] == $license_id) {
                $new_license = $l;
                break;
            }
        }
        return WebinoCRM_Service_Base::success( [
            'message' => 'لایسنس با موفقیت افزوده شد',
            'license' => $new_license
        ] );
	}

		public static function update( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!webinocrm_user_can_manage_licenses()) {
            return WebinoCRM_Service_Base::error( ['message' => __('دسترسی غیرمجاز.', 'webinocrm')] );
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return WebinoCRM_Service_Base::error( ['message' => 'شناسه لایسنس نامعتبر است'] );
        }
        $data = [];
        if (isset($_POST['project_name'])) {
            $data['project_name'] = sanitize_text_field($_POST['project_name']);
        }
        if (isset($_POST['domain'])) {
            $data['domain'] = WebinoCRM_License_Manager::normalize_domain(sanitize_text_field($_POST['domain']));
        }
        if (isset($_POST['license_key'])) {
            $data['license_key'] = sanitize_text_field($_POST['license_key']);
        }
        if (isset($_POST['expiry_date'])) {
            $data['expiry_date'] = sanitize_text_field($_POST['expiry_date']);
        }
        if (isset($_POST['start_date'])) {
            $data['start_date'] = sanitize_text_field($_POST['start_date']);
        }
        if (isset($_POST['logo_url'])) {
            $data['logo_url'] = esc_url_raw($_POST['logo_url']);
        }
        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }
        if (empty($data)) {
            return WebinoCRM_Service_Base::error( ['message' => 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده است'] );
        }
        $result = WebinoCRM_License_Manager::update_license($id, $data);
        if (!$result) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در به‌روزرسانی لایسنس'] );
        }
        return WebinoCRM_Service_Base::success( ['message' => 'لایسنس با موفقیت به‌روزرسانی شد'] );
	}

		public static function renew( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!webinocrm_user_can_manage_licenses()) {
            return WebinoCRM_Service_Base::error( ['message' => __('دسترسی غیرمجاز.', 'webinocrm')] );
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $new_expiry_date = isset($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : '';
        if (!$id || empty($new_expiry_date)) {
            return WebinoCRM_Service_Base::error( ['message' => 'لطفاً تاریخ انقضای جدید را وارد کنید'] );
        }
        $result = WebinoCRM_License_Manager::renew_license($id, $new_expiry_date);
        if (!$result) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در تمدید لایسنس'] );
        }
        return WebinoCRM_Service_Base::success( ['message' => 'لایسنس با موفقیت تمدید شد'] );
	}

		public static function cancel( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!webinocrm_user_can_manage_licenses()) {
            return WebinoCRM_Service_Base::error( ['message' => __('دسترسی غیرمجاز.', 'webinocrm')] );
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return WebinoCRM_Service_Base::error( ['message' => 'شناسه لایسنس نامعتبر است'] );
        }
        $result = WebinoCRM_License_Manager::cancel_license($id);
        if (!$result) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در لغو لایسنس'] );
        }
        return WebinoCRM_Service_Base::success( ['message' => 'لایسنس با موفقیت لغو شد'] );
	}

		public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!webinocrm_user_can_manage_licenses()) {
            return WebinoCRM_Service_Base::error( ['message' => __('دسترسی غیرمجاز.', 'webinocrm')] );
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return WebinoCRM_Service_Base::error( ['message' => 'شناسه لایسنس نامعتبر است'] );
        }
        $result = WebinoCRM_License_Manager::delete_license($id);
        if (!$result) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در حذف لایسنس'] );
        }
        return WebinoCRM_Service_Base::success( ['message' => 'لایسنس با موفقیت حذف شد'] );
	}


	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_licenses' => array( __CLASS__, 'list' ),
				'webinocrm_add_license' => array( __CLASS__, 'create' ),
				'webinocrm_update_license' => array( __CLASS__, 'update' ),
				'webinocrm_renew_license' => array( __CLASS__, 'renew' ),
				'webinocrm_cancel_license' => array( __CLASS__, 'cancel' ),
				'webinocrm_delete_license' => array( __CLASS__, 'delete' ),

			)
		);
	}
}
