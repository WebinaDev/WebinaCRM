<?php
/**
 * Sidebar Component
 * 
 * Renders the dashboard sidebar with logo and navigation menu
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Component class
 */
class WebinoCRM_Sidebar_Component extends WebinoCRM_Abstract_Component {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'sidebar' );
	}

	/**
	 * Render the sidebar component
	 */
	public function render() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		$this->load_template( 'sidebar.php' );
	}

	/**
	 * Enqueue sidebar assets
	 */
	public function enqueue_assets() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Note: CSS is loaded by main template, we only load JS here

		// Enqueue JS
		wp_enqueue_script(
			'webino-sidebar',
			WEBINOCRM_PLUGIN_URL . 'assets/js/components/sidebar.js',
			array( 'jquery' ),
			WEBINOCRM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'webino-sidebar',
			'webinoSidebar',
			array(
				'currentPage' => WebinoCRM_Sidebar_Data_Provider::get_current_page(),
				'i18n'        => array(
					'toggleSidebar' => __( 'Toggle Sidebar', 'webinocrm' ),
				),
			)
		);
	}

	/**
	 * Get sidebar data
	 *
	 * @return array Sidebar data.
	 */
	public function get_data() {
		return array(
			'logo_url'     => WebinoCRM_Sidebar_Data_Provider::get_logo_url(),
			'menu_items'   => WebinoCRM_Sidebar_Data_Provider::get_menu_items(),
			'current_page' => WebinoCRM_Sidebar_Data_Provider::get_current_page(),
		);
	}
}

