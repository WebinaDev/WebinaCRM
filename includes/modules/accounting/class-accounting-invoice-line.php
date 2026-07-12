<?php
/**
 * Accounting Invoice Line model (ردیف فاکتور).
 *
 * @package WebinoCRM
 * @subpackage Accounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Accounting_Invoice_Line
 */
class WebinoCRM_Accounting_Invoice_Line {

	const TABLE = 'webinocrm_accounting_invoice_lines';

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
	 * Get lines for an invoice.
	 *
	 * @param int $invoice_id Invoice id.
	 * @return array
	 */
	public static function get_lines( $invoice_id ) {
		global $wpdb;
		$table = self::get_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE invoice_id = %d ORDER BY sort_order ASC, id ASC",
				(int) $invoice_id
			)
		);
	}

	/**
	 * Save lines for an invoice (replace all).
	 *
	 * @param int   $invoice_id Invoice id.
	 * @param array $lines      Array of line arrays (product_id, quantity, unit_id, unit_price, discount_percent, discount_amount, tax_percent, tax_amount, description, sort_order).
	 * @return bool
	 */
	public static function save_lines( $invoice_id, $lines ) {
		global $wpdb;
		$table = self::get_table();
		$invoice_id = (int) $invoice_id;
		if ( $invoice_id <= 0 ) {
			return false;
		}
		$wpdb->delete( $table, array( 'invoice_id' => $invoice_id ), array( '%d' ) );
		if ( empty( $lines ) || ! is_array( $lines ) ) {
			return true;
		}
		$sort = 0;
		foreach ( $lines as $line ) {
			$product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
			if ( $product_id <= 0 ) {
				continue;
			}
			$qty    = isset( $line['quantity'] ) ? floatval( $line['quantity'] ) : 1;
			$price  = isset( $line['unit_price'] ) ? floatval( $line['unit_price'] ) : 0;
			$disc_p = isset( $line['discount_percent'] ) ? floatval( $line['discount_percent'] ) : null;
			$disc_a = isset( $line['discount_amount'] ) ? floatval( $line['discount_amount'] ) : null;
			$tax_p  = isset( $line['tax_percent'] ) ? floatval( $line['tax_percent'] ) : null;
			$tax_a  = isset( $line['tax_amount'] ) ? floatval( $line['tax_amount'] ) : null;
			$unit_id = isset( $line['unit_id'] ) && (int) $line['unit_id'] > 0 ? (int) $line['unit_id'] : null;
			$desc   = isset( $line['description'] ) ? sanitize_textarea_field( $line['description'] ) : null;
			$wpdb->insert(
				$table,
				array(
					'invoice_id'        => $invoice_id,
					'product_id'        => $product_id,
					'quantity'          => $qty,
					'unit_id'           => $unit_id,
					'unit_price'        => $price,
					'discount_percent'  => $disc_p,
					'discount_amount'   => $disc_a,
					'tax_percent'       => $tax_p,
					'tax_amount'        => $tax_a,
					'description'       => $desc,
					'sort_order'        => $sort++,
				),
				array( '%d', '%d', '%f', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%d' )
			);
		}
		return true;
	}
}
