<?php
/**
 * Footer Data Provider
 * 
 * Provides data for the footer component
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Footer Data Provider class
 */
class WebinoCRM_Footer_Data_Provider {

	/**
	 * Get copyright text
	 *
	 * @return string Copyright text.
	 */
	public static function get_copyright_text() {
		// Use white label copyright if available
		if (class_exists('WebinoCRM_White_Label')) {
			$copyright_text = WebinoCRM_White_Label::get_copyright_text();
			/**
			 * Filter footer copyright text
			 *
			 * @param string $copyright_text Copyright text with HTML.
			 */
			return apply_filters( 'webinocrm_footer_copyright_text', $copyright_text );
		}
		
		// Default copyright
		$year = gmdate( 'Y' );
		
		$company_name = __( 'Webino Institute', 'webinocrm' );
		$company_url  = 'https://webinoco.com';
		
		$company_link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener" class="text-primary fw-semibold">%2$s</a>',
			esc_url( $company_url ),
			esc_html( $company_name )
		);
		
		/* translators: 1: year, 2: heart icon, 3: company link */
		$copyright_text = sprintf(
			__( '%1$s © Designed with %2$s by %3$s', 'webinocrm' ),
			'<span id="year">' . esc_html( $year ) . '</span>',
			'<span class="bi bi-heart-fill text-danger"></span>',
			$company_link
		);
		
		/**
		 * Filter footer copyright text
		 *
		 * @param string $copyright_text Copyright text with HTML.
		 */
		return apply_filters( 'webinocrm_footer_copyright_text', $copyright_text );
	}

	/**
	 * Get footer links
	 *
	 * @return array Footer links.
	 */
	public static function get_footer_links() {
		$links = array();
		
		/**
		 * Filter footer links
		 *
		 * @param array $links Footer links array.
		 */
		return apply_filters( 'webinocrm_footer_links', $links );
	}

	/**
	 * Get footer credits
	 *
	 * @return string Footer credits text.
	 */
	public static function get_footer_credits() {
		$credits = __( 'All rights reserved', 'webinocrm' );
		
		/**
		 * Filter footer credits
		 *
		 * @param string $credits Footer credits text.
		 */
		return apply_filters( 'webinocrm_footer_credits', $credits );
	}
}

