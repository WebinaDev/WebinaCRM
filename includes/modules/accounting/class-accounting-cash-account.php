<?php
/**
 * Accounting Cash Account (صندوق / بانک / تنخواه).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Cash_Account
 */
class WebinoCRM_Accounting_Cash_Account {

	const TABLE = 'webinocrm_accounting_cash_accounts';

	const TYPE_BANK  = 'bank';
	const TYPE_CASH  = 'cash';
	const TYPE_PETTY = 'petty';

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
	 * Get by id.
	 *
	 * @param int $id Cash account id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get all (optionally by type, active only).
	 *
	 * @param array $args type, is_active.
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::get_table();
		$where  = array( '1=1' );
		$values = array();
		if ( ! empty( $args['type'] ) && in_array( $args['type'], array( self::TYPE_BANK, self::TYPE_CASH, self::TYPE_PETTY ), true ) ) {
			$where[] = 'type = %s';
			$values[] = $args['type'];
		}
		if ( isset( $args['is_active'] ) && $args['is_active'] !== '' ) {
			$where[] = 'is_active = %d';
			$values[] = (int) $args['is_active'];
		}
		$where_sql = implode( ' AND ', $where );
		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY sort_order ASC, id ASC";
		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}
		return $wpdb->get_results( $sql );
	}

	/**
	 * Create cash account.
	 *
	 * @param array $data name, type, code, description, card_no, sheba, chart_account_id, is_active, sort_order.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( empty( $name ) ) {
			return false;
		}
		$type = isset( $data['type'] ) && in_array( $data['type'], array( self::TYPE_BANK, self::TYPE_CASH, self::TYPE_PETTY ), true ) ? $data['type'] : self::TYPE_BANK;
		$insert = array(
			'name'             => $name,
			'type'             => $type,
			'code'             => isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : null,
			'description'      => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
			'card_no'          => isset( $data['card_no'] ) ? sanitize_text_field( $data['card_no'] ) : null,
			'sheba'            => isset( $data['sheba'] ) ? sanitize_text_field( $data['sheba'] ) : null,
			'chart_account_id' => isset( $data['chart_account_id'] ) && (int) $data['chart_account_id'] > 0 ? (int) $data['chart_account_id'] : null,
			'is_active'        => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'sort_order'       => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' );
		$r = $wpdb->insert( $table, $insert, $formats );
		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update cash account.
	 *
	 * @param int   $id   Cash account id.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::get_table();
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$allowed = array( 'name', 'type', 'code', 'description', 'card_no', 'sheba', 'chart_account_id', 'is_active', 'sort_order' );
		$updates = array();
		$formats = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, array( 'chart_account_id', 'is_active', 'sort_order' ), true ) ) {
				$v = (int) $data[ $key ];
				$updates[ $key ] = $key === 'chart_account_id' && $v <= 0 ? null : $v;
				$formats[] = '%d';
			} elseif ( $key === 'type' ) {
				$updates[ $key ] = in_array( $data[ $key ], array( self::TYPE_BANK, self::TYPE_CASH, self::TYPE_PETTY ), true ) ? $data[ $key ] : self::TYPE_BANK;
				$formats[] = '%s';
			} elseif ( in_array( $key, array( 'description' ), true ) ) {
				$updates[ $key ] = sanitize_textarea_field( $data[ $key ] );
				$formats[] = '%s';
			} else {
				$updates[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[] = '%s';
			}
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete cash account (only if not used in receipts).
	 *
	 * @param int $id Cash account id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$receipts = $wpdb->prefix . 'webinocrm_accounting_receipt_vouchers';
		$used = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $receipts WHERE cash_account_id = %d OR transfer_to_cash_account_id = %d",
			$id,
			$id
		) );
		if ( (int) $used > 0 ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
