<?php

namespace WebinaBaleBusiness\Database;

class ProfileRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wbb_profiles';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_or_empty( string $chat_id ): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE chat_id = %s LIMIT 1",
				$chat_id
			),
			\ARRAY_A
		);
		if ( is_array( $row ) ) {
			return $row;
		}
		return array(
			'chat_id'               => $chat_id,
			'first_name'            => '',
			'last_name'             => '',
			'national_card_file_id' => '',
			'mobile'                => '',
			'is_complete'           => 0,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function upsert( string $chat_id, array $data ): bool {
		global $wpdb;
		$current = $this->get_or_empty( $chat_id );
		$merged  = array_merge( $current, $data );
		$payload = $this->sanitize_profile_data( $merged );
		$payload['is_complete'] = $this->is_complete_array( $payload ) ? 1 : 0;
		$payload['updated_at']  = \current_time( 'mysql' );

		$exists = ! empty( $current['id'] );
		if ( $exists ) {
			$result = $wpdb->update(
				$this->table,
				$payload,
				array( 'chat_id' => $chat_id ),
				array( '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%s' )
			);
			return false !== $result;
		}

		$payload['chat_id']    = $chat_id;
		$payload['created_at'] = \current_time( 'mysql' );
		$result = $wpdb->insert(
			$this->table,
			$payload,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return false !== $result;
	}

	public function is_complete( string $chat_id ): bool {
		$profile = $this->get_or_empty( $chat_id );
		return ! empty( $profile['is_complete'] );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function sanitize_profile_data( array $data ): array {
		return array(
			'first_name'            => \sanitize_text_field( (string) ( $data['first_name'] ?? '' ) ),
			'last_name'             => \sanitize_text_field( (string) ( $data['last_name'] ?? '' ) ),
			'national_card_file_id' => \sanitize_text_field( (string) ( $data['national_card_file_id'] ?? '' ) ),
			'mobile'                => preg_replace( '/[^0-9+]/', '', (string) ( $data['mobile'] ?? '' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function is_complete_array( array $data ): bool {
		return (string) ( $data['first_name'] ?? '' ) !== ''
			&& (string) ( $data['last_name'] ?? '' ) !== ''
			&& (string) ( $data['national_card_file_id'] ?? '' ) !== ''
			&& (string) ( $data['mobile'] ?? '' ) !== '';
	}
}
