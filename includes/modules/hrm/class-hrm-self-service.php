<?php
/**
 * HRM employee self-service aggregation.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Self_Service {

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	private static function guard_self() {
		if ( ! WebinoCRM_Hrm_Service::can_access_hrm_self() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return null;
	}

	/**
	 * Dashboard payload for GET /hrm/me.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function me( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}

		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-calculator.php';

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		$emp     = WebinoCRM_Hrm_Payroll_Calculator::employee_snapshot( $user_id );

		$decree = self::current_decree( $user_id );
		$org    = self::org_context( $user_id );
		$mgr    = self::direct_manager( $user_id, $org );

		$national_raw = (string) get_user_meta( $user_id, 'webino_national_id', true );
		$workshop     = null;
		if ( ! empty( $decree['workshop_id'] ) ) {
			$ws_table = WebinoCRM_Hrm_Service::table( 'workshops' );
			$ws_row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, code, name FROM $ws_table WHERE id = %d", (int) $decree['workshop_id'] ) );
			if ( $ws_row ) {
				$workshop = array(
					'id'   => (int) $ws_row->id,
					'code' => (string) $ws_row->code,
					'name' => (string) $ws_row->name,
				);
			}
		}

		$leave_balances = self::leave_balances_summary( $user_id );
		$latest_payslip = self::latest_payslip( $user_id );
		$open_requests  = self::open_requests_count( $user_id );

		return WebinoCRM_Service_Base::success(
			array(
				'identity' => array(
					'user_id'         => $user_id,
					'display_name'    => $user ? $user->display_name : '',
					'first_name'      => $user ? (string) $user->first_name : '',
					'last_name'       => $user ? (string) $user->last_name : '',
					'email'           => $user ? (string) $user->user_email : '',
					'avatar_url'      => get_avatar_url( $user_id, array( 'size' => 96 ) ),
					'personnel_code'  => (string) get_user_meta( $user_id, 'webino_personnel_code', true ),
					'national_id'     => WebinoCRM_Hrm_Service::mask_national_id( $national_raw ),
					'insurance_number'=> (string) get_user_meta( $user_id, 'webino_insurance_number', true ),
					'job_title'       => $org['job_title_name'] ?: (string) ( $decree['job_title'] ?? $emp['job_title'] ?? '' ),
					'department'      => $org['department_name'] ?: (string) ( $decree['department'] ?? '' ),
					'direct_manager'  => $mgr,
					'workshop'        => $workshop,
					'hire_date'       => self::format_date_field( (string) get_user_meta( $user_id, 'webino_hire_date', true ) ),
					'contract_type'   => (string) ( $decree['contract_type'] ?? get_user_meta( $user_id, 'webino_contract_type', true ) ),
				),
				'decree'          => $decree,
				'leave_balances'  => $leave_balances,
				'latest_payslip'  => $latest_payslip,
				'open_requests'   => $open_requests,
			)
		);
	}

	/**
	 * @param int $user_id User id.
	 * @return array<string,mixed>|null
	 */
	private static function current_decree( $user_id ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND status IN ('active','approved') ORDER BY effective_from DESC, id DESC LIMIT 1",
				(int) $user_id
			)
		);
		if ( ! $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE user_id = %d ORDER BY effective_from DESC, id DESC LIMIT 1",
					(int) $user_id
				)
			);
		}
		if ( ! $row ) {
			return null;
		}
		return array(
			'id'              => (int) $row->id,
			'decree_no'       => (string) $row->decree_no,
			'decree_type'     => (string) $row->decree_type,
			'status'          => (string) $row->status,
			'effective_from'  => self::format_date_field( (string) $row->effective_from ),
			'effective_to'    => self::format_date_field( (string) $row->effective_to ),
			'job_title'       => (string) $row->job_title,
			'department'      => (string) $row->department,
			'contract_type'   => (string) $row->contract_type,
			'workshop_id'     => (int) ( $row->workshop_id ?? 0 ),
			'base_salary'     => (float) ( $row->base_salary ?? 0 ),
		);
	}

	/**
	 * @param int $user_id User id.
	 * @return array<string,mixed>
	 */
	private static function org_context( $user_id ) {
		$out = array(
			'department_id'   => 0,
			'department_name' => '',
			'job_title_id'    => 0,
			'job_title_name'  => '',
		);
		if ( ! taxonomy_exists( 'organizational_position' ) ) {
			return $out;
		}
		$positions = wp_get_object_terms( (int) $user_id, 'organizational_position' );
		if ( is_wp_error( $positions ) || empty( $positions ) ) {
			return $out;
		}
		$pos = $positions[0];
		if ( (int) $pos->parent > 0 ) {
			$out['job_title_id']   = (int) $pos->term_id;
			$out['job_title_name'] = (string) $pos->name;
			$dept                  = get_term( (int) $pos->parent, 'organizational_position' );
			if ( $dept && ! is_wp_error( $dept ) ) {
				$out['department_id']   = (int) $dept->term_id;
				$out['department_name'] = (string) $dept->name;
			}
		} else {
			$out['department_id']   = (int) $pos->term_id;
			$out['department_name'] = (string) $pos->name;
		}
		return $out;
	}

	/**
	 * @param int                  $user_id User id.
	 * @param array<string,mixed>  $org     Org context.
	 * @return array<string,mixed>|null
	 */
	private static function direct_manager( $user_id, array $org ) {
		$meta_mgr = (string) get_user_meta( $user_id, 'webino_direct_manager', true );
		if ( $meta_mgr ) {
			return array( 'name' => $meta_mgr );
		}
		if ( empty( $org['department_id'] ) || ! taxonomy_exists( 'organizational_position' ) ) {
			return null;
		}
		$users = get_users(
			array(
				'meta_key'   => '_department_manager_dept_ids',
				'meta_compare' => 'EXISTS',
				'fields'     => array( 'ID', 'display_name' ),
			)
		);
		foreach ( $users as $u ) {
			$dept_ids = get_user_meta( (int) $u->ID, '_department_manager_dept_ids', true );
			if ( is_array( $dept_ids ) && in_array( (int) $org['department_id'], array_map( 'intval', $dept_ids ), true ) ) {
				return array(
					'id'   => (int) $u->ID,
					'name' => (string) $u->display_name,
				);
			}
		}
		return null;
	}

	/**
	 * @param int $user_id User id.
	 * @return array<int,array<string,mixed>>
	 */
	private static function leave_balances_summary( $user_id ) {
		global $wpdb;
		$year    = function_exists( 'webino_current_jalali_year' ) ? (int) webino_current_jalali_year() : (int) gmdate( 'Y' );
		$bal_tbl = WebinoCRM_Hrm_Service::table( 'leave_balances' );
		$typ_tbl = WebinoCRM_Hrm_Service::table( 'leave_types' );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, t.name AS type_name, t.code AS type_code
				FROM $bal_tbl b
				INNER JOIN $typ_tbl t ON t.id = b.leave_type_id
				WHERE b.user_id = %d AND b.year = %d",
				(int) $user_id,
				$year
			)
		);
		$items = array();
		foreach ( (array) $rows as $row ) {
			$allocated = (float) ( $row->allocated ?? $row->entitled ?? 0 );
			$used      = (float) ( $row->used ?? 0 );
			$items[]   = array(
				'leave_type_id' => (int) $row->leave_type_id,
				'type_name'     => (string) ( $row->type_name ?? '' ),
				'type_code'     => (string) ( $row->type_code ?? '' ),
				'year'          => (int) $row->year,
				'allocated'     => $allocated,
				'used'          => $used,
				'balance'       => (float) ( $row->balance ?? ( $allocated - $used ) ),
			);
		}
		return $items;
	}

	/**
	 * @param int $user_id User id.
	 * @return array<string,mixed>|null
	 */
	private static function latest_payslip( $user_id ) {
		global $wpdb;
		$p_table = WebinoCRM_Hrm_Service::table( 'payslips' );
		$r_table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$row     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, r.title AS run_title, r.jalali_year, r.jalali_month
				FROM $p_table p INNER JOIN $r_table r ON r.id = p.run_id
				WHERE p.user_id = %d ORDER BY r.jalali_year DESC, r.jalali_month DESC, p.id DESC LIMIT 1",
				(int) $user_id
			)
		);
		if ( ! $row ) {
			return null;
		}
		return array(
			'id'           => (int) $row->id,
			'run_title'    => (string) ( $row->run_title ?? '' ),
			'jalali_year'  => (int) ( $row->jalali_year ?? 0 ),
			'jalali_month' => (int) ( $row->jalali_month ?? 0 ),
			'gross'        => (float) ( $row->gross ?? 0 ),
			'net'          => (float) ( $row->net ?? 0 ),
			'deposit_date' => self::format_date_field( (string) ( $row->deposit_date ?? '' ) ),
		);
	}

	/**
	 * @param int $user_id User id.
	 * @return int
	 */
	private static function open_requests_count( $user_id ) {
		global $wpdb;
		if ( ! self::requests_table_exists() ) {
			return 0;
		}
		$table = WebinoCRM_Hrm_Service::table( 'requests' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d AND status IN ('pending_manager','pending_hr','draft')",
				(int) $user_id
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function requests_table_exists() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'requests' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * @param string $date Date.
	 * @return string
	 */
	private static function format_date_field( $date ) {
		$date = (string) $date;
		if ( ! $date || '0000-00-00' === $date ) {
			return '';
		}
		if ( function_exists( 'webino_gregorian_to_jalali' ) ) {
			return webino_gregorian_to_jalali( $date );
		}
		return $date;
	}

	public static function my_attendance( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		$user_id   = get_current_user_id();
		$date_from = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) );
		$date_to   = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) );
		$paged     = max( 1, WebinoCRM_Service_Base::int_param( $params, 'paged' ) ?: 1 );
		$per_page  = min( 100, max( 1, WebinoCRM_Service_Base::int_param( $params, 'per_page' ) ?: 31 ) );
		$offset    = ( $paged - 1 ) * $per_page;

		$table  = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		$where  = array( 'user_id = %d' );
		$values = array( $user_id );
		if ( $date_from ) {
			$where[]  = 'work_date >= %s';
			$values[] = $date_from;
		}
		if ( $date_to ) {
			$where[]  = 'work_date <= %s';
			$values[] = $date_to;
		}
		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where_sql", $values ) );
		$rows      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE $where_sql ORDER BY work_date DESC LIMIT %d OFFSET %d",
				array_merge( $values, array( $per_page, $offset ) )
			)
		);
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'         => (int) $row->id,
				'work_date'  => self::format_date_field( (string) $row->work_date ),
				'check_in'   => (string) ( $row->check_in ?? '' ),
				'check_out'  => (string) ( $row->check_out ?? '' ),
				'status'     => (string) ( $row->status ?? '' ),
				'notes'      => (string) ( $row->notes ?? '' ),
			);
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

	public static function my_shift( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		$user_id  = get_current_user_id();
		$shift_id = (int) get_user_meta( $user_id, 'webino_shift_template_id', true );
		if ( $shift_id <= 0 ) {
			return WebinoCRM_Service_Base::success( array( 'shift' => null ) );
		}
		$table = WebinoCRM_Hrm_Service::table( 'shift_templates' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $shift_id ) );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::success( array( 'shift' => null ) );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'shift' => array(
					'id'             => (int) $row->id,
					'name'           => (string) ( $row->name ?? '' ),
					'start_time'     => (string) ( $row->start_time ?? '' ),
					'end_time'       => (string) ( $row->end_time ?? '' ),
					'grace_minutes'  => (int) ( $row->grace_minutes ?? 0 ),
				),
			)
		);
	}

	public static function my_notices( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		if ( ! self::table_exists( 'notices' ) ) {
			return WebinoCRM_Service_Base::success( array( 'notices' => array() ) );
		}
		$today = current_time( 'Y-m-d' );
		$table = WebinoCRM_Hrm_Service::table( 'notices' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE is_active = 1 AND (date_from IS NULL OR date_from <= %s) AND (date_to IS NULL OR date_to >= %s) ORDER BY id DESC LIMIT 50",
				$today,
				$today
			)
		);
		$items = array();
		foreach ( (array) $rows as $row ) {
			$file_url = '';
			if ( ! empty( $row->attachment_id ) ) {
				$file_url = (string) wp_get_attachment_url( (int) $row->attachment_id );
			}
			$items[] = array(
				'id'         => (int) $row->id,
				'title'      => (string) $row->title,
				'body'       => (string) $row->body,
				'date_from'  => self::format_date_field( (string) ( $row->date_from ?? '' ) ),
				'date_to'    => self::format_date_field( (string) ( $row->date_to ?? '' ) ),
				'file_url'   => $file_url,
			);
		}
		return WebinoCRM_Service_Base::success( array( 'notices' => $items ) );
	}

	public static function my_dependents( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		if ( ! self::table_exists( 'dependents' ) ) {
			return WebinoCRM_Service_Base::success( array( 'dependents' => array() ) );
		}
		$user_id = get_current_user_id();
		$table   = WebinoCRM_Hrm_Service::table( 'dependents' );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY id ASC", $user_id ) );
		$items   = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'          => (int) $row->id,
				'full_name'   => (string) $row->full_name,
				'relation'    => (string) $row->relation,
				'national_id' => WebinoCRM_Hrm_Service::mask_national_id( (string) $row->national_id ),
				'birth_date'  => self::format_date_field( (string) ( $row->birth_date ?? '' ) ),
			);
		}
		return WebinoCRM_Service_Base::success( array( 'dependents' => $items ) );
	}

	public static function my_decrees( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		$user_id = get_current_user_id();
		$table   = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT id, decree_no, decree_type, status, effective_from, effective_to FROM $table WHERE user_id = %d ORDER BY effective_from DESC", $user_id ) );
		$items   = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'             => (int) $row->id,
				'decree_no'      => (string) $row->decree_no,
				'decree_type'    => (string) $row->decree_type,
				'status'         => (string) $row->status,
				'effective_from' => self::format_date_field( (string) $row->effective_from ),
				'effective_to'   => self::format_date_field( (string) $row->effective_to ),
			);
		}
		return WebinoCRM_Service_Base::success( array( 'decrees' => $items ) );
	}

	public static function my_assets( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		if ( ! self::table_exists( 'assets' ) ) {
			return WebinoCRM_Service_Base::success( array( 'assets' => array() ) );
		}
		$user_id = get_current_user_id();
		$table   = WebinoCRM_Hrm_Service::table( 'assets' );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC", $user_id ) );
		$items   = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'            => (int) $row->id,
				'asset_type'    => (string) $row->asset_type,
				'serial_number' => (string) $row->serial_number,
				'issued_at'     => self::format_date_field( (string) ( $row->issued_at ?? '' ) ),
				'returned_at'   => self::format_date_field( (string) ( $row->returned_at ?? '' ) ),
				'status'        => (string) ( $row->status ?? '' ),
			);
		}
		return WebinoCRM_Service_Base::success( array( 'assets' => $items ) );
	}

	public static function my_profile( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-profile-fields.php';
		$user_id = get_current_user_id();
		$profile = WebinoCRM_Hrm_Profile_Fields::read_profile( $user_id );
		if ( isset( $profile['sections']['identity_info']['fields']['national_id'] ) ) {
			$raw = $profile['sections']['identity_info']['fields']['national_id']['value'] ?? '';
			$profile['sections']['identity_info']['fields']['national_id']['value'] = WebinoCRM_Hrm_Service::mask_national_id( (string) $raw );
		}
		return WebinoCRM_Service_Base::success( array( 'profile' => $profile ) );
	}

	public static function org_chart( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_self();
		if ( $err ) {
			return $err;
		}
		if ( ! taxonomy_exists( 'organizational_position' ) ) {
			return WebinoCRM_Service_Base::success( array( 'departments' => array(), 'positions' => array() ) );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'organizational_position',
				'hide_empty' => false,
			)
		);
		$departments = array();
		$positions   = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$row = array(
					'id'     => (int) $term->term_id,
					'name'   => (string) $term->name,
					'parent' => (int) $term->parent,
				);
				if ( 0 === (int) $term->parent ) {
					$departments[] = $row;
				} else {
					$positions[] = $row;
				}
			}
		}
		return WebinoCRM_Service_Base::success(
			array(
				'departments' => $departments,
				'positions'   => $positions,
			)
		);
	}

	/**
	 * @param string $suffix Table suffix.
	 * @return bool
	 */
	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( $suffix );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
