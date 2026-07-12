<?php
/**
 * Auth Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Auth_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function send_login_otp( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
// Log for debugging
        webinocrm_debug_log('WebinoCRM: send_login_otp called');
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_otp_ip', 20, 3600 ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'تعداد درخواست کد بیش از حد مجاز است. لطفاً بعداً تلاش کنید.', 'webinocrm' ) ] );
        }
        if (!isset($_POST['phone_number'])) {
            webinocrm_debug_log('WebinoCRM: No phone number provided');
            return WebinoCRM_Service_Base::error( ['message' => __( 'شماره تلفن الزامی است.', 'webinocrm' )] );
        }
        try {
            $phone_number = sanitize_text_field($_POST['phone_number']);
            webinocrm_debug_log('WebinoCRM: Phone number received: ' . $phone_number);
            // Convert Persian/Arabic numerals to English first
            $phone_number = self::convert_persian_numbers($phone_number);
            webinocrm_debug_log('WebinoCRM: Phone number after conversion: ' . $phone_number);
            // Get pattern from settings (with fallback)
            $settings = WebinoCRM_Settings_Handler::get_all_settings();
            $phone_pattern = $settings['login_phone_pattern'] ?? '^09[0-9]{9}$';
            $phone_length = intval($settings['login_phone_length'] ?? 11);
            webinocrm_debug_log('WebinoCRM: Phone pattern: ' . $phone_pattern);
            webinocrm_debug_log('WebinoCRM: Phone length: ' . $phone_length);
            // Validate phone number format
            if (!preg_match('/' . $phone_pattern . '/', $phone_number)) {
                webinocrm_debug_log('WebinoCRM: Phone format invalid');
                return WebinoCRM_Service_Base::error( ['message' => __( 'فرمت شماره موبایل صحیح نیست. (مثال: 09123456789)', 'webinocrm' )] );
            }
            // Validate length
            if (strlen($phone_number) != $phone_length) {
                webinocrm_debug_log('WebinoCRM: Phone length invalid: ' . strlen($phone_number));
                return WebinoCRM_Service_Base::error( ['message' => sprintf( __( 'طول شماره موبایل باید %d رقم باشد.', 'webinocrm' ), $phone_length )] );
            }
            if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_otp_phone', 6, 3600, md5( $phone_number ) ) ) {
                return WebinoCRM_Service_Base::error( [ 'message' => __( 'برای این شماره تعداد درخواست کد بیش از حد مجاز است.', 'webinocrm' ) ] );
            }
            // Find user by phone number or create new one
            webinocrm_debug_log('WebinoCRM: Searching for user with phone: ' . $phone_number);
            $user = self::find_user_by_phone($phone_number);
            if (!$user) {
                webinocrm_debug_log('WebinoCRM: User not found, creating new user with phone: ' . $phone_number);
                // Create new user
                $user = self::create_user_from_phone($phone_number);
                if (!$user || is_wp_error($user)) {
                    webinocrm_debug_log('WebinoCRM: Failed to create user');
                    return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ایجاد حساب کاربری. لطفاً مجدداً تلاش کنید.', 'webinocrm' )] );
                }
                webinocrm_debug_log('WebinoCRM: New user created: ' . $user->user_login);
            } else {
                webinocrm_debug_log('WebinoCRM: User found: ' . $user->user_login);
            }
            // Generate OTP code with configurable length
            $otp_length = intval($settings['otp_length'] ?? 6);
            $otp_code = self::generate_otp_code($otp_length);
            // Store OTP in transient (expires in 5 minutes)
            $transient_key = 'webino_otp_' . md5($phone_number);
            set_transient($transient_key, [
                'code' => $otp_code,
                'user_id' => $user->ID,
                'attempts' => 0
            ], 5 * MINUTE_IN_SECONDS);
            // Send SMS
            $settings = WebinoCRM_Settings_Handler::get_all_settings();
            $sms_handler = webino_get_sms_handler($settings);
            if (!$sms_handler) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'سرویس پیامک به درستی پیکربندی نشده است.', 'webinocrm' )] );
            }
            // Get OTP pattern/template from settings
            $sms_service = $settings['sms_service'] ?? '';
            $success = false;
            if ($sms_service === 'melipayamak') {
                // Use pattern for Melipayamak
                $pattern_code = $settings['melipayamak_login_pattern'] ?? '';
                if (empty($pattern_code)) {
                    // Fallback to simple message
                    $message = "کد ورود شما: $otp_code\nاعتبار: 5 دقیقه";
                    $success = $sms_handler->send_sms($phone_number, $message);
                } else {
                    // Send with pattern
                    $success = $sms_handler->send_sms($phone_number, $pattern_code, ['amount' => $otp_code]);
                }
            } else {
                // For other services, use simple message
                $message_template = $settings['parsgreen_login_template'] ?? 'کد ورود شما: %CODE%';
                $message = str_replace('%CODE%', $otp_code, $message_template);
                $success = $sms_handler->send_sms($phone_number, $message);
            }
            if ($success) {
                WebinoCRM_Logger::add('ارسال کد ورود', [
                    'content' => "کد ورود برای کاربر {$user->user_login} ارسال شد.",
                    'type' => 'log'
                ]);
                return WebinoCRM_Service_Base::success( [
                    'message' => __( 'کد تایید به شماره موبایل شما ارسال شد.', 'webinocrm' ),
                    'expires_in' => 300 // 5 minutes in seconds
                ] );
            } else {
                return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ارسال پیامک. لطفاً بعداً تلاش کنید.', 'webinocrm' )] );
            }
        } catch (Exception $e) {
            webinocrm_debug_log('WebinoCRM Login OTP Error: ' . $e->getMessage());
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی رخ داد.', 'webinocrm' )] );
        }
	}

		public static function verify_login_otp( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_verify_ip', 50, 3600 ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است.', 'webinocrm' ) ] );
        }
        if (!isset($_POST['phone_number']) || !isset($_POST['otp_code'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'اطلاعات ناقص است.', 'webinocrm' )] );
        }
        try {
            $phone_number = sanitize_text_field($_POST['phone_number']);
            $otp_code = sanitize_text_field($_POST['otp_code']);
            // Convert Persian/Arabic numerals to English
            if (function_exists('tr_num')) {
                $phone_number = tr_num($phone_number, 'en');
                $otp_code = tr_num($otp_code, 'en');
            }
            // Get OTP from transient
            $transient_key = 'webino_otp_' . md5($phone_number);
            $otp_data = get_transient($transient_key);
            if (!$otp_data) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'کد تایید منقضی شده است. لطفاً کد جدید درخواست کنید.', 'webinocrm' )] );
            }
            // Check attempts
            if ($otp_data['attempts'] >= 3) {
                delete_transient($transient_key);
                return WebinoCRM_Service_Base::error( ['message' => __( 'تعداد تلاش‌های مجاز تمام شده است. لطفاً کد جدید درخواست کنید.', 'webinocrm' )] );
            }
            // Verify OTP code
            if ($otp_data['code'] !== $otp_code) {
                $otp_data['attempts']++;
                set_transient($transient_key, $otp_data, 5 * MINUTE_IN_SECONDS);
                $remaining = 3 - $otp_data['attempts'];
                return WebinoCRM_Service_Base::error( [
                    'message' => sprintf( __( 'کد تایید اشتباه است. (%d تلاش باقی‌مانده)', 'webinocrm' ), $remaining ),
                ] );
            }
            // OTP is correct, log in the user
            $user = get_user_by('ID', $otp_data['user_id']);
            if (!$user) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'کاربر یافت نشد.', 'webinocrm' )] );
            }
            // Clear the OTP transient
            delete_transient($transient_key);
            // Log in the user
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            // Log the login
            WebinoCRM_Logger::add('ورود با پیامک', [
                'content' => "کاربر {$user->user_login} با پیامک وارد شد.",
                'type' => 'log',
                'user_id' => $user->ID
            ]);
            // Get redirect URL based on user role
            $redirect_url = self::get_redirect_url_for_user($user);
            return WebinoCRM_Service_Base::success( [
                'message' => __( 'ورود موفقیت‌آمیز بود.', 'webinocrm' ),
                'redirect_url' => $redirect_url
            ] );
        } catch (Exception $e) {
            webinocrm_debug_log('WebinoCRM Verify OTP Error: ' . $e->getMessage());
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی رخ داد.', 'webinocrm' )] );
        }
	}

		public static function login_password( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_password_ip', 25, 3600 ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'تعداد تلاش‌های ورود بیش از حد مجاز است. لطفاً بعداً تلاش کنید.', 'webinocrm' ) ] );
        }
        $username = sanitize_text_field(
            $params['username'] ?? $params['login'] ?? $_POST['username'] ?? $_POST['login'] ?? ''
        );
        $password = $params['password'] ?? $_POST['password'] ?? '';
        $remember = ! empty( $params['remember'] ?? $_POST['remember'] ?? false );
        if ( '' === $username || '' === $password ) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'نام کاربری و رمز عبور الزامی است.', 'webinocrm' )] );
        }
        $username = self::convert_persian_numbers($username);
        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $phone_pattern = $settings['login_phone_pattern'] ?? '^09[0-9]{9}$';
        $user = null;
        if (preg_match('/' . $phone_pattern . '/', $username)) {
            $user = self::find_user_by_phone($username);
        }
        if (!$user) {
            $user = get_user_by('login', $username);
        }
        if (!$user) {
            $user = get_user_by('email', $username);
        }
        if (!$user && preg_match('/^[0-9]{10}$/', $username)) {
            $user = self::find_user_by_national_id($username);
        }
        if (!$user) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'نام کاربری، ایمیل، شماره موبایل یا کد ملی یافت نشد.', 'webinocrm' )] );
        }
        // Check password
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'رمز عبور اشتباه است.', 'webinocrm' )] );
        }
        // Log in the user
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        // Log the login
        WebinoCRM_Logger::add('ورود با رمز عبور', [
            'content' => "کاربر {$user->user_login} با رمز عبور وارد شد.",
            'type' => 'log',
            'user_id' => $user->ID
        ]);
        // Get redirect URL based on user role
        $redirect_url = self::get_redirect_url_for_user($user);
        return WebinoCRM_Service_Base::success( [
            'message' => __( 'ورود موفقیت‌آمیز بود.', 'webinocrm' ),
            'redirect_url' => $redirect_url
        ] );
	}

		public static function send_email_otp( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_email_otp_ip', 15, 3600 ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'تعداد درخواست بیش از حد مجاز است.', 'webinocrm' ) ] );
        }
        if (!isset($_POST['email'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'ایمیل الزامی است.', 'webinocrm' )] );
        }
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'فرمت ایمیل صحیح نیست.', 'webinocrm' )] );
        }
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_email_otp_addr', 5, 3600, md5( strtolower( $email ) ) ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'برای این ایمیل تعداد درخواست کد بیش از حد مجاز است.', 'webinocrm' ) ] );
        }
        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $otp_length = intval($settings['otp_length'] ?? 6);
        $otp_expiry_minutes = intval($settings['otp_expiry_minutes'] ?? 5);
        $expiry_seconds = $otp_expiry_minutes * MINUTE_IN_SECONDS;
        $user = get_user_by('email', $email);
        if (!$user) {
            $user = self::create_user_from_email($email);
            if (!$user || is_wp_error($user)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ایجاد حساب کاربری. لطفاً مجدداً تلاش کنید.', 'webinocrm' )] );
            }
        }
        $otp_code = self::generate_otp_code($otp_length);
        $transient_key = 'webino_email_otp_' . md5($email);
        set_transient($transient_key, [
            'code' => $otp_code,
            'user_id' => $user->ID,
            'attempts' => 0
        ], $expiry_seconds);
        $subject = $settings['login_email_otp_subject'] ?? 'کد ورود شما';
        $body_template = $settings['login_email_otp_body'] ?? "کد ورود شما: %CODE%\nاعتبار: {$otp_expiry_minutes} دقیقه";
        $body = str_replace('%CODE%', $otp_code, $body_template);
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $sent = wp_mail($email, $subject, $body, $headers);
        if ($sent) {
            WebinoCRM_Logger::add('ارسال کد ورود به ایمیل', [
                'content' => "کد ورود برای {$email} ارسال شد.",
                'type' => 'log'
            ]);
            return WebinoCRM_Service_Base::success( [
                'message' => __( 'کد تایید به ایمیل شما ارسال شد.', 'webinocrm' ),
                'expires_in' => $expiry_seconds
            ] );
        } else {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ارسال ایمیل. لطفاً بعداً تلاش کنید.', 'webinocrm' )] );
        }
	}

		public static function verify_email_otp( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_email_verify_ip', 50, 3600 ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است.', 'webinocrm' ) ] );
        }
        if (!isset($_POST['email']) || !isset($_POST['otp_code'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'اطلاعات ناقص است.', 'webinocrm' )] );
        }
        $email = sanitize_email($_POST['email']);
        $otp_code = sanitize_text_field($_POST['otp_code']);
        if (function_exists('tr_num')) {
            $otp_code = tr_num($otp_code, 'en');
        }
        $transient_key = 'webino_email_otp_' . md5($email);
        $otp_data = get_transient($transient_key);
        if (!$otp_data) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'کد تایید منقضی شده است. لطفاً کد جدید درخواست کنید.', 'webinocrm' )] );
        }
        $otp_max_attempts = intval(WebinoCRM_Settings_Handler::get_setting('otp_max_attempts', 3));
        if ($otp_data['attempts'] >= $otp_max_attempts) {
            delete_transient($transient_key);
            return WebinoCRM_Service_Base::error( ['message' => __( 'تعداد تلاش‌های مجاز تمام شده است. لطفاً کد جدید درخواست کنید.', 'webinocrm' )] );
        }
        if ($otp_data['code'] !== $otp_code) {
            $otp_data['attempts']++;
            $expiry = intval(WebinoCRM_Settings_Handler::get_setting('otp_expiry_minutes', 5)) * MINUTE_IN_SECONDS;
            set_transient($transient_key, $otp_data, $expiry);
            $remaining = $otp_max_attempts - $otp_data['attempts'];
            return WebinoCRM_Service_Base::error( [
                'message' => sprintf( __( 'کد تایید اشتباه است. (%d تلاش باقی‌مانده)', 'webinocrm' ), $remaining ),
            ] );
        }
        $user = get_user_by('ID', $otp_data['user_id']);
        if (!$user) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'کاربر یافت نشد.', 'webinocrm' )] );
        }
        delete_transient($transient_key);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        WebinoCRM_Logger::add('ورود با کد ایمیل', [
            'content' => "کاربر {$user->user_login} با کد ایمیل وارد شد.",
            'type' => 'log',
            'user_id' => $user->ID
        ]);
        $redirect_url = self::get_redirect_url_for_user($user);
        return WebinoCRM_Service_Base::success( [
            'message' => __( 'ورود موفقیت‌آمیز بود.', 'webinocrm' ),
            'redirect_url' => $redirect_url
        ] );
	}

		public static function register( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! get_option('users_can_register') ) {
            return WebinoCRM_Service_Base::error( ['message' => __('ثبت‌نام در این سایت غیرفعال است.', 'webinocrm')] );
        }
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'register_password_ip', 12, 3600 ) ) {
            return WebinoCRM_Service_Base::error( ['message' => __('تعداد تلاش‌های ثبت‌نام بیش از حد مجاز است. لطفاً بعداً تلاش کنید.', 'webinocrm')] );
        }
        $kind = isset($_POST['register_kind']) ? sanitize_key(wp_unslash($_POST['register_kind'])) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? wp_unslash($_POST['confirm_password']) : '';
        if ($password === '' || $confirm_password === '') {
            return WebinoCRM_Service_Base::error( ['message' => __( 'رمز عبور و تکرار آن الزامی است.', 'webinocrm' )] );
        }
        if ($password !== $confirm_password) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'رمز عبور و تکرار آن یکسان نیست.', 'webinocrm' )] );
        }
        if (strlen($password) < 6) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'رمز عبور باید حداقل 6 کاراکتر باشد.', 'webinocrm' )] );
        }
        $identifier_raw = isset($_POST['identifier']) ? wp_unslash($_POST['identifier']) : '';
        $identifier_raw = self::convert_persian_numbers((string) $identifier_raw);
        if ($kind === 'email') {
            $email = sanitize_email($identifier_raw);
            if (!is_email($email)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'فرمت ایمیل صحیح نیست.', 'webinocrm' )] );
            }
            if (email_exists($email)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'این ایمیل قبلاً ثبت شده است.', 'webinocrm' )] );
            }
            $username = sanitize_user(str_replace(['@', '.'], ['_', '_'], $email), true);
            if (username_exists($username)) {
                $username = $username . '_' . time();
            }
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                return WebinoCRM_Service_Base::error( ['message' => $user_id->get_error_message()] );
            }
            $user = new WP_User($user_id);
            $user->set_role('client');
            WebinoCRM_Logger::add('ثبت نام کاربر جدید', [
                'content' => "کاربر جدید با ایمیل {$email} ثبت‌نام کرد (رمز عبور).",
                'type' => 'log',
                'user_id' => $user_id,
            ]);
        } elseif ($kind === 'username') {
            $username = sanitize_user($identifier_raw, true);
            if ($username === '' || !validate_username($username)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'نام کاربری معتبر نیست.', 'webinocrm' )] );
            }
            if (username_exists($username)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'این نام کاربری قبلاً ثبت شده است.', 'webinocrm' )] );
            }
            $user_id = wp_create_user($username, $password, '');
            if (is_wp_error($user_id)) {
                return WebinoCRM_Service_Base::error( ['message' => $user_id->get_error_message()] );
            }
            $user = new WP_User($user_id);
            $user->set_role('client');
            WebinoCRM_Logger::add('ثبت نام کاربر جدید', [
                'content' => "کاربر جدید با نام کاربری {$username} ثبت‌نام کرد.",
                'type' => 'log',
                'user_id' => $user_id,
            ]);
        } elseif ($kind === 'national_id') {
            $nid = preg_replace('/\D/', '', $identifier_raw);
            if (strlen($nid) !== 10) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'کد ملی باید ۱۰ رقم باشد.', 'webinocrm' )] );
            }
            if (self::find_user_by_national_id($nid)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'این کد ملی قبلاً ثبت شده است.', 'webinocrm' )] );
            }
            $base = 'user_nid_' . $nid;
            $login = $base;
            if (username_exists($login)) {
                $login = $base . '_' . time();
            }
            $user_id = wp_create_user($login, $password, '');
            if (is_wp_error($user_id)) {
                return WebinoCRM_Service_Base::error( ['message' => $user_id->get_error_message()] );
            }
            update_user_meta($user_id, 'webino_national_id', $nid);
            $user = new WP_User($user_id);
            $user->set_role('client');
            WebinoCRM_Logger::add('ثبت نام کاربر جدید', [
                'content' => "کاربر جدید با کد ملی ثبت‌نام کرد.",
                'type' => 'log',
                'user_id' => $user_id,
            ]);
        } else {
            return WebinoCRM_Service_Base::error( ['message' => __( 'نوع ثبت‌نام نامعتبر است.', 'webinocrm' )] );
        }
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        $redirect_url = self::get_redirect_url_for_user($user);
        return WebinoCRM_Service_Base::success( [
            'message' => __( 'ثبت‌نام با موفقیت انجام شد.', 'webinocrm' ),
            'redirect_url' => $redirect_url,
        ] );
	}

		public static function set_password( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'login_set_password', 15, 3600 ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است.', 'webinocrm' ) ] );
        }
        // Must be logged in (e.g. after OTP) so anonymous callers cannot reset arbitrary accounts with only a nonce.
        if ( ! is_user_logged_in() ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'برای تنظیم رمز ابتدا با کد تأیید وارد شوید.', 'webinocrm' ) ] );
        }
        if (!isset($_POST['phone_number']) || !isset($_POST['password']) || !isset($_POST['confirm_password'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'اطلاعات ناقص است.', 'webinocrm' )] );
        }
        $phone_number = sanitize_text_field( wp_unslash( $_POST['phone_number'] ) );
        $password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
        $confirm_password = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';
        // Convert Persian/Arabic numerals to English
        $phone_number = self::convert_persian_numbers($phone_number);
        if (empty($phone_number) || empty($password) || empty($confirm_password)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'تمام فیلدها الزامی است.', 'webinocrm' )] );
        }
        if ($password !== $confirm_password) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'رمز عبور و تکرار آن یکسان نیست.', 'webinocrm' )] );
        }
        if (strlen($password) < 6) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'رمز عبور باید حداقل 6 کاراکتر باشد.', 'webinocrm' )] );
        }
        $current = wp_get_current_user();
        $user_by_phone = self::find_user_by_phone($phone_number);
        if ( ! $user_by_phone || (int) $user_by_phone->ID !== (int) $current->ID ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شماره موبایل با حساب کاربری جاری مطابقت ندارد.', 'webinocrm' ) ] );
        }
        // Update password for the logged-in user only
        wp_set_password($password, $current->ID);
        // Log the password setting
        WebinoCRM_Logger::add('تنظیم رمز عبور', [
            'content' => "کاربر {$current->user_login} رمز عبور خود را تنظیم کرد.",
            'type' => 'log',
            'user_id' => $current->ID
        ]);
        // Get redirect URL
        $redirect_url = self::get_redirect_url_for_user($current);
        return WebinoCRM_Service_Base::success( [
            'message' => __( 'رمز عبور با موفقیت تنظیم شد.', 'webinocrm' ),
            'redirect_url' => $redirect_url
        ] );
	}


	private static function convert_persian_numbers($string) {
$persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $string = str_replace($persian, $english, $string);
        $string = str_replace($arabic, $english, $string);
        return $string;
	}

	private static function create_user_from_email($email) {
$username = sanitize_user(str_replace(['@', '.'], ['_', '_'], $email), true);
        if (username_exists($username)) {
            $username = $username . '_' . time();
        }
        $user_id = wp_create_user(
            $username,
            wp_generate_password(12, true, true),
            $email
        );
        if (is_wp_error($user_id)) {
            return false;
        }
        $user = new WP_User($user_id);
        $user->set_role('client');
        WebinoCRM_Logger::add('ثبت نام کاربر جدید', [
            'content' => "کاربر جدید با ایمیل {$email} ثبت نام کرد.",
            'type' => 'log',
            'user_id' => $user_id
        ]);
        return $user;
	}

	private static function create_user_from_phone($phone_number) {
// Generate username from phone number
        $username = 'user_' . $phone_number;
        // Check if username already exists (shouldn't happen, but just in case)
        if (username_exists($username)) {
            $username = 'user_' . $phone_number . '_' . time();
        }
        // Create user with phone as username
        $user_id = wp_create_user(
            $username,
            wp_generate_password(12, true, true), // Random temporary password
            '' // No email required for now
        );
        if (is_wp_error($user_id)) {
            webinocrm_debug_log('WebinoCRM: Error creating user: ' . $user_id->get_error_message());
            return false;
        }
        // Save phone number in user meta
        update_user_meta($user_id, 'webino_mobile_phone', $phone_number);
        // Set default role (client/customer)
        $user = new WP_User($user_id);
        $user->set_role('client');
        // Log the registration
        WebinoCRM_Logger::add('ثبت نام کاربر جدید', [
            'content' => "کاربر جدید با شماره موبایل {$phone_number} ثبت نام کرد.",
            'type' => 'log',
            'user_id' => $user_id
        ]);
        return $user;
	}

	private static function find_user_by_national_id($national_id) {
$users = get_users([
            'meta_key'   => 'webino_national_id',
            'meta_value' => $national_id,
            'number'     => 1,
        ]);
        if (!empty($users)) {
            return $users[0];
        }
        return null;
	}

	private static function find_user_by_phone($phone_number) {
$phone_meta_keys = [
            'webino_mobile_phone',
            'wpyarud_phone',
            'webino_phone_number',
            'user_phone_number',
            'billing_phone' // WooCommerce billing phone
        ];
        foreach ($phone_meta_keys as $meta_key) {
            $users = get_users([
                'meta_key' => $meta_key,
                'meta_value' => $phone_number,
                'number' => 1
            ]);
            if (!empty($users)) {
                return $users[0];
            }
        }
        return null;
	}

	private static function generate_otp_code($length = 6) {
$max = pow(10, $length) - 1;
        return str_pad(rand(0, $max), $length, '0', STR_PAD_LEFT);
	}

	private static function get_redirect_url_for_user( $user ) {
		return webinocrm_login_redirect_url( $user );
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webino_send_login_otp' => array( __CLASS__, 'send_login_otp' ),
				'webino_verify_login_otp' => array( __CLASS__, 'verify_login_otp' ),
				'crm_login_with_password' => array( __CLASS__, 'login_password' ),
				'webino_send_email_otp' => array( __CLASS__, 'send_email_otp' ),
				'webino_verify_email_otp' => array( __CLASS__, 'verify_email_otp' ),
				'webinocrm_register_with_password' => array( __CLASS__, 'register' ),
				'webino_set_password' => array( __CLASS__, 'set_password' ),

			)
		);
	}
}
