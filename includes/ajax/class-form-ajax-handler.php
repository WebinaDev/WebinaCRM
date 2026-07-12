<?php
/**
 * WebinoCRM Form, Invoice & Appointment AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/invoices for proforma CRUD.
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Form_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        // --- Form & Invoice Actions ---
        add_action('wp_ajax_webino_manage_pro_invoice', [$this, 'ajax_manage_pro_invoice']);
        add_action('wp_ajax_webino_generate_pro_invoice_pdf', [$this, 'ajax_generate_pro_invoice_pdf']);
        add_action('wp_ajax_webinocrm_get_invoices', [$this, 'ajax_get_invoices']);
        add_action('wp_ajax_webinocrm_get_invoice', [$this, 'ajax_get_invoice']);

        // Appointment actions: WebinoCRM_Appointment_Ajax_Handler only (avoid duplicate hooks).
    }

    private function notify_all_admins($title, $args) {
        $admins = get_users(['role__in' => ['administrator', 'system_manager'], 'fields' => 'ID']);
        foreach ($admins as $admin_id) {
            $notification_args = array_merge($args, ['user_id' => $admin_id]);
            WebinoCRM_Logger::add($title, $notification_args);
        }
    }

    public function ajax_manage_pro_invoice() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'save' ) );
    }
    
    public function ajax_get_invoices() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'list' ) );
    }

    public function ajax_get_invoice() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'get' ) );
    }

    public function ajax_generate_pro_invoice_pdf() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'generate_pdf' ) );
    }
    
}