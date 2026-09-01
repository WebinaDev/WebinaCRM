<?php
/**
 * HRM unified request / cartable workflow engine.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Request_Service {

	const REQUEST_TYPES = array(
		'leave',
		'mission',
		'petty_cash',
		'certificate_employment',
		'certificate_deduction',
		'loan',
		'advance',
		'profile_change',
		'attendance_dispute',
		'payslip_dispute',
		'equipment',
		'exit',
	);

	/**
	 * @return bool
	 */
	private static function table_ready() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'requests' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * @param object|null $row Request row.
	 * @return array<string,mixed>|null
	 */
	private static function format_request( $row ) {
		if ( ! $row ) {
			return null;
		}
		$user = get_userdata( (int) $row->user_id );
		$payload = json_decode( (string) ( $row->payload_json ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		return array(
			'id'           => (int) $row->id,
			'type'         => (string) $row->type,
			'user_id'      => (int) $row->user_id,
			'user_name'    => $user ? $user->display_name : '',
			'status'       => (string) $row->status,
			'payload'      => $payload,
			'manager_id'   => (int) ( $row->manager_id ?? 0 ),
			'hr_user_id'   => (int) ( $row->hr_user_id ?? 0 ),
			'ref_id'       => (int) ( $row->ref_id ?? 0 ),
			'notes'        => (string) ( $row->notes ?? '' ),
			'created_at'   => (string) ( $row->created_at ?? '' ),
			'updated_at'   => (string) ( $row->updated_at ?? '' ),
		);
	}

	/**
	 * @param int $user_id Employee user id.
	 * @return int Manager user id or 0.
	 */
	public static function resolve_manager_id( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( ! taxonomy_exists( 'organizational_position' ) ) {
			return 0;
		}
		$positions = wp_get_object_terms( $user_id, 'organizational_position' );
		if ( is_wp_error( $positions ) || empty( $positions ) ) {
			return 0;
		}
		$pos  = $positions[0];
		$dept = (int) $pos->parent > 0 ? (int) $pos->parent : (int) $pos->term_id;
		$users = get_users(
			array(
				'meta_key'     => '_department_manager_dept_ids',
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
			)
		);
		foreach ( $users as $mgr_id ) {
			$dept_ids = get_user_meta( (int) $mgr_id, '_department_manager_dept_ids', true );
			if ( is_array( $dept_ids ) && in_array( $dept, array_map( 'intval', $dept_ids ), true ) ) {
				return (int) $mgr_id;
			}
		}
		return 0;
	}

	/**
	 * Create cartable row from employee submission.
	 *
	 * @param string              $type    Request type.
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @param int                 $ref_id  Optional linked record id.
	 * @return int Request id.
	 */
	public static function create_request( $type, $user_id, array $payload, $ref_id = 0 ) {
		global $wpdb;
		if ( ! self::table_ready() ) {
			return 0;
		}
		$type    = sanitize_key( (string) $type );
		$user_id = (int) $user_id;
		if ( ! in_array( $type, self::REQUEST_TYPES, true ) ) {
			return 0;
		}
		$manager_id = self::resolve_manager_id( $user_id );
		$status     = $manager_id > 0 ? 'pending_manager' : 'pending_hr';
		$now        = current_time( 'mysql' );
		$table      = WebinoCRM_Hrm_Service::table( 'requests' );
		$wpdb->insert(
			$table,
			array(
				'type'         => $type,
				'user_id'      => $user_id,
				'status'       => $status,
				'payload_json' => wp_json_encode( $payload ),
				'manager_id'   => $manager_id,
				'ref_id'       => (int) $ref_id,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function my_requests( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! WebinoCRM_Hrm_Service::can_access_hrm_self() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( ! self::table_ready() ) {
			return WebinoCRM_Service_Base::success( array( 'requests' => array() ) );
		}
		$user_id = get_current_user_id();
		$status  = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) );
		$table   = WebinoCRM_Hrm_Service::table( 'requests' );
		if ( $status ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND status = %s ORDER BY id DESC LIMIT 100", $user_id, $status ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 100", $user_id ) );
		}
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_request( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'requests' => $items ) );
	}

	public static function inbox( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() && empty( WebinoCRM_Hrm_Service::managed_department_ids() ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( ! self::table_ready() ) {
			return WebinoCRM_Service_Base::success( array( 'requests' => array() ) );
		}
		$current = get_current_user_id();
		$is_hr   = WebinoCRM_Hrm_Service::can_manage_hrm();
		$table   = WebinoCRM_Hrm_Service::table( 'requests' );
		if ( $is_hr ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE status IN ('pending_hr','pending_manager') ORDER BY FIELD(status,'pending_hr','pending_manager'), id DESC LIMIT 200"
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE manager_id = %d AND status = 'pending_manager' ORDER BY id DESC LIMIT 200",
					$current
				)
			);
		}
		$items = array();
		foreach ( (array) $rows as $row ) {
			if ( ! $is_hr && 'pending_manager' !== $row->status ) {
				continue;
			}
			$items[] = self::format_request( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'requests' => $items ) );
	}

	public static function submit( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! WebinoCRM_Hrm_Service::can_access_hrm_self() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( ! self::table_ready() ) {
			return WebinoCRM_Service_Base::error( __( 'سیستم درخواست هنوز آماده نیست.', 'webinocrm' ) );
		}

		$type = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'type', '' ) );
		if ( ! in_array( $type, self::REQUEST_TYPES, true ) ) {
			return WebinoCRM_Service_Base::error( __( 'نوع درخواست نامعتبر است.', 'webinocrm' ) );
		}

		$payload_raw = WebinoCRM_Service_Base::param( $params, 'payload', array() );
		$payload     = is_array( $payload_raw ) ? $payload_raw : json_decode( (string) $payload_raw, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$user_id = get_current_user_id();
		$result  = self::handle_type_submit( $type, $user_id, $payload );
		if ( is_array( $result ) && empty( $result['success'] ) ) {
			return $result;
		}

		$ref_id = is_array( $result ) && isset( $result['ref_id'] ) ? (int) $result['ref_id'] : 0;
		$id     = 0;
		if ( in_array( $type, array( 'leave', 'mission' ), true ) && $ref_id > 0 ) {
			$id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}webinocrm_hrm_requests WHERE ref_id = %d AND type = %s ORDER BY id DESC LIMIT 1",
					$ref_id,
					$type
				)
			);
		}
		if ( $id <= 0 ) {
			$id = self::create_request( $type, $user_id, $payload, $ref_id );
		}
		$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}webinocrm_hrm_requests WHERE id = %d", $id ) ) : null;

		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'درخواست ثبت شد.', 'webinocrm' ),
				'request' => self::format_request( $row ),
			)
		);
	}

	/**
	 * @param string              $type    Type.
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return array<string,mixed>
	 */
	private static function handle_type_submit( $type, $user_id, array $payload ) {
		switch ( $type ) {
			case 'leave':
			case 'mission':
				return self::submit_leave_or_mission( $type, $user_id, $payload );
			case 'profile_change':
				return array( 'success' => true );
			default:
				return array( 'success' => true );
		}
	}

	/**
	 * @param string              $type    leave|mission.
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return array<string,mixed>
	 */
	private static function submit_leave_or_mission( $type, $user_id, array $payload ) {
		global $wpdb;
		$leave_type_id = WebinoCRM_Service_Base::int_param( $payload, 'leave_type_id' );
		$date_from     = WebinoCRM_Service_Base::normalize_date( (string) ( $payload['date_from'] ?? '' ) );
		$date_to       = WebinoCRM_Service_Base::normalize_date( (string) ( $payload['date_to'] ?? '' ) );
		$reason        = sanitize_textarea_field( (string) ( $payload['reason'] ?? '' ) );

		if ( 'mission' === $type && $leave_type_id <= 0 ) {
			$typ_tbl = WebinoCRM_Hrm_Service::table( 'leave_types' );
			$leave_type_id = (int) $wpdb->get_var( "SELECT id FROM $typ_tbl WHERE code = 'mission' LIMIT 1" );
		}
		if ( $leave_type_id <= 0 || ! $date_from || ! $date_to ) {
			return WebinoCRM_Service_Base::error( __( 'نوع مرخصی و بازه تاریخ الزامی است.', 'webinocrm' ) );
		}

		$res = WebinoCRM_Hrm_Leave_Service::request_save(
			array(
				'user_id'       => $user_id,
				'leave_type_id' => $leave_type_id,
				'date_from'     => $date_from,
				'date_to'       => $date_to,
				'reason'        => $reason,
			)
		);
		if ( empty( $res['success'] ) ) {
			return $res;
		}
		$ref_id = isset( $res['data']['request']['id'] ) ? (int) $res['data']['request']['id'] : 0;
		return array( 'success' => true, 'ref_id' => $ref_id );
	}

	public static function manager_action( array $params ) {
		return self::transition( $params, 'manager' );
	}

	public static function hr_action( array $params ) {
		return self::transition( $params, 'hr' );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string            $actor  manager|hr.
	 * @return array<string,mixed>
	 */
	private static function transition( array $params, $actor ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! self::table_ready() ) {
			return WebinoCRM_Service_Base::error( __( 'سیستم درخواست آماده نیست.', 'webinocrm' ) );
		}

		$id     = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$action = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'action', '' ) );
		$notes  = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) );

		if ( $id <= 0 || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}

		$table = WebinoCRM_Hrm_Service::table( 'requests' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست یافت نشد.', 'webinocrm' ), 404 );
		}

		$current = get_current_user_id();
		if ( 'manager' === $actor ) {
			if ( (int) $row->manager_id !== $current && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
			if ( 'pending_manager' !== $row->status ) {
				return WebinoCRM_Service_Base::error( __( 'وضعیت درخواست قابل تأیید مدیر نیست.', 'webinocrm' ) );
			}
			$new_status = 'reject' === $action ? 'rejected' : 'pending_hr';
		} else {
			if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
			if ( ! in_array( $row->status, array( 'pending_hr', 'pending_manager' ), true ) ) {
				return WebinoCRM_Service_Base::error( __( 'وضعیت درخواست قابل تأیید HR نیست.', 'webinocrm' ) );
			}
			$new_status = 'reject' === $action ? 'rejected' : 'approved';
		}

		$now  = current_time( 'mysql' );
		$data = array(
			'status'     => $new_status,
			'notes'      => $notes,
			'updated_at' => $now,
		);
		if ( 'manager' === $actor && 'approve' === $action ) {
			$data['manager_id'] = $current;
		}
		if ( 'hr' === $actor ) {
			$data['hr_user_id'] = $current;
		}

		$wpdb->update( $table, $data, array( 'id' => $id ) );

		if ( 'approved' === $new_status ) {
			self::apply_approval_effects( $row );
		} elseif ( 'rejected' === $new_status && ! empty( $row->ref_id ) && in_array( $row->type, array( 'leave', 'mission' ), true ) ) {
			WebinoCRM_Hrm_Leave_Service::request_reject( array( 'id' => (int) $row->ref_id, 'rejection_reason' => $notes ) );
		} elseif ( 'pending_hr' === $new_status && ! empty( $row->ref_id ) && in_array( $row->type, array( 'leave', 'mission' ), true ) ) {
			// Manager approved — leave stays pending until HR final approve.
		}

		if ( 'approved' === $new_status && ! empty( $row->ref_id ) && in_array( $row->type, array( 'leave', 'mission' ), true ) ) {
			WebinoCRM_Hrm_Leave_Service::request_approve( array( 'id' => (int) $row->ref_id ) );
		}

		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => 'approved' === $new_status
					? __( 'درخواست تأیید شد.', 'webinocrm' )
					: ( 'rejected' === $new_status ? __( 'درخواست رد شد.', 'webinocrm' ) : __( 'درخواست به HR ارجاع شد.', 'webinocrm' ) ),
				'request' => self::format_request( $updated ),
			)
		);
	}

	/**
	 * @param object $row Request row.
	 * @return void
	 */
	private static function apply_approval_effects( $row ) {
		$payload = json_decode( (string) ( $row->payload_json ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$user_id = (int) $row->user_id;
		$type    = (string) $row->type;

		switch ( $type ) {
			case 'profile_change':
				self::apply_profile_change( $user_id, $payload );
				break;
			case 'loan':
			case 'advance':
				self::apply_payroll_deduction( $user_id, $type, $payload );
				break;
			case 'certificate_employment':
			case 'certificate_deduction':
				self::archive_certificate( $user_id, $type, $payload, (int) $row->id );
				break;
			case 'equipment':
				self::assign_asset( $user_id, $payload );
				break;
			case 'exit':
				self::draft_termination_decree( $user_id, $payload );
				break;
			case 'attendance_dispute':
				self::apply_attendance_dispute( $user_id, $payload );
				break;
			case 'payslip_dispute':
				self::apply_payslip_dispute( $user_id, $payload );
				break;
			case 'petty_cash':
				// Mission petty cash — payload may include amount for treasury integration later.
				break;
		}
	}

	/**
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function apply_profile_change( $user_id, array $payload ) {
		$allowed = array( 'mobile_phone', 'landline_phone', 'address', 'iban', 'bank_name', 'bank_account_number' );
		foreach ( $allowed as $key ) {
			if ( isset( $payload[ $key ] ) ) {
				update_user_meta( $user_id, 'webino_' . $key, sanitize_text_field( (string) $payload[ $key ] ) );
			}
		}
	}

	/**
	 * @param int                 $user_id User id.
	 * @param string              $type    loan|advance.
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function apply_payroll_deduction( $user_id, $type, array $payload ) {
		global $wpdb;
		$amount = (float) ( $payload['amount'] ?? 0 );
		if ( $amount <= 0 ) {
			return;
		}
		$year  = function_exists( 'webino_current_jalali_year' ) ? (int) webino_current_jalali_year() : (int) gmdate( 'Y' );
		$month = function_exists( 'webino_current_jalali_month' ) ? (int) webino_current_jalali_month() : (int) gmdate( 'n' );
		$table = WebinoCRM_Hrm_Service::table( 'payroll_attendance' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND jalali_year = %d AND jalali_month = %d LIMIT 1",
				$user_id,
				$year,
				$month
			)
		);
		$col = 'loan' === $type ? 'loan_deduction' : 'advance_deduction';
		if ( $row ) {
			$wpdb->update(
				$table,
				array( $col => (float) $row->$col + $amount ),
				array( 'id' => (int) $row->id )
			);
		} else {
			$data = array(
				'user_id'      => $user_id,
				'jalali_year'  => $year,
				'jalali_month' => $month,
				$col           => $amount,
			);
			$wpdb->insert( $table, $data );
		}
	}

	/**
	 * @param int                 $user_id User id.
	 * @param string              $type    Certificate type.
	 * @param array<string,mixed> $payload Payload.
	 * @param int                 $req_id  Request id.
	 * @return void
	 */
	private static function archive_certificate( $user_id, $type, array $payload, $req_id ) {
		global $wpdb;
		if ( ! self::documents_table_ready() ) {
			return;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-certificate-print.php';
		$html = WebinoCRM_Hrm_Certificate_Print::render( $type, $user_id, $payload );
		$table = WebinoCRM_Hrm_Service::table( 'documents' );
		$now   = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'user_id'      => $user_id,
				'doc_type'     => $type,
				'title'        => 'certificate_employment' === $type ? __( 'گواهی اشتغال', 'webinocrm' ) : __( 'گواهی کسر از حقوق', 'webinocrm' ),
				'body_html'    => $html,
				'request_id'   => $req_id,
				'created_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function assign_asset( $user_id, array $payload ) {
		global $wpdb;
		if ( ! self::assets_table_ready() ) {
			return;
		}
		$table = WebinoCRM_Hrm_Service::table( 'assets' );
		$now   = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'asset_type'    => sanitize_key( (string) ( $payload['asset_type'] ?? 'other' ) ),
				'serial_number' => sanitize_text_field( (string) ( $payload['serial_number'] ?? '' ) ),
				'issued_at'     => current_time( 'Y-m-d' ),
				'status'        => 'assigned',
				'notes'         => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
	}

	/**
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function draft_termination_decree( $user_id, array $payload ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		$now   = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'decree_no'      => 'EXIT-' . $user_id . '-' . time(),
				'decree_type'    => 'terminate',
				'status'         => 'draft',
				'effective_from' => current_time( 'Y-m-d' ),
				'notes'          => sanitize_textarea_field( (string) ( $payload['reason'] ?? '' ) ),
				'created_by'     => get_current_user_id(),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
	}

	/**
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function apply_attendance_dispute( $user_id, array $payload ) {
		global $wpdb;
		$record_id = WebinoCRM_Service_Base::int_param( $payload, 'attendance_id' );
		if ( $record_id <= 0 ) {
			return;
		}
		$table = WebinoCRM_Hrm_Service::table( 'attendance_records' );
		$notes = sanitize_textarea_field( (string) ( $payload['resolution'] ?? '' ) );
		$data  = array( 'notes' => $notes );
		if ( ! empty( $payload['check_in'] ) ) {
			$data['check_in'] = sanitize_text_field( (string) $payload['check_in'] );
		}
		if ( ! empty( $payload['check_out'] ) ) {
			$data['check_out'] = sanitize_text_field( (string) $payload['check_out'] );
		}
		$wpdb->update( $table, $data, array( 'id' => $record_id, 'user_id' => $user_id ) );
	}

	/**
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function apply_payslip_dispute( $user_id, array $payload ) {
		global $wpdb;
		$payslip_id = WebinoCRM_Service_Base::int_param( $payload, 'payslip_id' );
		if ( $payslip_id <= 0 ) {
			return;
		}
		$table = WebinoCRM_Hrm_Service::table( 'payslips' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT meta_json FROM $table WHERE id = %d AND user_id = %d", $payslip_id, $user_id ) );
		if ( ! $row ) {
			return;
		}
		$meta = json_decode( (string) ( $row->meta_json ?? '{}' ), true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$meta['dispute_note'] = sanitize_textarea_field( (string) ( $payload['note'] ?? '' ) );
		$meta['dispute_resolved_at'] = current_time( 'mysql' );
		$wpdb->update( $table, array( 'meta_json' => wp_json_encode( $meta ) ), array( 'id' => $payslip_id ) );
	}

	/**
	 * Sync cartable when leave is approved via legacy HR leave page.
	 *
	 * @param int    $leave_id Leave request id.
	 * @param string $status   approved|rejected.
	 * @return void
	 */
	public static function sync_leave_cartable( $leave_id, $status ) {
		global $wpdb;
		if ( ! self::table_ready() ) {
			return;
		}
		$table = WebinoCRM_Hrm_Service::table( 'requests' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ref_id = %d AND type IN ('leave','mission') ORDER BY id DESC LIMIT 1", (int) $leave_id ) );
		if ( ! $row ) {
			return;
		}
		$new = 'approved' === $status ? 'approved' : 'rejected';
		$wpdb->update(
			$table,
			array(
				'status'     => $new,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $row->id )
		);
	}

	/**
	 * @return bool
	 */
	private static function documents_table_ready() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'documents' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * @return bool
	 */
	private static function assets_table_ready() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'assets' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	// —— HR staff admin: dependents / assets / shift ——

	public static function staff_dependents_list( array $params ) {
		return self::staff_collection_list( $params, 'dependents', 'can_manage_staff_user' );
	}

	public static function staff_dependents_save( array $params ) {
		return self::staff_dependent_save_impl( $params );
	}

	public static function staff_assets_list( array $params ) {
		return self::staff_collection_list( $params, 'assets', 'can_manage_staff_user' );
	}

	public static function staff_assets_save( array $params ) {
		return self::staff_asset_save_impl( $params );
	}

	public static function staff_shift_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		if ( $user_id <= 0 ) {
			$user_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$shift_id = (int) get_user_meta( $user_id, 'webino_shift_template_id', true );
		return WebinoCRM_Service_Base::success(
			array(
				'user_id'          => $user_id,
				'shift_template_id'=> $shift_id,
			)
		);
	}

	public static function staff_shift_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$user_id  = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$shift_id = WebinoCRM_Service_Base::int_param( $params, 'shift_template_id' );
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		update_user_meta( $user_id, 'webino_shift_template_id', $shift_id );
		return WebinoCRM_Service_Base::success(
			array(
				'message'           => __( 'شیفت کاری ذخیره شد.', 'webinocrm' ),
				'shift_template_id' => $shift_id,
			)
		);
	}

	public static function shift_templates_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$table = WebinoCRM_Hrm_Service::table( 'shift_templates' );
		$rows  = $wpdb->get_results( "SELECT id, name, start_time, end_time FROM $table ORDER BY name ASC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'         => (int) $row->id,
				'name'       => (string) $row->name,
				'start_time' => (string) $row->start_time,
				'end_time'   => (string) $row->end_time,
			);
		}
		return WebinoCRM_Service_Base::success( array( 'templates' => $items ) );
	}

	public static function certificate_print( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$type = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'type', '' ) );
		$req_id = WebinoCRM_Service_Base::int_param( $params, 'request_id' );
		if ( ! WebinoCRM_Hrm_Service::can_access_hrm_self() && ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-certificate-print.php';
		global $wpdb;
		if ( $req_id > 0 && self::documents_table_ready() ) {
			$table = WebinoCRM_Hrm_Service::table( 'documents' );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE request_id = %d LIMIT 1", $req_id ) );
			if ( $row && ( WebinoCRM_Hrm_Service::can_manage_hrm() || (int) $row->user_id === get_current_user_id() ) ) {
				return WebinoCRM_Service_Base::success( array( 'html' => (string) $row->body_html ) );
			}
		}
		$user_id = get_current_user_id();
		if ( WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			$uid = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
			if ( $uid > 0 ) {
				$user_id = $uid;
			}
		}
		$html = WebinoCRM_Hrm_Certificate_Print::render( $type, $user_id, array() );
		return WebinoCRM_Service_Base::success( array( 'html' => $html ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string            $suffix Table suffix.
	 * @param string            $guard  Guard method.
	 * @return array<string,mixed>
	 */
	private static function staff_collection_list( array $params, $suffix, $guard ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		if ( $user_id <= 0 ) {
			$user_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( ! self::table_exists( $suffix ) ) {
			return WebinoCRM_Service_Base::success( array( $suffix => array() ) );
		}
		$table = WebinoCRM_Hrm_Service::table( $suffix );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY id ASC", $user_id ) );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = (array) $row;
		}
		return WebinoCRM_Service_Base::success( array( $suffix => $items ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private static function staff_dependent_save_impl( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! self::table_exists( 'dependents' ) ) {
			return WebinoCRM_Service_Base::error( __( 'جدول وابستگان موجود نیست.', 'webinocrm' ) );
		}
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$id        = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$full_name = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'full_name', '' ) );
		if ( ! $full_name ) {
			return WebinoCRM_Service_Base::error( __( 'نام الزامی است.', 'webinocrm' ) );
		}
		$data = array(
			'user_id'     => $user_id,
			'full_name'   => $full_name,
			'relation'    => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'relation', '' ) ),
			'national_id' => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'national_id', '' ) ),
			'birth_date'  => WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'birth_date', '' ) ),
			'updated_at'  => current_time( 'mysql' ),
		);
		$table = WebinoCRM_Hrm_Service::table( 'dependents' );
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id, 'user_id' => $user_id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'ذخیره شد.', 'webinocrm' ), 'id' => $id ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private static function staff_asset_save_impl( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! self::table_exists( 'assets' ) ) {
			return WebinoCRM_Service_Base::error( __( 'جدول دارایی موجود نیست.', 'webinocrm' ) );
		}
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		if ( ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'user_id'       => $user_id,
			'asset_type'    => sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'asset_type', 'other' ) ),
			'serial_number' => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'serial_number', '' ) ),
			'issued_at'     => WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'issued_at', '' ) ) ?: current_time( 'Y-m-d' ),
			'returned_at'   => WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'returned_at', '' ) ),
			'status'        => sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'assigned' ) ),
			'notes'         => sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) ),
			'updated_at'    => current_time( 'mysql' ),
		);
		$table = WebinoCRM_Hrm_Service::table( 'assets' );
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id, 'user_id' => $user_id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'ذخیره شد.', 'webinocrm' ), 'id' => $id ) );
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
