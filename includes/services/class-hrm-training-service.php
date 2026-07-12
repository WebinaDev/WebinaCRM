<?php
/**
 * HRM training service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Training_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
	 */
	private static function guard_manage() {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return null;
	}

	public static function courses_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		if ( ! WebinoCRM_Hrm_Service::is_staff_user() && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$status = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) );
		$table  = WebinoCRM_Hrm_Service::table( 'courses' );
		if ( $status ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = %s ORDER BY title ASC", $status ) );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY title ASC" );
		}

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_course( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'courses' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_course( $row ) {
		return array(
			'id'              => (int) $row->id,
			'title'           => (string) $row->title,
			'description'     => (string) ( $row->description ?? '' ),
			'duration_hours'  => (float) ( $row->duration_hours ?? 0 ),
			'category'        => (string) ( $row->category ?? '' ),
			'status'          => (string) ( $row->status ?? 'draft' ),
		);
	}

	public static function courses_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_manage();
		if ( $err ) {
			return $err;
		}

		$id             = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$title          = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'title', '' ) );
		$description    = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'description', '' ) );
		$duration_hours = (float) WebinoCRM_Service_Base::param( $params, 'duration_hours', 0 );
		$category       = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'category', '' ) );
		$status         = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'draft' ) );

		if ( '' === $title ) {
			return WebinoCRM_Service_Base::error( __( 'عنوان دوره الزامی است.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'courses' );
		$data  = array(
			'title'          => $title,
			'description'    => $description,
			'duration_hours' => $duration_hours,
			'category'       => $category,
			'status'         => $status,
			'updated_at'     => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'دوره ذخیره شد.', 'webinocrm' ),
				'course'  => $row ? self::format_course( $row ) : null,
			)
		);
	}

	public static function sessions_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		if ( ! WebinoCRM_Hrm_Service::is_staff_user() && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$course_id = WebinoCRM_Service_Base::int_param( $params, 'course_id' );
		$table     = WebinoCRM_Hrm_Service::table( 'course_sessions' );
		if ( $course_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d ORDER BY session_date ASC, start_time ASC", $course_id ) );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY session_date DESC" );
		}

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_session( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'sessions' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_session( $row ) {
		$instructor = get_userdata( (int) ( $row->instructor_id ?? 0 ) );
		return array(
			'id'               => (int) $row->id,
			'course_id'        => (int) ( $row->course_id ?? 0 ),
			'session_date'     => (string) ( $row->session_date ?? '' ),
			'start_time'       => (string) ( $row->start_time ?? '' ),
			'end_time'         => (string) ( $row->end_time ?? '' ),
			'location'         => (string) ( $row->location ?? '' ),
			'instructor_id'    => (int) ( $row->instructor_id ?? 0 ),
			'instructor_name'  => $instructor ? $instructor->display_name : '',
			'notes'            => (string) ( $row->notes ?? '' ),
		);
	}

	public static function sessions_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_manage();
		if ( $err ) {
			return $err;
		}

		$id            = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$course_id     = WebinoCRM_Service_Base::int_param( $params, 'course_id' );
		$session_date  = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'session_date', '' ) );
		$start_time    = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'start_time', '' ) );
		$end_time      = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'end_time', '' ) );
		$location      = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'location', '' ) );
		$instructor_id = WebinoCRM_Service_Base::int_param( $params, 'instructor_id' );
		$notes         = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) );

		if ( $course_id <= 0 || ! $session_date ) {
			return WebinoCRM_Service_Base::error( __( 'دوره و تاریخ جلسه الزامی است.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'course_sessions' );
		$data  = array(
			'course_id'     => $course_id,
			'session_date'  => $session_date,
			'start_time'    => $start_time,
			'end_time'      => $end_time,
			'location'      => $location,
			'instructor_id' => $instructor_id,
			'notes'         => $notes,
			'updated_at'    => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'جلسه ذخیره شد.', 'webinocrm' ),
				'session' => $row ? self::format_session( $row ) : null,
			)
		);
	}

	public static function enrollments_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$course_id = WebinoCRM_Service_Base::int_param( $params, 'course_id' );
		$user_id   = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$can_hrm   = WebinoCRM_Hrm_Service::can_manage_hrm();

		if ( ! $can_hrm ) {
			if ( ! WebinoCRM_Hrm_Service::is_staff_user() ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
			if ( $user_id <= 0 ) {
				$user_id = get_current_user_id();
			} elseif ( $user_id !== get_current_user_id() && ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
		}

		$table  = WebinoCRM_Hrm_Service::table( 'enrollments' );
		$where  = array( '1=1' );
		$values = array();
		if ( $course_id > 0 ) {
			$where[]  = 'course_id = %d';
			$values[] = $course_id;
		}
		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $user_id;
		}

		$sql  = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY enrolled_at DESC';
		$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_enrollment( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'enrollments' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_enrollment( $row ) {
		$user = get_userdata( (int) ( $row->user_id ?? 0 ) );
		return array(
			'id'           => (int) $row->id,
			'course_id'    => (int) ( $row->course_id ?? 0 ),
			'user_id'      => (int) ( $row->user_id ?? 0 ),
			'user_name'    => $user ? $user->display_name : '',
			'status'       => (string) ( $row->status ?? 'enrolled' ),
			'enrolled_at'  => (string) ( $row->enrolled_at ?? '' ),
			'completed_at' => (string) ( $row->completed_at ?? '' ),
			'score'        => (float) ( $row->score ?? 0 ),
		);
	}

	public static function enrollments_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$id           = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$course_id    = WebinoCRM_Service_Base::int_param( $params, 'course_id' );
		$user_id      = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$status       = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'enrolled' ) );
		$score        = (float) WebinoCRM_Service_Base::param( $params, 'score', 0 );
		$can_hrm      = WebinoCRM_Hrm_Service::can_manage_hrm();

		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( ! $can_hrm && $user_id !== get_current_user_id() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( $course_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'دوره الزامی است.', 'webinocrm' ) );
		}
		if ( ! WebinoCRM_Hrm_Service::is_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر کارمند نیست.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'enrollments' );
		$data  = array(
			'course_id'  => $course_id,
			'user_id'    => $user_id,
			'status'     => $status,
			'score'      => $score,
			'updated_at' => $now,
		);
		if ( 'completed' === $status ) {
			$data['completed_at'] = $now;
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['enrolled_at'] = $now;
			$data['created_at']  = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message'    => __( 'ثبت‌نام ذخیره شد.', 'webinocrm' ),
				'enrollment' => $row ? self::format_enrollment( $row ) : null,
			)
		);
	}

	public static function register_actions() {}
}
