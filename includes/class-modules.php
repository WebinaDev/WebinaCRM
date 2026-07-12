<?php
/**
 * CRM dashboard module registry (sidebar).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds filterable module tree for React sidebar.
 */
class WebinoCRM_Modules {

	/**
	 * Remix icon class → lucide icon id.
	 *
	 * @var array<string,string>
	 */
	private static $icon_map = array(
		'ri-home-4-line'              => 'layout-dashboard',
		'ri-folder-2-line'            => 'folder',
		'ri-file-text-line'            => 'file-text',
		'ri-service-line'             => 'package',
		'ri-file-list-3-line'         => 'file-stack',
		'ri-customer-service-2-line'  => 'headphones',
		'ri-task-line'                => 'list-todo',
		'ri-calendar-check-line'      => 'calendar-check',
		'ri-user-add-line'            => 'user-plus',
		'ri-key-line'                 => 'key',
		'ri-group-line'               => 'users',
		'ri-user-star-line'           => 'user-cog',
		'ri-discuss-line'             => 'messages-square',
		'ri-megaphone-line'           => 'megaphone',
		'ri-bar-chart-box-line'       => 'bar-chart-3',
		'ri-file-list-2-line'         => 'scroll-text',
		'ri-line-chart-line'          => 'line-chart',
		'ri-settings-3-line'          => 'settings',
		'ri-robot-2-line'             => 'bot',
		'ri-calculator-line'          => 'calculator',
		'ri-user-line'                => 'user',
		'ri-box-3-line'               => 'box',
		'ri-bank-line'                => 'landmark',
		'ri-exchange-dollar-line'     => 'arrow-left-right',
		'ri-bank-card-line'           => 'credit-card',
		'ri-book-open-line'           => 'book-open',
		'ri-book-2-line'              => 'book',
		'ri-bar-chart-2-line'         => 'bar-chart-2',
		'ri-calendar-line'            => 'calendar',
		'ri-message-2-line'           => 'message-square',
		'ri-time-line'                => 'clock',
		'ri-send-plane-line'          => 'send',
		'ri-wallet-line'              => 'wallet',
		'ri-shopping-cart-line'       => 'shopping-cart',
		'ri-code-box-line'            => 'code',
		'ri-contacts-book-line'       => 'contact',
		'ri-phone-line'               => 'phone',
		'ri-user-settings-line'       => 'user-round-cog',
		'ri-customer-service-line'    => 'life-buoy',
		'ri-draft-line'               => 'file-pen',
		'ri-store-2-line'             => 'store',
		'ri-folder-line'              => 'folder',
		'ri-shopping-bag-line'        => 'shopping-bag',
		'ri-key-2-line'               => 'key-round',
		'ri-git-branch-line'          => 'git-branch',
		'ri-calendar-event-line'      => 'calendar-days',
		'ri-money-dollar-circle-line' => 'circle-dollar-sign',
		'ri-graduation-cap-line'      => 'graduation-cap',
	);

	/**
	 * @param string $remix_or_lucide Icon from menu builder.
	 * @return string Lucide icon id.
	 */
	public static function map_icon( $remix_or_lucide ) {
		$icon = (string) $remix_or_lucide;
		if ( isset( self::$icon_map[ $icon ] ) ) {
			return self::$icon_map[ $icon ];
		}
		if ( 0 === strpos( $icon, 'ri-' ) ) {
			return 'circle';
		}
		return $icon ? $icon : 'circle';
	}

	/**
	 * @param string $user_role CRM role slug.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_modules_for_role( $user_role = '' ) {
		if ( ! class_exists( 'WebinoCRM_Sidebar_Menu_Builder' ) ) {
			return array();
		}
		$raw = WebinoCRM_Sidebar_Menu_Builder::build_menu( $user_role );
		return self::menu_items_to_modules( $raw );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_default_modules() {
		$role = WebinoCRM_REST_Base::crm_role();
		$mods = self::get_modules_for_role( $role );
		/**
		 * Filter CRM dashboard sidebar modules.
		 *
		 * @param array<int,array<string,mixed>> $mods Module tree.
		 * @param string                         $role CRM role.
		 */
		return apply_filters( 'webinocrm_modules', $mods, $role );
	}

	/**
	 * Convert legacy flat menu (categories + links) to module tree.
	 *
	 * @param array<int,array<string,mixed>> $items Menu from Sidebar_Menu_Builder.
	 * @return array<int,array<string,mixed>>
	 */
	private static function menu_items_to_modules( array $items ) {
		$modules  = array();
		$current  = null;
		$children = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['type'] ) && 'category' === $item['type'] ) {
				if ( null !== $current && ! empty( $children ) ) {
					$current['children'] = $children;
					$modules[]           = $current;
				}
				$title    = isset( $item['title'] ) ? (string) $item['title'] : '';
				$cat_id   = ! empty( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : sanitize_key( 'cat-' . md5( $title ) );
				$current  = array(
					'id'         => $cat_id,
					'title'      => $title,
					'path'       => '#',
					'capability' => 'read',
					'icon'       => 'folder',
					'children'   => array(),
				);
				$children = array();
				continue;
			}

			if ( ! empty( $item['type'] ) && 'menu' === $item['type'] ) {
				$menu_children = array();
				if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
					foreach ( $item['children'] as $child ) {
						if ( ! is_array( $child ) ) {
							continue;
						}
						$child_id  = isset( $child['id'] ) ? (string) $child['id'] : '';
						$child_url = isset( $child['url'] ) ? (string) $child['url'] : '';
						$menu_children[] = array(
							'id'         => $child_id ? $child_id : sanitize_key( self::url_to_path( $child_url ) ),
							'title'      => isset( $child['title'] ) ? (string) $child['title'] : $child_id,
							'path'       => self::url_to_path( $child_url ),
							'capability' => 'read',
							'icon'       => self::map_icon( isset( $child['icon'] ) ? (string) $child['icon'] : '' ),
						);
					}
				}
				$url  = isset( $item['url'] ) ? (string) $item['url'] : '';
				$path = self::url_to_path( $url );
				$id   = isset( $item['id'] ) ? (string) $item['id'] : sanitize_key( $path );
				$node = array(
					'id'         => $id,
					'title'      => isset( $item['title'] ) ? (string) $item['title'] : $id,
					'path'       => $path,
					'capability' => 'read',
					'icon'       => self::map_icon( isset( $item['icon'] ) ? (string) $item['icon'] : '' ),
					'children'   => $menu_children,
				);
				if ( null === $current ) {
					$modules[] = $node;
				} else {
					$children[] = $node;
				}
				continue;
			}

			$id  = isset( $item['id'] ) ? (string) $item['id'] : '';
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			$path = self::url_to_path( $url );
			$node = array(
				'id'         => $id ? $id : sanitize_key( $path ),
				'title'      => isset( $item['title'] ) ? (string) $item['title'] : $id,
				'path'       => $path,
				'capability' => 'read',
				'icon'       => self::map_icon( isset( $item['icon'] ) ? (string) $item['icon'] : '' ),
			);

			if ( null === $current ) {
				if ( ! empty( $item['pinned'] ) ) {
					$node['pinned'] = true;
				}
				$modules[] = $node;
			} else {
				$children[] = $node;
			}
		}

		if ( null !== $current ) {
			if ( ! empty( $children ) ) {
				$current['children'] = $children;
			}
			$modules[] = $current;
		}

		return $modules;
	}

	/**
	 * @param string $url Absolute dashboard URL.
	 * @return string Router path e.g. /projects.
	 */
	private static function url_to_path( $url ) {
		$base = trailingslashit( home_url( '/dashboard' ) );
		if ( 0 === strpos( $url, $base ) ) {
			$rel = substr( $url, strlen( rtrim( $base, '/' ) ) );
			return $rel ? $rel : '/';
		}
		$parsed = wp_parse_url( $url, PHP_URL_PATH );
		if ( is_string( $parsed ) && preg_match( '#/dashboard(.*)$#', $parsed, $m ) ) {
			return $m[1] ? $m[1] : '/';
		}
		return '/';
	}

	/**
	 * Filter modules by CRM route access.
	 *
	 * @param array<int,array<string,mixed>> $modules Modules.
	 * @return array<int,array<string,mixed>>
	 */
	public static function filter_by_role( array $modules ) {
		$out = array();
		foreach ( $modules as $mod ) {
			$filtered = self::filter_node( $mod );
			if ( null !== $filtered ) {
				$out[] = $filtered;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $node Module node.
	 * @return array<string,mixed>|null
	 */
	private static function filter_node( array $node ) {
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$kids = array();
			foreach ( $node['children'] as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$fc = self::filter_node( $child );
				if ( null !== $fc ) {
					$kids[] = $fc;
				}
			}
			if ( empty( $kids ) && ( ! isset( $node['path'] ) || '#' === $node['path'] ) ) {
				return null;
			}
			$node['children'] = $kids;
			return $node;
		}

		$path    = isset( $node['path'] ) ? (string) $node['path'] : '/';
		$menu_id = isset( $node['id'] ) ? (string) $node['id'] : '';
		if ( ! WebinoCRM_REST_Base::can_access_menu_path( $path, $menu_id ) ) {
			return null;
		}
		return $node;
	}
}
