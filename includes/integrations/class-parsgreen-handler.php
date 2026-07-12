<?php
/**
 * WebinoCRM ParsGreen SMS Handler
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_ParsGreen_Handler implements WebinoCRM_SMS_Service_Interface {
    private $signature;
    private $from;
    private $api_url = 'http://sms.parsgreen.ir/Api/SendSMS.asmx?WSDL';

    /**
     * Constructor.
     */
    public function __construct( $signature, $from ) {
        $this->signature = $signature;
        $this->from = $from;
    }

    /**
     * Implements the send_sms method from the interface for simple text sending.
     *
     * @param string $to The recipient's mobile number.
     * @param string $message The text message to send.
     * @param array $params This parameter is ignored for ParsGreen simple text sending.
     * @return bool True on success, false on failure.
     */
    public function send_sms( $to, $message, $params = [] ) {
        if ( empty( $this->signature ) || empty( $this->from ) ) {
            webinocrm_integration_log( 'ParsGreen: Signature or sender number is not configured.' );
            return false;
        }

        if ( ! class_exists( 'SoapClient' ) ) {
            webinocrm_integration_log( 'ParsGreen: PHP SOAP extension not available (SoapClient missing).' );
            // Set a transient to show an admin notice
            set_transient('webino_soap_not_enabled', true, DAY_IN_SECONDS);
            return false;
        }
        
        // If the SOAP client exists, we can clear any previous notice
        delete_transient('webino_soap_not_enabled');

        // Convert phone number to English digits
        $to = webino_convert_phone_to_english($to);

        $message_utf8 = mb_convert_encoding($message, "UTF-8");

        $parameters = [
            'signature' => $this->signature,
            'from'      => $this->from,
            'to'        => [ $to ],
            'text'      => $message_utf8,
            'isFlash'   => false,
            'udh'       => ''
        ];


        try {
            $client   = new SoapClient( $this->api_url, ['encoding' => 'UTF-8']);
            $response = $client->SendGroupSmsSimple( $parameters );

            if ( isset($response->SendGroupSmsSimpleResult) && $response->SendGroupSmsSimpleResult > 0 ) {
                return true;
            }
            
            webinocrm_integration_log( 'ParsGreen API error code: ' . ($response->SendGroupSmsSimpleResult ?? 'UNKNOWN') );
            return false;
        } catch ( SoapFault $ex ) {
            webinocrm_integration_log( 'ParsGreen SoapFault: ' . $ex->faultstring );
            return false;
        }
    }
}