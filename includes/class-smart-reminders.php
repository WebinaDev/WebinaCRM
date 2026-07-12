<?php
/**
 * Smart Reminders & Notifications System
 * 
 * Intelligent reminder system with multiple notification channels
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Smart_Reminders {

    /**
     * Initialize Smart Reminders
     */
    public function __construct() {
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);
        add_action('webinocrm_check_reminders', [$this, 'process_reminders']);
        add_action('wp_ajax_webinocrm_create_reminder', [$this, 'ajax_create_reminder']);
        add_action('wp_ajax_webinocrm_get_reminders', [$this, 'ajax_get_reminders']);
        add_action('wp_ajax_webinocrm_snooze_reminder', [$this, 'ajax_snooze_reminder']);
        add_action('wp_ajax_webinocrm_dismiss_reminder', [$this, 'ajax_dismiss_reminder']);
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('webinocrm_check_reminders')) {
            wp_schedule_event(time(), 'every_minute', 'webinocrm_check_reminders');
        }
    }

    /**
     * Register custom cron schedules
     */
    public function register_cron_schedules($schedules) {
        if (!is_array($schedules)) {
            $schedules = [];
        }
        
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => __('هر دقیقه', 'webinocrm')
        ];
        
        return $schedules;
    }

    /**
     * Create a reminder
     */
    public static function create_reminder($args) {
        global $wpdb;

        $defaults = [
            'user_id' => get_current_user_id(),
            'title' => '',
            'description' => '',
            'remind_at' => '',
            'entity_type' => '',
            'entity_id' => 0,
            'reminder_type' => 'manual', // manual, auto, recurring
            'notification_channels' => ['in_app'], // in_app, email, sms, push
            'recurring_pattern' => null, // daily, weekly, monthly
            'priority' => 'normal', // low, normal, high, urgent
            'status' => 'pending', // pending, sent, snoozed, dismissed
            'metadata' => []
        ];

        $data = wp_parse_args($args, $defaults);
        
        // Validate remind_at date
        if (empty($data['remind_at'])) {
            return new WP_Error('invalid_date', 'تاریخ یادآوری معتبر نیست');
        }

        $data['notification_channels'] = maybe_serialize($data['notification_channels']);
        $data['metadata'] = maybe_serialize($data['metadata']);
        $data['created_at'] = current_time('mysql');

        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_reminders',
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $reminder_id = $wpdb->insert_id;

        // If it's a recurring reminder, create the next occurrence
        if ($data['recurring_pattern']) {
            self::schedule_next_occurrence($reminder_id, $data);
        }

        return $reminder_id;
    }

    /**
     * Process pending reminders
     */
    public function process_reminders() {
        global $wpdb;

        $now = current_time('mysql');

        // Get all pending reminders that are due
        $reminders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_reminders 
             WHERE status = 'pending' 
             AND remind_at <= %s
             ORDER BY priority DESC, remind_at ASC
             LIMIT 50",
            $now
        ));

        foreach ($reminders as $reminder) {
            $this->send_reminder($reminder);
        }
    }

    /**
     * Send a reminder through specified channels
     */
    private function send_reminder($reminder) {
        $channels = maybe_unserialize($reminder->notification_channels);
        
        if (!is_array($channels)) {
            $channels = ['in_app'];
        }

        $sent_successfully = false;

        foreach ($channels as $channel) {
            $result = $this->send_via_channel($reminder, $channel);
            if ($result) {
                $sent_successfully = true;
            }
        }

        if ($sent_successfully) {
            // Update reminder status
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'webinocrm_reminders',
                [
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                ],
                ['id' => $reminder->id],
                ['%s', '%s'],
                ['%d']
            );

            // If recurring, create next occurrence
            if ($reminder->recurring_pattern) {
                self::schedule_next_occurrence($reminder->id, (array)$reminder);
            }

            // Log activity
            WebinoCRM_Activity_Timeline::log([
                'user_id' => $reminder->user_id,
                'action_type' => 'reminder_sent',
                'entity_type' => $reminder->entity_type,
                'entity_id' => $reminder->entity_id,
                'description' => 'ارسال یادآوری: ' . $reminder->title
            ]);
        }

        return $sent_successfully;
    }

    /**
     * Send reminder via specific channel
     */
    private function send_via_channel($reminder, $channel) {
        switch ($channel) {
            case 'in_app':
                return $this->send_in_app_notification($reminder);
            
            case 'email':
                return $this->send_email_notification($reminder);
            
            case 'sms':
                return $this->send_sms_notification($reminder);
            
            case 'push':
                return $this->send_push_notification($reminder);
            
            default:
                return false;
        }
    }

    /**
     * Send in-app notification
     */
    private function send_in_app_notification($reminder) {
        // Use WebSocket handler to push notification
        WebinoCRM_WebSocket_Handler::broadcast_notification(
            [$reminder->user_id],
            [
                'type' => 'reminder',
                'title' => $reminder->title,
                'message' => $reminder->description,
                'priority' => $reminder->priority,
                'entity_type' => $reminder->entity_type,
                'entity_id' => $reminder->entity_id,
                'reminder_id' => $reminder->id
            ]
        );

        return true;
    }

    /**
     * Send email notification
     */
    private function send_email_notification($reminder) {
        $user = get_userdata($reminder->user_id);
        if (!$user) {
            return false;
        }

        $subject = 'یادآوری: ' . $reminder->title;
        $message = $this->get_email_template($reminder);

        return wp_mail(
            $user->user_email,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    /**
     * Send SMS notification
     */
    private function send_sms_notification($reminder) {
        $user = get_userdata($reminder->user_id);
        if (!$user) {
            return false;
        }

        $phone = get_user_meta($reminder->user_id, 'phone', true);
        if (empty($phone)) {
            return false;
        }

        $message = "یادآوری: {$reminder->title}\n{$reminder->description}";

        // Use SMS service
        $sms_service = WebinoCRM_Settings_Handler::get_setting('sms_service', 'melipayamak');
        
        if ($sms_service === 'melipayamak') {
            $sms = new WebinoCRM_Melipayamak_Handler();
        } else {
            $sms = new WebinoCRM_Parsgreen_Handler();
        }

        return $sms->send_sms($phone, $message);
    }

    /**
     * Send push notification
     */
    private function send_push_notification($reminder) {
        // Use WebSocket for browser push notifications
        return $this->send_in_app_notification($reminder);
    }

    /**
     * Get email template for reminder
     */
    private function get_email_template($reminder) {
        $entity_link = '';
        if ($reminder->entity_id && $reminder->entity_type) {
            $entity_link = get_edit_post_link($reminder->entity_id);
        }
        
        // Get user language preference
        $locale = get_locale();
        $user_lang = get_user_meta($reminder->user_id, 'webino_language', true);
        if ($user_lang === 'en') {
            $locale = 'en_US';
        } elseif ($user_lang === 'fa') {
            $locale = 'fa_IR';
        }
        
        $is_rtl = ($locale === 'fa_IR');
        $direction = $is_rtl ? 'rtl' : 'ltr';
        $lang = substr($locale, 0, 2);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="<?php echo esc_attr($direction); ?>" lang="<?php echo esc_attr($lang); ?>">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Tahoma, Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                .container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
                .header { border-bottom: 3px solid #4CAF50; padding-bottom: 20px; margin-bottom: 20px; }
                .title { color: #333; font-size: 24px; margin: 0; }
                .content { color: #666; line-height: 1.8; margin: 20px 0; }
                .button { display: inline-block; background: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 class="title"><?php echo esc_html($reminder->title); ?></h1>
                </div>
                <div class="content">
                    <p><?php echo nl2br(esc_html($reminder->description)); ?></p>
                    <?php if ($entity_link): ?>
                        <a href="<?php echo esc_url($entity_link); ?>" class="button">مشاهده جزئیات</a>
                    <?php endif; ?>
                </div>
                <div class="footer">
                    <p>این یادآوری توسط سیستم WebinoCRM ارسال شده است.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Schedule next occurrence for recurring reminders
     */
    private static function schedule_next_occurrence($parent_id, $reminder_data) {
        $current_time = strtotime($reminder_data['remind_at']);
        $next_time = null;

        switch ($reminder_data['recurring_pattern']) {
            case 'daily':
                $next_time = strtotime('+1 day', $current_time);
                break;
            
            case 'weekly':
                $next_time = strtotime('+1 week', $current_time);
                break;
            
            case 'monthly':
                $next_time = strtotime('+1 month', $current_time);
                break;
            
            case 'yearly':
                $next_time = strtotime('+1 year', $current_time);
                break;
        }

        if ($next_time) {
            $reminder_data['remind_at'] = date('Y-m-d H:i:s', $next_time);
            $reminder_data['status'] = 'pending';
            $reminder_data['parent_id'] = $parent_id;
            
            self::create_reminder($reminder_data);
        }
    }

    /**
     * Get user reminders
     */
    public static function get_user_reminders($user_id, $status = null) {
        global $wpdb;

        $where = $wpdb->prepare('user_id = %d', $user_id);
        
        if ($status) {
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}webinocrm_reminders
             WHERE {$where}
             ORDER BY remind_at ASC
             LIMIT 100"
        );
    }

    /**
     * Snooze a reminder
     */
    public static function snooze_reminder($reminder_id, $snooze_until) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'webinocrm_reminders',
            [
                'remind_at' => $snooze_until,
                'status' => 'pending'
            ],
            ['id' => $reminder_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Dismiss a reminder
     */
    public static function dismiss_reminder($reminder_id) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'webinocrm_reminders',
            ['status' => 'dismissed'],
            ['id' => $reminder_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * AJAX: Create reminder
     */
    public function ajax_create_reminder() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $data = [
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'remind_at' => sanitize_text_field($_POST['remind_at'] ?? ''),
            'entity_type' => sanitize_key($_POST['entity_type'] ?? ''),
            'entity_id' => intval($_POST['entity_id'] ?? 0),
            'priority' => sanitize_key($_POST['priority'] ?? 'normal'),
            'notification_channels' => $_POST['channels'] ?? ['in_app'],
            'recurring_pattern' => sanitize_key($_POST['recurring'] ?? '')
        ];

        $result = self::create_reminder($data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'یادآوری با موفقیت ایجاد شد',
            'reminder_id' => $result
        ]);
    }

    /**
     * AJAX: Get reminders
     */
    public function ajax_get_reminders() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $user_id = get_current_user_id();
        $status = sanitize_key($_POST['status'] ?? '');

        $reminders = self::get_user_reminders($user_id, $status ?: null);

        wp_send_json_success(['reminders' => $reminders]);
    }

    /**
     * AJAX: Snooze reminder
     */
    public function ajax_snooze_reminder() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        $snooze_minutes = intval($_POST['minutes'] ?? 10);

        $snooze_until = date('Y-m-d H:i:s', strtotime("+{$snooze_minutes} minutes"));

        $result = self::snooze_reminder($reminder_id, $snooze_until);

        if ($result) {
            wp_send_json_success(['message' => 'یادآوری به تعویق افتاد']);
        } else {
            wp_send_json_error(['message' => 'خطا در به تعویق انداختن یادآوری']);
        }
    }

    /**
     * AJAX: Dismiss reminder
     */
    public function ajax_dismiss_reminder() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $reminder_id = intval($_POST['reminder_id'] ?? 0);

        $result = self::dismiss_reminder($reminder_id);

        if ($result) {
            wp_send_json_success(['message' => 'یادآوری نادیده گرفته شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در نادیده گرفتن یادآوری']);
        }
    }
}

