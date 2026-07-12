<?php

namespace WebinaBaleBusiness\Analytics;

class EventLogger {

	public static function log( string $chat_id, string $event_type, array $payload = array() ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_events';
		$wpdb->insert(
			$table,
			array(
				'chat_id'    => $chat_id,
				'event_type' => $event_type,
				'payload'    => wp_json_encode( $payload ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	public static function count_by_event( string $event_type ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_events';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_type = %s", $event_type ) );
	}
}
