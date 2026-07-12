<?php
/**
 * REST API for Bale bot settings, logs, and diagnostics (CRM dashboard).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebinoCRM_Bale_REST_API
 */
class WebinoCRM_Bale_REST_API {

	/**
	 * WebinoCRM_Bale_REST_API constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Whether the current user may manage Bale settings (system manager or admin).
	 *
	 * @return bool
	 */
	public static function can_manage_bale() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		return in_array( 'administrator', $roles, true ) || in_array( 'system_manager', $roles, true );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'webinocrm/v1',
			'/bale/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'permission_manage' ),
				'args'                => array(
					'limit' => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/webhook-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_webhook_url' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/set-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_webhook' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/diagnostics/webhook-info',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'diagnostics_webhook_info' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/diagnostics/test-log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'diagnostics_test_log' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/diagnostics/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'diagnostics_stats' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/message/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_bulk_message' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/user-logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_logs' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/campaigns',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_campaigns' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_campaign' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/campaigns/(?P<id>\d+)/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_campaign' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/kpi',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'kpi_dashboard' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/bale/automation/process-queue',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_automation_queue' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function permission_manage() {
		return self::can_manage_bale();
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		$settings = \WebinaBaleBusiness\Core\Plugin::get_settings();
		unset( $settings['webhook_secret'] );
		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( array( 'message' => __( 'بدنهٔ درخواست JSON معتبر نیست.', 'webinocrm' ) ), 400 );
		}
		$current   = \WebinaBaleBusiness\Core\Plugin::get_settings();
		$merged    = array_merge( $current, $body );
		$sanitized = \WebinaBaleBusiness\Admin\SettingsPage::instance()->sanitize( $merged );
		\WebinaBaleBusiness\Core\Plugin::update_settings( $sanitized );
		$this->sync_bale_settings_to_crm( $sanitized );
		return new WP_REST_Response( \WebinaBaleBusiness\Core\Plugin::get_settings(), 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_logs( $request ) {
		$limit = (int) $request->get_param( 'limit' );
		$rows  = \WebinaBaleBusiness\Support\Logger::latest( $limit > 0 ? $limit : 50 );
		return new WP_REST_Response( array( 'logs' => $rows ), 200 );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function get_webhook_url() {
		$url = \WebinaBaleBusiness\Api\WebhookController::instance()->webhook_url();
		return new WP_REST_Response(
			array(
				'url'     => $url,
				'message' => __( 'این URL را در پنل بله برای ربات خود setWebhook کنید.', 'webinocrm' ),
			),
			200
		);
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function set_webhook() {
		$client = new \WebinaBaleBusiness\Bale\Client();
		$url    = \WebinaBaleBusiness\Api\WebhookController::instance()->webhook_url();
		$result = $client->set_webhook( $url );
		return new WP_REST_Response( array( 'result' => $result ), 200 );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function diagnostics_webhook_info() {
		$client = new \WebinaBaleBusiness\Bale\Client();
		$info   = $client->get_webhook_info();
		return new WP_REST_Response( array( 'webhook_info' => $info ), 200 );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function diagnostics_test_log() {
		\WebinaBaleBusiness\Support\Logger::log(
			'info',
			'manual_test_log',
			array( 'source' => 'webinocrm_crm' )
		);
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function diagnostics_stats() {
		return new WP_REST_Response(
			array(
				'support_opened'       => \WebinaBaleBusiness\Analytics\EventLogger::count_by_event( 'support_opened' ),
				'support_item_clicked' => \WebinaBaleBusiness\Analytics\EventLogger::count_by_event( 'support_item_clicked' ),
			),
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function send_message( $request ) {
		$payload  = (array) $request->get_json_params();
		$user_id  = absint( $payload['user_id'] ?? 0 );
		$message  = sanitize_textarea_field( (string) ( $payload['message'] ?? '' ) );
		$chat_id  = sanitize_text_field( (string) get_user_meta( $user_id, 'webinocrm_bale_chat_id', true ) );

		if ( $user_id <= 0 || $message === '' ) {
			return new WP_REST_Response( array( 'message' => __( 'داده ارسال پیام نامعتبر است.', 'webinocrm' ) ), 400 );
		}
		if ( $chat_id === '' ) {
			return new WP_REST_Response( array( 'message' => __( 'این کاربر شناسه بله ثبت‌شده ندارد.', 'webinocrm' ) ), 400 );
		}

		$client = new \WebinaBaleBusiness\Bale\Client();
		$res    = $client->send_message(
			array(
				'chat_id' => $chat_id,
				'text'    => $message,
			)
		);

		\WebinaBaleBusiness\Support\Logger::log(
			'info',
			'bale_manual_message_sent',
			array(
				'user_id' => $user_id,
				'chat_id' => $chat_id,
			)
		);

		return new WP_REST_Response( array( 'result' => $res ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function send_bulk_message( $request ) {
		$payload = (array) $request->get_json_params();
		$message = sanitize_textarea_field( (string) ( $payload['message'] ?? '' ) );
		$mode    = sanitize_key( (string) ( $payload['mode'] ?? 'all' ) );
		$roles   = isset( $payload['roles'] ) && is_array( $payload['roles'] ) ? array_map( 'sanitize_key', $payload['roles'] ) : array();

		if ( $message === '' ) {
			return new WP_REST_Response( array( 'message' => __( 'متن پیام الزامی است.', 'webinocrm' ) ), 400 );
		}

		$query_args = array(
			'number'     => 5000,
			'meta_key'   => 'webinocrm_bale_chat_id',
			'meta_compare' => 'EXISTS',
			'fields'     => 'ID',
		);
		if ( $mode === 'filtered' && ! empty( $roles ) ) {
			$query_args['role__in'] = $roles;
		}

		$user_ids = get_users( $query_args );
		$client   = new \WebinaBaleBusiness\Bale\Client();
		$sent     = 0;
		$failed   = 0;

		foreach ( $user_ids as $user_id ) {
			$chat_id = sanitize_text_field( (string) get_user_meta( (int) $user_id, 'webinocrm_bale_chat_id', true ) );
			if ( $chat_id === '' ) {
				continue;
			}
			$res = $client->send_message(
				array(
					'chat_id' => $chat_id,
					'text'    => $message,
				)
			);
			if ( is_array( $res ) && ! empty( $res['ok'] ) ) {
				++$sent;
			} else {
				++$failed;
			}
		}

		\WebinaBaleBusiness\Support\Logger::log(
			'info',
			'bale_bulk_message_sent',
			array(
				'mode'   => $mode,
				'total'  => count( $user_ids ),
				'sent'   => $sent,
				'failed' => $failed,
			)
		);

		return new WP_REST_Response(
			array(
				'total'  => count( $user_ids ),
				'sent'   => $sent,
				'failed' => $failed,
			),
			200
		);
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function get_stats() {
		global $wpdb;
		$events_table     = $wpdb->prefix . 'wbb_events';
		$logs_table       = $wpdb->prefix . 'wbb_logs';
		$profiles_table   = $wpdb->prefix . 'wbb_profiles';
		$businesses_table = $wpdb->prefix . 'wbb_businesses';

		$data = array(
			'total_events'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table}" ),
			'total_logs'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ),
			'total_users'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$profiles_table}" ),
			'total_businesses'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$businesses_table}" ),
			'started_users'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT chat_id) FROM {$events_table} WHERE event_type = %s", 'conversation_started' ) ),
		);
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_user_logs( $request ) {
		global $wpdb;
		$chat_id = sanitize_text_field( (string) $request->get_param( 'chat_id' ) );
		$limit   = max( 10, min( 300, absint( $request->get_param( 'limit' ) ) ) );
		if ( $chat_id === '' ) {
			return new WP_REST_Response( array( 'message' => __( 'chat_id الزامی است.', 'webinocrm' ) ), 400 );
		}
		$events_table = $wpdb->prefix . 'wbb_events';
		$logs_table   = $wpdb->prefix . 'wbb_logs';
		$events       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, event_type AS type, payload, created_at FROM {$events_table} WHERE chat_id = %s ORDER BY id DESC LIMIT %d",
				$chat_id,
				$limit
			),
			\ARRAY_A
		);
		$logs         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, log_type AS type, context AS payload, created_at FROM {$logs_table} WHERE context LIKE %s ORDER BY id DESC LIMIT %d",
				'%' . $wpdb->esc_like( $chat_id ) . '%',
				$limit
			),
			\ARRAY_A
		);
		return new WP_REST_Response(
			array(
				'chat_id' => $chat_id,
				'events'  => is_array( $events ) ? $events : array(),
				'logs'    => is_array( $logs ) ? $logs : array(),
			),
			200
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function sync_bale_settings_to_crm( array $settings ): void {
		if ( ! class_exists( 'WebinoCRM_Settings_Handler' ) ) {
			return;
		}
		$current               = \WebinoCRM_Settings_Handler::get_all_settings();
		$current['bale_bot']   = $settings;
		\WebinoCRM_Settings_Handler::update_settings( $current );
	}

	public function list_campaigns() {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_campaigns';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200", \ARRAY_A );
		return new WP_REST_Response( array( 'campaigns' => is_array( $rows ) ? $rows : array() ), 200 );
	}

	public function create_campaign( $request ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'wbb_campaigns';
		$payload = (array) $request->get_json_params();
		$name    = sanitize_text_field( (string) ( $payload['name'] ?? '' ) );
		$segment = sanitize_key( (string) ( $payload['segment_key'] ?? 'newcomer' ) );
		$variant = strtoupper( sanitize_key( (string) ( $payload['variant'] ?? 'A' ) ) );
		$message = sanitize_textarea_field( (string) ( $payload['message_template'] ?? '' ) );
		if ( $name === '' || $message === '' ) {
			return new WP_REST_Response( array( 'message' => __( 'نام کمپین و متن پیام الزامی است.', 'webinocrm' ) ), 400 );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'name'             => $name,
				'segment_key'      => $segment,
				'variant'          => in_array( $variant, array( 'A', 'B' ), true ) ? $variant : 'A',
				'message_template' => $message,
				'cta_text'         => sanitize_text_field( (string) ( $payload['cta_text'] ?? '' ) ),
				'status'           => 'draft',
				'scheduled_for'    => ! empty( $payload['scheduled_for'] ) ? sanitize_text_field( (string) $payload['scheduled_for'] ) : null,
				'created_by'       => get_current_user_id(),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return new WP_REST_Response( array( 'id' => (int) $wpdb->insert_id ), 200 );
	}

	public function run_campaign( $request ) {
		global $wpdb;
		$campaign_id   = absint( $request->get_param( 'id' ) );
		$campaigns_tbl = $wpdb->prefix . 'wbb_campaigns';
		$delivery_tbl  = $wpdb->prefix . 'wbb_campaign_deliveries';
		$campaign      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns_tbl} WHERE id = %d LIMIT 1", $campaign_id ), \ARRAY_A );
		if ( ! is_array( $campaign ) ) {
			return new WP_REST_Response( array( 'message' => __( 'کمپین یافت نشد.', 'webinocrm' ) ), 404 );
		}
		$segment = (string) ( $campaign['segment_key'] ?? 'newcomer' );
		$chat_ids = $this->resolve_segment_chat_ids( $segment );
		$client   = new \WebinaBaleBusiness\Bale\Client();
		$delivered = 0;
		$failed    = 0;
		$now       = current_time( 'mysql' );
		foreach ( $chat_ids as $chat_id ) {
			$res = $client->send_message(
				array(
					'chat_id' => $chat_id,
					'text'    => (string) $campaign['message_template'],
				)
			);
			$status = ( is_array( $res ) && ! empty( $res['ok'] ) ) ? 'delivered' : 'failed';
			if ( $status === 'delivered' ) {
				++$delivered;
			} else {
				++$failed;
			}
			$wpdb->insert(
				$delivery_tbl,
				array(
					'campaign_id'       => $campaign_id,
					'chat_id'           => $chat_id,
					'variant'           => (string) ( $campaign['variant'] ?? 'A' ),
					'status'            => $status,
					'delivered_at'      => $status === 'delivered' ? $now : null,
					'response_payload'  => wp_json_encode( $res ),
					'created_at'        => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
		$wpdb->update(
			$campaigns_tbl,
			array(
				'status'     => 'sent',
				'updated_at' => $now,
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return new WP_REST_Response(
			array(
				'total'     => count( $chat_ids ),
				'delivered' => $delivered,
				'failed'    => $failed,
				'metrics'   => \WebinaBaleBusiness\Automation\Engine::campaign_metrics( $campaign_id ),
			),
			200
		);
	}

	public function kpi_dashboard() {
		global $wpdb;
		$events_table = $wpdb->prefix . 'wbb_events';
		$leads_table  = $wpdb->prefix . 'wbb_leads';
		$delivery_tbl = $wpdb->prefix . 'wbb_campaign_deliveries';

		$started_users = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT chat_id) FROM {$events_table} WHERE event_type = %s", 'conversation_started' ) );
		$lead_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads_table}" );
		$converted     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads_table} WHERE converted_customer_id IS NOT NULL AND converted_customer_id > 0" );
		$sales_ready   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads_table} WHERE funnel_stage = %s", 'sales-ready' ) );
		$deliveries    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$delivery_tbl}" );
		$clicked       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$delivery_tbl} WHERE status = %s", 'clicked' ) );
		$replied       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$delivery_tbl} WHERE status = %s", 'replied' ) );
		$lead_rate     = $started_users > 0 ? round( ( $lead_count / $started_users ) * 100, 2 ) : 0;
		$convert_rate  = $lead_count > 0 ? round( ( $converted / $lead_count ) * 100, 2 ) : 0;

		return new WP_REST_Response(
			array(
				'start_to_lead_rate'      => $lead_rate,
				'lead_to_customer_rate'   => $convert_rate,
				'first_response_minutes'  => 0,
				'retention_rate'          => 0,
				'campaign_revenue_impact' => 0,
				'funnel_dropoff'          => array(
					'started_users' => $started_users,
					'leads'         => $lead_count,
					'sales_ready'   => $sales_ready,
					'customers'     => $converted,
				),
				'campaign_metrics'        => array(
					'deliveries' => $deliveries,
					'clicked'    => $clicked,
					'replied'    => $replied,
				),
			),
			200
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function resolve_segment_chat_ids( string $segment_key ): array {
		global $wpdb;
		$leads_table = $wpdb->prefix . 'wbb_leads';
		$where_sql   = '1=1';
		$params      = array();
		if ( $segment_key === 'hot-leads' ) {
			$where_sql = 'funnel_stage IN (%s,%s)';
			$params    = array( 'hot', 'sales-ready' );
		} elseif ( $segment_key === 'past-buyers' ) {
			$where_sql = 'score >= %d';
			$params    = array( 90 );
		} elseif ( $segment_key === 'inactive-30d' ) {
			$where_sql = 'last_event_at < DATE_SUB(NOW(), INTERVAL 30 DAY)';
		} elseif ( $segment_key === 'newcomer' ) {
			$where_sql = 'funnel_stage = %s';
			$params    = array( 'new' );
		}
		$sql = "SELECT chat_id FROM {$leads_table} WHERE {$where_sql} ORDER BY id DESC LIMIT 2000";
		$prepared = ! empty( $params ) ? $wpdb->prepare( $sql, ...$params ) : $sql;
		$rows     = $wpdb->get_col( $prepared );
		$rows     = is_array( $rows ) ? $rows : array();
		return array_values( array_filter( array_map( 'strval', $rows ) ) );
	}

	public function process_automation_queue() {
		$res = \WebinaBaleBusiness\Automation\Engine::process_queue_due_items();
		return new WP_REST_Response( $res, 200 );
	}
}
