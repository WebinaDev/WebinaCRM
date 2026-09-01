<?php
/**
 * Elementor Kit seed + design-memory sync.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Kit {

	const KIT_OPTION = 'webinocrm_site_kit_id';
	const FONT_FAMILY = 'Yekan Bakh';

	/**
	 * Light agency palette inspired by DMROOM (red accent + warm dark text).
	 *
	 * @return array<string,string>
	 */
	public static function palette() {
		return array(
			'primary'   => '#231110',
			'secondary' => '#130803',
			'accent'    => '#CA2C24',
			'bg'        => '#FFFFFF',
			'surface'   => '#F5F5F5',
			'text'      => '#231110',
			'muted'     => '#6B6B6B',
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'elementor/init', array( __CLASS__, 'ensure_kit_on_init' ), 5 );
		add_filter( 'elementor/fonts/groups', array( __CLASS__, 'register_font_group' ) );
		add_filter( 'elementor/fonts/additional_fonts', array( __CLASS__, 'register_additional_fonts' ) );
	}

	/**
	 * Custom font group label in Elementor.
	 *
	 * @param array<string,string> $groups Font groups.
	 * @return array<string,string>
	 */
	public static function register_font_group( $groups ) {
		$groups['webina'] = 'Webina';
		return $groups;
	}

	/**
	 * Register Yekan Bakh for Elementor typography controls.
	 *
	 * @param array<string,string> $fonts Fonts map.
	 * @return array<string,string>
	 */
	public static function register_additional_fonts( $fonts ) {
		$fonts[ self::FONT_FAMILY ] = 'webina';
		return $fonts;
	}

	/**
	 * Lazy kit ensure when Elementor loads.
	 */
	public static function ensure_kit_on_init() {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		self::ensure_kit();
	}

	/**
	 * Create or update Elementor kit + sync design memory.
	 *
	 * @return int Kit post ID.
	 */
	public static function ensure_kit() {
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return 0;
		}

		$kit_id = (int) get_option( self::KIT_OPTION, 0 );
		if ( $kit_id && 'elementor_library' === get_post_type( $kit_id ) ) {
			self::apply_kit_settings( $kit_id );
			self::sync_design_memory( $kit_id );
			return $kit_id;
		}

		$kit_id = wp_insert_post(
			array(
				'post_title'  => 'Webina Kit',
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_template_type' => 'kit',
				),
			),
			true
		);

		if ( is_wp_error( $kit_id ) ) {
			return 0;
		}

		update_option( self::KIT_OPTION, $kit_id );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			update_option( 'elementor_active_kit', $kit_id );
		}

		self::apply_kit_settings( $kit_id );
		self::sync_design_memory( $kit_id );
		return $kit_id;
	}

	/**
	 * @param int $kit_id Kit post ID.
	 */
	private static function apply_kit_settings( $kit_id ) {
		$palette = self::palette();
		$colors  = array(
			array(
				'_id'   => 'webina_primary',
				'title' => 'Primary',
				'color' => $palette['primary'],
			),
			array(
				'_id'   => 'webina_secondary',
				'title' => 'Secondary',
				'color' => $palette['secondary'],
			),
			array(
				'_id'   => 'webina_accent',
				'title' => 'Accent',
				'color' => $palette['accent'],
			),
			array(
				'_id'   => 'webina_bg',
				'title' => 'Background',
				'color' => $palette['bg'],
			),
			array(
				'_id'   => 'webina_surface',
				'title' => 'Surface',
				'color' => $palette['surface'],
			),
			array(
				'_id'   => 'webina_text',
				'title' => 'Text',
				'color' => $palette['text'],
			),
			array(
				'_id'   => 'webina_muted',
				'title' => 'Muted',
				'color' => $palette['muted'],
			),
		);

		$settings = array(
			'system_colors'         => $colors,
			'custom_colors'         => array(),
			'system_typography'     => array(
				array(
					'_id'                    => 'webina_heading',
					'title'                  => 'Heading',
					'typography_typography'  => 'custom',
					'typography_font_family' => self::FONT_FAMILY,
					'typography_font_weight' => '700',
				),
				array(
					'_id'                    => 'webina_body',
					'title'                  => 'Body',
					'typography_typography'  => 'custom',
					'typography_font_family' => self::FONT_FAMILY,
					'typography_font_weight' => '400',
				),
			),
			'default_generic_fonts' => self::FONT_FAMILY,
			'button_border_radius'  => array(
				'unit'  => 'px',
				'size'  => 999,
				'sizes' => array(),
				'top'   => '999',
				'right' => '999',
				'bottom'=> '999',
				'left'  => '999',
			),
			'container_width'       => array(
				'unit' => 'px',
				'size' => 1200,
			),
			'space_between_widgets' => array(
				'unit' => 'px',
				'size' => 28,
			),
		);

		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
		update_post_meta( $kit_id, '_elementor_edit_mode', 'builder' );
	}

	/**
	 * Sync AI design memory with kit palette IDs.
	 *
	 * @param int $kit_id Kit post ID.
	 */
	public static function sync_design_memory( $kit_id = 0 ) {
		if ( ! class_exists( 'WebinoCRM_AI_Design_Memory' ) ) {
			return;
		}

		$palette = self::palette();
		$memory  = WebinoCRM_AI_Design_Memory::get();

		$memory['palette'] = $palette;
		$memory['source']  = 'webina_site_kit';
		$memory['kit_color_ids'] = array(
			'primary'   => 'webina_primary',
			'secondary' => 'webina_secondary',
			'accent'    => 'webina_accent',
			'bg'        => 'webina_bg',
			'surface'   => 'webina_surface',
			'text'      => 'webina_text',
			'muted'     => 'webina_muted',
		);
		$memory['font_family'] = self::FONT_FAMILY;
		$memory['updated_at']  = gmdate( 'c' );

		update_option( WebinoCRM_AI_Design_Memory::OPTION, $memory );
	}
}
