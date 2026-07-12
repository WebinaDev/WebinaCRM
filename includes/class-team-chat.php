<?php
/**
 * Team Chat/Collaboration System
 * 
 * Real-time team chat and collaboration features
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Team_Chat {

    /**
     * Initialize Team Chat
     */
    public function __construct() {
        // REST + SPA via WebinoCRM_Chat_Service; legacy wp_ajax hooks removed.
    }

    /**
     * Send message
     */
    public static function send_message($args) {
        global $wpdb;

        $defaults = [
            'sender_id' => get_current_user_id(),
            'channel_id' => 0,
            'recipient_id' => 0,
            'message' => '',
            'message_type' => 'text', // text, image, file, system
            'parent_id' => 0, // for threaded replies
            'metadata' => []
        ];

        $data = wp_parse_args($args, $defaults);

        if (empty($data['message'])) {
            return new WP_Error('empty_message', 'پیام خالی است');
        }

        // Insert message
        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_chat_messages',
            [
                'sender_id' => $data['sender_id'],
                'channel_id' => $data['channel_id'],
                'recipient_id' => $data['recipient_id'],
                'message' => $data['message'],
                'message_type' => $data['message_type'],
                'parent_id' => $data['parent_id'],
                'metadata' => maybe_serialize($data['metadata']),
                'sent_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        $message_id = $wpdb->insert_id;

        // Update channel last activity
        if ($data['channel_id']) {
            $wpdb->update(
                $wpdb->prefix . 'webinocrm_chat_channels',
                ['last_activity' => current_time('mysql')],
                ['id' => $data['channel_id']],
                ['%s'],
                ['%d']
            );
        }

        // Send real-time notification via WebSocket
        $recipients = $data['channel_id'] 
            ? self::get_channel_members($data['channel_id'])
            : [$data['recipient_id']];

        $message_data = self::get_message($message_id);

        WebinoCRM_WebSocket_Handler::broadcast_notification(
            $recipients,
            [
                'type' => 'chat_message',
                'message' => $message_data
            ]
        );

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'user_id' => $data['sender_id'],
            'action_type' => 'chat_message_sent',
            'entity_type' => 'chat',
            'entity_id' => $message_id,
            'description' => 'ارسال پیام در چت'
        ]);

        return $message_id;
    }

    /**
     * Get messages
     */
    public static function get_messages($args = []) {
        global $wpdb;

        $defaults = [
            'channel_id' => 0,
            'recipient_id' => 0,
            'sender_id' => 0,
            'parent_id' => null,
            'since' => null,
            'limit' => 50,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $where_values = [];

        if ($args['channel_id']) {
            $where[] = 'channel_id = %d';
            $where_values[] = $args['channel_id'];
        }

        if ($args['recipient_id']) {
            $where[] = '(recipient_id = %d OR (sender_id = %d AND recipient_id = %d))';
            $current_user = get_current_user_id();
            $where_values[] = $current_user;
            $where_values[] = $current_user;
            $where_values[] = $args['recipient_id'];
        }

        if ($args['sender_id']) {
            $where[] = 'sender_id = %d';
            $where_values[] = $args['sender_id'];
        }

        if ($args['parent_id'] !== null) {
            $where[] = 'parent_id = %d';
            $where_values[] = $args['parent_id'];
        }

        if ($args['since']) {
            $where[] = 'sent_at > %s';
            $where_values[] = $args['since'];
        }

        $where[] = 'is_deleted = 0';

        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }

        $query = "SELECT m.*, u.display_name as sender_name, u.user_email as sender_email
                  FROM {$wpdb->prefix}webinocrm_chat_messages m
                  LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY m.sent_at DESC
                  LIMIT %d OFFSET %d";

        $messages = $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset'])
        );

        // Get read receipts
        foreach ($messages as $message) {
            $message->metadata = maybe_unserialize($message->metadata);
            $message->read_by = self::get_message_read_receipts($message->id);
        }

        return array_reverse($messages);
    }

    /**
     * Get single message
     */
    public static function get_message($message_id) {
        global $wpdb;

        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name, u.user_email as sender_email
             FROM {$wpdb->prefix}webinocrm_chat_messages m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.id = %d",
            $message_id
        ));

        if ($message) {
            $message->metadata = maybe_unserialize($message->metadata);
            $message->read_by = self::get_message_read_receipts($message->id);
        }

        return $message;
    }

    /**
     * Create channel
     */
    public static function create_channel($args) {
        global $wpdb;

        $defaults = [
            'name' => '',
            'description' => '',
            'type' => 'public', // public, private, direct
            'created_by' => get_current_user_id(),
            'members' => []
        ];

        $data = wp_parse_args($args, $defaults);

        if (empty($data['name'])) {
            return new WP_Error('empty_name', 'نام کانال خالی است');
        }

        // Insert channel
        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_chat_channels',
            [
                'name' => $data['name'],
                'description' => $data['description'],
                'type' => $data['type'],
                'created_by' => $data['created_by'],
                'created_at' => current_time('mysql'),
                'last_activity' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );

        $channel_id = $wpdb->insert_id;

        // Add members
        $members = array_unique(array_merge([$data['created_by']], (array)$data['members']));
        
        foreach ($members as $user_id) {
            self::add_channel_member($channel_id, $user_id);
        }

        return $channel_id;
    }

    /**
     * Get channels
     */
    public static function get_channels($user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $channels = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as creator_name,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}webinocrm_chat_channel_members 
                     WHERE channel_id = c.id) as member_count,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}webinocrm_chat_messages 
                     WHERE channel_id = c.id AND is_deleted = 0) as message_count
             FROM {$wpdb->prefix}webinocrm_chat_channels c
             LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
             WHERE c.id IN (
                 SELECT channel_id FROM {$wpdb->prefix}webinocrm_chat_channel_members
                 WHERE user_id = %d
             )
             ORDER BY c.last_activity DESC",
            $user_id
        ));

        // Get unread count for each channel
        foreach ($channels as $channel) {
            $channel->unread_count = self::get_unread_count($user_id, $channel->id);
        }

        return $channels;
    }

    /**
     * Get direct chats
     */
    public static function get_direct_chats($user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
                    CASE 
                        WHEN m.sender_id = %d THEN m.recipient_id 
                        ELSE m.sender_id 
                    END as user_id,
                    u.display_name as user_name,
                    u.user_email,
                    MAX(m.sent_at) as last_message_time,
                    (SELECT message FROM {$wpdb->prefix}webinocrm_chat_messages 
                     WHERE (sender_id = %d AND recipient_id = user_id) 
                        OR (sender_id = user_id AND recipient_id = %d)
                     ORDER BY sent_at DESC LIMIT 1) as last_message
             FROM {$wpdb->prefix}webinocrm_chat_messages m
             LEFT JOIN {$wpdb->users} u ON 
                 (CASE WHEN m.sender_id = %d THEN m.recipient_id ELSE m.sender_id END) = u.ID
             WHERE (m.sender_id = %d OR m.recipient_id = %d)
             AND m.channel_id = 0
             AND m.is_deleted = 0
             GROUP BY user_id
             ORDER BY last_message_time DESC",
            $user_id, $user_id, $user_id, $user_id, $user_id, $user_id
        ));

        // Get unread count for each chat
        foreach ($chats as $chat) {
            $chat->unread_count = self::get_unread_count($user_id, 0, $chat->user_id);
        }

        return $chats;
    }

    /**
     * Add channel member
     */
    public static function add_channel_member($channel_id, $user_id) {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'webinocrm_chat_channel_members',
            [
                'channel_id' => $channel_id,
                'user_id' => $user_id,
                'joined_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
    }

    /**
     * Get channel members
     */
    public static function get_channel_members($channel_id) {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}webinocrm_chat_channel_members
             WHERE channel_id = %d",
            $channel_id
        ));
    }

    /**
     * Mark message as read
     */
    public static function mark_as_read($message_id, $user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return $wpdb->insert(
            $wpdb->prefix . 'webinocrm_chat_read_receipts',
            [
                'message_id' => $message_id,
                'user_id' => $user_id,
                'read_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
    }

    /**
     * Mark all messages in channel as read
     */
    public static function mark_channel_as_read($channel_id, $user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $messages = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}webinocrm_chat_messages
             WHERE channel_id = %d
             AND sender_id != %d
             AND id NOT IN (
                 SELECT message_id FROM {$wpdb->prefix}webinocrm_chat_read_receipts
                 WHERE user_id = %d
             )",
            $channel_id, $user_id, $user_id
        ));

        foreach ($messages as $message_id) {
            self::mark_as_read($message_id, $user_id);
        }

        return true;
    }

    /**
     * Get unread count
     */
    public static function get_unread_count($user_id = 0, $channel_id = 0, $sender_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $where = ['m.is_deleted = 0', 'm.sender_id != %d'];
        $where_values = [$user_id];

        if ($channel_id) {
            $where[] = 'm.channel_id = %d';
            $where_values[] = $channel_id;
        }

        if ($sender_id) {
            $where[] = 'm.sender_id = %d';
            $where_values[] = $sender_id;
            $where[] = 'm.recipient_id = %d';
            $where_values[] = $user_id;
        }

        $where_clause = implode(' AND ', $where);
        $where_clause = $wpdb->prepare($where_clause, $where_values);

        return $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}webinocrm_chat_messages m
             WHERE {$where_clause}
             AND m.id NOT IN (
                 SELECT message_id FROM {$wpdb->prefix}webinocrm_chat_read_receipts
                 WHERE user_id = {$user_id}
             )"
        );
    }

    /**
     * Get message read receipts
     */
    private static function get_message_read_receipts($message_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.user_id, r.read_at, u.display_name
             FROM {$wpdb->prefix}webinocrm_chat_read_receipts r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.message_id = %d",
            $message_id
        ), ARRAY_A);
    }

    /**
     * Search messages
     */
    public static function search_messages($query, $channel_id = 0) {
        global $wpdb;

        $where = ['m.is_deleted = 0'];
        $where_values = [];

        $where[] = 'm.message LIKE %s';
        $where_values[] = '%' . $wpdb->esc_like($query) . '%';

        if ($channel_id) {
            $where[] = 'm.channel_id = %d';
            $where_values[] = $channel_id;
        }

        $where_clause = implode(' AND ', $where);
        $where_clause = $wpdb->prepare($where_clause, $where_values);

        return $wpdb->get_results(
            "SELECT m.*, u.display_name as sender_name
             FROM {$wpdb->prefix}webinocrm_chat_messages m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE {$where_clause}
             ORDER BY m.sent_at DESC
             LIMIT 50"
        );
    }

    /**
     * Delete message
     */
    public static function delete_message($message_id, $user_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Check if user is sender
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_chat_messages WHERE id = %d",
            $message_id
        ));

        if (!$message || $message->sender_id != $user_id) {
            return new WP_Error('unauthorized', 'شما مجاز به حذف این پیام نیستید');
        }

        return $wpdb->update(
            $wpdb->prefix . 'webinocrm_chat_messages',
            ['is_deleted' => 1, 'deleted_at' => current_time('mysql')],
            ['id' => $message_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    /**
     * AJAX Handlers
     */
    public function ajax_send_message() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $result = self::send_message([
            'channel_id' => intval($_POST['channel_id'] ?? 0),
            'recipient_id' => intval($_POST['recipient_id'] ?? 0),
            'message' => wp_kses_post($_POST['message'] ?? ''),
            'message_type' => sanitize_key($_POST['message_type'] ?? 'text'),
            'parent_id' => intval($_POST['parent_id'] ?? 0)
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'پیام ارسال شد',
            'message_id' => $result
        ]);
    }

    public function ajax_get_messages() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $messages = self::get_messages([
            'channel_id' => intval($_POST['channel_id'] ?? 0),
            'recipient_id' => intval($_POST['recipient_id'] ?? 0),
            'since' => sanitize_text_field($_POST['since'] ?? null),
            'limit' => intval($_POST['limit'] ?? 50)
        ]);

        wp_send_json_success(['messages' => $messages]);
    }

    public function ajax_get_channels() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $channels = self::get_channels();

        wp_send_json_success(['channels' => $channels]);
    }

    public function ajax_create_channel() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $result = self::create_channel([
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'type' => sanitize_key($_POST['type'] ?? 'public'),
            'members' => $_POST['members'] ?? []
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'کانال ایجاد شد',
            'channel_id' => $result
        ]);
    }

    public function ajax_get_direct_chats() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $chats = self::get_direct_chats();

        wp_send_json_success(['chats' => $chats]);
    }

    public function ajax_mark_as_read() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $message_id = intval($_POST['message_id'] ?? 0);
        $channel_id = intval($_POST['channel_id'] ?? 0);

        if ($channel_id) {
            self::mark_channel_as_read($channel_id);
        } else {
            self::mark_as_read($message_id);
        }

        wp_send_json_success(['message' => 'خوانده شد']);
    }

    public function ajax_get_unread_count() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $count = self::get_unread_count();

        wp_send_json_success(['count' => $count]);
    }

    public function ajax_search_messages() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $query = sanitize_text_field($_POST['query'] ?? '');
        $channel_id = intval($_POST['channel_id'] ?? 0);

        $messages = self::search_messages($query, $channel_id);

        wp_send_json_success(['messages' => $messages]);
    }

    public function ajax_delete_message() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $message_id = intval($_POST['message_id'] ?? 0);

        $result = self::delete_message($message_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'پیام حذف شد']);
    }

    public function ajax_typing_indicator() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $channel_id = intval($_POST['channel_id'] ?? 0);
        $recipient_id = intval($_POST['recipient_id'] ?? 0);
        $user_id = get_current_user_id();

        // Broadcast typing indicator via WebSocket
        $recipients = $channel_id 
            ? self::get_channel_members($channel_id)
            : [$recipient_id];

        WebinoCRM_WebSocket_Handler::broadcast_notification(
            $recipients,
            [
                'type' => 'typing_indicator',
                'user_id' => $user_id,
                'channel_id' => $channel_id
            ]
        );

        wp_send_json_success();
    }
}

