<?php
/**
 * Visitor service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Visitor_Service {
	use WebinoCRM_Domain_Service_Trait;

	public static function get( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! webinocrm_user_can_view_visitor_stats() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : wp_date( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : wp_date( 'Y-m-d' );
		$today     = wp_date( 'Y-m-d' );
		return WebinoCRM_Service_Base::success(
			array(
				'overall'          => WebinoCRM_Visitor_Statistics::get_overall_stats( $date_from, $date_to ),
				'daily'            => WebinoCRM_Visitor_Statistics::get_daily_visits( $date_from, $date_to ),
				'top_pages'        => WebinoCRM_Visitor_Statistics::get_top_pages( 10, $date_from, $date_to ),
				'browsers'         => WebinoCRM_Visitor_Statistics::get_browser_stats( $date_from, $date_to ),
				'os'               => WebinoCRM_Visitor_Statistics::get_os_stats( $date_from, $date_to ),
				'devices'          => WebinoCRM_Visitor_Statistics::get_device_stats( $date_from, $date_to ),
				'referrers'        => WebinoCRM_Visitor_Statistics::get_referrer_stats( 10, $date_from, $date_to ),
				'recent'           => WebinoCRM_Visitor_Statistics::get_recent_visitors( 50, $date_from, $date_to ),
				'online'           => WebinoCRM_Visitor_Statistics::get_online_visitors(),
				'tracking_enabled' => WebinoCRM_Settings_Handler::is_visitor_tracking_enabled(),
				'live_kpis_global' => ( $date_from !== $today || $date_to !== $today ),
			)
		);
	}

	public static function save_settings( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! webinocrm_user_can_view_visitor_stats() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Settings_Handler::save_tab_settings( 'visitor_tracking', $_POST );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'          => __( 'تنظیمات آمار بازدید ذخیره شد.', 'webinocrm' ),
				'tracking_enabled' => WebinoCRM_Settings_Handler::is_visitor_tracking_enabled(),
			)
		);
	}

	public static function track( array $params ) {
		if ( ! WebinoCRM_Settings_Handler::is_visitor_tracking_enabled() ) {
			return WebinoCRM_Service_Base::success( array( 'tracked' => false ) );
		}
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::success( array( 'tracked' => false ) );
		}
		if ( function_exists( 'webinocrm_rate_limit_allow' ) && ! webinocrm_rate_limit_allow( 'visitor_track', 180, 60 ) ) {
			return WebinoCRM_Service_Base::success( array( 'tracked' => false ) );
		}
		$page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : null;
		$page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : null;
		$referrer   = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : null;
		$entity_id  = isset( $_POST['entity_id'] ) ? absint( $_POST['entity_id'] ) : null;
		$ok         = WebinoCRM_Visitor_Statistics::track_visit( $page_url, $page_title, $referrer, $entity_id );
		return WebinoCRM_Service_Base::success( array( 'tracked' => $ok ) );
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_visitor_stats' => array( __CLASS__, 'get' ),
				'webinocrm_save_visitor_settings' => array( __CLASS__, 'save_settings' ),
				'webinocrm_track_visit' => array( __CLASS__, 'track' ),
			)
		);
	}
}
