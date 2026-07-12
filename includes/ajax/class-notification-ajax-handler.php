<?php
/**
 * WebinoCRM Notification AJAX Handler
 *
 * @package WebinoCRM
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Notification_Ajax_Handler {

    public function __construct() {
        add_action('wp_ajax_webino_get_notifications', [$this, 'get_notifications']);
        add_action('wp_ajax_webino_mark_notification_read', [$this, 'mark_notification_read']);
    }

    /**
     * AJAX handler to fetch unread notifications for the current user.
     */
    public function get_notifications() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            wp_send_json_success(['count' => 0, 'html' => '']);
            return;
        }

        $args = [
            'post_type' => 'crm_log',
            'author' => $user_id,
            'posts_per_page' => 10,
            'meta_query' => [
                ['key' => '_log_type', 'value' => 'notification']
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $notifications = get_posts($args);

        // Count only unread notifications
        $unread_args = $args;
        $unread_args['meta_query'][] = ['key' => '_is_read', 'value' => '0'];
        $unread_count = count(get_posts($unread_args));

        if (empty($notifications)) {
            wp_send_json_success(['count' => 0, 'html' => '<li class="webino-no-notifications">هیچ اعلانی وجود ندارد.</li>']);
        }

        $html = '';
        foreach ($notifications as $note) {
            $is_read = get_post_meta($note->ID, '_is_read', true);
            $object_id = get_post_meta($note->ID, '_related_object_id', true);
            $object_type = $object_id ? get_post_type($object_id) : '';

            $link = '#'; // Default link
            if ($object_id) {
                switch ($object_type) {
                    case 'task':
                        $link = add_query_arg(['view' => 'tasks', 'open_task_id' => $object_id], webino_get_dashboard_url());
                        break;
                    case 'ticket':
                         $link = add_query_arg(['view' => 'tickets', 'ticket_id' => $object_id], webino_get_dashboard_url());
                        break;
                    case 'appointment':
                        $link = add_query_arg(['view' => 'appointments'], webino_get_dashboard_url());
                        break;
                     case 'contract':
                        $link = add_query_arg(['view' => 'invoices'], webino_get_dashboard_url());
                        break;
                }
            }

            $html .= sprintf(
                '<li data-id="%d" class="%s"><a href="%s">%s <small>%s</small></a></li>',
                esc_attr($note->ID),
                ($is_read == '1' ? 'webino-read' : 'webino-unread'),
                esc_url($link),
                esc_html($note->post_title),
                esc_html(human_time_diff(get_the_time('U', $note->ID), current_time('timestamp')) . ' پیش')
            );
        }
        
        wp_send_json_success(['count' => $unread_count, 'html' => $html]);
    }

    /**
     * AJAX handler to mark a notification as read.
     */
    public function mark_notification_read() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (isset($_POST['id'])) {
            $note_id = intval($_POST['id']);
            $note = get_post($note_id);
            // Ensure the user is the owner of the notification
            if ($note && $note->post_author == get_current_user_id()) {
                update_post_meta($note_id, '_is_read', '1');
                wp_send_json_success(['message' => 'خوانده شد.']);
            }
        }
        wp_send_json_error(['message' => 'خطا در پردازش درخواست.']);
    }
}