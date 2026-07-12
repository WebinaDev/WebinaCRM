<?php
/**
 * In-place CRM core update from marketplace package.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Core_Updater {

	const LOCK_KEY        = 'webinocrm_core_update_lock';
	const CHECK_CACHE_KEY = 'webinocrm_core_update_check';
	const CHECK_CACHE_TTL = 900;
	const MAX_BACKUPS     = 5;
	const CORE_SLUG       = 'webinocrm';

	/**
	 * @param bool $force_refresh Force cache refresh.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_update_status( $force_refresh = false ) {
		$current = defined( 'WEBINOCRM_VERSION' ) ? (string) WEBINOCRM_VERSION : '0.0.0';
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CHECK_CACHE_KEY );
			if ( is_array( $cached ) ) {
				$cached['version'] = $current;
				return $cached;
			}
		}

		$check = WebinoCRM_Marketplace_Manager::get_crm_core_update_check( $current );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$payload = array(
			'version'           => $current,
			'latest_version'    => (string) ( $check['latest_version'] ?? $current ),
			'update_available'  => ! empty( $check['update_available'] ),
			'release_notes'     => (string) ( $check['release_notes'] ?? '' ),
			'package_available' => ! empty( $check['package_available'] ),
		);
		set_transient( self::CHECK_CACHE_KEY, $payload, self::CHECK_CACHE_TTL );
		return $payload;
	}

	/**
	 * @param string $version Target version.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function run_update( $version = '' ) {
		if ( get_transient( self::LOCK_KEY ) ) {
			return new WP_Error( 'locked', __( 'A CRM core update is already in progress.', 'webinocrm' ), array( 'status' => 409 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to update CRM core.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip', __( 'ZipArchive is not available on this server.', 'webinocrm' ), array( 'status' => 500 ) );
		}

		$domain  = WebinoCRM_License_Manager::normalize_domain( wp_parse_url( home_url(), PHP_URL_HOST ) ?: '' );
		$license = WebinoCRM_Marketplace_Manager::assert_licensed_domain( $domain );
		if ( is_wp_error( $license ) ) {
			return $license;
		}

		set_transient( self::LOCK_KEY, 1, 15 * MINUTE_IN_SECONDS );
		$backup_dir = null;
		$staging    = null;

		try {
			$token = WebinoCRM_Marketplace_Manager::create_download_token( $domain, self::CORE_SLUG, (string) $version );
			if ( is_wp_error( $token ) ) {
				throw new Exception( $token->get_error_message() );
			}
			$url = add_query_arg( 'token', rawurlencode( (string) $token ), rest_url( 'webinocrm/v1/marketplace/download' ) );
			$tmp = download_url( $url, 600 );
			if ( is_wp_error( $tmp ) ) {
				throw new Exception( $tmp->get_error_message() );
			}

			$staging = trailingslashit( WP_CONTENT_DIR ) . '.webinocrm-core-update-' . wp_generate_password( 8, false, false );
			wp_mkdir_p( $staging );
			$unzip = self::unzip_to( $tmp, $staging );
			wp_delete_file( $tmp );
			if ( is_wp_error( $unzip ) ) {
				throw new Exception( $unzip->get_error_message() );
			}

			$core_src = self::locate_core_source_dir( $staging );
			if ( is_wp_error( $core_src ) ) {
				throw new Exception( $core_src->get_error_message() );
			}
			$valid = self::validate_core_package( $core_src );
			if ( is_wp_error( $valid ) ) {
				throw new Exception( $valid->get_error_message() );
			}

			$new_version = self::read_version_from_plugin_file( $core_src . 'webinocrm.php' );
			if ( '' === $new_version ) {
				throw new Exception( __( 'Could not read version from CRM update package.', 'webinocrm' ) );
			}
			$current = defined( 'WEBINOCRM_VERSION' ) ? (string) WEBINOCRM_VERSION : '0.0.0';
			if ( version_compare( $new_version, $current, '<' ) && ! apply_filters( 'webinocrm_allow_core_downgrade', false ) ) {
				throw new Exception( __( 'Downgrading CRM core is not allowed.', 'webinocrm' ) );
			}

			$backup_dir = self::create_backup();
			if ( is_wp_error( $backup_dir ) ) {
				throw new Exception( $backup_dir->get_error_message() );
			}
			$copied = self::copy_tree( $core_src, trailingslashit( WEBINOCRM_PLUGIN_DIR ) );
			if ( is_wp_error( $copied ) ) {
				self::restore_backup( $backup_dir );
				throw new Exception( $copied->get_error_message() );
			}

			delete_transient( self::CHECK_CACHE_KEY );
			self::prune_old_backups();
			flush_rewrite_rules( false );

			return array(
				'ok'               => true,
				'version'          => $new_version,
				'previous_version' => $current,
				'reload_required'  => true,
				'backup_path'      => $backup_dir,
			);
		} catch ( Exception $e ) {
			if ( $backup_dir && is_dir( $backup_dir ) ) {
				self::restore_backup( $backup_dir );
			}
			return new WP_Error( 'update_failed', $e->getMessage(), array( 'status' => 500 ) );
		} finally {
			delete_transient( self::LOCK_KEY );
			if ( $staging && is_dir( $staging ) ) {
				self::rrmdir( $staging );
			}
		}
	}

	/**
	 * @param string $staging Root extraction folder.
	 * @return string|WP_Error
	 */
	private static function locate_core_source_dir( $staging ) {
		$direct = trailingslashit( $staging ) . 'webinocrm/';
		if ( is_readable( $direct . 'webinocrm.php' ) ) {
			return $direct;
		}
		self::flatten_single_root_folder( $staging );
		$direct = trailingslashit( $staging ) . 'webinocrm/';
		if ( is_readable( $direct . 'webinocrm.php' ) ) {
			return $direct;
		}
		if ( is_readable( trailingslashit( $staging ) . 'webinocrm.php' ) ) {
			return trailingslashit( $staging );
		}
		return new WP_Error( 'invalid_package', __( 'Update ZIP does not contain webinocrm/webinocrm.php.', 'webinocrm' ), array( 'status' => 500 ) );
	}

	/**
	 * @param string $source Source directory with trailing slash.
	 * @return true|WP_Error
	 */
	private static function validate_core_package( $source ) {
		$required = array(
			'webinocrm.php',
			'includes/',
			'client/',
		);
		$missing = array();
		foreach ( $required as $path ) {
			$abs = $source . $path;
			if ( str_ends_with( $path, '/' ) ) {
				if ( ! is_dir( $abs ) ) {
					$missing[] = $path;
				}
				continue;
			}
			if ( ! is_readable( $abs ) ) {
				$missing[] = $path;
			}
		}
		if ( $missing ) {
			return new WP_Error( 'invalid_package', sprintf( __( 'CRM core package is missing required paths: %s', 'webinocrm' ), implode( ', ', $missing ) ), array( 'status' => 500, 'missing' => $missing ) );
		}
		return true;
	}

	private static function read_version_from_plugin_file( $file ) {
		if ( ! is_readable( $file ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			return '';
		}
		if ( preg_match( "/define\s*\(\s*'WEBINOCRM_VERSION'\s*,\s*'([^']+)'/", $contents, $m ) ) {
			return (string) $m[1];
		}
		if ( preg_match( '/Version:\s*([0-9a-zA-Z.-]+)/', $contents, $m ) ) {
			return (string) trim( $m[1] );
		}
		return '';
	}

	private static function create_backup() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'backup', $upload['error'], array( 'status' => 500 ) );
		}
		$root = trailingslashit( $upload['basedir'] ) . 'webinocrm-core-backups/';
		wp_mkdir_p( $root );
		$dest = $root . gmdate( 'Y-m-d-His' ) . '/';
		wp_mkdir_p( $dest );
		$copied = self::copy_tree( trailingslashit( WEBINOCRM_PLUGIN_DIR ), $dest );
		if ( is_wp_error( $copied ) ) {
			self::rrmdir( $dest );
			return $copied;
		}
		return $dest;
	}

	private static function restore_backup( $backup_dir ) {
		if ( ! is_dir( $backup_dir ) ) {
			return;
		}
		self::copy_tree( trailingslashit( $backup_dir ), trailingslashit( WEBINOCRM_PLUGIN_DIR ) );
	}

	private static function prune_old_backups() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}
		$root = trailingslashit( $upload['basedir'] ) . 'webinocrm-core-backups/';
		if ( ! is_dir( $root ) ) {
			return;
		}
		$dirs = array();
		foreach ( array_diff( scandir( $root ) ?: array(), array( '.', '..' ) ) as $name ) {
			$path = $root . $name;
			if ( is_dir( $path ) ) {
				$dirs[] = $path;
			}
		}
		usort(
			$dirs,
			static function ( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);
		foreach ( array_slice( $dirs, self::MAX_BACKUPS ) as $old ) {
			self::rrmdir( $old );
		}
	}

	private static function copy_tree( $src, $dest ) {
		if ( ! is_dir( $src ) ) {
			return new WP_Error( 'copy', __( 'Source directory is missing.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		wp_mkdir_p( $dest );
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			$rel    = substr( $item->getPathname(), strlen( $src ) );
			$target = $dest . $rel;
			if ( $item->isDir() ) {
				wp_mkdir_p( $target );
			} else {
				wp_mkdir_p( dirname( $target ) );
				if ( ! copy( $item->getPathname(), $target ) ) {
					return new WP_Error( 'copy', __( 'Failed to copy update files.', 'webinocrm' ), array( 'status' => 500 ) );
				}
			}
		}
		return true;
	}

	private static function unzip_to( $zip, $dest ) {
		$archive = new ZipArchive();
		if ( true !== $archive->open( $zip ) ) {
			return new WP_Error( 'zip', __( 'Could not open update ZIP.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		$archive->extractTo( $dest );
		$archive->close();
		return true;
	}

	private static function flatten_single_root_folder( $dir ) {
		$entries = array_diff( scandir( $dir ) ?: array(), array( '.', '..' ) );
		if ( 1 !== count( $entries ) ) {
			return;
		}
		$only = $dir . '/' . reset( $entries );
		if ( ! is_dir( $only ) ) {
			return;
		}
		foreach ( array_diff( scandir( $only ) ?: array(), array( '.', '..' ) ) as $item ) {
			rename( $only . '/' . $item, $dir . '/' . $item );
		}
		rmdir( $only );
	}

	private static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = array_diff( scandir( $dir ) ?: array(), array( '.', '..' ) );
		foreach ( $items as $item ) {
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::rrmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $dir );
	}
}
