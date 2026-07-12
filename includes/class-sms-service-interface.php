<?php
/**
 * WebinoCRM SMS Service Interface
 *
 * Defines a standard contract for all SMS provider handlers.
 * Any new SMS provider class must implement this interface.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

interface WebinoCRM_SMS_Service_Interface {

    /**
     * Sends an SMS message.
     *
     * While different providers might have different features (like pattern SMS vs. simple text),
     * this interface ensures a unified method call. The implementation within each class
     * will handle its specific logic.
     *
     * @param string $to The recipient's mobile number.
     * @param string $message The text message or pattern code.
     * @param array $params Optional parameters, primarily for pattern-based services.
     * @return bool True on success, false on failure.
     */
    public function send_sms( $to, $message, $params = [] );
}