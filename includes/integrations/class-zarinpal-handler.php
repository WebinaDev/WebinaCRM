<?php
/**
 * Zarinpal Payment Gateway Handler
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Zarinpal_Handler {
    
    private $merchant_id;
    private $sandbox;
    private $request_api_url;
    private $verify_api_url;
    private $payment_url;

    public function __construct($merchant_id, $sandbox = false) {
        $this->merchant_id = $merchant_id;
        $this->sandbox = $sandbox;
        
        if ($sandbox) {
            $this->request_api_url = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';
            $this->verify_api_url = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';
            $this->payment_url = 'https://sandbox.zarinpal.com/pg/StartPay/';
        } else {
            $this->request_api_url = 'https://api.zarinpal.com/pg/v4/payment/request.json';
            $this->verify_api_url = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
            $this->payment_url = 'https://www.zarinpal.com/pg/StartPay/';
        }
    }

    /**
     * Request Payment
     */
    public function request_payment($amount, $description, $callback_url, $email = '', $mobile = '') {
        $data = [
            'merchant_id' => $this->merchant_id,
            'amount' => $amount * 10, // Convert Toman to Rial
            'description' => $description,
            'callback_url' => $callback_url
        ];
        
        if ($email) $data['metadata']['email'] = $email;
        if ($mobile) $data['metadata']['mobile'] = $mobile;

        $response = wp_remote_post($this->request_api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'خطا در اتصال به درگاه پرداخت: ' . $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['data']['authority']) && !empty($body['data']['code']) && $body['data']['code'] == 100) {
            return [
                'success' => true,
                'authority' => $body['data']['authority'],
                'payment_url' => $this->payment_url . $body['data']['authority']
            ];
        }

        return [
            'success' => false,
            'message' => $body['errors']['message'] ?? 'خطا در ایجاد درخواست پرداخت.'
        ];
    }

    /**
     * Verify Payment
     */
    public function verify_payment($authority, $amount) {
        $data = [
            'merchant_id' => $this->merchant_id,
            'authority' => $authority,
            'amount' => $amount * 10 // Convert Toman to Rial
        ];

        $response = wp_remote_post($this->verify_api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'خطا در تایید پرداخت: ' . $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['data']['code']) && $body['data']['code'] == 100) {
            return [
                'success' => true,
                'ref_id' => $body['data']['ref_id'],
                'card_pan' => $body['data']['card_pan'] ?? '',
                'card_hash' => $body['data']['card_hash'] ?? ''
            ];
        }

        return [
            'success' => false,
            'message' => $body['errors']['message'] ?? 'تایید پرداخت ناموفق بود.'
        ];
    }
}
