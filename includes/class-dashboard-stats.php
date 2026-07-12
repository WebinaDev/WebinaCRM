<?php
/**
 * Dashboard stats for SPA and PHP dashboard.
 * Returns the same stats array used by dashboard-system-manager partial (cached).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Dashboard_Stats {

	/**
	 * Load helpers required for stats queries.
	 *
	 * @return void
	 */
	private static function ensure_dependencies() {
		if ( ! defined( 'WEBINOCRM_PLUGIN_DIR' ) ) {
			return;
		}
		if ( ! class_exists( 'WebinoCRM_Term_Helper' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/helpers/class-term-helper.php';
		}
	}

	/**
	 * Month label for dashboard revenue chart.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function get_revenue_month_label( $timestamp ) {
		$is_fa = function_exists( 'webino_get_current_language' ) && webino_get_current_language() === 'fa';
		if ( $is_fa && function_exists( 'crm_format_date' ) ) {
			$label = crm_format_date( $timestamp, 'F' );
			if ( is_string( $label ) && $label !== '' ) {
				return $label;
			}
		}
		return gmdate_i18n( 'F', $timestamp );
	}

	/**
	 * Get system manager dashboard stats (cached 15 min).
	 *
	 * @return array Stats array with total_projects, completed_projects, total_tasks, etc.
	 */
	public static function get_system_manager_stats() {
		self::ensure_dependencies();
		$current_lang = function_exists( 'webino_get_current_language' ) ? webino_get_current_language() : 'en';
		$cache_suffix = '_' . $current_lang;
		$cache_key    = 'webino_system_manager_stats_v4' . $cache_suffix;

		$stats = get_transient( $cache_key );
		if ( false !== $stats && is_array( $stats ) ) {
			return $stats;
		}

		// Project stats
		$all_projects    = get_posts( [ 'post_type' => 'project', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
		$total_projects  = count( $all_projects );
		$new_projects_this_month = 0;
		$completed_projects = 0;
		$in_progress_projects = 0;
		$pending_projects = 0;
		$current_month_start = gmdate( 'Y-m-01' );

		foreach ( $all_projects as $project ) {
			if ( strtotime( $project->post_date ) >= strtotime( $current_month_start ) ) {
				$new_projects_this_month++;
			}
			$status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $project->ID, 'project_status', 'pending' );
			if ( $status_slug === 'completed' || $status_slug === 'done' ) {
				$completed_projects++;
			} elseif ( $status_slug === 'in-progress' || $status_slug === 'active' ) {
				$in_progress_projects++;
			} else {
				$pending_projects++;
			}
		}

		$last_month_projects = count( get_posts( [
			'post_type'      => 'project',
			'posts_per_page' => -1,
			'date_query'     => [
				[
					'after'     => gmdate( 'Y-m-01', strtotime( '-1 month' ) ),
					'before'    => gmdate( 'Y-m-t', strtotime( '-1 month' ) ),
					'inclusive' => true,
				],
			],
		] ) );
		$new_projects_growth = $last_month_projects > 0 ? ( ( $new_projects_this_month - $last_month_projects ) / $last_month_projects ) * 100 : 0;

		// Task stats
		$all_tasks       = get_posts( [ 'post_type' => 'task', 'posts_per_page' => -1 ] );
		$total_tasks     = count( $all_tasks );
		$completed_tasks = 0;
		$pending_tasks   = 0;
		$overdue_tasks   = 0;
		foreach ( $all_tasks as $task ) {
			$status = WebinoCRM_Term_Helper::get_first_term_slug( $task->ID, 'task_status', 'todo' );
			if ( $status === 'done' ) {
				$completed_tasks++;
			} else {
				$pending_tasks++;
				$due_date = get_post_meta( $task->ID, '_due_date', true );
				if ( $due_date && strtotime( $due_date ) < strtotime( 'today' ) ) {
					$overdue_tasks++;
				}
			}
		}

		// Customer count
		$counts = count_users();
		$avail_roles = isset( $counts['avail_roles'] ) && is_array( $counts['avail_roles'] ) ? $counts['avail_roles'] : array();
		$customer_count = (int) ( $avail_roles['customer'] ?? $avail_roles['client'] ?? 0 );
		$new_customers_this_month = count( get_users( [
			'role'       => 'customer',
			'date_query' => [ [ 'after' => gmdate( 'Y-m-01' ), 'inclusive' => true ] ],
		] ) );

		// Ticket stats
		$all_tickets     = get_posts( [ 'post_type' => 'ticket', 'posts_per_page' => -1 ] );
		$total_tickets   = count( $all_tickets );
		$open_tickets    = 0;
		$resolved_tickets = 0;
		foreach ( $all_tickets as $ticket ) {
			$status = get_post_meta( $ticket->ID, '_ticket_status', true ) ?: 'open';
			if ( in_array( $status, [ 'open', 'pending' ], true ) ) {
				$open_tickets++;
			} else {
				$resolved_tickets++;
			}
		}

		// Financial
		$income_this_month = 0;
		$income_last_month = 0;
		$total_revenue     = 0;
		$current_month_start = gmdate( 'Y-m-01' );
		$last_month_start = gmdate( 'Y-m-01', strtotime( '-1 month' ) );
		$last_month_end   = gmdate( 'Y-m-t', strtotime( '-1 month' ) );
		$contracts        = get_posts( [ 'post_type' => 'contract', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
		foreach ( $contracts as $contract ) {
			$amount = (float) get_post_meta( $contract->ID, '_total_amount', true );
			$total_revenue += $amount;
			$installments = get_post_meta( $contract->ID, '_installments', true );
			if ( is_array( $installments ) ) {
				foreach ( $installments as $inst ) {
					if ( ! is_array( $inst ) ) {
						continue;
					}
					if ( ( $inst['status'] ?? 'pending' ) === 'paid' && isset( $inst['due_date'] ) ) {
						$due_date = strtotime( $inst['due_date'] );
						if ( $due_date >= strtotime( $current_month_start ) ) {
							$income_this_month += (int) ( $inst['amount'] ?? 0 );
						}
						if ( $due_date >= strtotime( $last_month_start ) && $due_date <= strtotime( $last_month_end ) ) {
							$income_last_month += (int) ( $inst['amount'] ?? 0 );
						}
					}
				}
			}
		}
		$revenue_growth   = $income_last_month > 0 ? ( ( $income_this_month - $income_last_month ) / $income_last_month ) * 100 : 0;
		$completion_rate  = $total_tasks > 0 ? ( $completed_tasks / $total_tasks ) * 100 : 0;

		$stats = [
			'total_projects'          => $total_projects,
			'new_projects_this_month' => $new_projects_this_month,
			'new_projects_growth'     => $new_projects_growth,
			'completed_projects'      => $completed_projects,
			'in_progress_projects'    => $in_progress_projects,
			'pending_projects'        => $pending_projects,
			'total_tasks'             => $total_tasks,
			'completed_tasks'        => $completed_tasks,
			'pending_tasks'           => $pending_tasks,
			'overdue_tasks'           => $overdue_tasks,
			'completion_rate'         => $completion_rate,
			'customer_count'          => $customer_count,
			'new_customers_this_month' => $new_customers_this_month,
			'total_tickets'           => $total_tickets,
			'open_tickets'            => $open_tickets,
			'resolved_tickets'        => $resolved_tickets,
			'income_this_month'       => $income_this_month,
			'total_revenue'           => $total_revenue,
			'revenue_growth'          => $revenue_growth,
		];

		set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );
		return $stats;
	}

	/**
	 * Get full dashboard data for system manager (stats, team, running projects, daily tasks, projects table, charts).
	 *
	 * @return array Full dashboard data.
	 */
	public static function get_system_manager_dashboard_full() {
		self::ensure_dependencies();
		$stats  = self::get_system_manager_stats();
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [ 'stats' => $stats, 'team' => [], 'running_projects' => [], 'daily_tasks' => [], 'projects_table' => [], 'revenue_data' => [], 'monthly_goals' => [] ];
		}

		$cache_key = 'webino_dashboard_full_v1_' . $user_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return array_merge( [ 'stats' => $stats ], $cached );
		}

		// Team members
		$team_members   = get_users( [ 'role__in' => [ 'team_member', 'system_manager', 'administrator' ] ] );
		$team_data      = [];
		foreach ( $team_members as $member ) {
			$member_tasks = get_posts( [
				'post_type'      => 'task',
				'posts_per_page' => -1,
				'meta_query'     => [ [ 'key' => '_assigned_to', 'value' => $member->ID, 'compare' => '=' ] ],
			] );
			$member_completed = 0;
			foreach ( $member_tasks as $task ) {
				if ( WebinoCRM_Term_Helper::get_first_term_slug( $task->ID, 'task_status' ) === 'done' ) {
					$member_completed++;
				}
			}
			$member_total = count( $member_tasks );
			$last_activity = get_user_meta( $member->ID, 'last_activity', true );
			$is_online = $last_activity && ( time() - (int) $last_activity ) < 900;
			$role_display = in_array( 'system_manager', (array) $member->roles, true ) || in_array( 'administrator', (array) $member->roles, true )
				? __( 'Team Lead', 'webinocrm' ) : __( 'Team Member', 'webinocrm' );
			$team_data[] = [
				'id'             => $member->ID,
				'name'           => $member->display_name,
				'email'          => $member->user_email,
				'avatar'         => get_avatar_url( $member->ID, [ 'size' => 32 ] ),
				'role'           => $role_display,
				'total_tasks'    => $member_total,
				'completed_tasks'=> $member_completed,
				'progress'       => $member_total > 0 ? round( ( $member_completed / $member_total ) * 100, 1 ) : 0,
				'is_online'      => $is_online,
			];
		}
		usort( $team_data, fn( $a, $b ) => $b['completed_tasks'] - $a['completed_tasks'] );
		$team_data = array_slice( $team_data, 0, 6 );

		// Running projects
		$running_args = [
			'post_type'      => 'project',
			'posts_per_page' => 5,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		];
		if ( taxonomy_exists( 'project_status' ) ) {
			$running_args['tax_query'] = [
				[
					'taxonomy' => 'project_status',
					'field'    => 'slug',
					'terms'    => [ 'in-progress', 'active' ],
					'operator' => 'IN',
				],
			];
		}
		$running = get_posts( $running_args );
		$running_projects = [];
		foreach ( $running as $project ) {
			$project_tasks = get_posts( [ 'post_type' => 'task', 'posts_per_page' => -1, 'meta_query' => [ [ 'key' => '_project_id', 'value' => $project->ID, 'compare' => '=' ] ] ] );
			$done = 0;
			foreach ( $project_tasks as $task ) {
				if ( WebinoCRM_Term_Helper::get_first_term_slug( $task->ID, 'task_status' ) === 'done' ) {
					$done++;
				}
			}
			$total = count( $project_tasks );
			$assigned = get_post_meta( $project->ID, '_assigned_members', true );
			$running_projects[] = [
				'id'       => $project->ID,
				'title'    => $project->post_title,
				'excerpt'  => wp_trim_words( $project->post_content ?? '', 15 ),
				'progress' => $total > 0 ? round( ( $done / $total ) * 100, 0 ) : 0,
				'done'     => $done,
				'total'    => $total,
				'assigned' => is_array( $assigned ) ? $assigned : [],
				'modified' => $project->post_modified,
			];
		}

		// Daily tasks
		$today_str = gmdate( 'Y-m-d' );
		$tomorrow  = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		$daily_raw = get_posts( [
			'post_type'   => 'task',
			'posts_per_page' => 10,
			'meta_query'  => [
				'relation' => 'OR',
				[ 'key' => '_due_date', 'value' => $today_str, 'compare' => '=' ],
				[ 'key' => '_due_date', 'value' => $tomorrow, 'compare' => '=' ],
			],
			'orderby' => 'meta_value',
			'meta_key' => '_due_date',
			'order'   => 'ASC',
		] );
		$daily_tasks = [];
		foreach ( $daily_raw as $task ) {
			$due = get_post_meta( $task->ID, '_due_date', true );
			$time = get_post_meta( $task->ID, '_due_time', true );
			$proj_id = get_post_meta( $task->ID, '_project_id', true );
			$project_title = $proj_id ? ( get_the_title( $proj_id ) ?: '' ) : '';
			$daily_tasks[] = [
				'id'       => $task->ID,
				'title'    => $task->post_title,
				'due_date' => $due,
				'due_time' => $time,
				'project'  => $project_title,
			];
		}

		// Projects table
		$projects_raw = get_posts( [ 'post_type' => 'project', 'posts_per_page' => 10, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ] );
		$projects_table = [];
		foreach ( $projects_raw as $project ) {
			$project_tasks = get_posts( [ 'post_type' => 'task', 'posts_per_page' => -1, 'meta_query' => [ [ 'key' => '_project_id', 'value' => $project->ID, 'compare' => '=' ] ] ] );
			$done = 0;
			foreach ( $project_tasks as $task ) {
				if ( WebinoCRM_Term_Helper::get_first_term_slug( $task->ID, 'task_status' ) === 'done' ) {
					$done++;
				}
			}
			$total = count( $project_tasks );
			$status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $project->ID, 'project_status', 'pending' );
			$status_name = WebinoCRM_Term_Helper::get_first_term_name( $project->ID, 'project_status', __( 'Pending', 'webinocrm' ) );
			$assigned = get_post_meta( $project->ID, '_assigned_members', true );
			$due_date = get_post_meta( $project->ID, '_due_date', true );
			$projects_table[] = [
				'id'          => $project->ID,
				'title'       => $project->post_title,
				'done_tasks'  => $done,
				'total_tasks' => $total,
				'progress'    => $total > 0 ? round( ( $done / $total ) * 100, 0 ) : 0,
				'status'      => $status_name,
				'status_slug' => $status_slug,
				'assigned'    => is_array( $assigned ) ? $assigned : [],
				'due_date'    => $due_date,
			];
		}

		// Revenue data (6 months)
		$contracts   = get_posts( [ 'post_type' => 'contract', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
		$revenue_data = [];
		$month_labels = [];
		for ( $i = 5; $i >= 0; $i-- ) {
			$month_start = gmdate( 'Y-m-01', strtotime( "-$i months" ) );
			$month_end   = gmdate( 'Y-m-t', strtotime( "-$i months" ) );
			$month_revenue = 0;
			foreach ( $contracts as $contract ) {
				$installments = get_post_meta( $contract->ID, '_installments', true );
				if ( ! is_array( $installments ) ) {
					continue;
				}
				foreach ( $installments as $inst ) {
					if ( ! is_array( $inst ) ) {
						continue;
					}
					if ( ( $inst['status'] ?? 'pending' ) === 'paid' && ! empty( $inst['due_date'] ) ) {
						$ts = strtotime( $inst['due_date'] );
						if ( $ts >= strtotime( $month_start ) && $ts <= strtotime( $month_end ) ) {
							$month_revenue += (int) ( $inst['amount'] ?? 0 );
						}
					}
				}
			}
			$revenue_data[] = $month_revenue;
			$month_labels[] = self::get_revenue_month_label( strtotime( $month_start ) );
		}

		// Monthly goals
		$current_month_projects = count( get_posts( [ 'post_type' => 'project', 'posts_per_page' => -1, 'date_query' => [ [ 'after' => gmdate( 'Y-m-01' ), 'inclusive' => true ] ] ] ) );
		$monthly_goals = [
			'new_projects' => $current_month_projects,
			'completed'    => $stats['completed_projects'] ?? 0,
			'pending'      => $stats['pending_projects'] ?? 0,
		];

		$out = [
			'team'           => $team_data,
			'running_projects' => $running_projects,
			'daily_tasks'    => $daily_tasks,
			'projects_table' => $projects_table,
			'revenue_data'   => [ 'values' => $revenue_data, 'labels' => $month_labels ],
			'monthly_goals'  => $monthly_goals,
		];
		set_transient( $cache_key, $out, 5 * MINUTE_IN_SECONDS );
		return array_merge( [ 'stats' => $stats ], $out );
	}

	/**
	 * Get team member dashboard stats (tasks, projects, tickets for current user).
	 *
	 * @return array Stats array.
	 */
	public static function get_team_member_stats() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [];
		}

		$cache_key = 'webino_team_member_stats_' . $user_id;
		$stats     = get_transient( $cache_key );
		if ( false !== $stats && is_array( $stats ) ) {
			return $stats;
		}

		$all_tasks = get_posts( [
			'post_type'      => 'task',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => '_assigned_to', 'value' => $user_id, 'compare' => '=' ],
			],
		] );

		$total_tasks     = count( $all_tasks );
		$completed_tasks = 0;
		$in_progress     = 0;
		$overdue         = 0;
		$today_tasks     = 0;
		$project_ids     = [];
		$today_str       = gmdate( 'Y-m-d' );
		$week_end        = gmdate( 'Y-m-d', strtotime( '+7 days' ) );

		foreach ( $all_tasks as $task ) {
			$project_id = get_post_meta( $task->ID, '_project_id', true );
			if ( $project_id ) {
				$project_ids[] = $project_id;
			}
			$status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $task->ID, 'task_status', 'todo' );
			if ( $status_slug === 'done' ) {
				$completed_tasks++;
			} elseif ( $status_slug === 'in-progress' ) {
				$in_progress++;
			} else {
				$due_date = get_post_meta( $task->ID, '_due_date', true );
				if ( $due_date ) {
					if ( $due_date < $today_str ) {
						$overdue++;
					} elseif ( $due_date === $today_str ) {
						$today_tasks++;
					}
				}
			}
		}

		$total_projects = count( array_unique( $project_ids ) );
		$completion_rate = $total_tasks > 0 ? ( $completed_tasks / $total_tasks ) * 100 : 0;

		$my_tickets = get_posts( [
			'post_type'      => 'ticket',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => '_assigned_to', 'value' => $user_id, 'compare' => '=' ],
			],
		] );
		$total_tickets = count( $my_tickets );
		$open_tickets  = 0;
		foreach ( $my_tickets as $t ) {
			$st = get_post_meta( $t->ID, '_ticket_status', true ) ?: 'open';
			if ( in_array( $st, [ 'open', 'pending' ], true ) ) {
				$open_tickets++;
			}
		}

		$stats = [
			'total_tasks'      => $total_tasks,
			'completed_tasks'  => $completed_tasks,
			'in_progress_tasks'=> $in_progress,
			'overdue_tasks'    => $overdue,
			'today_tasks'      => $today_tasks,
			'total_projects'   => $total_projects,
			'completion_rate'  => round( $completion_rate, 1 ),
			'total_tickets'    => $total_tickets,
			'open_tickets'     => $open_tickets,
		];

		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
		return $stats;
	}

	/**
	 * Get client dashboard stats (projects, tickets, contracts for current user).
	 *
	 * @return array Stats array.
	 */
	public static function get_client_stats() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [];
		}

		$cache_key = 'webino_client_stats_' . $user_id;
		$stats     = get_transient( $cache_key );
		if ( false !== $stats && is_array( $stats ) ) {
			return $stats;
		}

		$my_projects = get_posts( [
			'post_type'      => 'project',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => '_customer_id', 'value' => $user_id, 'compare' => '=' ],
			],
		] );
		$total_projects   = count( $my_projects );
		$active_projects  = 0;
		$completed_projects = 0;
		foreach ( $my_projects as $p ) {
			$status = get_post_meta( $p->ID, '_project_status', true );
			if ( $status === 'active' || $status === 'in-progress' ) {
				$active_projects++;
			} elseif ( $status === 'completed' ) {
				$completed_projects++;
			}
		}

		$my_tickets = get_posts( [
			'post_type'  => 'ticket',
			'posts_per_page' => -1,
			'author'     => $user_id,
		] );
		$total_tickets = count( $my_tickets );
		$open_tickets  = 0;
		foreach ( $my_tickets as $t ) {
			$st = get_post_meta( $t->ID, '_ticket_status', true ) ?: 'open';
			if ( in_array( $st, [ 'open', 'pending' ], true ) ) {
				$open_tickets++;
			}
		}

		$my_contracts = get_posts( [
			'post_type'      => 'contract',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => '_customer_id', 'value' => $user_id, 'compare' => '=' ],
			],
		] );
		$total_contracts = count( $my_contracts );
		$total_value     = 0;
		$paid_amount     = 0;
		$pending_amount  = 0;
		foreach ( $my_contracts as $c ) {
			$amount = (float) get_post_meta( $c->ID, '_total_amount', true );
			$total_value += $amount;
			$installments = get_post_meta( $c->ID, '_installments', true );
			if ( is_array( $installments ) ) {
				foreach ( $installments as $inst ) {
					if ( ( $inst['status'] ?? 'pending' ) === 'paid' ) {
						$paid_amount += (int) ( $inst['amount'] ?? 0 );
					} else {
						$pending_amount += (int) ( $inst['amount'] ?? 0 );
					}
				}
			}
		}

		$stats = [
			'total_projects'    => $total_projects,
			'active_projects'   => $active_projects,
			'completed_projects'=> $completed_projects,
			'total_tickets'     => $total_tickets,
			'open_tickets'      => $open_tickets,
			'total_contracts'   => $total_contracts,
			'total_value'       => $total_value,
			'paid_amount'       => $paid_amount,
			'pending_amount'    => $pending_amount,
		];

		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
		return $stats;
	}
}
