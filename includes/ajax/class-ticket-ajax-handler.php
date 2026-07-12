<?php
/**
 * WebinoCRM Ticket AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/tickets. Kept for legacy WP admin pages.
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Ticket_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        add_action('wp_ajax_webino_new_ticket', [$this, 'ajax_new_ticket']);
        add_action('wp_ajax_ticket_reply', [$this, 'ajax_ticket_reply']);
        add_action('wp_ajax_webino_convert_ticket_to_task', [$this, 'ajax_convert_ticket_to_task']);
        add_action('wp_ajax_webino_submit_ticket_rating', [$this, 'ajax_submit_ticket_rating']);
        add_action('wp_ajax_nopriv_webino_submit_ticket_rating', [$this, 'ajax_submit_ticket_rating']);
        add_action('wp_ajax_webino_get_canned_response', [$this, 'ajax_get_canned_response']);
        add_action('wp_ajax_webinocrm_get_tickets', [$this, 'ajax_get_tickets']);
        add_action('wp_ajax_webinocrm_get_ticket', [$this, 'ajax_get_ticket']);
    }

    public function ajax_get_tickets() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
        }
        $current_user_id = get_current_user_id();
        $is_manager = current_user_can('manage_options');
        $is_team_member = in_array('team_member', (array) wp_get_current_user()->roles);

        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        $status_filter = isset($_POST['status_filter']) ? sanitize_key($_POST['status_filter']) : '';
        $priority_filter = isset($_POST['priority_filter']) ? sanitize_key($_POST['priority_filter']) : '';
        $department_filter = isset($_POST['department_filter']) ? intval($_POST['department_filter']) : 0;
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;

        $args = [
            'post_type' => 'ticket',
            'posts_per_page' => 15,
            'post_status' => 'publish',
            'paged' => $paged,
            'orderby' => 'modified',
            'order' => 'DESC',
            'tax_query' => ['relation' => 'AND'],
        ];
        if ($status_filter) {
            $args['tax_query'][] = ['taxonomy' => 'ticket_status', 'field' => 'slug', 'terms' => $status_filter];
        }
        if ($priority_filter) {
            $args['tax_query'][] = ['taxonomy' => 'ticket_priority', 'field' => 'slug', 'terms' => $priority_filter];
        }
        if ($department_filter > 0) {
            $args['tax_query'][] = ['taxonomy' => 'organizational_position', 'field' => 'term_id', 'terms' => $department_filter];
        }
        if ($search) {
            $args['s'] = $search;
        }
        if (!$is_manager && !$is_team_member) {
            $args['author'] = $current_user_id;
        }

        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $status_terms = get_the_terms($post->ID, 'ticket_status');
            $department_terms = get_the_terms($post->ID, 'organizational_position');
            $priority_terms = get_the_terms($post->ID, 'ticket_priority');
            $items[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'author_name' => get_the_author_meta('display_name', $post->post_author),
                'modified' => get_the_modified_date('Y/m/d H:i', $post),
                'status_slug' => !empty($status_terms) ? $status_terms[0]->slug : 'open',
                'status_name' => !empty($status_terms) ? $status_terms[0]->name : 'نامشخص',
                'department_name' => !empty($department_terms) ? $department_terms[0]->name : '---',
                'priority_slug' => !empty($priority_terms) ? $priority_terms[0]->slug : 'default',
                'priority_name' => !empty($priority_terms) ? $priority_terms[0]->name : '---',
            ];
        }

        $statuses = [];
        foreach (get_terms(['taxonomy' => 'ticket_status', 'hide_empty' => false]) as $t) {
            $statuses[] = ['slug' => $t->slug, 'name' => $t->name];
        }
        $priorities = [];
        foreach (get_terms(['taxonomy' => 'ticket_priority', 'hide_empty' => false]) as $t) {
            $priorities[] = ['slug' => $t->slug, 'name' => $t->name, 'term_id' => $t->term_id];
        }
        $departments = [];
        foreach (get_terms(['taxonomy' => 'organizational_position', 'hide_empty' => false, 'parent' => 0]) as $t) {
            $departments[] = ['id' => $t->term_id, 'name' => $t->name];
        }

        wp_send_json_success([
            'tickets' => $items,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'departments' => $departments,
            'total_pages' => $query->max_num_pages,
            'current_page' => $paged,
            'is_manager' => $is_manager,
            'is_team_member' => $is_team_member,
        ]);
    }

    public function ajax_get_ticket() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
        }
        $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
        $current_user_id = get_current_user_id();

        if (!function_exists('webino_can_user_view_ticket') || !webino_can_user_view_ticket($ticket_id, $current_user_id)) {
            wp_send_json_error(['message' => 'شما دسترسی لازم برای مشاهده این تیکت را ندارید.']);
        }

        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== 'ticket') {
            wp_send_json_error(['message' => 'تیکت یافت نشد.']);
        }

        $status_terms = get_the_terms($ticket_id, 'ticket_status');
        $department_terms = get_the_terms($ticket_id, 'organizational_position');
        $priority_terms = get_the_terms($ticket_id, 'ticket_priority');
        $assigned_to_id = get_post_meta($ticket_id, '_assigned_to', true);

        $comments = get_comments(['post_id' => $ticket_id, 'status' => 'approve', 'order' => 'ASC']);
        $replies = [];
        foreach ($comments as $c) {
            $replies[] = [
                'id' => $c->comment_ID,
                'author' => $c->comment_author,
                'content' => $c->comment_content,
                'date' => $c->comment_date,
            ];
        }

        $departments = [];
        foreach (get_terms(['taxonomy' => 'organizational_position', 'hide_empty' => false, 'parent' => 0]) as $t) {
            $departments[] = ['id' => $t->term_id, 'name' => $t->name];
        }
        $statuses = [];
        foreach (get_terms(['taxonomy' => 'ticket_status', 'hide_empty' => false]) as $t) {
            $statuses[] = ['slug' => $t->slug, 'name' => $t->name];
        }
        $priorities = [];
        foreach (get_terms(['taxonomy' => 'ticket_priority', 'hide_empty' => false]) as $t) {
            $priorities[] = ['term_id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name];
        }

        wp_send_json_success([
            'ticket' => [
                'id' => $ticket->ID,
                'title' => $ticket->post_title,
                'content' => $ticket->post_content,
                'author_id' => (int) $ticket->post_author,
                'author_name' => get_the_author_meta('display_name', $ticket->post_author),
                'date' => get_the_date('Y/m/d H:i', $ticket),
                'status_slug' => !empty($status_terms) ? $status_terms[0]->slug : 'open',
                'status_name' => !empty($status_terms) ? $status_terms[0]->name : 'نامشخص',
                'department_name' => !empty($department_terms) ? $department_terms[0]->name : '---',
                'department_id' => !empty($department_terms) ? $department_terms[0]->term_id : 0,
                'priority_name' => !empty($priority_terms) ? $priority_terms[0]->name : '---',
                'priority_id' => !empty($priority_terms) ? $priority_terms[0]->term_id : 0,
                'assigned_to_id' => (int) $assigned_to_id,
                'assigned_to_name' => $assigned_to_id ? get_the_author_meta('display_name', $assigned_to_id) : '---',
                'is_closed' => (!empty($status_terms) && $status_terms[0]->slug === 'closed'),
                'replies' => $replies,
            ],
            'departments' => $departments,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'can_manage' => current_user_can('manage_options') || current_user_can('edit_others_posts'),
        ]);
    }

    private function notify_all_admins($title, $args) {
        $admins = get_users(['role__in' => ['administrator', 'system_manager'], 'fields' => 'ID']);
        foreach ($admins as $admin_id) {
            $notification_args = array_merge($args, ['user_id' => $admin_id]);
            WebinoCRM_Logger::add($title, $notification_args);
        }
    }

    public function ajax_new_ticket() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(['message' => 'برای ارسال تیکت باید وارد شوید.']);
        }
    
        $title = sanitize_text_field($_POST['ticket_title']);
        $content = wp_kses_post($_POST['ticket_content']);
        $department_id = intval($_POST['department']);
        $priority_id = intval($_POST['ticket_priority']);
        
        if (empty($title) || empty($content) || empty($department_id) || empty($priority_id)) {
            wp_send_json_error(['message' => 'عنوان، دپارتمان، اولویت و متن تیکت الزامی هستند.']);
        }
    
        $ticket_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'ticket',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
        
        if (!is_wp_error($ticket_id)) {
            wp_set_object_terms($ticket_id, 'open', 'ticket_status');
            wp_set_object_terms($ticket_id, $department_id, 'organizational_position');
            wp_set_object_terms($ticket_id, $priority_id, 'ticket_priority');
    
            if (!empty($_FILES['ticket_attachments'])) {
                $attachment_ids = [];
                $files = $_FILES['ticket_attachments'];
                $allowed_mime_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/zip', 'application/x-rar-compressed'];
                
                foreach ($files['name'] as $key => $value) {
                    if ($files['name'][$key]) {
                        if ($files['size'][$key] > 5 * 1024 * 1024) { 
                            continue;
                        }
                        if (!in_array($files['type'][$key], $allowed_mime_types)) {
                            continue;
                        }

                        $_FILES = ["ticket_attachment_single" => [
                            'name' => $files['name'][$key],
                            'type' => $files['type'][$key],
                            'tmp_name' => $files['tmp_name'][$key],
                            'error' => $files['error'][$key],
                            'size' => $files['size'][$key]
                        ]];
                        $attachment_id = media_handle_upload("ticket_attachment_single", $ticket_id);
                        if (!is_wp_error($attachment_id)) {
                            $attachment_ids[] = $attachment_id;
                        }
                    }
                }
                if (!empty($attachment_ids)) {
                    update_post_meta($ticket_id, '_ticket_attachments', $attachment_ids);
                }
            }
    
            $users_in_dept = get_users(['tax_query' => [['taxonomy' => 'organizational_position', 'field' => 'term_id', 'terms' => $department_id]]]);
            foreach($users_in_dept as $user) {
                WebinoCRM_Logger::add('تیکت جدید در دپارتمان شما', ['content' => sprintf("تیکت جدیدی با موضوع '%s' ثبت شد.", $title), 'type' => 'notification', 'object_id' => $ticket_id, 'user_id' => $user->ID]);
            }

            wp_send_json_success(['message' => 'تیکت شما با موفقیت ثبت شد.', 'reload' => true]);
        } else {
            wp_send_json_error(['message' => 'خطا در ثبت تیکت.']);
        }
    }
    
    public function ajax_ticket_reply() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(['message' => 'برای پاسخ به تیکت باید وارد شوید.']);
        }
    
        $ticket_id = intval($_POST['ticket_id']);
        $comment_content = wp_kses_post($_POST['comment']);
        $ticket = get_post($ticket_id);
        $current_user = wp_get_current_user();
    
        if ( !webino_can_user_view_ticket($ticket_id, $current_user->ID) || empty($comment_content) ) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز یا متن پاسخ خالی است.']);
        }
    
        $comment_id = wp_insert_comment([
            'comment_post_ID' => $ticket_id,
            'comment_author' => $current_user->display_name,
            'comment_author_email' => $current_user->user_email,
            'comment_content' => $comment_content,
            'user_id' => $current_user->ID,
            'comment_approved' => 1
        ]);
    
        if (is_wp_error($comment_id)) {
            wp_send_json_error(['message' => 'خطا در ثبت پاسخ.']);
        }
    
        if (!empty($_FILES['reply_attachments'])) {
            $attachment_ids = [];
            $files = $_FILES['reply_attachments'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/zip', 'application/x-rar-compressed'];

            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {
                    if ($files['size'][$key] > 5 * 1024 * 1024 || !in_array($files['type'][$key], $allowed_mime_types)) {
                        continue;
                    }
                    $_FILES = ["reply_attachment_single" => ['name' => $files['name'][$key],'type' => $files['type'][$key],'tmp_name' => $files['tmp_name'][$key],'error' => $files['error'][$key],'size' => $files['size'][$key]]];
                    $attachment_id = media_handle_upload("reply_attachment_single", 0);
                    if (!is_wp_error($attachment_id)) {
                        $attachment_ids[] = $attachment_id;
                    }
                }
            }
            if (!empty($attachment_ids)) {
                add_comment_meta($comment_id, '_reply_attachments', $attachment_ids);
            }
        }
    
        $is_staff_reply = current_user_can('edit_others_posts');
    
        if ( $is_staff_reply ) {
            if (isset($_POST['ticket_status'])) wp_set_object_terms($ticket_id, sanitize_key($_POST['ticket_status']), 'ticket_status');
            if (isset($_POST['department'])) wp_set_object_terms($ticket_id, intval($_POST['department']), 'organizational_position');
            if (isset($_POST['assigned_to'])) update_post_meta($ticket_id, '_assigned_to', intval($_POST['assigned_to']));
            if (isset($_POST['ticket_priority'])) wp_set_object_terms($ticket_id, intval($_POST['ticket_priority']), 'ticket_priority');

            WebinoCRM_Logger::add(__('پاسخ به تیکت شما', 'webinocrm'), ['content' => sprintf(__("پشتیبانی به تیکت شما با موضوع '%s' پاسخ داد.", 'webinocrm'), $ticket->post_title), 'type' => 'notification', 'user_id' => $ticket->post_author, 'object_id' => $ticket_id]);
        } else {
            wp_set_object_terms( $ticket_id, 'in-progress', 'ticket_status' );
            $assigned_to_id = get_post_meta($ticket_id, '_assigned_to', true);
            if ($assigned_to_id) {
                WebinoCRM_Logger::add(__('پاسخ مشتری به تیکت', 'webinocrm'), ['content' => sprintf(__("مشتری به تیکت '%s' پاسخ داد.", 'webinocrm'), $ticket->post_title), 'type' => 'notification', 'object_id' => $ticket_id, 'user_id' => $assigned_to_id]);
            } else {
                $this->notify_all_admins(__('پاسخ مشتری به تیکت', 'webinocrm'), ['content' => sprintf(__("مشتری به تیکت '%s' پاسخ داد.", 'webinocrm'), $ticket->post_title), 'type' => 'notification', 'object_id' => $ticket_id]);
            }
        }
        
        wp_send_json_success(['message' => 'پاسخ شما با موفقیت ثبت شد.', 'reload' => true]);
    }

    public function ajax_convert_ticket_to_task() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }
    
        $ticket_id = intval($_POST['ticket_id']);
        $ticket = get_post($ticket_id);
    
        if (!$ticket || $ticket->post_type !== 'ticket') {
            wp_send_json_error(['message' => 'تیکت یافت نشد.']);
        }
    
        $existing_task = get_posts(['post_type' => 'task', 'meta_key' => '_created_from_ticket', 'meta_value' => $ticket_id, 'posts_per_page' => 1]);
        if (!empty($existing_task)) {
            wp_send_json_error(['message' => 'یک وظیفه از قبل برای این تیکت ایجاد شده است.']);
        }
    
        $task_id = wp_insert_post([
            'post_title' => '[تیکت] ' . $ticket->post_title,
            'post_content' => 'این وظیفه از تیکت شماره ' . $ticket_id . " ایجاد شده است.\n\n" . $ticket->post_content,
            'post_type' => 'task',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
    
        if (is_wp_error($task_id)) {
            wp_send_json_error(['message' => 'خطا در ایجاد وظیفه.']);
        }
    
        update_post_meta($task_id, '_created_from_ticket', $ticket_id);
        wp_set_object_terms($task_id, 'to-do', 'task_status');
    
        wp_insert_comment([
            'comment_post_ID' => $ticket_id,
            'comment_content' => 'این تیکت به وظیفه شماره ' . $task_id . ' تبدیل شد.',
            'user_id' => get_current_user_id(),
            'comment_author' => 'سیستم',
            'comment_approved' => 1,
        ]);
    
        wp_send_json_success(['message' => 'تیکت با موفقیت به وظیفه تبدیل شد.', 'reload' => true]);
    }

    public function ajax_get_canned_response() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('edit_posts') || !isset($_POST['response_id'])) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }
    
        $response_id = intval($_POST['response_id']);
        $response = get_post($response_id);
    
        if ($response && $response->post_type === 'canned_response') {
            wp_send_json_success(['content' => $response->post_content]);
        } else {
            wp_send_json_error(['message' => 'پاسخ یافت نشد.']);
        }
    }

    public function ajax_submit_ticket_rating() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'ticket_csat', 20, 3600 ) ) {
            wp_send_json_error( [ 'message' => __( 'تعداد درخواست‌ها بیش از حد مجاز است.', 'webinocrm' ) ] );
        }
        $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        
        if (empty($ticket_id) || empty($rating) || $rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => 'اطلاعات نامعتبر است.']);
        }

        $ticket = get_post($ticket_id);
        if (!$ticket) {
            wp_send_json_error(['message' => 'تیکت یافت نشد.']);
        }

        // Security check for non-logged in users via token
        if (!is_user_logged_in()) {
            $token = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
            $csat_token = get_post_meta($ticket_id, '_csat_token', true);
            if (empty($token) || $token !== $csat_token) {
                 wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
            }
        } else {
            // Security check for logged in users
            if ($ticket->post_author != get_current_user_id() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'شما اجازه امتیاز دادن به این تیکت را ندارید.']);
            }
        }
        
        // Prevent duplicate rating
        if (get_post_meta($ticket_id, '_ticket_rating', true)) {
            wp_send_json_error(['message' => 'شما قبلاً به این تیکت امتیاز داده‌اید.']);
        }

        update_post_meta($ticket_id, '_ticket_rating', $rating);

        wp_send_json_success(['message' => 'امتیاز شما با موفقیت ثبت شد. از بازخورد شما سپاسگزاریم!']);
    }
}