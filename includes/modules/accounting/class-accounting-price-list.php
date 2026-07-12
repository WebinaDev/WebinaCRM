<?php
/**
 * Accounting Price List model (لیست قیمت).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Price_List
 */
class WebinoCRM_Accounting_Price_List {

	const TABLE = 'webinocrm_accounting_price_lists';
	const TABLE_ITEMS = 'webinocrm_accounting_price_list_items';

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
	 * Get items table name.
	 *
	 * @return string
	 */
	public static function get_items_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_ITEMS;
	}

	/**
	 * Get price list by id.
	 *
	 * @param int $id Price list id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get default price list.
	 *
	 * @return object|null
	 */
	public static function get_default() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( "SELECT * FROM $table WHERE is_default = 1 LIMIT 1" );
	}

	/**
	 * Get all price lists.
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY is_default DESC, name ASC" );
	}

	/**
	 * Get items of a price list (product_id => price).
	 *
	 * @param int $price_list_id Price list id.
	 * @return array Associative array product_id => object (id, product_id, price, min_quantity).
	 */
	public static function get_items( $price_list_id ) {
		global $wpdb;
		$table = self::get_items_table();
		$price_list_id = (int) $price_list_id;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE price_list_id = %d ORDER BY product_id",
				$price_list_id
			)
		);
		$out = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->product_id ] = $row;
		}
		return $out;
	}

	/**
	 * Set or update one item in a price list.
	 *
	 * @param int   $price_list_id Price list id.
	 * @param int   $product_id    Product id.
	 * @param float $price        Price.
	 * @param float $min_quantity  Optional min quantity.
	 * @return bool
	 */
	public static function set_item( $price_list_id, $product_id, $price, $min_quantity = 1 ) {
		global $wpdb;
		$items_table = self::get_items_table();
		$price_list_id = (int) $price_list_id;
		$product_id    = (int) $product_id;
		$price         = floatval( $price );
		$min_quantity  = floatval( $min_quantity );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $items_table WHERE price_list_id = %d AND product_id = %d",
				$price_list_id,
				$product_id
			)
		);

		if ( $existing ) {
			return (bool) $wpdb->update(
				$items_table,
				array( 'price' => $price, 'min_quantity' => $min_quantity ),
				array( 'id' => $existing->id ),
				array( '%f', '%f' ),
				array( '%d' )
			);
		}

		return (bool) $wpdb->insert(
			$items_table,
			array(
				'price_list_id' => $price_list_id,
				'product_id'    => $product_id,
				'price'         => $price,
				'min_quantity'   => $min_quantity,
			),
			array( '%d', '%d', '%f', '%f' )
		);
	}

	/**
	 * Create price list.
	 *
	 * @param array $data name, description, is_default, valid_from, valid_to.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( empty( $name ) ) {
			return false;
		}

		$is_default = isset( $data['is_default'] ) ? (int) $data['is_default'] : 0;
		if ( $is_default ) {
			$wpdb->query( "UPDATE $table SET is_default = 0" );
		}

		$r = $wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'is_default'  => $is_default,
				'valid_from'  => isset( $data['valid_from'] ) ? sanitize_text_field( $data['valid_from'] ) : null,
				'valid_to'    => isset( $data['valid_to'] ) ? sanitize_text_field( $data['valid_to'] ) : null,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update price list.
	 *
	 * @param int   $id   Price list id.
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

		if ( ! empty( $data['is_default'] ) ) {
			$wpdb->query( "UPDATE $table SET is_default = 0" );
		}

		$updates = array();
		$formats = array();
		if ( array_key_exists( 'name', $data ) ) {
			$updates['name'] = sanitize_text_field( $data['name'] );
			$formats[]       = '%s';
		}
		if ( array_key_exists( 'description', $data ) ) {
			$updates['description'] = sanitize_textarea_field( $data['description'] );
			$formats[]               = '%s';
		}
		if ( array_key_exists( 'is_default', $data ) ) {
			$updates['is_default'] = (int) $data['is_default'];
			$formats[]              = '%d';
		}
		if ( array_key_exists( 'valid_from', $data ) ) {
			$updates['valid_from'] = $data['valid_from'] ? sanitize_text_field( $data['valid_from'] ) : null;
			$formats[]             = '%s';
		}
		if ( array_key_exists( 'valid_to', $data ) ) {
			$updates['valid_to'] = $data['valid_to'] ? sanitize_text_field( $data['valid_to'] ) : null;
			$formats[]           = '%s';
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete price list and its items.
	 *
	 * @param int $id Price list id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$items_table = self::get_items_table();
		$wpdb->delete( $items_table, array( 'price_list_id' => $id ), array( '%d' ) );
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
