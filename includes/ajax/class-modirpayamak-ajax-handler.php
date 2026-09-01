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
		add_action( 'wp_ajax_webinocrm_modirpayamak_customer_ledger', array( $this, 'customer_ledger' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_ensure_customers', array( $this, 'ensure_customers' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_attach_number', array( $this, 'attach_number' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_detach_number', array( $this, 'detach_number' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_attach_pattern', array( $this, 'attach_pattern' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_detach_pattern', array( $this, 'detach_pattern' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_domain_pattern_registry', array( $this, 'domain_pattern_registry' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_domain_secretaries', array( $this, 'domain_secretaries' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_save_domain_secretary', array( $this, 'save_domain_secretary' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_delete_domain_secretary', array( $this, 'delete_domain_secretary' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_get_packages', array( $this, 'get_packages' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_save_package', array( $this, 'save_package' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_delete_package', array( $this, 'delete_package' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_get_tariffs', array( $this, 'get_tariffs' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_save_tariff', array( $this, 'save_tariff' ) );
		add_action( 'wp_ajax_webinocrm_modirpayamak_delete_tariff', array( $this, 'delete_tariff' ) );
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
	public function customer_ledger() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'customer_ledger' ) );
	}

	/**
	 * @return void
	 */
	public function ensure_customers() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'ensure_customers_from_licenses' ) );
	}

	/**
	 * @return void
	 */
	public function attach_number() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'attach_number' ) );
	}

	/**
	 * @return void
	 */
	public function detach_number() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'detach_number' ) );
	}

	/**
	 * @return void
	 */
	public function attach_pattern() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'attach_pattern' ) );
	}

	public function detach_pattern() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'detach_pattern' ) );
	}

	/**
	 * @return void
	 */
	public function domain_pattern_registry() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'domain_pattern_registry' ) );
	}

	/**
	 * @return void
	 */
	public function domain_secretaries() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'domain_secretaries' ) );
	}

	/**
	 * @return void
	 */
	public function save_domain_secretary() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'save_domain_secretary' ) );
	}

	/**
	 * @return void
	 */
	public function delete_domain_secretary() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'delete_domain_secretary' ) );
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
	public function get_tariffs() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'tariffs' ) );
	}

	/**
	 * @return void
	 */
	public function save_tariff() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'tariff_save' ) );
	}

	/**
	 * @return void
	 */
	public function delete_tariff() {
		$this->emit_service( array( 'WebinoCRM_Modirpayamak_Service', 'tariff_delete' ) );
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
