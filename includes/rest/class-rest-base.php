<?php
/**
 * Shared REST helpers for CRM dashboard.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helpers for REST controllers.
 */
class WebinoCRM_REST_Base {

	/**
	 * @return bool
	 */
	public static function can_read() {
		return is_user_logged_in() && current_user_can( 'read' );
	}

	/**
	 * @param string $cap Capability.
	 * @return bool
	 */
	public static function can( $cap ) {
		return is_user_logged_in() && current_user_can( $cap );
	}

	/**
	 * CRM dashboard role slug for current user.
	 *
	 * @return string
	 */
	public static function crm_role() {
		if ( ! class_exists( 'WebinoCRM_Dashboard_Router' ) ) {
			return 'guest';
		}
		$user = wp_get_current_user();
		return WebinoCRM_Dashboard_Router::get_user_dashboard_role( $user );
	}

	/**
	 * Check CRM route access by slug (matches PHP router roles).
	 *
	 * @param string $route_slug Route key from WebinoCRM_Dashboard_Router.
	 * @return bool
	 */
	/**
	 * Alias for route permission checks in controllers.
	 *
	 * @param string $route_slug Route key.
	 * @return bool
	 */
	public static function can_for_route( $route_slug ) {
		return self::can_access_route( $route_slug );
	}

	/**
	 * Map ERP dashboard path or menu id to a legacy router slug.
	 *
	 * @param string $path     Router path e.g. /crm/leads.
	 * @param string $menu_id  Sidebar menu item id.
	 * @return string Router slug for can_access_route.
	 */
	public static function resolve_route_slug_from_path( $path, $menu_id = '' ) {
		$menu_id = sanitize_key( (string) $menu_id );
		$path    = trim( (string) $path, '/' );

		if ( $menu_id ) {
			$routes = WebinoCRM_Dashboard_Router::get_routes();
			if ( isset( $routes[ $menu_id ] ) ) {
				return $menu_id;
			}
			if ( 0 === strpos( $menu_id, 'accounting' ) ) {
				return 'accounting';
			}
			if ( 0 === strpos( $menu_id, 'modirpayamak' ) || 0 === strpos( $menu_id, 'marketplace' ) ) {
				return 'settings';
			}
			if ( 'mfg-overview' === $menu_id ) {
				return 'settings';
			}
			if ( 'bale-business' === $menu_id ) {
				return 'bale-business';
			}
			if ( 'visitor-statistics' === $menu_id ) {
				return 'visitor-statistics';
			}
			if ( in_array( $menu_id, array( 'dashboard', 'reports', 'leads', 'customers', 'staff', 'tickets', 'consultations', 'projects', 'tasks', 'chat', 'time-tracking', 'appointments', 'invoices', 'services', 'campaigns', 'contracts', 'documents', 'logs', 'licenses', 'settings', 'profile' ), true ) ) {
				return $menu_id;
			}
		}

		if ( '' === $path ) {
			return '';
		}

		$parts  = explode( '/', $path );
		$prefix = $parts[0];

		if ( 'admin' === $prefix ) {
			if ( isset( $parts[1] ) && 'integrations' === $parts[1] && isset( $parts[2] ) && 'bale' === $parts[2] ) {
				return 'bale-business';
			}
			if ( isset( $parts[1] ) && 'analytics' === $parts[1] ) {
				return 'visitor-statistics';
			}
			if ( isset( $parts[1] ) && 'logs' === $parts[1] ) {
				return 'logs';
			}
			if ( isset( $parts[1] ) && 'licenses' === $parts[1] ) {
				return 'licenses';
			}
			return 'settings';
		}

		$segment_map = array(
			'hrm'     => array(
				'staff'       => 'staff',
				'attendance'  => 'hrm-attendance',
				'leave'       => 'hrm-leave',
				'payroll'     => 'hrm-payroll',
				'recruitment' => 'hrm-recruitment',
				'performance' => 'hrm-performance',
				'training'    => 'hrm-training',
			),
			'crm'     => array(
				'leads'         => 'leads',
				'customers'     => 'customers',
				'tickets'       => 'tickets',
				'consultations' => 'consultations',
			),
			'pm'      => array(
				'projects'      => 'projects',
				'tasks'         => 'tasks',
				'chat'          => 'chat',
				'time-tracking' => 'time-tracking',
				'appointments'  => 'appointments',
			),
			'sales'   => array(
				'invoices'  => 'invoices',
				'catalog'   => 'services',
				'campaigns' => 'campaigns',
			),
			'docs'    => array(
				'contracts' => 'contracts',
				'files'     => 'documents',
			),
			'finance' => 'accounting',
			'scm'     => 'accounting',
			'mfg'     => 'settings',
		);

		if ( isset( $segment_map[ $prefix ] ) ) {
			$mapped = $segment_map[ $prefix ];
			if ( is_string( $mapped ) ) {
				return $mapped;
			}
			$seg = isset( $parts[1] ) ? $parts[1] : '';
			if ( $seg && isset( $mapped[ $seg ] ) ) {
				return $mapped[ $seg ];
			}
		}

		if ( isset( $parts[0] ) && ! isset( $segment_map[ $prefix ] ) ) {
			return $parts[0];
		}

		return $prefix;
	}

	/**
	 * @param string $route_slug Route key from WebinoCRM_Dashboard_Router.
	 * @return bool
	 */
	public static function can_access_route( $route_slug ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$slug   = (string) $route_slug;
		$routes = WebinoCRM_Dashboard_Router::get_routes();
		if ( isset( $routes[ $slug ] ) ) {
			$role = self::crm_role();
			return in_array( $role, (array) $routes[ $slug ]['roles'], true );
		}

		if ( 'modirpayamak' === $slug && function_exists( 'webinocrm_user_can_manage_modirpayamak' ) ) {
			return webinocrm_user_can_manage_modirpayamak();
		}
		if ( 'marketplace' === $slug && function_exists( 'webinocrm_user_can_manage_marketplace' ) ) {
			return webinocrm_user_can_manage_marketplace();
		}

		$role = self::crm_role();
		if ( in_array( $role, array( 'system_manager', 'administrator' ), true ) && class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			foreach ( WebinoCRM_Erp_Module_Registry::role_menu_ids() as $allowed_ids ) {
				if ( in_array( $slug, $allowed_ids, true ) ) {
					return true;
				}
			}
			if ( in_array( $slug, array( 'dashboard', 'reports', 'settings', 'accounting', 'bale-business', 'logs', 'licenses', 'visitor-statistics' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current user may see a bootstrap sidebar node.
	 *
	 * @param string $path    Module path.
	 * @param string $menu_id Menu item id.
	 * @return bool
	 */
	public static function can_access_menu_path( $path, $menu_id = '' ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$menu_id = (string) $menu_id;
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) && '' !== $menu_id ) {
			$sub_key = WebinoCRM_Erp_Module_Registry::submodule_key_for_menu_id( $menu_id );
			if ( $sub_key && ! WebinoCRM_Erp_Module_Registry::is_submodule_enabled( $sub_key ) ) {
				return false;
			}
			if ( in_array( $menu_id, WebinoCRM_Erp_Module_Registry::distribution_menu_ids(), true )
				&& ! WebinoCRM_Erp_Module_Registry::is_module_enabled( 'distribution' ) ) {
				return false;
			}
		}
		$role = self::crm_role();
		if ( in_array( $role, array( 'system_manager', 'administrator' ), true ) ) {
			return true;
		}
		$slug = self::resolve_route_slug_from_path( $path, $menu_id );
		return self::can_access_route( $slug );
	}

	/**
	 * @return string
	 */
	public static function login_nonce_action() {
		return 'webinocrm_login';
	}

	/**
	 * Grant webinocrm_route_* capabilities for sidebar menu items (including nested children).
	 *
	 * @param array<string,bool> $out   Capability map (by reference).
	 * @param array<int,mixed>   $items Menu rows.
	 * @return void
	 */
	private static function collect_menu_capabilities( array &$out, array $items ) {
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['id'] ) ) {
				$out[ 'webinocrm_route_' . sanitize_key( (string) $item['id'] ) ] = true;
			}
			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				self::collect_menu_capabilities( $out, $item['children'] );
			}
		}
	}

	/**
	 * @return array<string,bool>
	 */
	public static function crm_capabilities_map() {
		$routes = WebinoCRM_Dashboard_Router::get_routes();
		$role   = self::crm_role();
		$out    = array(
			'crm_role' => true,
		);
		foreach ( $routes as $slug => $route ) {
			$key         = 'webinocrm_route_' . ( $slug ? $slug : 'home' );
			$out[ $key ] = in_array( $role, (array) $route['roles'], true );
		}
		if ( class_exists( 'WebinoCRM_Sidebar_Menu_Builder' ) ) {
			$menu = WebinoCRM_Sidebar_Menu_Builder::build_menu( $role );
			self::collect_menu_capabilities( $out, $menu );
		}
		return $out;
	}
}
