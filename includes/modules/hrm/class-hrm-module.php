<?php
/**
 * HRM module bootstrap.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Module {

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'WebinoCRM_Sidebar_Menu_Builder' ) ) {
			return true;
		}
		return WebinoCRM_Sidebar_Menu_Builder::is_module_enabled( 'hrm' );
	}

	/**
	 * Load HRM domain classes.
	 */
	public static function load() {
		if ( ! self::is_enabled() ) {
			return;
		}
		$dir = WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/';
		require_once $dir . 'class-hrm-profile-fields.php';
		require_once $dir . 'class-hrm-payroll-calculator.php';
		require_once $dir . 'class-hrm-payroll-finance-integration.php';
	}
}
