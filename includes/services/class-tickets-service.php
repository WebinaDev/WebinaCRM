<?php
/**
 * Tickets Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Tickets_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!is_user_logged_in()) {
            return WebinoCRM_Service_Base::error( ['message' => 'لطفاً وارد شوید.'] );
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
        return WebinoCRM_Service_Base::success( [
            'tickets' => $items,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'departments' => $departments,
            'total_pages' => $query->max_num_pages,
            'current_page' => $paged,
            'is_manager' => $is_manager,
            'is_team_member' => $is_team_member,
        ] );
	}

		public static function get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!is_user_logged_in()) {
            return WebinoCRM_Service_Base::error( ['message' => 'لطفاً وارد شوید.'] );
        }
        $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
        $current_user_id = get_current_user_id();
        if (!function_exists('webino_can_user_view_ticket') || !webino_can_user_view_ticket($ticket_id, $current_user_id)) {
            return WebinoCRM_Service_Base::error( ['message' => 'شما دسترسی لازم برای مشاهده این تیکت را ندارید.'] );
        }
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== 'ticket') {
            return WebinoCRM_Service_Base::error( ['message' => 'تیکت یافت نشد.'] );
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
        return WebinoCRM_Service_Base::success( [
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
        ] );
	}

		public static function create( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! is_user_logged_in() ) {
            return WebinoCRM_Service_Base::error( ['message' => 'برای ارسال تیکت باید وارد شوید.'] );
        }
        $title = sanitize_text_field($_POST['ticket_title']);
        $content = wp_kses_post($_POST['ticket_content']);
        $department_id = intval($_POST['department']);
        $priority_id = intval($_POST['ticket_priority']);
        if (empty($title) || empty($content) || empty($department_id) || empty($priority_id)) {
            return WebinoCRM_Service_Base::error( ['message' => 'عنوان، دپارتمان، اولویت و متن تیکت الزامی هستند.'] );
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
            return WebinoCRM_Service_Base::success( ['message' => 'تیکت شما با موفقیت ثبت شد.', 'reload' => true] );
        } else {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ثبت تیکت.'] );
        }
	}

		public static function reply( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! is_user_logged_in() ) {
            return WebinoCRM_Service_Base::error( ['message' => 'برای پاسخ به تیکت باید وارد شوید.'] );
        }
        $ticket_id = intval($_POST['ticket_id']);
        $comment_content = wp_kses_post($_POST['comment']);
        $ticket = get_post($ticket_id);
        $current_user = wp_get_current_user();
        if ( !webino_can_user_view_ticket($ticket_id, $current_user->ID) || empty($comment_content) ) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز یا متن پاسخ خالی است.'] );
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
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ثبت پاسخ.'] );
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
                self::notify_all_admins(__('پاسخ مشتری به تیکت', 'webinocrm'), ['content' => sprintf(__("مشتری به تیکت '%s' پاسخ داد.", 'webinocrm'), $ticket->post_title), 'type' => 'notification', 'object_id' => $ticket_id]);
            }
        }
        return WebinoCRM_Service_Base::success( ['message' => 'پاسخ شما با موفقیت ثبت شد.', 'reload' => true] );
	}

		public static function convert_task( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $ticket_id = intval($_POST['ticket_id']);
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== 'ticket') {
            return WebinoCRM_Service_Base::error( ['message' => 'تیکت یافت نشد.'] );
        }
        $existing_task = get_posts(['post_type' => 'task', 'meta_key' => '_created_from_ticket', 'meta_value' => $ticket_id, 'posts_per_page' => 1]);
        if (!empty($existing_task)) {
            return WebinoCRM_Service_Base::error( ['message' => 'یک وظیفه از قبل برای این تیکت ایجاد شده است.'] );
        }
        $task_id = wp_insert_post([
            'post_title' => '[تیکت] ' . $ticket->post_title,
            'post_content' => 'این وظیفه از تیکت شماره ' . $ticket_id . " ایجاد شده است.\n\n" . $ticket->post_content,
            'post_type' => 'task',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
        if (is_wp_error($task_id)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ایجاد وظیفه.'] );
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
        return WebinoCRM_Service_Base::success( ['message' => 'تیکت با موفقیت به وظیفه تبدیل شد.', 'reload' => true] );
	}


	/**
	 * Canned responses for ticket composer.
	 *
	 * @param array $params Params.
	 * @return array
	 */
	public static function canned_responses( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! is_user_logged_in() ) {
			return WebinoCRM_Service_Base::error( [ 'message' => 'لطفاً وارد شوید.' ] );
		}
		$posts = get_posts( [
			'post_type'      => 'canned_response',
			'numberposts'    => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		$items = [];
		foreach ( $posts as $p ) {
			$items[] = [
				'id'      => $p->ID,
				'title'   => $p->post_title,
				'content' => $p->post_content,
			];
		}
		return WebinoCRM_Service_Base::success( [ 'responses' => $items ] );
	}

	private static function notify_all_admins($title, $args) {
$admins = get_users(['role__in' => ['administrator', 'system_manager'], 'fields' => 'ID']);
        foreach ($admins as $admin_id) {
            $notification_args = array_merge($args, ['user_id' => $admin_id]);
            WebinoCRM_Logger::add($title, $notification_args);
        }
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_tickets' => array( __CLASS__, 'list' ),
				'webinocrm_get_ticket' => array( __CLASS__, 'get' ),
				'webino_new_ticket' => array( __CLASS__, 'create' ),
				'ticket_reply' => array( __CLASS__, 'reply' ),
				'webino_convert_ticket_to_task' => array( __CLASS__, 'convert_task' ),

			)
		);
	}
}
