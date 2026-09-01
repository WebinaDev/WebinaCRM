<?php

namespace WebinaBaleBusiness\Admin;

use WebinaBaleBusiness\Database\BusinessRepository;
use WebinaBaleBusiness\Database\ProfileRepository;

class BusinessAdminSync {

	private const META_BUSINESS_ID = '_wbb_business_id';
	private const META_CHAT_ID = '_wbb_chat_id';
	private const META_OWNER_NAME = '_wbb_owner_name';
	private const META_BUSINESS_TYPE = '_wbb_business_type';
	private const META_BUSINESS_FIELD = '_wbb_business_field';
	private const META_WEBSITE_URL = '_wbb_website_url';
	private const META_WEBSITE_NAME = '_wbb_website_name';
	private const META_BOT_NAME = '_wbb_bot_name';
	private const META_BOT_SHORT_DESC = '_wbb_bot_short_desc';
	private const META_BOT_WELCOME_TEXT = '_wbb_bot_welcome_text';
	private const META_BOT_PROFILE_IMAGE = '_wbb_bot_profile_image_file_id';
	private const META_PROFILE_FIRST_NAME = '_wbb_profile_first_name';
	private const META_PROFILE_LAST_NAME = '_wbb_profile_last_name';
	private const META_PROFILE_MOBILE = '_wbb_profile_mobile';
	private const META_PROFILE_NATIONAL_CARD = '_wbb_profile_national_card_file_id';
	private const META_REVIEW_STATUS = '_wbb_review_status';
	private const META_ADMIN_NOTE = '_wbb_admin_note';

	public static function init(): void {
		\add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		\add_action( 'save_post_wbb_biz_submit', array( __CLASS__, 'save_review_meta' ) );
	}

	public static function sync_business_submission( int $business_id ): void {
		$businesses = new BusinessRepository();
		$profiles   = new ProfileRepository();
		$business   = $businesses->get_by_id( $business_id );
		if ( ! is_array( $business ) ) {
			return;
		}

		$chat_id = (string) ( $business['chat_id'] ?? '' );
		if ( $chat_id === '' ) {
			return;
		}
		$profile = $profiles->get_or_empty( $chat_id );

		$submission_id = self::find_submission_id_by_business( $business_id );
		$title         = (string) ( $business['bot_name'] ?: $business['owner_name'] ?: ( 'Business #' . $business_id ) );
		$post_data     = array(
			'post_type'   => 'wbb_biz_submit',
			'post_status' => 'publish',
			'post_title'  => \sanitize_text_field( $title ),
		);
		if ( $submission_id > 0 ) {
			$post_data['ID'] = $submission_id;
			\wp_update_post( $post_data );
		} else {
			$submission_id = (int) \wp_insert_post( $post_data );
		}
		if ( $submission_id <= 0 ) {
			return;
		}

		\update_post_meta( $submission_id, self::META_BUSINESS_ID, $business_id );
		\update_post_meta( $submission_id, self::META_CHAT_ID, $chat_id );
		\update_post_meta( $submission_id, self::META_OWNER_NAME, (string) ( $business['owner_name'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_BUSINESS_TYPE, (string) ( $business['business_type'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_BUSINESS_FIELD, (string) ( $business['business_field'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_WEBSITE_URL, (string) ( $business['website_url'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_WEBSITE_NAME, (string) ( $business['website_name'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_BOT_NAME, (string) ( $business['bot_name'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_BOT_SHORT_DESC, (string) ( $business['bot_short_desc'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_BOT_WELCOME_TEXT, (string) ( $business['bot_welcome_text'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_BOT_PROFILE_IMAGE, (string) ( $business['bot_profile_image_file_id'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_PROFILE_FIRST_NAME, (string) ( $profile['first_name'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_PROFILE_LAST_NAME, (string) ( $profile['last_name'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_PROFILE_MOBILE, (string) ( $profile['mobile'] ?? '' ) );
		\update_post_meta( $submission_id, self::META_PROFILE_NATIONAL_CARD, (string) ( $profile['national_card_file_id'] ?? '' ) );

		$current_status = \sanitize_key( (string) ( $business['status'] ?? 'pending' ) );
		if ( ! \in_array( $current_status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$current_status = 'pending';
		}
		$review_status = \get_post_meta( $submission_id, self::META_REVIEW_STATUS, true );
		if ( $review_status === '' ) {
			\update_post_meta( $submission_id, self::META_REVIEW_STATUS, $current_status );
		}
	}

	public static function get_status_label( string $status ): string {
		$normalized = \sanitize_key( $status );
		if ( $normalized === 'approved' ) {
			return 'تایید شده';
		}
		if ( $normalized === 'rejected' ) {
			return 'رد شده';
		}
		if ( $normalized === 'completed' ) {
			return 'آماده بررسی';
		}
		if ( $normalized === 'draft' ) {
			return 'پیش نویس';
		}
		return 'در انتظار بررسی';
	}

	/**
	 * @return array{status:string,note:string}
	 */
	public static function get_review_for_business( int $business_id ): array {
		$submission_id = self::find_submission_id_by_business( $business_id );
		if ( $submission_id <= 0 ) {
			return array(
				'status' => 'pending',
				'note'   => '',
			);
		}
		return array(
			'status' => (string) \get_post_meta( $submission_id, self::META_REVIEW_STATUS, true ),
			'note'   => (string) \get_post_meta( $submission_id, self::META_ADMIN_NOTE, true ),
		);
	}

	public static function register_meta_box(): void {
		\add_meta_box(
			'wbb-business-review',
			'بررسی درخواست کسب و کار',
			array( __CLASS__, 'render_meta_box' ),
			'wbb_biz_submit',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		\wp_nonce_field( 'wbb_business_review_save', 'wbb_business_review_nonce' );
		$review_status = (string) \get_post_meta( $post->ID, self::META_REVIEW_STATUS, true );
		$admin_note    = (string) \get_post_meta( $post->ID, self::META_ADMIN_NOTE, true );
		$fields        = array(
			'شناسه کسب و کار'   => (string) \get_post_meta( $post->ID, self::META_BUSINESS_ID, true ),
			'شناسه چت'         => (string) \get_post_meta( $post->ID, self::META_CHAT_ID, true ),
			'نام صاحب کسب و کار' => (string) \get_post_meta( $post->ID, self::META_OWNER_NAME, true ),
			'نوع کسب و کار'     => (string) \get_post_meta( $post->ID, self::META_BUSINESS_TYPE, true ),
			'حوزه فعالیت'       => (string) \get_post_meta( $post->ID, self::META_BUSINESS_FIELD, true ),
			'نام بازو'          => (string) \get_post_meta( $post->ID, self::META_BOT_NAME, true ),
			'موبایل'            => (string) \get_post_meta( $post->ID, self::META_PROFILE_MOBILE, true ),
			'تصویر کارت ملی'    => (string) \get_post_meta( $post->ID, self::META_PROFILE_NATIONAL_CARD, true ),
			'تصویر پروفایل بازو' => (string) \get_post_meta( $post->ID, self::META_BOT_PROFILE_IMAGE, true ),
		);

		echo '<table class="form-table"><tbody>';
		foreach ( $fields as $label => $value ) {
			echo '<tr><th>' . \esc_html( $label ) . '</th><td><code>' . \esc_html( $value !== '' ? $value : '-' ) . '</code></td></tr>';
		}
		echo '</tbody></table>';

		echo '<p><label for="wbb_review_status"><strong>وضعیت بررسی</strong></label></p>';
		echo '<select id="wbb_review_status" name="wbb_review_status">';
		$options = array(
			'pending'  => 'در انتظار بررسی',
			'approved' => 'تایید شده',
			'rejected' => 'رد شده',
		);
		foreach ( $options as $key => $label ) {
			echo '<option value="' . \esc_attr( $key ) . '" ' . \selected( $review_status, $key, false ) . '>' . \esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p><label for="wbb_admin_note"><strong>یادداشت ادمین</strong></label></p>';
		echo '<textarea id="wbb_admin_note" name="wbb_admin_note" rows="4" style="width:100%;">' . \esc_textarea( $admin_note ) . '</textarea>';
	}

	public static function save_review_meta( int $post_id ): void {
		if ( ! isset( $_POST['wbb_business_review_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['wbb_business_review_nonce'] ) ), 'wbb_business_review_save' ) ) {
			return;
		}
		if ( \defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE ) {
			return;
		}
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$status = isset( $_POST['wbb_review_status'] ) ? \sanitize_key( \wp_unslash( $_POST['wbb_review_status'] ) ) : 'pending';
		if ( ! \in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$status = 'pending';
		}
		$admin_note = isset( $_POST['wbb_admin_note'] ) ? \sanitize_textarea_field( \wp_unslash( $_POST['wbb_admin_note'] ) ) : '';

		\update_post_meta( $post_id, self::META_REVIEW_STATUS, $status );
		\update_post_meta( $post_id, self::META_ADMIN_NOTE, $admin_note );

		$business_id = (int) \get_post_meta( $post_id, self::META_BUSINESS_ID, true );
		if ( $business_id > 0 ) {
			$businesses = new BusinessRepository();
			$businesses->set_status_by_id( $business_id, $status );
		}
	}

	private static function find_submission_id_by_business( int $business_id ): int {
		$posts = \get_posts(
			array(
				'post_type'      => 'wbb_biz_submit',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_BUSINESS_ID,
						'value' => $business_id,
					),
				),
			)
		);
		return isset( $posts[0] ) ? (int) $posts[0] : 0;
	}
}
