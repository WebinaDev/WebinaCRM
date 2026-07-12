<?php
/**
 * WebinoCRM Cron Handler
 * Manages scheduled tasks like sending payment reminders and creating daily tasks.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Cron_Handler {
    
    public function __construct() {
        add_action( 'webino_daily_reminder_hook', [ $this, 'send_payment_reminders' ] );
        add_action( 'webino_daily_reminder_hook', [ $this, 'send_task_reminders' ] );
        add_action( 'webino_create_daily_tasks_hook', [ $this, 'create_daily_tasks' ] );
        add_action( 'webinocrm_cleanup_logs', [ 'WebinoCRM_Logger', 'cleanup_old_logs' ] );
    }
	
	/**
     * Creates daily tasks automatically based on templates.
     */
    public function create_daily_tasks() {
        // Find the term for "daily" tasks
        $daily_category = get_term_by('slug', 'daily', 'task_category');
        if (!$daily_category) {
            webinocrm_debug_log('WebinoCRM Cron: "Daily" task category not found.');
            return;
        }

        // Get all task templates marked as "daily"
        $daily_templates = get_posts([
            'post_type' => 'task_template',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'task_category',
                    'field'    => 'term_id',
                    'terms'    => $daily_category->term_id,
                ],
            ],
        ]);

        if (empty($daily_templates)) {
            return; // No daily templates to process
        }
		
		$today_str = wp_date('Y-m-d');

        foreach ($daily_templates as $template) {
            $assigned_role_id = get_post_meta($template->ID, '_assigned_role', true);
            if (empty($assigned_role_id)) {
                continue;
            }

            // Find all users with this organizational position
            $users_with_role = get_users([
                'tax_query' => [
                    [
                        'taxonomy' => 'organizational_position',
                        'field'    => 'term_id',
                        'terms'    => $assigned_role_id,
                    ],
                ],
                'fields' => 'ID',
            ]);

            if (empty($users_with_role)) {
                continue;
            }

            // Create a task for each user with that role
            foreach ($users_with_role as $user_id) {
                // Check if a task with the same title was already created for this user today
                $existing_tasks = get_posts([
                    'post_type' => 'task',
                    'title' => $template->post_title,
                    'date_query' => [
                        ['year' => wp_date('Y'), 'month' => wp_date('m'), 'day' => wp_date('d')]
                    ],
                    'meta_query' => [
                        ['key' => '_assigned_to', 'value' => $user_id]
                    ]
                ]);

                if (!empty($existing_tasks)) {
                    continue; // Skip if already created today
                }

                $task_id = wp_insert_post([
                    'post_title'   => $template->post_title,
                    'post_content' => $template->post_content,
                    'post_type'    => 'task',
                    'post_status'  => 'publish',
                    'post_author'  => 1, // System User
                ]);

                if (!is_wp_error($task_id)) {
                    // Assign to the user
                    update_post_meta($task_id, '_assigned_to', $user_id);
                    // Set deadline for today
                    update_post_meta($task_id, '_due_date', $today_str);
                    // Set default status to the first one in the workflow
                    wp_set_object_terms($task_id, webino_get_default_task_status_slug(), 'task_status');
					// Set category to "Daily"
					wp_set_object_terms($task_id, $daily_category->term_id, 'task_category');

                    // Log and notify
                    WebinoCRM_Logger::add('تسک روزانه جدید', [
                        'content'   => "تسک خودکار '{$template->post_title}' برای شما ایجاد شد.",
                        'type'      => 'notification',
                        'user_id'   => $user_id,
                        'object_id' => $task_id
                    ]);
                }
            }
        }
    }

    public function send_payment_reminders() {
        $settings = WebinoCRM_Settings_Handler::get_all_settings();
        $sms_handler = webino_get_sms_handler($settings);

        if ( !$sms_handler ) {
            set_transient('webino_sms_not_configured', true, DAY_IN_SECONDS);
            webinocrm_debug_log('WebinoCRM Cron: No active and correctly configured SMS service found for payment reminders.');
            return;
        }

        // If cron runs successfully, remove the notice transient
        delete_transient('webino_sms_not_configured');

        $contracts = get_posts(['post_type' => 'contract', 'posts_per_page' => -1, 'post_status' => 'publish']);
        if (empty($contracts)) return;

        // Use WordPress's timezone for accurate date comparison
        $today = new DateTime('now', new DateTimeZone(wp_timezone_string()));
        $today->setTime(0, 0, 0);

        foreach ( $contracts as $contract_post ) {
            $installments = get_post_meta( $contract_post->ID, '_installments', true );
            $customer_id = $contract_post->post_author;
            
            $customer_phone = get_user_meta($customer_id, 'webino_mobile_phone', true);

            if ( empty($installments) || ! is_array($installments) || empty($customer_phone) ) {
                continue;
            }

            foreach ($installments as $installment) {
                if (($installment['status'] ?? 'pending') !== 'paid') {
                    try {
                        $due_date = new DateTime($installment['due_date'], new DateTimeZone(wp_timezone_string()));
                        $due_date->setTime(0, 0, 0);
                        
                        $interval = $today->diff($due_date);
                        if ($interval->invert) continue; 

                        $days_left = $interval->days;
                        $amount_formatted = number_format($installment['amount']);
                        
                        $message_or_pattern = '';
                        $params = [];

                        if (in_array($days_left, [0, 1, 3])) {
                            if ($settings['sms_service'] === 'melipayamak') {
                                $pattern_map = [
                                    3 => $settings['pattern_3_days'] ?? '',
                                    1 => $settings['pattern_1_day'] ?? '',
                                    0 => $settings['pattern_due_today'] ?? '',
                                ];
                                $message_or_pattern = $pattern_map[$days_left] ?? null;
                                $params = ['amount' => $amount_formatted];
                            } elseif ($settings['sms_service'] === 'parsgreen') {
                                $message_template_map = [
                                    3 => $settings['parsgreen_msg_3_days'] ?? '',
                                    1 => $settings['parsgreen_msg_1_day'] ?? '',
                                    0 => $settings['parsgreen_msg_due_today'] ?? '',
                                ];
                                $message_template = $message_template_map[$days_left] ?? null;
                                if ($message_template) {
                                     $message_or_pattern = str_replace('{amount}', $amount_formatted, $message_template);
                                }
                            }

                            if (!empty($message_or_pattern)) {
                                $sms_handler->send_sms($customer_phone, $message_or_pattern, $params);
                            }
                        }
                    } catch (Exception $e) {
                        webinocrm_debug_log('WebinoCRM Cron Error: Invalid date format for contract ID ' . $contract_post->ID);
                    }
                }
            }
        }
    }
    
    /**
     * Sends email notifications for upcoming or overdue tasks.
     * **FIXED**: Uses WordPress timezone-aware functions for date comparisons.
     */
    public function send_task_reminders() {
        // Use wp_date to get dates in WordPress's configured timezone.
        $today_str = wp_date('Y-m-d');
        $tomorrow_str = wp_date('Y-m-d', strtotime('+1 day'));

        $tasks_due_soon = get_posts([
            'post_type' => 'task',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                // Overdue tasks
                [
                    'key' => '_due_date',
                    'value' => $today_str,
                    'compare' => '<',
                    'type' => 'DATE'
                ],
                // Tasks due today or tomorrow
                [
                    'key' => '_due_date',
                    'value' => [$today_str, $tomorrow_str],
                    'compare' => 'IN',
                    'type' => 'DATE'
                ]
            ],
            'tax_query' => [
                [
                    'taxonomy' => 'task_status',
                    'field' => 'slug',
                    'terms' => 'done',
                    'operator' => 'NOT IN'
                ]
            ]
        ]);

        if (empty($tasks_due_soon)) {
            return;
        }
        
        $reminders_by_user = [];

        // Group tasks by assigned user
        foreach ($tasks_due_soon as $task) {
            $assigned_to = get_post_meta($task->ID, '_assigned_to', true);
            if ($assigned_to) {
                if (!isset($reminders_by_user[$assigned_to])) {
                    $reminders_by_user[$assigned_to] = [];
                }
                $reminders_by_user[$assigned_to][] = $task;
            }
        }

        // Send one summary email per user
        foreach ($reminders_by_user as $user_id => $tasks) {
            $user = get_userdata($user_id);
            if (!$user) continue;

            $to = $user->user_email;
            $subject = 'یادآوری وظایف WebinoCRM';
            
            $body = '<p>سلام ' . esc_html($user->display_name) . '،</p>';
            $body .= '<p>این یک یادآوری برای وظایف زیر است که ددلاین آن‌ها نزدیک یا گذشته است:</p>';
            $body .= '<ul>';

            foreach ($tasks as $task) {
                $due_date = get_post_meta($task->ID, '_due_date', true);
                $status_text = (strtotime($due_date) < strtotime($today_str)) ? '<strong style="color:red;">(گذشته)</strong>' : '';
                $body .= sprintf('<li><strong>%s</strong> - تاریخ سررسید: %s %s</li>', esc_html($task->post_title), esc_html($due_date), $status_text);
            }

            $body .= '</ul>';
            $body .= '<p>لطفاً برای مدیریت وظایf خود به داشبورد مراجعه کنید.</p>';
            $body .= '<p><a href="' . esc_url(webino_get_dashboard_url()) . '">رفتن به داشبورد</a></p>';
            
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($to, $subject, $body, $headers);
        }
    }
}