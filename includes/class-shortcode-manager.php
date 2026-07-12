<?php
/**
 * Shortcodes: legacy CRM UI blocks now point users to the React SPA.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Shortcode_Manager {

	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	public function register_shortcodes() {
		$notice = array( $this, 'render_spa_redirect_notice' );

		add_shortcode( 'webino_dashboard', $notice );
		add_shortcode( 'projects', $notice );
		add_shortcode( 'contracts', $notice );
		add_shortcode( 'webino_invoices', $notice );
		add_shortcode( 'pro_invoices', $notice );
		add_shortcode( 'appointments', $notice );
		add_shortcode( 'tickets', $notice );
		add_shortcode( 'tasks', $notice );
		add_shortcode( 'consultations', $notice );
		add_shortcode( 'webino_customers', $notice );
		add_shortcode( 'webino_staff', $notice );
		add_shortcode( 'webino_subscriptions', $notice );
		add_shortcode( 'webino_reports', $notice );
		add_shortcode( 'webino_settings', $notice );
		add_shortcode( 'crm_logs', $notice );
		add_shortcode( 'leads', $notice );

		// Login shortcode is registered on WebinoCRM_Login_Page only.
	}

	/**
	 * Legacy shortcodes: show a link to the SPA dashboard (or login).
	 *
	 * @return string
	 */
	public function render_spa_redirect_notice() {
		if ( ! is_user_logged_in() ) {
			return '<p class="webinocrm-spa-notice"><a href="' . esc_url( home_url( '/dashboard/login' ) ) . '">' . esc_html__( 'ورود', 'webinocrm' ) . '</a></p>';
		}
		return '<p class="webinocrm-spa-notice"><a href="' . esc_url( home_url( '/dashboard' ) ) . '">' . esc_html__( 'رفتن به داشبورد', 'webinocrm' ) . '</a></p>';
	}
}
