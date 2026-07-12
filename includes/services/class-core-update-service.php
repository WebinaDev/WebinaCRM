<?php
/**
 * CRM core update service (REST + action map).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Core_Update_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>
	 */
	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
		return array();
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function status( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$deny = self::guard();
		if ( $deny ) {
			return $deny;
		}
		$refresh = ! empty( $params['refresh'] ) && in_array( (string) $params['refresh'], array( '1', 'true', 'yes' ), true );
		$result  = WebinoCRM_Core_Updater::get_update_status( $refresh );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}
		return WebinoCRM_Service_Base::success( $result );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function run( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$deny = self::guard();
		if ( $deny ) {
			return $deny;
		}
		$version = sanitize_text_field( (string) ( $params['version'] ?? '' ) );
		$result  = WebinoCRM_Core_Updater::run_update( $version );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}
		return WebinoCRM_Service_Base::success( $result );
	}

	/**
	 * @return void
	 */
	public static function register_actions() {
		self::register_map(
			array(
				'check_crm_core_update' => array( __CLASS__, 'status' ),
				'run_crm_core_update'   => array( __CLASS__, 'run' ),
			)
		);
	}
}
