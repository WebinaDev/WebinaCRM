<?php
/**
 * Logs service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Logs_Service {
	use WebinoCRM_Domain_Service_Trait;

	public static function list( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! webinocrm_user_can_view_logs() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$log_type = isset( $_POST['log_tab'] ) ? sanitize_key( $_POST['log_tab'] ) : 'events';
		$paged    = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
		$meta_value = ( 'system' === $log_type ) ? 'system_error' : 'log';
		$query = new WP_Query(
			array(
				'post_type'      => 'crm_log',
				'posts_per_page' => 20,
				'paged'          => $paged,
				'meta_key'       => '_log_type',
				'meta_value'     => $meta_value,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $query->posts as $p ) {
			$items[] = array(
				'id'      => $p->ID,
				'title'   => $p->post_title,
				'content' => $p->post_content,
				'author'  => get_the_author_meta( 'display_name', $p->post_author ),
				'date'    => get_the_date( 'Y/m/d H:i', $p ),
			);
		}
		return WebinoCRM_Service_Base::success(
			array(
				'logs'         => $items,
				'total'        => $query->found_posts,
				'total_pages'  => $query->max_num_pages,
				'current_page' => $paged,
			)
		);
	}

	public static function system( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! webinocrm_user_can_view_logs() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$page   = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
		$limit  = isset( $_POST['limit'] ) ? min( 100, max( 1, (int) $_POST['limit'] ) ) : 20;
		$offset = ( $page - 1 ) * $limit;
		$args   = array(
			'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
			'date_to'   => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
			'log_type'  => isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : '',
			'severity'  => isset( $_POST['severity'] ) ? sanitize_text_field( wp_unslash( $_POST['severity'] ) ) : '',
			'user_id'   => isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : '',
			'search'    => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'limit'     => $limit,
			'offset'    => $offset,
		);
		$logs  = WebinoCRM_Logger::get_system_logs( $args );
		$total = WebinoCRM_Logger::get_system_logs_count( $args );
		return WebinoCRM_Service_Base::success(
			array(
				'logs'         => $logs,
				'total'        => $total,
				'total_pages'  => (int) ceil( $total / $limit ),
				'current_page' => $page,
			)
		);
	}

	public static function delete_system( array $params ) {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! webinocrm_user_can_view_logs() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$deleted = WebinoCRM_Logger::delete_all_system_logs();
		return WebinoCRM_Service_Base::success(
			array(
				'deleted' => $deleted,
				'message' => __( 'همه لاگ‌های سیستم حذف شد.', 'webinocrm' ),
			)
		);
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_logs' => array( __CLASS__, 'list' ),
				'webinocrm_get_system_logs' => array( __CLASS__, 'system' ),
				'webinocrm_delete_system_logs' => array( __CLASS__, 'delete_system' ),
			)
		);
	}
}
