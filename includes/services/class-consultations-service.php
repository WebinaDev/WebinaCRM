<?php
/**
 * Consultations Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Consultations_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $posts = get_posts([
            'post_type'   => 'consultation',
            'posts_per_page' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
        $statuses = [];
        $terms = get_terms(['taxonomy' => 'consultation_status', 'hide_empty' => false]);
        foreach ($terms as $t) {
            $statuses[] = ['slug' => $t->slug, 'name' => $t->name];
        }
        $items = [];
        foreach ($posts as $p) {
            $phone = get_post_meta($p->ID, '_consultation_phone', true);
            $email = get_post_meta($p->ID, '_consultation_email', true);
            $type = get_post_meta($p->ID, '_consultation_type', true);
            $datetime = get_post_meta($p->ID, '_consultation_datetime', true);
            $status_terms = get_the_terms($p->ID, 'consultation_status');
            $status_slug = !empty($status_terms) ? $status_terms[0]->slug : 'in-progress';
            $status_name = !empty($status_terms) ? $status_terms[0]->name : 'در حال انجام';
            $items[] = [
                'id'           => $p->ID,
                'name'         => $p->post_title,
                'phone'        => $phone,
                'email'        => $email,
                'type'         => $type,
                'type_label'   => ($type === 'in-person') ? 'حضوری' : 'تلفنی',
                'datetime'     => $datetime,
                'datetime_display' => ! empty( $datetime ) && class_exists( 'WebinoCRM_Date_Formatter' )
                    ? WebinoCRM_Date_Formatter::format_date( $datetime, 'Y/m/d H:i' )
                    : ( ! empty( $datetime ) ? date_i18n( 'Y/m/d H:i', strtotime( $datetime ) ) : '' ),
                'status_slug'  => $status_slug,
                'status_name'  => $status_name,
                'notes'        => $p->post_content,
            ];
        }
        return WebinoCRM_Service_Base::success( ['consultations' => $items, 'statuses' => $statuses] );
	}

		public static function save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $consultation_id = WebinoCRM_Service_Base::int_param( $params, 'consultation_id', 0 );
        $name          = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) );
        $phone         = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'phone', '' ) );
        $email         = sanitize_email( (string) WebinoCRM_Service_Base::param( $params, 'email', '' ) );
        $type          = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'type', 'phone' ) );
        $date          = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'date', '' ) );
        $time          = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'time', '' ) );
        $status_slug   = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'in-progress' ) );
        $notes         = wp_kses_post( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) );
        if (empty($name) || empty($phone)) {
            return WebinoCRM_Service_Base::error( ['message' => 'نام و شماره تماس الزامی است.'] );
        }
        $post_title = $name;
        $date_gregorian = '';
        if (!empty($date)) {
            if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', trim($date))) {
                $date_gregorian = trim($date);
            } else {
                $date_gregorian = function_exists('webino_jalali_to_gregorian') ? webino_jalali_to_gregorian($date) : '';
            }
        }
        $datetime = !empty($date_gregorian) ? $date_gregorian . ' ' . trim($time) : '';
        $post_data = [
            'post_title' => $post_title,
            'post_content' => $notes,
            'post_status' => 'publish',
            'post_type' => 'consultation',
        ];
        if ($consultation_id > 0) {
            $post_data['ID'] = $consultation_id;
            $result = wp_update_post($post_data, true);
            $message = 'مشاوره با موفقیت به‌روزرسانی شد.';
        } else {
            $result = wp_insert_post($post_data, true);
            $message = 'مشاوره جدید با موفقیت ثبت شد.';
        }
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در پردازش مشاوره.'] );
        }
        $the_consultation_id = is_int($result) ? $result : $consultation_id;
        update_post_meta($the_consultation_id, '_consultation_phone', $phone);
        update_post_meta($the_consultation_id, '_consultation_email', $email);
        update_post_meta($the_consultation_id, '_consultation_type', $type);
        update_post_meta($the_consultation_id, '_consultation_datetime', $datetime);
        wp_set_object_terms($the_consultation_id, $status_slug, 'consultation_status');
        return WebinoCRM_Service_Base::success( ['message' => $message, 'reload' => true] );
	}

		public static function convert_project( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $consultation_id = WebinoCRM_Service_Base::int_param( $params, 'consultation_id' );
        if ( ! $consultation_id && ! empty( $params['id'] ) ) {
            $consultation_id = absint( $params['id'] );
        }
        $consultation = get_post( $consultation_id );
        if (!$consultation || $consultation->post_type !== 'consultation') {
            return WebinoCRM_Service_Base::error( ['message' => 'مشاوره یافت نشد.'] );
        }
        $name = $consultation->post_title;
        $email = get_post_meta($consultation_id, '_consultation_email', true);
        $phone = get_post_meta($consultation_id, '_consultation_phone', true);
        // Find or create user
        $customer_id = 0;
        if (!empty($email) && ($user = get_user_by('email', $email))) {
            $customer_id = $user->ID;
        } else {
            // Create a new user
            $username = !empty($email) ? sanitize_user(substr($email, 0, strpos($email, '@')), true) : sanitize_user('user_' . $phone, true);
            if (empty($username) || username_exists($username)) {
                $username = 'user_' . time();
            }
            $password = wp_generate_password();
            $customer_id = wp_create_user($username, $password, $email);
            if (is_wp_error($customer_id)) {
                 return WebinoCRM_Service_Base::error( ['message' => 'خطا در ایجاد کاربر جدید: ' . $customer_id->get_error_message()] );
            }
            wp_update_user(['ID' => $customer_id, 'display_name' => $name, 'first_name' => $name, 'role' => 'customer']);
            update_user_meta($customer_id, 'webino_mobile_phone', $phone);
        }
        // Create Contract
        $contract_id = wp_insert_post([
            'post_title' => 'قرارداد برای ' . $name,
            'post_author' => $customer_id,
            'post_status' => 'publish',
            'post_type' => 'contract',
        ]);
        if (is_wp_error($contract_id)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ایجاد قرارداد.'] );
        }
        // Create Project
        $project_id = wp_insert_post([
            'post_title' => 'پروژه جدید برای ' . $name,
            'post_author' => $customer_id,
            'post_status' => 'publish',
            'post_type' => 'project',
        ]);
        if (is_wp_error($project_id)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ایجاد پروژه.'] );
        }
        // Link project to contract
        update_post_meta($project_id, '_contract_id', $contract_id);
        // Update consultation status
        wp_set_object_terms($consultation_id, 'converted', 'consultation_status');
        $base = function_exists( 'webino_get_dashboard_spa_base_path' )
            ? webino_get_dashboard_spa_base_path()
            : '/dashboard';
        $redirect_url = rtrim( $base, '/' ) . '/contracts?action=new&contract_id=' . (int) $contract_id;
        return WebinoCRM_Service_Base::success( [
            'message'      => 'مشاوره با موفقیت به پروژه تبدیل شد. در حال انتقال به صفحه قرارداد...',
            'redirect_url' => $redirect_url,
        ] );
	}


	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_consultations' => array( __CLASS__, 'list' ),
				'webino_manage_consultation' => array( __CLASS__, 'save' ),
				'webino_convert_consultation_to_project' => array( __CLASS__, 'convert_project' ),

			)
		);
	}
}
