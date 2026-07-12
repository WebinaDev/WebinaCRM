<?php
/**
 * Accounting User Defaults (پیش‌فرض فاکتور: شخص و لیست قیمت).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_User_Defaults
 */
class WebinoCRM_Accounting_User_Defaults {

	const TABLE = 'webinocrm_accounting_user_defaults';

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
	 * Get defaults for current user (or specified user).
	 *
	 * @param int|null $user_id User id or null for current.
	 * @return object { default_invoice_person_id, default_price_list_id }
	 */
	public static function get( $user_id = null ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return (object) array( 'default_invoice_person_id' => null, 'default_price_list_id' => null );
		}
		$table = self::get_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d", $user_id ) );
		if ( ! $row ) {
			return (object) array( 'default_invoice_person_id' => null, 'default_price_list_id' => null );
		}
		return $row;
	}

	/**
	 * Save defaults for current user.
	 *
	 * @param array $data default_invoice_person_id, default_price_list_id.
	 * @param int|null $user_id User id or null for current.
	 * @return bool
	 */
	public static function save( $data, $user_id = null ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		$table = self::get_table();

		$person_id_raw = isset( $data['default_invoice_person_id'] ) ? (int) $data['default_invoice_person_id'] : null;
		$list_id_raw   = isset( $data['default_price_list_id'] ) ? (int) $data['default_price_list_id'] : null;
		$person_id     = $person_id_raw > 0 ? $person_id_raw : null;
		$list_id       = $list_id_raw > 0 ? $list_id_raw : null;

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d", $user_id ) );
		if ( $existing ) {
			return (bool) $wpdb->update(
				$table,
				array(
					'default_invoice_person_id' => $person_id,
					'default_price_list_id'   => $list_id,
				),
				array( 'user_id' => $user_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		}
		return (bool) $wpdb->insert(
			$table,
			array(
				'user_id'                    => $user_id,
				'default_invoice_person_id'  => $person_id,
				'default_price_list_id'      => $list_id,
			),
			array( '%d', '%d', '%d' )
		);
	}
}
