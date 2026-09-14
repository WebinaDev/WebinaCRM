<?php
/**
 * Basalam OAuth proxy — Webina owns client secret; merchants never see it.
 *
 * Flow:
 * 1) Merchant Dashboard POST /webinocrm/v1/basalam/oauth/start
 * 2) User SSO on basalam.com → redirect to https://webina.dev/api/basalam/oauth/callback
 * 3) Exchange code at auth.basalam.com → handoff to merchant save-token page
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Basalam_OAuth_Proxy {

	const SESSION_PREFIX = 'webinocrm_basalam_oauth_';
	const SESSION_TTL    = 600;
	const HANDOFF_TTL    = 180;
	const CALLBACK_PATH  = '/api/basalam/oauth/callback';

	/**
	 * @return void
	 */
	public static function init() {
		require_once __DIR__ . '/class-basalam-oauth-config.php';
		require_once __DIR__ . '/class-basalam-connections.php';
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_callback' ), 0 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	/**
	 * @return void
	 */
	public static function register_rewrite() {
		add_rewrite_rule(
			'^api/basalam/oauth/callback/?$',
			'index.php?webinocrm_basalam_oauth_callback=1',
			'top'
		);
		if ( '1' !== (string) get_option( 'webinocrm_basalam_oauth_rewrite_flushed', '' ) ) {
			flush_rewrite_rules( false );
			update_option( 'webinocrm_basalam_oauth_rewrite_flushed', '1', false );
		}
	}

	/**
	 * @param array<int,string> $vars Query vars.
	 * @return array<int,string>
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'webinocrm_basalam_oauth_callback';
		return $vars;
	}

	/**
	 * Handle https://webina.dev/api/basalam/oauth/callback?code&state
	 *
	 * @return void
	 */
	public static function maybe_handle_callback() {
		if ( is_admin() ) {
			return;
		}
		if ( empty( $_GET['code'] ) || empty( $_GET['state'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path        = untrailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
		$flag        = (string) get_query_var( 'webinocrm_basalam_oauth_callback' );

		if ( '1' !== $flag && self::CALLBACK_PATH !== $path ) {
			return;
		}

		$code  = sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = self::complete_authorization( $code, $state );
		if ( is_wp_error( $result ) ) {
			status_header( 400 );
			nocache_headers();
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Basalam OAuth', 'webinocrm' ),
				array( 'response' => 400 )
			);
		}

		nocache_headers();
		self::redirect_to_merchant( (string) $result );
		exit;
	}

	/**
	 * Cross-site handoff redirect. wp_safe_redirect() only allows same-host URLs and
	 * falls back to webina.dev/wp-admin — which is exactly the bug we hit.
	 *
	 * @param string $url Absolute merchant URL from complete_authorization().
	 * @return void
	 */
	private static function redirect_to_merchant( $url ) {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			wp_die(
				esc_html__( 'Invalid merchant redirect URL.', 'webinocrm' ),
				esc_html__( 'Basalam OAuth', 'webinocrm' ),
				array( 'response' => 400 )
			);
		}

		$target_host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $target_host ) {
			wp_die(
				esc_html__( 'Invalid merchant redirect host.', 'webinocrm' ),
				esc_html__( 'Basalam OAuth', 'webinocrm' ),
				array( 'response' => 400 )
			);
		}

		$allow = static function ( $hosts ) use ( $target_host ) {
			$hosts   = is_array( $hosts ) ? $hosts : array();
			$hosts[] = $target_host;
			return array_values( array_unique( $hosts ) );
		};
		add_filter( 'allowed_redirect_hosts', $allow, 99 );
		$safe = wp_validate_redirect( $url, false );
		remove_filter( 'allowed_redirect_hosts', $allow, 99 );

		if ( ! $safe ) {
			wp_die(
				esc_html__( 'Merchant redirect was rejected.', 'webinocrm' ),
				esc_html__( 'Basalam OAuth', 'webinocrm' ),
				array( 'response' => 400 )
			);
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Host validated against OAuth session site_url via wp_validate_redirect + allowed_redirect_hosts.
		wp_redirect( $safe, 302 );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'webinocrm/v1',
			'/basalam/oauth/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_start' ),
				'permission_callback' => array( __CLASS__, 'public_rate_limit' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/basalam/oauth/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_refresh' ),
				'permission_callback' => array( __CLASS__, 'public_rate_limit' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/basalam/oauth/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_status' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/basalam/oauth/config',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_config_save' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/basalam/connections',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_connections_list' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/basalam/connections/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_connections_disconnect' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/basalam/oauth/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_merchant_disconnect' ),
				'permission_callback' => array( __CLASS__, 'public_rate_limit' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function public_rate_limit( $request ) {
		if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'basalam_oauth', 120, 60 ) ) {
			return new WP_Error( 'webinocrm_rate_limit', __( 'Too many requests.', 'webinocrm' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_status( $request ) {
		return new WP_REST_Response( WebinoCRM_Basalam_OAuth_Config::status() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_config_save( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$saved = WebinoCRM_Basalam_OAuth_Config::save( is_array( $params ) ? $params : array() );
		return new WP_REST_Response(
			array(
				'ok'     => true,
				'status' => WebinoCRM_Basalam_OAuth_Config::status(),
			)
		);
	}

	/**
	 * Admin: list registered merchant Basalam connections.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_connections_list( $request ) {
		return new WP_REST_Response(
			array(
				'connections' => WebinoCRM_Basalam_Connections::all(),
			)
		);
	}

	/**
	 * Admin: mark a site disconnected in the registry (does not clear remote WP tokens).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_connections_disconnect( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$site_url = isset( $params['site_url'] ) ? esc_url_raw( (string) $params['site_url'] ) : '';
		if ( '' === $site_url || ! wp_http_validate_url( $site_url ) ) {
			return new WP_Error( 'basalam_site', __( 'Valid site_url is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		WebinoCRM_Basalam_Connections::mark_disconnected( $site_url );
		return new WP_REST_Response(
			array(
				'ok'          => true,
				'connections' => WebinoCRM_Basalam_Connections::all(),
			)
		);
	}

	/**
	 * Merchant ping after local disconnect.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_merchant_disconnect( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$site_url = isset( $params['site_url'] ) ? esc_url_raw( (string) $params['site_url'] ) : '';
		if ( '' === $site_url || ! wp_http_validate_url( $site_url ) ) {
			return new WP_Error( 'basalam_site', __( 'Valid site_url is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		WebinoCRM_Basalam_Connections::mark_disconnected( $site_url );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_start( $request ) {
		if ( ! WebinoCRM_Basalam_OAuth_Config::is_ready() ) {
			return new WP_Error(
				'basalam_oauth_not_configured',
				__( 'Basalam OAuth is not configured on WebinaCRM (missing client secret).', 'webinocrm' ),
				array( 'status' => 503 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$site_url   = isset( $params['site_url'] ) ? esc_url_raw( (string) $params['site_url'] ) : '';
		$return_url = isset( $params['return_url'] ) ? esc_url_raw( (string) $params['return_url'] ) : '';

		if ( '' === $site_url || ! wp_http_validate_url( $site_url ) ) {
			return new WP_Error( 'basalam_oauth_site', __( 'Valid site_url is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		// return_url must stay on the same merchant host (prevent open redirect via CRM).
		if ( '' !== $return_url ) {
			$site_host   = (string) wp_parse_url( $site_url, PHP_URL_HOST );
			$return_host = (string) wp_parse_url( $return_url, PHP_URL_HOST );
			if ( '' === $return_host || 0 !== strcasecmp( $site_host, $return_host ) || ! wp_http_validate_url( $return_url ) ) {
				$return_url = '';
			}
		}

		$state   = wp_generate_password( 48, false, false );
		$session = array(
			'site_url'   => untrailingslashit( $site_url ),
			'return_url' => $return_url,
			'created'    => time(),
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '',
			'handoff_key'=> wp_generate_password( 32, false, false ),
		);
		set_transient( self::SESSION_PREFIX . $state, $session, self::SESSION_TTL );

		$cfg = WebinoCRM_Basalam_OAuth_Config::get();
		$sso = WebinoCRM_Basalam_OAuth_Config::SSO_URL
			. '?client_id=' . rawurlencode( $cfg['client_id'] )
			. '&scope=' . rawurlencode( $cfg['scopes'] )
			. '&redirect_uri=' . rawurlencode( $cfg['redirect_uri'] )
			. '&state=' . rawurlencode( $state );

		return new WP_REST_Response(
			array(
				'url'          => $sso,
				'state'        => $state,
				'redirect_uri' => $cfg['redirect_uri'],
				'client_id'    => $cfg['client_id'],
				'expires_in'   => self::SESSION_TTL,
			)
		);
	}

	/**
	 * Refresh vendor access token via CRM (keeps client_secret server-side).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_refresh( $request ) {
		if ( ! WebinoCRM_Basalam_OAuth_Config::is_ready() ) {
			return new WP_Error( 'basalam_oauth_not_configured', __( 'Basalam OAuth is not configured.', 'webinocrm' ), array( 'status' => 503 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$refresh = isset( $params['refresh_token'] ) ? sanitize_text_field( (string) $params['refresh_token'] ) : '';
		if ( '' === $refresh ) {
			return new WP_Error( 'basalam_oauth_refresh', __( 'refresh_token is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$cfg  = WebinoCRM_Basalam_OAuth_Config::get();
		$body = array(
			'grant_type'    => 'refresh_token',
			'client_id'     => $cfg['client_id'],
			'client_secret' => $cfg['client_secret'],
			'refresh_token' => $refresh,
			'redirect_uri'  => $cfg['redirect_uri'],
		);

		$token = self::exchange_token( $body );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$site_url = isset( $params['site_url'] ) ? esc_url_raw( (string) $params['site_url'] ) : '';
		if ( '' !== $site_url && wp_http_validate_url( $site_url ) ) {
			$vendor_hint = isset( $params['vendor_id'] ) ? absint( $params['vendor_id'] ) : 0;
			if ( $vendor_hint > 0 ) {
				WebinoCRM_Basalam_Connections::upsert( $site_url, $vendor_hint, 'connected' );
			} else {
				// Touch last_seen without inventing a vendor id.
				foreach ( WebinoCRM_Basalam_Connections::all() as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					if ( 0 === strcasecmp( untrailingslashit( (string) ( $row['site_url'] ?? '' ) ), untrailingslashit( $site_url ) ) ) {
						WebinoCRM_Basalam_Connections::upsert( $site_url, absint( $row['vendor_id'] ?? 0 ), 'connected' );
						break;
					}
				}
			}
		}

		return new WP_REST_Response(
			array(
				'access_token'  => $token['access_token'] ?? '',
				'refresh_token' => $token['refresh_token'] ?? $refresh,
				'expires_in'    => isset( $token['expires_in'] ) ? (int) $token['expires_in'] : 0,
				'token_type'    => $token['token_type'] ?? 'Bearer',
			)
		);
	}

	/**
	 * @param string $code  Authorization code.
	 * @param string $state Session state.
	 * @return string|WP_Error Redirect URL to merchant.
	 */
	public static function complete_authorization( $code, $state ) {
		if ( ! WebinoCRM_Basalam_OAuth_Config::is_ready() ) {
			return new WP_Error( 'basalam_oauth_not_configured', __( 'Basalam OAuth is not configured.', 'webinocrm' ) );
		}

		$key     = self::SESSION_PREFIX . $state;
		$session = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $session ) || empty( $session['site_url'] ) ) {
			return new WP_Error( 'basalam_oauth_state', __( 'OAuth session expired or invalid. Start connection again from your store dashboard.', 'webinocrm' ) );
		}

		$cfg  = WebinoCRM_Basalam_OAuth_Config::get();
		$body = array(
			'grant_type'    => 'authorization_code',
			'client_id'     => $cfg['client_id'],
			'client_secret' => $cfg['client_secret'],
			'redirect_uri'  => $cfg['redirect_uri'],
			'code'          => $code,
		);

		$token = self::exchange_token( $body );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$access  = (string) ( $token['access_token'] ?? '' );
		$refresh = (string) ( $token['refresh_token'] ?? '' );
		$expires = isset( $token['expires_in'] ) ? (int) $token['expires_in'] : 0;

		if ( '' === $access ) {
			return new WP_Error( 'basalam_oauth_token', __( 'Basalam did not return an access token.', 'webinocrm' ) );
		}

		$vendor_id = self::resolve_vendor_id( $access );
		$site      = untrailingslashit( (string) $session['site_url'] );

		if ( $vendor_id < 1 ) {
			$err = add_query_arg(
				array(
					'oauth'  => 'error',
					'reason' => 'vendor',
				),
				$site . '/dashboard/settings/shop/basalam/'
			);
			$session_host = (string) wp_parse_url( $site, PHP_URL_HOST );
			$err_host     = (string) wp_parse_url( $err, PHP_URL_HOST );
			if ( '' === $session_host || 0 !== strcasecmp( $session_host, $err_host ) ) {
				return new WP_Error(
					'basalam_oauth_vendor',
					__( 'Could not resolve Basalam vendor id for this account. Reconnect from a vendor booth account.', 'webinocrm' )
				);
			}
			return $err;
		}

		WebinoCRM_Basalam_Connections::upsert( $site, $vendor_id, 'connected' );

		$ts          = (string) time();
		$handoff_key = (string) ( $session['handoff_key'] ?? '' );
		// Merchant-verifiable HMAC (no client_secret on store). Bound to one-time handoff_key from CRM session.
		$sig_payload = $access . '|' . $refresh . '|' . $ts . '|' . (string) $vendor_id . '|' . untrailingslashit( (string) $session['site_url'] );
		$sig         = hash_hmac( 'sha256', $sig_payload, $handoff_key );

		// Short-lived handoff record so merchant can optionally verify via CRM (future).
		set_transient(
			self::SESSION_PREFIX . 'handoff_' . substr( $sig, 0, 32 ),
			array(
				'site_url'   => $session['site_url'],
				'return_url' => $session['return_url'] ?? '',
				'created'    => time(),
			),
			self::HANDOFF_TTL
		);

		$args = array(
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => (string) $expires,
			'vendor_id'     => (string) $vendor_id,
			'is_vendor'     => 'true',
			'webino_sig'    => $sig,
			'webino_ts'     => $ts,
			'webino_hk'     => $handoff_key,
			'oauth'         => 'handoff',
		);
		if ( ! empty( $session['return_url'] ) ) {
			// Never propagate a fake "already connected" flag in return_url.
			$ru = remove_query_arg( array( 'oauth', 'access_token', 'refresh_token', 'vendor_id', 'webino_sig', 'webino_ts', 'webino_hk' ), (string) $session['return_url'] );
			$args['return_url'] = $ru;
		}

		// Prefer customer Dashboard (SPA) so users never land in merchant wp-admin.
		$handoff_base = $site . '/dashboard/settings/shop/basalam/';
		$handoff      = add_query_arg( $args, $handoff_base );

		// Final host must match the site_url from the OAuth start session.
		$session_host  = (string) wp_parse_url( $site, PHP_URL_HOST );
		$handoff_host  = (string) wp_parse_url( $handoff, PHP_URL_HOST );
		if ( '' === $session_host || 0 !== strcasecmp( $session_host, $handoff_host ) ) {
			return new WP_Error( 'basalam_oauth_host', __( 'Merchant handoff host mismatch.', 'webinocrm' ) );
		}

		return $handoff;
	}

	/**
	 * @param array<string,mixed> $body Token request body.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function exchange_token( array $body ) {
		$response = wp_remote_post(
			WebinoCRM_Basalam_OAuth_Config::TOKEN_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) {
			$message = isset( $data['error_description'] )
				? (string) $data['error_description']
				: ( isset( $data['message'] ) ? (string) $data['message'] : __( 'Token exchange failed.', 'webinocrm' ) );
			return new WP_Error( 'basalam_oauth_exchange', $message, array( 'status' => $code > 0 ? $code : 502, 'body' => $data ) );
		}

		return $data;
	}

	/**
	 * Resolve booth vendor_id from OpenAPI. Returns 0 when unknown.
	 *
	 * Prefer GET /v1/users/me → vendor.id (Basalam docs + WNC adapter).
	 * Never treat users/me top-level user id as vendor_id.
	 *
	 * @param string $access_token Access token.
	 * @return int
	 */
	private static function resolve_vendor_id( $access_token ) {
		$response = wp_remote_get(
			'https://openapi.basalam.com/v1/users/me',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Accept'        => 'application/json',
					'user-agent'    => 'WebinaCRM-Basalam-OAuth',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WebinaCRM Basalam OAuth] users/me request failed: ' . $response->get_error_message() );
			}
			return 0;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WebinaCRM Basalam OAuth] users/me HTTP ' . $code . ' (no usable body)' );
			}
			return 0;
		}

		$id = self::extract_vendor_id_from_users_me( $data );
		if ( $id > 0 ) {
			return $id;
		}

		$has_vendor_key = array_key_exists( 'vendor', $data ) || ( isset( $data['data'] ) && is_array( $data['data'] ) && array_key_exists( 'vendor', $data['data'] ) );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				'[WebinaCRM Basalam OAuth] vendor.id missing on users/me; vendor_key=' . ( $has_vendor_key ? '1' : '0' )
			);
		}

		return 0;
	}

	/**
	 * Extract booth id from /v1/users/me payload. Prefer nested vendor.id; never use user id.
	 *
	 * @param array<string,mixed> $data API payload.
	 * @return int
	 */
	private static function extract_vendor_id_from_users_me( array $data ) {
		if ( ! empty( $data['vendor']['id'] ) && is_numeric( $data['vendor']['id'] ) ) {
			return (int) $data['vendor']['id'];
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			if ( ! empty( $data['data']['vendor']['id'] ) && is_numeric( $data['data']['vendor']['id'] ) ) {
				return (int) $data['data']['vendor']['id'];
			}
			if ( ! empty( $data['data']['vendor_id'] ) && is_numeric( $data['data']['vendor_id'] ) ) {
				return (int) $data['data']['vendor_id'];
			}
		}
		if ( ! empty( $data['vendor_id'] ) && is_numeric( $data['vendor_id'] ) ) {
			return (int) $data['vendor_id'];
		}
		// Pure vendor object (rare): only accept top-level id when nested vendor is absent
		// and a vendor-ish title/name field is present — still prefer never using bare user objects.
		if ( isset( $data['vendor'] ) && null === $data['vendor'] ) {
			return 0;
		}
		return 0;
	}
}
