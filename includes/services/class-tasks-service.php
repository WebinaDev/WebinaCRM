<?php
/**
 * Tasks Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Tasks_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!function_exists('webinocrm_user_can_manage_tasks') || !webinocrm_user_can_manage_tasks()) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        $project_filter = isset($_POST['project_filter']) ? intval($_POST['project_filter']) : 0;
        $staff_filter = isset($_POST['staff_filter']) ? intval($_POST['staff_filter']) : 0;
        $priority_filter = isset($_POST['priority_filter']) ? sanitize_key($_POST['priority_filter']) : '';
        $label_filter = isset($_POST['label_filter']) ? sanitize_key($_POST['label_filter']) : '';
        $status_filter = isset($_POST['status']) ? webinocrm_ensure_task_status_slug($_POST['status']) : '';
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        $per_page = isset($_POST['per_page']) ? min(100, max(1, intval($_POST['per_page']))) : 50;
        $args = [
            'post_type' => 'task',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        ];
        if (!empty($search)) {
            $args['s'] = $search;
        }
        $meta_query = [];
        if ($project_filter > 0) {
            $meta_query[] = ['key' => '_project_id', 'value' => $project_filter, 'compare' => '='];
        }
        if ($staff_filter > 0) {
            $meta_query[] = ['key' => '_assigned_to', 'value' => $staff_filter, 'compare' => '='];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        $tax_query = [];
        if (!empty($priority_filter)) {
            $tax_query[] = ['taxonomy' => 'task_priority', 'field' => 'slug', 'terms' => $priority_filter];
        }
        if (!empty($label_filter)) {
            $tax_query[] = ['taxonomy' => 'task_label', 'field' => 'slug', 'terms' => $label_filter];
        }
        if (!empty($status_filter)) {
            $tax_query[] = ['taxonomy' => 'task_status', 'field' => 'slug', 'terms' => $status_filter];
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        $current_user_id = get_current_user_id();
        $user_roles = (array) wp_get_current_user()->roles;
        $is_manager = current_user_can('manage_options')
            || in_array('administrator', $user_roles, true)
            || in_array('system_manager', $user_roles, true);
        if (!$is_manager) {
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = [];
            }
            $args['meta_query'][] = ['key' => '_assigned_to', 'value' => $current_user_id, 'compare' => '='];
            $args['meta_query']['relation'] = 'AND';
        }
        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $task_id = $post->ID;
            $due_date = get_post_meta($task_id, '_due_date', true);
            $project_id = (int) get_post_meta($task_id, '_project_id', true);
            $assigned_to = (int) get_post_meta($task_id, '_assigned_to', true);
            $status_terms = get_the_terms($task_id, 'task_status');
            $priority_terms = get_the_terms($task_id, 'task_priority');
            $label_terms = get_the_terms($task_id, 'task_label');
            $status_slug = $status_name = '';
            if ($status_terms && !is_wp_error($status_terms)) {
                $status_slug = $status_terms[0]->slug;
                $status_name = $status_terms[0]->name;
            }
            $priority_slug = $priority_name = '';
            if ($priority_terms && !is_wp_error($priority_terms)) {
                $priority_slug = $priority_terms[0]->slug;
                $priority_name = $priority_terms[0]->name;
            }
            $labels = [];
            if ($label_terms && !is_wp_error($label_terms)) {
                foreach ($label_terms as $t) {
                    $labels[] = ['slug' => $t->slug, 'name' => $t->name];
                }
            }
            $assigned_name = $assigned_to ? get_the_author_meta('display_name', $assigned_to) : '';
            $project_title = $project_id ? get_the_title($project_id) : '';
            $items[] = [
                'id' => $task_id,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'status_slug' => $status_slug,
                'status_name' => $status_name,
                'priority_slug' => $priority_slug,
                'priority_name' => $priority_name,
                'labels' => $labels,
                'due_date' => $due_date,
                'project_id' => $project_id,
                'project_title' => $project_title,
                'assigned_to' => $assigned_to,
                'assigned_name' => $assigned_name,
                'post_date' => $post->post_date,
            ];
        }
        $all_statuses = get_terms(['taxonomy' => 'task_status', 'hide_empty' => false, 'orderby' => 'term_order', 'order' => 'ASC']);
        $statuses_list = [];
        if ($all_statuses && !is_wp_error($all_statuses)) {
            foreach ($all_statuses as $t) {
                $statuses_list[] = ['id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name, 'count' => $t->count];
            }
        }
        $priorities_list = [];
        $priorities = get_terms(['taxonomy' => 'task_priority', 'hide_empty' => false]);
        if ($priorities && !is_wp_error($priorities)) {
            foreach ($priorities as $t) {
                $priorities_list[] = ['id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name];
            }
        }
        $labels_list = [];
        $labels_tax = get_terms(['taxonomy' => 'task_label', 'hide_empty' => false]);
        if ($labels_tax && !is_wp_error($labels_tax)) {
            foreach ($labels_tax as $t) {
                $labels_list[] = ['id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name];
            }
        }
        $projects_list = [];
        $staff_list = [];
        if ($is_manager) {
            $projects_raw = get_posts(['post_type' => 'project', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
            $projects_list = array_map(function ($p) {
                return ['id' => $p->ID, 'title' => $p->post_title];
            }, $projects_raw);
            $staff_raw = get_users(['role__in' => ['system_manager', 'team_member', 'administrator'], 'orderby' => 'display_name']);
            $staff_list = array_map(function ($u) {
                return ['id' => $u->ID, 'display_name' => $u->display_name];
            }, $staff_raw);
        } elseif (webinocrm_current_user_has_role(['team_member'])) {
            $accessible_ids = webinocrm_get_accessible_project_ids_for_user($current_user_id);
            if (!empty($accessible_ids)) {
                $projects_raw = get_posts([
                    'post_type' => 'project',
                    'post__in' => $accessible_ids,
                    'numberposts' => -1,
                    'post_status' => 'publish',
                    'orderby' => 'title',
                    'order' => 'ASC',
                ]);
                $projects_list = array_map(function ($p) {
                    return ['id' => $p->ID, 'title' => $p->post_title];
                }, $projects_raw);
            }
            $me = get_userdata($current_user_id);
            if ($me) {
                $staff_list = [['id' => $me->ID, 'display_name' => $me->display_name]];
            }
        }
        $filtered_total = (int) $query->found_posts;
        $completed_in_set = 0;
        foreach ($items as $row) {
            if (($row['status_slug'] ?? '') === 'done') {
                $completed_in_set++;
            }
        }
        $active_in_set = $filtered_total - $completed_in_set;
        $project_ids_in_set = array_unique(array_filter(array_column($items, 'project_id')));
        return WebinoCRM_Service_Base::success( [
            'tasks' => $items,
            'statuses' => $statuses_list,
            'priorities' => $priorities_list,
            'labels' => $labels_list,
            'projects' => $projects_list,
            'staff' => $staff_list,
            'can_delete_tasks' => function_exists('webinocrm_user_can_delete_tasks') && webinocrm_user_can_delete_tasks(),
            'total_pages' => $query->max_num_pages,
            'total' => $filtered_total,
            'stats' => [
                'total_tasks' => $filtered_total,
                'active_tasks' => $active_in_set,
                'completed_tasks' => $completed_in_set,
                'total_projects' => count($project_ids_in_set),
            ],
        ] );
	}

		public static function create( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks') || !isset($_POST['title'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز یا اطلاعات ناقص.', 'webinocrm' )] );
        }
        $title = sanitize_text_field($_POST['title']);
        $status_slug = sanitize_key($_POST['status_slug']);
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : 0;
        if (empty($project_id) || empty($assigned_to)) {
             return WebinoCRM_Service_Base::error( ['message' => __( 'برای افزودن سریع، لطفاً ابتدا برد را بر اساس پروژه و کارمند فیلتر کنید.', 'webinocrm' )] );
        }
        $task_id = wp_insert_post(['post_title' => $title, 'post_type' => 'task', 'post_status' => 'publish', 'post_author' => get_current_user_id()]);
        if (is_wp_error($task_id)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ایجاد تسک.', 'webinocrm' )] );
        }
        update_post_meta($task_id, '_project_id', $project_id);
        update_post_meta($task_id, '_assigned_to', $assigned_to);
        wp_set_post_terms($task_id, $status_slug, 'task_status');
        $medium_priority = get_term_by('slug', 'medium', 'task_priority');
        if ($medium_priority) {
            wp_set_post_terms($task_id, $medium_priority->term_id, 'task_priority');
        }
		$default_cat = get_term_by('slug', 'project-based', 'task_category');
		if ($default_cat) {
			wp_set_object_terms($task_id, $default_cat->term_id, 'task_category');
		}
        self::_log_task_activity($task_id, 'وظیفه را به صورت سریع ایجاد کرد.');
        self::send_task_assignment_email($assigned_to, $task_id);
        $project_title = get_the_title($project_id);
        WebinoCRM_Logger::add('تسک جدید به شما محول شد', ['content' => "تسک '{$title}' در پروژه '{$project_title}' به شما تخصیص داده شد.", 'type' => 'notification', 'user_id' => $assigned_to, 'object_id' => $task_id]);
        $task_html = function_exists('webino_render_task_card') ? webino_render_task_card(get_post($task_id)) : '';
        return WebinoCRM_Service_Base::success( ['message' => __( 'تسک سریع با موفقیت اضافه شد.', 'webinocrm' ), 'task_html' => $task_html] );
	}

		public static function update_status( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        // Allow administrators and managers
        if (!current_user_can('edit_tasks') && !current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        // Check for both 'status' (from new drag&drop) and 'new_status_slug' (from old system)
        $new_status_slug = isset($_POST['status']) ? sanitize_key($_POST['status']) : (isset($_POST['new_status_slug']) ? sanitize_key($_POST['new_status_slug']) : '');
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        if (!$task_id || !$new_status_slug) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'اطلاعات ناقص.', 'webinocrm' )] );
        }
        $rules = WebinoCRM_Settings_Handler::get_setting('workflow_rules', []);
        $user = wp_get_current_user();
        if (isset($rules[$new_status_slug]) && !empty($rules[$new_status_slug])) {
            if (empty(array_intersect($rules[$new_status_slug], $user->roles))) {
                return WebinoCRM_Service_Base::error( ['message' => __( 'شما اجازه انتقال وظیفه به این وضعیت را ندارید.', 'webinocrm' )] );
            }
        }
        $old_status_name = WebinoCRM_Term_Helper::get_first_term_name( $task_id, 'task_status', 'نامشخص' );
        $term = get_term_by('slug', $new_status_slug, 'task_status');
        if ($term) {
            wp_set_post_terms($task_id, $term->term_id, 'task_status');
            self::_log_task_activity($task_id, sprintf('وضعیت وظیفه را از "%s" به "%s" تغییر داد.', $old_status_name, $term->name));
            self::execute_automations('status_changed', $task_id, $new_status_slug);
            return WebinoCRM_Service_Base::success( ['message' => __( 'وضعیت تسک به‌روزرسانی شد.', 'webinocrm' )] );
        } else {
             return WebinoCRM_Service_Base::error( ['message' => __( 'وضعیت نامعتبر است.', 'webinocrm' )] );
        }
	}

		public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('delete_tasks') || !isset($_POST['task_id'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $task_id = intval($_POST['task_id']);
        $task = get_post($task_id);
        if (!$task || (!current_user_can('manage_options') && $task->post_author != get_current_user_id())) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شما اجازه حذف این تسک را ندارید.', 'webinocrm' )] );
        }
        $result = wp_delete_post($task_id, true);
        if ($result) {
            WebinoCRM_Logger::add('تسک حذف شد', ['content' => "تسک '{$task->post_title}' توسط " . wp_get_current_user()->display_name . " حذف شد.", 'type' => 'log', 'object_id' => $task_id]);
            return WebinoCRM_Service_Base::success( ['message' => __( 'تسک با موفقیت حذف شد.', 'webinocrm' )] );
        } else {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در حذف تسک.', 'webinocrm' )] );
        }
	}


	private static function _log_task_activity($task_id, $activity_text) {
$activity_log = get_post_meta($task_id, '_task_activity_log', true);
        if (!is_array($activity_log)) {
            $activity_log = [];
        }
        $current_user = wp_get_current_user();
        $new_log_entry = [
            'user_id' => $current_user->ID,
            'user_name' => $current_user->display_name,
            'text' => $activity_text,
            'time' => current_time('mysql'),
        ];
        array_unshift($activity_log, $new_log_entry);
        update_post_meta($task_id, '_task_activity_log', $activity_log);
	}

	private static function execute_automations($trigger, $task_id, $trigger_value = null) {
$settings = WebinoCRM_Settings_Handler::get_setting('automations', []);
        $automations = $settings['automations'] ?? [];
        foreach ($automations as $automation) {
            $rule_trigger = $automation['trigger'] ?? '';
            $rule_action = $automation['action'] ?? '';
            $rule_value = $automation['value'] ?? '';
            $trigger_condition_met = ($rule_trigger === $trigger);
            if ($trigger_condition_met) {
                switch ($rule_action) {
                    case 'change_status':
                        $term = get_term_by('slug', $rule_value, 'task_status');
                        if ($term) {
                            wp_set_post_terms($task_id, $term->term_id, 'task_status');
                            self::_log_task_activity($task_id, sprintf('وضعیت به صورت خودکار به "%s" تغییر کرد.', $term->name));
                        }
                        break;
                    case 'assign_user':
                        $user_id = intval($rule_value);
                        if (get_user_by('ID', $user_id)) {
                            update_post_meta($task_id, '_assigned_to', $user_id);
                            self::_log_task_activity($task_id, sprintf('وظیفه به صورت خودکار به "%s" تخصیص داده شد.', get_the_author_meta('display_name', $user_id)));
                        }
                        break;
                    case 'add_comment':
                        wp_insert_comment(['comment_post_ID' => $task_id, 'comment_content' => $rule_value, 'user_id' => 0, 'comment_author' => 'سیستم اتوماسیون', 'comment_author_email' => 'system@webino.com', 'comment_approved' => 1]);
                        self::_log_task_activity($task_id, 'یک کامنت خودکار توسط سیستم ثبت شد.');
                        break;
                }
            }
        }
	}

	private static function send_task_assignment_email($user_id, $task_id) {
$user = get_userdata($user_id);
        $task = get_post($task_id);
        if (!$user || !$task) return;
        $project_title = get_the_title(get_post_meta($task_id, '_project_id', true));
        $dashboard_url = function_exists('webino_get_dashboard_url') ? webino_get_dashboard_url() : home_url();
        $to = $user->user_email;
        $subject = 'یک تسک جدید به شما تخصیص داده شد: ' . $task->post_title;
        $body = '<p>سلام ' . esc_html($user->display_name) . '،</p>';
        $body .= '<p>یک تسک جدید در پروژه <strong>' . esc_html($project_title) . '</strong> به شما محول شده است:</p>';
        $body .= '<ul><li><strong>عنوان تسک:</strong> ' . esc_html($task->post_title) . '</li></ul>';
        $body .= '<p>برای مشاهده جزئیات به داشبورد مراجعه کنید:</p>';
        $body .= '<p><a href="' . esc_url($dashboard_url) . '">رفتن به داشبورد</a></p>';
        wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
	}

	public static function create_extended( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if (!current_user_can('edit_tasks') || !isset($_POST['title'])) {
			return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز یا اطلاعات ناقص.', 'webinocrm' )] );
		}
		$settings = WebinoCRM_Settings_Handler::get_all_settings();
		$notification_prefs = $settings['notifications']['new_task'] ?? [];
		$title = sanitize_text_field($_POST['title']);
		$content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
		$task_category_id = isset($_POST['task_category']) ? intval($_POST['task_category']) : 0;
		$due_date = webino_jalali_to_gregorian(sanitize_text_field($_POST['due_date']));
		$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
		$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
		$story_points = isset($_POST['story_points']) ? sanitize_text_field($_POST['story_points']) : '';
		$task_labels = isset($_POST['task_labels']) ? sanitize_text_field($_POST['task_labels']) : '';
		$show_to_customer = isset($_POST['show_to_customer']) ? 1 : 0;
		$assigned_to_user = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : 0;
		$assigned_to_role = isset($_POST['assigned_role']) ? intval($_POST['assigned_role']) : 0;
		if (empty($project_id) || (empty($assigned_to_user) && empty($assigned_to_role))) {
			return WebinoCRM_Service_Base::error( ['message' => __( 'لطفاً پروژه و مسئول تسک را انتخاب کنید.', 'webinocrm' )] );
		}
		$task_id = wp_insert_post([
			'post_title' => $title, 'post_content' => $content, 'post_type' => 'task',
			'post_status' => 'publish', 'post_author' => get_current_user_id(), 'post_parent' => $parent_id
		]);
		if (is_wp_error($task_id)) {
			return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ایجاد تسک.', 'webinocrm' )] );
		}
		update_post_meta($task_id, '_project_id', $project_id);
		update_post_meta($task_id, '_show_to_customer', $show_to_customer);
		if (!empty($due_date)) update_post_meta($task_id, '_due_date', $due_date);
		if (!empty($story_points)) update_post_meta($task_id, '_story_points', $story_points);
		wp_set_post_terms($task_id, $task_category_id, 'task_category');
        wp_set_post_terms($task_id, webino_get_default_task_status_slug(), 'task_status');
		if (!empty($task_labels)) wp_set_post_terms($task_id, array_map('trim', explode(',', $task_labels)), 'task_label');
		$assigned_user_ids = [];
		if ($assigned_to_user > 0) {
			update_post_meta($task_id, '_assigned_to', $assigned_to_user);
			$assigned_user_ids[] = $assigned_to_user;
		} elseif ($assigned_to_role > 0) {
			update_post_meta($task_id, '_assigned_role', $assigned_to_role);
			$users_with_role = get_users(['tax_query' => [['taxonomy' => 'organizational_position', 'field' => 'term_id', 'terms' => $assigned_to_role]], 'fields' => 'ID']);
			if (!empty($users_with_role)) {
				update_post_meta($task_id, '_assigned_to_multiple', $users_with_role);
				update_post_meta($task_id, '_assigned_to', $users_with_role[0]);
				$assigned_user_ids = $users_with_role;
			}
		}
		self::_log_task_activity($task_id, 'وظیفه را ایجاد کرد.');
		if (!empty($_FILES['task_attachments'])) {
            $attachment_ids = [];
            $files = $_FILES['task_attachments'];
            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {
                    $_FILES = ["task_attachment_single" => ['name' => $files['name'][$key], 'type' => $files['type'][$key], 'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key], 'size' => $files['size'][$key]]];
                    $attachment_id = media_handle_upload("task_attachment_single", $task_id);
                    if (!is_wp_error($attachment_id)) $attachment_ids[] = $attachment_id;
                }
            }
            if(!empty($attachment_ids)) update_post_meta($task_id, '_task_attachments', $attachment_ids);
		}
		$project_title = get_the_title($project_id);
		$notification_message_plain = "تسک جدید '{$title}' در پروژه '{$project_title}' به شما تخصیص داده شد.";
		$notification_message_html = "تسک جدید <b>'{$title}'</b> در پروژه <b>'{$project_title}'</b> به شما تخصیص داده شد.";
		foreach ($assigned_user_ids as $user_id_to_notify) {
			$user = get_userdata($user_id_to_notify);
			if (!$user) continue;
			WebinoCRM_Logger::add('تسک جدید به شما محول شد', ['content' => $notification_message_plain, 'type' => 'notification', 'user_id' => $user_id_to_notify, 'object_id' => $task_id]);
			if (!empty($notification_prefs['email'])) self::send_task_assignment_email($user_id_to_notify, $task_id);
			if (!empty($notification_prefs['sms'])) {
				$sms_handler = webino_get_sms_handler($settings);
				$phone = get_user_meta($user_id_to_notify, 'webino_mobile_phone', true);
				if ($sms_handler && !empty($phone)) $sms_handler->send_sms($phone, $notification_message_plain);
			}
			if (!empty($notification_prefs['telegram'])) {
				$bot_token = $settings['telegram_bot_token'] ?? '';
				$chat_id = $settings['telegram_chat_id'] ?? '';
				if (!empty($bot_token) && !empty($chat_id)) {
					$telegram_handler = new WebinoCRM_Telegram_Handler($bot_token, $chat_id);
					$telegram_handler->send_message("کاربر گرامی {$user->display_name},\n" . $notification_message_html);
				}
			}
		}
		return WebinoCRM_Service_Base::success( ['message' => __( 'تسک با موفقیت اضافه شد و اطلاع‌رسانی‌ها ارسال گردید.', 'webinocrm' ), 'reload' => true] );
	}

	public static function calendar( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        $start = isset( $_POST['start'] ) ? sanitize_text_field( $_POST['start'] ) : ( isset( $params['start'] ) ? sanitize_text_field( (string) $params['start'] ) : '' );
        $end = isset( $_POST['end'] ) ? sanitize_text_field( $_POST['end'] ) : ( isset( $params['end'] ) ? sanitize_text_field( (string) $params['end'] ) : '' );
        $args = [
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => []
        ];
        if ($start && $end) {
            $args['meta_query'][] = [
                'key' => '_due_date',
                'value' => [$start, $end],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ];
        }
        $tasks = get_posts($args);
        $events = [];
        foreach ($tasks as $task) {
            $due_date = get_post_meta($task->ID, '_due_date', true);
            if ($due_date) {
                $status_terms = get_the_terms($task->ID, 'task_status');
                $color = '#845adf';
                if ($status_terms && !is_wp_error($status_terms)) {
                    $slug = $status_terms[0]->slug;
                    if ($slug === 'done') $color = '#28a745';
                    elseif ($slug === 'in-progress') $color = '#4ebedb';
                    elseif ($slug === 'review') $color = '#ffc107';
                }
                $events[] = [
                    'id' => $task->ID,
                    'title' => $task->post_title,
                    'start' => $due_date,
                    'color' => $color,
                    'allDay' => true
                ];
            }
        }
        return WebinoCRM_Service_Base::success( ['events' => $events] );
	}

	public static function gantt( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        $tasks = get_posts([
            'post_type' => 'task',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        $gantt_tasks = [];
        foreach ($tasks as $task) {
            $start_date = get_post_meta($task->ID, '_start_date', true);
            $due_date = get_post_meta($task->ID, '_due_date', true);
            if (!$start_date) $start_date = $task->post_date;
            if (!$due_date) $due_date = date('Y-m-d', strtotime($start_date . ' +7 days'));
            $status_terms = get_the_terms($task->ID, 'task_status');
            $progress = 0;
            if ($status_terms && !is_wp_error($status_terms)) {
                $slug = $status_terms[0]->slug;
                if ($slug === 'done') $progress = 1;
                elseif ($slug === 'in-progress') $progress = 0.5;
                elseif ($slug === 'review') $progress = 0.8;
            }
            $gantt_tasks[] = [
                'id' => $task->ID,
                'text' => $task->post_title,
                'start_date' => date('d-m-Y', strtotime($start_date)),
                'duration' => self::calculate_duration($start_date, $due_date),
                'progress' => $progress,
                'open' => true
            ];
        }
        return WebinoCRM_Service_Base::success( ['tasks' => $gantt_tasks] );
	}

	public static function views( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        $project_filter = isset($_POST['project_filter']) ? intval($_POST['project_filter']) : 0;
        $args = ['post_type' => 'task', 'posts_per_page' => -1];
        if($project_filter > 0){
            $args['meta_query'] = [
                [
                    'key' => '_project_id',
                    'value' => $project_filter
                ]
            ];
        }
        $tasks = new WP_Query($args);
        $events = $gantt_data = $gantt_links = [];
        if ($tasks->have_posts()) {
            while ($tasks->have_posts()) {
                $tasks->the_post();
                $due_date = get_post_meta(get_the_ID(), '_due_date', true);
                if($due_date) {
                    $events[] = ['id' => get_the_ID(), 'title' => get_the_title(), 'start' => $due_date, 'allDay' => true];
                    $gantt_data[] = ['id' => get_the_ID(), 'text' => get_the_title(), 'start_date' => get_the_date('Y-m-d'), 'end_date' => $due_date, 'parent' => get_post()->post_parent, 'open' => true];
                }
                if (get_post()->post_parent != 0) {
                    $gantt_links[] = ['id' => 'link_' . get_the_ID(), 'source' => get_post()->post_parent, 'target' => get_the_ID(), 'type' => '0'];
                }
            }
        }
        wp_reset_postdata();
        return WebinoCRM_Service_Base::success( ['calendar_events' => $events, 'gantt_tasks' => ['data' => $gantt_data, 'links' => $gantt_links]] );
	}

	public static function save_content( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!isset($_POST['task_id']) || !isset($_POST['content']) || !current_user_can('edit_tasks')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطای دسترسی.', 'webinocrm' )] );
        }
        $task_id = intval($_POST['task_id']);
        $content = wp_kses_post($_POST['content']);
        $result = wp_update_post(['ID' => $task_id, 'post_content' => $content], true);
        self::_log_task_activity($task_id, 'توضیحات وظیفه را به‌روزرسانی کرد.');
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ذخیره‌سازی.', 'webinocrm' )] );
        } else {
            return WebinoCRM_Service_Base::success( ['new_content_html' => wpautop($content)] );
        }
	}

	public static function add_comment( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'خطای دسترسی.', 'webinocrm' )] );
        }
        $task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : ( isset( $params['task_id'] ) ? intval( $params['task_id'] ) : ( isset( $params['id'] ) ? intval( $params['id'] ) : 0 ) );
        if ( $task_id <= 0 ) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شناسه وظیفه نامعتبر است.', 'webinocrm' )] );
        }
        $comment_raw = isset( $_POST['comment_text'] ) ? $_POST['comment_text'] : ( isset( $_POST['comment'] ) ? $_POST['comment'] : ( isset( $params['comment_text'] ) ? $params['comment_text'] : ( isset( $params['comment'] ) ? $params['comment'] : '' ) ) );
        if ('' === trim((string) $comment_raw)) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'متن نظر الزامی است.', 'webinocrm' )] );
        }
        $comment_text = wp_kses_post($comment_raw);
        $user = wp_get_current_user();
        $comment_id = wp_insert_comment(['comment_post_ID' => $task_id, 'comment_author' => $user->display_name, 'comment_author_email' => $user->user_email, 'comment_content' => $comment_text, 'user_id' => $user->ID, 'comment_approved' => 1]);
        if ($comment_id) {
            self::_log_task_activity($task_id, sprintf('یک نظر جدید ثبت کرد: "%s"', esc_html(wp_trim_words($comment_text, 10))));
            preg_match_all('/@(\w+)/', $comment_text, $matches);
            if (!empty($matches[1])) {
                foreach (array_unique($matches[1]) as $login) {
                    if ($mentioned_user = get_user_by('login', $login)) {
                         WebinoCRM_Logger::add(sprintf('شما در تسک "%s" منشن شدید', get_the_title($task_id)), ['content' => sprintf('%s شما را در یک نظر منشن کرد.', $user->display_name), 'type' => 'notification', 'user_id' => $mentioned_user->ID, 'object_id' => $task_id]);
                    }
                }
            }
            self::execute_automations('comment_added', $task_id);
            $comment = get_comment($comment_id);
            ob_start();
            echo '<li class="webino-comment-item"><div class="webino-comment-avatar">' . get_avatar($comment->user_id, 32) . '</div><div class="webino-comment-content"><p><strong>' . esc_html($comment->comment_author) . '</strong>: ' . wp_kses_post(wpautop($comment->comment_content)) . '</p><span class="webino-comment-date">' . human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . ' پیش</span></div></li>';
            return WebinoCRM_Service_Base::success( ['comment_html' => ob_get_clean()] );
        } else {
             return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ثبت نظر.', 'webinocrm' )] );
        }
	}

	public static function manage_checklist( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : ( isset( $params['task_id'] ) ? intval( $params['task_id'] ) : ( isset( $params['id'] ) ? intval( $params['id'] ) : 0 ) );
        if ( $task_id <= 0 ) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شناسه وظیفه نامعتبر است.', 'webinocrm' )] );
        }
        $sub_action = '';
        if (isset($_POST['sub_action'])) {
            $sub_action = sanitize_key($_POST['sub_action']);
        } elseif (isset($_POST['action_type'])) {
            $sub_action = sanitize_key($_POST['action_type']);
        } elseif (isset($params['sub_action'])) {
            $sub_action = sanitize_key($params['sub_action']);
        } elseif (isset($params['action_type'])) {
            $sub_action = sanitize_key($params['action_type']);
        }
        if ('' === $sub_action) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'عملیات نامعتبر.', 'webinocrm' )] );
        }
        $checklist = get_post_meta($task_id, '_task_checklist', true) ?: [];
        switch ($sub_action) {
            case 'add':
                $text_raw = isset( $_POST['text'] ) ? $_POST['text'] : ( isset( $_POST['item_text'] ) ? $_POST['item_text'] : ( isset( $params['text'] ) ? $params['text'] : ( isset( $params['item_text'] ) ? $params['item_text'] : '' ) ) );
                $text = sanitize_text_field($text_raw);
                if (empty($text)) return WebinoCRM_Service_Base::error( ['message' => __( 'متن نمی‌تواند خالی باشد.', 'webinocrm' )] );
                $checklist['item_' . time()] = ['text' => $text, 'checked' => false];
                self::_log_task_activity($task_id, sprintf('آیتم چک‌لیست "%s" را اضافه کرد.', $text));
                break;
            case 'toggle':
                $item_id = sanitize_key( isset( $_POST['item_id'] ) ? $_POST['item_id'] : ( isset( $params['item_id'] ) ? $params['item_id'] : '' ) );
                if (isset($checklist[$item_id])) {
                    $checklist[$item_id]['checked'] = !$checklist[$item_id]['checked'];
                    $log_action = $checklist[$item_id]['checked'] ? 'کامل' : 'ناکامل';
                    self::_log_task_activity($task_id, sprintf('وضعیت آیتم چک‌لیست "%s" را به %s تغییر داد.', $checklist[$item_id]['text'], $log_action));
                }
                break;
            case 'delete':
                $item_id = sanitize_key( isset( $_POST['item_id'] ) ? $_POST['item_id'] : ( isset( $params['item_id'] ) ? $params['item_id'] : '' ) );
                if (isset($checklist[$item_id])) {
                    self::_log_task_activity($task_id, sprintf('آیتم چک‌لیست "%s" را حذف کرد.', $checklist[$item_id]['text']));
                    unset($checklist[$item_id]);
                }
                break;
        }
        update_post_meta($task_id, '_task_checklist', $checklist);
        return WebinoCRM_Service_Base::success( ['checklist' => $checklist] );
	}

	public static function log_time( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks')) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $task_id = self::_resolve_task_id( $params );
        $hours = isset( $_POST['hours'] ) ? floatval( $_POST['hours'] ) : ( isset( $params['hours'] ) ? floatval( $params['hours'] ) : 0 );
        $description = isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : ( isset( $params['description'] ) ? sanitize_text_field( $params['description'] ) : '' );
        if ( $task_id <= 0 ) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'شناسه وظیفه نامعتبر است.', 'webinocrm' )] );
        }
        if ($hours <= 0) return WebinoCRM_Service_Base::error( ['message' => __( 'ساعت وارد شده باید بزرگتر از صفر باشد.', 'webinocrm' )] );
        $time_logs = get_post_meta($task_id, '_task_time_logs', true) ?: [];
        $current_user = wp_get_current_user();
        $new_log = ['user_id' => $current_user->ID, 'user_name' => $current_user->display_name, 'hours' => $hours, 'description' => $description, 'date' => current_time('mysql')];
        $time_logs[] = $new_log;
        update_post_meta($task_id, '_task_time_logs', $time_logs);
        self::_log_task_activity($task_id, sprintf('%.2f ساعت زمان ثبت کرد.', $hours));
        return WebinoCRM_Service_Base::success( ['new_log' => $new_log, 'total_hours' => array_sum(wp_list_pluck($time_logs, 'hours'))] );
	}

	public static function quick_edit( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks') || !isset($_POST['task_id']) || !isset($_POST['field'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $task_id = intval($_POST['task_id']);
        $field = sanitize_key($_POST['field']);
        $value = $_POST['value'];
        switch ($field) {
            case 'title':
                wp_update_post(['ID' => $task_id, 'post_title' => sanitize_text_field($value)]);
                self::_log_task_activity($task_id, sprintf('عنوان وظیفه را به "%s" تغییر داد.', sanitize_text_field($value)));
                break;
            case 'due_date':
                update_post_meta($task_id, '_due_date', webino_jalali_to_gregorian(sanitize_text_field($value)));
                self::_log_task_activity($task_id, sprintf('ددلاین را به "%s" تغییر داد.', sanitize_text_field($value)));
                break;
            case 'assignee':
                $assignee_id = intval($value);
                update_post_meta($task_id, '_assigned_to', $assignee_id);
                $new_assignee = get_userdata($assignee_id);
                if ($new_assignee) {
                    $log_message = sprintf('مسئول وظیفه را به "%s" تغییر داد.', $new_assignee->display_name);
                } else {
                    $log_message = 'مسئول وظیفه را حذف کرد.';
                }
                self::_log_task_activity($task_id, $log_message);
                break;
        }
        $task_html = function_exists('webino_render_task_card') ? webino_render_task_card(get_post($task_id)) : '';
        return WebinoCRM_Service_Base::success( ['message' => __( 'وظیفه به‌روزرسانی شد.', 'webinocrm' ), 'task_html' => $task_html] );
	}

	public static function update_assignee( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks') || !isset($_POST['task_id']) || !isset($_POST['assignee_id'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $task_id = intval($_POST['task_id']);
        $assignee_id = intval($_POST['assignee_id']);
        $new_assignee = get_userdata($assignee_id);
        if (!$new_assignee && $assignee_id > 0) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'کاربر انتخاب شده معتبر نیست.', 'webinocrm' )] );
        }
        update_post_meta($task_id, '_assigned_to', $assignee_id);
        $log_message = ($assignee_id > 0) ? sprintf('مسئول وظیفه را به "%s" تغییر داد.', $new_assignee->display_name) : 'مسئول وظیفه را حذف کرد.';
        self::_log_task_activity($task_id, $log_message);
        return WebinoCRM_Service_Base::success( ['message' => __( 'مسئول وظیفه با موفقیت به‌روزرسانی شد.', 'webinocrm' )] );
	}

	public static function search_for_linking( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : ( isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '' );
        $current = isset( $_POST['current_task_id'] ) ? intval( $_POST['current_task_id'] ) : ( isset( $params['current_task_id'] ) ? intval( $params['current_task_id'] ) : 0 );
        $tasks = new WP_Query(['post_type' => 'task', 'posts_per_page' => 10, 's' => $search, 'post__not_in' => [$current]]);
        $results = [];
        if ($tasks->have_posts()) {
            while ($tasks->have_posts()) { $tasks->the_post(); $results[] = ['id' => get_the_ID(), 'text' => '#' . get_the_ID() . ': ' . get_the_title()]; }
        }
        wp_reset_postdata();
        return WebinoCRM_Service_Base::success( $results );
	}

	public static function add_link( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        $from_id = isset( $_POST['from_task_id'] ) ? intval( $_POST['from_task_id'] ) : ( isset( $params['from_task_id'] ) ? intval( $params['from_task_id'] ) : 0 );
        $to_id = isset( $_POST['to_task_id'] ) ? intval( $_POST['to_task_id'] ) : ( isset( $params['to_task_id'] ) ? intval( $params['to_task_id'] ) : 0 );
        $type = isset( $_POST['link_type'] ) ? sanitize_key( $_POST['link_type'] ) : ( isset( $params['link_type'] ) ? sanitize_key( $params['link_type'] ) : 'relates_to' );
        $links = get_post_meta($from_id, '_task_links', true) ?: [];
        $links[] = ['type' => $type, 'task_id' => $to_id];
        update_post_meta($from_id, '_task_links', $links);
        $inverse_map = ['blocks' => 'is_blocked_by', 'is_blocked_by' => 'blocks', 'relates_to' => 'relates_to'];
        $inverse_links = get_post_meta($to_id, '_task_links', true) ?: [];
        $inverse_links[] = ['type' => $inverse_map[$type], 'task_id' => $from_id];
        update_post_meta($to_id, '_task_links', $inverse_links);
        self::_log_task_activity($from_id, sprintf('وظیفه را به #%d با نوع "%s" پیوند داد.', $to_id, $type));
        self::_log_task_activity($to_id, sprintf('وظیفه به #%d با نوع "%s" پیوند داده شد.', $from_id, $inverse_map[$type]));
        return WebinoCRM_Service_Base::success( ['message' => __( 'پیوند با موفقیت ایجاد شد.', 'webinocrm' )] );
	}

	public static function remove_link( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'edit_tasks' ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
		$from_id = isset( $_POST['from_task_id'] ) ? intval( $_POST['from_task_id'] ) : ( isset( $params['from_task_id'] ) ? intval( $params['from_task_id'] ) : ( isset( $_POST['source_id'] ) ? intval( $_POST['source_id'] ) : ( isset( $params['source_id'] ) ? intval( $params['source_id'] ) : 0 ) ) );
		$to_id   = isset( $_POST['to_task_id'] ) ? intval( $_POST['to_task_id'] ) : ( isset( $params['to_task_id'] ) ? intval( $params['to_task_id'] ) : ( isset( $_POST['target_id'] ) ? intval( $_POST['target_id'] ) : ( isset( $params['target_id'] ) ? intval( $params['target_id'] ) : 0 ) ) );
		if ( $from_id <= 0 || $to_id <= 0 ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'اطلاعات پیوند ناقص است.', 'webinocrm' ) ) );
		}
		$from_links = get_post_meta( $from_id, '_task_links', true );
		if ( ! is_array( $from_links ) ) {
			$from_links = array();
		}
		$removed_type = null;
		foreach ( $from_links as $link ) {
			if ( (int) ( $link['task_id'] ?? 0 ) === $to_id ) {
				$removed_type = isset( $link['type'] ) ? sanitize_key( $link['type'] ) : 'relates_to';
				break;
			}
		}
		if ( null === $removed_type ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'پیوند یافت نشد.', 'webinocrm' ) ) );
		}
		$from_links = self::_filter_task_links_for_peer( $from_links, $to_id );
		$to_links   = get_post_meta( $to_id, '_task_links', true );
		if ( ! is_array( $to_links ) ) {
			$to_links = array();
		}
		$to_links = self::_filter_task_links_for_peer( $to_links, $from_id );
		update_post_meta( $from_id, '_task_links', $from_links );
		update_post_meta( $to_id, '_task_links', $to_links );
		self::_log_task_activity( $from_id, sprintf( 'پیوند با وظیفه #%d را حذف کرد.', $to_id ) );
		self::_log_task_activity( $to_id, sprintf( 'پیوند با وظیفه #%d حذف شد.', $from_id ) );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'پیوند با موفقیت حذف شد.', 'webinocrm' ) ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $links Links.
	 * @param int                            $peer_id Peer task ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function _filter_task_links_for_peer( array $links, $peer_id ) {
		return array_values(
			array_filter(
				$links,
				static function ( $link ) use ( $peer_id ) {
					return (int) ( $link['task_id'] ?? 0 ) !== (int) $peer_id;
				}
			)
		);
	}

	public static function bulk_edit( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks') || !isset($_POST['task_ids']) || !is_array($_POST['task_ids'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز یا اطلاعات ناقص.', 'webinocrm' )] );
        }
        $task_ids = array_map('intval', $_POST['task_ids']);
        $actions = $_POST['bulk_actions'];
        foreach ($task_ids as $task_id) {
            if (!empty($actions['status'])) wp_set_post_terms($task_id, sanitize_key($actions['status']), 'task_status');
            if (!empty($actions['assignee'])) update_post_meta($task_id, '_assigned_to', intval($actions['assignee']));
            if (!empty($actions['priority'])) wp_set_post_terms($task_id, intval($actions['priority']), 'task_priority');
        }
        return WebinoCRM_Service_Base::success( ['message' => __( 'وظایف با موفقیت به‌روزرسانی شدند.', 'webinocrm' )] );
	}

	public static function save_as_template( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('edit_tasks') || !isset($_POST['task_id']) || !isset($_POST['template_name'])) {
            return WebinoCRM_Service_Base::error( ['message' => __( 'دسترسی غیرمجاز.', 'webinocrm' )] );
        }
        $task_id = intval($_POST['task_id']);
        $template_name = sanitize_text_field($_POST['template_name']);
        $source_task = get_post($task_id);
        if (!$source_task) return WebinoCRM_Service_Base::error( ['message' => __( 'وظیفه منبع یافت نشد.', 'webinocrm' )] );
        $template_id = wp_insert_post(['post_title' => $template_name, 'post_content' => $source_task->post_content, 'post_type' => 'task_template', 'post_status' => 'publish']);
        if (is_wp_error($template_id)) return WebinoCRM_Service_Base::error( ['message' => __( 'خطا در ایجاد قالب.', 'webinocrm' )] );
        $priority_term = WebinoCRM_Term_Helper::get_first_term( $task_id, 'task_priority' );
        if ( $priority_term ) {
            update_post_meta( $template_id, '_template_priority', $priority_term->term_id );
        }
        update_post_meta($template_id, '_template_story_points', get_post_meta($task_id, '_story_points', true));
        update_post_meta($template_id, '_template_checklist', get_post_meta($task_id, '_task_checklist', true));
        return WebinoCRM_Service_Base::success( ['message' => __( 'قالب با موفقیت ذخیره شد.', 'webinocrm' )] );
	}

	/**
	 * Resolve task ID from request params.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return int
	 */
	private static function _resolve_task_id( array $params ) {
		if ( isset( $_POST['task_id'] ) ) {
			return (int) $_POST['task_id'];
		}
		if ( isset( $params['task_id'] ) ) {
			return (int) $params['task_id'];
		}
		if ( isset( $params['id'] ) ) {
			return (int) $params['id'];
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'edit_tasks' ) && ! ( function_exists( 'webinocrm_user_can_manage_tasks' ) && webinocrm_user_can_manage_tasks() ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$task_id = self::_resolve_task_id( $params );
		if ( $task_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه وظیفه نامعتبر است.', 'webinocrm' ) );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-task-data-builder.php';
		$task = WebinoCRM_Task_Data_Builder::build_detail( $task_id );
		if ( null === $task ) {
			return WebinoCRM_Service_Base::error( __( 'وظیفه یافت نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'task' => $task ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function upload_attachment( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'edit_posts' ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
		$task_id = self::_resolve_task_id( $params );
		if ( $task_id <= 0 ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'شناسه وظیفه نامعتبر است.', 'webinocrm' ) ) );
		}
		if ( empty( $_FILES['file'] ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'فایلی انتخاب نشده است.', 'webinocrm' ) ) );
		}
		$file = $_FILES['file'];
		$allowed_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'application/pdf',
			'application/zip',
			'application/x-rar-compressed',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'فرمت فایل مجاز نیست.', 'webinocrm' ) ) );
		}
		if ( (int) $file['size'] > 10 * 1024 * 1024 ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'حجم فایل بیش از حد مجاز است (حداکثر 10MB).', 'webinocrm' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_upload( 'file', $task_id );
		if ( is_wp_error( $attachment_id ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => $attachment_id->get_error_message() ) );
		}
		$attachments = get_post_meta( $task_id, '_task_attachments', true );
		if ( ! is_array( $attachments ) ) {
			$attachments = array();
		}
		$attachments[] = (int) $attachment_id;
		update_post_meta( $task_id, '_task_attachments', $attachments );
		$file_url  = wp_get_attachment_url( $attachment_id );
		$file_name = basename( get_attached_file( $attachment_id ) );
		$file_type = wp_check_filetype( $file_name );
		return WebinoCRM_Service_Base::success(
			array(
				'message'    => __( 'فایل با موفقیت آپلود شد.', 'webinocrm' ),
				'attachment' => array(
					'id'   => (int) $attachment_id,
					'url'  => (string) $file_url,
					'name' => $file_name,
					'type' => isset( $file_type['ext'] ) ? (string) $file_type['ext'] : '',
				),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function delete_attachment( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'delete_posts' ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
		$task_id = self::_resolve_task_id( $params );
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : ( isset( $params['attachment_id'] ) ? (int) $params['attachment_id'] : 0 );
		if ( $task_id <= 0 || $attachment_id <= 0 ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'اطلاعات ناقص است.', 'webinocrm' ) ) );
		}
		$attachments = get_post_meta( $task_id, '_task_attachments', true );
		if ( ! is_array( $attachments ) ) {
			$attachments = array();
		}
		$attachments = array_values(
			array_filter(
				$attachments,
				static function ( $id ) use ( $attachment_id ) {
					return (int) $id !== $attachment_id;
				}
			)
		);
		update_post_meta( $task_id, '_task_attachments', $attachments );
		wp_delete_attachment( $attachment_id, true );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'فایل حذف شد.', 'webinocrm' ) ) );
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_tasks' => array( __CLASS__, 'list' ),
				'webino_quick_add_task' => array( __CLASS__, 'create' ),
				'webino_update_task_status' => array( __CLASS__, 'update_status' ),
				'webino_delete_task' => array( __CLASS__, 'delete' ),
				'webino_add_task' => array( __CLASS__, 'create_extended' ),
				'webino_create_task' => array( __CLASS__, 'create_extended' ),
				'webino_get_tasks_calendar' => array( __CLASS__, 'calendar' ),
				'webino_get_tasks_gantt' => array( __CLASS__, 'gantt' ),
				'get_tasks_for_views' => array( __CLASS__, 'views' ),
				'webino_save_task_content' => array( __CLASS__, 'save_content' ),
				'webino_add_task_comment' => array( __CLASS__, 'add_comment' ),
				'webino_manage_checklist' => array( __CLASS__, 'manage_checklist' ),
				'crm_log_time' => array( __CLASS__, 'log_time' ),
				'webino_quick_edit_task' => array( __CLASS__, 'quick_edit' ),
				'webino_update_task_assignee' => array( __CLASS__, 'update_assignee' ),
				'webino_search_tasks_for_linking' => array( __CLASS__, 'search_for_linking' ),
				'webino_add_task_link' => array( __CLASS__, 'add_link' ),
				'webino_remove_task_link' => array( __CLASS__, 'remove_link' ),
				'webino_bulk_edit_tasks' => array( __CLASS__, 'bulk_edit' ),
				'webino_save_task_as_template' => array( __CLASS__, 'save_as_template' ),
			)
		);
	}
}
