<?php
/**
 * Moadian TSP API client (v2 self-tsp).
 *
 * Uses OpenSSL for RSA signatures. Full JWE packaging is best-effort;
 * payload structure follows INTA invoice JSON.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Low-level Moadian HTTP + signing helpers.
 */
final class WebinoCRM_Accounting_Moadian_Client {

	/**
	 * @return string
	 */
	public static function base_url() {
		$sandbox = ! empty( WebinoCRM_Accounting_Moadian_Config::get()['moadian_sandbox'] );
		// Official production; sandbox flag kept for future TSP sandbox hosts.
		return $sandbox
			? 'https://tp.tax.gov.ir/req/api/self-tsp/'
			: 'https://tp.tax.gov.ir/req/api/self-tsp/';
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_server_information() {
		return self::request( 'sync/GET_SERVER_INFORMATION', array(), false );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_fiscal_information() {
		$fiscal = (string) WebinoCRM_Accounting_Moadian_Config::get()['fiscal_id'];
		if ( '' === $fiscal ) {
			return new WP_Error( 'acc_moadian_fiscal', __( 'Fiscal memory ID is not configured.', 'webinocrm' ) );
		}
		return self::request(
			'sync/GET_FISCAL_INFORMATION',
			array( 'fiscalId' => $fiscal ),
			true
		);
	}

	/**
	 * Send invoice packet(s).
	 *
	 * @param array<int,array<string,mixed>> $packets Packets.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function send_invoices( array $packets ) {
		return self::request(
			'async/normal-enqueue',
			array( 'packets' => $packets ),
			true
		);
	}

	/**
	 * @param string $uid UID.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function inquiry_by_uid( $uid ) {
		return self::request(
			'sync/INQUIRY_BY_UID',
			array( 'uidList' => array( (string) $uid ) ),
			true
		);
	}

	/**
	 * @param string $ref Reference number.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function inquiry_by_reference( $ref ) {
		return self::request(
			'sync/INQUIRY_BY_REFERENCE_NUMBER',
			array( 'referenceNumber' => array( (string) $ref ) ),
			true
		);
	}

	/**
	 * Build a signed packet wrapper for an invoice body.
	 *
	 * @param array<string,mixed> $invoice_body Invoice JSON (header+body+payments).
	 * @param string              $uid          Packet UID.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function wrap_packet( array $invoice_body, $uid ) {
		$fiscal = (string) WebinoCRM_Accounting_Moadian_Config::get()['fiscal_id'];
		$payload = wp_json_encode( $invoice_body );
		$sig     = self::sign_payload( (string) $payload );
		if ( is_wp_error( $sig ) ) {
			return $sig;
		}
		return array(
			'uid'           => (string) $uid,
			'packetType'    => 'INVOICE.V01',
			'retry'         => false,
			'data'          => array(
				'payload'   => base64_encode( (string) $payload ),
				'signature' => $sig,
			),
			'encryptionKeyId' => '',
			'symmetricKey'    => '',
			'iv'              => '',
			'fiscalId'        => $fiscal,
			'dataSignature'   => $sig,
		);
	}

	/**
	 * RSA-SHA256 sign (base64).
	 *
	 * @param string $payload Payload.
	 * @return string|WP_Error
	 */
	public static function sign_payload( $payload ) {
		$key_pem = WebinoCRM_Accounting_Moadian_Config::private_key_pem();
		if ( '' === $key_pem ) {
			return new WP_Error( 'acc_moadian_key', __( 'Private key is not configured.', 'webinocrm' ) );
		}
		$key = openssl_pkey_get_private( $key_pem );
		if ( ! $key ) {
			return new WP_Error( 'acc_moadian_key_invalid', __( 'Invalid private key PEM.', 'webinocrm' ) );
		}
		$signature = '';
		$ok        = openssl_sign( $payload, $signature, $key, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new WP_Error( 'acc_moadian_sign', __( 'Failed to sign Moadian payload.', 'webinocrm' ) );
		}
		return base64_encode( $signature );
	}

	/**
	 * Generate 22-char taxid: fiscal(6) + hex datetime + serial.
	 *
	 * @param string $serial Serial numeric string.
	 * @param int    $ts     Unix timestamp.
	 * @return string
	 */
	public static function make_taxid( $serial, $ts = null ) {
		$fiscal = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) WebinoCRM_Accounting_Moadian_Config::get()['fiscal_id'] ), 0, 6 ) );
		$fiscal = str_pad( $fiscal, 6, '0' );
		$ts     = $ts ?: time();
		$hex_ts = strtoupper( str_pad( dechex( $ts ), 8, '0', STR_PAD_LEFT ) );
		$ser    = strtoupper( str_pad( dechex( (int) preg_replace( '/\D/', '', (string) $serial ) % 0xFFFFFF ), 6, '0', STR_PAD_LEFT ) );
		$raw    = $fiscal . $hex_ts . substr( $ser, -5 );
		// Verhoeff-like simple check digit placeholder (last char).
		$sum = 0;
		$len = strlen( $raw );
		for ( $i = 0; $i < $len; $i++ ) {
			$sum += ord( $raw[ $i ] ) * ( $i + 1 );
		}
		$check = strtoupper( dechex( $sum % 16 ) );
		return substr( $raw . $check, 0, 22 );
	}

	/**
	 * @param string               $path   Relative path.
	 * @param array<string,mixed>  $body   Body.
	 * @param bool                 $auth   Require keys.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function request( $path, array $body, $auth ) {
		if ( $auth && '' === WebinoCRM_Accounting_Moadian_Config::private_key_pem() ) {
			return new WP_Error( 'acc_moadian_key', __( 'Private key is not configured.', 'webinocrm' ) );
		}
		$url  = rtrim( self::base_url(), '/' ) . '/' . ltrim( $path, '/' );
		$json = wp_json_encode( $body );
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => 'WebinoDashboard-Accounting/1.0',
		);
		if ( $auth ) {
			$token = self::auth_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'timeout' => 45,
			'headers' => $headers,
			'body'    => $json,
		);
		$proxy = trim( (string) ( WebinoCRM_Accounting_Moadian_Config::get()['moadian_proxy'] ?? '' ) );
		if ( '' !== $proxy ) {
			$args['proxy'] = $proxy;
		}
		$res = wp_remote_post( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			$data = array( 'raw' => wp_remote_retrieve_body( $res ) );
		}
		self::log( null, null, 'out', $path, $code, $body, $data );
		if ( $code < 200 || $code >= 300 ) {
			$msg = (string) ( $data['message'] ?? $data['error'] ?? __( 'Moadian API error.', 'webinocrm' ) );
			return new WP_Error( 'acc_moadian_http', $msg, array( 'status' => $code, 'data' => $data ) );
		}
		return $data;
	}

	/**
	 * Simple JWT-like token: base64 header.payload.signature (TSP expects signed token).
	 *
	 * @return string|WP_Error
	 */
	private static function auth_token() {
		$fiscal = (string) WebinoCRM_Accounting_Moadian_Config::get()['fiscal_id'];
		$header = array( 'alg' => 'RS256', 'typ' => 'JWT' );
		$now    = time();
		$payload = array(
			'nonce'    => wp_generate_uuid4(),
			'iat'      => $now,
			'exp'      => $now + 120,
			'fiscalId' => $fiscal,
		);
		$h = self::b64url( wp_json_encode( $header ) );
		$p = self::b64url( wp_json_encode( $payload ) );
		$sig = self::sign_payload( $h . '.' . $p );
		if ( is_wp_error( $sig ) ) {
			return $sig;
		}
		return $h . '.' . $p . '.' . self::b64url( base64_decode( $sig ) );
	}

	/**
	 * @param string $data Data.
	 * @return string
	 */
	private static function b64url( $data ) {
		return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' );
	}

	/**
	 * @param int|null            $invoice_id Invoice.
	 * @param int|null            $job_id     Job.
	 * @param string              $direction  Direction.
	 * @param string              $endpoint   Endpoint.
	 * @param int                 $http_code  HTTP.
	 * @param array<string,mixed> $payload    Payload.
	 * @param array<string,mixed> $response   Response.
	 * @return void
	 */
	public static function log( $invoice_id, $job_id, $direction, $endpoint, $http_code, $payload, $response ) {
		WebinoCRM_Accounting_Db_Compat::insert(
			'moadian_log',
			array(
				'invoice_id' => $invoice_id ? absint( $invoice_id ) : null,
				'job_id'     => $job_id ? absint( $job_id ) : null,
				'direction'  => sanitize_key( $direction ),
				'endpoint'   => sanitize_text_field( $endpoint ),
				'http_code'  => (int) $http_code,
				'payload'    => wp_json_encode( $payload ),
				'response'   => wp_json_encode( $response ),
			)
		);
	}
}
