<?php
/**
 * WebinoCRM Campaign AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/campaigns. Kept for legacy hooks.
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Campaign_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


	private const CHANNELS = array( 'web', 'bale', 'phone', 'in-person', 'other' );
	private const STATUSES = array( 'draft', 'active', 'paused', 'ended' );

	public function __construct() {
		add_action( 'wp_ajax_webinocrm_get_campaigns', array( $this, 'ajax_get_campaigns' ) );
		add_action( 'wp_ajax_webino_manage_campaign', array( $this, 'ajax_manage_campaign' ) );
		add_action( 'wp_ajax_webino_delete_campaign', array( $this, 'ajax_delete_campaign' ) );
	}

	private function verify_request() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
	}

	private function count_leads_for_campaign( $campaign_id ) {
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

	private function format_campaign( $post ) {
		$id            = (int) $post->ID;
		$channel       = get_post_meta( $id, '_campaign_channel', true ) ?: 'web';
		$status        = get_post_meta( $id, '_campaign_status', true ) ?: 'draft';
		$budget        = get_post_meta( $id, '_campaign_budget', true );
		$start         = get_post_meta( $id, '_campaign_start_date', true );
		$end           = get_post_meta( $id, '_campaign_end_date', true );
		$channel_label = $this->channel_label( $channel );
		$status_label  = $this->status_label( $status );

		return array(
			'id'              => $id,
			'title'           => $post->post_title,
			'description'     => $post->post_content,
			'channel'         => $channel,
			'channel_label'   => $channel_label,
			'status'          => $status,
			'status_label'    => $status_label,
			'budget'          => $budget !== '' && $budget !== false ? (float) $budget : 0,
			'start_date'      => $start ?: '',
			'end_date'        => $end ?: '',
			'start_date_display' => $start ? date_i18n( 'Y/m/d', strtotime( $start ) ) : '',
			'end_date_display'   => $end ? date_i18n( 'Y/m/d', strtotime( $end ) ) : '',
			'lead_count'      => $this->count_leads_for_campaign( $id ),
			'created'         => $post->post_date,
		);
	}

	private function channel_label( $slug ) {
		$labels = array(
			'web'       => __( 'وب', 'webinocrm' ),
			'bale'      => __( 'بله', 'webinocrm' ),
			'phone'     => __( 'تلفنی', 'webinocrm' ),
			'in-person' => __( 'حضوری', 'webinocrm' ),
			'other'     => __( 'سایر', 'webinocrm' ),
		);
		return $labels[ $slug ] ?? $slug;
	}

	private function status_label( $slug ) {
		$labels = array(
			'draft'  => __( 'پیش‌نویس', 'webinocrm' ),
			'active' => __( 'فعال', 'webinocrm' ),
			'paused' => __( 'متوقف', 'webinocrm' ),
			'ended'  => __( 'پایان‌یافته', 'webinocrm' ),
		);
		return $labels[ $slug ] ?? $slug;
	}

	public function ajax_get_campaigns() {
		$this->emit_service( array( 'WebinoCRM_Campaigns_Service', 'list' ) );
	}

	public function ajax_manage_campaign() {
		$this->emit_service( array( 'WebinoCRM_Campaigns_Service', 'save' ) );
	}

	public function ajax_delete_campaign() {
		$this->emit_service( array( 'WebinoCRM_Campaigns_Service', 'delete' ) );
	}
}
