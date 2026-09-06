<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 * @author     Arsalan Arghavan
 */

class WebinoCRM {

    /**
     * The single instance of the class.
     * @var WebinoCRM
     */
    protected static $_instance = null;

    /**
     * Main WebinoCRM Instance.
     * Ensures only one instance of WebinoCRM is loaded or can be loaded.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * WebinoCRM Constructor.
     */
    public function __construct() {
        // Register autoloader for enterprise features (lazy loading)
        spl_autoload_register([__CLASS__, 'autoload_enterprise_features']);
        
        $this->load_dependencies();
        $this->define_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     * **MODIFIED**: Now loads the main AJAX handler instead of the old monolithic one.
     */
    private function load_dependencies() {
        // Core Classes
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-admin-menu.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-cpt-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-roles-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-user-profile.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-shortcode-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-form-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-main-ajax-handler.php'; // **CORRECTED**
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-cron-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-settings-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-log-database.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-reports-dashboard.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-visitor-statistics.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-agile-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-automation-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-pdf-reporter.php';
        
        // SMS Interface and Integrations
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-sms-service-interface.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-zarinpal-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-melipayamak-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-parsgreen-handler.php';
		// NEW: Telegram Handler
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-telegram-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-elementor-lead-integration.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-bale-rest-api.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/trait-ajax-service-delegate.php';
        
        // Login Page and AJAX Handler
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-login-page.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-login-ajax-handler.php';
        
        // Dashboard Router + SPA shell
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-router.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-rewrite.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-assets.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-webinocrm-dashboard-ssr.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-rahn-public-rewrite.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-modules.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/sidebar/class-sidebar-menu-builder.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-app.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-task-template-manager.php';
        
        // Helper Classes
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-date-formatter.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-number-formatter.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-calendar-helper.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-dashboard-locale.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-term-helper.php';
        
        // Component System
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/components/class-component-registry.php';
        
        // Cache & Performance Optimizer
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-cache-optimizer.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-database-optimizer.php';
        
        // License Management
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-license-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-license-ajax-handler.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-license-api.php';

        // Marketplace module store
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-gitea-client.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-marketplace-release-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-marketplace-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-marketplace-api.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-webinocrm-core-updater.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-marketplace-ajax-handler.php';

        // ModirPayamak SMS panel (IPPanel Edge)
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/integrations/class-modirpayamak-edge-client.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-modirpayamak-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-modirpayamak-tariffs.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-modirpayamak-api.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-constants.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-install.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-template-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-settings-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-pattern-sync-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-order-notify-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-order-messages-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-auth-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-newsletter-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-secretary-service.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-api.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ajax/class-modirpayamak-ajax-handler.php';

        // Accounting module (when enabled via settings)
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/class-erp-module-registry.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/accounting/class-accounting-module.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-module.php';

        // AI content engine (GapGPT / Elementor pages / Woo jobs when available).
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/ai-content/bootstrap.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-webinocrm-rest-content-pages.php';
        WebinoCRM_REST_Content_Pages::init();
        WebinoCRM_Hrm_Module::load();
        
        // ENTERPRISE FEATURES (Lazy loaded - only when needed)
        // These will be loaded on-demand through autoloader
    }

    /**
     * Autoloader for Enterprise Features (Lazy Loading)
     */
    public static function autoload_enterprise_features($class_name) {
        // Only load our classes
        if (strpos($class_name, 'WebinoCRM_') !== 0) {
            return;
        }

        $class_map = [
            'WebinoCRM_WebSocket_Handler' => 'class-websocket-handler.php',
            'WebinoCRM_Elasticsearch_Handler' => 'class-elasticsearch-handler.php',
            'WebinoCRM_Activity_Timeline' => 'class-activity-timeline.php',
            'WebinoCRM_Smart_Reminders' => 'class-smart-reminders.php',
            'WebinoCRM_PWA_Handler' => 'class-pwa-handler.php',
            'WebinoCRM_Time_Tracking' => 'class-time-tracking.php',
            'WebinoCRM_Document_Management' => 'class-document-management.php',
            'WebinoCRM_White_Label' => 'class-white-label.php',
            'WebinoCRM_Advanced_Analytics' => 'class-advanced-analytics.php',
            'WebinoCRM_Team_Chat' => 'class-team-chat.php',
            'WebinoCRM_Session_Management' => 'class-session-management.php',
            'WebinoCRM_Data_Encryption' => 'class-data-encryption.php',
            'WebinoCRM_Field_Security' => 'class-field-security.php',
        ];

        if (isset($class_map[$class_name])) {
            $file = WEBINOCRM_PLUGIN_DIR . 'includes/' . $class_map[$class_name];
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Register all of the hooks related to the functionality of the plugin.
     * **MODIFIED**: Initializes the new main AJAX handler.
     */
    private function define_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_visitor_tracking' ], 20 );
        add_action( 'plugins_loaded', [ $this, 'handle_language_switch' ], 1 );

        new WebinoCRM_License_API();
        new WebinoCRM_Marketplace_API();
        new WebinoCRM_ModirPayamak_API();
        new WebinoCRM_Sms_API();
        new WebinoCRM_Cron_Handler();

        new WebinoCRM_Main_Ajax_Handler();

        if ( is_admin() ) {
            new WebinoCRM_Admin_Menu();
            new WebinoCRM_License_Ajax_Handler();
            new WebinoCRM_Marketplace_Ajax_Handler();
            new WebinoCRM_ModirPayamak_Ajax_Handler();
        }

        add_action(
            'init',
            static function () {
                if ( function_exists( 'webinocrm_is_license_rest_request' ) && webinocrm_is_license_rest_request() ) {
                    return;
                }
                WebinoCRM_Accounting_Module::load();
                new WebinoCRM_CPT_Manager();
            },
            0
        );

        new WebinoCRM_Roles_Manager();
        new WebinoCRM_User_Profile();
        new WebinoCRM_Shortcode_Manager();
        new WebinoCRM_Form_Handler();

        add_action(
            'init',
            static function () {
                if ( is_admin() || get_query_var( 'webino_dashboard', false ) ) {
                    WebinoCRM_Component_Registry::instance();
                }
            },
            5
        );

        new WebinoCRM_Agile_Handler();
        new WebinoCRM_Automation_Handler();
        new WebinoCRM_Login_Page();
        new WebinoCRM_Login_Ajax_Handler();
        new WebinoCRM_Dashboard_Router();
        webinocrm_dashboard();
        WebinoCRM_Rahn_Public_Rewrite::init();
        // Ensure rahn tables exist on existing installs + flush rewrite once.
        add_action(
            'init',
            static function () {
                if ( ! class_exists( 'WebinoCRM_Rahn_Service' ) && is_readable( WEBINOCRM_PLUGIN_DIR . 'includes/services/class-rahn-service.php' ) ) {
                    require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-rahn-service.php';
                }
                if ( ! class_exists( 'WebinoCRM_Rahn_Service' ) ) {
                    return;
                }
                $had_schema = false !== get_option( WebinoCRM_Rahn_Service::SCHEMA_OPTION, false );
                WebinoCRM_Rahn_Service::maybe_install_tables();
                if ( ! $had_schema || '1' === (string) get_option( 'webinocrm_rahn_flush_rewrites', '' ) ) {
                    WebinoCRM_Rahn_Public_Rewrite::register_rewrites();
                    flush_rewrite_rules( false );
                    delete_option( 'webinocrm_rahn_flush_rewrites' );
                }
            },
            20
        );
        new WebinoCRM_Cache_Optimizer();
        new WebinoCRM_Database_Optimizer();
        new WebinoCRM_Bale_REST_API();
        new WebinoCRM_Session_Management();
        new WebinoCRM_PWA_Handler();
        new WebinoCRM_White_Label();
        
        // Load Activity Timeline and Smart Reminders on 'init' hook (when pluggable functions are available)
        add_action('init', function() {
            if (is_admin() || is_user_logged_in()) {
                new WebinoCRM_Activity_Timeline();
                new WebinoCRM_Smart_Reminders();
            }
        });
        
        // Elementor Pro form integration - run early so we register before forms module fires actions/register
        add_action( 'elementor_pro/init', [ $this, 'init_elementor_integration' ], 0 );

        // Load advanced features only when needed
        add_action('admin_enqueue_scripts', function($hook) {
            // WebSocket - only on dashboard pages
            if (strpos($hook, 'webino-') !== false) {
                new WebinoCRM_WebSocket_Handler();
            }
            
            // Chat, Documents, Time Tracking: SPA + REST (see docs/WEBINODASHBOARD-SPLIT.md).
        });
        
        // Data Encryption - Always load for security
        new WebinoCRM_Data_Encryption();
        new WebinoCRM_Field_Security();
    }

    /**
     * Legacy front-end dashboard bundles (shortcodes, Xintra) were removed; the React SPA loads its own assets on /dashboard.
     */
    public function enqueue_dashboard_assets() {
        return;
    }

    /**
     * Enqueue visitor tracking script on front only (not admin, not dashboard) when setting is enabled.
     */
    public function enqueue_visitor_tracking() {
        if ( is_admin() ) {
            return;
        }
        if ( get_query_var( 'webino_dashboard', false ) ) {
            return;
        }
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( strpos( $uri, '/dashboard' ) !== false || strpos( $uri, 'wp-admin' ) !== false || strpos( $uri, 'wp-login' ) !== false ) {
            return;
        }
        if ( ! WebinoCRM_Settings_Handler::is_visitor_tracking_enabled() ) {
            return;
        }
        wp_enqueue_script(
            'webinocrm-visitor-tracking',
            WEBINOCRM_PLUGIN_URL . 'assets/js/visitor-tracking.js',
            array(),
            WEBINOCRM_VERSION,
            true
        );
        wp_localize_script( 'webinocrm-visitor-tracking', 'webinocrm_visitor_tracking', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'webinocrm-ajax-nonce' ),
        ) );
    }
    
    /**
     * Initialize Elementor Pro form integration when Elementor is loaded.
     */
    public function init_elementor_integration() {
        if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
            return;
        }
        new WebinoCRM_Elementor_Lead_Integration();
    }

    /**
     * Handle language switching from cookie
     * Must run early (before textdomain is loaded)
     */
    public function handle_language_switch() {
        if ( ! function_exists( 'webino_apply_language_locale' ) ) {
            return;
        }
        webino_apply_language_locale();
    }
    
    /**
     * The main execution function of the plugin.
     */
    public function run() {
        // The plugin is running. This function can be expanded later if needed.
    }
}