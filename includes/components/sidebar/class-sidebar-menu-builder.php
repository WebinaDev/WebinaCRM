<?php
/**
 * Sidebar Menu Builder
 *
 * Builds menu structure based on user role.
 * Menu is organized in ERP modules (see WebinoCRM_Erp_Module_Registry).
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Menu Builder class
 */
class WebinoCRM_Sidebar_Menu_Builder {

	/**
	 * Menu items cache
	 *
	 * @var array
	 */
	private static $menu_cache = array();

	/**
	 * Check if a module is enabled.
	 *
	 * @param string $module_key ERP settings_key or legacy key (dashboard, projects, crm, accounting, …).
	 * @return bool
	 */
	public static function is_module_enabled( $module_key ) {
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			if ( WebinoCRM_Erp_Module_Registry::is_module_enabled( $module_key ) ) {
				return true;
			}
			// Legacy-only keys not in ERP registry.
			$erp_keys = array( 'hrm', 'finance', 'crm', 'pm', 'scm', 'sales', 'mfg', 'docs', 'distribution', 'admin', 'ai_content' );
			if ( in_array( $module_key, $erp_keys, true ) ) {
				return false;
			}
		}

		$settings = class_exists( 'WebinoCRM_Settings_Handler' ) ? WebinoCRM_Settings_Handler::get_all_settings() : array();
		$key      = 'module_' . $module_key . '_enabled';
		return isset( $settings[ $key ] ) ? ( (string) $settings[ $key ] === '1' ) : true;
	}

	/**
	 * Build menu for specific user role
	 *
	 * @param string $user_role User role.
	 * @return array Menu items array.
	 */
	public static function build_menu( $user_role = '' ) {
		if ( empty( $user_role ) ) {
			$user_role = self::get_current_user_role();
		}

		$cache_key = 'menu_' . $user_role;
		if ( isset( self::$menu_cache[ $cache_key ] ) ) {
			return self::$menu_cache[ $cache_key ];
		}

		$menu_items = array();

		switch ( $user_role ) {
			case 'system_manager':
			case 'administrator':
				$menu_items = self::get_manager_menu();
				break;
			case 'sales_consultant':
				$menu_items = self::get_sales_consultant_menu();
				break;
			case 'finance_manager':
				$menu_items = self::get_finance_menu();
				break;
			case 'team_member':
				$menu_items = self::get_team_member_menu();
				break;
			case 'client':
			case 'customer':
				$menu_items = self::get_customer_menu();
				break;
			default:
				$menu_items = array();
				break;
		}

		$menu_items = apply_filters( 'webinocrm_sidebar_menu_items', $menu_items, $user_role );
		self::$menu_cache[ $cache_key ] = $menu_items;

		return $menu_items;
	}

	/**
	 * Get manager menu items (ERP modules + pinned dashboard).
	 *
	 * @return array Menu items with categories.
	 */
	private static function get_manager_menu() {
		$dashboard_url = home_url( '/dashboard' );
		$items         = array();

		if ( self::is_module_enabled( 'dashboard' ) ) {
			$items[] = array(
				'id'     => 'dashboard',
				'title'  => __( 'داشبورد', 'webinocrm' ),
				'url'    => $dashboard_url,
				'icon'   => 'ri-home-4-line',
				'pinned' => true,
			);
			$items[] = array(
				'id'     => 'reports',
				'title'  => __( 'گزارشات کلی', 'webinocrm' ),
				'url'    => $dashboard_url . '/reports',
				'icon'   => 'ri-bar-chart-box-line',
				'pinned' => true,
			);
		}

		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			$items = WebinoCRM_Erp_Module_Registry::append_manager_module_menus( $dashboard_url, $items );
		}

		return $items;
	}

	/**
	 * Get sales consultant menu items
	 *
	 * @return array Menu items.
	 */
	private static function get_sales_consultant_menu() {
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			return WebinoCRM_Erp_Module_Registry::filter_menu_for_role( 'sales_consultant', self::get_manager_menu() );
		}
		return array();
	}

	/**
	 * Get finance manager menu items
	 *
	 * @return array Menu items.
	 */
	private static function get_finance_menu() {
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			return WebinoCRM_Erp_Module_Registry::filter_menu_for_role( 'finance_manager', self::get_manager_menu() );
		}
		return array();
	}

	/**
	 * Get team member menu items
	 *
	 * @return array Menu items.
	 */
	private static function get_team_member_menu() {
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			return WebinoCRM_Erp_Module_Registry::filter_menu_for_role( 'team_member', self::get_manager_menu() );
		}
		return array();
	}

	/**
	 * Get customer menu items
	 *
	 * @return array Menu items.
	 */
	private static function get_customer_menu() {
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			return WebinoCRM_Erp_Module_Registry::filter_menu_for_role( 'customer', self::get_manager_menu() );
		}
		return array();
	}

	/**
	 * Get current user role
	 *
	 * @return string User role.
	 */
	private static function get_current_user_role() {
		if ( ! is_user_logged_in() ) {
			return 'guest';
		}

		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		if ( in_array( 'administrator', $roles, true ) || in_array( 'system_manager', $roles, true ) ) {
			return 'system_manager';
		}
		if ( in_array( 'finance_manager', $roles, true ) ) {
			return 'finance_manager';
		}
		if ( in_array( 'team_member', $roles, true ) ) {
			return 'team_member';
		}
		if ( in_array( 'sales_consultant', $roles, true ) ) {
			return 'sales_consultant';
		}
		if ( in_array( 'customer', $roles, true ) || in_array( 'client', $roles, true ) ) {
			return 'customer';
		}

		return 'guest';
	}

	/**
	 * Clear menu cache
	 */
	public static function clear_cache() {
		self::$menu_cache = array();
	}
}
