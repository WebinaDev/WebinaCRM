<?php

namespace WebinaBaleBusiness\Support;

class Logger {

	/**
	 * @param array<string,mixed> $context
	 */
	public static function log( string $level, string $type, array $context = array() ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'wbb_logs';

		$wpdb->insert(
			$table,
			array(
				'level'      => sanitize_key( $level ),
				'log_type'   => sanitize_key( $type ),
				'context'    => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log(
				$level,
				'[' . $type . '] ' . wp_json_encode( $context ),
				array( 'source' => 'webina-bale-business' )
			);
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function latest( int $limit = 20 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_logs';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, level, log_type, context, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
				max( 1, $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
