<?php
/**
 * Public site lead capture (admin-ajax).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Leads {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_webinocrm_site_lead', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_webinocrm_site_lead', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_webinocrm_site_career', array( __CLASS__, 'handle_career' ) );
		add_action( 'wp_ajax_nopriv_webinocrm_site_career', array( __CLASS__, 'handle_career' ) );
	}

	/**
	 * Handle lead form submission.
	 */
	public static function handle() {
		check_ajax_referer( 'webinocrm_site_lead', 'nonce' );

		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$service = sanitize_text_field( wp_unslash( $_POST['service'] ?? '' ) );
		$page    = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );

		if ( '' === $name || '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'نام و موبایل الزامی است.', 'webinocrm' ) ), 400 );
		}

		if ( ! post_type_exists( 'lead' ) ) {
			wp_send_json_error( array( 'message' => __( 'سیستم CRM در دسترس نیست.', 'webinocrm' ) ), 503 );
		}

		$parts = preg_split( '/\s+/u', trim( $name ), 2 );
		$first = $parts[0] ?? $name;
		$last  = $parts[1] ?? '-';

		$service_label = $service;
		if ( function_exists( 'webinocrm_site_brand' ) ) {
			$brand_services = (array) ( webinocrm_site_brand()['services'] ?? array() );
			if ( isset( $brand_services[ $service ] ) ) {
				$service_label = (string) $brand_services[ $service ];
			}
		}

		$content = $message;
		if ( $service_label ) {
			$content = trim( 'خدمت درخواستی: ' . $service_label . "\n\n" . $message );
		}

		$lead_id = wp_insert_post(
			array(
				'post_type'    => 'lead',
				'post_title'   => $name,
				'post_content' => $content,
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $lead_id ) || ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ثبت درخواست.', 'webinocrm' ) ), 500 );
		}

		update_post_meta( $lead_id, '_first_name', $first );
		update_post_meta( $lead_id, '_last_name', $last );
		update_post_meta( $lead_id, '_mobile', $phone );
		update_post_meta( $lead_id, '_webina_source_page', $page );
		update_post_meta( $lead_id, '_webina_service', $service );
		update_post_meta( $lead_id, '_webina_service_label', $service_label );
		update_post_meta(
			$lead_id,
			'_elementor_form_data',
			wp_json_encode(
				array(
					'name'    => $name,
					'phone'   => $phone,
					'message' => $message,
					'service' => $service_label,
				),
				JSON_UNESCAPED_UNICODE
			)
		);

		if ( taxonomy_exists( 'lead_source' ) ) {
			wp_set_object_terms( $lead_id, 'website', 'lead_source', false );
		}

		wp_send_json_success( array( 'message' => __( 'درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.', 'webinocrm' ) ) );
	}

	/**
	 * Handle careers / collaborate form.
	 */
	public static function handle_career() {
		check_ajax_referer( 'webinocrm_site_lead', 'nonce' );

		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$field = sanitize_text_field( wp_unslash( $_POST['field'] ?? '' ) );
		$level = sanitize_text_field( wp_unslash( $_POST['level'] ?? '' ) );
		$type  = sanitize_text_field( wp_unslash( $_POST['work_type'] ?? '' ) );
		$exp   = sanitize_text_field( wp_unslash( $_POST['experience'] ?? '' ) );
		$city  = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
		$link  = esc_url_raw( wp_unslash( $_POST['resume_link'] ?? '' ) );

		if ( '' === $name || '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'نام و موبایل الزامی است.', 'webinocrm' ) ), 400 );
		}

		if ( ! post_type_exists( 'lead' ) ) {
			wp_send_json_error( array( 'message' => __( 'سیستم CRM در دسترس نیست.', 'webinocrm' ) ), 503 );
		}

		$content = "درخواست همکاری\nزمینه: {$field}\nسطح: {$level}\nنوع: {$type}\nسابقه: {$exp}\nشهر: {$city}\nلینک: {$link}";

		$lead_id = wp_insert_post(
			array(
				'post_type'    => 'lead',
				'post_title'   => 'همکاری: ' . $name,
				'post_content' => $content,
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $lead_id ) || ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ثبت درخواست.', 'webinocrm' ) ), 500 );
		}

		update_post_meta( $lead_id, '_first_name', $name );
		update_post_meta( $lead_id, '_mobile', $phone );
		update_post_meta( $lead_id, '_webina_career', 1 );
		update_post_meta( $lead_id, '_webina_service', 'careers' );

		if ( taxonomy_exists( 'lead_source' ) ) {
			wp_set_object_terms( $lead_id, 'careers', 'lead_source', false );
		}

		wp_send_json_success( array( 'message' => __( 'درخواست همکاری ثبت شد.', 'webinocrm' ) ) );
	}
}
