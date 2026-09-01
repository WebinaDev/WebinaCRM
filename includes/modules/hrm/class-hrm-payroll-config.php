<?php
/**
 * Iranian payroll configuration (rates, legal benefits, tax brackets).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Payroll_Config {

	const OPTION_KEY = 'webinocrm_hrm_payroll_config';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'company_name'               => '',
			'economic_code'              => '',
			'national_id'                => '',
			'address'                    => '',
			'phone'                      => '',
			'employee_insurance_pct'     => 7,
			'employer_insurance_pct'     => 20,
			'unemployment_insurance_pct' => 3,
			'payroll_min_daily_wage'     => 3463656,
			'payroll_ceiling_multiplier' => 7,
			'payroll_tax_exemption'      => 0,
			'payroll_legal_food'         => 22000000,
			'payroll_legal_housing'      => 9000000,
			'payroll_legal_marriage'     => 5000000,
			'payroll_legal_seniority'    => 2820000,
			'payroll_overtime_rate'      => 1.4,
			'payroll_night_ot_rate'      => 1.35,
			'payroll_holiday_ot_rate'    => 1.4,
			'payroll_child_benefit_each' => 7167750,
			'payroll_volume_insurable'   => true,
			'payroll_sick_counts_worked' => true,
			'default_workshop_id'        => 0,
			'payroll_tax_brackets'       => array(
				array( 'up_to' => 120000000, 'rate' => 0 ),
				array( 'up_to' => 165000000, 'rate' => 10 ),
				array( 'up_to' => 270000000, 'rate' => 15 ),
				array( 'up_to' => 400000000, 'rate' => 20 ),
				array( 'up_to' => 0, 'rate' => 30 ),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>
	 */
	public static function save( array $data ) {
		$new = self::get();
		$text_keys = array( 'company_name', 'economic_code', 'national_id', 'address', 'phone' );
		foreach ( $text_keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$new[ $key ] = sanitize_text_field( (string) $data[ $key ] );
			}
		}
		$num_keys = array(
			'employee_insurance_pct', 'employer_insurance_pct', 'unemployment_insurance_pct',
			'payroll_min_daily_wage', 'payroll_ceiling_multiplier', 'payroll_tax_exemption',
			'payroll_legal_food', 'payroll_legal_housing', 'payroll_legal_marriage',
			'payroll_legal_seniority', 'payroll_overtime_rate', 'payroll_night_ot_rate',
			'payroll_holiday_ot_rate', 'payroll_child_benefit_each', 'default_workshop_id',
		);
		foreach ( $num_keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$new[ $key ] = is_numeric( $data[ $key ] ) ? (float) $data[ $key ] : $new[ $key ];
			}
		}
		if ( array_key_exists( 'payroll_volume_insurable', $data ) ) {
			$new['payroll_volume_insurable'] = ! empty( $data['payroll_volume_insurable'] );
		}
		if ( array_key_exists( 'payroll_sick_counts_worked', $data ) ) {
			$new['payroll_sick_counts_worked'] = ! empty( $data['payroll_sick_counts_worked'] );
		}
		if ( isset( $data['payroll_tax_brackets'] ) && is_array( $data['payroll_tax_brackets'] ) ) {
			$new['payroll_tax_brackets'] = $data['payroll_tax_brackets'];
		}
		update_option( self::OPTION_KEY, $new, false );
		return $new;
	}
}
