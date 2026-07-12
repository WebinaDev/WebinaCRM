<?php
/**
 * Leads Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Leads_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! self::can_manage_leads() ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شما دسترسی لازم را ندارید.', 'webinocrm' ) ] );
        }
        $paged    = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? min( 50, max( 1, (int) $_POST['per_page'] ) ) : 20;
        $search   = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
        $status   = isset( $_POST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['status_filter'] ) ) : '';
        $args     = [
            'post_type'      => 'lead',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            's'              => $search,
        ];
        if ( $status !== '' ) {
            $args['tax_query'] = [ [ 'taxonomy' => 'lead_status', 'field' => 'slug', 'terms' => $status ] ];
        }
        $query   = new WP_Query( $args );
        $leads   = [];
        $statuses = get_terms( [ 'taxonomy' => 'lead_status', 'hide_empty' => false ] );
        if ( is_wp_error( $statuses ) ) {
            $statuses = [];
        }
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $lead_id    = get_the_ID();
                $terms      = wp_get_object_terms( $lead_id, 'lead_status' );
                $status_name = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0]->name : __( 'نامشخص', 'webinocrm' );
                $status_slug = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '';
                $assigned_to = (int) get_post_meta( $lead_id, '_lead_assigned_to', true );
                $source_terms = wp_get_object_terms( $lead_id, 'lead_source' );
                $source_name  = ( ! empty( $source_terms ) && ! is_wp_error( $source_terms ) ) ? $source_terms[0]->name : '';
                $source_slug  = ( ! empty( $source_terms ) && ! is_wp_error( $source_terms ) ) ? $source_terms[0]->slug : '';
                $form_data_raw = get_post_meta( $lead_id, '_elementor_form_data', true );
                $form_submission_data = [];
                if ( ! empty( $form_data_raw ) ) {
                    $decoded = json_decode( $form_data_raw, true );
                    if ( is_array( $decoded ) ) {
                        $form_submission_data = $decoded;
                    }
                }
                $leads[] = [
                    'id'          => $lead_id,
                    'first_name'  => get_post_meta( $lead_id, '_first_name', true ),
                    'last_name'   => get_post_meta( $lead_id, '_last_name', true ),
                    'mobile'      => get_post_meta( $lead_id, '_mobile', true ),
                    'email'       => get_post_meta( $lead_id, '_email', true ),
                    'business_name' => get_post_meta( $lead_id, '_business_name', true ),
                    'gender'      => get_post_meta( $lead_id, '_gender', true ),
                    'notes'       => get_post( $lead_id )->post_content ?: '',
                    'status_name' => $status_name,
                    'status_slug' => $status_slug,
                    'date'        => get_the_date( 'Y-m-d' ),
                    'assigned_to' => $assigned_to,
                    'assigned_to_name' => $assigned_to ? self::get_user_display_name( $assigned_to ) : '',
                    'last_assignment_note' => get_post_meta( $lead_id, '_last_assignment_note', true ) ?: '',
                    'lead_source_slug' => $source_slug,
                    'lead_source_name' => $source_name,
                    'campaign_id' => (int) get_post_meta( $lead_id, '_campaign_id', true ),
                    'form_submission_data' => $form_submission_data,
                ];
            }
            wp_reset_postdata();
        }
        $lead_sources = get_terms( [ 'taxonomy' => 'lead_source', 'hide_empty' => false ] );
        $lead_sources = is_wp_error( $lead_sources ) ? [] : $lead_sources;
        $campaigns = get_posts( [ 'post_type' => 'campaign', 'posts_per_page' => -1, 'orderby' => 'title' ] );
        return WebinoCRM_Service_Base::success( [
            'leads'   => $leads,
            'total'   => (int) $query->found_posts,
            'pages'   => (int) $query->max_num_pages,
            'statuses' => array_map( function ( $t ) {
                return [ 'slug' => $t->slug, 'name' => $t->name ];
            }, is_array( $statuses ) ? $statuses : [] ),
            'lead_sources' => array_map( function ( $t ) {
                return [ 'slug' => $t->slug, 'name' => $t->name ];
            }, $lead_sources ),
            'campaigns' => array_map( function ( $p ) {
                return [ 'id' => $p->ID, 'title' => $p->post_title ];
            }, $campaigns ),
        ] );
	}

		public static function create( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
// Log the start of add lead operation
        webinocrm_lead_debug_log('WebinoCRM: Starting add lead operation');
        try {
            // Check nonce
            // Check user permissions
            if ( ! self::can_manage_leads() ) {
                webinocrm_lead_debug_log('WebinoCRM: User does not have permission to add lead. User ID: ' . get_current_user_id());
                return WebinoCRM_Service_Base::error( ['message' => __('شما دسترسی لازم برای انجام این کار را ندارید.', 'webinocrm')] );
            }
            // Validate and sanitize input data
            $first_name = sanitize_text_field($_POST['first_name'] ?? '');
            $last_name = sanitize_text_field($_POST['last_name'] ?? '');
            $mobile = sanitize_text_field($_POST['mobile'] ?? '');
            $business_name = sanitize_text_field($_POST['business_name'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $gender = sanitize_text_field($_POST['gender'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $lead_source = sanitize_text_field($_POST['lead_source'] ?? '');
            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
            webinocrm_lead_debug_log("WebinoCRM: Lead data - First: {$first_name}, Last: {$last_name}, Mobile: {$mobile}, Email: {$email}, Business: {$business_name}, Gender: {$gender}");
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($mobile)) {
                webinocrm_lead_debug_log('WebinoCRM: Missing required fields for lead');
                return WebinoCRM_Service_Base::error( ['message' => __('نام، نام خانوادگی و شماره موبایل ضروری هستند.', 'webinocrm')] );
            }
            // Insert lead
            $lead_id = wp_insert_post([
                'post_type' => 'lead',
                'post_title' => $first_name . ' ' . $last_name,
                'post_content' => $notes,
                'post_status' => 'publish',
            ]);
            if (is_wp_error($lead_id)) {
                webinocrm_lead_debug_log('WebinoCRM: Error inserting lead: ' . $lead_id->get_error_message());
                return WebinoCRM_Service_Base::error( ['message' => __('خطایی در ثبت سرنخ رخ داد.', 'webinocrm')] );
            }
            webinocrm_lead_debug_log("WebinoCRM: Lead created successfully with ID: {$lead_id}");
            // Update meta data
            update_post_meta($lead_id, '_first_name', $first_name);
            update_post_meta($lead_id, '_last_name', $last_name);
            update_post_meta($lead_id, '_mobile', $mobile);
            update_post_meta($lead_id, '_email', $email);
            update_post_meta($lead_id, '_business_name', $business_name);
            update_post_meta($lead_id, '_gender', $gender);
            webinocrm_lead_debug_log("WebinoCRM: Lead meta data updated for ID: {$lead_id}");
            // Set lead source
            if ( ! empty( $lead_source ) ) {
                wp_set_object_terms( $lead_id, $lead_source, 'lead_source' );
            }
            // Set campaign
            if ( $campaign_id > 0 ) {
                update_post_meta( $lead_id, '_campaign_id', $campaign_id );
            }
            // Set lead status
            $settings = WebinoCRM_Settings_Handler::get_all_settings();
            $default_status = !empty($settings['lead_default_status']) ? $settings['lead_default_status'] : null;
            if ($default_status) {
                $status_result = wp_set_object_terms($lead_id, $default_status, 'lead_status');
                webinocrm_lead_debug_log("WebinoCRM: Set default status '{$default_status}' for lead {$lead_id}. Result: " . print_r($status_result, true));
            } else {
                $first_status = get_terms(['taxonomy' => 'lead_status', 'hide_empty' => false, 'number' => 1, 'orderby' => 'term_id', 'order' => 'ASC']);
                if (!empty($first_status) && !is_wp_error($first_status)) {
                    $status_result = wp_set_object_terms($lead_id, $first_status[0]->slug, 'lead_status');
                    webinocrm_lead_debug_log("WebinoCRM: Set first available status '{$first_status[0]->slug}' for lead {$lead_id}. Result: " . print_r($status_result, true));
                }
            }
            // Send SMS if enabled
            if (!empty($settings['lead_auto_sms_enabled']) && $settings['lead_auto_sms_enabled'] == '1') {
                webinocrm_lead_debug_log("WebinoCRM: Auto SMS enabled, attempting to send welcome SMS for lead {$lead_id}");
                self::send_lead_welcome_sms($lead_id, $first_name, $last_name, $mobile, $business_name, $gender, $settings);
            } else {
                webinocrm_lead_debug_log("WebinoCRM: Auto SMS disabled for lead {$lead_id}");
            }
            // Add to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('افزودن سرنخ', [
                    'content' => "سرنخ جدید با نام {$first_name} {$last_name} و شماره {$mobile} ثبت شد.",
                    'type' => 'log',
                    'details' => [
                        'lead_id' => $lead_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'mobile' => $mobile,
                        'business_name' => $business_name,
                        'gender' => $gender,
                        'user_id' => get_current_user_id()
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::success( ['message' => __('سرنخ با موفقیت ثبت شد.', 'webinocrm'), 'reload' => true] );
        } catch (Exception $e) {
            webinocrm_lead_debug_log('WebinoCRM Add Lead Error: ' . $e->getMessage());
            webinocrm_lead_debug_log('WebinoCRM Add Lead Error Stack Trace: ' . $e->getTraceAsString());
            // Add error to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('خطای افزودن سرنخ', [
                    'content' => 'خطا در افزودن سرنخ: ' . $e->getMessage(),
                    'type' => 'error',
                    'details' => [
                        'user_id' => get_current_user_id(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'post_data' => $_POST
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی در هنگام ثبت سرنخ رخ داد.', 'webinocrm' )] );
        }
	}

		public static function update( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
// Log the start of edit lead operation
        webinocrm_lead_debug_log('WebinoCRM: Starting edit lead operation');
        try {
            // Check nonce
            // Check user permissions
            if ( ! self::can_manage_leads() ) {
                webinocrm_lead_debug_log('WebinoCRM: User does not have permission to edit lead. User ID: ' . get_current_user_id());
                return WebinoCRM_Service_Base::error( ['message' => __('شما دسترسی لازم برای انجام این کار را ندارید.', 'webinocrm')] );
            }
            $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
            if ($lead_id <= 0) {
                webinocrm_lead_debug_log('WebinoCRM: Invalid lead ID in edit lead: ' . $lead_id);
                return WebinoCRM_Service_Base::error( ['message' => __('شناسه سرنخ نامعتبر است.', 'webinocrm')] );
            }
            webinocrm_lead_debug_log("WebinoCRM: Editing lead ID: {$lead_id}");
            // Validate and sanitize input data
            $first_name = sanitize_text_field($_POST['first_name'] ?? '');
            $last_name = sanitize_text_field($_POST['last_name'] ?? '');
            $mobile = sanitize_text_field($_POST['mobile'] ?? '');
            $business_name = sanitize_text_field($_POST['business_name'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $gender = sanitize_text_field($_POST['gender'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $lead_source = sanitize_text_field($_POST['lead_source'] ?? '');
            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
            webinocrm_lead_debug_log("WebinoCRM: Lead edit data - First: {$first_name}, Last: {$last_name}, Mobile: {$mobile}, Email: {$email}, Business: {$business_name}, Gender: {$gender}");
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($mobile)) {
                webinocrm_lead_debug_log('WebinoCRM: Missing required fields for lead edit');
                return WebinoCRM_Service_Base::error( ['message' => __('نام، نام خانوادگی و شماره موبایل ضروری هستند.', 'webinocrm')] );
            }
            // Update post data
            $post_data = ['ID' => $lead_id, 'post_title' => $first_name . ' ' . $last_name, 'post_content' => $notes];
            $result = wp_update_post($post_data, true);
            if (is_wp_error($result)) {
                webinocrm_lead_debug_log('WebinoCRM: Error updating lead post: ' . $result->get_error_message());
                return WebinoCRM_Service_Base::error( ['message' => __('خطایی در به‌روزرسانی سرنخ رخ داد.', 'webinocrm')] );
            }
            webinocrm_lead_debug_log("WebinoCRM: Lead post updated successfully for ID: {$lead_id}");
            // Update meta data
            update_post_meta($lead_id, '_first_name', $first_name);
            update_post_meta($lead_id, '_last_name', $last_name);
            update_post_meta($lead_id, '_mobile', $mobile);
            update_post_meta($lead_id, '_email', $email);
            update_post_meta($lead_id, '_business_name', $business_name);
            update_post_meta($lead_id, '_gender', $gender);
            if ( $campaign_id > 0 ) {
                update_post_meta( $lead_id, '_campaign_id', $campaign_id );
            } else {
                delete_post_meta( $lead_id, '_campaign_id' );
            }
            if ( ! empty( $lead_source ) ) {
                wp_set_object_terms( $lead_id, $lead_source, 'lead_source' );
            } else {
                wp_set_object_terms( $lead_id, [], 'lead_source' );
            }
            webinocrm_lead_debug_log("WebinoCRM: Lead meta data updated for ID: {$lead_id}");
            // Update lead status if provided
            if (isset($_POST['lead_status'])) {
                $status_slug = sanitize_text_field($_POST['lead_status']);
                if (!empty($status_slug)) {
                    $term_result = wp_set_object_terms($lead_id, $status_slug, 'lead_status');
                    if (is_wp_error($term_result)) {
                        webinocrm_lead_debug_log('WebinoCRM: Error setting lead status: ' . $term_result->get_error_message());
                        return WebinoCRM_Service_Base::error( ['message' => sprintf( __( 'خطایی در هنگام به‌روزرسانی وضعیت رخ داد: %s', 'webinocrm' ), $term_result->get_error_message() )] );
                    }
                    webinocrm_lead_debug_log("WebinoCRM: Lead status updated to '{$status_slug}' for ID: {$lead_id}");
                }
            }
            // Clear any caches that might affect the display
            wp_cache_delete($lead_id, 'post_meta');
            clean_post_cache($lead_id);
            // Add to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('ویرایش سرنخ', [
                    'content' => "سرنخ با نام {$first_name} {$last_name} ویرایش شد.",
                    'type' => 'log',
                    'details' => [
                        'lead_id' => $lead_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'mobile' => $mobile,
                        'business_name' => $business_name,
                        'gender' => $gender,
                        'user_id' => get_current_user_id()
                    ]
                ]);
            }
            $redirect_url = home_url('/dashboard/leads');
            if ( ! empty( $_POST['_wp_http_referer'] ) ) {
                $redirect_url = esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) );
            }
            return WebinoCRM_Service_Base::success( ['message' => __('تغییرات با موفقیت ذخیره شد.', 'webinocrm'), 'redirect_url' => $redirect_url] );
        } catch (Exception $e) {
            webinocrm_lead_debug_log('WebinoCRM Edit Lead Error: ' . $e->getMessage());
            webinocrm_lead_debug_log('WebinoCRM Edit Lead Error Stack Trace: ' . $e->getTraceAsString());
            // Add error to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('خطای ویرایش سرنخ', [
                    'content' => 'خطا در ویرایش سرنخ: ' . $e->getMessage(),
                    'type' => 'error',
                    'details' => [
                        'lead_id' => $lead_id ?? 0,
                        'user_id' => get_current_user_id(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'post_data' => $_POST
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی در هنگام ویرایش سرنخ رخ داد.', 'webinocrm' )] );
        }
	}

		public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
// Log the start of delete lead operation
        webinocrm_lead_debug_log('WebinoCRM: Starting delete lead operation');
        try {
            // Check nonce
            // Check user permissions
            if ( ! self::can_manage_leads() ) {
                webinocrm_lead_debug_log('WebinoCRM: User does not have permission to delete lead. User ID: ' . get_current_user_id());
                return WebinoCRM_Service_Base::error( ['message' => __('شما دسترسی لازم برای انجام این کار را ندارید.', 'webinocrm')] );
            }
            $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
            if ($lead_id <= 0) {
                webinocrm_lead_debug_log('WebinoCRM: Invalid lead ID in delete lead: ' . $lead_id);
                return WebinoCRM_Service_Base::error( ['message' => __('شناسه سرنخ نامعتبر است.', 'webinocrm')] );
            }
            // Get lead info for logging before deletion
            $lead = get_post($lead_id);
            $lead_name = $lead ? $lead->post_title : 'Unknown';
            $first_name = get_post_meta($lead_id, '_first_name', true);
            $last_name = get_post_meta($lead_id, '_last_name', true);
            $mobile = get_post_meta($lead_id, '_mobile', true);
            webinocrm_lead_debug_log("WebinoCRM: Deleting lead ID: {$lead_id}, Name: {$lead_name}");
            $result = wp_delete_post($lead_id, true);
            if ($result === false) {
                webinocrm_lead_debug_log('WebinoCRM: Error deleting lead: ' . $lead_id);
                return WebinoCRM_Service_Base::error( ['message' => __('خطایی در حذف سرنخ رخ داد.', 'webinocrm')] );
            }
            webinocrm_lead_debug_log("WebinoCRM: Lead deleted successfully: {$lead_id}");
            // Add to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('حذف سرنخ', [
                    'content' => "سرنخ {$lead_name} ({$first_name} {$last_name}) حذف شد.",
                    'type' => 'log',
                    'details' => [
                        'lead_id' => $lead_id,
                        'lead_name' => $lead_name,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'mobile' => $mobile,
                        'user_id' => get_current_user_id()
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::success( ['message' => __('سرنخ با موفقیت حذف شد.', 'webinocrm'), 'reload' => true] );
        } catch (Exception $e) {
            webinocrm_lead_debug_log('WebinoCRM Delete Lead Error: ' . $e->getMessage());
            webinocrm_lead_debug_log('WebinoCRM Delete Lead Error Stack Trace: ' . $e->getTraceAsString());
            // Add error to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('خطای حذف سرنخ', [
                    'content' => 'خطا در حذف سرنخ: ' . $e->getMessage(),
                    'type' => 'error',
                    'details' => [
                        'lead_id' => $lead_id ?? 0,
                        'user_id' => get_current_user_id(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'post_data' => $_POST
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی در هنگام حذف سرنخ رخ داد.', 'webinocrm' )] );
        }
	}

		public static function assign( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! self::can_manage_leads() ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شما دسترسی لازم را ندارید.', 'webinocrm' ) ] );
        }
        $lead_id      = isset( $_POST['lead_id'] ) ? intval( $_POST['lead_id'] ) : 0;
        $assigned_to  = isset( $_POST['assigned_to'] ) ? intval( $_POST['assigned_to'] ) : 0;
        $note         = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
        if ( ! $lead_id ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شناسه سرنخ نامعتبر است.', 'webinocrm' ) ] );
        }
        $lead = get_post( $lead_id );
        if ( ! $lead || $lead->post_type !== 'lead' ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'سرنخ یافت نشد.', 'webinocrm' ) ] );
        }
        update_post_meta( $lead_id, '_lead_assigned_to', $assigned_to );
        if ( ! empty( $note ) ) {
            update_post_meta( $lead_id, '_last_assignment_note', $note );
        } elseif ( $assigned_to === 0 ) {
            delete_post_meta( $lead_id, '_last_assignment_note' );
        }
        if ( $assigned_to > 0 ) {
            $assigned_term = get_term_by( 'slug', 'assigned', 'lead_status' );
            if ( $assigned_term ) {
                wp_set_object_terms( $lead_id, $assigned_term->term_id, 'lead_status' );
            }
        } else {
            $new_term = get_term_by( 'slug', 'new', 'lead_status' );
            if ( $new_term ) {
                wp_set_object_terms( $lead_id, $new_term->term_id, 'lead_status' );
            } else {
                $first_status = get_terms( [
                    'taxonomy'   => 'lead_status',
                    'hide_empty' => false,
                    'number'     => 1,
                    'orderby'    => 'term_id',
                    'order'      => 'ASC',
                ] );
                if ( ! empty( $first_status ) && ! is_wp_error( $first_status ) ) {
                    wp_set_object_terms( $lead_id, $first_status[0]->term_id, 'lead_status' );
                }
            }
        }
        wp_cache_delete( $lead_id, 'post_meta' );
        clean_post_cache( $lead_id );
        if ( class_exists( 'WebinoCRM_Logger' ) ) {
            $assignee_name = $assigned_to ? self::get_user_display_name( $assigned_to ) : __( 'بدون ارجاع', 'webinocrm' );
            WebinoCRM_Logger::add( 'ارجاع سرنخ', [
                'content' => sprintf( __( 'سرنخ #%1$d به %2$s ارجاع داده شد.', 'webinocrm' ), $lead_id, $assignee_name ),
                'type' => 'log',
                'details' => [ 'lead_id' => $lead_id, 'assigned_to' => $assigned_to, 'user_id' => get_current_user_id() ],
            ] );
        }
        return WebinoCRM_Service_Base::success( [ 'message' => __( 'ارجاع با موفقیت انجام شد.', 'webinocrm' ), 'reload' => true ] );
	}

		public static function change_status( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
// Log the start of change lead status operation
        webinocrm_lead_debug_log('WebinoCRM: Starting change lead status operation');
        try {
            // Check nonce
            // Check user permissions
            if ( ! self::can_manage_leads() ) {
                webinocrm_lead_debug_log('WebinoCRM: User does not have permission to change lead status. User ID: ' . get_current_user_id());
                return WebinoCRM_Service_Base::error( ['message' => __('شما دسترسی لازم برای انجام این کار را ندارید.', 'webinocrm')] );
            }
            $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
            $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
            webinocrm_lead_debug_log("WebinoCRM: Changing lead status - Lead ID: {$lead_id}, New Status: {$new_status}");
            if ($lead_id <= 0 || empty($new_status)) {
                webinocrm_lead_debug_log('WebinoCRM: Invalid lead ID or status in change lead status');
                return WebinoCRM_Service_Base::error( ['message' => __('اطلاعات ارسالی نامعتبر است.', 'webinocrm')] );
            }
            // Get lead info for logging
            $lead = get_post($lead_id);
            $lead_name = $lead ? $lead->post_title : 'Unknown';
            // Get current status before change
            $current_terms = get_the_terms($lead_id, 'lead_status');
            $current_status = !empty($current_terms) && !is_wp_error($current_terms) ? $current_terms[0]->name : 'بدون وضعیت';
            // Get new status name
            $new_status_term = get_term_by('slug', $new_status, 'lead_status');
            $new_status_name = $new_status_term ? $new_status_term->name : $new_status;
            webinocrm_lead_debug_log("WebinoCRM: Changing lead status from '{$current_status}' to '{$new_status_name}' for lead ID: {$lead_id}");
            $result = wp_set_object_terms($lead_id, $new_status, 'lead_status');
            if (is_wp_error($result)) {
                webinocrm_lead_debug_log('WebinoCRM: Error changing lead status: ' . $result->get_error_message());
                return WebinoCRM_Service_Base::error( ['message' => sprintf( __( 'خطا در تغییر وضعیت: %s', 'webinocrm' ), $result->get_error_message() )] );
            }
            webinocrm_lead_debug_log("WebinoCRM: Lead status changed successfully for ID: {$lead_id} from '{$current_status}' to '{$new_status_name}'");
            // Clear any caches that might affect the display
            wp_cache_delete($lead_id, 'post_meta');
            clean_post_cache($lead_id);
            // Add to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('تغییر وضعیت سرنخ', [
                    'content' => "وضعیت سرنخ {$lead_name} از '{$current_status}' به '{$new_status_name}' تغییر یافت.",
                    'type' => 'log',
                    'details' => [
                        'lead_id' => $lead_id,
                        'lead_name' => $lead_name,
                        'old_status' => $current_status,
                        'new_status' => $new_status_name,
                        'new_status_slug' => $new_status,
                        'user_id' => get_current_user_id()
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::success( ['message' => __('وضعیت با موفقیت به‌روزرسانی شد.', 'webinocrm')] );
        } catch (Exception $e) {
            webinocrm_lead_debug_log('WebinoCRM Change Lead Status Error: ' . $e->getMessage());
            webinocrm_lead_debug_log('WebinoCRM Change Lead Status Error Stack Trace: ' . $e->getTraceAsString());
            // Add error to system logs
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('خطای تغییر وضعیت سرنخ', [
                    'content' => 'خطا در تغییر وضعیت سرنخ: ' . $e->getMessage(),
                    'type' => 'error',
                    'details' => [
                        'lead_id' => $lead_id ?? 0,
                        'new_status' => $new_status ?? '',
                        'user_id' => get_current_user_id(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'post_data' => $_POST
                    ]
                ]);
            }
            return WebinoCRM_Service_Base::error( ['message' => __( 'یک خطای سیستمی در هنگام تغییر وضعیت سرنخ رخ داد.', 'webinocrm' )] );
        }
	}

		public static function assignees( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! self::can_manage_leads() ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شما دسترسی لازم را ندارید.', 'webinocrm' ) ] );
        }
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $args = [
            'role__in' => [ 'sales_consultant', 'system_manager', 'administrator' ],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ];
        if ( ! empty( $search ) ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name', 'user_nicename' ];
        }
        $users = get_users( $args );
        $items = array_map( function ( $u ) {
            return [ 'id' => $u->ID, 'display_name' => $u->display_name ];
        }, $users );
        return WebinoCRM_Service_Base::success( [ 'users' => $items ] );
	}

		public static function for_contract( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! self::can_manage_leads() ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شما دسترسی لازم را ندارید.', 'webinocrm' ) ] );
        }
        $lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
        if ( ! $lead_id ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شناسه سرنخ نامعتبر است.', 'webinocrm' ) ] );
        }
        $lead = get_post( $lead_id );
        if ( ! $lead || $lead->post_type !== 'lead' ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'سرنخ یافت نشد.', 'webinocrm' ) ] );
        }
        $first_name   = get_post_meta( $lead_id, '_first_name', true );
        $last_name    = get_post_meta( $lead_id, '_last_name', true );
        $mobile       = get_post_meta( $lead_id, '_mobile', true );
        $email        = get_post_meta( $lead_id, '_email', true );
        $business_name = get_post_meta( $lead_id, '_business_name', true );
        $existing_customer_id = 0;
        if ( ! empty( $mobile ) || ! empty( $email ) ) {
            $args = [ 'role' => 'customer' ];
            if ( ! empty( $email ) ) {
                $u = get_user_by( 'email', $email );
                if ( $u ) $existing_customer_id = $u->ID;
            }
            if ( ! $existing_customer_id && ! empty( $mobile ) ) {
                $users = get_users( [ 'meta_key' => 'webino_mobile_phone', 'meta_value' => $mobile, 'number' => 1 ] );
                if ( ! empty( $users ) ) $existing_customer_id = $users[0]->ID;
            }
        }
        return WebinoCRM_Service_Base::success( [
            'lead_id'   => $lead_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'mobile'     => $mobile,
            'email'      => $email,
            'business_name' => $business_name,
            'existing_customer_id' => $existing_customer_id,
        ] );
	}

		public static function create_customer( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if ( ! self::can_manage_leads() ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شما دسترسی لازم را ندارید.', 'webinocrm' ) ] );
        }
        $lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
        if ( ! $lead_id ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'شناسه سرنخ نامعتبر است.', 'webinocrm' ) ] );
        }
        $lead = get_post( $lead_id );
        if ( ! $lead || $lead->post_type !== 'lead' ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'سرنخ یافت نشد.', 'webinocrm' ) ] );
        }
        $first_name   = get_post_meta( $lead_id, '_first_name', true );
        $last_name    = get_post_meta( $lead_id, '_last_name', true );
        $mobile       = get_post_meta( $lead_id, '_mobile', true );
        $email        = get_post_meta( $lead_id, '_email', true );
        $business_name = get_post_meta( $lead_id, '_business_name', true );
        if ( empty( $first_name ) || empty( $last_name ) || empty( $mobile ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => __( 'نام، نام خانوادگی و موبایل سرنخ الزامی هستند.', 'webinocrm' ) ] );
        }
        $login = 'cust_' . $lead_id . '_' . preg_replace( '/\D/', '', $mobile );
        $login = sanitize_user( $login, true );
        if ( strlen( $login ) < 3 ) $login = 'cust_' . $lead_id . '_' . time();
        $email = ! empty( $email ) ? sanitize_email( $email ) : $login . '@webinocrm.local';
        $user_id = wp_create_user( $login, wp_generate_password( 12, true ), $email );
        if ( is_wp_error( $user_id ) ) {
            return WebinoCRM_Service_Base::error( [ 'message' => $user_id->get_error_message() ] );
        }
        wp_update_user( [
            'ID' => $user_id,
            'display_name' => trim( $first_name . ' ' . $last_name ),
            'first_name' => $first_name,
            'last_name' => $last_name,
        ] );
        $u = get_user_by( 'ID', $user_id );
        if ( $u ) {
            $u->set_role( 'customer' );
        }
        update_user_meta( $user_id, 'webino_mobile_phone', $mobile );
        if ( ! empty( $business_name ) ) {
            update_user_meta( $user_id, 'billing_company', $business_name );
        }
        update_post_meta( $lead_id, '_converted_to_customer_id', $user_id );
        $terms = wp_get_object_terms( $lead_id, 'lead_status' );
        $converted = get_term_by( 'slug', 'converted', 'lead_status' );
        if ( $converted ) {
            wp_set_object_terms( $lead_id, $converted->term_id, 'lead_status' );
        }
        return WebinoCRM_Service_Base::success( [
            'customer_id' => $user_id,
            'message' => __( 'مشتری با موفقیت از سرنخ ایجاد شد.', 'webinocrm' ),
        ] );
	}


	private static function can_manage_leads() {
$user = wp_get_current_user();
        $roles = (array) $user->roles;
        return current_user_can('manage_options')
            || in_array('system_manager', $roles, true)
            || in_array('sales_consultant', $roles, true);
	}

	private static function get_user_display_name( $user_id ) {
$user = $user_id ? get_userdata( (int) $user_id ) : null;
        return $user && $user->exists() ? $user->display_name : '';
	}

	private static function send_lead_welcome_sms($lead_id, $first_name, $last_name, $mobile, $business_name, $gender, $settings) {
webinocrm_lead_debug_log("WebinoCRM: Starting SMS send for lead {$lead_id}, gender: {$gender}, mobile: {$mobile}");
        try {
            // Get SMS handler
            if (!function_exists('webino_get_sms_handler')) {
                require_once WEBINOCRM_PLUGIN_DIR . 'includes/webino-functions.php';
            }
            $sms_handler = webino_get_sms_handler($settings);
            if (!$sms_handler) {
                webinocrm_lead_debug_log('WebinoCRM: SMS handler not configured for lead welcome SMS');
                if (class_exists('WebinoCRM_Logger')) {
                    WebinoCRM_Logger::add('خطای پیامک سرنخ', [
                        'content' => 'سرویس پیامک پیکربندی نشده است.',
                        'type' => 'error',
                        'details' => [
                            'lead_id' => $lead_id,
                            'mobile' => $mobile,
                            'gender' => $gender
                        ]
                    ]);
                }
            }
            webinocrm_lead_debug_log('WebinoCRM: SMS handler loaded successfully');
            // Determine message template based on gender and service
            $message = '';
            $params = [];
            $sms_service = $settings['sms_service'] ?? 'melipayamak';
            webinocrm_lead_debug_log("WebinoCRM: SMS service: {$sms_service}, Gender: {$gender}");
            if ($sms_service === 'melipayamak') {
                // Use pattern-based SMS for Melipayamak
                if ($gender === 'male' && !empty($settings['lead_pattern_male'])) {
                    $message = $settings['lead_pattern_male'];
                    $params = ['first_name' => $first_name, 'last_name' => $last_name, 'business_name' => $business_name];
                    webinocrm_lead_debug_log("WebinoCRM: Using Melipayamak male pattern: {$message}");
                } elseif ($gender === 'female' && !empty($settings['lead_pattern_female'])) {
                    $message = $settings['lead_pattern_female'];
                    $params = ['first_name' => $first_name, 'last_name' => $last_name, 'business_name' => $business_name];
                    webinocrm_lead_debug_log("WebinoCRM: Using Melipayamak female pattern: {$message}");
                } else {
                    webinocrm_lead_debug_log("WebinoCRM: No Melipayamak pattern found for gender: {$gender}");
                }
            } else {
                // Use text-based SMS for ParsGreen
                if ($gender === 'male' && !empty($settings['parsgreen_lead_msg_male'])) {
                    $message = $settings['parsgreen_lead_msg_male'];
                    webinocrm_lead_debug_log("WebinoCRM: Using ParsGreen male message: {$message}");
                } elseif ($gender === 'female' && !empty($settings['parsgreen_lead_msg_female'])) {
                    $message = $settings['parsgreen_lead_msg_female'];
                    webinocrm_lead_debug_log("WebinoCRM: Using ParsGreen female message: {$message}");
                } else {
                    webinocrm_lead_debug_log("WebinoCRM: No ParsGreen message found for gender: {$gender}");
                }
            }
            if (empty($message)) {
                webinocrm_lead_debug_log('WebinoCRM: No SMS template found for lead gender: ' . $gender . ', service: ' . $sms_service);
                if (class_exists('WebinoCRM_Logger')) {
                    WebinoCRM_Logger::add('خطای پیامک سرنخ', [
                        'content' => 'الگوی پیامک برای جنسیت ' . $gender . ' و سرویس ' . $sms_service . ' یافت نشد.',
                        'type' => 'error',
                        'details' => [
                            'lead_id' => $lead_id,
                            'mobile' => $mobile,
                            'gender' => $gender,
                            'sms_service' => $sms_service
                        ]
                    ]);
                }
            }
            // Replace placeholders in message
            $original_message = $message;
            $full_name = trim($first_name . ' ' . $last_name);
            $message = str_replace('{first_name}', $first_name, $message);
            $message = str_replace('{last_name}', $last_name, $message);
            $message = str_replace('{full_name}', $full_name, $message);
            $message = str_replace('{business_name}', $business_name, $message);
            webinocrm_lead_debug_log("WebinoCRM: Final message after replacement: {$message}");
            // Send SMS
            $result = $sms_handler->send_sms($mobile, $message, $params);
            if ($result) {
                webinocrm_lead_debug_log('WebinoCRM: Welcome SMS sent successfully to lead ID: ' . $lead_id);
                if (class_exists('WebinoCRM_Logger')) {
                    WebinoCRM_Logger::add('ارسال پیامک سرنخ', [
                        'content' => "پیامک خوشامدگویی برای سرنخ {$first_name} {$last_name} ارسال شد.",
                        'type' => 'log',
                        'details' => [
                            'lead_id' => $lead_id,
                            'mobile' => $mobile,
                            'gender' => $gender,
                            'sms_service' => $sms_service,
                            'message' => $message
                        ]
                    ]);
                }
            } else {
                webinocrm_lead_debug_log('WebinoCRM: Failed to send welcome SMS to lead ID: ' . $lead_id);
                if (class_exists('WebinoCRM_Logger')) {
                    WebinoCRM_Logger::add('خطای پیامک سرنخ', [
                        'content' => 'خطا در ارسال پیامک خوشامدگویی برای سرنخ.',
                        'type' => 'error',
                        'details' => [
                            'lead_id' => $lead_id,
                            'mobile' => $mobile,
                            'gender' => $gender,
                            'sms_service' => $sms_service,
                            'message' => $message
                        ]
                    ]);
                }
            }
        } catch (Exception $e) {
            webinocrm_lead_debug_log('WebinoCRM SMS Error: ' . $e->getMessage());
            webinocrm_lead_debug_log('WebinoCRM SMS Error Stack Trace: ' . $e->getTraceAsString());
            if (class_exists('WebinoCRM_Logger')) {
                WebinoCRM_Logger::add('خطای پیامک سرنخ', [
                    'content' => 'خطا در ارسال پیامک سرنخ: ' . $e->getMessage(),
                    'type' => 'error',
                    'details' => [
                        'lead_id' => $lead_id,
                        'mobile' => $mobile,
                        'gender' => $gender,
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine()
                    ]
                ]);
            }
        }
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webino_get_leads' => array( __CLASS__, 'list' ),
				'webino_add_lead' => array( __CLASS__, 'create' ),
				'webino_edit_lead' => array( __CLASS__, 'update' ),
				'webino_delete_lead' => array( __CLASS__, 'delete' ),
				'webino_assign_lead' => array( __CLASS__, 'assign' ),
				'webino_change_lead_status' => array( __CLASS__, 'change_status' ),
				'webinocrm_get_lead_assignees' => array( __CLASS__, 'assignees' ),
				'webinocrm_get_lead_for_contract' => array( __CLASS__, 'for_contract' ),
				'webinocrm_create_customer_from_lead' => array( __CLASS__, 'create_customer' ),

			)
		);
	}
}
