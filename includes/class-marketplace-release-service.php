<?php
/**
 * Marketplace module releases (Gitea + local cache).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Release rows and hybrid ZIP resolution.
 */
class WebinoCRM_Marketplace_Release_Service {

	const STATUS_DRAFT     = 'draft';
	const STATUS_PUBLISHED = 'published';
	const STATUS_ARCHIVED  = 'archived';

	const SOURCE_GITEA_ASSET = 'gitea_asset';
	const SOURCE_CRM_BUILD   = 'crm_build';
	const REQUIRED_ZIP_PATHS = array(
		'manifest.json',
		'bootstrap.php',
		'includes/',
		'client/dist/module.js',
	);

	const CORE_ZIP_CONTRACTS = array(
		'webino-dashboard' => array(
			'webino-dashboard.php',
			'includes/',
			'includes/class-webino-dashboard-bootstrap.php',
			'assets/dashboard-build/build-entry.json',
		),
		'webinocrm' => array(
			'webinocrm.php',
			'includes/',
			'client/',
		),
	);

	/**
	 * Last WP_Error from package resolution (for download-token debug).
	 *
	 * @var WP_Error|null
	 */
	private static $last_resolve_error = null;

	/**
	 * @return string
	 */
	public static function releases_table() {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_marketplace_releases';
	}

	/**
	 * @return string
	 */
	public static function packages_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'webinocrm-packages';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * @param int $module_id Module id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_module( $module_id, $include_draft = true ) {
		global $wpdb;
		$table = self::releases_table();
		$sql   = "SELECT * FROM $table WHERE module_id = %d";
		if ( ! $include_draft ) {
			$sql .= " AND status = '" . esc_sql( self::STATUS_PUBLISHED ) . "'";
		}
		$sql .= ' ORDER BY published_at DESC, id DESC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, (int) $module_id ), ARRAY_A ) ?: array();
	}

	/**
	 * @param int $id Release id.
	 * @return array<string,mixed>|null
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::releases_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * @param int    $module_id Module id.
	 * @param string $version   Version string.
	 * @return array<string,mixed>|null
	 */
	public static function get_published_by_version( $module_id, $version ) {
		$version = trim( (string) $version );
		if ( '' === $version ) {
			return null;
		}
		global $wpdb;
		$table      = self::releases_table();
		$candidates = self::version_lookup_candidates( $version );
		foreach ( $candidates as $ver ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE module_id = %d AND version = %s AND status = %s LIMIT 1",
					(int) $module_id,
					$ver,
					self::STATUS_PUBLISHED
				),
				ARRAY_A
			);
			if ( $row ) {
				return $row;
			}
		}
		foreach ( $candidates as $ver ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE module_id = %d AND tag_name = %s AND status = %s LIMIT 1",
					(int) $module_id,
					$ver,
					self::STATUS_PUBLISHED
				),
				ARRAY_A
			);
			if ( $row ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Version/tag strings to try when matching a published release row.
	 *
	 * @param string $version Requested version or tag.
	 * @return array<int,string>
	 */
	private static function version_lookup_candidates( $version ) {
		$version = trim( (string) $version );
		if ( '' === $version ) {
			return array();
		}
		$candidates = array( $version );
		$stripped   = ltrim( $version, 'vV' );
		if ( '' !== $stripped && $stripped !== $version ) {
			$candidates[] = $stripped;
		}
		if ( '' !== $stripped && ! preg_match( '/^v/i', $version ) ) {
			$candidates[] = 'v' . $stripped;
		}
		return array_values( array_unique( $candidates ) );
	}

	/**
	 * @param int $module_id Module id.
	 * @return array<string,mixed>|null
	 */
	public static function get_latest_published( $module_id ) {
		global $wpdb;
		$table = self::releases_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE module_id = %d AND status = %s ORDER BY published_at DESC, id DESC LIMIT 1",
				(int) $module_id,
				self::STATUS_PUBLISHED
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * @param array<string,mixed> $data Release fields.
	 * @return int|false
	 */
	public static function save_release( array $data ) {
		global $wpdb;
		$table = self::releases_table();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$row = array(
			'module_id'        => (int) ( $data['module_id'] ?? 0 ),
			'version'          => sanitize_text_field( (string) ( $data['version'] ?? '' ) ),
			'tag_name'         => sanitize_text_field( (string) ( $data['tag_name'] ?? '' ) ),
			'changelog'        => wp_kses_post( (string) ( $data['changelog'] ?? '' ) ),
			'gitea_release_id' => isset( $data['gitea_release_id'] ) ? (int) $data['gitea_release_id'] : null,
			'package_path'     => isset( $data['package_path'] ) ? (string) $data['package_path'] : null,
			'package_source'   => sanitize_key( (string) ( $data['package_source'] ?? '' ) ),
			'status'           => sanitize_key( (string) ( $data['status'] ?? self::STATUS_DRAFT ) ),
			'updated_at'       => current_time( 'mysql' ),
		);
		if ( ! in_array( $row['status'], array( self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED ), true ) ) {
			$row['status'] = self::STATUS_DRAFT;
		}
		if ( self::STATUS_PUBLISHED === $row['status'] && empty( $data['published_at'] ) ) {
			$row['published_at'] = current_time( 'mysql' );
		} elseif ( ! empty( $data['published_at'] ) ) {
			$row['published_at'] = $data['published_at'];
		}

		if ( $id ) {
			$ok = $wpdb->update( $table, $row, array( 'id' => $id ) );
			return false !== $ok ? $id : false;
		}
		$row['created_at'] = current_time( 'mysql' );
		$ok              = $wpdb->insert( $table, $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Create or link a Gitea release for a CRM draft release.
	 *
	 * @param int $release_id Release id.
	 * @return true|WP_Error
	 */
	public static function ensure_gitea_release_draft( $release_id ) {
		$release = self::get_by_id( (int) $release_id );
		if ( ! $release ) {
			return new WP_Error( 'not_found', __( 'Release not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $release['gitea_release_id'] ) ) {
			return true;
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( (int) $release['module_id'] );
		if ( ! $module || 'gitea' !== (string) ( $module['package_source'] ?? '' ) ) {
			return true;
		}
		if ( ! class_exists( 'WebinoCRM_Gitea_Client' ) || ! WebinoCRM_Gitea_Client::is_configured() ) {
			return new WP_Error( 'gitea_config', __( 'Gitea is not configured.', 'webinocrm' ), array( 'status' => 503 ) );
		}
		$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
		$repo  = (string) ( $module['gitea_repo'] ?: $module['slug'] );
		$tag   = (string) ( $release['tag_name'] ?: $release['version'] );
		if ( '' === $tag ) {
			return new WP_Error( 'invalid_tag', __( 'Tag name is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$title   = (string) ( $module['name'] ?? $module['slug'] ) . ' ' . (string) $release['version'];
		$created = WebinoCRM_Gitea_Client::create_release(
			$owner,
			$repo,
			$tag,
			$title,
			(string) ( $release['changelog'] ?? '' )
		);
		if ( empty( $created['ok'] ) || ! is_array( $created['data'] ) ) {
			return new WP_Error( 'gitea_release', $created['message'] ?? __( 'Could not create Gitea release.', 'webinocrm' ), array( 'status' => 502 ) );
		}
		$gitea_release_id = (int) ( $created['data']['id'] ?? 0 );
		self::save_release(
			array(
				'id'               => (int) $release['id'],
				'module_id'        => (int) $release['module_id'],
				'version'          => (string) $release['version'],
				'tag_name'         => $tag,
				'changelog'        => (string) ( $release['changelog'] ?? '' ),
				'package_path'     => $release['package_path'] ?? null,
				'gitea_release_id' => $gitea_release_id,
				'status'           => (string) ( $release['status'] ?? self::STATUS_DRAFT ),
			)
		);
		return true;
	}

	/**
	 * @param int $release_id Release id.
	 * @return true|WP_Error
	 */
	public static function publish( $release_id ) {
		$release = self::get_by_id( (int) $release_id );
		if ( ! $release ) {
			return new WP_Error( 'not_found', __( 'Release not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		$module = WebinoCRM_Marketplace_Manager::get_module_by_id( (int) $release['module_id'] );
		if ( ! $module ) {
			return new WP_Error( 'not_found', __( 'Module not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}

		$tag = (string) ( $release['tag_name'] ?: $release['version'] );
		if ( '' === $tag ) {
			return new WP_Error( 'invalid_tag', __( 'Tag name is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$gitea_release_id = (int) ( $release['gitea_release_id'] ?? 0 );
		if ( 'gitea' === (string) ( $module['package_source'] ?? '' ) ) {
			$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
			$repo  = (string) ( $module['gitea_repo'] ?: $module['slug'] );
			if ( ! $gitea_release_id ) {
				$title = (string) ( $module['name'] ?? $module['slug'] ) . ' ' . (string) $release['version'];
				$created = WebinoCRM_Gitea_Client::create_release( $owner, $repo, $tag, $title, (string) ( $release['changelog'] ?? '' ) );
				if ( empty( $created['ok'] ) || ! is_array( $created['data'] ) ) {
					return new WP_Error( 'gitea_release', $created['message'] ?? __( 'Could not create Gitea release.', 'webinocrm' ), array( 'status' => 502 ) );
				}
				$gitea_release_id = (int) ( $created['data']['id'] ?? 0 );
			}
		}

		$package_path   = (string) ( $release['package_path'] ?? '' );
		$package_source = (string) ( $release['package_source'] ?? '' );

		if ( ( ! $package_path || ! is_readable( $package_path ) ) && 'gitea' === (string) ( $module['package_source'] ?? '' ) ) {
			$built = self::ensure_package_for_release( $module, $release, $gitea_release_id, $tag );
			if ( is_wp_error( $built ) ) {
				return $built;
			}
			$package_path   = $built['path'];
			$package_source = $built['source'];
		}

		if ( ! $package_path || ! is_readable( $package_path ) ) {
			return new WP_Error( 'no_package', __( 'Package ZIP is not available for this release.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $module['is_core'] ) ) {
			$validated = self::validate_core_zip_contract( $package_path, (string) $module['slug'] );
		} else {
			$validated = self::validate_zip_contract( $package_path );
		}
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$release['status']           = self::STATUS_PUBLISHED;
		$release['published_at']     = current_time( 'mysql' );
		$release['gitea_release_id'] = $gitea_release_id;
		self::persist_resolved_package( $module, $release, $package_path, $package_source );

		return true;
	}

	/**
	 * Validate release ZIP contract (manifest-aware for module SPA entry).
	 *
	 * @param string $zip_path ZIP file path.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function validate_zip_contract( $zip_path ) {
		$layout = self::parse_zip_layout( $zip_path );
		if ( is_wp_error( $layout ) ) {
			return $layout;
		}
		$required = self::required_paths_for_module_manifest( $layout['manifest'] );
		$missing  = array();
		foreach ( $required as $path ) {
			if ( ! self::zip_has_required_path( $layout['entries'], $path ) ) {
				$missing[] = $path;
			}
		}
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'zip_contract_failed',
				sprintf(
					/* translators: %s: comma-separated missing paths */
					__( 'Module ZIP contract failed. Missing required paths: %s', 'webinocrm' ),
					implode( ', ', $missing )
				),
				array(
					'status'  => 400,
					'missing' => $missing,
				)
			);
		}

		return array(
			'ok'       => true,
			'zip_path' => $zip_path,
			'required' => $required,
		);
	}

	/**
	 * Validate core product ZIP layout (dashboard / CRM).
	 *
	 * @param string $zip_path ZIP file path.
	 * @param string $slug     Core module slug.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function validate_core_zip_contract( $zip_path, $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! isset( self::CORE_ZIP_CONTRACTS[ $slug ] ) ) {
			return new WP_Error( 'invalid_core_slug', __( 'Unknown core product slug.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$layout = self::parse_zip_layout( $zip_path );
		if ( is_wp_error( $layout ) ) {
			return $layout;
		}
		$missing = array();
		foreach ( self::CORE_ZIP_CONTRACTS[ $slug ] as $required ) {
			if ( ! self::zip_has_required_path( $layout['entries'], $required ) ) {
				$missing[] = $required;
			}
		}
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'zip_contract_failed',
				sprintf(
					/* translators: %s: comma-separated missing paths */
					__( 'Core ZIP contract failed. Missing required paths: %s', 'webinocrm' ),
					implode( ', ', $missing )
				),
				array(
					'status'  => 400,
					'missing' => $missing,
				)
			);
		}
		return array(
			'ok'       => true,
			'zip_path' => $zip_path,
			'required' => self::CORE_ZIP_CONTRACTS[ $slug ],
		);
	}

	/**
	 * Required paths inside a module ZIP based on manifest (mirrors Dashboard install checks).
	 *
	 * @param array<string,mixed>|null $manifest Parsed manifest.json.
	 * @return list<string>
	 */
	public static function required_paths_for_module_manifest( $manifest ) {
		$paths     = array( 'manifest.json', 'includes/' );
		$bootstrap = 'bootstrap.php';
		if ( is_array( $manifest ) && ! empty( $manifest['bootstrap'] ) ) {
			$bootstrap = ltrim( (string) $manifest['bootstrap'], '/' );
		}
		$paths[] = $bootstrap;
		$client  = is_array( $manifest['client'] ?? null ) ? $manifest['client'] : array();
		$routes  = isset( $client['routes'] ) && is_array( $client['routes'] ) ? $client['routes'] : array();
		if ( ! empty( $routes ) ) {
			$entry = ! empty( $client['entry'] ) ? ltrim( (string) $client['entry'], '/' ) : 'client/dist/module.js';
			$paths[] = $entry;
		}
		$vendor = is_array( $manifest['vendor'] ?? null ) ? $manifest['vendor'] : array();
		$required_vendor = isset( $vendor['required_paths'] ) && is_array( $vendor['required_paths'] ) ? $vendor['required_paths'] : array();
		foreach ( $required_vendor as $rel ) {
			$rel = ltrim( (string) $rel, '/' );
			if ( '' !== $rel ) {
				$paths[] = $rel;
			}
		}
		return $paths;
	}

	/**
	 * @param string $zip_path ZIP path.
	 * @return array{entries:list<string>,manifest:?array<string,mixed>}|WP_Error
	 */
	private static function parse_zip_layout( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unavailable', __( 'ZipArchive extension is not available on CRM server.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		if ( ! is_readable( $zip_path ) ) {
			return new WP_Error( 'zip_not_found', __( 'ZIP file is not readable.', 'webinocrm' ), array( 'status' => 404 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'zip_open_failed', __( 'Could not open module ZIP.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		if ( $zip->numFiles < 1 ) {
			$zip->close();
			return new WP_Error( 'zip_empty', __( 'Module ZIP is empty.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$entries       = array();
		$roots         = array();
		$manifest_raw  = null;
		$manifest_zip  = null;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '' === $name ) {
				continue;
			}
			$name      = ltrim( str_replace( '\\', '/', $name ), '/' );
			$entries[] = $name;
			$parts     = explode( '/', $name );
			if ( ! empty( $parts[0] ) ) {
				$roots[ $parts[0] ] = true;
			}
			$base = basename( $name );
			if ( 'manifest.json' === $base && null === $manifest_zip ) {
				$manifest_zip = $name;
			}
		}

		$normalized = $entries;
		if ( 1 === count( $roots ) ) {
			$single_root = array_key_first( $roots );
			$prefix      = $single_root . '/';
			$normalized  = array();
			foreach ( $entries as $entry ) {
				if ( 0 === strpos( $entry, $prefix ) ) {
					$normalized[] = substr( $entry, strlen( $prefix ) );
				} else {
					$normalized[] = $entry;
				}
			}
		}

		if ( $manifest_zip ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw = $zip->getFromName( $manifest_zip );
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$manifest_raw = $decoded;
				}
			}
		}
		$zip->close();

		return array(
			'entries'  => $normalized,
			'manifest' => $manifest_raw,
		);
	}

	/**
	 * @param array<int,string> $entries  Normalized zip entries.
	 * @param string            $required Required file/dir.
	 * @return bool
	 */
	private static function zip_has_required_path( array $entries, $required ) {
		$required = ltrim( str_replace( '\\', '/', (string) $required ), '/' );
		$is_dir = '/' === substr( $required, -1 );
		foreach ( $entries as $entry ) {
			$entry = ltrim( str_replace( '\\', '/', $entry ), '/' );
			if ( $is_dir ) {
				if ( 0 === strpos( $entry, $required ) ) {
					return true;
				}
				continue;
			}
			if ( $entry === $required ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $module  Module row.
	 * @param array<string,mixed> $release Release row.
	 * @param int                 $gitea_release_id Gitea release id.
	 * @param string              $tag     Tag.
	 * @return array{path:string,source:string}|WP_Error
	 */
	public static function ensure_package_for_release( array $module, array $release, $gitea_release_id, $tag ) {
		$owner = (string) ( $module['gitea_owner'] ?: WebinoCRM_Gitea_Client::org() );
		$repo  = (string) ( $module['gitea_repo'] ?: $module['slug'] );
		$slug  = sanitize_key( (string) $module['slug'] );
		$ver   = sanitize_file_name( (string) $release['version'] );
		$dest  = trailingslashit( self::packages_dir() ) . $slug . '-' . $ver . '.zip';

		if ( is_readable( $dest ) ) {
			return array(
				'path'   => $dest,
				'source' => (string) ( $release['package_source'] ?: self::SOURCE_CRM_BUILD ),
			);
		}

		$archive_tag = (string) ( $release['tag_name'] ?: $release['version'] ?: $tag );
		$archive_dl  = WebinoCRM_Gitea_Client::download_archive( $owner, $repo, $archive_tag );
		if ( ! empty( $archive_dl['ok'] ) && ! empty( $archive_dl['body'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dest, $archive_dl['body'] );
			if ( is_readable( $dest ) ) {
				return array(
					'path'   => $dest,
					'source' => self::SOURCE_CRM_BUILD,
				);
			}
		}

		$gr = self::find_gitea_release_row( $owner, $repo, $gitea_release_id, $tag );
		if ( is_array( $gr ) ) {
			$asset = WebinoCRM_Gitea_Client::first_zip_asset( $gr );
			if ( $asset ) {
				$dl = WebinoCRM_Gitea_Client::download_asset( $asset );
				if ( ! empty( $dl['ok'] ) && ! empty( $dl['body'] ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					file_put_contents( $dest, $dl['body'] );
					if ( is_readable( $dest ) ) {
						return array(
							'path'   => $dest,
							'source' => self::SOURCE_GITEA_ASSET,
						);
					}
				}
			}
		}

		$built = self::build_zip_from_tag( $owner, $repo, $tag, $dest );
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		return array(
			'path'   => $dest,
			'source' => self::SOURCE_CRM_BUILD,
		);
	}

	/**
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @param string $tag   Tag.
	 * @param string $dest  Destination zip path.
	 * @return true|WP_Error
	 */
	public static function build_zip_from_tag( $owner, $repo, $tag, $dest ) {
		$tag = sanitize_text_field( (string) $tag );

		if ( self::git_archive_available() ) {
			$clone_url = self::git_clone_url( $owner, $repo );
			if ( $clone_url ) {
				$tmpdir = trailingslashit( get_temp_dir() ) . 'webinocrm-mp-' . wp_generate_password( 8, false, false );
				wp_mkdir_p( $tmpdir );
				$clone = sprintf(
					'git clone --depth 1 --branch %s %s %s 2>&1',
					escapeshellarg( $tag ),
					escapeshellarg( $clone_url ),
					escapeshellarg( $tmpdir )
				);
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
				exec( $clone, $out, $code );
				if ( 0 === $code && is_dir( $tmpdir ) ) {
					$archive = sprintf(
						'git -C %s archive --format=zip --output=%s HEAD 2>&1',
						escapeshellarg( $tmpdir ),
						escapeshellarg( $dest )
					);
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
					exec( $archive, $out2, $code2 );
					self::rrmdir( $tmpdir );
					if ( 0 === $code2 && is_readable( $dest ) ) {
						return true;
					}
				} else {
					self::rrmdir( $tmpdir );
				}
			}
		}

		$dl = WebinoCRM_Gitea_Client::download_archive( $owner, $repo, $tag );
		if ( empty( $dl['ok'] ) || empty( $dl['body'] ) ) {
			return new WP_Error(
				'build_failed',
				$dl['message'] ?? __( 'Could not download source archive from Gitea.', 'webinocrm' ),
				array( 'status' => 502 )
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dest, $dl['body'] );
		if ( ! is_readable( $dest ) ) {
			return new WP_Error( 'build_failed', __( 'Could not write package ZIP.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public static function git_archive_available() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		@exec( 'git --version 2>&1', $out, $code );
		$cached = ( 0 === $code );
		return $cached;
	}

	/**
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @return string
	 */
	private static function git_clone_url( $owner, $repo ) {
		$base = WebinoCRM_Gitea_Client::base_url();
		$token = WebinoCRM_Gitea_Client::api_token();
		if ( '' === $base || '' === $token ) {
			return '';
		}
		$host = wp_parse_url( $base, PHP_URL_HOST );
		if ( ! $host ) {
			return '';
		}
		$scheme = wp_parse_url( $base, PHP_URL_SCHEME ) ?: 'https';
		return sprintf( '%s://oauth2:%s@%s/%s/%s.git', $scheme, rawurlencode( $token ), $host, rawurlencode( $owner ), rawurlencode( $repo ) );
	}

	/**
	 * @return WP_Error|null
	 */
	public static function get_last_resolve_error() {
		return self::$last_resolve_error;
	}

	/**
	 * @param int                 $module_id Module id.
	 * @param string              $version   Requested version or empty.
	 * @param array<string,mixed> $module    Optional module row (latest_release_id).
	 * @return array<string,mixed>|null
	 */
	public static function find_published_release( $module_id, $version = '', array $module = array() ) {
		$mid     = (int) $module_id;
		$release = null;
		if ( '' !== $version ) {
			$release = self::get_published_by_version( $mid, $version );
			if ( ! $release ) {
				$release = self::get_latest_published( $mid );
			}
		} else {
			$release = self::get_latest_published( $mid );
			if ( ! $release && ! empty( $module['latest_release_id'] ) ) {
				$row = self::get_by_id( (int) $module['latest_release_id'] );
				if ( $row && self::STATUS_PUBLISHED === (string) ( $row['status'] ?? '' ) ) {
					$release = $row;
				}
			}
		}
		return $release;
	}

	/**
	 * @param array<string,mixed> $module       Module row.
	 * @param array<string,mixed> $release      Published release row.
	 * @param string              $package_path Resolved ZIP path.
	 * @param string              $package_source Source id.
	 * @return void
	 */
	public static function persist_resolved_package( array $module, array $release, $package_path, $package_source = '' ) {
		$package_path   = (string) $package_path;
		$package_source = $package_source ? (string) $package_source : (string) ( $release['package_source'] ?? self::SOURCE_CRM_BUILD );
		self::save_release(
			array(
				'id'               => (int) $release['id'],
				'module_id'        => (int) $release['module_id'],
				'version'          => (string) $release['version'],
				'tag_name'         => (string) ( $release['tag_name'] ?? '' ),
				'changelog'        => (string) ( $release['changelog'] ?? '' ),
				'gitea_release_id' => (int) ( $release['gitea_release_id'] ?? 0 ),
				'package_path'     => $package_path,
				'package_source'   => $package_source,
				'status'           => (string) ( $release['status'] ?? self::STATUS_PUBLISHED ),
				'published_at'     => $release['published_at'] ?? null,
			)
		);
		global $wpdb;
		$wpdb->update(
			WebinoCRM_Marketplace_Manager::modules_table(),
			array(
				'version'           => (string) $release['version'],
				'latest_release_id' => (int) $release['id'],
				'package_path'      => $package_path,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => (int) $module['id'] )
		);
	}

	/**
	 * @param array<string,mixed> $module  Module row.
	 * @param array<string,mixed> $release Release row.
	 * @return string Empty if unavailable.
	 */
	private static function resolve_release_package_path( array $module, array $release ) {
		$path = (string) ( $release['package_path'] ?? '' );
		if ( '' !== $path && is_readable( $path ) ) {
			return $path;
		}
		if ( 'gitea' !== (string) ( $module['package_source'] ?? '' ) ) {
			return ( '' !== $path && is_readable( $path ) ) ? $path : '';
		}
		$tag      = (string) ( $release['tag_name'] ?: $release['version'] );
		$built    = self::ensure_package_for_release( $module, $release, (int) ( $release['gitea_release_id'] ?? 0 ), $tag );
		if ( is_wp_error( $built ) ) {
			self::$last_resolve_error = $built;
			return '';
		}
		self::persist_resolved_package( $module, $release, $built['path'], $built['source'] );
		return $built['path'];
	}

	/**
	 * @param string $owner            Gitea owner.
	 * @param string $repo             Gitea repo.
	 * @param int    $gitea_release_id CRM-linked Gitea release id.
	 * @param string $tag              Tag name fallback.
	 * @return array<string,mixed>|null
	 */
	private static function find_gitea_release_row( $owner, $repo, $gitea_release_id, $tag ) {
		$list = WebinoCRM_Gitea_Client::list_releases( $owner, $repo );
		if ( empty( $list['ok'] ) || ! is_array( $list['data'] ) ) {
			return null;
		}
		$releases = $list['data'];
		if ( $gitea_release_id ) {
			foreach ( $releases as $gr ) {
				if ( is_array( $gr ) && (int) ( $gr['id'] ?? 0 ) === (int) $gitea_release_id ) {
					return $gr;
				}
			}
		}
		$tag       = (string) $tag;
		$tag_norm  = ltrim( strtolower( $tag ), 'v' );
		if ( '' === $tag ) {
			return null;
		}
		foreach ( $releases as $gr ) {
			if ( ! is_array( $gr ) ) {
				continue;
			}
			$gr_tag = (string) ( $gr['tag_name'] ?? '' );
			if ( $gr_tag === $tag || ltrim( strtolower( $gr_tag ), 'v' ) === $tag_norm ) {
				return $gr;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $module  Module row.
	 * @param string              $version Version or empty for latest.
	 * @return string Empty if unavailable.
	 */
	public static function resolve_package_path( array $module, $version = '' ) {
		self::$last_resolve_error = null;
		$source = (string) ( $module['package_source'] ?? 'local' );
		if ( 'gitea' !== $source ) {
			return WebinoCRM_Marketplace_Manager::resolve_package_path( $module, $version );
		}
		$mid = (int) ( $module['id'] ?? 0 );
		if ( ! $mid ) {
			return '';
		}
		$release = self::find_published_release( $mid, $version, $module );
		if ( $release ) {
			$path = self::resolve_release_package_path( $module, $release );
			if ( '' !== $path ) {
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
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				self::rrmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $dir );
	}

	/**
	 * @param int  $release_id Release id.
	 * @param bool $delete_gitea Delete remote release (not implemented — manual).
	 * @return bool
	 */
	public static function delete_release( $release_id ) {
		global $wpdb;
		$release = self::get_by_id( (int) $release_id );
		if ( ! $release ) {
			return false;
		}
		if ( ! empty( $release['package_path'] ) && is_file( $release['package_path'] ) ) {
			wp_delete_file( $release['package_path'] );
		}
		return (bool) $wpdb->delete( self::releases_table(), array( 'id' => (int) $release_id ) );
	}
}
