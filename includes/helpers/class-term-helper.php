<?php
/**
 * Safe wrappers around wp_get_post_terms for REST/AJAX handlers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post taxonomy helpers that tolerate missing taxonomies and WP_Error.
 */
class WebinoCRM_Term_Helper {

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int,WP_Term> Empty array on error.
	 */
	public static function get_terms( $post_id, $taxonomy ) {
		$terms = wp_get_post_terms( (int) $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return WP_Term|null
	 */
	public static function get_first_term( $post_id, $taxonomy ) {
		$terms = self::get_terms( $post_id, $taxonomy );
		return isset( $terms[0] ) ? $terms[0] : null;
	}

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $default  Default slug.
	 * @return string
	 */
	public static function get_first_term_slug( $post_id, $taxonomy, $default = '' ) {
		$term = self::get_first_term( $post_id, $taxonomy );
		return $term && isset( $term->slug ) ? (string) $term->slug : $default;
	}

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $default  Default label.
	 * @return string
	 */
	public static function get_first_term_name( $post_id, $taxonomy, $default = '' ) {
		$term = self::get_first_term( $post_id, $taxonomy );
		return $term && isset( $term->name ) ? (string) $term->name : $default;
	}

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $fields   fields arg for wp_get_post_terms (e.g. 'names', 'ids').
	 * @return array<int|string> Empty array on error.
	 */
	public static function get_term_field_list( $post_id, $taxonomy, $fields = 'names' ) {
		$terms = wp_get_post_terms( (int) $post_id, $taxonomy, array( 'fields' => $fields ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return $terms;
	}
}
