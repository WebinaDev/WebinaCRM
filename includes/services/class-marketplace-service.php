<?php
/**
 * Marketplace Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Marketplace_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>
	 */
	private static function guard() {
		if ( ! function_exists( 'webinocrm_user_can_manage_marketplace' ) || ! webinocrm_user_can_manage_marketplace() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return true;
	}

	/**
	 * @return array<string,mixed>|true
	 */
	private static function check_guard() {
		$guard = self::guard();
		if ( true !== $guard ) {
			return is_array( $guard )
				? $guard
				: WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return true;
	}

	/**
	 * @return void
	 */
	private static function ensure_marketplace_dependencies() {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! class_exists( 'WebinoCRM_Marketplace_Manager' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-marketplace-manager.php';
		}
		if ( ! class_exists( 'WebinoCRM_Marketplace_Release_Service' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-marketplace-release-service.php';
		}
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function categories( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		try {
			WebinoCRM_Marketplace_Manager::maybe_create_tables();
			return WebinoCRM_Service_Base::success(
				array(
					'categories' => WebinoCRM_Marketplace_Manager::get_categories( false ),
				)
			);
		} catch ( Throwable $e ) {
			if ( function_exists( 'webinocrm_integration_log' ) ) {
				webinocrm_integration_log( 'Marketplace categories error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			}
			return WebinoCRM_Service_Base::error(
				__( 'خطا در بارگذاری دسته‌های مارکت‌پلیس.', 'webinocrm' ),
				500
			);
		}
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function category_save( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$cid = WebinoCRM_Marketplace_Manager::save_category(
			array(
				'id'     => $id,
				'slug'   => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'slug', '' ) ),
				'name'   => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ),
				'sort'   => WebinoCRM_Service_Base::int_param( $params, 'sort' ),
				'status' => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', 'active' ) ),
			)
		);
		if ( ! $cid ) {
			return WebinoCRM_Service_Base::error( __( 'Could not save category.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'id'      => $cid,
				'message' => __( 'Saved.', 'webinocrm' ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function category_delete( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : ( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		WebinoCRM_Marketplace_Manager::delete_category( $id );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Deleted.', 'webinocrm' ) ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function modules( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		try {
			WebinoCRM_Marketplace_Manager::maybe_create_tables();
			$include_core = '1' === (string) WebinoCRM_Service_Base::param( $params, 'include_core', '' );
			$modules = WebinoCRM_Marketplace_Manager::get_modules( '', false, $include_core );
			foreach ( $modules as &$mod ) {
				try {
					$mod['releases'] = WebinoCRM_Marketplace_Release_Service::list_for_module( (int) $mod['id'], true );
				} catch ( Throwable $e ) {
					$mod['releases'] = array();
					if ( function_exists( 'webinocrm_integration_log' ) ) {
						webinocrm_integration_log(
							'Marketplace releases list error for module ' . (int) $mod['id'] . ': ' . $e->getMessage()
						);
					}
				}
			}
			unset( $mod );
			return WebinoCRM_Service_Base::success(
				array(
					'modules'    => $modules,
					'categories' => WebinoCRM_Marketplace_Manager::get_categories( false ),
				)
			);
		} catch ( Throwable $e ) {
			if ( function_exists( 'webinocrm_integration_log' ) ) {
				webinocrm_integration_log( 'Marketplace modules error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			}
			return WebinoCRM_Service_Base::error(
				__( 'خطا در بارگذاری ماژول‌های مارکت‌پلیس.', 'webinocrm' ),
				500
			);
		}
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_get( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		$row = WebinoCRM_Marketplace_Manager::get_module_by_id( $id );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'Module not found.', 'webinocrm' ) );
		}
		$cat_slug = '';
		$cat_name = '';
		if ( ! empty( $row['category_id'] ) ) {
			global $wpdb;
			$cat_row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT slug, name FROM ' . WebinoCRM_Marketplace_Manager::categories_table() . ' WHERE id = %d',
					(int) $row['category_id']
				),
				ARRAY_A
			);
			if ( is_array( $cat_row ) ) {
				$cat_slug = (string) ( $cat_row['slug'] ?? '' );
				$cat_name = (string) ( $cat_row['name'] ?? '' );
			}
		}
		$row['category_slug'] = $cat_slug;
		$row['category_name'] = $cat_name;
		$row                  = WebinoCRM_Marketplace_Manager::attach_parent_module_fields( $row );
		$sync_warning         = null;
		if ( class_exists( 'WebinoCRM_Gitea_Client' ) && WebinoCRM_Gitea_Client::is_configured() ) {
			$should_sync = 'gitea' === (string) ( $row['package_source'] ?? '' )
				|| ! empty( $row['gitea_repo'] )
				|| ! empty( $row['gitea_repo_id'] );
			if ( $should_sync ) {
				$sync = WebinoCRM_Marketplace_Manager::ensure_gitea_repo( $id );
				if ( is_wp_error( $sync ) ) {
					$sync_warning = $sync->get_error_message();
				} else {
					$row = WebinoCRM_Marketplace_Manager::get_module_by_id( $id ) ?: $row;
					$row['category_slug'] = $cat_slug;
					$row['category_name'] = $cat_name;
					$row                  = WebinoCRM_Marketplace_Manager::attach_parent_module_fields( $row );
				}
			}
		}
		$module = WebinoCRM_Marketplace_Manager::format_module_public( $row );
		$module = WebinoCRM_Marketplace_Manager::enrich_module_gitea_meta( $module );
		$erp_key = WebinoCRM_Marketplace_Manager::erp_submodule_settings_key_for_slug( (string) ( $module['slug'] ?? '' ) );
		if ( $erp_key ) {
			$module['erp_submodule_settings_key'] = $erp_key;
		}
		$module['releases'] = WebinoCRM_Marketplace_Release_Service::list_for_module( $id, true );
		$payload = array( 'module' => $module );
		if ( $sync_warning ) {
			$payload['sync_warning'] = $sync_warning;
		}
		return WebinoCRM_Service_Base::success( $payload );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_save( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}

		$id              = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$slug            = sanitize_key( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'slug', '' ) ) );
		$create_gitea    = '1' === (string) WebinoCRM_Service_Base::param( $params, 'create_gitea_repo', '' );
		$package_source  = sanitize_key( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'package_source', '' ) ) );
		if ( '' === $package_source ) {
			$package_source = $create_gitea ? 'gitea' : 'local';
		}

		$data = array(
			'id'             => $id,
			'slug'           => $slug,
			'name'           => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ),
			'description'    => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', '' ) ),
			'readme_md'      => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'readme_md', '' ) ),
			'price'          => (float) WebinoCRM_Service_Base::param( $params, 'price', 0 ),
			'category_id'    => WebinoCRM_Service_Base::int_param( $params, 'category_id' ) ?: null,
			'version'        => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'version', '1.0.0' ) ),
			'settings_area'  => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'settings_area', 'shop' ) ),
			'settings_route' => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'settings_route', '' ) ),
			'detail_url'     => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'detail_url', '' ) ),
			'icon_url'       => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'icon_url', '' ) ),
			'is_free'        => '1' === (string) WebinoCRM_Service_Base::param( $params, 'is_free', '' ),
			'is_builtin'       => '1' === (string) WebinoCRM_Service_Base::param( $params, 'is_builtin', '' ),
			'status'           => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', 'active' ) ),
			'parent_module_id' => WebinoCRM_Service_Base::int_param( $params, 'parent_module_id' ) ?: null,
			'package_source'   => $package_source,
			'gitea_owner'    => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_owner', WebinoCRM_Gitea_Client::org() ) ),
			'gitea_repo'     => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_repo', $slug ) ),
		);

		if ( ! empty( $_FILES['package_zip']['tmp_name'] ) && is_uploaded_file( $_FILES['package_zip']['tmp_name'] ) ) {
			$stored = self::store_package_zip( $slug, $_FILES['package_zip'] );
			if ( is_wp_error( $stored ) ) {
				return WebinoCRM_Service_Base::error( $stored->get_error_message() );
			}
			$data['package_path']   = $stored;
			$data['package_source'] = 'local';
		}

		$mid = WebinoCRM_Marketplace_Manager::save_module( $data );
		if ( ! $mid ) {
			return WebinoCRM_Service_Base::error( __( 'Could not save module.', 'webinocrm' ) );
		}

		$warnings = array();

		if ( $create_gitea || 'gitea' === $package_source ) {
			$repo = WebinoCRM_Marketplace_Manager::ensure_gitea_repo( (int) $mid );
			if ( is_wp_error( $repo ) ) {
				$warnings['gitea_repo'] = $repo->get_error_message();
			}
		}

		$push_readme = '1' === (string) WebinoCRM_Service_Base::param( $params, 'push_readme_gitea', '' );
		if ( $push_readme && 'gitea' === $package_source && class_exists( 'WebinoCRM_Gitea_Client' ) ) {
			$module_row = WebinoCRM_Marketplace_Manager::get_module_by_id( (int) $mid );
			if ( $module_row ) {
				$owner  = (string) ( $module_row['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
				$repo   = (string) ( $module_row['gitea_repo'] ?: $module_row['slug'] );
				$readme = (string) ( $data['readme_md'] ?? '' );
				if ( '' !== $readme ) {
					$existing = WebinoCRM_Gitea_Client::get_file_contents( $owner, $repo, 'README.md' );
					$sha      = ! empty( $existing['ok'] ) ? (string) ( $existing['sha'] ?? '' ) : '';
					$pushed   = WebinoCRM_Gitea_Client::upsert_file_contents( $owner, $repo, 'README.md', $readme, '', $sha );
					if ( empty( $pushed['ok'] ) ) {
						$warnings['gitea_readme'] = (string) ( $pushed['message'] ?? __( 'Could not push README to Gitea.', 'webinocrm' ) );
					}
				}
			}
		}

		$payload = array(
			'id'      => $mid,
			'message' => __( 'Saved.', 'webinocrm' ),
		);
		if ( $warnings ) {
			$payload['warnings'] = $warnings;
		}
		return WebinoCRM_Service_Base::success( $payload );
	}

	/**
	 * @param string              $slug Module slug.
	 * @param array<string,mixed> $file $_FILES entry.
	 * @return string|WP_Error
	 */
	private static function store_package_zip( $slug, array $file ) {
		$slug = sanitize_key( (string) $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'invalid_slug', __( 'Slug is required for package upload.', 'webinocrm' ) );
		}
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		if ( ! str_ends_with( strtolower( $name ), '.zip' ) ) {
			return new WP_Error( 'invalid_zip', __( 'Package must be a ZIP file.', 'webinocrm' ) );
		}
		$dir  = WebinoCRM_Marketplace_Manager::packages_upload_dir();
		$dest = trailingslashit( $dir ) . $slug . '.zip';
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'upload_failed', __( 'Could not store package ZIP.', 'webinocrm' ) );
		}
		return $dest;
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_delete( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id          = isset( $params['id'] ) ? (int) $params['id'] : WebinoCRM_Service_Base::int_param( $params, 'id' );
		$delete_repo = '1' === (string) WebinoCRM_Service_Base::param( $params, 'delete_gitea_repo', '' );
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		WebinoCRM_Marketplace_Manager::delete_module( $id, $delete_repo );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Deleted.', 'webinocrm' ) ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_create_repo( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : ( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Marketplace_Manager::ensure_gitea_repo( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $id );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'Repository ready.', 'webinocrm' ),
				'module'  => $module ? WebinoCRM_Marketplace_Manager::enrich_module_gitea_meta( WebinoCRM_Marketplace_Manager::format_module_public( $module ) ) : null,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_repo_sync( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Marketplace_Manager::ensure_gitea_repo( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $id );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'Repository synchronized.', 'webinocrm' ),
				'module'  => $module ? WebinoCRM_Marketplace_Manager::enrich_module_gitea_meta( WebinoCRM_Marketplace_Manager::format_module_public( $module ) ) : null,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_repo_visibility( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $id );
		if ( ! $module || empty( $module['gitea_repo_id'] ) ) {
			return WebinoCRM_Service_Base::error( __( 'Gitea repository is not linked.', 'webinocrm' ) );
		}
		$private = '1' === (string) WebinoCRM_Service_Base::param( $params, 'private', '' );
		$owner   = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
		$repo    = (string) ( $module['gitea_repo'] ?: $module['slug'] );
		$updated = WebinoCRM_Gitea_Client::update_repo( $owner, $repo, array( 'private' => $private ) );
		if ( empty( $updated['ok'] ) ) {
			return WebinoCRM_Service_Base::error( $updated['message'] ?? __( 'Could not update repository.', 'webinocrm' ) );
		}
		$formatted = WebinoCRM_Marketplace_Manager::enrich_module_gitea_meta( WebinoCRM_Marketplace_Manager::format_module_public( $module ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => $private ? __( 'Repository is now private.', 'webinocrm' ) : __( 'Repository is now public.', 'webinocrm' ),
				'module'  => $formatted,
			)
		);
	}

	/**
	 * Pull README.md from Gitea into CRM module row.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_readme_sync( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		if ( ! $id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid id.', 'webinocrm' ) );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $id );
		if ( ! $module || empty( $module['gitea_repo_id'] ) ) {
			return WebinoCRM_Service_Base::error( __( 'Gitea repository is not linked.', 'webinocrm' ) );
		}
		$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
		$repo  = (string) ( $module['gitea_repo'] ?: $module['slug'] );
		$file  = WebinoCRM_Gitea_Client::get_file_contents( $owner, $repo, 'README.md' );
		if ( empty( $file['ok'] ) ) {
			return WebinoCRM_Service_Base::error( $file['message'] ?? __( 'Could not read README from Gitea.', 'webinocrm' ) );
		}
		WebinoCRM_Marketplace_Manager::save_module(
			array(
				'id'        => $id,
				'slug'      => (string) $module['slug'],
				'readme_md' => (string) ( $file['content'] ?? '' ),
			)
		);
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $id );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'README synchronized from Gitea.', 'webinocrm' ),
				'module'  => $module ? WebinoCRM_Marketplace_Manager::enrich_module_gitea_meta( WebinoCRM_Marketplace_Manager::format_module_public( $module ) ) : null,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function module_releases( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		return WebinoCRM_Service_Base::success(
			array(
				'releases' => WebinoCRM_Marketplace_Release_Service::list_for_module( $id, true ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function release_save( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$module_id  = isset( $params['id'] ) ? (int) $params['id'] : WebinoCRM_Service_Base::int_param( $params, 'module_id' );
		$release_id = WebinoCRM_Service_Base::int_param( $params, 'release_id' );
		if ( ! $module_id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid module id.', 'webinocrm' ) );
		}
		$version   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'version', '' ) ) );
		$tag       = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'tag_name', '' ) ) );
		if ( '' === $tag ) {
			$tag = 'v' . ltrim( $version, 'v' );
		}
		$package_path = null;
		if ( ! empty( $_FILES['package_zip']['tmp_name'] ) && is_uploaded_file( $_FILES['package_zip']['tmp_name'] ) ) {
			$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $module_id );
			$slug   = $module ? (string) $module['slug'] : 'module';
			$stored = self::store_package_zip( $slug . '-' . $version, $_FILES['package_zip'] );
			if ( is_wp_error( $stored ) ) {
				return WebinoCRM_Service_Base::error( $stored->get_error_message() );
			}
			$package_path = $stored;
		} elseif ( $release_id ) {
			$existing = WebinoCRM_Marketplace_Release_Service::get_by_id( $release_id );
			$package_path = $existing['package_path'] ?? null;
		}

		$rid = WebinoCRM_Marketplace_Release_Service::save_release(
			array(
				'id'           => $release_id,
				'module_id'    => $module_id,
				'version'      => $version,
				'tag_name'     => $tag,
				'changelog'    => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'changelog', '' ) ),
				'package_path' => $package_path,
				'status'       => WebinoCRM_Marketplace_Release_Service::STATUS_DRAFT,
			)
		);
		if ( ! $rid ) {
			return WebinoCRM_Service_Base::error( __( 'Could not save release.', 'webinocrm' ) );
		}

		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( $module_id );
		if ( $module && 'gitea' === (string) ( $module['package_source'] ?? '' ) ) {
			$gitea = WebinoCRM_Marketplace_Release_Service::ensure_gitea_release_draft( (int) $rid );
			if ( is_wp_error( $gitea ) ) {
				return WebinoCRM_Service_Base::error( $gitea->get_error_message() );
			}
		}

		return WebinoCRM_Service_Base::success(
			array(
				'id'      => $rid,
				'message' => __( 'Release saved.', 'webinocrm' ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function release_publish( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$release_id = WebinoCRM_Service_Base::int_param( $params, 'release_id' );
		if ( ! $release_id && ! empty( $params['release_id'] ) ) {
			$release_id = (int) $params['release_id'];
		}
		if ( ! $release_id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid release id.', 'webinocrm' ) );
		}
		$release = WebinoCRM_Marketplace_Release_Service::get_by_id( $release_id );
		if ( ! $release ) {
			return WebinoCRM_Service_Base::error( __( 'Release not found.', 'webinocrm' ) );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( (int) $release['module_id'] );
		$is_gitea = $module && 'gitea' === (string) ( $module['package_source'] ?? '' );
		if ( ! $is_gitea ) {
			$check = self::release_check(
				array(
					'release_id' => $release_id,
				)
			);
			if ( empty( $check['ok'] ) ) {
				return $check;
			}
		}
		$result = WebinoCRM_Marketplace_Release_Service::publish( $release_id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Release published.', 'webinocrm' ) ) );
	}

	/**
	 * Validate release ZIP contract before publish.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function release_check( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$release_id = WebinoCRM_Service_Base::int_param( $params, 'release_id' );
		if ( ! $release_id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid release id.', 'webinocrm' ) );
		}
		$release = WebinoCRM_Marketplace_Release_Service::get_by_id( $release_id );
		if ( ! $release ) {
			return WebinoCRM_Service_Base::error( __( 'Release not found.', 'webinocrm' ) );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( (int) $release['module_id'] );
		if ( $module && 'gitea' === (string) ( $module['package_source'] ?? '' ) ) {
			return WebinoCRM_Service_Base::success(
				array(
					'message'   => __( 'Gitea release will be validated on publish.', 'webinocrm' ),
					'releaseId' => $release_id,
				)
			);
		}
		$zip_path = (string) ( $release['package_path'] ?? '' );
		if ( '' === $zip_path || ! is_readable( $zip_path ) ) {
			return WebinoCRM_Service_Base::error( __( 'Release package ZIP is missing or unreadable.', 'webinocrm' ) );
		}
		$validated = WebinoCRM_Marketplace_Release_Service::validate_zip_contract( $zip_path );
		if ( is_wp_error( $validated ) ) {
			$data = $validated->get_error_data();
			$msg  = $validated->get_error_message();
			if ( is_array( $data ) && ! empty( $data['missing'] ) && is_array( $data['missing'] ) ) {
				$msg .= ' [' . implode( ', ', $data['missing'] ) . ']';
			}
			return WebinoCRM_Service_Base::error( $msg );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'   => __( 'ZIP contract validation passed.', 'webinocrm' ),
				'releaseId' => $release_id,
				'zipPath'   => $zip_path,
				'required'  => $validated['required'] ?? array(),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function release_delete( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$release_id = isset( $params['release_id'] ) ? (int) $params['release_id'] : 0;
		if ( ! $release_id ) {
			return WebinoCRM_Service_Base::error( __( 'Invalid release id.', 'webinocrm' ) );
		}
		WebinoCRM_Marketplace_Release_Service::delete_release( $release_id );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Release deleted.', 'webinocrm' ) ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function orders( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		return WebinoCRM_Service_Base::success(
			array(
				'orders' => WebinoCRM_Marketplace_Manager::get_orders(),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function gitea_settings_get( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		return WebinoCRM_Service_Base::success(
			array(
				'settings' => array(
					'gitea_base_url'    => (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_base_url', 'https://package.webina.dev' ),
					'gitea_org'         => (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_org', 'webina' ),
					'gitea_ip_override' => (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_ip_override', '' ),
					'gitea_ip_scheme'   => (string) WebinoCRM_Gitea_Client::ip_scheme(),
					'gitea_configured'  => WebinoCRM_Gitea_Client::is_configured(),
					'has_token'         => '' !== (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_api_token', '' ),
				),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function gitea_settings_save( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$all = WebinoCRM_Settings_Handler::get_all_settings();
		if ( WebinoCRM_Service_Base::param( $params, 'gitea_base_url', '' ) !== '' ) {
			$all['gitea_base_url'] = esc_url_raw( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_base_url', '' ) ) );
		}
		if ( WebinoCRM_Service_Base::param( $params, 'gitea_org', '' ) !== '' ) {
			$all['gitea_org'] = sanitize_key( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_org', '' ) ) );
		}
		if ( WebinoCRM_Service_Base::param( $params, 'gitea_ip_override', '' ) !== '' || array_key_exists( 'gitea_ip_override', $params ) ) {
			$all['gitea_ip_override'] = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_ip_override', '' ) ) );
		}
		$ip_scheme = sanitize_key( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_ip_scheme', '' ) ) );
		if ( '' !== $ip_scheme && in_array( $ip_scheme, array( 'auto', 'http', 'https' ), true ) ) {
			$all['gitea_ip_scheme'] = $ip_scheme;
		} elseif ( array_key_exists( 'gitea_ip_scheme', $params ) ) {
			$all['gitea_ip_scheme'] = 'auto';
		}
		$token = trim( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_api_token', '' ) ) );
		if ( '' !== $token ) {
			$all['gitea_api_token'] = $token;
		}
		WebinoCRM_Settings_Handler::update_settings( $all );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'Gitea settings saved.', 'webinocrm' ) ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function gitea_test( array $params ) {
		self::ensure_marketplace_dependencies();
		$guard = self::check_guard();
		if ( true !== $guard ) {
			return $guard;
		}
		$overrides = array(
			'gitea_base_url'    => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_base_url', '' ) ),
			'gitea_org'         => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_org', '' ) ),
			'gitea_ip_override' => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_ip_override', '' ) ),
			'gitea_ip_scheme'   => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_ip_scheme', '' ) ),
			'gitea_api_token'   => wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'gitea_api_token', '' ) ),
		);
		$result = WebinoCRM_Gitea_Client::run_connection_diagnostics( $overrides );
		return WebinoCRM_Service_Base::success( $result );
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_marketplace_categories'   => array( __CLASS__, 'categories' ),
				'webinocrm_save_marketplace_category'    => array( __CLASS__, 'category_save' ),
				'webinocrm_delete_marketplace_category'  => array( __CLASS__, 'category_delete' ),
				'webinocrm_get_marketplace_modules'      => array( __CLASS__, 'modules' ),
				'webinocrm_save_marketplace_module'      => array( __CLASS__, 'module_save' ),
				'webinocrm_delete_marketplace_module'    => array( __CLASS__, 'module_delete' ),
				'webinocrm_get_marketplace_orders'       => array( __CLASS__, 'orders' ),
				'webinocrm_check_marketplace_release'    => array( __CLASS__, 'release_check' ),
				'webinocrm_check_crm_core_update'        => array( 'WebinoCRM_Core_Update_Service', 'status' ),
				'webinocrm_run_crm_core_update'          => array( 'WebinoCRM_Core_Update_Service', 'run' ),
			)
		);
	}
}
