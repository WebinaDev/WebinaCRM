<?php
/**
 * Payroll calculation engine for HRM.
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

		$components = self::load_components();
		$staff_ids  = self::staff_user_ids();
		$payslips   = WebinoCRM_Hrm_Service::table( 'payslips' );
		$now        = current_time( 'mysql' );

		$wpdb->delete( $payslips, array( 'run_id' => $run_id ), array( '%d' ) );

		$total_gross       = 0.0;
		$total_deductions  = 0.0;
		$total_net         = 0.0;
		$generated         = 0;

		foreach ( $staff_ids as $user_id ) {
			$lines = self::build_employee_lines( (int) $user_id, $components, $run );
			if ( empty( $lines ) ) {
				continue;
			}

			$gross      = 0.0;
			$deductions = 0.0;
			foreach ( $lines as $line ) {
				$amount = (float) $line['amount'];
				if ( 'deduction' === $line['type'] ) {
					$deductions += $amount;
				} else {
					$gross += $amount;
				}
			}
			$net = $gross - $deductions;

			$wpdb->insert(
				$payslips,
				array(
					'run_id'          => $run_id,
					'user_id'         => (int) $user_id,
					'gross'           => $gross,
					'deductions'      => $deductions,
					'net'             => $net,
					'components_json' => wp_json_encode( $lines ),
					'status'          => 'calculated',
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
			);

			$total_gross      += $gross;
			$total_deductions += $deductions;
			$total_net        += $net;
			++$generated;
		}

		$wpdb->update(
			$runs,
			array(
				'status'           => 'calculated',
				'total_gross'      => $total_gross,
				'total_deductions' => $total_deductions,
				'total_net'        => $total_net,
				'calculated_at'    => $now,
				'updated_at'       => $now,
			),
			array( 'id' => $run_id ),
			array( '%s', '%f', '%f', '%f', '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'run_id'           => $run_id,
			'payslips_count'   => $generated,
			'total_gross'      => $total_gross,
			'total_deductions' => $total_deductions,
			'total_net'        => $total_net,
		);
	}

	/**
	 * @return array<int,object>
	 */
	private static function load_components() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'salary_components' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, id ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int,int>
	 */
	private static function staff_user_ids() {
		$query = new WP_User_Query(
			array(
				'role__in' => WebinoCRM_Hrm_Service::STAFF_ROLES,
				'fields'   => 'ID',
				'number'   => 5000,
			)
		);
		return array_map( 'intval', (array) $query->get_results() );
	}

	/**
	 * @param int                   $user_id    User id.
	 * @param array<int,object>     $components Salary components.
	 * @param object                $run        Payroll run row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_employee_lines( $user_id, array $components, $run ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'employee_salaries' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND (effective_to IS NULL OR effective_to = '' OR effective_to >= %s)
				AND effective_from <= %s ORDER BY id ASC",
				$user_id,
				$run->period_end,
				$run->period_start
			)
		);
		if ( empty( $rows ) ) {
			return array();
		}

		$by_component = array();
		foreach ( $rows as $row ) {
			$by_component[ (int) $row->component_id ] = (float) $row->amount;
		}

		$lines = array();
		foreach ( $components as $component ) {
			$component_id = (int) $component->id;
			if ( ! isset( $by_component[ $component_id ] ) ) {
				continue;
			}
			$amount = (float) $by_component[ $component_id ];
			if ( $amount <= 0 ) {
				continue;
			}
			$lines[] = array(
				'component_id'   => $component_id,
				'component_code' => (string) ( $component->code ?? '' ),
				'component_name' => (string) $component->name,
				'type'           => (string) ( $component->type ?? 'earning' ),
				'amount'         => $amount,
			);
		}
		return $lines;
	}
}
