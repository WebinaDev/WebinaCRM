<?php
/**
 * HRM staff service — wraps customers service for staff role.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Staff_Service {
	use WebinoCRM_Domain_Service_Trait;

	public static function list( array $params ) {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$params['role'] = 'staff';
		return WebinoCRM_Customers_Service::list( $params );
	}

	public static function save( array $params ) {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		foreach ( $params as $key => $value ) {
			if ( is_scalar( $value ) || is_array( $value ) ) {
				$_POST[ $key ] = $value;
			}
		}
		return WebinoCRM_Customers_Service::save( $params );
	}

	public static function delete( array $params ) {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( isset( $params['id'] ) ) {
			$params['user_id'] = (int) $params['id'];
		}
		foreach ( $params as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$_POST[ $key ] = $value;
			}
		}
		return WebinoCRM_Customers_Service::delete( $params );
	}

	public static function get_profile( array $params ) {
		$user_id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		if ( $user_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر نامعتبر است.', 'webinocrm' ), 400 );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( ! WebinoCRM_Hrm_Service::is_staff_user( $user_id ) && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر کارمند نیست.', 'webinocrm' ), 404 );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-profile-fields.php';
		return WebinoCRM_Service_Base::success( WebinoCRM_Hrm_Profile_Fields::read_profile( $user_id ) );
	}

	public static function save_profile( array $params ) {
		$user_id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		if ( $user_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر نامعتبر است.', 'webinocrm' ), 400 );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() && ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-profile-fields.php';
		$payload = isset( $params['profile'] ) && is_array( $params['profile'] ) ? $params['profile'] : $params;
		$result  = WebinoCRM_Hrm_Profile_Fields::save_profile( $user_id, $payload );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message(), (int) ( $result->get_error_data()['status'] ?? 400 ) );
		}
		return WebinoCRM_Service_Base::success( array(
			'message' => __( 'پروفایل ذخیره شد.', 'webinocrm' ),
			'profile' => WebinoCRM_Hrm_Profile_Fields::read_profile( $user_id ),
		) );
	}

	public static function org_positions_list( array $params ) {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$departments = array();
		$job_titles  = array();
		if ( taxonomy_exists( 'organizational_position' ) ) {
			$terms = get_terms( array( 'taxonomy' => 'organizational_position', 'hide_empty' => false, 'orderby' => 'name' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					if ( (int) $t->parent === 0 ) {
						$departments[] = array( 'id' => (int) $t->term_id, 'name' => $t->name );
					} else {
						$job_titles[] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'parent' => (int) $t->parent );
					}
				}
			}
		}
		return WebinoCRM_Service_Base::success( array(
			'departments' => $departments,
			'job_titles'  => $job_titles,
		) );
	}

	public static function org_position_save( array $params ) {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$_POST = array_merge( $_POST, $params );
		return WebinoCRM_Customers_Service::org_position_save( $params );
	}

	public static function org_position_delete( array $params ) {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$_POST = array_merge( $_POST, $params );
		return WebinoCRM_Customers_Service::org_position_delete( $params );
	}

	public static function register_actions() {}
}
