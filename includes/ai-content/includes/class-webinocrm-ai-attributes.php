<?php
/**
 * Per-category WooCommerce attribute templates.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store and apply attribute templates for product categories.
 */
final class WebinoCRM_AI_Attributes {

	/**
	 * @param int $product_cat_id Category ID.
	 * @return array{product_cat_id:int,attribute_ids:int[],labels:array}|null
	 */
	public static function get_template( $product_cat_id ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'attr_templates' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_cat_id = %d", (int) $product_cat_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$ids    = json_decode( (string) $row['attribute_ids'], true );
		$ids    = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		$labels = self::labels_for_ids( $ids );
		if ( ! $labels ) {
			$decoded = json_decode( (string) $row['labels'], true );
			$labels  = is_array( $decoded ) ? $decoded : array();
		}
		return array(
			'id'             => (int) $row['id'],
			'product_cat_id' => (int) $row['product_cat_id'],
			'attribute_ids'  => $ids,
			'labels'         => $labels,
			'updated_at'     => (string) $row['updated_at'],
		);
	}

	/**
	 * @return array{items:array}
	 */
	public static function list_templates() {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'attr_templates' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY product_cat_id ASC", ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$term = get_term( (int) $row['product_cat_id'], 'product_cat' );
			$ids  = json_decode( (string) $row['attribute_ids'], true );
			$ids  = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
			$labels = self::labels_for_ids( $ids );
			if ( ! $labels ) {
				$decoded = json_decode( (string) $row['labels'], true );
				$labels  = is_array( $decoded ) ? $decoded : array();
			}
			$items[] = array(
				'id'             => (int) $row['id'],
				'product_cat_id' => (int) $row['product_cat_id'],
				'category_name'  => ( $term && ! is_wp_error( $term ) ) ? $term->name : '',
				'attribute_ids'  => $ids,
				'labels'         => $labels,
				'updated_at'     => (string) $row['updated_at'],
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * Confirm a draft: create global attributes if needed and save template.
	 *
	 * @param int                 $product_cat_id Category.
	 * @param array<string,mixed> $draft Draft from AI or manual.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function confirm_template( $product_cat_id, $draft ) {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return new WP_Error( 'no_wc', __( 'WooCommerce required.', 'webinocrm' ) );
		}

		$attrs = isset( $draft['attributes'] ) && is_array( $draft['attributes'] ) ? $draft['attributes'] : array();
		if ( ! $attrs ) {
			return new WP_Error( 'empty', __( 'No attributes in draft.', 'webinocrm' ) );
		}

		$attribute_ids = array();
		$labels        = array();

		foreach ( $attrs as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $row['label'] ?? $row['name'] ?? '' ) );
			$slug  = sanitize_title( (string) ( $row['slug'] ?? $label ) );
			if ( '' === $label ) {
				continue;
			}

			$existing_id = 0;
			if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
				foreach ( wc_get_attribute_taxonomies() as $tax ) {
					if ( sanitize_title( $tax->attribute_name ) === $slug || $tax->attribute_label === $label ) {
						$existing_id = (int) $tax->attribute_id;
						break;
					}
				}
			}

			if ( $existing_id <= 0 ) {
				$existing_id = (int) wc_create_attribute(
					array(
						'name'         => $label,
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);
				if ( $existing_id <= 0 ) {
					continue;
				}
				$taxonomy = wc_attribute_taxonomy_name( $slug );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					register_taxonomy( $taxonomy, array( 'product' ) );
				}
			}

			$taxonomy = wc_attribute_taxonomy_name_by_id( $existing_id );
			$options  = isset( $row['options'] ) && is_array( $row['options'] ) ? $row['options'] : array();
			foreach ( $options as $opt ) {
				$opt = sanitize_text_field( (string) $opt );
				if ( '' === $opt ) {
					continue;
				}
				if ( ! term_exists( $opt, $taxonomy ) ) {
					wp_insert_term( $opt, $taxonomy );
				}
			}

			$attribute_ids[] = $existing_id;
			$labels[]        = array(
				'attribute_id' => $existing_id,
				'label'        => $label,
				'slug'         => $slug,
			);
		}

		if ( ! $attribute_ids ) {
			return new WP_Error( 'empty', __( 'Could not create attributes.', 'webinocrm' ) );
		}

		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'attr_templates' );
		$now   = current_time( 'mysql', true );
		$existing = self::get_template( (int) $product_cat_id );

		$data = array(
			'product_cat_id' => (int) $product_cat_id,
			'attribute_ids'  => wp_json_encode( $attribute_ids ),
			'labels'         => wp_json_encode( $labels ),
			'updated_at'     => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'product_cat_id' => (int) $product_cat_id ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
		}

		delete_transient( 'webinocrm_ai_attr_draft_' . (int) $product_cat_id );

		return self::get_template( (int) $product_cat_id );
	}

	/**
	 * Map existing WooCommerce attributes onto a category (no new attributes created).
	 *
	 * @param int   $product_cat_id Category.
	 * @param array $attribute_ids WC attribute IDs.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save_mapping( $product_cat_id, $attribute_ids ) {
		$product_cat_id = (int) $product_cat_id;
		if ( $product_cat_id < 1 ) {
			return new WP_Error( 'invalid', __( 'Invalid category.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$term = get_term( $product_cat_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'invalid', __( 'Invalid category.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$ids = array();
		foreach ( (array) $attribute_ids as $aid ) {
			$aid = (int) $aid;
			if ( $aid > 0 && ! in_array( $aid, $ids, true ) ) {
				$ids[] = $aid;
			}
		}
		if ( ! $ids ) {
			self::delete_template( $product_cat_id );
			return array(
				'product_cat_id' => $product_cat_id,
				'attribute_ids'  => array(),
				'labels'         => array(),
			);
		}

		$labels = self::labels_for_ids( $ids );
		if ( ! $labels ) {
			return new WP_Error( 'empty', __( 'No valid attributes.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$ids = wp_list_pluck( $labels, 'attribute_id' );

		global $wpdb;
		$table    = WebinoCRM_AI_Content_Db::table( 'attr_templates' );
		$now      = current_time( 'mysql', true );
		$existing = self::get_template( $product_cat_id );
		$data     = array(
			'product_cat_id' => $product_cat_id,
			'attribute_ids'  => wp_json_encode( $ids ),
			'labels'         => wp_json_encode( $labels ),
			'updated_at'     => $now,
		);
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'product_cat_id' => $product_cat_id ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
		}
		return self::get_template( $product_cat_id );
	}

	/**
	 * Merge templates for all product categories (unique attributes).
	 * Falls back to attributes already used on products in those categories.
	 *
	 * @param array<int> $category_ids Category IDs.
	 * @return array{attribute_ids:int[],labels:array}
	 */
	public static function merge_for_categories( $category_ids ) {
		$ids  = array();
		$seen = array();
		foreach ( (array) $category_ids as $cid ) {
			$cid = (int) $cid;
			$tpl = $cid > 0 ? self::get_template( $cid ) : null;
			if ( $tpl && ! empty( $tpl['attribute_ids'] ) ) {
				$src = $tpl['attribute_ids'];
			} else {
				$src = self::discover_in_categories( array( $cid ) )['attribute_ids'];
			}
			foreach ( (array) $src as $aid ) {
				$aid = (int) $aid;
				if ( $aid < 1 || isset( $seen[ $aid ] ) ) {
					continue;
				}
				$seen[ $aid ] = true;
				$ids[]        = $aid;
			}
		}
		return array(
			'attribute_ids' => $ids,
			'labels'        => self::labels_for_model( $ids ),
		);
	}

	/**
	 * Global attribute IDs already used on products in these categories.
	 *
	 * @param array<int> $category_ids Category IDs.
	 * @param int        $limit        Max products to scan.
	 * @return array{attribute_ids:int[],labels:array}
	 */
	public static function discover_in_categories( $category_ids, $limit = 200 ) {
		$empty = array(
			'attribute_ids' => array(),
			'labels'        => array(),
		);
		$category_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $category_ids ) ) ) );
		if ( ! $category_ids || ! function_exists( 'wc_get_product' ) ) {
			return $empty;
		}

		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => max( 1, (int) $limit ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $category_ids,
					),
				),
			)
		);

		$ids  = array();
		$seen = array();
		foreach ( (array) $q->posts as $pid ) {
			$p = wc_get_product( (int) $pid );
			if ( ! $p ) {
				continue;
			}
			foreach ( $p->get_attributes() as $attr ) {
				if ( ! is_object( $attr ) ) {
					continue;
				}
				$aid = (int) $attr->get_id();
				if ( $aid < 1 || isset( $seen[ $aid ] ) ) {
					continue;
				}
				$seen[ $aid ] = true;
				$ids[]        = $aid;
			}
		}

		return array(
			'attribute_ids' => $ids,
			'labels'        => self::labels_for_ids( $ids ),
		);
	}

	/**
	 * Shape sent to the model: names plus existing_options as hints only.
	 *
	 * @param array<int> $attribute_ids IDs.
	 * @return array<int,array<string,mixed>>
	 */
	public static function labels_for_model( $attribute_ids ) {
		$out = array();
		foreach ( self::labels_for_ids( $attribute_ids ) as $row ) {
			$out[] = array(
				'attribute_id'     => (int) ( $row['attribute_id'] ?? 0 ),
				'label'            => (string) ( $row['label'] ?? '' ),
				'slug'             => (string) ( $row['slug'] ?? '' ),
				'existing_options' => isset( $row['options'] ) && is_array( $row['options'] ) ? $row['options'] : array(),
			);
		}
		return $out;
	}

	/**
	 * @param array<int> $attribute_ids IDs.
	 * @return array<int,array<string,mixed>>
	 */
	public static function labels_for_ids( $attribute_ids ) {
		$out = array();
		if ( ! function_exists( 'wc_get_attribute' ) ) {
			return $out;
		}
		foreach ( (array) $attribute_ids as $aid ) {
			$aid  = (int) $aid;
			$attr = $aid > 0 ? wc_get_attribute( $aid ) : null;
			if ( ! $attr ) {
				continue;
			}
			$options  = array();
			$taxonomy = wc_attribute_taxonomy_name_by_id( $aid );
			if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$options[] = $term->name;
					}
				}
			}
			$out[] = array(
				'attribute_id' => $aid,
				'label'        => (string) $attr->name,
				'slug'         => (string) $attr->slug,
				'options'      => $options,
			);
		}
		return $out;
	}

	/**
	 * @param int $product_cat_id Category.
	 * @return true
	 */
	public static function delete_template( $product_cat_id ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'attr_templates' );
		$wpdb->delete( $table, array( 'product_cat_id' => (int) $product_cat_id ), array( '%d' ) );
		return true;
	}
}
