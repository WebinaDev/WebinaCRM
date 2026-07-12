<?php

namespace WebinaBaleBusiness\Database;

class BusinessRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wbb_businesses';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_by_chat( string $chat_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE chat_id = %s ORDER BY updated_at DESC, id DESC",
				$chat_id
			),
			\ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( int $id, string $chat_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d AND chat_id = %s LIMIT 1",
				$id,
				$chat_id
			),
			\ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function create( string $chat_id, array $data ): int {
		global $wpdb;
		$payload = $this->sanitize_business_data( $data );
		$now = \current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array_merge(
				array(
					'chat_id'    => $chat_id,
					'created_at' => $now,
					'updated_at' => $now,
				),
				$payload
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function update( int $id, string $chat_id, array $data ): bool {
		global $wpdb;
		$payload = $this->sanitize_business_data( $data );
		$payload['updated_at'] = \current_time( 'mysql' );
		$result = $wpdb->update(
			$this->table,
			$payload,
			array(
				'id'      => $id,
				'chat_id' => $chat_id,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
		return false !== $result;
	}

	public function delete( int $id, string $chat_id ): bool {
		global $wpdb;
		$result = $wpdb->delete(
			$this->table,
			array(
				'id'      => $id,
				'chat_id' => $chat_id,
			),
			array( '%d', '%s' )
		);
		return false !== $result;
	}

	public function set_status( int $id, string $chat_id, string $status ): bool {
		return $this->update(
			$id,
			$chat_id,
			array(
				'status' => \sanitize_key( $status ),
			)
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			\ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public function set_status_by_id( int $id, string $status ): bool {
		global $wpdb;
		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => \sanitize_key( $status ),
				'updated_at' => \current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function sanitize_business_data( array $data ): array {
		return array(
			'owner_name'              => \sanitize_text_field( (string) ( $data['owner_name'] ?? '' ) ),
			'business_type'           => \sanitize_text_field( (string) ( $data['business_type'] ?? '' ) ),
			'business_field'          => \sanitize_text_field( (string) ( $data['business_field'] ?? '' ) ),
			'has_website'             => ! empty( $data['has_website'] ) ? 1 : 0,
			'website_url'             => \esc_url_raw( (string) ( $data['website_url'] ?? '' ) ),
			'website_name'            => \sanitize_text_field( (string) ( $data['website_name'] ?? '' ) ),
			'bot_name'                => \sanitize_text_field( (string) ( $data['bot_name'] ?? '' ) ),
			'bot_short_desc'          => \sanitize_textarea_field( (string) ( $data['bot_short_desc'] ?? '' ) ),
			'bot_welcome_text'        => \sanitize_textarea_field( (string) ( $data['bot_welcome_text'] ?? '' ) ),
			'bot_profile_image_file_id' => \sanitize_text_field( (string) ( $data['bot_profile_image_file_id'] ?? '' ) ),
			'status'                  => \sanitize_key( (string) ( $data['status'] ?? 'draft' ) ),
		);
	}
}
