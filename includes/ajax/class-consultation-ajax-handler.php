<?php
/**
 * WebinoCRM Consultation AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/consultations. Kept for legacy hooks.
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Consultation_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

	public function __construct() {
		add_action( 'wp_ajax_webinocrm_get_consultations', array( $this, 'ajax_get_consultations' ) );
		add_action( 'wp_ajax_webino_manage_consultation', array( $this, 'ajax_manage_consultation' ) );
		add_action( 'wp_ajax_webino_convert_consultation_to_project', array( $this, 'ajax_convert_consultation_to_project' ) );
	}

	public function ajax_get_consultations() {
		$this->emit_service( array( 'WebinoCRM_Consultations_Service', 'list' ) );
	}

	public function ajax_manage_consultation() {
		$this->emit_service( array( 'WebinoCRM_Consultations_Service', 'save' ) );
	}

	public function ajax_convert_consultation_to_project() {
		$this->emit_service( array( 'WebinoCRM_Consultations_Service', 'convert_project' ) );
	}
}
