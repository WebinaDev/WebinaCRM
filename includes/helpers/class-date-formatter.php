<?php
/**
 * Date Formatter Helper Class
 * 
 * Handles date formatting based on language (Persian = Jalali, English = Gregorian)
 * 
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Date_Formatter {

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
	public static function is_persian() {
		return self::get_locale() === 'fa_IR';
	}

	/**
	 * Format date based on current language
	 *
	 * @param string|int $date Date string or timestamp
	 * @param string     $format Date format (default: 'Y/m/d')
	 * @return string Formatted date
	 */
	public static function format_date( $date = '', $format = 'Y/m/d' ) {
		if ( empty( $date ) ) {
			$timestamp = current_time( 'timestamp' );
		} elseif ( is_numeric( $date ) ) {
			$timestamp = $date;
		} else {
			$timestamp = strtotime( $date );
		}

		if ( self::is_persian() ) {
			return self::format_jalali_date( $timestamp, $format );
		} else {
			return date_i18n( $format, $timestamp );
		}
	}

	/**
	 * Format Jalali (Persian) date
	 *
	 * @param int    $timestamp Unix timestamp
	 * @param string $format Date format
	 * @return string Formatted Jalali date
	 */
	private static function format_jalali_date( $timestamp, $format = 'Y/m/d' ) {
		if ( ! function_exists( 'jdate' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php';
		}

		// Convert format to jdate format
		$jdate_format = str_replace(
			array( 'Y', 'm', 'd', 'H', 'i', 's', 'F', 'l', 'D', 'M' ),
			array( 'Y', 'n', 'd', 'H', 'i', 's', 'F', 'l', 'D', 'M' ),
			$format
		);

		return jdate( $jdate_format, $timestamp, '', 'Asia/Tehran', 'fa' );
	}

	/**
	 * Get current date with appropriate format
	 *
	 * @param string $format Date format
	 * @return string Current date
	 */
	public static function get_current_date( $format = 'Y/m/d' ) {
		return self::format_date( '', $format );
	}

	/**
	 * Convert Gregorian date to Jalali
	 *
	 * @param int $year Year
	 * @param int $month Month
	 * @param int $day Day
	 * @return array [year, month, day]
	 */
	public static function convert_to_jalali( $year, $month, $day ) {
		if ( ! function_exists( 'gregorian_to_jalali' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php';
		}

		return gregorian_to_jalali( $year, $month, $day );
	}

	/**
	 * Convert Jalali date to Gregorian
	 *
	 * @param int $year Year
	 * @param int $month Month
	 * @param int $day Day
	 * @return array [year, month, day]
	 */
	public static function convert_to_gregorian( $year, $month, $day ) {
		if ( ! function_exists( 'jalali_to_gregorian' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php';
		}

		return jalali_to_gregorian( $year, $month, $day );
	}

	/**
	 * Human readable time difference (Jalali for Persian)
	 *
	 * @param int|string $from From timestamp or date string
	 * @param int|string $to To timestamp or date string (default: now)
	 * @return string Human readable time difference
	 */
	public static function human_time_diff( $from, $to = '' ) {
		if ( empty( $to ) ) {
			$to = current_time( 'timestamp' );
		}

		if ( ! is_numeric( $from ) ) {
			$from = strtotime( $from );
		}

		if ( ! is_numeric( $to ) ) {
			$to = strtotime( $to );
		}

		$diff = $to - $from;

		if ( self::is_persian() ) {
			return self::human_time_diff_jalali( $diff );
		} else {
			return human_time_diff( $from, $to );
		}
	}

	/**
	 * Human readable time difference in Persian
	 *
	 * @param int $diff Time difference in seconds
	 * @return string Persian time difference
	 */
	private static function human_time_diff_jalali( $diff ) {
		$minute = 60;
		$hour   = 60 * $minute;
		$day    = 24 * $hour;
		$week   = 7 * $day;
		$month  = 30 * $day;
		$year   = 365 * $day;

		if ( $diff < $minute ) {
			return __( 'چند لحظه پیش', 'webinocrm' );
		} elseif ( $diff < $hour ) {
			$minutes = floor( $diff / $minute );
			return sprintf( _n( '%d دقیقه پیش', '%d دقیقه پیش', $minutes, 'webinocrm' ), $minutes );
		} elseif ( $diff < $day ) {
			$hours = floor( $diff / $hour );
			return sprintf( _n( '%d ساعت پیش', '%d ساعت پیش', $hours, 'webinocrm' ), $hours );
		} elseif ( $diff < $week ) {
			$days = floor( $diff / $day );
			return sprintf( _n( '%d روز پیش', '%d روز پیش', $days, 'webinocrm' ), $days );
		} elseif ( $diff < $month ) {
			$weeks = floor( $diff / $week );
			return sprintf( _n( '%d هفته پیش', '%d هفته پیش', $weeks, 'webinocrm' ), $weeks );
		} elseif ( $diff < $year ) {
			$months = floor( $diff / $month );
			return sprintf( _n( '%d ماه پیش', '%d ماه پیش', $months, 'webinocrm' ), $months );
		} else {
			$years = floor( $diff / $year );
			return sprintf( _n( '%d سال پیش', '%d سال پیش', $years, 'webinocrm' ), $years );
		}
	}

	/**
	 * Format date for display in dashboard
	 *
	 * @param string|int $date Date string or timestamp
	 * @param string     $format Format type: 'full', 'date', 'time', 'datetime'
	 * @return string Formatted date
	 */
	public static function format_for_display( $date = '', $format = 'full' ) {
		if ( self::is_persian() ) {
			switch ( $format ) {
				case 'full':
					return self::format_date( $date, 'l، j F Y - H:i' );
				case 'date':
					return self::format_date( $date, 'l، j F Y' );
				case 'time':
					return self::format_date( $date, 'H:i' );
				case 'datetime':
					return self::format_date( $date, 'Y/m/d H:i' );
				default:
					return self::format_date( $date, $format );
			}
		} else {
			switch ( $format ) {
				case 'full':
					return date_i18n( 'l, F j, Y - g:i A', is_numeric( $date ) ? $date : strtotime( $date ) );
				case 'date':
					return date_i18n( 'l, F j, Y', is_numeric( $date ) ? $date : strtotime( $date ) );
				case 'time':
					return date_i18n( 'g:i A', is_numeric( $date ) ? $date : strtotime( $date ) );
				case 'datetime':
					return date_i18n( 'Y/m/d g:i A', is_numeric( $date ) ? $date : strtotime( $date ) );
				default:
					return date_i18n( $format, is_numeric( $date ) ? $date : strtotime( $date ) );
			}
		}
	}
}

