<?php
/**
 * REST API for CRM dashboard SPA.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers routes under webinocrm/v1.
 */
class WebinoCRM_Dashboard_REST {

	const NS = 'webinocrm/v1';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-webinocrm-rest-content-media.php';

		register_rest_route(
			self::NS,
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'bootstrap' ),
				'permission_callback' => array( 'WebinoCRM_REST_Base', 'can_read' ),
			)
		);

		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'settings_get' ),
					'permission_callback' => array( 'WebinoCRM_REST_Base', 'can_read' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'settings_post' ),
					'permission_callback' => array( 'WebinoCRM_REST_Base', 'can_read' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/auth/session',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'auth_session' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/auth/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth_logout' ),
				'permission_callback' => array( 'WebinoCRM_REST_Base', 'can_read' ),
			)
		);

		register_rest_route(
			self::NS,
			'/manifest.webmanifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'manifest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/content/media',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( 'WebinoCRM_REST_Content_Media', 'list' ),
					'permission_callback' => static function () {
						return WebinoCRM_REST_Base::can( 'upload_files' );
					},
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( 'WebinoCRM_REST_Content_Media', 'upload' ),
					'permission_callback' => static function () {
						return WebinoCRM_REST_Base::can( 'upload_files' );
					},
				),
			)
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function bootstrap() {
		$uid    = get_current_user_id();
		$ui_loc = $uid ? (string) get_user_meta( $uid, 'webino_dashboard_locale', true ) : '';
		if ( '' === $ui_loc && function_exists( 'webino_get_current_language' ) ) {
			$lang   = webino_get_current_language();
			$ui_loc = ( 'fa' === $lang ) ? 'fa_IR' : 'en_US';
		}
		$locale = $ui_loc ? $ui_loc : determine_locale();

		$modules = WebinoCRM_Modules::get_default_modules();
		$modules = WebinoCRM_Modules::filter_by_role( $modules );
		$modules = self::normalize_modules( $modules );
		if ( empty( $modules ) ) {
			$modules = self::fallback_modules_for_role( WebinoCRM_REST_Base::crm_role() );
		}

		$ui_theme  = $uid ? (string) get_user_meta( $uid, 'webino_dashboard_theme', true ) : '';
		$ui_accent = $uid ? (string) get_user_meta( $uid, 'webino_dashboard_accent', true ) : '';
		$ui_fs     = $uid ? (string) get_user_meta( $uid, 'webino_dashboard_fullscreen', true ) : '';

		$wp_user = wp_get_current_user();
		$user    = array(
			'name'   => ( $wp_user && $wp_user->exists() ) ? (string) $wp_user->display_name : '',
			'email'  => ( $wp_user && $wp_user->exists() ) ? (string) $wp_user->user_email : '',
			'avatar' => ( $wp_user && $wp_user->exists() ) ? (string) get_avatar_url( $wp_user->ID, array( 'size' => 64 ) ) : '',
			'role'   => WebinoCRM_REST_Base::crm_role(),
		);

		$logo = '';
		if ( class_exists( 'WebinoCRM_Sidebar_Data_Provider' ) ) {
			$logo = (string) WebinoCRM_Sidebar_Data_Provider::get_logo_url();
		}

		$site = array(
			'name' => webinocrm_product_display_name(),
			'url'  => home_url( '/' ),
			'icon' => self::site_icon_url(),
			'logo' => $logo,
		);

		$caps = array();
		foreach ( WebinoCRM_REST_Base::crm_capabilities_map() as $cap => $allowed ) {
			if ( $allowed ) {
				$caps[] = $cap;
			}
		}
		if ( current_user_can( 'read' ) ) {
			$caps[] = 'read';
		}

		return new WP_REST_Response(
			array(
				'modules'      => $modules,
				'locale'       => $locale,
				'uiTheme'      => $ui_theme ? $ui_theme : 'system',
				'uiAccent'     => $ui_accent ? $ui_accent : 'default',
				'uiFullscreen' => ( '1' === $ui_fs || 'true' === $ui_fs ),
				'capabilities' => array_values( array_unique( $caps ) ),
				'user'         => $user,
				'site'         => $site,
				'flags'        => array(
					'crm'        => true,
					'accounting' => class_exists( 'WebinoCRM_Accounting_Module' ) && self::is_accounting_module_enabled(),
				),
			)
		);
	}

	/**
	 * Safe accounting module toggle check for REST bootstrap context.
	 *
	 * @return bool
	 */
	private static function is_accounting_module_enabled() {
		if ( ! class_exists( 'WebinoCRM_Sidebar_Menu_Builder' ) ) {
			$builder = WEBINOCRM_PLUGIN_DIR . 'includes/components/sidebar/class-sidebar-menu-builder.php';
			if ( is_readable( $builder ) ) {
				require_once $builder;
			}
		}
		if ( ! class_exists( 'WebinoCRM_Sidebar_Menu_Builder' ) ) {
			return true;
		}
		return WebinoCRM_Sidebar_Menu_Builder::is_module_enabled( 'finance' );
	}

	/**
	 * @param array<int,mixed> $modules
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_modules( array $modules ) {
		$out = array();
		foreach ( $modules as $module ) {
			if ( ! is_array( $module ) ) {
				continue;
			}
			$id    = isset( $module['id'] ) ? sanitize_key( (string) $module['id'] ) : '';
			$title = isset( $module['title'] ) ? trim( (string) $module['title'] ) : '';
			$path  = isset( $module['path'] ) ? (string) $module['path'] : '/';
			if ( '' === $title ) {
				continue;
			}
			$node = array(
				'id'         => '' !== $id ? $id : sanitize_key( 'm-' . md5( $title . '|' . $path ) ),
				'title'      => $title,
				'path'       => self::normalize_module_path( $path ),
				'capability' => isset( $module['capability'] ) ? (string) $module['capability'] : 'read',
				'icon'       => isset( $module['icon'] ) ? (string) $module['icon'] : 'circle',
			);

			if ( ! empty( $module['children'] ) && is_array( $module['children'] ) ) {
				$children = self::normalize_modules( $module['children'] );
				if ( ! empty( $children ) ) {
					$node['children'] = $children;
				}
			}

			$out[] = $node;
		}
		return $out;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function normalize_module_path( $path ) {
		$p = trim( (string) $path );
		if ( '' === $p || '#' === $p ) {
			return '/';
		}
		if ( 0 === strpos( $p, 'http://' ) || 0 === strpos( $p, 'https://' ) ) {
			$u = wp_parse_url( $p, PHP_URL_PATH );
			$p = is_string( $u ) ? $u : '/';
		}
		if ( '/' !== substr( $p, 0, 1 ) ) {
			$p = '/' . ltrim( $p, '/' );
		}
		return $p;
	}

	/**
	 * @param string $role
	 * @return array<int,array<string,mixed>>
	 */
	private static function fallback_modules_for_role( $role ) {
		$routes = WebinoCRM_Dashboard_Router::get_routes();
		$mods   = array();
		foreach ( $routes as $slug => $route ) {
			$roles = isset( $route['roles'] ) && is_array( $route['roles'] ) ? $route['roles'] : array();
			if ( ! in_array( $role, $roles, true ) ) {
				continue;
			}
			$title = isset( $route['title'] ) ? (string) $route['title'] : '';
			if ( '' === $title ) {
				continue;
			}
			$mods[] = array(
				'id'         => $slug ? sanitize_key( $slug ) : 'home',
				'title'      => $title,
				'path'       => '' === $slug ? '/' : '/' . ltrim( $slug, '/' ),
				'capability' => 'read',
				'icon'       => isset( $route['icon'] ) ? WebinoCRM_Modules::map_icon( (string) $route['icon'] ) : 'circle',
			);
		}
		return $mods;
	}

	/**
	 * @return string
	 */
	public static function site_icon_url() {
		$icon_id = (int) get_option( 'site_icon' );
		if ( $icon_id > 0 ) {
			$url = wp_get_attachment_image_url( $icon_id, 'full' );
			if ( is_string( $url ) && $url !== '' ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function settings_get() {
		$uid = get_current_user_id();
		return new WP_REST_Response(
			array(
				'ui_locale'             => get_user_meta( $uid, 'webino_dashboard_locale', true ) ?: '',
				'ui_theme'              => get_user_meta( $uid, 'webino_dashboard_theme', true ) ?: 'system',
				'ui_accent'             => get_user_meta( $uid, 'webino_dashboard_accent', true ) ?: 'default',
				'ui_fullscreen_default' => get_user_meta( $uid, 'webino_dashboard_fullscreen', true ) === '1',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function settings_post( $request ) {
		$user_id = get_current_user_id();
		$loc     = sanitize_text_field( (string) $request->get_param( 'ui_locale' ) );
		$theme   = sanitize_key( (string) $request->get_param( 'ui_theme' ) );
		$accent  = sanitize_key( (string) $request->get_param( 'ui_accent' ) );
		if ( $loc ) {
			update_user_meta( $user_id, 'webino_dashboard_locale', $loc );
		}
		if ( $theme ) {
			update_user_meta( $user_id, 'webino_dashboard_theme', $theme );
		}
		if ( $accent ) {
			$allowed = array( 'default', 'red', 'rose', 'orange', 'green', 'blue', 'yellow', 'violet' );
			if ( in_array( $accent, $allowed, true ) ) {
				update_user_meta( $user_id, 'webino_dashboard_accent', $accent );
			}
		}
		if ( null !== $request->get_param( 'ui_fullscreen_default' ) ) {
			update_user_meta( $user_id, 'webino_dashboard_fullscreen', ! empty( $request->get_param( 'ui_fullscreen_default' ) ) ? '1' : '0' );
		}
		return self::settings_get();
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function auth_session() {
		$uid  = get_current_user_id();
		$user = $uid ? wp_get_current_user() : null;
		return new WP_REST_Response(
			array(
				'logged_in' => is_user_logged_in(),
				'isLoggedIn' => is_user_logged_in(),
				'userId'     => $uid,
				'user'       => $user && $user->exists()
					? array(
						'id'    => $uid,
						'login' => $user->user_login,
						'name'  => $user->display_name,
					)
					: null,
			)
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function auth_logout() {
		wp_logout();
		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * PWA manifest for CRM dashboard shell.
	 *
	 * @return WP_REST_Response
	 */
	public static function manifest() {
		$body = array(
			'name'             => get_bloginfo( 'name' ) . ' — ' . __( 'Dashboard', 'webinocrm' ),
			'short_name'       => 'Dashboard',
			'start_url'        => home_url( '/dashboard/' ),
			'display'          => 'standalone',
			'background_color' => '#ffffff',
			'theme_color'      => '#0f172a',
		);
		$res = new WP_REST_Response( $body );
		$res->header( 'Content-Type', 'application/manifest+json; charset=' . get_option( 'blog_charset' ) );
		return $res;
	}
}
