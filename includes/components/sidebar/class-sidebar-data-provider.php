<?php
/**
 * Sidebar Data Provider
 * 
 * Provides data for the sidebar component
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Data Provider class
 */
class WebinoCRM_Sidebar_Data_Provider {

	/**
	 * Get sidebar logo URL
	 *
	 * @return string Logo URL.
	 */
	public static function get_logo_url() {
		// Use white label logo if available
		if (class_exists('WebinoCRM_White_Label')) {
			return WebinoCRM_White_Label::get_company_logo();
		}
		
		$custom_logo_id = get_theme_mod( 'custom_logo' );

		if ( $custom_logo_id ) {
			$logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
			if ( $logo ) {
				return $logo[0];
			}
		}

		// Default logo
		return WEBINOCRM_PLUGIN_URL . 'assets/images/logo.png';
	}

	/**
	 * Get menu items for current user
	 *
	 * @return array Menu items.
	 */
	public static function get_menu_items() {
		return WebinoCRM_Sidebar_Menu_Builder::build_menu();
	}

	/**
	 * Get current page slug
	 *
	 * @return string Current page slug.
	 */
	public static function get_current_page() {
		return get_query_var( 'dashboard_page', '' );
	}

	/**
	 * Check if menu item is active
	 *
	 * @param string $item_id Menu item ID.
	 * @return bool True if active, false otherwise.
	 */
	public static function is_menu_item_active( $item_id ) {
		$current_page = self::get_current_page();
		
		// Empty page means homepage (dashboard)
		if ( empty( $current_page ) ) {
			return $item_id === 'dashboard';
		}

		return $current_page === $item_id;
	}
}

