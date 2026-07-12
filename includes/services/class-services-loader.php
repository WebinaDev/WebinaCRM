<?php
/**
 * Loads CRM service classes and registers action map.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service bootstrap.
 */
class WebinoCRM_Services_Loader {

	/**
	 * @return void
	 */
	public static function init() {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-service-base.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-service-action-map.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-domain-service-trait.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/controllers/class-rest-controller-base.php';

		$services = array(
			'class-project-service.php',
			'class-contract-service.php',
			'class-dashboard-service.php',
			'class-settings-crm-service.php',
			'class-profile-service.php',
			'class-tasks-service.php',
			'class-tickets-service.php',
			'class-leads-service.php',
			'class-import-export-service.php',
			'class-customers-service.php',
			'class-appointments-service.php',
			'class-invoices-service.php',
			'class-consultations-service.php',
			'class-campaigns-service.php',
			'class-crm-services-module-service.php',
			'class-reports-service.php',
			'class-logs-service.php',
			'class-visitor-service.php',
			'class-licenses-service.php',
			'class-marketplace-service.php',
			'class-modirpayamak-service.php',
			'class-accounting-service.php',
			'class-warehouse-service.php',
			'class-auth-service.php',
			'class-chat-service.php',
			'class-realtime-service.php',
			'class-documents-service.php',
			'class-time-tracking-service.php',
			'class-core-update-service.php',
			'class-hrm-service.php',
			'class-hrm-staff-service.php',
			'class-hrm-attendance-service.php',
			'class-hrm-leave-service.php',
			'class-hrm-payroll-service.php',
			'class-hrm-recruitment-service.php',
			'class-hrm-performance-service.php',
			'class-hrm-training-service.php',
		);

		foreach ( $services as $file ) {
			$path = WEBINOCRM_PLUGIN_DIR . 'includes/services/' . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}

		$register = array(
			'WebinoCRM_Project_Service',
			'WebinoCRM_Contract_Service',
			'WebinoCRM_Dashboard_Service',
			'WebinoCRM_Settings_Crm_Service',
			'WebinoCRM_Profile_Service',
			'WebinoCRM_Tasks_Service',
			'WebinoCRM_Tickets_Service',
			'WebinoCRM_Leads_Service',
			'WebinoCRM_Customers_Service',
			'WebinoCRM_Appointments_Service',
			'WebinoCRM_Invoices_Service',
			'WebinoCRM_Consultations_Service',
			'WebinoCRM_Campaigns_Service',
			'WebinoCRM_Crm_Services_Module_Service',
			'WebinoCRM_Reports_Service',
			'WebinoCRM_Logs_Service',
			'WebinoCRM_Visitor_Service',
			'WebinoCRM_Licenses_Service',
			'WebinoCRM_Marketplace_Service',
			'WebinoCRM_Modirpayamak_Service',
			'WebinoCRM_Accounting_Service',
			'WebinoCRM_Auth_Service',
			'WebinoCRM_Chat_Service',
			'WebinoCRM_Documents_Service',
			'WebinoCRM_Time_Tracking_Service',
			'WebinoCRM_Core_Update_Service',
			'WebinoCRM_Hrm_Service',
			'WebinoCRM_Hrm_Staff_Service',
			'WebinoCRM_Hrm_Attendance_Service',
			'WebinoCRM_Hrm_Leave_Service',
			'WebinoCRM_Hrm_Payroll_Service',
			'WebinoCRM_Hrm_Recruitment_Service',
			'WebinoCRM_Hrm_Performance_Service',
			'WebinoCRM_Hrm_Training_Service',
		);

		foreach ( $register as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'register_actions' ) ) {
				call_user_func( array( $class, 'register_actions' ) );
			}
		}
	}
}
