<?php
/**
 * AI catalog / title proposals store and apply helpers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pending AI suggestions for product titles and catalog assignment.
 */
final class WebinoCRM_AI_Proposals {

	const GLOSSARY_OPTION = 'webinocrm_ai_title_glossary';
	const CHUNK_SIZE      = 25;

	/**
	 * @param string               $kind title|catalog.
	 * @param int                  $product_id Product.
	 * @param array<string,mixed>  $current Current snapshot.
	 * @param array<string,mixed>  $proposed Proposed payload.
	 * @return int|WP_Error Proposal ID.
	 */
	public static function upsert( $kind, $product_id, $current, $proposed ) {
		global $wpdb;
		WebinoCRM_AI_Content_Db::ensure_tables();
		$kind       = sanitize_key( (string) $kind );
		$product_id = (int) $product_id;
		if ( ! in_array( $kind, array( 'title', 'catalog' ), true ) || $product_id < 1 ) {
			return new WP_Error( 'ai_proposal', __( 'Invalid proposal.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$table = WebinoCRM_AI_Content_Db::table( 'proposals' );
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE kind = %s AND product_id = %d",
				$kind,
				$product_id
			)
		);
		$row = array(
			'kind'          => $kind,
			'product_id'    => $product_id,
			'current_json'  => wp_json_encode( $current ),
			'proposed_json' => wp_json_encode( $proposed ),
			'status'        => 'pending',
			'updated_at'    => $now,
		);
		if ( $existing ) {
			$wpdb->update(
				$table,
				$row,
				array( 'id' => (int) $existing ),
				array( '%s', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}
		$row['created_at'] = $now;
		$ok = $wpdb->insert(
			$table,
			$row,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'ai_proposal', __( 'Could not save proposal.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param string $kind Kind.
	 * @param array  $args Args: status, limit, offset.
	 * @return array{items:list<array>,total:int}
	 */
	public static function list_proposals( $kind, $args = array() ) {
		global $wpdb;
		WebinoCRM_AI_Content_Db::ensure_tables();
		$kind   = sanitize_key( (string) $kind );
		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'pending';
		$limit  = min( 500, max( 1, (int) ( $args['limit'] ?? 100 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$table  = WebinoCRM_AI_Content_Db::table( 'proposals' );

		$where_sql = 'kind = %s';
		$params    = array( $kind );
		if ( '' !== $status && 'all' !== $status ) {
			$where_sql .= ' AND status = %s';
			$params[]   = $status;
		}
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $params ), ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::present( $row );
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * @param int $id Proposal ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get( $id ) {
		global $wpdb;
		WebinoCRM_AI_Content_Db::ensure_tables();
		$table = WebinoCRM_AI_Content_Db::table( 'proposals' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'ai_proposal', __( 'Proposal not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		return self::present( $row );
	}

	/**
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	private static function present( $row ) {
		$current  = json_decode( (string) ( $row['current_json'] ?? '' ), true );
		$proposed = json_decode( (string) ( $row['proposed_json'] ?? '' ), true );
		$pid      = (int) ( $row['product_id'] ?? 0 );
		$name     = '';
		if ( $pid > 0 && function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( $pid );
			if ( $p ) {
				$name = $p->get_name();
			}
		}
		return array(
			'id'         => (int) ( $row['id'] ?? 0 ),
			'kind'       => (string) ( $row['kind'] ?? '' ),
			'product_id' => $pid,
			'product_name' => $name,
			'current'    => is_array( $current ) ? $current : array(),
			'proposed'   => is_array( $proposed ) ? $proposed : array(),
			'status'     => (string) ( $row['status'] ?? '' ),
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'updated_at' => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * @param int                      $id Proposal.
	 * @param array<string,mixed>|null $override Optional proposed override (e.g. edited title).
	 * @return array<string,mixed>|WP_Error
	 */
	public static function apply( $id, $override = null ) {
		$prop = self::get( (int) $id );
		if ( is_wp_error( $prop ) ) {
			return $prop;
		}
		if ( 'applied' === ( $prop['status'] ?? '' ) ) {
			return $prop;
		}
		$proposed = is_array( $override ) ? array_merge( (array) $prop['proposed'], $override ) : (array) $prop['proposed'];
		if ( 'title' === $prop['kind'] ) {
			$ok = self::apply_title( (int) $prop['product_id'], $proposed );
		} else {
			$ok = self::apply_catalog( (int) $prop['product_id'], $proposed );
		}
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'proposals' );
		$wpdb->update(
			$table,
			array(
				'proposed_json' => wp_json_encode( $proposed ),
				'status'        => 'applied',
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		return self::get( (int) $id );
	}

	/**
	 * @param int $id Proposal.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function skip( $id ) {
		global $wpdb;
		$prop = self::get( (int) $id );
		if ( is_wp_error( $prop ) ) {
			return $prop;
		}
		$table = WebinoCRM_AI_Content_Db::table( 'proposals' );
		$wpdb->update(
			$table,
			array(
				'status'     => 'skipped',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return self::get( (int) $id );
	}

	/**
	 * @param int                 $product_id Product.
	 * @param array<string,mixed> $proposed Proposed.
	 * @return true|WP_Error
	 */
	private static function apply_title( $product_id, $proposed ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'no_wc', __( 'WooCommerce required.', 'webinocrm' ) );
		}
		$p = wc_get_product( (int) $product_id );
		if ( ! $p ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'webinocrm' ) );
		}
		$name = sanitize_text_field( (string) ( $proposed['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'ai_title', __( 'Empty title.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$p->set_name( $name );
		if ( WebinoCRM_AI_Content_Settings::field_enabled( 'product', 'slug' ) && ! empty( $proposed['slug'] ) ) {
			$p->set_slug( sanitize_title( (string) $proposed['slug'] ) );
		} elseif ( WebinoCRM_AI_Content_Settings::field_enabled( 'product', 'slug' ) ) {
			$p->set_slug( sanitize_title( $name ) );
		}
		$p->save();
		self::merge_glossary_from_title( $name, $proposed );
		return true;
	}

	/**
	 * @param int                 $product_id Product.
	 * @param array<string,mixed> $proposed Proposed.
	 * @return true|WP_Error
	 */
	private static function apply_catalog( $product_id, $proposed ) {
		$settings = WebinoCRM_AI_Content_Settings::get();
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'no_wc', __( 'WooCommerce required.', 'webinocrm' ) );
		}
		$p = wc_get_product( (int) $product_id );
		if ( ! $p ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'webinocrm' ) );
		}

		if ( ! empty( $settings['catalog_assign_categories'] ) ) {
			$cat_ids = self::resolve_category_ids( $proposed );
			if ( is_wp_error( $cat_ids ) ) {
				return $cat_ids;
			}
			if ( $cat_ids ) {
				$with_parents = self::expand_category_ancestors( $cat_ids );
				wp_set_object_terms( (int) $product_id, $with_parents, 'product_cat', false );
			}
		}

		if ( ! empty( $settings['catalog_assign_brands'] ) && taxonomy_exists( 'product_brand' ) ) {
			$brand_id = self::resolve_brand_id( $proposed );
			if ( is_wp_error( $brand_id ) ) {
				return $brand_id;
			}
			if ( $brand_id > 0 ) {
				wp_set_object_terms( (int) $product_id, array( $brand_id ), 'product_brand', false );
			}
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $proposed Proposed.
	 * @return list<int>|WP_Error
	 */
	private static function resolve_category_ids( $proposed ) {
		$settings = WebinoCRM_AI_Content_Settings::get();
		$ids      = array();
		$raw_ids  = isset( $proposed['category_ids'] ) && is_array( $proposed['category_ids'] ) ? $proposed['category_ids'] : array();
		foreach ( $raw_ids as $cid ) {
			$cid = (int) $cid;
			if ( $cid > 0 && term_exists( $cid, 'product_cat' ) ) {
				$ids[] = $cid;
			}
		}
		$paths = isset( $proposed['categories'] ) && is_array( $proposed['categories'] ) ? $proposed['categories'] : array();
		foreach ( $paths as $row ) {
			if ( is_string( $row ) ) {
				$tid = self::find_or_create_category_path( array( $row ), ! empty( $settings['catalog_create_terms'] ) );
			} elseif ( is_array( $row ) ) {
				$path = array();
				if ( ! empty( $row['path'] ) && is_array( $row['path'] ) ) {
					$path = $row['path'];
				} elseif ( ! empty( $row['name'] ) ) {
					$path = array_filter( array( (string) ( $row['parent'] ?? '' ), (string) $row['name'] ) );
				}
				$tid = self::find_or_create_category_path( array_values( $path ), ! empty( $settings['catalog_create_terms'] ) );
			} else {
				continue;
			}
			if ( is_wp_error( $tid ) ) {
				return $tid;
			}
			if ( $tid > 0 ) {
				$ids[] = $tid;
			}
		}
		$new = isset( $proposed['new_categories'] ) && is_array( $proposed['new_categories'] ) ? $proposed['new_categories'] : array();
		foreach ( $new as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) ) {
				continue;
			}
			if ( empty( $settings['catalog_create_terms'] ) ) {
				continue;
			}
			$path = array();
			if ( ! empty( $row['parent'] ) ) {
				$path[] = (string) $row['parent'];
			}
			$path[] = (string) $row['name'];
			$tid    = self::find_or_create_category_path( $path, true );
			if ( is_wp_error( $tid ) ) {
				return $tid;
			}
			if ( $tid > 0 ) {
				$ids[] = $tid;
			}
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * @param list<string> $path Names from root to leaf.
	 * @param bool         $create Create missing.
	 * @return int|WP_Error Term ID.
	 */
	private static function find_or_create_category_path( $path, $create ) {
		$parent = 0;
		$last   = 0;
		foreach ( $path as $name ) {
			$name = trim( (string) $name );
			if ( '' === $name ) {
				continue;
			}
			$found = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'name'       => $name,
					'parent'     => $parent,
					'number'     => 1,
				)
			);
			if ( ! is_wp_error( $found ) && ! empty( $found[0] ) ) {
				$last   = (int) $found[0]->term_id;
				$parent = $last;
				continue;
			}
			// Fallback: match by name anywhere.
			$any = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'name'       => $name,
					'number'     => 1,
				)
			);
			if ( ! is_wp_error( $any ) && ! empty( $any[0] ) && ! $create ) {
				$last   = (int) $any[0]->term_id;
				$parent = $last;
				continue;
			}
			if ( ! $create ) {
				return 0;
			}
			$ins = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
			if ( is_wp_error( $ins ) ) {
				if ( 'term_exists' === $ins->get_error_code() ) {
					$last   = (int) $ins->get_error_data();
					$parent = $last;
					continue;
				}
				return $ins;
			}
			$last   = (int) $ins['term_id'];
			$parent = $last;
		}
		return $last;
	}

	/**
	 * @param list<int> $ids Leaf category IDs.
	 * @return list<int>
	 */
	public static function expand_category_ancestors( $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id < 1 ) {
				continue;
			}
			$out[] = $id;
			$term  = get_term( $id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$ancestors = get_ancestors( $id, 'product_cat', 'taxonomy' );
			foreach ( (array) $ancestors as $aid ) {
				$out[] = (int) $aid;
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * @param array<string,mixed> $proposed Proposed.
	 * @return int|WP_Error Brand term ID.
	 */
	private static function resolve_brand_id( $proposed ) {
		$settings = WebinoCRM_AI_Content_Settings::get();
		if ( ! empty( $proposed['brand_id'] ) ) {
			$bid = (int) $proposed['brand_id'];
			if ( $bid > 0 && term_exists( $bid, 'product_brand' ) ) {
				return $bid;
			}
		}
		$name = trim( (string) ( $proposed['brand'] ?? $proposed['brand_name'] ?? '' ) );
		if ( '' === $name ) {
			return 0;
		}
		$found = get_terms(
			array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => false,
				'name'       => $name,
				'number'     => 1,
			)
		);
		if ( ! is_wp_error( $found ) && ! empty( $found[0] ) ) {
			return (int) $found[0]->term_id;
		}
		if ( empty( $settings['catalog_create_terms'] ) ) {
			return 0;
		}
		$ins = wp_insert_term( $name, 'product_brand' );
		if ( is_wp_error( $ins ) ) {
			if ( 'term_exists' === $ins->get_error_code() ) {
				return (int) $ins->get_error_data();
			}
			return $ins;
		}
		return (int) $ins['term_id'];
	}

	/**
	 * @return list<array{id:int,name:string,parent:int,path:string}>
	 */
	public static function category_tree() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		$out = array();
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			$ancestors = array_reverse( get_ancestors( (int) $t->term_id, 'product_cat', 'taxonomy' ) );
			$names     = array();
			foreach ( $ancestors as $aid ) {
				$a = get_term( (int) $aid, 'product_cat' );
				if ( $a && ! is_wp_error( $a ) ) {
					$names[] = $a->name;
				}
			}
			$names[] = $t->name;
			$out[]   = array(
				'id'     => (int) $t->term_id,
				'name'   => $t->name,
				'parent' => (int) $t->parent,
				'path'   => implode( ' > ', $names ),
			);
		}
		return $out;
	}

	/**
	 * @return list<array{id:int,name:string}>
	 */
	public static function brand_list() {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		$out = array();
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'   => (int) $t->term_id,
				'name' => $t->name,
			);
		}
		return $out;
	}

	/**
	 * Product IDs for catalog/title batch.
	 *
	 * @param string $kind title|catalog.
	 * @param bool   $only_missing Catalog: only missing cats/brands; title: skip pending applied.
	 * @return list<int>
	 */
	public static function product_ids_for_batch( $kind, $only_missing = true ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$ids = wc_get_products(
			array(
				'limit'   => 500,
				'status'  => array( 'publish', 'draft', 'pending' ),
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			$p  = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			if ( 'catalog' === $kind && $only_missing ) {
				$cats   = $p->get_category_ids();
				$brands = taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $id, 'product_brand', array( 'fields' => 'ids' ) ) : array();
				$missing_cat   = empty( $cats );
				$missing_brand = taxonomy_exists( 'product_brand' ) && ( is_wp_error( $brands ) || empty( $brands ) );
				$settings      = WebinoCRM_AI_Content_Settings::get();
				$need = false;
				if ( ! empty( $settings['catalog_assign_categories'] ) && $missing_cat ) {
					$need = true;
				}
				if ( ! empty( $settings['catalog_assign_brands'] ) && $missing_brand ) {
					$need = true;
				}
				if ( ! $need ) {
					continue;
				}
			}
			$out[] = $id;
		}
		return $out;
	}

	/**
	 * @return array{product_types:list<string>,brands:list<string>}
	 */
	public static function get_glossary() {
		$g = get_option( self::GLOSSARY_OPTION, array() );
		if ( ! is_array( $g ) ) {
			$g = array();
		}
		return array(
			'product_types' => isset( $g['product_types'] ) && is_array( $g['product_types'] ) ? array_values( array_unique( array_map( 'strval', $g['product_types'] ) ) ) : array(),
			'brands'        => isset( $g['brands'] ) && is_array( $g['brands'] ) ? array_values( array_unique( array_map( 'strval', $g['brands'] ) ) ) : array(),
		);
	}

	/**
	 * @param array{product_types?:list<string>,brands?:list<string>} $add Additions.
	 * @return void
	 */
	public static function merge_glossary( $add ) {
		$g = self::get_glossary();
		if ( ! empty( $add['product_types'] ) && is_array( $add['product_types'] ) ) {
			foreach ( $add['product_types'] as $t ) {
				$t = trim( (string) $t );
				if ( '' !== $t && ! in_array( $t, $g['product_types'], true ) ) {
					$g['product_types'][] = $t;
				}
			}
		}
		if ( ! empty( $add['brands'] ) && is_array( $add['brands'] ) ) {
			foreach ( $add['brands'] as $b ) {
				$b = trim( (string) $b );
				if ( '' !== $b && ! in_array( $b, $g['brands'], true ) ) {
					$g['brands'][] = $b;
				}
			}
		}
		update_option( self::GLOSSARY_OPTION, $g, false );
	}

	/**
	 * @param string              $name Final title.
	 * @param array<string,mixed> $proposed Proposed parts.
	 * @return void
	 */
	private static function merge_glossary_from_title( $name, $proposed ) {
		$add = array(
			'product_types' => array(),
			'brands'        => array(),
		);
		if ( ! empty( $proposed['product'] ) ) {
			$add['product_types'][] = (string) $proposed['product'];
		}
		if ( ! empty( $proposed['brand'] ) ) {
			$add['brands'][] = (string) $proposed['brand'];
		}
		self::merge_glossary( $add );
	}

	/**
	 * Enqueue chunked jobs.
	 *
	 * @param string     $kind title|catalog.
	 * @param list<int>|null $product_ids Optional explicit IDs.
	 * @return array{ok:bool,job_ids:list<int>,count:int,chunks:int}|WP_Error
	 */
	public static function enqueue_batch( $kind, $product_ids = null ) {
		$kind = sanitize_key( (string) $kind );
		if ( ! in_array( $kind, array( 'title', 'catalog' ), true ) ) {
			return new WP_Error( 'ai_batch', __( 'Invalid kind.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$settings = WebinoCRM_AI_Content_Settings::get();
		if ( 'title' === $kind && empty( $settings['title_enabled'] ) ) {
			return new WP_Error( 'ai_title_off', __( 'Title rewrite is disabled in settings.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		if ( 'catalog' === $kind && empty( $settings['catalog_assign_categories'] ) && empty( $settings['catalog_assign_brands'] ) ) {
			return new WP_Error( 'ai_catalog_off', __( 'Catalog assignment is disabled in settings.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		if ( null === $product_ids ) {
			$only = 'catalog' === $kind ? ! empty( $settings['catalog_only_missing'] ) : false;
			$product_ids = self::product_ids_for_batch( $kind, $only );
		} else {
			$product_ids = array_values( array_filter( array_map( 'intval', (array) $product_ids ) ) );
		}
		if ( ! $product_ids ) {
			return new WP_Error( 'ai_batch', __( 'No products to process.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$job_type = 'title' === $kind ? 'title_rewrite' : 'catalog_classify';
		$chunks   = array_chunk( $product_ids, self::CHUNK_SIZE );
		$job_ids  = array();
		foreach ( $chunks as $chunk ) {
			$jid = WebinoCRM_AI_Queue::enqueue(
				$job_type,
				'batch',
				0,
				array( 'product_ids' => $chunk )
			);
			if ( is_wp_error( $jid ) ) {
				return $jid;
			}
			$job_ids[] = $jid;
		}
		return array(
			'ok'       => true,
			'job_ids'  => $job_ids,
			'count'    => count( $product_ids ),
			'chunks'   => count( $chunks ),
		);
	}
}
