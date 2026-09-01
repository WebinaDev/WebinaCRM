<?php
/**
 * Licenses Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Licenses_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! webinocrm_user_can_manage_licenses() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
		$licenses = WebinoCRM_License_Manager::get_all_licenses();
		return WebinoCRM_Service_Base::success(
			array(
				'licenses' => $licenses,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function create( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! webinocrm_user_can_manage_licenses() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}

		$project_name = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'project_name', '' ) );
		$domain       = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) );
		$expiry_raw   = trim( (string) WebinoCRM_Service_Base::param( $params, 'expiry_date', '' ) );
		$expiry_date  = null;
		if ( '' !== $expiry_raw ) {
			$expiry_date = WebinoCRM_Service_Base::normalize_date( $expiry_raw );
			if ( '' === $expiry_date ) {
				return WebinoCRM_Service_Base::error( array( 'message' => 'تاریخ انقضا نامعتبر است' ) );
			}
		}
		$start_raw  = trim( (string) WebinoCRM_Service_Base::param( $params, 'start_date', '' ) );
		$start_date = current_time( 'Y-m-d' );
		if ( '' !== $start_raw ) {
			$start_date = WebinoCRM_Service_Base::normalize_date( $start_raw );
			if ( '' === $start_date ) {
				return WebinoCRM_Service_Base::error( array( 'message' => 'تاریخ شروع نامعتبر است' ) );
			}
		}
		$logo_url = esc_url_raw( (string) WebinoCRM_Service_Base::param( $params, 'logo_url', '' ) );
		$status   = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'status', 'active' ) );
		if ( '' === $status ) {
			$status = 'active';
		}

		if ( '' === $project_name || '' === $domain ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'لطفاً تمام فیلدهای الزامی را پر کنید' ) );
		}

		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		// Validate domain format (more lenient)
		if ( ! preg_match( '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $domain ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'فرمت دامنه نامعتبر است. مثال: example.com' ) );
		}

		// Domain is the license - no license_key needed
		$data = array(
			'project_name' => $project_name,
			'domain'       => $domain,
			'expiry_date'  => $expiry_date,
			'start_date'   => $start_date,
			'logo_url'     => $logo_url,
			'status'       => $status,
		);

		$license_id = WebinoCRM_License_Manager::add_license( $data );
		if ( false === $license_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'webinocrm_licenses';
			$existing   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE domain = %s", $domain ) );
			if ( $existing ) {
				return WebinoCRM_Service_Base::error( array( 'message' => 'این دامنه قبلاً ثبت شده است.' ) );
			}
			return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در افزودن لایسنس. لطفاً دوباره تلاش کنید.' ) );
		}

		$licenses    = WebinoCRM_License_Manager::get_all_licenses();
		$new_license = null;
		foreach ( $licenses as $l ) {
			if ( (int) $l['id'] === (int) $license_id ) {
				$new_license = $l;
				break;
			}
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message' => 'لایسنس با موفقیت افزوده شد',
				'license' => $new_license,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function update( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! webinocrm_user_can_manage_licenses() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'شناسه لایسنس نامعتبر است' ) );
		}

		$data = array();
		if ( array_key_exists( 'project_name', $params ) || isset( $_POST['project_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['project_name'] = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'project_name', '' ) );
		}
		if ( array_key_exists( 'domain', $params ) || isset( $_POST['domain'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['domain'] = WebinoCRM_License_Manager::normalize_domain(
				sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'domain', '' ) )
			);
		}
		if ( array_key_exists( 'license_key', $params ) || isset( $_POST['license_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['license_key'] = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'license_key', '' ) );
		}
		if ( array_key_exists( 'expiry_date', $params ) || isset( $_POST['expiry_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$expiry_raw = trim( (string) WebinoCRM_Service_Base::param( $params, 'expiry_date', '' ) );
			if ( '' === $expiry_raw ) {
				$data['expiry_date'] = null;
			} else {
				$expiry_norm = WebinoCRM_Service_Base::normalize_date( $expiry_raw );
				if ( '' === $expiry_norm ) {
					return WebinoCRM_Service_Base::error( array( 'message' => 'تاریخ انقضا نامعتبر است' ) );
				}
				$data['expiry_date'] = $expiry_norm;
			}
		}
		if ( array_key_exists( 'start_date', $params ) || isset( $_POST['start_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$start_raw = trim( (string) WebinoCRM_Service_Base::param( $params, 'start_date', '' ) );
			if ( '' === $start_raw ) {
				$data['start_date'] = current_time( 'Y-m-d' );
			} else {
				$start_norm = WebinoCRM_Service_Base::normalize_date( $start_raw );
				if ( '' === $start_norm ) {
					return WebinoCRM_Service_Base::error( array( 'message' => 'تاریخ شروع نامعتبر است' ) );
				}
				$data['start_date'] = $start_norm;
			}
		}
		if ( array_key_exists( 'logo_url', $params ) || isset( $_POST['logo_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['logo_url'] = esc_url_raw( (string) WebinoCRM_Service_Base::param( $params, 'logo_url', '' ) );
		}
		if ( array_key_exists( 'status', $params ) || isset( $_POST['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['status'] = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) );
		}

		if ( empty( $data ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده است' ) );
		}

		$result = WebinoCRM_License_Manager::update_license( $id, $data );
		if ( ! $result ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در به‌روزرسانی لایسنس' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => 'لایسنس با موفقیت به‌روزرسانی شد' ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function renew( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! webinocrm_user_can_manage_licenses() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}

		$id         = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$expiry_raw = trim( (string) WebinoCRM_Service_Base::param( $params, 'expiry_date', '' ) );
		if ( ! $id || '' === $expiry_raw ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'لطفاً تاریخ انقضای جدید را وارد کنید' ) );
		}
		$new_expiry_date = WebinoCRM_Service_Base::normalize_date( $expiry_raw );
		if ( '' === $new_expiry_date ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'تاریخ انقضا نامعتبر است' ) );
		}

		$result = WebinoCRM_License_Manager::renew_license( $id, $new_expiry_date );
		if ( ! $result ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در تمدید لایسنس' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => 'لایسنس با موفقیت تمدید شد' ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function cancel( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! webinocrm_user_can_manage_licenses() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'شناسه لایسنس نامعتبر است' ) );
		}

		$result = WebinoCRM_License_Manager::cancel_license( $id );
		if ( ! $result ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در لغو لایسنس' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => 'لایسنس با موفقیت لغو شد' ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! webinocrm_user_can_manage_licenses() ) {
			return WebinoCRM_Service_Base::error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'شناسه لایسنس نامعتبر است' ) );
		}

		$result = WebinoCRM_License_Manager::delete_license( $id );
		if ( ! $result ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در حذف لایسنس' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => 'لایسنس با موفقیت حذف شد' ) );
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_licenses'    => array( __CLASS__, 'list' ),
				'webinocrm_add_license'     => array( __CLASS__, 'create' ),
				'webinocrm_update_license'  => array( __CLASS__, 'update' ),
				'webinocrm_renew_license'   => array( __CLASS__, 'renew' ),
				'webinocrm_cancel_license'  => array( __CLASS__, 'cancel' ),
				'webinocrm_delete_license'  => array( __CLASS__, 'delete' ),
			)
		);
	}
}
