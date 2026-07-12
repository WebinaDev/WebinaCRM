<?php
/**
 * Gitea REST API client for module package hosting.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP wrapper for Gitea API v1 (package.webina.dev).
 */
class WebinoCRM_Gitea_Client {

	/**
	 * Tag refs attempted on the last download_archive call.
	 *
	 * @var array<int,string>
	 */
	private static $last_archive_tags_tried = array();

	/**
	 * @return array<int,string>
	 */
	public static function get_last_archive_tags_tried() {
		return self::$last_archive_tags_tried;
	}

	/**
	 * Build tag/ref candidates for Gitea archive API (v1.0.0 vs 1.0.0).
	 *
	 * @param string $tag Tag or version string.
	 * @return array<int,string>
	 */
	public static function archive_tag_candidates( $tag ) {
		$tag = trim( (string) $tag );
		if ( '' === $tag ) {
			return array();
		}
		$candidates = array( $tag );
		$stripped   = ltrim( $tag, 'vV' );
		if ( '' !== $stripped && $stripped !== $tag ) {
			$candidates[] = $stripped;
		}
		if ( '' !== $stripped && ! preg_match( '/^v/i', $tag ) ) {
			$candidates[] = 'v' . $stripped;
		}
		return array_values( array_unique( $candidates ) );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::api_token() && '' !== self::base_url() && '' !== self::org();
	}

	/**
	 * @return string
	 */
	public static function base_url() {
		$url = (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_base_url', 'https://package.webina.dev' );
		return untrailingslashit( $url );
	}

	/**
	 * @return string
	 */
	public static function org() {
		return sanitize_key( (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_org', 'webina' ) );
	}

	/**
	 * @return string
	 */
	public static function api_token() {
		return (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_api_token', '' );
	}

	/**
	 * @return string
	 */
	public static function ip_override() {
		return trim( (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_ip_override', '' ) );
	}

	/**
	 * Direct IP connection scheme: auto | http | https.
	 *
	 * @return string
	 */
	public static function ip_scheme() {
		$scheme = sanitize_key( (string) WebinoCRM_Settings_Handler::get_setting( 'gitea_ip_scheme', 'auto' ) );
		return in_array( $scheme, array( 'auto', 'http', 'https' ), true ) ? $scheme : 'auto';
	}

	/**
	 * @return string
	 */
	public static function web_url() {
		return self::base_url();
	}

	/**
	 * @param string $repo Repo name (slug).
	 * @return string
	 */
	public static function repo_html_url( $repo ) {
		$owner = self::org();
		$repo  = sanitize_key( (string) $repo );
		return self::web_url() . '/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo );
	}

	/**
	 * @return array{ ok: bool, message: string, user?: string }
	 */
	public static function test_connection() {
		return self::test_connection_with_overrides( array() );
	}

	/**
	 * Test API connectivity using optional form overrides (unsaved settings).
	 *
	 * @param array<string,mixed> $overrides gitea_base_url, gitea_org, gitea_ip_override, gitea_api_token.
	 * @return array{ ok: bool, message: string, user?: string }
	 */
	public static function test_connection_with_overrides( array $overrides = array() ) {
		$result = self::run_connection_diagnostics( $overrides );
		return array(
			'ok'      => ! empty( $result['ok'] ),
			'message' => (string) ( $result['message'] ?? '' ),
			'user'    => (string) ( $result['user'] ?? '' ),
			'diag'    => $result['diag'] ?? array(),
			'steps'   => $result['steps'] ?? array(),
			'hints'   => $result['hints'] ?? array(),
		);
	}

	/**
	 * Multi-step connectivity diagnostics (network, auth, org).
	 *
	 * @param array<string,mixed> $overrides Optional settings overrides.
	 * @return array<string,mixed>
	 */
	public static function run_connection_diagnostics( array $overrides = array() ) {
		$config = self::resolve_config( $overrides );
		$hints  = array();
		$steps  = array();

		$sample_url = self::api_url_for_config( $config, '/version' );
		$rewrite    = self::rewrite_url_for_ip_override( $sample_url, $config );
		$diag       = array(
			'base_url'            => (string) $config['base_url'],
			'api_base'            => untrailingslashit( (string) $config['base_url'] ) . '/api/v1',
			'org'                 => (string) $config['org'],
			'ip_override'         => (string) $config['ip_override'],
			'ip_scheme'           => (string) $config['ip_scheme'],
			'host_header'         => (string) $rewrite['host_header'],
			'token_configured'    => '' !== (string) $config['api_token'],
			'resolved_url_sample' => (string) $rewrite['url'],
			'resolved_scheme'     => (string) $rewrite['scheme'],
			'sslverify'           => (bool) $rewrite['sslverify'],
		);

		if ( '' === $config['base_url'] || '' === $config['org'] ) {
			$hints[] = 'config_incomplete';
			return array(
				'ok'      => false,
				'message' => __( 'Gitea base URL and owner namespace are required.', 'webinocrm' ),
				'user'    => '',
				'diag'    => $diag,
				'steps'   => $steps,
				'hints'   => $hints,
			);
		}

		$steps['version'] = self::diagnostic_probe(
			$config,
			'version',
			__( 'API reachability', 'webinocrm' ),
			'GET',
			'/version',
			false
		);
		if ( ! empty( $steps['version']['curl_error'] ) && self::is_ssl_version_error_message( (string) $steps['version']['curl_error'] ) ) {
			$hints[] = 'ssl_wrong_version';
		}

		if ( '' === $config['api_token'] ) {
			$hints[] = 'token_missing';
			return array(
				'ok'      => false,
				'message' => __( 'API token is required.', 'webinocrm' ),
				'user'    => '',
				'diag'    => $diag,
				'steps'   => $steps,
				'hints'   => array_values( array_unique( $hints ) ),
			);
		}

		$steps['auth'] = self::diagnostic_probe(
			$config,
			'auth',
			__( 'Authentication', 'webinocrm' ),
			'GET',
			'/user',
			true
		);
		if ( ! empty( $steps['auth']['curl_error'] ) && self::is_ssl_version_error_message( (string) $steps['auth']['curl_error'] ) ) {
			$hints[] = 'ssl_wrong_version';
		}
		if ( empty( $steps['auth']['ok'] ) && 401 === (int) ( $steps['auth']['http'] ?? 0 ) ) {
			$hints[] = 'unauthorized';
		}

		$user       = '';
		$owner      = (string) $config['org'];
		$owner_kind = '';
		$diag['owner']      = $owner;
		$diag['owner_kind'] = '';

		if ( ! empty( $steps['auth']['ok'] ) ) {
			$auth_res = self::request_with_config( $config, 'GET', '/user' );
			$user     = is_array( $auth_res['data'] ?? null ) ? (string) ( $auth_res['data']['login'] ?? '' ) : '';

			$org_path  = '/orgs/' . rawurlencode( $owner );
			$user_path = '/users/' . rawurlencode( $owner );
			$org_probe = self::diagnostic_probe(
				$config,
				'owner',
				__( 'Owner access', 'webinocrm' ),
				'GET',
				$org_path,
				true
			);

			if ( ! empty( $org_probe['ok'] ) ) {
				$owner_kind         = 'org';
				$steps['owner']     = $org_probe;
				$owner_repos_path   = $org_path . '/repos';
			} else {
				$user_probe     = self::diagnostic_probe(
					$config,
					'owner',
					__( 'Owner access', 'webinocrm' ),
					'GET',
					$user_path,
					true
				);
				$steps['owner'] = $user_probe;
				if ( ! empty( $user_probe['ok'] ) ) {
					$owner_kind       = 'user';
					$owner_repos_path = $user_path . '/repos';
					if ( 404 === (int) ( $org_probe['http'] ?? 0 ) ) {
						$hints[] = 'owner_is_user';
					}
				} elseif ( 404 === (int) ( $org_probe['http'] ?? 0 ) && 404 === (int) ( $user_probe['http'] ?? 0 ) ) {
					$hints[] = 'owner_not_found';
					$owner_repos_path = $org_path . '/repos';
				} else {
					$owner_repos_path = $user_path . '/repos';
				}
			}

			if ( '' !== $owner_kind ) {
				$diag['owner_kind'] = $owner_kind;
				$steps['owner_repos'] = self::diagnostic_probe(
					$config,
					'owner_repos',
					__( 'Owner repositories', 'webinocrm' ),
					'GET',
					$owner_repos_path,
					true,
					array( 'limit' => 1 )
				);
			}
		}

		$hints = array_values( array_unique( $hints ) );
		$ok    = ! empty( $steps['auth']['ok'] ) && ! empty( $steps['owner']['ok'] );
		return array(
			'ok'      => $ok,
			'message' => $ok
				? ( $user
					? sprintf(
						/* translators: %s: Gitea username */
						__( 'Connected to Gitea as %s.', 'webinocrm' ),
						$user
					)
					: __( 'Connected to Gitea.', 'webinocrm' ) )
				: ( ! empty( $steps['auth']['message'] )
					? (string) $steps['auth']['message']
					: ( ! empty( $steps['version']['message'] )
						? (string) $steps['version']['message']
						: __( 'Connection failed.', 'webinocrm' ) ) ),
			'user'    => $user,
			'diag'    => $diag,
			'steps'   => $steps,
			'hints'   => $hints,
		);
	}

	/**
	 * @param string $name    Repo name.
	 * @param string $description Description.
	 * @param bool   $private Private repo.
	 * @return array{ ok: bool, data?: array<string,mixed>, message?: string, code?: int }
	 */
	public static function create_repo( $name, $description = '', $private = true ) {
		$name = sanitize_key( (string) $name );
		if ( '' === $name ) {
			return self::error( __( 'Invalid repository name.', 'webinocrm' ), 400 );
		}
		$config     = self::resolve_config( array() );
		$owner      = (string) $config['org'];
		$owner_kind = self::resolve_owner_kind( $config );
		$body       = array(
			'name'        => $name,
			'description' => (string) $description,
			'private'     => (bool) $private,
			'auto_init'   => true,
		);

		if ( null === $owner_kind ) {
			return self::error(
				sprintf(
					/* translators: %s: owner namespace */
					__( 'Owner "%s" was not found as organization or user.', 'webinocrm' ),
					$owner
				),
				404
			);
		}

		if ( 'org' === $owner_kind ) {
			return self::request(
				'POST',
				'/orgs/' . rawurlencode( $owner ) . '/repos',
				$body
			);
		}

		$auth  = self::request( 'GET', '/user' );
		$login = is_array( $auth['data'] ?? null ) ? (string) ( $auth['data']['login'] ?? '' ) : '';
		if ( $owner !== $login ) {
			return self::error(
				sprintf(
					/* translators: 1: configured owner, 2: API token username */
					__( 'Owner "%1$s" is a user account but does not match the API token user "%2$s". Create a Gitea organization or use a token for that user.', 'webinocrm' ),
					$owner,
					$login
				),
				403
			);
		}

		return self::request( 'POST', '/user/repos', $body );
	}

	/**
	 * Detect whether the configured owner namespace is a Gitea org or user.
	 *
	 * @param array<string,mixed> $config Resolved config.
	 * @return 'org'|'user'|null
	 */
	public static function resolve_owner_kind( array $config ) {
		$owner = (string) ( $config['org'] ?? '' );
		if ( '' === $owner ) {
			return null;
		}
		$org_res = self::request_with_config( $config, 'GET', '/orgs/' . rawurlencode( $owner ) );
		if ( ! empty( $org_res['ok'] ) ) {
			return 'org';
		}
		if ( 404 !== (int) ( $org_res['code'] ?? 0 ) ) {
			return null;
		}
		$user_res = self::request_with_config( $config, 'GET', '/users/' . rawurlencode( $owner ) );
		if ( ! empty( $user_res['ok'] ) ) {
			return 'user';
		}
		return null;
	}

	/**
	 * @param string $owner Owner (org).
	 * @param string $repo  Repo name.
	 * @return array{ ok: bool, data?: array<string,mixed>, message?: string, code?: int }
	 */
	public static function get_repo( $owner, $repo ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		return self::request( 'GET', '/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) );
	}

	/**
	 * @param string              $owner  Owner.
	 * @param string              $repo   Repo.
	 * @param array<string,mixed> $fields private, description, etc.
	 * @return array{ ok: bool, data?: array<string,mixed>, message?: string, code?: int }
	 */
	public static function update_repo( $owner, $repo, array $fields ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		$body  = array();
		if ( array_key_exists( 'private', $fields ) ) {
			$body['private'] = (bool) $fields['private'];
		}
		if ( array_key_exists( 'description', $fields ) ) {
			$body['description'] = (string) $fields['description'];
		}
		if ( ! $body ) {
			return self::error( __( 'No repository fields to update.', 'webinocrm' ), 400 );
		}
		return self::request(
			'PATCH',
			'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ),
			$body
		);
	}

	/**
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @return string
	 */
	public static function default_branch( $owner, $repo ) {
		$res = self::get_repo( $owner, $repo );
		if ( empty( $res['ok'] ) || ! is_array( $res['data'] ) ) {
			return 'main';
		}
		$branch = (string) ( $res['data']['default_branch'] ?? '' );
		return $branch ? $branch : 'main';
	}

	/**
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @param string $path  File path.
	 * @return array{ ok: bool, content?: string, sha?: string, message?: string }
	 */
	public static function get_file_contents( $owner, $repo, $path = 'README.md' ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		$path  = ltrim( (string) $path, '/' );
		$res   = self::request(
			'GET',
			'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/contents/' . rawurlencode( $path )
		);
		if ( empty( $res['ok'] ) || ! is_array( $res['data'] ) ) {
			return array(
				'ok'      => false,
				'message' => $res['message'] ?? __( 'Could not read file from repository.', 'webinocrm' ),
			);
		}
		$encoded = (string) ( $res['data']['content'] ?? '' );
		$content = base64_decode( str_replace( array( "\r", "\n" ), '', $encoded ), true );
		if ( false === $content ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not decode repository file.', 'webinocrm' ),
			);
		}
		return array(
			'ok'      => true,
			'content' => $content,
			'sha'     => (string) ( $res['data']['sha'] ?? '' ),
		);
	}

	/**
	 * Create or update a repository file.
	 *
	 * @param string $owner   Owner.
	 * @param string $repo    Repo.
	 * @param string $path    File path.
	 * @param string $content File content.
	 * @param string $message Commit message.
	 * @param string $sha     Existing blob sha (update).
	 * @return array{ ok: bool, data?: array<string,mixed>, message?: string }
	 */
	public static function upsert_file_contents( $owner, $repo, $path, $content, $message = '', $sha = '' ) {
		$owner   = sanitize_key( (string) $owner );
		$repo    = sanitize_key( (string) $repo );
		$path    = ltrim( (string) $path, '/' );
		$message = $message ? $message : __( 'Update README', 'webinocrm' );
		$body    = array(
			'message' => (string) $message,
			'content' => base64_encode( (string) $content ),
		);
		if ( '' !== $sha ) {
			$body['sha'] = (string) $sha;
		}
		$method = '' !== $sha ? 'PUT' : 'POST';
		return self::request(
			$method,
			'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/contents/' . rawurlencode( $path ),
			$body
		);
	}

	/**
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @return array{ ok: bool, message?: string }
	 */
	public static function delete_repo( $owner, $repo ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		$res   = self::request( 'DELETE', '/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) );
		return $res;
	}

	/**
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @return array{ ok: bool, data?: array<int,array<string,mixed>>, message?: string }
	 */
	public static function list_releases( $owner, $repo ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		$res   = self::request(
			'GET',
			'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/releases',
			array(),
			array( 'limit' => 50 )
		);
		if ( empty( $res['ok'] ) ) {
			return $res;
		}
		$data = is_array( $res['data'] ) ? $res['data'] : array();
		return array(
			'ok'   => true,
			'data' => $data,
		);
	}

	/**
	 * @param string $owner   Owner.
	 * @param string $repo    Repo.
	 * @param string $tag     Tag name.
	 * @param string $title   Release title.
	 * @param string $body    Release notes.
	 * @return array{ ok: bool, data?: array<string,mixed>, message?: string }
	 */
	public static function create_release( $owner, $repo, $tag, $title, $body = '', $target_commitish = '' ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		$tag   = sanitize_text_field( (string) $tag );
		if ( '' === $target_commitish ) {
			$target_commitish = self::default_branch( $owner, $repo );
		}
		return self::request(
			'POST',
			'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/releases',
			array(
				'tag_name'         => $tag,
				'target_commitish' => sanitize_text_field( (string) $target_commitish ),
				'name'             => $title ? $title : $tag,
				'body'             => (string) $body,
			)
		);
	}

	/**
	 * @param string $owner     Owner.
	 * @param string $repo      Repo.
	 * @param int    $release_id Release id.
	 * @param string $zip_path  Local ZIP path.
	 * @param string $name      Asset filename.
	 * @return array{ ok: bool, data?: array<string,mixed>, message?: string }
	 */
	public static function upload_release_asset( $owner, $repo, $release_id, $zip_path, $name = '' ) {
		$owner      = sanitize_key( (string) $owner );
		$repo       = sanitize_key( (string) $repo );
		$release_id = (int) $release_id;
		if ( ! $release_id || ! is_readable( $zip_path ) ) {
			return self::error( __( 'Release asset file is not readable.', 'webinocrm' ), 400 );
		}
		$name = $name ? $name : basename( $zip_path );
		$url  = self::api_url(
			'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/releases/' . $release_id . '/assets',
			array( 'name' => $name )
		);

		$boundary = wp_generate_password( 24, false );
		$body     = file_get_contents( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $body ) {
			return self::error( __( 'Could not read ZIP file.', 'webinocrm' ), 500 );
		}

		$payload  = "--{$boundary}\r\n";
		$payload .= 'Content-Disposition: form-data; name="attachment"; filename="' . $name . "\"\r\n";
		$payload .= "Content-Type: application/zip\r\n\r\n";
		$payload .= $body . "\r\n";
		$payload .= "--{$boundary}--\r\n";

		return self::raw_request( 'POST', $url, $payload, "multipart/form-data; boundary={$boundary}" );
	}

	/**
	 * Download release source archive for a tag.
	 *
	 * @param string $owner Owner.
	 * @param string $repo  Repo.
	 * @param string $tag   Tag.
	 * @return array{ ok: bool, body?: string, message?: string, code?: int }
	 */
	public static function download_archive( $owner, $repo, $tag ) {
		$owner = sanitize_key( (string) $owner );
		$repo  = sanitize_key( (string) $repo );
		$tag   = sanitize_text_field( (string) $tag );

		self::$last_archive_tags_tried = array();
		$last                          = null;

		foreach ( self::archive_tag_candidates( $tag ) as $candidate ) {
			self::$last_archive_tags_tried[] = $candidate;
			$url                             = self::api_url(
				'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/archive/' . rawurlencode( $candidate ) . '.zip'
			);
			$result                          = self::raw_request( 'GET', $url, '', '', true );
			if ( ! empty( $result['ok'] ) && ! empty( $result['body'] ) ) {
				return $result;
			}
			$last = $result;
		}

		if ( is_array( $last ) ) {
			return $last;
		}

		return self::error( __( 'Could not download source archive from Gitea.', 'webinocrm' ), 502 );
	}

	/**
	 * Find first ZIP asset on a Gitea release.
	 *
	 * @param array<string,mixed> $release Release payload.
	 * @return array<string,mixed>|null
	 */
	public static function first_zip_asset( array $release ) {
		$assets = $release['assets'] ?? array();
		if ( ! is_array( $assets ) ) {
			return null;
		}
		$fallback = null;
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = strtolower( (string) ( $asset['name'] ?? '' ) );
			if ( str_ends_with( $name, '.zip' ) || false !== strpos( $name, '.zip' ) ) {
				return $asset;
			}
			if ( null === $fallback && ( ! empty( $asset['browser_download_url'] ) || ! empty( $asset['api_url'] ) ) ) {
				$fallback = $asset;
			}
		}
		return $fallback;
	}

	/**
	 * @param array<string,mixed> $asset Asset row from API.
	 * @return array{ ok: bool, body?: string, message?: string }
	 */
	public static function download_asset( array $asset ) {
		$url = (string) ( $asset['browser_download_url'] ?? '' );
		if ( '' === $url ) {
			$id = (int) ( $asset['id'] ?? 0 );
			if ( $id ) {
				$url = (string) ( $asset['api_url'] ?? '' );
			}
		}
		if ( '' === $url ) {
			return self::error( __( 'Asset URL missing.', 'webinocrm' ), 404 );
		}
		if ( str_starts_with( $url, '/' ) ) {
			$url = self::base_url() . $url;
		}
		return self::raw_request( 'GET', $url, '', '', true );
	}

	/**
	 * @param string              $method HTTP method.
	 * @param string              $path   API path starting with /.
	 * @param array<string,mixed> $body   JSON body.
	 * @param array<string,mixed> $query  Query args.
	 * @return array{ ok: bool, code: int, data: mixed, message: string }
	 */
	public static function request( $method, $path, array $body = array(), array $query = array() ) {
		if ( ! self::is_configured() ) {
			return self::error( __( 'Gitea is not configured.', 'webinocrm' ), 503 );
		}
		return self::request_with_config( self::resolve_config( array() ), $method, $path, $body, $query );
	}

	/**
	 * @param string $method  HTTP method.
	 * @param string $url     Full URL.
	 * @param string $body    Raw body.
	 * @param string $content_type Content-Type header.
	 * @param bool   $binary  Return raw body on success.
	 * @return array<string,mixed>
	 */
	private static function raw_request( $method, $url, $body = '', $content_type = '', $binary = false ) {
		if ( ! self::is_configured() ) {
			return self::error( __( 'Gitea is not configured.', 'webinocrm' ), 503 );
		}
		$config  = self::resolve_config( array() );
		$headers = self::headers_for_config( $config, false );
		if ( $content_type ) {
			$headers['Content-Type'] = $content_type;
		}
		$args = array(
			'method'  => strtoupper( (string) $method ),
			'timeout' => 120,
			'headers' => $headers,
		);
		if ( '' !== $body ) {
			$args['body'] = $body;
		}
		$response = self::remote_request_with_config( $config, $url, $args );
		if ( is_wp_error( $response ) ) {
			return self::error( $response->get_error_message(), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			$msg = self::extract_error_message( $raw );
			return self::error( $msg ?: __( 'Gitea request failed.', 'webinocrm' ), $code );
		}
		if ( $binary ) {
			return array(
				'ok'   => true,
				'body' => $raw,
				'code' => $code,
			);
		}
		return self::parse_response( $response );
	}

	/**
	 * @param string              $path  Path.
	 * @param array<string,mixed> $query Query.
	 * @return string
	 */
	private static function api_url( $path, array $query = array() ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$url  = self::base_url() . '/api/v1' . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		return $url;
	}

	/**
	 * @param bool $json Accept JSON.
	 * @return array<string,string>
	 */
	private static function headers( $json = true ) {
		$headers = array(
			'Authorization' => 'token ' . self::api_token(),
		);
		if ( $json ) {
			$headers['Accept']       = 'application/json';
			$headers['Content-Type'] = 'application/json';
		}
		return $headers;
	}

	/**
	 * @param string               $url  URL.
	 * @param array<string,mixed>  $args wp_remote_request args.
	 * @return array|\WP_Error
	 */
	private static function remote_request( $url, array $args ) {
		return self::remote_request_with_config( self::resolve_config( array() ), $url, $args );
	}

	/**
	 * @param array|\WP_Error $response Response.
	 * @return array{ ok: bool, code: int, data: mixed, message: string }
	 */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return self::error( $response->get_error_message(), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		if ( 204 === $code ) {
			return array(
				'ok'      => true,
				'code'    => $code,
				'data'    => null,
				'message' => '',
			);
		}
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = self::extract_error_message( $raw, $data );
			return self::error( $msg ?: __( 'Gitea request failed.', 'webinocrm' ), $code );
		}
		return array(
			'ok'      => true,
			'code'    => $code,
			'data'    => $data,
			'message' => '',
		);
	}

	/**
	 * @param string       $raw  Body.
	 * @param mixed        $data Decoded JSON.
	 * @return string
	 */
	private static function extract_error_message( $raw, $data = null ) {
		if ( is_array( $data ) ) {
			if ( ! empty( $data['message'] ) ) {
				return (string) $data['message'];
			}
			if ( ! empty( $data['error'] ) ) {
				return is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
			}
		}
		return strlen( $raw ) > 200 ? substr( $raw, 0, 200 ) . '…' : $raw;
	}

	/**
	 * @param array<string,mixed> $overrides Optional settings overrides.
	 * @return array{ base_url: string, org: string, api_token: string, ip_override: string, ip_scheme: string }
	 */
	private static function resolve_config( array $overrides = array() ) {
		$base = isset( $overrides['gitea_base_url'] ) && '' !== trim( (string) $overrides['gitea_base_url'] )
			? untrailingslashit( esc_url_raw( wp_unslash( (string) $overrides['gitea_base_url'] ) ) )
			: self::base_url();
		$org = isset( $overrides['gitea_org'] ) && '' !== trim( (string) $overrides['gitea_org'] )
			? sanitize_key( wp_unslash( (string) $overrides['gitea_org'] ) )
			: self::org();
		$token = isset( $overrides['gitea_api_token'] ) && '' !== trim( (string) $overrides['gitea_api_token'] )
			? trim( wp_unslash( (string) $overrides['gitea_api_token'] ) )
			: self::api_token();
		$ip = array_key_exists( 'gitea_ip_override', $overrides )
			? trim( wp_unslash( (string) $overrides['gitea_ip_override'] ) )
			: self::ip_override();
		$ip_scheme = array_key_exists( 'gitea_ip_scheme', $overrides )
			? sanitize_key( wp_unslash( (string) $overrides['gitea_ip_scheme'] ) )
			: self::ip_scheme();
		if ( ! in_array( $ip_scheme, array( 'auto', 'http', 'https' ), true ) ) {
			$ip_scheme = 'auto';
		}
		return array(
			'base_url'    => $base,
			'org'         => $org,
			'api_token'   => $token,
			'ip_override' => $ip,
			'ip_scheme'   => $ip_scheme,
		);
	}

	/**
	 * @param array<string,mixed> $config  Resolved config.
	 * @param string              $method  HTTP method.
	 * @param string              $path    API path.
	 * @param array<string,mixed> $body    JSON body.
	 * @param array<string,mixed> $query   Query args.
	 * @return array{ ok: bool, code: int, data: mixed, message: string }
	 */
	private static function request_with_config( array $config, $method, $path, array $body = array(), array $query = array() ) {
		if ( '' === $config['base_url'] ) {
			return self::error( __( 'Gitea is not configured.', 'webinocrm' ), 503 );
		}
		$url  = self::api_url_for_config( $config, $path, $query );
		$args = array(
			'method'  => strtoupper( (string) $method ),
			'timeout' => 60,
			'headers' => self::headers_for_config( $config, true ),
		);
		if ( in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ), true ) && $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = self::remote_request_with_config( $config, $url, $args );
		return self::parse_response( $response );
	}

	/**
	 * @param array<string,mixed> $config Resolved config.
	 * @param string              $path   Path.
	 * @param array<string,mixed> $query  Query.
	 * @return string
	 */
	private static function api_url_for_config( array $config, $path, array $query = array() ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$url  = $config['base_url'] . '/api/v1' . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		return $url;
	}

	/**
	 * @param array<string,mixed> $config Resolved config.
	 * @param bool                $json   JSON headers.
	 * @return array<string,string>
	 */
	private static function headers_for_config( array $config, $json = true ) {
		$headers = array(
			'Authorization' => 'token ' . $config['api_token'],
		);
		if ( $json ) {
			$headers['Accept']       = 'application/json';
			$headers['Content-Type'] = 'application/json';
		}
		return $headers;
	}

	/**
	 * @param array<string,mixed> $config Resolved config.
	 * @param string              $url    URL.
	 * @param array<string,mixed> $args   Request args.
	 * @return array|\WP_Error
	 */
	private static function remote_request_with_config( array $config, $url, array $args ) {
		$response = self::execute_remote_request_with_config( $config, $url, $args, null );
		if (
			self::is_ssl_version_error_response( $response )
			&& '' !== trim( (string) ( $config['ip_override'] ?? '' ) )
			&& in_array( (string) ( $config['ip_scheme'] ?? 'auto' ), array( 'auto', 'https' ), true )
		) {
			$response = self::execute_remote_request_with_config( $config, $url, $args, 'http' );
		}
		return $response;
	}

	/**
	 * @param array<string,mixed> $config       Resolved config.
	 * @param string              $url          Request URL.
	 * @param array<string,mixed> $args         Request args.
	 * @param string|null         $force_scheme Force ip scheme.
	 * @return array|\WP_Error
	 */
	private static function execute_remote_request_with_config( array $config, $url, array $args, $force_scheme ) {
		$rewrite = self::rewrite_url_for_ip_override( $url, $config, $force_scheme );
		if ( '' !== $rewrite['host_header'] ) {
			$url = (string) $rewrite['url'];
			if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
				$args['headers'] = array();
			}
			$args['headers']['Host'] = (string) $rewrite['host_header'];
			$args['sslverify']       = (bool) $rewrite['sslverify'];
		}
		return wp_remote_request( $url, $args );
	}

	/**
	 * @param string              $url          URL.
	 * @param array<string,mixed> $config       Config.
	 * @param string|null         $force_scheme Optional forced scheme.
	 * @return array{ url: string, host_header: string, scheme: string, sslverify: bool }
	 */
	private static function rewrite_url_for_ip_override( $url, array $config, $force_scheme = null ) {
		$ip = trim( (string) ( $config['ip_override'] ?? '' ) );
		if ( '' === $ip || ! preg_match( '#^https?://#i', (string) $url ) ) {
			return array(
				'url'         => (string) $url,
				'host_header' => '',
				'scheme'      => '',
				'sslverify'   => true,
			);
		}
		$parsed = wp_parse_url( (string) $url );
		$host   = isset( $parsed['host'] ) ? (string) $parsed['host'] : '';
		if ( '' === $host ) {
			return array(
				'url'         => (string) $url,
				'host_header' => '',
				'scheme'      => '',
				'sslverify'   => true,
			);
		}
		$setting = (string) ( $config['ip_scheme'] ?? 'auto' );
		if ( null !== $force_scheme ) {
			$scheme = (string) $force_scheme;
		} elseif ( 'http' === $setting ) {
			$scheme = 'http';
		} elseif ( 'https' === $setting ) {
			$scheme = 'https';
		} else {
			$scheme = isset( $parsed['scheme'] ) ? (string) $parsed['scheme'] : 'https';
		}
		$port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
		$path     = ( isset( $parsed['path'] ) ? $parsed['path'] : '' ) . ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
		$new_url  = $scheme . '://' . $ip . $port . $path;
		$sslverify = ( 'http' === $scheme )
			? false
			: (bool) apply_filters( 'webinocrm_gitea_sslverify', true, $host, $ip );
		return array(
			'url'         => $new_url,
			'host_header' => $host,
			'scheme'      => $scheme,
			'sslverify'   => $sslverify,
		);
	}

	/**
	 * @param array|\WP_Error $response Response.
	 * @return bool
	 */
	private static function is_ssl_version_error_response( $response ) {
		if ( ! is_wp_error( $response ) ) {
			return false;
		}
		return self::is_ssl_version_error_message( $response->get_error_message() );
	}

	/**
	 * @param string $message Error message.
	 * @return bool
	 */
	private static function is_ssl_version_error_message( $message ) {
		$message = strtolower( (string) $message );
		return str_contains( $message, 'wrong version number' )
			|| str_contains( $message, 'ssl routines' )
			|| str_contains( $message, 'curl error 35' );
	}

	/**
	 * @param array<string,mixed> $config   Config.
	 * @param string              $key      Step key.
	 * @param string              $label    Label.
	 * @param string              $method   HTTP method.
	 * @param string              $path     API path.
	 * @param bool                $with_auth Send auth header.
	 * @param array<string,mixed> $query    Query args.
	 * @return array<string,mixed>
	 */
	private static function diagnostic_probe( array $config, $key, $label, $method, $path, $with_auth, array $query = array() ) {
		$started = microtime( true );
		$url     = self::api_url_for_config( $config, $path, $query );
		$rewrite = self::rewrite_url_for_ip_override( $url, $config );
		$headers = $with_auth
			? self::headers_for_config( $config, true )
			: array( 'Accept' => 'application/json' );
		$args    = array(
			'method'  => strtoupper( (string) $method ),
			'timeout' => 30,
			'headers' => $headers,
		);
		$response = self::remote_request_with_config( $config, $url, $args );
		$ms       = (int) round( ( microtime( true ) - $started ) * 1000 );
		$curl_err = '';
		$http     = 0;
		$message  = '';
		$hint     = '';
		$ok       = false;

		if ( is_wp_error( $response ) ) {
			$curl_err = $response->get_error_message();
			$message  = $curl_err;
			$hint     = self::is_ssl_version_error_message( $curl_err ) ? 'ssl_wrong_version' : 'network_error';
		} else {
			$http = (int) wp_remote_retrieve_response_code( $response );
			$raw  = (string) wp_remote_retrieve_body( $response );
			$data = json_decode( $raw, true );
			$ok   = $http >= 200 && $http < 300;
			if ( ! $ok ) {
				$message = self::extract_error_message( $raw, $data );
				if ( 401 === $http ) {
					$hint = 'unauthorized';
				} elseif ( 404 === $http ) {
					$hint = 'not_found';
				} else {
					$hint = 'http_error';
				}
			} else {
				$hint = 'ok';
			}
		}

		return array(
			'key'        => (string) $key,
			'label'      => (string) $label,
			'url'        => (string) $rewrite['url'],
			'http'       => $http,
			'ok'         => $ok,
			'hint'       => $hint,
			'message'    => $message,
			'ms'         => $ms,
			'curl_error' => $curl_err,
		);
	}

	/**
	 * @param string $message Message.
	 * @param int    $code    HTTP code.
	 * @return array{ ok: bool, code: int, data: null, message: string }
	 */
	private static function error( $message, $code = 400 ) {
		return array(
			'ok'      => false,
			'code'    => (int) $code,
			'data'    => null,
			'message' => (string) $message,
		);
	}
}
