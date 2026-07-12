<?php
/**
 * WebinoCRM Form Handler (SECURED & FINAL)
 *
 * This class now handles only non-AJAX GET requests (like payment) and legacy form submissions.
 * All primary entity management (users, projects, etc.) is now handled by class-ajax-handler.php.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Form_Handler {

    /**
     * Constructor. Hooks the main router and the ticket reply handler.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'router' ] );
    }
    
    /**
     * Helper function to send a notification to all system administrators.
     */
    private function notify_all_admins($title, $args) {
        $admins = get_users([ 'role__in' => ['administrator', 'system_manager'], 'fields' => 'ID', ]);
        foreach ($admins as $admin_id) {
            WebinoCRM_Logger::add($title, array_merge($args, ['user_id' => $admin_id]));
        }
    }

    /**
     * Main router to direct form submissions and GET actions to the correct handler.
     */
    public function router() {
        // --- Handle GET Actions (Payment, etc.) ---
        if (isset($_GET['webino_action'])) {
             $action = sanitize_key($_GET['webino_action']);
             switch ($action) {
                 case 'pay_installment': $this->handle_payment_request(); return;
                 case 'verify_payment': $this->handle_payment_verification(); return;
                 case 'delete_form': $this->handle_delete_form(); return; // This is a GET link with nonce
                 case 'download_tasks_pdf': // **ADDED FOR PDF EXPORT**
                    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'download_tasks_pdf_nonce')) {
                        $this->handle_tasks_pdf_export();
                    }
                    return;
             }
        }

        // --- Handle POST Actions ---
        if (!isset($_POST['webino_action'])) return;
        
        $action = sanitize_key($_POST['webino_action']);

        // Route to the correct handler based on the action
        switch($action) {
            case 'save_webino_settings':
                $this->handle_save_settings();
                return;
            case 'submit_automation_form': 
                $this->handle_submit_automation_form(); 
                return; 
        }
    }
    
    /**
     * NEW: Handles saving all settings from the settings page tabs.
     */
    private function handle_save_settings() {
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'webino_save_settings_nonce')) {
            $this->redirect_with_notice('security_failed');
        }
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('permission_denied');
        }

        if (isset($_POST['webino_settings']) && is_array($_POST['webino_settings'])) {
            $current_settings = WebinoCRM_Settings_Handler::get_all_settings();
            // Sanitize and remove slashes from submitted data
            $submitted_settings = stripslashes_deep($_POST['webino_settings']);
            
            // Merge new settings with old ones to not lose data from other tabs
            $new_settings = array_merge($current_settings, $submitted_settings);

            WebinoCRM_Settings_Handler::update_settings($new_settings);
        }
        
        // ** THE FIX IS HERE **
        // Get the redirect URL directly from the form submission to ensure we return to the correct tab.
        $redirect_url = wp_get_referer();
        if ( !$redirect_url ) {
            // Fallback to the _wp_http_referer field if the server variable is not available.
            $redirect_url = isset($_POST['_wp_http_referer']) ? wp_unslash($_POST['_wp_http_referer']) : home_url('/');
        }

        $this->redirect_with_notice('settings_saved', $redirect_url);
    }

    private function redirect_with_notice($notice_key, $base_url = '') {
        $url = empty($base_url) ? wp_get_referer() : $base_url;
        if (!$url) $url = home_url('/');
        $url = remove_query_arg(['webino_action', '_wpnonce', 'action', 'user_id', 'webino_notice', 'item_id'], $url);
        wp_redirect( add_query_arg('webino_notice', $notice_key, $url) );
        exit;
    }
    
    private function handle_delete_form() {
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'webino_delete_form_' . $form_id)) {
            $this->redirect_with_notice('security_failed');
        }
        
        wp_delete_post($form_id, true);
        $this->redirect_with_notice('form_deleted_success');
    }

    private function handle_submit_automation_form() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'webino_submit_automation_form_' . sanitize_key($_POST['token']))) {
            wp_die('خطای امنیتی.');
        }

        $form_id = intval($_POST['form_id']);
        $token = sanitize_key($_POST['token']);
        $token_data = get_post_meta($form_id, '_automation_token_' . $token, true);

        if (empty($token_data)) {
            wp_die('لینک نامعتبر است یا قبلاً استفاده شده.');
        }
        
        $customer_id = $token_data['customer_id'];
        $product_id = $token_data['product_id'];
        $product = wc_get_product($product_id);
        $customer = get_userdata($customer_id);

        $project_title = sprintf('پروژه %s - %s', $product->get_name(), $customer->display_name);
        
        $project_content = "این پروژه به صورت خودکار پس از خرید محصول ایجاد شده است.\n\n";
        $project_content .= "اطلاعات تکمیلی از فرم:\n";
        if (isset($_POST['form_fields']) && is_array($_POST['form_fields'])) {
            foreach ($_POST['form_fields'] as $label => $value) {
                $project_content .= sprintf("- **%s:** %s\n", sanitize_text_field($label), sanitize_text_field($value));
            }
        }

        $project_id = wp_insert_post([
            'post_title'    => $project_title,
            'post_content'  => $project_content,
            'post_author'   => $customer_id,
            'post_status'   => 'publish',
            'post_type'     => 'project',
        ]);

        if (!is_wp_error($project_id)) {
            $is_subscription = class_exists('WC_Subscriptions') && wcs_product_is_subscription($product);
            $category = $is_subscription ? 'اشتراک' : 'سرویس';
            update_post_meta($project_id, '_project_category', $category);

            $contract_id = wp_insert_post([
                'post_title' => sprintf('قرارداد برای: %s', $project_title),
                'post_type' => 'contract', 'post_status' => 'publish', 'post_author' => $customer_id
            ]);

            if (!is_wp_error($contract_id)) {
                update_post_meta($contract_id, '_project_id', $project_id);
            }

            delete_post_meta($form_id, '_automation_token_' . $token);
            $this->redirect_with_notice('project_created_success');
        } else {
             wp_die('خطا در ایجاد پروژه.');
        }
    }
    
    private function handle_payment_request() {
        $contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;
        $installment_index = isset($_GET['installment_index']) ? intval($_GET['installment_index']) : 0;

        if (!$contract_id || !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'pay_installment_' . $contract_id . '_' . $installment_index)) {
            $this->redirect_with_notice('security_failed');
        }
        
        $installments = get_post_meta($contract_id, '_installments', true);
        if (!is_array($installments) || !isset($installments[$installment_index])) $this->redirect_with_notice('installment_not_found');

        $amount_toman = (int) $installments[$installment_index]['amount'];
        $merchant_id = WebinoCRM_Settings_Handler::get_setting('zarinpal_merchant_id');

        if (empty($merchant_id)) $this->redirect_with_notice('gateway_not_configured');
        if ($amount_toman < 100) $this->redirect_with_notice('invalid_amount');

        $zarinpal = new CSM_Zarinpal_Handler($merchant_id);
        $callback_url = add_query_arg(['webino_action' => 'verify_payment', 'contract_id' => $contract_id, 'installment_index' => $installment_index], wp_get_referer());
        $project_title = get_the_title(get_post_meta($contract_id, '_project_id', true));
        $description = sprintf(__('پرداخت قسط شماره %d پروژه %s', 'webinocrm'), ($installment_index + 1), $project_title);
        
        $payment_link = $zarinpal->create_payment_link($amount_toman, $description, $callback_url);

        if ($payment_link) {
            wp_redirect($payment_link);
            exit;
        } else {
            $this->redirect_with_notice('payment_failed');
        }
    }

    private function handle_payment_verification() {
        $referrer_url = wp_get_referer();
        $contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;
        $installment_index = isset($_GET['installment_index']) ? intval($_GET['installment_index']) : 0;
        $authority = isset($_GET['Authority']) ? sanitize_text_field($_GET['Authority']) : '';
        $status = isset($_GET['Status']) ? sanitize_text_field($_GET['Status']) : '';

        if (empty($contract_id) || empty($authority) || empty($status)) $this->redirect_with_notice('payment_failed_verification', $referrer_url);
        if ( $status !== 'OK' ) $this->redirect_with_notice('payment_cancelled', $referrer_url);

        $installments = get_post_meta($contract_id, '_installments', true);
        if (!is_array($installments) || !isset($installments[$installment_index])) $this->redirect_with_notice('payment_failed_verification', $referrer_url);
        
        $amount_toman = (int) $installments[$installment_index]['amount'];
        $merchant_id = WebinoCRM_Settings_Handler::get_setting('zarinpal_merchant_id');
        $zarinpal = new CSM_Zarinpal_Handler($merchant_id);
        
        $verification = $zarinpal->verify_payment($amount_toman, $authority);

        if ( $verification && $verification['status'] === 'success' ) {
            $installments[$installment_index]['status'] = 'paid';
            $installments[$installment_index]['ref_id'] = $verification['ref_id'];
            update_post_meta($contract_id, '_installments', $installments);
            
            $contract = get_post($contract_id);
            $project_title = get_the_title(get_post_meta($contract_id, '_project_id', true));
            $customer = get_userdata($contract->post_author);
            
            $this->notify_all_admins(__('پرداخت موفق قسط', 'webinocrm'), ['content' => sprintf(__("مشتری '%s' یک قسط برای پروژه '%s' پرداخت کرد.", 'webinocrm'), $customer->display_name, $project_title), 'type' => 'notification', 'object_id' => $contract_id]);
            WebinoCRM_Logger::add(__('قسط با موفقیت پرداخت شد', 'webinocrm'), ['content' => sprintf(__("پرداخت شما برای قسط پروژه '%s' موفقیت آمیز بود. کد رهگیری: %s", 'webinocrm'), $project_title, $verification['ref_id']), 'type' => 'log', 'user_id' => $customer->ID, 'object_id' => $contract_id]);

            $this->redirect_with_notice('payment_success', $referrer_url);
        } else {
            $this->redirect_with_notice('payment_failed_verification', $referrer_url);
        }
    }
    
    /**
     * Handles the export of tasks to a PDF file.
     * This is a new method added for the PDF export feature.
     */
    private function handle_tasks_pdf_export() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this report.');
        }

        // 1. Fetch the completed tasks for today
        $today_query = new WP_Query([
            'post_type' => 'task',
            'posts_per_page' => -1,
            'date_query' => [
                ['after' => 'today', 'inclusive' => true]
            ],
            'tax_query' => [['taxonomy' => 'task_status', 'field' => 'slug', 'terms' => 'done']]
        ]);
        
        // 2. Prepare data for the table
        $header = ['پروژه', 'عنوان وظیفه', 'مسئول', 'ددلاین'];
        $data = [];
        if ($today_query->have_posts()) {
            while ($today_query->have_posts()) {
                $today_query->the_post();
                $task_id = get_the_ID();
                $project_id = get_post_meta($task_id, '_project_id', true);
                $assigned_id = get_post_meta($task_id, '_assigned_to', true);
                
                $project_title = $project_id ? get_the_title($project_id) : '---';
                $assignee_name = $assigned_id ? get_the_author_meta('display_name', $assigned_id) : '---';
                $due_date = get_post_meta($task_id, '_due_date', true);

                $data[] = [
                    $project_title,
                    get_the_title(),
                    $assignee_name,
                    $due_date
                ];
            }
            wp_reset_postdata();
        }

        // 3. Generate PDF
        $pdf = new WebinoCRM_PDF_Reporter();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->TaskTable($header, $data);
        $pdf->Output('D', 'WebinoCRM-Daily-Report-' . date('Y-m-d') . '.pdf');
        exit;
    }
}