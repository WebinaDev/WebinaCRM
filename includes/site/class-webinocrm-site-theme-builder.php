<?php
/**
 * Elementor Theme Builder templates (header/footer/archive/404).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Theme_Builder {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_query_vars' ) );
	}

	/**
	 * Register portfolio filter query vars.
	 */
	public static function register_query_vars() {
		add_rewrite_tag( '%portfolio_service%', '([^&]+)' );
		add_rewrite_tag( '%portfolio_industry%', '([^&]+)' );
	}

	/**
	 * Seed Theme Builder templates.
	 *
	 * @param bool $force Overwrite Elementor data on existing templates.
	 */
	public static function seed_templates( $force = false ) {
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return;
		}

		self::create_template(
			'Webina Header',
			'header',
			WebinoCRM_Site_Elementor_Builder::build_header(),
			array(),
			$force
		);
		self::create_template(
			'Webina Footer',
			'footer',
			WebinoCRM_Site_Elementor_Builder::build_footer(),
			array(),
			$force
		);
		self::create_template(
			'Webina 404',
			'error-404',
			WebinoCRM_Site_Elementor_Builder::build_404(),
			array(),
			$force
		);
		self::create_template(
			'Portfolio Archive',
			'archive',
			WebinoCRM_Site_Elementor_Builder::build_portfolio_archive(),
			array( 'post_type' => WebinoCRM_Site_Portfolio::POST_TYPE ),
			$force
		);
		self::create_template(
			'Portfolio Single',
			'single',
			WebinoCRM_Site_Elementor_Builder::build_portfolio_single(),
			array( 'post_type' => WebinoCRM_Site_Portfolio::POST_TYPE ),
			$force
		);
	}

	/**
	 * @param string              $title Title.
	 * @param string              $type Template type.
	 * @param array<int,mixed>    $data Elementor data.
	 * @param array<string,mixed> $conditions Conditions.
	 * @param bool                $force Force overwrite.
	 */
	private static function create_template( $title, $type, $data, $conditions = array(), $force = false ) {
		$existing = WebinoCRM_Site_Seeder::find_by_title( $title, 'elementor_library' );
		$cond     = self::build_conditions( $type, $conditions );

		if ( $existing ) {
			$post_id = (int) $existing->ID;
			if ( $force || '' === (string) get_post_meta( $post_id, '_elementor_data', true ) ) {
				update_post_meta( $post_id, '_elementor_template_type', $type );
				update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			}
			if ( $force ) {
				update_post_meta( $post_id, '_elementor_conditions', $cond );
			}
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_elementor_template_type', $type );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		update_post_meta( $post_id, '_elementor_conditions', $cond );
		return $post_id;
	}

	/**
	 * @param string              $type Template type.
	 * @param array<string,mixed> $conditions Extra conditions (unused; Pro expects include/general).
	 * @return array<int,string>
	 */
	private static function build_conditions( $type, $conditions = array() ) {
		// Elementor Pro Theme Builder expects string tokens like "include/general".
		unset( $type, $conditions );
		return array( 'include/general' );
	}
}
