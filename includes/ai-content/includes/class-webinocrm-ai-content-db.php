<?php
/**
 * AI Content database tables.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jobs, calendar, attribute templates, run history.
 */
final class WebinoCRM_AI_Content_Db {

	const OPTION_VERSION = 'webinocrm_ai_content_db_version';
	const SCHEMA_VERSION = '1.2.0';

	/**
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_ai_' . $suffix;
	}

	/**
	 * @return bool
	 */
	public static function jobs_table_exists() {
		global $wpdb;
		$table = self::table( 'jobs' );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return (string) $found === $table;
	}

	/**
	 * @return bool
	 */
	public static function proposals_table_exists() {
		global $wpdb;
		$table = self::table( 'proposals' );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return (string) $found === $table;
	}

	/**
	 * @return void
	 */
	public static function ensure_tables() {
		global $wpdb;

		$stored  = (string) get_option( self::OPTION_VERSION, '' );
		$missing = ! self::jobs_table_exists() || ! self::proposals_table_exists();
		if ( $stored === self::SCHEMA_VERSION && ! $missing ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta( self::sql_jobs( $p, $charset ) );
		dbDelta( self::sql_calendar( $p, $charset ) );
		dbDelta( self::sql_attr_templates( $p, $charset ) );
		dbDelta( self::sql_runs( $p, $charset ) );
		dbDelta( self::sql_proposals( $p, $charset ) );

		update_option( self::OPTION_VERSION, self::SCHEMA_VERSION, false );
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset Charset.
	 * @return string
	 */
	private static function sql_jobs( $p, $charset ) {
		return "CREATE TABLE {$p}webinocrm_ai_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_type varchar(64) NOT NULL DEFAULT '',
			target_type varchar(32) NOT NULL DEFAULT '',
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			payload longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			provider varchar(32) NOT NULL DEFAULT '',
			model varchar(128) NOT NULL DEFAULT '',
			tokens_in int(11) NOT NULL DEFAULT 0,
			tokens_out int(11) NOT NULL DEFAULT 0,
			cost_toman decimal(16,4) NOT NULL DEFAULT 0,
			error_message text NULL,
			result_summary text NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			started_at datetime NULL,
			finished_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY job_type (job_type),
			KEY target (target_type, target_id)
		) $charset;";
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset Charset.
	 * @return string
	 */
	private static function sql_calendar( $p, $charset ) {
		return "CREATE TABLE {$p}webinocrm_ai_calendar (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slot_date date NOT NULL,
			content_type varchar(20) NOT NULL DEFAULT 'blog',
			topic varchar(500) NOT NULL DEFAULT '',
			focus_keyword varchar(255) NOT NULL DEFAULT '',
			secondary_keywords text NULL,
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'planned',
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY slot_date (slot_date),
			KEY status (status),
			KEY content_type (content_type)
		) $charset;";
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset Charset.
	 * @return string
	 */
	private static function sql_attr_templates( $p, $charset ) {
		return "CREATE TABLE {$p}webinocrm_ai_attr_templates (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_cat_id bigint(20) unsigned NOT NULL,
			attribute_ids longtext NOT NULL,
			labels longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_cat_id (product_cat_id)
		) $charset;";
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset Charset.
	 * @return string
	 */
	private static function sql_runs( $p, $charset ) {
		return "CREATE TABLE {$p}webinocrm_ai_runs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			target_type varchar(32) NOT NULL DEFAULT '',
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			focus_keyword varchar(255) NOT NULL DEFAULT '',
			title_hash char(64) NOT NULL DEFAULT '',
			content_fingerprint char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY focus_keyword (focus_keyword),
			KEY title_hash (title_hash),
			KEY target (target_type, target_id)
		) $charset;";
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset Charset.
	 * @return string
	 */
	private static function sql_proposals( $p, $charset ) {
		return "CREATE TABLE {$p}webinocrm_ai_proposals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(32) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			current_json longtext NULL,
			proposed_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY kind_product (kind, product_id),
			KEY status (status),
			KEY product_id (product_id)
		) $charset;";
	}
}
