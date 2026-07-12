<?php
/**
 * Abstract Component Class
 * 
 * Base class for all dashboard components (Header, Footer, Sidebar)
 * Provides common functionality for rendering, asset management, and data handling
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for components
 */
abstract class WebinoCRM_Abstract_Component {

	/**
	 * Component ID/name
	 *
	 * @var string
	 */
	protected $component_id;

	/**
	 * Component data
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Constructor
	 *
	 * @param string $component_id Component identifier.
	 */
	public function __construct( $component_id ) {
		$this->component_id = $component_id;
		$this->init();
	}

	/**
	 * Initialize component
	 * Hook into WordPress actions and filters
	 */
	protected function init() {
		// Hook into wp_enqueue_scripts for proper asset enqueuing
		// This ensures scripts are enqueued before wp_head() processes them
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Get component ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->component_id;
	}

	/**
	 * Render the component
	 * Must be implemented by child classes
	 *
	 * @return void
	 */
	abstract public function render();

	/**
	 * Enqueue component assets (CSS/JS)
	 * Must be implemented by child classes
	 *
	 * @return void
	 */
	abstract public function enqueue_assets();

	/**
	 * Get component data
	 * Must be implemented by child classes
	 *
	 * @return array
	 */
	abstract public function get_data();

	/**
	 * Get template path for component
	 *
	 * @param string $template_name Template file name.
	 * @return string Full path to template file.
	 */
	protected function get_template_path( $template_name ) {
		$template_path = WEBINOCRM_PLUGIN_DIR . 'templates/components/' . $this->component_id . '/' . $template_name;
		
		// Allow themes to override component templates
		$theme_template = get_stylesheet_directory() . '/webinocrm/components/' . $this->component_id . '/' . $template_name;
		
		if ( file_exists( $theme_template ) ) {
			return $theme_template;
		}
		
		return $template_path;
	}

	/**
	 * Load template file
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args Arguments to pass to template.
	 * @return void
	 */
	protected function load_template( $template_name, $args = array() ) {
		$template_path = $this->get_template_path( $template_name );
		
		if ( ! file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WebinoCRM: Template not found: %s', $template_path ) );
			return;
		}
		
		// Extract args to make them available in template
		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}
		
		// Make component data available
		$component_data = $this->get_data();
		
		include $template_path;
	}

	/**
	 * Check if current page is dashboard
	 *
	 * @return bool
	 */
	protected function is_dashboard_page() {
		return (bool) get_query_var( 'webino_dashboard', false );
	}

	/**
	 * Get current user's primary role
	 *
	 * @return string User role or 'guest' if not logged in.
	 */
	protected function get_user_role() {
		if ( ! is_user_logged_in() ) {
			return 'guest';
		}
		
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		
		if ( in_array( 'administrator', $roles, true ) || in_array( 'system_manager', $roles, true ) ) {
			return 'system_manager';
		}
		
		if ( in_array( 'finance_manager', $roles, true ) ) {
			return 'finance_manager';
		}
		
		if ( in_array( 'team_member', $roles, true ) ) {
			return 'team_member';
		}
		
		if ( in_array( 'customer', $roles, true ) ) {
			return 'customer';
		}
		
		return 'guest';
	}

	/**
	 * Set component data
	 *
	 * @param array $data Component data.
	 * @return void
	 */
	public function set_data( $data ) {
		$this->data = $data;
	}
}

