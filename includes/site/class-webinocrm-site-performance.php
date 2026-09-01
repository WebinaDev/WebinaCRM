<?php
/**
 * Front-end performance tweaks for public site.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Performance {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'lazy_images' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * @param array<int,string> $classes Classes.
	 * @return array<int,string>
	 */
	public static function body_class( $classes ) {
		if ( get_query_var( 'webino_dashboard', false ) ) {
			return $classes;
		}
		$classes[] = 'webina-site';
		return $classes;
	}

	/**
	 * @param array<string,string> $attr Attributes.
	 * @return array<string,string>
	 */
	public static function lazy_images( $attr ) {
		if ( is_admin() || get_query_var( 'webino_dashboard', false ) ) {
			return $attr;
		}
		if ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	/**
	 * Enqueue lightweight site assets (not dashboard).
	 */
	public static function enqueue_assets() {
		if ( get_query_var( 'webino_dashboard', false ) ) {
			return;
		}
		// Theme already enqueues webina-site from plugin or theme fallback.
		if ( wp_style_is( 'webina-site', 'enqueued' ) || wp_style_is( 'webina-site', 'done' ) ) {
			if ( is_post_type_archive( WebinoCRM_Site_Portfolio::POST_TYPE ) || is_singular( WebinoCRM_Site_Portfolio::POST_TYPE ) ) {
				wp_enqueue_script(
					'webinocrm-portfolio-filter',
					WEBINOCRM_PLUGIN_URL . 'assets/site/js/portfolio-filter.js',
					array( 'webina-site' ),
					WEBINOCRM_SITE_VERSION,
					true
				);
				wp_localize_script(
					'webinocrm-portfolio-filter',
					'webinaPortfolio',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'webina_portfolio_filter' ),
					)
				);
			}
			return;
		}
		wp_enqueue_style(
			'webinocrm-site',
			WEBINOCRM_PLUGIN_URL . 'assets/site/css/site.css',
			array(),
			WEBINOCRM_SITE_VERSION
		);
		wp_enqueue_style(
			'webinocrm-site-widgets',
			WEBINOCRM_PLUGIN_URL . 'assets/site/css/widgets.css',
			array( 'webinocrm-site' ),
			WEBINOCRM_SITE_VERSION
		);
		wp_enqueue_script(
			'webinocrm-site',
			WEBINOCRM_PLUGIN_URL . 'assets/site/js/site.js',
			array(),
			WEBINOCRM_SITE_VERSION,
			true
		);
		wp_localize_script(
			'webinocrm-site',
			'webinaSite',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'leadNonce'    => wp_create_nonce( 'webinocrm_site_lead' ),
				'leadAction'   => 'webinocrm_site_lead',
				'careerAction' => 'webinocrm_site_career',
			)
		);
		if ( is_post_type_archive( WebinoCRM_Site_Portfolio::POST_TYPE ) || is_singular( WebinoCRM_Site_Portfolio::POST_TYPE ) ) {
			wp_enqueue_script(
				'webinocrm-portfolio-filter',
				WEBINOCRM_PLUGIN_URL . 'assets/site/js/portfolio-filter.js',
				array( 'webinocrm-site' ),
				WEBINOCRM_SITE_VERSION,
				true
			);
			wp_localize_script(
				'webinocrm-portfolio-filter',
				'webinaPortfolio',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'webina_portfolio_filter' ),
				)
			);
		}
	}
}
