<?php
/**
 * Rahn-percent CRM service (quotes, statements, public share, contract lock).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service layer for sales/rahn-percent module.
 */
class WebinoCRM_Rahn_Service {

	const QUOTES_TABLE     = 'webinocrm_rahn_quotes';
	const STATEMENTS_TABLE = 'webinocrm_rahn_statements';
	const SCHEMA_OPTION    = 'webinocrm_rahn_schema_version';
	const SCHEMA_VERSION   = 1;

	/**
	 * Ensure calculator + settings classes are loaded.
	 *
	 * @return void
	 */
	private static function boot() {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! class_exists( 'WebinoCRM_Rahn_Calculator' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-rahn-calculator.php';
		}
		if ( ! class_exists( 'WebinoCRM_Rahn_Settings' ) ) {
			require_once WEBINOCRM_PLUGIN_DIR . 'includes/services/class-rahn-settings.php';
		}
		self::maybe_install_tables();
	}

	/**
	 * @return bool
	 */
	private static function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( class_exists( 'WebinoCRM_REST_Base' ) && WebinoCRM_REST_Base::can_access_route( 'rahn-percent' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Create tables if missing (activation + lazy upgrade).
	 *
	 * @return void
	 */
	public static function maybe_install_tables() {
		$ver = (int) get_option( self::SCHEMA_OPTION, 0 );
		if ( $ver >= self::SCHEMA_VERSION ) {
			global $wpdb;
			$table = $wpdb->prefix . self::QUOTES_TABLE;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists === $table ) {
				return;
			}
		}
		self::install_tables();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$quotes  = $wpdb->prefix . self::QUOTES_TABLE;
		$stmts   = $wpdb->prefix . self::STATEMENTS_TABLE;

		$sql_quotes = "CREATE TABLE $quotes (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			token varchar(64) NOT NULL,
			title varchar(255) DEFAULT '',
			status varchar(20) DEFAULT 'draft',
			customer_id bigint(20) DEFAULT 0,
			lead_id bigint(20) DEFAULT 0,
			contract_id bigint(20) DEFAULT 0,
			selected_ids longtext,
			items_snapshot longtext,
			calc_snapshot longtext,
			s_hat double DEFAULT 0,
			duration int(11) DEFAULT 6,
			mode varchar(20) DEFAULT 'from_p',
			p_wanted double DEFAULT 0,
			f_wanted double DEFAULT 0,
			F double DEFAULT 0,
			p double DEFAULT 0,
			locked_at datetime DEFAULT NULL,
			clause text,
			created_by bigint(20) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY status (status),
			KEY customer_id (customer_id),
			KEY contract_id (contract_id)
		) $charset;";

		$sql_stmts = "CREATE TABLE $stmts (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			contract_id bigint(20) NOT NULL,
			quote_id bigint(20) DEFAULT 0,
			year_month varchar(7) NOT NULL,
			G double DEFAULT 0,
			R double DEFAULT 0,
			D double DEFAULT 0,
			X double DEFAULT 0,
			S double DEFAULT 0,
			F double DEFAULT 0,
			p double DEFAULT 0,
			V double DEFAULT 0,
			C double DEFAULT NULL,
			Pi double DEFAULT NULL,
			invoice_id bigint(20) DEFAULT 0,
			notes text,
			created_by bigint(20) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY contract_month (contract_id, year_month),
			KEY invoice_id (invoice_id)
		) $charset;";

		dbDelta( $sql_quotes );
		dbDelta( $sql_stmts );
	}

	/**
	 * GET settings + catalog.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function settings_get( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'settings' => WebinoCRM_Rahn_Settings::get(),
			)
		);
	}

	/**
	 * POST settings + catalog.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function settings_save( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$payload = $params;
		if ( isset( $params['settings'] ) && is_array( $params['settings'] ) ) {
			$payload = $params['settings'];
		}
		// JSON body may nest catalog as JSON string.
		if ( isset( $payload['catalog'] ) && is_string( $payload['catalog'] ) ) {
			$decoded = json_decode( wp_unslash( $payload['catalog'] ), true );
			if ( is_array( $decoded ) ) {
				$payload['catalog'] = $decoded;
			}
		}
		if ( isset( $payload['review'] ) && is_string( $payload['review'] ) ) {
			$decoded = json_decode( wp_unslash( $payload['review'] ), true );
			if ( is_array( $decoded ) ) {
				$payload['review'] = $decoded;
			}
		}
		if ( isset( $payload['sales_definition'] ) && is_string( $payload['sales_definition'] ) ) {
			$decoded = json_decode( wp_unslash( $payload['sales_definition'] ), true );
			if ( is_array( $decoded ) ) {
				$payload['sales_definition'] = $decoded;
			}
		}

		$saved = WebinoCRM_Rahn_Settings::save( $payload );
		return WebinoCRM_Service_Base::success(
			array(
				'settings' => $saved,
				'message'  => __( 'تنظیمات رهن‌درصد ذخیره شد.', 'webinocrm' ),
			)
		);
	}

	/**
	 * Live quote calculation (admin).
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function calculate( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$settings = WebinoCRM_Rahn_Settings::get();
		$result   = self::run_calc( $params, $settings, true );

		return WebinoCRM_Service_Base::success( $result );
	}

	/**
	 * @param array<string,mixed> $params   Request.
	 * @param array<string,mixed> $settings Settings.
	 * @param bool                $internal Include cost internals.
	 * @return array<string,mixed>
	 */
	private static function run_calc( array $params, array $settings, $internal ) {
		$selected = self::parse_selected_ids( $params );
		$override = null;
		if ( ! empty( $params['items_snapshot'] ) ) {
			$snap = $params['items_snapshot'];
			if ( is_string( $snap ) ) {
				$snap = json_decode( wp_unslash( $snap ), true );
			}
			if ( is_array( $snap ) ) {
				$override = $snap;
			}
		}
		$items = WebinoCRM_Rahn_Settings::resolve_items( $selected, $override );

		$T     = isset( $params['T'] ) ? (int) $params['T'] : (int) $settings['T'];
		$s_hat = isset( $params['s_hat'] ) ? (float) $params['s_hat'] : (float) $settings['s_hat_default'];
		$mode  = sanitize_key( (string) ( $params['mode'] ?? 'from_p' ) );

		$lock_args = array(
			'items'    => $items,
			'T'        => $T,
			'm'        => (float) $settings['m'],
			'k'        => (float) $settings['k'],
			's_hat'    => $s_hat,
			'p_min'    => (float) $settings['p_min'],
			'p_max'    => (float) $settings['p_max'],
			'mode'     => $mode,
			'p_wanted' => isset( $params['p_wanted'] ) ? (float) $params['p_wanted'] : (float) $settings['p_default'],
			'F_wanted' => isset( $params['F_wanted'] ) ? (float) $params['F_wanted'] : 0.0,
		);

		// Accept p as percent (0-100) from UI when p_percent is sent.
		if ( isset( $params['p_percent'] ) ) {
			$lock_args['p_wanted'] = ( (float) $params['p_percent'] ) / 100.0;
		}

		$lock   = WebinoCRM_Rahn_Calculator::lock_contract( $lock_args );
		$clause = WebinoCRM_Rahn_Calculator::render_clause(
			(string) $settings['clause_template'],
			array(
				'F'     => $lock['F'],
				'p'     => $lock['p'],
				'S_hat' => $lock['S_hat'],
				'T'     => $lock['T'],
				'alpha' => $lock['alpha'],
			)
		);

		$public = WebinoCRM_Rahn_Calculator::public_payload( $lock, $items, $clause );

		$out = array(
			'selected_ids' => $selected,
			'items'        => $items,
			'clause'       => $clause,
			'public'       => $public,
			'lock'         => $internal ? $lock : null,
		);

		if ( $internal ) {
			$out['internal'] = array(
				'C'      => $lock['C'],
				'V_star' => $lock['V_star'],
				'F_min'  => $lock['F_min'],
				'S_BE'   => $lock['S_BE'],
				'm'      => $lock['m'],
				'k'      => $lock['k'],
				'breakdown' => $lock['breakdown'],
			);
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<int,string>
	 */
	private static function parse_selected_ids( array $params ) {
		$raw = $params['selected_ids'] ?? $params['service_ids'] ?? array();
		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			} else {
				$raw = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
			}
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( $raw as $id ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * List quotes.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function quotes_list( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::QUOTES_TABLE;
		$paged = max( 1, (int) ( $params['paged'] ?? 1 ) );
		$per   = 20;
		$offset = ( $paged - 1 ) * $per;
		$status = sanitize_key( (string) ( $params['status'] ?? '' ) );

		$where = '1=1';
		$args  = array();
		if ( '' !== $status ) {
			$where .= ' AND status = %s';
			$args[] = $status;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
		$total     = $args
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) )
			: (int) $wpdb->get_var( $count_sql );

		$list_sql = "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $args, array( $per, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ), ARRAY_A );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_quote_row( $row );
		}

		return WebinoCRM_Service_Base::success(
			array(
				'quotes'       => $items,
				'total_pages'  => max( 1, (int) ceil( $total / $per ) ),
				'current_page' => $paged,
				'total'        => $total,
			)
		);
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_quote_row( array $row ) {
		$share_url = home_url( '/rahn/' . $row['token'] . '/' );
		return array(
			'id'           => (int) $row['id'],
			'token'        => (string) $row['token'],
			'title'        => (string) $row['title'],
			'status'       => (string) $row['status'],
			'customer_id'  => (int) $row['customer_id'],
			'lead_id'      => (int) $row['lead_id'],
			'contract_id'  => (int) $row['contract_id'],
			'F'            => (float) $row['F'],
			'p'            => (float) $row['p'],
			'p_percent'    => (float) $row['p'] * 100.0,
			's_hat'        => (float) $row['s_hat'],
			'duration'     => (int) $row['duration'],
			'locked_at'    => $row['locked_at'],
			'clause'       => (string) $row['clause'],
			'share_url'    => $share_url,
			'created_at'   => (string) $row['created_at'],
			'updated_at'   => (string) $row['updated_at'],
			'selected_ids' => self::decode_json_list( $row['selected_ids'] ?? '' ),
		);
	}

	/**
	 * @param string $json JSON.
	 * @return array<int,mixed>
	 */
	private static function decode_json_list( $json ) {
		if ( ! is_string( $json ) || '' === $json ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Create or update a draft quote.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function quote_save( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		global $wpdb;
		$table    = $wpdb->prefix . self::QUOTES_TABLE;
		$settings = WebinoCRM_Rahn_Settings::get();
		$calc     = self::run_calc( $params, $settings, true );
		$lock     = $calc['lock'];

		$quote_id = (int) ( $params['id'] ?? $params['quote_id'] ?? 0 );
		$now      = current_time( 'mysql' );
		$title    = sanitize_text_field( (string) ( $params['title'] ?? '' ) );
		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %s: money amount */
				__( 'پیش‌نویس رهن‌درصد — %s', 'webinocrm' ),
				WebinoCRM_Rahn_Calculator::format_money( (float) $lock['F'] )
			);
		}

		$data = array(
			'title'          => $title,
			'customer_id'    => (int) ( $params['customer_id'] ?? 0 ),
			'lead_id'        => (int) ( $params['lead_id'] ?? 0 ),
			'selected_ids'   => wp_json_encode( $calc['selected_ids'] ),
			'items_snapshot' => wp_json_encode( $calc['items'] ),
			'calc_snapshot'  => wp_json_encode( $lock ),
			's_hat'          => (float) $lock['S_hat'],
			'duration'       => (int) $lock['T'],
			'mode'           => (string) $lock['mode'],
			'p_wanted'       => (float) ( $params['p_wanted'] ?? $lock['p'] ),
			'f_wanted'       => (float) ( $params['F_wanted'] ?? $lock['F'] ),
			'F'              => (float) $lock['F'],
			'p'              => (float) $lock['p'],
			'clause'         => (string) $calc['clause'],
			'updated_at'     => $now,
		);

		if ( $quote_id > 0 ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );
			if ( ! $existing ) {
				return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
			}
			if ( 'locked' === $existing['status'] || 'contracted' === $existing['status'] ) {
				return WebinoCRM_Service_Base::error( __( 'این پیش‌نویس قفل شده و قابل ویرایش نیست.', 'webinocrm' ), 400 );
			}
			$wpdb->update( $table, $data, array( 'id' => $quote_id ) );
		} else {
			$token = self::generate_token();
			$data['token']      = $token;
			$data['status']     = 'draft';
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$quote_id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );
		return WebinoCRM_Service_Base::success(
			array(
				'quote'      => self::format_quote_row( $row ),
				'calculation'=> $calc,
				'message'    => __( 'پیش‌نویس ذخیره شد.', 'webinocrm' ),
			)
		);
	}

	/**
	 * @return string
	 */
	private static function generate_token() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Lock F and p on a quote.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function quote_lock( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		global $wpdb;
		$table    = $wpdb->prefix . self::QUOTES_TABLE;
		$quote_id = (int) ( $params['id'] ?? $params['quote_id'] ?? 0 );
		if ( $quote_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه پیش‌نویس نامعتبر است.', 'webinocrm' ), 400 );
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
		}

		$settings = WebinoCRM_Rahn_Settings::get();
		$calc_params = array(
			'selected_ids'   => self::decode_json_list( $row['selected_ids'] ),
			'items_snapshot' => self::decode_json_list( $row['items_snapshot'] ),
			'T'              => (int) ( $params['T'] ?? $row['duration'] ),
			's_hat'          => (float) ( $params['s_hat'] ?? $row['s_hat'] ),
			'mode'           => sanitize_key( (string) ( $params['mode'] ?? $row['mode'] ) ),
			'p_wanted'       => (float) ( $params['p_wanted'] ?? $row['p_wanted'] ),
			'F_wanted'       => (float) ( $params['F_wanted'] ?? $row['f_wanted'] ),
		);
		if ( isset( $params['p_percent'] ) ) {
			$calc_params['p_percent'] = (float) $params['p_percent'];
		}

		$calc = self::run_calc( $calc_params, $settings, true );
		$lock = $calc['lock'];
		$now  = current_time( 'mysql' );

		$wpdb->update(
			$table,
			array(
				'status'         => 'locked',
				'selected_ids'   => wp_json_encode( $calc['selected_ids'] ),
				'items_snapshot' => wp_json_encode( $calc['items'] ),
				'calc_snapshot'  => wp_json_encode( $lock ),
				's_hat'          => (float) $lock['S_hat'],
				'duration'       => (int) $lock['T'],
				'mode'           => (string) $lock['mode'],
				'F'              => (float) $lock['F'],
				'p'              => (float) $lock['p'],
				'clause'         => (string) $calc['clause'],
				'locked_at'      => $now,
				'updated_at'     => $now,
			),
			array( 'id' => $quote_id )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );
		return WebinoCRM_Service_Base::success(
			array(
				'quote'       => self::format_quote_row( $row ),
				'calculation' => $calc,
				'message'     => __( 'ثابت و درصد قفل شدند.', 'webinocrm' ),
			)
		);
	}

	/**
	 * Create CRM contract from locked quote.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function quote_to_contract( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		global $wpdb;
		$table    = $wpdb->prefix . self::QUOTES_TABLE;
		$quote_id = (int) ( $params['id'] ?? $params['quote_id'] ?? 0 );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( 'locked' !== $row['status'] && 'contracted' !== $row['status'] ) {
			return WebinoCRM_Service_Base::error( __( 'ابتدا ثابت و درصد را قفل کنید.', 'webinocrm' ), 400 );
		}

		$customer_id = (int) ( $params['customer_id'] ?? $row['customer_id'] );
		if ( $customer_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'انتخاب مشتری الزامی است.', 'webinocrm' ), 400 );
		}

		$start = sanitize_text_field( (string) ( $params['start_date'] ?? '' ) );
		if ( '' === $start ) {
			$start = gmdate( 'Y-m-d' );
		}
		if ( ! preg_match( '/^\d{4}-\d{1,2}-\d{1,2}$/', $start ) && function_exists( 'webino_jalali_to_gregorian' ) ) {
			$start = webino_jalali_to_gregorian( $start );
		}

		$title = sanitize_text_field( (string) ( $params['contract_title'] ?? $row['title'] ) );
		if ( '' === $title ) {
			$title = __( 'قرارداد رهن‌درصد', 'webinocrm' );
		}

		$F = (float) $row['F'];
		$p = (float) $row['p'];
		$T = (int) $row['duration'];
		$months_label = $T . '-months';
		if ( 1 === $T ) {
			$months_label = '1-month';
		} elseif ( 3 === $T ) {
			$months_label = '3-months';
		} elseif ( 6 === $T ) {
			$months_label = '6-months';
		} elseif ( 12 === $T ) {
			$months_label = '12-months';
		}

		$contract_id = (int) $row['contract_id'];
		if ( $contract_id <= 0 ) {
			$contract_id = wp_insert_post(
				array(
					'post_type'   => 'contract',
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => $customer_id,
				),
				true
			);
			if ( is_wp_error( $contract_id ) ) {
				return WebinoCRM_Service_Base::error( $contract_id->get_error_message(), 500 );
			}
		}

		$contract_number = get_post_meta( $contract_id, '_contract_number', true );
		if ( ! $contract_number ) {
			$contract_number = 'RAHN-' . $contract_id . '-' . gmdate( 'ymd' );
			update_post_meta( $contract_id, '_contract_number', $contract_number );
		}

		update_post_meta( $contract_id, '_total_amount', (string) (int) round( $F * max( 1, $T ) ) );
		update_post_meta( $contract_id, '_total_installments', max( 1, $T ) );
		update_post_meta( $contract_id, '_project_start_date', $start );
		update_post_meta( $contract_id, '_project_contract_duration', $months_label );
		update_post_meta( $contract_id, '_project_subscription_model', 'rahn_percent' );
		update_post_meta( $contract_id, '_customer_id', $customer_id );
		update_post_meta( $contract_id, '_rahn_fixed', $F );
		update_post_meta( $contract_id, '_rahn_percent', $p );
		update_post_meta( $contract_id, '_rahn_locked_at', $row['locked_at'] ? $row['locked_at'] : current_time( 'mysql' ) );
		update_post_meta( $contract_id, '_rahn_quote_id', $quote_id );
		update_post_meta( $contract_id, '_rahn_cost_snapshot', $row['calc_snapshot'] );
		update_post_meta( $contract_id, '_rahn_s_hat', (float) $row['s_hat'] );
		update_post_meta( $contract_id, '_rahn_duration', $T );
		update_post_meta( $contract_id, '_rahn_clause', (string) $row['clause'] );

		// Monthly fixed installments as baseline schedule (percent billed separately each month).
		$installments = array();
		$base_ts      = strtotime( $start . ' 12:00:00' );
		for ( $i = 0; $i < max( 1, $T ); $i++ ) {
			$due = gmdate( 'Y-m-d', strtotime( '+' . $i . ' months', $base_ts ) );
			$installments[] = array(
				'amount'   => (string) (int) round( $F ),
				'due_date' => $due,
				'status'   => 'pending',
				'note'     => __( 'ثابت ماهانه رهن‌درصد', 'webinocrm' ),
			);
		}
		update_post_meta( $contract_id, '_installments', $installments );

		$wpdb->update(
			$table,
			array(
				'status'      => 'contracted',
				'contract_id' => $contract_id,
				'customer_id' => $customer_id,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $quote_id )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );

		return WebinoCRM_Service_Base::success(
			array(
				'quote'       => self::format_quote_row( $row ),
				'contract_id' => $contract_id,
				'message'     => __( 'قرارداد رهن‌درصد ایجاد شد.', 'webinocrm' ),
			)
		);
	}

	/**
	 * Delete draft quote.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function quote_delete( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		global $wpdb;
		$table    = $wpdb->prefix . self::QUOTES_TABLE;
		$quote_id = (int) ( $params['id'] ?? $params['quote_id'] ?? 0 );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $quote_id ), ARRAY_A );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( 'contracted' === $row['status'] ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس تبدیل‌شده به قرارداد قابل حذف نیست.', 'webinocrm' ), 400 );
		}
		$wpdb->delete( $table, array( 'id' => $quote_id ), array( '%d' ) );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'پیش‌نویس حذف شد.', 'webinocrm' ) ) );
	}

	/**
	 * Public GET by token.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function public_get( array $params ) {
		self::boot();
		$token = sanitize_text_field( (string) ( $params['token'] ?? '' ) );
		if ( '' === $token ) {
			return WebinoCRM_Service_Base::error( __( 'لینک نامعتبر است.', 'webinocrm' ), 404 );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::QUOTES_TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token = %s", $token ), ARRAY_A );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
		}

		$settings = WebinoCRM_Rahn_Settings::get();
		$calc     = self::run_calc(
			array(
				'selected_ids'   => self::decode_json_list( $row['selected_ids'] ),
				'items_snapshot' => self::decode_json_list( $row['items_snapshot'] ),
				'T'              => (int) $row['duration'],
				's_hat'          => (float) $row['s_hat'],
				'mode'           => (string) $row['mode'],
				'p_wanted'       => (float) $row['p'],
				'F_wanted'       => (float) $row['F'],
			),
			$settings,
			false
		);

		$catalog_public = array();
		foreach ( (array) $settings['catalog'] as $item ) {
			if ( empty( $item['active'] ) ) {
				continue;
			}
			$catalog_public[] = array(
				'id'              => $item['id'],
				'name'            => $item['name'],
				'billing'         => $item['billing'],
				'period_months'   => $item['period_months'],
				'renewable'       => ! empty( $item['renewable'] ),
				'category'        => $item['category'],
				'description'     => $item['description'],
				'default_selected'=> ! empty( $item['default_selected'] ),
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'token'        => $token,
				'title'        => (string) $row['title'],
				'status'       => (string) $row['status'],
				'locked'       => in_array( $row['status'], array( 'locked', 'contracted' ), true ),
				'selected_ids' => self::decode_json_list( $row['selected_ids'] ),
				'catalog'      => $catalog_public,
				'public'       => $calc['public'],
				'p_min'        => (float) $settings['p_min'],
				'p_max'        => (float) $settings['p_max'],
				's_hat'        => (float) $row['s_hat'],
				'T'            => (int) $row['duration'],
				'site_name'    => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Public recalculate (negotiation) — no internals.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function public_calculate( array $params ) {
		self::boot();
		$token = sanitize_text_field( (string) ( $params['token'] ?? '' ) );
		global $wpdb;
		$table = $wpdb->prefix . self::QUOTES_TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token = %s", $token ), ARRAY_A );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( in_array( $row['status'], array( 'locked', 'contracted' ), true ) ) {
			// Locked: return frozen values only.
			return self::public_get( $params );
		}

		$settings = WebinoCRM_Rahn_Settings::get();
		$selected = self::parse_selected_ids( $params );
		if ( empty( $selected ) ) {
			$selected = self::decode_json_list( $row['selected_ids'] );
		}

		$calc = self::run_calc(
			array(
				'selected_ids' => $selected,
				'T'            => (int) ( $params['T'] ?? $row['duration'] ),
				's_hat'        => (float) ( $params['s_hat'] ?? $row['s_hat'] ),
				'mode'         => sanitize_key( (string) ( $params['mode'] ?? 'from_p' ) ),
				'p_wanted'     => (float) ( $params['p_wanted'] ?? $row['p_wanted'] ),
				'F_wanted'     => (float) ( $params['F_wanted'] ?? $row['f_wanted'] ),
				'p_percent'    => $params['p_percent'] ?? null,
			),
			$settings,
			false
		);

		return WebinoCRM_Service_Base::success(
			array(
				'public'       => $calc['public'],
				'selected_ids' => $selected,
				'clause'       => $calc['clause'],
			)
		);
	}

	/**
	 * Public submit interest → create lead + update quote.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function public_submit( array $params ) {
		self::boot();
		$token = sanitize_text_field( (string) ( $params['token'] ?? '' ) );
		global $wpdb;
		$table = $wpdb->prefix . self::QUOTES_TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token = %s", $token ), ARRAY_A );
		if ( ! $row ) {
			return WebinoCRM_Service_Base::error( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ), 404 );
		}

		$name  = sanitize_text_field( (string) ( $params['name'] ?? '' ) );
		$phone = sanitize_text_field( (string) ( $params['phone'] ?? '' ) );
		$email = sanitize_email( (string) ( $params['email'] ?? '' ) );
		$note  = sanitize_textarea_field( (string) ( $params['note'] ?? '' ) );

		if ( '' === $name || ( '' === $phone && '' === $email ) ) {
			return WebinoCRM_Service_Base::error( __( 'نام و شماره تماس یا ایمیل الزامی است.', 'webinocrm' ), 400 );
		}

		$settings = WebinoCRM_Rahn_Settings::get();
		$selected = self::parse_selected_ids( $params );
		if ( empty( $selected ) ) {
			$selected = self::decode_json_list( $row['selected_ids'] );
		}

		$calc = self::run_calc(
			array(
				'selected_ids' => $selected,
				'T'            => (int) ( $params['T'] ?? $row['duration'] ),
				's_hat'        => (float) ( $params['s_hat'] ?? $row['s_hat'] ),
				'mode'         => sanitize_key( (string) ( $params['mode'] ?? $row['mode'] ) ),
				'p_wanted'     => (float) ( $params['p_wanted'] ?? $row['p'] ),
				'F_wanted'     => (float) ( $params['F_wanted'] ?? $row['F'] ),
				'p_percent'    => $params['p_percent'] ?? null,
			),
			$settings,
			true
		);
		$lock = $calc['lock'];

		$lead_content = sprintf(
			"درخواست رهن‌درصد\nنام: %s\nتلفن: %s\nایمیل: %s\nثابت: %s\nدرصد: %s\n%s\n%s",
			$name,
			$phone,
			$email,
			WebinoCRM_Rahn_Calculator::format_money( (float) $lock['F'] ),
			WebinoCRM_Rahn_Calculator::format_percent( (float) $lock['p'] ) . '%',
			(string) $calc['clause'],
			$note
		);

		$lead_id = wp_insert_post(
			array(
				'post_type'    => 'lead',
				'post_title'   => sprintf( __( 'لید رهن‌درصد — %s', 'webinocrm' ), $name ),
				'post_content' => $lead_content,
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $lead_id ) ) {
			return WebinoCRM_Service_Base::error( $lead_id->get_error_message(), 500 );
		}

		update_post_meta( $lead_id, '_lead_name', $name );
		update_post_meta( $lead_id, '_lead_phone', $phone );
		update_post_meta( $lead_id, '_lead_email', $email );
		update_post_meta( $lead_id, '_lead_source', 'rahn_percent' );
		update_post_meta( $lead_id, '_rahn_quote_id', (int) $row['id'] );
		update_post_meta( $lead_id, '_rahn_F', (float) $lock['F'] );
		update_post_meta( $lead_id, '_rahn_p', (float) $lock['p'] );

		$wpdb->update(
			$table,
			array(
				'lead_id'        => (int) $lead_id,
				'selected_ids'   => wp_json_encode( $calc['selected_ids'] ),
				'items_snapshot' => wp_json_encode( $calc['items'] ),
				'calc_snapshot'  => wp_json_encode( $lock ),
				's_hat'          => (float) $lock['S_hat'],
				'duration'       => (int) $lock['T'],
				'F'              => (float) $lock['F'],
				'p'              => (float) $lock['p'],
				'clause'         => (string) $calc['clause'],
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => (int) $row['id'] )
		);

		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.', 'webinocrm' ),
				'lead_id' => (int) $lead_id,
				'public'  => $calc['public'],
			)
		);
	}

	/**
	 * List contracts that use rahn_percent model.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function contracts_list( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'contract',
				'posts_per_page' => 50,
				'meta_key'       => '_project_subscription_model',
				'meta_value'     => 'rahn_percent',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'customer_id'    => (int) $post->post_author,
				'customer_name'  => ( get_userdata( (int) $post->post_author ) )->display_name ?? '',
				'F'              => (float) get_post_meta( $post->ID, '_rahn_fixed', true ),
				'p'              => (float) get_post_meta( $post->ID, '_rahn_percent', true ),
				'p_percent'      => (float) get_post_meta( $post->ID, '_rahn_percent', true ) * 100.0,
				'clause'         => (string) get_post_meta( $post->ID, '_rahn_clause', true ),
				'duration'       => (int) get_post_meta( $post->ID, '_rahn_duration', true ),
				's_hat'          => (float) get_post_meta( $post->ID, '_rahn_s_hat', true ),
				'locked_at'      => (string) get_post_meta( $post->ID, '_rahn_locked_at', true ),
			);
		}

		return WebinoCRM_Service_Base::success( array( 'contracts' => $items ) );
	}

	/**
	 * Monthly statement calculate + optionally persist / create invoice.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function statement_calculate( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$contract_id = (int) ( $params['contract_id'] ?? 0 );
		$post        = get_post( $contract_id );
		if ( ! $post || 'contract' !== $post->post_type ) {
			return WebinoCRM_Service_Base::error( __( 'قرارداد یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( 'rahn_percent' !== get_post_meta( $contract_id, '_project_subscription_model', true ) ) {
			return WebinoCRM_Service_Base::error( __( 'این قرارداد مدل رهن‌درصد نیست.', 'webinocrm' ), 400 );
		}

		$F = (float) get_post_meta( $contract_id, '_rahn_fixed', true );
		$p = (float) get_post_meta( $contract_id, '_rahn_percent', true );
		$C = null;
		$snap = get_post_meta( $contract_id, '_rahn_cost_snapshot', true );
		if ( is_string( $snap ) && $snap ) {
			$decoded = json_decode( $snap, true );
			if ( is_array( $decoded ) && isset( $decoded['C'] ) ) {
				$C = (float) $decoded['C'];
			}
		}

		$settings = WebinoCRM_Rahn_Settings::get();
		$sales    = array(
			'G' => (float) ( $params['G'] ?? 0 ),
			'R' => (float) ( $params['R'] ?? 0 ),
			'D' => (float) ( $params['D'] ?? 0 ),
			'X' => (float) ( $params['X'] ?? 0 ),
		);
		foreach ( array( 'G', 'R', 'D', 'X' ) as $key ) {
			if ( empty( $settings['sales_definition'][ $key ]['enabled'] ) ) {
				$sales[ $key ] = 0.0;
			}
		}

		$bill = WebinoCRM_Rahn_Calculator::monthly_bill( $F, $p, $sales, $C );

		$year_month = sanitize_text_field( (string) ( $params['year_month'] ?? gmdate( 'Y-m' ) ) );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $year_month ) ) {
			$year_month = gmdate( 'Y-m' );
		}

		$review_alert = self::check_review_alert( $contract_id, $bill, $settings );

		return WebinoCRM_Service_Base::success(
			array(
				'bill'         => $bill,
				'year_month'   => $year_month,
				'contract_id'  => $contract_id,
				'clause'       => (string) get_post_meta( $contract_id, '_rahn_clause', true ),
				'review_alert' => $review_alert,
				'sales_definition' => $settings['sales_definition'],
			)
		);
	}

	/**
	 * Persist monthly statement and optionally create pro_invoice.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function statement_save( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$calc_result = self::statement_calculate( $params );
		if ( empty( $calc_result['success'] ) ) {
			return $calc_result;
		}
		$data = $calc_result['data'];
		$bill = $data['bill'];
		$contract_id = (int) $data['contract_id'];
		$year_month  = (string) $data['year_month'];

		global $wpdb;
		$table = $wpdb->prefix . self::STATEMENTS_TABLE;
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, invoice_id FROM $table WHERE contract_id = %d AND year_month = %s",
				$contract_id,
				$year_month
			),
			ARRAY_A
		);

		$row_data = array(
			'contract_id' => $contract_id,
			'quote_id'    => (int) get_post_meta( $contract_id, '_rahn_quote_id', true ),
			'year_month'  => $year_month,
			'G'           => (float) $bill['G'],
			'R'           => (float) $bill['R'],
			'D'           => (float) $bill['D'],
			'X'           => (float) $bill['X'],
			'S'           => (float) $bill['S'],
			'F'           => (float) $bill['F'],
			'p'           => (float) $bill['p'],
			'V'           => (float) $bill['V'],
			'C'           => isset( $bill['C'] ) ? (float) $bill['C'] : null,
			'Pi'          => isset( $bill['Pi'] ) ? (float) $bill['Pi'] : null,
			'notes'       => sanitize_textarea_field( (string) ( $params['notes'] ?? '' ) ),
			'created_by'  => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		);

		$invoice_id = $existing ? (int) $existing['invoice_id'] : 0;
		$create_invoice = ! empty( $params['create_invoice'] );

		if ( $create_invoice && $invoice_id <= 0 ) {
			$invoice_id = self::create_pro_invoice( $contract_id, $bill, $year_month );
			$row_data['invoice_id'] = $invoice_id;
		} elseif ( $existing ) {
			$row_data['invoice_id'] = $invoice_id;
		}

		if ( $existing ) {
			unset( $row_data['created_at'], $row_data['created_by'] );
			$wpdb->update( $table, $row_data, array( 'id' => (int) $existing['id'] ) );
			$statement_id = (int) $existing['id'];
		} else {
			$wpdb->insert( $table, $row_data );
			$statement_id = (int) $wpdb->insert_id;
		}

		return WebinoCRM_Service_Base::success(
			array(
				'statement_id' => $statement_id,
				'invoice_id'   => $invoice_id,
				'bill'         => $bill,
				'year_month'   => $year_month,
				'review_alert' => $data['review_alert'],
				'message'      => __( 'صورتحساب ماهانه ذخیره شد.', 'webinocrm' ),
			)
		);
	}

	/**
	 * List statements for a contract.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function statements_list( array $params ) {
		self::boot();
		if ( ! self::can_manage() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$contract_id = (int) ( $params['contract_id'] ?? 0 );
		global $wpdb;
		$table = $wpdb->prefix . self::STATEMENTS_TABLE;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE contract_id = %d ORDER BY year_month DESC",
				$contract_id
			),
			ARRAY_A
		);
		return WebinoCRM_Service_Base::success( array( 'statements' => $rows ? $rows : array() ) );
	}

	/**
	 * @param int                 $contract_id Contract.
	 * @param array<string,mixed> $bill        Bill.
	 * @param string              $year_month  YYYY-MM.
	 * @return int Invoice ID.
	 */
	private static function create_pro_invoice( $contract_id, array $bill, $year_month ) {
		$post = get_post( $contract_id );
		$customer_id = $post ? (int) $post->post_author : 0;
		$projects = get_posts(
			array(
				'post_type'      => 'project',
				'posts_per_page' => 1,
				'meta_key'       => '_contract_id',
				'meta_value'     => $contract_id,
			)
		);
		$project_id = $projects ? (int) $projects[0]->ID : 0;

		$invoice_id = wp_insert_post(
			array(
				'post_type'    => 'pro_invoice',
				'post_title'   => sprintf( __( 'صورتحساب رهن‌درصد %s', 'webinocrm' ), $year_month ),
				'post_status'  => 'publish',
				'post_author'  => $customer_id,
				'post_content' => (string) get_post_meta( $contract_id, '_rahn_clause', true ),
			),
			true
		);
		if ( is_wp_error( $invoice_id ) ) {
			return 0;
		}

		$items = array(
			array(
				'title'    => sprintf( __( 'ثابت ماهانه (%s)', 'webinocrm' ), $year_month ),
				'qty'      => 1,
				'price'    => (float) $bill['F'],
				'discount' => 0,
			),
			array(
				'title'    => sprintf(
					__( 'سهم درصد فروش (%s%% از %s)', 'webinocrm' ),
					WebinoCRM_Rahn_Calculator::format_percent( (float) $bill['p'] ),
					WebinoCRM_Rahn_Calculator::format_money( (float) $bill['S'] )
				),
				'qty'      => 1,
				'price'    => (float) $bill['p_share'],
				'discount' => 0,
			),
		);

		update_post_meta( $invoice_id, '_pro_invoice_number', 'rahn-' . $contract_id . '-' . str_replace( '-', '', $year_month ) );
		update_post_meta( $invoice_id, '_project_id', $project_id );
		update_post_meta( $invoice_id, '_contract_id', $contract_id );
		update_post_meta( $invoice_id, '_issue_date', $year_month . '-01' );
		update_post_meta( $invoice_id, '_invoice_items', $items );
		update_post_meta( $invoice_id, '_final_total', (float) $bill['V'] );
		update_post_meta( $invoice_id, '_payment_method', __( 'رهن‌درصد — ماهانه', 'webinocrm' ) );

		return (int) $invoice_id;
	}

	/**
	 * Soft review alert vs locked Ŝ (does not change F/p).
	 *
	 * @param int                 $contract_id Contract.
	 * @param array<string,mixed> $bill        Current bill.
	 * @param array<string,mixed> $settings    Settings.
	 * @return array<string,mixed>|null
	 */
	private static function check_review_alert( $contract_id, array $bill, array $settings ) {
		$review = $settings['review'] ?? array();
		if ( empty( $review['enabled'] ) ) {
			return null;
		}
		$s_hat = (float) get_post_meta( $contract_id, '_rahn_s_hat', true );
		if ( $s_hat <= 0 ) {
			return null;
		}
		$deviation = abs( ( (float) $bill['S'] - $s_hat ) / $s_hat ) * 100.0;
		$threshold = (float) ( $review['deviation_percent'] ?? 25 );
		if ( $deviation < $threshold ) {
			return null;
		}
		return array(
			'message' => sprintf(
				/* translators: 1: deviation percent, 2: threshold */
				__( 'انحراف فروش از مبنای قرارداد حدود %1$s%% است (آستانه %2$s%%). در صورت تداوم، قرارداد جدید پیشنهاد شود — F و p ماه جاری تغییر نمی‌کنند.', 'webinocrm' ),
				WebinoCRM_Rahn_Calculator::format_percent( $deviation / 100.0 ),
				WebinoCRM_Rahn_Calculator::format_percent( $threshold / 100.0 )
			),
			'deviation_percent' => $deviation,
			'threshold'         => $threshold,
		);
	}
}
