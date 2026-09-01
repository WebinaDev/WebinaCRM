<?php
/**
 * Encrypt / decrypt AI API keys at rest.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenSSL helpers using WordPress AUTH_KEY.
 */
final class WebinoCRM_AI_Content_Crypto {

	/**
	 * @return string
	 */
	private static function key_material() {
		$salt = defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : 'webino-ai-fallback';
		return hash( 'sha256', $salt . '|webino-ai-content', true );
	}

	/**
	 * @param string $plain Plaintext.
	 * @return string Base64 payload or empty.
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$iv  = random_bytes( 16 );
		$raw = openssl_encrypt( $plain, 'AES-256-CBC', self::key_material(), OPENSSL_RAW_DATA, $iv );
		if ( false === $raw ) {
			return '';
		}
		return base64_encode( $iv . $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @param string $payload Encrypted payload.
	 * @return string
	 */
	public static function decrypt( $payload ) {
		$payload = (string) $payload;
		if ( '' === $payload ) {
			return '';
		}
		$bin = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $bin || strlen( $bin ) < 17 ) {
			return '';
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $bin;
		}
		$iv  = substr( $bin, 0, 16 );
		$raw = substr( $bin, 16 );
		$out = openssl_decrypt( $raw, 'AES-256-CBC', self::key_material(), OPENSSL_RAW_DATA, $iv );
		return false === $out ? '' : $out;
	}

	/**
	 * @param string $key API key.
	 * @return string Masked key for REST.
	 */
	public static function mask( $key ) {
		$key = (string) $key;
		$len = strlen( $key );
		if ( $len <= 8 ) {
			return $len > 0 ? str_repeat( '*', $len ) : '';
		}
		return substr( $key, 0, 4 ) . str_repeat( '*', max( 4, $len - 8 ) ) . substr( $key, -4 );
	}
}
