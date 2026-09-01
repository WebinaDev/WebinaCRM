<?php
/**
 * HRM payroll service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Payroll_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
	 */
	private static function guard_payroll() {
		if ( ! WebinoCRM_Hrm_Service::can_manage_payroll() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return null;
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_run( $row ) {
		return array(
			'id'                => (int) $row->id,
			'title'             => (string) ( $row->title ?? '' ),
			'period_year'       => (int) ( $row->period_year ?? 0 ),
			'period_month'      => (int) ( $row->period_month ?? 0 ),
			'period_start'      => (string) ( $row->period_start ?? '' ),
			'period_end'        => (string) ( $row->period_end ?? '' ),
			'status'            => (string) ( $row->status ?? 'draft' ),
			'total_gross'       => (float) ( $row->total_gross ?? 0 ),
			'total_deductions'  => (float) ( $row->total_deductions ?? 0 ),
			'total_net'         => (float) ( $row->total_net ?? 0 ),
			'journal_entry_id'  => (int) ( $row->journal_entry_id ?? 0 ),
			'calculated_at'     => (string) ( $row->calculated_at ?? '' ),
			'approved_by'       => (int) ( $row->approved_by ?? 0 ),
			'approved_at'       => (string) ( $row->approved_at ?? '' ),
		);
	}

	public static function settings_get( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'payroll_settings' );
		$row   = $wpdb->get_row( "SELECT * FROM $table ORDER BY id ASC LIMIT 1", ARRAY_A );
		if ( ! $row ) {
			$row = array(
				'id'                          => 0,
				'salary_expense_account_id'   => 0,
				'salary_payable_account_id'   => 0,
				'insurance_payable_account_id'=> 0,
				'tax_payable_account_id'      => 0,
				'employer_insurance_expense_account_id' => 0,
				'unemployment_expense_account_id'       => 0,
				'unemployment_payable_account_id'       => 0,
				'default_workshop_id'         => 0,
			);
		}
		return WebinoCRM_Service_Base::success( array( 'settings' => $row ) );
	}

	public static function settings_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'payroll_settings' );
		$data  = array(
			'salary_expense_account_id'    => WebinoCRM_Service_Base::int_param( $params, 'salary_expense_account_id' ),
			'salary_payable_account_id'    => WebinoCRM_Service_Base::int_param( $params, 'salary_payable_account_id' ),
			'insurance_payable_account_id' => WebinoCRM_Service_Base::int_param( $params, 'insurance_payable_account_id' ),
			'tax_payable_account_id'       => WebinoCRM_Service_Base::int_param( $params, 'tax_payable_account_id' ),
			'employer_insurance_expense_account_id' => WebinoCRM_Service_Base::int_param( $params, 'employer_insurance_expense_account_id' ),
			'unemployment_expense_account_id'       => WebinoCRM_Service_Base::int_param( $params, 'unemployment_expense_account_id' ),
			'unemployment_payable_account_id'       => WebinoCRM_Service_Base::int_param( $params, 'unemployment_payable_account_id' ),
			'default_workshop_id'          => WebinoCRM_Service_Base::int_param( $params, 'default_workshop_id' ),
			'updated_at'                   => current_time( 'mysql' ),
		);

		$existing = $wpdb->get_var( "SELECT id FROM $table ORDER BY id ASC LIMIT 1" );
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ), null, array( '%d' ) );
			$id = (int) $existing;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'تنظیمات حقوق ذخیره شد.', 'webinocrm' ),
				'settings' => $row,
			)
		);
	}

	public static function components_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'salary_components' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY sort_order ASC, name ASC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'          => (int) $row->id,
				'name'        => (string) $row->name,
				'code'        => (string) ( $row->code ?? '' ),
				'type'        => (string) ( $row->type ?? 'earning' ),
				'is_taxable'  => (int) ( $row->is_taxable ?? 0 ),
				'is_fixed'    => (int) ( $row->is_fixed ?? 1 ),
				'formula'     => (string) ( $row->formula ?? '' ),
				'sort_order'  => (int) ( $row->sort_order ?? 0 ),
				'is_active'   => (int) ( $row->is_active ?? 1 ),
			);
		}
		return WebinoCRM_Service_Base::success( array( 'components' => $items ) );
	}

	public static function components_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$id         = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$name       = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) );
		$code       = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'code', '' ) );
		$type       = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'type', 'earning' ) );
		$is_taxable = ! empty( $params['is_taxable'] ) ? 1 : 0;
		$is_fixed   = ! isset( $params['is_fixed'] ) || ! empty( $params['is_fixed'] ) ? 1 : 0;
		$formula    = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'formula', '' ) );
		$sort_order = WebinoCRM_Service_Base::int_param( $params, 'sort_order' );
		$is_active  = ! isset( $params['is_active'] ) || ! empty( $params['is_active'] ) ? 1 : 0;

		if ( '' === $name ) {
			return WebinoCRM_Service_Base::error( __( 'نام جزء حقوق الزامی است.', 'webinocrm' ) );
		}
		if ( '' === $code ) {
			$code = sanitize_title( $name );
		}
		if ( ! in_array( $type, array( 'earning', 'deduction' ), true ) ) {
			$type = 'earning';
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'salary_components' );
		$data  = array(
			'name'       => $name,
			'code'       => $code,
			'type'       => $type,
			'is_taxable' => $is_taxable,
			'is_fixed'   => $is_fixed,
			'formula'    => $formula,
			'sort_order' => $sort_order,
			'is_active'  => $is_active,
			'updated_at' => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'جزء حقوق ذخیره شد.', 'webinocrm' ),
				'id'      => $id,
			)
		);
	}

	public static function employee_salary_get( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		if ( $user_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر نامعتبر است.', 'webinocrm' ) );
		}

		$table = WebinoCRM_Hrm_Service::table( 'employee_salaries' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT es.*, sc.name AS component_name, sc.code AS component_code, sc.type AS component_type
				FROM $table es
				LEFT JOIN " . WebinoCRM_Hrm_Service::table( 'salary_components' ) . " sc ON sc.id = es.component_id
				WHERE es.user_id = %d ORDER BY sc.sort_order ASC, es.id ASC",
				$user_id
			)
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'              => (int) $row->id,
				'user_id'         => (int) $row->user_id,
				'component_id'    => (int) $row->component_id,
				'component_name'  => (string) ( $row->component_name ?? '' ),
				'component_code'  => (string) ( $row->component_code ?? '' ),
				'component_type'  => (string) ( $row->component_type ?? '' ),
				'amount'          => (float) ( $row->amount ?? 0 ),
				'effective_from'  => (string) ( $row->effective_from ?? '' ),
				'effective_to'    => (string) ( $row->effective_to ?? '' ),
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'user_id'  => $user_id,
				'salaries' => $items,
			)
		);
	}

	public static function employee_salary_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$user_id        = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$component_id   = WebinoCRM_Service_Base::int_param( $params, 'component_id' );
		$amount         = (float) WebinoCRM_Service_Base::param( $params, 'amount', 0 );
		$effective_from = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'effective_from', current_time( 'Y-m-d' ) ) );
		$effective_to   = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'effective_to', '' ) );
		$id             = WebinoCRM_Service_Base::int_param( $params, 'id' );

		if ( $user_id <= 0 || $component_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر و جزء حقوق الزامی است.', 'webinocrm' ) );
		}
		if ( ! WebinoCRM_Hrm_Service::is_staff_user( $user_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر کارمند نیست.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'employee_salaries' );
		$data  = array(
			'user_id'        => $user_id,
			'component_id'   => $component_id,
			'amount'         => $amount,
			'effective_from' => $effective_from,
			'effective_to'   => $effective_to ?: null,
			'updated_at'     => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'حقوق کارمند ذخیره شد.', 'webinocrm' ),
				'id'      => $id,
			)
		);
	}

	public static function runs_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY period_year DESC, period_month DESC, id DESC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_run( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'runs' => $items ) );
	}

	public static function run_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$id           = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$period_year  = WebinoCRM_Service_Base::int_param( $params, 'period_year' ) ?: (int) current_time( 'Y' );
		$period_month = WebinoCRM_Service_Base::int_param( $params, 'period_month' ) ?: (int) current_time( 'n' );
		$title        = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'title', '' ) );
		$period_start = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'period_start', '' ) );
		$period_end   = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'period_end', '' ) );

		if ( ! $period_start ) {
			$period_start = sprintf( '%04d-%02d-01', $period_year, $period_month );
		}
		if ( ! $period_end ) {
			$period_end = gmdate( 'Y-m-t', strtotime( $period_start ) );
		}
		if ( '' === $title ) {
			$title = sprintf( __( 'حقوق %1$s/%2$s', 'webinocrm' ), $period_year, $period_month );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$data  = array(
			'title'        => $title,
			'period_year'  => $period_year,
			'period_month' => $period_month,
			'period_start' => $period_start,
			'period_end'   => $period_end,
			'updated_at'   => $now,
		);

		if ( $id > 0 ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $id ) );
			if ( $existing && 'approved' === $existing->status ) {
				return WebinoCRM_Service_Base::error( __( 'دوره تأیید شده قابل ویرایش نیست.', 'webinocrm' ) );
			}
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['status']     = 'draft';
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'دوره حقوق ذخیره شد.', 'webinocrm' ),
				'run'     => $row ? self::format_run( $row ) : null,
			)
		);
	}

	public static function run_calculate( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه دوره نامعتبر است.', 'webinocrm' ) );
		}

		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-calculator.php';
		$result = WebinoCRM_Hrm_Payroll_Calculator::calculate_run( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}

		return WebinoCRM_Service_Base::success(
			array_merge(
				array( 'message' => __( 'حقوق محاسبه شد.', 'webinocrm' ) ),
				$result
			)
		);
	}

	public static function run_approve( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}

		$id    = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$run   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		if ( ! $run ) {
			return WebinoCRM_Service_Base::error( __( 'دوره حقوق یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( 'calculated' !== $run->status ) {
			return WebinoCRM_Service_Base::error( __( 'ابتدا حقوق را محاسبه کنید.', 'webinocrm' ) );
		}

		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-finance-integration.php';
		$journal_id = WebinoCRM_Hrm_Payroll_Finance_Integration::create_journal_for_run( $id );
		if ( is_wp_error( $journal_id ) ) {
			return WebinoCRM_Service_Base::error( $journal_id->get_error_message() );
		}

		$now = current_time( 'mysql' );
		$wpdb->update(
			$table,
			array(
				'status'      => 'approved',
				'approved_by' => get_current_user_id(),
				'approved_at' => $now,
				'updated_at'  => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		$payslips = WebinoCRM_Hrm_Service::table( 'payslips' );
		$wpdb->update(
			$payslips,
			array( 'status' => 'approved', 'updated_at' => $now ),
			array( 'run_id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message'          => __( 'دوره حقوق تأیید و سند حسابداری ثبت شد.', 'webinocrm' ),
				'run'              => $row ? self::format_run( $row ) : null,
				'journal_entry_id' => (int) $journal_id,
			)
		);
	}

	public static function run_get( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه دوره نامعتبر است.', 'webinocrm' ) );
		}

		$can_payroll = WebinoCRM_Hrm_Service::can_manage_payroll();
		if ( ! $can_payroll && ! WebinoCRM_Hrm_Service::is_staff_user() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'دوره حقوق یافت نشد.', 'webinocrm' ), 404 );
		}

		return WebinoCRM_Service_Base::success( array( 'run' => self::format_run( $row ) ) );
	}

	public static function payslips_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$run_id = WebinoCRM_Service_Base::int_param( $params, 'run_id' );
		if ( $run_id <= 0 ) {
			$run_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		if ( $run_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه دوره نامعتبر است.', 'webinocrm' ) );
		}

		$can_payroll = WebinoCRM_Hrm_Service::can_manage_payroll();
		$user_id     = WebinoCRM_Service_Base::int_param( $params, 'user_id' );

		$table  = WebinoCRM_Hrm_Service::table( 'payslips' );
		$where  = 'run_id = %d';
		$values = array( $run_id );

		if ( ! $can_payroll ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id > 0 ) {
			$where   .= ' AND user_id = %d';
			$values[] = $user_id;
		} elseif ( ! $can_payroll ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY user_id ASC", $values ) );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$user = get_userdata( (int) $row->user_id );
			$items[] = array(
				'id'              => (int) $row->id,
				'run_id'          => (int) $row->run_id,
				'user_id'         => (int) $row->user_id,
				'user_name'       => $user ? $user->display_name : '',
				'gross'           => (float) ( $row->gross ?? 0 ),
				'deductions'      => (float) ( $row->deductions ?? 0 ),
				'net'             => (float) ( $row->net ?? 0 ),
				'components'      => json_decode( (string) ( $row->components_json ?? '[]' ), true ) ?: array(),
				'status'          => (string) ( $row->status ?? '' ),
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'run_id'   => $run_id,
				'payslips' => $items,
			)
		);
	}

	public static function register_actions() {}

	public static function workshops_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		$table = WebinoCRM_Hrm_Service::table( 'workshops' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY is_default DESC, name ASC" );
		return WebinoCRM_Service_Base::success( array( 'items' => (array) $rows ) );
	}

	public static function workshops_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		$id   = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$code = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'code', '' ) );
		$name = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) );
		if ( '' === $code || '' === $name ) {
			return WebinoCRM_Service_Base::error( __( 'کد و نام کارگاه الزامی است.', 'webinocrm' ) );
		}
		$data = array(
			'code'          => $code,
			'name'          => $name,
			'row_code'      => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'row_code', '' ) ),
			'branch_code'   => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'branch_code', '' ) ),
			'branch_name'   => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'branch_name', '' ) ),
			'address'       => sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'address', '' ) ),
			'hardship_rate' => (float) WebinoCRM_Service_Base::param( $params, 'hardship_rate', 0 ),
			'is_default'    => ! empty( $params['is_default'] ) ? 1 : 0,
			'is_active'     => ! isset( $params['is_active'] ) || ! empty( $params['is_active'] ) ? 1 : 0,
			'updated_at'    => current_time( 'mysql' ),
		);
		$table = WebinoCRM_Hrm_Service::table( 'workshops' );
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'کارگاه ذخیره شد.', 'webinocrm' ), 'id' => $id ) );
	}

	public static function decrees_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$table   = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		if ( $user_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY effective_from DESC, id DESC", $user_id ) );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY effective_from DESC, id DESC LIMIT 500" );
		}
		return WebinoCRM_Service_Base::success( array( 'decrees' => (array) $rows ) );
	}

	public static function decrees_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		$id      = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		if ( $user_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر الزامی است.', 'webinocrm' ) );
		}
		$decree_no = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'decree_no', '' ) );
		if ( '' === $decree_no ) {
			$decree_no = 'HRM-' . $user_id . '-' . time();
		}
		$data = array(
			'user_id'                     => $user_id,
			'decree_no'                   => $decree_no,
			'decree_type'                 => sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'decree_type', 'hire' ) ),
			'issue_date'                  => WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'issue_date', '' ) ) ?: null,
			'effective_from'              => WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'effective_from', current_time( 'Y-m-d' ) ) ),
			'effective_to'                => WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'effective_to', '' ) ) ?: null,
			'status'                      => sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'draft' ) ),
			'workshop_id'                 => WebinoCRM_Service_Base::int_param( $params, 'workshop_id' ) ?: null,
			'job_title'                   => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'job_title', '' ) ),
			'job_code'                    => sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'job_code', '' ) ),
			'daily_wage'                  => (float) WebinoCRM_Service_Base::param( $params, 'daily_wage', 0 ),
			'base_salary'                 => (float) WebinoCRM_Service_Base::param( $params, 'base_salary', 0 ),
			'benefit_food'                => (float) WebinoCRM_Service_Base::param( $params, 'benefit_food', 0 ),
			'benefit_housing'             => (float) WebinoCRM_Service_Base::param( $params, 'benefit_housing', 0 ),
			'benefit_child'               => (float) WebinoCRM_Service_Base::param( $params, 'benefit_child', 0 ),
			'benefit_marriage'            => (float) WebinoCRM_Service_Base::param( $params, 'benefit_marriage', 0 ),
			'benefit_seniority'           => (float) WebinoCRM_Service_Base::param( $params, 'benefit_seniority', 0 ),
			'benefit_transport'           => (float) WebinoCRM_Service_Base::param( $params, 'benefit_transport', 0 ),
			'benefit_responsibility'      => (float) WebinoCRM_Service_Base::param( $params, 'benefit_responsibility', 0 ),
			'benefit_other_insurable'     => (float) WebinoCRM_Service_Base::param( $params, 'benefit_other_insurable', 0 ),
			'benefit_other_non_insurable' => (float) WebinoCRM_Service_Base::param( $params, 'benefit_other_non_insurable', 0 ),
			'hardship_rate'               => (float) WebinoCRM_Service_Base::param( $params, 'hardship_rate', 0 ),
			'notes'                       => sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) ),
			'updated_at'                  => current_time( 'mysql' ),
		);
		$table = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'حکم ذخیره شد.', 'webinocrm' ), 'id' => $id ) );
	}

	public static function payroll_attendance_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$jy      = WebinoCRM_Service_Base::int_param( $params, 'jalali_year' );
		$jm      = WebinoCRM_Service_Base::int_param( $params, 'jalali_month' );
		if ( ! WebinoCRM_Hrm_Service::can_manage_payroll() ) {
			$user_id = get_current_user_id();
		}
		$table  = WebinoCRM_Hrm_Service::table( 'payroll_attendance' );
		$where  = '1=1';
		$values = array();
		if ( $user_id > 0 ) {
			$where   .= ' AND user_id = %d';
			$values[] = $user_id;
		}
		if ( $jy > 0 ) {
			$where   .= ' AND jalali_year = %d';
			$values[] = $jy;
		}
		if ( $jm > 0 ) {
			$where   .= ' AND jalali_month = %d';
			$values[] = $jm;
		}
		$sql  = "SELECT * FROM $table WHERE $where ORDER BY jalali_year DESC, jalali_month DESC";
		$rows = $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );
		return WebinoCRM_Service_Base::success( array( 'items' => (array) $rows ) );
	}

	public static function payroll_attendance_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		$user_id = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$jy      = WebinoCRM_Service_Base::int_param( $params, 'jalali_year' );
		$jm      = WebinoCRM_Service_Base::int_param( $params, 'jalali_month' );
		if ( ! $user_id || ! $jy || $jm < 1 || $jm > 12 ) {
			return WebinoCRM_Service_Base::error( __( 'کاربر و سال/ماه شمسی الزامی است.', 'webinocrm' ) );
		}
		$table = WebinoCRM_Hrm_Service::table( 'payroll_attendance' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d AND jalali_year = %d AND jalali_month = %d", $user_id, $jy, $jm ) );
		$data = array(
			'user_id'             => $user_id,
			'jalali_year'         => $jy,
			'jalali_month'        => $jm,
			'absent_days'         => (float) WebinoCRM_Service_Base::param( $params, 'absent_days', 0 ),
			'leave_days'          => (float) WebinoCRM_Service_Base::param( $params, 'leave_days', 0 ),
			'unpaid_leave_days'   => (float) WebinoCRM_Service_Base::param( $params, 'unpaid_leave_days', 0 ),
			'sick_leave_days'     => (float) WebinoCRM_Service_Base::param( $params, 'sick_leave_days', 0 ),
			'overtime_hours'      => (float) WebinoCRM_Service_Base::param( $params, 'overtime_hours', 0 ),
			'night_hours'         => (float) WebinoCRM_Service_Base::param( $params, 'night_hours', 0 ),
			'holiday_hours'       => (float) WebinoCRM_Service_Base::param( $params, 'holiday_hours', 0 ),
			'volume_qty'          => (float) WebinoCRM_Service_Base::param( $params, 'volume_qty', 0 ),
			'piece_rate'          => (float) WebinoCRM_Service_Base::param( $params, 'piece_rate', 0 ),
			'loan_deduction'      => (float) WebinoCRM_Service_Base::param( $params, 'loan_deduction', 0 ),
			'advance_deduction'   => (float) WebinoCRM_Service_Base::param( $params, 'advance_deduction', 0 ),
			'notes'               => sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) ),
			'updated_at'          => current_time( 'mysql' ),
		);
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			$id = (int) $existing;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'کارکرد ذخیره شد.', 'webinocrm' ), 'id' => $id ) );
	}

	public static function tamin_jobs_search( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/tamin/class-hrm-tamin-jobs.php';
		WebinoCRM_Hrm_Tamin_Jobs::maybe_seed();
		$q     = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'q', '' ) );
		$limit = WebinoCRM_Service_Base::int_param( $params, 'limit' ) ?: 50;
		$result = WebinoCRM_Hrm_Tamin_Jobs::search( $q, $limit );
		return WebinoCRM_Service_Base::success( $result );
	}

	public static function tamin_dsk_export( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/tamin/class-hrm-tamin-list.php';
		$run_id = WebinoCRM_Service_Base::int_param( $params, 'run_id' );
		if ( ! $run_id ) {
			$run_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		$result = WebinoCRM_Hrm_Tamin_List::export_zip( $run_id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( $result );
	}

	public static function tamin_dsk_preview( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/tamin/class-hrm-tamin-list.php';
		$run_id = WebinoCRM_Service_Base::int_param( $params, 'run_id' ) ?: WebinoCRM_Service_Base::int_param( $params, 'id' );
		$result = WebinoCRM_Hrm_Tamin_List::preview( $run_id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( $result );
	}

	public static function print_payslip( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-print.php';
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( ! empty( $params['return_html'] ) ) {
			$html = WebinoCRM_Hrm_Payroll_Print::payslip( $id, true );
			if ( is_wp_error( $html ) ) {
				return WebinoCRM_Service_Base::error( $html->get_error_message(), (int) ( $html->get_error_data()['status'] ?? 400 ) );
			}
			return WebinoCRM_Service_Base::success( array( 'html' => $html ) );
		}
		WebinoCRM_Hrm_Payroll_Print::payslip( $id, false );
		return WebinoCRM_Service_Base::success( array() );
	}

	public static function print_decree( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-print.php';
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( ! empty( $params['return_html'] ) ) {
			$html = WebinoCRM_Hrm_Payroll_Print::decree( $id, true );
			if ( is_wp_error( $html ) ) {
				return WebinoCRM_Service_Base::error( $html->get_error_message(), (int) ( $html->get_error_data()['status'] ?? 400 ) );
			}
			return WebinoCRM_Service_Base::success( array( 'html' => $html ) );
		}
		WebinoCRM_Hrm_Payroll_Print::decree( $id, false );
		return WebinoCRM_Service_Base::success( array() );
	}

	public static function my_payslips( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! WebinoCRM_Hrm_Service::is_staff_user() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$user_id = get_current_user_id();
		$table   = WebinoCRM_Hrm_Service::table( 'payslips' );
		$runs    = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, r.title AS run_title, r.jalali_year, r.jalali_month, r.period_year, r.period_month, r.status AS run_status
				FROM $table p INNER JOIN $runs r ON r.id = p.run_id
				WHERE p.user_id = %d ORDER BY r.jalali_year DESC, r.jalali_month DESC, p.id DESC",
				$user_id
			)
		);
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'           => (int) $row->id,
				'run_id'       => (int) $row->run_id,
				'run_title'    => (string) ( $row->run_title ?? '' ),
				'jalali_year'  => (int) ( $row->jalali_year ?? 0 ),
				'jalali_month' => (int) ( $row->jalali_month ?? 0 ),
				'gross'        => (float) ( $row->gross ?? 0 ),
				'deductions'   => (float) ( $row->deductions ?? 0 ),
				'net'          => (float) ( $row->net ?? 0 ),
				'status'       => (string) ( $row->status ?? '' ),
				'days_worked'  => (float) ( $row->days_worked ?? 0 ),
				'overtime'     => (float) ( $row->overtime ?? 0 ),
				'employee_insurance' => (float) ( $row->employee_insurance ?? 0 ),
				'employer_insurance' => (float) ( $row->employer_insurance ?? 0 ),
				'tax'          => (float) ( $row->tax ?? 0 ),
				'loan_deduction' => (float) ( $row->loan_deduction ?? 0 ),
				'advance_deduction' => (float) ( $row->advance_deduction ?? 0 ),
				'deposit_date' => (string) ( $row->deposit_date ?? '' ),
				'iban'         => (string) get_user_meta( $user_id, 'webino_iban', true ),
			);
		}
		return WebinoCRM_Service_Base::success( array( 'payslips' => $items ) );
	}

	public static function payroll_config_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-config.php';
		return WebinoCRM_Service_Base::success( array( 'config' => WebinoCRM_Hrm_Payroll_Config::get() ) );
	}

	public static function payroll_config_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_payroll();
		if ( $err ) {
			return $err;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-config.php';
		$config = WebinoCRM_Hrm_Payroll_Config::save( $params );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'تنظیمات حقوق ذخیره شد.', 'webinocrm' ), 'config' => $config ) );
	}
}
