<?php
/**
 * PDF AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST POST /webinocrm/v1/invoices/{id}/pdf for invoices.
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_PDF_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;

    public function __construct() {
        add_action('wp_ajax_webino_generate_contract_pdf', [$this, 'generate_contract_pdf']);
        add_action('wp_ajax_webino_generate_invoice_pdf', [$this, 'generate_invoice_pdf']);
        add_action('wp_ajax_webino_download_pdf', [$this, 'download_pdf']);
    }

    /**
     * Generate Contract PDF
     */
    public function generate_contract_pdf() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
        
        if (!$contract_id) {
            wp_send_json_error(['message' => 'شناسه قرارداد نامعتبر است.']);
        }

        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        
        $pdf = new WebinoCRM_PDF_Generator();
        $result = $pdf->generate_contract_pdf($contract_id);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'فایل PDF با موفقیت ایجاد شد.',
                'pdf_url' => $result['url'],
                'filename' => $result['filename']
            ]);
        } else {
            wp_send_json_error(['message' => 'خطا در ایجاد فایل PDF.']);
        }
    }

    /**
     * Generate Invoice PDF
     */
    public function generate_invoice_pdf() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'generate_pdf' ) );
    }

    /**
     * Download PDF
     */
    public function download_pdf() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        $file_url = isset($_POST['file_url']) ? esc_url_raw($_POST['file_url']) : '';
        
        if (!$file_url) {
            wp_send_json_error(['message' => 'آدرس فایل نامعتبر است.']);
        }

        wp_send_json_success([
            'download_url' => $file_url
        ]);
    }
}

