<?php
/**
 * Header Data Provider
 * 
 * Provides data for the header component
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header Data Provider class
 */
class WebinoCRM_Header_Data_Provider {

	/**
	 * Get current user data
	 *
	 * @return array User data array.
	 */
	public static function get_user_data() {
		if ( ! is_user_logged_in() ) {
			return array(
				'name'   => __( 'Guest', 'webinocrm' ),
				'email'  => '',
				'avatar' => '',
				'role'   => 'guest',
			);
		}

		$current_user = wp_get_current_user();
		$user_role    = self::get_user_role_display( $current_user );
		$role_slug    = self::get_user_role_slug( $current_user );

		$avatar_size = 96;
		$avatar_url  = get_avatar_url( $current_user->ID, array( 'size' => $avatar_size ) );
		$pic_id      = get_user_meta( $current_user->ID, 'webino_profile_picture_id', true );
		if ( $pic_id && wp_attachment_is_image( $pic_id ) ) {
			$avatar_url = wp_get_attachment_image_url( (int) $pic_id, array( $avatar_size, $avatar_size ) ) ?: $avatar_url;
		}
		return array(
			'id'               => $current_user->ID,
			'name'             => $current_user->display_name,
			'email'            => $current_user->user_email,
			'avatar'           => get_avatar_url( $current_user->ID, array( 'size' => 32 ) ),
			'avatarLarge'      => $avatar_url,
			'role'             => $user_role,
			'roleSlug'         => $role_slug,
			'first_name'       => $current_user->first_name,
			'last_name'        => $current_user->last_name,
			'webino_mobile_phone' => get_user_meta( $current_user->ID, 'webino_mobile_phone', true ) ?: '',
		);
	}

	/**
	 * Get user role slug for dashboard logic.
	 *
	 * @param WP_User $user User object.
	 * @return string Role slug (system_manager, team_member, client, etc).
	 */
	private static function get_user_role_slug( $user ) {
		$roles = (array) $user->roles;
		if ( in_array( 'administrator', $roles, true ) || in_array( 'system_manager', $roles, true ) ) {
			return 'system_manager';
		}
		if ( in_array( 'finance_manager', $roles, true ) ) {
			return 'finance_manager';
		}
		if ( in_array( 'team_member', $roles, true ) ) {
			return 'team_member';
		}
		if ( in_array( 'customer', $roles, true ) || in_array( 'client', $roles, true ) ) {
			return 'client';
		}
		return 'guest';
	}

	/**
	 * Get user role display name
	 *
	 * @param WP_User $user User object.
	 * @return string Role display name.
	 */
	private static function get_user_role_display( $user ) {
		$roles = (array) $user->roles;

		if ( in_array( 'administrator', $roles, true ) || in_array( 'system_manager', $roles, true ) ) {
			return __( 'System Manager', 'webinocrm' );
		}

		if ( in_array( 'finance_manager', $roles, true ) ) {
			return __( 'Finance Manager', 'webinocrm' );
		}

		if ( in_array( 'team_member', $roles, true ) ) {
			return __( 'Team Member', 'webinocrm' );
		}

		if ( in_array( 'customer', $roles, true ) ) {
			return __( 'Customer', 'webinocrm' );
		}

		return __( 'User', 'webinocrm' );
	}

	/**
	 * Get notifications for current user
	 *
	 * @param int $limit Number of notifications to retrieve.
	 * @return array Array of notifications.
	 */
	public static function get_notifications( $limit = 5 ) {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_notifications';
		$lim   = max( 1, min( 20, (int) $limit ) );
		// Table is created on plugin activation (see WebinoCRM_Installer).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, type, title, message, is_read, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
				get_current_user_id(),
				$lim
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$ts   = strtotime( $r->created_at );
			$time = $ts ? sprintf(
				/* translators: %s: human-readable time difference */
				__( '%s ago', 'webinocrm' ),
				human_time_diff( $ts, current_time( 'timestamp' ) )
			) : '';

			$icon_map = array(
				'warning' => 'ri-alert-line',
				'error'   => 'ri-error-warning-line',
				'success' => 'ri-checkbox-circle-line',
			);
			$type     = isset( $r->type ) ? sanitize_key( $r->type ) : 'info';
			$icon     = isset( $icon_map[ $type ] ) ? $icon_map[ $type ] : 'ri-notification-3-line';

			$out[] = array(
				'id'           => (int) $r->id,
				'title'        => isset( $r->title ) ? wp_strip_all_tags( $r->title ) : '',
				'message'      => isset( $r->message ) ? wp_strip_all_tags( $r->message ) : '',
				'time'         => $time,
				'url'          => apply_filters( 'webinocrm_notification_link', home_url( '/dashboard' ), $r ),
				'icon'         => $icon,
				'avatar_class' => ( isset( $r->is_read ) && (int) $r->is_read === 0 ) ? 'bg-primary-transparent' : 'bg-secondary-transparent',
			);
		}

		return apply_filters( 'webinocrm_header_notifications', $out, $limit );
	}

	/**
	 * Get unread notifications count
	 *
	 * @return int Count of unread notifications.
	 */
	public static function get_unread_notifications_count() {
		if ( ! is_user_logged_in() ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_notifications';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
				get_current_user_id()
			)
		);

		return (int) $n;
	}

	/**
	 * Get search configuration
	 *
	 * @return array Search configuration.
	 */
	public static function get_search_config() {
		return array(
			'enabled'     => true,
			'placeholder' => __( 'Search...', 'webinocrm' ),
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'webino_search_nonce' ),
		);
	}

	/**
	 * Get language options
	 *
	 * @return array Language options.
	 */
	public static function get_language_options() {
		$current_locale = get_locale();

		return array(
			'current' => $current_locale,
			'options' => array(
				'fa_IR' => array(
					'name'      => __( 'Persian', 'webinocrm' ),
					'direction' => 'rtl',
				),
				'en_US' => array(
					'name'      => __( 'English', 'webinocrm' ),
					'direction' => 'ltr',
				),
			),
		);
	}

	/**
	 * Get logo URL
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
	 * Get profile menu items
	 *
	 * @return array Profile menu items.
	 */
	public static function get_profile_menu_items() {
		$dashboard_url = home_url( '/dashboard' );

		$items = array(
			array(
				'id'    => 'profile',
				'title' => __( 'My Profile', 'webinocrm' ),
				'url'   => $dashboard_url . '/profile',
				'icon'  => 'ri-user-3-line',
			),
			array(
				'id'    => 'settings',
				'title' => __( 'Settings', 'webinocrm' ),
				'url'   => $dashboard_url . '/settings',
				'icon'  => 'ri-settings-3-line',
			),
		);

		// Add logout
		$items[] = array(
			'id'    => 'logout',
			'title' => __( 'Logout', 'webinocrm' ),
			'url'   => wp_logout_url( home_url() ),
			'icon'  => 'ri-logout-box-line',
		);

		/**
		 * Filter profile menu items
		 *
		 * @param array $items Profile menu items.
		 */
		return apply_filters( 'webinocrm_header_profile_menu_items', $items );
	}
}

