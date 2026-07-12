<?php
/**
 * Import/Export AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/leads|customers/import|export.
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Import_Export_Ajax_Handler {

    public function __construct() {
        add_action('wp_ajax_webino_export_leads', [$this, 'export_leads']);
        add_action('wp_ajax_webino_export_customers', [$this, 'export_customers']);
        add_action('wp_ajax_webino_export_projects', [$this, 'export_projects']);
        add_action('wp_ajax_webino_import_leads', [$this, 'import_leads']);
        add_action('wp_ajax_webino_import_customers', [$this, 'import_customers']);
    }

    /**
     * Export Leads to CSV
     */
    public function export_leads() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $leads = get_posts([
            'post_type' => 'lead',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $csv_data = [];
        $csv_data[] = ['ID', 'نام', 'ایمیل', 'تلفن', 'وضعیت', 'منبع', 'تاریخ'];

        foreach ($leads as $lead) {
            $csv_data[] = [
                $lead->ID,
                $lead->post_title,
                get_post_meta($lead->ID, '_lead_email', true),
                get_post_meta($lead->ID, '_lead_phone', true),
                WebinoCRM_Term_Helper::get_first_term_name( $lead->ID, 'lead_status' ),
                WebinoCRM_Term_Helper::get_first_term_name( $lead->ID, 'lead_source' ),
                get_the_date('Y-m-d', $lead)
            ];
        }

        // Create CSV
        $filename = 'leads-export-' . date('Y-m-d') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $fp = fopen($file_path, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        foreach ($csv_data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);

        wp_send_json_success([
            'message' => count($leads) . ' سرنخ صادر شد.',
            'file_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Export Customers to CSV
     */
    public function export_customers() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $customers = get_users(['role__in' => ['customer', 'client', 'subscriber']]);

        $csv_data = [];
        $csv_data[] = ['ID', 'نام', 'ایمیل', 'تلفن', 'تاریخ عضویت'];

        foreach ($customers as $customer) {
            $csv_data[] = [
                $customer->ID,
                $customer->display_name,
                $customer->user_email,
                get_user_meta($customer->ID, 'mobile_phone', true),
                date('Y-m-d', strtotime($customer->user_registered))
            ];
        }

        // Create CSV
        $filename = 'customers-export-' . date('Y-m-d') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $fp = fopen($file_path, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        foreach ($csv_data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);

        wp_send_json_success([
            'message' => count($customers) . ' مشتری صادر شد.',
            'file_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Export Projects to CSV
     */
    public function export_projects() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $projects = get_posts([
            'post_type' => 'project',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $csv_data = [];
        $csv_data[] = ['ID', 'عنوان', 'مشتری', 'وضعیت', 'تاریخ شروع'];

        foreach ($projects as $project) {
            $customer_id = get_post_meta($project->ID, '_customer_id', true);
            $customer = get_user_by('id', $customer_id);
            $csv_data[] = [
                $project->ID,
                $project->post_title,
                $customer ? $customer->display_name : '',
                WebinoCRM_Term_Helper::get_first_term_name( $project->ID, 'project_status' ),
                get_the_date('Y-m-d', $project)
            ];
        }

        // Create CSV
        $filename = 'projects-export-' . date('Y-m-d') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $fp = fopen($file_path, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        foreach ($csv_data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);

        wp_send_json_success([
            'message' => count($projects) . ' پروژه صادر شد.',
            'file_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Import Leads from CSV
     */
    public function import_leads() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        $this->emit_import_service_result( array( 'WebinoCRM_Import_Export_Service', 'import_leads' ) );
    }

    /**
     * Import Customers from CSV
     */
    public function import_customers() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        $this->emit_import_service_result( array( 'WebinoCRM_Import_Export_Service', 'import_customers' ) );
    }

    /**
     * @param array{0: class-string, 1: string} $callable Service method.
     */
    private function emit_import_service_result( array $callable ) {
        if ( ! is_callable( $callable ) ) {
            wp_send_json_error( array( 'message' => __( 'سرویس import در دسترس نیست.', 'webinocrm' ) ) );
        }
        $result = call_user_func( $callable, array() );
        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result['data'] ?? $result );
        }
        wp_send_json_error( $result['data'] ?? array( 'message' => $result['message'] ?? __( 'خطا در import.', 'webinocrm' ) ) );
    }
}

