<?php
/**
 * Product newsletter SMS subscribers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribe and campaign send for product alerts.
 */
final class WebinoCRM_Sms_Newsletter_Service {

	/**
	 * @param string $domain Domain.
	 * @param int    $product_id Product id (0 = all).
	 * @param string $phone Phone.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function subscribe( $domain, $product_id, $phone ) {
		$domain     = WebinoCRM_License_Manager::normalize_domain( $domain );
		$product_id = max( 0, (int) $product_id );
		$phone      = WebinoCRM_Sms_Template_Service::normalize_phone( $phone );
		if ( '' === $phone ) {
			return new WP_Error( 'invalid_phone', __( 'Phone is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = WebinoCRM_Sms_Install::table( 'newsletter_subscribers' );
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE domain = %s AND product_id = %d AND phone = %s",
				$domain,
				$product_id,
				$phone
			)
		);
		if ( $exists ) {
			$wpdb->update( $table, array( 'status' => 'active', 'opt_in_at' => current_time( 'mysql' ) ), array( 'id' => $exists ) );
			return array( 'ok' => true, 'id' => $exists, 'updated' => true );
		}
		$wpdb->insert(
			$table,
			array(
				'domain'     => $domain,
				'product_id' => $product_id,
				'phone'      => $phone,
				'status'     => 'active',
				'opt_in_at'  => current_time( 'mysql' ),
			)
		);
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * @param string $domain Domain.
	 * @param int    $product_id Product filter.
	 * @param int    $page Page.
	 * @param int    $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_subscribers( $domain, $product_id = 0, $page = 1, $limit = 50 ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$table  = WebinoCRM_Sms_Install::table( 'newsletter_subscribers' );
		$offset = max( 0, ( (int) $page - 1 ) * (int) $limit );
		if ( $product_id > 0 ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE domain = %s AND product_id = %d AND status = 'active' ORDER BY id DESC LIMIT %d OFFSET %d",
					$domain,
					$product_id,
					(int) $limit,
					$offset
				),
				ARRAY_A
			) ?: array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE domain = %s AND status = 'active' ORDER BY id DESC LIMIT %d OFFSET %d",
				$domain,
				(int) $limit,
				$offset
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Soft-delete / unsubscribe a newsletter row for a domain.
	 *
	 * @param string $domain Domain.
	 * @param int    $id Subscriber id.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function unsubscribe( $domain, $id ) {
		global $wpdb;
		$domain = WebinoCRM_License_Manager::normalize_domain( $domain );
		$id     = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Subscriber id is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$table = WebinoCRM_Sms_Install::table( 'newsletter_subscribers' );
		$updated = $wpdb->update(
			$table,
			array( 'status' => 'inactive' ),
			array(
				'id'     => $id,
				'domain' => $domain,
			)
		);
		if ( false === $updated ) {
			return new WP_Error( 'db_error', __( 'Could not update subscriber.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		if ( 0 === (int) $updated ) {
			return new WP_Error( 'not_found', __( 'Subscriber not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		return array( 'ok' => true, 'id' => $id );
	}

	/**
	 * @param string              $domain Domain.
	 * @param int                 $product_id Product id (0 = all subscribers).
	 * @param string              $message Message template.
	 * @param array<string,string> $extra_vars Extra shortcode vars.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function send_campaign( $domain, $product_id, $message, array $extra_vars = array() ) {
		$domain     = WebinoCRM_License_Manager::normalize_domain( $domain );
		$product_id = max( 0, (int) $product_id );
		$message    = trim( (string) $message );
		if ( '' === $message ) {
			return new WP_Error( 'empty_message', __( 'Message is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$rows = self::list_subscribers( $domain, $product_id, 1, 500 );
		if ( ! $rows ) {
			return array( 'ok' => true, 'sent' => 0, 'message' => __( 'No subscribers.', 'webinocrm' ) );
		}

		$from   = WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, false );
		$sent   = 0;
		$errors = array();

		foreach ( $rows as $row ) {
			$vars = array_merge(
				array(
					'site_name' => (string) ( $extra_vars['site_name'] ?? '' ),
					'site_url'  => (string) ( $extra_vars['site_url'] ?? 'https://' . $domain ),
				),
				$extra_vars
			);
			$body   = WebinoCRM_Sms_Template_Service::render( $message, $vars );
			$result = WebinoCRM_ModirPayamak_Manager::customer_send(
				$domain,
				array(
					'sending_type' => 'webservice',
					'from_number'  => $from,
					'message'      => $body,
					'params'       => array( 'recipients' => array( (string) $row['phone'] ) ),
				)
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				continue;
			}
			++$sent;
		}

		return array(
			'ok'     => true,
			'sent'   => $sent,
			'total'  => count( $rows ),
			'errors' => $errors,
		);
	}
}
