<?php
/**
 * Live Elementor widget + local template catalog for page design prompts (v2).
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rich widget catalog aligned with the Elementor compiler.
 */
final class WebinoCRM_AI_Elementor_Catalog {

	/**
	 * Widget types the compiler can emit natively (html is always available).
	 *
	 * @return list<string>
	 */
	public static function buildable_widgets() {
		return array(
			'html',
			'heading',
			'text-editor',
			'button',
			'image',
			'icon',
			'icon-box',
			'icon-list',
			'image-box',
			'image-carousel',
			'image-gallery',
			'video',
			'counter',
			'accordion',
			'tabs',
			'toggle',
			'testimonial',
			'star-rating',
			'social-icons',
			'progress',
			'price-table',
			'alert',
			'spacer',
			'divider',
			'nested-tabs',
			'nested-accordion',
		);
	}

	/**
	 * Elementor Pro cinematic widgets — compile via html when compiler has no native builder.
	 *
	 * @return list<string>
	 */
	public static function cinematic_pro_widgets() {
		return array(
			'call-to-action',
			'flip-box',
			'slides',
			'animated-headline',
			'hotspot',
			'lottie',
		);
	}

	/**
	 * Widgets referenced as hints only (not in buildable list).
	 *
	 * @return list<string>
	 */
	public static function hint_only_widgets() {
		return array(
			'form',
			'inner-section',
		);
	}

	/**
	 * Metadata for each buildable widget.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function widget_definitions() {
		return array(
			'html'             => array(
				'role'           => 'cinematic_layout',
				'when_to_use'    => 'Hero glass panels, bento CSS grids, timelines, marquees, overlapping cards, full-bleed CTAs. Primary visual identity carrier.',
				'settings_shape' => array( 'html' => 'string', 'css' => 'css-scoped' ),
				'example_block'  => array(
					'widget'   => 'html',
					'column'   => 0,
					'settings' => array(
						'title' => 'Hero headline',
						'text'  => 'Short supporting line',
					),
				),
			),
			'heading'          => array(
				'role'           => 'typography',
				'when_to_use'    => 'Section titles, eyebrow alternatives, stat labels when native styling is enough.',
				'settings_shape' => array( 'title' => 'string', 'tag' => 'h1-h6', 'size' => 'number' ),
				'example_block'  => array(
					'widget'   => 'heading',
					'column'   => 0,
					'settings' => array( 'title' => 'Why choose us', 'tag' => 'h2', 'size' => 36 ),
				),
			),
			'text-editor'      => array(
				'role'           => 'body_copy',
				'when_to_use'    => 'Short paragraphs when layout is already cinematic via html blocks.',
				'settings_shape' => array( 'text' => 'string-html-light' ),
				'example_block'  => array(
					'widget'   => 'text-editor',
					'column'   => 1,
					'settings' => array( 'text' => '<p>One or two sentences max.</p>' ),
				),
			),
			'button'           => array(
				'role'           => 'cta',
				'when_to_use'    => 'Primary/secondary CTAs in split layouts and pricing rows.',
				'settings_shape' => array( 'title' => 'string', 'url' => 'string' ),
				'example_block'  => array(
					'widget'   => 'button',
					'column'   => 0,
					'settings' => array( 'title' => 'Get started', 'url' => '/contact' ),
				),
			),
			'image'            => array(
				'role'           => 'media',
				'when_to_use'    => 'Product shots, team photos, hero stills. Prefer media[].id from catalog.',
				'settings_shape' => array( 'image_id' => 'attachment_id', 'image_alt' => 'string' ),
				'example_block'  => array(
					'widget'   => 'image',
					'column'   => 1,
					'settings' => array( 'image_id' => 0, 'image_alt' => 'Product in use' ),
				),
			),
			'icon-box'         => array(
				'role'           => 'feature',
				'when_to_use'    => 'Bento cells, service tiles, value props with icon + title + blurb.',
				'settings_shape' => array( 'title' => 'string', 'text' => 'string', 'icon' => 'fas-fa-icon' ),
				'example_block'  => array(
					'widget'   => 'icon-box',
					'column'   => 0,
					'settings' => array( 'title' => 'Fast delivery', 'text' => 'Same-day in city.', 'icon' => 'fas fa-shipping-fast' ),
				),
			),
			'counter'          => array(
				'role'           => 'stats',
				'when_to_use'    => 'Social proof numbers in a stats strip or bento row.',
				'settings_shape' => array( 'number' => 'string', 'text' => 'string-label' ),
				'example_block'  => array(
					'widget'   => 'counter',
					'column'   => 0,
					'settings' => array( 'number' => '1200', 'text' => 'Happy clients' ),
				),
			),
			'accordion'        => array(
				'role'           => 'faq',
				'when_to_use'    => 'FAQ sections with 4–6 Q&A pairs.',
				'settings_shape' => array( 'items' => array( array( 'question' => 'string', 'answer' => 'string' ) ) ),
				'example_block'  => array(
					'widget'   => 'accordion',
					'column'   => 0,
					'settings' => array(
						'items' => array(
							array( 'question' => 'How do I order?', 'answer' => 'Pick options and checkout.' ),
						),
					),
				),
			),
			'tabs'             => array(
				'role'           => 'grouped_content',
				'when_to_use'    => 'Feature groups, process steps, spec sheets.',
				'settings_shape' => array( 'items' => array( array( 'title' => 'string', 'text' => 'string' ) ) ),
				'example_block'  => array(
					'widget'   => 'tabs',
					'column'   => 0,
					'settings' => array(
						'items' => array(
							array( 'title' => 'Design', 'text' => 'Brand-first layouts.' ),
							array( 'title' => 'Build', 'text' => 'Elementor-native output.' ),
						),
					),
				),
			),
			'testimonial'      => array(
				'role'           => 'social_proof',
				'when_to_use'    => 'Quotes with name and role.',
				'settings_shape' => array( 'text' => 'string', 'name' => 'string', 'role' => 'string' ),
				'example_block'  => array(
					'widget'   => 'testimonial',
					'column'   => 0,
					'settings' => array( 'text' => 'They nailed our rebrand.', 'name' => 'Sara M.', 'role' => 'Founder' ),
				),
			),
			'video'            => array(
				'role'           => 'media',
				'when_to_use'    => 'Showreel, product demo, background loop in split hero.',
				'settings_shape' => array( 'url' => 'youtube-or-mp4-url' ),
				'example_block'  => array(
					'widget'   => 'video',
					'column'   => 1,
					'settings' => array( 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
				),
			),
			'image-carousel'   => array(
				'role'           => 'gallery',
				'when_to_use'    => 'Logo wall, portfolio strip, product gallery.',
				'settings_shape' => array( 'gallery_ids' => array( 0 ) ),
				'example_block'  => array(
					'widget'   => 'image-carousel',
					'column'   => 0,
					'settings' => array( 'gallery_ids' => array() ),
				),
			),
			'star-rating'      => array(
				'role'           => 'trust',
				'when_to_use'    => 'Review score badge near testimonials.',
				'settings_shape' => array( 'number' => '1-5', 'title' => 'string' ),
				'example_block'  => array(
					'widget'   => 'star-rating',
					'column'   => 0,
					'settings' => array( 'number' => 5, 'title' => '4.9 average' ),
				),
			),
			'spacer'           => array(
				'role'           => 'layout',
				'when_to_use'    => 'Vertical rhythm between dense blocks.',
				'settings_shape' => array( 'size' => 'px' ),
				'example_block'  => array(
					'widget'   => 'spacer',
					'column'   => 0,
					'settings' => array( 'size' => 48 ),
				),
			),
			'divider'          => array(
				'role'           => 'layout',
				'when_to_use'    => 'Subtle section separators inside columns.',
				'settings_shape' => array(),
				'example_block'  => array(
					'widget'   => 'divider',
					'column'   => 0,
					'settings' => array(),
				),
			),
			'icon-list'        => array(
				'role'           => 'list',
				'when_to_use'    => 'Checklist benefits, spec bullets.',
				'settings_shape' => array( 'items' => array( array( 'title' => 'string', 'icon' => 'fas-fa-icon' ) ) ),
				'example_block'  => array(
					'widget'   => 'icon-list',
					'column'   => 0,
					'settings' => array(
						'items' => array(
							array( 'title' => 'No hidden fees', 'icon' => 'fas fa-check' ),
						),
					),
				),
			),
			'image-box'        => array(
				'role'           => 'feature',
				'when_to_use'    => 'Card with thumbnail + title + text.',
				'settings_shape' => array( 'image_id' => 'attachment_id', 'title' => 'string', 'text' => 'string' ),
				'example_block'  => array(
					'widget'   => 'image-box',
					'column'   => 0,
					'settings' => array( 'image_id' => 0, 'title' => 'Studio', 'text' => 'On-site shoots.' ),
				),
			),
			'image-gallery'    => array(
				'role'           => 'gallery',
				'when_to_use'    => 'Masonry-style still grid.',
				'settings_shape' => array( 'gallery_ids' => array( 0 ) ),
				'example_block'  => array(
					'widget'   => 'image-gallery',
					'column'   => 0,
					'settings' => array( 'gallery_ids' => array() ),
				),
			),
			'icon'             => array(
				'role'           => 'decoration',
				'when_to_use'    => 'Large decorative icon in minimal layouts.',
				'settings_shape' => array( 'icon' => 'fas-fa-icon' ),
				'example_block'  => array(
					'widget'   => 'icon',
					'column'   => 0,
					'settings' => array( 'icon' => 'fas fa-bolt' ),
				),
			),
			'toggle'           => array(
				'role'           => 'faq',
				'when_to_use'    => 'Compact FAQ alternative to accordion.',
				'settings_shape' => array( 'items' => array( array( 'question' => 'string', 'answer' => 'string' ) ) ),
				'example_block'  => array(
					'widget'   => 'toggle',
					'column'   => 0,
					'settings' => array(
						'items' => array(
							array( 'question' => 'Shipping?', 'answer' => 'Nationwide.' ),
						),
					),
				),
			),
			'nested-tabs'      => array(
				'role'           => 'grouped_content',
				'when_to_use'    => 'Elementor nested tabs when available.',
				'settings_shape' => array( 'items' => array( array( 'title' => 'string', 'text' => 'string' ) ) ),
				'example_block'  => array(
					'widget'   => 'nested-tabs',
					'column'   => 0,
					'settings' => array(
						'items' => array(
							array( 'title' => 'Plan A', 'text' => 'Starter scope.' ),
						),
					),
				),
			),
			'nested-accordion' => array(
				'role'           => 'faq',
				'when_to_use'    => 'Nested FAQ when Elementor Pro nested widgets are active.',
				'settings_shape' => array( 'items' => array( array( 'question' => 'string', 'answer' => 'string' ) ) ),
				'example_block'  => array(
					'widget'   => 'nested-accordion',
					'column'   => 0,
					'settings' => array(
						'items' => array(
							array( 'question' => 'Support?', 'answer' => 'Email and chat.' ),
						),
					),
				),
			),
			'social-icons'     => array(
				'role'           => 'footer',
				'when_to_use'    => 'Social row in footer or contact strip.',
				'settings_shape' => array(),
				'example_block'  => array(
					'widget'   => 'social-icons',
					'column'   => 0,
					'settings' => array(),
				),
			),
			'progress'         => array(
				'role'           => 'stats',
				'when_to_use'    => 'Skill bars, completion meters.',
				'settings_shape' => array( 'title' => 'string', 'number' => '0-100' ),
				'example_block'  => array(
					'widget'   => 'progress',
					'column'   => 0,
					'settings' => array( 'title' => 'Uptime', 'number' => 99 ),
				),
			),
			'price-table'      => array(
				'role'           => 'pricing',
				'when_to_use'    => 'Simple pricing column when not using html bento.',
				'settings_shape' => array( 'title' => 'string', 'text' => 'string', 'number' => 'price' ),
				'example_block'  => array(
					'widget'   => 'price-table',
					'column'   => 0,
					'settings' => array( 'title' => 'Pro', 'text' => 'For teams', 'number' => '99' ),
				),
			),
			'alert'            => array(
				'role'           => 'callout',
				'when_to_use'    => 'Promo banner, limited offer strip.',
				'settings_shape' => array( 'title' => 'string', 'text' => 'string' ),
				'example_block'  => array(
					'widget'   => 'alert',
					'column'   => 0,
					'settings' => array( 'title' => 'Launch offer', 'text' => 'Free audit this month.' ),
				),
			),
		);
	}

	/**
	 * Pro cinematic widgets with html fallback guidance.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function use_via_html_widgets() {
		$defs = array(
			'call-to-action'     => array(
				'role'        => 'cta',
				'when_to_use' => 'Full-bleed dual-column CTA with background — recreate with html+css.',
			),
			'flip-box'           => array(
				'role'        => 'interactive',
				'when_to_use' => 'Hover flip cards — use html+css 3D transform.',
			),
			'slides'             => array(
				'role'        => 'hero',
				'when_to_use' => 'Multi-slide hero — use html+css scroll-snap or marquee.',
			),
			'animated-headline'  => array(
				'role'        => 'typography',
				'when_to_use' => 'Rotating words in hero — use html+css animation.',
			),
			'hotspot'            => array(
				'role'        => 'interactive',
				'when_to_use' => 'Image hotspots — use html+css absolute positioning.',
			),
			'lottie'             => array(
				'role'        => 'motion',
				'when_to_use' => 'Light motion accents — prefer CSS animation in html block.',
			),
			'form'               => array(
				'role'        => 'lead_capture',
				'when_to_use' => 'Contact forms — link to existing form page or build styled html that links to /contact.',
			),
			'inner-section'      => array(
				'role'        => 'layout',
				'when_to_use' => 'Nested columns — use section layout.columns + multiple blocks instead.',
			),
		);
		$installed = self::installed_widget_names();
		$out       = array();
		foreach ( $defs as $name => $meta ) {
			if ( $installed && ! in_array( $name, $installed, true ) ) {
				continue;
			}
			$meta['name']          = $name;
			$meta['compile_as']    = 'html';
			$out[ $name ]          = $meta;
		}
		return $out;
	}

	/**
	 * Rich catalog for LLM prompts (installed ∩ buildable with full metadata).
	 *
	 * @return array<string,mixed>
	 */
	public static function for_prompt() {
		$installed  = self::installed_widget_names();
		$buildable  = self::buildable_widgets();
		$defs       = self::widget_definitions();
		$widgets    = array();

		foreach ( $buildable as $name ) {
			if ( $installed && ! in_array( $name, $installed, true ) && 'html' !== $name ) {
				continue;
			}
			$entry = isset( $defs[ $name ] ) ? $defs[ $name ] : array();
			$widgets[] = array_merge(
				array( 'name' => $name ),
				$entry
			);
		}

		if ( ! self::has_widget( $widgets, 'html' ) ) {
			$widgets[] = array_merge( array( 'name' => 'html' ), $defs['html'] );
		}

		return array(
			'elementor_active'  => defined( 'ELEMENTOR_VERSION' ),
			'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
			'widgets'           => $widgets,
			'use_via_html'      => array_values( self::use_via_html_widgets() ),
			'local_templates'   => self::local_templates(),
			'media'             => self::recent_media(),
			'notes'             => array(
				'Pass 1 (layout): pick widgets + settings only — no long html/css.',
				'Pass 2 (visual): fill html+css on html blocks and decorative wrappers.',
				'Unknown widget names compile as html widgets.',
				'Never set font-family; theme fonts apply.',
				'Use media[].id for image_id / gallery_ids when images exist.',
				'import_template_ids may only use local_templates[].id.',
			),
		);
	}

	/**
	 * @param list<array<string,mixed>> $widgets Widget rows.
	 * @param string                    $name Name.
	 * @return bool
	 */
	private static function has_widget( $widgets, $name ) {
		foreach ( $widgets as $w ) {
			if ( is_array( $w ) && (string) ( $w['name'] ?? '' ) === $name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a widget slot can carry cinematic html (pass 1 visual gate).
	 *
	 * @param array<string,mixed> $block Block.
	 * @return bool
	 */
	public static function block_is_html_capable( $block ) {
		if ( ! is_array( $block ) ) {
			return false;
		}
		$widget = sanitize_key( str_replace( '_', '-', (string) ( $block['widget'] ?? '' ) ) );
		if ( 'html' === $widget ) {
			return true;
		}
		if ( in_array( $widget, array( 'text-editor', 'inner-section', 'form' ), true ) ) {
			return true;
		}
		if ( in_array( $widget, self::cinematic_pro_widgets(), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return list<string>
	 */
	public static function installed_widget_names() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return self::buildable_widgets();
		}
		$wm = \Elementor\Plugin::$instance->widgets_manager;
		if ( ! method_exists( $wm, 'get_widget_types' ) ) {
			return self::buildable_widgets();
		}
		$types = $wm->get_widget_types();
		if ( ! is_array( $types ) ) {
			return self::buildable_widgets();
		}
		$names = array();
		foreach ( $types as $key => $widget ) {
			$name = is_object( $widget ) && method_exists( $widget, 'get_name' ) ? (string) $widget->get_name() : (string) $key;
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		$names = array_values( array_unique( $names ) );
		sort( $names );
		return $names;
	}

	/**
	 * @return list<array{id:int,title:string,type:string}>
	 */
	public static function local_templates() {
		$q = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 24,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $q as $p ) {
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => (string) $p->post_title,
				'type'  => (string) get_post_meta( $p->ID, '_elementor_template_type', true ),
			);
		}
		return $out;
	}

	/**
	 * @param int $template_id Template post ID.
	 * @return array<int,mixed>
	 */
	public static function template_elements( $template_id ) {
		$template_id = (int) $template_id;
		if ( $template_id <= 0 ) {
			return array();
		}
		$post = get_post( $template_id );
		if ( ! $post || 'elementor_library' !== $post->post_type ) {
			return array();
		}
		if ( class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$doc = \Elementor\Plugin::$instance->documents->get( $template_id );
			if ( $doc && method_exists( $doc, 'get_elements_data' ) ) {
				$data = $doc->get_elements_data();
				if ( is_array( $data ) ) {
					return $data;
				}
			}
		}
		$raw = get_post_meta( $template_id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	/**
	 * @return list<array{id:int,url:string,alt:string}>
	 */
	public static function recent_media() {
		$q = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 16,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $q as $p ) {
			$url = wp_get_attachment_image_url( $p->ID, 'large' );
			$out[] = array(
				'id'  => (int) $p->ID,
				'url' => $url ? $url : '',
				'alt' => (string) get_post_meta( $p->ID, '_wp_attachment_image_alt', true ),
			);
		}
		return $out;
	}
}
