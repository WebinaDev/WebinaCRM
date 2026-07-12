<?php
/**
 * Role-based Field Level Security
 * 
 * Controls field-level access based on user roles
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Field_Security {

    private $field_permissions = [];

    /**
     * Initialize Field Security
     */
    public function __construct() {
        add_action('init', [$this, 'load_field_permissions']);
        add_filter('webinocrm_can_view_field', [$this, 'can_view_field'], 10, 3);
        add_filter('webinocrm_can_edit_field', [$this, 'can_edit_field'], 10, 3);
        add_action('wp_ajax_webinocrm_update_field_permissions', [$this, 'ajax_update_field_permissions']);
        
        $this->setup_default_permissions();
    }

    /**
     * Setup default field permissions
     */
    private function setup_default_permissions() {
        $this->field_permissions = apply_filters('webinocrm_default_field_permissions', [
            // Lead fields
            'lead' => [
                '_lead_value' => [
                    'view' => ['administrator', 'system_manager', 'team_lead'],
                    'edit' => ['administrator', 'system_manager']
                ],
                '_lead_source' => [
                    'view' => ['administrator', 'system_manager', 'team_lead', 'team_member'],
                    'edit' => ['administrator', 'system_manager', 'team_lead']
                ],
                '_lead_phone' => [
                    'view' => ['administrator', 'system_manager', 'team_lead', 'team_member'],
                    'edit' => ['administrator', 'system_manager', 'team_lead', 'team_member']
                ],
                '_lead_email' => [
                    'view' => ['administrator', 'system_manager', 'team_lead', 'team_member'],
                    'edit' => ['administrator', 'system_manager', 'team_lead']
                ]
            ],
            
            // Project fields
            'project' => [
                '_project_budget' => [
                    'view' => ['administrator', 'system_manager', 'team_lead'],
                    'edit' => ['administrator', 'system_manager']
                ],
                '_project_cost' => [
                    'view' => ['administrator', 'system_manager'],
                    'edit' => ['administrator', 'system_manager']
                ],
                '_project_profit_margin' => [
                    'view' => ['administrator', 'system_manager'],
                    'edit' => ['administrator']
                ]
            ],
            
            // Contract fields
            'contract' => [
                '_contract_value' => [
                    'view' => ['administrator', 'system_manager', 'team_lead'],
                    'edit' => ['administrator', 'system_manager']
                ],
                '_contract_commission' => [
                    'view' => ['administrator', 'system_manager'],
                    'edit' => ['administrator']
                ],
                '_contract_payment_terms' => [
                    'view' => ['administrator', 'system_manager', 'team_lead'],
                    'edit' => ['administrator', 'system_manager']
                ]
            ],
            
            // Task fields
            'task' => [
                '_task_estimated_hours' => [
                    'view' => ['administrator', 'system_manager', 'team_lead', 'team_member'],
                    'edit' => ['administrator', 'system_manager', 'team_lead']
                ],
                '_task_actual_hours' => [
                    'view' => ['administrator', 'system_manager', 'team_lead'],
                    'edit' => ['administrator', 'system_manager', 'team_lead', 'team_member']
                ]
            ],
            
            // User fields
            'user' => [
                'salary' => [
                    'view' => ['administrator'],
                    'edit' => ['administrator']
                ],
                'commission_rate' => [
                    'view' => ['administrator', 'system_manager'],
                    'edit' => ['administrator']
                ],
                'bank_account' => [
                    'view' => ['administrator'],
                    'edit' => ['administrator']
                ]
            ]
        ]);
    }

    /**
     * Load field permissions from database
     */
    public function load_field_permissions() {
        $custom_permissions = get_option('webinocrm_field_permissions', []);
        
        if (!empty($custom_permissions)) {
            $this->field_permissions = array_merge($this->field_permissions, $custom_permissions);
        }
    }

    /**
     * Check if user can view field
     */
    public function can_view_field($can_view, $entity_type, $field_name, $user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Administrators can view everything
        if (user_can($user_id, 'administrator')) {
            return true;
        }

        // Check permissions
        if (isset($this->field_permissions[$entity_type][$field_name]['view'])) {
            $allowed_roles = $this->field_permissions[$entity_type][$field_name]['view'];
            return $this->user_has_any_role($user_id, $allowed_roles);
        }

        // Default: allow view
        return true;
    }

    /**
     * Check if user can edit field
     */
    public function can_edit_field($can_edit, $entity_type, $field_name, $user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Administrators can edit everything
        if (user_can($user_id, 'administrator')) {
            return true;
        }

        // Check permissions
        if (isset($this->field_permissions[$entity_type][$field_name]['edit'])) {
            $allowed_roles = $this->field_permissions[$entity_type][$field_name]['edit'];
            return $this->user_has_any_role($user_id, $allowed_roles);
        }

        // Default: deny edit
        return false;
    }

    /**
     * Check if user has any of the specified roles
     */
    private function user_has_any_role($user_id, $roles) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }

        $user_roles = $user->roles;

        return !empty(array_intersect($roles, $user_roles));
    }

    /**
     * Filter fields based on permissions
     */
    public static function filter_fields($fields, $entity_type, $user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $instance = new self();
        $filtered_fields = [];

        foreach ($fields as $field_name => $field_value) {
            if ($instance->can_view_field(true, $entity_type, $field_name, $user_id)) {
                $filtered_fields[$field_name] = $field_value;
            }
        }

        return $filtered_fields;
    }

    /**
     * Mask sensitive fields
     */
    public static function mask_field_value($value, $field_name) {
        $mask_patterns = [
            '_contract_value' => function($val) { 
                return number_format($val, 0) . ' تومان'; 
            },
            '_lead_phone' => function($val) { 
                return WebinoCRM_Data_Encryption::mask_phone($val); 
            },
            '_lead_email' => function($val) { 
                return WebinoCRM_Data_Encryption::mask_email($val); 
            },
            '_credit_card' => function($val) { 
                return WebinoCRM_Data_Encryption::mask_credit_card($val); 
            },
            '_bank_account' => function($val) { 
                return '****' . substr($val, -4); 
            }
        ];

        if (isset($mask_patterns[$field_name]) && is_callable($mask_patterns[$field_name])) {
            return $mask_patterns[$field_name]($value);
        }

        return $value;
    }

    /**
     * Get field permissions for entity
     */
    public static function get_field_permissions($entity_type) {
        $instance = new self();
        return $instance->field_permissions[$entity_type] ?? [];
    }

    /**
     * Update field permissions
     */
    public static function update_field_permissions($entity_type, $field_name, $permissions) {
        $current_permissions = get_option('webinocrm_field_permissions', []);
        
        if (!isset($current_permissions[$entity_type])) {
            $current_permissions[$entity_type] = [];
        }

        $current_permissions[$entity_type][$field_name] = $permissions;

        update_option('webinocrm_field_permissions', $current_permissions);

        return true;
    }

    /**
     * Get editable fields for user
     */
    public static function get_editable_fields($entity_type, $user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $instance = new self();
        $all_fields = $instance->field_permissions[$entity_type] ?? [];
        $editable_fields = [];

        foreach ($all_fields as $field_name => $permissions) {
            if ($instance->can_edit_field(false, $entity_type, $field_name, $user_id)) {
                $editable_fields[] = $field_name;
            }
        }

        return $editable_fields;
    }

    /**
     * Get viewable fields for user
     */
    public static function get_viewable_fields($entity_type, $user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $instance = new self();
        $all_fields = $instance->field_permissions[$entity_type] ?? [];
        $viewable_fields = [];

        foreach ($all_fields as $field_name => $permissions) {
            if ($instance->can_view_field(true, $entity_type, $field_name, $user_id)) {
                $viewable_fields[] = $field_name;
            }
        }

        return $viewable_fields;
    }

    /**
     * AJAX: Update field permissions
     */
    public function ajax_update_field_permissions() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'دسترسی کافی ندارید']);
        }

        $entity_type = sanitize_key($_POST['entity_type'] ?? '');
        $field_name = sanitize_key($_POST['field_name'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (empty($entity_type) || empty($field_name)) {
            wp_send_json_error(['message' => 'پارامترهای نامعتبر']);
        }

        $result = self::update_field_permissions($entity_type, $field_name, $permissions);

        wp_send_json_success(['message' => 'دسترسی‌ها بروزرسانی شد']);
    }
}

