<?php
/**
 * Header Component
 * 
 * Renders the dashboard header with logo, search, notifications, and profile menu
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header Component class
 */
class WebinoCRM_Header_Component extends WebinoCRM_Abstract_Component {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'header' );
	}

	/**
	 * Render the header component
	 */
	public function render() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Set component data before rendering
		$this->set_data( $this->get_data() );

		$this->load_template( 'header.php' );
	}

	/**
	 * Enqueue header assets
	 */
	public function enqueue_assets() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Note: CSS is loaded by main template, we only load JS here
		
		// Enqueue JS (in footer for better performance, but ensure jQuery and Bootstrap are loaded)
		wp_enqueue_script(
			'webino-header',
			WEBINOCRM_PLUGIN_URL . 'assets/js/components/header.js',
			array( 'jquery', 'webino-bootstrap-js' ),
			WEBINOCRM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'webino-header',
			'webinoHeader',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'webino_header_nonce' ),
				'i18n'     => array(
					'search'        => __( 'Search...', 'webinocrm' ),
					'noResults'     => __( 'No results found', 'webinocrm' ),
					'loading'       => __( 'Loading...', 'webinocrm' ),
					'notifications' => __( 'Notifications', 'webinocrm' ),
					'viewAll'       => __( 'View All', 'webinocrm' ),
				),
			)
		);
	}

	/**
	 * Get header data
	 *
	 * @return array Header data.
	 */
	public function get_data() {
		return array(
			'user'             => WebinoCRM_Header_Data_Provider::get_user_data(),
			'notifications'    => WebinoCRM_Header_Data_Provider::get_notifications(),
			'unread_count'     => WebinoCRM_Header_Data_Provider::get_unread_notifications_count(),
			'search_config'    => WebinoCRM_Header_Data_Provider::get_search_config(),
			'language_options' => WebinoCRM_Header_Data_Provider::get_language_options(),
			'logo_url'         => WebinoCRM_Header_Data_Provider::get_logo_url(),
			'profile_menu'     => WebinoCRM_Header_Data_Provider::get_profile_menu_items(),
		);
	}
}

