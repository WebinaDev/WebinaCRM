<?php
/**
 * WebinoCRM Elementor Pro Form Integration
 *
 * Registers the "ارسال به CRM" form action. Users add it via:
 * Form widget → Actions After Submit → Add Item → ارسال به CRM (WebinoCRM)
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WebinoCRM_Elementor_Lead_Integration {

    public function __construct() {
        add_action( 'elementor_pro/forms/actions/register', [ $this, 'register_form_action' ] );
    }

    /**
     * Registers the WebinoCRM form action.
     */
    public function register_form_action( $form_actions_registrar ) {
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-elementor-form-action.php';
        $form_actions_registrar->register( new WebinoCRM_Elementor_Form_Action() );
    }
}
