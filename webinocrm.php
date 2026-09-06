<?php
/**
 * Plugin Name:       WebinoERP
 * Plugin URI:        https://Webinoco.com/
 * Description:       A complete CRM and Project Management solution for Social Marketing agencies.
 * Version:           3.5.0
 * Author:            Arsalan Arghavan
 * Author URI:        https://ArsalanArghavan.ir/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webinocrm
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define Plugin Constants
define( 'WEBINOCRM_VERSION', '3.5.0' );
define( 'WEBINOCRM_PLUGIN_FILE', __FILE__ );
define( 'WEBINOCRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEBINOCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WEBINO_CRM_TEMPLATE_PATH', WEBINOCRM_PLUGIN_DIR . 'templates/' );

require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-installer.php';
require_once WEBINOCRM_PLUGIN_DIR . 'includes/webino-functions.php';

register_activation_hook( __FILE__, [ 'WebinoCRM_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WebinoCRM_Installer', 'deactivate' ] );

if ( function_exists( 'webinocrm_is_license_rest_request' ) && webinocrm_is_license_rest_request() ) {
	webinocrm_bootstrap_license_fastpath();
	return;
}

// Bale / Woo integration (WebinaBaleBusiness), embedded under WebinoCRM.
define( 'WEBINOCRM_BALE_DIR', WEBINOCRM_PLUGIN_DIR . 'includes/integrations/bale-business/' );
if ( ! defined( 'WBB_VERSION' ) ) {
	define( 'WBB_VERSION', '1.0.1' );
}
if ( ! defined( 'WBB_PLUGIN_FILE' ) ) {
	define( 'WBB_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WBB_PLUGIN_DIR' ) ) {
	define( 'WBB_PLUGIN_DIR', WEBINOCRM_BALE_DIR );
}
if ( ! defined( 'WBB_PLUGIN_URL' ) ) {
	define( 'WBB_PLUGIN_URL', WEBINOCRM_PLUGIN_URL . 'includes/integrations/bale-business/' );
}
require_once WEBINOCRM_BALE_DIR . 'autoload.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WBB_PLUGIN_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( '\WebinaBaleBusiness\Core\Plugin' ) ) {
			\WebinaBaleBusiness\Core\Plugin::instance()->init();
		}
	},
	20
);

require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-webinocrm.php';
require_once WEBINOCRM_PLUGIN_DIR . 'includes/site/bootstrap.php';

// === [START] FINAL & CORRECTED SCRIPT ENQUEUEING ===

/**
 * Registers and conditionally enqueues the plugin's scripts and styles.
 * OPTIMIZED: Loading scripts ONLY where they are needed for better performance.
 */
function webino_enqueue_assets($hook) {
    // --- GLOBAL SCRIPTS (Load on all WebinoCRM admin pages) ---
    // We only load these if the page belongs to our plugin.
    if (strpos($hook, 'webino-') === false) {
        return;
    }
    
    // Enqueue SweetAlert2 - Deferred loading
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11', true);

    // Register main script (but don't enqueue yet)
    wp_register_script(
        'webinocrm-scripts',
        WEBINOCRM_PLUGIN_URL . 'assets/js/webinocrm-scripts.js',
        array( 'jquery' ),
        WEBINOCRM_VERSION,
        true
    );

    // Enqueue the main script
    wp_enqueue_script('webinocrm-scripts');

    // Pass PHP variables to the main script
    $calendar_locale = function_exists('webino_get_current_locale') ? webino_get_current_locale() : 'fa_IR';
    $current_language = function_exists('webino_get_current_language') ? webino_get_current_language() : 'fa';

    wp_localize_script('webinocrm-scripts', 'webinocrm_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('webinocrm-ajax-nonce'),
        'rest_url' => esc_url_raw( rest_url( 'webinocrm/v1/' ) ),
        'rest_nonce' => wp_create_nonce( 'wp_rest' ),
        'calendar_locale' => $calendar_locale,
        'current_language' => $current_language,
        'lang'     => [
            'ok_button'     => __('باشه', 'webinocrm'),
            'success_title' => __('موفق', 'webinocrm'),
            'error_title'   => __('خطا', 'webinocrm'),
            'server_error'  => __('یک خطای سرور رخ داد.', 'webinocrm'),
        ]
    ]);

    // --- CONDITIONAL SCRIPTS (Load only on specific pages) ---
    $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    
    // Load Dark Mode CSS and JS
    wp_enqueue_style(
        'webinocrm-dark-mode',
        WEBINOCRM_PLUGIN_URL . 'assets/css/dark-mode.css',
        [],
        WEBINOCRM_VERSION
    );
    wp_enqueue_style(
        'webinocrm-modern-bridge',
        WEBINOCRM_PLUGIN_URL . 'assets/css/webinocrm-modern-bridge.css',
        ['webinocrm-dark-mode'],
        WEBINOCRM_VERSION
    );
    
    wp_enqueue_script(
        'webinocrm-dark-mode',
        WEBINOCRM_PLUGIN_URL . 'assets/js/dark-mode.js',
        ['jquery'],
        WEBINOCRM_VERSION,
        true
    );
    
    // Performance Optimization Script
    wp_enqueue_script(
        'webinocrm-performance',
        WEBINOCRM_PLUGIN_URL . 'assets/js/performance-optimization.js',
        ['jquery'],
        WEBINOCRM_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'webino_enqueue_assets');


/**
 * Enqueue scripts for frontend (shortcodes). Kept simple for now.
 */
function webino_enqueue_frontend_assets() {
    // You can enqueue specific frontend styles and scripts here if needed
}
add_action('wp_enqueue_scripts', 'webino_enqueue_frontend_assets');

// === [END] FINAL SOLUTION ===


/**
 * Checks for required plugin dependencies.
 */
function webino_check_dependencies() {
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        add_action( 'admin_notices', 'webino_dependency_notice' );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}
add_action( 'admin_init', 'webino_check_dependencies' );

/**
 * Renders the admin notice for missing dependencies.
 */
function webino_dependency_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'پلاگین WebinoCRM غیرفعال شد', 'webinocrm' ); ?></strong><br>
            <?php esc_html_e( 'این پلاگین نیازمند نصب و فعال بودن پلاگین WooCommerce است. لطفاً ابتدا آن را فعال کرده و سپس WebinoCRM را فعال کنید.', 'webinocrm' ); ?>
        </p>
    </div>
    <?php
}

// Load plugin textdomain for translation.
function webino_load_textdomain() {
    load_plugin_textdomain( 'webinocrm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'webino_load_textdomain', 0 );


/**
 * Begins execution of the plugin.
 */
function run_webinocrm() {
    $plugin = WebinoCRM::instance();
    $plugin->run();
}

run_webinocrm();