<?php
/**
 * Profile Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Profile_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function update( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!is_user_logged_in()) {
            return WebinoCRM_Service_Base::error( ['message' => 'شما وارد نشده‌اید.'] );
        }
        $user_id = get_current_user_id();
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $user_data = [
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name)
        ];
        if (!empty($password)) {
            if ($password !== $password_confirm) {
                return WebinoCRM_Service_Base::error( ['message' => 'رمزهای عبور وارد شده یکسان نیستند.'] );
            }
            $user_data['user_pass'] = $password;
        }
        $result = wp_update_user($user_data);
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در به‌روزرسانی پروفایل: ' . $result->get_error_message()] );
        }
        if (isset($_POST['webino_mobile_phone'])) {
            update_user_meta($user_id, 'webino_mobile_phone', sanitize_text_field($_POST['webino_mobile_phone']));
        }
        if (!empty($_FILES['webino_profile_picture']['name'])) {
            $attachment_id = media_handle_upload('webino_profile_picture', 0); // 0 means not attached to a post
            if (!is_wp_error($attachment_id)) {
                update_user_meta($user_id, 'webino_profile_picture_id', $attachment_id);
            }
        }
        return WebinoCRM_Service_Base::success( ['message' => 'پروفایل شما با موفقیت به‌روزرسانی شد.', 'reload' => true] );
	}


	public static function register_actions() {
		self::register_map(
			array(

				'webino_update_my_profile' => array( __CLASS__, 'update' ),

			)
		);
	}
}
