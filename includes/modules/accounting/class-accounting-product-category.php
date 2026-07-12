<?php
/**
 * Accounting Product Category model (دسته‌بندی درختی کالا).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Product_Category
 */
class WebinoCRM_Accounting_Product_Category {

	const TABLE = 'webinocrm_accounting_product_categories';

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
	 * Get category by id.
	 *
	 * @param int $id Category id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get children of a parent.
	 *
	 * @param int $parent_id 0 for roots.
	 * @return array
	 */
	public static function get_children( $parent_id = 0 ) {
		global $wpdb;
		$table = self::get_table();
		$parent_id = (int) $parent_id;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE parent_id = %d ORDER BY sort_order ASC, name ASC",
				$parent_id
			)
		);
	}

	/**
	 * Get flat list of all categories.
	 *
	 * @return array
	 */
	public static function get_all_flat() {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY sort_order ASC, name ASC" );
	}

	/**
	 * Build tree recursively.
	 *
	 * @param int $parent_id Parent id.
	 * @return array
	 */
	public static function get_tree( $parent_id = 0 ) {
		$children = self::get_children( $parent_id );
		foreach ( $children as $c ) {
			$c->children = self::get_tree( (int) $c->id );
		}
		return $children;
	}

	/**
	 * Create category.
	 *
	 * @param array $data name, parent_id, sort_order.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table();

		$name      = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$parent_id = isset( $data['parent_id'] ) ? (int) $data['parent_id'] : 0;
		$sort      = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;

		if ( empty( $name ) ) {
			return false;
		}

		$r = $wpdb->insert(
			$table,
			array(
				'name'       => $name,
				'parent_id'  => $parent_id,
				'sort_order' => $sort,
			),
			array( '%s', '%d', '%d' )
		);

		return $r ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update category.
	 *
	 * @param int   $id   Category id.
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
		if ( array_key_exists( 'parent_id', $data ) ) {
			$updates['parent_id'] = (int) $data['parent_id'];
			$formats[]            = '%d';
		}
		if ( array_key_exists( 'sort_order', $data ) ) {
			$updates['sort_order'] = (int) $data['sort_order'];
			$formats[]            = '%d';
		}
		if ( empty( $updates ) ) {
			return true;
		}
		return (bool) $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete category (only if no children and no products).
	 *
	 * @param int $id Category id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$children = self::get_children( $id );
		if ( ! empty( $children ) ) {
			return false;
		}
		$products_table = $wpdb->prefix . 'webinocrm_accounting_products';
		$count          = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $products_table WHERE category_id = %d", $id ) );
		if ( $count > 0 ) {
			return false;
		}
		$table = self::get_table();
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
