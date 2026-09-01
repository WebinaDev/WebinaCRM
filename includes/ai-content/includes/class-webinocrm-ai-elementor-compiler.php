<?php
/**
 * Compile PageBlueprint sections into Elementor element trees.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Archetype library → native Elementor widgets (no custom fonts).
 */
final class WebinoCRM_AI_Elementor_Compiler {

	/**
	 * @param array<string,mixed> $blueprint Page blueprint from LLM.
	 * @param int                 $page_id Page ID for CSS scoping.
	 * @return array<int,mixed>|WP_Error
	 */
	public static function compile( $blueprint, $page_id = 0 ) {
		if ( ! self::has_visual_blocks( $blueprint ) ) {
			return new WP_Error(
				'ai_page_no_blocks',
				__( 'Page blueprint has no visual blocks. Re-run page design.', 'webinocrm' )
			);
		}

		$memory   = WebinoCRM_AI_Design_Memory::get();
		$page_id  = (int) $page_id;
		$scope    = 'wai-p' . max( 1, $page_id );
		$sections = isset( $blueprint['sections'] ) && is_array( $blueprint['sections'] ) ? $blueprint['sections'] : array();
		$tree     = array();

		foreach ( self::imported_templates( $blueprint ) as $el ) {
			$tree[] = $el;
		}

		$page_css = WebinoCRM_AI_Elementor_Sanitize::css( (string) ( $blueprint['page_css'] ?? '' ) );
		if ( '' !== $page_css ) {
			$tree[] = self::section(
				array(
					self::column(
						100,
						array( self::widget_html( '<style>' . $page_css . '</style>', $scope, 'page-css' ) )
					),
				),
				array(
					'layout'  => 'full_width',
					'padding' => self::padding( 0, 0, 0, 0 ),
				),
				'page-css'
			);
		}

		if ( ! $sections ) {
			return new WP_Error(
				'ai_page_no_blocks',
				__( 'Page blueprint has no sections.', 'webinocrm' )
			);
		}

		foreach ( $sections as $i => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$blocks = isset( $section['blocks'] ) && is_array( $section['blocks'] ) ? $section['blocks'] : array();
			if ( ! $blocks ) {
				continue;
			}
			$built = self::build_visual_section( $section, $memory, (int) $i, $scope );
			if ( $built ) {
				$tree[] = $built;
			}
		}

		if ( count( $tree ) < 1 ) {
			return new WP_Error(
				'ai_page_no_blocks',
				__( 'Compiler produced an empty Elementor tree.', 'webinocrm' )
			);
		}

		return WebinoCRM_AI_Elementor_Sanitize::strip_fonts( $tree );
	}

	/**
	 * @param array<string,mixed> $blueprint Blueprint.
	 * @return bool
	 */
	public static function has_visual_blocks( $blueprint ) {
		if ( ! is_array( $blueprint ) ) {
			return false;
		}
		$sections = isset( $blueprint['sections'] ) && is_array( $blueprint['sections'] ) ? $blueprint['sections'] : array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$blocks = isset( $section['blocks'] ) && is_array( $section['blocks'] ) ? $section['blocks'] : array();
			if ( $blocks ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string              $archetype Archetype.
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Design memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>|null
	 */
	private static function build_section( $archetype, $content, $memory, $index, $scope = 'wai-p' ) {
		switch ( $archetype ) {
			case 'hero_cinematic':
				return self::section_hero( $content, $memory, $index, $scope );
			case 'bento_features':
				return self::section_bento( $content, $memory, $index );
			case 'stats_strip':
				return self::section_stats( $content, $memory, $index );
			case 'timeline':
				return self::section_timeline( $content, $memory, $index );
			case 'faq_accordion':
				return self::section_faq( $content, $memory, $index );
			case 'cta_fullbleed':
				return self::section_cta( $content, $memory, $index );
			case 'testimonials':
				return self::section_testimonials( $content, $memory, $index );
			case 'icon_grid':
				return self::section_icon_grid( $content, $memory, $index );
			case 'logo_strip':
				return self::section_logo_strip( $content, $memory, $index );
			case 'image_text_split':
			default:
				return self::section_split( $content, $memory, $index );
		}
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_hero( $content, $memory, $index, $scope = 'wai-p' ) {
		$pad_y   = (int) ( $memory['spacing']['section_y'] ?? 80 );
		$primary = self::color_settings( 'primary' );
		$accent  = self::color_settings( 'accent' );
		$text    = self::color_settings( 'text' );
		$bg      = self::color_settings( 'bg' );

		$heading = (string) ( $content['heading'] ?? $content['h1'] ?? '' );
		$sub     = (string) ( $content['subheading'] ?? $content['text'] ?? '' );
		$eyebrow = (string) ( $content['eyebrow'] ?? '' );
		$cta     = (string) ( $content['cta_text'] ?? '' );
		$url     = (string) ( $content['cta_url'] ?? '#' );
		$img_id  = self::resolve_image_id( $content );

		$left_widgets = array();
		if ( '' !== $eyebrow ) {
			$left_widgets[] = self::widget_heading( $eyebrow, 'h6', $accent, 14 );
		}
		$left_widgets[] = self::widget_heading( $heading, 'h1', $text, 48 );
		if ( '' !== $sub ) {
			$left_widgets[] = self::widget_text( '<p>' . esc_html( $sub ) . '</p>', $memory['spacing']['gap'] ?? 24 );
		}
		if ( '' !== $cta ) {
			$left_widgets[] = self::widget_button( $cta, $url, $primary, $memory );
		}

		$cols = array(
			self::column( 50, $left_widgets, array( 'content_position' => 'center' ) ),
		);

		$right = array();
		if ( $img_id > 0 ) {
			$right[] = self::widget_image( $img_id, (string) ( $content['image_alt'] ?? $heading ) );
		} else {
			$right[] = self::widget_html( self::cinematic_orb_html( $memory ), $scope, 'hero-orb-' . $index );
		}
		$cols[] = self::column( 50, $right, array( 'content_position' => 'center' ) );

		return self::section(
			$cols,
			array_merge(
				array(
					'layout'                     => 'full_width',
					'gap'                        => 'extended',
					'content_width'              => 'boxed',
					'content_width_custom'       => array( 'unit' => 'px', 'size' => 1200 ),
					'padding'                    => self::padding( $pad_y + 40, 24, $pad_y + 40, 24 ),
					'background_background'      => 'classic',
					'background_color'           => $bg['color'],
					'background_overlay_background' => 'gradient',
					'background_overlay_color'   => $primary['color'],
					'background_overlay_color_b' => $accent['color'],
					'background_overlay_opacity' => array( 'unit' => 'px', 'size' => 0.08 ),
					'background_overlay_gradient_angle' => array( 'unit' => 'deg', 'size' => 135 ),
				),
				self::maybe_globals( $bg, 'background_color' )
			),
			'hero-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_bento( $content, $memory, $index ) {
		$items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();
		if ( count( $items ) < 2 ) {
			$items = array(
				array( 'title' => (string) ( $content['heading'] ?? 'Feature' ), 'text' => (string) ( $content['text'] ?? '' ), 'icon' => 'fas fa-star' ),
				array( 'title' => 'Quality', 'text' => '', 'icon' => 'fas fa-gem' ),
				array( 'title' => 'Support', 'text' => '', 'icon' => 'fas fa-headset' ),
			);
		}
		$surface = self::color_settings( 'surface' );
		$text    = self::color_settings( 'text' );
		$accent  = self::color_settings( 'accent' );
		$pad_y   = (int) ( $memory['spacing']['section_y'] ?? 80 );
		$radius  = (int) ( $memory['radius']['card'] ?? 16 );

		$title_widgets = array();
		if ( ! empty( $content['heading'] ) ) {
			$title_widgets[] = self::widget_heading( (string) $content['heading'], 'h2', $text, 36 );
		}
		if ( ! empty( $content['subheading'] ) ) {
			$title_widgets[] = self::widget_text( '<p>' . esc_html( (string) $content['subheading'] ) . '</p>', 12 );
		}

		$rows = array();
		if ( $title_widgets ) {
			$rows[] = self::inner_section( array( self::column( 100, $title_widgets ) ), array( 'gap' => 'no' ) );
		}

		$chunk = array_chunk( array_slice( $items, 0, 6 ), 3 );
		foreach ( $chunk as $row_items ) {
			$cols = array();
			$w    = (int) floor( 100 / max( 1, count( $row_items ) ) );
			foreach ( $row_items as $ri => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$box = self::widget_icon_box(
					(string) ( $item['title'] ?? '' ),
					(string) ( $item['text'] ?? $item['description'] ?? '' ),
					(string) ( $item['icon'] ?? 'fas fa-check' ),
					$accent,
					$text
				);
				$col_settings = array(
					'background_background' => 'classic',
					'background_color'      => $surface['color'],
					'border_radius'         => self::sides( $radius ),
					'padding'               => self::padding( 28, 24, 28, 24 ),
					'box_shadow_box_shadow' => array(
						'horizontal' => 0,
						'vertical'   => 12,
						'blur'       => 40,
						'spread'     => 0,
						'color'      => 'rgba(15,23,42,0.08)',
					),
					'box_shadow_box_shadow_type' => 'yes',
				);
				if ( 0 === $ri && count( $row_items ) >= 3 ) {
					// Asymmetric first card slightly taller feel via extra padding.
					$col_settings['padding'] = self::padding( 36, 28, 36, 28 );
				}
				$cols[] = self::column( $w, array( $box ), $col_settings );
			}
			$rows[] = self::inner_section( $cols, array( 'gap' => 'extended' ) );
		}

		return self::section(
			array( self::column( 100, $rows ) ),
			array(
				'layout'                => 'full_width',
				'content_width'         => 'boxed',
				'content_width_custom'  => array( 'unit' => 'px', 'size' => 1200 ),
				'padding'               => self::padding( $pad_y, 24, $pad_y, 24 ),
				'background_background' => 'classic',
				'background_color'      => self::color_settings( 'bg' )['color'],
			),
			'bento-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_stats( $content, $memory, $index ) {
		$items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();
		if ( ! $items ) {
			$items = array(
				array( 'number' => '100', 'title' => 'Projects' ),
				array( 'number' => '50', 'title' => 'Clients' ),
				array( 'number' => '10', 'title' => 'Years' ),
				array( 'number' => '24', 'title' => 'Support' ),
			);
		}
		$primary = self::color_settings( 'primary' );
		$text    = self::color_settings( 'text' );
		$accent  = self::color_settings( 'accent' );
		$cols    = array();
		$w       = (int) floor( 100 / min( 4, max( 1, count( $items ) ) ) );
		foreach ( array_slice( $items, 0, 4 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$num = preg_replace( '/[^0-9.]/', '', (string) ( $item['number'] ?? $item['value'] ?? '0' ) );
			$cols[] = self::column(
				$w,
				array(
					self::widget_counter( (float) $num, (string) ( $item['title'] ?? $item['label'] ?? '' ), $accent, $text ),
				),
				array( 'align' => 'center' )
			);
		}
		return self::section(
			$cols,
			array(
				'layout'                => 'full_width',
				'content_width'         => 'boxed',
				'gap'                   => 'extended',
				'padding'               => self::padding( 56, 24, 56, 24 ),
				'background_background' => 'classic',
				'background_color'      => $primary['color'],
			),
			'stats-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_timeline( $content, $memory, $index ) {
		$items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();
		$text  = self::color_settings( 'text' );
		$accent = self::color_settings( 'accent' );
		$list  = array();
		foreach ( array_slice( $items, 0, 6 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$list[] = array(
				'text' => '<strong>' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</strong> — ' . esc_html( (string) ( $item['text'] ?? $item['description'] ?? '' ) ),
				'selected_icon' => array( 'value' => 'fas fa-circle', 'library' => 'fa-solid' ),
			);
		}
		if ( ! $list ) {
			$list[] = array(
				'text' => (string) ( $content['text'] ?? $content['heading'] ?? 'Step' ),
				'selected_icon' => array( 'value' => 'fas fa-circle', 'library' => 'fa-solid' ),
			);
		}
		$widgets = array();
		if ( ! empty( $content['heading'] ) ) {
			$widgets[] = self::widget_heading( (string) $content['heading'], 'h2', $text, 32 );
		}
		$widgets[] = array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'icon-list',
			'settings'   => array(
				'icon_list'     => $list,
				'icon_color'    => $accent['color'],
				'text_color'    => $text['color'],
				'space_between' => array( 'unit' => 'px', 'size' => 18 ),
			),
			'elements'   => array(),
		);
		return self::section(
			array( self::column( 100, $widgets ) ),
			array(
				'layout'               => 'full_width',
				'content_width'        => 'boxed',
				'content_width_custom' => array( 'unit' => 'px', 'size' => 900 ),
				'padding'              => self::padding( (int) ( $memory['spacing']['section_y'] ?? 80 ), 24, (int) ( $memory['spacing']['section_y'] ?? 80 ), 24 ),
			),
			'timeline-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_faq( $content, $memory, $index ) {
		$items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();
		if ( isset( $content['faqs'] ) && is_array( $content['faqs'] ) ) {
			$items = $content['faqs'];
		}
		$tabs = array();
		foreach ( array_slice( $items, 0, 8 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$tabs[] = array(
				'tab_title'   => (string) ( $item['question'] ?? $item['title'] ?? '' ),
				'tab_content' => wp_kses_post( (string) ( $item['answer'] ?? $item['text'] ?? '' ) ),
			);
		}
		if ( ! $tabs ) {
			$tabs[] = array(
				'tab_title'   => (string) ( $content['heading'] ?? 'FAQ' ),
				'tab_content' => wp_kses_post( (string) ( $content['text'] ?? '' ) ),
			);
		}
		$text    = self::color_settings( 'text' );
		$widgets = array();
		if ( ! empty( $content['heading'] ) ) {
			$widgets[] = self::widget_heading( (string) $content['heading'], 'h2', $text, 32 );
		}
		$widgets[] = array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'accordion',
			'settings'   => array(
				'tabs'             => $tabs,
				'title_color'      => $text['color'],
				'content_color'    => self::color_settings( 'muted' )['color'],
				'border_color'     => self::color_settings( 'surface' )['color'],
			),
			'elements'   => array(),
		);
		return self::section(
			array( self::column( 100, $widgets ) ),
			array(
				'layout'               => 'full_width',
				'content_width'        => 'boxed',
				'content_width_custom' => array( 'unit' => 'px', 'size' => 900 ),
				'padding'              => self::padding( (int) ( $memory['spacing']['section_y'] ?? 80 ), 24, (int) ( $memory['spacing']['section_y'] ?? 80 ), 24 ),
				'background_background'=> 'classic',
				'background_color'     => self::color_settings( 'surface' )['color'],
			),
			'faq-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_cta( $content, $memory, $index ) {
		$primary = self::color_settings( 'primary' );
		$accent  = self::color_settings( 'accent' );
		$text    = array( 'color' => '#ffffff', 'globals' => array() );
		$widgets = array(
			self::widget_heading( (string) ( $content['heading'] ?? '' ), 'h2', $text, 40 ),
		);
		if ( ! empty( $content['subheading'] ) || ! empty( $content['text'] ) ) {
			$widgets[] = self::widget_text( '<p>' . esc_html( (string) ( $content['subheading'] ?? $content['text'] ?? '' ) ) . '</p>', 16 );
		}
		if ( ! empty( $content['cta_text'] ) ) {
			$widgets[] = self::widget_button(
				(string) $content['cta_text'],
				(string) ( $content['cta_url'] ?? '#' ),
				$accent,
				$memory
			);
		}
		return self::section(
			array( self::column( 100, $widgets, array( 'align' => 'center' ) ) ),
			array(
				'layout'                => 'full_width',
				'content_width'         => 'boxed',
				'padding'               => self::padding( 96, 24, 96, 24 ),
				'background_background' => 'gradient',
				'background_color'      => $primary['color'],
				'background_color_b'    => $accent['color'],
				'background_gradient_angle' => array( 'unit' => 'deg', 'size' => 120 ),
			),
			'cta-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_testimonials( $content, $memory, $index ) {
		$items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();
		$text  = self::color_settings( 'text' );
		$muted = self::color_settings( 'muted' );
		$cols  = array();
		$slice = array_slice( $items ? $items : array( array( 'name' => 'Client', 'text' => (string) ( $content['text'] ?? '' ), 'title' => '' ) ), 0, 3 );
		$w     = (int) floor( 100 / max( 1, count( $slice ) ) );
		foreach ( $slice as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$cols[] = self::column(
				$w,
				array(
					array(
						'id'         => self::uid(),
						'elType'     => 'widget',
						'widgetType' => 'testimonial',
						'settings'   => array(
							'testimonial_content' => (string) ( $item['text'] ?? $item['content'] ?? '' ),
							'testimonial_name'    => (string) ( $item['name'] ?? '' ),
							'testimonial_job'     => (string) ( $item['title'] ?? $item['role'] ?? '' ),
							'content_color'       => $text['color'],
							'name_text_color'     => $text['color'],
							'job_text_color'      => $muted['color'],
						),
						'elements'   => array(),
					),
				),
				array(
					'background_background' => 'classic',
					'background_color'      => self::color_settings( 'surface' )['color'],
					'padding'               => self::padding( 28, 24, 28, 24 ),
					'border_radius'         => self::sides( (int) ( $memory['radius']['card'] ?? 16 ) ),
				)
			);
		}
		$widgets_title = array();
		if ( ! empty( $content['heading'] ) ) {
			$head = self::section(
				array( self::column( 100, array( self::widget_heading( (string) $content['heading'], 'h2', $text, 32 ) ) ) ),
				array( 'padding' => self::padding( 0, 0, 16, 0 ) ),
				'test-h-' . $index
			);
			// Flatten: wrap title + cards in one section.
			return self::section(
				array_merge(
					array( self::column( 100, array( self::widget_heading( (string) $content['heading'], 'h2', $text, 32 ) ) ) ),
					$cols
				),
				array(
					'layout'               => 'full_width',
					'content_width'        => 'boxed',
					'gap'                  => 'extended',
					'padding'              => self::padding( (int) ( $memory['spacing']['section_y'] ?? 80 ), 24, (int) ( $memory['spacing']['section_y'] ?? 80 ), 24 ),
				),
				'testi-' . $index
			);
		}
		return self::section(
			$cols,
			array(
				'layout'        => 'full_width',
				'content_width' => 'boxed',
				'gap'           => 'extended',
				'padding'       => self::padding( (int) ( $memory['spacing']['section_y'] ?? 80 ), 24, (int) ( $memory['spacing']['section_y'] ?? 80 ), 24 ),
			),
			'testi-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_icon_grid( $content, $memory, $index ) {
		$content['items'] = isset( $content['items'] ) ? $content['items'] : array();
		return self::section_bento( $content, $memory, $index );
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_logo_strip( $content, $memory, $index ) {
		$text    = self::color_settings( 'muted' );
		$widgets = array();
		if ( ! empty( $content['heading'] ) ) {
			$widgets[] = self::widget_heading( (string) $content['heading'], 'h3', $text, 18 );
		}
		$widgets[] = self::widget_divider();
		$widgets[] = self::widget_text(
			'<p style="letter-spacing:.08em;text-transform:uppercase;opacity:.7">' . esc_html( (string) ( $content['text'] ?? $content['subheading'] ?? '' ) ) . '</p>',
			8
		);
		return self::section(
			array( self::column( 100, $widgets, array( 'align' => 'center' ) ) ),
			array(
				'layout'        => 'full_width',
				'content_width' => 'boxed',
				'padding'       => self::padding( 40, 24, 40, 24 ),
			),
			'logos-' . $index
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @return array<string,mixed>
	 */
	private static function section_split( $content, $memory, $index ) {
		$text   = self::color_settings( 'text' );
		$primary = self::color_settings( 'primary' );
		$flip   = ! empty( $content['flip'] ) || ( 1 === ( $index % 2 ) );
		$img_id = self::resolve_image_id( $content );
		$left   = array();
		$right  = array();

		$text_col = array();
		if ( ! empty( $content['eyebrow'] ) ) {
			$text_col[] = self::widget_heading( (string) $content['eyebrow'], 'h6', self::color_settings( 'accent' ), 13 );
		}
		$text_col[] = self::widget_heading( (string) ( $content['heading'] ?? '' ), 'h2', $text, 34 );
		$html = (string) ( $content['html'] ?? '' );
		if ( '' !== $html ) {
			$text_col[] = self::widget_html( $html . ( ! empty( $content['css'] ) ? '<style>' . WebinoCRM_AI_Elementor_Sanitize::css( (string) $content['css'] ) . '</style>' : '' ), 'wai-p', 'split-html' );
		} elseif ( ! empty( $content['text'] ) || ! empty( $content['subheading'] ) ) {
			$text_col[] = self::widget_text( '<p>' . esc_html( (string) ( $content['text'] ?? $content['subheading'] ?? '' ) ) . '</p>', 16 );
		}
		if ( ! empty( $content['cta_text'] ) ) {
			$text_col[] = self::widget_button( (string) $content['cta_text'], (string) ( $content['cta_url'] ?? '#' ), $primary, $memory );
		}

		$media_col = array();
		if ( $img_id > 0 ) {
			$media_col[] = self::widget_image( $img_id, (string) ( $content['image_alt'] ?? $content['heading'] ?? '' ) );
		} else {
			$media_col[] = self::widget_html( self::cinematic_orb_html( $memory ), 'wai-p', 'split-orb' );
		}

		$cols = $flip
			? array( self::column( 48, $media_col ), self::column( 52, $text_col, array( 'content_position' => 'center' ) ) )
			: array( self::column( 52, $text_col, array( 'content_position' => 'center' ) ), self::column( 48, $media_col ) );

		return self::section(
			$cols,
			array(
				'layout'               => 'full_width',
				'content_width'        => 'boxed',
				'content_width_custom' => array( 'unit' => 'px', 'size' => 1200 ),
				'gap'                  => 'extended',
				'padding'              => self::padding( (int) ( $memory['spacing']['section_y'] ?? 80 ), 24, (int) ( $memory['spacing']['section_y'] ?? 80 ), 24 ),
			),
			'split-' . $index
		);
	}

	/**
	 * @param array<int,mixed>    $columns Columns.
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $css_id CSS id.
	 * @return array<string,mixed>
	 */
	private static function section( $columns, $settings, $css_id = '' ) {
		if ( '' !== $css_id ) {
			$settings['css_id'] = sanitize_html_class( $css_id );
		}
		return array(
			'id'       => self::uid(),
			'elType'   => 'section',
			'isInner'  => false,
			'settings' => $settings,
			'elements' => array_values( $columns ),
		);
	}

	/**
	 * @param array<int,mixed>    $columns Columns.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	private static function inner_section( $columns, $settings = array() ) {
		return array(
			'id'       => self::uid(),
			'elType'   => 'section',
			'isInner'  => true,
			'settings' => $settings,
			'elements' => array_values( $columns ),
		);
	}

	/**
	 * @param int                 $width Width percent.
	 * @param array<int,mixed>    $widgets Widgets.
	 * @param array<string,mixed> $extra Extra settings.
	 * @return array<string,mixed>
	 */
	private static function column( $width, $widgets, $extra = array() ) {
		$settings = array_merge(
			array(
				'_column_size' => (int) $width,
				'_inline_size' => null,
			),
			$extra
		);
		return array(
			'id'       => self::uid(),
			'elType'   => 'column',
			'settings' => $settings,
			'elements' => array_values( $widgets ),
		);
	}

	/**
	 * @param string              $title Title.
	 * @param string              $tag Tag.
	 * @param array<string,mixed> $color Color binding.
	 * @param int                 $size Size.
	 * @return array<string,mixed>
	 */
	private static function widget_heading( $title, $tag, $color, $size ) {
		$settings = array(
			'title'            => $title,
			'header_size'      => $tag,
			'align'            => 'inherit',
			'title_color'      => $color['color'],
			'typography_typography' => 'custom',
			'typography_font_size'  => array( 'unit' => 'px', 'size' => (int) $size ),
			'typography_font_weight'=> '700',
		);
		if ( ! empty( $color['globals']['color'] ) ) {
			$settings['__globals__'] = array( 'title_color' => $color['globals']['color'] );
		}
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'heading',
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * @param string $html HTML.
	 * @param int    $gap Gap.
	 * @return array<string,mixed>
	 */
	private static function widget_text( $html, $gap = 0 ) {
		$settings = array(
			'editor' => wp_kses_post( $html ),
		);
		if ( $gap > 0 ) {
			$settings['_margin'] = self::padding( $gap, 0, 0, 0 );
		}
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'text-editor',
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * @param string              $text Text.
	 * @param string              $url URL.
	 * @param array<string,mixed> $color Color.
	 * @param array<string,mixed> $memory Memory.
	 * @return array<string,mixed>
	 */
	private static function widget_button( $text, $url, $color, $memory ) {
		$radius = (int) ( $memory['radius']['button'] ?? 8 );
		$style  = (string) ( $memory['button_style'] ?? 'solid' );
		$settings = array(
			'text'            => $text,
			'link'            => array( 'url' => esc_url_raw( $url ), 'is_external' => '', 'nofollow' => '' ),
			'size'            => 'md',
			'background_color'=> $color['color'],
			'button_text_color'=> '#ffffff',
			'border_radius'   => self::sides( $radius ),
			'button_type'     => 'default',
		);
		if ( 'outline' === $style ) {
			$settings['background_color']   = 'rgba(0,0,0,0)';
			$settings['button_text_color']  = $color['color'];
			$settings['border_border']      = 'solid';
			$settings['border_width']       = self::sides( 2 );
			$settings['border_color']       = $color['color'];
		} elseif ( 'soft' === $style ) {
			$settings['background_color']  = self::color_settings( 'surface' )['color'];
			$settings['button_text_color'] = $color['color'];
		}
		if ( ! empty( $color['globals']['color'] ) && 'outline' !== $style && 'soft' !== $style ) {
			$settings['__globals__'] = array( 'background_color' => $color['globals']['color'] );
		}
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'button',
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * @param int    $id Attachment.
	 * @param string $alt Alt.
	 * @return array<string,mixed>
	 */
	private static function widget_image( $id, $alt ) {
		$url = wp_get_attachment_image_url( (int) $id, 'large' );
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'image',
			'settings'   => array(
				'image'      => array(
					'url' => $url ? $url : '',
					'id'  => (int) $id,
					'alt' => $alt,
					'source' => 'library',
				),
				'image_size' => 'large',
				'align'      => 'center',
				'border_radius' => self::sides( 16 ),
			),
			'elements'   => array(),
		);
	}

	/**
	 * @param string              $title Title.
	 * @param string              $desc Desc.
	 * @param string              $icon Icon.
	 * @param array<string,mixed> $accent Accent.
	 * @param array<string,mixed> $text Text color.
	 * @return array<string,mixed>
	 */
	private static function widget_icon_box( $title, $desc, $icon, $accent, $text ) {
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'icon-box',
			'settings'   => array(
				'selected_icon'       => array( 'value' => $icon ? $icon : 'fas fa-check', 'library' => 'fa-solid' ),
				'title_text'          => $title,
				'description_text'    => $desc,
				'position'            => 'top',
				'primary_color'       => $accent['color'],
				'title_color'         => $text['color'],
				'description_color'   => self::color_settings( 'muted' )['color'],
			),
			'elements'   => array(),
		);
	}

	/**
	 * @param float               $number Number.
	 * @param string              $title Title.
	 * @param array<string,mixed> $accent Accent.
	 * @param array<string,mixed> $text Text.
	 * @return array<string,mixed>
	 */
	private static function widget_counter( $number, $title, $accent, $text ) {
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'counter',
			'settings'   => array(
				'starting_number' => 0,
				'ending_number'   => $number,
				'title'           => $title,
				'number_color'    => '#ffffff',
				'title_color'     => '#ffffff',
			),
			'elements'   => array(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function widget_divider() {
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => 'divider',
			'settings'   => array(
				'style' => 'solid',
				'weight'=> array( 'unit' => 'px', 'size' => 1 ),
				'color' => self::color_settings( 'surface' )['color'],
				'width' => array( 'unit' => '%', 'size' => 40 ),
				'align' => 'center',
			),
			'elements'   => array(),
		);
	}

	/**
	 * @param string $slot Slot.
	 * @return array{color:string,globals:array<string,string>}
	 */
	private static function color_settings( $slot ) {
		return WebinoCRM_AI_Design_Memory::color_binding( $slot );
	}

	/**
	 * @param array<string,mixed> $binding Binding.
	 * @param string              $key Settings key.
	 * @return array<string,mixed>
	 */
	private static function maybe_globals( $binding, $key ) {
		if ( empty( $binding['globals']['color'] ) ) {
			return array();
		}
		return array(
			'__globals__' => array( $key => $binding['globals']['color'] ),
		);
	}

	/**
	 * @param int $t Top.
	 * @param int $r Right.
	 * @param int $b Bottom.
	 * @param int $l Left.
	 * @return array<string,mixed>
	 */
	private static function padding( $t, $r, $b, $l ) {
		return array(
			'unit'     => 'px',
			'top'      => (string) $t,
			'right'    => (string) $r,
			'bottom'   => (string) $b,
			'left'     => (string) $l,
			'isLinked' => false,
		);
	}

	/**
	 * @param int $n Size.
	 * @return array<string,mixed>
	 */
	private static function sides( $n ) {
		$v = (string) (int) $n;
		return array(
			'unit'     => 'px',
			'top'      => $v,
			'right'    => $v,
			'bottom'   => $v,
			'left'     => $v,
			'isLinked' => true,
		);
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @return int
	 */
	private static function resolve_image_id( $content ) {
		if ( ! empty( $content['image_id'] ) ) {
			return (int) $content['image_id'];
		}
		// Prefer a recent media image from the library.
		$q = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		return $q ? (int) $q[0] : 0;
	}

	/**
	 * @param array<string,mixed> $blueprint Blueprint.
	 * @return array<int,mixed>
	 */
	private static function imported_templates( $blueprint ) {
		$ids = isset( $blueprint['import_template_ids'] ) && is_array( $blueprint['import_template_ids'] )
			? $blueprint['import_template_ids']
			: array();
		$out = array();
		foreach ( $ids as $tid ) {
			$els = WebinoCRM_AI_Elementor_Catalog::template_elements( (int) $tid );
			foreach ( $els as $el ) {
				if ( is_array( $el ) ) {
					$out[] = $el;
				}
			}
		}
		return WebinoCRM_AI_Elementor_Sanitize::strip_fonts( $out );
	}

	/**
	 * @param array<string,mixed> $section Section.
	 * @param array<string,mixed> $memory Memory.
	 * @param int                 $index Index.
	 * @param string              $scope CSS scope class.
	 * @return array<string,mixed>
	 */
	private static function build_visual_section( $section, $memory, $index, $scope ) {
		$layout  = isset( $section['layout'] ) && is_array( $section['layout'] ) ? $section['layout'] : array();
		$blocks  = isset( $section['blocks'] ) && is_array( $section['blocks'] ) ? $section['blocks'] : array();
		$content = isset( $section['content'] ) && is_array( $section['content'] ) ? $section['content'] : array();
		$cols_def = isset( $layout['columns'] ) && is_array( $layout['columns'] ) && $layout['columns']
			? array_map( 'intval', $layout['columns'] )
			: array( 100 );
		if ( count( $cols_def ) < 1 ) {
			$cols_def = array( 100 );
		}
		$sum = array_sum( $cols_def );
		if ( $sum <= 0 ) {
			$cols_def = array( 100 );
			$sum      = 100;
		}
		$buckets = array();
		foreach ( $cols_def as $ci => $w ) {
			$buckets[ $ci ] = array();
		}

		foreach ( $blocks as $bi => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$col = (int) ( $block['column'] ?? 0 );
			if ( $col < 0 || $col >= count( $cols_def ) ) {
				$col = 0;
			}
			$widget = self::block_to_widget( $block, $memory, $scope, $index . '-' . $bi );
			if ( $widget ) {
				$buckets[ $col ][] = $widget;
			}
		}

		if ( empty( $blocks ) && $content ) {
			$buckets[0][] = self::widget_html( self::cinematic_fallback_html( $content, $memory ), $scope, 'fb-' . $index );
		}

		$columns = array();
		foreach ( $cols_def as $ci => $w ) {
			$width     = (int) max( 10, round( ( $w / $sum ) * 100 ) );
			$columns[] = self::column( $width, $buckets[ $ci ], array( 'content_position' => 'center' ) );
		}

		$pad_y    = (int) ( $memory['spacing']['section_y'] ?? 80 );
		$min_h    = (int) ( $layout['min_height'] ?? 0 );
		$bg       = isset( $layout['background'] ) && is_array( $layout['background'] ) ? $layout['background'] : array();
		$settings = array(
			'layout'               => ! empty( $layout['full_width'] ) || ! isset( $layout['full_width'] ) ? 'full_width' : 'boxed',
			'content_width'        => 'boxed',
			'content_width_custom' => array( 'unit' => 'px', 'size' => 1200 ),
			'gap'                  => 'extended',
			'padding'              => self::padding( $pad_y, 24, $pad_y, 24 ),
		);
		if ( $min_h > 0 ) {
			$settings['min_height'] = array( 'unit' => 'px', 'size' => $min_h );
		}
		if ( ! empty( $layout['overlap'] ) ) {
			$settings['z_index'] = 2;
			$settings['margin']  = array(
				'unit'     => 'px',
				'top'      => -48,
				'right'    => 0,
				'bottom'   => 0,
				'left'     => 0,
				'isLinked' => false,
			);
		}
		$bg_type = sanitize_key( (string) ( $bg['type'] ?? 'classic' ) );
		if ( 'gradient' === $bg_type ) {
			$settings['background_background']      = 'gradient';
			$settings['background_color']           = (string) ( $bg['color'] ?? self::color_settings( 'primary' )['color'] );
			$settings['background_color_b']         = (string) ( $bg['color_b'] ?? self::color_settings( 'accent' )['color'] );
			$settings['background_gradient_angle']  = array( 'unit' => 'deg', 'size' => 135 );
		} else {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = (string) ( $bg['color'] ?? self::color_settings( 'bg' )['color'] );
		}
		$img_id = (int) ( $bg['image_id'] ?? 0 );
		if ( $img_id > 0 ) {
			$url = wp_get_attachment_image_url( $img_id, 'full' );
			if ( $url ) {
				$settings['background_background'] = 'classic';
				$settings['background_image']      = array( 'url' => $url, 'id' => $img_id );
				$settings['background_size']       = 'cover';
				$settings['background_overlay_background'] = 'classic';
				$settings['background_overlay_color']      = self::color_settings( 'primary' )['color'];
				$ov = isset( $bg['overlay'] ) ? (float) $bg['overlay'] : 0.35;
				$settings['background_overlay_opacity'] = array( 'unit' => 'px', 'size' => max( 0, min( 1, $ov ) ) );
			}
		}
		$video_url = (string) ( $bg['video_url'] ?? '' );
		if ( 'video' === $bg_type && '' !== trim( $video_url ) ) {
			$settings['background_background'] = 'video';
			$settings['background_video_link'] = esc_url_raw( $video_url );
			$settings['background_play_once']  = '';
			$settings['background_play_on_mobile'] = 'yes';
		}

		return self::section( $columns, $settings, 'vis-' . $index );
	}

	/**
	 * @param array<string,mixed> $block Block.
	 * @param array<string,mixed> $memory Memory.
	 * @param string              $scope Scope.
	 * @param string              $key Unique key.
	 * @return array<string,mixed>|null
	 */
	private static function block_to_widget( $block, $memory, $scope, $key ) {
		$widget = sanitize_key( str_replace( '_', '-', (string) ( $block['widget'] ?? 'html' ) ) );
		$st     = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$html   = (string) ( $block['html'] ?? $st['html'] ?? '' );
		$css    = WebinoCRM_AI_Elementor_Sanitize::css( (string) ( $block['css'] ?? $st['css'] ?? '' ) );
		$text   = self::color_settings( 'text' );
		$accent = self::color_settings( 'accent' );
		$primary = self::color_settings( 'primary' );

		$installed = WebinoCRM_AI_Elementor_Catalog::installed_widget_names();
		$buildable = WebinoCRM_AI_Elementor_Catalog::buildable_widgets();
		if ( ! in_array( $widget, $buildable, true ) || ( $installed && ! in_array( $widget, $installed, true ) && 'html' !== $widget ) ) {
			$widget = 'html';
		}

		switch ( $widget ) {
			case 'heading':
				return self::widget_heading( (string) ( $st['title'] ?? $st['heading'] ?? $st['text'] ?? '' ), (string) ( $st['tag'] ?? 'h2' ), $text, (int) ( $st['size'] ?? 32 ) );
			case 'text-editor':
				$body = $html ? $html : (string) ( $st['text'] ?? $st['content'] ?? '' );
				return self::widget_text( $body, 0 );
			case 'button':
				return self::widget_button( (string) ( $st['title'] ?? $st['text'] ?? 'CTA' ), (string) ( $st['url'] ?? $st['cta_url'] ?? '#' ), $primary, $memory );
			case 'image':
				$id = (int) ( $st['image_id'] ?? $block['image_id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = self::resolve_image_id( $st );
				}
				if ( $id > 0 ) {
					return self::widget_image( $id, (string) ( $st['image_alt'] ?? $st['alt'] ?? '' ) );
				}
				return self::widget_html( self::cinematic_orb_html( $memory ), $scope, 'img-' . $key );
			case 'icon-box':
				return self::widget_icon_box( (string) ( $st['title'] ?? '' ), (string) ( $st['text'] ?? '' ), (string) ( $st['icon'] ?? 'fas fa-star' ), $accent, $text );
			case 'counter':
				$num = preg_replace( '/[^0-9.]/', '', (string) ( $st['number'] ?? $st['title'] ?? '0' ) );
				return self::widget_counter( (float) $num, (string) ( $st['text'] ?? $st['title'] ?? '' ), $accent, $text );
			case 'divider':
				return self::widget_divider();
			case 'spacer':
				return self::el_widget(
					'spacer',
					array(
						'space' => array( 'unit' => 'px', 'size' => (int) ( $st['size'] ?? 40 ) ),
					)
				);
			case 'video':
				$url = (string) ( $st['url'] ?? $st['youtube_url'] ?? '' );
				return self::el_widget(
					'video',
					array(
						'youtube_url' => $url,
						'image_overlay' => array(),
					)
				);
			case 'star-rating':
				return self::el_widget(
					'star-rating',
					array(
						'rating'          => (float) ( $st['number'] ?? 5 ),
						'title'           => (string) ( $st['title'] ?? '' ),
						'star_style'      => 'star-fontawesome',
						'unmarked_color'  => self::color_settings( 'muted' )['color'],
					)
				);
			case 'alert':
				return self::el_widget(
					'alert',
					array(
						'alert_title'   => (string) ( $st['title'] ?? '' ),
						'alert_description' => (string) ( $st['text'] ?? '' ),
					)
				);
			case 'progress':
				return self::el_widget(
					'progress',
					array(
						'title'          => (string) ( $st['title'] ?? '' ),
						'percent'        => array( 'size' => (float) ( $st['number'] ?? 80 ), 'unit' => '%' ),
						'bar_color'      => $accent['color'],
					)
				);
			case 'accordion':
			case 'nested-accordion':
			case 'toggle':
				return self::widget_accordion_like( $widget === 'toggle' ? 'toggle' : 'accordion', $st, $block );
			case 'tabs':
			case 'nested-tabs':
				return self::widget_accordion_like( 'tabs', $st, $block );
			case 'testimonial':
				return self::el_widget(
					'testimonial',
					array(
						'testimonial_content' => (string) ( $st['text'] ?? '' ),
						'testimonial_name'    => (string) ( $st['name'] ?? $st['title'] ?? '' ),
						'testimonial_job'     => (string) ( $st['role'] ?? '' ),
					)
				);
			case 'icon-list':
				$items = isset( $st['items'] ) && is_array( $st['items'] ) ? $st['items'] : array();
				$icon_items = array();
				foreach ( $items as $it ) {
					if ( ! is_array( $it ) ) {
						continue;
					}
					$icon_items[] = array(
						'text'          => (string) ( $it['title'] ?? $it['text'] ?? '' ),
						'selected_icon' => array( 'value' => (string) ( $it['icon'] ?? 'fas fa-check' ), 'library' => 'fa-solid' ),
					);
				}
				return self::el_widget( 'icon-list', array( 'icon_list' => $icon_items ) );
			case 'image-carousel':
			case 'image-gallery':
				$ids = isset( $st['gallery_ids'] ) && is_array( $st['gallery_ids'] ) ? $st['gallery_ids'] : array();
				$gallery = array();
				foreach ( $ids as $gid ) {
					$gid = (int) $gid;
					$url = $gid ? wp_get_attachment_image_url( $gid, 'large' ) : '';
					if ( $url ) {
						$gallery[] = array( 'id' => $gid, 'url' => $url );
					}
				}
				if ( ! $gallery ) {
					return self::widget_html( self::cinematic_orb_html( $memory ), $scope, 'gal-' . $key );
				}
				$type = 'image-carousel' === $widget ? 'image-carousel' : 'image-gallery';
				return self::el_widget( $type, array( 'carousel' => $gallery, 'gallery' => $gallery, 'wp_gallery' => $gallery ) );
			case 'social-icons':
				return self::el_widget(
					'social-icons',
					array(
						'social_icon_list' => array(
							array( 'social_icon' => array( 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ) ),
							array( 'social_icon' => array( 'value' => 'fab fa-linkedin', 'library' => 'fa-brands' ) ),
						),
					)
				);
			case 'price-table':
				return self::el_widget(
					'price-table',
					array(
						'heading'      => (string) ( $st['title'] ?? '' ),
						'sub_heading'  => (string) ( $st['text'] ?? '' ),
						'price'        => (string) ( $st['number'] ?? '' ),
					)
				);
			case 'icon':
				return self::el_widget(
					'icon',
					array(
						'selected_icon' => array( 'value' => (string) ( $st['icon'] ?? 'fas fa-star' ), 'library' => 'fa-solid' ),
						'primary_color' => $accent['color'],
					)
				);
			case 'image-box':
				$id = (int) ( $st['image_id'] ?? 0 );
				$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
				return self::el_widget(
					'image-box',
					array(
						'image'            => array( 'url' => $url ? $url : '', 'id' => $id ),
						'title_text'       => (string) ( $st['title'] ?? '' ),
						'description_text' => (string) ( $st['text'] ?? '' ),
					)
				);
			case 'inner-section':
			case 'form':
			default:
				if ( '' === $html ) {
					$html = self::cinematic_fallback_html( array_merge( $st, array( 'heading' => $st['title'] ?? '' ) ), $memory );
				}
				if ( '' !== $css ) {
					$html = '<style>' . $css . '</style>' . $html;
				}
				return self::widget_html( $html, $scope, 'b-' . $key );
		}
	}

	/**
	 * @param string              $type accordion|tabs|toggle.
	 * @param array<string,mixed> $st Settings.
	 * @param array<string,mixed> $block Block.
	 * @return array<string,mixed>
	 */
	private static function widget_accordion_like( $type, $st, $block ) {
		$items = isset( $st['items'] ) && is_array( $st['items'] ) ? $st['items'] : array();
		if ( ! $items && isset( $block['settings']['items'] ) ) {
			$items = $block['settings']['items'];
		}
		$tabs = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$tabs[] = array(
				'tab_title'   => (string) ( $it['question'] ?? $it['title'] ?? '' ),
				'tab_content' => (string) ( $it['answer'] ?? $it['text'] ?? '' ),
			);
		}
		if ( ! $tabs ) {
			$tabs[] = array( 'tab_title' => (string) ( $st['title'] ?? 'FAQ' ), 'tab_content' => (string) ( $st['text'] ?? '' ) );
		}
		return self::el_widget( $type, array( 'tabs' => $tabs ) );
	}

	/**
	 * @param string              $type Widget type.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	private static function el_widget( $type, $settings ) {
		return array(
			'id'         => self::uid(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * @param string $html HTML.
	 * @param string $scope Scope class.
	 * @param string $block_id Block id.
	 * @return array<string,mixed>
	 */
	private static function widget_html( $html, $scope, $block_id ) {
		$block_id = sanitize_html_class( $block_id );
		$inner    = WebinoCRM_AI_Elementor_Sanitize::html( (string) $html );
		$wrapped  = '<div class="' . esc_attr( $scope ) . '"><div class="wai-b ' . esc_attr( $block_id ) . '">' . $inner . '</div></div>';
		return self::el_widget( 'html', array( 'html' => $wrapped ) );
	}

	/**
	 * @param array<string,mixed> $memory Memory.
	 * @return string
	 */
	private static function cinematic_orb_html( $memory ) {
		$p = esc_attr( (string) ( $memory['palette']['primary'] ?? '#0f172a' ) );
		$a = esc_attr( (string) ( $memory['palette']['accent'] ?? '#e11d48' ) );
		$s = esc_attr( (string) ( $memory['palette']['surface'] ?? '#f8fafc' ) );
		$css = WebinoCRM_AI_Elementor_Sanitize::css(
			'.wai-orb{position:relative;min-height:320px;border-radius:28px;overflow:hidden;background:radial-gradient(circle at 30% 20%,' . $a . ' 0%,transparent 42%),linear-gradient(135deg,' . $p . ',' . $s . ');box-shadow:0 30px 80px rgba(15,23,42,.18)}'
			. '.wai-orb:before{content:"";position:absolute;inset:18% 12%;border-radius:999px;background:rgba(255,255,255,.12);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.25)}'
			. '@keyframes wai-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}'
			. '.wai-orb{animation:wai-float 8s ease-in-out infinite}'
		);
		return '<style>' . $css . '</style><div class="wai-orb" aria-hidden="true"></div>';
	}

	/**
	 * @param array<string,mixed> $content Content.
	 * @param array<string,mixed> $memory Memory.
	 * @return string
	 */
	private static function cinematic_fallback_html( $content, $memory ) {
		$p = esc_attr( (string) ( $memory['palette']['primary'] ?? '#0f172a' ) );
		$a = esc_attr( (string) ( $memory['palette']['accent'] ?? '#e11d48' ) );
		$t = esc_html( (string) ( $content['heading'] ?? $content['title'] ?? '' ) );
		$d = esc_html( (string) ( $content['subheading'] ?? $content['text'] ?? '' ) );
		$css = WebinoCRM_AI_Elementor_Sanitize::css(
			'.wai-glass{padding:48px 40px;border-radius:24px;background:linear-gradient(160deg,rgba(255,255,255,.16),rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.2);box-shadow:0 24px 60px rgba(15,23,42,.16)}'
			. '.wai-glass h2{margin:0 0 12px;font-size:clamp(28px,4vw,48px);color:' . $p . '}'
			. '.wai-glass p{margin:0;opacity:.85;color:' . $a . '}'
		);
		return '<style>' . $css . '</style><div class="wai-glass"><h2>' . $t . '</h2><p>' . $d . '</p></div>';
	}

	/**
	 * @return string
	 */
	private static function uid() {
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 7 );
	}
}
