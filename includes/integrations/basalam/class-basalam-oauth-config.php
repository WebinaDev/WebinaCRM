<?php
/**
 * Basalam OAuth client config — stored only in CRM option (admin UI).
 * Never ship secrets to merchants.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Basalam_OAuth_Config {

	const OPTION_KEY = 'webinocrm_basalam_oauth';

	const DEFAULT_CLIENT_ID    = '2357';
	const DEFAULT_REDIRECT_URI = 'https://webina.dev/api/basalam/oauth/callback';
	const TOKEN_URL            = 'https://auth.basalam.com/oauth/token';
	const SSO_URL              = 'https://basalam.com/accounts/sso';

	/**
	 * Default vendor scopes (parity with sync-basalam / WebinoBasalam).
	 */
	const DEFAULT_SCOPES = 'vendor.product.write vendor.product.read vendor.parcel.write vendor.parcel.read vendor.profile.read vendor.profile.write customer.profile.read customer.profile.write customer.order.read customer.order.write customer.chat.read customer.chat.write customer.wallet.read customer.wallet.write order-processing customer.identity.read';

	/**
	 * One-shot: import legacy credentials.local.php into option if option is empty.
	 *
	 * @return void
	 */
	public static function maybe_migrate_local_file() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( is_array( $stored ) && ! empty( $stored['client_secret'] ) ) {
			return;
		}

		$file = __DIR__ . '/credentials.local.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$data = include $file;
		if ( ! is_array( $data ) || empty( $data['client_secret'] ) ) {
			return;
		}

		self::save(
			array(
				'client_id'     => (string) ( $data['client_id'] ?? self::DEFAULT_CLIENT_ID ),
				'client_secret' => (string) $data['client_secret'],
				'redirect_uri'  => (string) ( $data['redirect_uri'] ?? self::DEFAULT_REDIRECT_URI ),
				'scopes'        => self::DEFAULT_SCOPES,
			)
		);
	}

	/**
	 * @return array{client_id:string,client_secret:string,redirect_uri:string,scopes:string}
	 */
	public static function get() {
		self::maybe_migrate_local_file();

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$client_id    = sanitize_text_field( (string) ( $stored['client_id'] ?? '' ) );
		$client_secret = (string) ( $stored['client_secret'] ?? '' );
		$redirect_uri = esc_url_raw( (string) ( $stored['redirect_uri'] ?? '' ) );
		$scopes       = sanitize_text_field( (string) ( $stored['scopes'] ?? '' ) );

		return array(
			'client_id'     => '' !== $client_id ? $client_id : self::DEFAULT_CLIENT_ID,
			'client_secret' => $client_secret,
			'redirect_uri'  => '' !== $redirect_uri ? $redirect_uri : self::DEFAULT_REDIRECT_URI,
			'scopes'        => '' !== $scopes ? $scopes : self::DEFAULT_SCOPES,
		);
	}

	/**
	 * Persist from CRM admin UI. Secret only written when non-empty and not masked.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return array{client_id:string,client_secret:string,redirect_uri:string,scopes:string}
	 */
	public static function save( array $data ) {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$secret = isset( $data['client_secret'] ) ? (string) $data['client_secret'] : '';
		if ( '' === $secret || '***' === $secret ) {
			$secret = (string) ( $stored['client_secret'] ?? '' );
		}

		$redirect = isset( $data['redirect_uri'] ) ? esc_url_raw( (string) $data['redirect_uri'] ) : '';
		if ( '' === $redirect ) {
			$redirect = self::DEFAULT_REDIRECT_URI;
		}

		$new = array(
			'client_id'     => sanitize_text_field( (string) ( $data['client_id'] ?? ( $stored['client_id'] ?? self::DEFAULT_CLIENT_ID ) ) ),
			'client_secret' => sanitize_text_field( $secret ),
			'redirect_uri'  => $redirect,
			'scopes'        => sanitize_text_field( (string) ( $data['scopes'] ?? ( $stored['scopes'] ?? self::DEFAULT_SCOPES ) ) ),
		);
		if ( '' === $new['client_id'] ) {
			$new['client_id'] = self::DEFAULT_CLIENT_ID;
		}
		if ( '' === $new['scopes'] ) {
			$new['scopes'] = self::DEFAULT_SCOPES;
		}

		update_option( self::OPTION_KEY, $new, false );
		return self::get();
	}

	/**
	 * Public status for CRM admin (secret masked).
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		$cfg = self::get();
		return array(
			'configured'    => '' !== $cfg['client_secret'],
			'client_id'     => $cfg['client_id'],
			'client_secret' => '' !== $cfg['client_secret'] ? '***' : '',
			'redirect_uri'  => $cfg['redirect_uri'],
			'scopes'        => $cfg['scopes'],
			'token_url'     => self::TOKEN_URL,
			'sso_url'       => self::SSO_URL,
			'callback_path' => '/api/basalam/oauth/callback',
		);
	}

	/**
	 * @return bool
	 */
	public static function is_ready() {
		$cfg = self::get();
		return '' !== $cfg['client_id'] && '' !== $cfg['client_secret'] && '' !== $cfg['redirect_uri'];
	}
}
