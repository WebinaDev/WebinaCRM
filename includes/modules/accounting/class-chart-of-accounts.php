<?php
/**
 * Chart of Accounts – Iranian standard hierarchical coding.
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Chart_Of_Accounts
 */
class WebinoCRM_Accounting_Chart_Of_Accounts {

	const TABLE = 'webinocrm_accounting_chart_accounts';

	const LEVEL_GROUP  = 1;
	const LEVEL_CLASS  = 2;
	const LEVEL_LEDGER = 3;
	const LEVEL_DETAIL = 4;

	const TYPE_ASSET    = 'asset';
	const TYPE_LIABILITY = 'liability';
	const TYPE_EQUITY   = 'equity';
	const TYPE_INCOME   = 'income';
	const TYPE_EXPENSE  = 'expense';

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
	 * Get account by id.
	 *
	 * @param int $id Account id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get account by code and fiscal year.
	 *
	 * @param string $code Account code.
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @return object|null
	 */
	public static function get_by_code( $code, $fiscal_year_id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE code = %s AND fiscal_year_id = %d",
			sanitize_text_field( $code ),
			(int) $fiscal_year_id
		) );
	}

	/**
	 * Get tree of accounts for a fiscal year (flat list with parent_id, sorted).
	 *
	 * @param int $fiscal_year_id Fiscal year id.
	 * @return array
	 */
	public static function get_tree( $fiscal_year_id ) {
		global $wpdb;
		$table = self::get_table();
		$list  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE fiscal_year_id = %d ORDER BY sort_order ASC, code ASC",
			(int) $fiscal_year_id
		), ARRAY_A );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Get children of an account.
	 *
	 * @param int $parent_id Parent account id (0 for root).
	 * @param int $fiscal_year_id Fiscal year id.
	 * @return array
	 */
	public static function get_children( $parent_id, $fiscal_year_id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE parent_id = %d AND fiscal_year_id = %d ORDER BY sort_order ASC, code ASC",
			(int) $parent_id,
			(int) $fiscal_year_id
		), ARRAY_A );
	}

	/**
	 * Check if code exists in fiscal year.
	 *
	 * @param string $code Account code.
	 * @param int    $fiscal_year_id Fiscal year id.
	 * @param int    $exclude_id Optional account id to exclude (for update).
	 * @return bool
	 */
	public static function code_exists( $code, $fiscal_year_id, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::get_table();
		$code  = sanitize_text_field( $code );
		$sql   = $wpdb->prepare( "SELECT 1 FROM $table WHERE code = %s AND fiscal_year_id = %d", $code, (int) $fiscal_year_id );
		if ( $exclude_id > 0 ) {
			$sql .= $wpdb->prepare( ' AND id != %d', (int) $exclude_id );
		}
		return (bool) $wpdb->get_var( $sql );
	}

	/**
	 * Create an account.
	 *
	 * @param array $data code, title, level, parent_id, account_type, fiscal_year_id, is_system, sort_order.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$code           = isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : '';
		$title          = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		$level          = isset( $data['level'] ) ? (int) $data['level'] : 1;
		$parent_id      = isset( $data['parent_id'] ) ? (int) $data['parent_id'] : 0;
		$account_type   = isset( $data['account_type'] ) ? sanitize_key( $data['account_type'] ) : self::TYPE_ASSET;
		$fiscal_year_id = isset( $data['fiscal_year_id'] ) ? (int) $data['fiscal_year_id'] : 0;
		$is_system      = isset( $data['is_system'] ) ? (int) $data['is_system'] : 0;
		$sort_order     = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;

		if ( empty( $code ) || empty( $title ) || $fiscal_year_id <= 0 ) {
			return false;
		}
		if ( self::code_exists( $code, $fiscal_year_id ) ) {
			return false;
		}

		$r = $wpdb->insert(
			$table,
			array(
				'code'           => $code,
				'title'          => $title,
				'level'          => $level,
				'parent_id'      => $parent_id,
				'account_type'   => $account_type,
				'fiscal_year_id' => $fiscal_year_id,
				'is_system'      => $is_system,
				'sort_order'     => $sort_order,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d' )
		);

		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an account.
	 *
	 * @param int   $id   Account id.
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

		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}

		if ( ! empty( $row->is_system ) && isset( $data['code'] ) ) {
			// System accounts: do not allow code change in this layer (optional).
		}

		if ( isset( $data['code'] ) && self::code_exists( $data['code'], (int) $row->fiscal_year_id, $id ) ) {
			return false;
		}

		$updates = array();
		$formats = array();
		$allowed = array( 'code', 'title', 'level', 'parent_id', 'account_type', 'sort_order' );
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( $key === 'code' ) {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[]       = '%s';
			} elseif ( $key === 'title' ) {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[]       = '%s';
			} elseif ( $key === 'account_type' ) {
				$updates[ $key ] = sanitize_key( $data[ $key ] );
				$formats[]       = '%s';
			} else {
				$updates[ $key ] = (int) $data[ $key ];
				$formats[]       = '%d';
			}
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete an account (only if no journal lines and no children).
	 *
	 * @param int $id Account id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::get( $id );
		if ( ! $row || ! empty( $row->is_system ) ) {
			return false;
		}
		$children = self::get_children( $id, (int) $row->fiscal_year_id );
		if ( ! empty( $children ) ) {
			return false;
		}
		$lines_table = $wpdb->prefix . 'webinocrm_accounting_journal_lines';
		$count       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $lines_table WHERE account_id = %d", $id ) );
		if ( $count > 0 ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
