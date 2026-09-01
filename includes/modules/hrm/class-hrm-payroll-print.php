<?php
/**
 * Printable Iranian payslip and employment decree (HTML A4 RTL).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Payroll_Print {

	/**
	 * @param int  $slip_id Slip id.
	 * @param bool $return Return HTML instead of exit.
	 * @return string|WP_Error|void
	 */
	public static function payslip( $slip_id, $return = false ) {
		global $wpdb;
		if ( ! WebinoCRM_Hrm_Service::can_manage_payroll() && ! WebinoCRM_Hrm_Service::is_staff_user() ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز.', 'webinocrm' ), array( 'status' => 403 ) );
		}

		$table = WebinoCRM_Hrm_Service::table( 'payslips' );
		$slip  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $slip_id ), ARRAY_A );
		if ( ! $slip ) {
			return new WP_Error( 'not_found', __( 'فیش حقوق یافت نشد.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_payroll() && (int) $slip['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز.', 'webinocrm' ), array( 'status' => 403 ) );
		}

		$emp = WebinoCRM_Hrm_Payroll_Calculator::employee_snapshot( (int) $slip['user_id'] );
		$run = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WebinoCRM_Hrm_Service::table( 'payroll_runs' ) . ' WHERE id = %d', (int) $slip['run_id'] ), ARRAY_A );
		$cfg = WebinoCRM_Hrm_Payroll_Config::get();
		$wh  = null;
		if ( ! empty( $run['workshop_id'] ) ) {
			$wh = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WebinoCRM_Hrm_Service::table( 'workshops' ) . ' WHERE id = %d', (int) $run['workshop_id'] ), ARRAY_A );
		}
		$decree = null;
		if ( ! empty( $slip['decree_id'] ) ) {
			$decree = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WebinoCRM_Hrm_Service::table( 'employment_decrees' ) . ' WHERE id = %d', (int) $slip['decree_id'] ), ARRAY_A );
		}
		$items = json_decode( (string) ( $slip['items_json'] ?? '' ), true );
		$meta  = json_decode( (string) ( $slip['meta_json'] ?? '' ), true );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$company = $cfg;
		ob_start();
		include WEBINOCRM_PLUGIN_DIR . 'templates/hrm/payslip-print.php';
		$html = ob_get_clean();
		if ( $return ) {
			return $html;
		}
		self::send_html( $html );
	}

	/**
	 * @param int  $decree_id Decree id.
	 * @param bool $return Return HTML.
	 * @return string|WP_Error|void
	 */
	public static function decree( $decree_id, $return = false ) {
		global $wpdb;
		if ( ! WebinoCRM_Hrm_Service::can_manage_payroll() && ! WebinoCRM_Hrm_Service::can_access_hrm_self() ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		$table  = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		$decree = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $decree_id ), ARRAY_A );
		if ( ! $decree ) {
			return new WP_Error( 'not_found', __( 'حکم کارگزینی یافت نشد.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_payroll() && (int) $decree['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		$emp     = WebinoCRM_Hrm_Payroll_Calculator::employee_snapshot( (int) $decree['user_id'] );
		$company = WebinoCRM_Hrm_Payroll_Config::get();
		$wh      = ! empty( $decree['workshop_id'] )
			? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WebinoCRM_Hrm_Service::table( 'workshops' ) . ' WHERE id = %d', (int) $decree['workshop_id'] ), ARRAY_A )
			: null;
		ob_start();
		include WEBINOCRM_PLUGIN_DIR . 'templates/hrm/decree-print.php';
		$html = ob_get_clean();
		if ( $return ) {
			return $html;
		}
		self::send_html( $html );
	}

	/**
	 * @param string $html HTML.
	 */
	private static function send_html( $html ) {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param float|int|string $n Number.
	 * @return string
	 */
	public static function money( $n ) {
		return number_format( (float) $n, 0, '.', ',' );
	}
}
