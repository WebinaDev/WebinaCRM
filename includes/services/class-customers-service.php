<?php
/**
 * Customers Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Customers_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		try {
        $can_list = current_user_can( 'manage_options' )
            || ( function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'system_manager', 'administrator' ) ) );
        if ( ! $can_list ) {
            return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
        }
        $has_org_taxonomy = taxonomy_exists( 'organizational_position' );
        $search = isset($params['search']) ? sanitize_text_field($params['search']) : (isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '');
        $role_filter = isset($params['role']) ? sanitize_key($params['role']) : (isset($_POST['role']) ? sanitize_key($_POST['role']) : '');
        $paged = isset($params['paged']) ? max(1, (int) $params['paged']) : (isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1);
        $per_page = isset($params['per_page']) ? min(100, max(1, (int) $params['per_page'])) : (isset($_GET['per_page']) ? min(100, max(1, (int) $_GET['per_page'])) : 25);
        $args = [
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
        ];
        if (!empty($role_filter)) {
            if ($role_filter === 'staff') {
                $args['role__in'] = ['system_manager', 'finance_manager', 'team_member', 'administrator'];
            } else {
                $args['role'] = $role_filter;
            }
        }
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name'];
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => 'webino_mobile_phone', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'webino_national_id', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'first_name', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'last_name', 'value' => $search, 'compare' => 'LIKE'],
            ];
        } else {
            unset($args['meta_query']);
        }
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_users = (int) $user_query->get_total();
        $user_counts = count_users();
        if ( ! is_array( $user_counts ) ) {
            $user_counts = array();
        }
        $wp_roles = wp_roles();
        $role_defs = ( $wp_roles && isset( $wp_roles->roles ) && is_array( $wp_roles->roles ) )
            ? $wp_roles->roles
            : array();
        $items = [];
        foreach ($users as $user) {
            try {
            $phone = get_user_meta($user->ID, 'webino_mobile_phone', true);
            $role_slug = !empty($user->roles) ? $user->roles[0] : '';
            $role_name = ( ! empty( $user->roles ) && isset( $role_defs[ $user->roles[0] ]['name'] ) )
                ? $role_defs[ $user->roles[0] ]['name']
                : '---';
            $avatar_url = get_avatar_url($user->ID, ['size' => 40]);
            $can_delete = (get_current_user_id() != $user->ID && $user->ID != 1);
            $department_name = '---';
            $job_title_name = '---';
            $department_id = 0;
            $job_title_id = 0;
            if ( $has_org_taxonomy && ( $role_filter === 'staff' || in_array( $role_slug, [ 'system_manager', 'finance_manager', 'team_member', 'administrator' ], true ) ) ) {
                $positions = wp_get_object_terms( $user->ID, 'organizational_position' );
                if ( ! is_wp_error( $positions ) && ! empty( $positions ) ) {
                    $pos = $positions[0];
                    if ((int) $pos->parent === 0) {
                        $department_name = $pos->name;
                        $department_id = (int) $pos->term_id;
                    } else {
                        $job_title_name = $pos->name;
                        $job_title_id = (int) $pos->term_id;
                        $department_id = (int) $pos->parent;
                        $parent_dept = get_term($pos->parent, 'organizational_position');
                        if (!is_wp_error($parent_dept) && $parent_dept) {
                            $department_name = $parent_dept->name;
                        }
                    }
                }
            }
            $items[] = [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->user_email,
                'phone' => $phone ?: '',
                'role_slug' => $role_slug,
                'role_name' => $role_name,
                'department_name' => $department_name,
                'job_title_name' => $job_title_name,
                'department_id' => $department_id,
                'job_title_id' => $job_title_id,
                'registered' => $user->user_registered,
                'registered_jalali' => self::format_user_registered( $user->user_registered ),
                'avatar_url' => $avatar_url,
                'delete_nonce' => $can_delete ? wp_create_nonce('webino_delete_user_' . $user->ID) : '',
                'can_delete' => $can_delete,
            ];
            } catch ( Throwable $e ) {
                if ( function_exists( 'webinocrm_integration_log' ) ) {
                    webinocrm_integration_log(
                        'Customers list user skip #' . (int) $user->ID . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                    );
                }
            }
        }
        $avail_roles = isset( $user_counts['avail_roles'] ) && is_array( $user_counts['avail_roles'] )
            ? $user_counts['avail_roles']
            : array();
        $stats = [
            'total' => isset( $user_counts['total_users'] ) ? (int) $user_counts['total_users'] : $total_users,
            'customers' => (int) ( $avail_roles['customer'] ?? $avail_roles['client'] ?? 0 ),
            'staff' => 0,
        ];
        foreach ( ['system_manager', 'finance_manager', 'team_member', 'administrator'] as $r ) {
            $stats['staff'] += (int) ( $avail_roles[ $r ] ?? 0 );
        }
        $roles = [];
        $editable_roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
        if ( is_array( $editable_roles ) ) {
            foreach ( $editable_roles as $slug => $role_data ) {
                $roles[] = [
                    'slug' => $slug,
                    'name' => is_array( $role_data ) && isset( $role_data['name'] ) ? $role_data['name'] : (string) $slug,
                ];
            }
        }
        $payload = [
            'users' => $items,
            'stats' => $stats,
            'roles' => $roles,
            'total' => $total_users,
            'total_pages' => $per_page > 0 ? (int) ceil($total_users / $per_page) : 1,
            'current_page' => $paged,
            'per_page' => $per_page,
        ];
        $departments = [];
        $job_titles = [];
        try {
            $all_positions = $has_org_taxonomy
                ? get_terms( [ 'taxonomy' => 'organizational_position', 'hide_empty' => false, 'orderby' => 'name' ] )
                : [];
            if ( $all_positions && ! is_wp_error( $all_positions ) ) {
                foreach ( $all_positions as $t ) {
                    if ( (int) $t->parent === 0 ) {
                        $departments[] = [ 'id' => $t->term_id, 'name' => $t->name ];
                    } else {
                        $job_titles[] = [ 'id' => $t->term_id, 'name' => $t->name, 'parent' => $t->parent ];
                    }
                }
            }
        } catch ( Throwable $e ) {
            if ( function_exists( 'webinocrm_integration_log' ) ) {
                webinocrm_integration_log( 'Customers list org terms error: ' . $e->getMessage() );
            }
        }
        $payload['departments'] = $departments;
        $payload['job_titles'] = $job_titles;
        return WebinoCRM_Service_Base::success( $payload );
		} catch ( Throwable $e ) {
			if ( function_exists( 'webinocrm_integration_log' ) ) {
				webinocrm_integration_log(
					'Customers list error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
				);
			}
			$message = __( 'خطا در بارگذاری لیست کاربران.', 'webinocrm' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
				$message .= ' (' . $e->getMessage() . ')';
			}
			return WebinoCRM_Service_Base::error(
				$message,
				500
			);
		}
	}

		public static function save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شما دسترسی لازم برای مدیریت کاربران را ندارید.', 'webinocrm' )] );
        }
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $password = $_POST['password'];
        $role = sanitize_key($_POST['role']);
        if (!is_email($email) || empty($last_name) || empty($role)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'لطفاً فیلدهای ضروری (نام خانوادگی، ایمیل و نقش) را پر کنید.', 'webinocrm' )] );
        }
        if ($user_id === 0 && empty($password)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'برای کاربر جدید، وارد کردن رمز عبور ضروری است.', 'webinocrm' )] );
        }
        $user_data = [
            'user_email' => $email, 'first_name' => $first_name, 'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name), 'role' => $role
        ];
        if (!empty($password)) $user_data['user_pass'] = $password;
        if ($user_id > 0) {
            $user_data['ID'] = $user_id;
            $result = wp_update_user($user_data);
            $message = 'پروفایل با موفقیت به‌روزرسانی شد.';
        } else {
            if (email_exists($email)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'کاربری با این ایمیل از قبل وجود دارد.', 'webinocrm' )] );
            }
            $user_data['user_login'] = $email;
            $result = wp_insert_user($user_data);
            $message = 'کاربر جدید با موفقیت ایجاد شد.';
        }
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( ['message' => sprintf( __( 'خطا در ذخیره‌سازی اطلاعات کاربر: %s', 'webinocrm' ), $result->get_error_message() )] );
        } else {
            $the_user_id = is_int($result) ? $result : $user_id;
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'webino_') === 0) {
                    $sanitized_value = sanitize_text_field($value);
                    if ($key === 'webino_birth_date' || $key === 'webino_hire_date') {
                        $sanitized_value = webino_jalali_to_gregorian($sanitized_value);
                    }
                    update_user_meta($the_user_id, $key, $sanitized_value);
                }
            }
            if ( self::is_staff_role( $role ) && ( isset( $_POST['department'] ) || isset( $_POST['job_title'] ) ) ) {
                $department_id = isset( $_POST['department'] ) ? intval( $_POST['department'] ) : 0;
                $job_title_id  = isset( $_POST['job_title'] ) ? intval( $_POST['job_title'] ) : 0;
                $term_to_set   = $job_title_id > 0 ? $job_title_id : ( $department_id > 0 ? $department_id : 0 );
                if ( $term_to_set > 0 ) {
                    wp_set_object_terms( $the_user_id, $term_to_set, 'organizational_position', false );
                } else {
                    wp_set_object_terms( $the_user_id, [], 'organizational_position', false );
                }
            } elseif ( ! self::is_staff_role( $role ) ) {
                wp_set_object_terms( $the_user_id, [], 'organizational_position', false );
            }
            if (!empty($_FILES['webino_profile_picture']['name'])) {
                $attachment_id = media_handle_upload('webino_profile_picture', $the_user_id);
                if (!is_wp_error($attachment_id)) {
                    update_user_meta($the_user_id, 'webino_profile_picture_id', $attachment_id);
                }
            }
            return WebinoCRM_Service_Base::success( [ 'message' => $message ] );
        }
	}

		public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('delete_users') || !isset($_POST['user_id']) || !isset($_POST['nonce'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شما دسترسی لازم برای حذف کاربر را ندارید.', 'webinocrm' )] );
        }
        $user_id_to_delete = intval($_POST['user_id']);
        $current_user_id = get_current_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'webino_delete_user_' . $user_id_to_delete)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطای امنیتی. لطفاً صفحه را رفرش کنید.', 'webinocrm' )] );
        }
        if ($user_id_to_delete === $current_user_id) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شما نمی‌توانید حساب کاربری خود را حذف کنید.', 'webinocrm' )] );
        }
        if ($user_id_to_delete === 1) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'امکان حذف مدیر اصلی سایت وجود ندارد.', 'webinocrm' )] );
        }
        if (!current_user_can('delete_user', $user_id_to_delete)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شما اجازه حذف این کاربر را ندارید.', 'webinocrm' )] );
        }
        if (wp_delete_user($user_id_to_delete)) {
            return WebinoCRM_Service_Base::success( ['message' => __( 'کاربر با موفقیت حذف شد.', 'webinocrm' )] );
        } else {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطایی در هنگام حذف کاربر رخ داد.', 'webinocrm' )] );
        }
	}

		public static function org_position_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! current_user_can( 'manage_options' ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => 'دسترسی غیرمجاز.' ] );
        }
        $term_id   = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
        $name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
        if ( $name === '' ) {
            return WebinoCRM_Service_Base::error( [ 'message' => 'نام الزامی است.' ] );
        }
        if ( $term_id > 0 ) {
            $result = wp_update_term( $term_id, 'organizational_position', [
                'name'   => $name,
                'parent' => $parent_id,
            ] );
        } else {
            $result = wp_insert_term( $name, 'organizational_position', [ 'parent' => $parent_id ] );
        }
        if ( is_wp_error( $result ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => $result->get_error_message() ] );
        }
        $id = $term_id > 0 ? $term_id : (int) $result['term_id'];
        return WebinoCRM_Service_Base::success( [
            'message' => $term_id > 0 ? 'به‌روزرسانی شد.' : 'ایجاد شد.',
            'term'    => [ 'id' => $id, 'name' => $name, 'parent' => $parent_id ],
        ] );
	}

		public static function org_position_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! current_user_can( 'manage_options' ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => 'دسترسی غیرمجاز.' ] );
        }
        $term_id = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
        if ( $term_id <= 0 ) {
            return WebinoCRM_Service_Base::error( [ 'message' => 'شناسه نامعتبر است.' ] );
        }
        $children = get_terms( [
            'taxonomy'   => 'organizational_position',
            'parent'     => $term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ] );
        if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => 'ابتدا زیرمجموعه‌ها را حذف کنید.' ] );
        }
        $result = wp_delete_term( $term_id, 'organizational_position' );
        if ( is_wp_error( $result ) || ! $result ) {
            return WebinoCRM_Service_Base::error( [ 'message' => 'خطا در حذف.' ] );
        }
        return WebinoCRM_Service_Base::success( [ 'message' => 'حذف شد.' ] );
	}

		public static function sms( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options') || !isset($_POST['user_id']) || !isset($_POST['message'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز یا اطلاعات ناقص.', 'webinocrm' )] );
        }
        try {
            $user_id = intval($_POST['user_id']);
            $message = sanitize_textarea_field($_POST['message']);
            $user_phone = get_user_meta($user_id, 'webino_mobile_phone', true);
            if (empty($user_phone)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'شماره موبایل برای این کاربر ثبت نشده است.', 'webinocrm' )] );
            }
            if (empty($message)) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'متن پیام نمی‌تواند خالی باشد.', 'webinocrm' )] );
            }
            $settings = WebinoCRM_Settings_Handler::get_all_settings();
            $sms_handler = webino_get_sms_handler($settings);
            if (!$sms_handler) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'سرویس پیامک به درستی پیکربندی نشده است. لطفاً به بخش تنظیمات مراجعه کنید.', 'webinocrm' )] );
            }
            $success = $sms_handler->send_sms($user_phone, $message);
            if ($success) {
                WebinoCRM_Logger::add('ارسال پیامک دستی', ['content' => "یک پیامک دستی به کاربر با شناسه {$user_id} ارسال شد.", 'type' => 'log']);
                return WebinoCRM_Service_Base::success( ['message' => __( 'پیامک با موفقیت ارسال شد.', 'webinocrm' )] );
            } else {
                return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ارسال پیامک. لطفاً تنظیمات سرویس پیامک و لاگ‌های سرور را بررسی کنید.', 'webinocrm' )] );
            }
        } catch (Exception $e) {
            webinocrm_integration_log('SMS manual send exception: ' . $e->getMessage());
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی در هنگام ارسال پیامک رخ داد. جزئیات خطا در لاگ سرور ثبت شد.', 'webinocrm' )] );
        }
	}

		public static function bale( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        if ($user_id <= 0 || $message === '') {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شناسه کاربر یا متن پیام نامعتبر است.', 'webinocrm' )] );
        }
        $chat_id = (string) get_user_meta($user_id, 'webinocrm_bale_chat_id', true);
        if ($chat_id === '') {
            return WebinoCRM_Service_Base::error( ['message' => __( 'این کاربر هنوز به ربات بله متصل نشده است.', 'webinocrm' )] );
        }
        $client = new \WebinaBaleBusiness\Bale\Client();
        $result = $client->send_message(['chat_id' => $chat_id, 'text' => $message]);
        \WebinaBaleBusiness\Support\Logger::log('info', 'bale_manual_message_sent', ['user_id' => $user_id, 'chat_id' => $chat_id]);
        return WebinoCRM_Service_Base::success( ['message' => __( 'پیام بله ارسال شد.', 'webinocrm' ), 'result' => $result] );
	}

		public static function bale_bulk( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'all';
        $roles = [];
        if (isset($_POST['roles'])) {
            $raw_roles = wp_unslash($_POST['roles']);
            if (is_array($raw_roles)) {
                $roles = array_map('sanitize_key', $raw_roles);
            } elseif (is_string($raw_roles) && $raw_roles !== '') {
                $roles = array_filter(array_map('sanitize_key', explode(',', $raw_roles)));
            }
        }
        if ($message === '') {
            return WebinoCRM_Service_Base::error( ['message' => __( 'متن پیام نمی‌تواند خالی باشد.', 'webinocrm' )] );
        }
        $args = [
            'number' => 5000,
            'fields' => 'ID',
            'meta_key' => 'webinocrm_bale_chat_id',
            'meta_compare' => 'EXISTS',
        ];
        if ($mode === 'filtered' && !empty($roles)) {
            $args['role__in'] = $roles;
        }
        $users = get_users($args);
        $client = new \WebinaBaleBusiness\Bale\Client();
        $sent = 0;
        $failed = 0;
        foreach ($users as $uid) {
            $chat_id = (string) get_user_meta((int) $uid, 'webinocrm_bale_chat_id', true);
            if ($chat_id === '') {
                continue;
            }
            $result = $client->send_message(['chat_id' => $chat_id, 'text' => $message]);
            if (is_array($result) && !empty($result['ok'])) {
                $sent++;
            } else {
                $failed++;
            }
        }
        \WebinaBaleBusiness\Support\Logger::log('info', 'bale_bulk_message_sent', ['mode' => $mode, 'sent' => $sent, 'failed' => $failed]);
        return WebinoCRM_Service_Base::success( ['message' => __( 'ارسال انبوه انجام شد.', 'webinocrm' ), 'total' => count($users), 'sent' => $sent, 'failed' => $failed] );
	}


	private static function is_staff_role( $role_slug ) {
return in_array( $role_slug, [ 'system_manager', 'finance_manager', 'team_member', 'administrator' ], true );
	}

	/**
	 * Safe registration date for list payloads (Jalali when available).
	 *
	 * @param string $registered MySQL datetime.
	 * @return string
	 */
	private static function format_user_registered( $registered ) {
		if ( empty( $registered ) ) {
			return '';
		}
		try {
			if ( ! class_exists( 'WebinoCRM_Date_Formatter' ) && defined( 'WEBINOCRM_PLUGIN_DIR' ) ) {
				require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-date-formatter.php';
			}
			if ( class_exists( 'WebinoCRM_Date_Formatter' ) ) {
				return WebinoCRM_Date_Formatter::format_date( $registered, 'Y/m/d' );
			}
			if ( function_exists( 'jdate' ) ) {
				$ts = strtotime( $registered );
				return $ts ? jdate( 'Y/m/d', $ts ) : '';
			}
		} catch ( Throwable $e ) {
			// Fall through to Gregorian.
		}
		$ts = strtotime( $registered );
		return $ts ? gmdate( 'Y/m/d', $ts ) : '';
	}

	/**
	 * Customer 360 JSON for SPA.
	 *
	 * @param array $params Request params.
	 * @return array
	 */
	public static function customer_360( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}
		$customer_id = isset( $params['id'] ) ? (int) $params['id'] : ( isset( $params['customer_id'] ) ? (int) $params['customer_id'] : 0 );
		if ( $customer_id <= 0 ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'شناسه مشتری نامعتبر است.', 'webinocrm' ) ] );
		}
		$customer = get_user_by( 'id', $customer_id );
		if ( ! $customer ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'مشتری یافت نشد.', 'webinocrm' ) ] );
		}
		$projects = get_posts( [
			'post_type'      => 'project',
			'posts_per_page' => -1,
			'meta_query'     => [ [ 'key' => '_customer_id', 'value' => $customer_id ] ],
		] );
		$contracts = get_posts( [
			'post_type'      => 'contract',
			'posts_per_page' => -1,
			'meta_query'     => [ [ 'key' => '_customer_id', 'value' => $customer_id ] ],
		] );
		$tickets = get_posts( [
			'post_type'      => 'ticket',
			'posts_per_page' => 10,
			'author'         => $customer_id,
		] );
		$total_revenue = 0.0;
		$contract_items = [];
		foreach ( $contracts as $contract ) {
			$amount = (float) get_post_meta( $contract->ID, '_total_amount', true );
			$total_revenue += $amount;
			$contract_items[] = [
				'id'     => $contract->ID,
				'title'  => $contract->post_title,
				'date'   => get_the_date( 'Y-m-d', $contract ),
				'amount' => $amount,
			];
		}
		$project_items = array_map( static function ( $p ) {
			return [
				'id'    => $p->ID,
				'title' => $p->post_title,
				'date'  => get_the_date( 'Y-m-d', $p ),
			];
		}, $projects );
		$ticket_items = array_map( static function ( $t ) {
			return [
				'id'    => $t->ID,
				'title' => $t->post_title,
				'date'  => get_the_date( 'Y-m-d', $t ),
			];
		}, $tickets );
		return WebinoCRM_Service_Base::success( [
			'customer' => [
				'id'           => $customer->ID,
				'display_name' => $customer->display_name,
				'email'        => $customer->user_email,
				'phone'        => get_user_meta( $customer_id, 'webino_mobile_phone', true ) ?: '',
				'registered'   => $customer->user_registered,
				'avatar_url'   => get_avatar_url( $customer_id, [ 'size' => 96 ] ),
			],
			'stats'    => [
				'projects_count'  => count( $projects ),
				'contracts_count' => count( $contracts ),
				'tickets_count'   => count( $tickets ),
				'total_revenue'   => $total_revenue,
			],
			'projects'  => $project_items,
			'contracts' => $contract_items,
			'tickets'   => $ticket_items,
		] );
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_users' => array( __CLASS__, 'list' ),
				'webino_manage_user' => array( __CLASS__, 'save' ),
				'webino_delete_user' => array( __CLASS__, 'delete' ),
				'webinocrm_manage_org_position' => array( __CLASS__, 'org_position_save' ),
				'webinocrm_delete_org_position' => array( __CLASS__, 'org_position_delete' ),
				'webino_send_custom_sms' => array( __CLASS__, 'sms' ),
				'webinocrm_send_bale_message' => array( __CLASS__, 'bale' ),
				'webinocrm_send_bale_bulk_message' => array( __CLASS__, 'bale_bulk' ),

			)
		);
	}
}
