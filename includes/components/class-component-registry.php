<?php
/**
 * Component Registry
 * 
 * Manages registration and lifecycle of all dashboard components
 * Implements singleton pattern for global access
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component Registry class
 */
class WebinoCRM_Component_Registry {

	/**
	 * Singleton instance
	 *
	 * @var WebinoCRM_Component_Registry
	 */
	private static $instance = null;

	/**
	 * Registered components
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Components loaded status
	 *
	 * @var bool
	 */
	private $components_loaded = false;

	/**
	 * Get singleton instance
	 *
	 * @return WebinoCRM_Component_Registry
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_components' ) );
		add_action( 'template_redirect', array( $this, 'maybe_disable_admin_bar' ) );
	}

	/**
	 * Load all component classes
	 */
	public function load_components() {
		if ( $this->components_loaded ) {
			return;
		}

		// Load abstract component first
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/abstract-class-component.php';

		// Load header components
		if ( file_exists( WEBINOCRM_PLUGIN_DIR . 'includes/components/header/class-header-component.php' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/header/class-header-component.php';
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/header/class-header-data-provider.php';
			$this->register( 'header', new WebinoCRM_Header_Component() );
		}

		// Load sidebar components
		if ( file_exists( WEBINOCRM_PLUGIN_DIR . 'includes/components/sidebar/class-sidebar-component.php' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/sidebar/class-sidebar-component.php';
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/sidebar/class-sidebar-data-provider.php';
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/sidebar/class-sidebar-menu-builder.php';
			$this->register( 'sidebar', new WebinoCRM_Sidebar_Component() );
		}

		// Load footer components
		if ( file_exists( WEBINOCRM_PLUGIN_DIR . 'includes/components/footer/class-footer-component.php' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/footer/class-footer-component.php';
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/footer/class-footer-data-provider.php';
			$this->register( 'footer', new WebinoCRM_Footer_Component() );
		}

		$this->components_loaded = true;

		/**
		 * Fires after all components have been loaded
		 *
		 * @param WebinoCRM_Component_Registry $this The registry instance.
		 */
		do_action( 'webinocrm_components_loaded', $this );
	}

	/**
	 * Register a component
	 *
	 * @param string                         $component_id Component identifier.
	 * @param WebinoCRM_Abstract_Component $component Component instance.
	 * @return bool True on success, false on failure.
	 */
	public function register( $component_id, $component ) {
		if ( ! $component instanceof WebinoCRM_Abstract_Component ) {
			return false;
		}

		$this->components[ $component_id ] = $component;

		/**
		 * Fires after a component is registered
		 *
		 * @param string                         $component_id Component ID.
		 * @param WebinoCRM_Abstract_Component $component Component instance.
		 */
		do_action( 'webinocrm_component_registered', $component_id, $component );

		return true;
	}

	/**
	 * Get a registered component
	 *
	 * @param string $component_id Component identifier.
	 * @return WebinoCRM_Abstract_Component|null Component instance or null if not found.
	 */
	public function get( $component_id ) {
		return isset( $this->components[ $component_id ] ) ? $this->components[ $component_id ] : null;
	}

	/**
	 * Get all registered components
	 *
	 * @return array Array of component instances.
	 */
	public function get_all() {
		return $this->components;
	}

	/**
	 * Check if component exists
	 *
	 * @param string $component_id Component identifier.
	 * @return bool
	 */
	public function has( $component_id ) {
		return isset( $this->components[ $component_id ] );
	}

	/**
	 * Unregister a component
	 *
	 * @param string $component_id Component identifier.
	 * @return bool True on success, false if component doesn't exist.
	 */
	public function unregister( $component_id ) {
		if ( ! $this->has( $component_id ) ) {
			return false;
		}

		unset( $this->components[ $component_id ] );

		/**
		 * Fires after a component is unregistered
		 *
		 * @param string $component_id Component ID.
		 */
		do_action( 'webinocrm_component_unregistered', $component_id );

		return true;
	}

	/**
	 * Disable WordPress admin bar on dashboard pages
	 */
	public function maybe_disable_admin_bar() {
		if ( $this->is_dashboard_page() ) {
			add_filter( 'show_admin_bar', '__return_false' );
			remove_action( 'wp_head', '_admin_bar_bump_cb' );
			
			// Dequeue theme styles and scripts that might conflict
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_theme_assets' ), 999 );
		}
	}
	
	/**
	 * Dequeue theme assets on dashboard pages
	 */
	public function dequeue_theme_assets() {
		// Dequeue all theme styles
		global $wp_styles;
		if ( isset( $wp_styles->queue ) ) {
			foreach ( $wp_styles->queue as $handle ) {
				$src = $wp_styles->registered[ $handle ]->src ?? '';
				// Dequeue if it's from theme directory
				if ( strpos( $src, get_template_directory_uri() ) !== false || 
				     strpos( $src, get_stylesheet_directory_uri() ) !== false ) {
					wp_dequeue_style( $handle );
				}
			}
		}
		
		// Dequeue all theme scripts
		global $wp_scripts;
		if ( isset( $wp_scripts->queue ) ) {
			foreach ( $wp_scripts->queue as $handle ) {
				$src = $wp_scripts->registered[ $handle ]->src ?? '';
				// Dequeue if it's from theme directory
				if ( strpos( $src, get_template_directory_uri() ) !== false || 
				     strpos( $src, get_stylesheet_directory_uri() ) !== false ) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	/**
	 * Check if current page is the SPA dashboard route.
	 *
	 * @return bool
	 */
	private function is_dashboard_page() {
		if ( get_query_var( 'webino_dashboard', false ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}
}

