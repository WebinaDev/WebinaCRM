<?php
/**
 * Email Handler - Send Contracts, Invoices, Notifications
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Email_Handler {

    /**
     * Send Contract Email
     */
    public static function send_contract_email($contract_id, $customer_email = null) {
        $contract = get_post($contract_id);
        if (!$contract) {
            return false;
        }

        // Get customer
        $customer_id = get_post_meta($contract_id, '_customer_id', true);
        if (!$customer_email && $customer_id) {
            $customer = get_user_by('id', $customer_id);
            $customer_email = $customer ? $customer->user_email : null;
        }

        if (!$customer_email) {
            return false;
        }

        // Generate PDF
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        $pdf = new WebinoCRM_PDF_Generator();
        $pdf_result = $pdf->generate_contract_pdf($contract_id);

        if (!$pdf_result) {
            return false;
        }

        // Email content - Use white label company name if available
        $company_name = get_option('blogname');
        if (class_exists('WebinoCRM_White_Label')) {
            $company_name = WebinoCRM_White_Label::get_company_name();
        }
        $subject = 'قرارداد همکاری - ' . $company_name;
        $message = self::get_contract_email_template($contract, $pdf_result['url'], $company_name);

        // Send email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // Attach PDF
        $attachments = [$pdf_result['path']];

        $sent = wp_mail($customer_email, $subject, $message, $headers, $attachments);

        return $sent;
    }

    /**
     * Send Invoice Email
     */
    public static function send_invoice_email($invoice_id, $customer_email = null) {
        $invoice = get_post($invoice_id);
        if (!$invoice) {
            return false;
        }

        // Get customer
        $customer_id = $invoice->post_author;
        if (!$customer_email) {
            $customer = get_user_by('id', $customer_id);
            $customer_email = $customer ? $customer->user_email : null;
        }

        if (!$customer_email) {
            return false;
        }

        // Generate PDF
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        $pdf = new WebinoCRM_PDF_Generator();
        $pdf_result = $pdf->generate_invoice_pdf($invoice_id);

        if (!$pdf_result) {
            return false;
        }

        // Email content - Use white label company name if available
        $company_name = get_option('blogname');
        if (class_exists('WebinoCRM_White_Label')) {
            $company_name = WebinoCRM_White_Label::get_company_name();
        }
        $invoice_number = get_post_meta($invoice_id, '_pro_invoice_number', true);
        $subject = 'پیش‌فاکتور شماره ' . $invoice_number . ' - ' . $company_name;
        $message = self::get_invoice_email_template($invoice, $pdf_result['url'], $invoice_number, $company_name, $customer_id);

        // Send email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $attachments = [$pdf_result['path']];

        $sent = wp_mail($customer_email, $subject, $message, $headers, $attachments);

        return $sent;
    }

    /**
     * Contract Email Template
     */
    private static function get_contract_email_template($contract, $pdf_url, $company_name = null, $user_id = null) {
        if (!$company_name) {
            $company_name = get_option('blogname');
        }
        
        // Get user language preference
        $locale = get_locale();
        if ($user_id) {
            $user_lang = get_user_meta($user_id, 'webino_language', true);
            if ($user_lang === 'en') {
                $locale = 'en_US';
            } elseif ($user_lang === 'fa') {
                $locale = 'fa_IR';
            }
        }
        
        $is_rtl = ($locale === 'fa_IR');
        $direction = $is_rtl ? 'rtl' : 'ltr';
        $lang = substr($locale, 0, 2);
        
        // Get white label colors and logo
        $primary_color = '#845adf';
        $logo_url = '';
        $header_color = '';
        
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $logo_url = WebinoCRM_White_Label::get_email_logo();
            $header_color = get_option('webinocrm_wl_email_header_color', $primary_color);
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="<?php echo esc_attr($direction); ?>" lang="<?php echo esc_attr($lang); ?>">
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Tahoma, Arial, sans-serif; direction: <?php echo esc_attr($direction); ?>; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; background: #f5f5f5; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <?php if ($logo_url): ?>
                <div style="text-align: center; padding: 20px 30px 10px;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>" style="max-width: 150px; height: auto;">
                </div>
                <?php endif; ?>
                <div style="background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> 100%); color: #fff; padding: 30px; text-align: center;">
                    <h1 style="margin: 0; font-size: 24px;"><?php echo $is_rtl ? 'قرارداد همکاری' : 'Contract Agreement'; ?></h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;"><?php echo esc_html($company_name); ?></p>
                </div>
                
                <div style="padding: 30px;">
                    <p style="font-size: 16px; line-height: 1.8; color: #333;">
                        با سلام و احترام،
                    </p>
                    
                    <p style="font-size: 14px; line-height: 1.8; color: #555;">
                        قرارداد همکاری شما با عنوان <strong><?php echo esc_html($contract->post_title); ?></strong> آماده شده است.
                    </p>
                    
                    <p style="font-size: 14px; line-height: 1.8; color: #555;">
                        لطفاً فایل پیوست را مطالعه فرمایید و در صورت نیاز به توضیحات بیشتر با ما تماس بگیرید.
                    </p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url($pdf_url); ?>" 
                           style="display: inline-block; background: <?php echo esc_attr($primary_color); ?>; color: #fff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                            دانلود فایل PDF قرارداد
                        </a>
                    </div>
                    
                    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                    
                    <p style="font-size: 12px; color: #999; text-align: center;">
                        این ایمیل به صورت خودکار از سیستم <?php echo esc_html($company_name); ?> ارسال شده است.
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Invoice Email Template
     */
    private static function get_invoice_email_template($invoice, $pdf_url, $invoice_number, $company_name = null, $user_id = null) {
        if (!$company_name) {
            $company_name = get_option('blogname');
        }
        
        // Get user language preference
        $locale = get_locale();
        if ($user_id) {
            $user_lang = get_user_meta($user_id, 'webino_language', true);
            if ($user_lang === 'en') {
                $locale = 'en_US';
            } elseif ($user_lang === 'fa') {
                $locale = 'fa_IR';
            }
        }
        
        $is_rtl = ($locale === 'fa_IR');
        $direction = $is_rtl ? 'rtl' : 'ltr';
        $lang = substr($locale, 0, 2);
        
        // Get white label colors and logo
        $primary_color = '#845adf';
        $logo_url = '';
        $header_color = '';
        
        if (class_exists('WebinoCRM_White_Label')) {
            $primary_color = WebinoCRM_White_Label::get_primary_color();
            $logo_url = WebinoCRM_White_Label::get_email_logo();
            $header_color = get_option('webinocrm_wl_email_header_color', $primary_color);
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="<?php echo esc_attr($direction); ?>" lang="<?php echo esc_attr($lang); ?>">
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Tahoma, Arial, sans-serif; direction: <?php echo esc_attr($direction); ?>; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; background: #f5f5f5; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <?php if ($logo_url): ?>
                <div style="text-align: center; padding: 20px 30px 10px;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>" style="max-width: 150px; height: auto;">
                </div>
                <?php endif; ?>
                <div style="background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, <?php echo esc_attr(self::darken_color($primary_color, 10)); ?> 100%); color: #fff; padding: 30px; text-align: center;">
                    <h1 style="margin: 0; font-size: 24px;"><?php echo $is_rtl ? 'پیش‌فاکتور' : 'Invoice'; ?></h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">شماره: <?php echo esc_html($invoice_number); ?></p>
                </div>
                
                <div style="padding: 30px;">
                    <p style="font-size: 16px; line-height: 1.8; color: #333;">
                        با سلام،
                    </p>
                    
                    <p style="font-size: 14px; line-height: 1.8; color: #555;">
                        پیش‌فاکتور خدمات شما آماده شده است. لطفاً فایل پیوست را مشاهده فرمایید.
                    </p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url($pdf_url); ?>" 
                           style="display: inline-block; background: <?php echo esc_attr($primary_color); ?>; color: #fff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                            دانلود پیش‌فاکتور PDF
                        </a>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-right: 4px solid <?php echo esc_attr($primary_color); ?>; margin: 20px 0;">
                        <p style="margin: 0; font-size: 13px; color: #555;">
                            <strong>توجه:</strong> این پیش‌فاکتور صرفاً جهت اطلاع بوده و به منزله صدور فاکتور رسمی نیست.
                        </p>
                    </div>
                    
                    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                    
                    <p style="font-size: 12px; color: #999; text-align: center;">
                        <?php echo esc_html($company_name); ?> | این ایمیل به صورت خودکار ارسال شده است
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
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
}

