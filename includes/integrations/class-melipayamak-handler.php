<?php
/**
 * WebinoCRM Melipayamak SMS Handler
 * Implements the SMS Service Interface using the legacy REST API (username/password).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure the interface is loaded before implementing it
if ( ! interface_exists('WebinoCRM_SMS_Service_Interface') ) {
    require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-sms-service-interface.php';
}

class CSM_Melipayamak_Handler implements WebinoCRM_SMS_Service_Interface {
    private $username;
    private $password;
    private $sender_number;
    private $api_base_url = 'https://rest.payamak-panel.com/api/SendSMS/%s';

    public function __construct( $username, $password, $sender_number ) {
        $this->username = $username;
        $this->password = $password;
        $this->sender_number = $sender_number;
    }
    
    /**
     * Sends an SMS message.
     * If $params is provided, it sends a pattern-based SMS (BaseServiceNumber).
     * Otherwise, it sends a simple text message (SendSMS).
     *
     * @param string $to The recipient's mobile number.
     * @param string $message The text message OR the bodyId for pattern.
     * @param array $params The parameters for pattern-based SMS.
     * @return bool True on success, false on failure.
     */
    public function send_sms( $to, $message, $params = [] ) {
        if (empty($this->sender_number) || empty($this->username) || empty($this->password)) {
            webinocrm_integration_log('Melipayamak: Username, Password, or Sender not configured.');
            return false;
        }

        // Convert phone number to English digits
        $to = webino_convert_phone_to_english($to);

        $data = [];
        $endpoint = '';

        // Check if this is a pattern-based send or a simple send
        if (!empty($params)) {
            // --- This is a pattern-based SMS ---
            $endpoint = 'BaseServiceNumber';
            $bodyId = $message;
            $text = '';

            // The legacy API expects parameters to be a single string separated by ';'
            if (isset($params['amount'])) {
                $text = (string) $params['amount'];
            }
            
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'to'       => $to,
                'bodyId'   => $bodyId,
                'text'     => $text,
            ];

        } else {
            // --- This is a simple text SMS ---
            $endpoint = 'SendSMS';
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'to'       => $to,
                'from'     => $this->sender_number,
                'text'     => $message,
                'isflash'  => false
            ];
        }
        
        $api_url = sprintf($this->api_base_url, $endpoint);
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($data),
            'timeout' => 30,
        ];

        $response = wp_remote_post( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            webinocrm_integration_log('Melipayamak HTTP: ' . $response->get_error_message());
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body);

        if (isset($result->Value) && is_numeric($result->Value) && $result->Value > 0) {
            return true;
        } else {
            webinocrm_integration_log('Melipayamak API error: ' . $response_body);
            return false;
        }
    }
}