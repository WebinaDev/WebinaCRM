<?php
/**
 * Official Tamin occupation codes (JOBCODE → title).
 *
 * Seeded from data/tamin-jobs.csv.gz (~201k rows). Lookup only — never load full list in SPA.
 *
 * @package WebinoDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tamin jobs reference.
 */
final class WebinoCRM_Hrm_Tamin_Jobs {

	const SEED_OPTION = 'webinocrm_hrm_tamin_jobs_seeded';

	/**
	 * Seed from gzipped CSV if empty / not marked.
	 *
	 * @return int Rows inserted (0 if skipped).
	 */
	public static function maybe_seed() {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'tamin_jobs' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 1000 ) {
			update_option( self::SEED_OPTION, '1.4.0', false );
			return 0;
		}
		$path = WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/data/tamin-jobs.csv.gz';
		if ( ! is_readable( $path ) ) {
			return 0;
		}
		$fh = @gzopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $fh ) {
			return 0;
		}
		$header = fgetcsv( $fh );
		if ( ! is_array( $header ) ) {
			gzclose( $fh );
			return 0;
		}
		$inserted = 0;
		$batch    = array();
		while ( ( $row = fgetcsv( $fh ) ) !== false ) {
			if ( count( $row ) < 2 ) {
				continue;
			}
			$code  = sanitize_text_field( (string) $row[0] );
			$title = sanitize_text_field( (string) $row[1] );
			if ( '' === $code || '' === $title ) {
				continue;
			}
			$batch[] = $wpdb->prepare( '(%s,%s)', $code, $title );
			if ( count( $batch ) >= 500 ) {
				$inserted += self::flush_batch( $table, $batch );
				$batch     = array();
			}
		}
		if ( $batch ) {
			$inserted += self::flush_batch( $table, $batch );
		}
		gzclose( $fh );
		update_option( self::SEED_OPTION, '1.4.0', false );
		return $inserted;
	}

	/**
	 * @param string        $table Table.
	 * @param array<int,string> $batch Prepared tuples.
	 * @return int
	 */
	private static function flush_batch( $table, array $batch ) {
		global $wpdb;
		$sql = "INSERT IGNORE INTO {$table} (job_code, title) VALUES " . implode( ',', $batch ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return count( $batch );
	}

	/**
	 * Search jobs (max 50).
	 *
	 * @param string $q Query.
	 * @param int    $limit Limit.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function search( $q, $limit = 50 ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'tamin_jobs' );
		$q     = trim( (string) $q );
		$limit = min( 50, max( 1, absint( $limit ) ) );
		if ( '' === $q ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT id, job_code, title FROM {$table} ORDER BY job_code ASC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array( 'items' => is_array( $rows ) ? $rows : array(), 'total' => $total );
		}
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, job_code, title FROM {$table} WHERE job_code LIKE %s OR title LIKE %s ORDER BY job_code ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like,
				$like,
				$limit
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE job_code LIKE %s OR title LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like,
				$like
			)
		);
		return array( 'items' => is_array( $rows ) ? $rows : array(), 'total' => $total );
	}

	/**
	 * @param string $code Code.
	 * @return array<string,mixed>|null
	 */
	public static function get_by_code( $code ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'tamin_jobs' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE job_code = %s", sanitize_text_field( (string) $code ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}
}
