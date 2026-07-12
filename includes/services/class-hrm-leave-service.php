<?php
/**
 * HRM leave service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Leave_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function default_leave_types() {
		return array(
			array(
				'name'             => 'استحقاقی',
				'code'             => 'annual',
				'is_paid'          => 1,
				'max_days_per_year'=> 26,
				'sort_order'       => 1,
			),
			array(
				'name'             => 'استعلاجی',
				'code'             => 'sick',
				'is_paid'          => 1,
				'max_days_per_year'=> 15,
				'sort_order'       => 2,
			),
			array(
				'name'             => 'بدون حقوق',
				'code'             => 'unpaid',
				'is_paid'          => 0,
				'max_days_per_year'=> 0,
				'sort_order'       => 3,
			),
			array(
				'name'             => 'ماموریت',
				'code'             => 'mission',
				'is_paid'          => 1,
				'max_days_per_year'=> 0,
				'sort_order'       => 4,
			),
			array(
				'name'             => 'ازدواج',
				'code'             => 'marriage',
				'is_paid'          => 1,
				'max_days_per_year'=> 3,
				'sort_order'       => 5,
			),
			array(
				'name'             => 'فوت بستگان',
				'code'             => 'bereavement',
				'is_paid'          => 1,
				'max_days_per_year'=> 3,
				'sort_order'       => 6,
			),
		);
	}

	/**
	 * @return void
	 */
	private static function seed_default_types() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'leave_types' );
		$now   = current_time( 'mysql' );
		foreach ( self::default_leave_types() as $type ) {
			$wpdb->insert(
				$table,
				array_merge(
					$type,
					array(
						'is_active'  => 1,
						'created_at' => $now,
						'updated_at' => $now,
					)
				),
				array( '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_type( $row ) {
		return array(
			'id'                => (int) $row->id,
			'name'              => (string) $row->name,
			'code'              => (string) ( $row->code ?? '' ),
			'is_paid'           => (int) ( $row->is_paid ?? 0 ),
			'max_days_per_year' => (float) ( $row->max_days_per_year ?? 0 ),
			'is_active'         => (int) ( $row->is_active ?? 1 ),
			'sort_order'        => (int) ( $row->sort_order ?? 0 ),
		);
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_request( $row ) {
		$user = get_userdata( (int) $row->user_id );
		return array(
			'id'            => (int) $row->id,
			'user_id'       => (int) $row->user_id,
			'user_name'     => $user ? $user->display_name : '',
			'leave_type_id' => (int) $row->leave_type_id,
			'date_from'     => (string) $row->date_from,
			'date_to'       => (string) $row->date_to,
			'days'          => (float) ( $row->days ?? 0 ),
			'status'        => (string) ( $row->status ?? 'pending' ),
			'reason'        => (string) ( $row->reason ?? '' ),
			'approved_by'   => (int) ( $row->approved_by ?? 0 ),
			'approved_at'   => (string) ( $row->approved_at ?? '' ),
			'rejection_reason' => (string) ( $row->rejection_reason ?? '' ),
		);
	}

	/**
	 * @param string $from Date Y-m-d.
	 * @param string $to   Date Y-m-d.
	 * @return float
	 */
	private static function count_leave_days( $from, $to ) {
		$start = strtotime( $from );
		$end   = strtotime( $to );
		if ( ! $start || ! $end || $end < $start ) {
			return 0;
		}
		return (float) ( ( ( $end - $start ) / DAY_IN_SECONDS ) + 1 );
	}

	public static function types_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		if ( ! WebinoCRM_Hrm_Service::is_staff_user() && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$table = WebinoCRM_Hrm_Service::table( 'leave_types' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		if ( 0 === $count && WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			self::seed_default_types();
		}

		$rows = $wpdb->get_results( "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, name ASC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_type( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'types' => $items ) );
	}

	public static function types_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$id                = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$name              = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) );
		$code              = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'code', '' ) );
		$is_paid           = ! empty( $params['is_paid'] ) ? 1 : 0;
		$max_days          = (float) WebinoCRM_Service_Base::param( $params, 'max_days_per_year', 0 );
		$sort_order        = WebinoCRM_Service_Base::int_param( $params, 'sort_order' );
		$is_active         = ! isset( $params['is_active'] ) || ! empty( $params['is_active'] ) ? 1 : 0;

		if ( '' === $name ) {
			return WebinoCRM_Service_Base::error( __( 'نام نوع مرخصی الزامی است.', 'webinocrm' ) );
		}
		if ( '' === $code ) {
			$code = sanitize_title( $name );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'leave_types' );
		$data  = array(
			'name'              => $name,
			'code'              => $code,
			'is_paid'           => $is_paid,
			'max_days_per_year' => $max_days,
			'sort_order'        => $sort_order,
			'is_active'         => $is_active,
			'updated_at'        => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update(
				$table,
				$data,
				array( 'id' => $id ),
				array( '%s', '%s', '%d', '%f', '%d', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$data['created_at'] = $now;
			$wpdb->insert(
				$table,
				$data,
				array( '%s', '%s', '%d', '%f', '%d', '%d', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'نوع مرخصی ذخیره شد.', 'webinocrm' ),
				'type'    => $row ? self::format_type( $row ) : null,
			)
		);
	}

	public static function requests_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$status  = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) );
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$paged   = max( 1, WebinoCRM_Service_Base::int_param( $params, 'paged' ) ?: 1 );
		$per_page = min( 100, max( 1, WebinoCRM_Service_Base::int_param( $params, 'per_page' ) ?: 25 ) );
		$offset  = ( $paged - 1 ) * $per_page;

		$can_hrm = WebinoCRM_Hrm_Service::can_manage_hrm();
		if ( ! $can_hrm ) {
			if ( $user_id <= 0 ) {
				$user_id = get_current_user_id();
			}
			if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
		}

		$table  = WebinoCRM_Hrm_Service::table( 'leave_requests' );
		$where  = array( '1=1' );
		$values = array();

		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}
		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $user_id;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		$list_sql  = "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";

		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $values, array( $per_page, $offset ) ) ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
			$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) );
		}

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_request( $row );
		}

		return WebinoCRM_Service_Base::success(
			array(
				'requests'    => $items,
				'total'       => $total,
				'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
				'paged'       => $paged,
			)
		);
	}

	public static function request_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$id            = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$user_id       = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$leave_type_id = WebinoCRM_Service_Base::int_param( $params, 'leave_type_id' );
		$date_from     = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) );
		$date_to       = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) );
		$reason        = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'reason', '' ) );

		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() && ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) && $user_id !== get_current_user_id() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( $leave_type_id <= 0 || ! $date_from || ! $date_to ) {
			return WebinoCRM_Service_Base::error( __( 'نوع مرخصی و بازه تاریخ الزامی است.', 'webinocrm' ) );
		}
		if ( strtotime( $date_to ) < strtotime( $date_from ) ) {
			return WebinoCRM_Service_Base::error( __( 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.', 'webinocrm' ) );
		}

		$days  = self::count_leave_days( $date_from, $date_to );
		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'leave_requests' );
		$data  = array(
			'user_id'       => $user_id,
			'leave_type_id' => $leave_type_id,
			'date_from'     => $date_from,
			'date_to'       => $date_to,
			'days'          => $days,
			'reason'        => $reason,
			'updated_at'    => $now,
		);

		if ( $id > 0 ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
			if ( ! $existing ) {
				return WebinoCRM_Service_Base::error( __( 'درخواست یافت نشد.', 'webinocrm' ), 404 );
			}
			if ( 'pending' !== $existing->status && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
				return WebinoCRM_Service_Base::error( __( 'درخواست قابل ویرایش نیست.', 'webinocrm' ) );
			}
			$wpdb->update(
				$table,
				$data,
				array( 'id' => $id ),
				array( '%d', '%d', '%s', '%s', '%f', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$data['status']     = 'pending';
			$data['created_at'] = $now;
			$wpdb->insert(
				$table,
				$data,
				array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'درخواست مرخصی ذخیره شد.', 'webinocrm' ),
				'request' => $row ? self::format_request( $row ) : null,
			)
		);
	}

	/**
	 * @param int    $request_id Request id.
	 * @param string $new_status Status.
	 * @return array<string,mixed>
	 */
	private static function set_request_status( $request_id, $new_status, $extra = array() ) {
		global $wpdb;
		$table   = WebinoCRM_Hrm_Service::table( 'leave_requests' );
		$request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $request_id ) );
		if ( ! $request ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( (int) $request->user_id ) && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$now  = current_time( 'mysql' );
		$data = array_merge(
			array(
				'status'      => $new_status,
				'approved_by' => get_current_user_id(),
				'approved_at' => $now,
				'updated_at'  => $now,
			),
			$extra
		);

		$wpdb->update(
			$table,
			$data,
			array( 'id' => (int) $request_id ),
			null,
			array( '%d' )
		);

		if ( 'approved' === $new_status ) {
			self::sync_balance_on_approve( $request );
			self::sync_job_status_on_approve( $request );
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $request_id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => 'approved' === $new_status
					? __( 'درخواست مرخصی تأیید شد.', 'webinocrm' )
					: __( 'درخواست مرخصی رد شد.', 'webinocrm' ),
				'request' => $row ? self::format_request( $row ) : null,
			)
		);
	}

	/**
	 * @param object $request Leave request row.
	 * @return void
	 */
	private static function sync_balance_on_approve( $request ) {
		global $wpdb;
		$year  = (int) gmdate( 'Y', strtotime( (string) $request->date_from ) );
		$table = WebinoCRM_Hrm_Service::table( 'leave_balances' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND leave_type_id = %d AND year = %d LIMIT 1",
				(int) $request->user_id,
				(int) $request->leave_type_id,
				$year
			)
		);
		$days = (float) $request->days;
		if ( $row ) {
			$used = (float) $row->used + $days;
			$wpdb->update(
				$table,
				array(
					'used'       => $used,
					'balance'    => max( 0, (float) $row->allocated - $used ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row->id ),
				array( '%f', '%f', '%s' ),
				array( '%d' )
			);
		} else {
			$type = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . WebinoCRM_Hrm_Service::table( 'leave_types' ) . ' WHERE id = %d',
					(int) $request->leave_type_id
				)
			);
			$allocated = $type ? (float) $type->max_days_per_year : 0;
			$wpdb->insert(
				$table,
				array(
					'user_id'       => (int) $request->user_id,
					'leave_type_id' => (int) $request->leave_type_id,
					'year'          => $year,
					'allocated'     => $allocated,
					'used'          => $days,
					'balance'       => max( 0, $allocated - $days ),
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s' )
			);
		}
	}

	/**
	 * @param object $request Leave request row.
	 * @return void
	 */
	private static function sync_job_status_on_approve( $request ) {
		$today = current_time( 'Y-m-d' );
		if ( $today >= (string) $request->date_from && $today <= (string) $request->date_to ) {
			update_user_meta( (int) $request->user_id, 'webino_job_status', 'on_leave' );
		}
	}

	public static function request_approve( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه نامعتبر است.', 'webinocrm' ) );
		}
		return self::set_request_status( $id, 'approved' );
	}

	public static function request_reject( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$id     = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$reason = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'rejection_reason', '' ) );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه نامعتبر است.', 'webinocrm' ) );
		}
		return self::set_request_status( $id, 'rejected', array( 'rejection_reason' => $reason ) );
	}

	public static function balances_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$year    = WebinoCRM_Service_Base::int_param( $params, 'year' );
		if ( $year <= 0 ) {
			$year = (int) current_time( 'Y' );
		}

		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() && ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) && $user_id !== get_current_user_id() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$table = WebinoCRM_Hrm_Service::table( 'leave_balances' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, t.name AS type_name, t.code AS type_code
				FROM $table b
				LEFT JOIN " . WebinoCRM_Hrm_Service::table( 'leave_types' ) . " t ON t.id = b.leave_type_id
				WHERE b.user_id = %d AND b.year = %d
				ORDER BY t.sort_order ASC, t.name ASC",
				$user_id,
				$year
			)
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'            => (int) $row->id,
				'user_id'       => (int) $row->user_id,
				'leave_type_id' => (int) $row->leave_type_id,
				'type_name'     => (string) ( $row->type_name ?? '' ),
				'type_code'     => (string) ( $row->type_code ?? '' ),
				'year'          => (int) $row->year,
				'allocated'     => (float) ( $row->allocated ?? 0 ),
				'used'          => (float) ( $row->used ?? 0 ),
				'balance'       => (float) ( $row->balance ?? 0 ),
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'user_id'  => $user_id,
				'year'     => $year,
				'balances' => $items,
			)
		);
	}

	public static function register_actions() {}
}
