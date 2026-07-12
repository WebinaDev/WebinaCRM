<?php
/**
 * WebinoCRM Telegram Handler
 * Sends notifications via a Telegram Bot.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Telegram_Handler {
    private $bot_token;
    private $chat_id;
    private $api_base_url = 'https://api.telegram.org/bot';

    /**
     * Constructor.
     * @param string $bot_token The Telegram Bot API token.
     * @param string $chat_id The target chat/channel ID.
     */
    public function __construct( $bot_token, $chat_id ) {
        $this->bot_token = $bot_token;
        $this->chat_id = $chat_id;
    }
    
    /**
     * Sends a message to the specified chat ID.
     *
     * @param string $message The message text to send. Supports basic HTML.
     * @return bool True on success, false on failure.
     */
    public function send_message( $message ) {
        if ( empty($this->bot_token) || empty($this->chat_id) ) {
            webinocrm_integration_log('Telegram: Bot Token or Chat ID is not configured.');
            return false;
        }

        $api_url = $this->api_base_url . $this->bot_token . '/sendMessage';
        
        $data = [
            'chat_id'    => $this->chat_id,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ];
        
        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => json_encode($data),
            'timeout' => 15,
        ];

        $response = wp_remote_post( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            webinocrm_integration_log('Telegram HTTP: ' . $response->get_error_message());
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body);

        if (isset($result->ok) && $result->ok === true) {
            return true;
        } else {
            webinocrm_integration_log('Telegram API: ' . ($result->description ?? 'Unknown error'));
            return false;
        }
    }
}