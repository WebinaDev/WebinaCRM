<?php
/**
 * Login route alias — redirects to isolated dashboard SPA login.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Login_Page {

	/**
	 * @return string
	 */
	public static function login_url() {
		return home_url( '/dashboard/login' );
	}

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_shortcode( 'crm_login', array( $this, 'render_login_page' ) );
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^login/?$', 'index.php?crm_login=1', 'top' );
	}

	/**
	 * @param array<int,string> $vars Query vars.
	 * @return array<int,string>
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'crm_login';
		return $vars;
	}

	public function template_redirect() {
		if ( ! get_query_var( 'crm_login' ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$user         = wp_get_current_user();
			$redirect_url = $this->get_redirect_url_for_user( $user );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		wp_safe_redirect( self::login_url() );
		exit;
	}

	/**
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_login_page( $atts = array() ) {
		$url  = esc_url( self::login_url() );
		$text = esc_html__( 'ورود', 'webinocrm' );
		return '<p class="webinocrm-spa-notice"><a href="' . $url . '">' . $text . '</a></p>';
	}

	/**
	 * @param WP_User $user WordPress user.
	 * @return string
	 */
	private function get_redirect_url_for_user( $user ) {
		return webinocrm_login_redirect_url( $user );
	}

	public static function activate() {
		$instance = new self();
		$instance->add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
