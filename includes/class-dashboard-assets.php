<?php
/**
 * Enqueue dashboard SPA assets only on /dashboard.
 *
 * @package WebinoDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset registration for Vite build output.
 */
class WebinoCRM_Dashboard_Assets {

	/**
	 * True when Vite entry JS is missing on disk (enqueue aborted).
	 *
	 * @var bool
	 */
	private static $build_missing = false;

	/**
	 * Resolved entry paths after render_entry_tags() (used by enqueue).
	 *
	 * @var array{js: string, css: string, js_path: string, css_path: string}|null
	 */
	private static $resolved_snapshot = null;

	/**
	 * @var bool
	 */
	private static $entry_tags_printed = false;

	/**
	 * @return bool
	 */
	public static function is_build_missing() {
		return self::$build_missing;
	}

	/**
	 * Cache-bust token for shell / service worker (max filemtime of entry assets).
	 *
	 * @return string
	 */
	public static function get_deploy_asset_version() {
		$build_dir = WEBINOCRM_PLUGIN_DIR . 'assets/dashboard-build/';
		$resolved  = self::resolve_entry_assets( $build_dir );
		if ( null === $resolved ) {
			return WEBINOCRM_VERSION;
		}
		$version = is_readable( $resolved['js_path'] ) ? (int) filemtime( $resolved['js_path'] ) : 0;
		if ( '' !== $resolved['css_path'] && is_readable( $resolved['css_path'] ) ) {
			$version = max( $version, (int) filemtime( $resolved['css_path'] ) );
		}
		return (string) max( 1, $version );
	}

	/**
	 * @param string $url Absolute asset URL.
	 * @return array{ok: bool, status: int, content_type: string|null}
	 */
	private static function probe_asset_http( $url ) {
		$res = wp_remote_head(
			$url,
			array(
				'timeout'   => 8,
				'sslverify' => true,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'           => false,
				'status'       => 0,
				'content_type' => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$type = wp_remote_retrieve_header( $res, 'content-type' );
		return array(
			'ok'           => $code >= 200 && $code < 400,
			'status'       => $code,
			'content_type' => is_string( $type ) ? $type : null,
		);
	}

	/**
	 * Visible notice when the SPA build was not deployed.
	 *
	 * @return void
	 */
	public static function render_build_missing_notice() {
		if ( ! self::$build_missing ) {
			return;
		}
		$msg = esc_html__( 'Dashboard build files are missing on the server.', 'webinocrm' );
		$hint = esc_html__( 'Upload assets/dashboard-build from: cd client && npm run build', 'webinocrm' );
		echo '<div id="wd-php-build-missing" role="alert" style="box-sizing:border-box;padding:1.5rem;font-family:system-ui,sans-serif;background:#fef2f2;color:#7f1d1d;min-height:50vh;">';
		echo '<p style="margin:0 0 0.5rem;font-size:1.125rem;font-weight:600;">' . $msg . '</p>';
		echo '<p style="margin:0;font-size:0.875rem;">' . $hint . '</p>';
		echo '</div>';
		echo '<script>try{var n=document.getElementById("wd-php-build-missing"),r=document.getElementById("root");if(n&&r){r.innerHTML=n.outerHTML;n.remove();}}catch(e){}</script>';
	}

	/**
	 * @return void
	 */
	public function __construct() {
		add_filter( 'script_loader_tag', array( $this, 'force_app_script_module' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'maybe_serve_admin_tools' ), 0 );
	}

	/**
	 * Admin JSON tools: ?wd_diag=1 and ?wd_sniff=1 on /dashboard.
	 *
	 * @return void
	 */
	public function maybe_serve_admin_tools() {
		if ( ! webinocrm_dashboard()->rewrite->is_dashboard_request() ) {
			return;
		}

		$is_diag  = isset( $_GET['wd_diag'] ) && '1' === (string) wp_unslash( $_GET['wd_diag'] );
		$is_sniff = isset( $_GET['wd_sniff'] ) && '1' === (string) wp_unslash( $_GET['wd_sniff'] );
		if ( ! $is_diag && ! $is_sniff ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Forbidden', 'webinocrm' ), '', array( 'response' => 403 ) );
		}

		if ( $is_sniff ) {
			$this->send_html_sniff_json();
			return;
		}

		$this->send_deploy_diag_json();
	}

	/**
	 * @return void
	 */
	private function send_deploy_diag_json() {

		$build_dir   = WEBINOCRM_PLUGIN_DIR . 'assets/dashboard-build/';
		$assets_php  = WEBINOCRM_PLUGIN_DIR . 'includes/class-webinocrm-assets.php';
		$resolved    = self::resolve_entry_assets( $build_dir );
		$opcache_on  = function_exists( 'opcache_get_status' );
		$opcache_ok  = false;
		if ( $opcache_on ) {
			$st = opcache_get_status( false );
			$opcache_ok = is_array( $st ) && ! empty( $st['opcache_enabled'] );
		}

		$build_url    = WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/';
		$legacy_js    = $build_dir . 'assets/index.js';
		$legacy_css   = $build_dir . 'assets/index.css';
		$expected_js  = null !== $resolved ? $build_url . $resolved['js'] : null;
		$expected_css = null !== $resolved && '' !== $resolved['css'] ? $build_url . $resolved['css'] : null;
		$shared_rel   = null !== $resolved ? self::resolve_shared_chunk_rel( $build_dir, $resolved ) : null;
		$expected_shared = null !== $shared_rel ? $build_url . $shared_rel : null;

		$asset_http = array();
		if ( null !== $expected_js ) {
			$asset_http['entry_js'] = self::probe_asset_http( $expected_js );
		}
		if ( null !== $expected_css ) {
			$asset_http['entry_css'] = self::probe_asset_http( $expected_css );
		}
		if ( null !== $expected_shared ) {
			$asset_http['shared_js'] = self::probe_asset_http( $expected_shared );
		}

		$payload = array(
			'plugin_version'          => WEBINOCRM_VERSION,
			'plugin_dir'              => WEBINOCRM_PLUGIN_DIR,
			'plugin_url'              => WEBINOCRM_PLUGIN_URL,
			'php_file_mtime'          => is_readable( $assets_php ) ? (int) filemtime( $assets_php ) : 0,
			'build_dir_exists'        => is_dir( $build_dir ),
			'build_entry_json'        => is_readable( $build_dir . 'build-entry.json' ),
			'manifest_json'           => is_readable( $build_dir . 'manifest.json' ),
			'vite_manifest'           => is_readable( $build_dir . '.vite/manifest.json' ),
			'resolved_js'             => null !== $resolved ? $resolved['js'] : null,
			'resolved_css'            => null !== $resolved ? $resolved['css'] : null,
			'resolved_js_url'         => $expected_js,
			'expected_js_url'         => $expected_js,
			'expected_css_url'        => $expected_css,
			'expected_shared_url'     => $expected_shared,
			'legacy_index_js_on_disk' => is_readable( $legacy_js ),
			'legacy_index_css_on_disk'=> is_readable( $legacy_css ),
			'asset_http'              => $asset_http,
			'deploy_asset_version'    => self::get_deploy_asset_version(),
			'build_missing'           => self::$build_missing,
			'opcache'                 => $opcache_on ? ( $opcache_ok ? 'enabled' : 'disabled' ) : 'unavailable',
			'rewrite_version'         => (string) get_option( 'webinocrm_dashboard_rewrite_version', '' ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * HTML / cache sniff for admins (?wd_sniff=1).
	 *
	 * @return void
	 */
	private function send_html_sniff_json() {
		$build_dir = WEBINOCRM_PLUGIN_DIR . 'assets/dashboard-build/';
		$build_url = WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/';
		$resolved  = self::resolve_entry_assets( $build_dir );

		$legacy_js  = $build_dir . 'assets/index.js';
		$legacy_css = $build_dir . 'assets/index.css';
		$alias_js_ok  = false;
		$alias_css_ok = false;
		if ( null !== $resolved && is_readable( $legacy_js ) && is_readable( $resolved['js_path'] ) ) {
			$alias_js_ok = ( filesize( $legacy_js ) === filesize( $resolved['js_path'] ) )
				&& ( md5_file( $legacy_js ) === md5_file( $resolved['js_path'] ) );
		}
		if ( null !== $resolved && '' !== $resolved['css_path'] && is_readable( $legacy_css ) && is_readable( $resolved['css_path'] ) ) {
			$alias_css_ok = ( filesize( $legacy_css ) === filesize( $resolved['css_path'] ) )
				&& ( md5_file( $legacy_css ) === md5_file( $resolved['css_path'] ) );
		}

		$shell_js  = null !== $resolved ? $build_url . $resolved['js'] : null;
		$shell_css = null !== $resolved && '' !== $resolved['css'] ? $build_url . $resolved['css'] : null;
		$has_hashed = null !== $resolved && false !== strpos( $resolved['js'], 'index-' );

		$legacy_js_url  = $build_url . 'assets/index.js';
		$legacy_css_url = $build_url . 'assets/index.css';
		$asset_http     = array();
		if ( null !== $shell_js ) {
			$asset_http['shell_entry_js'] = self::probe_asset_http( $shell_js );
		}
		if ( null !== $shell_css ) {
			$asset_http['shell_entry_css'] = self::probe_asset_http( $shell_css );
		}
		$asset_http['compat_index_js']  = self::probe_asset_http( $legacy_js_url );
		$asset_http['compat_index_css'] = self::probe_asset_http( $legacy_css_url );

		$page_cache_hint = '';
		if ( $has_hashed && $alias_js_ok && $alias_css_ok ) {
			$page_cache_hint = 'PHP and compat aliases OK. If the browser still requests bare index.js/css, purge full-page cache for /dashboard (LiteSpeed, Cloudflare APO) and hard-refresh.';
		} elseif ( null === $resolved ) {
			$page_cache_hint = 'Build missing on disk.';
		}

		$payload = array(
			'plugin_version'             => WEBINOCRM_VERSION,
			'shell_would_emit_js_url'    => $shell_js,
			'shell_would_emit_css_url'   => $shell_css,
			'has_hashed_entry_tag'       => $has_hashed,
			'has_legacy_index_js_on_disk'=> is_readable( $legacy_js ),
			'has_legacy_index_css_on_disk'=> is_readable( $legacy_css ),
			'compat_aliases_ok'          => $alias_js_ok && ( null === $resolved || '' === $resolved['css'] || $alias_css_ok ),
			'compat_alias_js_matches_entry' => $alias_js_ok,
			'compat_alias_css_matches_entry'=> $alias_css_ok,
			'asset_http'                 => $asset_http,
			'page_cache_hint'            => $page_cache_hint,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Print hashed entry CSS/JS in the document head (before wp_head).
	 *
	 * @return void
	 */
	public static function render_entry_tags() {
		if ( self::$entry_tags_printed ) {
			return;
		}

		$build_dir = WEBINOCRM_PLUGIN_DIR . 'assets/dashboard-build/';
		$build_url = WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/';
		$resolved  = self::resolve_entry_assets( $build_dir );
		if ( null === $resolved ) {
			self::$build_missing = true;
			self::render_version_marker( null, null );
			return;
		}

		self::$resolved_snapshot = $resolved;
		self::$entry_tags_printed = true;
		self::$build_missing     = false;

		$entry_js      = $resolved['js_path'];
		$entry_css     = $resolved['css_path'];
		$entry_js_url  = $build_url . $resolved['js'];
		$entry_css_url = '' !== $resolved['css'] ? $build_url . $resolved['css'] : '';

		self::render_version_marker( $resolved['js'], $resolved['css'] );

		if ( '' !== $entry_css_url && is_readable( $entry_css ) ) {
			$ver = (string) filemtime( $entry_css );
			echo '<link rel="stylesheet" id="webinocrm-dashboard-app-css" href="' . esc_url( $entry_css_url ) . '?ver=' . esc_attr( $ver ) . '" media="all" />' . "\n";
		}

		$shared_rel = self::resolve_shared_chunk_rel( $build_dir, $resolved );
		if ( null !== $shared_rel ) {
			echo '<link rel="modulepreload" href="' . esc_url( $build_url . $shared_rel ) . '" crossorigin />' . "\n";
		}

		$href = esc_url( rest_url( 'webinocrm/v1/manifest.webmanifest' ) );
		echo '<link rel="manifest" href="' . $href . '" />' . "\n";

		if ( is_readable( $entry_js ) ) {
			$ver = (string) filemtime( $entry_js );
			echo '<script type="module" id="webinocrm-dashboard-app-js" src="' . esc_url( $entry_js_url ) . '?ver=' . esc_attr( $ver ) . '"></script>' . "\n";
		}
	}

	/**
	 * HTML comment in head so View Source shows active plugin + entry paths.
	 *
	 * @param string|null $js_rel Relative JS under dashboard-build.
	 * @param string|null $css_rel Relative CSS under dashboard-build.
	 * @return void
	 */
	private static function render_version_marker( $js_rel, $css_rel ) {
		$ver = WEBINOCRM_VERSION;
		if ( null === $js_rel ) {
			echo '<!-- WebinoCRM ' . esc_html( $ver ) . ' build=MISSING -->' . "\n";
			return;
		}
		$js  = esc_html( $js_rel );
		$css = $css_rel ? esc_html( $css_rel ) : 'none';
		echo '<!-- WebinoCRM ' . esc_html( $ver ) . ' entry=' . $js . ' css=' . $css . ' -->' . "\n";
	}

	/**
	 * Vite outputs native ES modules (`import` / `import()`). Without `type="module"`,
	 * browsers parse the file as a classic script and throw — React never mounts (#root stays empty).
	 *
	 * @param string $tag    Full script element HTML.
	 * @param string $handle Registered script handle.
	 * @param string $src    Script URL (unused).
	 * @return string
	 */
	/**
	 * @param string $build_dir Absolute path to dashboard-build (trailing slash).
	 * @return array<string,mixed>|null
	 */
	/**
	 * @param string $build_dir Absolute path to dashboard-build (trailing slash).
	 * @return array{js: string, css: string, js_path: string, css_path: string}|null
	 */
	private static function load_build_entry_json( $build_dir ) {
		$path = $build_dir . 'build-entry.json';
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['js'] ) ) {
			return null;
		}
		$js_rel  = ltrim( (string) $data['js'], '/' );
		$js_path = $build_dir . $js_rel;
		if ( ! is_readable( $js_path ) ) {
			return null;
		}
		$css_rel  = '';
		$css_path = '';
		if ( ! empty( $data['css'] ) ) {
			$css_rel  = ltrim( (string) $data['css'], '/' );
			$css_path = $build_dir . $css_rel;
			if ( ! is_readable( $css_path ) ) {
				$css_rel  = '';
				$css_path = '';
			}
		}
		return array(
			'js'       => $js_rel,
			'css'      => $css_rel,
			'js_path'  => $js_path,
			'css_path' => $css_path,
		);
	}

	/**
	 * When manifest / build-entry.json are missing (e.g. .vite not uploaded), match hashed files on disk.
	 *
	 * @param string $build_dir Absolute path to dashboard-build (trailing slash).
	 * @return array{js: string, css: string, js_path: string, css_path: string}|null
	 */
	private static function discover_hashed_entry_assets( $build_dir ) {
		$assets_dir = $build_dir . 'assets/';
		if ( ! is_dir( $assets_dir ) ) {
			return null;
		}

		$js_files = glob( $assets_dir . 'index-*.js' );
		if ( ! is_array( $js_files ) || empty( $js_files ) ) {
			return null;
		}

		$js_path = $js_files[0];
		if ( count( $js_files ) > 1 ) {
			usort(
				$js_files,
				static function ( $a, $b ) {
					return filemtime( $b ) <=> filemtime( $a );
				}
			);
			$js_path = $js_files[0];
		}

		$js_rel = 'assets/' . basename( $js_path );
		$css_rel  = '';
		$css_path = '';
		$css_files = glob( $assets_dir . 'index-*.css' );
		if ( is_array( $css_files ) && ! empty( $css_files ) ) {
			$css_path = $css_files[0];
			if ( count( $css_files ) > 1 ) {
				usort(
					$css_files,
					static function ( $a, $b ) {
						return filemtime( $b ) <=> filemtime( $a );
					}
				);
				$css_path = $css_files[0];
			}
			$css_rel = 'assets/' . basename( $css_path );
		}

		return array(
			'js'       => $js_rel,
			'css'      => $css_rel,
			'js_path'  => $js_path,
			'css_path' => $css_path,
		);
	}

	/**
	 * Resolve entry paths: build-entry.json → Vite manifest → glob hashed files.
	 *
	 * @param string $build_dir Absolute path to dashboard-build (trailing slash).
	 * @return array{js: string, css: string, js_path: string, css_path: string, manifest: array<string,mixed>|null, entry: array<string,mixed>|null}|null
	 */
	private static function resolve_entry_assets( $build_dir ) {
		$basic = self::load_build_entry_json( $build_dir );
		if ( null !== $basic ) {
			return array_merge(
				$basic,
				array(
					'manifest' => self::load_vite_manifest( $build_dir ),
					'entry'    => null,
				)
			);
		}

		$from_manifest = self::resolve_vite_entry_assets( $build_dir );
		if ( null !== $from_manifest ) {
			return $from_manifest;
		}

		$discovered = self::discover_hashed_entry_assets( $build_dir );
		if ( null === $discovered ) {
			return null;
		}

		return array_merge(
			$discovered,
			array(
				'manifest' => self::load_vite_manifest( $build_dir ),
				'entry'    => null,
			)
		);
	}

	private static function load_vite_manifest( $build_dir ) {
		$manifest_paths = array(
			$build_dir . 'manifest.json',
			$build_dir . '.vite/manifest.json',
		);

		foreach ( $manifest_paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$raw = file_get_contents( $path );
			if ( false === $raw ) {
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Resolve hashed entry JS/CSS from the Vite build manifest.
	 *
	 * @param string $build_dir Absolute path to dashboard-build (trailing slash).
	 * @return array{js: string, css: string, js_path: string, css_path: string, manifest: array<string,mixed>, entry: array<string,mixed>}|null
	 */
	private static function resolve_vite_entry_assets( $build_dir ) {
		$manifest = self::load_vite_manifest( $build_dir );

		if ( null === $manifest ) {
			return null;
		}

		$entry = null;
		foreach ( array( 'index.html', 'src/main.tsx' ) as $key ) {
			if ( isset( $manifest[ $key ] ) && is_array( $manifest[ $key ] ) ) {
				$entry = $manifest[ $key ];
				break;
			}
		}

		if ( null === $entry && ! empty( $manifest ) ) {
			foreach ( $manifest as $item ) {
				if ( is_array( $item ) && ! empty( $item['isEntry'] ) ) {
					$entry = $item;
					break;
				}
			}
		}

		if ( null === $entry || empty( $entry['file'] ) ) {
			return null;
		}

		$js_rel  = ltrim( (string) $entry['file'], '/' );
		$js_path = $build_dir . $js_rel;
		if ( ! is_readable( $js_path ) ) {
			return null;
		}

		$css_rel  = '';
		$css_path = '';
		if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
			$css_rel = ltrim( (string) $entry['css'][0], '/' );
			$css_path = $build_dir . $css_rel;
			if ( ! is_readable( $css_path ) ) {
				$css_rel  = '';
				$css_path = '';
			}
		}

		return array(
			'js'       => $js_rel,
			'css'      => $css_rel,
			'js_path'  => $js_path,
			'css_path' => $css_path,
			'manifest' => $manifest,
			'entry'    => $entry,
		);
	}

	/**
	 * @param string              $build_dir Build directory (trailing slash).
	 * @param array<string,mixed> $resolved  Result from resolve_entry_assets().
	 * @return string|null Relative path under dashboard-build.
	 */
	private static function resolve_shared_chunk_rel( $build_dir, $resolved ) {
		$manifest = isset( $resolved['manifest'] ) && is_array( $resolved['manifest'] ) ? $resolved['manifest'] : null;
		$entry    = isset( $resolved['entry'] ) && is_array( $resolved['entry'] ) ? $resolved['entry'] : null;
		if ( null !== $manifest && null !== $entry ) {
			$rel = self::resolve_vite_shared_chunk_rel( $build_dir, $manifest, $entry );
			if ( null !== $rel ) {
				return $rel;
			}
		}

		$info_path = $build_dir . 'build-entry.json';
		if ( is_readable( $info_path ) ) {
			$data = json_decode( (string) file_get_contents( $info_path ), true );
			if ( is_array( $data ) && ! empty( $data['shared'] ) ) {
				$rel = ltrim( (string) $data['shared'], '/' );
				if ( is_readable( $build_dir . $rel ) ) {
					return $rel;
				}
			}
		}

		$matches = glob( $build_dir . 'assets/dashboard-shared-*.js' );
		if ( is_array( $matches ) && ! empty( $matches[0] ) ) {
			return 'assets/' . basename( $matches[0] );
		}

		return null;
	}

	/**
	 * @param string                   $build_dir Absolute build directory (trailing slash).
	 * @param array<string,mixed>      $manifest  Vite manifest.
	 * @param array<string,mixed>      $entry     Manifest entry chunk.
	 * @return string|null Relative JS path (e.g. assets/dashboard-shared-*.js).
	 */
	private static function resolve_vite_shared_chunk_rel( $build_dir, $manifest, $entry ) {
		if ( empty( $entry['imports'] ) || ! is_array( $entry['imports'] ) ) {
			return null;
		}

		foreach ( $entry['imports'] as $import_key ) {
			if ( ! is_string( $import_key ) || false === strpos( $import_key, 'dashboard-shared' ) ) {
				continue;
			}
			if ( empty( $manifest[ $import_key ] ) || ! is_array( $manifest[ $import_key ] ) ) {
				continue;
			}
			$chunk = $manifest[ $import_key ];
			if ( empty( $chunk['file'] ) ) {
				continue;
			}
			$rel = ltrim( (string) $chunk['file'], '/' );
			if ( is_readable( $build_dir . $rel ) ) {
				return $rel;
			}
		}

		return null;
	}

	/**
	 * @param int $user_id WordPress user ID.
	 * @return array<string,mixed>|null
	 */
	private static function get_cached_bootstrap( $user_id ) {
		if ( $user_id <= 0 ) {
			return null;
		}
		$cached = get_transient( 'webinocrm_dashboard_boot_' . $user_id );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @param int                  $user_id WordPress user ID.
	 * @param array<string,mixed>  $data    Bootstrap payload.
	 * @return void
	 */
	private static function set_cached_bootstrap( $user_id, $data ) {
		if ( $user_id <= 0 || ! is_array( $data ) ) {
			return;
		}
		set_transient( 'webinocrm_dashboard_boot_' . $user_id, $data, 90 );
	}

	public function force_app_script_module( $tag, $handle, $src ) {
		if ( 'webinocrm-dashboard-app' !== $handle ) {
			return $tag;
		}
		if ( preg_match( '/\btype\s*=\s*([\'"])module\1/i', $tag ) ) {
			return $tag;
		}
		// WordPress may emit type="text/javascript"; replace so `import` remains valid.
		$tag = preg_replace( '/\s+type\s*=\s*([\'"])text\/javascript\1/i', ' type=$1module$1', $tag, 1 );
		if ( preg_match( '/\btype\s*=\s*([\'"])module\1/i', $tag ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script type="module" ', $tag, 1 );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		$rewrite = webinocrm_dashboard()->rewrite;
		if ( ! $rewrite->is_dashboard_request() ) {
			return;
		}

		$build_dir = WEBINOCRM_PLUGIN_DIR . 'assets/dashboard-build/';
		$build_url = WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/';

		$resolved = self::$resolved_snapshot ?? self::resolve_entry_assets( $build_dir );
		if ( null === $resolved ) {
			self::$build_missing = true;
			if ( ! self::$entry_tags_printed ) {
				add_action(
					'wp_head',
					static function () {
						self::render_version_marker( null, null );
					},
					1
				);
			}
			add_action(
				'wp_footer',
				static function () {
					echo '<script>console.warn("[WebinoCRM] Build missing. Run: cd client && npm install && npm run build");</script>';
				},
				9999
			);
			return;
		}

		$entry_js  = $resolved['js_path'];
		$entry_css = $resolved['css_path'];

		$asset_version = is_readable( $entry_js ) ? (int) filemtime( $entry_js ) : 0;
		if ( is_readable( $entry_css ) ) {
			$asset_version = max( $asset_version, (int) filemtime( $entry_css ) );
		}

		wp_register_script( 'webinocrm-dashboard-app', false, array(), WEBINOCRM_VERSION, true );
		wp_enqueue_script( 'webinocrm-dashboard-app' );

		wp_localize_script(
			'webinocrm-dashboard-app',
			'webinoDashboard',
			self::build_dashboard_client_config( $asset_version, $build_url )
		);
	}

	/**
	 * Build frontend config shared by wp_localize and standalone shell inline bootstrap.
	 *
	 * @param int    $asset_version Max mtime for entry assets.
	 * @param string $build_url     Base URL of dashboard build directory.
	 * @return array<string,mixed>
	 */
	public static function build_dashboard_client_config( $asset_version = 0, $build_url = '' ) {
		$base   = trailingslashit( home_url( '/dashboard' ) );
		$uid    = get_current_user_id();
		$locale = $uid ? (string) get_user_meta( $uid, 'webino_dashboard_locale', true ) : '';
		if ( '' === $locale ) {
			$locale = determine_locale();
		}

		$bootstrap = null;
		if ( is_user_logged_in() ) {
			$bootstrap = self::get_cached_bootstrap( $uid );
			if ( null === $bootstrap ) {
				try {
					$response = WebinoCRM_Dashboard_REST::bootstrap();
					if ( $response instanceof WP_REST_Response ) {
						$bootstrap = $response->get_data();
						if ( is_array( $bootstrap ) ) {
							self::set_cached_bootstrap( $uid, $bootstrap );
						}
					}
				} catch ( Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( '[WebinoCRM] bootstrap embed failed: ' . $e->getMessage() );
					}
					$bootstrap = null;
				}
			}
		}

		return array(
			'version'      => WEBINOCRM_VERSION,
			'assetVersion' => (string) max( 1, (int) $asset_version ),
			'baseUrl'      => $base,
			'assetBase'    => $build_url ? $build_url : WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/',
			'homeUrl'      => home_url( '/' ),
			'restUrl'      => esc_url_raw( rest_url( 'webinocrm/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'ajaxNonce'    => wp_create_nonce( 'webinocrm-ajax-nonce' ),
			'loginNonce'   => wp_create_nonce( WebinoCRM_REST_Base::login_nonce_action() ),
			'i18n'         => array(
				'server_error' => __( 'Server error', 'webinocrm' ),
			),
			'locale'       => $locale,
			'isLogged'     => is_user_logged_in(),
			'userId'       => $uid,
			'siteName'     => webinocrm_product_display_name(),
			'siteIconUrl'  => WebinoCRM_Dashboard_REST::site_icon_url(),
			'license'      => array(),
			'bootstrap'    => $bootstrap,
			'marketplaceSettingsSections' => apply_filters( 'webinocrm_marketplace_settings_sections', array() ),
			'flags'        => array(
				'crm'                  => true,
				'disableServiceWorker' => true,
			),
			'wsUrl'        => class_exists( 'WebinoCRM_Realtime_Service' ) ? WebinoCRM_Realtime_Service::get_ws_url() : '',
			'wsEnabled'    => class_exists( 'WebinoCRM_Realtime_Service' ),
		);
	}

	/**
	 * Reduce theme CSS/JS noise on the isolated dashboard route.
	 *
	 * @return void
	 */
	public function dequeue_theme_on_dashboard() {
		if ( ! webinocrm_dashboard()->rewrite->is_dashboard_request() ) {
			return;
		}

		$keep = array( 'webinocrm-dashboard-app' );

		global $wp_styles;
		if ( $wp_styles instanceof WP_Styles ) {
			$handles = array_unique( array_merge( $wp_styles->queue, array_keys( $wp_styles->registered ) ) );
			foreach ( $handles as $handle ) {
				if ( $this->should_keep_dashboard_asset_handle( $handle, $keep ) ) {
					continue;
				}
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}

		global $wp_scripts;
		if ( $wp_scripts instanceof WP_Scripts ) {
			$handles = array_unique( array_merge( $wp_scripts->queue, array_keys( $wp_scripts->registered ) ) );
			foreach ( $handles as $handle ) {
				if ( $this->should_keep_dashboard_asset_handle( $handle, $keep ) ) {
					continue;
				}
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}
	}

	/**
	 * @param string        $handle Asset handle.
	 * @param array<string> $keep   Handles to preserve.
	 * @return bool
	 */
	private function should_keep_dashboard_asset_handle( $handle, array $keep ) {
		if ( in_array( $handle, $keep, true ) ) {
			return true;
		}
		if ( 0 === strpos( $handle, 'webinocrm-dashboard' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $which bale|telegram.
	 * @return bool
	 */
	private static function bot_has_token( $which ) {
		$opt = ( 'telegram' === $which ) ? 'webino_dashboard_telegram_bot_settings' : 'webino_dashboard_bale_bot_settings';
		$s   = get_option( $opt, array() );
		if ( ! is_array( $s ) ) {
			return false;
		}
		$t = isset( $s['bot_token'] ) ? trim( (string) $s['bot_token'] ) : '';
		return '' !== $t;
	}
}
