<?php
/**
 * IPPanel Edge API client for ModirPayamak (مدیرپیامک).
 *
 * @package WebinoCRM
 * @see https://ippanelcom.github.io/Edge-Document/docs/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin HTTP wrapper around edge.ippanel.com/v1.
 */
class WebinoCRM_ModirPayamak_Edge_Client {

	const BASE_URL = 'https://edge.ippanel.com/v1';

	/**
	 * @return string
	 */
	public static function api_key() {
		return (string) WebinoCRM_Settings_Handler::get_setting( 'modirpayamak_api_key', '' );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::api_key() && (string) WebinoCRM_Settings_Handler::get_setting( 'modirpayamak_enabled', '0' ) === '1';
	}

	/**
	 * @return string
	 */
	public static function default_from() {
		$from = (string) WebinoCRM_Settings_Handler::get_setting( 'modirpayamak_default_from', '' );
		return $from ? $from : '+983000505';
	}

	/**
	 * @param string              $method HTTP method.
	 * @param string              $path   Path after /v1 e.g. api/send.
	 * @param array<string,mixed> $body   JSON body.
	 * @param array<string,mixed> $query  Query args.
	 * @return array{ ok: bool, code: int, data: mixed, meta: mixed, message: string, raw: string }
	 */
	public static function request( $method, $path, array $body = array(), array $query = array() ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return self::error( __( 'ModirPayamak API key is not configured.', 'webinocrm' ), 503 );
		}

		$path = ltrim( (string) $path, '/' );
		$url  = trailingslashit( self::BASE_URL ) . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => strtoupper( (string) $method ),
			'timeout' => 45,
			'headers' => array(
				'Authorization' => $key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);
		if ( in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ), true ) && $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return self::error( $response->get_error_message(), 502 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );
		$data = is_array( $json ) ? ( $json['data'] ?? null ) : null;
		$meta = is_array( $json ) ? ( $json['meta'] ?? array() ) : array();
		$msg  = is_array( $meta ) && ! empty( $meta['message'] ) ? (string) $meta['message'] : '';
		$ok   = $code >= 200 && $code < 300 && ( ! is_array( $meta ) || ! isset( $meta['status'] ) || $meta['status'] );

		return array(
			'ok'      => $ok,
			'code'    => $code,
			'data'    => $data,
			'meta'    => $meta,
			'message' => $msg,
			'raw'     => $raw,
		);
	}

	/**
	 * @param string $message Message.
	 * @param int    $code    HTTP-ish code.
	 * @return array{ ok: bool, code: int, data: null, meta: array, message: string, raw: string }
	 */
	private static function error( $message, $code = 400 ) {
		return array(
			'ok'      => false,
			'code'    => $code,
			'data'    => null,
			'meta'    => array(),
			'message' => (string) $message,
			'raw'     => '',
		);
	}

	// --- Auth ---
	public static function login( $username, $password ) {
		return self::request( 'POST', 'api/auth/login', array( 'username' => $username, 'password' => $password ) );
	}

	public static function logout() {
		return self::request( 'POST', 'api/auth/logout' );
	}

	// --- Send ---
	public static function send( array $payload ) {
		return self::request( 'POST', 'api/send', $payload );
	}

	public static function send_webservice( $from, $message, array $recipients, $send_time = null ) {
		$body = array(
			'sending_type' => 'webservice',
			'from_number'  => $from,
			'message'      => $message,
			'params'       => array( 'recipients' => array_values( $recipients ) ),
		);
		if ( $send_time ) {
			$body['send_time'] = $send_time;
		}
		return self::send( $body );
	}

	public static function send_pattern( $from, $code, array $recipients, array $params ) {
		return self::send(
			array(
				'sending_type' => 'pattern',
				'from_number'  => $from,
				'code'         => $code,
				'recipients'   => array_values( $recipients ),
				'params'       => $params,
			)
		);
	}

	public static function send_peer_to_peer( array $groups, $from ) {
		return self::send(
			array(
				'sending_type' => 'peer_to_peer',
				'from_number'  => $from,
				'params'       => array( 'groups' => $groups ),
			)
		);
	}

	public static function calculate_price( array $payload ) {
		return self::request( 'POST', 'api/send/calculate-price', $payload );
	}

	public static function cancel_scheduled( $outbox_id ) {
		return self::request( 'POST', 'api/send/cancel-scheduled', array( 'messages_outbox_id' => $outbox_id ) );
	}

	// --- Reports ---
	public static function report_outbox( $page = 1, $limit = 20, array $filters = array() ) {
		return self::request(
			'POST',
			'api/report/new_list',
			array(
				'page'    => (int) $page,
				'limit'   => (int) $limit,
				'filters' => $filters,
			)
		);
	}

	public static function report_outbox_by_id( $outbox_id ) {
		return self::request( 'GET', 'api/report/outbox/' . rawurlencode( (string) $outbox_id ) );
	}

	public static function report_inbox( $page = 1, $limit = 20, array $filters = array() ) {
		return self::request(
			'POST',
			'api/report/inbox',
			array(
				'page'    => (int) $page,
				'limit'   => (int) $limit,
				'filters' => $filters,
			)
		);
	}

	// --- Payment ---
	public static function my_credit() {
		return self::request( 'GET', 'api/payment/credit/mine' );
	}

	// --- Patterns ---
	public static function list_patterns( array $query = array() ) {
		return self::request( 'GET', 'api/patterns', array(), $query );
	}

	public static function get_pattern( $code ) {
		return self::request( 'GET', 'api/patterns/' . rawurlencode( (string) $code ) );
	}

	public static function create_pattern( array $payload ) {
		return self::request( 'POST', 'api/patterns', $payload );
	}

	public static function update_pattern( $code, array $payload ) {
		return self::request( 'PUT', 'api/patterns/' . rawurlencode( (string) $code ), $payload );
	}

	public static function delete_pattern( $code ) {
		return self::request( 'DELETE', 'api/patterns/' . rawurlencode( (string) $code ) );
	}

	// --- Phonebook ---
	public static function list_phonebooks( array $query = array() ) {
		return self::request( 'GET', 'api/phonebooks', array(), $query );
	}

	public static function create_phonebook( array $payload ) {
		return self::request( 'POST', 'api/phonebooks', $payload );
	}

	public static function update_phonebook( $id, array $payload ) {
		return self::request( 'PUT', 'api/phonebooks/' . (int) $id, $payload );
	}

	public static function delete_phonebook( $id ) {
		return self::request( 'DELETE', 'api/phonebooks/' . (int) $id );
	}

	public static function list_phonebook_numbers( $phonebook_id, array $query = array() ) {
		return self::request( 'GET', 'api/phonebooks/' . (int) $phonebook_id . '/numbers', array(), $query );
	}

	public static function store_phonebook_number( $phonebook_id, array $payload ) {
		return self::request( 'POST', 'api/phonebooks/' . (int) $phonebook_id . '/numbers', $payload );
	}

	// --- Numbers ---
	public static function list_numbers( array $query = array() ) {
		return self::request( 'GET', 'api/numbers', array(), $query );
	}

	// --- Users (reseller) ---
	public static function list_users( array $query = array() ) {
		return self::request( 'GET', 'api/user', array(), $query );
	}

	public static function create_user( array $payload ) {
		return self::request( 'POST', 'api/user/create', $payload );
	}

	public static function show_user( $user_id ) {
		return self::request( 'GET', 'api/user/' . rawurlencode( (string) $user_id ) );
	}

	public static function update_user( $user_id, array $payload ) {
		return self::request( 'PUT', 'api/user/' . rawurlencode( (string) $user_id ), $payload );
	}

	// --- Packages ---
	public static function list_packages( array $query = array() ) {
		return self::request( 'GET', 'api/packages', array(), $query );
	}

	// --- Drafts ---
	public static function list_drafts( array $query = array() ) {
		return self::request( 'GET', 'api/drafts', array(), $query );
	}

	public static function create_draft( array $payload ) {
		return self::request( 'POST', 'api/drafts', $payload );
	}

	public static function delete_draft( $id ) {
		return self::request( 'DELETE', 'api/drafts/' . (int) $id );
	}

	// --- Tickets ---
	public static function list_tickets( array $query = array() ) {
		return self::request( 'GET', 'api/tickets', array(), $query );
	}

	public static function create_ticket( array $payload ) {
		return self::request( 'POST', 'api/tickets', $payload );
	}

	public static function show_ticket( $id ) {
		return self::request( 'GET', 'api/tickets/' . (int) $id );
	}

	public static function reply_ticket( $id, array $payload ) {
		return self::request( 'POST', 'api/tickets/' . (int) $id . '/reply', $payload );
	}

	/**
	 * Generic proxy for admin AJAX (any Edge path).
	 *
	 * @param string              $method HTTP method.
	 * @param string              $path   API path.
	 * @param array<string,mixed> $body   Body.
	 * @param array<string,mixed> $query  Query.
	 * @return array<string,mixed>
	 */
	public static function proxy( $method, $path, array $body = array(), array $query = array() ) {
		return self::request( $method, $path, $body, $query );
	}
}
