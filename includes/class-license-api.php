<?php
/**
 * License REST API
 *
 * Provides REST API endpoints for license validation and activation
 *
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class WebinoCRM_License_API {

    const ROUTE_CHECK = 'wp-json/webinocrm/v1/license/check';
    const ROUTE_ACTIVATE = 'wp-json/webinocrm/v1/license/activate';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('webinocrm/v1', '/license/check', [
            'methods' => 'POST',
            'callback' => [$this, 'check_license'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args' => [
                'domain' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('webinocrm/v1', '/license/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activate_license'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args' => [
                'domain' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

    }

    /**
     * Domain-based license API: open read/check; rate-limit activate writes.
     *
     * @param WP_REST_Request $request Request.
     * @return bool|WP_Error
     */
    public function rest_permission_check($request) {
        return $this->rate_limit_or_error($request);
    }

    /**
     * Rate limit write endpoints only (activate).
     *
     * @param WP_REST_Request $request Request.
     * @return bool|WP_Error
     */
    private function rate_limit_or_error($request) {
        $route = $request->get_route();
        if (false === strpos($route, '/license/activate')) {
            return true;
        }
        if (function_exists('webinocrm_rate_limit_allow') && !webinocrm_rate_limit_allow('license_rest_activate', 60, 60)) {
            return new WP_Error('webinocrm_rate_limit', __('Too many requests.', 'webinocrm'), ['status' => 429]);
        }
        return true;
    }

    /**
     * @param array<string,mixed> $data   Response body.
     * @param int                 $status HTTP status.
     * @return WP_REST_Response
     */
    private function rest_response($data, $status = 200) {
        $response = new WP_REST_Response($data, $status);
        if (defined('WEBINOCRM_LICENSE_FASTPATH_ACTIVE')) {
            $response->header('X-Webino-Fastpath', '1');
        }
        return $response;
    }

    /**
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function check_license($request) {
        $started = microtime(true);
        $domain = $request->get_param('domain');
        $this->log_license_request('check', $domain, $request);

        if (empty($domain)) {
            return $this->rest_response([
                'status' => 'error',
                'message' => 'دامنه الزامی است',
            ], 400);
        }

        $validation = WebinoCRM_License_Manager::validate_license($domain, false);

        $this->maybe_log_slow($started, 'check', $domain, $request);

        if ($validation['valid']) {
            return $this->rest_response([
                'status' => 'valid',
                'expiry_date' => $validation['expiry_date'],
                'remaining_days' => $validation['remaining_days'],
                'remaining_percentage' => $validation['remaining_percentage'],
            ], 200);
        }

        return $this->rest_response([
            'status' => $validation['status'],
            'message' => $validation['message'],
            'expiry_date' => $validation['expiry_date'] ?? null,
        ], 200);
    }

    /**
     * Activate license endpoint (domain is the license)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function activate_license($request) {
        $started = microtime(true);
        $domain = $request->get_param('domain');
        $this->log_license_request('activate', $domain, $request);

        if (empty($domain)) {
            return $this->rest_response([
                'status' => 'error',
                'message' => 'دامنه الزامی است',
            ], 400);
        }

        $result = WebinoCRM_License_Manager::activate_license_for_domain($domain);
        $this->maybe_log_slow($started, 'activate', $domain, $request);

        if (!empty($result['ok'])) {
            return $this->rest_response([
                'status' => 'success',
                'message' => $result['message'],
                'expiry_date' => $result['expiry_date'] ?? null,
                'remaining_days' => $result['remaining_days'] ?? null,
                'remaining_percentage' => $result['remaining_percentage'] ?? null,
            ], 200);
        }

        $http = !empty($result['http_status']) ? (int) $result['http_status'] : 200;
        return $this->rest_response([
            'status' => $result['status'] ?? 'error',
            'message' => $result['message'] ?? 'خطا در فعال‌سازی',
            'code' => $result['code'] ?? 'activate_failed',
        ], $http);
    }

    /**
     * @param string               $action  Action name.
     * @param string               $domain  Domain.
     * @param WP_REST_Request|null $request Request.
     * @return void
     */
    private function log_license_request($action, $domain, $request = null) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $cid = $this->get_correlation_id($request);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[WebinoCRM License] ' . $action . ' domain=' . $domain . ' correlation_id=' . $cid);
    }

    /**
     * @param WP_REST_Request|null $request Request.
     * @return string
     */
    private function get_correlation_id($request) {
        if (!$request instanceof WP_REST_Request) {
            return '';
        }
        $cid = (string) $request->get_header('X-Webino-Correlation-Id');
        if ('' === $cid) {
            $cid = (string) $request->get_header('x_webino_correlation_id');
        }
        return $cid;
    }

    /**
     * @param float                $started Start time.
     * @param string               $action  Action name.
     * @param string               $domain  Domain.
     * @param WP_REST_Request|null $request Request.
     * @return void
     */
    private function maybe_log_slow($started, $action, $domain, $request = null) {
        $ms = (int) round((microtime(true) - $started) * 1000);
        if ($ms < 500) {
            return;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $cid = $this->get_correlation_id($request);
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[WebinoCRM License] slow ' . $action . ' domain=' . $domain . ' latency_ms=' . $ms . ' correlation_id=' . $cid);
        }
    }
}
