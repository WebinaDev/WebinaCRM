<?php
/**
 * Dashboard service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Dashboard_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function summary( array $params ) {
		$denied = self::require_system_manager();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		if ( ! class_exists( 'WebinoCRM_Dashboard_Stats' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-stats.php';
		}
		return WebinoCRM_Service_Base::success( WebinoCRM_Dashboard_Stats::get_system_manager_stats() );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function full( array $params ) {
		$denied = self::require_system_manager();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		if ( ! class_exists( 'WebinoCRM_Dashboard_Stats' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-stats.php';
		}
		if ( ! class_exists( 'WebinoCRM_Term_Helper' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-term-helper.php';
		}
		try {
			return WebinoCRM_Service_Base::success( WebinoCRM_Dashboard_Stats::get_system_manager_dashboard_full() );
		} catch ( Throwable $e ) {
			if ( function_exists( 'webinocrm_integration_log' ) ) {
				webinocrm_integration_log( 'Dashboard full error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			}
			return WebinoCRM_Service_Base::error(
				__( 'خطا در بارگذاری داشبورد.', 'webinocrm' ),
				500
			);
		}
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function team_stats( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! is_user_logged_in() ) {
			return WebinoCRM_Service_Base::error( __( 'لطفاً وارد شوید.', 'webinocrm' ) );
		}
		$roles = (array) wp_get_current_user()->roles;
		if ( ! in_array( 'team_member', $roles, true ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		if ( ! class_exists( 'WebinoCRM_Dashboard_Stats' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-stats.php';
		}
		return WebinoCRM_Service_Base::success( WebinoCRM_Dashboard_Stats::get_team_member_stats() );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function client_stats( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! is_user_logged_in() ) {
			return WebinoCRM_Service_Base::error( __( 'لطفاً وارد شوید.', 'webinocrm' ) );
		}
		$roles = (array) wp_get_current_user()->roles;
		$is_client = in_array( 'customer', $roles, true ) || in_array( 'client', $roles, true );
		if ( ! $is_client ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		if ( ! class_exists( 'WebinoCRM_Dashboard_Stats' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-stats.php';
		}
		return WebinoCRM_Service_Base::success( WebinoCRM_Dashboard_Stats::get_client_stats() );
	}

	/**
	 * @return array<string,mixed>|true
	 */
	private static function require_system_manager() {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! is_user_logged_in() ) {
			return WebinoCRM_Service_Base::error( __( 'لطفاً وارد شوید.', 'webinocrm' ) );
		}
		$roles = (array) wp_get_current_user()->roles;
		$is_manager = in_array( 'administrator', $roles, true )
			|| in_array( 'system_manager', $roles, true )
			|| in_array( 'finance_manager', $roles, true );
		if ( ! $is_manager ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		return true;
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_dashboard_stats' => array( __CLASS__, 'summary' ),
				'webinocrm_get_dashboard_full' => array( __CLASS__, 'full' ),
				'webinocrm_get_team_member_stats' => array( __CLASS__, 'team_stats' ),
				'webinocrm_get_client_stats' => array( __CLASS__, 'client_stats' ),
			)
		);
	}
}
