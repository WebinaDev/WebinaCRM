<?php
/**
 * White Labeling System
 * 
 * Allows complete branding customization of the CRM
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_White_Label {

    /**
     * Initialize White Label Handler
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page'], 100);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_custom_branding']);
        add_action('login_enqueue_scripts', [$this, 'customize_login_page']);
        add_filter('admin_footer_text', [$this, 'custom_admin_footer']);
        add_filter('update_footer', [$this, 'custom_version_footer'], 11);
        add_action('wp_head', [$this, 'add_custom_css']);
        add_action('wp_head', [$this, 'apply_color_scheme']);
        add_action('admin_head', [$this, 'add_custom_admin_css']);
        add_action('admin_head', [$this, 'apply_color_scheme']);
        add_action('login_head', [$this, 'apply_color_scheme']);
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'webino-crm',
            'تنظیمات White Label',
            'White Label',
            'manage_options',
            'webino-white-label',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Branding
        register_setting('webinocrm_white_label', 'webinocrm_wl_company_name');
        register_setting('webinocrm_white_label', 'webinocrm_wl_company_logo');
        register_setting('webinocrm_white_label', 'webinocrm_wl_company_icon');
        register_setting('webinocrm_white_label', 'webinocrm_wl_login_logo');
        register_setting('webinocrm_white_label', 'webinocrm_wl_primary_color');
        register_setting('webinocrm_white_label', 'webinocrm_wl_secondary_color');
        register_setting('webinocrm_white_label', 'webinocrm_wl_accent_color');
        
        // Text Customization
        register_setting('webinocrm_white_label', 'webinocrm_wl_dashboard_title');
        register_setting('webinocrm_white_label', 'webinocrm_wl_welcome_message');
        register_setting('webinocrm_white_label', 'webinocrm_wl_footer_text');
        register_setting('webinocrm_white_label', 'webinocrm_wl_support_email');
        register_setting('webinocrm_white_label', 'webinocrm_wl_support_phone');
        register_setting('webinocrm_white_label', 'webinocrm_wl_support_url');
        
        // Advanced
        register_setting('webinocrm_white_label', 'webinocrm_wl_custom_css');
        register_setting('webinocrm_white_label', 'webinocrm_wl_custom_js');
        register_setting('webinocrm_white_label', 'webinocrm_wl_hide_branding');
        register_setting('webinocrm_white_label', 'webinocrm_wl_custom_menu_icon');
        register_setting('webinocrm_white_label', 'webinocrm_wl_email_logo');
        register_setting('webinocrm_white_label', 'webinocrm_wl_email_header_color');
        register_setting('webinocrm_white_label', 'webinocrm_wl_email_footer_text');
        
        // Domain/URL Customization
        register_setting('webinocrm_white_label', 'webinocrm_wl_custom_domain');
        register_setting('webinocrm_white_label', 'webinocrm_wl_app_url_slug');
        register_setting('webinocrm_white_label', 'webinocrm_wl_company_url');
        register_setting('webinocrm_white_label', 'webinocrm_wl_copyright_text');
        
        // Additional color settings
        register_setting('webinocrm_white_label', 'webinocrm_wl_success_color');
        register_setting('webinocrm_white_label', 'webinocrm_wl_warning_color');
        register_setting('webinocrm_white_label', 'webinocrm_wl_danger_color');
        register_setting('webinocrm_white_label', 'webinocrm_wl_info_color');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php settings_fields('webinocrm_white_label'); ?>
                
                <div class="webino-white-label-settings">
                    
                    <!-- Branding Tab -->
                    <div class="postbox">
                        <div class="inside">
                            <h2>برندینگ و لوگو</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th>نام شرکت</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_company_name" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_company_name', 'WebinoERP')); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>لوگوی داشبورد</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_company_logo" 
                                               id="company_logo" 
                                               value="<?php echo esc_url(get_option('webinocrm_wl_company_logo')); ?>" 
                                               class="regular-text">
                                        <button type="button" class="button webino-upload-btn" data-target="company_logo">
                                            آپلود لوگو
                                        </button>
                                        <?php if (get_option('webinocrm_wl_company_logo')): ?>
                                            <br><img src="<?php echo esc_url(get_option('webinocrm_wl_company_logo')); ?>" 
                                                     style="max-width: 200px; margin-top: 10px;">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>آیکون (Favicon)</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_company_icon" 
                                               id="company_icon" 
                                               value="<?php echo esc_url(get_option('webinocrm_wl_company_icon')); ?>" 
                                               class="regular-text">
                                        <button type="button" class="button webino-upload-btn" data-target="company_icon">
                                            آپلود آیکون
                                        </button>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>لوگوی صفحه ورود</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_login_logo" 
                                               id="login_logo" 
                                               value="<?php echo esc_url(get_option('webinocrm_wl_login_logo')); ?>" 
                                               class="regular-text">
                                        <button type="button" class="button webino-upload-btn" data-target="login_logo">
                                            آپلود لوگو
                                        </button>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ اصلی</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_primary_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_primary_color', '#4CAF50')); ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ ثانویه</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_secondary_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_secondary_color', '#2196F3')); ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ تاکیدی</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_accent_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_accent_color', '#FF5722')); ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ موفقیت</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_success_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_success_color', '#28a745')); ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ هشدار</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_warning_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_warning_color', '#ffc107')); ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ خطا</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_danger_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_danger_color', '#dc3545')); ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>رنگ اطلاعات</th>
                                    <td>
                                        <input type="color" name="webinocrm_wl_info_color" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_info_color', '#17a2b8')); ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Text Customization -->
                    <div class="postbox">
                        <div class="inside">
                            <h2>سفارشی‌سازی متن</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th>عنوان داشبورد</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_dashboard_title" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_dashboard_title', 'داشبورد')); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>پیام خوش‌آمدگویی</th>
                                    <td>
                                        <textarea name="webinocrm_wl_welcome_message" 
                                                  class="large-text" 
                                                  rows="3"><?php echo esc_textarea(get_option('webinocrm_wl_welcome_message')); ?></textarea>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>متن فوتر</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_footer_text" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_footer_text')); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>ایمیل پشتیبانی</th>
                                    <td>
                                        <input type="email" name="webinocrm_wl_support_email" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_support_email')); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>تلفن پشتیبانی</th>
                                    <td>
                                        <input type="text" name="webinocrm_wl_support_phone" 
                                               value="<?php echo esc_attr(get_option('webinocrm_wl_support_phone')); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>URL پشتیبانی</th>
                                    <td>
                                        <input type="url" name="webinocrm_wl_support_url" 
                                               value="<?php echo esc_url(get_option('webinocrm_wl_support_url')); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>URL شرکت</th>
                                    <td>
                                        <input type="url" name="webinocrm_wl_company_url" 
                                               value="<?php echo esc_url(get_option('webinocrm_wl_company_url', 'https://webinoco.com')); ?>" 
                                               class="regular-text">
                                        <p class="description">این URL در کپی‌رایت فوتر استفاده می‌شود</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>متن کپی‌رایت سفارشی</th>
                                    <td>
                                        <textarea name="webinocrm_wl_copyright_text" 
                                                  class="large-text" 
                                                  rows="3" 
                                                  placeholder="<?php esc_attr_e('اگر خالی باشد، از متن پیش‌فرض استفاده می‌شود', 'webinocrm'); ?>"><?php echo esc_textarea(get_option('webinocrm_wl_copyright_text')); ?></textarea>
                                        <p class="description">می‌توانید از HTML استفاده کنید. متغیرهای موجود: {year}, {company_name}, {company_url}</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div class="postbox">
                        <div class="inside">
                            <h2>تنظیمات پیشرفته</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th>CSS سفارشی</th>
                                    <td>
                                        <textarea name="webinocrm_wl_custom_css" 
                                                  class="large-text code" 
                                                  rows="10"><?php echo esc_textarea(get_option('webinocrm_wl_custom_css')); ?></textarea>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>JavaScript سفارشی</th>
                                    <td>
                                        <textarea name="webinocrm_wl_custom_js" 
                                                  class="large-text code" 
                                                  rows="10"><?php echo esc_textarea(get_option('webinocrm_wl_custom_js')); ?></textarea>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>مخفی کردن برندینگ</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="webinocrm_wl_hide_branding" 
                                                   value="1" <?php checked(get_option('webinocrm_wl_hide_branding'), 1); ?>>
                                            مخفی کردن تمام نشانه‌های "WebinoCRM"
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                </div>
                
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.webino-upload-btn').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                var mediaUploader = wp.media({
                    title: 'انتخاب تصویر',
                    button: { text: 'انتخاب' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + target).val(attachment.url);
                });
                
                mediaUploader.open();
            });
        });
        </script>
        
        <style>
        .webino-white-label-settings .postbox {
            margin-bottom: 20px;
        }
        .webino-white-label-settings h2 {
            padding: 15px;
            background: #f5f5f5;
            margin: 0 0 20px 0;
            border-bottom: 1px solid #ddd;
        }
        </style>
        <?php
    }

    /**
     * Enqueue custom branding
     */
    public function enqueue_custom_branding() {
        if (strpos($_SERVER['REQUEST_URI'], 'webino-') === false) {
            return;
        }

        $primary_color = get_option('webinocrm_wl_primary_color', '#4CAF50');
        $secondary_color = get_option('webinocrm_wl_secondary_color', '#2196F3');
        $accent_color = get_option('webinocrm_wl_accent_color', '#FF5722');

        $custom_css = "
            :root {
                --webino-primary: {$primary_color};
                --webino-secondary: {$secondary_color};
                --webino-accent: {$accent_color};
            }
            .webino-primary-bg { background-color: {$primary_color} !important; }
            .webino-primary-color { color: #ffffff !important; }
            .btn-primary,
            .btn-purple { 
                background-color: {$primary_color} !important; 
                border-color: {$primary_color} !important; 
                color: #ffffff !important; 
            }
        ";

        wp_add_inline_style('webinocrm-styles', $custom_css);

        // Custom JS
        $custom_js = get_option('webinocrm_wl_custom_js');
        if ($custom_js) {
            wp_add_inline_script('webinocrm-scripts', $custom_js);
        }
    }

    /**
     * Customize login page
     */
    public function customize_login_page() {
        $logo = get_option('webinocrm_wl_login_logo');
        $primary_color = get_option('webinocrm_wl_primary_color', '#4CAF50');
        
        ?>
        <style type="text/css">
            #login h1 a, .login h1 a {
                <?php if ($logo): ?>
                background-image: url(<?php echo esc_url($logo); ?>);
                <?php endif; ?>
                height: 80px;
                width: 320px;
                background-size: contain;
                background-repeat: no-repeat;
                padding-bottom: 30px;
            }
            .wp-core-ui .button-primary {
                background: <?php echo esc_attr($primary_color); ?>;
                border-color: <?php echo esc_attr($primary_color); ?>;
            }
            .wp-core-ui .button-primary:hover {
                background: <?php echo esc_attr($primary_color); ?>;
                filter: brightness(1.1);
            }
        </style>
        <?php
    }

    /**
     * Custom admin footer
     */
    public function custom_admin_footer($text) {
        $footer_text = get_option('webinocrm_wl_footer_text');
        
        if ($footer_text && strpos($_SERVER['REQUEST_URI'], 'webino-') !== false) {
            return $footer_text;
        }
        
        return $text;
    }

    /**
     * Custom version footer
     */
    public function custom_version_footer($text) {
        if (get_option('webinocrm_wl_hide_branding') && strpos($_SERVER['REQUEST_URI'], 'webino-') !== false) {
            return '';
        }
        
        return $text;
    }

    /**
     * Add custom CSS to frontend
     */
    public function add_custom_css() {
        if ( function_exists( 'webinocrm_dashboard' ) && webinocrm_dashboard()->rewrite->is_dashboard_request() ) {
            return;
        }

        $custom_css = get_option('webinocrm_wl_custom_css');
        
        if ($custom_css) {
            echo '<style type="text/css">' . wp_strip_all_tags($custom_css) . '</style>';
        }
    }

    /**
     * Add custom CSS to admin
     */
    public function add_custom_admin_css() {
        if (strpos($_SERVER['REQUEST_URI'], 'webino-') === false) {
            return;
        }

        $custom_css = get_option('webinocrm_wl_custom_css');
        $logo = get_option('webinocrm_wl_company_logo');
        
        ?>
        <style type="text/css">
            <?php if ($logo): ?>
            .webinocrm-logo {
                background-image: url(<?php echo esc_url($logo); ?>) !important;
            }
            <?php endif; ?>
            
            <?php echo wp_strip_all_tags($custom_css); ?>
        </style>
        <?php
    }

    /**
     * Apply color scheme to CSS variables
     */
    public function apply_color_scheme() {
        if ( function_exists( 'webinocrm_dashboard' ) && webinocrm_dashboard()->rewrite->is_dashboard_request() ) {
            return;
        }

        $primary_color = self::get_primary_color();
        $secondary_color = self::get_secondary_color();
        $accent_color = self::get_accent_color();
        $success_color = self::get_success_color();
        $warning_color = self::get_warning_color();
        $danger_color = self::get_danger_color();
        $info_color = self::get_info_color();
        
        // Convert hex to RGB for CSS variables
        $primary_rgb = self::hex_to_rgb($primary_color);
        $secondary_rgb = self::hex_to_rgb($secondary_color);
        
        ?>
        <style type="text/css">
            :root {
                --webino-primary-color: <?php echo esc_attr($primary_color); ?>;
                --webino-primary-rgb: <?php echo esc_attr($primary_rgb); ?>;
                --webino-secondary-color: <?php echo esc_attr($secondary_color); ?>;
                --webino-secondary-rgb: <?php echo esc_attr($secondary_rgb); ?>;
                --webino-accent-color: <?php echo esc_attr($accent_color); ?>;
                --webino-success-color: <?php echo esc_attr($success_color); ?>;
                --webino-warning-color: <?php echo esc_attr($warning_color); ?>;
                --webino-danger-color: <?php echo esc_attr($danger_color); ?>;
                --webino-info-color: <?php echo esc_attr($info_color); ?>;
            }
            
            /* Override Bootstrap primary colors */
            .bg-primary {
                background-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            .border-primary {
                border-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Text primary - always white for visibility */
            .text-primary,
            [class*="text-primary"],
            *[style*="color"][style*="<?php echo esc_attr($primary_color); ?>"] {
                color: #ffffff !important;
            }
            
            /* Buttons - keep background primary but text white */
            .btn-primary,
            .btn-purple {
                background-color: <?php echo esc_attr($primary_color); ?> !important;
                border-color: <?php echo esc_attr($primary_color); ?> !important;
                color: #ffffff !important;
            }
            
            .btn-primary:hover,
            .btn-primary:focus,
            .btn-primary:active,
            .btn-purple:hover,
            .btn-purple:focus,
            .btn-purple:active {
                background-color: <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> !important;
                border-color: <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> !important;
                color: #ffffff !important;
            }
            
            /* Settings tabs */
            .webino-tab:hover,
            .webino-tab.active {
                color: <?php echo esc_attr($primary_color); ?> !important;
                border-bottom-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            .webino-tab:hover {
                border-bottom-color: rgba(<?php echo esc_attr($primary_rgb); ?>, 0.7) !important;
            }
            
            /* Nav tabs */
            .nav-tabs .nav-link:hover,
            .nav-tabs .nav-link.active {
                color: <?php echo esc_attr($primary_color); ?> !important;
                border-bottom-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Override any text elements using primary color variable */
            * {
                --primary-text-color: #ffffff !important;
            }
            
            /* Additional purple/primary color overrides */
            .settings-nav-item.active {
                background-color: <?php echo esc_attr($primary_color); ?> !important;
                color: #ffffff !important;
            }
            
            .settings-nav-item:hover:not(.active) {
                background-color: rgba(<?php echo esc_attr($primary_rgb); ?>, 0.1) !important;
            }
            
            /* Gradient backgrounds - use primary color */
            .project-card-header,
            .data-table thead th,
            .profile-header,
            .gradient-1,
            .gradient-1 .stat-widget-icon {
                background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, <?php echo esc_attr(self::darken_color($primary_color, 15)); ?> 100%) !important;
            }
            
            /* Progress bars */
            .project-progress-bar {
                background: linear-gradient(90deg, <?php echo esc_attr($primary_color); ?> 0%, <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> 100%) !important;
            }
            
            /* Custom buttons */
            .webino-btn-primary,
            .webino-button,
            .webino-button-primary,
            .webino-badge-primary,
            .webino-status-badge.status-completed {
                background: <?php echo esc_attr($primary_color); ?> !important;
                border-color: <?php echo esc_attr($primary_color); ?> !important;
                color: #ffffff !important;
            }
            
            .webino-button:hover,
            .webino-button:focus,
            .webino-button:active,
            .webino-button-primary:hover {
                background: <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> !important;
                border-color: <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> !important;
                color: #ffffff !important;
            }
            
            /* Icons and meta items */
            .project-card-meta-item i,
            .webino-project-card-details-grid i {
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Pagination */
            .webino-pagination .current,
            .pagination-wrapper .current {
                background-color: <?php echo esc_attr($primary_color); ?> !important;
                border-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Task placeholder */
            .webino-task-placeholder {
                border-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Calendar */
            .calendar-day.has-appointment {
                background: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Staff stat */
            .staff-stat-value {
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Breadcrumb links */
            .breadcrumb .breadcrumb-item a,
            .webino-breadcrumb a {
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Light buttons - commonly used in tables */
            .btn-primary-light,
            .btn.btn-primary-light {
                background-color: rgba(<?php echo esc_attr($primary_rgb); ?>, 0.1) !important;
                color: <?php echo esc_attr($primary_color); ?> !important;
                border-color: rgba(<?php echo esc_attr($primary_rgb); ?>, 0.2) !important;
            }
            
            .btn-primary-light:hover,
            .btn-primary-light:focus,
            .btn-primary-light:active,
            .btn.btn-primary-light:hover,
            .btn.btn-primary-light:focus,
            .btn.btn-primary-light:active {
                background-color: <?php echo esc_attr($primary_color); ?> !important;
                color: #ffffff !important;
                border-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* DataTables buttons */
            .dt-button {
                background-color: <?php echo esc_attr($primary_color); ?> !important;
                color: #ffffff !important;
            }
            
            /* Breadcrumb links in dashboard */
            .page-header-breadcrumb .breadcrumb-item a,
            .page-header-breadcrumb .breadcrumb-item a:hover {
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
        </style>
        <?php
    }
    
    /**
     * Convert hex color to RGB
     */
    private static function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "$r, $g, $b";
    }
    
    /**
     * Darken a hex color
     */
    private static function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                   str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                   str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Get company name
     */
    public static function get_company_name() {
        $name = get_option('webinocrm_wl_company_name', 'WebinoERP');
        return apply_filters('webinocrm_company_name', $name);
    }

    /**
     * Get company logo
     */
    public static function get_company_logo() {
        $logo = get_option('webinocrm_wl_company_logo', '');
        if (empty($logo)) {
            $logo = WEBINOCRM_PLUGIN_URL . 'assets/images/logo.png';
        }
        return apply_filters('webinocrm_company_logo', $logo);
    }
    
    /**
     * Get company URL
     */
    public static function get_company_url() {
        $url = get_option('webinocrm_wl_company_url', 'https://webinoco.com');
        return apply_filters('webinocrm_company_url', $url);
    }
    
    /**
     * Get favicon
     */
    public static function get_favicon() {
        $favicon = get_option('webinocrm_wl_company_icon', '');
        if (empty($favicon)) {
            $favicon = WEBINOCRM_PLUGIN_URL . 'assets/images/brand-logos/favicon.ico';
        }
        return apply_filters('webinocrm_favicon', $favicon);
    }
    
    /**
     * Get favicon by size (for PWA)
     */
    public static function get_favicon_by_size($size = 'default') {
        $favicon = self::get_favicon();
        // If default favicon is used, try to get size-specific icon
        if (strpos($favicon, 'favicon.ico') !== false) {
            $size_map = [
                '72x72' => 'icon-72x72.png',
                '96x96' => 'icon-96x96.png',
                '128x128' => 'icon-128x128.png',
                '144x144' => 'icon-144x144.png',
                '152x152' => 'icon-152x152.png',
                '192x192' => 'icon-192x192.png',
                '384x384' => 'icon-384x384.png',
                '512x512' => 'icon-512x512.png',
            ];
            if (isset($size_map[$size])) {
                $size_icon = WEBINOCRM_PLUGIN_URL . 'assets/images/' . $size_map[$size];
                if (file_exists(str_replace(WEBINOCRM_PLUGIN_URL, WEBINOCRM_PLUGIN_DIR, $size_icon))) {
                    return apply_filters('webinocrm_favicon_size', $size_icon, $size);
                }
            }
        }
        return apply_filters('webinocrm_favicon_size', $favicon, $size);
    }
    
    /**
     * Get login logo
     */
    public static function get_login_logo() {
        $logo = get_option('webinocrm_wl_login_logo', '');
        if (empty($logo)) {
            $logo = self::get_company_logo();
        }
        return apply_filters('webinocrm_login_logo', $logo);
    }
    
    /**
     * Get email logo
     */
    public static function get_email_logo() {
        $logo = get_option('webinocrm_wl_email_logo', '');
        if (empty($logo)) {
            $logo = self::get_company_logo();
        }
        return apply_filters('webinocrm_email_logo', $logo);
    }

    /**
     * Get primary color
     */
    public static function get_primary_color() {
        $color = get_option('webinocrm_wl_primary_color', '#4CAF50');
        return apply_filters('webinocrm_primary_color', $color);
    }
    
    /**
     * Get secondary color
     */
    public static function get_secondary_color() {
        $color = get_option('webinocrm_wl_secondary_color', '#2196F3');
        return apply_filters('webinocrm_secondary_color', $color);
    }
    
    /**
     * Get accent color
     */
    public static function get_accent_color() {
        $color = get_option('webinocrm_wl_accent_color', '#FF5722');
        return apply_filters('webinocrm_accent_color', $color);
    }
    
    /**
     * Get success color
     */
    public static function get_success_color() {
        $color = get_option('webinocrm_wl_success_color', '#28a745');
        return apply_filters('webinocrm_success_color', $color);
    }
    
    /**
     * Get warning color
     */
    public static function get_warning_color() {
        $color = get_option('webinocrm_wl_warning_color', '#ffc107');
        return apply_filters('webinocrm_warning_color', $color);
    }
    
    /**
     * Get danger color
     */
    public static function get_danger_color() {
        $color = get_option('webinocrm_wl_danger_color', '#dc3545');
        return apply_filters('webinocrm_danger_color', $color);
    }
    
    /**
     * Get info color
     */
    public static function get_info_color() {
        $color = get_option('webinocrm_wl_info_color', '#17a2b8');
        return apply_filters('webinocrm_info_color', $color);
    }
    
    /**
     * Get primary colors as array
     */
    public static function get_primary_colors() {
        return [
            'primary' => self::get_primary_color(),
            'secondary' => self::get_secondary_color(),
            'accent' => self::get_accent_color(),
            'success' => self::get_success_color(),
            'warning' => self::get_warning_color(),
            'danger' => self::get_danger_color(),
            'info' => self::get_info_color(),
        ];
    }

    /**
     * Get support email
     */
    public static function get_support_email() {
        return get_option('webinocrm_wl_support_email', get_option('admin_email'));
    }

    /**
     * Get support phone
     */
    public static function get_support_phone() {
        return get_option('webinocrm_wl_support_phone', '');
    }
    
    /**
     * Get support URL
     */
    public static function get_support_url() {
        return get_option('webinocrm_wl_support_url', '');
    }
    
    /**
     * Get copyright text
     */
    public static function get_copyright_text() {
        $custom_text = get_option('webinocrm_wl_copyright_text', '');
        if (!empty($custom_text)) {
            $text = $custom_text;
            $text = str_replace('{year}', date('Y'), $text);
            $text = str_replace('{company_name}', self::get_company_name(), $text);
            $text = str_replace('{company_url}', self::get_company_url(), $text);
            return apply_filters('webinocrm_copyright_text', $text);
        }
        
        // Default copyright
        $year = date('Y');
        $company_name = self::get_company_name();
        $company_url = self::get_company_url();
        
        $text = sprintf(
            '%s © Designed with %s by <a href="%s" target="_blank" rel="noopener">%s</a>',
            $year,
            '<span class="bi bi-heart-fill text-danger"></span>',
            esc_url($company_url),
            esc_html($company_name)
        );
        
        return apply_filters('webinocrm_copyright_text', $text);
    }
    
    /**
     * Check if branding should be hidden
     */
    public static function should_hide_branding() {
        return (bool) get_option('webinocrm_wl_hide_branding', false);
    }
    
    /**
     * Replace WebinoCRM text with custom name in a string
     */
    public static function replace_brand_name($text) {
        if (self::should_hide_branding()) {
            $text = str_replace('WebinoCRM', self::get_company_name(), $text);
            $text = str_replace('WebinoERP', self::get_company_name(), $text);
            $text = str_replace('پازلینگ سی‌آرام', self::get_company_name(), $text);
        }
        return $text;
    }
}

