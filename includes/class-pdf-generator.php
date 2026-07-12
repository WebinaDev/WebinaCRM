<?php
/**
 * PDF Generator for Contracts and Invoices
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/fpdf/fpdf.php';

class WebinoCRM_PDF_Generator extends FPDF {
    
    private $company_name;
    private $company_address;
    private $company_phone;
    private $company_email;
    private $logo_path;
    
    public function __construct() {
        parent::__construct('P', 'mm', 'A4');
        
        // Get company info from white label or settings
        if (class_exists('WebinoCRM_White_Label')) {
            $this->company_name = WebinoCRM_White_Label::get_company_name();
            $logo_url = WebinoCRM_White_Label::get_company_logo();
            // Convert URL to file path
            if ($logo_url && strpos($logo_url, home_url()) === 0) {
                $this->logo_path = str_replace(home_url('/'), ABSPATH, $logo_url);
            } elseif ($logo_url && strpos($logo_url, 'http') !== 0) {
                // Relative path
                $this->logo_path = ABSPATH . ltrim($logo_url, '/');
            } elseif ($logo_url) {
                // Try to download external logo or use as-is
                $this->logo_path = $logo_url;
            }
        } else {
            $this->company_name = get_option('blogname', 'شرکت');
            // Logo
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
                if ($logo) {
                    $this->logo_path = str_replace(home_url('/'), ABSPATH, $logo[0]);
                }
            }
        }
        
        $this->company_address = get_option('webinocrm_company_address', '');
        $this->company_phone = get_option('webinocrm_company_phone', '');
        $this->company_email = get_option('webinocrm_company_email', get_option('admin_email'));
        
        // Add Persian font
        $this->AddFont('Vazir', '', 'Vazirmatn.ttf', true);
        $this->SetFont('Vazir', '', 12);
        $this->SetRTL(true);
    }
    
    /**
     * Set RTL support
     */
    public function SetRTL($rtl = true) {
        $this->rtl = $rtl;
    }
    
    /**
     * Override Cell for RTL
     */
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        if ($this->rtl && $align == '') {
            $align = 'R';
        }
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }
    
    /**
     * Header
     */
    public function Header() {
        // Logo
        if ($this->logo_path && file_exists($this->logo_path)) {
            $this->Image($this->logo_path, 10, 6, 30);
        }
        
        // Company info
        $this->SetFont('Vazir', '', 14);
        $this->SetXY(160, 10);
        $this->Cell(40, 10, $this->company_name, 0, 1, 'R');
        
        $this->SetFont('Vazir', '', 9);
        $this->SetX(120);
        $this->Cell(80, 5, $this->company_phone, 0, 1, 'R');
        $this->SetX(120);
        $this->Cell(80, 5, $this->company_email, 0, 1, 'R');
        
        // Line - Use white label primary color if available
        $this->SetLineWidth(0.5);
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $rgb = $this->hex_to_rgb($primary_color);
            $this->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->SetDrawColor(132, 90, 223);
        }
        $this->Line(10, 35, 200, 35);
        
        $this->Ln(15);
    }
    
    /**
     * Footer
     */
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Vazir', '', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'صفحه ' . $this->PageNo(), 0, 0, 'C');
    }
    
    /**
     * Generate Contract PDF
     */
    public function generate_contract_pdf($contract_id) {
        $contract = get_post($contract_id);
        if (!$contract) {
            return false;
        }
        
        // Get contract meta
        $customer_id = get_post_meta($contract_id, '_customer_id', true);
        $customer = get_user_by('id', $customer_id);
        $contract_number = get_post_meta($contract_id, '_contract_number', true);
        $start_date = get_post_meta($contract_id, '_contract_start_date', true);
        $total_amount = get_post_meta($contract_id, '_total_amount', true);
        $installments = get_post_meta($contract_id, '_installments', true);
        
        $this->AddPage();
        
        // Title - Use white label primary color if available
        $this->SetFont('Vazir', '', 18);
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $rgb = $this->hex_to_rgb($primary_color);
            $this->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->SetTextColor(132, 90, 223);
        }
        $this->Cell(0, 15, 'قرارداد همکاری', 0, 1, 'C');
        
        // Contract Number
        $this->SetFont('Vazir', '', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'شماره قرارداد: ' . $contract_number, 0, 1, 'R');
        $this->Cell(0, 8, 'تاریخ: ' . ($start_date ? date_i18n('Y/m/d', strtotime($start_date)) : '---'), 0, 1, 'R');
        $this->Ln(5);
        
        // Customer Info - Use white label primary color if available
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $rgb = $this->hex_to_rgb($primary_color);
            $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->SetFillColor(132, 90, 223);
        }
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Vazir', '', 12);
        $this->Cell(0, 10, 'اطلاعات طرف قرارداد', 0, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Vazir', '', 11);
        $this->Cell(0, 8, 'نام: ' . ($customer ? $customer->display_name : '---'), 0, 1, 'R');
        $this->Cell(0, 8, 'ایمیل: ' . ($customer ? $customer->user_email : '---'), 0, 1, 'R');
        $this->Ln(5);
        
        // Contract Details - Use white label primary color if available
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $rgb = $this->hex_to_rgb($primary_color);
            $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->SetFillColor(132, 90, 223);
        }
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Vazir', '', 12);
        $this->Cell(0, 10, 'جزئیات قرارداد', 0, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Vazir', '', 11);
        $this->Cell(0, 8, 'عنوان: ' . $contract->post_title, 0, 1, 'R');
        $this->Cell(0, 8, 'مبلغ کل: ' . number_format($total_amount) . ' تومان', 0, 1, 'R');
        $this->Ln(5);
        
        // Installments Table - Use white label primary color if available
        if (!empty($installments) && is_array($installments)) {
            if (class_exists('WebinoCRM_White_Label')) {
                $primary_color = WebinoCRM_White_Label::get_primary_color();
                $rgb = $this->hex_to_rgb($primary_color);
                $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
            } else {
                $this->SetFillColor(132, 90, 223);
            }
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Vazir', '', 12);
            $this->Cell(0, 10, 'اقساط', 0, 1, 'R', true);
            
            $this->SetFillColor(240, 240, 240);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Vazir', '', 10);
            
            // Header
            $this->Cell(20, 8, 'ردیف', 1, 0, 'C', true);
            $this->Cell(50, 8, 'مبلغ (تومان)', 1, 0, 'C', true);
            $this->Cell(40, 8, 'تاریخ سررسید', 1, 0, 'C', true);
            $this->Cell(30, 8, 'وضعیت', 1, 1, 'C', true);
            
            // Rows
            $this->SetFillColor(255, 255, 255);
            $counter = 1;
            foreach ($installments as $inst) {
                $this->Cell(20, 7, $counter++, 1, 0, 'C');
                $this->Cell(50, 7, number_format($inst['amount'] ?? 0), 1, 0, 'C');
                $this->Cell(40, 7, $inst['due_date'] ?? '---', 1, 0, 'C');
                $status_text = ($inst['status'] ?? 'pending') === 'paid' ? 'پرداخت شده' : 'در انتظار';
                $this->Cell(30, 7, $status_text, 1, 1, 'C');
            }
        }
        
        // Signature Section
        $this->Ln(20);
        $this->SetFont('Vazir', '', 10);
        $this->Cell(95, 8, 'امضا طرف قرارداد', 'T', 0, 'C');
        $this->Cell(95, 8, 'امضا شرکت', 'T', 1, 'C');
        
        // Output
        $filename = 'contract-' . $contract_number . '.pdf';
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['path'] . '/' . $filename;
        
        $this->Output('F', $pdf_path);
        
        return [
            'path' => $pdf_path,
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ];
    }
    
    /**
     * Generate Invoice PDF
     */
    public function generate_invoice_pdf($invoice_id) {
        $invoice = get_post($invoice_id);
        if (!$invoice) {
            return false;
        }
        
        // Get invoice meta
        $customer_id = $invoice->post_author;
        $customer = get_user_by('id', $customer_id);
        $invoice_number = get_post_meta($invoice_id, '_pro_invoice_number', true);
        $issue_date = get_post_meta($invoice_id, '_issue_date', true);
        $items = get_post_meta($invoice_id, '_invoice_items', true);
        
        $this->AddPage();
        
        // Title - Use white label primary color if available
        $this->SetFont('Vazir', '', 18);
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $rgb = $this->hex_to_rgb($primary_color);
            $this->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->SetTextColor(132, 90, 223);
        }
        $this->Cell(0, 15, 'پیش‌فاکتور', 0, 1, 'C');
        
        // Invoice Number
        $this->SetFont('Vazir', '', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'شماره: ' . $invoice_number, 0, 1, 'R');
        $this->Cell(0, 8, 'تاریخ صدور: ' . ($issue_date ? date_i18n('Y/m/d', strtotime($issue_date)) : date_i18n('Y/m/d')), 0, 1, 'R');
        $this->Ln(5);
        
        // Customer Info - Use white label primary color if available
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $rgb = $this->hex_to_rgb($primary_color);
            $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->SetFillColor(132, 90, 223);
        }
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'مشتری', 0, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Vazir', '', 11);
        $this->Cell(0, 8, 'نام: ' . ($customer ? $customer->display_name : '---'), 0, 1, 'R');
        $this->Cell(0, 8, 'ایمیل: ' . ($customer ? $customer->user_email : '---'), 0, 1, 'R');
        $this->Ln(5);
        
        // Items Table - Use white label primary color if available
        if (!empty($items) && is_array($items)) {
            if (class_exists('WebinoCRM_White_Label')) {
                $primary_color = WebinoCRM_White_Label::get_primary_color();
                $rgb = $this->hex_to_rgb($primary_color);
                $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
            } else {
                $this->SetFillColor(132, 90, 223);
            }
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Vazir', '', 11);
            $this->Cell(0, 10, 'اقلام', 0, 1, 'R', true);
            
            $this->SetFillColor(240, 240, 240);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Vazir', '', 9);
            
            // Header
            $this->Cell(10, 8, '#', 1, 0, 'C', true);
            $this->Cell(60, 8, 'عنوان خدمت', 1, 0, 'C', true);
            $this->Cell(40, 8, 'قیمت', 1, 0, 'C', true);
            $this->Cell(30, 8, 'تخفیف', 1, 0, 'C', true);
            $this->Cell(40, 8, 'مبلغ نهایی', 1, 1, 'C', true);
            
            // Items
            $this->SetFillColor(255, 255, 255);
            $counter = 1;
            $total = 0;
            
            foreach ($items as $item) {
                $price = (float)($item['price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                $final = $price - $discount;
                $total += $final;
                
                $this->Cell(10, 7, $counter++, 1, 0, 'C');
                $this->Cell(60, 7, $item['title'] ?? '', 1, 0, 'R');
                $this->Cell(40, 7, number_format($price), 1, 0, 'C');
                $this->Cell(30, 7, number_format($discount), 1, 0, 'C');
                $this->Cell(40, 7, number_format($final), 1, 1, 'C');
            }
            
            // Total - Use white label primary color if available
            if (class_exists('WebinoCRM_White_Label')) {
                $primary_color = WebinoCRM_White_Label::get_primary_color();
                $rgb = $this->hex_to_rgb($primary_color);
                $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
            } else {
                $this->SetFillColor(132, 90, 223);
            }
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Vazir', '', 11);
            $this->Cell(140, 8, 'جمع کل', 1, 0, 'R', true);
            $this->Cell(40, 8, number_format($total) . ' تومان', 1, 1, 'C', true);
        }
        
        // Footer note
        $this->Ln(10);
        $this->SetFont('Vazir', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->MultiCell(0, 5, 'این پیش‌فاکتور صرفاً جهت اطلاع بوده و به منزله صدور فاکتور رسمی نیست.', 0, 'R');
        
        // Output
        $filename = 'invoice-' . $invoice_number . '.pdf';
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['path'] . '/' . $filename;
        
        $this->Output('F', $pdf_path);
        
        return [
            'path' => $pdf_path,
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ];
    }
    
    /**
     * Convert hex color to RGB array
     */
    private function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }
}

