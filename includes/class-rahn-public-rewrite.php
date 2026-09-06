<?php
/**
 * Public /rahn/{token} rewrite for shareable calculator.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves a lightweight public shell for the rahn-percent customer form.
 */
class WebinoCRM_Rahn_Public_Rewrite {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/**
	 * @return void
	 */
	public static function register_rewrites() {
		add_rewrite_rule( '^rahn/([^/]+)/?$', 'index.php?webino_rahn=1&rahn_token=$matches[1]', 'top' );
	}

	/**
	 * @param array<int,string> $vars Vars.
	 * @return array<int,string>
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'webino_rahn';
		$vars[] = 'rahn_token';
		return $vars;
	}

	/**
	 * @param WP $wp WP.
	 * @return void
	 */
	public static function parse_request( $wp ) {
		if ( ! empty( $wp->query_vars['webino_rahn'] ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}
		$path = trim( $path, '/' );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home ) && '' !== $home && '/' !== $home ) {
			$home = trim( $home, '/' );
			if ( $home !== '' && 0 === strpos( $path, $home . '/' ) ) {
				$path = substr( $path, strlen( $home ) + 1 );
			}
		}
		if ( preg_match( '#^rahn/([^/]+)/?$#', $path, $m ) ) {
			$wp->query_vars['webino_rahn'] = '1';
			$wp->query_vars['rahn_token']  = $m[1];
		}
	}

	/**
	 * @return void
	 */
	public static function maybe_serve() {
		if ( ! get_query_var( 'webino_rahn' ) ) {
			return;
		}
		$token = sanitize_text_field( (string) get_query_var( 'rahn_token' ) );
		if ( '' === $token ) {
			status_header( 404 );
			nocache_headers();
			echo esc_html__( 'لینک نامعتبر است.', 'webinocrm' );
			exit;
		}

		$template = WEBINOCRM_PLUGIN_DIR . 'templates/rahn/public-shell.php';
		if ( ! is_readable( $template ) ) {
			status_header( 500 );
			echo 'Rahn shell missing';
			exit;
		}

		status_header( 200 );
		nocache_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		include $template;
		exit;
	}

	/**
	 * Flush on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::register_rewrites();
		flush_rewrite_rules();
	}
}
