<?php
/**
 * Register bundled Webina theme directory.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Theme {

	const THEME_SLUG = 'webina';

	/**
	 * Hooks.
	 */
	public static function init() {
		self::register_directory();
		add_action( 'after_setup_theme', array( __CLASS__, 'register_directory' ), 0 );
		add_action( 'after_switch_theme', array( __CLASS__, 'maybe_switch_to_webina' ), 5 );
	}

	/**
	 * Allow themes inside plugin bundle.
	 */
	public static function register_directory() {
		if ( function_exists( 'register_theme_directory' ) ) {
			register_theme_directory( WEBINOCRM_PLUGIN_DIR . 'theme' );
		}
	}

	/**
	 * Switch active theme to Webina on first seed if safe.
	 */
	public static function maybe_switch_to_webina() {
		// No-op; seeder handles switch_theme.
	}

	/**
	 * Activate Webina theme if present.
	 */
	public static function activate() {
		self::register_directory();
		if ( wp_get_theme( self::THEME_SLUG )->exists() ) {
			switch_theme( self::THEME_SLUG );
		}
	}
}
