<?php
/**
 * Dashboard UI locale helpers (SPA ui_locale ↔ WordPress gettext).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve and apply user-facing locale for REST/AJAX responses.
 */
class WebinoCRM_Dashboard_Locale {

	/**
	 * @param int $user_id User ID (0 = current).
	 * @return string Normalized locale slug: fa or en.
	 */
	public static function get_user_ui_locale( $user_id = 0 ) {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id <= 0 ) {
			return self::normalize_locale( get_locale() );
		}
		$stored = get_user_meta( $user_id, 'webino_dashboard_locale', true );
		if ( is_string( $stored ) && '' !== $stored ) {
			return self::normalize_locale( $stored );
		}
		return self::normalize_locale( get_locale() );
	}

	/**
	 * @param string $locale Raw locale.
	 * @return string fa|en
	 */
	public static function normalize_locale( $locale ) {
		$locale = strtolower( (string) $locale );
		if ( str_starts_with( $locale, 'en' ) ) {
			return 'en';
		}
		return 'fa';
	}

	/**
	 * WordPress locale string for switch_to_locale().
	 *
	 * @param string $ui fa|en.
	 * @return string
	 */
	public static function wp_locale_for_ui( $ui ) {
		return 'en' === self::normalize_locale( $ui ) ? 'en_US' : 'fa_IR';
	}

	/**
	 * Run callback under the user's dashboard locale.
	 *
	 * @param callable $callback Callback.
	 * @param int      $user_id  User ID (0 = current).
	 * @return mixed
	 */
	public static function with_user_locale( $callback, $user_id = 0 ) {
		if ( ! is_callable( $callback ) ) {
			return null;
		}
		$target = self::wp_locale_for_ui( self::get_user_ui_locale( $user_id ) );
		$previous = determine_locale();
		if ( $target === $previous ) {
			return call_user_func( $callback );
		}
		switch_to_locale( $target );
		try {
			return call_user_func( $callback );
		} finally {
			restore_previous_locale();
		}
	}
}
