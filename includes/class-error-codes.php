<?php
/**
 * WebinoCRM Error Codes and Messages
 * Centralized list for structured error handling and logging.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Error_Codes {

    // --- Contract Management Errors (PRJ_0xx) ---
    const PRJ_ERR_NONCE_FAILED             = 'PRJ_001';
    const PRJ_ERR_ACCESS_DENIED            = 'PRJ_002';
    const PRJ_ERR_MISSING_CONTRACT_DATA    = 'PRJ_003';
    const PRJ_ERR_INVALID_CUSTOMER         = 'PRJ_004';
    const PRJ_ERR_INVALID_START_DATE       = 'PRJ_005';
    const PRJ_ERR_CONTRACT_SAVE_FAILED     = 'PRJ_006';
    const PRJ_ERR_INVALID_INSTALLMENT_DATE = 'PRJ_007';
    const PRJ_ERR_CONTRACT_NOT_FOUND       = 'PRJ_008';
    const PRJ_ERR_CONTRACT_DELETE_FAILED   = 'PRJ_009';
    const PRJ_ERR_CANCEL_CONTRACT_FAILED   = 'PRJ_010';

    // --- Project Management Errors (PRJ_2xx) ---
    const PRJ_ERR_MISSING_PROJECT_DATA     = 'PRJ_201';
    const PRJ_ERR_CONTRACT_INVALID         = 'PRJ_202';
    const PRJ_ERR_PROJECT_SAVE_FAILED      = 'PRJ_203';
    const PRJ_ERR_PROJECT_NOT_FOUND        = 'PRJ_204';
    const PRJ_ERR_PROJECT_DELETE_FAILED    = 'PRJ_205';


    // --- Product/Service Automation Errors (PRJ_3xx) ---
    const PRJ_ERR_PRODUCT_WOC_INACTIVE     = 'PRJ_301';
    const PRJ_ERR_MISSING_PRODUCT_DATA     = 'PRJ_302';
    const PRJ_ERR_PRODUCT_NOT_FOUND        = 'PRJ_303';
    const PRJ_ERR_PRODUCT_PROJECT_FAILED   = 'PRJ_304';
    
    // --- Generic System Errors (SYS_...) ---
    const SYS_ERR_UNKNOWN                  = 'SYS_999';

    private static $messages = [
        self::PRJ_ERR_NONCE_FAILED             => 'خطای امنیتی: عدم تطابق توکن امنیتی (Nonce). درخواست رد شد.',
        self::PRJ_ERR_ACCESS_DENIED            => 'خطای دسترسی: شما اجازه انجام این عملیات را ندارید. (نقش کاربری ناکافی)',
        self::PRJ_ERR_MISSING_CONTRACT_DATA    => 'خطای اعتبار سنجی: اطلاعات ضروری (مشتری و تاریخ شروع) قرارداد ناقص است.',
        self::PRJ_ERR_INVALID_CUSTOMER         => 'خطای اعتبار سنجی: مشتری انتخاب شده معتبر نیست.',
        self::PRJ_ERR_INVALID_START_DATE       => 'خطای اعتبار سنجی: فرمت تاریخ شروع قرارداد نامعتبر است. (مثال: 1403/05/10)',
        self::PRJ_ERR_CONTRACT_SAVE_FAILED     => 'خطای ذخیره‌سازی: عملیات `wp_insert_post` یا `wp_update_post` قرارداد با خطا مواجه شد.',
        self::PRJ_ERR_INVALID_INSTALLMENT_DATE => 'خطای اعتبار سنجی: تاریخ یکی از اقساط نامعتبر یا در فرمت اشتباه وارد شده است.',
        self::PRJ_ERR_CONTRACT_NOT_FOUND       => 'خطای سیستمی: قرارداد مورد نظر برای عملیات یافت نشد.',
        self::PRJ_ERR_CONTRACT_DELETE_FAILED   => 'خطای سیستمی: عملیات حذف دائمی قرارداد توسط وردپرس با شکست مواجه شد.',
        self::PRJ_ERR_CANCEL_CONTRACT_FAILED   => 'خطای پردازش: لغو قرارداد با مشکل مواجه شد. شناسه قرارداد نامعتبر است.',

        self::PRJ_ERR_MISSING_PROJECT_DATA     => 'خطای اعتبار سنجی: عنوان پروژه یا اطلاعات قرارداد برای ایجاد پروژه الزامی است.',
        self::PRJ_ERR_CONTRACT_INVALID         => 'خطای اعتبار سنجی: قرارداد مرتبط انتخاب شده معتبر نیست.',
        self::PRJ_ERR_PROJECT_SAVE_FAILED      => 'خطای ذخیره‌سازی: عملیات `wp_insert_post` یا `wp_update_post` پروژه با خطا مواجه شد.',
        self::PRJ_ERR_PROJECT_NOT_FOUND        => 'خطای سیستمی: پروژه مورد نظر یافت نشد.',
        self::PRJ_ERR_PROJECT_DELETE_FAILED    => 'خطای سیستمی: حذف پروژه با شکست مواجه شد.',
        
        self::PRJ_ERR_PRODUCT_WOC_INACTIVE     => 'خطای وابستگی: افزونه ووکامرس فعال نیست. قابلیت افزودن خدمات از محصول غیرفعال است.',
        self::PRJ_ERR_MISSING_PRODUCT_DATA     => 'خطای داده: شناسه قرارداد یا شناسه محصول برای افزودن خدمات ناقص است.',
        self::PRJ_ERR_PRODUCT_NOT_FOUND        => 'خطای سیستمی: قرارداد یا محصول انتخابی برای افزودن خدمات یافت نشد.',
        self::PRJ_ERR_PRODUCT_PROJECT_FAILED   => 'خطای پردازشی: هیچ پروژه‌ای از محصول ایجاد نشد. محصول انتخابی ممکن است فاقد زیرمحصول باشد.',

        self::SYS_ERR_UNKNOWN                  => 'خطای ناشناخته سرور: یک خطای غیرمنتظره در سمت سرور رخ داد.',
    ];

    /**
     * Retrieves the detailed message for a given error code.
     *
     * @param string $code The WebinoCRM error code.
     * @return string The human-readable error message.
     */
    public static function get_message($code) {
        return self::$messages[$code] ?? self::$messages[self::SYS_ERR_UNKNOWN];
    }
    
    /**
     * Sends a structured JSON error response and logs the event.
     *
     * @param string $code The predefined error code.
     * @param array $extra_data Additional context for the log.
     * @param int $http_code The HTTP status code to return.
     */
    public static function send_error($code, $extra_data = [], $http_code = 200) {
        $message = self::get_message($code);
        
        // Log the detailed error using the existing WebinoCRM_Logger class
        if (class_exists('WebinoCRM_Logger')) {
            $log_content = "کد خطا: {$code} - پیام: {$message}";
            if (!empty($extra_data)) {
                $log_content .= " | جزئیات: " . print_r($extra_data, true);
            }
            // لاگ اجباری تمام خطاها
            WebinoCRM_Logger::add('خطای ساختاریافته', [
                'content' => $log_content,
                'error_code' => $code,
                'request_data' => $_POST,
                'user' => wp_get_current_user()->ID
            ], 'error');
        }

        wp_send_json_error([
            'message' => $message,
            'error_code' => $code,
        ], $http_code);
    }
}