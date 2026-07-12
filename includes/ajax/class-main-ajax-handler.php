<?php
/**
 * WebinoCRM Main AJAX Handler
 *
 * This class instantiates all other AJAX handlers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Main_Ajax_Handler {

    public function __construct() {
        $this->load_dependencies();
        $this->define_hooks();
    }

    private function load_dependencies() {
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-services-loader.php';
        WebinoCRM_Services_Loader::init();
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-error-codes.php'; // <-- خط جدید
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/trait-ajax-service-delegate.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-user-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-project-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-task-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-ticket-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-notification-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-workflow-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-lead-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-form-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-agile-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-consultation-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-sms-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-settings-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-pdf-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-appointment-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-attachment-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-modal-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-email-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-payment-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-import-export-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-dashboard-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-services-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-campaign-ajax-handler.php';
    }

    private function define_hooks() {
        new WebinoCRM_User_Ajax_Handler();
        new WebinoCRM_Project_Ajax_Handler();
        new WebinoCRM_Task_Ajax_Handler();
        new WebinoCRM_Ticket_Ajax_Handler();
        new WebinoCRM_Notification_Ajax_Handler();
        new WebinoCRM_Workflow_Ajax_Handler();
        new WebinoCRM_Lead_Ajax_Handler();
        new WebinoCRM_Form_Ajax_Handler();
        new WebinoCRM_Agile_Ajax_Handler();
        new WebinoCRM_Consultation_Ajax_Handler();
        new WebinoCRM_SMS_Ajax_Handler();
        new WebinoCRM_Settings_Ajax_Handler();
        new WebinoCRM_PDF_Ajax_Handler();
        new WebinoCRM_Appointment_Ajax_Handler();
        new WebinoCRM_Attachment_Ajax_Handler();
        new WebinoCRM_Modal_Ajax_Handler();
        new WebinoCRM_Email_Ajax_Handler();
        new WebinoCRM_Payment_Ajax_Handler();
        new WebinoCRM_Import_Export_Ajax_Handler();
        new WebinoCRM_Dashboard_Ajax_Handler();
        new WebinoCRM_Services_Ajax_Handler();
        new WebinoCRM_Campaign_Ajax_Handler();
    }
}