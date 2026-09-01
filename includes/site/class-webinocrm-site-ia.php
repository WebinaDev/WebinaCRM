<?php
/**
 * IA helpers: mega menu, URLs, child links.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_IA {

	/**
	 * @var array<string,mixed>|null
	 */
	private static $tree = null;

	/**
	 * Load IA tree from bundled JSON.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_tree() {
		if ( null !== self::$tree ) {
			return self::$tree;
		}

		$file = WEBINOCRM_SITE_DIR . 'data/ia-tree.json';
		if ( ! is_readable( $file ) ) {
			self::$tree = array( 'tree' => array(), 'leaf_count' => 0 );
			return self::$tree;
		}

		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			$data = array( 'tree' => array(), 'leaf_count' => 0 );
		}
		self::$tree = $data;
		return self::$tree;
	}

	/**
	 * Root node.
	 *
	 * @return array<string,mixed>
	 */
	public static function root() {
		$data = self::get_tree();
		return isset( $data['tree'] ) && is_array( $data['tree'] ) ? $data['tree'] : array();
	}

	/**
	 * Relative path after IA root.
	 *
	 * @param string $path Full path.
	 * @return string
	 */
	public static function relative_path( $path ) {
		$path = trim( (string) $path, '/' );
		if ( '' === $path ) {
			return '';
		}
		$parts = explode( '/', $path );
		if ( count( $parts ) <= 1 ) {
			return '';
		}
		array_shift( $parts );
		return implode( '/', $parts );
	}

	/**
	 * Public URL for an IA node.
	 *
	 * @param array<string,mixed> $node Node.
	 * @return string
	 */
	public static function url_for_node( $node ) {
		$rel = self::relative_path( (string) ( $node['path'] ?? '' ) );
		if ( '' === $rel ) {
			return home_url( '/' );
		}
		return trailingslashit( home_url( '/' . $rel ) );
	}

	/**
	 * Depth within services/solutions section (segment count).
	 *
	 * @param array<string,mixed> $node Node.
	 * @return int
	 */
	public static function section_depth( $node ) {
		$rel = self::relative_path( (string) ( $node['path'] ?? '' ) );
		if ( '' === $rel ) {
			return 0;
		}
		return count( explode( '/', $rel ) );
	}

	/**
	 * Find first child of root matching section + slug.
	 *
	 * @param string $section Section key.
	 * @param string $slug Slug.
	 * @return array<string,mixed>|null
	 */
	public static function find_section_node( $section, $slug = '' ) {
		foreach ( (array) ( self::root()['children'] ?? array() ) as $child ) {
			if ( (string) ( $child['section'] ?? '' ) !== $section ) {
				continue;
			}
			if ( '' === $slug || (string) ( $child['slug'] ?? '' ) === $slug ) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * Direct children link rows for hub grids.
	 *
	 * @param array<string,mixed> $node Parent node.
	 * @param int                 $limit Max items.
	 * @return array<int,array<string,string>>
	 */
	public static function child_links( $node, $limit = 12 ) {
		$out = array();
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$title = WebinoCRM_Site_Content::clean_title_public( (string) ( $child['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$out[] = array(
				'title' => $title,
				'url'   => self::url_for_node( $child ),
				'excerpt' => WebinoCRM_Site_Content::short_blurb( $title ),
			);
		}
		return $out;
	}

	/**
	 * Mega menu columns for services or solutions.
	 *
	 * @param string $section services|solutions.
	 * @return array<int,array<string,mixed>>
	 */
	public static function mega_menu_tree( $section ) {
		$root = self::find_section_node( $section );
		if ( ! $root ) {
			return array();
		}

		$columns = array();
		foreach ( (array) ( $root['children'] ?? array() ) as $hub ) {
			$hub_title = WebinoCRM_Site_Content::clean_title_public( (string) ( $hub['title'] ?? '' ) );
			$links     = array();
			foreach ( (array) ( $hub['children'] ?? array() ) as $group ) {
				$group_title = WebinoCRM_Site_Content::clean_title_public( (string) ( $group['title'] ?? '' ) );
				if ( $group_title ) {
					$links[] = array(
						'title' => $group_title,
						'url'   => self::url_for_node( $group ),
					);
				}
				foreach ( (array) ( $group['children'] ?? array() ) as $leaf ) {
					if ( count( $links ) >= 6 ) {
						break 2;
					}
					$leaf_title = WebinoCRM_Site_Content::clean_title_public( (string) ( $leaf['title'] ?? '' ) );
					if ( $leaf_title ) {
						$links[] = array(
							'title' => $leaf_title,
							'url'   => self::url_for_node( $leaf ),
						);
					}
				}
			}
			$columns[] = array(
				'title' => $hub_title,
				'slug'  => (string) ( $hub['slug'] ?? '' ),
				'url'   => self::url_for_node( $hub ),
				'links' => array_slice( $links, 0, 6 ),
			);
		}
		return $columns;
	}

	/**
	 * Flatten all page nodes (excluding root container).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all_pages() {
		$pages = array();
		self::walk_pages( self::root(), $pages, 0 );
		return $pages;
	}

	/**
	 * @param array<string,mixed>            $node Node.
	 * @param array<int,array<string,mixed>> $pages Accumulator.
	 * @param int                            $depth Depth.
	 */
	private static function walk_pages( $node, &$pages, $depth ) {
		if ( $depth > 0 && ! empty( $node['title'] ) ) {
			$pages[] = $node;
		}
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
			self::walk_pages( $child, $pages, $depth + 1 );
		}
	}

	/**
	 * Pages that should become WP pages (skip portfolio archive facets).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function seedable_pages() {
		$skip_titles = array(
			'آرشیو کل پروژه‌ها',
			'فیلتر بر اساس خدمات',
			'فیلتر بر اساس صنعت',
			'صفحه جزئیات پروژه Case Study',
		);

		return array_values(
			array_filter(
				self::all_pages(),
				static function ( $node ) use ( $skip_titles ) {
					return ! in_array( (string) ( $node['title'] ?? '' ), $skip_titles, true );
				}
			)
		);
	}
}
