<?php
/**
 * Prompt builders for AI content jobs.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System + user prompts and JSON schemas.
 */
final class WebinoCRM_AI_Prompts {

	/**
	 * @return string
	 */
	public static function system_rules() {
		$s = WebinoCRM_AI_Content_Settings::get();
		$tpl = (string) ( $s['prompt_system'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$tpl  = $defs['prompt_system'];
		}
		return WebinoCRM_AI_Content_Settings::interpolate_prompt( $tpl, $s );
	}

	/**
	 * @param string $entity Entity.
	 * @return string
	 */
	public static function entity_instructions( $entity ) {
		$s    = WebinoCRM_AI_Content_Settings::get();
		$map  = array(
			'product'       => 'prompt_product',
			'product_cat'   => 'prompt_product_cat',
			'product_brand' => 'prompt_product_brand',
			'blog'          => 'prompt_blog',
			'blog_cat'      => 'prompt_blog_cat',
			'coffee'        => 'prompt_coffee',
			'page'          => 'prompt_page',
		);
		$key = isset( $map[ $entity ] ) ? $map[ $entity ] : '';
		$tpl = $key ? (string) ( $s[ $key ] ?? '' ) : '';
		if ( '' === trim( $tpl ) && $key ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$tpl  = (string) ( $defs[ $key ] ?? '' );
		}
		return WebinoCRM_AI_Content_Settings::interpolate_prompt( $tpl, $s );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function product_schema() {
		$full = array(
			'name'              => 'string',
			'slug'              => 'string-kebab',
			'short_description' => 'html-short',
			'description'       => 'html-long-with-h2-h3',
			'english_name'      => 'string',
			'tags'              => array( 'string' ),
			'focus_keyword'     => 'string',
			'seo'               => array(
				'title'                => 'string',
				'description'          => 'string',
				'focus_keyword'        => 'string',
				'breadcrumb_title'     => 'string',
				'facebook_title'       => 'string',
				'facebook_description' => 'string',
				'twitter_title'        => 'string',
				'twitter_description'  => 'string',
				'schema_type'          => 'product',
				'brand'                => 'string',
			),
			'faqs'              => array(
				array( 'question' => 'string', 'answer' => 'string' ),
			),
			'custom_labels'     => array(
				array( 'text' => 'string-short-shop-badge', 'color' => '#hex' ),
			),
			'ai_review_summary' => 'string',
			'attributes'        => array(
				array( 'name' => 'string', 'options' => array( 'string' ) ),
			),
			'related_product_ids' => array( 'int-from-related_products' ),
		);
		if ( WebinoCRM_AI_Content_Settings::coffee_enabled() ) {
			$full['coffee'] = array(
				'blend_arabica' => 'int-0-100',
				'blend_robusta' => 'int-0-100-sum-with-arabica-is-100',
				'acidity'       => (object) array( 'level_id' => 'int-within-scale' ),
				'caffeine_mg'   => 'int',
				'bitterness'    => 'int-within-scale',
				'sweetness'     => 'int-within-scale',
				'body'          => 'int-within-scale',
				'origin_ids'    => array( 'int-from-provided-origins' ),
				'visible'       => array(
					'blend'      => 'bool',
					'acidity'    => 'bool',
					'caffeine'   => 'bool',
					'bitterness' => 'bool',
					'sweetness'  => 'bool',
					'body'       => 'bool',
					'origin'     => 'bool',
				),
			);
		}
		return self::filter_schema( 'product', $full );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function blog_schema() {
		$full = array(
			'title'          => 'string',
			'slug'           => 'string-kebab',
			'excerpt'        => 'string',
			'content'        => 'html-long-with-h2-h3',
			'focus_keyword'  => 'string',
			'tags'           => array( 'string' ),
			'category_names' => array( 'string' ),
			'seo'            => array(
				'title'            => 'string',
				'description'      => 'string',
				'focus_keyword'    => 'string',
				'breadcrumb_title' => 'string',
				'schema_type'      => 'article',
			),
		);
		return self::filter_schema( 'blog', $full );
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @return array<string,mixed>
	 */
	public static function term_schema( $taxonomy = 'product_cat' ) {
		$entity = WebinoCRM_AI_Content_Settings::entity_for_type( $taxonomy );
		$full   = array(
			'description'   => 'html-long',
			'focus_keyword' => 'string',
			'seo'           => array(
				'title'         => 'string',
				'description'   => 'string',
				'focus_keyword' => 'string',
				'schema_type'   => 'collectionpage',
			),
		);
		return self::filter_schema( $entity, $full );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function categories_schema() {
		return array(
			'categories' => array(
				array(
					'name'        => 'string',
					'slug'        => 'string',
					'description' => 'string',
					'parent'      => 'string-or-empty',
					'children'    => array( 'string' ),
				),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function catalog_classify_schema() {
		return array(
			'items' => array(
				array(
					'product_id'      => 'int',
					'category_ids'    => array( 'int' ),
					'categories'      => array(
						array(
							'name'   => 'string',
							'parent' => 'string-or-empty',
							'path'   => array( 'string' ),
						),
					),
					'new_categories'  => array(
						array(
							'name'   => 'string',
							'parent' => 'string-or-empty',
						),
					),
					'brand_id'        => 'int-or-0',
					'brand'           => 'string',
				),
			),
			'glossary_brands' => array( 'string' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function title_rewrite_schema() {
		return array(
			'items' => array(
				array(
					'product_id'   => 'int',
					'product'      => 'string',
					'brand'        => 'string',
					'model'        => 'string',
					'feature'      => 'string-or-empty',
					'name'         => 'string',
				),
			),
			'glossary_product_types' => array( 'string' ),
			'glossary_brands'        => array( 'string' ),
		);
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function catalog_classify_user( $ctx ) {
		$s    = WebinoCRM_AI_Content_Settings::get();
		$task = (string) ( $s['prompt_catalog'] ?? '' );
		if ( '' === trim( $task ) ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$task = (string) ( $defs['prompt_catalog'] ?? '' );
		}
		$task = WebinoCRM_AI_Content_Settings::interpolate_prompt( $task, $s );
		return wp_json_encode(
			array(
				'task'               => $task,
				'assign_categories'  => ! empty( $s['catalog_assign_categories'] ),
				'assign_brands'      => ! empty( $s['catalog_assign_brands'] ),
				'create_missing'     => ! empty( $s['catalog_create_terms'] ),
				'category_tree'      => $ctx['category_tree'] ?? array(),
				'brands'             => $ctx['brands'] ?? array(),
				'products'           => $ctx['products'] ?? array(),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function title_rewrite_user( $ctx ) {
		$s    = WebinoCRM_AI_Content_Settings::get();
		$task = (string) ( $s['prompt_title'] ?? '' );
		if ( '' === trim( $task ) ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$task = (string) ( $defs['prompt_title'] ?? '' );
		}
		$task = WebinoCRM_AI_Content_Settings::interpolate_prompt( $task, $s );
		$pattern = (string) ( $s['title_pattern'] ?? '{product} {brand} مدل {model} {feature}' );
		if ( empty( $s['title_include_feature'] ) ) {
			$pattern = trim( str_replace( '{feature}', '', $pattern ) );
		}
		return wp_json_encode(
			array(
				'task'                 => $task,
				'pattern'              => $pattern,
				'brand_script'         => ( 'en' === ( $s['title_brand_script'] ?? 'fa' ) ) ? 'en' : 'fa',
				'include_feature'      => ! empty( $s['title_include_feature'] ),
				'glossary_product_types' => $ctx['glossary']['product_types'] ?? array(),
				'glossary_brands'      => $ctx['glossary']['brands'] ?? array(),
				'example'              => 'نگه دارنده پایه موبایل تسکو مدل 001',
				'products'             => $ctx['products'] ?? array(),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function attribute_template_schema() {
		return array(
			'attributes' => array(
				array(
					'label'   => 'string',
					'slug'    => 'string',
					'options' => array( 'string' ),
				),
			),
		);
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function product_user( $ctx ) {
		$s    = WebinoCRM_AI_Content_Settings::get();
		$faqs = WebinoCRM_AI_Content_Settings::field_spec( 'product', 'faqs' );
		$out  = array(
			'task'                  => self::entity_instructions( 'product' ),
			'product'               => $ctx,
			'min_description_words' => (int) $s['min_product_words'],
			'include_faqs'          => $faqs['enabled'] ? max( 1, $faqs['length'] ) : 0,
			'attribute_template'    => $ctx['attribute_template'] ?? array(),
			'related_products'      => $ctx['related'] ?? array(),
			'research_notes'        => $ctx['research_notes'] ?? '',
			'review_emojis'         => ! empty( $s['review_emojis'] ),
			'length_rules'          => WebinoCRM_AI_Content_Settings::length_instructions( 'product' ),
			'enabled_fields'        => array_keys( self::product_schema() ),
			'do_not_generate'       => array( 'video_url', 'video_cover_url', 'initial_stock_quantity', 'prices', 'images' ),
		);
		if ( ! empty( $ctx['coffee'] ) && WebinoCRM_AI_Content_Settings::coffee_enabled() ) {
			$out['coffee_task']         = self::entity_instructions( 'coffee' );
			$out['coffee']              = $ctx['coffee'];
			$out['coffee_length_rules'] = WebinoCRM_AI_Content_Settings::length_instructions( 'coffee' );
		}
		return wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function blog_user( $ctx ) {
		$s = WebinoCRM_AI_Content_Settings::get();
		return wp_json_encode(
			array(
				'task'          => self::entity_instructions( 'blog' ),
				'topic'         => $ctx['topic'] ?? '',
				'focus_keyword' => $ctx['focus_keyword'] ?? '',
				'secondary'     => $ctx['secondary_keywords'] ?? array(),
				'category'      => $ctx['category'] ?? '',
				'related'       => $ctx['related'] ?? array(),
				'min_words'     => (int) $s['min_blog_words'],
				'publish_as'    => $s['auto_publish'] ? $s['publish_status'] : 'draft',
				'length_rules'  => WebinoCRM_AI_Content_Settings::length_instructions( 'blog' ),
				'enabled_fields'=> array_keys( self::blog_schema() ),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function term_user( $ctx ) {
		$tax    = sanitize_key( (string) ( $ctx['taxonomy'] ?? 'product_cat' ) );
		$entity = WebinoCRM_AI_Content_Settings::entity_for_type( $tax );
		$s      = WebinoCRM_AI_Content_Settings::get();
		return wp_json_encode(
			array(
				'task'          => self::entity_instructions( $entity ),
				'term'          => $ctx,
				'min_words'     => (int) $s['min_term_words'],
				'focus_keyword' => $ctx['focus_keyword'] ?? ( $ctx['name'] ?? '' ),
				'length_rules'  => WebinoCRM_AI_Content_Settings::length_instructions( $entity ),
				'enabled_fields'=> array_keys( self::term_schema( $tax ) ),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * System prompt for page design (separate from product/blog system).
	 *
	 * @return string
	 */
	public static function page_system_rules() {
		$s   = WebinoCRM_AI_Content_Settings::get();
		$tpl = (string) ( $s['prompt_page_system'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$tpl  = (string) ( $defs['prompt_page_system'] ?? '' );
		}
		return WebinoCRM_AI_Content_Settings::interpolate_prompt( $tpl, $s );
	}

	/**
	 * Pass 1 — layout + widget skeleton (no long html/css).
	 *
	 * @return string
	 */
	public static function page_layout_system_rules() {
		$s   = WebinoCRM_AI_Content_Settings::get();
		$tpl = (string) ( $s['prompt_page_layout_system'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$tpl  = (string) ( $defs['prompt_page_layout_system'] ?? '' );
		}
		return WebinoCRM_AI_Content_Settings::interpolate_prompt( $tpl, $s );
	}

	/**
	 * Pass 2 — cinematic html/css only.
	 *
	 * @return string
	 */
	public static function page_visual_system_rules() {
		$s   = WebinoCRM_AI_Content_Settings::get();
		$tpl = (string) ( $s['prompt_page_visual_system'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			$defs = WebinoCRM_AI_Content_Settings::default_prompts();
			$tpl  = (string) ( $defs['prompt_page_visual_system'] ?? '' );
		}
		return WebinoCRM_AI_Content_Settings::interpolate_prompt( $tpl, $s );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function page_layout_schema() {
		$full = array(
			'title'               => 'string',
			'slug'                => 'string-kebab',
			'excerpt'             => 'string-short',
			'h1'                  => 'string',
			'focus_keyword'       => 'string',
			'seo_title'           => 'string',
			'seo_description'     => 'string',
			'page_settings'       => array(
				'full_width' => true,
			),
			'import_template_ids' => array( 0 ),
			'seo'                 => array(
				'title'         => 'string',
				'description'   => 'string',
				'focus_keyword' => 'string',
				'schema_type'   => 'webpage',
			),
			'sections'            => array(
				array(
					'archetype' => 'string-hint-hero|bento|stats|faq|cta|timeline|marquee',
					'variant'   => 'string',
					'layout'    => array(
						'columns'    => array( 50, 50 ),
						'min_height' => 560,
						'full_width' => true,
						'overlap'    => false,
						'background' => array(
							'type'     => 'gradient|classic|image|video',
							'color'    => '#hex',
							'color_b'  => '#hex',
							'image_id' => 0,
							'video_url'=> 'string',
							'overlay'  => 0.2,
						),
					),
					'blocks'    => array(
						array(
							'widget'   => 'from elementor_catalog.widgets[].name',
							'column'   => 0,
							'settings' => array(
								'title'       => 'string-short',
								'text'        => 'string-short',
								'url'         => 'string',
								'image_id'    => 0,
								'image_alt'   => 'string',
								'icon'        => 'string',
								'number'      => 'string',
								'items'       => array(),
							),
						),
					),
				),
			),
			'palette_suggestion'  => array(
				'primary'   => '#hex',
				'secondary' => '#hex',
				'accent'    => '#hex',
				'bg'        => '#hex',
				'surface'   => '#hex',
				'text'      => '#hex',
				'muted'     => '#hex',
			),
		);
		return self::filter_schema( 'page', $full );
	}

	/**
	 * Visual pass — html/css overlay on layout skeleton.
	 *
	 * @return array<string,mixed>
	 */
	public static function page_visual_schema() {
		return array(
			'page_css' => 'css-no-font-family',
			'sections' => array(
				array(
					'blocks' => array(
						array(
							'html' => 'html-for-html-widget-or-wrapper',
							'css'  => 'css-scoped-for-this-block',
						),
					),
				),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function page_schema() {
		$full = array(
			'title'            => 'string',
			'slug'             => 'string-kebab',
			'excerpt'          => 'string',
			'h1'               => 'string',
			'focus_keyword'    => 'string',
			'seo_title'        => 'string',
			'seo_description'  => 'string',
			'page_css'         => 'css-no-font-family',
			'page_settings'    => array(
				'full_width' => true,
			),
			'import_template_ids' => array( 0 ),
			'seo'              => array(
				'title'         => 'string',
				'description'   => 'string',
				'focus_keyword' => 'string',
				'schema_type'   => 'webpage',
			),
			'sections'         => array(
				array(
					'archetype' => 'string-hint-from-approved_archetypes',
					'variant'   => 'string',
					'layout'    => array(
						'columns'     => array( 50, 50 ),
						'min_height'  => 560,
						'full_width'  => true,
						'overlap'     => false,
						'background'  => array(
							'type'     => 'gradient|classic|image|video',
							'color'    => '#hex',
							'color_b'  => '#hex',
							'image_id' => 0,
							'overlay'  => 0.2,
						),
					),
					'content'   => array(
						'eyebrow'    => 'string',
						'heading'    => 'string',
						'subheading' => 'string',
						'text'       => 'string',
						'html'       => 'html-optional',
						'css'        => 'css-optional',
						'cta_text'   => 'string',
						'cta_url'    => 'string',
						'image_id'   => 0,
						'image_alt'  => 'string',
						'gallery_ids'=> array( 0 ),
						'items'      => array(
							array(
								'title'       => 'string',
								'text'        => 'string',
								'icon'        => 'fas-fa-icon',
								'number'      => 'string',
								'question'    => 'string',
								'answer'      => 'string',
								'name'        => 'string',
								'role'        => 'string',
							),
						),
					),
					'blocks'    => array(
						array(
							'widget'   => 'html|heading|button|image|counter|accordion|tabs|testimonial|video|icon-box|...',
							'column'   => 0,
							'html'     => 'html-for-html-widget',
							'css'      => 'css-scoped-for-this-block',
							'settings' => array(
								'title'     => 'string',
								'text'      => 'string',
								'url'       => 'string',
								'image_id'  => 0,
								'image_alt' => 'string',
								'icon'      => 'string',
								'number'    => 'string',
								'items'     => array(),
							),
						),
					),
				),
			),
			'palette_suggestion' => array(
				'primary'   => '#hex',
				'secondary' => '#hex',
				'accent'    => '#hex',
				'bg'        => '#hex',
				'surface'   => '#hex',
				'text'      => '#hex',
				'muted'     => '#hex',
			),
		);
		return self::filter_schema( 'page', $full );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function page_user( $ctx ) {
		return self::page_layout_user( $ctx );
	}

	/**
	 * Shared page context for layout and visual passes.
	 *
	 * @param array<string,mixed> $ctx Context.
	 * @return array<string,mixed>
	 */
	private static function page_context_base( $ctx ) {
		$s = WebinoCRM_AI_Content_Settings::get();
		return array(
			'task'                => self::entity_instructions( 'page' ),
			'page_prompt'         => (string) ( $ctx['page_prompt'] ?? '' ),
			'page'                => array(
				'id'    => (int) ( $ctx['page_id'] ?? 0 ),
				'title' => (string) ( $ctx['title'] ?? '' ),
				'slug'  => (string) ( $ctx['slug'] ?? '' ),
			),
			'site_description'    => (string) ( $s['site_description'] ?? '' ),
			'site_topic'          => (string) ( $s['site_topic'] ?? '' ),
			'palette_mode'        => (string) ( $s['palette_mode'] ?? 'site' ),
			'design_memory'       => $ctx['design_memory'] ?? WebinoCRM_AI_Design_Memory::for_prompt(),
			'approved_archetypes' => WebinoCRM_AI_Design_Memory::all_archetypes(),
			'elementor_catalog'   => class_exists( 'WebinoCRM_AI_Elementor_Catalog', false )
				? WebinoCRM_AI_Elementor_Catalog::for_prompt()
				: array(),
			'related_urls'        => $ctx['related_urls'] ?? array(),
			'min_words'           => (int) ( $s['min_page_words'] ?? 150 ),
			'focus_keyword'       => (string) ( $ctx['focus_keyword'] ?? '' ),
			'length_rules'        => WebinoCRM_AI_Content_Settings::length_instructions( 'page' ),
			'regenerate_hint'     => (string) ( $ctx['regenerate_hint'] ?? '' ),
		);
	}

	/**
	 * Pass 1 user payload.
	 *
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function page_layout_user( $ctx ) {
		$s    = WebinoCRM_AI_Content_Settings::get();
		$task = (string) ( $s['prompt_page_layout'] ?? '' );
		if ( '' === trim( $task ) ) {
			$task = (string) ( WebinoCRM_AI_Content_Settings::default_prompts()['prompt_page_layout'] ?? self::entity_instructions( 'page' ) );
		}
		$task = WebinoCRM_AI_Content_Settings::interpolate_prompt( $task, $s );
		return wp_json_encode(
			array_merge(
				self::page_context_base( $ctx ),
				array(
					'task'             => $task,
					'pass'             => 'layout',
					'enabled_fields'   => array_keys( self::page_layout_schema() ),
					'rules'            => array(
						'never_set_fonts'             => true,
						'blocks_required'             => true,
						'no_long_html_css_in_pass1'   => true,
						'min_sections'                => 5,
						'reuse_design_memory_palette' => true,
						'pick_widgets_from_catalog'   => true,
					),
				)
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * Pass 2 user payload.
	 *
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function page_visual_user( $ctx ) {
		$s    = WebinoCRM_AI_Content_Settings::get();
		$task = (string) ( $s['prompt_page_visual'] ?? '' );
		if ( '' === trim( $task ) ) {
			$task = (string) ( WebinoCRM_AI_Content_Settings::default_prompts()['prompt_page_visual'] ?? '' );
		}
		$task = WebinoCRM_AI_Content_Settings::interpolate_prompt( $task, $s );
		return wp_json_encode(
			array_merge(
				self::page_context_base( $ctx ),
				array(
					'task'             => $task,
					'pass'             => 'visual',
					'page_layout'      => $ctx['page_layout'] ?? array(),
					'enabled_fields'   => array_keys( self::page_visual_schema() ),
					'rules'            => array(
						'never_set_fonts'            => true,
						'do_not_change_copy'         => true,
						'fill_html_css_only'         => true,
						'html_css_cinematic_required'=> true,
						'reuse_design_memory_palette'=> true,
					),
				)
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * Merge layout skeleton with visual html/css overlay.
	 *
	 * @param array<string,mixed> $layout Layout pass output.
	 * @param array<string,mixed> $visual Visual pass output.
	 * @return array<string,mixed>
	 */
	public static function merge_page_blueprint( $layout, $visual ) {
		if ( ! is_array( $layout ) ) {
			$layout = array();
		}
		if ( ! is_array( $visual ) ) {
			$visual = array();
		}
		$merged = $layout;
		if ( ! empty( $visual['page_css'] ) ) {
			$merged['page_css'] = (string) $visual['page_css'];
		}
		$layout_sections = isset( $layout['sections'] ) && is_array( $layout['sections'] ) ? $layout['sections'] : array();
		$visual_sections = isset( $visual['sections'] ) && is_array( $visual['sections'] ) ? $visual['sections'] : array();
		$sections        = array();
		foreach ( $layout_sections as $si => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$vsection = isset( $visual_sections[ $si ] ) && is_array( $visual_sections[ $si ] ) ? $visual_sections[ $si ] : array();
			$blocks   = isset( $section['blocks'] ) && is_array( $section['blocks'] ) ? $section['blocks'] : array();
			$vblocks  = isset( $vsection['blocks'] ) && is_array( $vsection['blocks'] ) ? $vsection['blocks'] : array();
			$merged_blocks = array();
			foreach ( $blocks as $bi => $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}
				$vblock = isset( $vblocks[ $bi ] ) && is_array( $vblocks[ $bi ] ) ? $vblocks[ $bi ] : array();
				if ( ! empty( $vblock['html'] ) ) {
					$block['html'] = (string) $vblock['html'];
				}
				if ( ! empty( $vblock['css'] ) ) {
					$block['css'] = (string) $vblock['css'];
				}
				$merged_blocks[] = $block;
			}
			$section['blocks'] = $merged_blocks;
			$sections[]        = $section;
		}
		$merged['sections'] = $sections;
		return $merged;
	}

	/**
	 * @param string              $kind blog|product.
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function suggest_categories_user( $kind, $ctx ) {
		$settings = WebinoCRM_AI_Content_Settings::get();
		$task     = 'blog' === $kind
			? 'Suggest blog category tree for this site niche.'
			: 'Suggest product categories for uncategorized products.';
		return wp_json_encode(
			array(
				'task'            => $task,
				'site_topic'      => $settings['site_topic'],
				'existing'        => $ctx['existing'] ?? array(),
				'product_samples' => $ctx['product_samples'] ?? array(),
				'regenerate_hint' => (string) ( $ctx['regenerate_hint'] ?? '' ),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 * @return string
	 */
	public static function attr_template_user( $ctx ) {
		return wp_json_encode(
			array(
				'task'            => 'Propose WooCommerce global attributes for this product category. Fixed attribute set for all products in the category.',
				'category'        => $ctx['category'] ?? array(),
				'sample_products' => $ctx['sample_products'] ?? array(),
				'regenerate_hint' => (string) ( $ctx['regenerate_hint'] ?? '' ),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
	}

	/**
	 * Drop disabled fields from a schema.
	 *
	 * @param string              $entity Entity.
	 * @param array<string,mixed> $schema Schema.
	 * @return array<string,mixed>
	 */
	private static function filter_schema( $entity, $schema ) {
		$out = array();
		foreach ( $schema as $key => $shape ) {
			$field = $key;
			if ( 'focus_keyword' === $key ) {
				$field = 'seo';
			}
			if ( ! WebinoCRM_AI_Content_Settings::field_enabled( $entity, $field ) ) {
				continue;
			}
			$out[ $key ] = $shape;
		}
		return $out ? $out : $schema;
	}
}
