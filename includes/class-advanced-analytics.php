<?php
/**
 * Advanced Analytics Dashboard
 * 
 * Comprehensive analytics and reporting system
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Advanced_Analytics {

    /**
     * Initialize Analytics
     */
    public function __construct() {
        // REST + SPA via WebinoCRM_Reports_Service; legacy wp_ajax hooks removed.
    }

    /**
     * Export analytics (CSV stream; PDF if lib available).
     *
     * @param array<string,mixed> $args type, format, date_from, date_to.
     * @return array<string,mixed>|null Error array; null when stream started (exit).
     */
    public static function export_analytics( $args = array() ) {
        $type      = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : 'overview';
        $format    = isset( $args['format'] ) ? sanitize_key( (string) $args['format'] ) : 'csv';
        $date_from = isset( $args['date_from'] ) ? sanitize_text_field( (string) $args['date_from'] ) : gmdate( 'Y-m-01' );
        $date_to   = isset( $args['date_to'] ) ? sanitize_text_field( (string) $args['date_to'] ) : gmdate( 'Y-m-t' );

        switch ( $type ) {
            case 'sales':
                $data = self::get_sales_analytics( $date_from, $date_to );
                break;
            case 'team':
                $data = self::get_team_performance( $date_from, $date_to );
                break;
            case 'customer':
            case 'customers':
                $data = self::get_customer_analytics( $date_from, $date_to );
                break;
            default:
                $data = self::get_overview( $date_from, $date_to );
                $type = 'overview';
        }

        if ( 'pdf' === $format && file_exists( WEBINOCRM_PLUGIN_DIR . 'includes/lib/fpdf/fpdf.php' ) ) {
            self::export_pdf_static( $data, $type );
            return null;
        }

        self::export_csv_static( $data, $type );
        return null;
    }

    /**
     * Get overview analytics
     */
    public static function get_overview($date_from = null, $date_to = null) {
        global $wpdb;

        if (!$date_from) $date_from = date('Y-m-01');
        if (!$date_to) $date_to = date('Y-m-t');

        $data = [];

        // Total leads
        $data['total_leads'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'lead' 
             AND post_status != 'trash'
             AND post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Leads by status
        $data['leads_by_status'] = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as status, COUNT(*) as count
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lead_status'
             WHERE p.post_type = 'lead'
             AND p.post_status != 'trash'
             AND p.post_date BETWEEN %s AND %s
             GROUP BY pm.meta_value",
            $date_from, $date_to
        ), ARRAY_A);

        // Conversion rate
        $converted_leads = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lead_status'
             WHERE p.post_type = 'lead'
             AND pm.meta_value = 'converted'
             AND p.post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        $data['conversion_rate'] = $data['total_leads'] > 0 
            ? round(($converted_leads / $data['total_leads']) * 100, 2) 
            : 0;

        // Total projects
        $data['total_projects'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'project' 
             AND post_status != 'trash'
             AND post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Active projects
        $data['active_projects'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_project_status'
             WHERE p.post_type = 'project'
             AND pm.meta_value IN ('in_progress', 'pending')
             AND p.post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Total revenue
        $data['total_revenue'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_value'
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND p.post_date BETWEEN %s AND %s",
            $date_from, $date_to
        )) ?: 0;

        // Total tasks
        $data['total_tasks'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'task' 
             AND post_status != 'trash'
             AND post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Completed tasks
        $data['completed_tasks'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_task_status'
             WHERE p.post_type = 'task'
             AND pm.meta_value = 'completed'
             AND p.post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Task completion rate
        $data['task_completion_rate'] = $data['total_tasks'] > 0
            ? round(($data['completed_tasks'] / $data['total_tasks']) * 100, 2)
            : 0;

        // Average response time (from tickets)
        $data['avg_response_time'] = self::get_average_response_time($date_from, $date_to);

        // Customer satisfaction (if ratings exist)
        $data['customer_satisfaction'] = self::get_customer_satisfaction($date_from, $date_to);

        return $data;
    }

    /**
     * Get sales analytics
     */
    public static function get_sales_analytics($date_from = null, $date_to = null) {
        global $wpdb;

        if (!$date_from) $date_from = date('Y-m-01');
        if (!$date_to) $date_to = date('Y-m-t');

        $data = [];

        // Sales by month
        $data['sales_by_month'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(p.post_date, '%%Y-%%m') as month,
                COUNT(*) as count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_value'
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND p.post_date BETWEEN %s AND %s
             GROUP BY DATE_FORMAT(p.post_date, '%%Y-%%m')
             ORDER BY month",
            $date_from, $date_to
        ), ARRAY_A);

        // Sales by product/service (if available)
        $data['sales_by_category'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.name as category,
                COUNT(*) as count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_value'
             LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND tt.taxonomy = 'contract_category'
             AND p.post_date BETWEEN %s AND %s
             GROUP BY t.term_id
             ORDER BY total DESC",
            $date_from, $date_to
        ), ARRAY_A);

        // Sales funnel
        $data['sales_funnel'] = self::get_sales_funnel($date_from, $date_to);

        // Top customers
        $data['top_customers'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                pm2.meta_value as customer_id,
                u.display_name as customer_name,
                COUNT(*) as contract_count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_value
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_value'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_contract_customer'
             LEFT JOIN {$wpdb->users} u ON pm2.meta_value = u.ID
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND p.post_date BETWEEN %s AND %s
             GROUP BY pm2.meta_value
             ORDER BY total_value DESC
             LIMIT 10",
            $date_from, $date_to
        ), ARRAY_A);

        // Sales forecast (simple linear regression)
        $data['sales_forecast'] = self::forecast_sales($date_from, $date_to);

        return $data;
    }

    /**
     * Get team performance analytics
     */
    public static function get_team_performance($date_from = null, $date_to = null) {
        global $wpdb;

        if (!$date_from) $date_from = date('Y-m-01');
        if (!$date_to) $date_to = date('Y-m-t');

        $data = [];

        // Tasks by team member
        $data['tasks_by_member'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.ID as user_id,
                u.display_name as user_name,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN pm2.meta_value = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN pm2.meta_value = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_task_assignee'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_task_status'
             LEFT JOIN {$wpdb->users} u ON pm.meta_value = u.ID
             WHERE p.post_type = 'task'
             AND p.post_status != 'trash'
             AND p.post_date BETWEEN %s AND %s
             GROUP BY u.ID
             ORDER BY total_tasks DESC",
            $date_from, $date_to
        ), ARRAY_A);

        // Time tracking by member (if time tracking exists)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}webinocrm_time_entries'")) {
            $data['time_by_member'] = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    u.ID as user_id,
                    u.display_name as user_name,
                    SUM(duration_minutes) as total_minutes,
                    SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END) as billable_minutes,
                    SUM(cost) as total_revenue
                 FROM {$wpdb->prefix}webinocrm_time_entries te
                 LEFT JOIN {$wpdb->users} u ON te.user_id = u.ID
                 WHERE te.status = 'stopped'
                 AND te.start_time BETWEEN %s AND %s
                 GROUP BY u.ID
                 ORDER BY total_minutes DESC",
                $date_from, $date_to
            ), ARRAY_A);
        }

        // Projects by member
        $data['projects_by_member'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.ID as user_id,
                u.display_name as user_name,
                COUNT(*) as project_count
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_project_manager'
             LEFT JOIN {$wpdb->users} u ON pm.meta_value = u.ID
             WHERE p.post_type = 'project'
             AND p.post_status != 'trash'
             AND p.post_date BETWEEN %s AND %s
             GROUP BY u.ID
             ORDER BY project_count DESC",
            $date_from, $date_to
        ), ARRAY_A);

        // Activity score
        foreach ($data['tasks_by_member'] as &$member) {
            $member['activity_score'] = self::calculate_activity_score($member['user_id'], $date_from, $date_to);
        }

        return $data;
    }

    /**
     * Get customer analytics
     */
    public static function get_customer_analytics($date_from = null, $date_to = null) {
        global $wpdb;

        if (!$date_from) $date_from = date('Y-m-01');
        if (!$date_to) $date_to = date('Y-m-t');

        $data = [];

        // New customers
        $data['new_customers'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID)
             FROM {$wpdb->users} u
             WHERE u.user_registered BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Customer lifetime value
        $data['customer_ltv'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                pm.meta_value as customer_id,
                u.display_name as customer_name,
                COUNT(DISTINCT p.ID) as total_contracts,
                SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) as lifetime_value
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_customer'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_contract_value'
             LEFT JOIN {$wpdb->users} u ON pm.meta_value = u.ID
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             GROUP BY pm.meta_value
             ORDER BY lifetime_value DESC
             LIMIT 20",
            $date_from, $date_to
        ), ARRAY_A);

        // Customer retention rate
        $data['retention_rate'] = self::calculate_retention_rate($date_from, $date_to);

        // Churn rate
        $data['churn_rate'] = 100 - $data['retention_rate'];

        // Average customer value
        $total_customers = count($data['customer_ltv']);
        $total_value = array_sum(array_column($data['customer_ltv'], 'lifetime_value'));
        $data['avg_customer_value'] = $total_customers > 0 ? $total_value / $total_customers : 0;

        return $data;
    }

    /**
     * Get sales funnel
     */
    private static function get_sales_funnel($date_from, $date_to) {
        global $wpdb;

        $stages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        $funnel = [];

        foreach ($stages as $stage) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lead_status'
                 WHERE p.post_type = 'lead'
                 AND pm.meta_value = %s
                 AND p.post_date BETWEEN %s AND %s",
                $stage, $date_from, $date_to
            ));

            $funnel[] = [
                'stage' => $stage,
                'count' => intval($count)
            ];
        }

        return $funnel;
    }

    /**
     * Forecast sales (simple linear regression)
     */
    private static function forecast_sales($date_from, $date_to) {
        global $wpdb;

        // Get historical data for last 6 months
        $historical_data = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(p.post_date, '%Y-%m') as month,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_value'
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
             ORDER BY month",
            ARRAY_A
        );

        // Simple linear regression to forecast next month
        if (count($historical_data) >= 3) {
            $n = count($historical_data);
            $sum_x = 0; $sum_y = 0; $sum_xy = 0; $sum_xx = 0;

            foreach ($historical_data as $i => $data) {
                $x = $i + 1;
                $y = floatval($data['total']);
                $sum_x += $x;
                $sum_y += $y;
                $sum_xy += $x * $y;
                $sum_xx += $x * $x;
            }

            $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
            $intercept = ($sum_y - $slope * $sum_x) / $n;

            // Forecast next month
            $next_value = $slope * ($n + 1) + $intercept;

            return [
                'next_month' => date('Y-m', strtotime('+1 month')),
                'forecasted_value' => round($next_value, 2),
                'confidence' => 'medium' // Simple confidence level
            ];
        }

        return null;
    }

    /**
     * Calculate activity score for user
     */
    private static function calculate_activity_score($user_id, $date_from, $date_to) {
        global $wpdb;

        $score = 0;

        // Tasks completed (1 point each)
        $tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_task_assignee'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_task_status'
             WHERE p.post_type = 'task'
             AND pm.meta_value = %d
             AND pm2.meta_value = 'completed'
             AND p.post_date BETWEEN %s AND %s",
            $user_id, $date_from, $date_to
        ));

        $score += intval($tasks) * 1;

        // Leads converted (5 points each)
        $leads = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lead_assigned_to'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_lead_status'
             WHERE p.post_type = 'lead'
             AND pm.meta_value = %d
             AND pm2.meta_value = 'converted'
             AND p.post_date BETWEEN %s AND %s",
            $user_id, $date_from, $date_to
        ));

        $score += intval($leads) * 5;

        // Activities logged (0.5 points each)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}webinocrm_activities'")) {
            $activities = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}webinocrm_activities
                 WHERE user_id = %d
                 AND created_at BETWEEN %s AND %s",
                $user_id, $date_from, $date_to
            ));

            $score += intval($activities) * 0.5;
        }

        return round($score, 1);
    }

    /**
     * Calculate customer retention rate
     */
    private static function calculate_retention_rate($date_from, $date_to) {
        global $wpdb;

        // This is a simplified calculation
        // You might want to implement more sophisticated logic based on your business model

        $period_start = date('Y-m-d', strtotime($date_from));
        $period_end = date('Y-m-d', strtotime($date_to));

        // Customers at start of period
        $customers_start = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.meta_value)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_customer'
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND p.post_date < %s",
            $period_start
        ));

        // Customers still active at end of period
        $customers_retained = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.meta_value)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_contract_customer'
             WHERE p.post_type = 'contract'
             AND p.post_status = 'publish'
             AND p.post_date BETWEEN %s AND %s
             AND pm.meta_value IN (
                 SELECT DISTINCT pm2.meta_value
                 FROM {$wpdb->posts} p2
                 LEFT JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id AND pm2.meta_key = '_contract_customer'
                 WHERE p2.post_type = 'contract'
                 AND p2.post_date < %s
             )",
            $period_start, $period_end, $period_start
        ));

        return $customers_start > 0 ? round(($customers_retained / $customers_start) * 100, 2) : 0;
    }

    /**
     * Get average response time
     */
    private static function get_average_response_time($date_from, $date_to) {
        global $wpdb;

        // Calculate from tickets if table exists
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, p.post_date, pm.meta_value))
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ticket_first_response'
             WHERE p.post_type = 'ticket'
             AND pm.meta_value IS NOT NULL
             AND p.post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        return $result ? round($result, 2) : 0;
    }

    /**
     * Get customer satisfaction
     */
    private static function get_customer_satisfaction($date_from, $date_to) {
        global $wpdb;

        // If you have a rating system
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(pm.meta_value AS DECIMAL(3,2)))
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_rating'
             WHERE p.post_type IN ('project', 'ticket')
             AND pm.meta_value IS NOT NULL
             AND p.post_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        return $result ? round($result, 2) : 0;
    }

    /**
     * AJAX Handlers
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-t'));

        $data = self::get_overview($date_from, $date_to);

        wp_send_json_success($data);
    }

    public function ajax_get_sales_analytics() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-t'));

        $data = self::get_sales_analytics($date_from, $date_to);

        wp_send_json_success($data);
    }

    public function ajax_get_team_performance() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-t'));

        $data = self::get_team_performance($date_from, $date_to);

        wp_send_json_success($data);
    }

    public function ajax_get_customer_analytics() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-t'));

        $data = self::get_customer_analytics($date_from, $date_to);

        wp_send_json_success($data);
    }

    public function ajax_export_analytics() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $type = sanitize_key($_POST['type'] ?? 'overview');
        $format = sanitize_key($_POST['format'] ?? 'csv');
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-t'));

        // Get data based on type
        switch ($type) {
            case 'sales':
                $data = self::get_sales_analytics($date_from, $date_to);
                break;
            case 'team':
                $data = self::get_team_performance($date_from, $date_to);
                break;
            case 'customer':
                $data = self::get_customer_analytics($date_from, $date_to);
                break;
            default:
                $data = self::get_overview($date_from, $date_to);
        }

        // Export based on format
        $result = self::export_analytics(
            array(
                'type'      => $type,
                'format'    => $format,
                'date_from' => $date_from,
                'date_to'   => $date_to,
            )
        );
        if ( is_array( $result ) ) {
            wp_send_json_error( array( 'message' => isset( $result['data']['message'] ) ? $result['data']['message'] : __( 'خطا در خروجی.', 'webinocrm' ) ) );
        }
    }

    /**
     * Export to CSV
     */
    private static function export_csv_static( $data, $type ) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analytics-' . $type . '-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write data
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                fputcsv($output, [$key]);
                foreach ($value as $row) {
                    fputcsv($output, is_array($row) ? $row : [$row]);
                }
            } else {
                fputcsv($output, [$key, $value]);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export to PDF
     */
    private static function export_pdf_static( $data, $type ) {
        require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/fpdf/fpdf.php';

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Analytics Report: ' . ucfirst($type), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $pdf->Cell(0, 8, $key . ': ' . $value, 0, 1);
            }
        }

        $pdf->Output('D', 'analytics-' . $type . '-' . date('Y-m-d') . '.pdf');
        exit;
    }
}

