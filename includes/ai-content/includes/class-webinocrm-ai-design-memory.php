<?php
/**
 * Persistent visual design memory for AI page generation.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand palette + layout tokens reused across Elementor pages.
 */
final class WebinoCRM_AI_Design_Memory {

	const OPTION = 'webinocrm_ai_design_memory';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'palette'           => array(
				'primary'   => '#0a0a0b',
				'secondary' => '#18181b',
				'accent'    => '#c9a227',
				'bg'        => '#09090b',
				'surface'   => '#141416',
				'text'      => '#fafafa',
				'muted'     => '#a1a1aa',
			),
			'kit_color_ids'     => array(
				'primary'   => '',
				'secondary' => '',
				'accent'    => '',
				'bg'        => '',
				'surface'   => '',
				'text'      => '',
				'muted'     => '',
			),
			'radius'            => array(
				'button'  => 8,
				'card'    => 16,
				'section' => 0,
			),
			'spacing'           => array(
				'section_y' => 80,
				'gap'       => 24,
			),
			'shadow'            => '0 18px 50px rgba(15,23,42,0.12)',
			'button_style'      => 'solid',
			'approved_archetypes' => array(
				'hero_cinematic',
				'bento_features',
				'stats_strip',
				'timeline',
				'faq_accordion',
				'cta_fullbleed',
				'testimonials',
				'image_text_split',
				'icon_grid',
				'logo_strip',
			),
			'source'            => '',
			'locked'            => false,
			'updated_at'        => '',
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
		$merged['palette']       = self::sanitize_palette( isset( $stored['palette'] ) && is_array( $stored['palette'] ) ? $stored['palette'] : $defaults['palette'] );
		$merged['kit_color_ids'] = self::sanitize_kit_ids( isset( $stored['kit_color_ids'] ) && is_array( $stored['kit_color_ids'] ) ? $stored['kit_color_ids'] : $defaults['kit_color_ids'] );
		$merged['radius']        = self::sanitize_radius( isset( $stored['radius'] ) && is_array( $stored['radius'] ) ? $stored['radius'] : $defaults['radius'] );
		$merged['spacing']       = self::sanitize_spacing( isset( $stored['spacing'] ) && is_array( $stored['spacing'] ) ? $stored['spacing'] : $defaults['spacing'] );
		$merged['shadow']        = sanitize_text_field( (string) ( $merged['shadow'] ?? $defaults['shadow'] ) );
		$merged['button_style']  = in_array( (string) ( $merged['button_style'] ?? '' ), array( 'solid', 'outline', 'soft' ), true )
			? (string) $merged['button_style']
			: 'solid';
		$merged['approved_archetypes'] = self::sanitize_archetypes(
			isset( $stored['approved_archetypes'] ) && is_array( $stored['approved_archetypes'] )
				? $stored['approved_archetypes']
				: $defaults['approved_archetypes']
		);
		$merged['source']  = sanitize_key( (string) ( $merged['source'] ?? '' ) );
		$merged['locked']  = ! empty( $merged['locked'] );
		$merged['updated_at'] = sanitize_text_field( (string) ( $merged['updated_at'] ?? '' ) );
		return $merged;
	}

	/**
	 * Compact JSON for prompts (no font keys ever).
	 *
	 * @return array<string,mixed>
	 */
	public static function for_prompt() {
		$m = self::get();
		return array(
			'palette'             => $m['palette'],
			'radius'              => $m['radius'],
			'spacing'             => $m['spacing'],
			'shadow'              => $m['shadow'],
			'button_style'        => $m['button_style'],
			'approved_archetypes' => $m['approved_archetypes'],
			'source'              => $m['source'],
			'locked'              => $m['locked'],
			'note'                => 'Never set font_family. Use site theme fonts. Reuse this palette on every page.',
		);
	}

	/**
	 * @param array<string,mixed> $input Raw.
	 * @param bool                $force Allow overwrite when locked.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save( $input, $force = false ) {
		$cur = self::get();
		if ( ! empty( $cur['locked'] ) && ! $force ) {
			return new WP_Error( 'ai_design_locked', __( 'Design memory is locked. Unlock or reset before changing.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$raw = is_array( $input ) ? $input : array();
		$out = $cur;

		if ( isset( $raw['palette'] ) && is_array( $raw['palette'] ) ) {
			$out['palette'] = self::sanitize_palette( array_merge( $cur['palette'], $raw['palette'] ) );
		}
		if ( isset( $raw['kit_color_ids'] ) && is_array( $raw['kit_color_ids'] ) ) {
			$out['kit_color_ids'] = self::sanitize_kit_ids( array_merge( $cur['kit_color_ids'], $raw['kit_color_ids'] ) );
		}
		if ( isset( $raw['radius'] ) && is_array( $raw['radius'] ) ) {
			$out['radius'] = self::sanitize_radius( array_merge( $cur['radius'], $raw['radius'] ) );
		}
		if ( isset( $raw['spacing'] ) && is_array( $raw['spacing'] ) ) {
			$out['spacing'] = self::sanitize_spacing( array_merge( $cur['spacing'], $raw['spacing'] ) );
		}
		if ( array_key_exists( 'shadow', $raw ) ) {
			$out['shadow'] = sanitize_text_field( (string) $raw['shadow'] );
		}
		if ( isset( $raw['button_style'] ) ) {
			$bs = sanitize_key( (string) $raw['button_style'] );
			$out['button_style'] = in_array( $bs, array( 'solid', 'outline', 'soft' ), true ) ? $bs : 'solid';
		}
		if ( isset( $raw['approved_archetypes'] ) && is_array( $raw['approved_archetypes'] ) ) {
			$out['approved_archetypes'] = self::sanitize_archetypes( $raw['approved_archetypes'] );
		}
		if ( isset( $raw['source'] ) ) {
			$src = sanitize_key( (string) $raw['source'] );
			$out['source'] = in_array( $src, array( 'extracted', 'suggested', '' ), true ) ? $src : $out['source'];
		}
		if ( array_key_exists( 'locked', $raw ) ) {
			$out['locked'] = (bool) $raw['locked'];
		}
		$out['updated_at'] = current_time( 'mysql', true );

		update_option( self::OPTION, $out, false );
		return self::get();
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function reset() {
		delete_option( self::OPTION );
		return self::get();
	}

	/**
	 * Lock after first successful page design.
	 *
	 * @return void
	 */
	public static function lock_after_success() {
		$m = self::get();
		if ( ! empty( $m['locked'] ) ) {
			return;
		}
		$m['locked']     = true;
		$m['updated_at'] = current_time( 'mysql', true );
		update_option( self::OPTION, $m, false );
	}

	/**
	 * Extract colors from Elementor active kit.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function extract_from_elementor_kit() {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return new WP_Error( 'no_elementor', __( 'Elementor is not active.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$kits = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits || ! method_exists( $kits, 'get_active_kit' ) ) {
			return new WP_Error( 'no_kit', __( 'Elementor kit not available.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$kit = $kits->get_active_kit();
		if ( ! $kit ) {
			return new WP_Error( 'no_kit', __( 'Elementor kit not available.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$settings = method_exists( $kit, 'get_settings_for_display' )
			? $kit->get_settings_for_display()
			: ( method_exists( $kit, 'get_settings' ) ? $kit->get_settings() : array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$colors = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
			if ( empty( $settings[ $bucket ] ) || ! is_array( $settings[ $bucket ] ) ) {
				continue;
			}
			foreach ( $settings[ $bucket ] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id    = sanitize_text_field( (string) ( $row['_id'] ?? '' ) );
				$title = strtolower( (string) ( $row['title'] ?? '' ) );
				$color = self::normalize_hex( (string) ( $row['color'] ?? '' ) );
				if ( '' === $id || '' === $color ) {
					continue;
				}
				$colors[] = array(
					'id'    => $id,
					'title' => $title,
					'color' => $color,
				);
			}
		}

		if ( ! $colors ) {
			return new WP_Error( 'no_palette', __( 'No colors found in Elementor kit.', 'webinocrm' ), array( 'status' => 404 ) );
		}

		$palette = self::defaults()['palette'];
		$kit_ids = self::defaults()['kit_color_ids'];
		$map     = array(
			'primary'   => array( 'primary', 'brand', 'main' ),
			'secondary' => array( 'secondary', 'second' ),
			'accent'    => array( 'accent', 'highlight', 'cta' ),
			'bg'        => array( 'background', 'bg', 'white' ),
			'surface'   => array( 'surface', 'card', 'muted bg' ),
			'text'      => array( 'text', 'foreground', 'dark' ),
			'muted'     => array( 'muted', 'subtle', 'gray', 'grey' ),
		);

		foreach ( $map as $slot => $needles ) {
			foreach ( $colors as $c ) {
				foreach ( $needles as $n ) {
					if ( false !== strpos( $c['title'], $n ) ) {
						$palette[ $slot ] = $c['color'];
						$kit_ids[ $slot ] = $c['id'];
						continue 3;
					}
				}
			}
		}

		// Fallbacks by index if titles did not match.
		$slots = array_keys( $palette );
		$i     = 0;
		foreach ( $colors as $c ) {
			if ( $i >= count( $slots ) ) {
				break;
			}
			$slot = $slots[ $i ];
			if ( empty( $kit_ids[ $slot ] ) ) {
				$palette[ $slot ] = $c['color'];
				$kit_ids[ $slot ] = $c['id'];
			}
			++$i;
		}

		return self::save(
			array(
				'palette'       => $palette,
				'kit_color_ids' => $kit_ids,
				'source'        => 'extracted',
				'locked'        => false,
			),
			true
		);
	}

	/**
	 * Apply suggested palette from AI into memory (and optionally kit custom colors).
	 *
	 * @param array<string,mixed> $palette Palette hex map.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function apply_suggested_palette( $palette ) {
		$clean = self::sanitize_palette( is_array( $palette ) ? $palette : array() );
		$saved = self::save(
			array(
				'palette' => $clean,
				'source'  => 'suggested',
				'locked'  => false,
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		self::push_custom_colors_to_kit( $clean );
		return self::get();
	}

	/**
	 * @param array<string,string> $palette Palette.
	 * @return void
	 */
	private static function push_custom_colors_to_kit( $palette ) {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		$kits = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits || ! method_exists( $kits, 'get_active_kit_for_frontend' ) && ! method_exists( $kits, 'get_active_kit' ) ) {
			return;
		}
		$kit = method_exists( $kits, 'get_active_kit' ) ? $kits->get_active_kit() : null;
		if ( ! $kit || ! method_exists( $kit, 'get_settings' ) || ! method_exists( $kit, 'save' ) ) {
			return;
		}

		$settings = $kit->get_settings();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$custom = isset( $settings['custom_colors'] ) && is_array( $settings['custom_colors'] ) ? $settings['custom_colors'] : array();
		$ids    = array();
		$labels = array(
			'primary'   => 'Webino Primary',
			'secondary' => 'Webino Secondary',
			'accent'    => 'Webino Accent',
			'bg'        => 'Webino BG',
			'surface'   => 'Webino Surface',
			'text'      => 'Webino Text',
			'muted'     => 'Webino Muted',
		);

		foreach ( $labels as $slot => $title ) {
			$id = 'webino_' . $slot;
			$found = false;
			foreach ( $custom as &$row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( (string) ( $row['_id'] ?? '' ) === $id || strtolower( (string) ( $row['title'] ?? '' ) ) === strtolower( $title ) ) {
					$row['color'] = $palette[ $slot ];
					$row['title'] = $title;
					$row['_id']   = $id;
					$found        = true;
					break;
				}
			}
			unset( $row );
			if ( ! $found ) {
				$custom[] = array(
					'_id'   => $id,
					'title' => $title,
					'color' => $palette[ $slot ],
				);
			}
			$ids[ $slot ] = $id;
		}

		$settings['custom_colors'] = $custom;
		try {
			$kit->save( array( 'settings' => $settings ) );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Best-effort; memory still holds hex values.
		}

		$mem = self::get();
		$mem['kit_color_ids'] = array_merge( $mem['kit_color_ids'], $ids );
		$mem['updated_at']    = current_time( 'mysql', true );
		update_option( self::OPTION, $mem, false );
	}

	/**
	 * Resolve a color for Elementor settings (prefer global).
	 *
	 * @param string $slot Palette slot.
	 * @return array{color:string,globals:array<string,string>}
	 */
	public static function color_binding( $slot ) {
		$m     = self::get();
		$slot  = sanitize_key( $slot );
		$hex   = isset( $m['palette'][ $slot ] ) ? (string) $m['palette'][ $slot ] : '#0f172a';
		$kit_id = isset( $m['kit_color_ids'][ $slot ] ) ? (string) $m['kit_color_ids'][ $slot ] : '';
		$out   = array(
			'color'   => $hex,
			'globals' => array(),
		);
		if ( '' !== $kit_id ) {
			$out['globals']['color'] = 'globals/colors?id=' . rawurlencode( $kit_id );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $palette Palette.
	 * @return array<string,string>
	 */
	public static function sanitize_palette( $palette ) {
		$defaults = self::defaults()['palette'];
		$out      = $defaults;
		foreach ( $defaults as $k => $fallback ) {
			if ( isset( $palette[ $k ] ) ) {
				$hex = self::normalize_hex( (string) $palette[ $k ] );
				$out[ $k ] = '' !== $hex ? $hex : $fallback;
			}
		}
		return $out;
	}

	/**
	 * @param string $hex Hex.
	 * @return string
	 */
	public static function normalize_hex( $hex ) {
		$hex = trim( (string) $hex );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex ) ) {
			if ( 4 === strlen( $hex ) ) {
				return '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
			}
			return strtolower( $hex );
		}
		if ( preg_match( '/^([0-9a-fA-F]{6})$/', $hex ) ) {
			return '#' . strtolower( $hex );
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $ids Kit ids.
	 * @return array<string,string>
	 */
	private static function sanitize_kit_ids( $ids ) {
		$out = self::defaults()['kit_color_ids'];
		foreach ( $out as $k => $_ ) {
			if ( isset( $ids[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $ids[ $k ] );
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $radius Radius.
	 * @return array<string,int>
	 */
	private static function sanitize_radius( $radius ) {
		$out = self::defaults()['radius'];
		foreach ( $out as $k => $_ ) {
			if ( isset( $radius[ $k ] ) ) {
				$out[ $k ] = max( 0, min( 64, (int) $radius[ $k ] ) );
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $spacing Spacing.
	 * @return array<string,int>
	 */
	private static function sanitize_spacing( $spacing ) {
		$out = self::defaults()['spacing'];
		foreach ( $out as $k => $_ ) {
			if ( isset( $spacing[ $k ] ) ) {
				$out[ $k ] = max( 0, min( 200, (int) $spacing[ $k ] ) );
			}
		}
		return $out;
	}

	/**
	 * @param array<int,mixed> $list Archetypes.
	 * @return list<string>
	 */
	public static function sanitize_archetypes( $list ) {
		$allowed = self::defaults()['approved_archetypes'];
		$out     = array();
		foreach ( (array) $list as $item ) {
			$key = sanitize_key( (string) $item );
			if ( in_array( $key, $allowed, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
		return $out ? $out : $allowed;
	}

	/**
	 * @return list<string>
	 */
	public static function all_archetypes() {
		return self::defaults()['approved_archetypes'];
	}
}
