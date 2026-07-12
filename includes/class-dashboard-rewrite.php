<?php
/**
 * Front-end routes for /dashboard (CRM SPA).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers rewrite rules and serves the SPA shell template.
 */
class WebinoCRM_Dashboard_Rewrite {

	/**
	 * @return void
	 */
	public function register_rewrites() {
		add_rewrite_rule( '^dashboard(/.*)?$', 'index.php?webino_dashboard=1&wd_path=$matches[1]', 'top' );
	}

	/**
	 * @param array<int,string> $vars Query vars.
	 * @return array<int,string>
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'webino_dashboard';
		$vars[] = 'wd_path';
		return $vars;
	}

	/**
	 * Fallback when rewrite rules are stale.
	 *
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	public function parse_dashboard_request( $wp ) {
		if ( ! empty( $wp->query_vars['webino_dashboard'] ) ) {
			return;
		}

		$relative = $this->request_path_relative_to_home();
		if ( null === $relative ) {
			return;
		}

		if ( 'dashboard' === $relative ) {
			$wp->query_vars['webino_dashboard'] = '1';
			$wp->query_vars['wd_path']          = '';
			return;
		}

		if ( 0 === strpos( $relative, 'dashboard/' ) ) {
			$wp->query_vars['webino_dashboard'] = '1';
			$wp->query_vars['wd_path']          = substr( $relative, strlen( 'dashboard/' ) );
		}
	}

	/**
	 * @return string|null
	 */
	private function request_path_relative_to_home() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}

		$path      = trim( $path, '/' );
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '' !== $home_path && '/' !== $home_path ) {
			$home_path = trim( $home_path, '/' );
			if ( $home_path !== '' && 0 === strpos( $path, $home_path . '/' ) ) {
				$path = substr( $path, strlen( $home_path ) + 1 );
			} elseif ( $path === $home_path ) {
				$path = '';
			}
		}

		return $path;
	}

	/**
	 * Serve the SPA shell before the theme renders (avoids Elementor/theme overriding template_include).
	 *
	 * @return void
	 */
	public function maybe_serve_shell() {
		if ( ! $this->is_dashboard_request() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			if ( ! $this->is_dashboard_login_path() ) {
				wp_safe_redirect( home_url( '/dashboard/login' ) );
				exit;
			}
		} else {
			$cap = apply_filters( 'webinocrm_dashboard_required_cap', 'read' );
			if ( ! current_user_can( $cap ) ) {
				wp_die(
					esc_html__( 'You do not have permission to access this dashboard.', 'webinocrm' ),
					esc_html__( 'Dashboard', 'webinocrm' ),
					array( 'response' => 403 )
				);
			}
		}

		$shell = WEBINOCRM_PLUGIN_DIR . 'templates/dashboard/dashboard-shell.php';
		if ( ! is_readable( $shell ) ) {
			return;
		}

		nocache_headers();
		include $shell;
		exit;
	}

	/**
	 * @param string $template Path to theme template.
	 * @return string
	 */
	public function template_include( $template ) {
		if ( ! $this->is_dashboard_request() ) {
			return $template;
		}

		$shell = WEBINOCRM_PLUGIN_DIR . 'templates/dashboard/dashboard-shell.php';
		if ( is_readable( $shell ) ) {
			return $shell;
		}

		return $template;
	}

	/**
	 * @return bool
	 */
	public function is_dashboard_request() {
		if ( get_query_var( 'webino_dashboard' ) ) {
			return true;
		}

		$relative = $this->request_path_relative_to_home();
		if ( null === $relative ) {
			return false;
		}

		return 'dashboard' === $relative || 0 === strpos( $relative, 'dashboard/' );
	}

	/**
	 * True when the SPA login route is requested (/dashboard/login).
	 *
	 * @return bool
	 */
	public function is_dashboard_login_path() {
		$path = trim( (string) get_query_var( 'wd_path' ), '/' );
		if ( 'login' === $path ) {
			return true;
		}

		$relative = $this->request_path_relative_to_home();
		if ( null === $relative ) {
			return false;
		}

		return 'dashboard/login' === $relative || 0 === strpos( $relative, 'dashboard/login/' );
	}
}
