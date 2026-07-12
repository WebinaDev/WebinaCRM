<?php
/**
 * Time tracking service layer (REST / SPA).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Time_Tracking_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
	 */
	private static function guard() {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! is_user_logged_in() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			return null;
		}
		if ( function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'system_manager', 'team_member' ) ) ) {
			return null;
		}
		return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string|array<int,string> $keys Keys.
	 * @param int                    $default Default.
	 * @return int
	 */
	private static function int_param( array $params, $keys, $default = 0 ) {
		foreach ( (array) $keys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				return (int) $params[ $key ];
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $key ] ) ) {
				return (int) $_POST[ $key ];
			}
		}
		return $default;
	}

	public static function active_timer( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$user_id = self::int_param( $params, 'user_id', get_current_user_id() );
		$timer   = WebinoCRM_Time_Tracking::get_active_timer( $user_id );
		return WebinoCRM_Service_Base::success( array( 'timer' => $timer ) );
	}

	public static function start( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Time_Tracking::start_timer(
			array(
				'user_id'     => self::int_param( $params, 'user_id', get_current_user_id() ),
				'entity_type' => isset( $params['entity_type'] ) ? sanitize_key( (string) $params['entity_type'] ) : 'task',
				'entity_id'   => self::int_param( $params, 'entity_id', 0 ),
				'description' => isset( $params['description'] ) ? sanitize_textarea_field( (string) $params['description'] ) : '',
				'is_billable' => ! empty( $params['is_billable'] ) ? 1 : 0,
				'hourly_rate' => isset( $params['hourly_rate'] ) ? (float) $params['hourly_rate'] : 0,
			)
		);
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'تایمر شروع شد.', 'webinocrm' ),
				'timer_id' => (int) $result,
			)
		);
	}

	public static function stop( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه تایمر نامعتبر است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Time_Tracking::stop_timer( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'تایمر متوقف شد.', 'webinocrm' ) ) );
	}

	public static function pause( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه تایمر نامعتبر است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Time_Tracking::pause_timer( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'تایمر متوقف موقت شد.', 'webinocrm' ) ) );
	}

	public static function resume( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه تایمر نامعتبر است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Time_Tracking::resume_timer( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'تایمر از سر گرفته شد.', 'webinocrm' ) ) );
	}

	public static function entries( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$user_id = isset( $params['user_id'] ) ? self::int_param( $params, 'user_id', 0 ) : null;
		if ( 0 === $user_id ) {
			$user_id = null;
		}
		$entity_id = isset( $params['entity_id'] ) ? self::int_param( $params, 'entity_id', 0 ) : null;
		if ( 0 === $entity_id ) {
			$entity_id = null;
		}
		$entries = WebinoCRM_Time_Tracking::get_time_entries(
			array(
				'user_id'     => $user_id,
				'entity_type' => isset( $params['entity_type'] ) ? sanitize_key( (string) $params['entity_type'] ) : null,
				'entity_id'   => $entity_id,
				'date_from'   => isset( $params['date_from'] ) ? sanitize_text_field( (string) $params['date_from'] ) : null,
				'date_to'     => isset( $params['date_to'] ) ? sanitize_text_field( (string) $params['date_to'] ) : null,
				'limit'       => self::int_param( $params, 'limit', 100 ),
				'offset'      => self::int_param( $params, 'offset', 0 ),
			)
		);
		return WebinoCRM_Service_Base::success( array( 'entries' => $entries ) );
	}

	public static function manual_entry( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$entry_id = WebinoCRM_Time_Tracking::add_manual_entry(
			array(
				'user_id'          => self::int_param( $params, 'user_id', get_current_user_id() ),
				'entity_type'      => isset( $params['entity_type'] ) ? sanitize_key( (string) $params['entity_type'] ) : 'task',
				'entity_id'        => self::int_param( $params, 'entity_id', 0 ),
				'description'      => isset( $params['description'] ) ? sanitize_textarea_field( (string) $params['description'] ) : '',
				'date'             => isset( $params['date'] ) ? sanitize_text_field( (string) $params['date'] ) : wp_date( 'Y-m-d' ),
				'duration_minutes' => isset( $params['duration_minutes'] ) ? (float) $params['duration_minutes'] : 0,
				'is_billable'      => ! empty( $params['is_billable'] ) ? 1 : 0,
				'hourly_rate'      => isset( $params['hourly_rate'] ) ? (float) $params['hourly_rate'] : 0,
			)
		);
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'زمان با موفقیت ثبت شد.', 'webinocrm' ),
				'entry_id' => (int) $entry_id,
			)
		);
	}

	public static function delete_entry( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه ورودی نامعتبر است.', 'webinocrm' ) );
		}
		$ok = WebinoCRM_Time_Tracking::delete_entry( $id );
		if ( ! $ok ) {
			return WebinoCRM_Service_Base::error( __( 'خطا در حذف ورودی.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'ورودی حذف شد.', 'webinocrm' ) ) );
	}

	public static function report( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Time_Tracking' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول زمان‌سنجی در دسترس نیست.', 'webinocrm' ) );
		}
		$user_id = isset( $params['user_id'] ) ? self::int_param( $params, 'user_id', 0 ) : null;
		if ( 0 === $user_id ) {
			$user_id = null;
		}
		$entity_id = isset( $params['entity_id'] ) ? self::int_param( $params, 'entity_id', 0 ) : null;
		if ( 0 === $entity_id ) {
			$entity_id = null;
		}
		$report = WebinoCRM_Time_Tracking::get_time_report(
			array(
				'user_id'     => $user_id,
				'entity_type' => isset( $params['entity_type'] ) ? sanitize_key( (string) $params['entity_type'] ) : null,
				'entity_id'   => $entity_id,
				'date_from'   => isset( $params['date_from'] ) ? sanitize_text_field( (string) $params['date_from'] ) : wp_date( 'Y-m-01' ),
				'date_to'     => isset( $params['date_to'] ) ? sanitize_text_field( (string) $params['date_to'] ) : wp_date( 'Y-m-t' ),
				'group_by'    => isset( $params['group_by'] ) ? sanitize_key( (string) $params['group_by'] ) : 'day',
			)
		);
		return WebinoCRM_Service_Base::success( array( 'report' => $report ) );
	}

	public static function register_actions() {
		// REST-only.
	}
}
