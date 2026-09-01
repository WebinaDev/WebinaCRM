<?php
/**
 * HRM schema migrations (Iranian payroll extensions).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Schema {

	const SCHEMA_OPTION = 'webinocrm_hrm_schema_version';
	const SCHEMA_VERSION  = '1.2.0';

	/**
	 * Run dbDelta when version changes.
	 */
	public static function ensure() {
		$current = (string) get_option( self::SCHEMA_OPTION, '' );
		if ( self::SCHEMA_VERSION === $current ) {
			return;
		}
		self::install();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Create / alter HRM payroll tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'webinocrm_hrm_';

		$tables = array(
			"CREATE TABLE {$p}workshops (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code varchar(40) NOT NULL,
				name varchar(191) NOT NULL,
				row_code varchar(20) DEFAULT NULL,
				branch_code varchar(40) DEFAULT NULL,
				branch_name varchar(191) DEFAULT NULL,
				address text DEFAULT NULL,
				hardship_rate decimal(8,4) NOT NULL DEFAULT 0,
				is_default tinyint(1) NOT NULL DEFAULT 0,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY code (code)
			) $charset;",
			"CREATE TABLE {$p}tamin_jobs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_code varchar(40) NOT NULL,
				title varchar(255) NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY job_code (job_code),
				KEY title (title(100))
			) $charset;",
			"CREATE TABLE {$p}employment_decrees (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				decree_no varchar(60) NOT NULL,
				decree_type varchar(40) NOT NULL DEFAULT 'hire',
				issue_date date DEFAULT NULL,
				effective_from date NOT NULL,
				effective_to date DEFAULT NULL,
				status varchar(30) NOT NULL DEFAULT 'draft',
				workshop_id bigint(20) unsigned DEFAULT NULL,
				job_title varchar(191) DEFAULT NULL,
				job_code varchar(40) DEFAULT NULL,
				department varchar(191) DEFAULT NULL,
				contract_type varchar(40) DEFAULT NULL,
				daily_wage decimal(18,2) NOT NULL DEFAULT 0,
				base_salary decimal(18,2) NOT NULL DEFAULT 0,
				benefit_food decimal(18,2) NOT NULL DEFAULT 0,
				benefit_housing decimal(18,2) NOT NULL DEFAULT 0,
				benefit_child decimal(18,2) NOT NULL DEFAULT 0,
				benefit_marriage decimal(18,2) NOT NULL DEFAULT 0,
				benefit_seniority decimal(18,2) NOT NULL DEFAULT 0,
				benefit_transport decimal(18,2) NOT NULL DEFAULT 0,
				benefit_responsibility decimal(18,2) NOT NULL DEFAULT 0,
				benefit_other_insurable decimal(18,2) NOT NULL DEFAULT 0,
				benefit_other_non_insurable decimal(18,2) NOT NULL DEFAULT 0,
				hardship_rate decimal(8,4) NOT NULL DEFAULT 0,
				items_json longtext DEFAULT NULL,
				previous_decree_id bigint(20) unsigned DEFAULT NULL,
				notes text DEFAULT NULL,
				created_by bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY decree_no (decree_no),
				KEY effective_from (effective_from),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$p}payroll_attendance (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				jalali_year smallint(5) unsigned NOT NULL,
				jalali_month tinyint(3) unsigned NOT NULL,
				absent_days decimal(8,2) NOT NULL DEFAULT 0,
				leave_days decimal(8,2) NOT NULL DEFAULT 0,
				unpaid_leave_days decimal(8,2) NOT NULL DEFAULT 0,
				sick_leave_days decimal(8,2) NOT NULL DEFAULT 0,
				overtime_hours decimal(10,2) NOT NULL DEFAULT 0,
				night_hours decimal(10,2) NOT NULL DEFAULT 0,
				holiday_hours decimal(10,2) NOT NULL DEFAULT 0,
				volume_qty decimal(18,4) NOT NULL DEFAULT 0,
				piece_rate decimal(18,4) NOT NULL DEFAULT 0,
				loan_deduction decimal(18,2) NOT NULL DEFAULT 0,
				advance_deduction decimal(18,2) NOT NULL DEFAULT 0,
				notes text DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY user_month (user_id, jalali_year, jalali_month)
			) $charset;",
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		self::ensure_payroll_columns( $p );
		self::ensure_portal_tables( $p );
		self::ensure_accounting_extras();
	}

	/**
	 * Extend existing payroll tables for Iranian calc.
	 *
	 * @param string $p Table prefix.
	 */
	private static function ensure_payroll_columns( $p ) {
		global $wpdb;

		$alters = array(
			"{$p}payroll_settings" => array(
				'employer_insurance_expense_account_id bigint(20) DEFAULT 0',
				'unemployment_expense_account_id bigint(20) DEFAULT 0',
				'unemployment_payable_account_id bigint(20) DEFAULT 0',
				'default_workshop_id bigint(20) DEFAULT 0',
				'config_json longtext DEFAULT NULL',
			),
			"{$p}payroll_runs" => array(
				'period_year int(11) DEFAULT 0',
				'period_month int(11) DEFAULT 0',
				'jalali_year smallint(5) unsigned DEFAULT 0',
				'jalali_month tinyint(3) unsigned DEFAULT 0',
				'workshop_id bigint(20) unsigned DEFAULT NULL',
				'year_month varchar(7) DEFAULT NULL',
				'days_in_month tinyint(3) unsigned DEFAULT 30',
				'total_insurable decimal(15,2) DEFAULT 0',
				'total_emp_ins decimal(15,2) DEFAULT 0',
				'total_er_ins decimal(15,2) DEFAULT 0',
				'total_unemployment decimal(15,2) DEFAULT 0',
				'total_tax decimal(15,2) DEFAULT 0',
				'list_status varchar(30) DEFAULT NULL',
				'calculated_at datetime DEFAULT NULL',
				'approved_by bigint(20) DEFAULT 0',
				'approved_at datetime DEFAULT NULL',
				'created_by bigint(20) DEFAULT 0',
				'updated_at datetime DEFAULT NULL',
			),
			"{$p}payslips" => array(
				'decree_id bigint(20) unsigned DEFAULT NULL',
				'days_worked decimal(8,2) DEFAULT 0',
				'insurable_wage decimal(15,2) DEFAULT 0',
				'insurable_capped decimal(15,2) DEFAULT 0',
				'employee_insurance decimal(15,2) DEFAULT 0',
				'employer_insurance decimal(15,2) DEFAULT 0',
				'unemployment_insurance decimal(15,2) DEFAULT 0',
				'taxable_income decimal(15,2) DEFAULT 0',
				'tax decimal(15,2) DEFAULT 0',
				'overtime decimal(15,2) DEFAULT 0',
				'overtime_night decimal(15,2) DEFAULT 0',
				'overtime_holiday decimal(15,2) DEFAULT 0',
				'volume_pay decimal(15,2) DEFAULT 0',
				'volume_qty decimal(18,4) DEFAULT 0',
				'other_deductions decimal(15,2) DEFAULT 0',
				'loan_deduction decimal(15,2) DEFAULT 0',
				'advance_deduction decimal(15,2) DEFAULT 0',
				'items_json longtext DEFAULT NULL',
				'meta_json longtext DEFAULT NULL',
				'components_json longtext DEFAULT NULL',
				'deposit_date date DEFAULT NULL',
				'created_at datetime DEFAULT NULL',
				'updated_at datetime DEFAULT NULL',
			),
			"{$p}salary_components" => array(
				'name varchar(191) DEFAULT NULL',
				'is_taxable tinyint(1) DEFAULT 1',
				'is_fixed tinyint(1) DEFAULT 1',
				'formula text DEFAULT NULL',
				'is_active tinyint(1) DEFAULT 1',
				'created_at datetime DEFAULT NULL',
				'updated_at datetime DEFAULT NULL',
			),
			"{$p}employee_salaries" => array(
				'component_id bigint(20) DEFAULT 0',
				'amount decimal(15,2) DEFAULT 0',
				'effective_to date DEFAULT NULL',
				'created_at datetime DEFAULT NULL',
				'updated_at datetime DEFAULT NULL',
			),
		);

		foreach ( $alters as $table => $columns ) {
			foreach ( $columns as $col_def ) {
				$col_name = strtok( $col_def, ' ' );
				$exists   = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $table . '` LIKE %s', $col_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( empty( $exists ) ) {
					$wpdb->query( "ALTER TABLE `$table` ADD COLUMN $col_def" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
		}
	}

	/**
	 * Portal / cartable tables (schema 1.2.0).
	 *
	 * @param string $p Table prefix.
	 */
	private static function ensure_portal_tables( $p ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$tables = array(
			"CREATE TABLE {$p}requests (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				type varchar(40) NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				status varchar(30) NOT NULL DEFAULT 'draft',
				payload_json longtext DEFAULT NULL,
				manager_id bigint(20) unsigned DEFAULT 0,
				hr_user_id bigint(20) unsigned DEFAULT 0,
				ref_id bigint(20) unsigned DEFAULT 0,
				notes text DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY status (status),
				KEY manager_id (manager_id),
				KEY ref_id (ref_id)
			) $charset;",
			"CREATE TABLE {$p}dependents (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				full_name varchar(191) NOT NULL,
				relation varchar(60) DEFAULT NULL,
				national_id varchar(20) DEFAULT NULL,
				birth_date date DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id)
			) $charset;",
			"CREATE TABLE {$p}assets (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				asset_type varchar(40) NOT NULL DEFAULT 'other',
				serial_number varchar(120) DEFAULT NULL,
				issued_at date DEFAULT NULL,
				returned_at date DEFAULT NULL,
				status varchar(30) NOT NULL DEFAULT 'assigned',
				notes text DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id)
			) $charset;",
			"CREATE TABLE {$p}notices (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL,
				body longtext DEFAULT NULL,
				date_from date DEFAULT NULL,
				date_to date DEFAULT NULL,
				attachment_id bigint(20) unsigned DEFAULT 0,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_by bigint(20) unsigned DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) $charset;",
			"CREATE TABLE {$p}documents (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				doc_type varchar(40) NOT NULL,
				title varchar(255) NOT NULL,
				body_html longtext DEFAULT NULL,
				attachment_id bigint(20) unsigned DEFAULT 0,
				request_id bigint(20) unsigned DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY request_id (request_id)
			) $charset;",
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Moadian / Hesabfa accounting tables.
	 */
	private static function ensure_accounting_extras() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'webinocrm_accounting_';

		$tables = array(
			"CREATE TABLE {$p}moadian_jobs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				invoice_id bigint(20) unsigned NOT NULL,
				action varchar(40) NOT NULL DEFAULT 'send',
				status varchar(30) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				uid varchar(64) DEFAULT NULL,
				reference_number varchar(64) DEFAULT NULL,
				last_error text DEFAULT NULL,
				run_after datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY invoice_id (invoice_id),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$p}moadian_log (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				invoice_id bigint(20) unsigned DEFAULT NULL,
				job_id bigint(20) unsigned DEFAULT NULL,
				direction varchar(10) NOT NULL DEFAULT 'out',
				endpoint varchar(191) DEFAULT NULL,
				http_code int(11) DEFAULT NULL,
				payload longtext DEFAULT NULL,
				response longtext DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY invoice_id (invoice_id)
			) $charset;",
			"CREATE TABLE {$p}hesabfa_map (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				entity_type varchar(40) NOT NULL,
				local_id bigint(20) unsigned NOT NULL,
				remote_id bigint(20) unsigned DEFAULT NULL,
				remote_code varchar(100) DEFAULT NULL,
				content_hash varchar(64) DEFAULT NULL,
				remote_updated_at datetime DEFAULT NULL,
				last_synced_at datetime DEFAULT NULL,
				last_direction varchar(10) DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY entity_local (entity_type, local_id),
				KEY remote_id (remote_id)
			) $charset;",
			"CREATE TABLE {$p}hesabfa_jobs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				action varchar(40) NOT NULL DEFAULT 'push',
				entity_type varchar(40) DEFAULT NULL,
				local_id bigint(20) unsigned DEFAULT NULL,
				remote_id bigint(20) unsigned DEFAULT NULL,
				remote_code varchar(100) DEFAULT NULL,
				status varchar(30) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				payload longtext DEFAULT NULL,
				last_error text DEFAULT NULL,
				run_after datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$p}hesabfa_log (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				method varchar(191) DEFAULT NULL,
				direction varchar(10) DEFAULT 'out',
				http_code int(11) DEFAULT NULL,
				payload longtext DEFAULT NULL,
				response longtext DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) $charset;",
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		$inv = $p . 'invoices';
		$inv_cols = array(
			'inty tinyint(1) DEFAULT 2',
			'inp tinyint(1) DEFAULT 1',
			'ins tinyint(1) DEFAULT 1',
			'taxid varchar(32) DEFAULT NULL',
			'reference_uid varchar(64) DEFAULT NULL',
			'moadian_status varchar(30) NOT NULL DEFAULT \'none\'',
			'buyer_json longtext DEFAULT NULL',
			'correction_of_id bigint(20) unsigned DEFAULT NULL',
			'subtotal decimal(18,2) DEFAULT 0',
			'tax decimal(18,2) DEFAULT 0',
			'total decimal(18,2) DEFAULT 0',
		);
		foreach ( $inv_cols as $col_def ) {
			$col_name = strtok( $col_def, ' ' );
			$exists   = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$inv` LIKE %s", $col_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( empty( $exists ) ) {
				$wpdb->query( "ALTER TABLE `$inv` ADD COLUMN $col_def" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		$line = $p . 'invoice_lines';
		$line_cols = array(
			'sstid varchar(20) DEFAULT NULL',
			'vat_rate decimal(5,2) DEFAULT 0',
			'vat_amount decimal(18,2) DEFAULT 0',
			'line_total decimal(18,2) DEFAULT 0',
		);
		foreach ( $line_cols as $col_def ) {
			$col_name = strtok( $col_def, ' ' );
			$exists   = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$line` LIKE %s", $col_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( empty( $exists ) ) {
				$wpdb->query( "ALTER TABLE `$line` ADD COLUMN $col_def" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}
}
