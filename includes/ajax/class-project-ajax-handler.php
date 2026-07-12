<?php
/**
 * WebinoCRM Project & Contract AJAX Handler
 *
 * @deprecated 2.x REST API is the source of truth for the dashboard SPA.
 * @package WebinoCRM
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Project_Ajax_Handler {
    use WebinoCRM_Ajax_Service_Delegate;


    public function __construct() {
        add_action('wp_ajax_webino_manage_project', [$this, 'ajax_manage_project']);
        add_action('wp_ajax_webino_delete_project', [$this, 'ajax_delete_project']);
        add_action('wp_ajax_webino_manage_contract', [$this, 'ajax_manage_contract']);
        add_action('wp_ajax_webino_cancel_contract', [$this, 'ajax_cancel_contract']);
        add_action('wp_ajax_webino_add_project_to_contract', [$this, 'ajax_add_project_to_contract']);
        add_action('wp_ajax_webino_add_services_from_product', [$this, 'ajax_add_services_from_product']);
        add_action('wp_ajax_webino_get_projects_for_customer', [$this, 'ajax_get_projects_for_customer']);
        add_action('wp_ajax_webino_delete_contract', [$this, 'ajax_delete_contract']);
        add_action('wp_ajax_webinocrm_get_contracts', [$this, 'ajax_get_contracts']);
        add_action('wp_ajax_webinocrm_get_contract', [$this, 'ajax_get_contract']);
        add_action('wp_ajax_webinocrm_get_projects', [$this, 'ajax_get_projects']);
        add_action('wp_ajax_webinocrm_get_project', [$this, 'ajax_get_project']);
        add_action('wp_ajax_webinocrm_get_project_templates', [$this, 'ajax_get_project_templates']);
        add_action('wp_ajax_webinocrm_get_project_assignees', [ $this, 'ajax_get_project_assignees' ]);
        add_action('wp_ajax_webinocrm_get_product_projects_preview', [ $this, 'ajax_get_product_projects_preview' ]);
        add_action('wp_ajax_webinocrm_get_assignable_employees', [ $this, 'ajax_get_assignable_employees' ]);
    }

    /**
     * Returns project titles that would be created from a WC product (Simple=1, Grouped=N children).
     */
    public function ajax_get_product_projects_preview() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Project_Service', 'product_preview' ) );
    }

    
    public function ajax_get_project_assignees() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Project_Service', 'assignees' ) );
    }

    
    public function ajax_get_assignable_employees() {
        $this->load_dependencies();
        if ( ! check_ajax_referer( 'webinocrm-ajax-nonce', 'security', false ) ) {
            wp_send_json_error( [ 'message' => __( 'درخواست نامعتبر.', 'webinocrm' ) ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
        }
        $department_id = isset( $_POST['department_id'] ) ? absint( $_POST['department_id'] ) : 0;
        $role_filter   = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
        $sort_by_load  = isset( $_POST['sort_by_load'] ) && $_POST['sort_by_load'] === '1';

        $args = [
            'role__in' => [ 'system_manager', 'team_member', 'administrator' ],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ];
        if ( $role_filter && in_array( $role_filter, [ 'system_manager', 'team_member', 'administrator' ], true ) ) {
            $args['role'] = $role_filter;
            unset( $args['role__in'] );
        }
        if ( $department_id > 0 ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'organizational_position',
                    'field'    => 'term_id',
                    'terms'    => $department_id,
                ],
            ];
        }
        $users = get_users( $args );

        if ( $sort_by_load && ! empty( $users ) ) {
            $counts = [];
            foreach ( $users as $u ) {
                $projects = get_posts( [
                    'post_type'      => 'project',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        [ 'key' => '_assigned_to', 'value' => $u->ID, 'compare' => '=' ],
                    ],
                ] );
                $tasks = get_posts( [
                    'post_type'      => 'task',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [ [ 'key' => '_assigned_to', 'value' => $u->ID, 'compare' => '=' ] ],
                ] );
                $counts[ $u->ID ] = count( $projects ) + count( $tasks );
            }
            usort( $users, function ( $a, $b ) use ( $counts ) {
                $ca = $counts[ $a->ID ] ?? 0;
                $cb = $counts[ $b->ID ] ?? 0;
                return $ca <=> $cb;
            } );
        }

        $items = array_map( function ( $u ) {
            return [ 'id' => $u->ID, 'display_name' => $u->display_name ];
        }, $users );
        wp_send_json_success( [ 'users' => $items ] );
    }

    /**
     * Get list of contracts for React dashboard (JSON).
     */
    public function ajax_get_contracts() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Contract_Service', 'list' ) );
    }

    public function ajax_get_contract() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Contract_Service', 'get' ) );
    }

    public function ajax_get_projects() {
        $this->load_dependencies();
        if ( ! WebinoCRM_Service_Base::verify_ajax_nonce() ) {
            wp_send_json_error( array( 'message' => 'خطای امنیتی.' ) );
        }
        WebinoCRM_Service_Base::emit_json( WebinoCRM_Project_Service::list( WebinoCRM_Service_Base::post_params() ) );
    }

    public function ajax_get_project() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Project_Service', 'get' ) );
    }

    public function ajax_manage_project() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Project_Service', 'create_or_update' ) );
    }

    public function ajax_delete_project() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Project_Service', 'delete' ) );
    }

    public function ajax_manage_contract() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Contract_Service', 'create_or_update' ) );
    }

    public function ajax_delete_contract() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Contract_Service', 'delete' ) );
    }

    public function ajax_cancel_contract() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Contract_Service', 'cancel' ) );
    }

    public function ajax_add_project_to_contract() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Contract_Service', 'add_project' ) );
    }

    public function ajax_add_services_from_product() {
        $this->load_dependencies();
        ob_start();

        if (!check_ajax_referer('webinocrm-ajax-nonce', 'security', false)) {
            $this->clean_buffer_and_send_error(WebinoCRM_Error_Codes::PRJ_ERR_NONCE_FAILED);
        }
        if (!current_user_can('manage_options')) {
            $this->clean_buffer_and_send_error(WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED);
        }

        if (!function_exists('wc_get_product')) {
            $this->clean_buffer_and_send_error(WebinoCRM_Error_Codes::PRJ_ERR_PRODUCT_WOC_INACTIVE);
        }

        $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (empty($contract_id) || empty($product_id)) {
            $this->clean_buffer_and_send_error(WebinoCRM_Error_Codes::PRJ_ERR_MISSING_PRODUCT_DATA, ['contract_id' => $contract_id, 'product_id' => $product_id]);
        }

        $contract = get_post($contract_id);
        $product = wc_get_product($product_id);

        if (!$contract || !$product) {
            $this->clean_buffer_and_send_error(WebinoCRM_Error_Codes::PRJ_ERR_PRODUCT_NOT_FOUND, ['contract_found' => (bool)$contract, 'product_found' => (bool)$product]);
        }
        
        $created_projects_count = 0;
        $child_products = $product->is_type('grouped') ? $product->get_children() : [$product->get_id()];
        
        foreach($child_products as $child_product_id) {
            $child_product = wc_get_product($child_product_id);
            if (!$child_product) continue;
            
            $project_id = wp_insert_post(['post_title' => $child_product->get_name(), 'post_author' => $contract->post_author, 'post_status' => 'publish', 'post_type' => 'project'], true);
            
            if (!is_wp_error($project_id)) {
                update_post_meta($project_id, '_contract_id', $contract_id);
                $active_status = get_term_by('slug', 'active', 'project_status');
                if ($active_status) {
                    wp_set_object_terms($project_id, $active_status->term_id, 'project_status');
                }
                $created_projects_count++;
            }
        }

        if($created_projects_count > 0) {
            $message = $created_projects_count . ' پروژه با موفقیت از محصول ایجاد و به این قرارداد متصل شد.';
            WebinoCRM_Logger::add('موفقیت در افزودن خدمات', ['content' => $message], 'log');
            ob_end_clean();
            wp_send_json_success(['message' => $message, 'reload' => true]);
        } else {
            $this->clean_buffer_and_send_error(WebinoCRM_Error_Codes::PRJ_ERR_PRODUCT_PROJECT_FAILED, ['product_id' => $product_id, 'contract_id' => $contract_id]);
        }
    }

    public function ajax_get_projects_for_customer() {
        $this->emit_service( array( 'WebinoCRM_Invoices_Service', 'customer_projects' ) );
    }

    public function ajax_get_project_templates() {
        $this->load_dependencies();
        $this->verify_ajax_or_die();
        $this->emit_service( array( 'WebinoCRM_Project_Service', 'templates' ) );
    }

    private function load_dependencies() {
        if (defined('WEBINOCRM_PLUGIN_DIR')) {
            require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-error-codes.php';
            require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-logger.php';
            require_once WEBINOCRM_PLUGIN_DIR . 'includes/webino-functions.php';
            if (file_exists(WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php')) {
                 require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/jdf.php';
            }
        }
    }

    private function clean_buffer_and_send_error($code, $extra_data = []) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (class_exists('WebinoCRM_Error_Codes')) {
            WebinoCRM_Error_Codes::send_error($code, $extra_data);
        } else {
            wp_send_json_error(['message' => 'یک خطای مهم رخ داد. کلاس خطا یافت نشد.'], 500);
        }
    }
}
