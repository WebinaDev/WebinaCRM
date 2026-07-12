<?php
/**
 * Dashboard SPA bootstrap (rewrite, assets, REST).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton for CRM dashboard shell.
 */
final class WebinoCRM_Dashboard_App {

	/**
	 * @var WebinoCRM_Dashboard_App|null
	 */
	private static $instance = null;

	/**
	 * @var WebinoCRM_Dashboard_Rewrite
	 */
	public $rewrite;

	/**
	 * @var WebinoCRM_Dashboard_Assets
	 */
	public $assets;

	/**
	 * @return WebinoCRM_Dashboard_App
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->rewrite = new WebinoCRM_Dashboard_Rewrite();
		$this->assets  = new WebinoCRM_Dashboard_Assets();

		add_action( 'init', array( $this->rewrite, 'register_rewrites' ), 1 );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 20 );
		add_action( 'parse_request', array( $this->rewrite, 'parse_dashboard_request' ), 1 );
		add_filter( 'query_vars', array( $this->rewrite, 'register_query_vars' ) );
		add_filter( 'template_include', array( $this->rewrite, 'template_include' ), 999 );
		add_action( 'template_redirect', array( $this->rewrite, 'maybe_serve_shell' ), 0 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_legacy_dashboard_page' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_prevent_dashboard_html_cache' ), 0 );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this->assets, 'dequeue_theme_on_dashboard' ), 999 );

		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-rest-base.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-rest-response.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-rest-legacy-invoker.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-rest-registry.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-dashboard-rest.php';
		WebinoCRM_Dashboard_REST::init();
		WebinoCRM_REST_Registry::init();
	}

	/**
	 * @return void
	 */
	public function maybe_flush_rewrites() {
		$option = 'webinocrm_dashboard_rewrite_version';
		$stored = (string) get_option( $option, '' );
		if ( $stored === WEBINOCRM_VERSION ) {
			return;
		}
		$this->rewrite->register_rewrites();
		flush_rewrite_rules( false );
		update_option( $option, WEBINOCRM_VERSION, false );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_webinocrm_dashboard_boot_%' OR option_name LIKE '_transient_timeout_webinocrm_dashboard_boot_%'"
		);
	}

	/**
	 * Redirect legacy WP page (theme layout) to isolated /dashboard SPA shell.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_dashboard_page() {
		if ( $this->rewrite->is_dashboard_request() ) {
			return;
		}

		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$page_id = (int) get_option( 'webino_dashboard_page_id', 0 );
		if ( $page_id <= 0 || (int) get_queried_object_id() !== $page_id ) {
			return;
		}

		wp_safe_redirect( home_url( '/dashboard' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function maybe_prevent_dashboard_html_cache() {
		if ( ! $this->rewrite->is_dashboard_request() ) {
			return;
		}
		nocache_headers();
	}

	/**
	 * @param bool $show Whether to show admin bar.
	 * @return bool
	 */
	public function hide_admin_bar( $show ) {
		if ( $this->rewrite->is_dashboard_request() ) {
			return false;
		}
		return $show;
	}
}

/**
 * @return WebinoCRM_Dashboard_App
 */
function webinocrm_dashboard() {
	return WebinoCRM_Dashboard_App::instance();
}
