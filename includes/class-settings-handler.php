<?php
/**
 * Central settings storage in webinocrm_settings with legacy option fallback.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Settings_Handler {

	private const OPTION_KEY = 'webinocrm_settings';

	/**
	 * Legacy wp_options keys mapped to unified setting keys.
	 *
	 * @var array<string, string>
	 */
	private static $legacy_option_map = array(
		'primary_color'       => 'webinocrm_primary_color',
		'font_family'         => 'webinocrm_font_family',
		'font_size'           => 'webinocrm_font_size',
		'sms_service'         => 'webinocrm_sms_service',
		'sms_username'        => 'webinocrm_sms_username',
		'sms_password'        => 'webinocrm_sms_password',
		'sms_sender'          => 'webinocrm_sms_sender',
		'zarinpal_merchant'   => 'webinocrm_zarinpal_merchant',
		'zarinpal_sandbox'    => 'webinocrm_zarinpal_sandbox',
		'telegram_bot_token'  => 'webinocrm_telegram_bot_token',
		'telegram_chat_id'    => 'webinocrm_telegram_chat_id',
	);

	/**
	 * Allowed keys per settings tab.
	 *
	 * @var array<string, string[]>
	 */
	private static $tab_keys = array(
		'authentication'    => array(
			'login_phone_pattern',
			'login_phone_length',
			'login_sms_template',
			'otp_expiry_minutes',
			'otp_max_attempts',
			'otp_length',
			'melipayamak_login_pattern',
			'parsgreen_login_template',
			'enable_password_login',
			'enable_sms_login',
			'login_redirect_url',
			'logout_redirect_url',
			'force_logout_inactive',
			'inactive_timeout_minutes',
			'login_email_otp_subject',
			'login_email_otp_body',
		),
		'style'             => array( 'primary_color', 'font_family', 'font_size' ),
		'sms'               => array( 'sms_service', 'sms_username', 'sms_password', 'sms_sender' ),
		'modirpayamak'      => array( 'modirpayamak_api_key', 'modirpayamak_default_from', 'modirpayamak_enabled', 'modirpayamak_sms_price_per_unit', 'modirpayamak_sms_tax_percent', 'modirpayamak_sms_surcharge_rial', 'modirpayamak_reseller_credit_alert' ),
		'payment'           => array( 'zarinpal_merchant', 'zarinpal_sandbox' ),
		'notifications'     => array( 'telegram_bot_token', 'telegram_chat_id' ),
		'visitor_tracking'  => array( 'enable_visitor_statistics' ),
	);

	/**
	 * Default values for tab fields.
	 *
	 * @var array<string, mixed>
	 */
	private static $defaults = array(
		'enable_visitor_statistics' => '1',
		'login_phone_pattern'         => '^09[0-9]{9}$',
		'login_phone_length'          => 11,
		'login_sms_template'          => 'کد ورود شما: %CODE%',
		'otp_expiry_minutes'          => 5,
		'otp_max_attempts'            => 3,
		'otp_length'                  => 6,
		'primary_color'               => '#845adf',
		'font_family'                 => 'IRANSans',
		'font_size'                   => 14,
		'zarinpal_sandbox'            => '0',
		'sms_service'                 => '',
		'modirpayamak_enabled'        => '0',
		'modirpayamak_sms_price_per_unit' => 500,
		'modirpayamak_sms_tax_percent' => 10,
		'modirpayamak_sms_surcharge_rial' => 40,
		'modirpayamak_reseller_credit_alert' => 100000,
	);

	public static function get_all_settings() {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::$defaults );
		return self::hydrate_from_legacy( $settings );
	}

	public static function get_setting( $key, $default = '' ) {
		$settings = self::get_all_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public static function update_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return false;
		}
		$previous = get_option( self::OPTION_KEY, array() );
		$result   = update_option( self::OPTION_KEY, $settings );
		if ( $result ) {
			return true;
		}
		// WordPress returns false when the value is unchanged — treat as success.
		return maybe_serialize( $previous ) === maybe_serialize( $settings );
	}

	/**
	 * Whether visitor tracking is enabled.
	 */
	public static function is_visitor_tracking_enabled() {
		return (string) self::get_setting( 'enable_visitor_statistics', '1' ) === '1';
	}

	/**
	 * Get normalized settings for a dashboard tab.
	 *
	 * @param string $tab Tab slug.
	 * @return array|\WP_Error
	 */
	public static function get_tab_settings( $tab ) {
		$tab = sanitize_key( $tab );
		if ( ! isset( self::$tab_keys[ $tab ] ) ) {
			return new WP_Error( 'invalid_tab', __( 'تب تنظیمات نامعتبر است.', 'webinocrm' ) );
		}

		$all = self::get_all_settings();

		switch ( $tab ) {
			case 'authentication':
				return array(
					'login_phone_pattern'        => $all['login_phone_pattern'] ?? self::$defaults['login_phone_pattern'],
					'login_phone_length'         => (int) ( $all['login_phone_length'] ?? self::$defaults['login_phone_length'] ),
					'login_sms_template'         => $all['login_sms_template'] ?? self::$defaults['login_sms_template'],
					'otp_expiry_minutes'         => (int) ( $all['otp_expiry_minutes'] ?? self::$defaults['otp_expiry_minutes'] ),
					'otp_max_attempts'           => (int) ( $all['otp_max_attempts'] ?? self::$defaults['otp_max_attempts'] ),
					'otp_length'                 => (int) ( $all['otp_length'] ?? self::$defaults['otp_length'] ),
					'enable_password_login'      => ! empty( $all['enable_password_login'] ),
					'enable_sms_login'           => ! empty( $all['enable_sms_login'] ),
					'login_redirect_url'         => $all['login_redirect_url'] ?? '',
					'logout_redirect_url'        => $all['logout_redirect_url'] ?? '',
					'force_logout_inactive'      => ! empty( $all['force_logout_inactive'] ),
					'inactive_timeout_minutes'   => (int) ( $all['inactive_timeout_minutes'] ?? 30 ),
				);

			case 'style':
				return array(
					'primary_color' => (string) ( $all['primary_color'] ?? self::$defaults['primary_color'] ),
					'font_family'   => (string) ( $all['font_family'] ?? self::$defaults['font_family'] ),
					'font_size'     => (int) ( $all['font_size'] ?? self::$defaults['font_size'] ),
				);

			case 'payment':
				return array(
					'zarinpal_merchant' => (string) ( $all['zarinpal_merchant'] ?? '' ),
					'zarinpal_sandbox'  => (string) ( $all['zarinpal_sandbox'] ?? '0' ) === '1',
				);

			case 'sms':
				return array(
					'sms_service'  => (string) ( $all['sms_service'] ?? '' ),
					'sms_username' => (string) ( $all['sms_username'] ?? '' ),
					'sms_password' => (string) ( $all['sms_password'] ?? '' ),
					'sms_sender'   => (string) ( $all['sms_sender'] ?? '' ),
				);

			case 'modirpayamak':
				return array(
					'modirpayamak_api_key'              => (string) ( $all['modirpayamak_api_key'] ?? '' ),
					'modirpayamak_default_from'         => (string) ( $all['modirpayamak_default_from'] ?? '' ),
					'modirpayamak_enabled'              => (string) ( $all['modirpayamak_enabled'] ?? '0' ) === '1',
					'modirpayamak_sms_price_per_unit'   => (float) ( $all['modirpayamak_sms_price_per_unit'] ?? 500 ),
					'modirpayamak_sms_tax_percent'      => (float) ( $all['modirpayamak_sms_tax_percent'] ?? 10 ),
					'modirpayamak_sms_surcharge_rial'   => (float) ( $all['modirpayamak_sms_surcharge_rial'] ?? 40 ),
					'modirpayamak_reseller_credit_alert' => (float) ( $all['modirpayamak_reseller_credit_alert'] ?? 100000 ),
				);

			case 'notifications':
				return array(
					'telegram_bot_token' => (string) ( $all['telegram_bot_token'] ?? '' ),
					'telegram_chat_id'   => (string) ( $all['telegram_chat_id'] ?? '' ),
				);

			case 'visitor_tracking':
				return array(
					'tracking_enabled' => self::is_visitor_tracking_enabled(),
				);

			default:
				return array();
		}
	}

	/**
	 * Save whitelisted fields for a tab into webinocrm_settings (+ sync legacy options).
	 *
	 * @param string $tab Tab slug.
	 * @param array  $payload Raw POST-like data.
	 * @return true|\WP_Error
	 */
	public static function save_tab_settings( $tab, array $payload ) {
		$tab = sanitize_key( $tab );
		if ( ! isset( self::$tab_keys[ $tab ] ) ) {
			return new WP_Error( 'invalid_tab', __( 'تب تنظیمات نامعتبر است.', 'webinocrm' ) );
		}

		$settings = self::get_all_settings();
		$keys     = self::$tab_keys[ $tab ];

		if ( 'authentication' === $tab && array_key_exists( 'login_phone_pattern', $payload ) ) {
			$pattern = sanitize_text_field( $payload['login_phone_pattern'] );
			if ( @preg_match( '/' . $pattern . '/', '' ) === false ) {
				return new WP_Error( 'invalid_pattern', __( 'پترن Regex نامعتبر است.', 'webinocrm' ) );
			}
		}

		foreach ( $keys as $key ) {
			if ( 'visitor_tracking' === $tab && 'enable_visitor_statistics' !== $key ) {
				continue;
			}
			if ( ! array_key_exists( $key, $payload ) ) {
				continue;
			}
			$settings[ $key ] = self::sanitize_tab_value( $tab, $key, $payload );
		}

		if ( ! self::update_settings( $settings ) ) {
			return new WP_Error( 'save_failed', __( 'خطا در ذخیره تنظیمات.', 'webinocrm' ) );
		}

		self::sync_legacy_options( $tab, $settings );

		return true;
	}

	/**
	 * @param string $tab
	 * @param string $key
	 * @param array  $payload
	 * @return mixed
	 */
	/**
	 * Interpret POST/AJAX values as boolean (handles "0", "false", false, etc.).
	 *
	 * @param array  $payload
	 * @param string $key
	 * @return bool
	 */
	private static function is_truthy_post( array $payload, $key ) {
		if ( ! array_key_exists( $key, $payload ) ) {
			return false;
		}
		$value = $payload[ $key ];
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( '0', 'false', 'off', 'no' ), true ) ) {
			return false;
		}
		return $value !== '';
	}

	private static function sanitize_tab_value( $tab, $key, array $payload ) {
		if ( 'visitor_tracking' === $tab && 'enable_visitor_statistics' === $key ) {
			return self::is_truthy_post( $payload, $key ) ? '1' : '0';
		}

		if ( in_array( $key, array( 'enable_password_login', 'enable_sms_login', 'force_logout_inactive' ), true ) ) {
			return self::is_truthy_post( $payload, $key ) ? 1 : 0;
		}

		if ( 'zarinpal_sandbox' === $key ) {
			return self::is_truthy_post( $payload, $key ) ? '1' : '0';
		}

		if ( 'modirpayamak_enabled' === $key ) {
			return self::is_truthy_post( $payload, $key ) ? '1' : '0';
		}

		if ( in_array( $key, array( 'modirpayamak_sms_price_per_unit', 'modirpayamak_sms_tax_percent', 'modirpayamak_sms_surcharge_rial', 'modirpayamak_reseller_credit_alert' ), true ) ) {
			return (float) ( $payload[ $key ] ?? 0 );
		}

		if ( in_array( $key, array( 'login_phone_length', 'otp_expiry_minutes', 'otp_max_attempts', 'otp_length', 'inactive_timeout_minutes', 'font_size' ), true ) ) {
			return (int) ( $payload[ $key ] ?? 0 );
		}

		if ( in_array( $key, array( 'login_redirect_url', 'logout_redirect_url' ), true ) ) {
			return esc_url_raw( $payload[ $key ] ?? '' );
		}

		if ( in_array( $key, array( 'login_sms_template', 'parsgreen_login_template', 'login_email_otp_body' ), true ) ) {
			return sanitize_textarea_field( $payload[ $key ] ?? '' );
		}

		if ( 'primary_color' === $key || 'category_color' === $key ) {
			return sanitize_hex_color( $payload[ $key ] ?? '' ) ?: ( self::$defaults['primary_color'] ?? '#845adf' );
		}

		return sanitize_text_field( $payload[ $key ] ?? '' );
	}

	/**
	 * Merge legacy standalone options into settings array when missing.
	 *
	 * @param array $settings
	 * @return array
	 */
	private static function hydrate_from_legacy( array $settings ) {
		foreach ( self::$legacy_option_map as $key => $option_name ) {
			if ( ! isset( $settings[ $key ] ) || $settings[ $key ] === '' ) {
				$legacy = get_option( $option_name, null );
				if ( null !== $legacy && $legacy !== false && $legacy !== '' ) {
					$settings[ $key ] = $legacy;
				}
			}
		}
		return $settings;
	}

	/**
	 * Write legacy options for backward compatibility with older code paths.
	 *
	 * @param string $tab
	 * @param array  $settings
	 */
	private static function sync_legacy_options( $tab, array $settings ) {
		$keys = self::$tab_keys[ $tab ] ?? array();
		foreach ( $keys as $key ) {
			if ( ! isset( self::$legacy_option_map[ $key ] ) ) {
				continue;
			}
			$value = $settings[ $key ] ?? '';
			if ( 'font_size' === $key ) {
				$value = (int) $value;
			}
			update_option( self::$legacy_option_map[ $key ], $value );
		}
	}
}
