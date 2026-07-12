<?php
/**
 * WebinoCRM Custom Post Type & Taxonomy Manager
 *
 * This class handles the registration of all custom post types and taxonomies
 * required for the plugin to function, including new Agile and Consultation features.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WebinoCRM_CPT_Manager {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ], 0 );
        add_action( 'init', [ $this, 'register_taxonomies' ], 0 );
        // Add meta box for linking templates to products
        add_action( 'add_meta_boxes', [ $this, 'add_project_template_meta_box' ] );
        add_action( 'save_post_product', [ $this, 'save_project_template_meta_box' ] );
        // NEW: Add meta box for task templates
        add_action( 'add_meta_boxes', [ $this, 'add_task_template_meta_box' ] );
        add_action( 'save_post_task_template', [ $this, 'save_task_template_meta_box' ] );
    }

    public function register_post_types() {
        // === START: LEAD CPT (NEW) ===
        register_post_type( 'lead', [
            'labels'        => [
                'name'                  => _x('سرنخ‌ها', 'Post Type General Name', 'webinocrm'),
                'singular_name'         => _x('سرنخ', 'Post Type Singular Name', 'webinocrm'),
                'menu_name'             => __('سرنخ‌ها', 'webinocrm'),
                'all_items'             => __('همه سرنخ‌ها', 'webinocrm'),
                'add_new_item'          => __('افزودن سرنخ جدید', 'webinocrm'),
                'add_new'               => __('افزودن جدید', 'webinocrm'),
                'edit_item'             => __('ویرایش سرنخ', 'webinocrm'),
                'search_items'          => __('جستجوی سرنخ', 'webinocrm'),
            ],
            'public'        => false, // Not public on the frontend website
            'show_ui'       => true,  // Show in the admin panel
            'show_in_menu'  => 'webino-crm', // Main menu page slug
            'supports'      => ['title', 'editor', 'author', 'custom-fields'], // 'editor' for notes
            'hierarchical'  => false,
            'taxonomies'    => ['lead_status', 'lead_source'],
            'menu_icon'     => 'dashicons-businessperson',
        ]);
        // === END: LEAD CPT (NEW) ===

        // Campaign CPT
        register_post_type( 'campaign', [
            'labels'        => [
                'name'          => __( 'کمپین‌ها', 'webinocrm' ),
                'singular_name' => __( 'کمپین', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'webino-crm',
            'supports'      => ['title', 'editor'],
            'hierarchical'  => false,
        ]);

        // Canned Response CPT - NEW
        register_post_type( 'canned_response', [
            'labels'        => [
                'name'          => __( 'پاسخ‌های آماده', 'webinocrm' ),
                'singular_name' => __( 'پاسخ آماده', 'webinocrm' ),
                'add_new_item'  => __( 'افزودن پاسخ جدید', 'webinocrm' ),
                'add_new'       => __( 'افزودن جدید', 'webinocrm' ),
                'edit_item'     => __( 'ویرایش پاسخ آماده', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'webino-crm-info',
            'supports'      => ['title', 'editor'],
            'hierarchical'  => false,
        ]);
        
        // Consultation CPT - NEW
        register_post_type( 'consultation', [
            'labels'        => [
                'name'          => __( 'مشاوره‌ها', 'webinocrm' ),
                'singular_name' => __( 'مشاوره', 'webinocrm' ),
                'add_new_item'  => __( 'افزودن مشاوره جدید', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true, // Show in admin for debugging, but managed via frontend
            'show_in_menu'  => 'webino-crm-info',
            'supports'      => ['title', 'editor', 'author', 'custom-fields'],
            'hierarchical'  => false,
        ]);

        // Project Template CPT
        register_post_type( 'project_template', [
            'labels'        => [
                'name'          => __( 'قالب‌های پروژه', 'webinocrm' ),
                'singular_name' => __( 'قالب پروژه', 'webinocrm' ),
                'add_new_item'  => __( 'افزودن قالب پروژه جدید', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'webino-crm-info', // Show under main CRM menu
            'supports'      => ['title'],
            'hierarchical'  => false,
        ]);

        // **MODIFIED: Form CPT - Hidden from backend UI**
        register_post_type( 'crm_form', [
            'labels'        => [
                'name'          => __( 'Forms', 'webinocrm' ),
                'singular_name' => __( 'Form', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => false, // Completely hide from admin UI
            'show_in_menu'  => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'supports'      => ['title', 'editor', 'author'],
        ]);

        // Epic CPT
        register_post_type( 'epic', [
            'labels'        => [
                'name'          => __( 'Epics', 'webinocrm' ),
                'singular_name' => __( 'Epic', 'webinocrm' ),
                'add_new_item'  => __( 'Add New Epic', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'rewrite'       => ['slug' => 'epic'],
            'show_in_rest'  => true,
            'supports'      => ['title', 'editor', 'author', 'custom-fields'],
        ]);

        // Sprint CPT
        register_post_type( 'sprint', [
            'labels'        => [
                'name'          => __( 'Sprints', 'webinocrm' ),
                'singular_name' => __( 'Sprint', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'author', 'custom-fields'],
        ]);

        // Task Template CPT
        register_post_type( 'task_template', [
            'labels'        => [
                'name'          => __( 'قالب‌های تسک', 'webinocrm' ),
                'singular_name' => __( 'قالب تسک', 'webinocrm' ),
                'add_new_item'  => __( 'افزودن قالب تسک جدید', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'webino-crm-info',
            'supports'      => ['title', 'editor', 'custom-fields'],
        ]);
        
        // Project CPT
        register_post_type( 'project', [
            'labels'        => [
                'name'          => __( 'Projects', 'webinocrm' ),
                'singular_name' => __( 'Project', 'webinocrm' ),
            ],
            'public'        => true,
            'show_in_menu'  => false,
            'rewrite'       => ['slug' => 'project'],
            'show_in_rest'  => true,
            'supports'      => ['title', 'editor', 'author', 'custom-fields', 'thumbnail'],
            'has_archive'   => true,
        ]);

        // Task CPT
        register_post_type( 'task', [
            'labels'        => [
                'name'          => __( 'Tasks', 'webinocrm' ),
                'singular_name' => __( 'Task', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'hierarchical'  => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'editor', 'author', 'custom-fields', 'comments', 'page-attributes', 'thumbnail'],
        ]);

        // Contract CPT
        register_post_type( 'contract', [
            'labels'        => [
                'name'          => __( 'Contracts', 'webinocrm' ),
                'singular_name' => __( 'Contract', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'author', 'custom-fields'],
        ]);
        
        // Log & Notification CPT
        register_post_type( 'crm_log', [
            'labels'        => [
                'name'          => __( 'Logs & Notifications', 'webinocrm' ),
                'singular_name' => __( 'Log', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'editor', 'author'],
        ]);

        // Ticket CPT
        register_post_type( 'ticket', [
            'labels'        => [
                'name'          => __( 'Tickets', 'webinocrm' ),
                'singular_name' => __( 'Ticket', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'editor', 'author', 'comments', 'custom-fields'],
        ]);

        // Appointment CPT
        register_post_type( 'appointment', [
            'labels'        => [
                'name'          => __( 'Appointments', 'webinocrm' ),
                'singular_name' => __( 'Appointment', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'editor', 'author', 'custom-fields'],
        ]);

        // Pro-forma Invoice CPT
        register_post_type( 'pro_invoice', [
            'labels'        => [
                'name'          => __( 'Pro-forma Invoices', 'webinocrm' ),
                'singular_name' => __( 'Pro-forma Invoice', 'webinocrm' ),
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false,
            'supports'      => ['title', 'editor', 'author', 'custom-fields'],
        ]);
    }

    public function register_taxonomies() {
        // === START: LEAD STATUS TAXONOMY (NEW) ===
        register_taxonomy('lead_status', 'lead', [
            'label' => __( 'وضعیت سرنخ', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'وضعیت‌های سرنخ', 'webinocrm' ),
                'singular_name' => __( 'وضعیت سرنخ', 'webinocrm' ),
                'add_new_item'  => __( 'افزودن وضعیت جدید', 'webinocrm' ),
                'edit_item'     => __( 'ویرایش وضعیت', 'webinocrm' ),
            ]
        ]);
        // === END: LEAD STATUS TAXONOMY (NEW) ===

        // Lead Source Taxonomy
        register_taxonomy('lead_source', 'lead', [
            'label' => __( 'منبع ورود', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'منابع ورود', 'webinocrm' ),
                'singular_name' => __( 'منبع ورود', 'webinocrm' ),
            ]
        ]);

        // Consultation Status Taxonomy
        register_taxonomy('consultation_status', 'consultation', [
            'label' => __( 'نتیجه مشاوره', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'نتایج مشاوره', 'webinocrm' ),
                'singular_name' => __( 'نتیجه مشاوره', 'webinocrm' ),
            ]
        ]);
        
        // Appointment Status Taxonomy - NEW
        register_taxonomy('appointment_status', 'appointment', [
            'label' => __( 'وضعیت قرار', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'وضعیت‌های قرار', 'webinocrm' ),
                'singular_name' => __( 'وضعیت قرار', 'webinocrm' ),
            ]
        ]);

        // Project Status Taxonomy
        register_taxonomy('project_status', 'project', [
            'label' => __( 'Project Status', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'وضعیت‌های پروژه', 'webinocrm' ),
                'singular_name' => __( 'وضعیت پروژه', 'webinocrm' ),
                'menu_name' => __( 'وضعیت پروژه', 'webinocrm' ),
            ]
        ]);

        // Contract Status Taxonomy - NEW
        register_taxonomy('contract_status', 'contract', [
            'label' => __( 'وضعیت قرارداد', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'وضعیت‌های قرارداد', 'webinocrm' ),
                'singular_name' => __( 'وضعیت قرارداد', 'webinocrm' ),
            ]
        ]);

        // Task Category Taxonomy
        register_taxonomy('task_category', ['task', 'task_template'], [
            'label' => __( 'Task Category', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'دسته‌بندی تسک', 'webinocrm' ),
                'singular_name' => __( 'دسته‌بندی تسک', 'webinocrm' ),
                'menu_name' => __( 'دسته‌بندی تسک', 'webinocrm' ),
            ]
        ]);

        // Task Status Taxonomy
        register_taxonomy('task_status', 'task', ['label' => __( 'Task Status', 'webinocrm' ), 'hierarchical' => true, 'show_in_rest' => true]);
        
        // Task Priority Taxonomy
        register_taxonomy('task_priority', 'task', ['label' => __( 'Task Priority', 'webinocrm' ), 'hierarchical' => true, 'show_in_rest' => true]);
        
        // Task Labels Taxonomy
        register_taxonomy('task_label', 'task', [
            'label' => __( 'Labels', 'webinocrm' ),
            'hierarchical' => false,
            'rewrite' => ['slug' => 'task-label'],
            'show_admin_column' => true,
            'show_in_rest' => true,
            'labels' => [
                'name' => __( 'Labels', 'webinocrm' ), 'singular_name' => __( 'Label', 'webinocrm' ),
                'search_items' => __( 'Search Labels', 'webinocrm' ), 'all_items' => __( 'All Labels', 'webinocrm' ),
                'popular_items' => __( 'Popular Labels', 'webinocrm' ), 'edit_item' => __( 'Edit Label', 'webinocrm' ),
                'update_item' => __( 'Update Label', 'webinocrm' ), 'add_new_item' => __( 'Add New Label', 'webinocrm' ),
                'new_item_name' => __( 'New Label Name', 'webinocrm' ), 'menu_name' => __( 'Labels', 'webinocrm' ),
            ]
        ]);
        
        // **MODIFIED**: Now applies to users AND tickets for departments
        register_taxonomy('organizational_position', ['user', 'ticket'], [
            'label' => __( 'Organizational Positions', 'webinocrm' ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, 
            'hierarchical' => true, 
            'rewrite' => false,
            'labels' => [
                'name' => __( 'جایگاه‌های سازمانی', 'webinocrm' ), 'singular_name' => __( 'جایگاه سازمانی', 'webinocrm' ),
                'search_items' => __( 'جستجوی جایگاه', 'webinocrm' ), 'all_items' => __( 'تمام جایگاه‌ها', 'webinocrm' ),
                'edit_item' => __( 'ویرایش جایگاه', 'webinocrm' ), 'update_item' => __( 'بروزرسانی جایگاه', 'webinocrm' ),
                'add_new_item' => __( 'افزودن جایگاه جدید', 'webinocrm' ), 'new_item_name' => __( 'نام جایگاه جدید', 'webinocrm' ),
                'menu_name' => __( 'جایگاه‌های سازمانی', 'webinocrm' ),
                 'parent_item' => __( 'دپارتمان والد', 'webinocrm' ),
                'parent_item_colon' => __( 'دپارتمان والد:', 'webinocrm' ),
            ]
        ]);

        // Ticket Status Taxonomy
        register_taxonomy('ticket_status', 'ticket', ['label' => __( 'Ticket Status', 'webinocrm' ), 'hierarchical' => true]);
        
        // Ticket Priority Taxonomy - NEW
        register_taxonomy('ticket_priority', 'ticket', [
            'label' => __( 'اولویت تیکت', 'webinocrm' ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
        ]);
    }
    
    /**
     * Creates default terms for taxonomies only if they don't exist.
     * This now correctly checks terms in the 'lead_status' taxonomy.
     */
    public static function create_default_terms() {
        // === START: LEAD STATUSES (CORRECTED) ===
        // New installs: create all. Existing: add any missing (assigned, contracted).
        $lead_statuses = [
            'جدید' => 'new',
            'ارجاع داده شده' => 'assigned',
            'در حال پیگیری' => 'in-progress',
            'قرارداد شده' => 'contracted',
            'مشتری شده' => 'converted',
            'لغو شده' => 'cancelled',
        ];
        foreach ($lead_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'lead_status' ) ) {
                wp_insert_term( $name, 'lead_status', ['slug' => $slug] );
            }
        }
        // === END: LEAD STATUSES (CORRECTED) ===

        // Lead Sources
        $lead_sources = [
            'تلگرام' => 'telegram',
            'بله' => 'bale',
            'کارشناس ثبت کرده' => 'consultant',
            'سایت' => 'website',
            'اینستاگرام' => 'instagram',
            'بازاریابی' => 'marketing',
        ];
        foreach ($lead_sources as $name => $slug) {
            if ( ! term_exists( $slug, 'lead_source' ) ) {
                wp_insert_term( $name, 'lead_source', ['slug' => $slug] );
            }
        }

        // Consultation Statuses
        $consultation_statuses = ['در حال پیگیری' => 'in-progress', 'تبدیل به پروژه' => 'converted', 'بسته شده' => 'closed'];
        foreach ($consultation_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'consultation_status' ) ) wp_insert_term( $name, 'consultation_status', ['slug' => $slug] );
        }

        // Appointment Statuses - NEW
        $appointment_statuses = [
            'در انتظار تایید' => 'pending',
            'تایید شده'       => 'confirmed',
            'لغو شده'         => 'cancelled',
            'انجام شده'       => 'completed',
        ];
        foreach ($appointment_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'appointment_status' ) ) wp_insert_term( $name, 'appointment_status', ['slug' => $slug] );
        }

        // Project Statuses
        $project_statuses = ['فعال' => 'active', 'تکمیل شده' => 'completed', 'در انتظار' => 'on-hold', 'لغو شده' => 'cancelled'];
        foreach ($project_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'project_status' ) ) wp_insert_term( $name, 'project_status', ['slug' => $slug] );
        }

        // Contract Statuses - NEW
        $contract_statuses = ['فعال' => 'active', 'لغو شده' => 'cancelled', 'تکمیل شده' => 'completed'];
        foreach ($contract_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'contract_status' ) ) wp_insert_term( $name, 'contract_status', ['slug' => $slug] );
        }

        // Task Categories
        $task_categories = ['روزانه' => 'daily', 'هفتگی' => 'weekly', 'پروژه‌ای' => 'project-based'];
        foreach ($task_categories as $name => $slug) {
            if ( ! term_exists( $slug, 'task_category' ) ) wp_insert_term( $name, 'task_category', ['slug' => $slug] );
        }

        // Task Statuses
        $task_statuses = ['انجام نشده' => 'to-do', 'در حال انجام' => 'in-progress', 'انجام شده' => 'done'];
        foreach ($task_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'task_status' ) ) wp_insert_term( $name, 'task_status', ['slug' => $slug] );
        }
        
        // Task Priorities
        $task_priorities = [__('High', 'webinocrm') => 'high', __('Medium', 'webinocrm') => 'medium', __('Low', 'webinocrm') => 'low'];
        foreach ($task_priorities as $name => $slug) {
            if ( ! term_exists( $slug, 'task_priority' ) ) wp_insert_term( $name, 'task_priority', ['slug' => $slug] );
        }
        
        // Default Organizational Positions (Departments)
        $departments = ['پشتیبانی فنی' => 'technical-support', 'فروش و مالی' => 'sales-finance', 'مدیریت' => 'management'];
        foreach ($departments as $name => $slug) {
             if ( ! term_exists( $slug, 'organizational_position' ) ) {
                wp_insert_term( $name, 'organizational_position', ['slug' => $slug] );
            }
        }

        // Ticket Statuses
        $ticket_statuses = [
            __('Open', 'webinocrm') => 'open',
            __('Waiting for Customer', 'webinocrm') => 'waiting-on-customer',
            __('Waiting for Support', 'webinocrm') => 'waiting-on-support',
            __('Closed', 'webinocrm') => 'closed'
        ];
        foreach ($ticket_statuses as $name => $slug) {
            if ( ! term_exists( $slug, 'ticket_status' ) ) wp_insert_term( $name, 'ticket_status', ['slug' => $slug] );
        }
        
        // Ticket Priorities - NEW
        $ticket_priorities = ['کم' => 'low', 'متوسط' => 'medium', 'زیاد' => 'high', 'فوری' => 'urgent'];
        foreach ($ticket_priorities as $name => $slug) {
            if ( ! term_exists( $slug, 'ticket_priority' ) ) {
                wp_insert_term( $name, 'ticket_priority', ['slug' => $slug] );
            }
        }
    }
    
    public function add_task_template_meta_box() {
        add_meta_box(
            'task_template_options',
            __( 'تنظیمات قالب تسک', 'webinocrm' ),
            [ $this, 'render_task_template_meta_box' ],
            'task_template', 'normal', 'high'
        );
    }

    public function render_task_template_meta_box( $post ) {
        wp_nonce_field('webino_save_task_template_options', 'task_template_nonce');

        $assigned_role_id = get_post_meta($post->ID, '_assigned_role', true);
        $task_category_id = get_post_meta($post->ID, '_task_category', true);
        
        $positions = get_terms(['taxonomy' => 'organizational_position', 'hide_empty' => false]);
        $categories = get_terms(['taxonomy' => 'task_category', 'hide_empty' => false]);

        echo '<p><strong>' . __('دسته‌بندی (برای اتوماسیون):', 'webinocrm') . '</strong></p>';
        echo '<select name="_task_category" style="width:100%;">';
        echo '<option value="">' . __('انتخاب کنید', 'webinocrm') . '</option>';
        foreach ($categories as $category) {
            echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($task_category_id, $category->term_id, false) . '>' . esc_html($category->name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('برای ساخت تسک‌های خودکار، "روزانه" را انتخاب کنید.', 'webinocrm') . '</p>';

        echo '<hr style="margin: 20px 0;">';

        echo '<p><strong>' . __('نقش مسئول (برای اتوماسیون):', 'webinocrm') . '</strong></p>';
        echo '<select name="_assigned_role" style="width:100%;">';
        echo '<option value="">' . __('انتخاب کنید', 'webinocrm') . '</option>';
        foreach ($positions as $position) {
            echo '<option value="' . esc_attr($position->term_id) . '" ' . selected($assigned_role_id, $position->term_id, false) . '>' . esc_html($position->name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('این تسک روزانه برای تمام کارمندانی که این جایگاه شغلی را دارند ساخته خواهد شد.', 'webinocrm') . '</p>';
    }

    public function save_task_template_meta_box( $post_id ) {
        if (!isset($_POST['task_template_nonce']) || !wp_verify_nonce($_POST['task_template_nonce'], 'webino_save_task_template_options')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['_task_category'])) {
            update_post_meta($post_id, '_task_category', intval($_POST['_task_category']));
        }
        if (isset($_POST['_assigned_role'])) {
            update_post_meta($post_id, '_assigned_role', intval($_POST['_assigned_role']));
        }
    }

    public function add_project_template_meta_box() {
        add_meta_box(
            'project_template_link',
            __( 'اتوماسیون WebinoCRM', 'webinocrm' ),
            [ $this, 'render_project_template_meta_box' ],
            'product', 'side', 'default'
        );
    }

    public function render_project_template_meta_box( $post ) {
        wp_nonce_field('webino_save_project_template_link', 'project_template_nonce');
        $linked_template_id = get_post_meta($post->ID, '_project_template_id', true);

        $templates = get_posts(['post_type' => 'project_template', 'posts_per_page' => -1]);

        echo '<p>' . __('این محصول (خدمت) پس از فروش، کدام قالب پروژه را ایجاد کند؟', 'webinocrm') . '</p>';
        echo '<select name="_project_template_id" style="width:100%;">';
        echo '<option value="">' . __('هیچکدام (پروژه‌ای ساخته نشود)', 'webinocrm') . '</option>';
        if ($templates) {
            foreach ($templates as $template) {
                echo '<option value="' . esc_attr($template->ID) . '" ' . selected($linked_template_id, $template->ID, false) . '>' . esc_html($template->post_title) . '</option>';
            }
        }
        echo '</select>';

        // Task template & service type (for recurring vs one-time tasks)
        $task_template_id = get_post_meta($post->ID, '_task_template_id', true);
        $service_task_type = get_post_meta($post->ID, '_service_task_type', true) ?: 'onetime';
        $task_templates = get_posts(['post_type' => 'task_template', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);

        echo '<hr style="margin: 16px 0;">';
        echo '<p><strong>' . __('قالب تسک (اولویت بالاتر از قالب پروژه)', 'webinocrm') . '</strong></p>';
        echo '<select name="_task_template_id" style="width:100%;">';
        echo '<option value="">' . __('هیچکدام', 'webinocrm') . '</option>';
        if ($task_templates) {
            foreach ($task_templates as $t) {
                echo '<option value="' . esc_attr($t->ID) . '" ' . selected($task_template_id, $t->ID, false) . '>' . esc_html($t->post_title) . '</option>';
            }
        }
        echo '</select>';
        echo '<p class="description">' . __('اگر تنظیم شود، تسک‌ها از این قالب ساخته می‌شوند.', 'webinocrm') . '</p>';

        echo '<p style="margin-top: 12px;"><strong>' . __('نوع خدمت (تکرار تسک)', 'webinocrm') . '</strong></p>';
        $types = [
            'onetime' => __('یکبار (مثل سایت)', 'webinocrm'),
            'daily'   => __('روزانه (مثل اینستاگرام)', 'webinocrm'),
            'weekly'  => __('هفتگی', 'webinocrm'),
            'monthly' => __('ماهانه', 'webinocrm'),
        ];
        echo '<select name="_service_task_type" style="width:100%;">';
        foreach ($types as $val => $label) {
            echo '<option value="' . esc_attr($val) . '" ' . selected($service_task_type, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<hr style="margin: 16px 0;">';
        echo '<p><strong>' . __('دپارتمان پیشفرض', 'webinocrm') . '</strong></p>';
        $default_dept_id = (int) get_post_meta( $post->ID, '_default_department_id', true );
        $departments = get_terms( [ 'taxonomy' => 'organizational_position', 'hide_empty' => false, 'parent' => 0 ] );
        echo '<select name="_default_department_id" style="width:100%;">';
        echo '<option value="">' . __('انتخاب کنید', 'webinocrm') . '</option>';
        if ( ! is_wp_error( $departments ) ) {
            foreach ( $departments as $dept ) {
                echo '<option value="' . esc_attr( $dept->term_id ) . '" ' . selected( $default_dept_id, $dept->term_id, false ) . '>' . esc_html( $dept->name ) . '</option>';
            }
        }
        echo '</select>';
        echo '<p class="description">' . __('پروژه‌های ایجادشده از این محصول به این دپارتمان تخصیص داده می‌شوند.', 'webinocrm') . '</p>';
    }

    public function save_project_template_meta_box( $post_id ) {
        if (!isset($_POST['project_template_nonce']) || !wp_verify_nonce($_POST['project_template_nonce'], 'webino_save_project_template_link')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_product', $post_id)) return;

        if (isset($_POST['_project_template_id'])) {
            update_post_meta($post_id, '_project_template_id', intval($_POST['_project_template_id']));
        }
        if (isset($_POST['_task_template_id'])) {
            update_post_meta($post_id, '_task_template_id', intval($_POST['_task_template_id']));
        }
        if (isset($_POST['_service_task_type'])) {
            $type = sanitize_key($_POST['_service_task_type']);
            if (in_array($type, ['onetime', 'daily', 'weekly', 'monthly'], true)) {
                update_post_meta($post_id, '_service_task_type', $type);
            }
        }
        if (isset($_POST['_default_department_id'])) {
            $dept_id = (int) $_POST['_default_department_id'];
            update_post_meta($post_id, '_default_department_id', $dept_id > 0 ? $dept_id : '');
        }
    }
    
    public function add_task_meta_boxes() {
        add_meta_box('task_details_meta_box', __('Task Details', 'webinocrm'), [$this, 'render_task_details_meta_box'], 'task', 'side', 'default');
    }

    public function render_task_details_meta_box($post) {
        wp_nonce_field('webino_save_task_details', 'task_details_nonce');

        $epics = get_posts(['post_type' => 'epic', 'numberposts' => -1]);
        $current_epic = get_post_meta($post->ID, '_task_epic_id', true);
        echo '<p><strong>' . __('Epic', 'webinocrm') . '</strong></p>';
        echo '<select name="task_epic_id" style="width:100%;">';
        echo '<option value="">-- ' . __('No Epic', 'webinocrm') . ' --</option>';
        foreach ($epics as $epic) {
            echo '<option value="' . esc_attr($epic->ID) . '" ' . selected($current_epic, $epic->ID, false) . '>' . esc_html($epic->post_title) . '</option>';
        }
        echo '</select><hr>';

        $story_points = get_post_meta($post->ID, '_story_points', true);
        echo '<p><strong>' . __('Story Points', 'webinocrm') . '</strong></p>';
        echo '<input type="number" name="story_points" value="' . esc_attr($story_points) . '" style="width:100%;" />';
    }

    public function save_task_meta_boxes($post_id) {
        if (!isset($_POST['task_details_nonce']) || !wp_verify_nonce($_POST['task_details_nonce'], 'webino_save_task_details')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['task_epic_id'])) update_post_meta($post_id, '_task_epic_id', intval($_POST['task_epic_id']));
        if (isset($_POST['story_points'])) update_post_meta($post_id, '_story_points', sanitize_text_field($_POST['story_points']));
    }
}