<?php

namespace WebinaBaleBusiness\Database;

class SessionRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wbb_sessions';
	}

	public function set( string $chat_id, string $key, $value ): void {
		global $wpdb;
		$wpdb->replace(
			$this->table,
			array(
				'chat_id'       => $chat_id,
				'session_key'   => $key,
				'session_value' => wp_json_encode( $value ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	public function get( string $chat_id, string $key, $default = null ) {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_value FROM {$this->table} WHERE chat_id = %s AND session_key = %s",
				$chat_id,
				$key
			)
		);
		if ( null === $value ) {
			return $default;
		}
		$decoded = json_decode( (string) $value, true );
		return null === $decoded ? $default : $decoded;
	}
}
