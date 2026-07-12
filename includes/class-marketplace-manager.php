<?php
/**
 * Marketplace catalog, entitlements, and orders.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data layer for module marketplace.
 */
class WebinoCRM_Marketplace_Manager {

	const ENTITLEMENT_OWNED   = 'owned';
	const ENTITLEMENT_EXPIRED = 'expired';

	const ORDER_PENDING = 'pending';
	const ORDER_PAID    = 'paid';
	const ORDER_FAILED  = 'failed';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_create_tables' ), 2 );
	}

	/**
	 * @return void
	 */
	public static function maybe_create_tables() {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_marketplace_categories';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) !== $table ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-installer.php';
			WebinoCRM_Installer::create_marketplace_tables();
			self::seed_builtin_modules();
		}
		self::maybe_migrate_schema();
	}

	/**
	 * Add Gitea columns and releases table on existing installs.
	 *
	 * @return void
	 */
	public static function maybe_migrate_schema() {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-installer.php';
		if ( '1' !== get_option( 'webinocrm_marketplace_schema_v2', '' ) ) {
			WebinoCRM_Installer::create_marketplace_tables();
			update_option( 'webinocrm_marketplace_schema_v2', '1', false );
		}
		if ( '1' !== get_option( 'webinocrm_marketplace_schema_v3', '' ) ) {
			self::migrate_schema_v3();
			update_option( 'webinocrm_marketplace_schema_v3', '1', false );
		}
		if ( '1' !== get_option( 'webinocrm_marketplace_schema_v4', '' ) ) {
			self::migrate_schema_v4();
			update_option( 'webinocrm_marketplace_schema_v4', '1', false );
		}
		if ( '1' !== get_option( 'webinocrm_marketplace_schema_v5', '' ) ) {
			self::migrate_schema_v5();
			update_option( 'webinocrm_marketplace_schema_v5', '1', false );
		}
	}

	/**
	 * Core dashboard product column + seed.
	 *
	 * @return void
	 */
	public static function migrate_schema_v3() {
		global $wpdb;
		$table = self::modules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_results( "SHOW COLUMNS FROM `$table` LIKE 'is_core'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE `$table` ADD COLUMN is_core tinyint(1) DEFAULT 0 AFTER is_builtin" );
		}
		self::seed_core_module();
	}

	/**
	 * Module README markdown column.
	 *
	 * @return void
	 */
	public static function migrate_schema_v4() {
		global $wpdb;
		$table = self::modules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_results( "SHOW COLUMNS FROM `$table` LIKE 'readme_md'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE `$table` ADD COLUMN readme_md longtext DEFAULT NULL AFTER description" );
		}
	}

	/**
	 * Parent marketplace product for sub-modules.
	 *
	 * @return void
	 */
	public static function migrate_schema_v5() {
		global $wpdb;
		$table = self::modules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_results( "SHOW COLUMNS FROM `$table` LIKE 'parent_module_id'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE `$table` ADD COLUMN parent_module_id bigint(20) DEFAULT NULL AFTER category_id, ADD KEY parent_module_id (parent_module_id)" );
		}
		self::seed_erp_submodule_parent_links();
	}

	/**
	 * Link known ERP marketplace sub-module slugs to a parent product when possible.
	 *
	 * @return void
	 */
	public static function seed_erp_submodule_parent_links() {
		if ( ! class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			return;
		}
		global $wpdb;
		$table = self::modules_table();
		foreach ( WebinoCRM_Erp_Module_Registry::get_submodules() as $sub ) {
			$slug = sanitize_key( (string) ( $sub['marketplace_slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$module = self::get_module_by_slug( $slug );
			if ( ! $module || ! empty( $module['parent_module_id'] ) ) {
				continue;
			}
			$erp_parent = sanitize_key( (string) ( $sub['parent_module'] ?? '' ) );
			$parent_id  = self::resolve_marketplace_parent_id_for_erp( $erp_parent );
			if ( $parent_id ) {
				$wpdb->update(
					$table,
					array( 'parent_module_id' => $parent_id ),
					array( 'id' => (int) $module['id'] )
				);
			}
		}
	}

	/**
	 * @param string $erp_parent_module ERP registry parent module id (e.g. sales).
	 * @return int|null
	 */
	public static function resolve_marketplace_parent_id_for_erp( $erp_parent_module ) {
		$erp_parent_module = sanitize_key( (string) $erp_parent_module );
		if ( '' === $erp_parent_module ) {
			return null;
		}
		$candidates = array( $erp_parent_module, $erp_parent_module . '-module' );
		foreach ( $candidates as $candidate ) {
			$row = self::get_module_by_slug( $candidate );
			if ( $row && empty( $row['is_core'] ) ) {
				return (int) $row['id'];
			}
		}
		return null;
	}

	/** @var string */
	const CORE_MODULE_SLUG     = 'webino-dashboard';
	const CRM_CORE_MODULE_SLUG = 'webinocrm';

	/**
	 * @return string
	 */
	public static function categories_table() {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_marketplace_categories';
	}

	/**
	 * @return string
	 */
	public static function modules_table() {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_marketplace_modules';
	}

	/**
	 * @return string
	 */
	public static function entitlements_table() {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_marketplace_entitlements';
	}

	/**
	 * @return string
	 */
	public static function orders_table() {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_marketplace_orders';
	}

	/**
	 * @param bool $active_only Active rows only.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_categories( $active_only = true ) {
		global $wpdb;
		$table = self::categories_table();
		$sql   = "SELECT * FROM $table";
		if ( $active_only ) {
			$sql .= " WHERE status = 'active'";
		}
		$sql .= ' ORDER BY sort ASC, name ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * @param string $category_slug Category slug or empty for all.
	 * @param bool   $active_only   Active modules only.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_modules( $category_slug = '', $active_only = true, $include_core = false ) {
		global $wpdb;
		$table = self::modules_table();
		$cat   = self::categories_table();
		$sql   = "SELECT m.*, c.slug AS category_slug, c.name AS category_name,
			p.slug AS parent_slug, p.name AS parent_name
			FROM $table m
			LEFT JOIN $cat c ON c.id = m.category_id
			LEFT JOIN $table p ON p.id = m.parent_module_id";
		$where = array();
		if ( $active_only ) {
			$where[] = "m.status = 'active'";
		}
		if ( ! $include_core ) {
			$where[] = '(m.is_core IS NULL OR m.is_core = 0)';
		}
		if ( '' !== $category_slug ) {
			$where[] = $wpdb->prepare( 'c.slug = %s', $category_slug );
		}
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY m.sort ASC, m.name ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A ) ?: array();
		foreach ( $rows as &$row ) {
			$row = self::enrich_module_catalog_meta( self::format_module_public( $row ) );
		}
		return $rows;
	}

	/**
	 * Customer catalog: active modules with a published, installable package (excludes core).
	 *
	 * @param array<int,array<string,mixed>> $modules Formatted module rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function filter_installable_catalog_modules( array $modules ) {
		return array_values(
			array_filter(
				$modules,
				static function ( $mod ) {
					if ( ! is_array( $mod ) ) {
						return false;
					}
					if ( ! empty( $mod['is_core'] ) ) {
						return false;
					}
					if ( 'active' !== (string) ( $mod['status'] ?? '' ) ) {
						return false;
					}
					return ! empty( $mod['package_available'] );
				}
			)
		);
	}

	/**
	 * @param array<string,mixed> $row Raw or formatted module row.
	 * @return array<string,mixed>
	 */
	public static function attach_parent_module_fields( array $row ) {
		$parent_id = isset( $row['parent_module_id'] ) ? (int) $row['parent_module_id'] : 0;
		if ( $parent_id <= 0 ) {
			$row['parent_module_id'] = null;
			$row['parent_slug']      = null;
			$row['parent_name']      = null;
			return $row;
		}
		if ( empty( $row['parent_slug'] ) || empty( $row['parent_name'] ) ) {
			$parent = self::get_module_by_id( $parent_id );
			if ( $parent ) {
				$row['parent_slug'] = (string) ( $parent['slug'] ?? '' );
				$row['parent_name'] = (string) ( $parent['name'] ?? '' );
			}
		}
		$row['parent_module_id'] = $parent_id;
		return $row;
	}

	/**
	 * @param string $slug Module slug.
	 * @return array<string,mixed>|null
	 */
	public static function get_module_by_slug( $slug ) {
		global $wpdb;
		$table = self::modules_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE slug = %s LIMIT 1", sanitize_key( $slug ) ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Formatted catalog module row by slug (category + parent joins, release metadata).
	 *
	 * @param string $slug Module slug.
	 * @return array<string,mixed>|null
	 */
	public static function get_module_catalog_by_slug( $slug ) {
		global $wpdb;
		$slug = sanitize_key( (string) $slug );
		if ( '' === $slug ) {
			return null;
		}
		$table = self::modules_table();
		$cat   = self::categories_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.*, c.slug AS category_slug, c.name AS category_name,
				p.slug AS parent_slug, p.name AS parent_name
				FROM $table m
				LEFT JOIN $cat c ON c.id = m.category_id
				LEFT JOIN $table p ON p.id = m.parent_module_id
				WHERE m.slug = %s LIMIT 1",
				$slug
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row = self::attach_parent_module_fields( $row );
		return self::enrich_module_catalog_meta( self::format_module_public( $row ) );
	}

	/**
	 * @param int $id Module id.
	 * @return array<string,mixed>|null
	 */
	public static function get_module_by_id( $id ) {
		global $wpdb;
		$table = self::modules_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	public static function format_module_public( $row ) {
		$icon = ! empty( $row['icon_url'] ) ? (string) $row['icon_url'] : '';
		if ( $icon && is_numeric( $icon ) ) {
			$url = wp_get_attachment_url( (int) $icon );
			$icon = $url ? $url : '';
		}
		$gitea_owner = (string) ( $row['gitea_owner'] ?? '' );
		$gitea_repo  = (string) ( $row['gitea_repo'] ?? '' );
		$repo_url    = '';
		if ( 'gitea' === (string) ( $row['package_source'] ?? '' ) && $gitea_repo && class_exists( 'WebinoCRM_Gitea_Client' ) ) {
			$repo_url = WebinoCRM_Gitea_Client::repo_html_url( $gitea_repo );
		}
		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'slug'            => (string) ( $row['slug'] ?? '' ),
			'name'            => (string) ( $row['name'] ?? '' ),
			'description'     => (string) ( $row['description'] ?? '' ),
			'readme_md'       => (string) ( $row['readme_md'] ?? '' ),
			'icon_url'        => $icon,
			'detail_url'      => (string) ( $row['detail_url'] ?? '' ),
			'category_id'       => isset( $row['category_id'] ) ? (int) $row['category_id'] : null,
			'parent_module_id'  => ! empty( $row['parent_module_id'] ) ? (int) $row['parent_module_id'] : null,
			'parent_slug'       => ! empty( $row['parent_slug'] ) ? (string) $row['parent_slug'] : null,
			'parent_name'       => ! empty( $row['parent_name'] ) ? (string) $row['parent_name'] : null,
			'children'          => array(),
			'category_slug'     => (string) ( $row['category_slug'] ?? '' ),
			'category_name'     => (string) ( $row['category_name'] ?? '' ),
			'price'           => (float) ( $row['price'] ?? 0 ),
			'currency'        => (string) ( $row['currency'] ?? 'IRT' ),
			'is_free'         => ! empty( $row['is_free'] ),
			'is_builtin'      => ! empty( $row['is_builtin'] ),
			'is_core'         => ! empty( $row['is_core'] ),
			'version'         => (string) ( $row['version'] ?? '1.0.0' ),
			'settings_area'   => (string) ( $row['settings_area'] ?? 'shop' ),
			'settings_route'  => (string) ( $row['settings_route'] ?? '' ),
			'sort'            => (int) ( $row['sort'] ?? 0 ),
			'status'          => (string) ( $row['status'] ?? 'active' ),
			'catalog_status'  => (string) ( $row['status'] ?? 'active' ),
			'package_source'  => (string) ( $row['package_source'] ?? 'local' ),
			'gitea_owner'     => $gitea_owner,
			'gitea_repo'      => $gitea_repo,
			'gitea_repo_id'   => isset( $row['gitea_repo_id'] ) ? (int) $row['gitea_repo_id'] : null,
			'gitea_repo_url'  => $repo_url,
			'gitea_repo_linked' => ! empty( $row['gitea_repo_id'] ),
			'latest_release_id' => isset( $row['latest_release_id'] ) ? (int) $row['latest_release_id'] : null,
			'latest_version'    => (string) ( $row['version'] ?? '1.0.0' ),
			'package_available' => false,
		);
	}

	/**
	 * Align catalog fields with latest published release and package readiness.
	 *
	 * @param array<string,mixed> $module Formatted module row.
	 * @return array<string,mixed>
	 */
	public static function enrich_module_catalog_meta( array $module ) {
		$mid = (int) ( $module['id'] ?? 0 );
		if ( ! $mid ) {
			return $module;
		}
		$raw = self::get_module_by_id( $mid );
		if ( ! is_array( $raw ) ) {
			return $module;
		}
		$latest_version = (string) ( $module['version'] ?? '1.0.0' );
		$release          = null;
		if ( class_exists( 'WebinoCRM_Marketplace_Release_Service' ) ) {
			$release = WebinoCRM_Marketplace_Release_Service::find_published_release( $mid, '', $raw );
			if ( $release && ! empty( $release['version'] ) ) {
				$latest_version = (string) $release['version'];
			}
		}
		$module['latest_version']        = $latest_version;
		$module['version']               = $latest_version;
		$module['has_published_release']  = is_array( $release ) && ! empty( $release );
		$path                            = self::resolve_package_path( $raw, $latest_version );
		$module['package_available']     = is_string( $path ) && '' !== $path && is_readable( $path );
		$latest_updated_at               = '';
		if ( is_array( $release ) ) {
			if ( ! empty( $release['published_at'] ) ) {
				$latest_updated_at = (string) $release['published_at'];
			} elseif ( ! empty( $release['updated_at'] ) ) {
				$latest_updated_at = (string) $release['updated_at'];
			}
		}
		if ( '' === $latest_updated_at && ! empty( $raw['updated_at'] ) ) {
			$latest_updated_at = (string) $raw['updated_at'];
		}
		$module['latest_updated_at'] = '' !== $latest_updated_at ? $latest_updated_at : null;
		$module['latest_changelog']  = is_array( $release ) ? (string) ( $release['changelog'] ?? '' ) : '';
		return $module;
	}

	/**
	 * Attach live Gitea repository metadata to a formatted module row.
	 *
	 * @param array<string,mixed> $module Formatted module.
	 * @return array<string,mixed>
	 */
	public static function enrich_module_gitea_meta( array $module ) {
		if ( empty( $module['gitea_repo_linked'] ) || ! class_exists( 'WebinoCRM_Gitea_Client' ) || ! WebinoCRM_Gitea_Client::is_configured() ) {
			return $module;
		}
		$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
		$repo  = (string) ( $module['gitea_repo'] ?: $module['slug'] ?? '' );
		if ( '' === $repo ) {
			return $module;
		}
		$res = WebinoCRM_Gitea_Client::get_repo( $owner, $repo );
		if ( empty( $res['ok'] ) || ! is_array( $res['data'] ) ) {
			return $module;
		}
		$data = $res['data'];
		$module['gitea_private']       = ! empty( $data['private'] );
		$module['gitea_default_branch'] = (string) ( $data['default_branch'] ?? 'main' );
		$module['gitea_html_url']      = (string) ( $data['html_url'] ?? $module['gitea_repo_url'] ?? '' );
		if ( empty( $module['gitea_repo_url'] ) && ! empty( $module['gitea_html_url'] ) ) {
			$module['gitea_repo_url'] = (string) $module['gitea_html_url'];
		}
		return $module;
	}

	/**
	 * Resolve ERP submodule settings key for a marketplace slug.
	 *
	 * @param string $slug Module slug.
	 * @return string|null
	 */
	public static function erp_submodule_settings_key_for_slug( $slug ) {
		if ( ! class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			return null;
		}
		$slug = sanitize_key( (string) $slug );
		foreach ( WebinoCRM_Erp_Module_Registry::get_submodules() as $sub ) {
			if ( ! empty( $sub['marketplace_slug'] ) && (string) $sub['marketplace_slug'] === $slug ) {
				return (string) ( $sub['settings_key'] ?? '' );
			}
		}
		return null;
	}

	/**
	 * @param string $domain Normalized domain.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_entitlements_for_domain( $domain ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		if ( '' === $domain ) {
			return array();
		}
		$ent = self::entitlements_table();
		$mod = self::modules_table();
		$sql = "SELECT e.*, m.slug AS module_slug, m.name AS module_name, m.is_free, m.is_builtin
			FROM $ent e
			INNER JOIN $mod m ON m.id = e.module_id
			WHERE e.domain = %s AND e.status = %s
			ORDER BY m.sort ASC, m.name ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare( $sql, $domain, self::ENTITLEMENT_OWNED ),
			ARRAY_A
		) ?: array();
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $module_id Module id.
	 * @return bool
	 */
	public static function domain_has_entitlement( $domain, $module_id ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$ent    = self::entitlements_table();
		$found  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $ent WHERE domain = %s AND module_id = %d AND status = %s LIMIT 1",
				$domain,
				(int) $module_id,
				self::ENTITLEMENT_OWNED
			)
		);
		return (bool) $found;
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $module_id Module id.
	 * @param int|null $order_id Order id.
	 * @param string $version Installed version label.
	 * @return bool
	 */
	public static function grant_entitlement( $domain, $module_id, $order_id = null, $version = '' ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$ent    = self::entitlements_table();
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $ent WHERE domain = %s AND module_id = %d LIMIT 1",
				$domain,
				(int) $module_id
			)
		);
		$data = array(
			'domain'             => $domain,
			'module_id'          => (int) $module_id,
			'status'             => self::ENTITLEMENT_OWNED,
			'order_id'           => $order_id ? (int) $order_id : null,
			'installed_version'  => $version ? (string) $version : null,
			'expires_at'         => null,
			'updated_at'         => current_time( 'mysql' ),
		);
		if ( $exists ) {
			$ok = (bool) $wpdb->update( $ent, $data, array( 'id' => (int) $exists ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$ok = (bool) $wpdb->insert( $ent, $data );
		}
		if ( $ok && class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			$module = self::get_module_by_id( (int) $module_id );
			if ( is_array( $module ) && ! empty( $module['slug'] ) ) {
				WebinoCRM_Erp_Module_Registry::apply_submodule_entitlement( (string) $module['slug'], true );
			}
		}
		return $ok;
	}

	/**
	 * Revoke or clear entitlement when a module is uninstalled on the customer site.
	 *
	 * @param string $domain      Licensed domain.
	 * @param string $module_slug Module slug.
	 * @return true|WP_Error
	 */
	public static function revoke_entitlement( $domain, $module_slug ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$slug   = sanitize_key( $module_slug );
		$module = self::get_module_by_slug( $slug );
		if ( ! $module ) {
			return new WP_Error( 'not_found', __( 'Module not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		$ent = self::entitlements_table();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $ent WHERE domain = %s AND module_id = %d LIMIT 1",
				$domain,
				(int) $module['id']
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return true;
		}
		$now = current_time( 'mysql' );
		if ( ! empty( $module['is_free'] ) && ! (bool) apply_filters( 'webino_crm_revoke_entitlement_on_uninstall', false ) ) {
			$wpdb->update(
				$ent,
				array(
					'installed_version' => null,
					'updated_at'        => $now,
				),
				array( 'id' => (int) $row['id'] )
			);
			return true;
		}
		$wpdb->update(
			$ent,
			array(
				'status'            => self::ENTITLEMENT_EXPIRED,
				'installed_version' => null,
				'updated_at'        => $now,
			),
			array( 'id' => (int) $row['id'] )
		);
		if ( class_exists( 'WebinoCRM_Erp_Module_Registry' ) ) {
			WebinoCRM_Erp_Module_Registry::apply_submodule_entitlement( $slug, false );
		}
		return true;
	}

	/**
	 * @param string               $domain Domain.
	 * @param array<string,mixed>  $module Module row.
	 * @return true|WP_Error
	 */
	public static function ensure_entitlement_for_install( $domain, $module ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$mid    = (int) ( $module['id'] ?? 0 );
		if ( ! $mid ) {
			return new WP_Error( 'invalid_module', __( 'Module not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( self::domain_has_entitlement( $domain, $mid ) ) {
			return true;
		}
		if ( ! empty( $module['is_free'] ) ) {
			self::grant_entitlement( $domain, $mid, null, (string) ( $module['version'] ?? '' ) );
			return true;
		}
		return new WP_Error( 'no_entitlement', __( 'Purchase required for this module.', 'webinocrm' ), array( 'status' => 403 ) );
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $module_id Module id.
	 * @param float  $amount Amount in Toman.
	 * @return int|false Order id.
	 */
	public static function create_order( $domain, $module_id, $amount ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$ok     = $wpdb->insert(
			self::orders_table(),
			array(
				'domain'     => $domain,
				'module_id'  => (int) $module_id,
				'amount'     => $amount,
				'currency'   => 'IRT',
				'status'     => self::ORDER_PENDING,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param int    $order_id Order id.
	 * @param string $authority Zarinpal authority.
	 * @return bool
	 */
	public static function set_order_authority( $order_id, $authority ) {
		global $wpdb;
		return (bool) $wpdb->update(
			self::orders_table(),
			array(
				'authority'  => sanitize_text_field( $authority ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $order_id )
		);
	}

	/**
	 * @param int    $order_id Order id.
	 * @param string $ref_id Reference id.
	 * @return bool
	 */
	public static function complete_order( $order_id, $ref_id ) {
		global $wpdb;
		$order = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::orders_table() . ' WHERE id = %d', (int) $order_id ),
			ARRAY_A
		);
		if ( ! $order ) {
			return false;
		}
		$wpdb->update(
			self::orders_table(),
			array(
				'status'     => self::ORDER_PAID,
				'ref_id'     => sanitize_text_field( $ref_id ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $order_id )
		);
		$module = self::get_module_by_id( (int) $order['module_id'] );
		if ( $module ) {
			self::grant_entitlement(
				(string) $order['domain'],
				(int) $order['module_id'],
				(int) $order_id,
				(string) ( $module['version'] ?? '' )
			);
		}
		return true;
	}

	/**
	 * @param string $domain Domain.
	 * @param string $module_slug Module slug.
	 * @return string|WP_Error Token.
	 */
	public static function create_download_token( $domain, $module_slug, $version = '' ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$module = self::get_module_by_slug( $module_slug );
		if ( ! $module ) {
			return new WP_Error( 'not_found', __( 'Module not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $module['is_core'] ) ) {
			$licensed = self::assert_licensed_domain( $domain );
			if ( is_wp_error( $licensed ) ) {
				return $licensed;
			}
		} elseif ( ! self::domain_has_entitlement( $domain, (int) $module['id'] ) ) {
			$grant = self::ensure_entitlement_for_install( $domain, $module );
			if ( is_wp_error( $grant ) ) {
				return $grant;
			}
		}
		$path = self::resolve_package_path( $module, $version );
		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'no_package',
				__( 'Package file is not available.', 'webinocrm' ),
				array(
					'status' => 404,
					'debug'  => self::package_debug_context( $module, $version ),
				)
			);
		}
		if ( class_exists( 'WebinoCRM_Marketplace_Release_Service' ) ) {
			if ( ! empty( $module['is_core'] ) ) {
				$zip_ok = WebinoCRM_Marketplace_Release_Service::validate_core_zip_contract( $path, (string) $module['slug'] );
			} else {
				$zip_ok = WebinoCRM_Marketplace_Release_Service::validate_zip_contract( $path );
			}
			if ( is_wp_error( $zip_ok ) ) {
				return $zip_ok;
			}
		}
		$release_id = 0;
		if ( class_exists( 'WebinoCRM_Marketplace_Release_Service' ) && 'gitea' === (string) ( $module['package_source'] ?? '' ) ) {
			$release = '' !== $version
				? WebinoCRM_Marketplace_Release_Service::get_published_by_version( (int) $module['id'], $version )
				: WebinoCRM_Marketplace_Release_Service::get_latest_published( (int) $module['id'] );
			if ( $release ) {
				$release_id = (int) $release['id'];
			}
		}
		$token = wp_generate_password( 32, false, false );
		set_transient(
			'webinocrm_mp_dl_' . $token,
			array(
				'domain'     => $domain,
				'module_id'  => (int) $module['id'],
				'slug'       => (string) $module['slug'],
				'path'       => $path,
				'version'    => (string) $version,
				'release_id' => $release_id,
			),
			5 * MINUTE_IN_SECONDS
		);
		return $token;
	}

	/**
	 * @param string $token Download token.
	 * @return array<string,mixed>|null
	 */
	public static function consume_download_token( $token ) {
		$key  = 'webinocrm_mp_dl_' . sanitize_key( $token );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return null;
		}
		delete_transient( $key );
		return $data;
	}

	/**
	 * Diagnostic context when a module ZIP cannot be resolved (admin / install logs).
	 *
	 * @param array<string,mixed> $module  Module row.
	 * @param string              $version Requested version or empty for latest.
	 * @return array<string,mixed>
	 */
	public static function package_debug_context( $module, $version = '' ) {
		$mid = (int) ( $module['id'] ?? 0 );
		$ctx = array(
			'slug'               => (string) ( $module['slug'] ?? '' ),
			'version'            => (string) $version,
			'package_source'     => (string) ( $module['package_source'] ?? '' ),
			'gitea_owner'        => (string) ( $module['gitea_owner'] ?? '' ),
			'gitea_repo'         => (string) ( $module['gitea_repo'] ?? $module['slug'] ?? '' ),
			'release_id'         => 0,
			'release_status'     => '',
			'package_path_db'    => '',
			'package_readable'   => false,
			'gitea_release_id'   => 0,
			'tag_name'           => '',
			'archive_tags_tried' => array(),
			'ensure_error'       => '',
			'ensure_error_code'  => '',
		);
		if ( class_exists( 'WebinoCRM_Gitea_Client' ) ) {
			if ( '' === $ctx['gitea_owner'] ) {
				$ctx['gitea_owner'] = WebinoCRM_Gitea_Client::org();
			}
			$ctx['archive_tags_tried'] = WebinoCRM_Gitea_Client::get_last_archive_tags_tried();
		}
		if ( class_exists( 'WebinoCRM_Marketplace_Release_Service' ) && 'gitea' === $ctx['package_source'] && $mid ) {
			$release = WebinoCRM_Marketplace_Release_Service::find_published_release( $mid, $version, $module );
			if ( $release ) {
				$path_db               = (string) ( $release['package_path'] ?? '' );
				$ctx['release_id']     = (int) ( $release['id'] ?? 0 );
				$ctx['release_status'] = (string) ( $release['status'] ?? '' );
				$ctx['release_version'] = (string) ( $release['version'] ?? $release['tag_name'] ?? '' );
				$ctx['package_path_db']  = $path_db;
				$ctx['package_readable'] = '' !== $path_db && is_readable( $path_db );
				$ctx['gitea_release_id'] = (int) ( $release['gitea_release_id'] ?? 0 );
				$ctx['tag_name']         = (string) ( $release['tag_name'] ?? '' );
			}
			$resolve_err = WebinoCRM_Marketplace_Release_Service::get_last_resolve_error();
			if ( $resolve_err instanceof WP_Error ) {
				$ctx['ensure_error']      = $resolve_err->get_error_message();
				$ctx['ensure_error_code'] = $resolve_err->get_error_code();
			}
			if ( class_exists( 'WebinoCRM_Gitea_Client' ) ) {
				$ctx['archive_tags_tried'] = WebinoCRM_Gitea_Client::get_last_archive_tags_tried();
			}
		}
		return $ctx;
	}

	/**
	 * @param array<string,mixed> $module Module row.
	 * @return string Empty if missing.
	 */
	public static function resolve_package_path( $module, $version = '' ) {
		if ( 'gitea' === (string) ( $module['package_source'] ?? '' ) && class_exists( 'WebinoCRM_Marketplace_Release_Service' ) ) {
			$path = WebinoCRM_Marketplace_Release_Service::resolve_package_path( $module, $version );
			if ( $path ) {
				return $path;
			}
		}
		$package = $module['package_path'] ?? '';
		if ( ! $package ) {
			return '';
		}
		if ( is_numeric( $package ) ) {
			$path = get_attached_file( (int) $package );
			return ( $path && is_readable( $path ) ) ? $path : '';
		}
		$path = (string) $package;
		return is_readable( $path ) ? $path : '';
	}

	/**
	 * @return string
	 */
	public static function packages_upload_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'webinocrm-marketplace';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * @param array<string,mixed> $data Category fields.
	 * @return int|false
	 */
	public static function save_category( array $data ) {
		global $wpdb;
		$table = self::categories_table();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$slug = sanitize_key( (string) ( $data['slug'] ?? '' ) );
		if ( '' === $slug && '' !== $name ) {
			$slug = sanitize_title( $name );
		}
		if ( '' === $slug && '' !== $name ) {
			$slug = 'cat-' . (int) ( $id ?: time() );
		}
		$row = array(
			'slug'   => $slug,
			'name'   => $name,
			'sort'   => (int) ( $data['sort'] ?? 0 ),
			'status' => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
		);
		if ( '' === $row['slug'] || '' === $row['name'] ) {
			return false;
		}
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
			return $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$ok                = $wpdb->insert( $table, $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param int $id Category id.
	 * @return bool
	 */
	public static function delete_category( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::categories_table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Accept attachment ID or URL for module icon storage.
	 *
	 * @param string $raw Raw icon value from request.
	 * @return string|null
	 */
	public static function sanitize_module_icon_url( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}
		if ( ctype_digit( $raw ) ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				return null;
			}
			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				return null;
			}
			return (string) $id;
		}
		$url = esc_url_raw( $raw );
		return '' !== $url ? $url : null;
	}

	/**
	 * @param array<string,mixed> $data Module fields.
	 * @return int|false
	 */
	public static function save_module( array $data ) {
		global $wpdb;
		$table = self::modules_table();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;
		if ( $id ) {
			$existing = self::get_module_by_id( $id );
			if ( $existing ) {
				$merge_keys = array(
					'name',
					'description',
					'icon_url',
					'detail_url',
					'category_id',
					'price',
					'currency',
					'is_free',
					'is_builtin',
					'package_source',
					'gitea_owner',
					'gitea_repo',
					'gitea_repo_id',
					'package_path',
					'version',
					'settings_area',
					'settings_route',
					'sort',
					'status',
					'parent_module_id',
				);
				foreach ( $merge_keys as $key ) {
					if ( ! array_key_exists( $key, $data ) ) {
						$data[ $key ] = $existing[ $key ] ?? null;
					}
				}
				if ( '' === sanitize_key( (string) ( $data['slug'] ?? '' ) ) ) {
					$data['slug'] = (string) ( $existing['slug'] ?? '' );
				}
			}
		}
		$slug  = sanitize_key( (string) ( $data['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return false;
		}
		$parent_module_id = null;
		if ( array_key_exists( 'parent_module_id', $data ) ) {
			$parent_module_id = ! empty( $data['parent_module_id'] ) ? (int) $data['parent_module_id'] : null;
			if ( $parent_module_id ) {
				$parent = self::get_module_by_id( $parent_module_id );
				if ( ! $parent || ! empty( $parent['is_core'] ) ) {
					return false;
				}
				if ( $id && $parent_module_id === $id ) {
					return false;
				}
				if ( ! empty( $parent['parent_module_id'] ) ) {
					return false;
				}
				if ( $id && self::module_parent_chain_contains( $parent_module_id, $id ) ) {
					return false;
				}
			}
		}
		$row = array(
			'slug'            => $slug,
			'name'            => sanitize_text_field( (string) ( $data['name'] ?? $slug ) ),
			'description'     => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'icon_url'        => array_key_exists( 'icon_url', $data )
				? self::sanitize_module_icon_url( (string) $data['icon_url'] )
				: null,
			'detail_url'      => isset( $data['detail_url'] ) ? esc_url_raw( (string) $data['detail_url'] ) : null,
			'category_id'     => ! empty( $data['category_id'] ) ? (int) $data['category_id'] : null,
			'price'           => (float) ( $data['price'] ?? 0 ),
			'currency'        => sanitize_text_field( (string) ( $data['currency'] ?? 'IRT' ) ),
			'is_free'         => ! empty( $data['is_free'] ) ? 1 : 0,
			'is_builtin'      => ! empty( $data['is_builtin'] ) ? 1 : 0,
			'package_source'  => sanitize_key( (string) ( $data['package_source'] ?? 'local' ) ),
			'gitea_owner'     => sanitize_key( (string) ( $data['gitea_owner'] ?? '' ) ),
			'gitea_repo'      => sanitize_key( (string) ( $data['gitea_repo'] ?? '' ) ),
			'gitea_repo_id'   => ! empty( $data['gitea_repo_id'] ) ? (int) $data['gitea_repo_id'] : null,
			'package_path'    => isset( $data['package_path'] ) ? (string) $data['package_path'] : null,
			'version'         => sanitize_text_field( (string) ( $data['version'] ?? '1.0.0' ) ),
			'settings_area'   => sanitize_key( (string) ( $data['settings_area'] ?? 'shop' ) ),
			'settings_route'  => isset( $data['settings_route'] ) ? sanitize_text_field( (string) $data['settings_route'] ) : null,
			'sort'            => (int) ( $data['sort'] ?? 0 ),
			'status'          => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
			'updated_at'      => current_time( 'mysql' ),
		);
		if ( array_key_exists( 'readme_md', $data ) ) {
			$row['readme_md'] = wp_kses_post( (string) $data['readme_md'] );
		}
		if ( ! in_array( $row['package_source'], array( 'local', 'gitea' ), true ) ) {
			$row['package_source'] = 'local';
		}
		if ( array_key_exists( 'parent_module_id', $data ) ) {
			$row['parent_module_id'] = $parent_module_id;
		}
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
			return $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$ok                = $wpdb->insert( $table, $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param int $ancestor_id Potential ancestor module id.
	 * @param int $child_id    Module id that must not appear in the chain.
	 * @return bool
	 */
	private static function module_parent_chain_contains( $ancestor_id, $child_id ) {
		$seen   = array();
		$cursor = (int) $ancestor_id;
		while ( $cursor > 0 ) {
			if ( $cursor === (int) $child_id ) {
				return true;
			}
			if ( isset( $seen[ $cursor ] ) ) {
				return true;
			}
			$seen[ $cursor ] = true;
			$row = self::get_module_by_id( $cursor );
			if ( ! $row || empty( $row['parent_module_id'] ) ) {
				return false;
			}
			$cursor = (int) $row['parent_module_id'];
		}
		return false;
	}

	/**
	 * @param int  $id Module id.
	 * @param bool $delete_gitea_repo Delete Gitea repo.
	 * @return bool
	 */
	public static function delete_module( $id, $delete_gitea_repo = false ) {
		global $wpdb;
		$module = self::get_module_by_id( (int) $id );
		if ( ! $module ) {
			return false;
		}
		if ( ! empty( $module['is_core'] ) ) {
			return false;
		}
		if ( $delete_gitea_repo && 'gitea' === (string) ( $module['package_source'] ?? '' ) ) {
			$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
			$repo  = (string) ( $module['gitea_repo'] ?: $module['slug'] );
			WebinoCRM_Gitea_Client::delete_repo( $owner, $repo );
		}
		$releases = class_exists( 'WebinoCRM_Marketplace_Release_Service' )
			? WebinoCRM_Marketplace_Release_Service::list_for_module( (int) $id, true )
			: array();
		foreach ( $releases as $rel ) {
			WebinoCRM_Marketplace_Release_Service::delete_release( (int) $rel['id'] );
		}
		return (bool) $wpdb->delete( self::modules_table(), array( 'id' => (int) $id ) );
	}

	/**
	 * @param int $module_id Module id.
	 * @return true|WP_Error
	 */
	public static function ensure_gitea_repo( $module_id ) {
		$module = self::get_module_by_id( (int) $module_id );
		if ( ! $module ) {
			return new WP_Error( 'not_found', __( 'Module not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( ! WebinoCRM_Gitea_Client::is_configured() ) {
			return new WP_Error( 'gitea_config', __( 'Gitea is not configured.', 'webinocrm' ), array( 'status' => 503 ) );
		}
		$slug  = sanitize_key( (string) $module['slug'] );
		$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
		$repo  = (string) ( $module['gitea_repo'] ?: $slug );
		$check = WebinoCRM_Gitea_Client::get_repo( $owner, $repo );
		if ( ! empty( $check['ok'] ) && is_array( $check['data'] ) ) {
			global $wpdb;
			$wpdb->update(
				self::modules_table(),
				array(
					'package_source' => 'gitea',
					'gitea_owner'    => $owner,
					'gitea_repo'     => $repo,
					'gitea_repo_id'  => (int) ( $check['data']['id'] ?? 0 ),
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'id' => (int) $module['id'] )
			);
			return true;
		}
		$created = WebinoCRM_Gitea_Client::create_repo( $repo, (string) ( $module['description'] ?? $module['name'] ), true );
		if ( empty( $created['ok'] ) || ! is_array( $created['data'] ) ) {
			return new WP_Error( 'gitea_repo', $created['message'] ?? __( 'Could not create repository.', 'webinocrm' ), array( 'status' => 502 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::modules_table(),
			array(
				'package_source' => 'gitea',
				'gitea_owner'    => $owner,
				'gitea_repo'     => $repo,
				'gitea_repo_id'  => (int) ( $created['data']['id'] ?? 0 ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => (int) $module['id'] )
		);
		return true;
	}

	/**
	 * @param bool $active_only Active orders only.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_orders( $active_only = false ) {
		global $wpdb;
		$orders = self::orders_table();
		$mod    = self::modules_table();
		$sql    = "SELECT o.*, m.slug AS module_slug, m.name AS module_name
			FROM $orders o
			LEFT JOIN $mod m ON m.id = o.module_id
			ORDER BY o.id DESC
			LIMIT 500";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A ) ?: array();
		if ( $active_only ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) {
						return WebinoCRM_Marketplace_Manager::ORDER_PAID === ( $row['status'] ?? '' );
					}
				)
			);
		}
		return $rows;
	}

	/**
	 * @param string $domain Domain must have valid license.
	 * @return true|WP_Error
	 */
	public static function assert_licensed_domain( $domain ) {
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		if ( '' === $domain ) {
			return new WP_Error( 'missing_domain', __( 'Domain is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$check = WebinoCRM_License_Manager::validate_license( $domain, false );
		if ( empty( $check['valid'] ) ) {
			return new WP_Error(
				'license_invalid',
				$check['message'] ?? __( 'License is not active for this domain.', 'webinocrm' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Seed built-in free modules (WFCP, bots).
	 *
	 * @return void
	 */
	public static function seed_builtin_modules() {
		global $wpdb;
		$cat_table = self::categories_table();
		$cat_slug  = 'built-in';
		$cat_id    = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM $cat_table WHERE slug = %s LIMIT 1", $cat_slug )
		);
		if ( ! $cat_id ) {
			$wpdb->insert(
				$cat_table,
				array(
					'slug'       => $cat_slug,
					'name'       => __( 'Built-in', 'webinocrm' ),
					'sort'       => 0,
					'status'     => 'active',
					'created_at' => current_time( 'mysql' ),
				)
			);
			$cat_id = (int) $wpdb->insert_id;
		}
		$modules = array(
			array(
				'slug'            => 'wfcp-module',
				'name'            => __( 'Webina Woo Core Pricing', 'webinocrm' ),
				'description'     => __( 'Bundled multi-layer store pricing (WFCP).', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/pricing/dashboard',
				'version'         => '1.1.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'wfcp-module',
			),
			array(
				'slug'            => 'sms-panel-module',
				'name'            => __( 'SMS panel', 'webinocrm' ),
				'description'     => __( 'SMS marketing panel for the dashboard.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/marketing/sms',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'sms-panel-module',
			),
			array(
				'slug'            => 'analytics-module',
				'name'            => __( 'Analytics', 'webinocrm' ),
				'description'     => __( 'Visitor and page analytics-module for the dashboard.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'site',
				'settings_route'  => '/settings/site/analytics-module',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'analytics-module',
			),
			array(
				'slug'            => 'bale-bot-module',
				'name'            => __( 'Bale shop bot', 'webinocrm' ),
				'description'     => __( 'Bale messenger bot for your online store.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/bots/bale',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'bale-bot-module',
			),
			array(
				'slug'            => 'telegram-bot-module',
				'name'            => __( 'Telegram shop bot', 'webinocrm' ),
				'description'     => __( 'Telegram bot for your online store.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/bots/telegram',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'telegram-bot-module',
			),
			array(
				'slug'            => 'digipay-upg-module',
				'name'            => __( 'DigiPay UPG', 'webinocrm' ),
				'description'     => __( 'Integrated DigiPay payment gateways (BPG, CPG, Wallet, IPG).', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/digipay',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'digipay-upg-module',
			),
			array(
				'slug'            => 'digikala-sellers-module',
				'name'            => __( 'Digikala Sellers', 'webinocrm' ),
				'description'     => __( 'Digikala Seller Open API integration module for products, inventory, and orders.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/digikala',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'digikala-sellers-module',
			),
			array(
				'slug'            => 'torob-products-extractor-module',
				'name'            => __( 'Torob Products Extractor', 'webinocrm' ),
				'description'     => __( 'Torob product extraction and tracking integration.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/torob-extractor',
				'version'         => '2.1.2',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'torob-products-extractor-module',
			),
			array(
				'slug'            => 'torobpay-gateway-module',
				'name'            => __( 'TorobPay Gateway', 'webinocrm' ),
				'description'     => __( 'TorobPay payment gateway integration module.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/torobpay',
				'version'         => '4.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'torobpay-gateway-module',
			),
			array(
				'slug'            => 'snapppay-gateway-module',
				'name'            => __( 'SnappPay Gateway', 'webinocrm' ),
				'description'     => __( 'SnappPay payment gateway integration with Searchwise feed.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/snapppay',
				'version'         => '1.4.2',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'snapppay-gateway-module',
			),
			array(
				'slug'            => 'basalam-module',
				'name'            => __( 'Basalam', 'webinocrm' ),
				'description'     => __( 'Full Basalam integration for gateway, wallet, subscriptions, and webhooks.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/basalam-module',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'basalam-module',
			),
			array(
				'slug'            => 'zarinpal-gateway-module',
				'name'            => __( 'Zarinpal Gateway', 'webinocrm' ),
				'description'     => __( 'Full Zarinpal payment gateway integration for your store.', 'webinocrm' ),
				'is_free'         => 1,
				'is_builtin'      => 0,
				'settings_area'   => 'shop',
				'settings_route'  => '/settings/shop/zarinpal',
				'version'         => '1.0.0',
				'package_source'  => 'gitea',
				'gitea_owner'     => WebinoCRM_Gitea_Client::org(),
				'gitea_repo'      => 'zarinpal-gateway-module',
			),
		);
		$table = self::modules_table();
		foreach ( $modules as $i => $m ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s LIMIT 1", $m['slug'] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array_merge(
					$m,
					array(
						'category_id' => $cat_id,
						'price'       => 0,
						'currency'    => 'IRT',
						'sort'        => $i,
						'status'      => 'active',
						'created_at'  => current_time( 'mysql' ),
						'updated_at'  => current_time( 'mysql' ),
					)
				)
			);
		}
		self::seed_core_module();
	}

	/**
	 * Dashboard core product (Gitea updates, hidden from marketplace catalog).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_core_module() {
		return self::get_module_by_slug( self::CORE_MODULE_SLUG );
	}

	/**
	 * CRM core product (self-update package).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_crm_core_module() {
		return self::get_module_by_slug( self::CRM_CORE_MODULE_SLUG );
	}

	/**
	 * @param string $current_version Installed dashboard version.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_core_update_check( $current_version = '' ) {
		$module = self::get_core_module();
		if ( ! $module || 'active' !== ( $module['status'] ?? '' ) ) {
			return new WP_Error( 'core_not_configured', __( 'Dashboard core package is not configured.', 'webinocrm' ), array( 'status' => 503 ) );
		}
		$release = null;
		if ( class_exists( 'WebinoCRM_Marketplace_Release_Service' ) ) {
			$release = WebinoCRM_Marketplace_Release_Service::get_latest_published( (int) $module['id'] );
		}
		$latest  = $release ? (string) ( $release['version'] ?? '' ) : (string) ( $module['version'] ?? '' );
		$notes   = $release ? (string) ( $release['changelog'] ?? '' ) : '';
		$current = sanitize_text_field( (string) $current_version );
		if ( '' === $current ) {
			$current = (string) ( $module['version'] ?? '' );
		}
		$update_available = '' !== $latest && '' !== $current && version_compare( $latest, $current, '>' );
		$path             = self::resolve_package_path( $module, $latest );
		$package_ready    = is_string( $path ) && is_readable( $path );
		return array(
			'slug'              => self::CORE_MODULE_SLUG,
			'current_version'   => $current,
			'latest_version'    => $latest,
			'update_available'  => $update_available,
			'release_notes'     => $notes,
			'package_available' => $package_ready,
		);
	}

	/**
	 * @param string $current_version Installed CRM version.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_crm_core_update_check( $current_version = '' ) {
		$module = self::get_crm_core_module();
		if ( ! $module || 'active' !== ( $module['status'] ?? '' ) ) {
			return new WP_Error( 'crm_core_not_configured', __( 'Platform core package is not configured.', 'webinocrm' ), array( 'status' => 503 ) );
		}
		$release = null;
		if ( class_exists( 'WebinoCRM_Marketplace_Release_Service' ) ) {
			$release = WebinoCRM_Marketplace_Release_Service::get_latest_published( (int) $module['id'] );
		}
		$latest  = $release ? (string) ( $release['version'] ?? '' ) : (string) ( $module['version'] ?? '' );
		$notes   = $release ? (string) ( $release['changelog'] ?? '' ) : '';
		$current = sanitize_text_field( (string) $current_version );
		if ( '' === $current ) {
			$current = defined( 'WEBINOCRM_VERSION' ) ? (string) WEBINOCRM_VERSION : (string) ( $module['version'] ?? '' );
		}
		$update_available = '' !== $latest && '' !== $current && version_compare( $latest, $current, '>' );
		$path             = self::resolve_package_path( $module, $latest );
		$package_ready    = is_string( $path ) && is_readable( $path );
		return array(
			'slug'              => self::CRM_CORE_MODULE_SLUG,
			'current_version'   => $current,
			'latest_version'    => $latest,
			'update_available'  => $update_available,
			'release_notes'     => $notes,
			'package_available' => $package_ready,
		);
	}

	/**
	 * @return void
	 */
	public static function seed_core_module() {
		global $wpdb;
		$table  = self::modules_table();
		$slug   = self::CORE_MODULE_SLUG;
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s LIMIT 1", $slug ) );
		$owner  = class_exists( 'WebinoCRM_Gitea_Client' ) ? WebinoCRM_Gitea_Client::org() : 'webina';
		$row    = array(
			'slug'           => $slug,
			'name'           => __( 'Webino Dashboard core', 'webinocrm' ),
			'description'    => __( 'Dashboard plugin core package (SPA + PHP). Updated via licensed sites only.', 'webinocrm' ),
			'is_free'        => 0,
			'is_builtin'     => 0,
			'is_core'        => 1,
			'version'        => '0.1.0',
			'package_source' => 'gitea',
			'gitea_owner'    => $owner,
			'gitea_repo'     => 'webino-dashboard',
			'status'         => 'active',
			'updated_at'     => current_time( 'mysql' ),
		);
		if ( $exists ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $exists ) );
			return;
		}
		$row['price']      = 0;
		$row['currency']   = 'IRT';
		$row['sort']       = -1;
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row );

		$crm_slug   = self::CRM_CORE_MODULE_SLUG;
		$crm_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s LIMIT 1", $crm_slug ) );
		$crm_row    = array(
			'slug'           => $crm_slug,
			'name'           => __( 'Dashboard core', 'webinocrm' ),
			'description'    => __( 'Dashboard core package (SPA + PHP). Updated via licensed sites only.', 'webinocrm' ),
			'is_free'        => 0,
			'is_builtin'     => 0,
			'is_core'        => 1,
			'version'        => defined( 'WEBINOCRM_VERSION' ) ? WEBINOCRM_VERSION : '3.0.0',
			'package_source' => 'gitea',
			'gitea_owner'    => $owner,
			'gitea_repo'     => 'webinocrm',
			'status'         => 'active',
			'updated_at'     => current_time( 'mysql' ),
		);
		if ( $crm_exists ) {
			$wpdb->update( $table, $crm_row, array( 'id' => (int) $crm_exists ) );
			return;
		}
		$crm_row['price']      = 0;
		$crm_row['currency']   = 'IRT';
		$crm_row['sort']       = -2;
		$crm_row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $crm_row );
	}
}

WebinoCRM_Marketplace_Manager::init();
