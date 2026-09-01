<?php
/**
 * AI Content settings.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site profile, providers, quotas, prompts, per-field switches.
 */
final class WebinoCRM_AI_Content_Settings {

	const OPTION = 'webino_dashboard_ai_content_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$lang = get_locale();
		if ( 0 === strpos( (string) $lang, 'fa' ) ) {
			$lang = 'fa';
		} else {
			$lang = 'en';
		}

		$prompts = self::default_prompts();

		return array_merge(
			array(
				'default_provider'       => 'grok',
				'fallback_order'         => array( 'grok', 'gemini', 'openai', 'gapgpt' ),
				'grok_api_key'           => '',
				'gemini_api_key'         => '',
				'openai_api_key'         => '',
				'gapgpt_api_key'         => '',
				'grok_model'             => 'grok-2-latest',
				'gemini_model'           => 'gemini-2.0-flash',
				'openai_model'           => 'gpt-4o-mini',
				'gapgpt_model'           => 'gpt-5.6-terra',
				'site_name'              => '',
				'site_topic'             => (string) get_option( 'blogdescription', '' ),
				'site_description'       => '',
				'palette_mode'           => 'site',
				'tone'                   => 'professional, friendly',
				'tones'                  => self::default_tones(),
				'language'               => $lang,
				'seo_sep'                => ' - ',
				'temperature'            => 0.55,
				'max_tokens'             => 0,
				'page_provider'          => '',
				'page_model'             => '',
				'page_max_tokens'        => 64000,
				'web_research'           => true,
				'review_emojis'          => true,
				'daily_blog_quota'       => 1,
				'daily_product_quota'    => 5,
				'auto_publish'           => false,
				'publish_status'         => 'draft',
				'min_blog_words'         => 900,
				'min_product_words'      => 400,
				'min_term_words'         => 250,
				'min_page_words'         => 150,
				'require_site_name'      => true,
				'internal_links_min'     => 2,
				'internal_links_max'     => 4,
				'max_regenerate'         => 0,
				'similarity_threshold'   => 0.72,
				'usd_to_toman'           => 100000,
				'gapgpt_rates'           => array(),
				'gapgpt_rates_updated_at'=> '2026-08-23',
				'enabled'                => true,
				'do_product'             => true,
				'do_product_cat'         => true,
				'do_product_brand'       => true,
				'do_blog'                => true,
				'do_blog_cat'            => true,
				'do_page'                => true,
				'do_coffee'              => true,
				'catalog_assign_categories' => true,
				'catalog_assign_brands'     => true,
				'catalog_create_terms'      => true,
				'catalog_only_missing'      => true,
				'title_enabled'             => true,
				'title_pattern'             => '{product} {brand} مدل {model} {feature}',
				'title_brand_script'        => 'fa',
				'title_include_feature'     => true,
				'fields'                    => self::field_defaults(),
			),
			$prompts
		);
	}

	/**
	 * @return list<string>
	 */
	public static function tone_slugs() {
		return array(
			'professional',
			'friendly',
			'expert',
			'educational',
			'sales',
			'storytelling',
			'luxury',
			'casual',
			'enthusiastic',
		);
	}

	/**
	 * @return array<string,bool>
	 */
	public static function default_tones() {
		$out = array();
		foreach ( self::tone_slugs() as $slug ) {
			$out[ $slug ] = in_array( $slug, array( 'professional', 'friendly' ), true );
		}
		return $out;
	}

	/**
	 * Previous built-in prompts (for one-time upgrade when the site never customized them).
	 *
	 * @return array<string,string|array<int,string>>
	 */
	public static function legacy_default_prompts() {
		return array(
			'prompt_system'  => "You are an expert SEO content writer for an e-commerce WordPress/WooCommerce site.\nWrite in {language}. Tone: {tone}.\nSite name: \"{site_name}\". Site topic/niche: \"{site_topic}\".\nAlways mention the site name naturally at least once near the introduction and once in the conclusion.\nFollow Rank Math and Google helpful-content best practices:\n- Primary focus keyword in title, slug, first ~100 words, at least one H2, SEO title, meta description.\n- Keyword density roughly 0.5%–1.5%; never stuff.\n- SEO title length ~10–60 chars; meta description ~70–160 chars.\n- Prefer SEO title pattern: %title%{seo_sep}%sitename% or a crafted title that includes the site name when natural.\n- Use clear H2/H3 hierarchy. No medical/legal claims without sources. No fluff.\n- Add 2–4 internal link placeholders as HTML anchors with paths like /product/... or /magazine/... when related items are provided.\n- Return ONLY valid JSON. No markdown fences.",
			'prompt_product' => array(
				'Complete WooCommerce product content for all enabled SEO and marketing fields. Keep existing factual data (SKU, brand, price cues). Fill empty fields. If attribute_template is set, only fill those attribute names with values; do not invent new attribute names.',
				'Complete WooCommerce product content for all enabled SEO and marketing fields. Keep existing factual data (SKU, brand, price cues, current attributes). Fill empty fields. If attribute_template is set, only fill those attribute names; prefer provided option lists; do not invent new attribute names. Structure the review/description with H2/H3 (intro, tasting or use, comparison when related products exist, who it is for, then a final summary with how to order from {site_name}).',
			),
			'prompt_page_system' => array(
				"You are an elite brand web designer + SEO copywriter for WordPress/Elementor.\nWrite in {language}. Tone: {tone}.\nSite name: \"{site_name}\". Niche: \"{site_topic}\". Site domain description: \"{site_description}\".\nYou output a PageBlueprint JSON only (never raw Elementor JSON).\nDesign rules:\n- Extraordinary, innovative, brand-aligned layouts using ONLY approved archetypes.\n- Reuse the provided design_memory palette on every page. Never invent unrelated colors.\n- NEVER specify fonts or font_family — site theme fonts apply.\n- Sections must feel cinematic: asymmetric splits, bento grids, full-bleed CTAs, timelines, stats, FAQ — not plain stacked text.\n- Mention the site name naturally at least once in visible copy.\nSEO rules (Rank Math / helpful content):\n- focus_keyword in title, slug, h1, first section copy, seo_title, seo_description.\n- SEO title ~10–60 chars; meta description ~70–160 chars.\n- Clear H1 once; H2/H3 hierarchy inside section texts/html.\n- Return ONLY valid JSON. No markdown fences.",
			),
			'prompt_page' => array(
				'Design one landing/marketing page for Elementor. Follow the page_prompt closely for content goals. Use 4–7 sections from approved_archetypes. Fill rich Persian/English copy matching site_description. Include internal link CTAs when related_urls are provided. If palette_mode is suggest and design_memory is empty, also return palette_suggestion with hex colors for primary,secondary,accent,bg,surface,text,muted.',
			),
		);
	}

	/**
	 * Default editable prompts (placeholders interpolated at generate time).
	 *
	 * @return array<string,string>
	 */
	public static function default_prompts() {
		return array(
			'prompt_system' => "You are an expert SEO content writer for an e-commerce WordPress/WooCommerce site.\nWrite in {language}. Tone: {tone}.\nSite name: \"{site_name}\". Site topic/niche: \"{site_topic}\".\nAlways mention the site name naturally at least once near the introduction and once in the conclusion.\nNever invent product specifications from your own training data. Use only facts in the product context and research_notes. If a spec is unknown (blend ratios, roast, caffeine, origin, weight, etc.), omit it or leave the field empty — do not guess.\n{emoji_policy}\nFollow Rank Math and Google helpful-content best practices:\n- Primary focus keyword in title, slug, first ~100 words, at least one H2, SEO title, meta description.\n- Keyword density roughly 0.5%–1.5%; never stuff.\n- SEO title length ~10–60 chars; meta description ~70–160 chars.\n- Prefer SEO title pattern: %title%{seo_sep}%sitename% or a crafted title that includes the site name when natural.\n- Use clear H2/H3 hierarchy. No medical/legal claims without sources. No fluff.\n- When related_products are provided, insert {internal_links_min}–{internal_links_max} internal HTML anchors using ONLY those name/url pairs. Do not invent paths.\n- End the long description with an H2 summary that recaps the product and explains how to order from \"{site_name}\" on this product page (choose options such as weight/grind/roast if present, add to cart, and checkout).\n- Return ONLY valid JSON. No markdown fences.",
			'prompt_product' => 'Complete WooCommerce product content for all enabled SEO and marketing fields. Keep existing factual data (SKU, brand, price cues, current attributes). Fill empty fields. If attribute_template is set, only fill those attribute names; do not invent new attribute names. For each value: if one of existing_options exactly matches the product fact, reuse that exact text; otherwise create the correct new value. Never pick a nearby or wrong existing option (for example do not use a blend value for single-origin robusta). If the fact is unknown, leave that attribute empty. Structure the review/description with H2/H3 (intro, tasting or use, comparison when related products exist, who it is for, then a final summary with how to order from {site_name}).',
			'prompt_product_cat' => 'Write archive/landing content for a WooCommerce product category. Be specific to the category and site niche.',
			'prompt_product_brand' => 'Write archive/landing content for a product brand. Cover brand story, range, and why to buy from this site.',
			'prompt_blog' => 'Write a helpful, original blog post for this site niche. Use H2/H3, cover the topic thoroughly, and include the focus keyword naturally.',
			'prompt_blog_cat' => 'Write archive/landing content for a blog category. Explain what readers will find in this category.',
			'prompt_coffee' => 'Fill the coffee tasting profile. blend_arabica + blend_robusta must sum to 100 (use 0/0 only if this is not coffee). Pick origin_ids only from the provided origin list. Keep scale fields inside scale_min/scale_max. caffeine_mg must not exceed caffeine_max. Set visible flags for sections that have meaningful values. Do not invent origins or change pack weight.',
			'prompt_catalog' => 'Assign WooCommerce product_cat and product_brand using the existing site tree. Prefer existing category ids/paths. Create a new category only when nothing in the tree fits, and always attach it under a real parent when possible. Return every selected leaf AND its parents conceptually (server will add ancestors). Prefer existing brand names; create a brand only if it is genuinely missing. One primary brand per product.',
			'prompt_title' => 'Rewrite each product title to match the pattern and be consistent across the catalog. Reuse glossary product_type phrases exactly (never mix synonyms like holder vs phone stand). Write all brand names in the requested script (all Persian or all English). Feature/size is optional. Keep model codes as-is. Return JSON only.',
			'prompt_page_system' => "You are an elite brand art director + SEO copywriter for WordPress/Elementor.\nWrite in {language}. Tone: {tone}.\nSite name: \"{site_name}\". Niche: \"{site_topic}\". Site domain: \"{site_description}\".\nOutput a Visual PageBlueprint JSON only (never raw Elementor _elementor_data).\nThis is a MARKETING PAGE, not an article. Copy lives inside cinematic layouts.\nDesign rules:\n- Every section MUST include blocks[] with real Elementor widget names from the catalog.\n- At least one block per major section MUST be widget=html with substantial HTML+CSS (glass hero, CSS-grid bento, real timeline, marquee, overlapping cards, full-bleed CTA). CSS may be long.\n- Also use native widgets: heading, button, image, counter, accordion, tabs, testimonial, video, image-carousel, star-rating, icon-box, spacer, divider.\n- page_css: scoped visual identity (gradients, motion, radius) using design_memory hex colors. NEVER font-family.\n- layout: full-width, min_height, overlays, column splits, overlap. Not a single text column.\n- Reuse design_memory palette. Import local templates only via import_template_ids from the catalog.\n- Mention the site name naturally once in visible copy.\nSEO: focus_keyword in title, slug, h1, first hero copy, seo_title, seo_description. SEO title ~10–60 chars; meta ~70–160. One H1. Return ONLY valid JSON.",
			'prompt_page' => 'Design one extraordinary landing page. Follow page_prompt. 5–8 sections. Required mix: cinematic HTML hero, bento/grid, stats counters, FAQ accordion, full-bleed CTA, plus images/video when media exists. Fill blocks[].html and blocks[].css for visual identity. Include internal CTAs from related_urls. If palette_mode is suggest and design_memory is empty, also return palette_suggestion (primary,secondary,accent,bg,surface,text,muted).',
			'prompt_page_layout_system' => "You are an elite brand art director for WordPress/Elementor.\nWrite in {language}. Tone: {tone}.\nSite name: \"{site_name}\". Niche: \"{site_topic}\". Site domain: \"{site_description}\".\nPass 1 — LAYOUT ONLY. Output PageLayout JSON (never raw Elementor _elementor_data).\nThis is a MARKETING PAGE, not an article. Short punchy copy inside widget settings only.\nRules:\n- Minimum 5–8 sections: full-width hero, bento/grid, stats, FAQ, full-bleed CTA, plus media when available.\n- Every section MUST have blocks[] with widget names from elementor_catalog.widgets (use role + when_to_use).\n- At least one html-capable block (widget=html) in hero AND in final CTA section.\n- layout: full_width, min_height, column splits, overlap flags, gradient/image/video backgrounds.\n- Do NOT output long html or css in pass 1 — settings copy only.\n- Reuse design_memory palette tokens in background colors.\n- Mention site name once in hero settings copy.\nSEO (light): focus_keyword in title, slug, h1, seo.title, seo.description. Return ONLY valid JSON.",
			'prompt_page_layout' => 'Design the page skeleton for page_prompt. Pick widgets from the catalog. 5–8 sections with diverse archetypes. Hero + CTA must include widget=html blocks (empty html ok). Stats use counter widgets. FAQ uses accordion. Include related_urls as button urls where natural. If palette_mode is suggest and design_memory is empty, return palette_suggestion.',
			'prompt_page_visual_system' => "You are a cinematic web visual designer for WordPress/Elementor html widgets.\nWrite in {language}. Tone: {tone}.\nSite name: \"{site_name}\". Niche: \"{site_topic}\".\nPass 2 — VISUAL IDENTITY ONLY. You receive page_layout from pass 1.\nFill blocks[].html and blocks[].css + page_css. Do NOT change widget choices, column indices, or marketing copy except tiny hierarchy tweaks.\nDesign language: glassmorphism, gradients, CSS Grid bento, real timelines, marquees, overlapping cards, full-bleed CTAs.\nUse design_memory hex colors in CSS. NEVER font-family.\nScope classes with the page scope hint in rules. Return ONLY valid JSON.",
			'prompt_page_visual' => 'Add cinematic HTML+CSS to the provided page_layout. Every html widget block needs substantial html+css. Non-html blocks may get wrapper html if needed. page_css for global motion/gradients. Keep copy from pass 1. No articles — visual marketing only.',
		);
	}

	/**
	 * @return array<string,array<string,array{enabled:bool,length:int,unit:string}>>
	 */
	public static function field_defaults() {
		$sw = array( 'enabled' => true, 'length' => 0, 'unit' => 'words' );
		return array(
			'product'       => array(
				'name'              => $sw,
				'slug'              => $sw,
				'english_name'      => $sw,
				'short_description' => array( 'enabled' => true, 'length' => 2, 'unit' => 'paragraphs' ),
				'description'       => array( 'enabled' => true, 'length' => 400, 'unit' => 'words' ),
				'ai_review_summary' => array( 'enabled' => true, 'length' => 2000, 'unit' => 'words' ),
				'faqs'              => array( 'enabled' => true, 'length' => 4, 'unit' => 'count' ),
				'custom_labels'     => array( 'enabled' => true, 'length' => 3, 'unit' => 'count' ),
				'attributes'        => $sw,
				'tags'              => $sw,
				'seo'               => $sw,
			),
			'product_cat'   => array(
				'description' => array( 'enabled' => true, 'length' => 250, 'unit' => 'words' ),
				'seo'         => $sw,
			),
			'product_brand' => array(
				'description' => array( 'enabled' => true, 'length' => 250, 'unit' => 'words' ),
				'seo'         => $sw,
			),
			'blog'          => array(
				'title'   => $sw,
				'slug'    => $sw,
				'excerpt' => array( 'enabled' => true, 'length' => 2, 'unit' => 'paragraphs' ),
				'content' => array( 'enabled' => true, 'length' => 900, 'unit' => 'words' ),
				'tags'    => $sw,
				'seo'     => $sw,
			),
			'blog_cat'      => array(
				'description' => array( 'enabled' => true, 'length' => 250, 'unit' => 'words' ),
				'seo'         => $sw,
			),
			'coffee'        => array(
				'blend_arabica'  => $sw,
				'blend_robusta'  => $sw,
				'acidity'        => $sw,
				'caffeine_mg'    => $sw,
				'bitterness'     => $sw,
				'sweetness'      => $sw,
				'body'           => $sw,
				'origin_ids'     => $sw,
				'visible'        => $sw,
			),
			'page'          => array(
				'title'   => $sw,
				'slug'    => $sw,
				'excerpt' => array( 'enabled' => true, 'length' => 2, 'unit' => 'paragraphs' ),
				'content' => array( 'enabled' => true, 'length' => 150, 'unit' => 'words' ),
				'seo'     => $sw,
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$defaults = self::defaults();
		$merged   = array_merge( $defaults, $stored );
		$merged['fields'] = self::merge_fields( $defaults['fields'], isset( $stored['fields'] ) && is_array( $stored['fields'] ) ? $stored['fields'] : array() );

		$prompt_defaults = self::default_prompts();
		$legacy_prompts  = self::legacy_default_prompts();
		foreach ( $prompt_defaults as $pk => $pv ) {
			$current = (string) ( $merged[ $pk ] ?? '' );
			$legacy  = isset( $legacy_prompts[ $pk ] ) ? (array) $legacy_prompts[ $pk ] : array();
			if ( '' === trim( $current ) || in_array( $current, $legacy, true ) ) {
				$merged[ $pk ] = $pv;
			}
		}

		$merged['tones'] = self::sanitize_tones(
			isset( $stored['tones'] ) && is_array( $stored['tones'] )
				? $stored['tones']
				: self::tones_from_legacy_string( (string) ( $merged['tone'] ?? '' ) )
		);
		$merged['tone']          = self::resolved_tone( $merged );
		$merged['web_research']  = array_key_exists( 'web_research', $stored ) ? ! empty( $stored['web_research'] ) : true;
		$merged['review_emojis'] = array_key_exists( 'review_emojis', $stored ) ? ! empty( $stored['review_emojis'] ) : true;

		foreach ( array( 'grok_api_key', 'gemini_api_key', 'openai_api_key', 'gapgpt_api_key' ) as $k ) {
			if ( ! empty( $merged[ $k ] ) && 0 === strpos( (string) $merged[ $k ], 'enc:' ) ) {
				$merged[ $k ] = WebinoCRM_AI_Content_Crypto::decrypt( substr( (string) $merged[ $k ], 4 ) );
			}
		}

		$merged['min_product_words'] = self::derived_words( $merged, 'product', 'description', 400 );
		$merged['min_blog_words']    = self::derived_words( $merged, 'blog', 'content', 900 );
		$merged['min_term_words']    = self::derived_words( $merged, 'product_cat', 'description', 250 );
		$merged['min_page_words']    = self::derived_words( $merged, 'page', 'content', 150 );

		return $merged;
	}

	/**
	 * Settings safe for REST (masked keys).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_public() {
		$s = self::get();
		$s['grok_api_key_masked']    = WebinoCRM_AI_Content_Crypto::mask( (string) $s['grok_api_key'] );
		$s['gemini_api_key_masked']  = WebinoCRM_AI_Content_Crypto::mask( (string) $s['gemini_api_key'] );
		$s['openai_api_key_masked']  = WebinoCRM_AI_Content_Crypto::mask( (string) $s['openai_api_key'] );
		$s['gapgpt_api_key_masked']  = WebinoCRM_AI_Content_Crypto::mask( (string) $s['gapgpt_api_key'] );
		$s['has_grok_key']           = '' !== trim( (string) $s['grok_api_key'] );
		$s['has_gemini_key']         = '' !== trim( (string) $s['gemini_api_key'] );
		$s['has_openai_key']         = '' !== trim( (string) $s['openai_api_key'] );
		$s['has_gapgpt_key']         = '' !== trim( (string) $s['gapgpt_api_key'] );
		unset( $s['grok_api_key'], $s['gemini_api_key'], $s['openai_api_key'], $s['gapgpt_api_key'] );
		$s['site_name']       = self::resolved_site_name( $s );
		$s['prompt_defaults'] = self::default_prompts();
		$s['coffee_module']   = self::coffee_module_active();
		$s['queue_paused']    = class_exists( 'WebinoCRM_AI_Queue', false ) && WebinoCRM_AI_Queue::is_paused();
		return $s;
	}

	/**
	 * @param array<string,mixed>|null $settings Settings.
	 * @return string
	 */
	public static function resolved_site_name( $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = self::get();
		}
		$name = trim( (string) ( $settings['site_name'] ?? '' ) );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * @return bool
	 */
	public static function has_any_provider_key() {
		$s = self::get();
		foreach ( array( 'grok_api_key', 'gemini_api_key', 'openai_api_key', 'gapgpt_api_key' ) as $k ) {
			if ( '' !== trim( (string) ( $s[ $k ] ?? '' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public static function coffee_module_active() {
		return class_exists( 'WebinoCRM_Coffee_Profile', false );
	}

	/**
	 * @return bool
	 */
	public static function coffee_enabled() {
		return self::coffee_module_active() && self::entity_enabled( 'coffee' );
	}

	/**
	 * @param string $entity product|product_cat|product_brand|blog|blog_cat.
	 * @return bool
	 */
	public static function entity_enabled( $entity ) {
		$s   = self::get();
		$key = 'do_' . sanitize_key( $entity );
		return ! empty( $s[ $key ] );
	}

	/**
	 * @param string $entity Entity.
	 * @return true|WP_Error
	 */
	public static function assert_entity( $entity ) {
		if ( self::entity_enabled( $entity ) ) {
			return true;
		}
		return new WP_Error(
			'ai_entity_off',
			__( 'This AI content type is turned off in settings.', 'webinocrm' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * @param string $entity Entity.
	 * @param string $field Field.
	 * @return bool
	 */
	public static function field_enabled( $entity, $field ) {
		if ( ! self::entity_enabled( $entity ) ) {
			return false;
		}
		$s = self::get();
		if ( ! isset( $s['fields'][ $entity ][ $field ] ) ) {
			return true;
		}
		return ! empty( $s['fields'][ $entity ][ $field ]['enabled'] );
	}

	/**
	 * @param string $entity Entity.
	 * @param string $field Field.
	 * @return array{enabled:bool,length:int,unit:string}
	 */
	public static function field_spec( $entity, $field ) {
		$s    = self::get();
		$spec = isset( $s['fields'][ $entity ][ $field ] ) && is_array( $s['fields'][ $entity ][ $field ] )
			? $s['fields'][ $entity ][ $field ]
			: array( 'enabled' => true, 'length' => 0, 'unit' => 'words' );
		return array(
			'enabled' => ! empty( $spec['enabled'] ),
			'length'  => max( 0, (int) ( $spec['length'] ?? 0 ) ),
			'unit'    => sanitize_key( (string) ( $spec['unit'] ?? 'words' ) ),
		);
	}

	/**
	 * Length instructions for enabled fields with a target.
	 *
	 * @param string $entity Entity.
	 * @return array<string,string>
	 */
	public static function length_instructions( $entity ) {
		$s    = self::get();
		$out  = array();
		$rows = isset( $s['fields'][ $entity ] ) && is_array( $s['fields'][ $entity ] ) ? $s['fields'][ $entity ] : array();
		foreach ( $rows as $field => $spec ) {
			if ( ! is_array( $spec ) || empty( $spec['enabled'] ) ) {
				continue;
			}
			$len  = max( 0, (int) ( $spec['length'] ?? 0 ) );
			$unit = sanitize_key( (string) ( $spec['unit'] ?? 'words' ) );
			if ( $len < 1 ) {
				continue;
			}
			if ( 'paragraphs' === $unit ) {
				$out[ $field ] = sprintf( 'Write %s as %d paragraphs.', $field, $len );
			} elseif ( 'count' === $unit ) {
				$out[ $field ] = sprintf( 'Include exactly %d items for %s.', $len, $field );
			} elseif ( 'page' === $entity && 'content' === $field ) {
				$out[ $field ] = sprintf(
					'Short marketing copy inside blocks and html widgets (~%d words total across the page). Not an article or long-form text column.',
					$len
				);
			} else {
				$out[ $field ] = sprintf( 'Write %s with at least %d words.', $field, $len );
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function resolved_tone( $settings ) {
		$tones = self::sanitize_tones( isset( $settings['tones'] ) && is_array( $settings['tones'] ) ? $settings['tones'] : array() );
		$on    = array();
		foreach ( self::tone_slugs() as $slug ) {
			if ( ! empty( $tones[ $slug ] ) ) {
				$on[] = $slug;
			}
		}
		return $on ? implode( ', ', $on ) : 'professional, friendly';
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function emoji_policy_text( $settings ) {
		if ( ! empty( $settings['review_emojis'] ) ) {
			return 'Use one relevant emoji in every sentence or paragraph of the product description and review body so the text is easy on the eyes. Never in SEO title, slug, or meta description.';
		}
		return 'Do not use any emojis.';
	}

	/**
	 * @param array<string,mixed> $input Tones map.
	 * @return array<string,bool>
	 */
	public static function sanitize_tones( $input ) {
		$out = self::default_tones();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$any = false;
		foreach ( self::tone_slugs() as $slug ) {
			if ( array_key_exists( $slug, $input ) ) {
				$out[ $slug ] = (bool) $input[ $slug ];
			}
			if ( $out[ $slug ] ) {
				$any = true;
			}
		}
		if ( ! $any ) {
			$out['professional'] = true;
			$out['friendly']     = true;
		}
		return $out;
	}

	/**
	 * @param string $legacy Free-text tone.
	 * @return array<string,bool>
	 */
	public static function tones_from_legacy_string( $legacy ) {
		$out  = self::default_tones();
		$text = strtolower( str_replace( array( '-', '_' ), ' ', (string) $legacy ) );
		$any  = false;
		foreach ( self::tone_slugs() as $slug ) {
			$on = false !== strpos( $text, $slug );
			$out[ $slug ] = $on;
			if ( $on ) {
				$any = true;
			}
		}
		if ( ! $any ) {
			$out['professional'] = true;
			$out['friendly']     = true;
		}
		return $out;
	}

	/**
	 * @param string              $template Prompt.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function interpolate_prompt( $template, $settings ) {
		$lang_code = 'fa' === ( $settings['language'] ?? 'fa' ) ? 'fa' : 'en';
		$lang      = 'fa' === $lang_code ? 'Persian (Farsi)' : 'English';
		$min_links = max( 0, (int) ( $settings['internal_links_min'] ?? 2 ) );
		$max_links = max( $min_links, (int) ( $settings['internal_links_max'] ?? 4 ) );
		$map       = array(
			'{site_name}'          => self::resolved_site_name( $settings ),
			'{language}'           => $lang,
			'{tone}'               => self::resolved_tone( $settings ),
			'{site_topic}'         => (string) ( $settings['site_topic'] ?? '' ),
			'{site_description}'   => (string) ( $settings['site_description'] ?? '' ),
			'{seo_sep}'            => (string) ( $settings['seo_sep'] ?? ' - ' ),
			'{internal_links_min}' => (string) $min_links,
			'{internal_links_max}' => (string) $max_links,
			'{emoji_policy}'       => self::emoji_policy_text( $settings ),
		);
		return strtr( (string) $template, $map );
	}

	/**
	 * Map REST/job type to settings entity.
	 *
	 * @param string $type Generate type or taxonomy.
	 * @return string
	 */
	public static function entity_for_type( $type ) {
		$map = array(
			'product'       => 'product',
			'product_fill'  => 'product',
			'post'          => 'blog',
			'blog'          => 'blog',
			'blog_write'    => 'blog',
			'product_cat'   => 'product_cat',
			'product_brand' => 'product_brand',
			'category'      => 'blog_cat',
			'blog_cat'      => 'blog_cat',
			'page'          => 'page',
			'page_design'   => 'page',
		);
		$key = sanitize_key( $type );
		return isset( $map[ $key ] ) ? $map[ $key ] : $key;
	}

	/**
	 * @param array<string,mixed> $input Raw.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$cur  = self::get();
		$raw  = is_array( $input ) ? $input : array();
		$out  = $cur;

		$providers = array( 'grok', 'gemini', 'openai', 'gapgpt' );
		if ( isset( $raw['default_provider'] ) ) {
			$p = sanitize_key( (string) $raw['default_provider'] );
			$out['default_provider'] = in_array( $p, $providers, true ) ? $p : 'grok';
		}

		if ( isset( $raw['fallback_order'] ) && is_array( $raw['fallback_order'] ) ) {
			$order = array();
			foreach ( $raw['fallback_order'] as $item ) {
				$k = sanitize_key( (string) $item );
				if ( in_array( $k, $providers, true ) && ! in_array( $k, $order, true ) ) {
					$order[] = $k;
				}
			}
			if ( $order ) {
				$out['fallback_order'] = $order;
			}
		}

		foreach ( array( 'grok_model', 'gemini_model', 'openai_model', 'gapgpt_model', 'site_topic', 'tone', 'seo_sep', 'site_name', 'title_pattern', 'page_model' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}

		if ( array_key_exists( 'site_description', $raw ) ) {
			$out['site_description'] = sanitize_textarea_field( (string) $raw['site_description'] );
		}

		if ( isset( $raw['palette_mode'] ) ) {
			$pm = sanitize_key( (string) $raw['palette_mode'] );
			$out['palette_mode'] = in_array( $pm, array( 'site', 'suggest' ), true ) ? $pm : 'site';
		}

		if ( isset( $raw['language'] ) ) {
			$lang = sanitize_key( (string) $raw['language'] );
			$out['language'] = in_array( $lang, array( 'fa', 'en' ), true ) ? $lang : 'fa';
		}

		if ( isset( $raw['title_brand_script'] ) ) {
			$script = sanitize_key( (string) $raw['title_brand_script'] );
			$out['title_brand_script'] = in_array( $script, array( 'fa', 'en' ), true ) ? $script : 'fa';
		}

		if ( array_key_exists( 'temperature', $raw ) ) {
			$out['temperature'] = min( 2, max( 0, round( (float) $raw['temperature'], 2 ) ) );
		}
		if ( array_key_exists( 'max_tokens', $raw ) ) {
			$out['max_tokens'] = min( 128000, max( 0, (int) $raw['max_tokens'] ) );
		}
		if ( array_key_exists( 'page_max_tokens', $raw ) ) {
			$out['page_max_tokens'] = min( 128000, max( 0, (int) $raw['page_max_tokens'] ) );
		}
		if ( isset( $raw['page_provider'] ) ) {
			$pp = sanitize_key( (string) $raw['page_provider'] );
			$out['page_provider'] = ( '' === $pp || 'default' === $pp ) ? '' : ( in_array( $pp, $providers, true ) ? $pp : '' );
		}

		foreach ( array( 'daily_blog_quota', 'daily_product_quota', 'min_blog_words', 'min_product_words', 'min_term_words', 'min_page_words', 'internal_links_min', 'internal_links_max', 'max_regenerate' ) as $ikey ) {
			if ( array_key_exists( $ikey, $raw ) ) {
				$out[ $ikey ] = max( 0, (int) $raw[ $ikey ] );
			}
		}

		if ( array_key_exists( 'similarity_threshold', $raw ) ) {
			$out['similarity_threshold'] = min( 0.99, max( 0.3, (float) $raw['similarity_threshold'] ) );
		}

		foreach ( array( 'auto_publish', 'require_site_name', 'enabled', 'do_product', 'do_product_cat', 'do_product_brand', 'do_blog', 'do_blog_cat', 'do_page', 'do_coffee', 'web_research', 'review_emojis', 'catalog_assign_categories', 'catalog_assign_brands', 'catalog_create_terms', 'catalog_only_missing', 'title_enabled', 'title_include_feature' ) as $bkey ) {
			if ( array_key_exists( $bkey, $raw ) ) {
				$out[ $bkey ] = (bool) $raw[ $bkey ];
			}
		}

		if ( isset( $raw['tones'] ) && is_array( $raw['tones'] ) ) {
			$out['tones'] = self::sanitize_tones( $raw['tones'] );
			$out['tone']  = self::resolved_tone( $out );
		} elseif ( array_key_exists( 'tone', $raw ) ) {
			$out['tones'] = self::tones_from_legacy_string( (string) $raw['tone'] );
			$out['tone']  = self::resolved_tone( $out );
		}

		if ( isset( $raw['publish_status'] ) ) {
			$st = sanitize_key( (string) $raw['publish_status'] );
			$out['publish_status'] = in_array( $st, array( 'draft', 'publish', 'pending' ), true ) ? $st : 'draft';
		}

		foreach ( array_keys( self::default_prompts() ) as $pkey ) {
			if ( array_key_exists( $pkey, $raw ) ) {
				$out[ $pkey ] = sanitize_textarea_field( (string) $raw[ $pkey ] );
			}
		}

		if ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
			$out['fields'] = self::merge_fields( self::field_defaults(), $raw['fields'] );
		}

		if ( array_key_exists( 'usd_to_toman', $raw ) ) {
			$out['usd_to_toman'] = min( 1000000, max( 1000, (int) $raw['usd_to_toman'] ) );
		}

		if ( isset( $raw['gapgpt_rates'] ) && is_array( $raw['gapgpt_rates'] ) ) {
			$rates = array();
			foreach ( $raw['gapgpt_rates'] as $mid => $row ) {
				$id = sanitize_text_field( strtolower( (string) $mid ) );
				if ( '' === $id || ! is_array( $row ) ) {
					continue;
				}
				$parsed = class_exists( 'WebinoCRM_AI_Pricing', false )
					? WebinoCRM_AI_Pricing::parse_rate_row( $row )
					: null;
				if ( $parsed ) {
					$rates[ $id ] = $parsed;
				}
			}
			$out['gapgpt_rates'] = $rates;
			$out['gapgpt_rates_updated_at'] = gmdate( 'Y-m-d' );
		}

		foreach ( array( 'grok_api_key', 'gemini_api_key', 'openai_api_key', 'gapgpt_api_key' ) as $kkey ) {
			if ( ! array_key_exists( $kkey, $raw ) ) {
				continue;
			}
			$val = trim( (string) $raw[ $kkey ] );
			if ( '' === $val || false !== strpos( $val, '*' ) ) {
				continue;
			}
			$out[ $kkey ] = $val;
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $input Raw.
	 * @return array<string,mixed>
	 */
	public static function save( $input ) {
		$sanitized = self::sanitize( $input );
		$store     = $sanitized;
		unset( $store['prompt_defaults'], $store['coffee_module'], $store['queue_paused'] );

		foreach ( array( 'grok_api_key', 'gemini_api_key', 'openai_api_key', 'gapgpt_api_key' ) as $k ) {
			$plain = (string) ( $store[ $k ] ?? '' );
			$store[ $k ] = '' === $plain ? '' : ( 'enc:' . WebinoCRM_AI_Content_Crypto::encrypt( $plain ) );
		}

		delete_transient( 'webino_ai_gapgpt_models' );
		update_option( self::OPTION, $store, false );
		return self::get_public();
	}

	/**
	 * @param array<string,array<string,array<string,mixed>>> $defaults Defaults.
	 * @param array<string,mixed>                             $stored Stored.
	 * @return array<string,array<string,array{enabled:bool,length:int,unit:string}>>
	 */
	public static function merge_fields_public( $defaults, $stored ) {
		return self::merge_fields( $defaults, $stored );
	}

	/**
	 * @param array<string,array<string,array<string,mixed>>> $defaults Defaults.
	 * @param array<string,mixed>                             $stored Stored.
	 * @return array<string,array<string,array{enabled:bool,length:int,unit:string}>>
	 */
	private static function merge_fields( $defaults, $stored ) {
		$out = $defaults;
		if ( ! is_array( $stored ) ) {
			return $out;
		}
		foreach ( $defaults as $entity => $fields ) {
			if ( ! isset( $stored[ $entity ] ) || ! is_array( $stored[ $entity ] ) ) {
				continue;
			}
			foreach ( $fields as $fkey => $spec ) {
				if ( ! isset( $stored[ $entity ][ $fkey ] ) || ! is_array( $stored[ $entity ][ $fkey ] ) ) {
					continue;
				}
				$row = $stored[ $entity ][ $fkey ];
				if ( array_key_exists( 'enabled', $row ) ) {
					$out[ $entity ][ $fkey ]['enabled'] = (bool) $row['enabled'];
				}
				if ( array_key_exists( 'length', $row ) ) {
					$out[ $entity ][ $fkey ]['length'] = max( 0, (int) $row['length'] );
				}
				if ( isset( $row['unit'] ) ) {
					$u = sanitize_key( (string) $row['unit'] );
					if ( in_array( $u, array( 'words', 'paragraphs', 'count' ), true ) ) {
						$out[ $entity ][ $fkey ]['unit'] = $u;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Provider extras for page_design jobs only.
	 *
	 * @return array<string,mixed>
	 */
	public static function page_provider_extra() {
		$s     = self::get();
		$extra = array();
		$pp    = sanitize_key( (string) ( $s['page_provider'] ?? '' ) );
		if ( in_array( $pp, array( 'grok', 'gemini', 'openai', 'gapgpt' ), true ) ) {
			$extra['force_provider'] = $pp;
		}
		$pm = trim( (string) ( $s['page_model'] ?? '' ) );
		if ( '' !== $pm ) {
			$extra['force_model'] = sanitize_text_field( $pm );
		}
		$max = (int) ( $s['page_max_tokens'] ?? 0 );
		if ( $max > 0 ) {
			$extra['max_tokens'] = min( 128000, $max );
		}
		// Wait until the model returns (no HTTP timeout for page design).
		$extra['timeout'] = 0;
		return $extra;
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $entity Entity.
	 * @param string              $field Field.
	 * @param int                 $fallback Fallback words.
	 * @return int
	 */
	private static function derived_words( $settings, $entity, $field, $fallback ) {
		$spec = isset( $settings['fields'][ $entity ][ $field ] ) && is_array( $settings['fields'][ $entity ][ $field ] )
			? $settings['fields'][ $entity ][ $field ]
			: array();
		$len  = max( 0, (int) ( $spec['length'] ?? 0 ) );
		$unit = sanitize_key( (string) ( $spec['unit'] ?? 'words' ) );
		if ( $len < 1 ) {
			return max( 1, (int) $fallback );
		}
		if ( 'paragraphs' === $unit ) {
			return max( 50, $len * 80 );
		}
		if ( 'count' === $unit ) {
			return max( 1, (int) $fallback );
		}
		return $len;
	}
}
