<?php
/**
 * Accounting Fiscal Year model – Iranian standard.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Fiscal_Year
 */
class WebinoCRM_Accounting_Fiscal_Year {

	const TABLE = 'webinocrm_accounting_fiscal_years';

	/**
	 * Get table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Get active fiscal year.
	 *
	 * @return object|null
	 */
	public static function get_active() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1"
			)
		);
	}

	/**
	 * Get fiscal year by id.
	 *
	 * @param int $id Fiscal year id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get all fiscal years.
	 *
	 * @param string $order Order: ASC or DESC.
	 * @return array
	 */
	public static function get_all( $order = 'DESC' ) {
		global $wpdb;
		$table = self::get_table();
		$order = $order === 'ASC' ? 'ASC' : 'DESC';
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY start_date $order" );
	}

	/**
	 * Create a fiscal year.
	 *
	 * @param array $data name, start_date, end_date, is_active (optional).
	 * @return int|false Insert id or false.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$name       = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$start_date = isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : '';
		$end_date   = isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : '';
		$is_active  = isset( $data['is_active'] ) ? (int) $data['is_active'] : 0;

		if ( empty( $name ) || empty( $start_date ) || empty( $end_date ) ) {
			return false;
		}

		if ( $is_active ) {
			$wpdb->query( "UPDATE $table SET is_active = 0" );
		}

		$r = $wpdb->insert(
			$table,
			array(
				'name'       => $name,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'is_active'  => $is_active,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a fiscal year.
	 *
	 * @param int   $id   Fiscal year id.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::get_table();
		$id    = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		$updates = array();
		$formats = array();
		if ( array_key_exists( 'name', $data ) ) {
			$updates['name'] = sanitize_text_field( $data['name'] );
			$formats[]       = '%s';
		}
		if ( array_key_exists( 'start_date', $data ) ) {
			$updates['start_date'] = sanitize_text_field( $data['start_date'] );
			$formats[]            = '%s';
		}
		if ( array_key_exists( 'end_date', $data ) ) {
			$updates['end_date'] = sanitize_text_field( $data['end_date'] );
			$formats[]           = '%s';
		}
		if ( array_key_exists( 'is_active', $data ) ) {
			$updates['is_active'] = (int) $data['is_active'];
			$formats[]            = '%d';
			if ( $updates['is_active'] ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $table SET is_active = 0 WHERE id != %d", $id ) );
			}
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete a fiscal year (only if no journal entries).
	 *
	 * @param int $id Fiscal year id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$entries_table = $wpdb->prefix . 'webinocrm_accounting_journal_entries';
		$count         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $entries_table WHERE fiscal_year_id = %d", $id ) );
		if ( $count > 0 ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
