<?php
/**
 * Apply AI-generated content to WordPress entities.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writers for product, post, and taxonomy terms.
 */
final class WebinoCRM_AI_Writer {

	/**
	 * @param int                 $product_id Product ID.
	 * @param array<string,mixed> $data Generated data.
	 * @param array<string,mixed> $opts Options.
	 * @return true|WP_Error
	 */
	public static function apply_product( $product_id, $data, $opts = array() ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'no_wc', __( 'WooCommerce required.', 'webinocrm' ) );
		}
		$p = wc_get_product( (int) $product_id );
		if ( ! $p ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'webinocrm' ) );
		}

		$on     = static function ( $field ) {
			return WebinoCRM_AI_Content_Settings::field_enabled( 'product', $field );
		};
		$name   = $on( 'name' ) ? sanitize_text_field( (string) ( $data['name'] ?? $p->get_name() ) ) : $p->get_name();
		$slug   = $on( 'slug' ) ? sanitize_title( (string) ( $data['slug'] ?? $name ) ) : $p->get_slug();
		$short  = $on( 'short_description' ) ? (string) ( $data['short_description'] ?? '' ) : '';
		$desc   = $on( 'description' ) ? (string) ( $data['description'] ?? '' ) : '';

		if ( $on( 'name' ) && $name ) {
			$p->set_name( $name );
		}
		if ( $on( 'slug' ) && $slug ) {
			$p->set_slug( $slug );
		}
		if ( $short ) {
			$p->set_short_description( wp_kses_post( $short ) );
		}
		if ( $desc ) {
			$p->set_description( wp_kses_post( $desc ) );
		}

		$status = ! empty( $opts['publish'] ) ? (string) ( $opts['status'] ?? 'draft' ) : 'draft';
		if ( in_array( $status, array( 'draft', 'publish', 'pending' ), true ) && ! empty( $opts['set_status'] ) ) {
			$p->set_status( $status );
		}

		$seo = $on( 'seo' ) ? self::normalize_seo( $data, 'product', $name ) : array();
		if ( class_exists( 'WebinoCRM_REST', false ) ) {
			if ( $seo ) {
				WebinoCRM_REST::apply_product_seo( $p, $seo );
			}
			$ishop = array();
			if ( $on( 'english_name' ) && ! empty( $data['english_name'] ) ) {
				$ishop['english_name'] = sanitize_text_field( (string) $data['english_name'] );
			}
			if ( $on( 'ai_review_summary' ) && ! empty( $data['ai_review_summary'] ) ) {
				$ishop['ai_review_summary'] = sanitize_textarea_field( (string) $data['ai_review_summary'] );
			}
			if ( $on( 'faqs' ) && ! empty( $data['faqs'] ) && is_array( $data['faqs'] ) ) {
				$ishop['faqs'] = $data['faqs'];
			}
			if ( $on( 'custom_labels' ) && ! empty( $data['custom_labels'] ) && is_array( $data['custom_labels'] ) ) {
				$ishop['custom_labels'] = $data['custom_labels'];
			}
			if ( $ishop ) {
				WebinoCRM_REST::apply_product_ishop( $p, $ishop );
			}
		}

		if ( $on( 'attributes' ) && ! empty( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			self::apply_product_attributes( $p, $data['attributes'], $opts );
		}

		if ( $on( 'tags' ) && ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$tag_ids = array();
			foreach ( $data['tags'] as $tag_name ) {
				$term = term_exists( (string) $tag_name, 'product_tag' );
				if ( ! $term ) {
					$term = wp_insert_term( sanitize_text_field( (string) $tag_name ), 'product_tag' );
				}
				if ( ! is_wp_error( $term ) ) {
					$tag_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
				}
			}
			if ( $tag_ids ) {
				$p->set_tag_ids( $tag_ids );
			}
		}

		$related_ids = isset( $opts['related_ids'] ) && is_array( $opts['related_ids'] ) ? array_values( array_filter( array_map( 'intval', $opts['related_ids'] ) ) ) : array();
		if ( $related_ids ) {
			$picked = array();
			if ( ! empty( $data['related_product_ids'] ) && is_array( $data['related_product_ids'] ) ) {
				$wanted = array_map( 'intval', $data['related_product_ids'] );
				foreach ( $related_ids as $rid ) {
					if ( in_array( $rid, $wanted, true ) ) {
						$picked[] = $rid;
					}
				}
			}
			$p->set_upsell_ids( $picked ? $picked : $related_ids );
		}

		$p->save();

		$coffee = self::apply_coffee_profile( (int) $product_id, $data );
		if ( is_wp_error( $coffee ) ) {
			return $coffee;
		}

		$focus = (string) ( $seo['focus_keyword'] ?? '' );
		WebinoCRM_AI_Seo_Gate::record_run( 'product', (int) $product_id, $focus, $name, $desc );

		return true;
	}

	/**
	 * @param array<string,mixed> $data Data.
	 * @param array<string,mixed> $opts Options.
	 * @return int|WP_Error Post ID.
	 */
	public static function create_or_update_post( $data, $opts = array() ) {
		$on      = static function ( $field ) {
			return WebinoCRM_AI_Content_Settings::field_enabled( 'blog', $field );
		};
		$title   = $on( 'title' ) ? sanitize_text_field( (string) ( $data['title'] ?? '' ) ) : '';
		$content = $on( 'content' ) ? wp_kses_post( (string) ( $data['content'] ?? '' ) ) : '';
		$excerpt = $on( 'excerpt' ) ? sanitize_textarea_field( (string) ( $data['excerpt'] ?? '' ) ) : '';
		$slug    = $on( 'slug' ) ? sanitize_title( (string) ( $data['slug'] ?? $title ) ) : '';
		$focus   = sanitize_text_field( (string) ( $data['focus_keyword'] ?? ( $data['seo']['focus_keyword'] ?? '' ) ) );

		$post_id = (int) ( $opts['post_id'] ?? 0 );
		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );
			if ( $existing ) {
				if ( '' === $title ) {
					$title = $existing->post_title;
				}
				if ( '' === $content ) {
					$content = $existing->post_content;
				}
				if ( '' === $excerpt ) {
					$excerpt = $existing->post_excerpt;
				}
				if ( '' === $slug ) {
					$slug = $existing->post_name;
				}
			}
		}

		if ( '' === $title || '' === $content ) {
			return new WP_Error( 'ai_empty', __( 'Empty blog content.', 'webinocrm' ) );
		}

		$settings = WebinoCRM_AI_Content_Settings::get();
		$status   = 'draft';
		if ( ! empty( $opts['publish'] ) || ! empty( $settings['auto_publish'] ) ) {
			$status = sanitize_key( (string) ( $opts['status'] ?? $settings['publish_status'] ) );
			if ( ! in_array( $status, array( 'draft', 'publish', 'pending' ), true ) ) {
				$status = 'draft';
			}
		}

		$post_id = (int) ( $opts['post_id'] ?? 0 );
		$args    = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_name'    => $slug,
			'post_status'  => $status,
			'post_type'    => 'post',
		);

		if ( $post_id > 0 ) {
			$args['ID'] = $post_id;
			$result     = wp_update_post( $args, true );
		} else {
			$result = wp_insert_post( $args, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id = (int) $result;

		if ( $on( 'tags' ) && ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $data['tags'] ), false );
		}

		if ( ! empty( $opts['category_id'] ) ) {
			wp_set_post_categories( $post_id, array( (int) $opts['category_id'] ) );
		} elseif ( ! empty( $data['category_names'] ) && is_array( $data['category_names'] ) ) {
			$ids = array();
			foreach ( $data['category_names'] as $cname ) {
				$term = term_exists( (string) $cname, 'category' );
				if ( ! $term ) {
					$term = wp_insert_term( sanitize_text_field( (string) $cname ), 'category' );
				}
				if ( ! is_wp_error( $term ) ) {
					$ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
				}
			}
			if ( $ids ) {
				wp_set_post_categories( $post_id, $ids );
			}
		}

		if ( $on( 'seo' ) ) {
			$seo = self::normalize_seo( $data, 'article', $title );
			self::apply_post_seo( $post_id, $seo );
		}

		WebinoCRM_AI_Seo_Gate::record_run( 'post', $post_id, $focus, $title, $content );

		return $post_id;
	}

	/**
	 * @param int                 $term_id Term ID.
	 * @param string              $taxonomy Taxonomy.
	 * @param array<string,mixed> $data Data.
	 * @return true|WP_Error
	 */
	public static function apply_term( $term_id, $taxonomy, $data ) {
		$term_id  = (int) $term_id;
		$taxonomy = sanitize_key( $taxonomy );
		$term     = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', __( 'Term not found.', 'webinocrm' ) );
		}

		$entity = WebinoCRM_AI_Content_Settings::entity_for_type( $taxonomy );
		$desc   = '';
		if ( WebinoCRM_AI_Content_Settings::field_enabled( $entity, 'description' ) ) {
			$desc = wp_kses_post( (string) ( $data['description'] ?? '' ) );
			if ( $desc ) {
				wp_update_term( $term_id, $taxonomy, array( 'description' => $desc ) );
			}
		}

		if ( WebinoCRM_AI_Content_Settings::field_enabled( $entity, 'seo' ) ) {
			$seo = self::normalize_seo( $data, 'collectionpage', $term->name );
			self::apply_term_seo( $term_id, $seo );
		} else {
			$seo = array();
		}

		$focus = (string) ( $seo['focus_keyword'] ?? $term->name );
		WebinoCRM_AI_Seo_Gate::record_run( $taxonomy, $term_id, $focus, $term->name, $desc );

		return true;
	}

	/**
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $seo SEO fields.
	 * @return void
	 */
	public static function apply_post_seo( $post_id, $seo ) {
		$map = array(
			'title'                => 'rank_math_title',
			'description'          => 'rank_math_description',
			'focus_keyword'        => 'rank_math_focus_keyword',
			'canonical_url'        => 'rank_math_canonical_url',
			'breadcrumb_title'     => 'rank_math_breadcrumb_title',
			'facebook_title'       => 'rank_math_facebook_title',
			'facebook_description' => 'rank_math_facebook_description',
			'facebook_image'       => 'rank_math_facebook_image',
			'twitter_title'        => 'rank_math_twitter_title',
			'twitter_description'  => 'rank_math_twitter_description',
			'twitter_image'        => 'rank_math_twitter_image',
			'twitter_card_type'    => 'rank_math_twitter_card_type',
			'schema_type'          => 'rank_math_rich_snippet',
		);
		foreach ( $map as $key => $meta ) {
			if ( array_key_exists( $key, $seo ) ) {
				update_post_meta( (int) $post_id, $meta, sanitize_text_field( (string) $seo[ $key ] ) );
			}
		}
		if ( ! empty( $seo['pillar_content'] ) ) {
			update_post_meta( (int) $post_id, 'rank_math_pillar_content', 'on' );
		}
	}

	/**
	 * @param int                 $term_id Term ID.
	 * @param array<string,mixed> $seo SEO.
	 * @return void
	 */
	public static function apply_term_seo( $term_id, $seo ) {
		$map = array(
			'title'                => 'rank_math_title',
			'description'          => 'rank_math_description',
			'focus_keyword'        => 'rank_math_focus_keyword',
			'facebook_title'       => 'rank_math_facebook_title',
			'facebook_description' => 'rank_math_facebook_description',
			'schema_type'          => 'rank_math_rich_snippet',
		);
		foreach ( $map as $key => $meta ) {
			if ( array_key_exists( $key, $seo ) ) {
				update_term_meta( (int) $term_id, $meta, sanitize_text_field( (string) $seo[ $key ] ) );
			}
		}
	}

	/**
	 * Apply AI page blueprint + Elementor tree.
	 *
	 * @param int                 $page_id Page ID.
	 * @param array<string,mixed> $data Blueprint.
	 * @param array<string,mixed> $opts Options.
	 * @return true|WP_Error
	 */
	public static function apply_page( $page_id, $data, $opts = array() ) {
		$page_id = (int) $page_id;
		$post    = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'not_page', __( 'Page not found.', 'webinocrm' ) );
		}

		$on = static function ( $field ) {
			return WebinoCRM_AI_Content_Settings::field_enabled( 'page', $field );
		};

		$title   = $on( 'title' ) ? sanitize_text_field( (string) ( $data['title'] ?? $post->post_title ) ) : $post->post_title;
		$slug    = $on( 'slug' ) ? sanitize_title( (string) ( $data['slug'] ?? $title ) ) : $post->post_name;
		$excerpt = $on( 'excerpt' ) ? sanitize_textarea_field( (string) ( $data['excerpt'] ?? '' ) ) : $post->post_excerpt;
		$h1      = sanitize_text_field( (string) ( $data['h1'] ?? $title ) );

		$settings = WebinoCRM_AI_Content_Settings::get();
		$status   = $post->post_status;
		if ( ! empty( $opts['set_status'] ) || ! empty( $settings['auto_publish'] ) ) {
			$status = sanitize_key( (string) ( $opts['status'] ?? $settings['publish_status'] ?? 'draft' ) );
			if ( ! in_array( $status, array( 'draft', 'publish', 'pending' ), true ) ) {
				$status = 'draft';
			}
		}

		$fallback_html = '';
		if ( $on( 'content' ) ) {
			$fallback_html = '<h1>' . esc_html( $h1 ) . '</h1>';
			if ( $excerpt ) {
				$fallback_html .= '<p>' . esc_html( $excerpt ) . '</p>';
			}
		}

		$args = array(
			'ID'           => $page_id,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
		);
		if ( $fallback_html ) {
			$args['post_content'] = $fallback_html;
		}
		$result = wp_update_post( $args, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! empty( $opts['page_prompt'] ) ) {
			update_post_meta( $page_id, '_webino_ai_page_prompt', sanitize_textarea_field( (string) $opts['page_prompt'] ) );
		}

		if ( $on( 'seo' ) ) {
			$seo = self::normalize_seo( $data, 'webpage', $title );
			self::apply_post_seo( $page_id, $seo );
		}

		// Palette suggestion when mode=suggest and unlocked / empty source.
		if ( ! empty( $data['palette_suggestion'] ) && is_array( $data['palette_suggestion'] ) ) {
			$mode = (string) ( $settings['palette_mode'] ?? 'site' );
			$mem  = WebinoCRM_AI_Design_Memory::get();
			if ( 'suggest' === $mode && ( empty( $mem['source'] ) || empty( $mem['locked'] ) ) ) {
				WebinoCRM_AI_Design_Memory::apply_suggested_palette( $data['palette_suggestion'] );
			}
		}

		$tree  = WebinoCRM_AI_Elementor_Compiler::compile( $data, $page_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$saved = WebinoCRM_AI_Elementor::save_document( $page_id, $tree );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		WebinoCRM_AI_Design_Memory::lock_after_success();

		$focus   = (string) ( $data['focus_keyword'] ?? ( $data['seo']['focus_keyword'] ?? '' ) );
		$content = WebinoCRM_AI_Seo_Gate::flatten_page_blueprint( $data );
		WebinoCRM_AI_Seo_Gate::record_run( 'page', $page_id, $focus, $title, $content );

		return true;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function map_post_seo( $post_id ) {
		$id = (int) $post_id;
		return array(
			'title'                => (string) get_post_meta( $id, 'rank_math_title', true ),
			'description'          => (string) get_post_meta( $id, 'rank_math_description', true ),
			'focus_keyword'        => (string) get_post_meta( $id, 'rank_math_focus_keyword', true ),
			'canonical_url'        => (string) get_post_meta( $id, 'rank_math_canonical_url', true ),
			'breadcrumb_title'     => (string) get_post_meta( $id, 'rank_math_breadcrumb_title', true ),
			'pillar_content'       => 'on' === (string) get_post_meta( $id, 'rank_math_pillar_content', true ),
			'facebook_title'       => (string) get_post_meta( $id, 'rank_math_facebook_title', true ),
			'facebook_description' => (string) get_post_meta( $id, 'rank_math_facebook_description', true ),
			'facebook_image'       => (string) get_post_meta( $id, 'rank_math_facebook_image', true ),
			'twitter_title'        => (string) get_post_meta( $id, 'rank_math_twitter_title', true ),
			'twitter_description'  => (string) get_post_meta( $id, 'rank_math_twitter_description', true ),
			'twitter_image'        => (string) get_post_meta( $id, 'rank_math_twitter_image', true ),
			'twitter_card_type'    => (string) get_post_meta( $id, 'rank_math_twitter_card_type', true ) ?: 'summary_large_image',
			'schema_type'          => (string) get_post_meta( $id, 'rank_math_rich_snippet', true ) ?: 'article',
		);
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array<string,mixed>
	 */
	public static function map_term_seo( $term_id ) {
		$id = (int) $term_id;
		return array(
			'title'                => (string) get_term_meta( $id, 'rank_math_title', true ),
			'description'          => (string) get_term_meta( $id, 'rank_math_description', true ),
			'focus_keyword'        => (string) get_term_meta( $id, 'rank_math_focus_keyword', true ),
			'facebook_title'       => (string) get_term_meta( $id, 'rank_math_facebook_title', true ),
			'facebook_description' => (string) get_term_meta( $id, 'rank_math_facebook_description', true ),
			'schema_type'          => (string) get_term_meta( $id, 'rank_math_rich_snippet', true ) ?: 'collectionpage',
		);
	}

	/**
	 * @param array<string,mixed> $data Raw.
	 * @param string              $schema_default Default schema.
	 * @param string              $fallback_title Title.
	 * @return array<string,mixed>
	 */
	private static function normalize_seo( $data, $schema_default, $fallback_title ) {
		$seo = isset( $data['seo'] ) && is_array( $data['seo'] ) ? $data['seo'] : array();
		if ( empty( $seo['focus_keyword'] ) && ! empty( $data['focus_keyword'] ) ) {
			$seo['focus_keyword'] = $data['focus_keyword'];
		}
		if ( empty( $seo['title'] ) && ! empty( $data['seo_title'] ) ) {
			$seo['title'] = $data['seo_title'];
		}
		if ( empty( $seo['description'] ) && ! empty( $data['seo_description'] ) ) {
			$seo['description'] = $data['seo_description'];
		}
		if ( empty( $seo['title'] ) ) {
			$settings     = WebinoCRM_AI_Content_Settings::get();
			$seo['title'] = $fallback_title . (string) $settings['seo_sep'] . WebinoCRM_AI_Content_Settings::resolved_site_name( $settings );
		}
		if ( empty( $seo['schema_type'] ) ) {
			$seo['schema_type'] = $schema_default;
		}
		return $seo;
	}

	/**
	 * Apply AI coffee tasting profile when the module is on.
	 *
	 * @param int                 $product_id Product.
	 * @param array<string,mixed> $data Generated data.
	 * @return true|WP_Error
	 */
	private static function apply_coffee_profile( $product_id, $data ) {
		if ( ! WebinoCRM_AI_Content_Settings::coffee_enabled() ) {
			return true;
		}
		if ( empty( $data['coffee'] ) || ! is_array( $data['coffee'] ) ) {
			return true;
		}
		$on  = static function ( $field ) {
			return WebinoCRM_AI_Content_Settings::field_enabled( 'coffee', $field );
		};
		$c   = $data['coffee'];
		$in  = WebinoCRM_Coffee_Profile::get_profile( (int) $product_id );
		if ( $on( 'blend_arabica' ) && array_key_exists( 'blend_arabica', $c ) ) {
			$in['blend_arabica'] = $c['blend_arabica'];
		}
		if ( $on( 'blend_robusta' ) && array_key_exists( 'blend_robusta', $c ) ) {
			$in['blend_robusta'] = $c['blend_robusta'];
		}
		if ( $on( 'acidity' ) && isset( $c['acidity'] ) && is_array( $c['acidity'] ) ) {
			$in['acidity'] = $c['acidity'];
		}
		if ( $on( 'caffeine_mg' ) && array_key_exists( 'caffeine_mg', $c ) ) {
			$in['caffeine_mg'] = $c['caffeine_mg'];
		}
		if ( $on( 'bitterness' ) && array_key_exists( 'bitterness', $c ) ) {
			$in['bitterness'] = $c['bitterness'];
		}
		if ( $on( 'sweetness' ) && array_key_exists( 'sweetness', $c ) ) {
			$in['sweetness'] = $c['sweetness'];
		}
		if ( $on( 'body' ) && array_key_exists( 'body', $c ) ) {
			$in['body'] = $c['body'];
		}
		if ( $on( 'origin_ids' ) && isset( $c['origin_ids'] ) && is_array( $c['origin_ids'] ) ) {
			$in['origin_ids'] = $c['origin_ids'];
		}
		if ( $on( 'visible' ) && isset( $c['visible'] ) && is_array( $c['visible'] ) ) {
			$in['visible'] = $c['visible'];
		}
		$saved = WebinoCRM_Coffee_Profile::save_profile( (int) $product_id, $in );
		return is_wp_error( $saved ) ? $saved : true;
	}

	/**
	 * @param WC_Product          $p Product.
	 * @param array<int,mixed>    $attrs Attributes from AI.
	 * @param array<string,mixed> $opts Options including template attribute ids.
	 * @return void
	 */
	private static function apply_product_attributes( $p, $attrs, $opts ) {
		$objects   = array();
		$template  = isset( $opts['attribute_ids'] ) && is_array( $opts['attribute_ids'] ) ? array_map( 'intval', $opts['attribute_ids'] ) : array();
		$by_label  = array();

		foreach ( $attrs as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label   = sanitize_text_field( (string) ( $row['name'] ?? $row['label'] ?? '' ) );
			$options = isset( $row['options'] ) && is_array( $row['options'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $row['options'] ) ) ) : array();
			if ( '' === $label || ! $options ) {
				continue;
			}
			$by_label[ mb_strtolower( $label ) ] = $options;
		}

		if ( $template ) {
			foreach ( $template as $attr_id ) {
				$attr_id = (int) $attr_id;
				if ( $attr_id <= 0 || ! function_exists( 'wc_get_attribute' ) ) {
					continue;
				}
				$attr = wc_get_attribute( $attr_id );
				if ( ! $attr ) {
					continue;
				}
				$label = (string) $attr->name;
				$key   = mb_strtolower( $label );
				$optsv = $by_label[ $key ] ?? array();
				if ( ! $optsv ) {
					continue;
				}
				$taxonomy = wc_attribute_taxonomy_name_by_id( $attr_id );
				foreach ( $optsv as $opt_name ) {
					if ( ! term_exists( $opt_name, $taxonomy ) ) {
						wp_insert_term( $opt_name, $taxonomy );
					}
				}
				$attribute = new WC_Product_Attribute();
				$attribute->set_id( $attr_id );
				$attribute->set_name( $taxonomy );
				$attribute->set_options( $optsv );
				$attribute->set_visible( true );
				$attribute->set_variation( false );
				$objects[] = $attribute;
			}
		} else {
			foreach ( $by_label as $label => $optsv ) {
				$attribute = new WC_Product_Attribute();
				$attribute->set_id( 0 );
				$attribute->set_name( $label );
				$attribute->set_options( $optsv );
				$attribute->set_visible( true );
				$attribute->set_variation( false );
				$objects[] = $attribute;
			}
		}

		if ( $objects ) {
			$p->set_attributes( $objects );
		}
	}
}
