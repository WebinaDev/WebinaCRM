<?php
/**
 * Appointment AJAX Handler
 *
 * @deprecated 2.x REST API is the source of truth for the dashboard SPA.
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Appointment_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        add_action('wp_ajax_webino_manage_appointment', [$this, 'manage_appointment']);
        add_action('wp_ajax_webino_get_appointments_calendar', [$this, 'get_appointments_calendar']);
        add_action('wp_ajax_webino_quick_create_appointment', [$this, 'quick_create_appointment']);
        add_action('wp_ajax_webino_delete_appointment', [$this, 'delete_appointment']);
        add_action('wp_ajax_webino_update_appointment_date', [$this, 'update_appointment_date']);
        add_action('wp_ajax_webino_get_customers_list', [$this, 'get_customers_list']);
        add_action('wp_ajax_webino_client_request_appointment', [$this, 'client_request_appointment']);
        add_action('wp_ajax_webinocrm_get_appointments', [$this, 'ajax_get_appointments']);
        add_action('wp_ajax_webinocrm_get_appointment', [$this, 'ajax_get_appointment']);
    }

    /**
     * Manage Appointment (existing - keeping compatibility)
     */
    public function manage_appointment() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');

        if (!function_exists('webinocrm_user_can_manage_appointments') || !webinocrm_user_can_manage_appointments()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pending';
        $notes = isset($_POST['notes']) ? wp_kses_post($_POST['notes']) : '';

        if (!$customer_id || !$title || $date === '' || $time === '') {
            wp_send_json_error(['message' => 'لطفاً تمام فیلدهای ضروری را پر کنید.']);
        }

        $datetime = function_exists('webinocrm_normalize_appointment_datetime')
            ? webinocrm_normalize_appointment_datetime($date, $time)
            : ($date . ' ' . $time);
        if ($datetime === '') {
            wp_send_json_error(['message' => 'تاریخ یا ساعت نامعتبر است.']);
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
            wp_send_json_error(['message' => 'خطا در ذخیره قرار ملاقات.']);
        }

        update_post_meta($result, '_appointment_datetime', $datetime);
        wp_set_post_terms($result, [$status], 'appointment_status');

        wp_send_json_success([
            'message' => $message,
            'appointment_id' => (int) $result,
        ]);
    }

    /**
     * Get Appointments for Calendar
     */
    public function get_appointments_calendar() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');

        if (!function_exists('webinocrm_user_can_read_appointments') || !webinocrm_user_can_read_appointments()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
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
        
        wp_send_json_success(['events' => $events]);
    }

    /**
     * Quick Create Appointment
     */
    public function quick_create_appointment() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '10:00';

        if (!$customer_id || !$title || !$date) {
            wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
        }

        $datetime = $date . ' ' . $time;

        $post_id = wp_insert_post([
            'post_type' => 'appointment',
            'post_title' => $title,
            'post_author' => $customer_id,
            'post_status' => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'خطا در ایجاد قرار ملاقات.']);
        }

        update_post_meta($post_id, '_appointment_datetime', $datetime);
        wp_set_post_terms($post_id, ['pending'], 'appointment_status');

        wp_send_json_success([
            'message' => 'قرار ملاقات ایجاد شد.',
            'appointment_id' => $post_id
        ]);
    }

    /**
     * Delete Appointment
     */
    public function delete_appointment() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');

        if (!function_exists('webinocrm_user_can_manage_appointments') || !webinocrm_user_can_manage_appointments()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        if ($appointment_id && wp_delete_post($appointment_id, true)) {
            wp_send_json_success(['message' => 'قرار ملاقات حذف شد.']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف.']);
        }
    }

    /**
     * Update Appointment Date
     */
    public function update_appointment_date() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
        
        if ($appointment_id && $new_date) {
            update_post_meta($appointment_id, '_appointment_datetime', $new_date);
            wp_send_json_success(['message' => 'تاریخ به‌روزرسانی شد.']);
        } else {
            wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
        }
    }

    /**
     * Get Customers List
     */
    public function get_customers_list() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');

        if (!function_exists('webinocrm_user_can_manage_appointments') || !webinocrm_user_can_manage_appointments()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $customers = get_users(['role__in' => ['customer', 'subscriber', 'client']]);
        
        $customer_list = [];
        foreach ($customers as $customer) {
            $customer_list[] = [
                'id' => $customer->ID,
                'name' => $customer->display_name
            ];
        }
        
        wp_send_json_success(['customers' => $customer_list]);
    }

    /**
     * Client Request Appointment
     */
    public function client_request_appointment() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
        }

        $current_user = wp_get_current_user();
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $notes = isset($_POST['notes']) ? wp_kses_post($_POST['notes']) : '';

        if (!$title || !$date || !$time) {
            wp_send_json_error(['message' => 'لطفاً تمام فیلدها را پر کنید.']);
        }

        $datetime = $date . ' ' . $time;

        $post_id = wp_insert_post([
            'post_type' => 'appointment',
            'post_title' => $title,
            'post_content' => $notes,
            'post_author' => $current_user->ID,
            'post_status' => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'خطا در ثبت درخواست.']);
        }

        update_post_meta($post_id, '_appointment_datetime', $datetime);
        wp_set_post_terms($post_id, ['pending'], 'appointment_status');

        wp_send_json_success([
            'message' => 'درخواست شما ثبت شد و در اسرع وقت بررسی خواهد شد.',
        ]);
    }

    /**
     * Get appointments list for React dashboard.
     */
    public function ajax_get_appointments() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!function_exists('webinocrm_user_can_read_appointments') || !webinocrm_user_can_read_appointments()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
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
        wp_send_json_success([
            'appointments' => $items,
            'statuses' => $statuses_list,
            'total' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
        ]);
    }

    /**
     * Get single appointment for React (edit form).
     */
    public function ajax_get_appointment() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!function_exists('webinocrm_user_can_read_appointments') || !webinocrm_user_can_read_appointments()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $post = get_post($appointment_id);
        if (!$post || $post->post_type !== 'appointment') {
            wp_send_json_error(['message' => 'قرار ملاقات یافت نشد.']);
        }
        $datetime = get_post_meta($appointment_id, '_appointment_datetime', true);
        $date_val = $datetime ? date('Y-m-d', strtotime($datetime)) : '';
        $time_val = $datetime ? date('H:i', strtotime($datetime)) : '';
        $status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $appointment_id, 'appointment_status', 'pending' );
        wp_send_json_success([
            'appointment' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'notes' => $post->post_content,
                'customer_id' => (int) $post->post_author,
                'date' => $date_val,
                'time' => $time_val,
                'status_slug' => $status_slug,
            ],
        ]);
    }
}

