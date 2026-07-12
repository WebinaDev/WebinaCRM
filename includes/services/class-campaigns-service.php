<?php
/**
 * Campaigns service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Campaigns_Service {
	use WebinoCRM_Domain_Service_Trait;

	private const CHANNELS = array( 'web', 'bale', 'phone', 'in-person', 'other' );
	private const STATUSES = array( 'draft', 'active', 'paused', 'ended' );

	public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::verify_request();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$search  = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'search', '' ) );
		$status  = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status_filter', '' ) );
		$channel = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'channel_filter', '' ) );
		$paged   = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'paged', 1 ) );
		$per_page = 20;
		$meta_query = array( 'relation' => 'AND' );
		if ( $status && in_array( $status, self::STATUSES, true ) ) {
			$meta_query[] = array(
				'key'   => '_campaign_status',
				'value' => $status,
			);
		}
		if ( $channel && in_array( $channel, self::CHANNELS, true ) ) {
			$meta_query[] = array(
				'key'   => '_campaign_channel',
				'value' => $channel,
			);
		}
		$args = array(
			'post_type'      => 'campaign',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::format_campaign( $post );
		}
		$channels = array();
		foreach ( self::CHANNELS as $c ) {
			$channels[] = array( 'slug' => $c, 'name' => self::channel_label( $c ) );
		}
		$statuses = array();
		foreach ( self::STATUSES as $s ) {
			$statuses[] = array( 'slug' => $s, 'name' => self::status_label( $s ) );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'campaigns'   => $items,
				'channels'    => $channels,
				'statuses'    => $statuses,
				'total_pages' => (int) $query->max_num_pages,
				'total'       => (int) $query->found_posts,
			)
		);
	}

	public static function save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::verify_request();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$campaign_id = isset( $_POST['campaign_id'] ) ? (int) $_POST['campaign_id'] : 0;
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$channel     = isset( $_POST['channel'] ) ? sanitize_key( $_POST['channel'] ) : 'web';
		$status      = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'draft';
		$budget      = isset( $_POST['budget'] ) ? floatval( $_POST['budget'] ) : 0;
		$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		if ( ! $title ) {
			return WebinoCRM_Service_Base::error( __( 'عنوان کمپین الزامی است.', 'webinocrm' ) );
		}
		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			$channel = 'web';
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = 'draft';
		}
		if ( $start_date && $end_date && strtotime( $end_date ) < strtotime( $start_date ) ) {
			return WebinoCRM_Service_Base::error( __( 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.', 'webinocrm' ) );
		}
		$post_data = array(
			'post_title'   => $title,
			'post_content' => $description,
			'post_type'    => 'campaign',
			'post_status'  => 'publish',
		);
		if ( $campaign_id > 0 ) {
			$post_data['ID'] = $campaign_id;
			$result          = wp_update_post( $post_data, true );
			$message         = __( 'کمپین با موفقیت به‌روزرسانی شد.', 'webinocrm' );
		} else {
			$result  = wp_insert_post( $post_data, true );
			$message = __( 'کمپین با موفقیت ایجاد شد.', 'webinocrm' );
		}
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		$id = $campaign_id > 0 ? $campaign_id : (int) $result;
		update_post_meta( $id, '_campaign_channel', $channel );
		update_post_meta( $id, '_campaign_status', $status );
		update_post_meta( $id, '_campaign_budget', $budget );
		update_post_meta( $id, '_campaign_start_date', $start_date );
		update_post_meta( $id, '_campaign_end_date', $end_date );
		return WebinoCRM_Service_Base::success(
			array(
				'message'  => $message,
				'campaign' => self::format_campaign( get_post( $id ) ),
			)
		);
	}

	public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$denied = self::verify_request();
		if ( is_array( $denied ) ) {
			return $denied;
		}
		$campaign_id = isset( $_POST['campaign_id'] ) ? (int) $_POST['campaign_id'] : 0;
		if ( $campaign_id <= 0 || get_post_type( $campaign_id ) !== 'campaign' ) {
			return WebinoCRM_Service_Base::error( __( 'کمپین یافت نشد.', 'webinocrm' ) );
		}
		$lead_count = self::count_leads_for_campaign( $campaign_id );
		$unlink     = isset( $_POST['unlink_leads'] ) && $_POST['unlink_leads'] === '1';
		if ( $lead_count > 0 && ! $unlink ) {
			return WebinoCRM_Service_Base::error(
				sprintf(
					/* translators: %d: lead count */
					__( 'این کمپین به %d سرنخ متصل است. برای حذف، گزینه جدا کردن سرنخ‌ها را تأیید کنید.', 'webinocrm' ),
					$lead_count
				),
				409
			);
		}
		if ( $lead_count > 0 && $unlink ) {
			global $wpdb;
			$wpdb->delete(
				$wpdb->postmeta,
				array(
					'meta_key'   => '_campaign_id',
					'meta_value' => (string) $campaign_id,
				)
			);
		}
		$deleted = wp_delete_post( $campaign_id, true );
		if ( ! $deleted ) {
			return WebinoCRM_Service_Base::error( __( 'خطا در حذف کمپین.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'کمپین حذف شد.', 'webinocrm' ) ) );
	}

	/**
	 * @return true|array<string,mixed>
	 */
	private static function verify_request() {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		return true;
	}

	private static function count_leads_for_campaign( $campaign_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'lead' AND p.post_status != 'trash'
				AND pm.meta_key = '_campaign_id' AND pm.meta_value = %s",
				(string) $campaign_id
			)
		);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private static function format_campaign( $post ) {
		$id            = (int) $post->ID;
		$channel       = get_post_meta( $id, '_campaign_channel', true ) ?: 'web';
		$status        = get_post_meta( $id, '_campaign_status', true ) ?: 'draft';
		$budget        = get_post_meta( $id, '_campaign_budget', true );
		$start         = get_post_meta( $id, '_campaign_start_date', true );
		$end           = get_post_meta( $id, '_campaign_end_date', true );
		return array(
			'id'                 => $id,
			'title'              => $post->post_title,
			'description'        => $post->post_content,
			'channel'            => $channel,
			'channel_label'      => self::channel_label( $channel ),
			'status'             => $status,
			'status_label'       => self::status_label( $status ),
			'budget'             => $budget !== '' && $budget !== false ? (float) $budget : 0,
			'start_date'         => $start ?: '',
			'end_date'           => $end ?: '',
			'start_date_display' => $start ? date_i18n( 'Y/m/d', strtotime( $start ) ) : '',
			'end_date_display'   => $end ? date_i18n( 'Y/m/d', strtotime( $end ) ) : '',
			'lead_count'         => self::count_leads_for_campaign( $id ),
			'created'            => $post->post_date,
		);
	}

	private static function channel_label( $slug ) {
		$labels = array(
			'web'       => __( 'وب', 'webinocrm' ),
			'bale'      => __( 'بله', 'webinocrm' ),
			'phone'     => __( 'تلفنی', 'webinocrm' ),
			'in-person' => __( 'حضوری', 'webinocrm' ),
			'other'     => __( 'سایر', 'webinocrm' ),
		);
		return $labels[ $slug ] ?? $slug;
	}

	private static function status_label( $slug ) {
		$labels = array(
			'draft'  => __( 'پیش‌نویس', 'webinocrm' ),
			'active' => __( 'فعال', 'webinocrm' ),
			'paused' => __( 'متوقف', 'webinocrm' ),
			'ended'  => __( 'پایان‌یافته', 'webinocrm' ),
		);
		return $labels[ $slug ] ?? $slug;
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_campaigns' => array( __CLASS__, 'list' ),
				'webino_manage_campaign' => array( __CLASS__, 'save' ),
				'webino_delete_campaign' => array( __CLASS__, 'delete' ),
			)
		);
	}
}
