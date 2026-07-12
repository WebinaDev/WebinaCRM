<?php
/**
 * WebinoCRM User AJAX Handler
 *
 * @deprecated 2.x Dashboard SPA uses REST /webinocrm/v1/customers. Kept for legacy WP admin pages.
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_User_Ajax_Handler {

	use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        add_action('wp_ajax_webino_manage_user', [$this, 'ajax_manage_user']);
        add_action('wp_ajax_webino_delete_user', [$this, 'ajax_delete_user']);
        add_action('wp_ajax_webino_update_my_profile', [$this, 'ajax_update_my_profile']);
        add_action('wp_ajax_webino_search_users', [$this, 'ajax_search_users']);
        add_action('wp_ajax_webinocrm_get_users', [$this, 'ajax_get_users']);
        add_action('wp_ajax_webinocrm_send_bale_message', [$this, 'ajax_send_bale_message']);
        add_action('wp_ajax_webinocrm_send_bale_bulk_message', [$this, 'ajax_send_bale_bulk_message']);
        add_action('wp_ajax_webinocrm_manage_org_position', [$this, 'ajax_manage_org_position']);
        add_action('wp_ajax_webinocrm_delete_org_position', [$this, 'ajax_delete_org_position']);
    }

    private function is_staff_role( $role_slug ) {
        return in_array( $role_slug, [ 'system_manager', 'finance_manager', 'team_member', 'administrator' ], true );
    }

    /**
     * Get list of users for React dashboard (JSON).
     */
    public function ajax_get_users() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'list' ) );
    }

    public function ajax_manage_user() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'save' ) );
    }

    public function ajax_manage_org_position() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'org_position_save' ) );
    }

    public function ajax_delete_org_position() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'org_position_delete' ) );
    }

    public function ajax_delete_user() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'delete' ) );
    }

    public function ajax_update_my_profile() {
        $this->emit_service( array( 'WebinoCRM_Profile_Service', 'update' ) );
    }

    public function ajax_search_users() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $args = [
            'orderby' => 'display_name', 
            'order' => 'ASC',
            'search' => '*' . esc_attr($search_query) . '*',
            'search_columns' => ['user_login', 'user_email', 'user_nicename', 'display_name'],
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'webino_mobile_phone',
                    'value' => $search_query,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'webino_national_id',
                    'value' => $search_query,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'first_name',
                    'value' => $search_query,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'last_name',
                    'value' => $search_query,
                    'compare' => 'LIKE'
                ]
            ]
        ];
        
        if (empty($search_query)) {
            unset($args['search']);
            unset($args['meta_query']);
        }

        $all_users = get_users($args);
        $output_html = '';

        if (empty($all_users)) {
            $output_html = '<tr><td colspan="5">هیچ کاربری با این مشخصات یافت نشد.</td></tr>';
        } else {
            foreach ($all_users as $user) {
                $edit_url = add_query_arg(['view' => 'customers', 'action' => 'edit', 'user_id' => $user->ID]);
                $role_name = !empty($user->roles) ? esc_html(wp_roles()->roles[$user->roles[0]]['name']) : '---';
                $registered_date = jdate('Y/m/d', strtotime($user->user_registered));
                
                $output_html .= '<tr data-user-row-id="'. esc_attr($user->ID) .'">';
                $output_html .= '<td>' . get_avatar($user->ID, 32) . ' ' . esc_html($user->display_name) . '</td>';
                $output_html .= '<td>' . esc_html($user->user_email) . '</td>';
                $output_html .= '<td>' . $role_name . '</td>';
                $output_html .= '<td>' . $registered_date . '</td>';
                $output_html .= '<td>';
                $output_html .= '<a href="' . esc_url($edit_url) . '" class="webino-button webino-button-sm">ویرایش</a> ';
                $output_html .= '<button class="webino-button webino-button-sm send-sms-btn" data-user-id="' . esc_attr($user->ID) . '" data-user-name="' . esc_attr($user->display_name) . '"><i class="ri-message-3-line"></i></button>';
                if ( get_current_user_id() != $user->ID && $user->ID != 1 ) {
                    $output_html .= ' <button class="webino-button webino-button-sm delete-user-btn" data-user-id="'. esc_attr($user->ID) .'" data-nonce="'. wp_create_nonce('webino_delete_user_' . $user->ID) .'" style="background-color: #dc3545 !important;">حذف</button>';
                }
                $output_html .= '</td>';
                $output_html .= '</tr>';
            }
        }

        wp_send_json_success(['html' => $output_html]);
    }

    public function ajax_send_bale_message() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'bale' ) );
    }

    public function ajax_send_bale_bulk_message() {
        $this->emit_service( array( 'WebinoCRM_Customers_Service', 'bale_bulk' ) );
    }
}