<?php
/**
 * WebinoCRM Dashboard Router (Exact Copy of Xintra Template)
 * Manages all dashboard routes with clean URLs
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Dashboard_Router {

    /**
     * All available dashboard routes
     */
    private static $routes = null;
    
    /**
     * Get all routes
     * 
     * @return array Routes array
     */
    public static function get_routes() {
        if (self::$routes === null) {
            self::init_routes();
        }
        return self::$routes;
    }
    
    /**
     * Initialize routes
     */
    private static function init_routes() {
        self::$routes = [
        // Main dashboard
        '' => [
            'title' => __( 'داشبورد', 'webinocrm' ),
            'icon' => 'ri-home-4-line',
            'roles' => ['system_manager', 'team_member', 'client'],
            'partial' => 'dashboard',
        ],
        
        // Projects
        'projects' => [
            'title' => __( 'پروژه‌ها', 'webinocrm' ),
            'icon' => 'ri-folder-2-line',
            'roles' => ['system_manager', 'team_member', 'client'],
        ],
        
        // Contracts
        'contracts' => [
            'title' => __( 'قراردادها', 'webinocrm' ),
            'icon' => 'ri-file-text-line',
            'roles' => ['system_manager', 'finance_manager', 'team_member', 'sales_consultant', 'client'],
        ],
        
        // Services & Products (Manager only)
        'services' => [
            'title' => __( 'خدمات و محصولات', 'webinocrm' ),
            'icon' => 'ri-service-line',
            'roles' => ['system_manager'],
        ],
        
        // Invoices
        'invoices' => [
            'title' => __( 'پیش‌فاکتورها', 'webinocrm' ),
            'icon' => 'ri-file-list-3-line',
            'roles' => ['system_manager', 'finance_manager', 'team_member', 'client'],
            'partial' => 'page-pro-invoices',
        ],

        // Rahn-percent calculator
        'rahn-percent' => [
            'title' => __( 'رهن‌درصد', 'webinocrm' ),
            'icon' => 'ri-percent-line',
            'roles' => ['system_manager', 'finance_manager', 'sales_consultant'],
        ],
        
        // Tickets
        'tickets' => [
            'title' => __( 'تیکت‌ها', 'webinocrm' ),
            'icon' => 'ri-customer-service-2-line',
            'roles' => ['system_manager', 'team_member', 'client'],
            'partial' => 'page-tickets',
        ],
        
        // Tasks
        'tasks' => [
            'title' => __( 'وظایف', 'webinocrm' ),
            'icon' => 'ri-task-line',
            'roles' => ['system_manager', 'team_member'],
        ],

        // Team chat
        'chat' => [
            'title' => __( 'چت تیمی', 'webinocrm' ),
            'icon' => 'ri-message-2-line',
            'roles' => [ 'system_manager', 'team_member' ],
        ],

        // Documents
        'documents' => [
            'title' => __( 'اسناد', 'webinocrm' ),
            'icon' => 'ri-folder-line',
            'roles' => [ 'system_manager', 'team_member' ],
        ],

        // Time tracking
        'time-tracking' => [
            'title' => __( 'زمان‌سنجی', 'webinocrm' ),
            'icon' => 'ri-time-line',
            'roles' => [ 'system_manager', 'team_member' ],
        ],
        
        // Appointments
        'appointments' => [
            'title' => __( 'قرار ملاقات‌ها', 'webinocrm' ),
            'icon' => 'ri-calendar-check-line',
            'roles' => ['system_manager', 'team_member', 'client'],
        ],
        
        // Leads (Manager + Sales Consultant)
        'leads' => [
            'title' => __( 'سرنخ‌ها', 'webinocrm' ),
            'icon' => 'ri-user-add-line',
            'roles' => ['system_manager', 'sales_consultant'],
            'partial' => 'page-leads',
        ],
        
        // Licenses (Manager only)
        'licenses' => [
            'title' => __( 'لایسنس‌های مارکت‌پلیس', 'webinocrm' ),
            'icon' => 'ri-key-line',
            'roles' => ['system_manager'],
        ],
        
        // Customers (Manager + Sales Consultant for contract creation)
        'customers' => [
            'title' => __( 'مشتریان', 'webinocrm' ),
            'icon' => 'ri-group-line',
            'roles' => ['system_manager', 'sales_consultant'],
            'partial' => 'page-customers',
        ],
        
        // Staff (Manager only)
        'staff' => [
            'title' => __( 'کارکنان', 'webinocrm' ),
            'icon' => 'ri-user-star-line',
            'roles' => ['system_manager'],
        ],
        
        // Consultations (Manager only)
        'consultations' => [
            'title' => __( 'مشاوره‌ها', 'webinocrm' ),
            'icon' => 'ri-discuss-line',
            'roles' => ['system_manager'],
            'partial' => 'page-consultations',
        ],
        
        // Campaigns (Manager only)
        'campaigns' => [
            'title' => __( 'کمپین‌ها', 'webinocrm' ),
            'icon' => 'ri-megaphone-line',
            'roles' => ['system_manager'],
        ],

        // Reports (Manager + Finance)
        'reports' => [
            'title' => __( 'گزارشات', 'webinocrm' ),
            'icon' => 'ri-bar-chart-box-line',
            'roles' => ['system_manager', 'finance_manager'],
            'partial' => 'page-reports',
        ],
        
        // Logs (Manager only)
        'logs' => [
            'title' => __( 'لاگ‌ها', 'webinocrm' ),
            'icon' => 'ri-file-list-2-line',
            'roles' => ['system_manager'],
        ],

        // Visitor statistics (Manager only)
        'visitor-statistics' => [
            'title' => __( 'آمار بازدید', 'webinocrm' ),
            'icon' => 'ri-line-chart-line',
            'roles' => ['system_manager'],
        ],
        
        // Settings (Manager only)
        'settings' => [
            'title' => __( 'تنظیمات', 'webinocrm' ),
            'icon' => 'ri-settings-3-line',
            'roles' => ['system_manager'],
        ],

        // Bale business bot (Manager only)
        'bale-business' => [
            'title' => __( 'ربات کسب‌وکار', 'webinocrm' ),
            'icon' => 'ri-robot-2-line',
            'roles' => ['system_manager'],
        ],
        
        // My Profile (All logged-in users)
        'profile' => [
            'title' => __( 'پروفایل من', 'webinocrm' ),
            'icon' => 'ri-user-3-line',
            'roles' => ['system_manager', 'team_member', 'client'],
            'partial' => 'page-my-profile',
        ],
        ];

        // Accounting module routes (when module is enabled)
        if (
            class_exists( 'WebinoCRM_Sidebar_Menu_Builder' )
            && (
                WebinoCRM_Sidebar_Menu_Builder::is_module_enabled( 'finance' )
                || WebinoCRM_Sidebar_Menu_Builder::is_module_enabled( 'accounting' )
            )
        ) {
            self::$routes['accounting'] = [
                'title'  => __( 'حسابداری', 'webinocrm' ),
                'icon'   => 'ri-calculator-line',
                'roles'  => [ 'system_manager', 'finance_manager' ],
                'partial' => 'page-accounting',
            ];
        }
    }

    /**
     * Constructor — routing is handled by WebinoCRM_Dashboard_App (template_include).
     */
    public function __construct() {
        // Route registry only; SPA shell served via WebinoCRM_Dashboard_Rewrite.
    }

    /**
     * Flush rewrite rules when plugin version changes.
     */
    public function maybe_flush_rewrites_on_version_change() {
        $saved = get_option('webinocrm_rewrite_version', '');
        $current = defined('WEBINOCRM_VERSION') ? WEBINOCRM_VERSION : '3.0.0';
        if ($saved !== $current) {
            flush_rewrite_rules();
            update_option('webinocrm_rewrite_version', $current);
        }
    }

    public function add_rewrite_rules() {
        add_rewrite_rule('^dashboard/?$', 'index.php?webino_dashboard=1', 'top');
        add_rewrite_rule('^dashboard/accounting/([^/]+)/?$', 'index.php?webino_dashboard=1&dashboard_page=accounting', 'top');
        add_rewrite_rule('^dashboard/([^/]+)/?$', 'index.php?webino_dashboard=1&dashboard_page=$matches[1]', 'top');
        add_rewrite_rule('^dashboard/([^/]+)/([0-9]+)/?$', 'index.php?webino_dashboard=1&dashboard_page=$matches[1]&item_id=$matches[2]', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'webino_dashboard';
        $vars[] = 'dashboard_page';
        $vars[] = 'item_id';
        return $vars;
    }

    public function template_redirect() {
        if (get_query_var('webino_dashboard')) {
            $this->load_dashboard_template();
            exit;
        }
    }

    private function load_dashboard_template() {
        if (!is_user_logged_in()) {
            wp_safe_redirect( home_url( '/dashboard/login' ) );
            exit;
        }

        $page = get_query_var('dashboard_page');
        if (empty($page)) {
            $page = '';
        }

        $routes = self::get_routes();
        
        if (!isset($routes[$page])) {
            wp_redirect(home_url('/dashboard'));
            exit;
        }

        $user = wp_get_current_user();
        $user_role = $this->get_user_dashboard_role($user);
        $route = $routes[$page];

        if (!in_array($user_role, $route['roles'])) {
            wp_redirect(home_url('/dashboard'));
            exit;
        }

        $this->render_dashboard_wrapper($page, $route, $user);
    }

    
    /**
     * Legacy Xintra enqueue removed; SPA shell loads its own assets.
     */
    public function enqueue_dashboard_assets() {
    }

    /**
     * Render dashboard wrapper using new component system
     */
    private function render_dashboard_wrapper($current_page, $route, $user) {
        $spa_shell = WEBINOCRM_PLUGIN_DIR . 'templates/dashboard/dashboard-shell.php';
        if ( file_exists( $spa_shell ) ) {
            include $spa_shell;
            return;
        }
        wp_die(
            esc_html__( 'Dashboard is unavailable. Run: cd client && npm install && npm run build', 'webinocrm' ),
            esc_html__( 'WebinoCRM', 'webinocrm' ),
            array( 'response' => 503 )
        );
    }

    public static function get_user_dashboard_role( $user ) {
        $roles = (array) $user->roles;
        
        if (in_array('administrator', $roles) || in_array('system_manager', $roles)) {
            return 'system_manager';
        }
        if (in_array('finance_manager', $roles)) {
            return 'finance_manager';
        }
        if (in_array('team_member', $roles)) {
            return 'team_member';
        }
        if (in_array('sales_consultant', $roles)) {
            return 'sales_consultant';
        }
        if (in_array('client', $roles) || in_array('customer', $roles)) {
            return 'client';
        }
        
        return 'guest';
    }

    private function get_role_label($user) {
        $roles = (array) $user->roles;
        
        if (in_array('administrator', $roles)) {
            return 'مدیر کل';
        }
        if (in_array('system_manager', $roles)) {
            return 'مدیر سیستم';
        }
        if (in_array('team_member', $roles)) {
            return 'کارمند';
        }
        if (in_array('sales_consultant', $roles)) {
            return 'کارشناس فروش';
        }
        if (in_array('client', $roles) || in_array('customer', $roles)) {
            return 'مشتری';
        }
        
        return 'کاربر';
    }

    public static function get_user_routes() {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        
        $user_role = 'guest';
        if (in_array('administrator', $roles) || in_array('system_manager', $roles)) {
            $user_role = 'system_manager';
        } elseif (in_array('team_member', $roles)) {
            $user_role = 'team_member';
        } elseif (in_array('sales_consultant', $roles)) {
            $user_role = 'sales_consultant';
        } elseif (in_array('client', $roles) || in_array('customer', $roles)) {
            $user_role = 'client';
        }

        $available_routes = [];
        $routes = self::get_routes();
        foreach ($routes as $slug => $route) {
            if (in_array($user_role, $route['roles'])) {
                $available_routes[$slug] = $route;
            }
        }

        return $available_routes;
    }

    public static function activate() {
        if ( class_exists( 'WebinoCRM_Dashboard_Rewrite' ) ) {
            $rewrite = new WebinoCRM_Dashboard_Rewrite();
            $rewrite->register_rewrites();
        } else {
            $instance = new self();
            $instance->add_rewrite_rules();
        }
        flush_rewrite_rules( false );
        update_option( 'webinocrm_dashboard_rewrite_version', defined( 'WEBINOCRM_VERSION' ) ? WEBINOCRM_VERSION : '3.0.1', false );
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
