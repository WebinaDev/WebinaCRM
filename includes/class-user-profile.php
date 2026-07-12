<?php
/**
 * WebinoCRM User Profile Handler
 *
 * This class adds custom fields to user profiles, including a profile picture.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WebinoCRM_User_Profile {

    /**
     * Holds the definitions for all custom profile fields.
     * @var array
     */
    private $profile_fields = [];

    /**
     * Constructor. Hooks into WordPress actions.
     */
    public function __construct() {
        $this->define_profile_fields();
        add_action( 'show_user_profile', [ $this, 'render_custom_profile_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'render_custom_profile_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_custom_profile_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_custom_profile_fields' ] );
        
        // Make the form can handle file uploads
        add_action( 'user_edit_form_tag', function(){ echo 'enctype="multipart/form-data"'; });
        
        // Enqueue scripts for the admin profile page
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Enqueues scripts and styles for the admin user profile pages.
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on user profile pages
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }

        // ** NEW **: Enqueue Persian Datepicker assets from CDN
        wp_enqueue_style('persian-datepicker-css', 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css', [], '1.2.0');
        wp_enqueue_script('persian-date', 'https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js', [], '1.1.0', true);
        wp_enqueue_script('persian-datepicker-js', 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', ['jquery', 'persian-date'], '1.2.0', true);

        // Enqueue the main script which now handles initialization globally
        wp_enqueue_script('webino-admin-profile-scripts', WEBINOCRM_PLUGIN_URL . 'assets/js/webinocrm-scripts.js', ['jquery', 'persian-datepicker-js'], WEBINOCRM_VERSION, true);
    }

    /**
     * Defines all the custom fields for the user profile.
     */
    private function define_profile_fields() {
        $this->profile_fields = [
            'identity_info' => [ 'title' => 'اطلاعات هویتی', 'fields' => [
                'father_name' => ['label' => 'نام پدر', 'type' => 'text'],
                'birth_date' => ['label' => 'تاریخ تولد', 'type' => 'text'],
                'national_id' => ['label' => 'کد ملی', 'type' => 'text'],
                'id_number' => ['label' => 'شماره شناسنامه', 'type' => 'text'],
                'id_issue_place' => ['label' => 'محل صدور', 'type' => 'text'],
                'marital_status' => ['label' => 'وضعیت تأهل', 'type' => 'select', 'options' => ['' => 'انتخاب کنید', 'single' => 'مجرد', 'married' => 'متاهل']],
                'children_count' => ['label' => 'تعداد فرزندان', 'type' => 'number'],
            ]],
            'contact_info' => [ 'title' => 'اطلاعات تماس و ارتباطی', 'fields' => [
                'mobile_phone' => ['label' => 'شماره موبایل', 'type' => 'tel'],
                'landline_phone' => ['label' => 'تلفن ثابت', 'type' => 'tel'],
                'address' => ['label' => 'آدرس محل سکونت', 'type' => 'textarea', 'full_width' => true],
                'emergency_contact_1_name' => ['label' => 'نام مخاطب اضطراری ۱', 'type' => 'text'],
                'emergency_contact_1_phone' => ['label' => 'شماره مخاطب اضطراری ۱', 'type' => 'tel'],
                'emergency_contact_2_name' => ['label' => 'نام مخاطب اضطراری ۲', 'type' => 'text'],
                'emergency_contact_2_phone' => ['label' => 'شماره مخاطب اضطراری ۲', 'type' => 'tel'],
            ]],
            'job_info' => [ 'title' => 'اطلاعات شغلی / سازمانی', 'fields' => [
                'organizational_position' => ['label' => 'جایگاه سازمانی (دپارتمان/عنوان)', 'type' => 'position_select'],
                'department_manager_dept_ids' => ['label' => 'دپارتمان‌های مدیریتی (برای ارجاع پروژه)', 'type' => 'department_manager_multi'],
                'personnel_code' => ['label' => 'کد پرسنلی', 'type' => 'text'],
                'direct_manager' => ['label' => 'مدیر مستقیم', 'type' => 'text'],
                'hire_date' => ['label' => 'تاریخ استخدام', 'type' => 'text'],
                'contract_type' => ['label' => 'نوع قرارداد', 'type' => 'select', 'options' => ['' => 'انتخاب کنید', 'permanent' => 'رسمی', 'contractual' => 'پیمانی', 'project' => 'پروژه‌ای']],
                'job_status' => ['label' => 'وضعیت شغلی', 'type' => 'select', 'options' => ['' => 'انتخاب کنید', 'active' => 'فعال', 'on_leave' => 'مرخصی', 'mission' => 'ماموریت']],
            ]],
            'financial_info' => [ 'title' => 'اطلاعات مالی و حقوقی', 'fields' => [
                'bank_account_number' => ['label' => 'شماره حساب', 'type' => 'text'], 'bank_name' => ['label' => 'نام بانک', 'type' => 'text'],
                'iban' => ['label' => 'شماره شبا', 'type' => 'text'], 'salary_details' => ['label' => 'حقوق و مزایا', 'type' => 'textarea'],
                'deductions' => ['label' => 'کسورات', 'type' => 'textarea'],
            ]],
            'insurance_legal_info' => [ 'title' => 'اطلاعات بیمه و قانونی', 'fields' => [
                'insurance_number' => ['label' => 'شماره بیمه', 'type' => 'text'], 'tax_file_number' => ['label' => 'شماره پرونده مالیاتی', 'type' => 'text'],
                'insurance_history' => ['label' => 'سوابق بیمه‌ای', 'type' => 'textarea'],
            ]],
            'professional_history' => [ 'title' => 'سوابق حرفه‌ای و آموزشی', 'fields' => [
                'education' => ['label' => 'تحصیلات', 'type' => 'textarea'], 'training_courses' => ['label' => 'دوره‌ها', 'type' => 'textarea'],
                'skills_certificates' => ['label' => 'مهارت‌ها و گواهینامه‌ها', 'type' => 'textarea'], 'previous_jobs' => ['label' => 'سوابق کاری قبلی', 'type' => 'textarea'],
            ]],
        ];
    }

    public function render_custom_profile_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) return;
        
        echo '<h2>اطلاعات تکمیلی WebinoCRM</h2>';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="webino_profile_picture">عکس پروفایل</label></th>
                <td>
                    <?php echo get_avatar($user->ID, 96); ?>
                    <input type="file" name="webino_profile_picture" id="webino_profile_picture" accept="image/*">
                    <p class="description">برای بهترین نمایش، از یک تصویر مربع استفاده کنید.</p>
                </td>
            </tr>
        </table>
        <?php

        foreach ($this->profile_fields as $section) {
            echo '<h3>' . esc_html($section['title']) . '</h3>';
            echo '<table class="form-table">';
            foreach ($section['fields'] as $field_key => $field) {
                $meta_key = 'webino_' . $field_key;
                $value = get_user_meta($user->ID, $meta_key, true);
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($field['label']); ?></label></th>
                    <td>
                        <?php
                        $field_class = ($field_key === 'birth_date' || $field_key === 'hire_date') ? 'webino-jalali-date-picker' : '';
                        $display_value = ($field_key === 'birth_date' || $field_key === 'hire_date') ? webino_gregorian_to_jalali($value) : $value;
                        
                        switch ($field['type']) {
                            case 'textarea':
                                echo '<textarea name="' . esc_attr($meta_key) . '" id="' . esc_attr($meta_key) . '" rows="5" cols="30" class="regular-text">' . esc_textarea($value) . '</textarea>';
                                break;
                            case 'select':
                                echo '<select name="' . esc_attr($meta_key) . '" id="' . esc_attr($meta_key) . '">';
                                foreach ($field['options'] as $opt_val => $opt_label) {
                                    echo '<option value="' . esc_attr($opt_val) . '" ' . selected($value, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                                }
                                echo '</select>';
                                break;
                           case 'department_manager_multi':
                                $dept_ids = (array) get_user_meta( $user->ID, '_department_manager_dept_ids', true );
                                $dept_ids = array_filter( array_map( 'intval', $dept_ids ) );
                                $departments = get_terms( [ 'taxonomy' => 'organizational_position', 'hide_empty' => false, 'parent' => 0 ] );
                                echo '<select name="_department_manager_dept_ids[]" id="_department_manager_dept_ids" multiple style="width:100%; min-height:80px;">';
                                echo '<option value="">—</option>';
                                if ( ! is_wp_error( $departments ) ) {
                                    foreach ( $departments as $d ) {
                                        echo '<option value="' . esc_attr( $d->term_id ) . '" ' . ( in_array( $d->term_id, $dept_ids, true ) ? 'selected' : '' ) . '>' . esc_html( $d->name ) . '</option>';
                                    }
                                }
                                echo '</select>';
                                echo '<p class="description">' . esc_html__( 'اگر این کاربر مدیر دپارتمان است، دپارتمان‌هایی که مدیریت می‌کند را انتخاب کنید. پروژه‌های آن دپارتمان را می‌تواند به کارمند ارجاع دهد.', 'webinocrm' ) . '</p>';
                                break;
                           case 'position_select':
                                $current_pos = wp_get_object_terms($user->ID, 'organizational_position', ['fields' => 'ids']);
                                $current_pos_id = !empty($current_pos) ? $current_pos[0] : 0;
                                wp_dropdown_categories([
                                    'taxonomy' => 'organizational_position',
                                    'name' => 'organizational_position',
                                    'selected' => $current_pos_id,
                                    'show_option_none' => '-- بدون جایگاه --',
                                    'hierarchical' => true,
                                    'show_count' => false,
                                    'hide_empty' => false,
                                ]);
                                break;
                            default:
                                echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($meta_key) . '" id="' . esc_attr($meta_key) . '" value="' . esc_attr($display_value) . '" class="regular-text ' . $field_class . '" />';
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            echo '</table>';
        }
    }
    
    public function save_custom_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) return;

        foreach ($this->profile_fields as $section) {
            foreach ($section['fields'] as $field_key => $field) {
                if ( in_array( $field['type'], [ 'position_select', 'department_manager_multi' ], true ) ) continue; // Handled separately
                $meta_key = 'webino_' . $field_key;
                if (isset($_POST[$meta_key])) {
                    $value = sanitize_text_field($_POST[$meta_key]);
                    if ($field_key === 'birth_date' || $field_key === 'hire_date') {
                        $value = webino_jalali_to_gregorian($value);
                    }
                    update_user_meta($user_id, $meta_key, $value);
                }
            }
        }

        // Save organizational position
        if (isset($_POST['organizational_position'])) {
            wp_set_object_terms($user_id, intval($_POST['organizational_position']), 'organizational_position', false);
        }

        // Save department manager dept ids
        if ( isset( $_POST['_department_manager_dept_ids'] ) && is_array( $_POST['_department_manager_dept_ids'] ) ) {
            $dept_ids = array_filter( array_map( 'intval', $_POST['_department_manager_dept_ids'] ) );
            update_user_meta( $user_id, '_department_manager_dept_ids', $dept_ids );
        }
        
        // Handle profile picture upload
        if (!empty($_FILES['webino_profile_picture']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('webino_profile_picture', 0);
            if (!is_wp_error($attachment_id)) {
                update_user_meta($user_id, 'webino_profile_picture_id', $attachment_id);
            }
        }
    }
}

// Add a filter to use our custom profile picture as the avatar
add_filter('get_avatar_url', function($url, $id_or_email, $args) {
    $user_id = 0;
    if (is_numeric($id_or_email)) {
        $user_id = (int) $id_or_email;
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        }
    } else {
        $user = get_user_by('email', $id_or_email);
        $user_id = $user ? $user->ID : 0;
    }

    if ($user_id === 0) return $url;

    $attachment_id = get_user_meta($user_id, 'webino_profile_picture_id', true);
    if ($attachment_id) {
        $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        if ($image_url) return $image_url;
    }

    return $url;
}, 10, 3);