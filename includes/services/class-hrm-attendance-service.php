<?php
/**
 * HRM attendance service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Attendance_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
	 */
	private static function guard_staff() {
		if ( ! WebinoCRM_Hrm_Service::is_staff_user() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return null;
	}

	/**
	 * @param int $department_id Department term id.
	 * @return array<int,int>
	 */
	private static function user_ids_in_department( $department_id ) {
		$department_id = (int) $department_id;
		if ( $department_id <= 0 || ! taxonomy_exists( 'organizational_position' ) ) {
			return array();
		}
		$ids = get_objects_in_term( $department_id, 'organizational_position' );
		if ( is_wp_error( $ids ) || empty( $ids ) ) {
			return array();
		}
		$job_titles = get_terms(
			array(
				'taxonomy'   => 'organizational_position',
				'hide_empty' => false,
				'parent'     => $department_id,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $job_titles ) && ! empty( $job_titles ) ) {
			foreach ( $job_titles as $term_id ) {
				$more = get_objects_in_term( (int) $term_id, 'organizational_position' );
				if ( ! is_wp_error( $more ) && ! empty( $more ) ) {
					$ids = array_merge( $ids, $more );
				}
			}
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_row( $row ) {
		$user_id = (int) $row->user_id;
		$user    = get_userdata( $user_id );
		return array(
			'id'          => (int) $row->id,
			'user_id'     => $user_id,
			'user_name'   => $user ? $user->display_name : '',
			'work_date'   => (string) $row->work_date,
			'check_in'    => (string) ( $row->check_in ?? '' ),
			'check_out'   => (string) ( $row->check_out ?? '' ),
			'source'      => (string) ( $row->source ?? 'self' ),
			'status'      => (string) ( $row->status ?? 'present' ),
			'notes'       => (string) ( $row->notes ?? '' ),
			'approved_by' => (int) ( $row->approved_by ?? 0 ),
		);
	}

	public static function list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$user_id    = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$date_from  = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) );
		$date_to    = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) );
		$department = WebinoCRM_Service_Base::int_param( $params, 'department' );
		$paged      = max( 1, WebinoCRM_Service_Base::int_param( $params, 'paged' ) ?: 1 );
		$per_page   = min( 100, max( 1, WebinoCRM_Service_Base::int_param( $params, 'per_page' ) ?: 25 ) );
		$offset     = ( $paged - 1 ) * $per_page;

		$can_hrm = WebinoCRM_Hrm_Service::can_manage_hrm();
		if ( ! $can_hrm ) {
			if ( $user_id <= 0 ) {
				$user_id = get_current_user_id();
			}
			if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
		}

		$table  = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		$where  = array( '1=1' );
		$values = array();

		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $user_id;
		} elseif ( $department > 0 ) {
			$dept_users = self::user_ids_in_department( $department );
			if ( empty( $dept_users ) ) {
				return WebinoCRM_Service_Base::success(
					array(
						'items'       => array(),
						'total'       => 0,
						'total_pages' => 0,
						'paged'       => $paged,
					)
				);
			}
			$placeholders = implode( ',', array_fill( 0, count( $dept_users ), '%d' ) );
			$where[]      = "user_id IN ($placeholders)";
			$values       = array_merge( $values, $dept_users );
		}

		if ( $date_from ) {
			$where[]  = 'work_date >= %s';
			$values[] = $date_from;
		}
		if ( $date_to ) {
			$where[]  = 'work_date <= %s';
			$values[] = $date_to;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		$list_sql  = "SELECT * FROM $table WHERE $where_sql ORDER BY work_date DESC, id DESC LIMIT %d OFFSET %d";

		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $values, array( $per_page, $offset ) ) ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
			$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) );
		}

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_row( $row );
		}

		return WebinoCRM_Service_Base::success(
			array(
				'items'       => $items,
				'total'       => $total,
				'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
				'paged'       => $paged,
			)
		);
	}

	/**
	 * @param int $user_id User id.
	 * @param string $work_date Y-m-d.
	 * @return object|null
	 */
	private static function get_today_record( $user_id, $work_date ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND work_date = %s LIMIT 1",
				(int) $user_id,
				$work_date
			)
		);
	}

	public static function check_in( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_staff();
		if ( $err ) {
			return $err;
		}

		$user_id   = get_current_user_id();
		$work_date = current_time( 'Y-m-d' );
		$now       = current_time( 'mysql' );
		$table     = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		$existing  = self::get_today_record( $user_id, $work_date );

		if ( $existing && ! empty( $existing->check_in ) ) {
			return WebinoCRM_Service_Base::error( __( 'ورود امروز قبلاً ثبت شده است.', 'webinocrm' ) );
		}

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'check_in'   => $now,
					'source'     => 'self',
					'status'     => 'present',
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$id = (int) $existing->id;
		} else {
			$wpdb->insert(
				$table,
				array(
					'user_id'    => $user_id,
					'work_date'  => $work_date,
					'check_in'   => $now,
					'check_out'  => null,
					'source'     => 'self',
					'status'     => 'present',
					'notes'      => '',
					'approved_by'=> 0,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'ورود با موفقیت ثبت شد.', 'webinocrm' ),
				'record'  => $row ? self::format_row( $row ) : null,
			)
		);
	}

	public static function check_out( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_staff();
		if ( $err ) {
			return $err;
		}

		$user_id   = get_current_user_id();
		$work_date = current_time( 'Y-m-d' );
		$now       = current_time( 'mysql' );
		$table     = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		$existing  = self::get_today_record( $user_id, $work_date );

		if ( ! $existing || empty( $existing->check_in ) ) {
			return WebinoCRM_Service_Base::error( __( 'ابتدا ورود امروز را ثبت کنید.', 'webinocrm' ) );
		}
		if ( ! empty( $existing->check_out ) ) {
			return WebinoCRM_Service_Base::error( __( 'خروج امروز قبلاً ثبت شده است.', 'webinocrm' ) );
		}

		$wpdb->update(
			$table,
			array(
				'check_out'  => $now,
				'updated_at' => $now,
			),
			array( 'id' => (int) $existing->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $existing->id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'خروج با موفقیت ثبت شد.', 'webinocrm' ),
				'record'  => $row ? self::format_row( $row ) : null,
			)
		);
	}

	public static function save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$id        = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$user_id   = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$work_date = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'work_date', '' ) );
		$check_in  = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'check_in', '' ) );
		$check_out = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'check_out', '' ) );
		$status    = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'approved' ) );
		$notes     = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) );

		if ( $user_id <= 0 || ! $work_date ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر و تاریخ الزامی است.', 'webinocrm' ) );
		}
		if ( ! WebinoCRM_Hrm_Service::is_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر کارمند نیست.', 'webinocrm' ) );
		}

		$allowed_status = array( 'present', 'absent', 'late', 'approved', 'pending' );
		if ( ! in_array( $status, $allowed_status, true ) ) {
			$status = 'approved';
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		$data  = array(
			'user_id'     => $user_id,
			'work_date'   => $work_date,
			'check_in'    => $check_in ?: null,
			'check_out'   => $check_out ?: null,
			'source'      => 'manual',
			'status'      => $status,
			'notes'       => $notes,
			'approved_by' => get_current_user_id(),
			'updated_at'  => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update(
				$table,
				$data,
				array( 'id' => $id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$dup = self::get_today_record( $user_id, $work_date );
			if ( $dup ) {
				$id = (int) $dup->id;
				$wpdb->update(
					$table,
					$data,
					array( 'id' => $id ),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
					array( '%d' )
				);
			} else {
				$data['created_at'] = $now;
				$wpdb->insert(
					$table,
					$data,
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
				);
				$id = (int) $wpdb->insert_id;
			}
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'رکورد حضور ذخیره شد.', 'webinocrm' ),
				'record'  => $row ? self::format_row( $row ) : null,
			)
		);
	}

	public static function register_actions() {}
}
