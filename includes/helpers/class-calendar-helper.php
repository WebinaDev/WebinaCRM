<?php
/**
 * Calendar Helper Class
 * 
 * Handles calendar initialization based on language (Persian = Jalali, English = Gregorian)
 * 
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Calendar_Helper {

	/**
	 * Get current language locale
	 *
	 * @return string 'fa_IR' or 'en_US'
	 */
	public static function get_locale() {
		// Priority 1: User meta (saved preference)
		if ( is_user_logged_in() ) {
			$user_lang = get_user_meta( get_current_user_id(), 'webino_language', true );
			if ( ! empty( $user_lang ) ) {
				if ( $user_lang === 'en' ) {
					return 'en_US';
				} elseif ( $user_lang === 'fa' ) {
					return 'fa_IR';
				}
			}
		}

		// Priority 2: Cookie (temporary preference)
		$cookie_lang = isset( $_COOKIE['webino_language'] ) ? sanitize_text_field( $_COOKIE['webino_language'] ) : '';
		if ( $cookie_lang === 'en' ) {
			return 'en_US';
		} elseif ( $cookie_lang === 'fa' ) {
			return 'fa_IR';
		}

		// Priority 3: WordPress locale (only if explicitly Persian)
		$locale = get_locale();
		if ( strpos( $locale, 'fa' ) !== false || strpos( $locale, 'fa_IR' ) !== false ) {
			return 'fa_IR';
		}

		// Default: English
		return 'en_US';
	}

	/**
	 * Check if current language is Persian
	 *
	 * @return bool
	 */
	private static function is_persian() {
		return self::get_locale() === 'fa_IR';
	}

	/**
	 * Get calendar configuration based on language
	 *
	 * @return array Calendar configuration
	 */
	public static function get_calendar_config() {
		if ( self::is_persian() ) {
			return array(
				'type'        => 'jalali',
				'locale'      => 'fa',
				'dateFormat' => 'Y/m/d',
				'timeFormat' => 'H:i',
				'firstDay'    => 6, // Saturday
				'direction'   => 'rtl',
			);
		} else {
			return array(
				'type'        => 'gregorian',
				'locale'      => 'en',
				'dateFormat'  => 'Y/m/d',
				'timeFormat'  => 'g:i A',
				'firstDay'    => 0, // Sunday
				'direction'   => 'ltr',
			);
		}
	}

	/**
	 * Initialize calendar JavaScript
	 *
	 * @param string $selector CSS selector for calendar input
	 * @param array  $options Additional options
	 * @return string JavaScript code
	 */
	public static function init_calendar( $selector, $options = array() ) {
		$config = self::get_calendar_config();
		$default_options = array(
			'selector'    => $selector,
			'dateFormat'  => $config['dateFormat'],
			'timeFormat'  => $config['timeFormat'],
			'firstDay'    => $config['firstDay'],
			'direction'   => $config['direction'],
		);

		$options = wp_parse_args( $options, $default_options );

		if ( self::is_persian() ) {
			return self::init_persian_datepicker( $options );
		} else {
			return self::init_flatpickr( $options );
		}
	}

	/**
	 * Initialize Persian Datepicker
	 *
	 * @param array $options Options
	 * @return string JavaScript code
	 */
	private static function init_persian_datepicker( $options ) {
		$js = sprintf(
			"jQuery('%s').persianDatepicker({
				observer: true,
				format: '%s',
				altField: '%s',
				altFormat: 'YYYY/MM/DD',
				timePicker: %s,
				timePickerFormat: '%s',
				calendarType: 'persian',
				initialValue: false,
				initialValueType: 'persian',
				onlyTimePicker: false,
				onlySelectOnDate: false,
				calendar: {
					persian: {
						locale: 'fa',
						showHint: true,
						leapYearMode: 'algorithmic'
					}
				},
				navigator: {
					enabled: true,
					scroll: {
						enabled: true
					},
					text: {
						btnNextText: '%s',
						btnPrevText: '%s'
					}
				},
				toolbox: {
					enabled: true,
					calendarSwitch: {
						enabled: true,
						format: 'YYYY/MM/DD'
					},
					todayButton: {
						enabled: true,
						text: '%s'
					},
					submitButton: {
						enabled: true,
						text: '%s'
					},
					clearButton: {
						enabled: true,
						text: '%s'
					}
				}
			});",
			esc_js( $options['selector'] ),
			esc_js( $options['dateFormat'] ),
			esc_js( $options['selector'] ),
			isset( $options['timePicker'] ) && $options['timePicker'] ? 'true' : 'false',
			esc_js( $options['timeFormat'] ),
			esc_js( __( 'بعدی', 'webinocrm' ) ),
			esc_js( __( 'قبلی', 'webinocrm' ) ),
			esc_js( __( 'امروز', 'webinocrm' ) ),
			esc_js( __( 'تأیید', 'webinocrm' ) ),
			esc_js( __( 'پاک کردن', 'webinocrm' ) )
		);

		return $js;
	}

	/**
	 * Initialize Flatpickr (for English/Gregorian)
	 *
	 * @param array $options Options
	 * @return string JavaScript code
	 */
	private static function init_flatpickr( $options ) {
		$js = sprintf(
			"flatpickr('%s', {
				dateFormat: '%s',
				time_24hr: %s,
				locale: 'en',
				firstDayOfWeek: %d,
				enableTime: %s,
				timeFormat: '%s',
				altInput: true,
				altFormat: 'Y/m/d',
				allowInput: true,
				clickOpens: true,
				theme: 'default'
			});",
			esc_js( $options['selector'] ),
			esc_js( $options['dateFormat'] ),
			$options['timeFormat'] === 'H:i' ? 'true' : 'false',
			$options['firstDay'],
			isset( $options['timePicker'] ) && $options['timePicker'] ? 'true' : 'false',
			esc_js( $options['timeFormat'] )
		);

		return $js;
	}

	/**
	 * Get calendar assets (CSS/JS) to enqueue
	 *
	 * @return array Array of assets to enqueue
	 */
	public static function get_calendar_assets() {
		if ( self::is_persian() ) {
			return array(
				'css' => array(
					WEBINOCRM_PLUGIN_URL . 'assets/css/persianDatepicker-default.css',
				),
				'js'  => array(
					WEBINOCRM_PLUGIN_URL . 'assets/js/persianDatepicker.min.js',
				),
			);
		} else {
			return array(
				'css' => array(
					WEBINOCRM_PLUGIN_URL . 'assets/libs/flatpickr/flatpickr.min.css',
				),
				'js'  => array(
					WEBINOCRM_PLUGIN_URL . 'assets/libs/flatpickr/flatpickr.min.js',
				),
			);
		}
	}

	/**
	 * Enqueue calendar assets
	 *
	 * @return void
	 */
	public static function enqueue_calendar_assets() {
		$assets = self::get_calendar_assets();

		foreach ( $assets['css'] as $css ) {
			wp_enqueue_style( 'webinocrm-calendar-css', $css, array(), WEBINOCRM_VERSION );
		}

		foreach ( $assets['js'] as $js ) {
			wp_enqueue_script( 'webinocrm-calendar-js', $js, array( 'jquery' ), WEBINOCRM_VERSION, true );
		}
	}
}

