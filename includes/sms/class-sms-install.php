<?php
/**
 * SMS domain tables installer / upgrades.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates per-domain SMS tables and upgrades messages log.
 */
final class WebinoCRM_Sms_Install {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 3 );
	}

	/**
	 * @return void
	 */
	public static function maybe_upgrade() {
		global $wpdb;
		$flag = $wpdb->prefix . 'webinocrm_modirpayamak_domain_settings';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $flag ) . "'" ) !== $flag ) {
			self::create_tables();
		}
		self::create_secretaries_table();
		self::maybe_upgrade_messages_columns();
		self::maybe_upgrade_pattern_param_map();
		if ( class_exists( 'WebinoCRM_ModirPayamak_Manager', false ) ) {
			WebinoCRM_ModirPayamak_Manager::maybe_upgrade_domain_numbers_schema();
		}
	}

	/**
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'webinocrm_modirpayamak_' . $suffix;
	}

	/**
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$prefix = $wpdb->prefix . 'webinocrm_modirpayamak_';

		$table = $prefix . 'domain_settings';
		$sql   = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			scope varchar(20) NOT NULL,
			settings_json longtext NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY domain_scope (domain, scope)
		) $charset_collate;";
		dbDelta( $sql );

		$table = $prefix . 'message_templates';
		$sql   = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			scope varchar(30) NOT NULL,
			event_key varchar(80) NOT NULL,
			body longtext NOT NULL,
			pattern_code varchar(100) DEFAULT NULL,
			param_map longtext DEFAULT NULL,
			enabled tinyint(1) DEFAULT 1,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY domain_scope_event (domain, scope, event_key),
			KEY domain (domain)
		) $charset_collate;";
		dbDelta( $sql );

		$table = $prefix . 'pattern_registry';
		$sql   = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			scope varchar(30) NOT NULL,
			event_key varchar(80) NOT NULL,
			ippanel_code varchar(100) DEFAULT NULL,
			param_map longtext DEFAULT NULL,
			sync_status varchar(20) DEFAULT 'pending',
			last_error text,
			submitted_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY domain_scope_event (domain, scope, event_key),
			KEY sync_status (sync_status),
			KEY ippanel_code (ippanel_code)
		) $charset_collate;";
		dbDelta( $sql );

		$table = $prefix . 'newsletter_subscribers';
		$sql   = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			product_id bigint(20) NOT NULL DEFAULT 0,
			phone varchar(30) NOT NULL,
			status varchar(20) DEFAULT 'active',
			opt_in_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY domain_product_phone (domain, product_id, phone),
			KEY domain (domain),
			KEY product_id (product_id)
		) $charset_collate;";
		dbDelta( $sql );

		self::create_secretaries_table();
		self::maybe_upgrade_messages_columns();
	}

	/**
	 * @return void
	 */
	public static function create_secretaries_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table( 'secretaries' );
		$sql   = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			type varchar(40) NOT NULL,
			name varchar(191) DEFAULT '',
			keywords text,
			reply_body longtext,
			pattern_code varchar(100) DEFAULT '',
			forward_to varchar(40) DEFAULT '',
			enabled tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY domain (domain),
			KEY type (type)
		) $charset_collate;";
		dbDelta( $sql );
	}

	/**
	 * @return void
	 */
	public static function maybe_upgrade_messages_columns() {
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) !== $table ) {
			return;
		}
		$cols = $wpdb->get_col( "DESC $table", 0 );
		if ( ! is_array( $cols ) ) {
			return;
		}
		if ( ! in_array( 'context_type', $cols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD COLUMN context_type varchar(50) DEFAULT NULL AFTER status" );
		}
		if ( ! in_array( 'context_id', $cols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD COLUMN context_id varchar(50) DEFAULT NULL AFTER context_type" );
		}
		if ( ! in_array( 'recipient_role', $cols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD COLUMN recipient_role varchar(20) DEFAULT NULL AFTER context_id" );
		}
		if ( ! in_array( 'event_key', $cols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD COLUMN event_key varchar(80) DEFAULT NULL AFTER recipient_role" );
		}
	}

	/**
	 * Add param_map columns for pattern variable binding.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_pattern_param_map() {
		global $wpdb;
		foreach ( array( 'pattern_registry', 'message_templates' ) as $suffix ) {
			$table = self::table( $suffix );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) !== $table ) {
				continue;
			}
			$cols = $wpdb->get_col( "DESC $table", 0 );
			if ( ! is_array( $cols ) || in_array( 'param_map', $cols, true ) ) {
				continue;
			}
			$after = ( 'pattern_registry' === $suffix ) ? 'ippanel_code' : 'pattern_code';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $table ADD COLUMN param_map longtext DEFAULT NULL AFTER $after" );
		}
	}
}

WebinoCRM_Sms_Install::init();
