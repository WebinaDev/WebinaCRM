<?php
/**
 * Webina theme bootstrap.
 *
 * @package Webina
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBINA_THEME_VERSION', '1.3.0' );
define( 'WEBINA_THEME_DIR', get_template_directory() );
define( 'WEBINA_THEME_URI', get_template_directory_uri() );

/**
 * Resolve site asset from plugin first, then theme fallback copy.
 *
 * @param string $relative Relative path under assets/site/.
 * @return array{url:string,ver:string,path:string}
 */
function webina_theme_site_asset( $relative ) {
	$relative = ltrim( (string) $relative, '/' );
	$candidates = array();

	if ( defined( 'WEBINOCRM_PLUGIN_DIR' ) && defined( 'WEBINOCRM_PLUGIN_URL' ) ) {
		$candidates[] = array(
			'path' => WEBINOCRM_PLUGIN_DIR . 'assets/site/' . $relative,
			'url'  => WEBINOCRM_PLUGIN_URL . 'assets/site/' . $relative,
			'ver'  => defined( 'WEBINOCRM_SITE_VERSION' ) ? WEBINOCRM_SITE_VERSION : WEBINA_THEME_VERSION,
		);
	}

	$candidates[] = array(
		'path' => WEBINA_THEME_DIR . '/assets/site/' . $relative,
		'url'  => WEBINA_THEME_URI . '/assets/site/' . $relative,
		'ver'  => WEBINA_THEME_VERSION,
	);

	foreach ( $candidates as $c ) {
		if ( is_readable( $c['path'] ) ) {
			$ver = $c['ver'];
			$mtime = @filemtime( $c['path'] );
			if ( $mtime ) {
				$ver .= '.' . (string) $mtime;
			}
			return array(
				'url'  => $c['url'],
				'ver'  => $ver,
				'path' => $c['path'],
			);
		}
	}

	return array(
		'url'  => WEBINA_THEME_URI . '/assets/site/' . $relative,
		'ver'  => WEBINA_THEME_VERSION,
		'path' => '',
	);
}

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'webina', WEBINA_THEME_DIR . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 240, 'flex-height' => true, 'flex-width' => true ) );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );

		register_nav_menus(
			array(
				'primary' => __( 'Primary Menu', 'webina' ),
				'footer'  => __( 'Footer Menu', 'webina' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'webina-theme', WEBINA_THEME_URI . '/style.css', array(), WEBINA_THEME_VERSION );

		$css = webina_theme_site_asset( 'css/site.css' );
		wp_enqueue_style( 'webina-site', $css['url'], array( 'webina-theme' ), $css['ver'] );

		$widgets = webina_theme_site_asset( 'css/widgets.css' );
		if ( ! empty( $widgets['path'] ) ) {
			wp_enqueue_style( 'webina-site-widgets', $widgets['url'], array( 'webina-site' ), $widgets['ver'] );
		}

		$js = webina_theme_site_asset( 'js/site.js' );
		if ( ! empty( $js['path'] ) ) {
			wp_enqueue_script( 'webina-site', $js['url'], array(), $js['ver'], true );
			wp_localize_script(
				'webina-site',
				'webinaSite',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'leadNonce'    => wp_create_nonce( 'webinocrm_site_lead' ),
					'leadAction'   => 'webinocrm_site_lead',
					'careerAction' => 'webinocrm_site_career',
				)
			);
		}

		if ( is_rtl() ) {
			wp_enqueue_style( 'webina-theme-rtl', WEBINA_THEME_URI . '/rtl.css', array( 'webina-theme' ), WEBINA_THEME_VERSION );
		}
	},
	20
);

add_action(
	'elementor/theme/register_locations',
	static function ( $manager ) {
		$manager->register_all_core_location();
	}
);
