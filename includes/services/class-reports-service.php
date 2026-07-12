<?php
/**
 * Reports service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Reports_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
	 */
	private static function guard_reports() {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! class_exists( 'WebinoCRM_REST_Base' ) || ! WebinoCRM_REST_Base::can_access_route( 'reports' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 * @return array{date_from:string,date_to:string,tab:string}
	 */
	private static function parse_dates( array $params ) {
		$tab = isset( $params['tab'] ) ? sanitize_key( (string) $params['tab'] ) : 'overview';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['tab'] ) ) {
			$tab = sanitize_key( wp_unslash( (string) $_POST['tab'] ) );
		}
		$date_from = isset( $params['date_from'] ) ? sanitize_text_field( (string) $params['date_from'] ) : '';
		$date_to   = isset( $params['date_to'] ) ? sanitize_text_field( (string) $params['date_to'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $date_from && isset( $_POST['date_from'] ) ) {
			$date_from = sanitize_text_field( wp_unslash( (string) $_POST['date_from'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $date_to && isset( $_POST['date_to'] ) ) {
			$date_to = sanitize_text_field( wp_unslash( (string) $_POST['date_to'] ) );
		}
		if ( '' === $date_from ) {
			$date_from = gmdate( 'Y-m-01' );
		}
		if ( '' === $date_to ) {
			$date_to = gmdate( 'Y-m-d' );
		}
		return array(
			'tab'       => $tab,
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);
	}

	public static function get( array $params ) {
		$err = self::guard_reports();
		if ( $err ) {
			return $err;
		}

		$parsed    = self::parse_dates( $params );
		$tab       = $parsed['tab'];
		$date_from = $parsed['date_from'];
		$date_to   = $parsed['date_to'];

		$data = array(
			'tab'       => $tab,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'stats'     => array(),
			'charts'    => array(),
			'tables'    => array(),
			'analytics' => array(),
		);

		switch ( $tab ) {
			case 'sales':
				if ( class_exists( 'WebinoCRM_Advanced_Analytics' ) ) {
					$data['analytics'] = WebinoCRM_Advanced_Analytics::get_sales_analytics( $date_from, $date_to );
					$data['tables']    = array(
						'sales_by_month'    => $data['analytics']['sales_by_month'] ?? array(),
						'sales_by_category' => $data['analytics']['sales_by_category'] ?? array(),
						'top_customers'     => $data['analytics']['top_customers'] ?? array(),
						'sales_funnel'      => $data['analytics']['sales_funnel'] ?? array(),
					);
				}
				break;

			case 'team':
				if ( class_exists( 'WebinoCRM_Advanced_Analytics' ) ) {
					$data['analytics'] = WebinoCRM_Advanced_Analytics::get_team_performance( $date_from, $date_to );
					$data['tables']    = array(
						'tasks_by_member'    => $data['analytics']['tasks_by_member'] ?? array(),
						'time_by_member'     => $data['analytics']['time_by_member'] ?? array(),
						'projects_by_member' => $data['analytics']['projects_by_member'] ?? array(),
					);
				}
				break;

			case 'customers':
				if ( class_exists( 'WebinoCRM_Advanced_Analytics' ) ) {
					$data['analytics'] = WebinoCRM_Advanced_Analytics::get_customer_analytics( $date_from, $date_to );
					$data['stats']     = array(
						'new_customers'      => $data['analytics']['new_customers'] ?? 0,
						'retention_rate'     => $data['analytics']['retention_rate'] ?? 0,
						'churn_rate'         => $data['analytics']['churn_rate'] ?? 0,
						'avg_customer_value' => $data['analytics']['avg_customer_value'] ?? 0,
					);
					$data['tables']      = array(
						'customer_ltv' => $data['analytics']['customer_ltv'] ?? array(),
					);
				}
				break;

			case 'finance':
				$data['stats'] = WebinoCRM_Reports_Dashboard::get_overall_statistics( $date_from, $date_to );
				unset( $data['stats']['project_by_status'], $data['stats']['task_by_status'] );
				break;

			case 'tasks':
				$data['stats']  = self::get_tasks_tab_stats( $date_from, $date_to );
				$data['tables'] = array(
					'time_by_member' => self::get_time_summary_table( $date_from, $date_to ),
				);
				if ( class_exists( 'WebinoCRM_Reports_Dashboard' ) ) {
					$data['charts']['status_distribution'] = WebinoCRM_Reports_Dashboard::get_status_distribution( $date_from, $date_to );
				}
				break;

			case 'tickets':
				$data['stats']  = self::get_tickets_tab_stats( $date_from, $date_to );
				break;

			case 'agile':
				$data['stats']  = self::get_agile_tab_stats( $date_from, $date_to );
				$data['charts'] = array(
					'monthly' => WebinoCRM_Reports_Dashboard::get_monthly_performance( 6 ),
				);
				break;

			case 'overview':
			default:
				$tab               = 'overview';
				$data['tab']       = 'overview';
				$base              = WebinoCRM_Reports_Dashboard::get_overall_statistics( $date_from, $date_to );
				$advanced          = class_exists( 'WebinoCRM_Advanced_Analytics' )
					? WebinoCRM_Advanced_Analytics::get_overview( $date_from, $date_to )
					: array();
				$data['stats']     = array_merge( $base, $advanced );
				$data['analytics'] = $advanced;
				$data['charts']    = array(
					'daily'               => WebinoCRM_Reports_Dashboard::get_daily_statistics( $date_from, $date_to ),
					'monthly'             => WebinoCRM_Reports_Dashboard::get_monthly_performance( 6 ),
					'status_distribution' => WebinoCRM_Reports_Dashboard::get_status_distribution( $date_from, $date_to ),
					'growth'              => WebinoCRM_Reports_Dashboard::get_growth_statistics( $date_from, $date_to ),
				);
				if ( ! empty( $advanced['leads_by_status'] ) ) {
					$data['tables']['leads_by_status'] = $advanced['leads_by_status'];
				}
				break;
		}

		return WebinoCRM_Service_Base::success( $data );
	}

	/**
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array<string,mixed>
	 */
	private static function get_tasks_tab_stats( $date_from, $date_to ) {
		$advanced = class_exists( 'WebinoCRM_Advanced_Analytics' )
			? WebinoCRM_Advanced_Analytics::get_overview( $date_from, $date_to )
			: array();
		$time_rows = self::get_time_summary_table( $date_from, $date_to );
		$total_minutes = 0;
		foreach ( $time_rows as $row ) {
			$total_minutes += isset( $row['total_minutes'] ) ? (float) $row['total_minutes'] : 0;
		}
		return array(
			'total_tasks'          => $advanced['total_tasks'] ?? 0,
			'completed_tasks'      => $advanced['completed_tasks'] ?? 0,
			'task_completion_rate' => $advanced['task_completion_rate'] ?? 0,
			'total_time_minutes'   => $total_minutes,
			'total_time_hours'     => round( $total_minutes / 60, 2 ),
		);
	}

	/**
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_time_summary_table( $date_from, $date_to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'webinocrm_time_entries';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.display_name AS user_name,
					SUM(te.duration_minutes) AS total_minutes,
					SUM(te.cost) AS total_cost,
					COUNT(*) AS entry_count
				FROM {$table} te
				LEFT JOIN {$wpdb->users} u ON te.user_id = u.ID
				WHERE te.status = 'stopped'
				AND te.start_time BETWEEN %s AND %s
				GROUP BY te.user_id
				ORDER BY total_minutes DESC",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			),
			ARRAY_A
		);
	}

	/**
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array<string,mixed>
	 */
	private static function get_tickets_tab_stats( $date_from, $date_to ) {
		global $wpdb;
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = 'ticket' AND post_status != 'trash'
				AND post_date BETWEEN %s AND %s",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			)
		);
		$advanced = class_exists( 'WebinoCRM_Advanced_Analytics' )
			? WebinoCRM_Advanced_Analytics::get_overview( $date_from, $date_to )
			: array();
		return array(
			'total_tickets'     => $total,
			'avg_response_time' => $advanced['avg_response_time'] ?? 0,
		);
	}

	/**
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array<string,mixed>
	 */
	private static function get_agile_tab_stats( $date_from, $date_to ) {
		$base     = WebinoCRM_Reports_Dashboard::get_overall_statistics( $date_from, $date_to );
		$advanced = class_exists( 'WebinoCRM_Advanced_Analytics' )
			? WebinoCRM_Advanced_Analytics::get_overview( $date_from, $date_to )
			: array();
		return array(
			'total_projects'       => $base['total_projects'] ?? 0,
			'active_projects'      => $advanced['active_projects'] ?? 0,
			'total_tasks'          => $base['total_tasks'] ?? 0,
			'completed_tasks'      => $base['completed_tasks'] ?? 0,
			'task_completion_rate' => $advanced['task_completion_rate'] ?? 0,
		);
	}

	/**
	 * CSV export (contracts/tasks).
	 *
	 * @param array<string,mixed> $params type, date_from, date_to, security or _wpnonce.
	 * @return array<string,mixed>|null
	 */
	public static function export( array $params ) {
		$nonce = '';
		if ( ! empty( $params['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( (string) $params['_wpnonce'], 'wp_rest' ) ) {
				return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
			}
		} else {
			$nonce = isset( $params['security'] ) ? (string) $params['security'] : '';
			if ( '' === $nonce && isset( $_GET['security'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_GET['security'] ) );
			}
			if ( ! wp_verify_nonce( $nonce, 'webinocrm-ajax-nonce' ) ) {
				return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
			}
		}
		if ( ! class_exists( 'WebinoCRM_REST_Base' ) || ! WebinoCRM_REST_Base::can_access_route( 'reports' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		$type = isset( $params['type'] ) ? sanitize_key( (string) $params['type'] ) : 'contracts';
		if ( ! in_array( $type, array( 'contracts', 'tasks' ), true ) ) {
			$type = 'contracts';
		}
		$parsed    = self::parse_dates( $params );
		$date_from = $parsed['date_from'];
		$date_to   = $parsed['date_to'];
		WebinoCRM_Reports_Dashboard::export_to_csv(
			array(
				'type'      => $type,
				'date_from' => $date_from,
				'date_to'   => $date_to,
			)
		);
		return null;
	}

	/**
	 * Advanced analytics CSV/PDF export stream.
	 *
	 * @param array<string,mixed> $params type, format, date_from, date_to, _wpnonce.
	 * @return array<string,mixed>|null
	 */
	public static function export_analytics( array $params ) {
		if ( ! empty( $params['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( (string) $params['_wpnonce'], 'wp_rest' ) ) {
				return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
			}
		} elseif ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! class_exists( 'WebinoCRM_REST_Base' ) || ! WebinoCRM_REST_Base::can_access_route( 'reports' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		if ( ! class_exists( 'WebinoCRM_Advanced_Analytics' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول Analytics در دسترس نیست.', 'webinocrm' ) );
		}
		$parsed = self::parse_dates( $params );
		$type   = isset( $params['type'] ) ? sanitize_key( (string) $params['type'] ) : 'overview';
		if ( isset( $params['tab'] ) && 'overview' !== sanitize_key( (string) $params['tab'] ) ) {
			$type = sanitize_key( (string) $params['tab'] );
		}
		$format = isset( $params['format'] ) ? sanitize_key( (string) $params['format'] ) : 'csv';
		WebinoCRM_Advanced_Analytics::export_analytics(
			array(
				'type'      => $type,
				'format'    => $format,
				'date_from' => $parsed['date_from'],
				'date_to'   => $parsed['date_to'],
			)
		);
		return null;
	}

	public static function register_actions() {
		self::register_map(
			array(
				'webinocrm_get_reports'          => array( __CLASS__, 'get' ),
				'webinocrm_export_reports_csv'   => array( __CLASS__, 'export' ),
				'webinocrm_export_analytics'     => array( __CLASS__, 'export_analytics' ),
			)
		);
	}
}
