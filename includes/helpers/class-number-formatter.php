<?php
/**
 * Number Formatter Helper Class
 * 
 * Handles number formatting based on language (Persian = Persian digits, English = English digits)
 * 
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Number_Formatter {

	/**
	 * Persian digits
	 */
	const PERSIAN_DIGITS = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	/**
	 * English digits
	 */
	const ENGLISH_DIGITS = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

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
	 * Format number based on current language
	 *
	 * @param int|float|string $number Number to format
	 * @param int              $decimals Number of decimal places
	 * @param string          $decimal_separator Decimal separator
	 * @param string          $thousands_separator Thousands separator
	 * @return string Formatted number
	 */
	public static function format_number( $number, $decimals = 0, $decimal_separator = '.', $thousands_separator = ',' ) {
		$number = floatval( $number );
		$formatted = number_format( $number, $decimals, $decimal_separator, $thousands_separator );

		if ( self::is_persian() ) {
			return self::english_to_persian( $formatted );
		}

		return $formatted;
	}

	/**
	 * Convert English digits to Persian
	 *
	 * @param string $text Text containing English digits
	 * @return string Text with Persian digits
	 */
	public static function english_to_persian( $text ) {
		return str_replace( self::ENGLISH_DIGITS, self::PERSIAN_DIGITS, $text );
	}

	/**
	 * Convert Persian digits to English
	 *
	 * @param string $text Text containing Persian digits
	 * @return string Text with English digits
	 */
	public static function persian_to_english( $text ) {
		return str_replace( self::PERSIAN_DIGITS, self::ENGLISH_DIGITS, $text );
	}

	/**
	 * Format currency
	 *
	 * @param int|float $amount Amount
	 * @param string   $currency Currency symbol (default: 'تومان' for Persian, '$' for English)
	 * @param bool     $show_decimals Show decimal places
	 * @return string Formatted currency
	 */
	public static function format_currency( $amount, $currency = '', $show_decimals = false ) {
		if ( empty( $currency ) ) {
			$currency = self::is_persian() ? __( 'تومان', 'webinocrm' ) : '$';
		}

		$decimals = $show_decimals ? 2 : 0;
		$formatted = self::format_number( $amount, $decimals );

		if ( self::is_persian() ) {
			return $formatted . ' ' . $currency;
		} else {
			return $currency . $formatted;
		}
	}

	/**
	 * Format number with thousand separator
	 *
	 * @param int|float $number Number to format
	 * @return string Formatted number
	 */
	public static function format_with_separator( $number ) {
		return self::format_number( $number, 0, '.', ',' );
	}

	/**
	 * Format percentage
	 *
	 * @param float $value Percentage value (0-100)
	 * @param int   $decimals Decimal places
	 * @return string Formatted percentage
	 */
	public static function format_percentage( $value, $decimals = 1 ) {
		$formatted = number_format( $value, $decimals, '.', '' );

		if ( self::is_persian() ) {
			return self::english_to_persian( $formatted ) . '%';
		}

		return $formatted . '%';
	}

	/**
	 * Format file size
	 *
	 * @param int $bytes File size in bytes
	 * @return string Formatted file size
	 */
	public static function format_file_size( $bytes ) {
		$units = self::is_persian() 
			? array( __( 'بایت', 'webinocrm' ), __( 'کیلوبایت', 'webinocrm' ), __( 'مگابایت', 'webinocrm' ), __( 'گیگابایت', 'webinocrm' ) )
			: array( 'B', 'KB', 'MB', 'GB' );

		if ( $bytes == 0 ) {
			return '0 ' . $units[0];
		}

		$i = floor( log( $bytes, 1024 ) );
		$size = round( $bytes / pow( 1024, $i ), 2 );

		return self::format_number( $size, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Format number for display in charts
	 *
	 * @param int|float $number Number to format
	 * @param bool     $compact Use compact notation (K, M, B)
	 * @return string Formatted number
	 */
	public static function format_for_chart( $number, $compact = false ) {
		if ( ! $compact ) {
			return self::format_with_separator( $number );
		}

		$number = floatval( $number );

		if ( self::is_persian() ) {
			if ( $number >= 1000000000 ) {
				return self::format_number( $number / 1000000000, 1 ) . __( 'میلیارد', 'webinocrm' );
			} elseif ( $number >= 1000000 ) {
				return self::format_number( $number / 1000000, 1 ) . __( 'میلیون', 'webinocrm' );
			} elseif ( $number >= 1000 ) {
				return self::format_number( $number / 1000, 1 ) . __( 'هزار', 'webinocrm' );
			}
		} else {
			if ( $number >= 1000000000 ) {
				return self::format_number( $number / 1000000000, 1 ) . 'B';
			} elseif ( $number >= 1000000 ) {
				return self::format_number( $number / 1000000, 1 ) . 'M';
			} elseif ( $number >= 1000 ) {
				return self::format_number( $number / 1000, 1 ) . 'K';
			}
		}

		return self::format_number( $number );
	}

	/**
	 * Check if current language is Persian (public method)
	 *
	 * @return bool
	 */
	public static function is_persian() {
		return self::get_locale() === 'fa_IR';
	}
}

