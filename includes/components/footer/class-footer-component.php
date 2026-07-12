<?php
/**
 * Footer Component
 * 
 * Renders the dashboard footer with copyright and links
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Footer Component class
 */
class WebinoCRM_Footer_Component extends WebinoCRM_Abstract_Component {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'footer' );
	}

	/**
	 * Render the footer component
	 */
	public function render() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Set component data before rendering
		$this->set_data( $this->get_data() );

		$this->load_template( 'footer.php' );
	}

	/**
	 * Enqueue footer assets
	 */
	public function enqueue_assets() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Note: CSS is loaded by main template
		// Footer typically doesn't need JavaScript
	}

	/**
	 * Get footer data
	 *
	 * @return array Footer data.
	 */
	public function get_data() {
		return array(
			'copyright_text' => WebinoCRM_Footer_Data_Provider::get_copyright_text(),
			'footer_links'   => WebinoCRM_Footer_Data_Provider::get_footer_links(),
			'credits'        => WebinoCRM_Footer_Data_Provider::get_footer_credits(),
		);
	}
}

