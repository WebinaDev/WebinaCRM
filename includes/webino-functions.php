<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include the jdf library
require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php';

if ( ! function_exists( 'webino_get_dashboard_url' ) ) {
    function webino_get_dashboard_url() {
        if ( is_singular() ) {
            return get_permalink( get_the_ID() );
        }
        global $wp;
        return home_url( add_query_arg( [], $wp->request ) );
    }
}

if ( ! function_exists( 'webino_get_dashboard_spa_base_path' ) ) {
    /**
     * React Router basename for the dashboard SPA (no trailing slash).
     */
    function webino_get_dashboard_spa_base_path() {
        if ( defined( 'WEBINOCRM_SPA_BASEPATH' ) && WEBINOCRM_SPA_BASEPATH !== '' ) {
            return rtrim( WEBINOCRM_SPA_BASEPATH, '/' );
        }
        $path = parse_url( home_url( '/dashboard' ), PHP_URL_PATH );
        return ( is_string( $path ) && $path !== '' ) ? rtrim( $path, '/' ) : '/dashboard';
    }
}

if ( ! function_exists( 'webinocrm_product_display_name' ) ) {
    /**
     * Localized product display name (WebinoERP / سامانه وبینو).
     *
     * @param string|null $locale Optional locale code.
     * @return string
     */
    function webinocrm_product_display_name( $locale = null ) {
        if ( class_exists( 'WebinoCRM_White_Label' ) ) {
            $wl = WebinoCRM_White_Label::get_company_name();
            $legacy_defaults = array( 'WebinoCRM', 'WebinoERP', 'پازلینگ سی‌آرام' );
            if ( $wl && ! in_array( $wl, $legacy_defaults, true ) ) {
                return $wl;
            }
        }

        if ( null === $locale ) {
            if ( function_exists( 'webino_get_current_language' ) ) {
                $locale = webino_get_current_language();
            } else {
                $locale = determine_locale();
            }
        }

        if ( is_string( $locale ) && 0 === strpos( $locale, 'fa' ) ) {
            return 'سامانه وبینو';
        }

        return 'WebinoERP';
    }
}

if ( ! function_exists( 'webinocrm_login_redirect_url' ) ) {
    /**
     * Post-login redirect URL (dashboard SPA by default).
     *
     * @param WP_User|null $user User object.
     * @return string
     */
    function webinocrm_login_redirect_url( $user = null ) {
        if ( ! $user instanceof WP_User ) {
            $user = wp_get_current_user();
        }
        $custom = class_exists( 'WebinoCRM_Settings_Handler' )
            ? trim( (string) WebinoCRM_Settings_Handler::get_setting( 'login_redirect_url', '' ) )
            : '';
        if ( '' !== $custom ) {
            return esc_url_raw( $custom );
        }
        return home_url( '/dashboard' );
    }
}
if ( ! function_exists( 'webino_can_user_view_ticket' ) ) {
    /**
     * Checks if a user has permission to view a specific ticket.
     *
     * @param int $ticket_id The ID of the ticket.
     * @param int $user_id The ID of the user.
     * @return bool True if the user can view, false otherwise.
     */
    function webino_can_user_view_ticket( $ticket_id, $user_id ) {
        $ticket = get_post( $ticket_id );
        if ( ! $ticket || $ticket->post_type !== 'ticket' ) {
            return false;
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return false;
        }

        $user_roles = (array) $user->roles;

        // Admins and system managers can see everything.
        if ( in_array( 'administrator', $user_roles, true ) || in_array( 'system_manager', $user_roles, true ) ) {
            return true;
        }

        // The author of the ticket (customer) can see their own ticket.
        if ( (int) $ticket->post_author === $user_id ) {
            return true;
        }

        // Team members can view if they are assigned or belong to the ticket's department.
        if ( in_array( 'team_member', $user_roles, true ) ) {
            // Check if directly assigned
            $assigned_to = get_post_meta( $ticket_id, '_assigned_to', true );
            if ( (int) $assigned_to === $user_id ) {
                return true;
            }

            // Check if they belong to the department
            $user_positions = wp_get_object_terms( $user_id, 'organizational_position' );
            if ( ! is_wp_error( $user_positions ) && ! empty( $user_positions ) ) {
                $user_department_ids = [];
                foreach ( $user_positions as $pos ) {
                    // A user belongs to a department if it's their main position or their position's parent.
                    $user_department_ids[] = $pos->term_id;
                    if ( $pos->parent ) {
                        $user_department_ids[] = $pos->parent;
                    }
                }
                $user_department_ids = array_unique( $user_department_ids );

                $ticket_departments = WebinoCRM_Term_Helper::get_term_field_list( $ticket_id, 'organizational_position', 'ids' );
                if ( ! empty( $ticket_departments ) ) {
                    if ( ! empty( array_intersect( $user_department_ids, $ticket_departments ) ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

if ( ! function_exists( 'webino_user_can_assign_project' ) ) {
    /**
     * Checks if a user can assign a project (to employees).
     *
     * @param int $user_id   The user attempting to assign.
     * @param int $project_id The project being assigned.
     * @return bool True if the user can assign, false otherwise.
     */
    function webino_user_can_assign_project( $user_id, $project_id ) {
        if ( ! $user_id || ! $project_id ) {
            return false;
        }
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return false;
        }
        $roles = (array) $user->roles;

        // Administrator and system_manager can always assign.
        if ( in_array( 'administrator', $roles, true ) || in_array( 'system_manager', $roles, true ) ) {
            return true;
        }

        // Department manager: can assign if project's department is in their managed departments.
        $dept_manager_ids = (array) get_user_meta( $user_id, '_department_manager_dept_ids', true );
        $dept_manager_ids = array_filter( array_map( 'intval', $dept_manager_ids ) );
        if ( ! empty( $dept_manager_ids ) ) {
            $project_dept_id = (int) get_post_meta( $project_id, '_department_id', true );
            if ( $project_dept_id > 0 && in_array( $project_dept_id, $dept_manager_ids, true ) ) {
                return true;
            }
        }

        return false;
    }
}

// **REVISED & STABILIZED V3: Renders the HTML for a single task card**
if ( ! function_exists( 'webino_render_task_card' ) ) {
    function webino_render_task_card( $task_post ) {
        if (is_int($task_post)) {
            $task_post = get_post($task_post);
        }
        if ( ! $task_post || ! is_a($task_post, 'WP_Post') ) {
            return '';
        }
        
        global $post;
        $original_post = $post;
        $post = $task_post;
        setup_postdata($post);

        $task_id = get_the_ID();
        
        $priority_term = WebinoCRM_Term_Helper::get_first_term( $task_id, 'task_priority' );
        $priority_class = $priority_term ? 'priority-' . esc_attr( $priority_term->slug ) : 'priority-none';
        $priority_title = $priority_term ? esc_attr( $priority_term->name ) : 'بدون اولویت';

        $project_id = get_post_meta($task_id, '_project_id', true);
        $project_title = $project_id ? get_the_title($project_id) : '';
        $project_html = $project_title ? '<span class="webino-card-project"><i class="ri-folder-line"></i> ' . esc_html($project_title) . '</span>' : '';

        $assigned_user_id = get_post_meta($task_id, '_assigned_to', true);
        $assignee_avatar = $assigned_user_id ? get_avatar($assigned_user_id, 24) : '';
        
        $due_date = get_post_meta($task_id, '_due_date', true);
        $due_date_html = '';
        if ($due_date) {
            $due_timestamp = strtotime($due_date);
            $today_timestamp = strtotime('today');
            $due_date_class = '';

            if ($due_timestamp < $today_timestamp) {
                $due_date_class = 'webino-due-overdue';
            } elseif ($due_timestamp < strtotime('+2 days', $today_timestamp)) {
                $due_date_class = 'webino-due-near';
            }
            $due_date_html = '<span class="webino-card-due-date ' . $due_date_class . '"><i class="ri-calendar-line"></i> ' . jdate('Y/m/d', $due_timestamp) . '</span>';
        }

        $subtask_count = count(get_children(['post_parent' => $task_id, 'post_type' => 'task']));
        $subtask_html = $subtask_count > 0 ? '<span class="webino-card-subtasks"><i class="ri-task-line"></i> ' . esc_html($subtask_count) . '</span>' : '';

        $attachment_ids = get_post_meta($task_id, '_task_attachments', true);
        $attachment_count = is_array($attachment_ids) ? count($attachment_ids) : 0;
        $attachment_html = $attachment_count > 0 ? '<span class="webino-card-attachments"><i class="ri-attachment-2"></i> ' . esc_html($attachment_count) . '</span>' : '';
        
        $comment_count = get_comments_number();
        $comment_html = $comment_count > 0 ? '<span class="webino-card-comments"><i class="ri-chat-3-line"></i> ' . esc_html($comment_count) . '</span>' : '';

        $labels = WebinoCRM_Term_Helper::get_terms( $task_id, 'task_label' );
        $labels_html = '';
        if ( ! empty( $labels ) ) {
            $labels_html .= '<div class="webino-card-labels">';
            foreach($labels as $label) {
                $labels_html .= '<span class="webino-label">' . esc_html($label->name) . '</span>';
            }
            $labels_html .= '</div>';
        }
        
        $cover_html = has_post_thumbnail($task_id) ? '<div class="webino-card-cover">' . get_the_post_thumbnail($task_id, 'medium') . '</div>' : '';

        wp_reset_postdata();
        $post = $original_post;
        if($post) setup_postdata($post);

        return sprintf(
            '<div class="webino-task-card" data-task-id="%d">
                %s
                <div class="webino-card-content">
                    %s
                    <div class="webino-card-main">
                        <div class="webino-card-priority %s" title="%s"></div>
                        <h4 class="webino-card-title">%s</h4>
                    </div>
                    <div class="webino-card-footer">
                        <div class="webino-card-meta">
                            %s %s %s %s %s
                        </div>
                        <div class="webino-card-assignee">%s</div>
                    </div>
                </div>
            </div>',
            esc_attr($task_id),
            $cover_html,
            $labels_html,
            esc_attr($priority_class),
            esc_attr($priority_title),
            esc_html($task_post->post_title),
            $due_date_html,
            $attachment_html,
            $comment_html,
            $subtask_html,
            $project_html,
            $assignee_avatar
        );
    }
}

if ( ! function_exists( 'webino_jalali_to_gregorian' ) ) {
    /**
     * Converts a Jalali date string (with potential Farsi numerals) to Gregorian 'Y-m-d' format.
     * Enhanced with comprehensive validation and error handling.
     *
     * @param string $jalali_date The Jalali date, e.g., '1404/07/08' or '۱۴۰۴/۰۷/۰۸'.
     * @param bool $log_errors Whether to log errors for debugging (default: false).
     * @return string The Gregorian date, e.g., '2025-09-29', or an empty string on failure.
     */
    function webino_jalali_to_gregorian($jalali_date, $log_errors = false) {
        // Early return for empty input
        if(empty($jalali_date) || trim($jalali_date) === '') {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Empty date input');
            }
            return '';
        }
        
        // Store original for logging
        $original_input = $jalali_date;
        
        // *** CRITICAL FIX: Convert Farsi/Arabic numerals to English before any processing ***
        if (function_exists('tr_num')) {
            $jalali_date = tr_num($jalali_date, 'en');
        } else {
            // Fallback: manual conversion if tr_num doesn't exist
            $jalali_date = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], 
                                      ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $jalali_date);
        }
        
        // Normalize separators (accept both / and -)
        $jalali_date = str_replace(['-', '\\'], '/', $jalali_date);
        // Remove any whitespace
        $jalali_date = trim($jalali_date);
        
        // Validate format: should be YYYY/MM/DD or YYYY/M/D
        if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $jalali_date)) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Invalid format - ' . $original_input);
            }
            return '';
        }
        
        $parts = explode('/', $jalali_date);
        if(count($parts) !== 3) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Incorrect parts count - ' . $original_input);
            }
            return '';
        }

        // Ensure parts are integers and trim any whitespace
        $j_y = intval(trim($parts[0]));
        $j_m = intval(trim($parts[1]));
        $j_d = intval(trim($parts[2]));
        
        // Enhanced validation: check reasonable ranges
        // Jalali year should be between 1000 and 2000 (roughly 1620-2620 AD)
        if ($j_y < 1000 || $j_y > 2000) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Invalid year - ' . $original_input . ' (year: ' . $j_y . ')');
            }
            return '';
        }
        
        // Month should be 1-12
        if ($j_m < 1 || $j_m > 12) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Invalid month - ' . $original_input . ' (month: ' . $j_m . ')');
            }
            return '';
        }
        
        // Day should be at least 1
        if ($j_d < 1) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Invalid day - ' . $original_input . ' (day: ' . $j_d . ')');
            }
            return '';
        }
        
        // Use jcheckdate if available for precise validation
        if (function_exists('jcheckdate')) {
            if (!jcheckdate($j_m, $j_d, $j_y)) {
                if ($log_errors) {
                    webinocrm_debug_log('WebinoCRM Date Conversion: Invalid Jalali date - ' . $original_input . ' (Y:' . $j_y . ' M:' . $j_m . ' D:' . $j_d . ')');
                }
                return '';
            }
        } else {
            // Fallback validation: check day against max days in month
            $max_days = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]; // Persian months
            // Check for leap year in month 12
            if ($j_m == 12) {
                // Simple leap year check (not perfect but better than nothing)
                $max_days[11] = 30; // Default for Esfand
            }
            if ($j_d > $max_days[$j_m - 1]) {
                if ($log_errors) {
                    webinocrm_debug_log('WebinoCRM Date Conversion: Day exceeds month max - ' . $original_input);
                }
                return '';
            }
        }

        // Convert to Gregorian
        if (!function_exists('jalali_to_gregorian')) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: jalali_to_gregorian function not found');
            }
            return '';
        }
        
        try {
            $gregorian_parts = jalali_to_gregorian($j_y, $j_m, $j_d);
            
            // Validate conversion result
            if (!is_array($gregorian_parts) || count($gregorian_parts) !== 3) {
                if ($log_errors) {
                    webinocrm_debug_log('WebinoCRM Date Conversion: Invalid conversion result - ' . $original_input);
                }
                return '';
            }
            
            // Ensure all parts are valid
            $g_y = intval($gregorian_parts[0]);
            $g_m = intval($gregorian_parts[1]);
            $g_d = intval($gregorian_parts[2]);
            
            if ($g_y < 1900 || $g_y > 2100 || $g_m < 1 || $g_m > 12 || $g_d < 1 || $g_d > 31) {
                if ($log_errors) {
                    webinocrm_debug_log('WebinoCRM Date Conversion: Invalid Gregorian result - ' . $original_input . ' -> ' . implode('-', $gregorian_parts));
                }
                return '';
            }
            
            // Format as Y-m-d
            $result = sprintf('%04d-%02d-%02d', $g_y, $g_m, $g_d);
            
            // Final validation: check if the date is valid using strtotime
            if (strtotime($result) === false) {
                if ($log_errors) {
                    webinocrm_debug_log('WebinoCRM Date Conversion: Invalid final date - ' . $result);
                }
                return '';
            }
            
            return $result;
            
        } catch (\Exception $e) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Exception - ' . $e->getMessage() . ' for input: ' . $original_input);
            }
            return '';
        }
    }
}

if ( ! function_exists( 'webino_gregorian_to_jalali' ) ) {
    /**
     * Converts a Gregorian date string to Jalali 'Y/m/d' format.
     * Enhanced with comprehensive validation and error handling.
     *
     * @param string $gregorian_date The Gregorian date, e.g., '2025-09-29' or '2025/09/29'.
     * @param bool $log_errors Whether to log errors for debugging (default: false).
     * @return string The Jalali date, e.g., '1404/07/08', or an empty string on failure.
     */
    function webino_gregorian_to_jalali($gregorian_date, $log_errors = false) {
        // Early return for empty or invalid input
        if(empty($gregorian_date) || trim($gregorian_date) === '' || $gregorian_date == '0000-00-00') {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Empty or invalid Gregorian date input');
            }
            return '';
        }
        
        // Store original for logging
        $original_input = $gregorian_date;
        
        // Normalize separators
        $gregorian_date = str_replace(['/', '\\'], '-', trim($gregorian_date));
        
        // Validate format: should be YYYY-MM-DD or YYYY-M-D
        if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $gregorian_date)) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Invalid Gregorian format - ' . $original_input);
            }
            return '';
        }
        
        // Validate using strtotime
        $timestamp = strtotime($gregorian_date);
        if ($timestamp === false) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Invalid Gregorian date (strtotime failed) - ' . $original_input);
            }
            return '';
        }
        
        // Check if jdate function exists
        if (!function_exists('jdate')) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: jdate function not found');
            }
            return '';
        }
        
        try {
            $jalali_date = jdate('Y/m/d', $timestamp);
            
            // Validate result
            if (empty($jalali_date) || !preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $jalali_date)) {
                if ($log_errors) {
                    webinocrm_debug_log('WebinoCRM Date Conversion: Invalid Jalali conversion result - ' . $original_input . ' -> ' . $jalali_date);
                }
                return '';
            }
            
            return $jalali_date;
            
        } catch (\Exception $e) {
            if ($log_errors) {
                webinocrm_debug_log('WebinoCRM Date Conversion: Exception - ' . $e->getMessage() . ' for input: ' . $original_input);
            }
            return '';
        }
    }
}


/**
 * Automatically syncs user phone numbers across multiple meta keys when a user profile is updated.
 */
function webino_sync_user_phone_numbers($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    $phone_meta_keys = [
        'webino_mobile_phone', 'wpyarud_phone', 'webino_phone_number', 'user_phone_number'
    ];
    $updated_phone = null;

    foreach ($phone_meta_keys as $key) {
        if (isset($_POST[$key])) {
            $updated_phone = sanitize_text_field($_POST[$key]);
            break;
        }
    }

    if ($updated_phone !== null) {
        foreach ($phone_meta_keys as $key) {
            update_user_meta($user_id, $key, $updated_phone);
        }
    }
}
add_action('personal_options_update', 'webino_sync_user_phone_numbers');
add_action('edit_user_profile_update', 'webino_sync_user_phone_numbers');

/**
 * Gets the default task status slug (the first one in the defined order).
 * @return string The slug of the first status, or 'to-do' as a fallback.
 */
function webino_get_default_task_status_slug() {
    $statuses = get_terms([
        'taxonomy'   => 'task_status',
        'hide_empty' => false,
        'orderby'    => 'term_order',
        'order'      => 'ASC',
        'number'     => 1,
    ]);

    if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) {
        return $statuses[0]->slug;
    }

    return 'to-do'; // Fallback
}

if ( ! function_exists( 'webino_convert_phone_to_english' ) ) {
    /**
     * Converts Persian/Arabic digits in phone numbers to English digits
     * 
     * @param string $phone The phone number that may contain Persian/Arabic digits
     * @return string The phone number with English digits
     */
    function webino_convert_phone_to_english($phone) {
        if (empty($phone)) {
            return $phone;
        }
        
        // Use the tr_num function from jdf.php to convert Persian/Arabic digits to English
        return tr_num($phone, 'en');
    }
}

// ============================================
// Language and Number Formatting Helpers
// ============================================

/**
 * Get current language preference (returns 'en' or 'fa')
 * Priority: User Meta > Cookie > WordPress Locale
 * 
 * @return string 'en' or 'fa'
 */
if (!function_exists('webino_get_current_language')) {
	function webino_get_current_language() {
		// Priority 1: User meta
		if (is_user_logged_in()) {
			$user_lang = get_user_meta(get_current_user_id(), 'webino_language', true);
			if (!empty($user_lang) && ($user_lang === 'fa' || $user_lang === 'en')) {
				return $user_lang;
			}
		}
		
		// Priority 2: Cookie
		$cookie_lang = isset($_COOKIE['webino_language']) ? sanitize_text_field($_COOKIE['webino_language']) : '';
		if ($cookie_lang === 'fa' || $cookie_lang === 'en') {
			return $cookie_lang;
		}
		
		// Priority 3: WordPress locale (only if explicitly Persian)
		$locale = get_locale();
		if (strpos($locale, 'fa') !== false || strpos($locale, 'fa_IR') !== false) {
			return 'fa';
		}
		
		// Default: English
		return 'en';
	}
}

/**
 * WordPress locale string for current language preference.
 *
 * @return string 'fa_IR' or 'en_US'
 */
if ( ! function_exists( 'webino_get_current_locale' ) ) {
	function webino_get_current_locale() {
		return webino_get_current_language() === 'fa' ? 'fa_IR' : 'en_US';
	}
}

/**
 * Apply locale filter from user language preference (meta, cookie, WP locale).
 * Call before translated strings or menu building when bootstrap runs early.
 */
if ( ! function_exists( 'webino_apply_language_locale' ) ) {
	function webino_apply_language_locale() {
		$locale = webino_get_current_locale();
		add_filter(
			'locale',
			static function () use ( $locale ) {
				return $locale;
			},
			1,
			0
		);
	}
}

/**
 * Format number based on current language
 * 
 * @param int|float|string $number Number to format
 * @param int $decimals Number of decimal places
 * @return string Formatted number
 */
if (!function_exists('crm_format_number')) {
	function crm_format_number($number, $decimals = 0) {
		// Get current language
		$current_lang = webino_get_current_language();
		
		// If English, return English digits (no conversion) - DO NOT use class formatter
		if ($current_lang === 'en') {
			return number_format($number, $decimals);
		}
		
		// If Persian, use class formatter or convert manually
		if (class_exists('WebinoCRM_Number_Formatter')) {
			// Double-check: Make sure the class also thinks it's Persian
			if (method_exists('WebinoCRM_Number_Formatter', 'is_persian')) {
				$class_is_persian = WebinoCRM_Number_Formatter::is_persian();
				if (!$class_is_persian) {
					// Class thinks it's English, so return English digits
					return number_format($number, $decimals);
				}
			}
			
			// Use class formatter
			if (method_exists('WebinoCRM_Number_Formatter', 'format_number')) {
				return WebinoCRM_Number_Formatter::format_number($number, $decimals);
			}
		}
		
		// Manual conversion to Persian
		$formatted = number_format($number, $decimals);
		$persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
		$english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
		return str_replace($english_digits, $persian_digits, $formatted);
	}
}

/**
 * Format percentage based on current language
 * 
 * @param float|string $value Percentage value
 * @param int $decimals Number of decimal places
 * @return string Formatted percentage
 */
if (!function_exists('crm_format_percentage')) {
	function crm_format_percentage($value, $decimals = 1) {
		// Get current language
		$current_lang = webino_get_current_language();
		
		// Format number
		$formatted = number_format($value, $decimals);
		
		// If Persian, convert to Persian digits
		if ($current_lang === 'fa') {
			if (class_exists('WebinoCRM_Number_Formatter') && method_exists('WebinoCRM_Number_Formatter', 'format_percentage')) {
				return WebinoCRM_Number_Formatter::format_percentage($value, $decimals);
			}
			$persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
			$english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
			$formatted = str_replace($english_digits, $persian_digits, $formatted);
		}
		
		return $formatted . '%';
	}
}

/**
 * Format currency based on current language
 * 
 * @param float|string $amount Amount to format
 * @param string $currency Currency symbol or name
 * @return string Formatted currency
 */
if (!function_exists('crm_format_currency')) {
	function crm_format_currency($amount, $currency = '') {
		// Get current language
		$current_lang = webino_get_current_language();
		
		// Format number
		$formatted = number_format($amount);
		
		// If Persian, convert to Persian digits
		if ($current_lang === 'fa') {
			if (class_exists('WebinoCRM_Number_Formatter') && method_exists('WebinoCRM_Number_Formatter', 'format_currency')) {
				return WebinoCRM_Number_Formatter::format_currency($amount, $currency);
			}
			$persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
			$english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
			$formatted = str_replace($english_digits, $persian_digits, $formatted);
			$default_currency = empty($currency) ? 'تومان' : $currency;
			return $formatted . ' ' . $default_currency;
		} else {
			// English: return English digits
			$default_currency = empty($currency) ? '$' : $currency;
			return $default_currency . $formatted;
		}
	}
}

/**
 * Format date based on current language
 * 
 * @param string|int $date Date string or timestamp
 * @param string $format Date format
 * @return string Formatted date
 */
if (!function_exists('crm_format_date')) {
	function crm_format_date($date = '', $format = 'Y/m/d') {
		if (class_exists('WebinoCRM_Date_Formatter') && method_exists('WebinoCRM_Date_Formatter', 'format_date')) {
			return WebinoCRM_Date_Formatter::format_date($date, $format);
		}
		if (empty($date)) {
			return date_i18n($format);
		}
		if (is_numeric($date)) {
			return date_i18n($format, $date);
		}
		return date_i18n($format, strtotime($date));
	}
}

/**
 * Get human-readable time difference
 * 
 * @param int|string $from Start time
 * @param int|string $to End time (default: current time)
 * @return string Human-readable time difference
 */
if (!function_exists('webino_human_time_diff')) {
	function webino_human_time_diff($from, $to = '') {
		if (class_exists('WebinoCRM_Date_Formatter') && method_exists('WebinoCRM_Date_Formatter', 'human_time_diff')) {
			return WebinoCRM_Date_Formatter::human_time_diff($from, $to);
		}
		if (empty($to)) {
			$to = current_time('timestamp');
		}
		if (!is_numeric($from)) {
			$from = strtotime($from);
		}
		if (!is_numeric($to)) {
			$to = strtotime($to);
		}
		return human_time_diff($from, $to);
	}
}

if ( ! function_exists( 'webino_get_sms_handler' ) ) {
    /**
     * Retrieves the correct SMS handler instance based on saved settings.
     *
     * @param array $settings The plugin's settings array.
     * @return WebinoCRM_SMS_Service_Interface|null The handler instance or null if not configured.
     */
    function webino_get_sms_handler( $settings ) {
        $active_service = $settings['sms_service'] ?? null;
        $handler = null;

        switch ($active_service) {
            case 'melipayamak':
                $username = $settings['melipayamak_username'] ?? '';
                $password = $settings['melipayamak_password'] ?? '';
                $sender_number = $settings['melipayamak_sender_number'] ?? '';
                if ($username && $password && $sender_number) {
                    $handler = new CSM_Melipayamak_Handler($username, $password, $sender_number);
                }
                break;
            case 'parsgreen':
                $signature = $settings['parsgreen_signature'] ?? '';
                $sender_number = $settings['parsgreen_sender_number'] ?? '';
                if ($signature && $sender_number) {
                    $handler = new WebinoCRM_ParsGreen_Handler($signature, $sender_number);
                }
                break;
        }

        return $handler;
    }
}

if ( ! function_exists( 'webinocrm_get_request_ip' ) ) {
	/**
	 * Best-effort client IP for rate limiting (respects common proxy headers when present).
	 *
	 * @return string
	 */
	function webinocrm_get_request_ip() {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);
		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			if ( $key === 'HTTP_X_FORWARDED_FOR' && strpos( $raw, ',' ) !== false ) {
				$parts = explode( ',', $raw );
				$raw   = trim( $parts[0] );
			}
			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return $raw;
			}
		}
		return '0.0.0.0';
	}
}

if ( ! function_exists( 'webinocrm_rate_limit_allow' ) ) {
	/**
	 * Simple transient-based rate limiter (per IP + optional suffix).
	 *
	 * @param string $action_slug Unique action id.
	 * @param int    $max_requests Max attempts within the window.
	 * @param int    $window_seconds Window length in seconds.
	 * @param string $extra_suffix Optional extra key (e.g. phone hash).
	 * @return bool True if request is allowed, false if limit exceeded.
	 */
	function webinocrm_rate_limit_allow( $action_slug, $max_requests, $window_seconds, $extra_suffix = '' ) {
		$ip      = webinocrm_get_request_ip();
		$key     = 'wcrm_rl_' . md5( (string) $action_slug . '|' . $ip . '|' . (string) $extra_suffix );
		$count   = (int) get_transient( $key );
		if ( $count >= $max_requests ) {
			/**
			 * Fires when a rate limit bucket is already full (monitoring / WAF hooks).
			 *
			 * @param string $action_slug   Action key.
			 * @param string $ip            Client IP.
			 * @param string $extra_suffix  Optional suffix.
			 * @param int    $max_requests  Limit.
			 * @param int    $window_seconds Window seconds.
			 */
			do_action( 'webinocrm_rate_limit_exceeded', $action_slug, $ip, (string) $extra_suffix, $max_requests, $window_seconds );
			return false;
		}
		set_transient( $key, $count + 1, $window_seconds );
		return true;
	}
}

if ( ! function_exists( 'webinocrm_debug_log' ) ) {
	/**
	 * Log to PHP error log only when WP_DEBUG is enabled (avoid noisy/leaky production logs).
	 *
	 * @param string $message Message.
	 */
	function webinocrm_debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}
	}
}

if ( ! function_exists( 'webinocrm_lead_debug_log' ) ) {
	/**
	 * Verbose lead AJAX tracing — only when WEBINOCRM_LEAD_DEBUG is true (avoids huge logs when WP_DEBUG is on).
	 *
	 * @param string $message Message.
	 */
	function webinocrm_lead_debug_log( $message ) {
		if ( defined( 'WEBINOCRM_LEAD_DEBUG' ) && WEBINOCRM_LEAD_DEBUG ) {
			webinocrm_debug_log( $message );
		}
	}
}

if ( ! function_exists( 'webinocrm_integration_log' ) ) {
	/**
	 * Always log integration failures (SMS, Telegram, …) to PHP error_log for production ops (short line).
	 *
	 * @param string $message Message.
	 */
	function webinocrm_integration_log( $message ) {
		$msg = is_string( $message ) ? $message : wp_json_encode( $message );
		if ( strlen( $msg ) > 500 ) {
			$msg = substr( $msg, 0, 497 ) . '...';
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'WebinoCRM Integration: ' . $msg );
	}
}

if ( ! function_exists( 'webinocrm_current_user_can_accounting' ) ) {
	/**
	 * Whether the current user may access accounting AJAX and reports.
	 *
	 * @return bool
	 */
	function webinocrm_current_user_can_accounting() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'webinocrm_manage_accounting' ) ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}
}

if ( ! function_exists( 'webinocrm_current_user_has_role' ) ) {
	/**
	 * @param string|array $roles Role slug(s).
	 * @return bool
	 */
	function webinocrm_current_user_has_role( $roles ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user  = wp_get_current_user();
		$roles = (array) $roles;
		return (bool) array_intersect( $roles, (array) $user->roles );
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_contracts' ) ) {
	function webinocrm_user_can_manage_contracts() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'finance_manager', 'sales_consultant' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_read_contracts' ) ) {
	function webinocrm_user_can_read_contracts() {
		if ( webinocrm_user_can_manage_contracts() ) {
			return true;
		}
		if ( webinocrm_current_user_has_role( [ 'team_member' ] ) ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'client', 'customer' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_projects' ) ) {
	function webinocrm_user_can_manage_projects() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return current_user_can( 'manage_options' )
			|| webinocrm_current_user_has_role( [ 'system_manager', 'administrator' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_read_projects' ) ) {
	function webinocrm_user_can_read_projects() {
		if ( webinocrm_user_can_manage_projects() ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'team_member', 'client', 'customer' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_tasks' ) ) {
	function webinocrm_user_can_manage_tasks() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_tasks' ) ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'system_manager', 'team_member', 'sales_consultant' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_delete_tasks' ) ) {
	function webinocrm_user_can_delete_tasks() {
		return current_user_can( 'delete_tasks' ) || current_user_can( 'manage_options' );
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_appointments' ) ) {
	function webinocrm_user_can_manage_appointments() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return webinocrm_current_user_has_role(
			[ 'system_manager', 'team_member', 'sales_consultant', 'administrator' ]
		);
	}
}

if ( ! function_exists( 'webinocrm_user_can_read_appointments' ) ) {
	function webinocrm_user_can_read_appointments() {
		if ( webinocrm_user_can_manage_appointments() ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'client', 'customer' ] );
	}
}

if ( ! function_exists( 'webinocrm_normalize_appointment_datetime' ) ) {
	/**
	 * Normalize date + time to Y-m-d H:i:s for storage.
	 *
	 * @param string $date Gregorian Y-m-d or Jalali Y/m/d.
	 * @param string $time H:i or H:i:s.
	 * @return string
	 */
	function webinocrm_normalize_appointment_datetime( $date, $time ) {
		$date = trim( (string) $date );
		$time = trim( (string) $time );
		if ( $date === '' ) {
			return '';
		}
		if ( ! preg_match( '/^\d{4}-\d{1,2}-\d{1,2}$/', $date ) && function_exists( 'webino_jalali_to_gregorian' ) ) {
			$converted = webino_jalali_to_gregorian( $date );
			if ( $converted ) {
				$date = $converted;
			}
		}
		if ( preg_match( '/^\d{4}-\d{1,2}-\d{1,2}$/', $date ) ) {
			$parts = explode( '-', $date );
			$date  = sprintf( '%04d-%02d-%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2] );
		}
		if ( $time === '' ) {
			$time = '00:00';
		}
		if ( preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
			$time .= ':00';
		}
		return $date . ' ' . $time;
	}
}

if ( ! function_exists( 'webinocrm_ensure_task_status_slug' ) ) {
	/**
	 * Map legacy "todo" slug to canonical "to-do".
	 *
	 * @param string $slug Status slug.
	 * @return string
	 */
	function webinocrm_ensure_task_status_slug( $slug ) {
		$slug = sanitize_key( $slug );
		if ( $slug === 'todo' ) {
			return 'to-do';
		}
		return $slug;
	}
}

if ( ! function_exists( 'webinocrm_get_accessible_project_ids_for_user' ) ) {
	/**
	 * Project IDs a team member may access (assigned or has tasks on).
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	function webinocrm_get_accessible_project_ids_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return [];
		}
		$ids = [];
		$assigned = get_posts(
			[
				'post_type'      => 'project',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => '_project_manager',
						'value'   => $user_id,
						'compare' => '=',
					],
					[
						'key'     => '_assigned_to',
						'value'   => $user_id,
						'compare' => '=',
					],
				],
			]
		);
		$ids = array_merge( $ids, array_map( 'intval', $assigned ) );
		$team_projects = get_posts(
			[
				'post_type'      => 'project',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		foreach ( $team_projects as $pid ) {
			$members = get_post_meta( $pid, '_assigned_team_members', true );
			if ( is_array( $members ) && in_array( $user_id, array_map( 'intval', $members ), true ) ) {
				$ids[] = (int) $pid;
			}
		}
		$task_projects = get_posts(
			[
				'post_type'      => 'task',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[ 'key' => '_assigned_to', 'value' => $user_id, 'compare' => '=' ],
				],
			]
		);
		foreach ( $task_projects as $tid ) {
			$pid = (int) get_post_meta( $tid, '_project_id', true );
			if ( $pid > 0 ) {
				$ids[] = $pid;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_licenses' ) ) {
	function webinocrm_user_can_manage_licenses() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'system_manager' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_marketplace' ) ) {
	function webinocrm_user_can_manage_marketplace() {
		return webinocrm_user_can_manage_licenses();
	}
}

if ( ! function_exists( 'webinocrm_user_can_manage_modirpayamak' ) ) {
	function webinocrm_user_can_manage_modirpayamak() {
		return webinocrm_user_can_manage_licenses();
	}
}

if ( ! function_exists( 'webinocrm_user_can_view_logs' ) ) {
	function webinocrm_user_can_view_logs() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'system_manager' ] );
	}
}

if ( ! function_exists( 'webinocrm_user_can_view_visitor_stats' ) ) {
	function webinocrm_user_can_view_visitor_stats() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return webinocrm_current_user_has_role( [ 'system_manager' ] );
	}
}

if ( ! function_exists( 'webinocrm_is_license_rest_request' ) ) {
	/**
	 * True when the current HTTP request targets license REST endpoints only.
	 *
	 * @return bool
	 */
	function webinocrm_is_license_rest_request() {
		if ( (bool) apply_filters( 'webinocrm_license_fastpath_disabled', false ) ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $uri ) {
			return false;
		}
		return (bool) preg_match( '#/wp-json/webinocrm/v1/license/(?:check|activate)(?:/|\?|$)#', $uri );
	}
}

if ( ! function_exists( 'webinocrm_bootstrap_license_fastpath' ) ) {
	/**
	 * Minimal bootstrap for license REST (skips full WebinoCRM stack).
	 *
	 * @return void
	 */
	function webinocrm_bootstrap_license_fastpath() {
		if ( ! defined( 'WEBINOCRM_LICENSE_FASTPATH_ACTIVE' ) ) {
			define( 'WEBINOCRM_LICENSE_FASTPATH_ACTIVE', true );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-settings-handler.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-license-manager.php';
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-license-api.php';

		add_action(
			'plugins_loaded',
			static function () {
				WebinoCRM_License_Manager::init();
			},
			1
		);

		add_action(
			'rest_api_init',
			static function () {
				new WebinoCRM_License_API();
			},
			5
		);
	}
}

if ( ! function_exists( 'webinocrm_schedule_plugin_cron_events' ) ) {
	/**
	 * Schedule plugin cron hooks (activation + daily admin repair).
	 *
	 * @return void
	 */
	function webinocrm_schedule_plugin_cron_events() {
		if ( ! wp_next_scheduled( 'webino_daily_reminder_hook' ) ) {
			wp_schedule_event( strtotime( 'today 9:00am', current_time( 'timestamp' ) ), 'daily', 'webino_daily_reminder_hook' );
		}

		if ( ! wp_next_scheduled( 'webino_create_daily_tasks_hook' ) ) {
			$start_hour = '09:00';
			if ( class_exists( 'WebinoCRM_Settings_Handler' ) ) {
				$settings   = WebinoCRM_Settings_Handler::get_all_settings();
				$start_hour = $settings['work_start_hour'] ?? '09:00';
			}
			wp_schedule_event( strtotime( 'today ' . $start_hour, current_time( 'timestamp' ) ), 'daily', 'webino_create_daily_tasks_hook' );
		}

		if ( ! wp_next_scheduled( 'webinocrm_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'webinocrm_cleanup_logs' );
		}

		if ( ! wp_next_scheduled( 'webinocrm_cleanup_sessions' ) ) {
			wp_schedule_event( time(), 'daily', 'webinocrm_cleanup_sessions' );
		}

		if ( ! wp_next_scheduled( 'webinocrm_weekly_db_optimize' ) ) {
			wp_schedule_event( time(), 'weekly', 'webinocrm_weekly_db_optimize' );
		}

		if ( ! wp_next_scheduled( 'webino_check_recurring_tasks' ) ) {
			wp_schedule_event( time(), 'daily', 'webino_check_recurring_tasks' );
		}
	}
}

if ( ! function_exists( 'webinocrm_admin_maybe_schedule_cron_events' ) ) {
	/**
	 * @return void
	 */
	function webinocrm_admin_maybe_schedule_cron_events() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_transient( 'webinocrm_cron_scheduled_v1' ) ) {
			return;
		}
		webinocrm_schedule_plugin_cron_events();
		set_transient( 'webinocrm_cron_scheduled_v1', 1, DAY_IN_SECONDS );
	}
	add_action( 'admin_init', 'webinocrm_admin_maybe_schedule_cron_events' );
}
// END OF FILE - NO CLOSING PHP TAG