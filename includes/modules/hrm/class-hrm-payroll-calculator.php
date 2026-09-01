<?php
/**
 * Iranian payroll calculation engine for HRM (Jalali, 7/20/3, decree-driven).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Payroll_Calculator {

	/**
	 * Calculate payslips for all active staff in a payroll run.
	 *
	 * @param int $run_id Payroll run id.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function calculate_run( $run_id ) {
		global $wpdb;

		$run_id = (int) $run_id;
		$runs   = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$run    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $runs WHERE id = %d", $run_id ) );
		if ( ! $run ) {
			return new WP_Error( 'not_found', __( 'دوره حقوق یافت نشد.', 'webinocrm' ) );
		}
		if ( in_array( (string) $run->status, array( 'approved' ), true ) ) {
			return new WP_Error( 'locked', __( 'دوره حقوق تأیید شده و قابل محاسبه مجدد نیست.', 'webinocrm' ) );
		}

		$jy = (int) ( $run->jalali_year ?? 0 );
		$jm = (int) ( $run->jalali_month ?? 0 );
		if ( ! $jy || ! $jm ) {
			$jy = (int) ( $run->period_year ?? 0 ) ?: (int) current_time( 'Y' );
			$jm = (int) ( $run->period_month ?? 0 ) ?: (int) current_time( 'n' );
		}
		$days = (int) ( $run->days_in_month ?? 0 ) ?: self::jalali_days_in_month( $jy, $jm );

		$staff_ids = self::staff_user_ids( (int) ( $run->workshop_id ?? 0 ) );
		$payslips  = WebinoCRM_Hrm_Service::table( 'payslips' );
		$now       = current_time( 'mysql' );

		$wpdb->delete( $payslips, array( 'run_id' => $run_id ), array( '%d' ) );

		$totals = array(
			'gross'          => 0.0,
			'deductions'     => 0.0,
			'net'            => 0.0,
			'insurable'      => 0.0,
			'emp_ins'        => 0.0,
			'er_ins'         => 0.0,
			'unemployment'   => 0.0,
			'tax'            => 0.0,
		);
		$generated = 0;

		foreach ( $staff_ids as $user_id ) {
			$emp  = self::employee_snapshot( (int) $user_id );
			$calc = self::calculate_slip( $emp, $jy, $jm, $days );
			if ( empty( $calc ) ) {
				continue;
			}

			$deductions = (float) $calc['employee_insurance'] + (float) $calc['tax'] + (float) $calc['other_deductions'];

			$wpdb->insert(
				$payslips,
				array(
					'run_id'                 => $run_id,
					'user_id'                => (int) $user_id,
					'decree_id'              => (int) ( $calc['decree_id'] ?? 0 ) ?: null,
					'days_worked'            => (float) $calc['days_worked'],
					'gross'                  => (float) $calc['gross'],
					'deductions'             => $deductions,
					'net'                    => (float) $calc['net'],
					'insurable_wage'         => (float) $calc['insurable_wage'],
					'insurable_capped'       => (float) $calc['insurable_capped'],
					'employee_insurance'     => (float) $calc['employee_insurance'],
					'employer_insurance'     => (float) $calc['employer_insurance'],
					'unemployment_insurance' => (float) $calc['unemployment_insurance'],
					'taxable_income'         => (float) $calc['taxable_income'],
					'tax'                    => (float) $calc['tax'],
					'overtime'               => (float) $calc['overtime'],
					'overtime_night'         => (float) $calc['overtime_night'],
					'overtime_holiday'       => (float) $calc['overtime_holiday'],
					'volume_pay'             => (float) $calc['volume_pay'],
					'volume_qty'             => (float) $calc['volume_qty'],
					'other_deductions'       => (float) $calc['other_deductions'],
					'loan_deduction'         => (float) $calc['loan_deduction'],
					'advance_deduction'      => (float) $calc['advance_deduction'],
					'items_json'             => wp_json_encode( $calc['items'] ),
					'meta_json'              => wp_json_encode( $calc['meta'] ),
					'components_json'        => wp_json_encode( $calc['items'] ),
					'status'                 => 'calculated',
					'created_at'             => $now,
					'updated_at'             => $now,
				)
			);

			$totals['gross']        += (float) $calc['gross'];
			$totals['deductions']   += $deductions;
			$totals['net']          += (float) $calc['net'];
			$totals['insurable']    += (float) $calc['insurable_capped'];
			$totals['emp_ins']      += (float) $calc['employee_insurance'];
			$totals['er_ins']       += (float) $calc['employer_insurance'];
			$totals['unemployment'] += (float) $calc['unemployment_insurance'];
			$totals['tax']          += (float) $calc['tax'];
			++$generated;
		}

		$wpdb->update(
			$runs,
			array(
				'status'             => 'calculated',
				'jalali_year'        => $jy,
				'jalali_month'       => $jm,
				'days_in_month'      => $days,
				'total_gross'        => $totals['gross'],
				'total_deductions'   => $totals['deductions'],
				'total_net'          => $totals['net'],
				'total_insurable'    => $totals['insurable'],
				'total_emp_ins'      => $totals['emp_ins'],
				'total_er_ins'       => $totals['er_ins'],
				'total_unemployment' => $totals['unemployment'],
				'total_tax'          => $totals['tax'],
				'list_status'        => 'ready',
				'calculated_at'      => $now,
				'updated_at'         => $now,
			),
			array( 'id' => $run_id )
		);

		return array(
			'run_id'           => $run_id,
			'payslips_count'   => $generated,
			'total_gross'      => $totals['gross'],
			'total_deductions' => $totals['deductions'],
			'total_net'        => $totals['net'],
		);
	}

	/**
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month.
	 * @return int
	 */
	public static function jalali_days_in_month( $jy, $jm ) {
		$jm = max( 1, min( 12, (int) $jm ) );
		if ( $jm <= 6 ) {
			return 31;
		}
		if ( $jm <= 11 ) {
			return 30;
		}
		list( $ry, $rm, $rd ) = self::gregorian_to_jalali_public( (int) $jy, 12, 30 );
		list( $jy2, $jm2, $jd2 ) = self::gregorian_to_jalali_public( $ry, $rm, $rd );
		if ( (int) $jy2 === (int) $jy && 12 === (int) $jm2 && 30 === (int) $jd2 ) {
			return 30;
		}
		return 29;
	}

	/**
	 * @param int $gy Gregorian year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return array{0:int,1:int,2:int}
	 */
	public static function gregorian_to_jalali_public( $gy, $gm, $gd ) {
		if ( function_exists( 'gregorian_to_jalali' ) ) {
			return gregorian_to_jalali( (int) $gy, (int) $gm, (int) $gd );
		}
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy    = (int) $gy;
		$gm    = (int) $gm;
		$gd    = (int) $gd;
		if ( $gy > 1600 ) {
			$jy = 979;
			$gy -= 1600;
		} else {
			$jy = 0;
			$gy -= 621;
		}
		$gy2  = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days = ( 365 * $gy ) + ( (int) ( ( $gy2 + 3 ) / 4 ) ) - ( (int) ( ( $gy2 + 99 ) / 100 ) ) + ( (int) ( ( $gy2 + 399 ) / 400 ) ) - 80 + $gd + $g_d_m[ $gm - 1 ];
		$jy  += 33 * ( (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * ( (int) ( $days / 1461 ) );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	/**
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month.
	 * @return string YYYY-MM.
	 */
	public static function jalali_to_year_month( $jy, $jm ) {
		if ( function_exists( 'jalali_to_gregorian' ) ) {
			list( $gy, $gm ) = jalali_to_gregorian( (int) $jy, (int) $jm, 15 );
			return sprintf( '%04d-%02d', $gy, $gm );
		}
		return sprintf( '%04d-%02d', (int) $jy, (int) $jm );
	}

	/**
	 * @param int $days Days in month.
	 * @return float
	 */
	public static function insurance_ceiling( $days ) {
		$cfg   = WebinoCRM_Hrm_Payroll_Config::get();
		$daily = (float) ( $cfg['payroll_min_daily_wage'] ?? 0 );
		$mult  = (float) ( $cfg['payroll_ceiling_multiplier'] ?? 7 );
		return round( $daily * $mult * max( 1, (int) $days ), 2 );
	}

	/**
	 * @param int $user_id User id.
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @return array<string,mixed>|null
	 */
	public static function get_active_decree( $user_id, $jy, $jm ) {
		$as_of = self::jalali_to_year_month( $jy, $jm ) . '-15';
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'employment_decrees' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND status = 'issued'
				AND effective_from <= %s AND (effective_to IS NULL OR effective_to = '' OR effective_to >= %s)
				ORDER BY effective_from DESC, id DESC LIMIT 1",
				(int) $user_id,
				$as_of,
				$as_of
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $user_id User id.
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @return array<string,mixed>|null
	 */
	public static function get_attendance( $user_id, $jy, $jm ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'payroll_attendance' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND jalali_year = %d AND jalali_month = %d",
				(int) $user_id,
				(int) $jy,
				(int) $jm
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string,mixed> $emp Employee snapshot.
	 * @param int                 $jy Year.
	 * @param int                 $jm Month.
	 * @param int                 $days Days.
	 * @return array<string,mixed>
	 */
	public static function calculate_slip( array $emp, $jy, $jm, $days ) {
		$cfg    = WebinoCRM_Hrm_Payroll_Config::get();
		$decree = self::get_active_decree( (int) $emp['id'], $jy, $jm );
		$emp    = self::apply_decree_to_emp( $emp, $decree );

		$att     = self::get_attendance( (int) $emp['id'], $jy, $jm );
		$absent  = (float) ( $att['absent_days'] ?? 0 );
		$unpaid  = (float) ( $att['unpaid_leave_days'] ?? 0 );
		$sick    = (float) ( $att['sick_leave_days'] ?? 0 );
		$ot_h    = (float) ( $att['overtime_hours'] ?? 0 );
		$night_h = (float) ( $att['night_hours'] ?? 0 );
		$hol_h   = (float) ( $att['holiday_hours'] ?? 0 );
		$vol_qty = (float) ( $att['volume_qty'] ?? 0 );
		$piece   = (float) ( $att['piece_rate'] ?? 0 );
		$loan    = (float) ( $att['loan_deduction'] ?? 0 );
		$advance = (float) ( $att['advance_deduction'] ?? 0 );

		$sick_worked = ! empty( $cfg['payroll_sick_counts_worked'] );
		$worked      = max( 0, (float) $days - $absent - $unpaid - ( $sick_worked ? 0 : $sick ) );

		$daily = (float) ( $emp['daily_wage'] ?? 0 );
		if ( $daily <= 0 && (float) ( $emp['base_salary'] ?? 0 ) > 0 ) {
			$daily = (float) $emp['base_salary'] / max( 1, (int) $days );
		}
		if ( $daily <= 0 ) {
			$daily = (float) ( $cfg['payroll_min_daily_wage'] ?? 0 );
		}

		$base_pay   = round( $daily * $worked, 2 );
		$ot_rate    = (float) ( $cfg['payroll_overtime_rate'] ?? 1.4 );
		$night_r    = (float) ( $cfg['payroll_night_ot_rate'] ?? 1.35 );
		$hol_r      = (float) ( $cfg['payroll_holiday_ot_rate'] ?? 1.4 );
		$hourly     = $daily / 7.33;
		$ot_pay     = round( $hourly * $ot_h * $ot_rate, 2 );
		$night_pay  = round( $hourly * $night_h * $night_r, 2 );
		$hol_pay    = round( $hourly * $hol_h * $hol_r, 2 );
		$volume_pay = round( $vol_qty * $piece, 2 );

		$food      = (float) ( $emp['benefit_food'] ?? 0 );
		$housing   = (float) ( $emp['benefit_housing'] ?? 0 );
		$child_each = (float) ( $cfg['payroll_child_benefit_each'] ?? 0 );
		$child     = (float) ( $emp['benefit_child'] ?? 0 );
		if ( $child <= 0 && $child_each > 0 ) {
			$child = $child_each * absint( $emp['children_count'] ?? 0 );
		}
		$marriage  = (float) ( $emp['benefit_marriage'] ?? 0 );
		$seniority = (float) ( $emp['benefit_seniority'] ?? 0 );
		$transport = (float) ( $emp['benefit_transport'] ?? 0 );
		$other_i   = (float) ( $emp['benefit_other_insurable'] ?? 0 );
		$other_n   = (float) ( $emp['benefit_other_non_insurable'] ?? 0 );

		$vol_insurable = ! empty( $cfg['payroll_volume_insurable'] );
		$insurable     = $base_pay + $ot_pay + $night_pay + $hol_pay + $food + $housing + $marriage + $seniority + $transport + $other_i;
		if ( $vol_insurable ) {
			$insurable += $volume_pay;
		}
		$gross = $insurable + $child + $other_n;
		if ( ! $vol_insurable ) {
			$gross += $volume_pay;
		}
		$ceiling = self::insurance_ceiling( $days );
		$capped  = min( $insurable, $ceiling );

		$emp_pct = (float) ( $cfg['employee_insurance_pct'] ?? 7 );
		$er_pct  = (float) ( $cfg['employer_insurance_pct'] ?? 20 );
		$ue_pct  = (float) ( $cfg['unemployment_insurance_pct'] ?? 3 );
		$hard    = (float) ( $emp['hardship_rate'] ?? 0 );
		$wh_id   = ! empty( $emp['workshop_id'] ) ? (int) $emp['workshop_id'] : 0;
		if ( $wh_id ) {
			global $wpdb;
			$wh = $wpdb->get_row( $wpdb->prepare( 'SELECT hardship_rate FROM ' . WebinoCRM_Hrm_Service::table( 'workshops' ) . ' WHERE id = %d', $wh_id ), ARRAY_A );
			if ( $wh ) {
				$hard += (float) ( $wh['hardship_rate'] ?? 0 );
			}
		}
		$er_pct += $hard;

		$emp_ins = round( $capped * $emp_pct / 100, 2 );
		$er_ins  = round( $capped * $er_pct / 100, 2 );
		$ue_ins  = round( $capped * $ue_pct / 100, 2 );

		$taxable = max( 0, $gross - $emp_ins - (float) ( $cfg['payroll_tax_exemption'] ?? 0 ) );
		$tax     = self::calc_monthly_tax_cumulative( (int) $emp['id'], $jy, $jm, $taxable );

		$other_ded = $loan + $advance;
		$net       = max( 0, $gross - $emp_ins - $tax - $other_ded );

		return array(
			'decree_id'              => (int) ( $emp['_decree_id'] ?? 0 ),
			'days_worked'            => $worked,
			'gross'                  => $gross,
			'insurable_wage'         => $insurable,
			'insurable_capped'       => $capped,
			'employee_insurance'     => $emp_ins,
			'employer_insurance'     => $er_ins,
			'unemployment_insurance' => $ue_ins,
			'taxable_income'         => $taxable,
			'tax'                    => $tax,
			'overtime'               => $ot_pay,
			'overtime_night'         => $night_pay,
			'overtime_holiday'       => $hol_pay,
			'volume_pay'             => $volume_pay,
			'volume_qty'             => $vol_qty,
			'other_deductions'       => $other_ded,
			'loan_deduction'         => $loan,
			'advance_deduction'      => $advance,
			'net'                    => $net,
			'items'                  => array(
				'base_pay'         => $base_pay,
				'overtime'         => $ot_pay,
				'overtime_night'   => $night_pay,
				'overtime_holiday' => $hol_pay,
				'volume'           => $volume_pay,
				'food'             => $food,
				'housing'          => $housing,
				'child'            => $child,
				'marriage'         => $marriage,
				'seniority'        => $seniority,
				'transport'        => $transport,
				'other_ins'        => $other_i,
				'other_non'        => $other_n,
				'loan'             => $loan,
				'advance'          => $advance,
			),
			'meta'                   => array(
				'daily_wage'    => $daily,
				'days_in_month' => $days,
				'absent_days'   => $absent,
				'sick_leave'    => $sick,
				'ceiling'       => $ceiling,
				'decree_id'     => (int) ( $emp['_decree_id'] ?? 0 ),
				'job_code'      => (string) ( $emp['job_code'] ?? '' ),
			),
		);
	}

	/**
	 * @param int   $user_id User.
	 * @param int   $jy Year.
	 * @param int   $jm Month.
	 * @param float $this_month_taxable Taxable.
	 * @return float
	 */
	public static function calc_monthly_tax_cumulative( $user_id, $jy, $jm, $this_month_taxable ) {
		global $wpdb;
		$rt = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$pt = WebinoCRM_Hrm_Service::table( 'payslips' );
		$prior = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.taxable_income),0) FROM $pt p
				INNER JOIN $rt r ON r.id = p.run_id
				WHERE p.user_id = %d AND r.jalali_year = %d AND r.jalali_month < %d AND r.status IN ('calculated','approved')",
				(int) $user_id,
				(int) $jy,
				(int) $jm
			)
		);
		$ytd_after  = $prior + max( 0, (float) $this_month_taxable );
		$tax_after  = self::calc_tax( $ytd_after );
		$tax_before = self::calc_tax( $prior );
		return round( max( 0, $tax_after - $tax_before ), 2 );
	}

	/**
	 * @param float $annual Annual taxable.
	 * @return float
	 */
	public static function calc_tax( $annual ) {
		$brackets  = WebinoCRM_Hrm_Payroll_Config::get()['payroll_tax_brackets'];
		$tax       = 0.0;
		$prev      = 0.0;
		$remaining = max( 0, (float) $annual );
		foreach ( (array) $brackets as $b ) {
			$up   = (float) ( $b['up_to'] ?? 0 );
			$rate = (float) ( $b['rate'] ?? 0 );
			if ( $up <= 0 ) {
				$tax += $remaining * $rate / 100;
				break;
			}
			$slice = min( $remaining, max( 0, $up - $prev ) );
			$tax  += $slice * $rate / 100;
			$remaining -= $slice;
			$prev = $up;
			if ( $remaining <= 0 ) {
				break;
			}
		}
		return $tax;
	}

	/**
	 * @param int $user_id User id.
	 * @return array<string,mixed>
	 */
	public static function employee_snapshot( $user_id ) {
		$user = get_userdata( $user_id );
		$cfg  = WebinoCRM_Hrm_Payroll_Config::get();
		$emp  = array(
			'id'                          => (int) $user_id,
			'name'                        => $user ? $user->display_name : '',
			'first_name'                  => $user ? (string) $user->first_name : '',
			'last_name'                   => $user ? (string) $user->last_name : '',
			'national_id'                 => (string) get_user_meta( $user_id, 'webino_national_id', true ),
			'insurance_no'                => (string) get_user_meta( $user_id, 'webino_insurance_number', true ),
			'father_name'                 => (string) get_user_meta( $user_id, 'webino_father_name', true ),
			'birth_date'                  => (string) get_user_meta( $user_id, 'webino_birth_date', true ),
			'hire_date'                   => (string) get_user_meta( $user_id, 'webino_hire_date', true ),
			'insurance_start'             => (string) get_user_meta( $user_id, 'webino_insurance_start', true ),
			'insurance_end'               => (string) get_user_meta( $user_id, 'webino_insurance_end', true ),
			'id_issue_place'              => (string) get_user_meta( $user_id, 'webino_id_issue_place', true ),
			'marital_status'              => (string) get_user_meta( $user_id, 'webino_marital_status', true ),
			'children_count'              => (int) get_user_meta( $user_id, 'webino_children_count', true ),
			'gender'                      => (string) get_user_meta( $user_id, 'webino_gender', true ),
			'job_title'                   => (string) get_user_meta( $user_id, 'webino_job_title', true ),
			'job_code'                    => (string) get_user_meta( $user_id, 'webino_job_code', true ),
			'workshop_id'                 => (int) get_user_meta( $user_id, 'webino_workshop_id', true ),
			'daily_wage'                  => (float) get_user_meta( $user_id, 'webino_daily_wage', true ),
			'base_salary'                 => (float) get_user_meta( $user_id, 'webino_base_salary', true ),
			'benefit_food'                => (float) ( get_user_meta( $user_id, 'webino_benefit_food', true ) ?: $cfg['payroll_legal_food'] ),
			'benefit_housing'             => (float) ( get_user_meta( $user_id, 'webino_benefit_housing', true ) ?: $cfg['payroll_legal_housing'] ),
			'benefit_marriage'            => (float) ( get_user_meta( $user_id, 'webino_benefit_marriage', true ) ?: $cfg['payroll_legal_marriage'] ),
			'benefit_seniority'           => (float) ( get_user_meta( $user_id, 'webino_benefit_seniority', true ) ?: $cfg['payroll_legal_seniority'] ),
			'benefit_child'               => 0.0,
			'benefit_transport'           => (float) get_user_meta( $user_id, 'webino_benefit_transport', true ),
			'benefit_other_insurable'     => (float) get_user_meta( $user_id, 'webino_benefit_other_insurable', true ),
			'benefit_other_non_insurable' => (float) get_user_meta( $user_id, 'webino_benefit_other_non_insurable', true ),
			'hardship_rate'               => (float) get_user_meta( $user_id, 'webino_hardship_rate', true ),
			'status'                      => 'active',
		);
		return $emp;
	}

	/**
	 * @param array<string,mixed>      $emp Employee.
	 * @param array<string,mixed>|null $decree Decree.
	 * @return array<string,mixed>
	 */
	private static function apply_decree_to_emp( array $emp, $decree ) {
		if ( ! is_array( $decree ) ) {
			return $emp;
		}
		$map = array(
			'daily_wage', 'base_salary', 'benefit_food', 'benefit_housing', 'benefit_child',
			'benefit_marriage', 'benefit_seniority', 'benefit_transport',
			'benefit_other_insurable', 'benefit_other_non_insurable', 'hardship_rate',
			'job_title', 'job_code', 'workshop_id', 'contract_type',
		);
		foreach ( $map as $k ) {
			if ( isset( $decree[ $k ] ) && ( '' !== $decree[ $k ] || is_numeric( $decree[ $k ] ) ) ) {
				$emp[ $k ] = $decree[ $k ];
			}
		}
		if ( ! empty( $decree['benefit_responsibility'] ) ) {
			$emp['benefit_other_insurable'] = (float) ( $emp['benefit_other_insurable'] ?? 0 ) + (float) $decree['benefit_responsibility'];
		}
		$emp['_decree_id'] = (int) $decree['id'];
		return $emp;
	}

	/**
	 * @param int $workshop_id Optional filter.
	 * @return array<int,int>
	 */
	private static function staff_user_ids( $workshop_id = 0 ) {
		$query = new WP_User_Query(
			array(
				'role__in' => WebinoCRM_Hrm_Service::STAFF_ROLES,
				'fields'   => 'ID',
				'number'   => 5000,
			)
		);
		$ids = array_map( 'intval', (array) $query->get_results() );
		if ( $workshop_id <= 0 ) {
			return $ids;
		}
		return array_values(
			array_filter(
				$ids,
				static function ( $uid ) use ( $workshop_id ) {
					$wh = (int) get_user_meta( $uid, 'webino_workshop_id', true );
					return ! $wh || $wh === $workshop_id;
				}
			)
		);
	}
}
