<?php
/**
 * WebinoCRM Automation Handler
 *
 * Handles the automation workflow from WooCommerce purchase to project creation.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Automation_Handler {

    public function __construct() {
        // Hook into WooCommerce Subscription activation
        add_action( 'woocommerce_subscription_status_active', [ $this, 'trigger_automation_on_subscription' ], 10, 1 );
    }

    /**
     * Main automation trigger after a subscription becomes active.
     * @param WC_Subscription $subscription The subscription object.
     */
    public function trigger_automation_on_subscription( $subscription ) {
        if ( ! $subscription ) return;

        $customer_id = $subscription->get_customer_id();
        if ( ! $customer_id ) return; // Only for registered users

        // Prevent duplicate execution
        if ( get_post_meta( $subscription->get_id(), '_automation_triggered', true ) ) {
            return;
        }

        // --- 1. Create the Master Contract ---
        $contract_id = $this->create_master_contract($subscription);
        if (is_wp_error($contract_id)) {
            // Log error
            return;
        }

        // --- 2. Loop through subscription items to create projects ---
        foreach ( $subscription->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $task_template_id  = get_post_meta( $product_id, '_task_template_id', true );
            $project_template_id = get_post_meta( $product_id, '_project_template_id', true );

            // Create project if either task template or project template is linked
            if ( $task_template_id || $project_template_id ) {
                $project_id = $this->create_project_from_template( $product_id, $contract_id );

                if ( ! is_wp_error( $project_id ) ) {
                    // 3. Create tasks: prefer task_template (supports recurring), fallback to project template
                    if ( $task_template_id ) {
                        $this->create_tasks_from_task_template( $project_id, (int) $task_template_id );
                    } elseif ( $project_template_id ) {
                        $this->create_tasks_from_template( $project_id, (int) $project_template_id );
                    }
                }
            }
        }

        // Mark as triggered to prevent re-running
        update_post_meta( $subscription->get_id(), '_automation_triggered', true );
    }

    /**
     * Creates a master contract based on the subscription details.
     * @param WC_Subscription $subscription
     * @return int|WP_Error The new contract ID or an error.
     */
    private function create_master_contract($subscription) {
        $customer_id = $subscription->get_customer_id();
        $customer = get_userdata($customer_id);

        $contract_title = sprintf('قرارداد اشتراک #%d - %s', $subscription->get_id(), $customer->display_name);

        $contract_id = wp_insert_post([
            'post_title'    => $contract_title,
            'post_author'   => $customer_id,
            'post_status'   => 'publish',
            'post_type'     => 'contract',
        ]);
        
        if (is_wp_error($contract_id)) {
            return $contract_id;
        }

        // Populate contract meta from subscription data
        update_post_meta($contract_id, '_project_subscription_model', 'subscription');
        update_post_meta($contract_id, '_project_start_date', $subscription->get_date('start_date', 'site'));
        update_post_meta($contract_id, '_project_end_date', $subscription->get_date('end', 'site'));
        // You can add more meta like duration if needed

        // Create installments based on subscription billing periods
        $installments = [];
        $billing_schedule = $subscription->get_billing_schedule();
        foreach($billing_schedule as $timestamp => $date_obj) {
            if ($timestamp > time()) { // Only for future payments
                 $installments[] = [
                    'amount' => $subscription->get_total(),
                    'due_date' => $date_obj->date('Y-m-d'),
                    'status' => 'pending'
                ];
            }
        }
        update_post_meta($contract_id, '_installments', $installments);
        
        return $contract_id;
    }

    /**
     * Creates a single project linked to a contract.
     * @param int $product_id The WooCommerce product ID.
     * @param int $contract_id The master contract ID.
     * @return int|WP_Error The new project ID or an error.
     */
    private function create_project_from_template($product_id, $contract_id) {
        $product = wc_get_product($product_id);
        $contract = get_post($contract_id);

        $project_id = wp_insert_post([
            'post_title' => $product->get_name(),
            'post_author' => $contract->post_author,
            'post_status' => 'publish',
            'post_type' => 'project',
        ]);

        if (is_wp_error($project_id)) {
            return $project_id;
        }

        // Link project to the contract
        update_post_meta($project_id, '_contract_id', $contract_id);
        
        // Set default status to "Active"
        $active_status = get_term_by('slug', 'active', 'project_status');
        if ($active_status) {
            wp_set_object_terms($project_id, $active_status->term_id, 'project_status');
        }

        return $project_id;
    }

    /**
     * Creates tasks from task_template (_template_tasks) - supports recurring types.
     * @param int $project_id  The project ID.
     * @param int $template_id The task_template post ID.
     */
    private function create_tasks_from_task_template( $project_id, $template_id ) {
        $tasks = get_post_meta( $template_id, '_template_tasks', true );
        if ( empty( $tasks ) || ! is_array( $tasks ) ) {
            return;
        }
        $start = strtotime( current_time( 'Y-m-d' ) );
        $todo_term = get_term_by( 'slug', 'todo', 'task_status' );
        $todo_term_id = $todo_term ? $todo_term->term_id : 0;

        foreach ( $tasks as $task_data ) {
            $title   = isset( $task_data['title'] ) ? sanitize_text_field( $task_data['title'] ) : '';
            $duration = isset( $task_data['duration'] ) ? floatval( $task_data['duration'] ) : 1;
            if ( empty( $title ) ) {
                continue;
            }
            $due_date = date( 'Y-m-d', strtotime( "+{$duration} days", $start ) );
            $task_id = wp_insert_post( [
                'post_title'   => $title,
                'post_type'    => 'task',
                'post_status'  => 'publish',
            ] );
            if ( $task_id && ! is_wp_error( $task_id ) ) {
                update_post_meta( $task_id, '_project_id', $project_id );
                update_post_meta( $task_id, '_start_date', date( 'Y-m-d', $start ) );
                update_post_meta( $task_id, '_due_date', $due_date );
                if ( $todo_term_id ) {
                    wp_set_object_terms( $task_id, $todo_term_id, 'task_status' );
                }
                $start = strtotime( $due_date );
            }
        }
    }

    /**
     * Creates default tasks in a new project based on a project template.
     * @param int $project_id The newly created project ID.
     * @param int $template_id The project template ID.
     */
    private function create_tasks_from_template($project_id, $template_id) {
        $default_tasks = get_post_meta($template_id, '_default_tasks', true);
        if (empty($default_tasks) || !is_array($default_tasks)) {
            return;
        }

        foreach ($default_tasks as $task_data) {
            $task_id = wp_insert_post([
                'post_title' => $task_data['title'],
                'post_content' => $task_data['content'] ?? '',
                'post_type' => 'task',
                'post_status' => 'publish',
            ]);

            if (!is_wp_error($task_id)) {
                // Assign project and a default 'To Do' status
                update_post_meta($task_id, '_project_id', $project_id);
                wp_set_object_terms($task_id, 'to-do', 'task_status');
                // You could also pre-assign users here if needed
            }
        }
    }
}