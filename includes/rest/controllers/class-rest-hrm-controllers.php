<?php
/**
 * HRM REST API routes.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_REST_Hrm_Controller extends WebinoCRM_REST_Controller_Base {

	public static function register_routes() {
		$cap_staff = 'staff';

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/staff', array( 'WebinoCRM_Hrm_Staff_Service', 'list' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/staff', array( 'WebinoCRM_Hrm_Staff_Service', 'save' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/hrm/staff/(?P<id>\d+)', array( 'WebinoCRM_Hrm_Staff_Service', 'delete' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/staff/(?P<id>\d+)/profile', array( 'WebinoCRM_Hrm_Staff_Service', 'get_profile' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/staff/(?P<id>\d+)/profile', array( 'WebinoCRM_Hrm_Staff_Service', 'save_profile' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/org-positions', array( 'WebinoCRM_Hrm_Staff_Service', 'org_positions_list' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/org-positions', array( 'WebinoCRM_Hrm_Staff_Service', 'org_position_save' ), $cap_staff );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/hrm/org-positions/(?P<id>\d+)', array( 'WebinoCRM_Hrm_Staff_Service', 'org_position_delete' ), $cap_staff );

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/attendance', array( 'WebinoCRM_Hrm_Attendance_Service', 'list' ), 'hrm-attendance' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/attendance', array( 'WebinoCRM_Hrm_Attendance_Service', 'save' ), 'hrm-attendance' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/attendance/check-in', array( 'WebinoCRM_Hrm_Attendance_Service', 'check_in' ), 'hrm-attendance' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/attendance/check-out', array( 'WebinoCRM_Hrm_Attendance_Service', 'check_out' ), 'hrm-attendance' );

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/leave/types', array( 'WebinoCRM_Hrm_Leave_Service', 'types_list' ), 'hrm-leave' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/leave/types', array( 'WebinoCRM_Hrm_Leave_Service', 'types_save' ), 'hrm-leave' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/leave/requests', array( 'WebinoCRM_Hrm_Leave_Service', 'requests_list' ), 'hrm-leave' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/leave/requests', array( 'WebinoCRM_Hrm_Leave_Service', 'request_save' ), 'hrm-leave' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/leave/requests/(?P<id>\d+)/approve', array( 'WebinoCRM_Hrm_Leave_Service', 'request_approve' ), 'hrm-leave' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/leave/requests/(?P<id>\d+)/reject', array( 'WebinoCRM_Hrm_Leave_Service', 'request_reject' ), 'hrm-leave' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/leave/balances', array( 'WebinoCRM_Hrm_Leave_Service', 'balances_list' ), 'hrm-leave' );

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/payroll/settings', array( 'WebinoCRM_Hrm_Payroll_Service', 'settings_get' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/payroll/settings', array( 'WebinoCRM_Hrm_Payroll_Service', 'settings_save' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/payroll/components', array( 'WebinoCRM_Hrm_Payroll_Service', 'components_list' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/payroll/components', array( 'WebinoCRM_Hrm_Payroll_Service', 'components_save' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/payroll/employee-salaries', array( 'WebinoCRM_Hrm_Payroll_Service', 'employee_salary_get' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/payroll/employee-salaries', array( 'WebinoCRM_Hrm_Payroll_Service', 'employee_salary_save' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/payroll/runs', array( 'WebinoCRM_Hrm_Payroll_Service', 'runs_list' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/payroll/runs', array( 'WebinoCRM_Hrm_Payroll_Service', 'run_save' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/payroll/runs/(?P<id>\d+)', array( 'WebinoCRM_Hrm_Payroll_Service', 'run_get' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/payroll/runs/(?P<id>\d+)/calculate', array( 'WebinoCRM_Hrm_Payroll_Service', 'run_calculate' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/payroll/runs/(?P<id>\d+)/approve', array( 'WebinoCRM_Hrm_Payroll_Service', 'run_approve' ), 'hrm-payroll' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/payroll/runs/(?P<id>\d+)/payslips', array( 'WebinoCRM_Hrm_Payroll_Service', 'payslips_list' ), 'hrm-payroll' );

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/recruitment/postings', array( 'WebinoCRM_Hrm_Recruitment_Service', 'job_postings_list' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/recruitment/postings', array( 'WebinoCRM_Hrm_Recruitment_Service', 'job_postings_save' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/recruitment/applicants', array( 'WebinoCRM_Hrm_Recruitment_Service', 'applicants_list' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/recruitment/applicants', array( 'WebinoCRM_Hrm_Recruitment_Service', 'applicants_save' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/hrm/recruitment/applicants/(?P<id>\d+)', array( 'WebinoCRM_Hrm_Recruitment_Service', 'applicants_delete' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/recruitment/applicants/(?P<id>\d+)/hire', array( 'WebinoCRM_Hrm_Recruitment_Service', 'applicant_hire' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/recruitment/interviews', array( 'WebinoCRM_Hrm_Recruitment_Service', 'interviews_list' ), 'hrm-recruitment' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/recruitment/interviews', array( 'WebinoCRM_Hrm_Recruitment_Service', 'interviews_save' ), 'hrm-recruitment' );

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/performance/kpi-templates', array( 'WebinoCRM_Hrm_Performance_Service', 'kpi_templates_list' ), 'hrm-performance' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/performance/kpi-templates', array( 'WebinoCRM_Hrm_Performance_Service', 'kpi_templates_save' ), 'hrm-performance' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/performance/cycles', array( 'WebinoCRM_Hrm_Performance_Service', 'cycles_list' ), 'hrm-performance' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/performance/cycles', array( 'WebinoCRM_Hrm_Performance_Service', 'cycles_save' ), 'hrm-performance' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/performance/reviews', array( 'WebinoCRM_Hrm_Performance_Service', 'reviews_list' ), 'hrm-performance' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/performance/reviews', array( 'WebinoCRM_Hrm_Performance_Service', 'reviews_save' ), 'hrm-performance' );

		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/training/courses', array( 'WebinoCRM_Hrm_Training_Service', 'courses_list' ), 'hrm-training' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/training/courses', array( 'WebinoCRM_Hrm_Training_Service', 'courses_save' ), 'hrm-training' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/training/sessions', array( 'WebinoCRM_Hrm_Training_Service', 'sessions_list' ), 'hrm-training' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/training/sessions', array( 'WebinoCRM_Hrm_Training_Service', 'sessions_save' ), 'hrm-training' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/hrm/training/enrollments', array( 'WebinoCRM_Hrm_Training_Service', 'enrollments_list' ), 'hrm-training' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/hrm/training/enrollments', array( 'WebinoCRM_Hrm_Training_Service', 'enrollments_save' ), 'hrm-training' );
	}
}
