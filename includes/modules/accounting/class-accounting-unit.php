<?php
/**
 * Accounting Unit model (واحد اصلی و فرعی).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Unit
 */
class WebinoCRM_Accounting_Unit {

	const TABLE = 'webinocrm_accounting_units';

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
	 * Get unit by id.
	 *
	 * @param int $id Unit id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get all units (main first, then by name).
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY is_main DESC, name ASC" );
	}

	/**
	 * Get main units only.
	 *
	 * @return array
	 */
	public static function get_main_units() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results( "SELECT * FROM $table WHERE is_main = 1 ORDER BY name ASC" );
	}

	/**
	 * Get sub-units for a base unit.
	 *
	 * @param int $base_unit_id Base (main) unit id.
	 * @return array
	 */
	public static function get_sub_units( $base_unit_id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE base_unit_id = %d ORDER BY name ASC",
				(int) $base_unit_id
			)
		);
	}

	/**
	 * Create unit.
	 *
	 * @param array $data name, symbol, is_main, base_unit_id, ratio_to_base.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$name   = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$symbol = isset( $data['symbol'] ) ? sanitize_text_field( $data['symbol'] ) : null;
		$is_main = isset( $data['is_main'] ) ? (int) $data['is_main'] : 1;
		$base_unit_id = isset( $data['base_unit_id'] ) ? (int) $data['base_unit_id'] : null;
		$ratio  = isset( $data['ratio_to_base'] ) ? floatval( $data['ratio_to_base'] ) : 1;

		if ( empty( $name ) ) {
			return false;
		}

		$r = $wpdb->insert(
			$table,
			array(
				'name'          => $name,
				'symbol'        => $symbol,
				'is_main'       => $is_main,
				'base_unit_id'  => $base_unit_id,
				'ratio_to_base' => $ratio,
			),
			array( '%s', '%s', '%d', '%d', '%f' )
		);

		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update unit.
	 *
	 * @param int   $id   Unit id.
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
		if ( array_key_exists( 'symbol', $data ) ) {
			$updates['symbol'] = sanitize_text_field( $data['symbol'] );
			$formats[]         = '%s';
		}
		if ( array_key_exists( 'is_main', $data ) ) {
			$updates['is_main'] = (int) $data['is_main'];
			$formats[]          = '%d';
		}
		if ( array_key_exists( 'base_unit_id', $data ) ) {
			$updates['base_unit_id'] = (int) $data['base_unit_id'];
			$formats[]               = '%d';
		}
		if ( array_key_exists( 'ratio_to_base', $data ) ) {
			$updates['ratio_to_base'] = floatval( $data['ratio_to_base'] );
			$formats[]               = '%f';
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete unit (only if not used by products).
	 *
	 * @param int $id Unit id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$products_table = $wpdb->prefix . 'webinocrm_accounting_products';
		$count_main = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $products_table WHERE main_unit_id = %d", $id ) );
		$count_sub  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $products_table WHERE sub_unit_id = %d", $id ) );
		if ( $count_main > 0 || $count_sub > 0 ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
