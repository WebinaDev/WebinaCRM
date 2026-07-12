<?php
/**
 * Seeds the chart of accounts from default Iranian chart data.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Chart_Seeder
 */
class WebinoCRM_Accounting_Chart_Seeder {

	/**
	 * Seed chart for a fiscal year. Skips codes that already exist.
	 *
	 * @param int $fiscal_year_id Fiscal year id.
	 * @return int Number of accounts inserted.
	 */
	public function seed( $fiscal_year_id ) {
		$fiscal_year_id = (int) $fiscal_year_id;
		if ( $fiscal_year_id <= 0 ) {
			return 0;
		}

		if ( ! function_exists( 'webinocrm_accounting_get_default_chart_iran' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/data-default-chart-iran.php';
		}
		$items = webinocrm_accounting_get_default_chart_iran();
		$code_to_id = array( '' => 0 );
		$inserted = 0;

		foreach ( $items as $item ) {
			$code = $item['code'];
			if ( WebinoCRM_Accounting_Chart_Of_Accounts::code_exists( $code, $fiscal_year_id ) ) {
				$existing = WebinoCRM_Accounting_Chart_Of_Accounts::get_by_code( $code, $fiscal_year_id );
				$code_to_id[ $code ] = $existing ? (int) $existing->id : 0;
				continue;
			}
			$parent_id = isset( $code_to_id[ $item['parent_code'] ] ) ? $code_to_id[ $item['parent_code'] ] : 0;
			$data = array(
				'code'           => $code,
				'title'          => $item['title'],
				'level'          => (int) $item['level'],
				'parent_id'      => $parent_id,
				'account_type'   => $item['account_type'],
				'fiscal_year_id' => $fiscal_year_id,
				'is_system'      => 1,
				'sort_order'     => isset( $item['sort_order'] ) ? (int) $item['sort_order'] : 0,
			);
			$new_id = WebinoCRM_Accounting_Chart_Of_Accounts::create( $data );
			if ( $new_id ) {
				$code_to_id[ $code ] = $new_id;
				$inserted++;
			}
		}

		return $inserted;
	}
}
