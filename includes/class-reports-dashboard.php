<?php
/**
 * WebinoCRM Reports Dashboard
 * آمار کلی، روزانه، ماهانه، توزیع وضعیت، رشد و صادرات CSV.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Reports_Dashboard {

	/**
	 * Get overall statistics for date range.
	 *
	 * @param string     $date_from Y-m-d.
	 * @param string     $date_to   Y-m-d.
	 * @param int|null   $user_id   Optional filter by author/assignee.
	 * @return array
	 */
	public static function get_overall_statistics( $date_from = null, $date_to = null, $user_id = null ) {
		if ( ! $date_from ) {
			$date_from = date( 'Y-m-01' );
		}
		if ( ! $date_to ) {
			$date_to = date( 'Y-m-d' );
		}
		$from_start = $date_from . ' 00:00:00';
		$to_end     = $date_to . ' 23:59:59';

		$args = array(
			'post_type'      => array( 'project', 'task', 'ticket', 'contract', 'lead' ),
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => $date_from,
					'before'    => $date_to,
					'inclusive' => true,
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $user_id ) {
			$args['author'] = $user_id;
		}

		$total_projects  = (int) wp_count_posts( 'project' )->publish;
		$total_tasks     = (int) wp_count_posts( 'task' )->publish;
		$total_tickets   = (int) wp_count_posts( 'ticket' )->publish;
		$total_contracts = (int) wp_count_posts( 'contract' )->publish;
		$user_counts     = count_users();
		$total_customers = isset( $user_counts['avail_roles']['customer'] ) ? (int) $user_counts['avail_roles']['customer'] : 0;
		$total_leads     = (int) wp_count_posts( 'lead' )->publish;

		$done_term    = get_term_by( 'slug', 'done', 'task_status' );
		$completed_tasks = $done_term ? (int) $done_term->count : 0;

		$project_statuses = get_terms( array( 'taxonomy' => 'project_status', 'hide_empty' => false ) );
		$project_by_status = array();
		if ( $project_statuses && ! is_wp_error( $project_statuses ) ) {
			foreach ( $project_statuses as $t ) {
				$project_by_status[] = array( 'name' => $t->name, 'count' => (int) $t->count );
			}
		}
		$task_statuses = get_terms( array( 'taxonomy' => 'task_status', 'hide_empty' => false ) );
		$task_by_status = array();
		if ( $task_statuses && ! is_wp_error( $task_statuses ) ) {
			foreach ( $task_statuses as $t ) {
				$task_by_status[] = array( 'name' => $t->name, 'count' => (int) $t->count );
			}
		}

		// Simple revenue: contracts count * fixed amount or meta sum if available
		$total_revenue = 0;
		$contract_ids = get_posts( array(
			'post_type'      => 'contract',
			'post_status'    => 'publish',
			'date_query'     => array( array( 'after' => $date_from, 'before' => $date_to, 'inclusive' => true ) ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		foreach ( $contract_ids as $cid ) {
			$amount = (float) get_post_meta( $cid, '_contract_amount', true );
			if ( ! $amount ) {
				$amount = 50000000;
			}
			$total_revenue += $amount;
		}

		return array(
			'total_projects'     => $total_projects,
			'total_tasks'        => $total_tasks,
			'completed_tasks'    => $completed_tasks,
			'total_tickets'      => $total_tickets,
			'total_contracts'    => $total_contracts,
			'total_leads'        => $total_leads,
			'total_customers'    => $total_customers,
			'total_revenue'      => $total_revenue,
			'currency'           => 'تومان',
			'project_by_status'  => $project_by_status,
			'task_by_status'     => $task_by_status,
		);
	}

	/**
	 * Get daily counts for chart (projects, tasks, etc. per day).
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array Array of { date, projects, tasks, tickets, contracts }.
	 */
	public static function get_daily_statistics( $date_from = null, $date_to = null ) {
		global $wpdb;
		if ( ! $date_from ) {
			$date_from = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $date_to ) {
			$date_to = date( 'Y-m-d' );
		}
		$from_start = $date_from . ' 00:00:00';
		$to_end     = $date_to . ' 23:59:59';

		$daily = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(post_date) AS d, post_type, COUNT(*) AS cnt
			FROM {$wpdb->posts}
			WHERE post_type IN ('project','task','ticket','contract')
			AND post_status = 'publish'
			AND post_date >= %s AND post_date <= %s
			GROUP BY d, post_type
			ORDER BY d ASC",
			$from_start,
			$to_end
		), OBJECT );

		$by_date = array();
		$t = strtotime( $date_from );
		$end = strtotime( $date_to );
		while ( $t <= $end ) {
			$d = date( 'Y-m-d', $t );
			$by_date[ $d ] = array( 'date' => $d, 'projects' => 0, 'tasks' => 0, 'tickets' => 0, 'contracts' => 0, 'total' => 0 );
			$t = strtotime( '+1 day', $t );
		}
		$pt_to_bucket = array(
			'project'  => 'projects',
			'task'     => 'tasks',
			'ticket'   => 'tickets',
			'contract' => 'contracts',
		);
		foreach ( $daily as $row ) {
			$d = $row->d;
			if ( ! isset( $by_date[ $d ] ) ) {
				$by_date[ $d ] = array( 'date' => $d, 'projects' => 0, 'tasks' => 0, 'tickets' => 0, 'contracts' => 0, 'total' => 0 );
			}
			$cnt = (int) $row->cnt;
			$bucket = isset( $pt_to_bucket[ $row->post_type ] ) ? $pt_to_bucket[ $row->post_type ] : null;
			if ( $bucket ) {
				$by_date[ $d ][ $bucket ] = $cnt;
				$by_date[ $d ]['total'] += $cnt;
			}
		}
		return array_values( $by_date );
	}

	/**
	 * Get monthly performance (last N months).
	 *
	 * @param int $months Number of months.
	 * @return array
	 */
	public static function get_monthly_performance( $months = 6 ) {
		global $wpdb;
		$months = max( 1, min( 24, (int) $months ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS m, post_type, COUNT(*) AS cnt
			FROM {$wpdb->posts}
			WHERE post_type IN ('project','task','ticket','contract')
			AND post_status = 'publish'
			AND post_date >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
			GROUP BY m, post_type
			ORDER BY m ASC",
			$months
		), OBJECT );

		$pt_to_bucket = array(
			'project'  => 'projects',
			'task'     => 'tasks',
			'ticket'   => 'tickets',
			'contract' => 'contracts',
		);
		$by_month = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $by_month[ $row->m ] ) ) {
				$by_month[ $row->m ] = array( 'month' => $row->m, 'projects' => 0, 'tasks' => 0, 'tickets' => 0, 'contracts' => 0, 'total' => 0 );
			}
			$cnt = (int) $row->cnt;
			$bucket = isset( $pt_to_bucket[ $row->post_type ] ) ? $pt_to_bucket[ $row->post_type ] : null;
			if ( $bucket ) {
				$by_month[ $row->m ][ $bucket ] = $cnt;
				$by_month[ $row->m ]['total'] += $cnt;
			}
		}
		return array_values( $by_month );
	}

	/**
	 * Get status distribution for projects and tasks.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array
	 */
	public static function get_status_distribution( $date_from = null, $date_to = null ) {
		$project_statuses = get_terms( array( 'taxonomy' => 'project_status', 'hide_empty' => false ) );
		$task_statuses    = get_terms( array( 'taxonomy' => 'task_status', 'hide_empty' => false ) );
		$out = array();
		if ( $project_statuses && ! is_wp_error( $project_statuses ) ) {
			foreach ( $project_statuses as $t ) {
				$out[] = array( 'label' => 'پروژه: ' . $t->name, 'count' => (int) $t->count );
			}
		}
		if ( $task_statuses && ! is_wp_error( $task_statuses ) ) {
			foreach ( $task_statuses as $t ) {
				$out[] = array( 'label' => 'وظیفه: ' . $t->name, 'count' => (int) $t->count );
			}
		}
		return $out;
	}

	/**
	 * Get growth vs previous period.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array
	 */
	public static function get_growth_statistics( $date_from, $date_to ) {
		$days = ( strtotime( $date_to ) - strtotime( $date_from ) ) / 86400;
		$prev_end   = date( 'Y-m-d', strtotime( $date_from . ' -1 day' ) );
		$prev_start = date( 'Y-m-d', strtotime( $prev_end . ' -' . (int) $days . ' days' ) );
		$current = self::get_overall_statistics( $date_from, $date_to );
		$previous = self::get_overall_statistics( $prev_start, $prev_end );
		$growth = function( $c, $p ) {
			if ( $p == 0 ) {
				return $c > 0 ? 100 : 0;
			}
			return round( ( ( $c - $p ) / $p ) * 100, 1 );
		};
		return array(
			'current'              => $current,
			'previous'             => $previous,
			'total_projects_growth' => $growth( $current['total_projects'], $previous['total_projects'] ),
			'total_tasks_growth'    => $growth( $current['total_tasks'], $previous['total_tasks'] ),
			'total_revenue_growth'  => $growth( $current['total_revenue'], $previous['total_revenue'] ),
			'completed_tasks_growth' => $growth( $current['completed_tasks'], $previous['completed_tasks'] ),
		);
	}

	/**
	 * Export to CSV (contracts or tasks in date range).
	 *
	 * @param array $args type (contracts|tasks), date_from, date_to. Outputs CSV and exits.
	 */
	public static function export_to_csv( $args = array() ) {
		$type = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : 'contracts';
		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : date( 'Y-m-01' );
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : date( 'Y-m-d' );

		$query_args = array(
			'post_type'      => $type === 'tasks' ? 'task' : 'contract',
			'post_status'    => 'publish',
			'date_query'     => array( array( 'after' => $date_from, 'before' => $date_to, 'inclusive' => true ) ),
			'posts_per_page' => 10000,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$posts = get_posts( $query_args );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=webinocrm-report-' . $type . '-' . date( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );

		if ( $type === 'tasks' ) {
			fputcsv( $out, array( 'ID', 'عنوان', 'وضعیت', 'پروژه', 'تاریخ ایجاد', 'نویسنده' ) );
			foreach ( $posts as $p ) {
				$terms = get_the_terms( $p->ID, 'task_status' );
				$status = $terms && ! is_wp_error( $terms ) ? ( $terms[0]->name ?? '' ) : '';
				$project_id = get_post_meta( $p->ID, '_project_id', true );
				$project_title = $project_id ? get_the_title( $project_id ) : '';
				$author = get_the_author_meta( 'display_name', $p->post_author );
				fputcsv( $out, array( $p->ID, $p->post_title, $status, $project_title, $p->post_date, $author ) );
			}
		} else {
			fputcsv( $out, array( 'ID', 'عنوان', 'مبلغ', 'تاریخ ایجاد', 'نویسنده' ) );
			foreach ( $posts as $p ) {
				$amount = get_post_meta( $p->ID, '_contract_amount', true );
				if ( ! $amount ) {
					$amount = 50000000;
				}
				$author = get_the_author_meta( 'display_name', $p->post_author );
				fputcsv( $out, array( $p->ID, $p->post_title, $amount, $p->post_date, $author ) );
			}
		}
		fclose( $out );
		exit;
	}
}
