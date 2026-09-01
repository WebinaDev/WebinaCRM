<?php
/**
 * Server-side first paint + route page payloads for the CRM dashboard SPA.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds embedded page data and HTML chrome for sub-1s first paint.
 */
final class WebinoCRM_Dashboard_SSR {

	/** @var array<string,mixed>|null */
	private static $page_cache = null;

	/**
	 * Dashboard path relative to /dashboard (no leading slash).
	 *
	 * @return string
	 */
	public static function current_path() {
		$raw = (string) get_query_var( 'wd_path' );
		return trim( $raw, '/' );
	}

	/**
	 * Route-scoped payload embedded as window.webinoDashboard.page.
	 *
	 * @return array<string,mixed>
	 */
	public static function build_page_payload() {
		if ( null !== self::$page_cache ) {
			return self::$page_cache;
		}

		$path = self::current_path();
		$out  = array(
			'path'      => $path,
			'route'     => self::route_id( $path ),
			'generated' => time(),
		);

		if ( ! is_user_logged_in() ) {
			self::$page_cache = $out;
			return $out;
		}

		// Home overview is loaded client-side via REST — keep SSR light.
		self::$page_cache = $out;
		return $out;
	}

	/**
	 * Map path to a stable route id.
	 *
	 * @param string $path Relative dashboard path.
	 * @return string
	 */
	public static function route_id( $path ) {
		$path = trim( (string) $path, '/' );
		if ( '' === $path || 'home' === $path ) {
			return 'home';
		}
		if ( 'admin/settings' === $path || 'settings' === $path ) {
			return 'settings-hub';
		}
		if ( preg_match( '#^(admin/)?settings/#', $path ) ) {
			return 'settings';
		}
		return 'app';
	}

	/**
	 * Render first-paint chrome HTML inside #root (replaced by React after boot).
	 *
	 * @param array<string,mixed>|null $bootstrap Bootstrap snapshot.
	 * @param array<string,mixed>|null $page      Page payload.
	 * @return void
	 */
	public static function render_first_paint( $bootstrap = null, $page = null ) {
		$site_name = webinocrm_product_display_name();
		$user      = wp_get_current_user();
		$user_name = $user instanceof WP_User && $user->ID ? ( $user->display_name ?: $user->user_login ) : '';
		$route     = is_array( $page ) && isset( $page['route'] ) ? (string) $page['route'] : self::route_id( self::current_path() );
		$is_dark   = is_array( $bootstrap ) && isset( $bootstrap['uiTheme'] ) && 'dark' === $bootstrap['uiTheme'];
		$title     = self::first_paint_title( $route );
		$bg        = $is_dark ? '#141414' : '#fafafa';
		$fg        = $is_dark ? '#f5f5f5' : '#171717';
		$muted     = $is_dark ? '#a3a3a3' : '#737373';
		$card      = $is_dark ? '#262626' : '#ffffff';
		$border    = $is_dark ? 'rgba(255,255,255,0.1)' : '#e5e5e5';
		$primary   = $is_dark ? '#e5e5e5' : '#171717';
		$sidebar_w = '16rem';

		echo '<div data-wd-ssr="chrome" style="box-sizing:border-box;display:flex;min-height:100vh;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg ) . ';font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;">';

		echo '<aside aria-hidden="true" style="display:none;width:' . esc_attr( $sidebar_w ) . ';flex-shrink:0;border-inline-end:1px solid ' . esc_attr( $border ) . ';background:' . esc_attr( $is_dark ? '#1a1a1a' : '#f5f5f5' ) . ';padding:1rem;" class="wd-ssr-sidebar">';
		echo '<div style="font-weight:600;font-size:0.95rem;margin-bottom:1.25rem;">' . esc_html( $site_name ) . '</div>';
		echo '<div style="height:0.5rem;width:70%;border-radius:999px;background:' . esc_attr( $border ) . ';margin-bottom:0.5rem;"></div>';
		echo '<div style="height:0.5rem;width:55%;border-radius:999px;background:' . esc_attr( $border ) . ';margin-bottom:0.5rem;"></div>';
		echo '<div style="height:0.5rem;width:62%;border-radius:999px;background:' . esc_attr( $border ) . ';"></div>';
		echo '</aside>';

		echo '<div style="flex:1;min-width:0;display:flex;flex-direction:column;">';
		echo '<header style="height:3.5rem;display:flex;align-items:center;gap:0.75rem;padding:0 1rem;border-bottom:1px solid ' . esc_attr( $border ) . ';">';
		echo '<div style="width:2rem;height:2rem;border-radius:0.5rem;background:' . esc_attr( $border ) . ';"></div>';
		echo '<div style="font-size:0.875rem;font-weight:500;">' . esc_html( $title ) . '</div>';
		if ( $user_name ) {
			echo '<div style="margin-inline-start:auto;font-size:0.75rem;color:' . esc_attr( $muted ) . ';">' . esc_html( $user_name ) . '</div>';
		}
		echo '</header>';

		echo '<main style="padding:1rem;flex:1;">';
		echo '<div style="margin-bottom:1rem;">';
		echo '<h1 style="margin:0;font-size:1.25rem;font-weight:600;letter-spacing:-0.02em;">' . esc_html( $title ) . '</h1>';
		echo '<p style="margin:0.35rem 0 0;font-size:0.8125rem;color:' . esc_attr( $muted ) . ';">' . esc_html( $site_name ) . '</p>';
		echo '</div>';

		self::render_route_cards( $route, $card, $border, $muted, $primary, $fg );

		echo '</main>';
		echo '</div>';
		echo '</div>';

		echo '<style>.wd-ssr-sidebar{display:none!important;}@media(min-width:768px){.wd-ssr-sidebar{display:block!important;}}</style>';
	}

	/**
	 * @param string $route Route id.
	 * @return string
	 */
	private static function first_paint_title( $route ) {
		if ( 'home' === $route ) {
			return __( 'Overview', 'webinocrm' );
		}
		if ( 'settings-hub' === $route || 'settings' === $route ) {
			return __( 'Settings', 'webinocrm' );
		}
		return __( 'Dashboard', 'webinocrm' );
	}

	/**
	 * @param string $route   Route id.
	 * @param string $card    Card bg.
	 * @param string $border  Border color.
	 * @param string $muted   Muted text.
	 * @param string $primary Primary.
	 * @param string $fg      Foreground.
	 * @return void
	 */
	private static function render_route_cards( $route, $card, $border, $muted, $primary, $fg ) {
		$card_style = 'background:' . esc_attr( $card ) . ';border:1px solid ' . esc_attr( $border ) . ';border-radius:1rem;padding:1.25rem;box-shadow:0 1px 2px rgb(0 0 0 / 0.04);';

		if ( 'settings-hub' === $route ) {
			echo '<div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));max-width:48rem;margin:0 auto;">';
			echo '<div style="' . $card_style . '"><div style="font-weight:600;margin-bottom:0.35rem;">' . esc_html__( 'General', 'webinocrm' ) . '</div><div style="font-size:0.8125rem;color:' . esc_attr( $muted ) . ';">' . esc_html__( 'Authentication, style, visitor tracking…', 'webinocrm' ) . '</div></div>';
			echo '<div style="' . $card_style . '"><div style="font-weight:600;margin-bottom:0.35rem;">' . esc_html__( 'CRM & projects', 'webinocrm' ) . '</div><div style="font-size:0.8125rem;color:' . esc_attr( $muted ) . ';">' . esc_html__( 'Workflow, SMS, accounting…', 'webinocrm' ) . '</div></div>';
			echo '</div>';
			return;
		}

		if ( 'settings' === $route ) {
			echo '<div style="display:flex;flex-direction:column;gap:1rem;">';
			echo '<div style="' . $card_style . '"><div style="height:0.75rem;width:40%;border-radius:999px;background:' . esc_attr( $border ) . ';margin-bottom:1rem;"></div>';
			echo '<div style="height:2.25rem;width:100%;max-width:28rem;border-radius:0.5rem;background:' . esc_attr( $border ) . ';margin-bottom:0.75rem;"></div>';
			echo '<div style="height:2.25rem;width:100%;max-width:28rem;border-radius:0.5rem;background:' . esc_attr( $border ) . ';margin-bottom:0.75rem;"></div>';
			echo '<div style="height:2rem;width:6rem;border-radius:0.5rem;background:' . esc_attr( $primary ) . ';opacity:0.85;"></div>';
			echo '</div></div>';
			return;
		}

		if ( 'home' === $route ) {
			echo '<div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));margin-bottom:1rem;">';
			echo '<div style="' . $card_style . '"><div style="font-size:0.75rem;color:' . esc_attr( $muted ) . ';margin-bottom:0.35rem;">' . esc_html__( 'Modules', 'webinocrm' ) . '</div><div style="height:1.5rem;width:60%;border-radius:999px;background:' . esc_attr( $border ) . ';"></div></div>';
			echo '<div style="' . $card_style . '"><div style="font-size:0.75rem;color:' . esc_attr( $muted ) . ';margin-bottom:0.35rem;">' . esc_html__( 'Activity', 'webinocrm' ) . '</div><div style="height:1.5rem;width:45%;border-radius:999px;background:' . esc_attr( $border ) . ';"></div></div>';
			echo '</div>';
			echo '<div style="' . $card_style . 'min-height:8rem;"></div>';
			return;
		}

		echo '<div style="' . $card_style . 'min-height:12rem;display:flex;align-items:center;justify-content:center;color:' . esc_attr( $muted ) . ';font-size:0.875rem;">' . esc_html__( 'Loading…', 'webinocrm' ) . '</div>';
	}
}
