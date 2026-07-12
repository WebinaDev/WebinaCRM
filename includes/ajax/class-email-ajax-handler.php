<?php
/**
 * Email AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST POST /webinocrm/v1/invoices/{id}/email for invoices.
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Email_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

    public function __construct() {
        add_action('wp_ajax_webino_send_contract_email', [$this, 'send_contract_email']);
        add_action('wp_ajax_webino_send_invoice_email', [$this, 'send_invoice_email']);
    }

    /**
     * Send Contract Email
     */
    public function send_contract_email() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (!$contract_id) {
            wp_send_json_error(['message' => 'شناسه قرارداد نامعتبر است.']);
        }

        if (!$email || !is_email($email)) {
            wp_send_json_error(['message' => 'ایمیل نامعتبر است.']);
        }

        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-email-handler.php';
        
        $sent = WebinoCRM_Email_Handler::send_contract_email($contract_id, $email);
        
        if ($sent) {
            wp_send_json_success(['message' => 'ایمیل با موفقیت ارسال شد.']);
        } else {
            wp_send_json_error(['message' => 'خطا در ارسال ایمیل. لطفاً تنظیمات ایمیل سرور را بررسی کنید.']);
        }
    }

    /**
     * Send Invoice Email
     */
    public function send_invoice_email() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'send_email' ) );
    }
}

