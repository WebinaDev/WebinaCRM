<?php
/**
 * WebinoCRM Installer
 *
 * This class handles all the tasks that need to be run when the plugin is activated or deactivated.
 *
 * @package WebinoCRM
 */

class WebinoCRM_Installer {

    /**
     * Activation hook callback.
     */
    public static function activate() {
        // Call role creation
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-roles-manager.php';
        $roles_manager = new WebinoCRM_Roles_Manager();
        $roles_manager->add_custom_roles();

        // Call CPT registration to make them available
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-cpt-manager.php';
        $cpt_manager = new WebinoCRM_CPT_Manager();
        $cpt_manager->register_post_types();
        $cpt_manager->register_taxonomies();
        
        // Create default terms for taxonomies
        WebinoCRM_CPT_Manager::create_default_terms();

        // Add login page rewrite rules
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-login-page.php';
        WebinoCRM_Login_Page::activate();

        // Add dashboard rewrite rules
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-router.php';
        WebinoCRM_Dashboard_Router::activate();

        // Public rahn calculator rewrite
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-rahn-public-rewrite.php';
        WebinoCRM_Rahn_Public_Rewrite::activate();
        update_option( 'webinocrm_rahn_flush_rewrites', '1', false );

        // Hard cut-over migration: rename legacy identifiers to Webino naming.
        self::run_hard_cutover_migration();

        // Create database tables for new features
        self::create_database_tables();

        if ( function_exists( 'webinocrm_schedule_plugin_cron_events' ) ) {
            webinocrm_schedule_plugin_cron_events();
        }

        // Bale / Woo bot tables and CPT seeds (embedded WebinaBaleBusiness).
        self::activate_bale_integration();

        // Flush rewrite rules to make CPT URLs work correctly
        flush_rewrite_rules();

        // Defer Webina site seed until init (rewrite API must be ready).
        update_option( 'webinocrm_site_needs_seed', '1' );

        // HIGHLIGHT: The creation of the dashboard page is now disabled.
        // self::create_dashboard_page();
    }

    /**
     * Hard cut-over migration from legacy DB identifiers (pre-WebinoCRM table prefix, CPT slugs, option keys).
     *
     * IMPORTANT (branding audit): This method is the **only** allowed place in the plugin source for literals
     * that match the old CRM table prefix and legacy CPT slug prefixes stored in existing MySQL databases.
     * Do not introduce those legacy name fragments anywhere else in the codebase; use Webino naming.
     * فارسی: فقط همین متد مجاز به نگه‌داشتن نام‌های قدیمی دیتابیس برای migration است.
     */
    private static function run_hard_cutover_migration() {
        global $wpdb;

        // Rename legacy plugin tables to webinocrm_* when found (historical table prefix in existing installs).
        $legacy_tbl = $wpdb->prefix . 'puzzlingcrm_';
        $table_like = $wpdb->esc_like( $legacy_tbl ) . '%';
        $legacy_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ) );
        if ( is_array( $legacy_tables ) ) {
            foreach ( $legacy_tables as $legacy_table ) {
                $new_table = str_replace( $legacy_tbl, $wpdb->prefix . 'webinocrm_', $legacy_table );
                if ( $new_table === $legacy_table ) {
                    continue;
                }
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );
                if ( ! $exists ) {
                    $wpdb->query( "RENAME TABLE `$legacy_table` TO `$new_table`" );
                }
            }
        }

        // Normalize legacy CPT slugs (pzl_*, webino_*, and a few alternate names) to current canonical slugs.
        $post_type_map = [
            'pzl_lead'            => 'lead',
            'webino_lead'         => 'lead',
            'pzl_campaign'        => 'campaign',
            'webino_campaign'     => 'campaign',
            'pzl_canned_response' => 'canned_response',
            'webino_canned_response' => 'canned_response',
            'pzl_consultation'    => 'consultation',
            'webino_consultation' => 'consultation',
            'pzl_project_template' => 'project_template',
            'webino_project_template' => 'project_template',
            'pzl_epic'            => 'epic',
            'webino_epic'         => 'epic',
            'pzl_sprint'          => 'sprint',
            'webino_sprint'       => 'sprint',
            'pzl_task_template'   => 'task_template',
            'webino_task_template' => 'task_template',
            'pzl_project'         => 'project',
            'webino_project'      => 'project',
            'pzl_task'            => 'task',
            'webino_task'         => 'task',
            'pzl_contract'        => 'contract',
            'webino_contract'     => 'contract',
            'pzl_ticket'          => 'ticket',
            'webino_ticket'       => 'ticket',
            'pzl_appointment'     => 'appointment',
            'webino_appointment'  => 'appointment',
            'pzl_pro_invoice'     => 'pro_invoice',
            'webino_pro_invoice'  => 'pro_invoice',
            'pzl_form'            => 'crm_form',
            'webino_form'         => 'crm_form',
            'pzl_crm_form'        => 'crm_form',
            'webino_crm_form'     => 'crm_form',
            'pzl_log'             => 'crm_log',
            'webino_log'          => 'crm_log',
            'pzl_crm_log'         => 'crm_log',
            'webino_crm_log'      => 'crm_log',
        ];
        foreach ( $post_type_map as $from => $to ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
                    $to,
                    $from
                )
            );
        }

        // Convert option names used by plugin.
        $wpdb->query(
            "UPDATE {$wpdb->options}
             SET option_name = REPLACE(REPLACE(option_name, 'puzzlingcrm', 'webinocrm'), 'pzl_', 'webino_')
             WHERE option_name LIKE 'puzzlingcrm%'
                OR option_name LIKE 'pzl\\_%'
                OR option_name LIKE '%_pzl\\_%'"
        );

        // Convert user meta keys used by plugin.
        $wpdb->query(
            "UPDATE {$wpdb->usermeta}
             SET meta_key = REPLACE(REPLACE(meta_key, 'puzzlingcrm', 'webinocrm'), 'pzl_', 'webino_')
             WHERE meta_key LIKE 'puzzlingcrm%'
                OR meta_key LIKE 'pzl\\_%'
                OR meta_key LIKE '%_pzl\\_%'"
        );

        // Convert post meta keys used by plugin.
        $wpdb->query(
            "UPDATE {$wpdb->postmeta}
             SET meta_key = REPLACE(REPLACE(meta_key, 'puzzlingcrm', 'webinocrm'), 'pzl_', 'webino_')
             WHERE meta_key LIKE 'puzzlingcrm%'
                OR meta_key LIKE 'pzl\\_%'
                OR meta_key LIKE '%_pzl\\_%'"
        );

        // Legacy object-cache transients used prefix pcrm_; current code uses wcrm_ (see WebinoCRM_Cache_Optimizer).
        $wpdb->query(
            "UPDATE {$wpdb->options}
             SET option_name = REPLACE(option_name, '_transient_pcrm_', '_transient_wcrm_')
             WHERE option_name LIKE '_transient\\_pcrm\\_%'"
        );
        $wpdb->query(
            "UPDATE {$wpdb->options}
             SET option_name = REPLACE(option_name, '_transient_timeout_pcrm_', '_transient_timeout_wcrm_')
             WHERE option_name LIKE '_transient\\_timeout\\_pcrm\\_%'"
        );
        $wpdb->query(
            "UPDATE {$wpdb->options}
             SET option_name = REPLACE(option_name, '_site_transient_pcrm_', '_site_transient_wcrm_')
             WHERE option_name LIKE '_site\\_transient\\_pcrm\\_%'"
        );
        $wpdb->query(
            "UPDATE {$wpdb->options}
             SET option_name = REPLACE(option_name, '_site_transient_timeout_pcrm_', '_site_transient_timeout_wcrm_')
             WHERE option_name LIKE '_site\\_transient\\_timeout\\_pcrm\\_%'"
        );

        // Shorten Bale CPT slug (WP max 20 chars).
        $wpdb->query(
            "UPDATE {$wpdb->posts}
             SET post_type = 'wbb_biz_submit'
             WHERE post_type = 'wbb_business_submission'"
        );
    }

    /**
     * Bale bot: CPT/taxonomies must be registered before Activator seeds posts/terms.
     */
    private static function activate_bale_integration() {
        if ( ! class_exists( '\WebinaBaleBusiness\Admin\ContentTypes' ) ) {
            return;
        }
        \WebinaBaleBusiness\Admin\ContentTypes::register();
        if ( class_exists( '\WebinaBaleBusiness\Database\Activator' ) ) {
            \WebinaBaleBusiness\Database\Activator::activate();
        }
    }

    /**
     * Create all required database tables
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Notifications table
        $table_name = $wpdb->prefix . 'webinocrm_notifications';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data text,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) $charset_collate;";
        dbDelta($sql);

        // Activities table
        $table_name = $wpdb->prefix . 'webinocrm_activities';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action_type varchar(50) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            description text NOT NULL,
            metadata text,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY entity (entity_type, entity_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Reminders table
        $table_name = $wpdb->prefix . 'webinocrm_reminders';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            remind_at datetime NOT NULL,
            entity_type varchar(50),
            entity_id bigint(20),
            reminder_type varchar(20) DEFAULT 'manual',
            notification_channels text,
            recurring_pattern varchar(20),
            priority varchar(20) DEFAULT 'normal',
            status varchar(20) DEFAULT 'pending',
            metadata text,
            sent_at datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY remind_at (remind_at),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Time Entries table
        $table_name = $wpdb->prefix . 'webinocrm_time_entries';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            description text,
            start_time datetime NOT NULL,
            end_time datetime,
            duration_minutes decimal(10,2),
            paused_duration int DEFAULT 0,
            paused_at datetime,
            cost decimal(10,2) DEFAULT 0,
            status varchar(20) DEFAULT 'running',
            is_billable tinyint(1) DEFAULT 1,
            hourly_rate decimal(10,2) DEFAULT 0,
            is_manual tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY entity (entity_type, entity_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Documents table
        $table_name = $wpdb->prefix . 'webinocrm_documents';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entity_type varchar(50),
            entity_id bigint(20),
            folder_id bigint(20) DEFAULT 0,
            title varchar(255) NOT NULL,
            description text,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_hash varchar(64) NOT NULL,
            mime_type varchar(100) NOT NULL,
            uploaded_by bigint(20) NOT NULL,
            is_private tinyint(1) DEFAULT 0,
            is_deleted tinyint(1) DEFAULT 0,
            version int DEFAULT 1,
            downloads int DEFAULT 0,
            uploaded_at datetime NOT NULL,
            last_downloaded_at datetime,
            deleted_at datetime,
            PRIMARY KEY  (id),
            KEY entity (entity_type, entity_id),
            KEY uploaded_by (uploaded_by),
            KEY is_deleted (is_deleted)
        ) $charset_collate;";
        dbDelta($sql);

        // Document Folders table
        $table_name = $wpdb->prefix . 'webinocrm_document_folders';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            parent_id bigint(20) DEFAULT 0,
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Document Versions table
        $table_name = $wpdb->prefix . 'webinocrm_document_versions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            document_id bigint(20) NOT NULL,
            version int NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            file_hash varchar(64) NOT NULL,
            uploaded_by bigint(20) NOT NULL,
            uploaded_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY document_id (document_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Document Shares table
        $table_name = $wpdb->prefix . 'webinocrm_document_shares';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            document_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            permissions varchar(20) DEFAULT 'view',
            shared_by bigint(20) NOT NULL,
            shared_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_share (document_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Document Tags table
        $table_name = $wpdb->prefix . 'webinocrm_document_tags';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            document_id bigint(20) NOT NULL,
            tag varchar(100) NOT NULL,
            PRIMARY KEY  (id),
            KEY document_id (document_id),
            KEY tag (tag)
        ) $charset_collate;";
        dbDelta($sql);

        // Sessions table
        $table_name = $wpdb->prefix . 'webinocrm_sessions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            session_token varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            device_info text,
            location text,
            login_time datetime NOT NULL,
            last_activity datetime NOT NULL,
            logout_time datetime,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_active (is_active),
            KEY session_token (session_token)
        ) $charset_collate;";
        dbDelta($sql);

        // Chat Messages table
        $table_name = $wpdb->prefix . 'webinocrm_chat_messages';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            channel_id bigint(20) DEFAULT 0,
            recipient_id bigint(20) DEFAULT 0,
            message text NOT NULL,
            message_type varchar(20) DEFAULT 'text',
            parent_id bigint(20) DEFAULT 0,
            metadata text,
            is_deleted tinyint(1) DEFAULT 0,
            sent_at datetime NOT NULL,
            deleted_at datetime,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY channel_id (channel_id),
            KEY recipient_id (recipient_id),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Chat Channels table
        $table_name = $wpdb->prefix . 'webinocrm_chat_channels';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            type varchar(20) DEFAULT 'public',
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL,
            last_activity datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY type (type)
        ) $charset_collate;";
        dbDelta($sql);

        // Chat Channel Members table
        $table_name = $wpdb->prefix . 'webinocrm_chat_channel_members';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            channel_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            joined_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_member (channel_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Chat Read Receipts table
        $table_name = $wpdb->prefix . 'webinocrm_chat_read_receipts';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            read_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_receipt (message_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql);

        // System logs table
        $table_name = $wpdb->prefix . 'webinocrm_system_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL COMMENT 'error, debug, console, button_error',
            severity varchar(20) DEFAULT 'info' COMMENT 'info, warning, error, critical',
            message text NOT NULL,
            context longtext DEFAULT NULL COMMENT 'JSON data',
            file varchar(500) DEFAULT NULL,
            line int(11) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY log_type (log_type),
            KEY severity (severity),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address(45))
        ) $charset_collate;";
        dbDelta($sql);

        // User logs table
        $table_name = $wpdb->prefix . 'webinocrm_user_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action_type varchar(100) NOT NULL COMMENT 'button_click, form_submit, ajax_call, page_view',
            action_description varchar(500) NOT NULL,
            target_type varchar(100) DEFAULT NULL,
            target_id bigint(20) DEFAULT NULL,
            metadata longtext DEFAULT NULL COMMENT 'JSON data',
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY target_type (target_type),
            KEY target_id (target_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address(45))
        ) $charset_collate;";
        dbDelta($sql);

        // Visitors table (for visitor statistics)
        $table_name = $wpdb->prefix . 'webinocrm_visitors';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            country varchar(100) DEFAULT NULL,
            country_code varchar(2) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            browser_version varchar(50) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            os_version varchar(50) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL COMMENT 'desktop, mobile, tablet',
            device_model varchar(255) DEFAULT NULL,
            first_visit datetime DEFAULT CURRENT_TIMESTAMP,
            last_visit datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            visit_count int(11) DEFAULT 1,
            is_bot tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY ip_address (ip_address(45)),
            KEY last_visit (last_visit),
            KEY is_bot (is_bot)
        ) $charset_collate;";
        dbDelta($sql);

        // Visits table
        $table_name = $wpdb->prefix . 'webinocrm_visits';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitor_id bigint(20) NOT NULL,
            page_url varchar(500) NOT NULL,
            page_title varchar(255) DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            referrer_domain varchar(255) DEFAULT NULL,
            search_engine varchar(50) DEFAULT NULL,
            search_keyword text DEFAULT NULL,
            visit_date datetime DEFAULT CURRENT_TIMESTAMP,
            session_id varchar(100) DEFAULT NULL,
            entity_id bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY visitor_id (visitor_id),
            KEY page_url (page_url(255)),
            KEY visit_date (visit_date),
            KEY session_id (session_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Visitor pages table (aggregated page stats)
        $table_name = $wpdb->prefix . 'webinocrm_visitor_pages';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_url varchar(500) NOT NULL,
            page_title varchar(255) DEFAULT NULL,
            visit_count int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            last_visit datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY page_url (page_url(255)),
            KEY visit_count (visit_count),
            KEY last_visit (last_visit)
        ) $charset_collate;";
        dbDelta($sql);

        // Accounting module tables
        self::create_accounting_tables();

        // Licenses table
        self::create_license_table();

        // Marketplace tables
        self::create_marketplace_tables();

        // ModirPayamak SMS panel tables
        self::create_modirpayamak_tables();

        // HRM module tables
        self::create_hrm_tables();
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-schema.php';
        WebinoCRM_Hrm_Schema::ensure();

        // Rahn-percent quotes + monthly statements
        self::create_rahn_tables();
    }

    /**
     * Create rahn-percent module tables.
     *
     * @return void
     */
    public static function create_rahn_tables() {
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-rahn-service.php';
        WebinoCRM_Rahn_Service::install_tables();
        update_option( WebinoCRM_Rahn_Service::SCHEMA_OPTION, WebinoCRM_Rahn_Service::SCHEMA_VERSION, false );
    }

    /**
     * Create HRM module database tables.
     *
     * @return void
     */
    public static function create_hrm_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $p               = $wpdb->prefix . 'webinocrm_hrm_';

        $tables = array(
            "CREATE TABLE {$p}shift_templates (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(191) NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                grace_minutes int(11) DEFAULT 0,
                is_default tinyint(1) DEFAULT 0,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}attendance_records (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                work_date date NOT NULL,
                check_in time DEFAULT NULL,
                check_out time DEFAULT NULL,
                source varchar(20) DEFAULT 'self',
                status varchar(20) DEFAULT 'present',
                notes text,
                approved_by bigint(20) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_date (user_id, work_date)
            ) $charset_collate;",
            "CREATE TABLE {$p}leave_types (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                code varchar(50) NOT NULL,
                title varchar(191) NOT NULL,
                paid tinyint(1) DEFAULT 1,
                max_days_per_year decimal(6,2) DEFAULT 0,
                requires_attachment tinyint(1) DEFAULT 0,
                sort_order int(11) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY code (code)
            ) $charset_collate;",
            "CREATE TABLE {$p}leave_balances (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                leave_type_id bigint(20) NOT NULL,
                year smallint(4) NOT NULL,
                entitled decimal(6,2) DEFAULT 0,
                used decimal(6,2) DEFAULT 0,
                carried decimal(6,2) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY user_type_year (user_id, leave_type_id, year)
            ) $charset_collate;",
            "CREATE TABLE {$p}leave_requests (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                leave_type_id bigint(20) NOT NULL,
                start_date date NOT NULL,
                end_date date NOT NULL,
                days decimal(6,2) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                approver_id bigint(20) DEFAULT 0,
                reason text,
                attachment_id bigint(20) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_status (user_id, status)
            ) $charset_collate;",
            "CREATE TABLE {$p}salary_components (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                code varchar(50) NOT NULL,
                title varchar(191) NOT NULL,
                type varchar(20) NOT NULL,
                taxable tinyint(1) DEFAULT 1,
                gl_account_id bigint(20) DEFAULT 0,
                sort_order int(11) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY code (code)
            ) $charset_collate;",
            "CREATE TABLE {$p}employee_salaries (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                effective_from date NOT NULL,
                base_salary decimal(15,2) DEFAULT 0,
                components_json longtext,
                bank_iban varchar(34) DEFAULT '',
                PRIMARY KEY (id),
                KEY user_effective (user_id, effective_from)
            ) $charset_collate;",
            "CREATE TABLE {$p}payroll_settings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                salary_expense_account_id bigint(20) DEFAULT 0,
                salary_payable_account_id bigint(20) DEFAULT 0,
                tax_payable_account_id bigint(20) DEFAULT 0,
                insurance_payable_account_id bigint(20) DEFAULT 0,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}payroll_runs (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                period_start date NOT NULL,
                period_end date NOT NULL,
                title varchar(191) NOT NULL,
                status varchar(20) DEFAULT 'draft',
                journal_entry_id bigint(20) DEFAULT 0,
                total_gross decimal(15,2) DEFAULT 0,
                total_deductions decimal(15,2) DEFAULT 0,
                total_net decimal(15,2) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}payslips (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                run_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                gross decimal(15,2) DEFAULT 0,
                deductions decimal(15,2) DEFAULT 0,
                net decimal(15,2) DEFAULT 0,
                lines_json longtext,
                status varchar(20) DEFAULT 'draft',
                PRIMARY KEY (id),
                KEY run_user (run_id, user_id)
            ) $charset_collate;",
            "CREATE TABLE {$p}job_postings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(191) NOT NULL,
                department_id bigint(20) DEFAULT 0,
                description text,
                status varchar(20) DEFAULT 'open',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}applicants (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                job_posting_id bigint(20) DEFAULT 0,
                first_name varchar(100) NOT NULL,
                last_name varchar(100) NOT NULL,
                email varchar(191) DEFAULT '',
                phone varchar(50) DEFAULT '',
                stage varchar(30) DEFAULT 'applied',
                notes text,
                hired_user_id bigint(20) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY stage (stage)
            ) $charset_collate;",
            "CREATE TABLE {$p}interviews (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                applicant_id bigint(20) NOT NULL,
                scheduled_at datetime NOT NULL,
                interviewer_id bigint(20) DEFAULT 0,
                notes text,
                result varchar(30) DEFAULT '',
                PRIMARY KEY (id),
                KEY applicant (applicant_id)
            ) $charset_collate;",
            "CREATE TABLE {$p}onboarding_tasks (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                title varchar(191) NOT NULL,
                done tinyint(1) DEFAULT 0,
                sort_order int(11) DEFAULT 0,
                PRIMARY KEY (id),
                KEY user_id (user_id)
            ) $charset_collate;",
            "CREATE TABLE {$p}kpi_templates (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(191) NOT NULL,
                department_id bigint(20) DEFAULT 0,
                criteria_json longtext,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}review_cycles (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(191) NOT NULL,
                period_start date NOT NULL,
                period_end date NOT NULL,
                status varchar(20) DEFAULT 'open',
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}reviews (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                cycle_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                reviewer_id bigint(20) DEFAULT 0,
                self_notes text,
                manager_notes text,
                total_score decimal(5,2) DEFAULT 0,
                status varchar(20) DEFAULT 'draft',
                PRIMARY KEY (id),
                KEY cycle_user (cycle_id, user_id)
            ) $charset_collate;",
            "CREATE TABLE {$p}review_scores (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                review_id bigint(20) NOT NULL,
                criterion_key varchar(100) NOT NULL,
                self_score decimal(5,2) DEFAULT 0,
                manager_score decimal(5,2) DEFAULT 0,
                weight decimal(5,2) DEFAULT 1,
                PRIMARY KEY (id),
                KEY review_id (review_id)
            ) $charset_collate;",
            "CREATE TABLE {$p}courses (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(191) NOT NULL,
                description text,
                status varchar(20) DEFAULT 'active',
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE {$p}course_sessions (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                course_id bigint(20) NOT NULL,
                session_date date NOT NULL,
                start_time time DEFAULT NULL,
                end_time time DEFAULT NULL,
                capacity int(11) DEFAULT 0,
                instructor_id bigint(20) DEFAULT 0,
                location varchar(191) DEFAULT '',
                PRIMARY KEY (id),
                KEY course_id (course_id)
            ) $charset_collate;",
            "CREATE TABLE {$p}enrollments (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                course_id bigint(20) NOT NULL,
                session_id bigint(20) DEFAULT 0,
                user_id bigint(20) NOT NULL,
                status varchar(20) DEFAULT 'enrolled',
                completed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY user_course (user_id, course_id)
            ) $charset_collate;",
        );

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        $settings = $wpdb->get_var( "SELECT id FROM {$p}payroll_settings LIMIT 1" );
        if ( ! $settings ) {
            $wpdb->insert( $p . 'payroll_settings', array( 'id' => 1 ), array( '%d' ) );
        }
    }

    /**
     * Create marketplace module store tables.
     *
     * @return void
     */
    public static function create_marketplace_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $prefix = $wpdb->prefix . 'webinocrm_';

        $table = $prefix . 'marketplace_categories';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            sort int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'marketplace_modules';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            readme_md longtext DEFAULT NULL,
            icon_url varchar(500) DEFAULT NULL,
            detail_url varchar(500) DEFAULT NULL,
            category_id bigint(20) DEFAULT NULL,
            parent_module_id bigint(20) DEFAULT NULL,
            price decimal(12,2) DEFAULT 0,
            currency varchar(10) DEFAULT 'IRT',
            is_free tinyint(1) DEFAULT 0,
            is_builtin tinyint(1) DEFAULT 0,
            is_core tinyint(1) DEFAULT 0,
            package_path varchar(500) DEFAULT NULL,
            package_source varchar(20) DEFAULT 'local',
            gitea_owner varchar(100) DEFAULT NULL,
            gitea_repo varchar(100) DEFAULT NULL,
            gitea_repo_id bigint(20) DEFAULT NULL,
            latest_release_id bigint(20) DEFAULT NULL,
            version varchar(50) DEFAULT '1.0.0',
            settings_area varchar(20) DEFAULT 'shop',
            settings_route varchar(255) DEFAULT NULL,
            sort int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id),
            KEY parent_module_id (parent_module_id),
            KEY status (status),
            KEY package_source (package_source)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'marketplace_releases';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            module_id bigint(20) NOT NULL,
            version varchar(50) NOT NULL,
            tag_name varchar(100) NOT NULL,
            changelog text,
            gitea_release_id bigint(20) DEFAULT NULL,
            package_path varchar(500) DEFAULT NULL,
            package_source varchar(30) DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            published_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY module_id (module_id),
            KEY status (status),
            KEY module_version (module_id, version)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'marketplace_entitlements';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            module_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'owned',
            order_id bigint(20) DEFAULT NULL,
            installed_version varchar(50) DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY domain_module (domain, module_id),
            KEY domain (domain),
            KEY module_id (module_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'marketplace_orders';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            module_id bigint(20) NOT NULL,
            amount decimal(12,2) NOT NULL,
            currency varchar(10) DEFAULT 'IRT',
            authority varchar(100) DEFAULT NULL,
            ref_id varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain (domain),
            KEY authority (authority),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-marketplace-manager.php';
        WebinoCRM_Marketplace_Manager::seed_builtin_modules();
    }

    /**
     * Create ModirPayamak (SMS panel) tables.
     *
     * @return void
     */
    public static function create_modirpayamak_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $prefix = $wpdb->prefix . 'webinocrm_modirpayamak_';

        $table = $prefix . 'accounts';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            balance decimal(14,2) DEFAULT 0,
            default_from varchar(50) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY domain (domain),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'ledger';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            account_id bigint(20) NOT NULL,
            type varchar(20) NOT NULL,
            amount decimal(14,2) NOT NULL,
            balance_after decimal(14,2) NOT NULL,
            ref_type varchar(50) DEFAULT NULL,
            ref_id bigint(20) DEFAULT NULL,
            note varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY account_id (account_id),
            KEY type (type)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'packages';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            amount decimal(14,2) NOT NULL,
            bonus decimal(14,2) DEFAULT 0,
            sort int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'tariffs';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            line_type varchar(50) NOT NULL,
            operator varchar(20) NOT NULL DEFAULT 'other',
            rate_fa decimal(14,4) NOT NULL DEFAULT 0,
            rate_la decimal(14,4) NOT NULL DEFAULT 0,
            sort int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY line_operator (line_type, operator),
            KEY status (status),
            KEY sort (sort)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'orders';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            package_id bigint(20) DEFAULT NULL,
            amount decimal(14,2) NOT NULL,
            credit_amount decimal(14,2) NOT NULL,
            authority varchar(100) DEFAULT NULL,
            ref_id varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain (domain),
            KEY authority (authority),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'messages';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            sending_type varchar(50) DEFAULT 'webservice',
            recipients text,
            message_body longtext,
            outbox_id varchar(100) DEFAULT NULL,
            cost decimal(14,2) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            provider_response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain (domain),
            KEY outbox_id (outbox_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'phonebooks';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            ippanel_id bigint(20) DEFAULT NULL,
            name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain (domain)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'contacts';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phonebook_id bigint(20) NOT NULL,
            name varchar(255) DEFAULT NULL,
            phone varchar(30) NOT NULL,
            email varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phonebook_id (phonebook_id),
            KEY phone (phone)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'domain_numbers';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            number varchar(50) NOT NULL,
            role varchar(20) NOT NULL DEFAULT 'service',
            label varchar(255) DEFAULT NULL,
            is_default tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY domain_number (domain, number),
            KEY domain (domain),
            KEY number (number),
            KEY domain_role (domain, role)
        ) $charset_collate;";
        dbDelta( $sql );

        require_once WEBINOCRM_PLUGIN_DIR . 'includes/sms/class-sms-install.php';
        WebinoCRM_Sms_Install::create_tables();

        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-modirpayamak-manager.php';
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-modirpayamak-tariffs.php';
        WebinoCRM_ModirPayamak_Manager::seed_default_packages();
        WebinoCRM_ModirPayamak_Tariffs::seed_defaults();
    }

    /**
     * Create accounting module database tables (Iranian standard).
     */
    private static function create_accounting_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $prefix = $wpdb->prefix . 'webinocrm_';

        // Fiscal years
        $table = $prefix . 'accounting_fiscal_years';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta( $sql );

        // Chart of accounts (hierarchical: group, class, ledger, detail)
        $table = $prefix . 'accounting_chart_accounts';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(20) NOT NULL,
            title varchar(255) NOT NULL,
            level tinyint(1) NOT NULL COMMENT '1=group, 2=class, 3=ledger, 4=detail',
            parent_id bigint(20) DEFAULT 0,
            account_type varchar(20) NOT NULL COMMENT 'asset, liability, equity, income, expense',
            fiscal_year_id bigint(20) NOT NULL,
            is_system tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code_fiscal (code, fiscal_year_id),
            KEY fiscal_year_id (fiscal_year_id),
            KEY parent_id (parent_id),
            KEY account_type (account_type)
        ) $charset_collate;";
        dbDelta( $sql );

        // Journal entries (voucher header)
        $table = $prefix . 'accounting_journal_entries';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fiscal_year_id bigint(20) NOT NULL,
            voucher_no varchar(50) NOT NULL,
            voucher_date date NOT NULL,
            description text,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) DEFAULT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, posted',
            PRIMARY KEY (id),
            KEY fiscal_year_id (fiscal_year_id),
            KEY voucher_date (voucher_date),
            KEY status (status),
            KEY reference (reference_type, reference_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Journal lines (voucher rows: debit/credit per account)
        $table = $prefix . 'accounting_journal_lines';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            journal_entry_id bigint(20) NOT NULL,
            account_id bigint(20) NOT NULL,
            debit decimal(18,2) NOT NULL DEFAULT 0,
            credit decimal(18,2) NOT NULL DEFAULT 0,
            description text,
            cost_center_id bigint(20) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY journal_entry_id (journal_entry_id),
            KEY account_id (account_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Sub-ledger (detail level: contact, project, etc.)
        $table = $prefix . 'accounting_sub_ledger';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            account_id bigint(20) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            code varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY account_id (account_id),
            KEY entity (entity_type, entity_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // --- Phase 1: Persons (اشخاص) and Goods/Services (کالا و خدمات) ---

        // Person categories (دسته‌بندی اشخاص)
        $table = $prefix . 'accounting_person_categories';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            parent_id bigint(20) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Persons / counterparties (طرف‌های حساب)
        $table = $prefix . 'accounting_persons';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(50) DEFAULT NULL,
            name varchar(255) NOT NULL,
            category_id bigint(20) DEFAULT NULL,
            credit_limit decimal(18,2) DEFAULT NULL,
            national_id varchar(20) DEFAULT NULL COMMENT 'شناسه ملی',
            economic_code varchar(20) DEFAULT NULL COMMENT 'کد اقتصادی',
            registration_no varchar(50) DEFAULT NULL COMMENT 'شماره ثبت',
            phone varchar(50) DEFAULT NULL,
            mobile varchar(50) DEFAULT NULL,
            extra_phones text DEFAULT NULL COMMENT 'JSON or semicolon-separated',
            address text DEFAULT NULL,
            person_type varchar(20) NOT NULL DEFAULT 'both' COMMENT 'customer, supplier, both',
            group_id bigint(20) DEFAULT NULL COMMENT 'گروه برای محدودیت دسترسی',
            image_url varchar(500) DEFAULT NULL,
            note text DEFAULT NULL,
            default_price_list_id bigint(20) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY person_type (person_type),
            KEY is_active (is_active),
            KEY name (name(100)),
            KEY code (code)
        ) $charset_collate;";
        dbDelta( $sql );

        // Product categories (دسته‌بندی درختی کالا)
        $table = $prefix . 'accounting_product_categories';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            parent_id bigint(20) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Units (واحد اصلی و فرعی)
        $table = $prefix . 'accounting_units';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(50) NOT NULL,
            symbol varchar(20) DEFAULT NULL,
            is_main tinyint(1) DEFAULT 1 COMMENT '1=main, 0=sub',
            base_unit_id bigint(20) DEFAULT NULL,
            ratio_to_base decimal(18,4) DEFAULT 1 COMMENT 'e.g. 12 for 1 box = 12 pcs',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY base_unit_id (base_unit_id)
        ) $charset_collate;";
        dbDelta( $sql );
        // Insert default main unit if empty (required for products)
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        if ( $count === 0 ) {
            $wpdb->insert(
                $table,
                array( 'name' => 'عدد', 'symbol' => 'عد', 'is_main' => 1, 'ratio_to_base' => 1 ),
                array( '%s', '%s', '%d', '%f' )
            );
        }

        // Price lists (لیست قیمت)
        $table = $prefix . 'accounting_price_lists';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            valid_from date DEFAULT NULL,
            valid_to date DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_default (is_default)
        ) $charset_collate;";
        dbDelta( $sql );

        // Products / Goods and services (کالا و خدمات)
        $table = $prefix . 'accounting_products';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            category_id bigint(20) DEFAULT NULL,
            main_unit_id bigint(20) NOT NULL,
            sub_unit_id bigint(20) DEFAULT NULL,
            sub_unit_ratio decimal(18,4) DEFAULT 1,
            purchase_price decimal(18,2) DEFAULT NULL,
            barcode text DEFAULT NULL COMMENT 'multiple barcodes semicolon-separated',
            inventory_controlled tinyint(1) DEFAULT 0,
            reorder_point decimal(18,2) DEFAULT NULL,
            tax_rate_sales decimal(8,2) DEFAULT NULL,
            tax_rate_purchase decimal(8,2) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            note text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY category_id (category_id),
            KEY main_unit_id (main_unit_id),
            KEY is_active (is_active),
            KEY name (name(100))
        ) $charset_collate;";
        dbDelta( $sql );

        // Price list items (قیمت در هر لیست قیمت)
        $table = $prefix . 'accounting_price_list_items';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            price_list_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            price decimal(18,2) NOT NULL,
            min_quantity decimal(18,2) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY list_product (price_list_id, product_id),
            KEY price_list_id (price_list_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // User defaults (شخص/لیست قیمت پیش‌فرض برای فاکتور)
        $table = $prefix . 'accounting_user_defaults';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            default_invoice_person_id bigint(20) DEFAULT NULL,
            default_price_list_id bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // --- Phase 2: Invoices (فاکتور خرید و فروش) ---
        $table = $prefix . 'accounting_invoices';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fiscal_year_id bigint(20) NOT NULL,
            invoice_no varchar(50) NOT NULL,
            invoice_type varchar(20) NOT NULL DEFAULT 'sales' COMMENT 'proforma, sales, purchase',
            person_id bigint(20) NOT NULL,
            invoice_date date NOT NULL,
            due_date date DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, confirmed, returned',
            seller_id bigint(20) DEFAULT NULL COMMENT 'فروشنده',
            project_id bigint(20) DEFAULT NULL,
            shipping_cost decimal(18,2) DEFAULT NULL,
            extra_additions decimal(18,2) DEFAULT NULL,
            extra_deductions decimal(18,2) DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) DEFAULT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY fiscal_no (fiscal_year_id, invoice_no),
            KEY person_id (person_id),
            KEY invoice_date (invoice_date),
            KEY status (status),
            KEY reference (reference_type, reference_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'accounting_invoice_lines';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity decimal(18,4) NOT NULL DEFAULT 1,
            unit_id bigint(20) DEFAULT NULL,
            unit_price decimal(18,2) NOT NULL DEFAULT 0,
            discount_percent decimal(8,2) DEFAULT NULL,
            discount_amount decimal(18,2) DEFAULT NULL,
            tax_percent decimal(8,2) DEFAULT NULL,
            tax_amount decimal(18,2) DEFAULT NULL,
            description text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // --- Phase 3: Cash accounts (صندوق/بانک/تنخواه) and Receipt/Payment (رسید و پرداخت) ---
        $table = $prefix . 'accounting_cash_accounts';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'bank' COMMENT 'bank, cash, petty',
            code varchar(50) DEFAULT NULL,
            description text DEFAULT NULL,
            card_no varchar(50) DEFAULT NULL,
            sheba varchar(34) DEFAULT NULL COMMENT 'شبا',
            chart_account_id bigint(20) DEFAULT NULL COMMENT 'حساب معین در نمودار حساب‌ها',
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $prefix . 'accounting_receipt_vouchers';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fiscal_year_id bigint(20) NOT NULL,
            voucher_no varchar(50) NOT NULL,
            voucher_date date NOT NULL,
            type varchar(20) NOT NULL COMMENT 'receipt, payment, transfer',
            cash_account_id bigint(20) NOT NULL COMMENT 'حساب صندوق/بانک (برای انتقال: مبدا)',
            transfer_to_cash_account_id bigint(20) DEFAULT NULL COMMENT 'فقط برای انتقال',
            person_id bigint(20) DEFAULT NULL COMMENT 'طرف حساب',
            amount decimal(18,2) NOT NULL DEFAULT 0,
            description text DEFAULT NULL,
            invoice_id bigint(20) DEFAULT NULL COMMENT 'تسویه فاکتور',
            project_id bigint(20) DEFAULT NULL,
            bank_fee decimal(18,2) DEFAULT NULL COMMENT 'کارمزد بانکی',
            journal_entry_id bigint(20) DEFAULT NULL COMMENT 'سند خودکار',
            created_by bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, posted',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY fiscal_no (fiscal_year_id, voucher_no),
            KEY cash_account_id (cash_account_id),
            KEY person_id (person_id),
            KEY voucher_date (voucher_date),
            KEY type (type),
            KEY status (status),
            KEY invoice_id (invoice_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // --- Phase 4: Cheques (چک دریافتی و پرداختی) ---
        $table = $prefix . 'accounting_checks';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL COMMENT 'receivable, payable',
            check_no varchar(50) NOT NULL COMMENT 'شماره چک',
            check_date date DEFAULT NULL COMMENT 'تاریخ چک',
            amount decimal(18,2) NOT NULL DEFAULT 0,
            cash_account_id bigint(20) NOT NULL COMMENT 'بانک',
            person_id bigint(20) NOT NULL COMMENT 'طرف حساب',
            due_date date NOT NULL COMMENT 'تاریخ سررسید',
            status varchar(20) NOT NULL DEFAULT 'in_safe' COMMENT 'in_safe, collected, returned, spent',
            receipt_voucher_id bigint(20) DEFAULT NULL COMMENT 'رسید/پرداخت مرتبط',
            description text DEFAULT NULL,
            journal_entry_id bigint(20) DEFAULT NULL COMMENT 'سند عملیات چک',
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY cash_account_id (cash_account_id),
            KEY person_id (person_id),
            KEY due_date (due_date),
            KEY status (status),
            KEY receipt_voucher_id (receipt_voucher_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // --- Phase 5: Warehouse (انبارداری) ---
        
        // Warehouses (انبارها)
        $table = $prefix . 'accounting_warehouses';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            code varchar(50) DEFAULT NULL,
            description text DEFAULT NULL,
            location text DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_default (is_default),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse item transactions (تراکنش‌های موجودی)
        $table = $prefix . 'accounting_warehouse_transactions';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            warehouse_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            transaction_type varchar(20) NOT NULL COMMENT 'inbound, outbound',
            quantity decimal(18,4) NOT NULL,
            unit_id bigint(20) DEFAULT NULL,
            document_type varchar(50) DEFAULT NULL COMMENT 'goods_receipt, invoice, transfer, adjustment, audit',
            document_id bigint(20) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) NOT NULL,
            transaction_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY warehouse_id (warehouse_id),
            KEY product_id (product_id),
            KEY transaction_type (transaction_type),
            KEY document_type (document_type),
            KEY transaction_date (transaction_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse current stock (موجودی فعلی هر کالا در هر انبار)
        $table = $prefix . 'accounting_warehouse_stock';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            warehouse_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity decimal(18,4) NOT NULL DEFAULT 0,
            unit_id bigint(20) DEFAULT NULL,
            valuation_method varchar(20) DEFAULT 'fifo' COMMENT 'fifo, lifo, average',
            last_transaction_date datetime DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY warehouse_product (warehouse_id, product_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse inbound (رسید کالا/انبار دریافت)
        $table = $prefix . 'accounting_warehouse_inbound';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            warehouse_id bigint(20) NOT NULL,
            reference_type varchar(50) DEFAULT NULL COMMENT 'purchase_invoice, purchase_return, transfer, adjustment',
            reference_id bigint(20) DEFAULT NULL,
            inbound_no varchar(50) NOT NULL,
            inbound_date date NOT NULL,
            description text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, received, posted',
            created_by bigint(20) NOT NULL,
            posted_by bigint(20) DEFAULT NULL,
            posted_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY warehouse_id (warehouse_id),
            KEY inbound_date (inbound_date),
            KEY status (status),
            KEY reference (reference_type, reference_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse inbound items (سطرهای رسید انبار)
        $table = $prefix . 'accounting_warehouse_inbound_items';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            inbound_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity_ordered decimal(18,4) DEFAULT NULL,
            quantity_received decimal(18,4) NOT NULL DEFAULT 0,
            unit_id bigint(20) DEFAULT NULL,
            unit_price decimal(18,2) DEFAULT NULL,
            location varchar(255) DEFAULT NULL COMMENT 'رک، قفسه، موقعیت',
            description text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY inbound_id (inbound_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse outbound (حواله/انبار صدور)
        $table = $prefix . 'accounting_warehouse_outbound';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            warehouse_id bigint(20) NOT NULL,
            reference_type varchar(50) DEFAULT NULL COMMENT 'sales_invoice, sales_return, transfer, adjustment',
            reference_id bigint(20) DEFAULT NULL,
            outbound_no varchar(50) NOT NULL,
            outbound_date date NOT NULL,
            destination_warehouse_id bigint(20) DEFAULT NULL COMMENT 'برای انتقال بین انبار',
            description text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, shipped, posted',
            created_by bigint(20) NOT NULL,
            posted_by bigint(20) DEFAULT NULL,
            posted_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY warehouse_id (warehouse_id),
            KEY outbound_date (outbound_date),
            KEY status (status),
            KEY reference (reference_type, reference_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse outbound items (سطرهای حواله)
        $table = $prefix . 'accounting_warehouse_outbound_items';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            outbound_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity_ordered decimal(18,4) DEFAULT NULL,
            quantity_shipped decimal(18,4) NOT NULL DEFAULT 0,
            unit_id bigint(20) DEFAULT NULL,
            unit_price decimal(18,2) DEFAULT NULL,
            location varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY outbound_id (outbound_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse audit (انبارگردانی/شمارش فیزیکی)
        $table = $prefix . 'accounting_warehouse_audits';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            warehouse_id bigint(20) NOT NULL,
            audit_no varchar(50) NOT NULL,
            audit_date date NOT NULL,
            fiscal_year_id bigint(20) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, in_progress, completed, posted',
            valuation_method varchar(20) DEFAULT 'fifo' COMMENT 'روش ارزیابی: fifo, lifo, average',
            total_discrepancy_amount decimal(18,2) DEFAULT 0,
            journal_entry_id bigint(20) DEFAULT NULL COMMENT 'سند توازن خودکار',
            created_by bigint(20) NOT NULL,
            posted_by bigint(20) DEFAULT NULL,
            posted_at datetime DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY warehouse_id (warehouse_id),
            KEY audit_date (audit_date),
            KEY status (status),
            KEY fiscal_year_id (fiscal_year_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse audit items (سطرهای انبارگردانی)
        $table = $prefix . 'accounting_warehouse_audit_items';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            unit_id bigint(20) DEFAULT NULL,
            system_quantity decimal(18,4) NOT NULL DEFAULT 0 COMMENT 'موجودی سیستمی',
            physical_quantity decimal(18,4) NOT NULL DEFAULT 0 COMMENT 'موجودی فیزیکی',
            variance_quantity decimal(18,4) NOT NULL DEFAULT 0 COMMENT 'اختلاف',
            unit_price decimal(18,2) DEFAULT NULL,
            variance_amount decimal(18,2) DEFAULT NULL COMMENT 'اختلاف ارزشی',
            variance_type varchar(20) DEFAULT NULL COMMENT 'surplus, shortage',
            location varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY audit_id (audit_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warehouse audit entry document (سند نتیجه انبارگردانی)
        $table = $prefix . 'accounting_warehouse_audit_entries';
        $sql   = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_id bigint(20) NOT NULL,
            journal_entry_id bigint(20) DEFAULT NULL,
            account_id bigint(20) NOT NULL,
            amount decimal(18,2) NOT NULL,
            type varchar(20) NOT NULL COMMENT 'debit, credit',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY audit_id (audit_id),
            KEY journal_entry_id (journal_entry_id),
            KEY account_id (account_id)
        ) $charset_collate;";
        dbDelta( $sql );
    }

    /**
     * Create licenses table
     */
    private static function create_license_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->prefix . 'webinocrm_licenses';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project_name varchar(255) NOT NULL,
            domain varchar(255) NOT NULL,
            license_key varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'inactive',
            expiry_date datetime DEFAULT NULL,
            start_date datetime DEFAULT NULL,
            logo_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY domain (domain),
            UNIQUE KEY license_key (license_key),
            KEY status (status),
            KEY expiry_date (expiry_date)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Deactivation hook callback.
     * Cleans up roles created by the plugin.
     */
    public static function deactivate() {
        // Call role removal
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-roles-manager.php';
        $roles_manager = new WebinoCRM_Roles_Manager();
        $roles_manager->remove_custom_roles();
        
        // Clean up login page rewrite rules
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-login-page.php';
        WebinoCRM_Login_Page::deactivate();
        
        // Clean up dashboard rewrite rules
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-dashboard-router.php';
        WebinoCRM_Dashboard_Router::deactivate();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Creates the frontend dashboard page if it doesn't exist
     * and stores its ID in the options table.
     * HIGHLIGHT: This function is no longer called on activation.
     */
    private static function create_dashboard_page() {
        $dashboard_page_id = get_option('webino_dashboard_page_id', 0);

        // Check if the page exists and is published
        if ( $dashboard_page_id && get_post_status($dashboard_page_id) === 'publish' ) {
            return;
        }

        // Create the page
        $page_id = wp_insert_post([
            'post_title'    => 'WebinoCRM Dashboard',
            'post_content'  => '[webino_dashboard]',
            'post_status'   => 'publish',
            'post_author'   => 1, // Assign to the main admin
            'post_type'     => 'page',
        ]);

        if ($page_id > 0 && !is_wp_error($page_id)) {
            // Store the page ID so we can retrieve it later reliably
            update_option('webino_dashboard_page_id', $page_id);
        }
    }
}