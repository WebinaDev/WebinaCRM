<?php
/**
 * HRM shared helpers and permissions.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Service {

	const STAFF_ROLES = array( 'system_manager', 'finance_manager', 'team_member', 'administrator' );

	/**
	 * @return bool
	 */
	public static function can_manage_hrm() {
		return current_user_can( 'manage_options' )
			|| ( function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'system_manager', 'administrator' ) ) );
	}

	/**
	 * @return bool
	 */
	public static function can_manage_payroll() {
		return self::can_manage_hrm()
			|| ( function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'finance_manager' ) ) );
	}

	/**
	 * @param int|null $user_id User id.
	 * @return bool
	 */
	public static function is_staff_user( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return (bool) array_intersect( self::STAFF_ROLES, (array) $user->roles );
	}

	/**
	 * @param string $role Role slug.
	 * @return bool
	 */
	public static function is_staff_role( $role ) {
		return in_array( (string) $role, self::STAFF_ROLES, true );
	}

	/**
	 * Department ids the user manages.
	 *
	 * @param int|null $user_id User id.
	 * @return array<int, int>
	 */
	public static function managed_department_ids( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$ids     = get_user_meta( $user_id, '_department_manager_dept_ids', true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}

	/**
	 * @param int $target_user_id Staff user id.
	 * @return bool
	 */
	/**
	 * Employee portal / hrm-self REST and menu access.
	 *
	 * @param int|null $user_id User id.
	 * @return bool
	 */
	public static function can_access_hrm_self( $user_id = null ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( self::can_manage_hrm() ) {
			return true;
		}
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( self::is_staff_user( $user_id ) ) {
			return true;
		}
		return ! empty( self::managed_department_ids( $user_id ) );
	}

	/**
	 * Mask national id for employee-facing views.
	 *
	 * @param string $national_id Raw national id.
	 * @return string
	 */
	public static function mask_national_id( $national_id ) {
		$digits = preg_replace( '/\D/', '', (string) $national_id );
		if ( strlen( $digits ) < 8 ) {
			return '****';
		}
		$masked = substr( $digits, 0, 3 ) . '****' . substr( $digits, -4 );
		if ( function_exists( 'webino_to_persian_digits' ) ) {
			return webino_to_persian_digits( $masked );
		}
		return $masked;
	}

	public static function can_manage_staff_user( $target_user_id ) {
		if ( self::can_manage_hrm() ) {
			return true;
		}
		$dept_ids = self::managed_department_ids();
		if ( empty( $dept_ids ) || ! taxonomy_exists( 'organizational_position' ) ) {
			return (int) $target_user_id === get_current_user_id();
		}
		$positions = wp_get_object_terms( (int) $target_user_id, 'organizational_position' );
		if ( is_wp_error( $positions ) || empty( $positions ) ) {
			return false;
		}
		$pos = $positions[0];
		$dept = (int) $pos->parent > 0 ? (int) $pos->parent : (int) $pos->term_id;
		return in_array( $dept, $dept_ids, true );
	}

	/**
	 * @param string $table_suffix Table suffix without prefix.
	 * @return string
	 */
	public static function table( $table_suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_hrm_' . $table_suffix;
	}

	public static function register_actions() {
		// REST-only module; no legacy AJAX map.
	}
}
