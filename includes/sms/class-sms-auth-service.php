<?php
/**
 * Site OTP via ModirPayamak (login / register).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OTP generation and verification for licensed domains.
 */
final class WebinoCRM_Sms_Auth_Service {

	const PURPOSE_LOGIN    = 'login';
	const PURPOSE_REGISTER = 'register';

	/**
	 * @param string $domain Domain.
	 * @param string $phone Phone.
	 * @param string $purpose login|register.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function send_otp( $domain, $phone, $purpose = self::PURPOSE_LOGIN ) {
		$domain  = WebinoCRM_License_Manager::normalize_domain( $domain );
		$phone   = WebinoCRM_Sms_Template_Service::normalize_phone( $phone );
		$purpose = sanitize_key( $purpose );

		if ( '' === $phone ) {
			return new WP_Error( 'invalid_phone', __( 'Phone is required.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$settings = WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SITE );
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'disabled', __( 'Site SMS is disabled.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		if ( self::PURPOSE_REGISTER === $purpose && empty( $settings['otp_register_enabled'] ) ) {
			return new WP_Error( 'disabled', __( 'Registration OTP is disabled.', 'webinocrm' ), array( 'status' => 403 ) );
		}
		if ( self::PURPOSE_LOGIN === $purpose && empty( $settings['otp_login_enabled'] ) ) {
			return new WP_Error( 'disabled', __( 'Login OTP is disabled.', 'webinocrm' ), array( 'status' => 403 ) );
		}

		$length = max( 4, min( 8, (int) ( $settings['otp_length'] ?? 6 ) ) );
		$code   = (string) wp_rand( (int) str_pad( '1', $length, '0' ), (int) str_pad( '9', $length, '9' ) );
		$expiry = max( 1, (int) ( $settings['otp_expiry_minutes'] ?? 5 ) ) * MINUTE_IN_SECONDS;
		$key    = self::storage_key( $domain, $phone, $purpose );

		set_transient(
			$key,
			array(
				'code'     => $code,
				'attempts' => 0,
			),
			$expiry
		);

		$template_key = self::PURPOSE_REGISTER === $purpose ? 'otp_register_template' : 'otp_login_template';
		$template     = (string) ( $settings[ $template_key ] ?? WebinoCRM_Sms_Constants::default_site_settings()[ $template_key ] );
		$from         = WebinoCRM_Sms_Settings_Service::resolve_from_number( $domain, true );
		$use_pattern  = ! empty( $settings['use_pattern_for_otp'] );

		if ( $use_pattern ) {
			$event_key = self::PURPOSE_REGISTER === $purpose ? 'otp_register' : 'otp_login';
			$registry  = WebinoCRM_Sms_Pattern_Sync_Service::get_registry_row(
				$domain,
				WebinoCRM_Sms_Constants::SCOPE_SITE,
				$event_key
			);
			$pattern_code = (string) ( $registry['ippanel_code'] ?? '' );
			if ( '' === $pattern_code || WebinoCRM_Sms_Pattern_Sync_Service::STATUS_FAILED === ( $registry['sync_status'] ?? '' ) ) {
				$sync = WebinoCRM_Sms_Pattern_Sync_Service::sync_body(
					$domain,
					WebinoCRM_Sms_Constants::SCOPE_SITE,
					$event_key,
					$template,
					$pattern_code
				);
				if ( is_wp_error( $sync ) ) {
					delete_transient( $key );
					return $sync;
				}
				$pattern_code = (string) ( $sync['ippanel_code'] ?? '' );
			}
			if ( '' === $pattern_code ) {
				delete_transient( $key );
				return new WP_Error( 'pattern_missing', __( 'OTP pattern is not ready.', 'webinocrm' ), array( 'status' => 502 ) );
			}
			$result = WebinoCRM_ModirPayamak_Manager::customer_send(
				$domain,
				array(
					'sending_type' => 'pattern',
					'from_number'  => $from,
					'code'         => $pattern_code,
					'recipients'   => array( $phone ),
					'params'       => array( 'code' => $code ),
				)
			);
		} else {
			$message = WebinoCRM_Sms_Template_Service::render( $template, array( 'code' => $code ) );
			$result  = WebinoCRM_ModirPayamak_Manager::customer_send(
				$domain,
				array(
					'sending_type' => 'webservice',
					'from_number'  => $from,
					'message'      => $message,
					'params'       => array( 'recipients' => array( $phone ) ),
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			delete_transient( $key );
			return $result;
		}

		return array(
			'ok'      => true,
			'expires' => $expiry,
			'length'  => $length,
		);
	}

	/**
	 * @param string $domain Domain.
	 * @param string $phone Phone.
	 * @param string $code OTP code.
	 * @param string $purpose Purpose.
	 * @return true|WP_Error
	 */
	public static function verify_otp( $domain, $phone, $code, $purpose = self::PURPOSE_LOGIN ) {
		$domain  = WebinoCRM_License_Manager::normalize_domain( $domain );
		$phone   = WebinoCRM_Sms_Template_Service::normalize_phone( $phone );
		$purpose = sanitize_key( $purpose );
		$code    = preg_replace( '/\D+/', '', (string) $code );

		$key  = self::storage_key( $domain, $phone, $purpose );
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return new WP_Error( 'expired', __( 'OTP expired or not found.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$attempts = (int) ( $data['attempts'] ?? 0 );
		$settings = WebinoCRM_Sms_Settings_Service::get( $domain, WebinoCRM_Sms_Constants::SCOPE_SITE );
		$max      = max( 1, (int) ( $settings['otp_max_attempts'] ?? 3 ) );
		if ( $attempts >= $max ) {
			delete_transient( $key );
			return new WP_Error( 'too_many_attempts', __( 'Too many OTP attempts.', 'webinocrm' ), array( 'status' => 429 ) );
		}

		if ( (string) $data['code'] !== $code ) {
			$data['attempts'] = $attempts + 1;
			set_transient( $key, $data, 5 * MINUTE_IN_SECONDS );
			return new WP_Error( 'invalid_code', __( 'Invalid OTP code.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		delete_transient( $key );
		return true;
	}

	/**
	 * @param string $domain Domain.
	 * @param string $phone Phone.
	 * @param string $purpose Purpose.
	 * @return string
	 */
	private static function storage_key( $domain, $phone, $purpose ) {
		return 'webinocrm_sms_otp_' . md5( $domain . '|' . $phone . '|' . $purpose );
	}
}
