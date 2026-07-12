<?php
/**
 * Appointments Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Appointments_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_read_appointments') || !webinocrm_user_can_read_appointments()) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        $per_page = isset($_POST['per_page']) ? min(100, max(1, intval($_POST['per_page']))) : 50;
        $args = [
            'post_type' => 'appointment',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => 'publish',
            'orderby' => 'meta_value',
            'meta_key' => '_appointment_datetime',
            'order' => 'ASC',
        ];
        if (!empty($search)) {
            $args['s'] = $search;
        }
        $meta_query = [];
        if (!empty($date_from) || !empty($date_to)) {
            if (!empty($date_from) && !empty($date_to)) {
                $meta_query[] = ['key' => '_appointment_datetime', 'value' => [$date_from . ' 00:00:00', $date_to . ' 23:59:59'], 'compare' => 'BETWEEN', 'type' => 'CHAR'];
            } elseif (!empty($date_from)) {
                $meta_query[] = ['key' => '_appointment_datetime', 'value' => $date_from, 'compare' => '>=', 'type' => 'CHAR'];
            } else {
                $meta_query[] = ['key' => '_appointment_datetime', 'value' => $date_to . ' 23:59:59', 'compare' => '<=', 'type' => 'CHAR'];
            }
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        if (webinocrm_current_user_has_role(['client', 'customer']) && !webinocrm_user_can_manage_appointments()) {
            $args['author'] = get_current_user_id();
        }
        $query = new \WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $customer_id = $post->post_author;
            $customer = get_user_by('id', $customer_id);
            $datetime = get_post_meta($post->ID, '_appointment_datetime', true);
            $status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $post->ID, 'appointment_status', 'pending' );
            $status_name = WebinoCRM_Term_Helper::get_first_term_name( $post->ID, 'appointment_status', 'نامشخص' );
            $items[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'notes' => $post->post_content,
                'customer_id' => $customer_id,
                'customer_name' => $customer ? $customer->display_name : '---',
                'datetime' => $datetime,
                'status_slug' => $status_slug,
                'status_name' => $status_name,
            ];
        }
        $statuses = get_terms(['taxonomy' => 'appointment_status', 'hide_empty' => false]);
        $statuses_list = [];
        if ($statuses && !is_wp_error($statuses)) {
            foreach ($statuses as $t) {
                $statuses_list[] = ['slug' => $t->slug, 'name' => $t->name];
            }
        }
        return WebinoCRM_Service_Base::success( [
            'appointments' => $items,
            'statuses' => $statuses_list,
            'total' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
        ] );
	}

		public static function get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_read_appointments') || !webinocrm_user_can_read_appointments()) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $post = get_post($appointment_id);
        if (!$post || $post->post_type !== 'appointment') {
            return WebinoCRM_Service_Base::error( ['message' => 'قرار ملاقات یافت نشد.'] );
        }
        $datetime = get_post_meta($appointment_id, '_appointment_datetime', true);
        $date_val = $datetime ? date('Y-m-d', strtotime($datetime)) : '';
        $time_val = $datetime ? date('H:i', strtotime($datetime)) : '';
        $status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $appointment_id, 'appointment_status', 'pending' );
        return WebinoCRM_Service_Base::success( [
            'appointment' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'notes' => $post->post_content,
                'customer_id' => (int) $post->post_author,
                'date' => $date_val,
                'time' => $time_val,
                'status_slug' => $status_slug,
            ],
        ] );
	}

		public static function calendar( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_read_appointments') || !webinocrm_user_can_read_appointments()) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';
        $args = [
            'post_type' => 'appointment',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        if ($start !== '' && $end !== '') {
            $args['meta_query'] = [
                [
                    'key' => '_appointment_datetime',
                    'value' => [$start . ' 00:00:00', $end . ' 23:59:59'],
                    'compare' => 'BETWEEN',
                    'type' => 'CHAR',
                ],
            ];
        } elseif ($start !== '') {
            $args['meta_query'] = [
                ['key' => '_appointment_datetime', 'value' => $start, 'compare' => '>=', 'type' => 'CHAR'],
            ];
        }
        if (webinocrm_current_user_has_role(['client', 'customer']) && !webinocrm_user_can_manage_appointments()) {
            $args['author'] = get_current_user_id();
        }
        $appointments = get_posts($args);
        $events = [];
        foreach ($appointments as $appointment) {
            $datetime = get_post_meta($appointment->ID, '_appointment_datetime', true);
            $customer_id = $appointment->post_author;
            $customer = get_user_by('id', $customer_id);
            $status = WebinoCRM_Term_Helper::get_first_term_slug( $appointment->ID, 'appointment_status', 'pending' );
            $colors = [
                'pending' => '#ffc107',
                'confirmed' => '#28a745',
                'cancelled' => '#dc3545',
                'completed' => '#17a2b8'
            ];
            $events[] = [
                'id' => $appointment->ID,
                'title' => $appointment->post_title,
                'start' => $datetime,
                'backgroundColor' => $colors[$status] ?? '#845adf',
                'borderColor' => $colors[$status] ?? '#845adf',
                'extendedProps' => [
                    'customer' => $customer ? $customer->display_name : 'نامشخص',
                    'status' => $status
                ]
            ];
        }
        return WebinoCRM_Service_Base::success( ['events' => $events] );
	}

		public static function save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_manage_appointments') || !webinocrm_user_can_manage_appointments()) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pending';
        $notes = isset($_POST['notes']) ? wp_kses_post($_POST['notes']) : '';
        if (!$customer_id || !$title || $date === '' || $time === '') {
            return WebinoCRM_Service_Base::error( ['message' => 'لطفاً تمام فیلدهای ضروری را پر کنید.'] );
        }
        $datetime = function_exists('webinocrm_normalize_appointment_datetime')
            ? webinocrm_normalize_appointment_datetime($date, $time)
            : ($date . ' ' . $time);
        if ($datetime === '') {
            return WebinoCRM_Service_Base::error( ['message' => 'تاریخ یا ساعت نامعتبر است.'] );
        }
        $post_data = [
            'post_type' => 'appointment',
            'post_title' => $title,
            'post_content' => $notes,
            'post_author' => $customer_id,
            'post_status' => 'publish'
        ];
        if ($appointment_id > 0) {
            $post_data['ID'] = $appointment_id;
            $result = wp_update_post($post_data);
            $message = 'قرار ملاقات بروزرسانی شد.';
        } else {
            $result = wp_insert_post($post_data);
            $message = 'قرار ملاقات جدید ایجاد شد.';
        }
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ذخیره قرار ملاقات.'] );
        }
        update_post_meta($result, '_appointment_datetime', $datetime);
        wp_set_post_terms($result, [$status], 'appointment_status');
        return WebinoCRM_Service_Base::success( [
            'message' => $message,
            'appointment_id' => (int) $result,
        ] );
	}

		public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_manage_appointments') || !webinocrm_user_can_manage_appointments()) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        if ($appointment_id && wp_delete_post($appointment_id, true)) {
            return WebinoCRM_Service_Base::success( ['message' => 'قرار ملاقات حذف شد.'] );
        } else {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در حذف.'] );
        }
	}

		public static function reschedule( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! function_exists( 'webinocrm_user_can_manage_appointments' ) || ! webinocrm_user_can_manage_appointments() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
		$appointment_id = isset( $params['appointment_id'] ) ? intval( $params['appointment_id'] ) : 0;
		if ( $appointment_id <= 0 ) {
			$appointment_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		$new_date = isset( $params['new_date'] ) ? sanitize_text_field( (string) $params['new_date'] ) : '';
		if ( $appointment_id <= 0 || '' === $new_date ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'اطلاعات ناقص است.', 'webinocrm' ) ) );
		}
		$post = get_post( $appointment_id );
		if ( ! $post || 'appointment' !== $post->post_type ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'قرار ملاقات یافت نشد.', 'webinocrm' ) ) );
		}
		update_post_meta( $appointment_id, '_appointment_datetime', $new_date );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'تاریخ به‌روزرسانی شد.', 'webinocrm' ) ) );
	}

		public static function customers( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_manage_appointments') || !webinocrm_user_can_manage_appointments()) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $customers = get_users(['role__in' => ['customer', 'subscriber', 'client']]);
        $customer_list = [];
        foreach ($customers as $customer) {
            $customer_list[] = [
                'id' => $customer->ID,
                'name' => $customer->display_name
            ];
        }
        return WebinoCRM_Service_Base::success( ['customers' => $customer_list] );
	}


	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_appointments' => array( __CLASS__, 'list' ),
				'webinocrm_get_appointment' => array( __CLASS__, 'get' ),
				'webino_get_appointments_calendar' => array( __CLASS__, 'calendar' ),
				'webino_manage_appointment' => array( __CLASS__, 'save' ),
				'webino_delete_appointment' => array( __CLASS__, 'delete' ),
				'webino_get_customers_list' => array( __CLASS__, 'customers' ),

			)
		);
	}
}
