<?php
/**
 * Marketplace admin AJAX handler.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPA CRUD for marketplace catalog.
 */
class WebinoCRM_Marketplace_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_webinocrm_get_marketplace_categories', array( $this, 'get_categories' ) );
		add_action( 'wp_ajax_webinocrm_save_marketplace_category', array( $this, 'save_category' ) );
		add_action( 'wp_ajax_webinocrm_delete_marketplace_category', array( $this, 'delete_category' ) );
		add_action( 'wp_ajax_webinocrm_get_marketplace_modules', array( $this, 'get_modules' ) );
		add_action( 'wp_ajax_webinocrm_save_marketplace_module', array( $this, 'save_module' ) );
		add_action( 'wp_ajax_webinocrm_delete_marketplace_module', array( $this, 'delete_module' ) );
		add_action( 'wp_ajax_webinocrm_get_marketplace_orders', array( $this, 'get_orders' ) );
	}

	/**
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( 'webinocrm-ajax-nonce', 'security' );
		if ( ! webinocrm_user_can_manage_marketplace() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ) );
		}
	}

	/**
	 * @return void
	 */
	public function get_categories() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'categories' ) );
	}

	/**
	 * @return void
	 */
	public function save_category() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'category_save' ) );
	}

	/**
	 * @return void
	 */
	public function delete_category() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'category_delete' ) );
	}

	/**
	 * @return void
	 */
	public function get_modules() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'modules' ) );
	}

	/**
	 * @return void
	 */
	public function save_module() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'module_save' ) );
	}

	/**
	 * @return void
	 */
	public function delete_module() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'module_delete' ) );
	}

	/**
	 * @return void
	 */
	public function get_orders() {
		$this->emit_service( array( 'WebinoCRM_Marketplace_Service', 'orders' ) );
	}
}
