<?php
/**
 * ModirPayamak admin AJAX handler.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPA + Edge proxy for ModirPayamak panel.
 */
class WebinoCRM_ModirPayamak_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_webinocrm_modirpayamak_dashboard', array( $this, 'dashboard' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_proxy', array( $this, 'proxy' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_get_customers', array( $this, 'get_customers' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_adjust_balance', array( $this, 'adjust_balance' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_get_packages', array( $this, 'get_packages' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_save_package', array( $this, 'save_package' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_delete_package', array( $this, 'delete_package' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_get_orders', array( $this, 'get_orders' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_admin_send', array( $this, 'admin_send' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_get_messages', array( $this, 'get_messages' ) );
	}

	/**
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'security' );
		if ( ! function_exists( 'webinocrm_user_can_manage_modirpayamak' ) || ! webinocrm_user_can_manage_modirpayamak() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
	}

	/**
	 * @return void
	 */
	public function dashboard() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'dashboard' ) );
	}

	/**
	 * Generic Edge API proxy.
	 *
	 * @return void
	 */
	public function proxy() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'proxy' ) );
	}

	/**
	 * @return void
	 */
	public function get_customers() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'customers' ) );
	}

	/**
	 * @return void
	 */
	public function adjust_balance() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'adjust_balance' ) );
	}

	/**
	 * @return void
	 */
	public function get_packages() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'packages' ) );
	}

	/**
	 * @return void
	 */
	public function save_package() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'package_save' ) );
	}

	/**
	 * @return void
	 */
	public function delete_package() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'package_delete' ) );
	}

	/**
	 * @return void
	 */
	public function get_orders() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'orders' ) );
	}

	/**
	 * @return void
	 */
	public function admin_send() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'send' ) );
	}

	/**
	 * @return void
	 */
	public function get_messages() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'messages' ) );
	}
}
