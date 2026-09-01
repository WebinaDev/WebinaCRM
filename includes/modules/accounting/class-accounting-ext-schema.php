<?php
/**
 * Extra accounting tables for Moadian / Hesabfa (CRM).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema ensure for accounting extensions.
 */
final class WebinoCRM_Accounting_Ext_Schema {
	const OPTION = 'webinocrm_accounting_ext_schema';
	const VERSION = '3.3.0';

	/**
	 * @return void
	 */
	public static function ensure() {
		if ( self::VERSION === get_option( self::OPTION, '' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'webinocrm_accounting_';
		$tables = array(
			"CREATE TABLE {$p}moadian_jobs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				invoice_id bigint(20) unsigned NOT NULL,
				action varchar(40) NOT NULL DEFAULT 'send',
				status varchar(30) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				uid varchar(64) NULL,
				reference_number varchar(64) NULL,
				last_error text NULL,
				run_after datetime NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY invoice_id (invoice_id),
				KEY status (status)
			) $c;",
			"CREATE TABLE {$p}moadian_log (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_id bigint(20) unsigned NULL,
				invoice_id bigint(20) unsigned NULL,
				direction varchar(20) NOT NULL DEFAULT 'out',
				payload longtext NULL,
				response longtext NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY job_id (job_id)
			) $c;",
			"CREATE TABLE {$p}hesabfa_map (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				entity varchar(40) NOT NULL,
				local_id bigint(20) unsigned NOT NULL,
				remote_id varchar(64) NOT NULL,
				meta longtext NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY entity_local (entity, local_id),
				KEY remote_id (remote_id)
			) $c;",
			"CREATE TABLE {$p}hesabfa_jobs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				direction varchar(20) NOT NULL DEFAULT 'push',
				entity varchar(40) NOT NULL,
				local_id bigint(20) unsigned NULL,
				payload longtext NULL,
				status varchar(30) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY status (status)
			) $c;",
			"CREATE TABLE {$p}hesabfa_log (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				action varchar(60) NOT NULL,
				request longtext NULL,
				response longtext NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id)
			) $c;",
			"CREATE TABLE {$p}bank_transfers (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				document_date date NOT NULL,
				from_account varchar(64) NULL,
				to_account varchar(64) NULL,
				amount decimal(18,2) NOT NULL DEFAULT 0,
				description text NULL,
				status varchar(30) NOT NULL DEFAULT 'posted',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id)
			) $c;",
		);
		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
		// Optional Moadian columns on invoices.
		$inv = $p . 'invoices';
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM {$inv}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$names = array_map(
			static function ( $c ) {
				return $c['Field'] ?? '';
			},
			is_array( $cols ) ? $cols : array()
		);
		if ( $names && ! in_array( 'moadian_status', $names, true ) ) {
			$wpdb->query( "ALTER TABLE {$inv} ADD COLUMN moadian_status varchar(30) NOT NULL DEFAULT 'none'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( $names && ! in_array( 'taxid', $names, true ) ) {
			$wpdb->query( "ALTER TABLE {$inv} ADD COLUMN taxid varchar(64) NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		update_option( self::OPTION, self::VERSION, false );
	}
}
